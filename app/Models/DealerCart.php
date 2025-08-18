<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealerCart extends Model
{
    use HasFactory;

    protected $guarded = [];
    // protected $fillable = ['guest_id', 'dealer_stock_id', 'user_id', 'quantity'];

    public function dealerStock()
    {
        return $this->belongsTo(DealerStock::class, 'dealer_stock_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
