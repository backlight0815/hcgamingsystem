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
        $schedule->command('signals:housekeeping')->everyThreeMinutes();
        $schedule->command('appointments:send-reminders')->everyMinute();

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
