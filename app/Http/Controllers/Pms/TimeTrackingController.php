<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmProject;
use App\Services\PmsSessionLease;
use App\Services\PmsAutomaticActivity;
use App\Services\PmsTimeTracking;
use App\Support\PmsAccess;
use App\Support\PmsDateTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimeTrackingController extends Controller
{
    private const WORK_TYPES = ['Development', 'QA Testing', 'Business Analysis', 'Project Management',
        'Documentation', 'Meeting', 'Investigation', 'Other'];

    public function index(Request $request, PmsSessionLease $lease, PmsTimeTracking $tracking)
    {
        $user = $request->user();
        $projects = PmProject::query()->whereNull('archived_at')
            ->when(! PmsAccess::administrator($user), fn ($query) => $query->whereHas('members', fn ($members) => $members->where('users.id', $user->id)))
            ->orderBy('name')->get(['id', 'project_reference', 'key', 'name', 'owner_id', 'archived_at']);
        $tickets = DB::table('pm_work_items')->whereIn('project_id', $projects->pluck('id'))
            ->whereNull('deleted_at')->orderBy('reference')->get(['id', 'project_id', 'reference', 'summary']);
        $session = $lease->current($request, $user);
        $last = Carbon::parse($session->last_eligible_at, 'UTC');
        $timer = $tracking->reconcile($user->id, $last);
        $range = $request->input('range', 'today');
        $start = match ($range) {
            'week' => now(PmsDateTime::ZONE)->startOfWeek(),
            'month' => now(PmsDateTime::ZONE)->startOfMonth(),
            default => now(PmsDateTime::ZONE)->startOfDay(),
        };
        $startUtc = $start->copy()->utc();
        $entries = DB::table('pm_time_entries')->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
            ->leftJoin('pm_work_items', 'pm_work_items.id', '=', 'pm_time_entries.ticket_id')
            ->where('pm_time_entries.user_id', $user->id)->where('pm_time_entries.ended_at', '>=', $startUtc)
            ->select('pm_time_entries.*', 'pm_projects.project_reference', 'pm_projects.name as project_name',
                'pm_work_items.reference as ticket_reference')->orderByDesc('pm_time_entries.ended_at')->paginate(20)->withQueryString();
        $tracked = DB::table('pm_time_entries')->where('user_id', $user->id)
            ->where('ended_at', '>=', $startUtc)->sum('duration_seconds');
        $trackedTotals = collect(['today' => now(PmsDateTime::ZONE)->startOfDay(),
            'week' => now(PmsDateTime::ZONE)->startOfWeek(),
            'month' => now(PmsDateTime::ZONE)->startOfMonth()])
            ->map(fn ($from) => (int) DB::table('pm_time_entries')->where('user_id', $user->id)
                ->where('ended_at', '>=', $from->utc())->sum('duration_seconds'));
        $workspace = DB::table('pm_workspace_activity_intervals')->where('user_id', $user->id)
            ->where('ended_at', '>=', $startUtc)->sum('duration_seconds');
        $sessions = DB::table('pm_workspace_sessions')->where('user_id', $user->id)
            ->orderByDesc('started_at')->limit(10)->get();
        $sessionSeconds = DB::table('pm_workspace_sessions')->where('user_id', $user->id)
            ->where(function ($query) use ($startUtc) {
                $query->where('started_at', '>=', $startUtc)
                    ->orWhere('last_eligible_at', '>=', $startUtc);
            })->get(['started_at', 'last_eligible_at', 'ended_at'])->sum(function ($row) use ($startUtc) {
                $start = max(Carbon::parse($row->started_at, 'UTC')->timestamp, $startUtc->timestamp);
                $last = Carbon::parse($row->last_eligible_at, 'UTC')->addSeconds((int) config('pms.session_idle_seconds', 3600));
                $end = min($row->ended_at ? Carbon::parse($row->ended_at, 'UTC')->timestamp : now('UTC')->timestamp,
                    $last->timestamp);
                return max(0, $end - $start);
            });
        $idle = max(0, $sessionSeconds - $workspace);
        $managedIds = $projects->filter(fn ($project) => PmsAccess::manageOwner($user, $project))->pluck('id');
        $team = $managedIds->isEmpty() ? collect() : DB::table('pm_time_entries')
            ->join('users', 'users.id', '=', 'pm_time_entries.user_id')
            ->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
            ->whereIn('pm_time_entries.project_id', $managedIds)->where('pm_time_entries.ended_at', '>=', $startUtc)
            ->groupBy('pm_time_entries.project_id', 'pm_projects.name', 'pm_time_entries.user_id', 'users.name')
            ->select('pm_projects.name as project_name', 'users.name as user_name')
            ->selectRaw('SUM(pm_time_entries.duration_seconds) as seconds')->orderBy('pm_projects.name')->get();
        $teamEntries = $user->isGlobalSuperAdmin() ? DB::table('pm_time_entries')
            ->join('users', 'users.id', '=', 'pm_time_entries.user_id')
            ->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
            ->where('pm_time_entries.ended_at', '>=', $startUtc)
            ->select('pm_time_entries.id', 'pm_time_entries.duration_seconds', 'pm_time_entries.description',
                'users.name as user_name', 'pm_projects.name as project_name')
            ->orderByDesc('pm_time_entries.ended_at')->limit(20)->get() : collect();
        $allocation = DB::table('pm_time_entries')->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
            ->where('pm_time_entries.user_id', $user->id)->where('pm_time_entries.ended_at', '>=', $startUtc)
            ->groupBy('pm_time_entries.project_id', 'pm_projects.name')
            ->select('pm_projects.name as project_name')->selectRaw('SUM(pm_time_entries.duration_seconds) as seconds')
            ->orderByDesc('seconds')->get();
        $ticketAllocation = DB::table('pm_time_entries')->join('pm_work_items', 'pm_work_items.id', '=', 'pm_time_entries.ticket_id')
            ->where('pm_time_entries.user_id', $user->id)->where('pm_time_entries.ended_at', '>=', $startUtc)
            ->groupBy('pm_time_entries.ticket_id', 'pm_work_items.reference')
            ->select('pm_work_items.reference')->selectRaw('SUM(pm_time_entries.duration_seconds) as seconds')
            ->orderByDesc('seconds')->limit(10)->get();

        return view('pms.time-tracking', compact('projects', 'tickets', 'session', 'timer', 'entries', 'tracked', 'trackedTotals', 'workspace', 'idle', 'sessions', 'team', 'teamEntries', 'allocation', 'ticketAllocation', 'range'));
    }

    public function status(Request $request, PmsSessionLease $lease, PmsTimeTracking $tracking)
    {
        $session = $lease->current($request, $request->user());
        abort_unless($session, 401);
        $timer = $tracking->reconcile($request->user()->id, Carbon::parse($session->last_eligible_at, 'UTC'));
        $expires = Carbon::parse($session->last_eligible_at, 'UTC')
            ->addSeconds((int) config('pms.session_idle_seconds', 3600));
        return response()->json(['expires_at' => $expires->toIso8601String(),
            'seconds_remaining' => max(0, $expires->timestamp - now('UTC')->timestamp),
            'active_seconds' => $session->active_seconds, 'activity_nonce' => $session->activity_nonce,
            'timer' => $timer]);
    }

    public function activity(Request $request, PmsSessionLease $lease)
    {
        $data = $request->validate(['workspace' => 'required|boolean', 'nonce' => 'required|string']);
        $session = $lease->activity($request, $request->user(), (bool) $data['workspace']);
        $expires = Carbon::parse($session->last_eligible_at, 'UTC')
            ->addSeconds((int) config('pms.session_idle_seconds', 3600));
        return response()->json(['expires_at' => $expires->toIso8601String(),
            'seconds_remaining' => max(0, $expires->timestamp - now('UTC')->timestamp),
            'active_seconds' => $session->active_seconds, 'activity_nonce' => $session->activity_nonce]);
    }

    public function control(Request $request, PmsSessionLease $lease, PmsTimeTracking $tracking)
    {
        abort_unless(PmsAccess::timeTracking($request->user(), 'pms.time_tracking.manage'), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'pause', 'resume', 'stop'])],
            'project_id' => 'required_if:action,start|nullable|integer|exists:pm_projects,id',
            'ticket_id' => 'nullable|integer|exists:pm_work_items,id',
            'work_type' => ['required_if:action,start', 'nullable', Rule::in(self::WORK_TYPES)],
            'description' => 'required_if:action,start|nullable|string|max:2000',
        ]);
        $session = $lease->current($request, $request->user());
        abort_unless($session, 401);
        $timer = $tracking->control($request->user(), $data['action'], $data,
            Carbon::parse($session->last_eligible_at, 'UTC'));
        return $request->expectsJson() ? response()->json(['timer' => $timer, 'saved' => $data['action'] === 'stop'])
            : redirect()->route('pms.time-tracking.index')->with('success', 'Work timer '.($data['action'] === 'stop' ? 'saved' : $data['action'].'d').'.');
    }

    public function export(Request $request)
    {
        abort_unless(PmsAccess::timeTracking($request->user(), 'pms.time_tracking.reports.export'), 403);
        $range = $request->input('range', 'today');
        $start = match ($range) {
            'week' => now(PmsDateTime::ZONE)->startOfWeek(),
            'month' => now(PmsDateTime::ZONE)->startOfMonth(),
            default => now(PmsDateTime::ZONE)->startOfDay(),
        };
        $query = DB::table('pm_time_entries')->join('pm_projects', 'pm_projects.id', '=', 'pm_time_entries.project_id')
            ->leftJoin('pm_work_items', 'pm_work_items.id', '=', 'pm_time_entries.ticket_id')
            ->where('pm_time_entries.user_id', $request->user()->id)
            ->where('pm_time_entries.ended_at', '>=', $start->utc())
            ->select('pm_time_entries.*', 'pm_projects.project_reference', 'pm_projects.name as project_name',
                'pm_work_items.reference as ticket_reference')
            ->orderBy('pm_time_entries.ended_at')->orderBy('pm_time_entries.id');
        $safe = static function ($value): string {
            $text = (string) $value;
            return preg_match('/^[\s]*[=+\-@]/u', $text) ? "'".$text : $text;
        };
        return response()->streamDownload(function () use ($query, $safe): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Project ID', 'Project', 'Ticket', 'Work Type', 'Description', 'Started MYT', 'Ended MYT', 'Duration Seconds']);
            $query->chunk(200, function ($rows) use ($stream, $safe): void {
                foreach ($rows as $row) fputcsv($stream, array_map($safe, [
                    $row->project_reference, $row->project_name, $row->ticket_reference,
                    $row->work_type, $row->description,
                    PmsDateTime::format($row->started_at, true, 'UTC'),
                    PmsDateTime::format($row->ended_at, true, 'UTC'), $row->duration_seconds,
                ]));
            });
            fclose($stream);
        }, 'pms_time_tracking_'.now(PmsDateTime::ZONE)->format('Y-m-d_Hi').'_MYT.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function adjust(Request $request, int $entry)
    {
        abort_unless($request->user()->isGlobalSuperAdmin(), 403);
        $data = $request->validate([
            'duration_seconds' => 'required|integer|min:0|max:86400',
            'description' => 'required|string|max:2000',
            'reason' => 'required|string|min:10|max:2000',
        ]);
        $updated = DB::transaction(function () use ($request, $entry, $data) {
            $locked = DB::table('pm_time_entries')->where('id', $entry)->lockForUpdate()->first();
            abort_unless($locked, 404);
            DB::table('pm_time_entry_adjustments')->insert([
                'entry_id' => $entry, 'actor_id' => $request->user()->id,
                'old_duration_seconds' => $locked->duration_seconds,
                'new_duration_seconds' => $data['duration_seconds'],
                'old_description' => $locked->description,
                'new_description' => $data['description'],
                'reason' => $data['reason'], 'created_at' => now('UTC'),
            ]);
            DB::table('pm_time_entries')->where('id', $entry)->update([
                'duration_seconds' => $data['duration_seconds'],
                'description' => $data['description'], 'updated_at' => now('UTC'),
            ]);
            return DB::table('pm_time_entries')->where('id', $entry)->first();
        });
        return $request->expectsJson() ? response()->json($updated)
            : back()->with('success', 'Time entry correction recorded.');
    }

    public function heartbeat(Request $request, PmsAutomaticActivity $activity)
    {
        $data = $request->validate([
            'tab_id' => 'required|uuid', 'visible' => 'required|boolean',
            'focused' => 'required|boolean', 'interacted' => 'required|boolean',
            'project_id' => 'nullable|integer', 'ticket_id' => 'nullable|integer',
            'context_signature' => 'nullable|string|size:64',
        ]);
        return response()->json($activity->heartbeat($request, $request->user(), $data));
    }
}
