<?php

namespace App\Jobs;

use App\Models\TradingSignal;
use App\Services\FirebasePushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTradingSignalPushNotification implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $signalId)
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return (string) $this->signalId;
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(FirebasePushNotificationService $push): void
    {
        $signal = TradingSignal::find($this->signalId);
        if (! $signal || $signal->workflow_status !== TradingSignal::WORKFLOW_PUBLISHED) {
            return;
        }

        $push->sendPublishedSignal($signal);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Trading signal push notification failed.', [
            'signal_id' => $this->signalId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
