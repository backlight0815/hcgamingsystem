<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TradingAppointment;
use App\Models\TradingAppointmentSlot;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradingAppointmentController extends Controller
{
    private const ADMIN_ROLES = [1, 2];
    private const TRADING_ROLES = [201, 202, 501, 502, 750, 760, 770];
    private const HOST_ROLES = [1, 2, 760];

    public function adminIndex(Request $request)
    {
        $this->ensureAdmin();

        $hostId = $request->input('host_id');
        $status = $request->input('status');
        $hosts = $this->availableHosts();
        $calendarMonth = $this->calendarMonth($request);
        $calendarHostIds = $hostId
            ? [(int) $hostId]
            : $hosts->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $appointmentCalendar = $this->buildAppointmentCalendar(
            $calendarMonth,
            $calendarHostIds,
            true,
            auth()->user()
        );

        $slotsQuery = TradingAppointmentSlot::with(['host', 'creator', 'appointment.requester'])
            ->latest('start_at');

        if ($hostId) {
            $slotsQuery->where('host_user_id', $hostId);
        }

        if ($status) {
            $slotsQuery->where('status', $status);
        }

        $slots = $slotsQuery->paginate(12, ['*'], 'slots_page')->withQueryString();
        $pendingRequests = TradingAppointment::with(['requester', 'host'])
            ->where('request_type', TradingAppointment::TYPE_PREFERRED)
            ->where('status', TradingAppointment::STATUS_PENDING)
            ->orderBy('scheduled_start_at')
            ->get();
        $appointments = TradingAppointment::with(['requester', 'host', 'slot'])
            ->latest('scheduled_start_at')
            ->paginate(12, ['*'], 'appointments_page')
            ->withQueryString();
        $metrics = [
            'available' => TradingAppointmentSlot::where('status', TradingAppointmentSlot::STATUS_ACTIVE)->where('start_at', '>=', now())->count(),
            'booked' => TradingAppointment::where('status', TradingAppointment::STATUS_APPROVED)->where('scheduled_start_at', '>=', now())->count(),
            'pending' => TradingAppointment::where('status', TradingAppointment::STATUS_PENDING)->count(),
            'cancelled' => TradingAppointment::where('status', TradingAppointment::STATUS_CANCELLED)->count(),
        ];

        return view('admin.trading_appointments.index', compact(
            'slots',
            'pendingRequests',
            'appointments',
            'hosts',
            'metrics',
            'hostId',
            'status',
            'calendarMonth',
            'appointmentCalendar'
        ));
    }

    public function storeSlot(Request $request)
    {
        $this->ensureAdmin();

        $data = $this->validatedSlotData($request);
        $startAt = Carbon::parse($data['start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $data['duration_minutes']);

        if ($this->hostHasConflict((int) $data['host_user_id'], $startAt, $endAt, null, null, true)) {
            return back()->withErrors(['start_at' => 'This host already has an appointment, pending request, or slot during the selected time.'])->withInput();
        }

        TradingAppointmentSlot::create([
            'host_user_id' => $data['host_user_id'],
            'created_by' => auth()->id(),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => $data['duration_minutes'],
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Appointment slot created successfully.');
    }

    public function updateSlot(Request $request, TradingAppointmentSlot $slot)
    {
        $this->ensureAdmin();
        abort_if($slot->status === TradingAppointmentSlot::STATUS_BOOKED, 403, 'Booked slots cannot be edited.');

        $data = $this->validatedSlotData($request);
        $startAt = Carbon::parse($data['start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $data['duration_minutes']);

        if ($this->hostHasConflict((int) $data['host_user_id'], $startAt, $endAt, $slot->id, null, true)) {
            return back()->withErrors(['start_at' => 'This host already has an appointment, pending request, or slot during the selected time.'])->withInput();
        }

        $slot->update([
            'host_user_id' => $data['host_user_id'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => $data['duration_minutes'],
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Appointment slot updated successfully.');
    }

    public function destroySlot(TradingAppointmentSlot $slot)
    {
        $this->ensureAdmin();
        abort_if($slot->status === TradingAppointmentSlot::STATUS_BOOKED, 403, 'Booked slots cannot be removed.');
        $slot->delete();

        return back()->with('success', 'Appointment slot removed successfully.');
    }

    public function index(Request $request)
    {
        $this->ensureTradingAccess();

        $user = auth()->user();
        $visibleHostIds = $this->visibleHostIdsFor($user);
        $calendarMonth = $this->calendarMonth($request);
        $appointmentCalendar = $this->buildAppointmentCalendar($calendarMonth, $visibleHostIds, false, $user);
        $slots = TradingAppointmentSlot::with('host')
            ->whereIn('host_user_id', $visibleHostIds)
            ->where('host_user_id', '!=', $user->id)
            ->where('status', TradingAppointmentSlot::STATUS_ACTIVE)
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->paginate(12, ['*'], 'slots_page')
            ->withQueryString();
        $myAppointments = TradingAppointment::with(['host', 'requester'])
            ->where(function ($query) use ($user): void {
                $query
                    ->where('requester_id', $user->id)
                    ->orWhere('host_user_id', $user->id);
            })
            ->latest('scheduled_start_at')
            ->paginate(12, ['*'], 'appointments_page')
            ->withQueryString();
        $preferredHosts = $this->availableHosts()
            ->whereIn('id', $visibleHostIds)
            ->reject(fn ($host): bool => (int) $host->id === (int) $user->id)
            ->values();

        return view('trading_appointments.index', compact(
            'slots',
            'myAppointments',
            'preferredHosts',
            'calendarMonth',
            'appointmentCalendar'
        ));
    }

    public function bookSlot(Request $request, TradingAppointmentSlot $slot)
    {
        $this->ensureTradingAccess();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'request_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $appointment = DB::transaction(function () use ($slot, $data) {
            $lockedSlot = TradingAppointmentSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canBookSlot(auth()->user(), $lockedSlot), 403);
            abort_unless($lockedSlot->isBookable(), 409, 'This slot is no longer available.');
            abort_if($lockedSlot->appointment()->whereIn('status', [TradingAppointment::STATUS_PENDING, TradingAppointment::STATUS_APPROVED])->exists(), 409, 'This slot is already booked.');

            $appointment = TradingAppointment::create([
                'appointment_slot_id' => $lockedSlot->id,
                'requester_id' => auth()->id(),
                'host_user_id' => $lockedSlot->host_user_id,
                'request_type' => TradingAppointment::TYPE_SLOT,
                'subject' => $data['subject'],
                'request_note' => $data['request_note'] ?? null,
                'scheduled_start_at' => $lockedSlot->start_at,
                'scheduled_end_at' => $lockedSlot->end_at,
                'duration_minutes' => $lockedSlot->duration_minutes,
                'status' => TradingAppointment::STATUS_APPROVED,
                'reviewed_by' => $lockedSlot->created_by,
                'reviewed_at' => now(),
            ]);

            $lockedSlot->update(['status' => TradingAppointmentSlot::STATUS_BOOKED]);

            return $appointment;
        });

        $this->notifyAppointmentApproved($appointment, 'Appointment booked');

        return back()->with('success', 'Appointment booked successfully.');
    }

    public function storePreferred(Request $request)
    {
        $this->ensureTradingAccess();

        $data = $request->validate([
            'host_user_id' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'preferred_start_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'request_note' => ['required', 'string', 'max:3000'],
        ]);

        $host = User::findOrFail($data['host_user_id']);
        abort_unless($this->canRequestHost(auth()->user(), $host), 403);

        $startAt = Carbon::parse($data['preferred_start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $data['duration_minutes']);

        if ($this->hostHasConflict((int) $host->id, $startAt, $endAt, null, null, true)) {
            return back()
                ->withErrors(['preferred_start_at' => 'This host already has a booked, pending, or unavailable slot during the selected time. Please choose another time from the calendar.'])
                ->withInput();
        }

        $appointment = TradingAppointment::create([
            'requester_id' => auth()->id(),
            'host_user_id' => $host->id,
            'request_type' => TradingAppointment::TYPE_PREFERRED,
            'subject' => $data['subject'],
            'request_note' => $data['request_note'],
            'scheduled_start_at' => $startAt,
            'scheduled_end_at' => $endAt,
            'duration_minutes' => $data['duration_minutes'],
            'status' => TradingAppointment::STATUS_PENDING,
        ]);

        AppNotificationService::notifyAdmins(
            'Preferred appointment needs review',
            auth()->user()->name . ' requested ' . $appointment->scheduled_start_at->format('M d, Y H:i') . ' with ' . ($host->name ?: $host->username) . '.',
            route('admin.trading.appointments.index'),
            'appointment'
        );

        return back()->with('success', 'Preferred appointment submitted for administration review.');
    }

    public function approve(Request $request, TradingAppointment $appointment)
    {
        $this->ensureAdmin();
        abort_unless($appointment->isPending(), 403);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($this->hostHasConflict((int) $appointment->host_user_id, $appointment->scheduled_start_at, $appointment->scheduled_end_at, null, $appointment->id)) {
            return back()->withErrors(['review_note' => 'The host already has another approved appointment or slot during this time.']);
        }

        $appointment->update([
            'status' => TradingAppointment::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? 'Approved by administration.',
        ]);

        $this->notifyAppointmentApproved($appointment, 'Appointment approved');

        return back()->with('success', 'Preferred appointment approved successfully.');
    }

    public function reject(Request $request, TradingAppointment $appointment)
    {
        $this->ensureAdmin();
        abort_unless($appointment->isPending(), 403);

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:3000'],
        ]);

        $appointment->update([
            'status' => TradingAppointment::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        AppNotificationService::notifyUser(
            (int) $appointment->requester_id,
            'Appointment not approved',
            $appointment->subject . ' was not approved. Reason: ' . $data['review_note'],
            route('trading.appointments.index'),
            'appointment'
        );

        return back()->with('success', 'Preferred appointment rejected.');
    }

    public function cancel(Request $request, TradingAppointment $appointment)
    {
        $this->ensureCanAccessAppointment($appointment);

        if (! $appointment->canCancelByPolicy()) {
            return back()->withErrors(['cancellation_reason' => 'Appointments can only be cancelled before the appointment date. The cancellation deadline was ' . $appointment->cancellationDeadlineLabel() . '.']);
        }

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        DB::transaction(function () use ($appointment, $data): void {
            $appointment->update([
                'status' => TradingAppointment::STATUS_CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'],
            ]);

            if ($appointment->slot && $appointment->slot->status === TradingAppointmentSlot::STATUS_BOOKED) {
                $appointment->slot->update(['status' => TradingAppointmentSlot::STATUS_ACTIVE]);
            }
        });

        $this->notifyAppointmentCancelled($appointment, $data['cancellation_reason']);

        return back()->with('success', 'Appointment cancelled successfully.');
    }

    private function calendarMonth(Request $request): Carbon
    {
        $month = $request->input('calendar_month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::parse($month . '-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function buildAppointmentCalendar(Carbon $calendarMonth, array $hostIds, bool $isAdmin, ?User $viewer): array
    {
        $hostIds = array_values(array_unique(array_filter(array_map('intval', $hostIds))));
        $rangeStart = $calendarMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $rangeEnd = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $eventsByDate = [];
        $counts = [
            'available' => 0,
            'booked' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'inactive' => 0,
            'expired' => 0,
        ];

        if ($hostIds) {
            $slotStatuses = $isAdmin
                ? [TradingAppointmentSlot::STATUS_ACTIVE, TradingAppointmentSlot::STATUS_BOOKED, TradingAppointmentSlot::STATUS_INACTIVE]
                : [TradingAppointmentSlot::STATUS_ACTIVE, TradingAppointmentSlot::STATUS_BOOKED];

            TradingAppointmentSlot::with(['host', 'appointment.requester'])
                ->whereIn('host_user_id', $hostIds)
                ->whereIn('status', $slotStatuses)
                ->whereBetween('start_at', [$rangeStart, $rangeEnd])
                ->orderBy('start_at')
                ->get()
                ->each(function (TradingAppointmentSlot $slot) use (&$eventsByDate, &$counts, $isAdmin, $viewer): void {
                    $event = $this->slotCalendarEvent($slot, $isAdmin, $viewer);
                    $eventsByDate[$slot->start_at->format('Y-m-d')][] = $event;
                    $counts[$event['status']]++;
                });

            $appointmentsQuery = TradingAppointment::with(['requester', 'host'])
                ->whereIn('host_user_id', $hostIds)
                ->whereNull('appointment_slot_id')
                ->whereBetween('scheduled_start_at', [$rangeStart, $rangeEnd])
                ->whereIn('status', [
                    TradingAppointment::STATUS_PENDING,
                    TradingAppointment::STATUS_APPROVED,
                    TradingAppointment::STATUS_CANCELLED,
                ])
                ->orderBy('scheduled_start_at');

            if (! $isAdmin && $viewer) {
                $appointmentsQuery->where(function ($query) use ($viewer): void {
                    $query
                        ->where('status', TradingAppointment::STATUS_APPROVED)
                        ->orWhere('requester_id', $viewer->id)
                        ->orWhere('host_user_id', $viewer->id);
                });
            }

            $appointmentsQuery->get()
                ->each(function (TradingAppointment $appointment) use (&$eventsByDate, &$counts, $isAdmin, $viewer): void {
                    $event = $this->appointmentCalendarEvent($appointment, $isAdmin, $viewer);
                    $eventsByDate[$appointment->scheduled_start_at->format('Y-m-d')][] = $event;
                    $counts[$event['status']]++;
                });
        }

        foreach ($eventsByDate as &$events) {
            usort($events, fn (array $left, array $right): int => $left['sort_at'] <=> $right['sort_at']);
        }
        unset($events);

        $weeks = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lte($rangeEnd)) {
            $week = [];

            for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
                $dateKey = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor->copy(),
                    'date_key' => $dateKey,
                    'is_current_month' => $cursor->isSameMonth($calendarMonth),
                    'is_today' => $cursor->isToday(),
                    'is_past' => $cursor->lt(now()->startOfDay()),
                    'events' => $eventsByDate[$dateKey] ?? [],
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'weeks' => $weeks,
            'counts' => $counts,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
        ];
    }

    private function slotCalendarEvent(TradingAppointmentSlot $slot, bool $isAdmin, ?User $viewer): array
    {
        $slot->loadMissing(['host', 'appointment.requester']);
        $appointment = $slot->appointment;
        $viewerId = (int) ($viewer->id ?? 0);
        $isOwnAppointment = $appointment && in_array($viewerId, [(int) $appointment->requester_id, (int) $appointment->host_user_id], true);
        $status = match ($slot->status) {
            TradingAppointmentSlot::STATUS_BOOKED => 'booked',
            TradingAppointmentSlot::STATUS_INACTIVE => 'inactive',
            default => $slot->start_at->isPast() ? 'expired' : 'available',
        };

        $title = match ($status) {
            'booked' => $isAdmin && $appointment
                ? 'Booked by ' . $this->displayUserName($appointment->requester)
                : ($isOwnAppointment ? 'Your appointment' : 'Booked'),
            'inactive' => 'Inactive slot',
            'expired' => 'Expired slot',
            default => 'Available slot',
        };

        $subtitleParts = [
            $this->displayUserName($slot->host),
            $slot->duration_minutes . ' min',
        ];

        if ($slot->location) {
            $subtitleParts[] = $slot->location;
        }

        if ($appointment && ($isAdmin || $isOwnAppointment)) {
            $subtitleParts[] = $appointment->subject;
        }

        return [
            'time' => $this->calendarTimeRange($slot->start_at, $slot->end_at),
            'title' => $title,
            'subtitle' => implode(' · ', array_filter($subtitleParts)),
            'status' => $status,
            'badge' => match ($status) {
                'booked' => 'Booked',
                'inactive' => 'Inactive',
                'expired' => 'Expired',
                default => 'Available',
            },
            'sort_at' => $slot->start_at->timestamp,
        ];
    }

    private function appointmentCalendarEvent(TradingAppointment $appointment, bool $isAdmin, ?User $viewer): array
    {
        $appointment->loadMissing(['requester', 'host']);
        $viewerId = (int) ($viewer->id ?? 0);
        $isOwnAppointment = in_array($viewerId, [(int) $appointment->requester_id, (int) $appointment->host_user_id], true);
        $status = match ($appointment->status) {
            TradingAppointment::STATUS_PENDING => 'pending',
            TradingAppointment::STATUS_CANCELLED => 'cancelled',
            default => 'booked',
        };

        $title = match ($status) {
            'pending' => $isAdmin
                ? 'Pending: ' . $this->displayUserName($appointment->requester)
                : 'Pending review',
            'cancelled' => 'Cancelled',
            default => $isAdmin
                ? $this->displayUserName($appointment->requester) . ' with ' . $this->displayUserName($appointment->host)
                : ($isOwnAppointment ? 'Your appointment' : 'Booked'),
        };

        $subtitleParts = $isAdmin
            ? [$appointment->subject, $appointment->duration_minutes . ' min']
            : [$this->displayUserName($appointment->host), $appointment->duration_minutes . ' min'];

        if (! $isAdmin && $isOwnAppointment) {
            $subtitleParts[] = $appointment->subject;
        }

        return [
            'time' => $this->calendarTimeRange($appointment->scheduled_start_at, $appointment->scheduled_end_at),
            'title' => $title,
            'subtitle' => implode(' · ', array_filter($subtitleParts)),
            'status' => $status,
            'badge' => match ($status) {
                'pending' => 'Pending',
                'cancelled' => 'Cancelled',
                default => 'Booked',
            },
            'sort_at' => $appointment->scheduled_start_at->timestamp,
        ];
    }

    private function calendarTimeRange(Carbon $startAt, ?Carbon $endAt): string
    {
        return $startAt->format('H:i') . ($endAt ? '-' . $endAt->format('H:i') : '');
    }

    private function displayUserName(?User $user): string
    {
        return $user?->name ?: ($user?->username ?: 'Unknown');
    }

    private function validatedSlotData(Request $request): array
    {
        return $request->validate([
            'host_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', self::HOST_ROLES)),
            ],
            'start_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in([TradingAppointmentSlot::STATUS_ACTIVE, TradingAppointmentSlot::STATUS_INACTIVE])],
        ]);
    }

    private function hostHasConflict(
        int $hostId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $ignoreSlotId = null,
        ?int $ignoreAppointmentId = null,
        bool $includePendingAppointments = false
    ): bool
    {
        $slotConflict = TradingAppointmentSlot::where('host_user_id', $hostId)
            ->whereIn('status', [TradingAppointmentSlot::STATUS_ACTIVE, TradingAppointmentSlot::STATUS_BOOKED])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->when($ignoreSlotId, fn ($query) => $query->where('id', '!=', $ignoreSlotId))
            ->exists();

        if ($slotConflict) {
            return true;
        }

        $appointmentStatuses = [TradingAppointment::STATUS_APPROVED];

        if ($includePendingAppointments) {
            $appointmentStatuses[] = TradingAppointment::STATUS_PENDING;
        }

        return TradingAppointment::where('host_user_id', $hostId)
            ->whereIn('status', $appointmentStatuses)
            ->where('scheduled_start_at', '<', $endAt)
            ->where('scheduled_end_at', '>', $startAt)
            ->when($ignoreAppointmentId, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->exists();
    }

    private function availableHosts()
    {
        return User::whereIn('role_id', self::HOST_ROLES)
            ->orderBy('role_id')
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    private function visibleHostIdsFor(User $user): array
    {
        $hostIds = User::whereIn('role_id', self::ADMIN_ROLES)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $leaderId = $this->leaderUplineId($user);

        if ((int) $user->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
            $hostIds[] = (int) $user->id;
        }

        if ($leaderId) {
            $hostIds[] = $leaderId;
        }

        return array_values(array_unique(array_filter($hostIds)));
    }

    private function canBookSlot(User $user, TradingAppointmentSlot $slot): bool
    {
        return in_array((int) $slot->host_user_id, $this->visibleHostIdsFor($user), true)
            && (int) $slot->host_user_id !== (int) $user->id;
    }

    private function canRequestHost(User $user, User $host): bool
    {
        return in_array((int) $host->id, $this->visibleHostIdsFor($user), true)
            && (int) $host->id !== (int) $user->id;
    }

    private function ensureCanAccessAppointment(TradingAppointment $appointment): void
    {
        $this->ensureTradingAccess();
        $user = auth()->user();

        if ($this->currentUserIsAdmin()) {
            return;
        }

        abort_unless(
            (int) $appointment->requester_id === (int) $user->id
            || (int) $appointment->host_user_id === (int) $user->id,
            403
        );
    }

    private function notifyAppointmentApproved(TradingAppointment $appointment, string $title): void
    {
        $appointment->loadMissing(['requester', 'host']);
        $time = $appointment->scheduled_start_at->format('M d, Y H:i');
        $message = $appointment->subject . ' is scheduled for ' . $time . '.';

        AppNotificationService::notifyUser((int) $appointment->requester_id, $title, $message, route('trading.appointments.index'), 'appointment');

        if ((int) $appointment->host_user_id !== (int) $appointment->requester_id) {
            AppNotificationService::notifyUser((int) $appointment->host_user_id, $title, $message, route('trading.appointments.index'), 'appointment');
        }
    }

    private function notifyAppointmentCancelled(TradingAppointment $appointment, string $reason): void
    {
        $appointment->loadMissing(['requester', 'host']);
        $cancelledBy = auth()->user();
        $title = 'Appointment cancelled';
        $message = $appointment->subject . ' was cancelled by ' . ($cancelledBy->name ?: $cancelledBy->username) . '. Reason: ' . $reason;
        $recipientIds = collect([$appointment->requester_id, $appointment->host_user_id])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => $id !== (int) auth()->id())
            ->unique();

        foreach ($recipientIds as $recipientId) {
            AppNotificationService::notifyUser($recipientId, $title, $message, route('trading.appointments.index'), 'appointment');
        }

        if (! $this->currentUserIsAdmin()) {
            AppNotificationService::notifyAdmins($title, $message, route('admin.trading.appointments.index'), 'appointment');
        }
    }

    private function leaderUplineId(User $user): ?int
    {
        if ((int) $user->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
            return (int) $user->id;
        }

        $current = $user;
        for ($depth = 0; $depth < 8; $depth++) {
            if (! $current->invited_by) {
                return null;
            }

            $upline = User::find($current->invited_by);
            if (! $upline) {
                return null;
            }

            if ((int) $upline->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
                return (int) $upline->id;
            }

            $current = $upline;
        }

        return null;
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->currentUserIsAdmin(), 403);
    }

    private function ensureTradingAccess(): void
    {
        abort_unless(
            auth()->check() && in_array((int) auth()->user()->role_id, array_merge(self::ADMIN_ROLES, self::TRADING_ROLES), true),
            403
        );
    }

    private function currentUserIsAdmin(): bool
    {
        return auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true);
    }
}
