<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Services\PmsBlockers;
use App\Services\PmsCycleProgress;
use App\Support\PmsAccess;
use App\Support\PmsDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CycleProgressReportController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'project_id' => 'nullable|integer|min:1', 'cycle_id' => 'nullable|integer|min:1',
            'release_id' => 'nullable|integer|min:1', 'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'developer_id' => 'nullable|integer|min:1', 'qa_id' => 'nullable|integer|min:1',
            'ticket_type' => 'nullable|string|max:12', 'ticket_status' => 'nullable|string|max:50',
        ]);
    }

    private function scope(Request $request, array $filters)
    {
        abort_unless(PmsAccess::timeTracking($request->user(), 'pms.time_tracking.reports.view'), 403);
        $query = PmCycle::query()->whereIn('project_id', PmsAccess::timeTrackingProjectIds($request->user()));
        if ($value = $filters['project_id'] ?? null) $query->where('project_id', $value);
        if ($value = $filters['cycle_id'] ?? null) $query->whereKey($value);
        if ($value = $filters['date_from'] ?? null) $query->whereDate('start_date', '>=', $value);
        if ($value = $filters['date_to'] ?? null) $query->whereDate('target_end_date', '<=', $value);
        if ($this->hasTicketFilters($filters)) {
            $query->whereHas('issues', fn ($issues) => $this->filterIssues($issues, $filters));
        }
        return $query;
    }

    private function hasTicketFilters(array $filters): bool
    {
        foreach (['release_id', 'developer_id', 'qa_id', 'ticket_type', 'ticket_status'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') return true;
        }
        return false;
    }

    private function filterIssues($query, array $filters): void
    {
        foreach (['release_id' => 'release_id', 'developer_id' => 'developer_id', 'qa_id' => 'qa_id',
            'ticket_type' => 'classification_key', 'ticket_status' => 'status'] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') $query->where($column, $filters[$filter]);
        }
    }

    public function index(Request $request, PmsCycleProgress $progress, PmsBlockers $blockers)
    {
        $filters = $this->filters($request);
        $cycles = $this->scope($request, $filters)->with([
            'project:id,name,key', 'phases', 'issues' => fn ($issues) => $this->filterIssues($issues, $filters),
        ])
            ->orderByDesc('start_date')->paginate(20)->withQueryString();
        $stages = $cycles->getCollection()->mapWithKeys(fn ($cycle) => [$cycle->id => $progress->forCycle($cycle)])->all();
        $today = now(PmsDateTime::ZONE)->toDateString();
        $phaseNames = $cycles->getCollection()->mapWithKeys(function ($cycle) use ($today): array {
            $current = $cycle->phases->first(fn ($phase) => $phase->planned_start_date->toDateString() <= $today
                && $phase->planned_end_date->toDateString() >= $today);
            return [$cycle->id => $current?->name ?: ($cycle->status === 'Closed' ? 'Complete' : 'Between phases')];
        })->all();
        $blockedIds = $blockers->activeTicketIds()->flip();
        $projectIds = PmsAccess::timeTrackingProjectIds($request->user());
        $projects = DB::table('pm_projects')->whereIn('id', $projectIds)->orderBy('name')->get(['id', 'name']);
        $releases = DB::table('pm_releases')->whereIn('project_id', $projectIds)->orderByDesc('id')->get(['id', 'release_key']);
        $people = DB::table('users')->whereIn('id', DB::table('pm_project_members')->whereIn('project_id', $projectIds)->select('user_id'))
            ->orderBy('name')->get(['id', 'name']);
        return $request->expectsJson() ? response()->json(compact('filters', 'cycles', 'stages', 'phaseNames'))
            : view('pms.cycle-progress-report', compact('filters', 'cycles', 'stages', 'phaseNames', 'blockedIds', 'projects', 'releases', 'people'));
    }

    public function export(Request $request, PmsCycleProgress $progress, PmsBlockers $blockers)
    {
        abort_unless(PmsAccess::timeTracking($request->user(), 'pms.time_tracking.reports.export'), 403);
        $filters = $this->filters($request);
        $query = $this->scope($request, $filters)->with([
            'project:id,name,key', 'issues' => fn ($issues) => $this->filterIssues($issues, $filters),
            'issues.developer:id,name', 'issues.qa:id,name',
        ])
            ->orderBy('id');
        $blocked = $blockers->activeTicketIds()->flip();
        $safe = static fn ($value) => preg_match('/^\s*[=+\-@]/u', (string) $value) ? "'".$value : (string) $value;
        return response()->streamDownload(function () use ($query, $progress, $blocked, $safe): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Project', 'Development Cycle', 'Ticket ID', 'Ticket Title', 'Type',
                'Developer', 'QA Engineer', 'Status', 'Development Completed', 'Testing Completed',
                'Production Completed', 'Reopen Count', 'Blocked', 'Last Updated MYT']);
            $query->chunk(100, function ($cycles) use ($stream, $progress, $blocked, $safe): void {
                foreach ($cycles as $cycle) foreach ($cycle->issues as $ticket) {
                    $done = static fn ($stage) => ! $progress->applies($ticket, $stage) ? 'N/A'
                        : (! $blocked->has($ticket->id) && $progress->complete($ticket, $stage) ? 'Yes' : 'No');
                    fputcsv($stream, array_map($safe, [$cycle->project?->name, $cycle->cycle_key, $ticket->reference,
                        $ticket->summary, $ticket->classification_key, $ticket->developer?->name,
                        $ticket->qa?->name, $ticket->status, $done('development'), $done('testing'),
                        $done('production'), $ticket->reopen_count, $blocked->has($ticket->id) ? 'Yes' : 'No',
                        PmsDateTime::format($ticket->updated_at, false, 'UTC')]));
                }
            });
            fclose($stream);
        }, 'pms_cycle_progress_'.now(PmsDateTime::ZONE)->format('Y-m-d_Hi').'_MYT.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
