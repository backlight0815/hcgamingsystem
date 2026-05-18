<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyTradingSignal extends Model
{
    use HasFactory;
    protected $table = 'trading_signals';
protected $dates = ['created_at', 'updated_at']; // Helps with Carbon if needed

    protected $fillable = [
        'trading_pair',
        'immediate_action',
                'entry_price',       // ✅ NEW

        'stop_loss',
        'target_1',
        'target_2',
        'target_3',
        'target_4',
        'target_5',
        'target_6',
        'target_7',
        'target_8',
        'target_9',
        'target_10',
        'disclaimer',
        'risk_level',
    ];

    public function performance()
{
    return $this->hasOne(SignalPerformance::class, 'signal_id');
}

}
