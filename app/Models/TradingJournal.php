<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingJournal extends Model
{
    use HasFactory;

    protected $table = 'trading_journals';

    protected $fillable = [
        'type',
        'user_id',
        'time_input_timezone',
        'time_input_offset_minutes',
        'open_date',
        'close_date',
        'pair',
        'direction',
        'entry_price',
        'exit_price',
        'lot_size',
        'pips',
        'profit_loss',
        'result',
        'notes',
    ];

    protected $casts = [
        'open_date' => 'datetime',
        'close_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
