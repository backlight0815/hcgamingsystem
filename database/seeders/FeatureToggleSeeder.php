<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FeatureToggle;

class FeatureToggleSeeder extends Seeder
{
    public function run()
    {
        foreach (FeatureToggle::defaultFeatureDefinitions() as $featureName => $definition) {
            FeatureToggle::updateOrCreate(
                ['feature_name' => $featureName],
                ['enabled' => (bool) ($definition['default'] ?? true)]
            );
        }
    }
}
