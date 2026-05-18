<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingSignalDiscord extends Model
{
    use HasFactory;

    protected $table = 'trading_signal_discord';

    protected $fillable = [
        'trading_signal_id',
        'community',
            'community_id',   // ✅ new foreign key
'category',
        'message_id',  // matches DB column
        'channel_id',  // matches DB column
    ];

    /**
     * Each Discord message belongs to a trading signal
     */
    public function tradingSignal()
    {
        return $this->belongsTo(TradingSignal::class, 'trading_signal_id');
    }

 public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

public function signal()
{
    return $this->belongsTo(TradingSignal::class, 'trading_signal_id');
}

}
