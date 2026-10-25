<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pms:prune-session-history', function () {
    $days = \Illuminate\Support\Facades\DB::table('pms_workspace_settings')->value('activity_retention_days')
        ?: config('pms.session_history_days', 365);
    $cutoff = now('UTC')->subDays(max(30, (int) $days));
    \Illuminate\Support\Facades\DB::table('pm_workspace_sessions')->whereNull('ended_at')
        ->where('last_eligible_at', '<', now('UTC')->subSeconds((int) config('pms.session_idle_seconds', 3600)))
        ->update(['ended_at' => now('UTC'), 'end_reason' => 'STALE_SESSION', 'updated_at' => now('UTC')]);
    $deleted = 0;
    do {
        $ids = \Illuminate\Support\Facades\DB::table('pm_workspace_sessions')
            ->whereNotNull('ended_at')->where('ended_at', '<', $cutoff)
            ->orderBy('id')->limit(500)->pluck('id')->all();
        if ($ids) \Illuminate\Support\Facades\DB::transaction(function () use ($ids, &$deleted): void {
            \Illuminate\Support\Facades\DB::table('pm_workspace_tab_states')->whereIn('session_id', $ids)->delete();
            \Illuminate\Support\Facades\DB::table('pm_workspace_activity_intervals')->whereIn('session_id', $ids)->delete();
            $deleted += \Illuminate\Support\Facades\DB::table('pm_workspace_sessions')->whereIn('id', $ids)->delete();
        });
    } while (count($ids) === 500);
    $this->info("Pruned {$deleted} ended PMS sessions.");
})->purpose('Prune ended PMS session metadata after the configured retention period');
