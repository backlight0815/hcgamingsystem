<?php

namespace App\Services;

use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PmsOverviewService
{
    private const CLOSED = ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed', 'Cancelled', 'Duplicate', 'Rejected'];

    private const COMPLETED = ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed'];

    private const RELEASE_CLOSED = ['Released', 'Completed', 'Cancelled'];

    public function data(array $visibleProjectIds, ?int $projectId, int $periodDays): array
    {
        $scopedIds = $projectId ? [$projectId] : $visibleProjectIds;
        $today = today();
        $periodStart = $today->copy()->subDays($periodDays - 1)->startOfDay();
        $projects = PmProject::whereIn('id', $visibleProjectIds)->whereNull('archived_at')
            ->orderBy('name')->get(['id', 'key', 'name']);
        $projectQuery = PmProject::whereIn('id', $scopedIds)->whereNull('archived_at');
        $ticketQuery = PmWorkItem::whereIn('project_id', $scopedIds);
        $openQuery = (clone $ticketQuery)->whereNotIn('status', self::CLOSED);
        $releaseQuery = PmRelease::whereIn('project_id', $scopedIds);
        $blockedIds = app(PmsBlockers::class)->activeTicketIds();
        $criticalQuery = (clone $openQuery)->whereIn('id', $blockedIds)->whereIn('priority', ['P0', 'P1']);

        $projectStatus = (clone $projectQuery)->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $projectTotal = (int) $projectStatus->sum();
        $active = (int) ($projectStatus['Active'] ?? 0);
        $planned = (int) ($projectStatus['Planned'] ?? 0) + (int) ($projectStatus['Approved'] ?? 0);
        $overdueProjects = (clone $projectQuery)->whereNotIn('status', ['Completed', 'Cancelled'])
            ->whereDate('target_date', '<', $today)->count();

        $finishedWithDue = (clone $ticketQuery)->whereIn('status', self::COMPLETED)
            ->whereBetween('completed_at', [$periodStart, now()])->whereNotNull('due_date');
        $dueCompleted = (clone $finishedWithDue)->count();
        $onTime = (clone $finishedWithDue)->whereRaw('date(completed_at) <= due_date')->count();
        $productionDone = (clone $ticketQuery)->where('status', 'Production Done')
            ->whereBetween('completed_at', [$periodStart, now()])->count();
        $qaResults = DB::table('pm_qa_verifications as verification')
            ->join('pm_work_items as ticket', 'ticket.id', '=', 'verification.issue_id')
            ->whereIn('ticket.project_id', $scopedIds)->whereNull('ticket.deleted_at')
            ->where('verification.tested_at', '>=', $periodStart)
            ->selectRaw('verification.result, count(*) as aggregate')
            ->groupBy('verification.result')->pluck('aggregate', 'result');
        $qaRuns = (int) $qaResults->sum();
        $qaPasses = (int) ($qaResults['Passed'] ?? 0);

        $counts = [
            'projects' => $projectTotal,
            'active_projects' => $active,
            'open' => (clone $openQuery)->count(),
            'production_done' => $productionDone,
            'production_incidents' => (clone $openQuery)->whereIn('classification_key', ['PRD', 'PDI'])->count(),
            'critical_blockers' => (clone $criticalQuery)->count(),
            'blocked' => (clone $openQuery)->whereIn('id', $blockedIds)->count(),
            'on_time_percent' => $dueCompleted ? (int) round(100 * $onTime / $dueCompleted) : null,
            'on_time_basis' => $onTime.' of '.$dueCompleted.' finished with a due date',
            'due_week' => (clone $openQuery)->whereBetween('due_date', [
                $today->toDateString(), $today->copy()->endOfWeek()->toDateString(),
            ])->count(),
            'overdue' => (clone $openQuery)->whereDate('due_date', '<', $today)->count(),
            'qat_open' => (clone $openQuery)->where('classification_key', 'QAT')->count(),
            'qat_verified' => (clone $ticketQuery)->where('classification_key', 'QAT')
                ->whereIn('status', ['QAT Done', 'Production Done', 'Verified', 'Closed'])->count(),
            'active_releases' => (clone $releaseQuery)->whereNotIn('status', self::RELEASE_CLOSED)->count(),
            'releases_due_30' => (clone $releaseQuery)->whereNotIn('status', self::RELEASE_CLOSED)
                ->whereBetween('planned_deploy_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->count(),
            'qa_pass_rate' => $qaRuns ? (int) round(100 * $qaPasses / $qaRuns) : null,
            'qa_pass_basis' => $qaPasses.' of '.$qaRuns.' checks passed',
        ];

        return [
            'projects' => $projects,
            'projectId' => $projectId,
            'periodDays' => $periodDays,
            'periodStart' => $periodStart,
            'counts' => $counts,
            'health' => [
                'total' => $projectTotal,
                'active' => $active,
                'planned' => $planned,
                'other' => $projectTotal - $active - $planned,
                'active_percent' => $projectTotal ? (int) round(100 * $active / $projectTotal) : null,
                'overdue' => $overdueProjects,
                'overdue_percent' => $projectTotal ? (int) round(100 * $overdueProjects / $projectTotal) : 0,
            ],
            'blockedTickets' => (clone $criticalQuery)->with(['project:id,name', 'developer:id,name', 'qa:id,name'])
                ->orderByDesc('updated_at')->limit(3)->get(['id', 'project_id', 'reference', 'issue_key', 'summary', 'priority', 'developer_id', 'qa_id', 'updated_at']),
            'classifications' => $this->classifications($openQuery),
            'statuses' => $this->statuses($ticketQuery),
            'priorities' => $this->priorities($ticketQuery),
            'releases' => $this->releases($releaseQuery),
        ];
    }

    private function classifications(Builder $openQuery): array
    {
        $raw = (clone $openQuery)->selectRaw('classification_key, count(*) as aggregate')
            ->groupBy('classification_key')->pluck('aggregate', 'classification_key');
        $groups = [
            ['key' => 'REQ', 'label' => 'Requirements', 'codes' => ['REQ', 'FTR'], 'tone' => 'blue'],
            ['key' => 'ENH', 'label' => 'Enhancements', 'codes' => ['ENH', 'EHN'], 'tone' => 'violet'],
            ['key' => 'PRD', 'label' => 'Production issues', 'codes' => ['PRD', 'PDI'], 'tone' => 'red'],
            ['key' => 'QAT', 'label' => 'QA tickets', 'codes' => ['QAT'], 'tone' => 'amber'],
            ['key' => 'TSK', 'label' => 'Development tasks', 'codes' => ['TSK'], 'tone' => 'slate'],
        ];
        $used = [];
        $rows = [];
        foreach ($groups as $group) {
            $count = 0;
            foreach ($group['codes'] as $code) {
                $count += (int) ($raw[$code] ?? 0);
                $used[] = $code;
            }
            $rows[] = ['label' => $group['label'], 'count' => $count, 'tone' => $group['tone'], 'category' => $group['key']];
        }
        $other = (int) $raw->except($used)->sum();
        if ($other) {
            $rows[] = ['label' => 'Other', 'count' => $other, 'tone' => 'slate', 'category' => null];
        }

        return $rows;
    }

    private function statuses(Builder $ticketQuery): array
    {
        return (clone $ticketQuery)->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')->get()->sortByDesc('aggregate')->take(5)
            ->values()->map(fn ($row) => ['label' => $row->status ?: 'Unknown', 'count' => (int) $row->aggregate])->all();
    }

    private function priorities(Builder $ticketQuery): array
    {
        $raw = (clone $ticketQuery)->selectRaw('priority, count(*) as aggregate')
            ->groupBy('priority')->pluck('aggregate', 'priority');
        $known = (int) ($raw['P0'] ?? 0) + (int) ($raw['P1'] ?? 0) + (int) ($raw['P2'] ?? 0)
            + (int) ($raw['P3'] ?? 0) + (int) ($raw['P4'] ?? 0);
        $values = [
            ['label' => 'Critical', 'count' => (int) ($raw['P0'] ?? 0) + (int) ($raw['P1'] ?? 0), 'tone' => 'critical', 'filter' => 'Critical'],
            ['label' => 'High', 'count' => (int) ($raw['P2'] ?? 0), 'tone' => 'high', 'filter' => 'High'],
            ['label' => 'Normal', 'count' => (int) ($raw['P3'] ?? 0), 'tone' => 'medium', 'filter' => 'Normal'],
            ['label' => 'Low', 'count' => (int) ($raw['P4'] ?? 0), 'tone' => 'low', 'filter' => 'Low'],
        ];
        $other = (int) $raw->sum() - $known;
        if ($other) {
            $values[] = ['label' => 'Other', 'count' => $other, 'tone' => 'slate', 'filter' => null];
        }
        $total = (int) $raw->sum();

        return collect($values)->map(function ($row) use ($total) {
            $row['percent'] = $total ? round(100 * $row['count'] / $total, 1) : 0;
            return $row;
        })->all();
    }

    private function releases(Builder $releaseQuery)
    {
        $service = app(PmsReleaseService::class);

        return (clone $releaseQuery)->whereNotIn('status', self::RELEASE_CLOSED)
            ->with(['project:id,key,name', 'owner:id,name', 'cycle.phases', 'globalCycle.stages', 'tickets'])
            ->orderBy('planned_deploy_date')->limit(4)->get()
            ->map(function (PmRelease $release) use ($service) {
                $readiness = $service->readiness($release);
                $passed = collect($readiness['checks'])->where('passed', true)->count();
                $total = count($readiness['checks']);

                return [
                    'release' => $release,
                    'state' => $readiness['state'],
                    'passed' => $passed,
                    'total' => $total,
                    'percent' => $total ? (int) round(100 * $passed / $total) : 0,
                ];
            });
    }
}
