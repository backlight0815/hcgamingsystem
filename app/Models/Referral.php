<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
class Referral extends Model
{
    use HasFactory;
    protected $table = 'referral';

    protected $fillable =[
        'user_id',
        'upline_user_id',

        'referral_code'
    ];

    public function agent(){
        return $this->belongsTo(User::class,'user_id');
    }
    public function upline()
    {
        return $this->belongsTo(User::class, 'upline_user_id');
    }

}
