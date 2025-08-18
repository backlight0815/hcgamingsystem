<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;
    protected $guarded=[];
    // protected $fillable = ['guest_id', 'product_id', 'user_id', 'quantity'];


    public function product(){
        return $this->belongsTo(Product::class,'product_id');
    }

    public function item()
    {
        return $this->morphTo(__FUNCTION__, 'type', 'product_id');
    }
    public function dealerStock()
    {
        return $this->belongsTo(DealerStock::class, 'product_id');
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
