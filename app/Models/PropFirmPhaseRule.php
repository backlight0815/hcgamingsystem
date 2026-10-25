<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropFirmPhaseRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'prop_firm_model_id',
        'phase_key',
        'phase_number',
        'display_name',
        'profit_target_percent',
        'max_daily_loss_percent',
        'max_overall_loss_percent',
        'minimum_trading_days',
        'max_trading_days',
        'daily_reset_time',
        'timezone',
        'leverage',
        'news_trading_rule',
        'overnight_holding_rule',
        'weekend_holding_rule',
        'inactivity_days',
        'risk_per_trade_percent',
        'reward_split',
        'reward_cycle',
        'consistency_rule',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'phase_number' => 'integer',
        'profit_target_percent' => 'decimal:2',
        'max_daily_loss_percent' => 'decimal:2',
        'max_overall_loss_percent' => 'decimal:2',
        'minimum_trading_days' => 'integer',
        'max_trading_days' => 'integer',
        'inactivity_days' => 'integer',
        'risk_per_trade_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function propFirmModel(): BelongsTo
    {
        return $this->belongsTo(PropFirmModel::class);
    }
}
