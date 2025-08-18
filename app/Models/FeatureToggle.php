<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureToggle extends Model
{
    protected $fillable = ['feature_name', 'enabled'];

    // Optional: add helper to get feature by name
    public static function isEnabled(string $featureName): bool
    {
        $feature = self::where('feature_name', $featureName)->first();
        if (!$feature) {
            // If feature not found, default to enabled
            return true;
        }
        return $feature->enabled;
    }
}
