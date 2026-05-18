<?php

namespace App\Console\Commands;

use App\Models\TradingAppointment;
use App\Services\AppNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send 15-minute reminders for approved trading appointments.';

    public function handle(): int
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(15);
        $sent = 0;

        TradingAppointment::with(['requester', 'host'])
            ->where('status', TradingAppointment::STATUS_APPROVED)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_start_at', [$now, $windowEnd])
            ->orderBy('id')
            ->chunkById(50, function ($appointments) use (&$sent): void {
                foreach ($appointments as $appointment) {
                    $time = $appointment->scheduled_start_at->format('M d, Y H:i');
                    $message = $appointment->subject . ' starts at ' . $time . '.';
                    $recipientIds = collect([$appointment->requester_id, $appointment->host_user_id])
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->unique();

                    foreach ($recipientIds as $recipientId) {
                        AppNotificationService::notifyUser(
                            $recipientId,
                            'Appointment starts in 15 minutes',
                            $message,
                            route('trading.appointments.index'),
                            'appointment_reminder'
                        );
                    }

                    $appointment->update(['reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Appointment reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
