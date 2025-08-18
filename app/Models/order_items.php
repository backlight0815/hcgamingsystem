<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_items extends Model
{
    use HasFactory;

    protected $guarded =[];
    protected $table = 'order_items';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function dealerproduct(){
        return $this->belongsTo(DealerStock::class,'product_id');
    }

}
