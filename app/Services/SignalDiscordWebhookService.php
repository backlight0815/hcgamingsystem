<?php

namespace App\Services;

use App\Models\TradingSignal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SignalDiscordWebhookService
{
    public function send(TradingSignal $signal, ?string $webhookUrl = null): array
    {
        $webhookUrl ??= config('trading_signals.discord_webhook_url');
        if (! config('trading_signals.discord_enabled') || blank($webhookUrl)) {
            throw new RuntimeException('Discord signal delivery is not configured.');
        }

        try {
            $separator = str_contains($webhookUrl, '?') ? '&' : '?';
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('trading_signals.discord_timeout_seconds', 10)))
                ->post($webhookUrl.$separator.http_build_query(['wait' => 'true']), [
                    'content' => $this->format($signal),
                    'allowed_mentions' => ['parse' => []],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Discord connection failed.', previous: $exception);
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Discord rate limited the signal delivery.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Discord rejected the signal delivery with HTTP '.$response->status().'.');
        }

        return [
            'message_id' => $response->json('id'),
            'channel_id' => $response->json('channel_id'),
        ];
    }

    public function format(TradingSignal $signal): string
    {
        $lines = [
            '📈 **Trading Signal**',
            '',
            '**Symbol:** '.strtoupper($signal->trading_pair),
            '**Direction:** '.strtoupper($signal->immediate_action),
        ];

        if (filled($signal->entry_price)) {
            $lines[] = '**Entry:** '.$signal->entry_price;
        }
        if (filled($signal->stop_loss)) {
            $lines[] = '**Stop Loss:** '.$signal->stop_loss;
        }

        $targetCount = (int) $signal->target_count > 0 ? (int) $signal->target_count : 10;
        $targets = collect(range(1, $targetCount))
            ->map(fn (int $number): ?string => filled($signal->{"target_{$number}"})
                ? "TP{$number}: ".$signal->{"target_{$number}"}
                : null)
            ->filter()
            ->values();
        if ($targets->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '**Take Profit:**';
            array_push($lines, ...$targets->all());
        }

        foreach ([
            'Timeframe' => $signal->timeframe,
            'Risk' => $signal->risk_level,
            'Strategy' => $signal->strategy,
            'Source' => str_replace('_', ' ', (string) $signal->source),
        ] as $label => $value) {
            if (filled($value)) {
                $lines[] = "**{$label}:** {$value}";
            }
        }

        if (filled($signal->description)) {
            $lines[] = '';
            $lines[] = $signal->description;
        }

        $publishedAt = $signal->published_at ?? $signal->created_at;
        if ($publishedAt) {
            $lines[] = '';
            $lines[] = '**Published:** '.$publishedAt
                ->clone()
                ->timezone(config('trading_signals.display_timezone'))
                ->format('d M Y H:i T');
        }

        return Str::limit(implode("\n", $lines), 1950, "\n…");
    }
}
