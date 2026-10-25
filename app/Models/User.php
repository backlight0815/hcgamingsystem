<?php

namespace App\Models;

//  use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Import the SoftDeletes trait

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable,SoftDeletes;

    public const ROLE_SUPER_ADMIN = 1;
    public const ROLE_COMPANY_ADMIN = 2;

    protected static function booted(): void
    {
        static::updated(function (self $user): void {
            if ($user->wasChanged('role_id')) {
                \App\Support\AdminAudit::record(auth()->user(), 'user.role_changed', 'user', $user->id, [
                    'from' => $user->getOriginal('role_id'), 'to' => $user->role_id,
                ]);
            }
        });
    }

    public function isGlobalSuperAdmin(): bool
    {
        return (int) $this->role_id === self::ROLE_SUPER_ADMIN && (string) $this->status === '1';
    }

    public function isBackofficeAdministrator(): bool
    {
        return (string) $this->status === '1'
            && in_array((int) $this->role_id, [self::ROLE_SUPER_ADMIN, self::ROLE_COMPANY_ADMIN], true);
    }

    public function projectManagementIdentity(): HasOne
    {
        return $this->hasOne(ProjectManagementUser::class);
    }

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
        'prop_firm_model_id',
        'prop_firm_phase_rule_id',
        'phase2_start_balance',
        'funded_status', // add this
        'prop_firm_review_status',
        'prop_firm_review_phase',
        'prop_firm_required_profitable_days',
        'prop_firm_trade_locked',
        'prop_firm_review_note',
        'prop_firm_review_requested_at',
        'prop_firm_review_approved_at',
        'final_exam_passed',
        'final_exam_passed_at',
        'prop_firm_unlocked',
        'total_score',

        'updated_at',
    ];

    protected $dates = [
        'banned_until',
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
        'phase2_start_balance' => 'decimal:2',
        'prop_firm_model_id' => 'integer',
        'prop_firm_phase_rule_id' => 'integer',
        'prop_firm_required_profitable_days' => 'integer',
        'prop_firm_trade_locked' => 'boolean',
        'prop_firm_review_requested_at' => 'datetime',
        'prop_firm_review_approved_at' => 'datetime',
        'final_exam_passed' => 'boolean',
        'final_exam_passed_at' => 'datetime',
        'prop_firm_unlocked' => 'boolean',
    ];

public function upline()
{
    return $this->belongsTo(User::class, 'invited_by');

}

public function ewalletTransactions()
{
    return $this->hasMany(EWalletTransaction::class);
}

public function uplineData()
{
    return $this->belongsTo(Referral::class, 'user_id');
}

public function roles()
{
    return $this->belongsToMany(Role::class);
}

public function role()
{
    return $this->belongsTo(Role::class, 'role_id');
}

public function hasRole($roleId)
{
    return $this->roles()->where('roles.id', $roleId)->exists();
}

public function isAdmin()
{
    return $this->isGlobalSuperAdmin();
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

public function submittedTradingSignals()
{
    return $this->hasMany(TradingSignal::class, 'source_user_id');
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

public function propFirmModel()
{
    return $this->belongsTo(PropFirmModel::class);
}

public function propFirmPhaseRule()
{
    return $this->belongsTo(PropFirmPhaseRule::class);
}

public function finalExamAttempts(): HasMany
{
    return $this->hasMany(FinalExamAttempt::class);
}

public function userPropFirmPreference(): HasOne
{
    return $this->hasOne(UserPropFirmPreference::class);
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

public function tradingAccounts(): HasMany
{
    return $this->hasMany(TradingAccount::class);
}

public function latestTradingPositionApplication(): HasOne
{
    return $this->hasOne(TradingPositionApplication::class)->latestOfMany();
}

public function pmsProfile(): HasOne
{
    return $this->hasOne(PmsProfile::class);
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
