<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingReason extends Model
{
    use HasFactory;

    protected $table = 'trading_reason';

    protected $fillable = [
        'name',
        'description',
    ];
}
