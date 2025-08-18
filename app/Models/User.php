<?php

namespace App\Models;

//  use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Referral;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import the SoftDeletes trait

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable,SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'referral_code',
        'customer_referral_code',

        'banned_until',
        'status',
        'invited_by',
        'role_id',
    'prop_firm_phase', // new field

        'updated_at'
    ];
    protected $dates =[
        'banned_until'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];




public function upline(){
    return $this->belongsTo(User::class,'invited_by');

}
public function ewalletTransactions()
{
    return $this->hasMany(EWalletTransaction::class);
}
public function uplineData(){
    return $this->belongsTo(Referral::class,'user_id');
}

public function roles()
{
    return $this->belongsToMany(Role::class);
}

public function hasRole($roleId)
{
    return $this->roles()->where('roles.id', $roleId)->exists();
}

public function isAdmin()
{
    return $this->role_id === 1; // Assuming role_id 1 represents the admin role

    // return $this->roles->contains('id', 1);
}

public function isDealer()
{
    return $this->roles->contains('id', 350);
}

public function orders()
{
    return $this->hasMany(orders::class);
}

public function cartItems()
{
    return $this->hasMany(Cart::class);
}

public function DealerCartItems()
{
    return $this->hasMany(DealerCart::class);
}

public function address()
{
    return $this->hasOne(Address::class);
}

public function invitationLinks()
{
    return $this->hasMany(ReferralLinks::class, 'user_id');
}

public function commissions()
{
    return $this->hasMany(Commission::class, 'upline_user_id');
}

public function dealerProductCategories(): HasMany
{
    return $this->hasMany(DealerProductCategory::class);
}
}
