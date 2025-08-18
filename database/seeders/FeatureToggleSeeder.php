<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FeatureToggle;

class FeatureToggleSeeder extends Seeder
{
    public function run()
    {
        $features = [
            ['feature_name' => 'ewallet_topup', 'enabled' => true],
            ['feature_name' => 'ewallet_withdraw', 'enabled' => false],
            // add other ewallet related toggles here
        ];

        foreach ($features as $feature) {
            FeatureToggle::updateOrCreate(
                ['feature_name' => $feature['feature_name']],
                ['enabled' => $feature['enabled']]
            );
        }
    }
}
