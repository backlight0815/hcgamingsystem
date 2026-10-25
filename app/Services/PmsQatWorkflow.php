<?php

namespace App\Services;

use App\Exceptions\PmsBusinessRuleException;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use App\Support\PmsStatusRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsQatWorkflow
{
    public const STATUSES = ['Open', 'In Progress', 'Code Review', 'Ready for QAT', 'QAT in Progress', 'QAT Done', 'Production Done', 'Reopen'];

    public const RESOLUTIONS = ['Environment Issue', 'Deployment Issue', 'Missing Requirements', 'Existing Behaviour', 'Not Bug', 'Unable to Test'];

    public const TRANSITIONS = [
        'Open' => ['In Progress', 'Reopen'],
        'In Progress' => ['Code Review', 'Ready for QAT', 'Reopen'],
        'Code Review' => ['Ready for QAT', 'In Progress', 'Reopen'],
        'Ready for QAT' => ['QAT in Progress', 'Reopen'],
        'QAT in Progress' => ['QAT Done', 'Reopen'],
        'QAT Done' => ['Production Done', 'Reopen'],
        'Production Done' => ['Reopen'],
        'Reopen' => ['In Progress'],
        'New' => ['Triaged', 'Blocked', 'Duplicate', 'Rejected'],
        'Triaged' => ['Assigned', 'Blocked', 'Duplicate', 'Rejected'],
        'Assigned' => ['In Progress', 'Blocked'],
        'Fixed' => ['Ready for Retest', 'Reopened'],
        'Ready for Retest' => ['Retesting', 'Blocked'],
        'Retesting' => ['Reopened', 'Blocked'],
        'Verified' => ['Closed', 'Reopened'],
        'Reopened' => ['Assigned', 'In Progress', 'Blocked'],
        'Blocked' => ['Triaged', 'Assigned', 'In Progress', 'Retesting'],
        'Closed' => [], 'Duplicate' => [], 'Rejected' => [],
    ];

    public function transition(PmWorkItem $ticket, User $actor, string $to, int $version, ?string $reason): PmWorkItem
    {
        return DB::transaction(function () use ($ticket, $actor, $to, $version, $reason): PmWorkItem {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless($locked->classification_key === 'QAT' && PmsTicketAccess::view($actor, $locked), 403);
            $this->checkVersion($locked, $version);
            if (! PmsStatusRegistry::allowsTransition('ticket', $locked->status_code,
                PmsStatusRegistry::code('ticket', $to), self::TRANSITIONS)) {
                $required = array_keys(array_filter(self::TRANSITIONS,
                    fn ($next) => in_array($to, $next, true)));
                throw new PmsBusinessRuleException('This QAT action is not available at the current stage.',
                    details: ['current_stage' => $locked->status, 'required_stage' => implode(' or ', $required) ?: 'A valid QAT stage']);
            }
            if ($to === 'Verified') {
                throw ValidationException::withMessages(['status' => 'Record a passed QA verification to complete QAT.']);
            }
            $manager = PmsTicketAccess::manage($actor, $locked);
            $developer = $locked->project && in_array(PmsAccess::role($actor, $locked->project), ['Development Lead', 'Developer'], true)
                && (int) $locked->developer_id === $actor->id;
            $qa = PmsTicketAccess::verify($actor, $locked);
            if ($to === 'QAT Done' && ! $qa) abort(403);
            if (in_array($to, ['Triaged', 'Assigned', 'Duplicate', 'Rejected', 'Closed', 'Production Done'], true) && ! $manager) {
                abort(403);
            }
            if (in_array($to, ['In Progress', 'Fixed', 'Ready for Retest', 'Code Review', 'Ready for QAT'], true) && ! $manager && ! $developer) {
                abort(403);
            }
            if (in_array($to, ['Retesting', 'Reopened', 'QAT in Progress', 'Reopen'], true) && ! $manager && ! $qa) {
                abort(403);
            }
            if ($to === 'Blocked' && ! $manager && ! $developer && ! $qa) {
                abort(403);
            }
            if (in_array($to, ['Assigned', 'In Progress', 'Fixed', 'Code Review'], true) && ! $locked->developer_id) {
                throw ValidationException::withMessages(['developer_id' => 'Assign an active project developer first.']);
            }
            if (in_array($to, ['Retesting', 'QAT in Progress'], true) && ! $locked->qa_id) {
                throw ValidationException::withMessages(['qa_id' => 'Assign an active QA engineer first.']);
            }
            if (in_array($to, ['Ready for Retest', 'Retesting', 'Closed', 'Ready for QAT', 'QAT in Progress', 'QAT Done', 'Production Done'], true)) {
                $blocked = app(PmsTicketRelationships::class)->unresolvedDependencies($locked->id);
                if ($blocked->isNotEmpty()) {
                    throw ValidationException::withMessages(['status' => 'Resolve or verify dependencies first: '.$blocked->pluck('reference')->join(', ')]);
                }
            }
            if (in_array($to, ['Reopened', 'Reopen', 'Blocked', 'Duplicate', 'Rejected'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Explain this QAT decision.']);
            }
            $from = $locked->status;
            $locked->status = $to;
            if ($to === 'QAT Done') {
                $locked->qa_result = $reason ?: 'QAT completed';
                $locked->qa_completed_at = now();
                $locked->verifications()->create(['tested_by' => $actor->id, 'result' => 'Passed',
                    'build' => null, 'environment' => null, 'remarks' => $locked->qa_result,
                    'tested_at' => now()]);
            }
            if (in_array($to, ['Reopened', 'Reopen'], true)) {
                $locked->reopen_count++;
            }
            $locked->lock_version++;
            $locked->save();
            $this->record($locked, $actor, $from, $to, $reason);

            return $locked;
        });
    }

    public function verify(PmWorkItem $ticket, User $actor, array $data): PmWorkItem
    {
        return DB::transaction(function () use ($ticket, $actor, $data): PmWorkItem {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless($locked->classification_key === 'QAT' && PmsTicketAccess::verify($actor, $locked), 403);
            $this->checkVersion($locked, (int) $data['lock_version']);
            if (! in_array($locked->status, ['Retesting', 'QAT in Progress'], true)) {
                throw new PmsBusinessRuleException('Start QAT verification before recording a result.',
                    details: ['current_stage' => $locked->status, 'required_stage' => 'Retesting or QAT in Progress']);
            }
            if ($data['result'] === 'Passed' && app(PmsTicketRelationships::class)->unresolvedDependencies($locked->id)->isNotEmpty()) {
                throw ValidationException::withMessages(['result' => 'Resolve or verify dependencies before passing QA verification.']);
            }
            $locked->verifications()->create([
                'tested_by' => $actor->id, 'result' => $data['result'], 'build' => $data['build'] ?? null,
                'environment' => $data['environment'] ?? null, 'remarks' => $data['remarks'],
                'regression_result' => $data['regression_result'] ?? null, 'tested_at' => $data['tested_at'] ?? now(),
            ]);
            $legacy = $locked->status === 'Retesting';
            $to = match ($data['result']) {
                'Passed' => $legacy ? 'Verified' : 'QAT Done',
                'Failed' => $legacy ? 'Reopened' : 'Reopen',
                default => $legacy ? 'Blocked' : 'Ready for QAT',
            };
            $locked->status = $to;
            $locked->qa_result = $data['remarks'];
            $locked->qa_completed_at = in_array($to, ['Verified', 'QAT Done'], true) ? now() : null;
            if (in_array($to, ['Reopened', 'Reopen'], true)) {
                $locked->reopen_count++;
            }
            $locked->lock_version++;
            $locked->save();
            $this->record($locked, $actor, $legacy ? 'Retesting' : 'QAT in Progress', $to, $data['remarks']);

            return $locked;
        });
    }

    private function checkVersion(PmWorkItem $ticket, int $version): void
    {
        if ((int) $ticket->lock_version !== $version) {
            throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and try again.']);
        }
    }

    private function record(PmWorkItem $ticket, User $actor, string $from, string $to, ?string $reason): void
    {
        DB::table('pm_issue_history')->insert([
            'issue_id' => $ticket->id, 'actor_id' => $actor->id, 'event' => 'transition',
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
            'changes' => json_encode(['lock_version' => $ticket->lock_version]), 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $actor->id, 'action' => 'ticket.transition', 'subject_type' => 'issue', 'subject_id' => $ticket->id,
            'details' => json_encode(['from' => $from, 'to' => $to]), 'created_at' => now(),
        ]);
    }
}
