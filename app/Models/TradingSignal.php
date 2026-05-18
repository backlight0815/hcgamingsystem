<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingSignal extends Model
{
    use HasFactory;

    protected $table = 'trading_signals';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'signal_code',            // ✅ ADD THIS
        'trading_pair',
        'immediate_action',
        'entry_price',
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
        'community_target',
        'community_category',
        'signal_image',
        'link',
            'trigger_time',           // ✅ newly added column
'category',
        'status',
        'IsDone',
        'is_done',
            'IsBE', // <-- must match database column exactly
    'cancel_reason',
    'community_id', // ✅ REQUIRED

    'IsSetBE',  // ✅ add this
        'user_id',
                'trading_reasons', // store multiple reason IDs as JSON

    ];

    /**
     * Discord relationships
     */
    public function discordMessages()
    {
        return $this->hasMany(TradingSignalDiscord::class, 'trading_signal_id');
    }

    public function discordCommunity()
    {
        return $this->hasOne(TradingSignalDiscord::class, 'trading_signal_id');
    }

    /**
     * Community relationship
     */
    public function community()
    {
        return $this->belongsTo(Community::class);
    }
   public function discordLinks()
    {
        return $this->hasMany(
            TradingSignalDiscord::class,
            'trading_signal_id',
            'id'
        );
    }
    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor for progress
     */
  protected $casts = [
        'trading_reasons' => 'array', // ✅ THIS FIXES IT

    'is_done' => 'integer',
    'IsDone' => 'integer',
    'IsBE' => 'integer',
    'IsSetBE' => 'integer',
    'status' => 'integer',
];

public function getProgressAttribute()
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

    $statusMap = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        15 => 'BE',
    ];

    return $statusMap[$this->status] ?? 'Unknown';
}


    // Accessor for IsBE
public function getIsBEAttribute($value)
{
    return $value;
}

// Accessor for IsSetBE
public function getIsSetBEAttribute($value)
{
    return $value;
}
// Accessor for reasons
public function reasons()
{
    if (empty($this->trading_reasons)) {
        return collect(); // return empty collection if no reasons
    }

    return TradingReason::whereIn('id', $this->trading_reasons)->get();
}
}
