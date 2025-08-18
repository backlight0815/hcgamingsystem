<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'upline_user_id',
        'downline_user_id',
        'order_id',
        'commission_amount',
    ];

    // Define relationships
    public function uplineUser()
    {
        return $this->belongsTo(Referral::class, 'upline_user_id', 'user_id');
    }

    public function downlineUserbane()
    {
        return $this->belongsTo(User::class, 'downline_user_id');
    }

    public function downlineUser()
    {
        return $this->belongsTo(Referral::class, 'downline_user_id', 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(orders::class, 'order_id');
    }

    public function user()
{
    return $this->belongsTo(User::class, 'upline_user_id');
}

}
