<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealerOrderItem extends Model
{
    protected $table = 'dealer_order_items';

    use HasFactory;

    public function dealerproduct()
    {
        return $this->belongsTo(DealerStock::class, 'dealer_product_id');
    }
}
