<?php

namespace App\Services;

use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsDateTime;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PmsActivityReport
{
    public function filters(Request $request): array
    {
        $data = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'last7', 'week', 'last30', 'month', 'custom'])],
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'employee_ids' => 'nullable|array|max:30', 'employee_ids.*' => 'integer|min:1',
            'position' => 'nullable|string|max:100', 'department' => 'nullable|string|max:100',
            'project_id' => 'nullable|integer|min:1', 'ticket_type' => 'nullable|string|max:12',
            'activity_status' => ['nullable', Rule::in(['active', 'idle'])],
            'sort' => ['nullable', Rule::in(['date', 'employee', 'project', 'active', 'idle'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $today = now(PmsDateTime::ZONE)->startOfDay();
        $range = $data['range'] ?? 'last7';
        $from = match ($range) {
            'today' => $today->copy(), 'yesterday' => $today->copy()->subDay(),
            'week' => $today->copy()->startOfWeek(), 'last30' => $today->copy()->subDays(29),
            'month' => $today->copy()->startOfMonth(),
            'custom' => Carbon::parse($data['from'] ?? $today->toDateString(), PmsDateTime::ZONE)->startOfDay(),
            default => $today->copy()->subDays(6),
        };
        $to = $range === 'yesterday' ? $today->copy()->subDay()
            : ($range === 'custom' ? Carbon::parse($data['to'] ?? $from->toDateString(), PmsDateTime::ZONE)->startOfDay() : $today->copy());
        $data['range'] = $range;
        $data['from'] = $from->toDateString();
        $data['to'] = $to->toDateString();
        $data['sort'] = $data['sort'] ?? 'date';
        $data['direction'] = $data['direction'] ?? 'desc';
        return $data;
    }

    public function query(User $viewer, array $filters): Builder
    {
        abort_unless(PmsAccess::timeTracking($viewer, 'pms.time_tracking.reports.view'), 403);
        $query = DB::table('pm_workspace_activity_intervals as activity')
            ->join('users', 'users.id', '=', 'activity.user_id')
            ->leftJoin('pms_profiles as profile', 'profile.user_id', '=', 'activity.user_id')
            ->leftJoin('pm_projects as project', 'project.id', '=', 'activity.project_id')
            ->leftJoin('pm_work_items as ticket', 'ticket.id', '=', 'activity.ticket_id')
            ->whereBetween('activity.activity_date', [$filters['from'], $filters['to']]);
        if (! $viewer->isGlobalSuperAdmin()) {
            $ids = PmsAccess::timeTrackingProjectIds($viewer);
            $query->whereIn('activity.project_id', $ids);
        }
        if ($ids = $filters['employee_ids'] ?? []) $query->whereIn('activity.user_id', $ids);
        if ($position = $filters['position'] ?? null) $query->where('profile.job_position', $position);
        if ($department = $filters['department'] ?? null) $query->where('profile.department', $department);
        if ($project = $filters['project_id'] ?? null) $query->where('activity.project_id', $project);
        if ($type = $filters['ticket_type'] ?? null) $query->where('activity.ticket_type', $type);
        if ($status = $filters['activity_status'] ?? null) $query->where('activity.activity_status', $status);
        return $query;
    }

    public function dashboard(User $viewer, array $filters): array
    {
        $base = $this->query($viewer, $filters);
        $totals = (clone $base)->selectRaw("COALESCE(SUM(CASE WHEN activity.activity_status = 'active' THEN activity.duration_seconds ELSE 0 END), 0) as active_seconds")
            ->selectRaw("COALESCE(SUM(CASE WHEN activity.activity_status = 'idle' THEN activity.duration_seconds ELSE 0 END), 0) as idle_seconds")
            ->selectRaw("COUNT(DISTINCT activity.user_id) as employees, COUNT(DISTINCT CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.project_id END) as projects")
            ->first();
        $days = Carbon::parse($filters['from'], PmsDateTime::ZONE)->diffInDays(Carbon::parse($filters['to'], PmsDateTime::ZONE)) + 1;
        $previousFilters = $filters;
        $previousFilters['to'] = Carbon::parse($filters['from'], PmsDateTime::ZONE)->subDay()->toDateString();
        $previousFilters['from'] = Carbon::parse($filters['from'], PmsDateTime::ZONE)->subDays($days)->toDateString();
        $previousSeconds = (int) (clone $this->query($viewer, $previousFilters))->where('activity.activity_status', 'active')->sum('activity.duration_seconds');
        $trend = $previousSeconds > 0 ? (int) round(((int) $totals->active_seconds - $previousSeconds) * 100 / $previousSeconds) : null;
        $sort = ['date' => 'activity_date', 'employee' => 'user_name', 'project' => 'project_name',
            'active' => 'active_seconds', 'idle' => 'idle_seconds'][$filters['sort']];
        $rows = (clone $base)->select(
            'activity.user_id', 'activity.project_id', 'activity.activity_date', 'users.name as user_name',
            'profile.job_position', 'profile.department', 'project.name as project_name'
        )->selectRaw("MIN(CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.started_at END) as first_at")
            ->selectRaw("MAX(CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.ended_at END) as last_at")
            ->selectRaw("SUM(CASE WHEN activity.activity_status = 'active' THEN activity.duration_seconds ELSE 0 END) as active_seconds")
            ->selectRaw("SUM(CASE WHEN activity.activity_status = 'idle' THEN activity.duration_seconds ELSE 0 END) as idle_seconds")
            ->groupBy('activity.user_id', 'activity.project_id', 'activity.activity_date', 'users.name',
                'profile.job_position', 'profile.department', 'project.name')
            ->orderBy($sort, $filters['direction'])->orderBy('activity.user_id')->paginate(25)->withQueryString();
        $visibleUsers = $rows->getCollection()->pluck('user_id')->unique()->all();
        $liveTabs = DB::table('pm_workspace_tab_states as tab')->join('pm_workspace_sessions as session', 'session.id', '=', 'tab.session_id')
            ->whereIn('session.user_id', $visibleUsers)->whereNull('session.ended_at')
            ->where('tab.last_seen_at', '>=', now('UTC')->subSeconds(90))
            ->orderByRaw("CASE tab.state WHEN 'active' THEN 0 WHEN 'idle' THEN 1 ELSE 2 END")
            ->orderByDesc('tab.last_seen_at')->get(['session.user_id', 'tab.state'])->unique('user_id')->keyBy('user_id');
        $latestSessions = DB::table('pm_workspace_sessions')->whereIn('user_id', $visibleUsers)
            ->orderByDesc('started_at')->get(['user_id', 'ended_at'])->unique('user_id')->keyBy('user_id');
        $daily = (clone $base)->where('activity.activity_status', 'active')
            ->select('activity.activity_date')->selectRaw('SUM(activity.duration_seconds) as seconds')
            ->groupBy('activity.activity_date')->orderBy('activity.activity_date')->get();
        $weekly = $daily->groupBy(fn ($item) => Carbon::parse($item->activity_date, PmsDateTime::ZONE)
            ->startOfWeek()->toDateString())->map(fn ($items, $week) => (object) [
                'week' => $week, 'seconds' => $items->sum('seconds'),
            ])->values();
        $employees = (clone $base)->where('activity.activity_status', 'active')
            ->select('users.name')->selectRaw('SUM(activity.duration_seconds) as seconds')
            ->groupBy('activity.user_id', 'users.name')->orderByDesc('seconds')->limit(8)->get();
        $projects = (clone $base)->where('activity.activity_status', 'active')
            ->select('project.name')->selectRaw('SUM(activity.duration_seconds) as seconds')
            ->groupBy('activity.project_id', 'project.name')->orderByDesc('seconds')->limit(8)->get();
        $options = [
            'projects' => DB::table('pm_projects')->whereIn('id', PmsAccess::timeTrackingProjectIds($viewer))->orderBy('name')->get(['id', 'name']),
            'employees' => DB::table('users')->join('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')
                ->where('pms_profiles.status', 'active')->whereIn('users.id', DB::table('pm_project_members')
                    ->whereIn('project_id', PmsAccess::timeTrackingProjectIds($viewer))->select('user_id'))
                ->orderBy('users.name')->get(['users.id', 'users.name']),
        ];
        return compact('totals', 'trend', 'rows', 'liveTabs', 'latestSessions', 'daily', 'weekly', 'employees', 'projects', 'options');
    }
}
