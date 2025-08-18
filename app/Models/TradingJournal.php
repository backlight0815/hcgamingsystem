<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingJournal extends Model
{
    use HasFactory;


protected $table = 'trading_journals';

    protected $fillable = [
              'type',          // ✅ 'trade' or 'deposit'
        'user_id',       // ✅ track owner
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

    public function user()
{
    return $this->belongsTo(User::class);
}

}
