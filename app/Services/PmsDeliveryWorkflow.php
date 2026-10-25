<?php

namespace App\Services;

use App\Exceptions\PmsBusinessRuleException;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsStatusRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsDeliveryWorkflow
{
    public const TRANSITIONS = [
        'Planned' => ['Open', 'Cancelled'],
        'Open' => ['In Progress', 'Blocked', 'Cancelled'],
        'In Progress' => ['Ready for Internal Testing', 'Blocked'],
        'Ready for Internal Testing' => ['Internal Testing', 'Reopened', 'Blocked'],
        'Internal Testing' => ['QAT Testing', 'Reopened', 'Blocked'],
        'QAT Testing' => ['Completed', 'Reopened', 'Blocked'],
        'Reopened' => ['In Progress', 'Blocked'],
        'Blocked' => ['Open', 'In Progress', 'Cancelled'],
        'Completed' => [], 'Cancelled' => [],
    ];

    public static function applies(PmWorkItem $ticket): bool
    {
        return in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN', 'PRD', 'TSK'], true)
            && str_starts_with($ticket->issue_key, $ticket->classification_key.'-');
    }

    public function transition(PmWorkItem $ticket, User $actor, array $data): PmWorkItem
    {
        return DB::transaction(function () use ($ticket, $actor, $data): PmWorkItem {
            $item = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless(self::applies($item) && $item->project && PmsAccess::allows($actor, $item->project, 'transition'), 403);
            if ((int) $item->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            $to = $data['status'];
            if (! PmsStatusRegistry::allowsTransition('ticket', $item->status_code,
                PmsStatusRegistry::code('ticket', $to), self::TRANSITIONS)) {
                $required = array_keys(array_filter(self::TRANSITIONS,
                    fn ($next) => in_array($to, $next, true)));
                throw new PmsBusinessRuleException('Move this ticket through its delivery workflow before selecting '.$to.'.',
                    details: ['current_stage' => $item->status, 'required_stage' => implode(' or ', $required) ?: 'A valid delivery stage']);
            }
            $role = PmsAccess::role($actor, $item->project);
            $manager = in_array($role, ['System Administrator', 'Project Administrator', 'Project Manager'], true);
            $developer = $manager || in_array($role, ['Development Lead', 'Developer'], true) && (int) $item->developer_id === $actor->id;
            $qa = $manager || in_array($role, ['QA Lead', 'QA Engineer'], true) && (int) $item->qa_id === $actor->id;
            if (in_array($to, ['Open', 'Cancelled'], true) && ! $manager) {
            abort(403);
            }
            if (in_array($to, ['In Progress', 'Ready for Internal Testing'], true) && ! $developer) {
            abort(403);
            }
            if (in_array($to, ['Internal Testing', 'QAT Testing', 'Completed', 'Reopened'], true) && ! $qa) {
            abort(403);
            }
            if ($to === 'Blocked' && ! $manager && ! $developer && ! $qa) {
            abort(403);
            }
            if (in_array($to, ['In Progress', 'Ready for Internal Testing'], true) && ! $item->developer_id) {
                throw ValidationException::withMessages(['developer_id' => 'Assign a developer first.']);
            }
            if (in_array($to, ['Internal Testing', 'QAT Testing', 'Completed'], true) && ! $item->qa_id) {
                throw ValidationException::withMessages(['qa_id' => 'Assign a QA engineer first.']);
            }
            if (in_array($to, ['Completed', 'Reopened', 'Blocked', 'Cancelled'], true) && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Record the test result or decision reason.']);
            }
            $from = $item->status;
            $item->status = $to;
            if ($to === 'In Progress' && ! $item->development_started_at) {
            $item->development_started_at = now();
            }
            if ($to === 'Reopened') {
            $item->reopen_count++;
            }
            if ($to === 'Completed') {
                $item->qa_result = $data['reason'];
                $item->qa_completed_at = now();
                $item->completed_at = now();
            }
            $item->lock_version++;
            $item->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $item->id, 'actor_id' => $actor->id, 'event' => 'transition',
                'from_status' => $from, 'to_status' => $to, 'reason' => $data['reason'] ?? null,
                'changes' => json_encode(['lock_version' => $item->lock_version]), 'created_at' => now(),
            ]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $actor->id, 'action' => 'ticket.transition', 'subject_type' => 'issue', 'subject_id' => $item->id,
                'details' => json_encode(['from' => $from, 'to' => $to]), 'created_at' => now(),
            ]);

            return $item;
        });
    }
}
