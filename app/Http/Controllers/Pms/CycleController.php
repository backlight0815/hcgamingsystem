<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\PmsCycleService;
use App\Services\PmsCycleProgress;
use App\Support\PmsAccess;
use App\Support\PmsGlobalAccess;
use App\Support\PmsModule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CycleController extends Controller
{
    public const STATUSES = ['Draft', 'Planned', 'Active', 'Internal Testing', 'QAT', 'On Hold', 'Closed', 'Cancelled'];
    private const STATUS_TRANSITIONS = [
        'Draft' => ['Planned', 'Cancelled'],
        'Planned' => ['Active', 'On Hold', 'Cancelled'],
        'Active' => ['Internal Testing', 'On Hold', 'Cancelled'],
        'Internal Testing' => ['Active', 'QAT', 'On Hold', 'Cancelled'],
        'QAT' => ['Internal Testing', 'Closed', 'On Hold', 'Cancelled'],
        'On Hold' => ['Planned', 'Active', 'Internal Testing', 'QAT', 'Cancelled'],
        'Closed' => [], 'Cancelled' => [],
    ];

    public static function allowedTransitions(PmCycle $cycle, bool $administrator = false): array
    {
        if ($administrator && in_array($cycle->status, ['Closed', 'Cancelled'], true)) return ['Planned'];
        $options = self::STATUS_TRANSITIONS[$cycle->status] ?? [];
        if ($cycle->status === 'On Hold') {
            $last = DB::table('pm_cycle_history')->where('cycle_id', $cycle->id)
                ->where('event', 'status_changed')->orderByDesc('id')->first();
            $resume = $last ? json_decode($last->before, true)['status'] ?? null : null;
            $options = array_values(array_filter($options, fn ($status) => $status === 'Cancelled' || $status === $resume));
        }

        return $options;
    }

    public function changeStatus(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'reason' => 'nullable|string|max:2000',
            'confirm_unresolved' => 'nullable|boolean',
            'lock_version' => 'required|integer|min:0',
        ]);
        $updated = DB::transaction(function () use ($request, $cycle, $data): PmCycle {
            $locked = PmCycle::lockForUpdate()->findOrFail($cycle->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This cycle changed. Refresh and try again.']);
            }
            $previous = $locked->status;
            $next = $data['status'];
            $override = $request->user()->isGlobalSuperAdmin() && in_array($previous, ['Closed', 'Cancelled'], true)
                && $next === 'Planned';
            if (! in_array($next, self::allowedTransitions($locked, $request->user()->isGlobalSuperAdmin()), true)) {
                throw ValidationException::withMessages(['status' => 'This cycle status transition is not permitted.']);
            }
            if (in_array($next, ['On Hold', 'Cancelled', 'Closed'], true) || $override) {
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Explain this status change.']);
                }
            }
            if ($previous === 'On Hold' && $next !== 'Cancelled') {
                $last = DB::table('pm_cycle_history')->where('cycle_id', $cycle->id)
                    ->where('event', 'status_changed')->orderByDesc('id')->first();
                $resume = $last ? json_decode($last->before, true)['status'] ?? null : null;
                if ($resume !== $next) {
                    throw ValidationException::withMessages(['status' => 'Resume the stage that was active before the hold.']);
                }
            }
            $unresolved = $locked->issues()->whereNotIn('status', ['Production Done', 'QAT Done', 'Completed', 'Cancelled'])->count();
            if ($next === 'Closed' && $unresolved > 0 && ! ($data['confirm_unresolved'] ?? false)) {
                throw ValidationException::withMessages(['confirm_unresolved' => "{$unresolved} tickets remain unresolved. Confirm before closing the cycle."]);
            }
            $locked->status = $next;
            $locked->updated_by = $request->user()->id;
            $locked->lock_version++;
            if ($previous === 'Planned' && $next === 'Active' && ! $locked->scope_baselined_at) {
                $locked->scope_baselined_at = now();
            }
            if ($next === 'Closed') $locked->actual_end_date = today();
            if ($override) $locked->actual_end_date = null;
            $locked->save();
            if ($previous === 'Planned' && $next === 'Active') {
                foreach ($locked->issues as $issue) {
                    DB::table('pm_cycle_scope_baselines')->updateOrInsert(
                        ['cycle_id' => $locked->id, 'issue_id' => $issue->id],
                        ['planned_units' => $issue->story_points ?? ($issue->estimate_minutes ?? 1),
                            'unit_basis' => $issue->story_points !== null ? 'story_points' : ($issue->estimate_minutes !== null ? 'minutes' : 'issue_count'),
                            'created_at' => now()]
                    );
                }
            }
            $this->history($locked, $request->user()->id, 'status_changed',
                ['status' => $previous], ['status' => $next, 'unresolved_tickets' => $unresolved], $data['reason'] ?? null);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'cycle.status_changed',
                'subject_type' => 'cycle', 'subject_id' => $locked->id,
                'details' => json_encode(['from' => $previous, 'to' => $next, 'unresolved_tickets' => $unresolved]),
                'created_at' => now(),
            ]);

            return $locked;
        });

        return $request->expectsJson() ? response()->json($updated) : back()->with('success', 'Cycle status updated.');
    }

    public function index(Request $request)
    {
        $visibleIds = $this->visibleProjectIds($request);
        $scope = PmCycle::query()->whereIn('project_id', $visibleIds);
        $summary = [
            'total' => (clone $scope)->count(),
            'active' => (clone $scope)->whereIn('status', ['Active', 'Internal Testing', 'QAT'])->count(),
            'upcoming' => (clone $scope)->where('status', 'Planned')->whereDate('start_date', '>', today())->count(),
            'completed' => (clone $scope)->where('status', 'Closed')->count(),
            'overdue' => (clone $scope)->whereNotIn('status', ['Closed', 'Cancelled'])->whereDate('target_end_date', '<', today())->count(),
        ];
        $query = (clone $scope)->with(['project:id,key,name', 'owner:id,name', 'issues',
            'phases:id,cycle_id,phase_key,name,completion_percent,planned_start_date,planned_end_date'])
            ->withCount(['issues', 'issues as completed_issues_count' => fn ($q) => $q->whereIn('status', ['Production Done', 'Completed'])]);
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('cycle_key', 'like', '%'.$term.'%'));
        }
        if ($request->filled('project')) {
            $query->where('project_id', $request->integer('project'));
        }
        if (in_array($request->status, self::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) $query->whereDate('start_date', '>=', $request->validate(['date_from' => 'date'])['date_from']);
        if ($request->filled('date_to')) $query->whereDate('target_end_date', '<=', $request->validate(['date_to' => 'date'])['date_to']);
        if ($request->filled('owner')) $query->where('owner_id', $request->integer('owner'));
        $sort = in_array($request->input('sort'), ['newest', 'deadline', 'progress', 'name'], true) ? $request->input('sort') : 'newest';
        if ($sort === 'progress') {
            $query->orderByRaw('(select coalesce(avg(completion_percent), 0) from pm_cycle_phases where pm_cycle_phases.cycle_id = pm_cycles.id) desc');
        } elseif ($sort === 'deadline') {
            $query->orderBy('target_end_date');
        } elseif ($sort === 'name') {
            $query->orderBy('name');
        } else {
            $query->orderByDesc('start_date');
        }
        $cycles = $query->orderByDesc('id')->paginate(24)->withQueryString();
        $progressService = app(PmsCycleProgress::class);
        $stageProgress = $cycles->getCollection()->mapWithKeys(fn ($cycle) => [$cycle->id => $progressService->forCycle($cycle)])->all();
        $projects = PmProject::whereIn('id', $visibleIds)->whereNull('archived_at')->orderBy('name')->get(['id', 'key', 'name']);
        $owners = User::whereIn('id', (clone $scope)->whereNotNull('owner_id')->select('owner_id'))->orderBy('name')->get(['id', 'name']);
        $settings = PmsModule::settings();
        $viewMode = in_array($request->input('view'), ['cards', 'table'], true) ? $request->input('view') : 'cards';
        $organizationCycles = PmGlobalCycle::whereNull('archived_at')->orderByDesc('start_date')->get(['id', 'cycle_key', 'name', 'status']);

        return $request->expectsJson() ? response()->json(['cycles' => $cycles, 'stage_progress' => $stageProgress, 'organization_cycles' => $organizationCycles])
            : view('pms.cycles', compact('cycles', 'projects', 'settings', 'owners', 'summary', 'viewMode', 'organizationCycles', 'stageProgress'));
    }

    public function store(Request $request, PmsCycleService $planner)
    {
        // Legacy API compatibility: this creates a project execution plan only.
        // New master cycles are created exclusively by GlobalCycleController.
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        if ($request->filled('project_id')) {
            abort_unless(PmsAccess::allows($request->user(), PmProject::findOrFail($request->input('project_id')), 'manage_cycle'), 403);
        }
        $data = $this->validated($request, $planner);
        $project = PmProject::findOrFail($data['project_id']);
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage_cycle'), 403);
        if (! empty($data['global_cycle_id']) && ! DB::table('pm_global_cycle_projects')
            ->where('cycle_id', $data['global_cycle_id'])->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['global_cycle_id' => 'Assign the project to this organization cycle first.']);
        }
        if (! empty($data['owner_id'])) {
            abort_unless($project->members()->where('users.id', $data['owner_id'])->exists(), 422, 'Choose a member of this project as cycle owner.');
        }
        $cycle = $this->createCycle($request, $project, $data, $planner);

        return $request->expectsJson() ? response()->json($cycle->load('phases'), 201) : redirect()->route('pms.cycles.show', $cycle)->with('success', 'Development cycle created.');
    }

    public function show(Request $request, PmCycle $cycle, PmsCycleService $planner)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'view'), 403);
        $cycle->load(['project.members:id,name', 'globalCycle:id,cycle_key,name', 'owner:id,name', 'phases', 'issues.developer:id,name', 'issues.qa:id,name']);
        $analytics = $planner->analytics($cycle);
        $history = DB::table('pm_cycle_history')->leftJoin('users', 'users.id', '=', 'pm_cycle_history.actor_id')
            ->where('pm_cycle_history.cycle_id', $cycle->id)->orderByDesc('pm_cycle_history.id')->limit(100)
            ->get(['pm_cycle_history.*', 'users.name as actor_name']);
        $unresolvedTickets = $cycle->issues->whereNotIn('status', ['Production Done', 'QAT Done', 'Completed', 'Cancelled'])->count();
        $availableIssues = $cycle->project->issues()->whereNull('cycle_id')->orderBy('issue_key')->get(['id', 'reference', 'issue_key', 'summary', 'classification_key', 'story_points', 'estimate_minutes']);
        $nextCycles = $cycle->project->cycles()->where('id', '<>', $cycle->id)->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->orderBy('start_date')->get(['id', 'cycle_key', 'name']);

        return $request->expectsJson()
            ? response()->json(['cycle' => $cycle, 'analytics' => $analytics, 'history' => $history])
            : view('pms.cycle', compact('cycle', 'analytics', 'history', 'availableIssues', 'nextCycles', 'unresolvedTickets'));
    }

    public function update(Request $request, PmCycle $cycle, PmsCycleService $planner)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'edit_cycle'), 403);
        abort_if(in_array($cycle->status, ['Closed', 'Cancelled'], true), 422, 'Closed or cancelled cycles cannot be rescheduled.');
        $data = $this->validated($request, $planner, $cycle);
        if (isset($data['status']) && $data['status'] !== $cycle->status) {
            throw ValidationException::withMessages(['status' => 'Use the cycle status control to change status.']);
        }
        abort_unless((int) $data['project_id'] === (int) $cycle->project_id, 403);
        if (! empty($data['owner_id'])) {
            abort_unless($cycle->project->members()->where('users.id', $data['owner_id'])->exists(), 422, 'Choose a member of this project as cycle owner.');
        }
        $data['lock_version'] = $request->validate(['lock_version' => 'required|integer|min:0'])['lock_version'];
        $scheduleFields = ['start_date', 'target_end_date', 'week_mode', 'development_days', 'internal_testing_days', 'qat_days', 'buffer_days'];
        $scheduleChanged = collect($scheduleFields)->contains(function ($field) use ($cycle, $data) {
            $previous = in_array($field, ['start_date', 'target_end_date'], true)
                ? $cycle->{$field}?->toDateString() : $cycle->{$field};
            return (string) $previous !== (string) $data[$field];
        });
        if ($scheduleChanged && blank($request->input('schedule_change_reason'))) {
            throw ValidationException::withMessages(['schedule_change_reason' => 'Explain every cycle schedule change. The original baseline is retained.']);
        }

        DB::transaction(function () use ($request, $cycle, $data, $planner, $scheduleChanged): void {
            $locked = PmCycle::lockForUpdate()->findOrFail($cycle->id);
            if ((int) $data['lock_version'] !== (int) $locked->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'This cycle changed. Refresh and try again.']);
            }
            unset($data['lock_version']);
            $before = $locked->only(array_keys($data));
            $locked->forceFill($data + ['updated_by' => $request->user()->id, 'schedule_change_reason' => $scheduleChanged ? $request->input('schedule_change_reason') : $locked->schedule_change_reason]);
            $locked->lock_version++;
            $locked->save();
            if ($scheduleChanged) {
                $existing = $locked->phases->keyBy('phase_key');
                foreach ($planner->schedule($data, PmsModule::settings()) as $planned) {
                    $phase = $existing->get($planned['phase_key']);
                    if ($phase) {
                        $phase->update(collect($planned)->only([
                            'name', 'sort_order', 'planned_start_date', 'planned_end_date',
                        ])->all());
                    } else {
                        $locked->phases()->create($planned);
                    }
                }
            }
            $this->history($locked, $request->user()->id, 'updated', $before, $locked->only(array_keys($data)), $request->input('schedule_change_reason'));
        });

        return $request->expectsJson() ? response()->json($cycle->fresh()->load('phases')) : back()->with('success', 'Cycle updated and schedule history retained.');
    }

    public function duplicate(Request $request, PmCycle $cycle, PmsCycleService $planner)
    {
        // Duplication is retained for historical API clients; it never creates a master cycle.
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'start_date' => 'required|date']);
        $duration = $cycle->start_date->diffInDays($cycle->target_end_date);
        $copy = $cycle->only(['project_id', 'goal', 'owner_id', 'week_mode', 'template_weeks', 'development_days', 'internal_testing_days', 'qat_days', 'buffer_days']);
        $copy['name'] = $data['name'];
        $copy['start_date'] = $data['start_date'];
        $copy['target_end_date'] = Carbon::parse($data['start_date'])->addDays($duration)->toDateString();
        $copy['status'] = 'Planned';
        $newCycle = $this->createCycle($request, $cycle->project, $copy, $planner);

        return $request->expectsJson() ? response()->json($newCycle->load('phases'), 201) : redirect()->route('pms.cycles.show', $newCycle)->with('success', 'Cycle duplicated without copying work items.');
    }

    public function assignIssue(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'edit_cycle'), 403);
        abort_if(in_array($cycle->status, ['Closed', 'Cancelled'], true), 422, 'Work cannot be added to a closed cycle.');
        $data = $request->validate(['issue_id' => 'required|integer|exists:pm_work_items,id']);
        $issue = PmWorkItem::findOrFail($data['issue_id']);
        abort_unless((int) $issue->project_id === (int) $cycle->project_id, 422, 'The work item belongs to another project.');
        DB::transaction(function () use ($request, $cycle, $issue): void {
            $issue = PmWorkItem::lockForUpdate()->findOrFail($issue->id);
            abort_unless((int) $issue->project_id === (int) $cycle->project_id, 422);
            $previous = $issue->cycle_id;
            if ($cycle->global_cycle_id && $issue->global_cycle_id
                && (int) $issue->global_cycle_id !== (int) $cycle->global_cycle_id) {
                throw ValidationException::withMessages(['issue_id' => 'This ticket belongs to another organization cycle.']);
            }
            if ($previous && (int) $previous !== (int) $cycle->id) {
                $issue->carryover_count++;
            }
            $issue->original_cycle_id ??= $cycle->id;
            $issue->cycle_id = $cycle->id;
            if ($cycle->global_cycle_id && ! $issue->global_cycle_id) {
                $issue->global_cycle_id = $cycle->global_cycle_id;
                $issue->original_global_cycle_id ??= $cycle->global_cycle_id;
            }
            $issue->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $issue->id, 'actor_id' => $request->user()->id, 'event' => 'cycle_changed',
                'reason' => $request->input('reason'), 'changes' => json_encode(['from_cycle_id' => $previous, 'to_cycle_id' => $cycle->id]), 'created_at' => now(),
            ]);
            if ($cycle->global_cycle_id) {
                DB::table('pm_issue_history')->insert([
                    'issue_id' => $issue->id, 'actor_id' => $request->user()->id, 'event' => 'organization_cycle_changed',
                    'reason' => $request->input('reason'),
                    'changes' => json_encode(['to_cycle_id' => $cycle->global_cycle_id, 'project_cycle_id' => $cycle->id]),
                    'created_at' => now(),
                ]);
            }
            $this->history($cycle, $request->user()->id, 'work_added', ['cycle_id' => $previous], ['issue_id' => $issue->id], $request->input('reason'));
        });

        return $request->expectsJson() ? response()->json($issue->fresh()) : back()->with('success', 'Work item added to the cycle.');
    }

    public function removeIssue(Request $request, PmCycle $cycle, PmWorkItem $issue)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'edit_cycle'), 403);
        abort_unless((int) $issue->cycle_id === (int) $cycle->id, 404);
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $issue->cycle_id = null;
        $issue->save();
        DB::table('pm_issue_history')->insert([
            'issue_id' => $issue->id, 'actor_id' => $request->user()->id, 'event' => 'cycle_removed',
            'reason' => $data['reason'], 'changes' => json_encode(['from_cycle_id' => $cycle->id]), 'created_at' => now(),
        ]);
        $this->history($cycle, $request->user()->id, 'work_removed', ['issue_id' => $issue->id], null, $data['reason']);

        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Work item returned to the backlog.');
    }

    public function updatePhase(Request $request, PmCycle $cycle, int $phaseId)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'edit_cycle'), 403);
        abort_if(in_array($cycle->status, ['Closed', 'Cancelled'], true), 422, 'Closed or cancelled cycles cannot be edited.');
        $phase = $cycle->phases()->findOrFail($phaseId);
        $data = $request->validate([
            'actual_start_date' => 'nullable|date', 'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'completion_percent' => 'required|integer|min:0|max:100', 'signed_off' => 'nullable|boolean',
            'planned_start_date' => 'sometimes|date', 'planned_end_date' => 'sometimes|date',
            'schedule_reason' => 'nullable|string|max:2000',
        ]);
        $plannedStart = \Carbon\Carbon::parse($data['planned_start_date'] ?? $phase->planned_start_date);
        $plannedEnd = \Carbon\Carbon::parse($data['planned_end_date'] ?? $phase->planned_end_date);
        if ($plannedStart->lt($cycle->start_date) || $plannedEnd->gt($cycle->target_end_date) || $plannedEnd->lt($plannedStart)) {
            throw ValidationException::withMessages(['planned_end_date' => 'Keep the phase schedule within the cycle dates, with finish on or after start.']);
        }
        if (($plannedStart->toDateString() !== $phase->planned_start_date->toDateString()
                || $plannedEnd->toDateString() !== $phase->planned_end_date->toDateString())
            && blank($data['schedule_reason'] ?? null)) {
            throw ValidationException::withMessages(['schedule_reason' => 'Explain the planned schedule change.']);
        }
        $before = $phase->only(array_keys($data));
        $reason = $data['schedule_reason'] ?? null;
        unset($data['schedule_reason']);
        if (array_key_exists('signed_off', $data)) {
            abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
            $data['signed_off_by'] = $request->boolean('signed_off') ? $request->user()->id : null;
            $data['signed_off_at'] = $request->boolean('signed_off') ? now() : null;
        }
        $phase->update($data);
        $this->history($cycle, $request->user()->id, 'phase_updated', $before, $phase->fresh()->toArray(), $reason);

        return $request->expectsJson() ? response()->json($phase->fresh()) : back()->with('success', 'Phase progress updated.');
    }

    public function start(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        abort_unless($cycle->status === 'Planned', 422, 'Only a planned cycle can start.');
        DB::transaction(function () use ($request, $cycle): void {
            $cycle->update(['status' => 'Active', 'scope_baselined_at' => now(), 'updated_by' => $request->user()->id]);
            foreach ($cycle->issues as $issue) {
                DB::table('pm_cycle_scope_baselines')->updateOrInsert(
                    ['cycle_id' => $cycle->id, 'issue_id' => $issue->id],
                    ['planned_units' => $issue->story_points ?? ($issue->estimate_minutes ?? 1), 'unit_basis' => $issue->story_points !== null ? 'story_points' : ($issue->estimate_minutes !== null ? 'minutes' : 'issue_count'), 'created_at' => now()]
                );
            }
            $this->history($cycle, $request->user()->id, 'started', ['status' => 'Planned'], ['status' => 'Active'], null);
        });

        return $request->expectsJson() ? response()->json($cycle->fresh()) : back()->with('success', 'Cycle started and scope baseline recorded.');
    }

    public function close(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        abort_unless(in_array($cycle->status, ['Planned', 'Active'], true), 422, 'This cycle is already closed or cancelled.');
        $unfinished = $cycle->issues()->whereNotIn('status', ['Production Done', 'Cancelled'])->get();
        $rules = ['unfinished_action' => ['nullable', Rule::in(['carry', 'backlog', 'cancel', 'blocked'])], 'target_cycle_id' => 'nullable|integer|exists:pm_cycles,id', 'reason' => 'nullable|string|max:5000'];
        $data = $request->validate($rules);
        if ($unfinished->isNotEmpty() && empty($data['unfinished_action'])) {
            throw ValidationException::withMessages(['unfinished_action' => 'Choose how to handle every unfinished item before closing the cycle.']);
        }
        if ($unfinished->isNotEmpty() && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain the unfinished-work decision.']);
        }

        DB::transaction(function () use ($request, $cycle, $unfinished, $data): void {
            if ($unfinished->isNotEmpty()) {
                if ($data['unfinished_action'] === 'carry') {
                    $target = PmCycle::where('project_id', $cycle->project_id)->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->findOrFail($data['target_cycle_id'] ?? 0);
                    PmWorkItem::whereIn('id', $unfinished->pluck('id'))->update(['cycle_id' => $target->id, 'carryover_count' => DB::raw('carryover_count + 1')]);
                } elseif ($data['unfinished_action'] === 'backlog') {
                    PmWorkItem::whereIn('id', $unfinished->pluck('id'))->update(['cycle_id' => null]);
                } elseif ($data['unfinished_action'] === 'cancel') {
                    PmWorkItem::whereIn('id', $unfinished->pluck('id'))->update([
                        'status' => 'Cancelled', 'status_code' => \App\Support\PmsStatusRegistry::code('ticket', 'Cancelled'),
                    ]);
                } else {
                    PmWorkItem::whereIn('id', $unfinished->pluck('id'))->update(['dependency_reason' => $data['reason']]);
                }
                foreach ($unfinished as $issue) {
                    DB::table('pm_issue_history')->insert([
                        'issue_id' => $issue->id, 'actor_id' => $request->user()->id, 'event' => 'cycle_close_disposition',
                        'reason' => $data['reason'], 'changes' => json_encode(['action' => $data['unfinished_action'], 'target_cycle_id' => $data['target_cycle_id'] ?? null]), 'created_at' => now(),
                    ]);
                }
            }
            $cycle->update(['status' => 'Closed', 'actual_end_date' => today(), 'updated_by' => $request->user()->id]);
            $this->history($cycle, $request->user()->id, 'closed', null, ['unfinished_action' => $data['unfinished_action'] ?? null, 'unfinished_count' => $unfinished->count()], $data['reason'] ?? null);
        });

        return $request->expectsJson() ? response()->json($cycle->fresh()) : back()->with('success', 'Cycle closed with unfinished work accounted for.');
    }

    public function cancel(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        $before = $cycle->status;
        $cycle->update(['status' => 'Cancelled', 'updated_by' => $request->user()->id]);
        $this->history($cycle, $request->user()->id, 'cancelled', ['status' => $before], ['status' => 'Cancelled'], $data['reason']);

        return $request->expectsJson() ? response()->json($cycle) : back()->with('success', 'Cycle cancelled; its work-item history is retained.');
    }

    public function destroy(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::allows($request->user(), $cycle->project, 'manage_cycle'), 403);
        $data = $request->validate(['confirmation' => ['required', Rule::in([$cycle->cycle_key])]]);
        DB::transaction(function () use ($request, $cycle, $data): void {
            $cycle->issues()->update(['cycle_id' => null]);
            $cycle->releases()->update(['cycle_id' => null]);
            $this->history($cycle, $request->user()->id, 'deleted', null, ['confirmation' => $data['confirmation']], null);
            $cycle->forceFill(['deleted_by' => $request->user()->id])->save();
            $cycle->delete();
        });

        return $request->expectsJson() ? response()->json(['deleted' => true]) : redirect()->route('pms.cycles.index')->with('success', 'Cycle deleted; its work items returned to the backlog.');
    }

    private function validated(Request $request, PmsCycleService $planner, ?PmCycle $cycle = null): array
    {
        $data = $request->validate([
            'project_id' => $cycle ? 'sometimes|integer|exists:pm_projects,id' : 'required|integer|exists:pm_projects,id',
            'global_cycle_id' => $cycle ? 'prohibited' : 'nullable|integer|exists:pm_global_cycles,id',
            'name' => 'required|string|max:255', 'goal' => 'nullable|string|max:10000', 'owner_id' => 'nullable|integer|exists:users,id',
            'status' => ['sometimes', Rule::in(['Draft', 'Planned', 'Active'])], 'week_mode' => ['required', Rule::in(['calendar', 'working'])],
            'template_weeks' => 'nullable|integer|in:4,5,6', 'start_date' => 'required|date', 'target_end_date' => 'nullable|date|after_or_equal:start_date',
            'development_days' => 'nullable|integer|min:1|max:180', 'internal_testing_days' => 'nullable|integer|min:1|max:180',
            'qat_days' => 'nullable|integer|min:1|max:180', 'buffer_days' => 'nullable|integer|min:0|max:180',
        ]);
        $data['project_id'] ??= $cycle?->project_id;
        $data['status'] ??= $cycle?->status ?? 'Planned';
        if (! empty($data['template_weeks'])) {
            $data['target_end_date'] ??= $planner->targetEnd($data['start_date'], (int) $data['template_weeks'], $data['week_mode'], PmsModule::settings())->toDateString();
            $defaults = $planner->template($data['week_mode'], (int) $data['template_weeks'], PmsModule::settings());
            foreach ($defaults as $field => $value) {
                $data[$field] ??= $value;
            }
        }
        foreach (['target_end_date', 'development_days', 'internal_testing_days', 'qat_days'] as $required) {
            if (! isset($data[$required])) {
                throw ValidationException::withMessages([$required => 'This field is required for a custom cycle.']);
            }
        }
        $data['buffer_days'] ??= 0;
        $planner->schedule($data, PmsModule::settings());

        return $data;
    }

    private function createCycle(Request $request, PmProject $project, array $data, PmsCycleService $planner): PmCycle
    {
        return DB::transaction(function () use ($request, $project, $data, $planner): PmCycle {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            $number = $locked->next_cycle_number++;
            $locked->save();
            $cycle = new PmCycle();
            $cycle->forceFill($data + [
                'cycle_number' => $number, 'cycle_key' => $locked->key.'-CYCLE-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'baseline_start_date' => $data['start_date'], 'baseline_end_date' => $data['target_end_date'],
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ])->save();
            $cycle->phases()->createMany($planner->schedule($data, PmsModule::settings()));
            $this->history($cycle, $request->user()->id, 'created', null, $cycle->only(['cycle_key', 'start_date', 'target_end_date', 'week_mode']), null);

            return $cycle;
        });
    }

    private function history(PmCycle $cycle, int $actorId, string $event, mixed $before, mixed $after, ?string $reason): void
    {
        DB::table('pm_cycle_history')->insert([
            'cycle_id' => $cycle->id, 'actor_id' => $actorId, 'event' => $event,
            'before' => $before === null ? null : json_encode($before), 'after' => $after === null ? null : json_encode($after),
            'reason' => $reason, 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $actorId, 'action' => 'cycle.'.$event, 'subject_type' => 'cycle', 'subject_id' => $cycle->id,
            'details' => json_encode(['reason' => $reason]), 'created_at' => now(),
        ]);
    }

    private function visibleProjectIds(Request $request)
    {
        return PmsAccess::administrator($request->user())
            ? PmProject::query()->whereNull('archived_at')->select('id')
            : DB::table('pm_project_members')->where('user_id', $request->user()->id)->select('project_id');
    }
}
