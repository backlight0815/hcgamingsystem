<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingPair extends Model
{
    use HasFactory;

    // Table name (optional, only if different from 'trading_pairs')
    protected $table = 'trading_pairs';

    // Allow mass assignment on these fields
    protected $fillable = [
        'symbol',
        'description',
    ];
}
