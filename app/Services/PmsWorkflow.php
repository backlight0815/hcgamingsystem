<?php

namespace App\Services;

use App\Exceptions\PmsBusinessRuleException;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsStatusRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsWorkflow
{
    public const STATUSES = ['Planned', 'Open', 'Development In Progress', 'Deployment', 'QAT In Progress', 'QAT Dependency', 'Reopen', 'On Hold', 'QAT Done', 'Production Done', 'Cancelled'];

    public const TRANSITIONS = [
        'Planned' => ['Open'],
        'Open' => ['Planned', 'Development In Progress'],
        'Development In Progress' => ['Open', 'Deployment'],
        'Deployment' => ['Development In Progress', 'QAT In Progress'],
        'QAT In Progress' => ['QAT Dependency', 'Reopen', 'QAT Done'],
        'QAT Dependency' => ['QAT In Progress', 'Reopen'],
        'Reopen' => ['Development In Progress'],
        'QAT Done' => ['Reopen', 'Production Done'],
        'Production Done' => [],
        'Cancelled' => [],
    ];

    public function transition(PmWorkItem $issue, User $actor, array $input): PmWorkItem
    {
        return DB::transaction(function () use ($issue, $actor, $input) {
            $item = PmWorkItem::query()->lockForUpdate()->findOrFail($issue->id);
            $project = $item->project;
            abort_unless($project, 404);
            abort_unless(PmsAccess::allows($actor, $project, 'transition'), 403);
            if ((int) ($input['lock_version'] ?? -1) !== $item->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'This issue changed since you opened it. Refresh and try again.']);
            }
            $to = $input['status'] ?? '';
            if (! PmsStatusRegistry::allowsTransition('ticket', $item->status_code,
                PmsStatusRegistry::code('ticket', $to), self::TRANSITIONS)) {
                $required = array_keys(array_filter(self::TRANSITIONS,
                    fn ($next) => in_array($to, $next, true)));
                throw new PmsBusinessRuleException('Move this ticket through its workflow before selecting '.$to.'.',
                    details: ['current_stage' => $item->status, 'required_stage' => implode(' or ', $required) ?: 'A valid workflow stage']);
            }
            $role = PmsAccess::role($actor, $project);
            $manager = in_array($role, ['System Administrator', 'Project Administrator', 'Project Manager'], true);
            $developer = $manager || in_array($role, ['Development Lead', 'Developer'], true) && (int) $item->developer_id === $actor->id;
            $qa = $manager || in_array($role, ['QA Lead', 'QA Engineer'], true) && (int) $item->qa_id === $actor->id;
            if (in_array($to, ['Development In Progress', 'Deployment'], true) && ! $developer) {
                abort(403);
            }
            if (in_array($to, ['QAT Dependency', 'Reopen', 'QAT Done'], true) && ! $qa) {
                abort(403);
            }
            if ($to === 'Production Done' && ! PmsAccess::allows($actor, $project, 'approve_production')) {
                abort(403);
            }
            if ($to === 'Development In Progress' && ! $item->developer_id) {
                throw ValidationException::withMessages(['developer_id' => 'Assign a developer first.']);
            }
            if ($to === 'QAT In Progress') {
                if (! $item->qa_id) {
                    throw ValidationException::withMessages(['qa_id' => 'Assign a QA engineer first.']);
                }
                if ($item->status === 'Deployment' && (! $item->qat_build || ! $item->qat_deployed_at || ! $item->qat_deployment_evidence)) {
                    throw ValidationException::withMessages(['status' => 'Record the QAT build, deployment time and success evidence before testing.']);
                }
            }
            if ($to === 'QAT Dependency' && (empty($input['reason']) || empty($input['dependency_owner_id']))) {
                throw ValidationException::withMessages(['reason' => 'A dependency reason and owner are required.']);
            }
            if ($to === 'Reopen' && empty($input['reason'])) {
                throw ValidationException::withMessages(['reason' => 'A reopen reason is required.']);
            }
            if ($to === 'QAT Done' && ! $item->qa_result) {
                throw ValidationException::withMessages(['qa_result' => 'Record the QA result before approval.']);
            }
            if ($to === 'Production Done' && (! $item->production_build || ! $item->production_deployed_at || ! $item->production_evidence)) {
                throw ValidationException::withMessages(['status' => 'Record a successful production deployment, build and timestamp first.']);
            }
            if (! empty($input['dependency_owner_id'])) {
                $member = DB::table('pm_project_members')->where('project_id', $project->id)->where('user_id', $input['dependency_owner_id'])->exists();
                if (! $member && ! PmsAccess::administrator(User::find($input['dependency_owner_id']))) {
                    throw ValidationException::withMessages(['dependency_owner_id' => 'Choose a project member as dependency owner.']);
                }
            }
            $from = $item->status;
            $item->status = $to;
            if ($to === 'Development In Progress' && ! $item->development_started_at) {
            $item->development_started_at = now();
            }
            if ($to === 'QAT Dependency') {
                $item->dependency_reason = $input['reason'];
                $item->dependency_owner_id = $input['dependency_owner_id'];
            }
            if ($to === 'Reopen') {
            $item->reopen_count++;
            }
            if ($to === 'QAT Done') {
            $item->qa_completed_at = now();
            }
            if ($to === 'Production Done') {
                $item->completed_at = now();
                $item->production_approved_by = $actor->id;
            }
            $item->lock_version++;
            $item->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $item->id, 'actor_id' => $actor->id, 'event' => 'transition',
                'from_status' => $from, 'to_status' => $to, 'reason' => $input['reason'] ?? null,
                'changes' => json_encode(['lock_version' => $item->lock_version]), 'created_at' => now(),
            ]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $actor->id, 'action' => 'issue.transition', 'subject_type' => 'issue', 'subject_id' => $item->id,
                'details' => json_encode(['from' => $from, 'to' => $to]), 'created_at' => now(),
            ]);
            if ($to === 'Reopen' && $item->developer_id && $item->developer_id !== $actor->id) {
                AppNotificationService::notifyUser(
                    $item->developer_id,
                    "{$item->reference} reopened by QA",
                    $input['reason'],
                    route('pms.issues.show', $item),
                    'project_management',
                    $actor->id
                );
            }

            return $item;
        });
    }
}
