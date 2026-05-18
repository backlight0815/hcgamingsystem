<?php

namespace App\Models;

//  use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Referral;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import the SoftDeletes trait
use App\Models\TradingSignal;

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
        'signal_provider_referral_code',
        'banned_until',
        'status',
        'invited_by',
        'role_id',
    'prop_firm_phase', // new field
    'funded_status', // add this
    'prop_firm_review_status',
    'prop_firm_review_phase',
    'prop_firm_trade_locked',
    'prop_firm_review_note',
    'prop_firm_review_requested_at',
    'prop_firm_review_approved_at',
    'total_score',

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
        'discord_connected_at' => 'datetime',
        'prop_firm_trade_locked' => 'boolean',
        'prop_firm_review_requested_at' => 'datetime',
        'prop_firm_review_approved_at' => 'datetime',
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

public function tradingSignals()
{
    return $this->hasMany(TradingSignal::class, 'user_id');
}

public function propFirmEvaluationQuestions(): HasMany
{
    return $this->hasMany(PropFirmEvaluationQuestion::class);
}

public function openPropFirmEvaluationQuestions(): HasMany
{
    return $this->hasMany(PropFirmEvaluationQuestion::class)
        ->whereIn('status', [
            PropFirmEvaluationQuestion::STATUS_OPEN,
            PropFirmEvaluationQuestion::STATUS_ANSWERED,
        ]);
}

public function traderOnboardingApplications(): HasMany
{
    return $this->hasMany(TraderOnboardingApplication::class);
}

public function latestTraderOnboardingApplication(): HasOne
{
    return $this->hasOne(TraderOnboardingApplication::class)->latestOfMany();
}

public function tradingPositionApplications(): HasMany
{
    return $this->hasMany(TradingPositionApplication::class);
}

public function latestTradingPositionApplication(): HasOne
{
    return $this->hasOne(TradingPositionApplication::class)->latestOfMany();
}

public function directTradingDownlines(): HasMany
{
    return $this->hasMany(User::class, 'invited_by');
}

public function isTradingMember(): bool
{
    return in_array((int) $this->role_id, TradingPositionApplication::tradingMemberRoles(), true);
}

public function isTradingLeader(): bool
{
    return in_array((int) $this->role_id, TradingPositionApplication::leaderRoles(), true);
}

public function isTradingRecruiter(): bool
{
    return in_array((int) $this->role_id, TradingPositionApplication::recruiterRoles(), true);
}

public function hasApprovedTraderOnboarding(): bool
{
    if ((int) $this->role_id !== 750) {
        return true;
    }

    if (! \Illuminate\Support\Facades\Schema::hasTable('trader_onboarding_applications')) {
        return false;
    }

    $latestApplication = $this->traderOnboardingApplications()
        ->latest('id')
        ->first();

    return $latestApplication && $latestApplication->isApproved();
}
}
