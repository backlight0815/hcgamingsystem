<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingSignalAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'trading_signal_id',
        'user_id',
        'action',
        'source',
        'request_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(TradingSignal::class, 'trading_signal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
