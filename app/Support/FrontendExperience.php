<?php

namespace App\Support;

use App\Models\FeatureToggle;
use App\Models\SitePreference;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FrontendExperience
{
    public const TRADING = 'trading';
    public const ECOMMERCE = 'ecommerce';
    public const COMBINED = 'combined';
    public const NEUTRAL = 'neutral';
    public const PREFERENCE_KEY = 'homepage_experience';
    private const CACHE_KEY = 'hc_frontend_experience_v1';

    public static function settings(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn (): array => self::loadFromDatabase());
        } catch (Throwable $exception) {
            try {
                return self::loadFromDatabase();
            } catch (Throwable $databaseException) {
                // If module state cannot be read, expose neither public experience.
                return [self::TRADING => false, self::ECOMMERCE => false, 'trading_visible' => false, 'ecommerce_visible' => false, 'preference' => self::ECOMMERCE];
            }
        }
    }

    private static function loadFromDatabase(): array
    {
        $toggles = FeatureToggle::query()
            ->whereIn('feature_name', [FeatureToggle::MODULE_TRADING, FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE])
            ->pluck('enabled', 'feature_name');
        try {
            $preference = SitePreference::query()->where('key', self::PREFERENCE_KEY)->value('value');
        } catch (Throwable $exception) {
            // Older deployments can resolve the existing module toggles before migration.
            $preference = null;
        }

        $visibility = \Illuminate\Support\Facades\Schema::hasTable('module_access_settings')
            ? \Illuminate\Support\Facades\DB::table('module_access_settings')->whereIn('module_key', [ModuleAccess::TRADINGHUB, ModuleAccess::DEALERSHIP])->pluck('public_visible', 'module_key')
            : collect();

        return [
            self::TRADING => (bool) ($toggles[FeatureToggle::MODULE_TRADING] ?? true),
            self::ECOMMERCE => (bool) ($toggles[FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE] ?? true),
            'trading_visible' => (bool) ($visibility[ModuleAccess::TRADINGHUB] ?? true),
            'ecommerce_visible' => (bool) ($visibility[ModuleAccess::DEALERSHIP] ?? true),
            'preference' => in_array($preference, [self::TRADING, self::ECOMMERCE, self::COMBINED], true)
                ? $preference : self::ECOMMERCE,
        ];
    }

    public static function enabled(string $module): bool
    {
        return (bool) (self::settings()[$module] ?? false);
    }

    public static function publicVisible(string $module): bool
    {
        $settings = self::settings();
        return (bool) ($settings[$module] ?? false) && (bool) ($settings[$module.'_visible'] ?? false);
    }

    public static function homepage(): string
    {
        $settings = self::settings();
        $trading = $settings[self::TRADING] && $settings['trading_visible'];
        $ecommerce = $settings[self::ECOMMERCE] && $settings['ecommerce_visible'];
        if ($trading && ! $ecommerce) {
            return self::TRADING;
        }
        if ($ecommerce && ! $trading) {
            return self::ECOMMERCE;
        }
        if (! $trading && ! $ecommerce) {
            return self::NEUTRAL;
        }

        return $settings['preference'];
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $exception) {
            // Database reads remain available if the cache store is offline.
        }
    }
}
