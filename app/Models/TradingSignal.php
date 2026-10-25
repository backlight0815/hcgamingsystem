<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TradingSignal extends Model
{
    use HasFactory;

    public const WORKFLOW_DRAFT = 'DRAFT';

    public const WORKFLOW_PENDING = 'PENDING';

    public const WORKFLOW_PUBLISHED = 'PUBLISHED';

    public const WORKFLOW_CANCELLED = 'CANCELLED';

    public const WORKFLOW_EXPIRED = 'EXPIRED';

    public const WORKFLOW_CLOSED = 'CLOSED';

    public const WORKFLOW_REJECTED = 'REJECTED';

    protected $table = 'trading_signals';

    protected $fillable = [
        'signal_uuid', 'signal_code', 'signal_title', 'trading_pair', 'market',
        'immediate_action', 'entry_price', 'entry_price_value', 'entry_price_min',
        'entry_price_max', 'stop_loss', 'stop_loss_value', 'target_1',
        'target_1_value', 'target_2', 'target_2_value', 'target_3',
        'target_3_value', 'target_4', 'target_4_value', 'target_5',
        'target_5_value', 'target_6', 'target_6_value', 'target_7',
        'target_7_value', 'target_8', 'target_8_value', 'target_9',
        'target_9_value', 'target_10', 'target_10_value', 'disclaimer',
        'risk_level', 'timeframe', 'strategy', 'description', 'notes', 'source',
        'source_user_id', 'client_request_id', 'workflow_status', 'published_at',
        'expires_at', 'cancelled_at', 'closed_at', 'discord_delivery_status',
        'discord_sent_at', 'discord_last_error', 'discord_attempts', 'version',
        'community_target', 'community_category', 'signal_image', 'link',
        'trigger_time', 'category', 'status', 'IsDone', 'is_done', 'IsBE',
        'cancel_reason', 'community_id', 'IsSetBE', 'user_id', 'trading_reasons',
        'pips_result', 'discord_message_id', 'discord_channel_id',
        'lifecycle_state', 'order_type', 'current_entry_price_value',
        'current_stop_loss_value', 'break_even_price_value', 'targets_hit',
        'target_count', 'current_target_index', 'percentage_remaining', 'activated_at',
        'completed_at', 'expired_at', 'archived', 'archived_at',
        'status_before_archive', 'last_update_type', 'last_updated_by',
    ];

    protected $casts = [
        'trading_reasons' => 'array',
        'is_done' => 'integer',
        'IsDone' => 'integer',
        'IsBE' => 'integer',
        'IsSetBE' => 'integer',
        'status' => 'integer',
        'entry_price_value' => 'decimal:8',
        'entry_price_min' => 'decimal:8',
        'entry_price_max' => 'decimal:8',
        'stop_loss_value' => 'decimal:8',
        'current_entry_price_value' => 'decimal:8',
        'current_stop_loss_value' => 'decimal:8',
        'break_even_price_value' => 'decimal:8',
        'target_1_value' => 'decimal:8',
        'target_2_value' => 'decimal:8',
        'target_3_value' => 'decimal:8',
        'target_4_value' => 'decimal:8',
        'target_5_value' => 'decimal:8',
        'target_6_value' => 'decimal:8',
        'target_7_value' => 'decimal:8',
        'target_8_value' => 'decimal:8',
        'target_9_value' => 'decimal:8',
        'target_10_value' => 'decimal:8',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'closed_at' => 'datetime',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'expired_at' => 'datetime',
        'archived_at' => 'datetime',
        'discord_sent_at' => 'datetime',
        'trigger_time' => 'datetime',
        'discord_attempts' => 'integer',
        'version' => 'integer',
        'targets_hit' => 'array',
        'target_count' => 'integer',
        'current_target_index' => 'integer',
        'percentage_remaining' => 'decimal:2',
        'archived' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (TradingSignal $signal): void {
            $legacyFields = [
                'entry_price', 'stop_loss', 'status', 'IsDone', 'is_done',
                'IsBE', 'IsSetBE', 'workflow_status', 'cancel_reason',
                'target_1', 'target_2', 'target_3', 'target_4', 'target_5',
                'target_6', 'target_7', 'target_8', 'target_9', 'target_10',
            ];
            if ($signal->isDirty('lifecycle_state')
                || ! collect($legacyFields)->contains(fn (string $field): bool => $signal->isDirty($field))) {
                return;
            }

            $signal->syncLifecycleProjectionFromLegacy();
            if (! $signal->isDirty('version')) {
                $signal->version = (int) $signal->version + 1;
            }
            if (Auth::id() !== null) {
                $signal->last_updated_by = (string) Auth::id();
            }
        });
    }

    public function discordMessages()
    {
        return $this->hasMany(TradingSignalDiscord::class, 'trading_signal_id');
    }

    public function discordCommunity()
    {
        return $this->hasOne(TradingSignalDiscord::class, 'trading_signal_id');
    }

    public function discordLinks()
    {
        return $this->hasMany(TradingSignalDiscord::class, 'trading_signal_id', 'id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function audits()
    {
        return $this->hasMany(TradingSignalAudit::class, 'trading_signal_id');
    }

    public function scopePublished($query)
    {
        return $query->whereIn('workflow_status', [
            self::WORKFLOW_PUBLISHED,
            self::WORKFLOW_CLOSED,
            self::WORKFLOW_CANCELLED,
            self::WORKFLOW_EXPIRED,
        ]);
    }

    public function getProgressAttribute(): string
    {
        if ($this->IsDone || $this->is_done) {
            return 'Done';
        }
        if ($this->IsBE) {
            return 'BE Hitted';
        }
        if ($this->IsSetBE) {
            return 'Set BE';
        }

        return [
            0 => 'Pending', 1 => 'Active', 2 => 'TP1', 3 => 'TP2', 4 => 'TP3',
            5 => 'TP4', 6 => 'TP5', 7 => 'TP6', 8 => 'TP7', 9 => 'TP8',
            10 => 'TP9', 11 => 'TP10', 12 => 'Cancelled', 13 => 'SL',
            14 => 'Done', 15 => 'BE',
        ][$this->status] ?? 'Unknown';
    }

    public function getMobileLifecycleStatusAttribute(): string
    {
        if ($this->archived || $this->lifecycle_state === 'archived') {
            return 'archived';
        }
        if ($this->workflow_status === self::WORKFLOW_CANCELLED || (int) $this->status === 12) {
            return 'cancelled';
        }
        if ($this->workflow_status === self::WORKFLOW_EXPIRED) {
            return 'expired';
        }
        if ((int) $this->status === 13) {
            return 'stop_loss_hit';
        }
        if ($this->IsBE || (int) $this->status === 15) {
            return 'break_even_hit';
        }
        if ($this->workflow_status === self::WORKFLOW_CLOSED || $this->IsDone || $this->is_done || (int) $this->status === 14) {
            return 'completed';
        }
        if ($this->IsSetBE) {
            return 'break_even';
        }
        if ((int) $this->status >= 2 && (int) $this->status <= 11) {
            return 'partially_closed';
        }
        if ((int) $this->status === 1 && in_array($this->lifecycle_state, [null, 'draft', 'scheduled', 'published', 'pending_entry'], true)) {
            return 'active';
        }
        if (filled($this->lifecycle_state)) {
            return (string) $this->lifecycle_state;
        }
        if ($this->workflow_status === self::WORKFLOW_PUBLISHED) {
            return 'pending_entry';
        }

        return strtolower((string) ($this->workflow_status ?: self::WORKFLOW_DRAFT));
    }

    private function syncLifecycleProjectionFromLegacy(): void
    {
        $status = (int) $this->status;
        if ($this->workflow_status === self::WORKFLOW_EXPIRED) {
            $this->lifecycle_state = 'expired';
            $this->expired_at ??= now();
            $this->last_update_type = 'expired';
        } elseif ($this->workflow_status === self::WORKFLOW_CANCELLED || $status === 12) {
            $this->lifecycle_state = 'cancelled';
            $this->workflow_status = self::WORKFLOW_CANCELLED;
            $this->cancelled_at ??= now();
            $this->last_update_type = 'cancelled';
        } elseif ($status === 13) {
            $this->lifecycle_state = 'stop_loss_hit';
            $this->workflow_status = self::WORKFLOW_CLOSED;
            $this->closed_at ??= now();
            $this->completed_at ??= now();
            $this->last_update_type = 'stop_loss_hit';
        } elseif ($status === 15 || $this->IsBE) {
            $this->lifecycle_state = 'break_even_hit';
            $this->workflow_status = self::WORKFLOW_CLOSED;
            $this->closed_at ??= now();
            $this->completed_at ??= now();
            $this->last_update_type = 'break_even_hit';
        } elseif ($this->IsDone || $this->is_done || $status === 14) {
            $this->lifecycle_state = 'completed';
            $this->workflow_status = self::WORKFLOW_CLOSED;
            $this->closed_at ??= now();
            $this->completed_at ??= now();
            $this->percentage_remaining = 0;
            $this->last_update_type = 'completed';
        } elseif ($status >= 2 && $status <= 11) {
            $targetNumber = $status - 1;
            $this->lifecycle_state = 'partially_closed';
            $this->workflow_status = self::WORKFLOW_PUBLISHED;
            $this->targets_hit = range(1, $targetNumber);
            $this->current_target_index = $targetNumber;
            $this->last_update_type = 'target_hit';
        } elseif ($status === 1) {
            $this->lifecycle_state = $this->IsSetBE ? 'break_even' : 'active';
            $this->workflow_status = self::WORKFLOW_PUBLISHED;
            $this->activated_at ??= now();
            $this->last_update_type = $this->IsSetBE ? 'move_sl_to_be' : 'entry_triggered';
        } elseif ($this->workflow_status === self::WORKFLOW_PUBLISHED) {
            $this->lifecycle_state = 'pending_entry';
            $this->last_update_type = 'details_updated';
        } else {
            $this->lifecycle_state = 'draft';
            $this->last_update_type = 'details_updated';
        }

        if ($this->isDirty('entry_price')) {
            $this->entry_price_value = $this->extractDecimal($this->entry_price);
            $this->current_entry_price_value = $this->entry_price_value;
        }
        if ($this->isDirty('stop_loss')) {
            $this->stop_loss_value = $this->extractDecimal($this->stop_loss);
            $this->current_stop_loss_value = $this->stop_loss_value;
        }
        for ($target = 1; $target <= 10; $target++) {
            if ($this->isDirty("target_{$target}")) {
                $this->{"target_{$target}_value"} = $this->extractDecimal($this->{"target_{$target}"});
            }
        }
        $this->target_count = collect(range(1, 10))
            ->filter(fn (int $target): bool => filled($this->{"target_{$target}"}))
            ->count();
    }

    private function extractDecimal(mixed $value): ?string
    {
        $normalized = str_replace(',', '', (string) $value);

        return preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches) ? $matches[0] : null;
    }

    public function reasons()
    {
        if (empty($this->trading_reasons)) {
            return collect();
        }

        return TradingReason::whereIn('id', $this->trading_reasons)->get();
    }
}
