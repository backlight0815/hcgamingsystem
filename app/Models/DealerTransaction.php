<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealerTransaction extends Model
{

    protected $table = 'downline_transactions';

    use HasFactory;

    public function order()
    {
        return $this->belongsTo(DealerOrder::class, 'dealer_order_id');
    }
}
