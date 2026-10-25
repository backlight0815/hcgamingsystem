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
            'project_management', 'pms', 'module_project_management' => FeatureToggle::MODULE_PROJECT_MANAGEMENT,
            default => $module,
        };
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $module): bool
    {
        $featureName = module_feature_name($module);
        return match ($featureName) {
            FeatureToggle::MODULE_TRADING => \App\Support\FrontendExperience::enabled(\App\Support\FrontendExperience::TRADING),
            FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE => \App\Support\FrontendExperience::enabled(\App\Support\FrontendExperience::ECOMMERCE),
            FeatureToggle::MODULE_PROJECT_MANAGEMENT => FeatureToggle::isEnabled(FeatureToggle::MODULE_PROJECT_MANAGEMENT, false),
            default => feature_toggle_value($featureName, true),
        };
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
