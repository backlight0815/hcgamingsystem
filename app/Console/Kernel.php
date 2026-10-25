<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Register your Discord command here
        \App\Console\Commands\DiscordCreateThread::class,
        \App\Console\Commands\SendAppointmentReminders::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('trading-ai:prune')->dailyAt('03:00')->timezone(config('app.timezone'))->withoutOverlapping();
        $schedule->command('appointments:send-reminders')->everyMinute()
            ->when(fn (): bool => \App\Support\TradingSubmodules::enabled('appointments'));
        $schedule->command('pms:release-reminders')->dailyAt('09:00')->timezone(config('app.timezone'))->withoutOverlapping();
        $schedule->command('pms:generate-cycles')->hourly()->timezone(config('app.timezone'))->withoutOverlapping();
        $schedule->command('pms:prune-session-history')->dailyAt('02:30')->timezone('Asia/Kuala_Lumpur')->withoutOverlapping();
        if (config('market_data.mode') === 'REAL' && config('market_data.auto_refresh')) {
            $schedule->command('market-insight:sync')->everyMinute()->withoutOverlapping(10)
                ->when(fn (): bool => \App\Models\FeatureToggle::isEnabled(\App\Models\FeatureToggle::MODULE_TRADING)
                    && \App\Support\TradingSubmodules::enabled('ai_market_insight'));
        }

        // Optional: schedule Discord thread creation (example)
        // $schedule->command('discord:create-thread 1363065104803041403')->dailyAt('10:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
