<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class TradingSignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requesterProviderId = (int) $this->user_id === (int) $request->user()?->id
            ? $request->attributes->get('firebase_uid')
            : null;
        $targets = [];
        $targetCount = (int) $this->target_count > 0 ? (int) $this->target_count : 10;
        for ($number = 1; $number <= $targetCount; $number++) {
            $value = $this->{"target_{$number}"};
            if (filled($value)) {
                $targets[] = (string) $value;
            }
        }

        return [
            'id' => (string) $this->id,
            'signal_uuid' => $this->signal_uuid,
            'signal_code' => $this->signal_code,
            'symbol' => $this->trading_pair,
            'trading_pair' => $this->trading_pair,
            'market' => $this->market,
            'signal_type' => strtoupper((string) $this->immediate_action),
            'immediate_action' => strtoupper((string) $this->immediate_action),
            'order_type' => $this->order_type ?: 'market_execution',
            'entry_price' => (string) $this->entry_price,
            'current_entry_price' => (string) ($this->current_entry_price_value ?? $this->entry_price),
            'entry_price_min' => $this->entry_price_min,
            'entry_price_max' => $this->entry_price_max,
            'stop_loss' => (string) $this->stop_loss,
            'current_stop_loss' => (string) ($this->current_stop_loss_value ?? $this->stop_loss),
            'break_even_price' => $this->break_even_price_value === null
                ? null
                : (string) $this->break_even_price_value,
            'targets' => $targets,
            ...collect($targets)->mapWithKeys(fn (string $value, int $index): array => [
                'target_'.($index + 1) => $value,
                'take_profit_'.($index + 1) => $value,
            ])->all(),
            'risk_level' => $this->risk_level,
            'timeframe' => $this->timeframe,
            'strategy' => $this->strategy,
            'signal_title' => $this->signal_title ?: $this->strategy ?: $this->trading_pair,
            'description' => $this->description,
            'disclaimer' => $this->description ?: $this->disclaimer,
            'notes' => $this->notes,
            'source' => $this->source,
            'signal_source' => $this->source,
            'source_user_id' => $this->source_user_id,
            'user_id' => $this->user_id,
            'provider_id' => $requesterProviderId ?: $this->user_id,
            'source_user' => $this->whenLoaded('sourceUser', fn (): ?array => $this->sourceUser ? [
                'id' => (string) $this->sourceUser->id,
                'name' => $this->sourceUser->name,
                'username' => $this->sourceUser->username,
            ] : null),
            'status' => (int) $this->status,
            'workflow_status' => $this->workflow_status,
            'lifecycle_status' => $this->mobile_lifecycle_status,
            'targets_hit' => array_values($this->targets_hit ?? []),
            'target_count' => count($targets),
            'current_target_index' => (int) $this->current_target_index,
            'percentage_remaining' => (float) ($this->percentage_remaining ?? 100),
            'is_done' => (int) ($this->is_done || $this->IsDone),
            'IsDone' => (int) ($this->is_done || $this->IsDone),
            'IsBE' => (int) $this->IsBE,
            'IsSetBE' => (int) $this->IsSetBE,
            'discord_delivery_status' => $this->discord_delivery_status,
            'discord_sent_at' => $this->discord_sent_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'archived' => (bool) $this->archived,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'status_before_archive' => $this->status_before_archive,
            'last_update_type' => $this->last_update_type,
            'last_updated_by' => $this->last_updated_by,
            'trigger_time' => $this->trigger_time?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'version' => (int) $this->version,
            'link' => $this->link,
            'chart_image_url' => $this->signal_image
                ? (Str::startsWith($this->signal_image, ['http://', 'https://'])
                    ? $this->signal_image
                    : asset($this->signal_image))
                : null,
        ];
    }
}
