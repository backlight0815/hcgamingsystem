<?php

namespace App\Services;

use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsStatusRegistry;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsTicketHoldService
{
    public function process(Request $request, PmWorkItem $ticket, array $data): array
    {
        $actor = $request->user();
        abort_unless(PmsTicketAccess::view($actor, $ticket), 403);
        $this->validateAction($ticket, $data);
        $role = $ticket->project ? PmsAccess::role($actor, $ticket->project) : null;
        if ($role === 'Business Analyst' && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true)) {
            $changes = $data['action'] === 'hold'
                ? ['status' => 'On Hold', 'hold_reason' => $data['reason'], 'hold_blocker_id' => $data['blocker_id'] ?? null]
                : ['status' => $data['status'], 'resume_reason' => $data['reason'] ?? null,
                    'override_unresolved' => (bool) ($data['override_unresolved'] ?? false)];
            $review = app(PmsChangeReviewService::class)->submit($request, $ticket, 'update', $changes, $data['action']);
            return ['pending' => true, 'review_id' => $review->id];
        }
        return ['pending' => false, 'ticket' => $this->apply($ticket, $actor, $data)];
    }

    public function apply(PmWorkItem $ticket, User $actor, array $data): PmWorkItem
    {
        $updated = DB::transaction(function () use ($ticket, $actor, $data): PmWorkItem {
            $item = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless(PmsTicketAccess::manage($actor, $item), 403);
            if ((int) ($data['lock_version'] ?? -1) !== (int) $item->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and try again.']);
            }
            $this->validateAction($item, $data);
            $from = $item->status;
            if ($data['action'] === 'hold') {
                $item->hold_previous_status_code = $item->status_code;
                $item->hold_reason = $data['reason'];
                $item->hold_blocker_id = $data['blocker_id'] ?? null;
                $item->held_by = $actor->id;
                $item->held_at = now();
                $item->status = 'On Hold';
            } else {
                $item->status = $data['status'];
                $item->hold_previous_status_code = null;
                $item->hold_reason = null;
                $item->hold_blocker_id = null;
                $item->held_by = null;
                $item->held_at = null;
            }
            $item->lock_version++;
            $item->save();
            $changes = ['from' => $from, 'to' => $item->status, 'blocker_id' => $data['blocker_id'] ?? null,
                'override_unresolved' => (bool) ($data['override_unresolved'] ?? false),
                'unresolved_blockers' => $data['action'] === 'resume' ? $this->unresolvedCount($item) : null];
            DB::table('pm_issue_history')->insert([
                'issue_id' => $item->id, 'actor_id' => $actor->id, 'event' => $data['action'],
                'from_status' => $from, 'to_status' => $item->status, 'reason' => $data['reason'] ?? null,
                'changes' => json_encode($changes), 'created_at' => now(),
            ]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $actor->id, 'action' => 'ticket.'.$data['action'],
                'subject_type' => 'ticket', 'subject_id' => $item->id,
                'details' => json_encode($changes), 'created_at' => now(),
            ]);
            return $item;
        });
        app(PmsTicketNotificationService::class)->notifyParticipants($updated, $actor,
            $updated->reference.' '.$data['action'], $data['reason'] ?? $updated->status);
        return $updated;
    }

    public function destinations(PmWorkItem $ticket): array
    {
        $states = $ticket->classification_key === 'QAT' ? array_keys(PmsQatWorkflow::TRANSITIONS)
            : (PmsDeliveryWorkflow::applies($ticket) ? array_keys(PmsDeliveryWorkflow::TRANSITIONS) : PmsWorkflow::STATUSES);
        return array_values(array_filter($states, fn ($status) => ! in_array($status,
            ['On Hold', 'Cancelled', 'Completed', 'Production Done', 'Closed', 'Duplicate', 'Rejected', 'Done', 'Released'], true)));
    }

    public function unresolvedCount(PmWorkItem $ticket): int
    {
        return DB::table('pm_ticket_blockers')->where('issue_id', $ticket->id)->whereNull('resolved_at')->count()
            + app(PmsTicketRelationships::class)->unresolvedDependencies($ticket->id)->count();
    }

    private function validateAction(PmWorkItem $ticket, array $data): void
    {
        if ($data['action'] === 'hold') {
            if ($ticket->status === 'On Hold' || ! in_array($ticket->status, $this->destinations($ticket), true)) {
                throw ValidationException::withMessages(['status' => 'This ticket cannot be paused from its current status.']);
            }
            if (blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Explain why work is paused.']);
            }
            if (! empty($data['blocker_id']) && ! DB::table('pm_ticket_blockers')->where('id', $data['blocker_id'])
                ->where('issue_id', $ticket->id)->whereNull('resolved_at')->exists()) {
                throw ValidationException::withMessages(['blocker_id' => 'Choose an active blocker on this ticket.']);
            }
            return;
        }
        if ($ticket->status !== 'On Hold') {
            throw ValidationException::withMessages(['status' => 'Only an On Hold ticket can resume.']);
        }
        if (! in_array($data['status'] ?? null, $this->destinations($ticket), true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid working status.']);
        }
        $previous = PmsStatusRegistry::key('ticket', (int) $ticket->hold_previous_status_code);
        if (in_array($previous, $this->destinations($ticket), true) && $data['status'] !== $previous) {
            throw ValidationException::withMessages(['status' => 'Resume to the previous status. Choose another status only if it is no longer valid.']);
        }
        if ($this->unresolvedCount($ticket) > 0 &&
            (! ($data['override_unresolved'] ?? false) || blank($data['reason'] ?? null))) {
            throw ValidationException::withMessages(['reason' => 'Unresolved blockers remain. Confirm the override and explain why work can continue.']);
        }
    }
}
