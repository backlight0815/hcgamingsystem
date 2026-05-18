<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    private const ADMIN_ROLES = [1, 2];
    private const SUPPORT_ROLES = [201, 202, 501, 502, 750, 760, 770];

    public function index(Request $request)
    {
        $this->ensureSupportAccess();

        $query = SupportTicket::with(['requester', 'assignedAdmin', 'latestMessage'])
            ->withCount('attachments')
            ->latest();

        if (! $this->currentUserIsAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(15)->withQueryString();
        $baseMetricsQuery = SupportTicket::query();

        if (! $this->currentUserIsAdmin()) {
            $baseMetricsQuery->where('user_id', auth()->id());
        }

        $metrics = [
            'open' => (clone $baseMetricsQuery)->where('status', SupportTicket::STATUS_OPEN)->count(),
            'answered' => (clone $baseMetricsQuery)->where('status', SupportTicket::STATUS_ANSWERED)->count(),
            'closed' => (clone $baseMetricsQuery)->where('status', SupportTicket::STATUS_CLOSED)->count(),
            'urgent' => (clone $baseMetricsQuery)->where('priority', SupportTicket::PRIORITY_URGENT)->where('status', '!=', SupportTicket::STATUS_CLOSED)->count(),
        ];

        $priorities = SupportTicket::priorities();
        $isAdmin = $this->currentUserIsAdmin();

        return view('support_tickets.index', compact(
            'tickets',
            'metrics',
            'priorities',
            'isAdmin',
            'search'
        ));
    }

    public function create()
    {
        $this->ensureSupportAccess();

        $priorities = SupportTicket::priorities();

        return view('support_tickets.create', compact('priorities'));
    }

    public function store(Request $request)
    {
        $this->ensureSupportAccess();

        $data = $this->validatedTicketData($request);

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'ticket_number' => $this->generateTicketNumber(),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $message = $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => $data['message'],
            'is_admin_reply' => $this->currentUserIsAdmin(),
        ]);

        $this->storeAttachments($request, $ticket, $message);

        AppNotificationService::notifyAdmins(
            'New support ticket opened',
            $ticket->ticket_number . ': ' . $ticket->subject,
            route('support.tickets.show', $ticket->id),
            'support_ticket'
        );

        return redirect()
            ->route('support.tickets.show', $ticket->id)
            ->with('success', 'Ticket opened successfully.');
    }

    public function show(SupportTicket $ticket)
    {
        $this->ensureCanViewTicket($ticket);

        $ticket->load([
            'requester',
            'assignedAdmin',
            'closer',
            'messages.user',
            'messages.attachments',
        ]);

        $isAdmin = $this->currentUserIsAdmin();

        return view('support_tickets.show', compact('ticket', 'isAdmin'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->ensureCanViewTicket($ticket);
        abort_if($ticket->isClosed(), 403, 'Closed tickets cannot receive replies.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip', 'max:20480'],
        ]);

        $isAdminReply = $this->currentUserIsAdmin();

        $message = $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => $data['message'],
            'is_admin_reply' => $isAdminReply,
        ]);

        $this->storeAttachments($request, $ticket, $message);

        $ticket->update([
            'status' => $isAdminReply ? SupportTicket::STATUS_ANSWERED : SupportTicket::STATUS_OPEN,
            'assigned_admin_id' => $isAdminReply ? auth()->id() : $ticket->assigned_admin_id,
        ]);

        if ($isAdminReply) {
            AppNotificationService::notifyUser(
                (int) $ticket->user_id,
                'Admin replied to your ticket',
                $ticket->ticket_number . ': ' . $ticket->subject,
                route('support.tickets.show', $ticket->id),
                'support_ticket'
            );
        } else {
            AppNotificationService::notifyAdmins(
                'Ticket needs admin attention',
                $ticket->ticket_number . ': ' . $ticket->subject,
                route('support.tickets.show', $ticket->id),
                'support_ticket'
            );
        }

        return redirect()
            ->route('support.tickets.show', $ticket->id)
            ->with('success', 'Reply added successfully.');
    }

    public function close(SupportTicket $ticket)
    {
        $this->ensureCanViewTicket($ticket);

        if ($ticket->isClosed()) {
            return back()->with('success', 'Ticket is already closed.');
        }

        $ticket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        if ($this->currentUserIsAdmin()) {
            AppNotificationService::notifyUser(
                (int) $ticket->user_id,
                'Your support ticket was closed',
                $ticket->ticket_number . ': ' . $ticket->subject,
                route('support.tickets.show', $ticket->id),
                'support_ticket'
            );
        } else {
            AppNotificationService::notifyAdmins(
                'Support ticket closed by requester',
                $ticket->ticket_number . ': ' . $ticket->subject,
                route('support.tickets.show', $ticket->id),
                'support_ticket'
            );
        }

        return redirect()
            ->route('support.tickets.show', $ticket->id)
            ->with('success', 'Ticket closed successfully.');
    }

    public function downloadAttachment(SupportTicketAttachment $attachment)
    {
        $ticket = $attachment->ticket;
        abort_unless($ticket, 404);
        $this->ensureCanViewTicket($ticket);
        abort_unless($attachment->file_path && Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    private function validatedTicketData(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip', 'max:20480'],
        ]);
    }

    private function storeAttachments(Request $request, SupportTicket $ticket, $message): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
            $filePath = $file->storeAs('support_ticket_attachments', $fileName, 'local');

            $ticket->attachments()->create([
                'support_ticket_message_id' => $message->id,
                'uploaded_by' => auth()->id(),
                'file_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?: 0,
            ]);
        }
    }

    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = 'TKT-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (SupportTicket::where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }

    private function ensureSupportAccess(): void
    {
        abort_unless(
            auth()->check() && in_array((int) auth()->user()->role_id, array_merge(self::ADMIN_ROLES, self::SUPPORT_ROLES), true),
            403
        );
    }

    private function ensureCanViewTicket(SupportTicket $ticket): void
    {
        $this->ensureSupportAccess();

        if ($this->currentUserIsAdmin()) {
            return;
        }

        abort_unless((int) $ticket->user_id === (int) auth()->id(), 403);
    }

    private function currentUserIsAdmin(): bool
    {
        return auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true);
    }
}
