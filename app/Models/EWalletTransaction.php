<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EWalletTransaction extends Model
{
    protected $table = 'ewallet_transactions';

    protected $guarded =[];
    use HasFactory;
}
