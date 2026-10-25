<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\PmsGlobalCycleService;
use App\Services\PmsBlockers;
use App\Support\PmsAccess;
use App\Support\PmsGlobalAccess;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GlobalCycleController extends Controller
{
    public function index(Request $request)
    {
        $settings = DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->first();
        $today = now($settings?->timezone ?: config('app.timezone'))->startOfDay();
        $visibleIds = PmsGlobalAccess::visibleProjectIds($request->user());
        $query = PmGlobalCycle::withCount([
            'tickets' => fn ($q) => $q->where(fn ($scope) => $scope->whereIn('project_id', $visibleIds)
                ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id))),
            'projects' => fn ($q) => $q->whereIn('pm_projects.id', $visibleIds),
        ])->with('owner:id,name');
        if (in_array($request->input('view'), ['current', 'upcoming', 'completed'], true)) {
            match ($request->input('view')) {
                'current' => $query->whereDate('start_date', '<=', $today->toDateString())->whereDate('end_date', '>=', $today->toDateString()),
                'upcoming' => $query->whereDate('start_date', '>', $today->toDateString()),
                'completed' => $query->whereIn('status', ['Completed', 'Delayed', 'Cancelled']),
            };
        }
        $cycles = $query->orderByDesc('start_date')->paginate(18)->withQueryString();
        $current = PmGlobalCycle::with(['stages', 'owner:id,name'])->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())->orderByDesc('start_date')->first();
        $ticketScope = PmWorkItem::where(fn ($q) => $q->whereIn('project_id', $visibleIds)
            ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id)));
        $activeProjects = PmProject::whereIn('id', $visibleIds)->whereNull('archived_at')->whereNotIn('status', ['Completed', 'Cancelled'])->count();
        $summary = [
            'active_projects' => $activeProjects,
            'open_tickets' => (clone $ticketScope)->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
            'qat' => (clone $ticketScope)->where('classification_key', 'QAT')->count(),
            'req' => (clone $ticketScope)->where('classification_key', 'REQ')->count(),
            'enh' => (clone $ticketScope)->whereIn('classification_key', ['ENH', 'EHN'])->count(),
            'prd' => (clone $ticketScope)->where('classification_key', 'PRD')->count(),
            'critical' => (clone $ticketScope)->whereIn('priority', ['P0', 'P1'])->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
            'overdue' => (clone $ticketScope)->whereDate('due_date', '<', $today->toDateString())->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
        ];
        $canManage = PmsGlobalAccess::manageCycles($request->user());
        $owners = $canManage ? User::whereIn('id', DB::table('pms_profiles')->where('status', 'active')->select('user_id'))
            ->orderBy('name')->get(['id', 'name']) : collect();

        return $request->expectsJson() ? response()->json(compact('cycles', 'current', 'summary', 'settings'))
            : view('pms.global-cycles', compact('cycles', 'current', 'summary', 'settings', 'canManage', 'owners', 'today'));
    }

    public function settings(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'auto_enabled' => 'required|boolean', 'interval_days' => 'required|integer|min:6|max:365',
            'anchor_date' => 'required|date', 'timezone' => 'required|timezone',
            'default_stage_durations' => 'nullable|array|size:6',
            'default_stage_durations.*' => 'integer|min:1|max:365',
        ]);
        if (isset($data['default_stage_durations'])) {
            if (array_diff(array_keys(PmsGlobalCycleService::STAGES), array_keys($data['default_stage_durations']))) {
                throw ValidationException::withMessages(['default_stage_durations' => 'Provide all six stage defaults.']);
            }
            $data['default_stage_durations'] = json_encode($data['default_stage_durations']);
        }
        DB::transaction(function () use ($request, $data): void {
            $settings = DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->lockForUpdate()->first();
            if (PmGlobalCycle::exists() && $settings->anchor_date !== $data['anchor_date']) {
                throw ValidationException::withMessages(['anchor_date' => 'The anchor date cannot change after cycles exist. Edit the current cycle dates instead.']);
            }
            DB::table('pm_global_cycle_settings')->where('id', $settings->id)->update($data + [
                'updated_by' => $request->user()->id, 'updated_at' => now(),
            ]);
        });

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('success', 'Automatic cycle settings saved.');
    }

    public function store(Request $request, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'goal' => 'nullable|string|max:20000',
            'cycle_key' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9_-]{2,31}$/', Rule::unique('pm_global_cycles', 'cycle_key')],
            'notes' => 'nullable|string|max:20000',
            'start_date' => 'required|date', 'end_date' => 'required|date',
            'production_target_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:end_date',
            'owner_id' => 'nullable|integer|exists:users,id',
            'stage_durations' => 'nullable|array|size:6',
            'stage_durations.*' => 'integer|min:1|max:365',
        ]);
        if (! empty($data['owner_id']) && ! PmsAccess::eligible(User::find($data['owner_id']))) {
            throw ValidationException::withMessages(['owner_id' => 'Choose an active PMS member.']);
        }
        $cycle = $service->create($data, $request->user());

        return $request->expectsJson() ? response()->json($cycle, 201) : redirect()->route('pms.global-cycles.show', $cycle)->with('success', 'Organization cycle created.');
    }

    public function show(Request $request, PmGlobalCycle $cycle)
    {
        $timezone = DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->value('timezone') ?: config('app.timezone');
        $today = now($timezone)->startOfDay();
        $cycle->load(['stages.owner:id,name', 'owner:id,name']);
        $visibleIds = PmsGlobalAccess::visibleProjectIds($request->user());
        $projects = $cycle->projects()->whereIn('pm_projects.id', $visibleIds)->with('owner:id,name')
            ->withCount([
                'issues as cycle_issues_count' => fn ($q) => $q->where('global_cycle_id', $cycle->id),
                'issues as cycle_done_count' => fn ($q) => $q->where('global_cycle_id', $cycle->id)->whereIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES),
            ])->get();
        $ticketQuery = $cycle->tickets()->with(['project:id,key,name', 'developer:id,name', 'qa:id,name', 'globalCycleStage:id,cycle_id,name'])
            ->where(fn ($q) => $q->whereIn('project_id', $visibleIds)
                ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id)));
        $blockedTicketIds = app(PmsBlockers::class)->activeTicketIds();
        if ($request->input('blocked') === '1') $ticketQuery->whereIn('id', $blockedTicketIds);
        if ($request->input('blocked') === '0') $ticketQuery->whereNotIn('id', $blockedTicketIds);
        if ($request->filled('stage')) {
            abort_unless($cycle->stages->contains('id', $request->integer('stage')), 422);
            $ticketQuery->where('global_cycle_stage_id', $request->integer('stage'));
        }
        foreach (['category' => 'classification_key', 'priority' => 'priority', 'status' => 'status', 'project' => 'project_id'] as $input => $column) {
            if ($request->filled($input)) {
                if ($input === 'priority' && $request->input('priority') === 'P1') {
                    $ticketQuery->whereIn($column, ['P0', 'P1']);
                } else {
                    $ticketQuery->where($column, $request->input($input));
                }
            }
        }
        if ($request->filled('assignee')) {
            $ticketQuery->where(fn ($q) => $q->where('developer_id', $request->integer('assignee'))
                ->orWhere('qa_id', $request->integer('assignee')));
        }
        if ($request->filled('department')) {
            $userIds = DB::table('pms_profiles')->where('department_id', $request->integer('department'))->select('user_id');
            $ticketQuery->where(fn ($q) => $q->whereIn('developer_id', $userIds)
                ->orWhereIn('qa_id', $userIds));
        }
        foreach (['created_from' => ['created_at', '>='], 'created_to' => ['created_at', '<='],
            'target_from' => ['due_date', '>='], 'target_to' => ['due_date', '<='],
            'completed_from' => ['completed_at', '>='], 'completed_to' => ['completed_at', '<=']] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $date = $request->validate([$input => 'date'])[$input];
                $ticketQuery->whereDate($column, $operator, $date);
            }
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $ticketQuery->where(fn ($q) => $q->where('issue_key', 'like', '%'.$term.'%')
                ->orWhere('reference', 'like', '%'.strtolower($term).'%')->orWhere('summary', 'like', '%'.$term.'%'));
        }
        $sort = in_array($request->input('sort'), ['newest', 'oldest', 'priority', 'target', 'status'], true)
            ? $request->input('sort') : 'newest';
        match ($sort) {
            'oldest' => $ticketQuery->orderBy('created_at'),
            'priority' => $ticketQuery->orderBy('priority'),
            'target' => $ticketQuery->orderBy('due_date'),
            'status' => $ticketQuery->orderBy('status'),
            default => $ticketQuery->orderByDesc('updated_at'),
        };
        $tickets = $ticketQuery->orderBy('id')->paginate(20, ['*'], 'tickets_page')->withQueryString();
        $allVisible = $cycle->tickets()->where(fn ($q) => $q->whereIn('project_id', $visibleIds)
            ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id)));
        $total = (clone $allVisible)->count();
        $completed = (clone $allVisible)->whereIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count();
        $metrics = [
            'total' => $total, 'completed' => $completed, 'open' => $total - $completed,
            'critical' => (clone $allVisible)->whereIn('priority', ['P0', 'P1'])->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
            'qat_pending' => (clone $allVisible)->where('classification_key', 'QAT')->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
            'overdue' => (clone $allVisible)->whereDate('due_date', '<', $today->toDateString())->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count(),
            'blocked' => (clone $allVisible)->whereIn('id', $blockedTicketIds)->count(),
            'progress' => $total ? round($completed / $total * 100) : 0,
            'days_remaining' => max(0, $today->diffInDays($cycle->end_date, false)),
        ];
        $stageCounts = (clone $allVisible)->whereNotNull('global_cycle_stage_id')
            ->selectRaw('global_cycle_stage_id, count(*) as total')->groupBy('global_cycle_stage_id')->pluck('total', 'global_cycle_stage_id');
        $stageDoneCounts = (clone $allVisible)->whereNotNull('global_cycle_stage_id')
            ->whereIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)
            ->selectRaw('global_cycle_stage_id, count(*) as total')->groupBy('global_cycle_stage_id')->pluck('total', 'global_cycle_stage_id');
        $stageProgress = $cycle->stages->mapWithKeys(function ($stage) use ($stageCounts, $stageDoneCounts) {
            $total = (int) ($stageCounts[$stage->id] ?? 0);
            return [$stage->id => $total ? (int) round(100 * ($stageDoneCounts[$stage->id] ?? 0) / $total) : 0];
        });
        $availableStatuses = (clone $allVisible)->distinct()->orderBy('status')->pluck('status');
        $showProjectRows = ! collect(['q', 'category', 'status', 'blocked', 'priority', 'stage', 'department',
            'assignee', 'created_from', 'created_to', 'target_from', 'target_to', 'completed_from', 'completed_to'])
            ->contains(fn ($key) => $request->filled($key));
        $history = DB::table('pm_global_cycle_history')->leftJoin('users', 'users.id', '=', 'pm_global_cycle_history.actor_id')
            ->where('cycle_id', $cycle->id)->select('pm_global_cycle_history.*', 'users.name as actor_name')
            ->orderByDesc('pm_global_cycle_history.id')->paginate(20, ['*'], 'history_page')->withQueryString();
        $projectOptions = PmProject::whereIn('id', $visibleIds)->whereNull('archived_at')->orderBy('name')->get(['id', 'key', 'name']);
        $departments = DB::table('pms_departments')->where('active', true)->orderBy('name')->get(['id', 'name']);
        $assignees = User::whereIn('id', (clone $allVisible)->whereNotNull('developer_id')->select('developer_id'))
            ->orWhereIn('id', (clone $allVisible)->whereNotNull('qa_id')->select('qa_id'))
            ->orderBy('name')->get(['id', 'name']);
        $canManage = PmsGlobalAccess::manageCycles($request->user());
        $owners = $canManage ? User::whereIn('id', DB::table('pms_profiles')->where('status', 'active')->select('user_id'))
            ->orderBy('name')->get(['id', 'name']) : collect();
        $releases = $cycle->releases()->with('project:id,key,name')->whereIn('project_id', $visibleIds)->orderBy('planned_deploy_date')->get();

        return $request->expectsJson() ? response()->json(compact('cycle', 'projects', 'tickets', 'metrics', 'history', 'releases'))
            : view('pms.global-cycle', compact('cycle', 'projects', 'tickets', 'metrics', 'history', 'projectOptions', 'canManage', 'owners', 'releases', 'today', 'blockedTicketIds', 'stageCounts', 'stageDoneCounts', 'stageProgress', 'departments', 'assignees', 'availableStatuses', 'showProjectRows'));
    }

    public function update(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'goal' => 'nullable|string|max:20000',
            'start_date' => 'required|date', 'end_date' => 'required|date',
            'owner_id' => 'nullable|integer|exists:users,id',
            'lock_version' => 'required|integer|min:0', 'reason' => 'nullable|string|max:5000',
        ]);
        if (! empty($data['owner_id']) && ! PmsAccess::eligible(User::find($data['owner_id']))) {
            throw ValidationException::withMessages(['owner_id' => 'Choose an active PMS member.']);
        }
        $updated = $service->reschedule($cycle, $data, $request->user());

        return $request->expectsJson() ? response()->json($updated) : back()->with('success', 'Cycle updated.');
    }

    public function stages(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        $data = $request->validate([
            'reason' => 'required|string|max:5000', 'stages' => 'required|array|size:6',
            'stages.*.stage_key' => ['required', Rule::in(array_keys(PmsGlobalCycleService::STAGES))],
            'stages.*.sort_order' => 'nullable|integer|min:1|max:6',
            'stages.*.duration_days' => 'required|integer|min:1|max:365',
            'stages.*.completion_percent' => 'required|integer|min:0|max:100',
            'stages.*.owner_id' => 'nullable|integer|exists:users,id',
            'stages.*.notes' => 'nullable|string|max:5000',
        ]);
        $order = collect($data['stages'])->pluck('sort_order')->filter();
        if ($order->isNotEmpty()) {
            if ($order->count() !== 6 || $order->unique()->count() !== 6) {
                throw ValidationException::withMessages(['stages' => 'Give every stage a distinct order from 1 to 6.']);
            }
            usort($data['stages'], fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }
        foreach ($data['stages'] as $stage) {
            if (!empty($stage['owner_id']) && !PmsAccess::eligible(User::find($stage['owner_id']))) {
                throw ValidationException::withMessages(['stages' => 'Stage owners must be active PMS members.']);
            }
        }
        $updated = $service->replaceStages($cycle, $data['stages'], $request->user(), $data['reason']);

        return $request->expectsJson() ? response()->json($updated) : back()->with('success', 'Stage plan updated.');
    }

    public function assignProjects(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        $data = $request->validate(['project_ids' => 'required|array|min:1|max:50', 'project_ids.*' => 'required|integer|distinct|exists:pm_projects,id']);
        $projects = PmProject::whereIn('id', $data['project_ids'])->get();
        foreach ($projects as $project) {
            abort_unless(PmsAccess::allows($request->user(), $project, 'manage_cycle'), 403);
        }
        DB::transaction(function () use ($request, $cycle, $service, $projects): void {
            foreach ($projects as $project) {
                $inserted = DB::table('pm_global_cycle_projects')->insertOrIgnore([
                    'cycle_id' => $cycle->id, 'project_id' => $project->id,
                    'assigned_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($inserted) {
                    $service->history($cycle, $request->user(), 'project_assigned', ['project_id' => $project->id]);
                }
            }
        });

        return $request->expectsJson() ? response()->json(['assigned' => $projects->count()]) : back()->with('success', 'Projects assigned to cycle.');
    }

    public function removeProject(Request $request, PmGlobalCycle $cycle, PmProject $project, PmsGlobalCycleService $service)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage_cycle'), 403);
        DB::transaction(function () use ($request, $cycle, $project, $service): void {
            $removed = DB::table('pm_global_cycle_projects')->where('cycle_id', $cycle->id)->where('project_id', $project->id)->delete();
            if ($removed) {
                $service->history($cycle, $request->user(), 'project_removed', ['project_id' => $project->id]);
            }
        });

        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Project removed from cycle. Its tickets remain unchanged.');
    }

    public function assignTicket(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        abort_if(in_array($cycle->status, ['Completed', 'Cancelled'], true), 422);
        $data = $request->validate(['issue_key' => 'required|string|max:48', 'reason' => 'nullable|string|max:5000']);
        $ticket = PmWorkItem::where('issue_key', strtoupper(trim($data['issue_key'])))
            ->orWhere('reference', strtolower(trim($data['issue_key'])))->firstOrFail();
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        if ($ticket->project) {
            abort_unless(PmsAccess::allows($request->user(), $ticket->project, 'manage_cycle'), 403);
        }
        DB::transaction(function () use ($request, $ticket, $cycle, $service, $data): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            $before = $locked->global_cycle_id;
            if ($before && (int) $before !== $cycle->id && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Explain carrying a ticket to another organization cycle.']);
            }
            $locked->original_global_cycle_id ??= $cycle->id;
            if ($before && (int) $before !== $cycle->id) $locked->global_cycle_stage_id = null;
            $locked->global_cycle_id = $cycle->id;
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $locked->id, 'actor_id' => $request->user()->id, 'event' => 'organization_cycle_changed',
                'reason' => $data['reason'] ?? null,
                'changes' => json_encode(['from_cycle_id' => $before, 'to_cycle_id' => $cycle->id]), 'created_at' => now(),
            ]);
            $service->history($cycle, $request->user(), 'ticket_assigned', ['ticket_id' => $ticket->id, 'from_cycle_id' => $before], $data['reason'] ?? null);
        });

        return $request->expectsJson() ? response()->json($ticket->fresh()) : back()->with('success', 'Ticket assigned to cycle.');
    }

    public function removeTicket(Request $request, PmGlobalCycle $cycle, PmWorkItem $ticket, PmsGlobalCycleService $service)
    {
        abort_unless((int) $ticket->global_cycle_id === $cycle->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        if ($ticket->project) {
            abort_unless(PmsAccess::allows($request->user(), $ticket->project, 'manage_cycle'), 403);
        }
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $cycle, $ticket, $service, $data): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless((int) $locked->global_cycle_id === $cycle->id, 404);
            $locked->global_cycle_id = null;
            $locked->global_cycle_stage_id = null;
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $locked->id, 'actor_id' => $request->user()->id, 'event' => 'organization_cycle_removed',
                'reason' => $data['reason'], 'changes' => json_encode(['from_cycle_id' => $cycle->id, 'to_cycle_id' => null]),
                'created_at' => now(),
            ]);
            $service->history($cycle, $request->user(), 'ticket_removed', ['ticket_id' => $ticket->id], $data['reason']);
        });

        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Ticket removed from cycle.');
    }

    public function assignTicketStage(Request $request, PmGlobalCycle $cycle, PmWorkItem $ticket, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        abort_unless((int) $ticket->global_cycle_id === $cycle->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        if ($ticket->project) abort_unless(PmsAccess::allows($request->user(), $ticket->project, 'manage_cycle'), 403);
        $data = $request->validate(['stage_id' => 'nullable|integer|exists:pm_global_cycle_stages,id']);
        if (! empty($data['stage_id'])) abort_unless($cycle->stages()->whereKey($data['stage_id'])->exists(), 422);
        DB::transaction(function () use ($request, $cycle, $ticket, $data, $service): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless((int) $locked->global_cycle_id === $cycle->id, 404);
            $before = $locked->global_cycle_stage_id;
            $locked->global_cycle_stage_id = $data['stage_id'] ?? null;
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $ticket->id, 'actor_id' => $request->user()->id, 'event' => 'organization_stage_changed',
                'changes' => json_encode(['from_stage_id' => $before, 'to_stage_id' => $locked->global_cycle_stage_id]),
                'created_at' => now(),
            ]);
            $service->history($cycle, $request->user(), 'ticket_stage_changed', [
                'ticket_id' => $ticket->id, 'from_stage_id' => $before, 'to_stage_id' => $locked->global_cycle_stage_id,
            ]);
        });

        return $request->expectsJson() ? response()->json($ticket->fresh()) : back()->with('success', 'Ticket stage updated.');
    }

    public function state(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['Completed', 'Cancelled', 'Active'])], 'reason' => 'required|string|max:5000']);
        if ($data['status'] === 'Completed' && $cycle->tickets()->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->exists()
            && ! PmsAccess::administrator($request->user())) {
            throw ValidationException::withMessages(['status' => 'Resolve open tickets or ask an administrator to approve closure with a reason.']);
        }
        $before = $cycle->status;
        DB::transaction(function () use ($cycle, $data, $service, $request, $before): void {
            $cycle->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'Completed' ? now() : null]);
            $service->history($cycle, $request->user(), 'status_changed', ['from' => $before, 'to' => $data['status']], $data['reason']);
        });

        return $request->expectsJson() ? response()->json($cycle->fresh()) : back()->with('success', 'Cycle status updated.');
    }

    public function archive(Request $request, PmGlobalCycle $cycle, PmsGlobalCycleService $service)
    {
        abort_unless(PmsGlobalAccess::manageCycles($request->user()), 403);
        abort_unless(in_array($cycle->status, ['Completed', 'Cancelled', 'Delayed'], true), 422);
        DB::transaction(function () use ($cycle, $service, $request): void {
            $cycle->update(['archived_at' => now()]);
            $service->history($cycle, $request->user(), 'archived');
        });

        return $request->expectsJson() ? response()->json(['archived' => true]) : back()->with('success', 'Cycle archived.');
    }
}
