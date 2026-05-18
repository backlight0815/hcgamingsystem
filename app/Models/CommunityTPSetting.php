<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunityTPSetting extends Model
{
    use HasFactory;

    protected $table = 'community_tp_settings';

    protected $fillable = [
        'community_id',
        'tp_level',
        'enabled',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
