<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class orders extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'user_id', 'total_amount','payment_proof','status'];
    protected $table = 'orders';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItems()
    {
        return $this->hasMany(order_items::class, 'order_id');
    }

    public function transactions()
    {
        return $this->hasMany(transactions::class, 'order_id');
    }
}
