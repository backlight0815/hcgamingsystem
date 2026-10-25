<?php

namespace App\Models;

use App\Notifications\PmsPasswordResetNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ProjectManagementUser extends Authenticatable
{
    use Notifiable;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'membership'; }

    protected $table = 'project_management_users';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'status_code' => 'integer',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'legacy_shared_auth' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PmsPasswordResetNotification($token));
    }
}
