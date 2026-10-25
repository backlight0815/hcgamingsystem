<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmsProfile extends Model
{
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'membership'; }
    protected $table = 'pms_profiles';

    protected $guarded = ['id'];

    protected $casts = [
        'status_code' => 'integer',
        'approved_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'can_create_all_tickets' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
