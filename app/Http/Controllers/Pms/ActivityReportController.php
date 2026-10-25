<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PmsActivityReport;
use App\Support\PmsAccess;
use App\Support\PmsDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;

final class ActivityReportController extends Controller
{
    public function index(Request $request, PmsActivityReport $report)
    {
        $filters = $report->filters($request);
        $data = $report->dashboard($request->user(), $filters);
        $legacyEntries = $request->user()->isGlobalSuperAdmin()
            ? DB::table('pm_time_entries')->join('users', 'users.id', '=', 'pm_time_entries.user_id')
                ->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
                ->orderByDesc('pm_time_entries.ended_at')->limit(10)
                ->get(['pm_time_entries.id', 'pm_time_entries.duration_seconds', 'pm_time_entries.description',
                    'users.name as user_name', 'pm_projects.name as project_name']) : collect();
        return $request->expectsJson() ? response()->json(['filters' => $filters] + $data)
            : view('pms.activity-report', ['filters' => $filters, 'legacyEntries' => $legacyEntries] + $data);
    }

    public function employee(Request $request, User $employee, PmsActivityReport $report)
    {
        $filters = $report->filters($request);
        $base = $report->query($request->user(), $filters)->where('activity.user_id', $employee->id);
        abort_unless((clone $base)->exists(), 404);
        $summary = (clone $base)->selectRaw("COALESCE(SUM(CASE WHEN activity.activity_status = 'active' THEN activity.duration_seconds ELSE 0 END), 0) as active_seconds")
            ->selectRaw("COALESCE(SUM(CASE WHEN activity.activity_status = 'idle' THEN activity.duration_seconds ELSE 0 END), 0) as idle_seconds")
            ->selectRaw("MIN(CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.started_at END) as first_at")
            ->selectRaw("MAX(CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.ended_at END) as last_at")
            ->selectRaw("COUNT(DISTINCT CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.activity_date END) as active_days")
            ->selectRaw("COUNT(DISTINCT CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.project_id END) as projects")
            ->selectRaw("COUNT(DISTINCT CASE WHEN activity.activity_status = 'active' AND activity.duration_seconds > 0 THEN activity.ticket_id END) as tickets")
            ->first();
        $timeline = (clone $base)->select('activity.*', 'project.name as project_name', 'ticket.reference as ticket_reference')
            ->orderByDesc('activity.started_at')->paginate(40)->withQueryString();
        $projects = (clone $base)->where('activity.activity_status', 'active')
            ->select('project.name')->selectRaw('SUM(activity.duration_seconds) as seconds')
            ->groupBy('activity.project_id', 'project.name')->orderByDesc('seconds')->get();
        $tickets = (clone $base)->where('activity.activity_status', 'active')->whereNotNull('activity.ticket_id')
            ->select('ticket.reference')->selectRaw('SUM(activity.duration_seconds) as seconds')
            ->groupBy('activity.ticket_id', 'ticket.reference')->orderByDesc('seconds')->get();
        return $request->expectsJson() ? response()->json(compact('filters', 'summary', 'timeline', 'projects', 'tickets'))
            : view('pms.activity-detail', compact('employee', 'filters', 'summary', 'timeline', 'projects', 'tickets'));
    }

    public function export(Request $request, PmsActivityReport $report)
    {
        abort_unless(PmsAccess::timeTracking($request->user(), 'pms.time_tracking.reports.export'), 403);
        $filters = $report->filters($request);
        $query = $report->query($request->user(), $filters)
            ->select('activity.*', 'users.name as user_name', 'profile.job_position', 'profile.department',
                'project.name as project_name', 'ticket.reference as ticket_reference')
            ->orderBy('activity.id');
        $headings = ['Employee Name', 'Position', 'Department', 'Date MYT', 'Project', 'Ticket Reference',
            'Ticket Type', 'First Activity MYT', 'Last Activity MYT', 'Activity Status',
            'Active Duration Seconds', 'Idle Duration Seconds', 'Total Tracked Duration Seconds', 'Reporting Timezone'];
        $map = static function ($row): array {
            $active = $row->activity_status === 'active' ? (int) $row->duration_seconds : 0;
            $idle = $row->activity_status === 'idle' ? (int) $row->duration_seconds : 0;
            $safe = static fn ($value) => preg_match('/^\s*[=+\-@]/u', (string) $value) ? "'".$value : (string) $value;
            return [$safe($row->user_name), $safe($row->job_position), $safe($row->department),
                $row->activity_date, $safe($row->project_name ?: 'General PMS Activity'), $safe($row->ticket_reference),
                $safe($row->ticket_type), PmsDateTime::format($row->started_at, false, 'UTC'),
                PmsDateTime::format($row->ended_at, false, 'UTC'), $row->activity_status,
                $active, $idle, $active + $idle, PmsDateTime::ZONE];
        };
        $filename = 'pms_activity_'.$filters['from'].'_'.$filters['to'];
        if ($request->input('format') === 'xlsx') {
            $sheet = new class($query, $headings, $map) implements FromQuery, WithHeadings, WithMapping {
                public function __construct(private $query, private array $headings, private $map) {}
                public function query() { return $this->query; }
                public function headings(): array { return $this->headings; }
                public function map($row): array { return ($this->map)($row); }
            };
            return Excel::download($sheet, $filename.'.xlsx');
        }
        return response()->streamDownload(function () use ($query, $headings, $map): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headings);
            foreach ($query->cursor() as $row) fputcsv($stream, $map($row));
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
