<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PmsSessionLease
{
    public function inspect(Request $request, User $user): bool
    {
        if ($request->session()->get('pms_expired')) {
            if ((int) $request->session()->get('pms_expired_user_id') === $user->id) return false;
            $request->session()->forget(['pms_expired', 'pms_expired_user_id', 'pms_lease_id']);
        }
        $hash = hash('sha256', $request->session()->getId().'|'.$user->id);
        $eligible = $this->eligibleRequest($request);
        return DB::transaction(function () use ($request, $user, $hash, $eligible): bool {
            $leaseId = $request->session()->get('pms_lease_id');
            $lease = $leaseId ? DB::table('pm_workspace_sessions')->where('id', $leaseId)->lockForUpdate()->first() : null;
            if ($lease && (int) $lease->user_id !== $user->id) {
                DB::table('pm_workspace_sessions')->where('id', $lease->id)->whereNull('ended_at')->update([
                    'ended_at' => now('UTC'), 'end_reason' => 'SECURITY_INVALIDATION', 'updated_at' => now('UTC'),
                ]);
                $request->session()->forget('pms_lease_id');
                $lease = null;
            }
            if (! $lease) {
                $lease = DB::table('pm_workspace_sessions')->where('session_hash', $hash)->lockForUpdate()->first();
                if ($lease) $request->session()->put('pms_lease_id', $lease->id);
            }
            if (! $lease) {
                $previous = DB::table('pm_workspace_sessions')->where('user_id', $user->id)
                    ->orderByDesc('id')->first();
                if ($previous) {
                    app(PmsTimeTracking::class)->reconcile($user->id, Carbon::parse($previous->last_eligible_at, 'UTC'));
                }
                $now = now('UTC');
                DB::table('pm_workspace_sessions')->insertOrIgnore([
                    'session_hash' => $hash, 'user_id' => $user->id, 'started_at' => $now,
                    'activity_nonce' => Str::random(40),
                    'last_eligible_at' => $now, 'last_workspace_at' => null,
                    'active_seconds' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $lease = DB::table('pm_workspace_sessions')->where('session_hash', $hash)->lockForUpdate()->first();
                $request->session()->put('pms_lease_id', $lease->id);
            }
            if ($lease->ended_at) return false;
            $last = Carbon::parse($lease->last_eligible_at, 'UTC');
            if ($last->addSeconds((int) config('pms.session_idle_seconds', 3600))->lessThanOrEqualTo(now('UTC'))) {
                DB::table('pm_workspace_sessions')->where('id', $lease->id)->update([
                    'ended_at' => now('UTC'), 'end_reason' => 'IDLE_TIMEOUT', 'updated_at' => now('UTC'),
                ]);
                $this->recordEnd($lease->id, $user->id);
                app(PmsTimeTracking::class)->reconcile($user->id, Carbon::parse($lease->last_eligible_at, 'UTC'));
                return false;
            }
            if ($eligible) {
                app(PmsTimeTracking::class)->reconcile($user->id, Carbon::parse($lease->last_eligible_at, 'UTC'));
                DB::table('pm_workspace_sessions')->where('id', $lease->id)
                    ->update(['last_eligible_at' => now('UTC'), 'updated_at' => now('UTC')]);
            }
            return true;
        });
    }

    public function activity(Request $request, User $user, bool $workspace = true): object
    {
        return DB::transaction(function () use ($request, $user, $workspace) {
            $lease = DB::table('pm_workspace_sessions')->where('id', $request->session()->get('pms_lease_id'))
                ->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($lease && ! $lease->ended_at, 401);
            abort_unless(hash_equals($lease->activity_nonce, (string) $request->input('nonce')), 409,
                'Refresh session state before registering another interaction.');
            $last = Carbon::parse($lease->last_eligible_at, 'UTC');
            abort_if($last->addSeconds((int) config('pms.session_idle_seconds', 3600))->lessThanOrEqualTo(now('UTC')), 401);
            $now = now('UTC');
            app(PmsTimeTracking::class)->reconcile($user->id, Carbon::parse($lease->last_eligible_at, 'UTC'));
            $active = (int) $lease->active_seconds;
            if ($workspace && $lease->last_workspace_at) {
                $gap = Carbon::parse($lease->last_workspace_at, 'UTC')->diffInSeconds($now);
                if ($gap <= (int) config('pms.active_idle_seconds', 300)) {
                    $seconds = min($gap, 60);
                    if ($seconds > 0) {
                        DB::table('pm_workspace_activity_intervals')->insert([
                            'session_id' => $lease->id, 'user_id' => $user->id,
                            'started_at' => $now->copy()->subSeconds($seconds), 'ended_at' => $now,
                            'duration_seconds' => $seconds,
                            'activity_date' => $now->copy()->timezone('Asia/Kuala_Lumpur')->toDateString(),
                            'activity_status' => 'active',
                        ]);
                        $active += $seconds;
                    }
                }
            }
            DB::table('pm_workspace_sessions')->where('id', $lease->id)->update([
                'last_eligible_at' => $now,
                'activity_nonce' => Str::random(40),
                'last_workspace_at' => $workspace ? $now : $lease->last_workspace_at,
                'active_seconds' => $active, 'updated_at' => $now,
            ]);
            return DB::table('pm_workspace_sessions')->where('id', $lease->id)->first();
        });
    }

    public function current(Request $request, User $user): ?object
    {
        return DB::table('pm_workspace_sessions')->where('id', $request->session()->get('pms_lease_id'))
            ->where('user_id', $user->id)->whereNull('ended_at')->first();
    }

    public function close(Request $request, ?User $user, string $reason): void
    {
        if (! $user) return;
        $lease = $this->current($request, $user);
        if (! $lease) return;
        app(PmsTimeTracking::class)->reconcile($user->id, Carbon::parse($lease->last_eligible_at, 'UTC'));
        DB::table('pm_workspace_sessions')->where('id', $lease->id)->whereNull('ended_at')->update([
            'ended_at' => now('UTC'), 'end_reason' => $reason, 'updated_at' => now('UTC'),
        ]);
        $this->recordEnd($lease->id, $user->id);
    }

    private function recordEnd(int $sessionId, int $userId): void
    {
        $now = now('UTC');
        DB::table('pm_workspace_activity_intervals')->insert([
            'session_id' => $sessionId, 'user_id' => $userId,
            'started_at' => $now, 'ended_at' => $now, 'duration_seconds' => 0,
            'activity_date' => $now->copy()->timezone('Asia/Kuala_Lumpur')->toDateString(),
            'activity_classification' => 'Session Ended', 'activity_status' => 'ended',
        ]);
    }

    private function eligibleRequest(Request $request): bool
    {
        // Presence heartbeats never renew the one-hour authentication lease.
        if ($request->is('backoffice/project-management/time-tracking/heartbeat')) return false;
        if ($request->isMethod('GET')) return $request->header('Sec-Fetch-Mode') === 'navigate'
            || (! $request->ajax() && str_contains((string) $request->header('Accept'), 'text/html')
                && ! str_contains($request->path(), '/time-tracking/session'));
        if (str_contains($request->path(), '/time-tracking/session')) return false;
        return ! $request->isMethod('HEAD');
    }
}
