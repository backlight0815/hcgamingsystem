<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealerOrder extends Model
{
        protected $table = 'dealers_order';

    use HasFactory;


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function DealerOrderItems()
    {
        return $this->hasMany(DealerOrderItem::class, 'order_id');
    }

    public function transactions()
    {
        return $this->hasMany(transactions::class, 'dealer_order_id');
    }
}
