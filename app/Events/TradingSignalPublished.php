<?php

namespace App\Events;

use App\Models\TradingSignal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradingSignalPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly TradingSignal $signal)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('trading-signals')];
    }

    public function broadcastAs(): string
    {
        return 'trading.signal.published';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'trading.signal.published',
            'signal_id' => $this->signal->id,
            'signal_uuid' => $this->signal->signal_uuid,
            'published_at' => $this->signal->published_at?->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('trading_signals.realtime_enabled');
    }
}
