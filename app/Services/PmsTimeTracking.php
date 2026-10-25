<?php

namespace App\Services;

use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PmsTimeTracking
{
    public function eligibleProject(User $user, int $projectId, ?int $ticketId = null): PmProject
    {
        $project = PmProject::findOrFail($projectId);
        abort_unless(! $project->archived_at && PmsAccess::allows($user, $project, 'view'), 403);
        if ($ticketId !== null) {
            abort_unless(PmWorkItem::where('id', $ticketId)->where('project_id', $projectId)->exists(), 422);
        }
        return $project;
    }

    public function reconcile(int $userId, Carbon $lastEligible): ?object
    {
        return DB::transaction(function () use ($userId, $lastEligible) {
            $timer = DB::table('pm_work_timers')->where('user_id', $userId)->lockForUpdate()->first();
            if (! $timer || $timer->status !== 'running') return $timer;
            $project = PmProject::find($timer->project_id);
            if (! $project || $project->archived_at || ! PmsAccess::allows(User::find($userId), $project, 'view')) {
                DB::table('pm_work_timers')->where('user_id', $userId)->update([
                    'status' => 'paused', 'resumed_at' => null, 'updated_at' => now('UTC'),
                ]);
                return DB::table('pm_work_timers')->where('user_id', $userId)->first();
            }
            $now = now('UTC');
            $cutoff = $lastEligible->copy()->addSeconds((int) config('pms.active_idle_seconds', 300));
            $end = $now->lessThan($cutoff) ? $now : $cutoff;
            $start = Carbon::parse($timer->resumed_at, 'UTC');
            $seconds = max(0, $end->timestamp - $start->timestamp);
            $expired = $now->greaterThan($cutoff);
            DB::table('pm_work_timers')->where('user_id', $userId)->update([
                'duration_seconds' => $timer->duration_seconds + $seconds,
                'resumed_at' => $expired ? null : $now,
                'status' => $expired ? 'paused' : 'running', 'updated_at' => $now,
            ]);
            return DB::table('pm_work_timers')->where('user_id', $userId)->first();
        });
    }

    public function control(User $user, string $action, array $data, Carbon $lastEligible): ?object
    {
        if ($action === 'start') {
            $this->eligibleProject($user, (int) $data['project_id'], isset($data['ticket_id']) ? (int) $data['ticket_id'] : null);
            return DB::transaction(function () use ($user, $data) {
                $now = now('UTC');
                $inserted = DB::table('pm_work_timers')->insertOrIgnore([
                    'user_id' => $user->id, 'project_id' => $data['project_id'],
                    'ticket_id' => $data['ticket_id'] ?? null, 'work_type' => $data['work_type'],
                    'description' => $data['description'], 'status' => 'running',
                    'started_at' => $now, 'resumed_at' => $now, 'duration_seconds' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $timer = DB::table('pm_work_timers')->where('user_id', $user->id)->lockForUpdate()->first();
                if (! $inserted) {
                    throw ValidationException::withMessages(['timer' => 'You already have a work timer. Stop it before starting another.']);
                }
                return $timer;
            });
        }
        $this->reconcile($user->id, $lastEligible);
        return DB::transaction(function () use ($user, $action) {
            $timer = DB::table('pm_work_timers')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $timer) throw ValidationException::withMessages(['timer' => 'No work timer is running.']);
            if ($action === 'resume') {
                $this->eligibleProject($user, (int) $timer->project_id, $timer->ticket_id ? (int) $timer->ticket_id : null);
            }
            if ($action === 'pause' && $timer->status === 'running') {
                DB::table('pm_work_timers')->where('user_id', $user->id)->update(['status' => 'paused', 'resumed_at' => null, 'updated_at' => now('UTC')]);
            } elseif ($action === 'resume' && $timer->status === 'paused') {
                DB::table('pm_work_timers')->where('user_id', $user->id)->update(['status' => 'running', 'resumed_at' => now('UTC'), 'updated_at' => now('UTC')]);
            } elseif ($action === 'stop') {
                DB::table('pm_time_entries')->insert([
                    'user_id' => $user->id, 'project_id' => $timer->project_id,
                    'ticket_id' => $timer->ticket_id, 'work_type' => $timer->work_type,
                    'description' => $timer->description, 'started_at' => $timer->started_at,
                    'ended_at' => now('UTC'), 'duration_seconds' => $timer->duration_seconds,
                    'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
                DB::table('pm_work_timers')->where('user_id', $user->id)->delete();
                return null;
            } else {
                throw ValidationException::withMessages(['timer' => 'This timer action is unavailable in its current state.']);
            }
            return DB::table('pm_work_timers')->where('user_id', $user->id)->first();
        });
    }
}
