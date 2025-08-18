<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class transactions extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'user_id', 'payment_proof'];
    protected $table = 'transactions';

    public function order()
    {
        return $this->belongsTo(orders::class, 'order_id');
    }
}
