<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use App\Services\PmsTicketHoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlockerController extends Controller
{
    public const CATEGORIES = [
        'Requirement Clarification', 'Development Dependency', 'External Dependency',
        'Environment Issue', 'Deployment Issue', 'Access / Permission Issue',
        'Technical Issue', 'Resource Constraint', 'Other',
    ];

    public function index(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        $blockers = DB::table('pm_ticket_blockers')->where('issue_id', $ticket->id)->orderByDesc('id')->get();
        $history = DB::table('pm_ticket_blocker_history')->whereIn('blocker_id', $blockers->pluck('id'))
            ->orderByDesc('id')->get();

        return response()->json(compact('blockers', 'history'));
    }

    public function store(Request $request, PmWorkItem $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $this->validated($request, $ticket);
        $pause = $request->validate(['pause_ticket' => 'nullable|boolean', 'lock_version' => 'required_if:pause_ticket,1|nullable|integer|min:0']);
        [$id, $hold] = DB::transaction(function () use ($request, $ticket, $data, $pause): array {
            $id = DB::table('pm_ticket_blockers')->insertGetId($data + [
                'issue_id' => $ticket->id, 'reported_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->record($id, $ticket, $request->user()->id, 'created', null, $data);
            $hold = ! empty($pause['pause_ticket'])
                ? app(PmsTicketHoldService::class)->process($request, $ticket, [
                    'action' => 'hold', 'reason' => $data['reason'], 'blocker_id' => $id,
                    'lock_version' => (int) $pause['lock_version'],
                ]) : null;
            return [$id, $hold];
        });

        return $request->expectsJson() ? response()->json($hold
            ? ['blocker' => DB::table('pm_ticket_blockers')->find($id), 'hold' => $hold]
            : DB::table('pm_ticket_blockers')->find($id), 201)
            : back()->with('success', $hold ? ($hold['pending'] ? 'Blocker recorded; On Hold submitted for PM review.' : 'Blocker recorded and ticket placed On Hold.') : 'Blocker recorded.');
    }

    public function update(Request $request, PmWorkItem $ticket, int $blocker)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $this->validated($request, $ticket);
        $state = $request->validate(['status_code' => ['nullable', 'integer', Rule::in([1, 2])]]);
        if (isset($state['status_code'])) $data['status_code'] = (int) $state['status_code'];
        DB::transaction(function () use ($request, $ticket, $blocker, $data): void {
            $before = DB::table('pm_ticket_blockers')->where('id', $blocker)->where('issue_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($before, 404);
            abort_if($before->resolved_at, 422, 'Reopen this blocker before editing it.');
            DB::table('pm_ticket_blockers')->where('id', $blocker)->update($data + ['updated_at' => now()]);
            $this->record($blocker, $ticket, $request->user()->id, 'updated', (array) $before, $data);
        });

        return $request->expectsJson() ? response()->json(DB::table('pm_ticket_blockers')->find($blocker))
            : back()->with('success', 'Blocker updated.');
    }

    public function resolve(Request $request, PmWorkItem $ticket, int $blocker)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['resolution_note' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $ticket, $blocker, $data): void {
            $before = DB::table('pm_ticket_blockers')->where('id', $blocker)->where('issue_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($before, 404);
            abort_if($before->resolved_at, 422, 'This blocker is already resolved.');
            DB::table('pm_ticket_blockers')->where('id', $blocker)->update([
                'status_code' => 3, 'resolved_at' => now(), 'resolved_by' => $request->user()->id,
                'resolution_note' => $data['resolution_note'], 'updated_at' => now(),
            ]);
            $this->record($blocker, $ticket, $request->user()->id, 'resolved', (array) $before, $data);
        });

        return $request->expectsJson() ? response()->json(['resolved' => true]) : back()->with('success', 'Blocker resolved.');
    }

    public function reopen(Request $request, PmWorkItem $ticket, int $blocker)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $ticket, $blocker, $data): void {
            $before = DB::table('pm_ticket_blockers')->where('id', $blocker)->where('issue_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($before, 404);
            abort_unless($before->resolved_at, 422, 'This blocker is already active.');
            DB::table('pm_ticket_blockers')->where('id', $blocker)->update([
                'status_code' => 1, 'resolved_at' => null, 'resolved_by' => null, 'resolution_note' => null, 'updated_at' => now(),
            ]);
            $this->record($blocker, $ticket, $request->user()->id, 'reopened', (array) $before, $data);
        });

        return $request->expectsJson() ? response()->json(['reopened' => true]) : back()->with('success', 'Blocker reopened.');
    }

    public function cancel(Request $request, PmWorkItem $ticket, int $blocker)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $ticket, $blocker, $data): void {
            $before = DB::table('pm_ticket_blockers')->where('id', $blocker)->where('issue_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($before, 404);
            abort_if($before->resolved_at, 422, 'This blocker is already closed.');
            DB::table('pm_ticket_blockers')->where('id', $blocker)->update([
                'status_code' => 4, 'resolved_at' => now(), 'resolved_by' => $request->user()->id,
                'resolution_note' => $data['reason'], 'updated_at' => now(),
            ]);
            $this->record($blocker, $ticket, $request->user()->id, 'cancelled', (array) $before, $data);
        });
        return $request->expectsJson() ? response()->json(['cancelled' => true]) : back()->with('success', 'Blocker cancelled.');
    }

    private function validated(Request $request, PmWorkItem $ticket): array
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'reason' => 'required|string|max:500', 'details' => 'nullable|string|max:10000',
            'related_issue_id' => 'nullable|integer|exists:pm_work_items,id',
            'related_issue_key' => 'nullable|string|max:48',
            'owner_id' => 'nullable|integer|exists:users,id',
            'expected_resolution_date' => 'nullable|date',
        ]);
        if (array_key_exists('related_issue_key', $data)) {
            $related = blank($data['related_issue_key']) ? null
                : PmWorkItem::where('issue_key', strtoupper(trim($data['related_issue_key'])))
                    ->orWhere('reference', strtolower(trim($data['related_issue_key'])))->first();
            if (filled($data['related_issue_key']) && ! $related) {
                throw ValidationException::withMessages(['related_issue_key' => 'Choose an existing ticket.']);
            }
            $data['related_issue_id'] = $related?->id;
            unset($data['related_issue_key']);
        }
        if (! empty($data['related_issue_id'])) {
            $related = PmWorkItem::findOrFail($data['related_issue_id']);
            if ($related->id === $ticket->id || ! PmsTicketAccess::view($request->user(), $related)) {
                throw ValidationException::withMessages(['related_issue_id' => 'Choose another ticket you can access.']);
            }
        }
        if (! empty($data['owner_id']) && ! PmsAccess::eligible(User::find($data['owner_id']))) {
            throw ValidationException::withMessages(['owner_id' => 'Choose an active PMS member.']);
        }
        if (! empty($data['owner_id']) && $ticket->project
            && ! $ticket->project->members()->where('users.id', $data['owner_id'])->exists()) {
            throw ValidationException::withMessages(['owner_id' => 'Choose a member of this project.']);
        }
        return $data;
    }

    private function authorizeTicket(Request $request, PmWorkItem $ticket): void
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        abort_unless(PmsTicketAccess::manage($request->user(), $ticket)
            || $ticket->project && PmsAccess::allows($request->user(), $ticket->project, 'edit_issue')
                && (PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst'
                    || in_array($request->user()->id, array_filter([$ticket->developer_id, $ticket->qa_id]), false)), 403);
    }

    private function record(int $id, PmWorkItem $ticket, int $actor, string $event, ?array $before, array $after): void
    {
        DB::table('pm_ticket_blocker_history')->insert([
            'blocker_id' => $id, 'actor_id' => $actor, 'event' => $event,
            'before' => $before ? json_encode($before) : null, 'after' => json_encode($after), 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $actor, 'action' => 'ticket.blocker_'.$event,
            'subject_type' => 'ticket', 'subject_id' => $ticket->id,
            'details' => json_encode(['blocker_id' => $id, 'reason' => $after['reason'] ?? $after['resolution_note'] ?? null]),
            'created_at' => now(),
        ]);
    }
}
