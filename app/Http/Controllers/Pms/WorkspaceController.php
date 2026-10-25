<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use App\Services\PmsCycleService;
use App\Services\PmsOverviewService;
use App\Support\PmsAccess;
use App\Support\PmsModule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function home(Request $request)
    {
        abort_unless(module_enabled('project_management'), 404);
        $user = \Illuminate\Support\Facades\Auth::guard('pms')->user() ?: $request->user();
        if (! $user) {
            return redirect()->route('pms.login');
        }
        if (! PmsAccess::workspace($user)) {
            $profile = DB::table('pms_profiles')->where('user_id', $user->id)->first();
            if ($profile && $profile->status === 'pending') {
                return view('pms.access-status', compact('profile'));
            }
            abort(403);
        }

        return redirect()->route('pms.dashboard');
    }

    public function dashboard(Request $request, PmsCycleService $cycleService, PmsOverviewService $overview)
    {
        if ($request->expectsJson()) {
            return response()->json($this->analytics($request, $cycleService));
        }

        $visibleProjectIds = $this->visibleProjectIds($request);
        $filters = $request->validate([
            'project' => ['nullable', 'integer'],
            'period' => ['nullable', Rule::in([7, 30, 90])],
        ]);
        $projectId = isset($filters['project']) ? (int) $filters['project'] : null;
        abort_if($projectId && ! in_array($projectId, $visibleProjectIds, true), 403);

        return view('pms.dashboard', $overview->data($visibleProjectIds, $projectId, (int) ($filters['period'] ?? 30)));
    }

    public function reports(Request $request, PmsCycleService $cycleService)
    {
        $data = $this->analytics($request, $cycleService);

        return $request->expectsJson() ? response()->json($data) : view('pms.reports', $data);
    }

    public function exportReports(Request $request, PmsCycleService $cycleService)
    {
        $data = $this->analytics($request, $cycleService);
        $filename = 'pms-operational-analytics-'.today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Section', 'Metric', 'Value', 'Basis']);

            $row = static function (string $section, string $metric, string|int|float $value, string $basis = '') use ($output): void {
                $safe = static fn ($cell) => preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', (string) $cell) ? "'".$cell : $cell;
                fputcsv($output, array_map($safe, [$section, $metric, $value, $basis]));
            };

            $row('Report', 'Period', $data['periodLabel']);
            $row('Report', 'Project', $data['projectId'] ? $data['projects']->firstWhere('id', $data['projectId'])?->name ?? 'Selected project' : 'All accessible projects');
            foreach ($data['statistics'] as $stat) {
                $row('Performance snapshot', $stat['label'], $stat['value'], $stat['basis']);
            }
            $row('Portfolio', 'Projects', $data['counts']['projects']);
            foreach ($data['projectStatus'] as $status => $count) {
                $row('Project status', (string) $status, (int) $count);
            }
            foreach (['Ticket classification' => 'classifications', 'Issue status' => 'statusDistribution', 'Priority' => 'priorityDistribution'] as $section => $key) {
                foreach ($data[$key] as $label => $count) {
                    $row($section, (string) $label, (int) $count);
                }
            }
            foreach ($data['delivery'] as $key => $value) {
                if ($key === 'tickets_by_category') {
                    foreach ($value as $category => $count) {
                        $row('Ticket categories', (string) ($category ?: 'Other'), (int) $count);
                    }
                } else {
                    $row('Software delivery', str_replace('_', ' ', ucfirst($key)), (int) $value);
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function board(Request $request)
    {
        $project = PmProject::findOrFail($request->integer('project'));
        abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        $issues = $project->issues()->with(['developer:id,name', 'qa:id,name'])->orderBy('id')->paginate(200)->withQueryString();

        return view('pms.board', compact('project', 'issues'));
    }

    public function audit(Request $request)
    {
        $projectIds = $this->visibleProjectIds($request);
        $issueIds = PmWorkItem::whereIn('project_id', $projectIds)->select('id');
        $cycleIds = PmCycle::whereIn('project_id', $projectIds)->select('id');
        $logs = DB::table('pm_audit_logs')->where(function ($query) use ($projectIds, $issueIds, $cycleIds): void {
            $query->where(fn ($q) => $q->where('subject_type', 'project')->whereIn('subject_id', $projectIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'issue')->whereIn('subject_id', $issueIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'cycle')->whereIn('subject_id', $cycleIds));
        })->orderByDesc('id')->paginate(50);

        return view('pms.audit', compact('logs'));
    }

    public function settings(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $settings = PmsModule::settings();
        $defectTypes = DB::table('pm_defect_types')->orderBy('name')->get();

        return view('pms.settings', compact('settings', 'defectTypes'));
    }

    public function addDefectType(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate(['name' => 'required|string|min:3|max:80']);
        $name = trim($data['name']);
        if (DB::table('pm_defect_types')->where('name', $name)->exists()) {
            DB::table('pm_defect_types')->where('name', $name)->update(['active' => true, 'updated_at' => now()]);
        } else {
            DB::table('pm_defect_types')->insert(['name' => $name, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'workspace.defect_type_enabled', 'subject_type' => 'workspace', 'subject_id' => 1, 'details' => json_encode(['name' => $name]), 'created_at' => now()]);

        return $request->expectsJson() ? response()->json(['name' => $name, 'active' => true]) : back()->with('success', 'Defect type enabled.');
    }

    public function disableDefectType(Request $request, int $type)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $defect = DB::table('pm_defect_types')->where('id', $type)->first();
        abort_unless($defect, 404);
        DB::table('pm_defect_types')->where('id', $type)->update(['active' => false, 'updated_at' => now()]);
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'workspace.defect_type_disabled', 'subject_type' => 'workspace', 'subject_id' => 1, 'details' => json_encode(['name' => $defect->name]), 'created_at' => now()]);

        return $request->expectsJson() ? response()->json(['active' => false]) : back()->with('success', 'Defect type disabled for new tickets.');
    }

    public function updateSettings(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'registration_policy' => ['required', Rule::in(['approval', 'invitation', 'disabled', 'open'])],
            'week_mode' => ['required', Rule::in(['calendar', 'working'])],
            'weekend_days' => 'nullable|array', 'weekend_days.*' => 'integer|min:0|max:6',
            'holidays' => 'nullable|string|max:20000',
            'attention_gap' => 'required|integer|min:1|max:100', 'risk_gap' => 'required|integer|min:1|max:100',
            'reopen_limit' => 'required|integer|min:1|max:100',
            'activity_idle_seconds' => 'sometimes|integer|min:60|max:1800',
            'activity_retention_days' => 'sometimes|integer|min:30|max:1095',
        ]);
        $holidays = collect(preg_split('/[\r\n,]+/', $data['holidays'] ?? '', -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($date) => trim($date))->each(fn ($date) => Carbon::createFromFormat('Y-m-d', $date))->unique()->values()->all();
        DB::table('pms_workspace_settings')->where('id', PmsModule::settings()->id)->update([
            'registration_policy' => $data['registration_policy'], 'week_mode' => $data['week_mode'],
            'weekend_days' => json_encode(array_values($data['weekend_days'] ?? [])), 'holidays' => json_encode($holidays),
            'health_thresholds' => json_encode(['attention_gap' => $data['attention_gap'], 'risk_gap' => $data['risk_gap'], 'reopen_limit' => $data['reopen_limit']]),
            'activity_idle_seconds' => $data['activity_idle_seconds'] ?? PmsModule::settings()->activity_idle_seconds,
            'activity_retention_days' => $data['activity_retention_days'] ?? PmsModule::settings()->activity_retention_days,
            'updated_by' => $request->user()->id, 'updated_at' => now(),
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('module_access_settings')) {
            DB::table('module_access_settings')->where('module_key', 'pms')->update([
                'registration_policy' => $data['registration_policy'], 'updated_by' => $request->user()->id, 'updated_at' => now(),
            ]);
        }
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => 'workspace.settings_updated', 'subject_type' => 'workspace', 'subject_id' => 1,
            'details' => json_encode(['registration_policy' => $data['registration_policy'], 'week_mode' => $data['week_mode']]), 'created_at' => now(),
        ]);

        return back()->with('success', 'Workspace policy and calendar settings saved.');
    }

    private function analytics(Request $request, PmsCycleService $cycleService): array
    {
        $visibleProjectIds = $this->visibleProjectIds($request);
        $projectId = $request->integer('project') ?: null;
        if ($projectId && ! in_array($projectId, $visibleProjectIds, true)) {
            abort(403);
        }
        $periodDays = in_array($request->integer('period'), [30, 90, 180, 365], true) ? $request->integer('period') : 90;
        $periodStart = today()->subDays($periodDays - 1)->startOfDay();
        $scopedProjectIds = $projectId ? [$projectId] : $visibleProjectIds;
        $projectsQuery = PmProject::whereIn('id', $scopedProjectIds)->whereNull('archived_at');
        $issuesQuery = PmWorkItem::whereIn('project_id', $scopedProjectIds)->where('created_at', '>=', $periodStart);
        $allIssuesQuery = PmWorkItem::whereIn('project_id', $scopedProjectIds);
        $closedStatuses = ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed', 'Cancelled', 'Duplicate', 'Rejected'];

        $counts = [
            'projects' => (clone $projectsQuery)->count(),
            'active' => (clone $projectsQuery)->where('status', 'Active')->count(),
            'overdue' => (clone $projectsQuery)->whereNotIn('status', ['Completed', 'Cancelled'])->whereDate('target_date', '<', today())->count(),
            'open' => (clone $allIssuesQuery)->whereNotIn('status', $closedStatuses)->count(),
            'on_hold' => (clone $allIssuesQuery)->where('status_code', \App\Support\PmsStatusRegistry::code('ticket', 'On Hold'))->count(),
            'with_active_blockers' => (clone $allIssuesQuery)->whereIn('id', app(\App\Services\PmsBlockers::class)->activeTicketIds())->count(),
            'with_overdue_blockers' => (clone $allIssuesQuery)->whereIn('id', DB::table('pm_ticket_blockers')->whereNull('resolved_at')->whereDate('expected_resolution_date', '<', today())->select('issue_id'))->count(),
            'resumed' => (clone $allIssuesQuery)->whereIn('id', DB::table('pm_issue_history')->where('event', 'resume')->select('issue_id'))->count(),
            'production_done' => (clone $issuesQuery)->where('status', 'Production Done')->count(),
            'pdi' => (clone $issuesQuery)->where('classification_key', 'PDI')->count(),
            'dependency' => (clone $allIssuesQuery)->where('status', 'QAT Dependency')->count(),
            'reopen' => (clone $issuesQuery)->where('reopen_count', '>', 0)->count(),
            'critical' => (clone $allIssuesQuery)->whereIn('priority', ['P0', 'P1'])->whereNotIn('status', $closedStatuses)->count(),
            'mine' => (clone $allIssuesQuery)->where(fn ($q) => $q->where('developer_id', $request->user()->id)->orWhere('qa_id', $request->user()->id))->whereNotIn('status', $closedStatuses)->count(),
        ];
        $projectStatus = (clone $projectsQuery)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $classifications = (clone $issuesQuery)->select('classification_key', DB::raw('count(*) as aggregate'))->groupBy('classification_key')->pluck('aggregate', 'classification_key');
        $statusDistribution = (clone $allIssuesQuery)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $priorityDistribution = (clone $allIssuesQuery)->select('priority', DB::raw('count(*) as aggregate'))->groupBy('priority')->pluck('aggregate', 'priority');

        $aging = ['0-3 days' => 0, '4-7 days' => 0, '8-14 days' => 0, '15-30 days' => 0, 'Over 30 days' => 0];
        (clone $allIssuesQuery)->whereNotIn('status', $closedStatuses)->get(['created_at'])->each(function ($issue) use (&$aging): void {
            $days = $issue->created_at->startOfDay()->diffInDays(today());
            $bucket = $days <= 3 ? '0-3 days' : ($days <= 7 ? '4-7 days' : ($days <= 14 ? '8-14 days' : ($days <= 30 ? '15-30 days' : 'Over 30 days')));
            $aging[$bucket]++;
        });

        $myIssues = (clone $allIssuesQuery)->with('project')->where(fn ($q) => $q->where('developer_id', $request->user()->id)->orWhere('qa_id', $request->user()->id))
            ->whereNotIn('status', $closedStatuses)->latest()->limit(8)->get();
        $activeCycles = PmCycle::with(['project:id,key,name', 'phases', 'issues'])->whereIn('project_id', $scopedProjectIds)->whereIn('status', ['Active', 'Internal Testing', 'QAT'])->orderBy('target_end_date')->limit(6)->get();
        $cycleCards = $activeCycles->map(fn ($cycle) => ['cycle' => $cycle, 'analytics' => $cycleService->analytics($cycle)]);
        $workload = DB::table('pm_work_items')->join('users', 'users.id', '=', 'pm_work_items.developer_id')
            ->whereIn('pm_work_items.project_id', $scopedProjectIds)->whereNotIn('pm_work_items.status', $closedStatuses)
            ->whereNull('pm_work_items.deleted_at')
            ->select('users.id', 'users.name', DB::raw('count(*) as issue_count'), DB::raw('coalesce(sum(pm_work_items.estimate_minutes),0) as minutes'))
            ->groupBy('users.id', 'users.name')->orderByDesc('issue_count')->limit(10)->get();
        $qaWorkload = DB::table('pm_work_items')->join('users', 'users.id', '=', 'pm_work_items.qa_id')
            ->whereIn('pm_work_items.project_id', $scopedProjectIds)->whereNotIn('pm_work_items.status', $closedStatuses)
            ->whereNull('pm_work_items.deleted_at')
            ->select('users.id', 'users.name', DB::raw('count(*) as issue_count'), DB::raw("sum(case when pm_work_items.status in ('QAT In Progress','QAT Dependency','Reopen') then 1 else 0 end) as active_qa_count"))
            ->groupBy('users.id', 'users.name')->orderByDesc('active_qa_count')->orderByDesc('issue_count')->limit(10)->get();

        $releaseReadiness = [
            'qat_done' => (clone $allIssuesQuery)->where('status', 'QAT Done')->count(),
            'active_qa' => (clone $allIssuesQuery)->whereIn('status', ['QAT In Progress', 'QAT Dependency', 'Reopen'])->count(),
            'dependencies' => (clone $allIssuesQuery)->where('status', 'QAT Dependency')->count(),
            'open_critical' => (clone $allIssuesQuery)->whereIn('priority', ['P0', 'P1'])->whereNotIn('status', ['Production Done', 'Cancelled'])->count(),
            'unassigned_development' => (clone $allIssuesQuery)->whereNull('developer_id')->whereNotIn('status', ['Production Done', 'Cancelled'])->count(),
            'awaiting_production_evidence' => (clone $allIssuesQuery)->where('status', 'QAT Done')->where(function ($query): void {
                $query->whereNull('production_build')->orWhereNull('production_deployed_at')->orWhereNull('production_evidence');
            })->count(),
        ];

        $completedEvents = (clone $allIssuesQuery)->whereNotNull('completed_at')->where('completed_at', '>=', $periodStart)->get(['completed_at'])->pluck('completed_at');
        $reopenEvents = DB::table('pm_issue_history')->join('pm_work_items', 'pm_work_items.id', '=', 'pm_issue_history.issue_id')
            ->whereIn('pm_work_items.project_id', $scopedProjectIds)->whereNull('pm_work_items.deleted_at')
            ->whereIn('pm_issue_history.to_status', ['Reopen', 'Reopened'])->where('pm_issue_history.created_at', '>=', $periodStart)
            ->pluck('pm_issue_history.created_at');
        $flowTrend = $this->flowTrend($periodStart, $periodDays, $completedEvents, $reopenEvents);

        $deliveryCohort = (clone $issuesQuery)->where('classification_key', '<>', 'QAT')
            ->whereNotIn('status', ['Cancelled', 'Duplicate', 'Rejected']);
        $issueTotal = (clone $deliveryCohort)->count();
        $completed = (clone $deliveryCohort)->whereIn('status', ['Production Done', 'Completed'])->count();
        $reachedQa = (clone $issuesQuery)->where(fn ($q) => $q->whereNotNull('qa_completed_at')->orWhere('reopen_count', '>', 0)
            ->orWhereIn('status', ['QAT In Progress', 'QAT in Progress', 'QAT Dependency', 'QAT Done', 'Production Done', 'Retesting', 'Verified', 'Closed', 'Reopened', 'Reopen']))->count();
        $reopened = (clone $issuesQuery)->where('reopen_count', '>', 0)->count();
        $eligibleOnTime = (clone $deliveryCohort)->whereIn('status', ['Production Done', 'Completed'])->whereNotNull('due_date')->count();
        $onTime = (clone $deliveryCohort)->whereIn('status', ['Production Done', 'Completed'])->whereNotNull('due_date')
            ->whereRaw('date(completed_at) <= due_date')->count();
        $qaResults = DB::table('pm_qa_verifications')->join('pm_work_items', 'pm_work_items.id', '=', 'pm_qa_verifications.issue_id')
            ->whereIn('pm_work_items.project_id', $scopedProjectIds)->whereNull('pm_work_items.deleted_at')
            ->where('pm_qa_verifications.tested_at', '>=', $periodStart)
            ->select('pm_qa_verifications.result', DB::raw('count(*) as aggregate'))->groupBy('pm_qa_verifications.result')->pluck('aggregate', 'result');
        $qaPasses = (int) ($qaResults['Passed'] ?? 0);
        $qaRuns = (int) $qaResults->sum();
        $resolvedQat = (clone $allIssuesQuery)->where('classification_key', 'QAT')
            ->whereIn('status', ['QAT Done', 'Production Done', 'Verified', 'Closed'])->whereNotNull('qa_completed_at')
            ->where('qa_completed_at', '>=', $periodStart)->get(['created_at', 'qa_completed_at']);
        $resolutionMinutes = $resolvedQat->sum(fn ($ticket) => $ticket->created_at->diffInMinutes(Carbon::parse($ticket->qa_completed_at)));
        $successfulRetests = DB::table('pm_qa_verifications as verification')
            ->join('pm_work_items as ticket', 'ticket.id', '=', 'verification.issue_id')
            ->whereIn('ticket.project_id', $scopedProjectIds)->whereNull('ticket.deleted_at')
            ->where('verification.result', 'Passed')->where('verification.created_at', '>=', $periodStart)
            ->select('verification.created_at', DB::raw("(select max(history.created_at) from pm_issue_history as history where history.issue_id = verification.issue_id and history.to_status in ('Retesting', 'QAT in Progress') and history.created_at <= verification.created_at) as retesting_at"))
            ->get()->filter(fn ($row) => filled($row->retesting_at));
        $retestMinutes = $successfulRetests->sum(fn ($row) => Carbon::parse($row->retesting_at)->diffInMinutes(Carbon::parse($row->created_at)));
        $dueReleases = PmRelease::whereIn('project_id', $scopedProjectIds)
            ->whereDate('planned_deploy_date', '>=', $periodStart->toDateString())
            ->whereDate('planned_deploy_date', '<=', today()->toDateString())
            ->where('status', '<>', 'Cancelled');
        $dueReleaseCount = (clone $dueReleases)->count();
        $completedReleaseCount = (clone $dueReleases)->where('status', 'Completed')->count();
        $statistics = [
            ['label' => 'Delivery rate', 'value' => $issueTotal ? round($completed / $issueTotal * 100, 1).'%' : 'N/A', 'basis' => "{$completed} completed / {$issueTotal} eligible non-QAT items created in period"],
            ['label' => 'Reopen rate', 'value' => $reachedQa ? round($reopened / $reachedQa * 100, 1).'%' : 'N/A', 'basis' => "{$reopened} reopened / {$reachedQa} items that reached QA"],
            ['label' => 'On-time delivery', 'value' => $eligibleOnTime ? round($onTime / $eligibleOnTime * 100, 1).'%' : 'N/A', 'basis' => "{$onTime} on time / {$eligibleOnTime} completed non-QAT items with due dates"],
            ['label' => 'QA pass rate', 'value' => $qaRuns ? round($qaPasses / $qaRuns * 100, 1).'%' : 'N/A', 'basis' => "{$qaPasses} passed / {$qaRuns} structured QAT verifications in period"],
            ['label' => 'Average defect resolution', 'value' => $resolvedQat->isNotEmpty() ? round($resolutionMinutes / $resolvedQat->count() / 60, 1).' h' : 'N/A', 'basis' => $resolvedQat->count().' currently verified/closed QAT tickets completed in period; creation to latest verification'],
            ['label' => 'Average QAT retest', 'value' => $successfulRetests->isNotEmpty() ? round($retestMinutes / $successfulRetests->count() / 60, 1).' h' : 'N/A', 'basis' => $successfulRetests->count().' successful retests in period; last Retesting transition to verification record'],
            ['label' => 'Release completion rate', 'value' => $dueReleaseCount ? round($completedReleaseCount / $dueReleaseCount * 100, 1).'%' : 'N/A', 'basis' => "{$completedReleaseCount} completed / {$dueReleaseCount} non-cancelled releases due in period"],
        ];
        $releasesQuery = PmRelease::whereIn('project_id', $scopedProjectIds);
        $delivery = [
            'tickets_by_category' => (clone $allIssuesQuery)->select('classification_key', DB::raw('count(*) as aggregate'))->groupBy('classification_key')->pluck('aggregate', 'classification_key'),
            'qat_open' => (clone $allIssuesQuery)->where('classification_key', 'QAT')->whereNotIn('status', $closedStatuses)->count(),
            'qat_verified' => (clone $allIssuesQuery)->where('classification_key', 'QAT')->whereIn('status', ['QAT Done', 'Production Done', 'Verified', 'Closed'])->count(),
            'qat_reopened' => (clone $allIssuesQuery)->where('classification_key', 'QAT')->where('reopen_count', '>', 0)->count(),
            'qa_passed' => $qaPasses, 'qa_failed' => (int) ($qaResults['Failed'] ?? 0),
            'tickets_overdue' => (clone $allIssuesQuery)->whereNotIn('status', $closedStatuses)->whereDate('due_date', '<', today())->count(),
            'releases_active' => (clone $releasesQuery)->whereNotIn('status', ['Released', 'Completed', 'Cancelled'])->count(),
            'releases_upcoming' => (clone $releasesQuery)->whereNotIn('status', ['Released', 'Completed', 'Cancelled'])
                ->whereDate('planned_deploy_date', '>=', today())->whereDate('planned_deploy_date', '<=', today()->addDays(30))->count(),
            'releases_completed' => (clone $releasesQuery)->where('status', 'Completed')->count(),
            'qat_intake' => PmWorkItem::whereNull('project_id')->where('classification_key', 'QAT')
                ->when(! PmsAccess::administrator($request->user()), fn ($q) => $q->where('reporter_id', $request->user()->id))->count(),
        ];
        $upcomingReleases = (clone $releasesQuery)->with('project:id,key,name')->whereNotIn('status', ['Released', 'Completed', 'Cancelled'])
            ->orderBy('planned_deploy_date')->limit(5)->get();

        $issueIds = PmWorkItem::whereIn('project_id', $scopedProjectIds)->select('id');
        $cycleIds = PmCycle::whereIn('project_id', $scopedProjectIds)->select('id');
        $recent = DB::table('pm_audit_logs')->where(function ($query) use ($scopedProjectIds, $issueIds, $cycleIds): void {
            $query->where(fn ($q) => $q->where('subject_type', 'project')->whereIn('subject_id', $scopedProjectIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'issue')->whereIn('subject_id', $issueIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'cycle')->whereIn('subject_id', $cycleIds));
        })->orderByDesc('id')->limit(10)->get();
        $projects = PmProject::whereIn('id', $visibleProjectIds)->whereNull('archived_at')->orderBy('name')->get(['id', 'key', 'name']);
        $periodLabel = $periodStart->toDateString().' to '.today()->toDateString();
        $periodStartDate = $periodStart->toDateString();
        $periodEndDate = today()->toDateString();

        return compact('counts', 'projectStatus', 'classifications', 'statusDistribution', 'priorityDistribution', 'aging', 'myIssues', 'cycleCards', 'workload', 'qaWorkload', 'releaseReadiness', 'flowTrend', 'statistics', 'recent', 'projects', 'projectId', 'periodDays', 'periodLabel', 'periodStartDate', 'periodEndDate', 'delivery', 'upcomingReleases');
    }

    private function flowTrend(Carbon $periodStart, int $periodDays, $completedEvents, $reopenEvents): array
    {
        $monthly = $periodDays > 90;
        $cursor = $monthly ? $periodStart->copy()->startOfMonth() : $periodStart->copy()->startOfWeek();
        $buckets = [];
        while ($cursor->lte(today())) {
            $key = $cursor->toDateString();
            $buckets[$key] = [
                'label' => $monthly ? $cursor->format('M Y') : 'Week of '.$cursor->format('M j'),
                'completed' => 0,
                'reopened' => 0,
            ];
            $cursor = $monthly ? $cursor->addMonth() : $cursor->addWeek();
        }
        foreach (['completed' => $completedEvents, 'reopened' => $reopenEvents] as $metric => $events) {
            foreach ($events as $event) {
                $date = Carbon::parse($event);
                $key = ($monthly ? $date->startOfMonth() : $date->startOfWeek())->toDateString();
                if (isset($buckets[$key])) {
                    $buckets[$key][$metric]++;
                }
            }
        }

        return array_values($buckets);
    }

    private function visibleProjectIds(Request $request): array
    {
        return PmsAccess::administrator($request->user())
            ? PmProject::query()->whereNull('archived_at')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('pm_project_members')->join('pm_projects', 'pm_projects.id', '=', 'pm_project_members.project_id')
                ->where('pm_project_members.user_id', $request->user()->id)->whereNull('pm_projects.deleted_at')->whereNull('pm_projects.archived_at')
                ->pluck('pm_project_members.project_id')->map(fn ($id) => (int) $id)->all();
    }
}
