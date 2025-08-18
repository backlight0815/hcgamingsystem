<?php

use App\Models\FeatureToggle;

if (!function_exists('feature_enabled')) {
    function feature_enabled(string $featureName): bool
    {
        return FeatureToggle::isEnabled($featureName);
    }
}
