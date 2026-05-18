<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignalPerformanceBackup extends Model
{
    // Specify the table name
    protected $table = 'signal_performances_backup';

    // Mass assignable attributes
    protected $fillable = [
        'signal_id',
        'tp_hit',
        'is_sl',
                    'community_id', // ✅ REQUIRED

        'is_cancelled',
        'profit_pips',
        'profit_usd',
    ];

    /**
     * Relationship to the TradingSignalBackup model
     */
      public function signalBackup()
    {
        return $this->belongsTo(TradingSignalBackup::class, 'signal_id');
    }

        public function backupSignal()
    {
        return $this->belongsTo(TradingSignalBackup::class, 'signal_id');
    }

    public function signal()
{
    return $this->belongsTo(TradingSignal::class, 'signal_id', 'id');
}
 /**
     * Relationship to the Community model
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }
}
