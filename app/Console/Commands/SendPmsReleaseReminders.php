<?php

namespace App\Console\Commands;

use App\Models\PmRelease;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Support\PmsAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPmsReleaseReminders extends Command
{
    protected $signature = 'pms:release-reminders';

    protected $description = 'Notify authorized release owners and managers about upcoming deployment dates';

    public function handle(): int
    {
        if (! module_enabled('project_management')) {
            return self::SUCCESS;
        }
        $sent = 0;
        PmRelease::with('project')->whereNotIn('status', ['Released', 'Completed', 'Cancelled'])
            ->whereDate('planned_deploy_date', '>=', today())
            ->whereDate('planned_deploy_date', '<=', today()->addDays(3))
            ->chunkById(100, function ($releases) use (&$sent): void {
                foreach ($releases as $release) {
                    if (! $release->project || $release->project->archived_at) {
                        continue;
                    }
                    $memberIds = DB::table('pm_project_members')->where('project_id', $release->project_id)
                        ->whereIn('role', ['Project Administrator', 'Project Manager', 'Release Manager'])->pluck('user_id');
                    $recipients = User::whereIn('id', $memberIds->push($release->owner_id)->filter()->unique())->get();
                    foreach ($recipients as $recipient) {
                        if (! PmsAccess::allows($recipient, $release->project, 'view')) {
                            continue;
                        }
                        $inserted = DB::table('pm_release_reminders')->insertOrIgnore([
                            'release_id' => $release->id, 'user_id' => $recipient->id,
                            'reminder_date' => today()->toDateString(), 'created_at' => now(),
                        ]);
                        if ($inserted) {
                            AppNotificationService::notifyUser($recipient->id,
                                "{$release->release_key} deployment approaching",
                                "{$release->name} is planned for {$release->planned_deploy_date->format('d M Y')}.",
                                route('pms.releases.show', $release), 'project_management');
                            $sent++;
                        }
                    }
                }
            });
        $this->info("Sent {$sent} PMS release reminder(s).");

        return self::SUCCESS;
    }
}
