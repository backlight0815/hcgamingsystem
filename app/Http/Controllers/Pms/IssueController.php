<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Services\AppNotificationService;
use App\Services\PmsWorkflow;
use App\Support\PmsAccess;
use App\Support\PmsPriority;
use App\Support\PmsPosition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    public const CLASSIFICATIONS = [
        'FTR' => 'Feature Request',
        'ENH' => 'Enhancement Request',
        'PDI' => 'Production Defect Incident',
    ];

    public function index(Request $request)
    {
        $query = PmWorkItem::with(['project:id,key,name', 'cycle:id,cycle_key,name', 'developer:id,name', 'qa:id,name'])->whereHas('project', fn ($q) => $q->whereNull('archived_at'));
        if (! PmsAccess::administrator($request->user())) {
            $query->whereIn('project_id', DB::table('pm_project_members')->where('user_id', $request->user()->id)->select('project_id'));
        }
        if ($request->filled('project')) {
        $query->where('project_id', $request->integer('project'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('cycle')) {
        $query->where('cycle_id', $request->integer('cycle'));
        }
        if (array_key_exists((string) $request->classification, self::CLASSIFICATIONS)) {
        $query->where('classification_key', $request->classification);
        }
        if (in_array($request->status, PmsWorkflow::STATUSES, true)) {
        $query->where('status', $request->status);
        }
        if (in_array($request->priority, ['P0', 'P1', 'P2', 'P3', 'P4'], true)) {
        $request->priority === 'P1' ? $query->whereIn('priority', ['P0','P1']) : $query->where('priority', $request->priority);
        } elseif (in_array($request->priority, ['Low','Normal','High','Critical'], true)) {
        $query->whereIn('priority', PmsPriority::codesFor($request->priority));
        }
        if ($request->boolean('critical')) {
        $query->whereIn('priority', ['P0', 'P1']);
        }
        if ($request->filled('q')) {
        $query->where(fn ($q) => $q->where('summary', 'like', '%'.$request->q.'%')
            ->orWhere('issue_key', 'like', '%'.$request->q.'%')->orWhere('reference', 'like', '%'.strtolower($request->q).'%'));
        }
        if ($request->input('mine') === 'development') {
        $query->where('developer_id', $request->user()->id);
        }
        if ($request->input('mine') === 'qa') {
        $query->where('qa_id', $request->user()->id);
        }
        if ($request->boolean('archived')) {
            abort_unless(PmsAccess::administrator($request->user()), 403);
            $query->onlyTrashed();
        }
        $issues = $query->orderByDesc('updated_at')->paginate(30)->withQueryString();
        $projects = PmProject::query();
        if (! PmsAccess::administrator($request->user())) {
        $projects->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id));
        }
        $projects = $projects->orderBy('name')->get(['id', 'key', 'name']);

        return $request->expectsJson() ? response()->json($issues) : view('pms.issues', compact('issues', 'projects'));
    }

    public function store(Request $request)
    {
        $this->normalizePriority($request);
        $explicitClassification = $request->filled('classification_key');
        if (! $explicitClassification && $request->filled('type')) {
            $legacy = (string) $request->input('type');
            $request->merge(['classification_key' => $legacy === 'Bug' ? 'PDI' : ($legacy === 'Enhancement' ? 'ENH' : 'FTR')]);
        }
        $data = $request->validate($this->rules(true, $explicitClassification));
        $project = PmProject::findOrFail($data['project_id']);
        $category = match ($data['classification_key']) { 'FTR' => 'REQ', 'PDI' => 'PRD', default => $data['classification_key'] };
        abort_unless(PmsAccess::canCreateTicket($request->user(), $category, $project), 403);
        abort_unless(PmsAccess::allows($request->user(), $project, 'edit_issue'), 403);
        abort_if($project->archived_at, 422, 'Archived projects cannot accept new work.');
        $this->validateAssignees($project, $data);
        $this->validateCycle($project, $data['cycle_id'] ?? null);
        if (! PmsAccess::allows($request->user(), $project, 'manage') && (! empty($data['developer_id']) || ! empty($data['qa_id']) || ($data['priority'] ?? 'P3') !== 'P3')) {
        abort(403);
        }
        if (! empty($data['fast_track'])) {
            abort_unless($data['classification_key'] === 'PDI' && PmsAccess::allows($request->user(), $project, 'manage'), 403);
            $data['fast_track_approved_by'] = $request->user()->id;
        }
        $item = DB::transaction(function () use ($request, $project, $data) {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            $sequence = $locked->next_issue_number;
            $key = $locked->key.'-'.$data['classification_key'].'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $locked->next_issue_number++;
            $locked->save();
            $item = new PmWorkItem();
            $item->forceFill(array_merge($data, [
                'project_id' => $project->id, 'issue_key' => $key, 'display_sequence' => $sequence,
                'reference' => \App\Support\PmsTicketReference::next(\App\Support\PmsTicketReference::category($data['classification_key'])),
                'type' => self::CLASSIFICATIONS[$data['classification_key']], 'reporter_id' => $request->user()->id, 'status' => 'Planned',
                'original_cycle_id' => $data['cycle_id'] ?? null,
            ]))->save();
            DB::table('pm_issue_history')->insert(['issue_id' => $item->id, 'actor_id' => $request->user()->id, 'event' => 'created', 'to_status' => 'Planned', 'created_at' => now()]);
            $this->audit($request, 'issue.created', $item);
            $this->notifyAssignment($item, 'developer', $item->developer_id, $request->user()->id);
            $this->notifyAssignment($item, 'QA engineer', $item->qa_id, $request->user()->id);

            return $item;
        });

        return $request->expectsJson() ? response()->json($item, 201) : redirect()->route('pms.issues.show', $item)->with('success', 'Issue created.');
    }

    public function show(Request $request, PmWorkItem $issue)
    {
        abort_unless($issue->project && PmsAccess::allows($request->user(), $issue->project, 'view'), 404);
        $issue->load(['project', 'cycle', 'developer:id,name', 'qa:id,name', 'reporter:id,name']);
        $history = DB::table('pm_issue_history')->where('issue_id', $issue->id)->orderByDesc('id')->limit(100)->get();
        $members = $issue->project->members()->with('pmsProfile')->select('users.id', 'users.name')->orderBy('users.name')->get();
        $cycles = $issue->project->cycles()->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->orderByDesc('start_date')->get(['id', 'cycle_key', 'name']);
        $changeReviews = app(\App\Services\PmsChangeReviewService::class)->history('ticket', $issue->id);

        return $request->expectsJson() ? response()->json(['issue' => $issue, 'history' => $history]) : view('pms.issue', compact('issue', 'history', 'members', 'cycles', 'changeReviews'));
    }

    public function update(Request $request, PmWorkItem $issue)
    {
        $this->normalizePriority($request);
        $project = $issue->project;
        abort_unless($project, 404);
        abort_unless(PmsAccess::allows($request->user(), $project, 'edit_issue'), 403);
        $data = $request->validate($this->rules(false, false));
        $manager = PmsAccess::allows($request->user(), $project, 'manage');
        $analyst = PmsAccess::role($request->user(), $project) === 'Business Analyst'
            && in_array($issue->classification_key, ['REQ', 'ENH', 'EHN'], true);
        if (! $manager && ! $analyst) {
            $role = PmsAccess::role($request->user(), $project);
            $isDeveloper = in_array($role, ['Developer', 'Development Lead'], true) && (int) $issue->developer_id === $request->user()->id;
            $isQa = in_array($role, ['QA Engineer', 'QA Lead'], true) && (int) $issue->qa_id === $request->user()->id;
            $isRelease = $role === 'Release Manager';
            abort_unless($isDeveloper || $isQa || $isRelease, 403);
            $allowed = $isDeveloper ? ['description', 'acceptance_criteria', 'estimate_minutes', 'story_points', 'time_spent_minutes', 'qat_build', 'qat_deployed_at', 'qat_deployment_evidence', 'root_cause', 'workaround', 'fix_version', 'incident_resolved_at', 'post_incident_review'] : ($isQa ? ['qa_result'] : ['production_build', 'production_deployed_at', 'production_evidence']);
            abort_if(array_diff(array_keys($data), array_merge($allowed, ['lock_version'])), 403);
        }
        $this->validateAssignees($project, $data, $issue);
        $this->validateCycle($project, $data['cycle_id'] ?? null);
        if ($analyst) {
            $review = app(\App\Services\PmsChangeReviewService::class)->submit($request, $issue, 'update', $data, 'issue');
            return $request->expectsJson() ? response()->json(['review_id' => $review->id, 'status' => 'pending'], 202)
                : back()->with('success', 'Issue changes submitted for Project Manager review.');
        }
        DB::transaction(function () use ($request, $issue, $data) {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($issue->id);
            if ($locked->lock_version !== (int) $data['lock_version']) {
            throw ValidationException::withMessages(['lock_version' => 'Issue changed. Refresh and retry.']);
            }
            unset($data['lock_version']);
            $before = $locked->only(array_keys($data));
            if (array_key_exists('cycle_id', $data) && $locked->cycle_id && $data['cycle_id'] && (int) $locked->cycle_id !== (int) $data['cycle_id']) {
                $locked->carryover_count++;
            }
            if (array_key_exists('cycle_id', $data) && $data['cycle_id'] && ! $locked->original_cycle_id) {
                $locked->original_cycle_id = $data['cycle_id'];
            }
            $locked->forceFill($data);
            $locked->lock_version++;
            $locked->save();
            $changed = array_keys($locked->getChanges());
            DB::table('pm_issue_history')->insert(['issue_id' => $locked->id, 'actor_id' => $request->user()->id, 'event' => 'updated', 'changes' => json_encode(['before' => $before, 'after' => $locked->only(array_keys($data))]), 'created_at' => now()]);
            $this->audit($request, 'issue.updated', $locked, ['fields' => array_keys($data)]);
            if (in_array('developer_id', $changed, true)) {
            $this->notifyAssignment($locked, 'developer', $locked->developer_id, $request->user()->id);
            }
            if (in_array('qa_id', $changed, true)) {
            $this->notifyAssignment($locked, 'QA engineer', $locked->qa_id, $request->user()->id);
            }
        });

        return $request->expectsJson() ? response()->json($issue->fresh()) : back()->with('success', 'Issue updated.');
    }

    public function transition(Request $request, PmWorkItem $issue, PmsWorkflow $workflow)
    {
        $data = $request->validate(['status' => ['required', Rule::in(PmsWorkflow::STATUSES)], 'reason' => 'nullable|string|max:5000', 'dependency_owner_id' => 'nullable|integer|exists:users,id', 'lock_version' => 'required|integer|min:0']);
        $updated = $workflow->transition($issue, $request->user(), $data);

        return $request->expectsJson() ? response()->json($updated) : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, PmWorkItem $issue)
    {
        app(TicketController::class)->destroy($request, $issue);

        return $request->expectsJson() ? response()->json(['archived' => true]) : redirect()->route('pms.issues.index')->with('success', 'Issue archived.');
    }

    public function restore(Request $request, int $id)
    {
        app(TicketController::class)->restore($request, $id);
        $issue = PmWorkItem::findOrFail($id);

        return $request->expectsJson() ? response()->json($issue) : redirect()->route('pms.issues.show', $issue)->with('success', 'Issue restored.');
    }

    private function rules(bool $creating, bool $strictClassification): array
    {
        $rules = [
            'summary' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:50000', 'acceptance_criteria' => 'nullable|string|max:20000',
            'priority' => ['sometimes', Rule::exists('pm_priorities', 'code')->where('active', true)],
            'severity' => 'nullable|string|max:20', 'developer_id' => 'nullable|integer|exists:users,id',
            'qa_id' => 'nullable|integer|exists:users,id', 'due_date' => 'nullable|date',
            'cycle_id' => 'nullable|integer|exists:pm_cycles,id', 'story_points' => 'nullable|numeric|min:0|max:999999.99',
            'estimate_minutes' => 'nullable|integer|min:0|max:10000000', 'time_spent_minutes' => 'nullable|integer|min:0|max:10000000',
            'qat_build' => 'nullable|string|max:255', 'qat_deployed_at' => 'nullable|date',
            'qat_deployment_evidence' => 'nullable|string|max:10000', 'qa_result' => 'nullable|string|max:10000',
            'production_build' => 'nullable|string|max:255', 'production_deployed_at' => 'nullable|date',
            'production_evidence' => 'nullable|string|max:10000',
            'target_release' => 'nullable|string|max:255', 'business_objective' => 'nullable|string|max:20000',
            'business_justification' => 'nullable|string|max:20000', 'expected_value' => 'nullable|string|max:20000',
            'current_behavior' => 'nullable|string|max:20000', 'proposed_behavior' => 'nullable|string|max:20000',
            'affected_component' => 'nullable|string|max:255', 'backward_compatibility' => 'nullable|string|max:20000',
            'detected_at' => 'nullable|date', 'production_environment' => 'nullable|string|max:255',
            'affected_module' => 'nullable|string|max:255', 'business_impact' => 'nullable|string|max:20000',
            'affected_users_estimate' => 'nullable|integer|min:0|max:1000000000', 'reproduction_steps' => 'nullable|string|max:50000',
            'expected_result' => 'nullable|string|max:50000', 'actual_result' => 'nullable|string|max:50000',
            'root_cause' => 'nullable|string|max:50000', 'workaround' => 'nullable|string|max:20000',
            'fix_version' => 'nullable|string|max:255', 'hotfix_required' => 'nullable|boolean',
            'fast_track' => 'nullable|boolean', 'fast_track_reason' => 'nullable|required_if:fast_track,1|string|max:5000',
            'incident_resolved_at' => 'nullable|date', 'post_incident_review' => 'nullable|string|max:50000',
        ];
        if ($creating) {
            $rules['project_id'] = 'required|integer|exists:pm_projects,id';
            $rules['summary'] = 'required|string|max:255';
            $rules['classification_key'] = ['required', Rule::in(array_keys(self::CLASSIFICATIONS))];
            $rules['priority'] = ['required', Rule::exists('pm_priorities', 'code')->where('active', true)];
            if ($strictClassification && request('classification_key') === 'FTR') {
                $rules['business_objective'] = 'required|string|max:20000';
                $rules['acceptance_criteria'] = 'required|string|max:20000';
            }
            if ($strictClassification && request('classification_key') === 'ENH') {
                $rules['current_behavior'] = 'required|string|max:20000';
                $rules['proposed_behavior'] = 'required|string|max:20000';
                $rules['acceptance_criteria'] = 'required|string|max:20000';
            }
            if ($strictClassification && request('classification_key') === 'PDI') {
                $rules['detected_at'] = 'required|date';
                $rules['production_environment'] = 'required|string|max:255';
                $rules['business_impact'] = 'required|string|max:20000';
                $rules['actual_result'] = 'required|string|max:50000';
            }
        } else {
            $rules['lock_version'] = 'required|integer|min:0';
        }

        return $rules;
    }

    private function normalizePriority(Request $request): void
    {
        if ($request->exists('priority')) {
            $request->merge(['priority' => match ($request->input('priority')) {
                'Low' => 'P4', 'Normal' => 'P3', 'High' => 'P2', 'Critical' => 'P1',
                default => $request->input('priority'),
            }]);
        }
    }

    private function validateAssignees(PmProject $project, array $data, ?PmWorkItem $current = null): void
    {
        foreach (['developer_id', 'qa_id'] as $field) {
            if (! array_key_exists($field, $data) || empty($data[$field])
                || $current && (int) $current->{$field} === (int) $data[$field]) continue;
            PmsPosition::validateAssignment((int) $data[$field], $field === 'qa_id' ? 'qa' : 'developer', $project->id);
        }
    }

    private function validateCycle(PmProject $project, mixed $cycleId): void
    {
        if ($cycleId && ! $project->cycles()->whereKey($cycleId)->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->exists()) {
            throw ValidationException::withMessages(['cycle_id' => 'Choose a planned or active cycle from this project.']);
        }
    }

    private function audit(Request $request, string $action, PmWorkItem $item, array $details = []): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'issue', 'subject_id' => $item->id,
            'details' => json_encode($details + ['reference' => $item->reference, 'title' => $item->summary,
                'project_id' => $item->project_id, 'actor_name' => $request->user()->name]), 'created_at' => now()]);
    }

    private function notifyAssignment(PmWorkItem $item, string $assignment, ?int $userId, int $actorId): void
    {
        if (! $userId || $userId === $actorId) {
        return;
        }
        AppNotificationService::notifyUser(
            $userId,
            "Assigned to {$item->reference}",
            "You are the {$assignment} for {$item->reference}: {$item->summary}",
            route('pms.issues.show', $item),
            'project_management',
            $actorId
        );
    }
}
