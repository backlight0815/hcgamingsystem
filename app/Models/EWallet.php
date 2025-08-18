<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EWallet extends Model
{

    protected $table = 'wallets';

    protected $guarded =[];
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function walletRequests()
    {
        return $this->hasMany(EwalletRequest::class);
    }
    use HasFactory;
}
