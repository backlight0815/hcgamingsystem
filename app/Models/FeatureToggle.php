<?php

namespace App\Models;

use App\Support\FrontendExperience;
use App\Support\TradingSubmodules;
use Illuminate\Database\Eloquent\Model;

class FeatureToggle extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $feature): void {
            FrontendExperience::forgetCache();
            TradingSubmodules::forgetCache();
            if (!$feature->wasRecentlyCreated && $feature->wasChanged('enabled') && (array_key_exists($feature->feature_name, self::moduleDefinitions())
                || str_starts_with($feature->feature_name, 'tradinghub_'))) {
                \App\Support\AdminAudit::record(auth()->user(), 'module.'.($feature->enabled ? 'enabled' : 'disabled'),
                    'feature_toggle', $feature->id, ['module' => $feature->feature_name]);
            }
        });
        static::deleted(function (): void { FrontendExperience::forgetCache(); TradingSubmodules::forgetCache(); });
    }

    protected $fillable = ['feature_name', 'enabled', 'updated_by'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public const MODULE_TRADING = 'module_trading';
    public const MODULE_DEALERSHIP_ECOMMERCE = 'module_dealership_ecommerce';
    public const MODULE_PROJECT_MANAGEMENT = 'module_project_management';

    public static function moduleDefinitions(): array
    {
        return [
            self::MODULE_TRADING => [
                'label' => 'Trading',
                'description' => 'Controls Trading Hub public and member pages and mobile Trading APIs. The admin sidebar follows this toggle.',
                'default' => true,
            ],
            self::MODULE_DEALERSHIP_ECOMMERCE => [
                'label' => 'Dealership E-Commerce',
                'description' => 'Controls the public store and shopping actions. The existing admin commerce sidebar also follows this toggle.',
                'default' => true,
            ],
            self::MODULE_PROJECT_MANAGEMENT => [
                'label' => 'Project Management',
                'description' => 'Private engineering workspace for authorized staff. Does not change the public storefront.',
                'default' => false,
            ],
        ];
    }

    public static function defaultFeatureDefinitions(): array
    {
        $submodules = [];
        foreach (TradingSubmodules::definitions() as $key => $definition) {
            $submodules[TradingSubmodules::featureName($key)] = $definition;
        }
        return array_merge(self::moduleDefinitions(), $submodules, [
            'ewallet_topup' => [
                'label' => 'E-Wallet Top Up',
                'description' => 'Allows dealers and agents to submit wallet top-up requests.',
                'default' => true,
            ],
            'ewallet_withdraw' => [
                'label' => 'E-Wallet Withdraw',
                'description' => 'Allows wallet withdrawal flows when available.',
                'default' => false,
            ],
            'DiscordIntegration' => [
                'label' => 'Discord Integration',
                'description' => 'Allows trading content to be sent to Discord.',
                'default' => true,
            ],
            'DiscordIntegration_Everyone' => [
                'label' => 'Discord Everyone Mention',
                'description' => 'Allows Discord posts to mention everyone when the community setting also permits it.',
                'default' => false,
            ],
            'signal_payout' => [
                'label' => 'Signal Payout',
                'description' => 'Enables reward eligibility for signal performance reports.',
                'default' => true,
            ],
            'propfirm' => [
                'label' => 'Prop Firm Review',
                'description' => 'Enables prop firm review controls on trader journals.',
                'default' => true,
            ],
            'referral_dealer' => [
                'label' => 'Dealer Referral',
                'description' => 'Shows dealer referral links and recruitment controls.',
                'default' => true,
            ],
            'referral_customer' => [
                'label' => 'Customer Referral',
                'description' => 'Shows customer referral links and recruitment controls.',
                'default' => true,
            ],
            'referral_signal_provider' => [
                'label' => 'Signal Provider Referral',
                'description' => 'Shows signal provider referral links and recruitment controls.',
                'default' => true,
            ],
        ]);
    }

    public static function ensureDefaultFeatures(): void
    {
        foreach (self::defaultFeatureDefinitions() as $featureName => $definition) {
            self::firstOrCreate(
                ['feature_name' => $featureName],
                ['enabled' => (bool) ($definition['default'] ?? true)]
            );
        }
    }

    public static function isEnabled(string $featureName, bool $default = true): bool
    {
        $feature = self::where('feature_name', $featureName)->first();
        if (!$feature) {
            return $default;
        }
        return $feature->enabled;
    }
}
