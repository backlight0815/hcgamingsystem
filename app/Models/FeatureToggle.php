<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureToggle extends Model
{
    protected $fillable = ['feature_name', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public const MODULE_TRADING = 'module_trading';
    public const MODULE_DEALERSHIP_ECOMMERCE = 'module_dealership_ecommerce';

    public static function moduleDefinitions(): array
    {
        return [
            self::MODULE_TRADING => [
                'label' => 'Trading',
                'description' => 'Shows trading journals, signals, market analysis, trader resources, certificates, and leaderboard features.',
                'default' => true,
            ],
            self::MODULE_DEALERSHIP_ECOMMERCE => [
                'label' => 'Dealership E-Commerce',
                'description' => 'Shows product catalogue, stock, orders, wallet, commission, recruitment, sales, and storefront management features.',
                'default' => true,
            ],
        ];
    }

    public static function defaultFeatureDefinitions(): array
    {
        return array_merge(self::moduleDefinitions(), [
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
