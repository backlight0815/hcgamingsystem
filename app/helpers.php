<?php

use App\Models\FeatureToggle;

if (!function_exists('feature_toggle_value')) {
    function feature_toggle_value(string $featureName, bool $default = false): bool
    {
        try {
            static $featureCache = null;

            if ($featureCache === null) {
                $featureCache = FeatureToggle::query()
                    ->pluck('enabled', 'feature_name')
                    ->map(fn ($enabled): bool => (bool) $enabled)
                    ->all();
            }

            return array_key_exists($featureName, $featureCache)
                ? (bool) $featureCache[$featureName]
                : $default;
        } catch (Throwable $exception) {
            return $default;
        }
    }
}

if (!function_exists('feature_enabled')) {
    function feature_enabled(string $featureName): bool
    {
        return feature_toggle_value($featureName, false);
    }
}

if (!function_exists('module_feature_name')) {
    function module_feature_name(string $module): string
    {
        return match ($module) {
            'trading', 'module_trading' => FeatureToggle::MODULE_TRADING,
            'ecommerce', 'dealership_ecommerce', 'module_dealership_ecommerce' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE,
            default => $module,
        };
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $module): bool
    {
        return feature_toggle_value(module_feature_name($module), true);
    }
}

if (!function_exists('module_statuses')) {
    function module_statuses(): array
    {
        return collect(FeatureToggle::moduleDefinitions())
            ->map(fn (array $definition, string $featureName): array => [
                'feature_name' => $featureName,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => feature_toggle_value($featureName, (bool) ($definition['default'] ?? true)),
            ])
            ->values()
            ->all();
    }
}
