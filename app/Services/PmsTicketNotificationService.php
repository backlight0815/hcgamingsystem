<?php

namespace App\Services;

use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsTicketAccess;
use Illuminate\Support\Facades\DB;

class PmsTicketNotificationService
{
    public function notifyParticipants(PmWorkItem $ticket, User $actor, string $title, string $message): void
    {
        $pics = DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)
            ->where('assignment_type', 'pic')->pluck('user_id');
        $ids = collect([$ticket->reporter_id, $ticket->developer_id, $ticket->qa_id])->concat($pics)
            ->filter()->unique()->reject(fn ($id) => (int) $id === $actor->id);
        foreach (User::whereIn('id', $ids)->get() as $recipient) {
            if (PmsTicketAccess::view($recipient, $ticket)) {
                AppNotificationService::notifyUser($recipient->id, $title, $message,
                    route('pms.tickets.show', $ticket), 'project_management', $actor->id);
            }
        }
    }
}
