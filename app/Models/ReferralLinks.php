<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralLinks extends Model
{
    use HasFactory;
    protected $table = 'referral_links'; // Set the table name if different from the model's name
    protected $guarded=[];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
