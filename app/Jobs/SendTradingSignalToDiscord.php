<?php

namespace App\Jobs;

use App\Models\Community;
use App\Models\TradingSignal;
use App\Models\TradingSignalDiscord;
use App\Services\SignalDiscordWebhookService;
use App\Services\TradingSignalAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTradingSignalToDiscord implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $signalId,
        public readonly ?int $communityId = null
    ) {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return $this->signalId.':'.($this->communityId ?? 'default');
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(
        SignalDiscordWebhookService $discord,
        TradingSignalAuditService $audit
    ): void {
        $signal = TradingSignal::find($this->signalId);
        if (! $signal || $signal->workflow_status !== TradingSignal::WORKFLOW_PUBLISHED) {
            return;
        }

        $community = $this->communityId ? Community::find($this->communityId) : null;
        $webhookUrl = $community?->discord_webhook ?: config('trading_signals.discord_webhook_url');

        $signal->forceFill([
            'discord_delivery_status' => 'PENDING',
            'discord_attempts' => (int) $signal->discord_attempts + 1,
            'discord_last_error' => null,
        ])->saveQuietly();

        try {
            $result = $discord->send($signal, $webhookUrl);
            $signal->forceFill([
                'discord_delivery_status' => 'SENT',
                'discord_message_id' => $result['message_id'] ?: $signal->discord_message_id,
                'discord_channel_id' => $result['channel_id'] ?: $signal->discord_channel_id,
                'discord_sent_at' => now(),
                'discord_last_error' => null,
            ])->saveQuietly();

            if ($community && $result['message_id'] && $result['channel_id']) {
                TradingSignalDiscord::updateOrCreate(
                    [
                        'trading_signal_id' => $signal->id,
                        'community_id' => $community->id,
                    ],
                    [
                        'category' => $community->category,
                        'message_id' => $result['message_id'],
                        'channel_id' => $result['channel_id'],
                    ]
                );
            }

            $audit->record($signal, 'DISCORD_SEND_SUCCESS', null, [
                'community_id' => $community?->id,
                'attempt' => $this->attempts(),
            ]);
            Log::info('Trading signal delivered to Discord.', [
                'signal_id' => $signal->id,
                'community_id' => $community?->id,
                'attempt' => $this->attempts(),
            ]);
        } catch (Throwable $exception) {
            $signal->forceFill([
                'discord_delivery_status' => 'PENDING',
                'discord_last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->saveQuietly();
            Log::warning('Trading signal Discord attempt failed.', [
                'signal_id' => $signal->id,
                'community_id' => $community?->id,
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $signal = TradingSignal::find($this->signalId);
        if (! $signal) {
            return;
        }

        $signal->forceFill([
            'discord_delivery_status' => 'FAILED',
            'discord_last_error' => mb_substr($exception?->getMessage() ?? 'Discord delivery failed.', 0, 1000),
        ])->saveQuietly();

        app(TradingSignalAuditService::class)->record($signal, 'DISCORD_SEND_FAILED', null, [
            'community_id' => $this->communityId,
            'attempts' => $this->attempts(),
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
