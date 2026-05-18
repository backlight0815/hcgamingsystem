<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignalPerformance extends Model
{
    protected $fillable = [
        'signal_id',
        'tp_hit',
        'is_sl',
            'community_id', // ✅ REQUIRED

        'is_cancelled',
        'profit_pips',
        'profit_usd',
   
    ];

 public function signal()
    {
        return $this->belongsTo(TradingSignal::class, 'signal_id');
    }

    
    // 🔑 Backup relation
    public function backupSignal()
    {
        return $this->belongsTo(TradingSignalBackup::class, 'signal_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }


    public function getProviderNameAttribute()
{
    // If signal exists in main table, return that user
    if ($this->signal) {
        return $this->signal->user->username ?? 'N/A';
    }

    // Else if signal exists in backup table
    if ($this->backupSignal) {
        return $this->backupSignal->user->username ?? 'N/A';
    }

    return 'N/A';
}
}
