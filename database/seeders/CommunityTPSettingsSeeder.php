<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Community;
use App\Models\CommunityTPSetting;

class CommunityTPSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $communities = Community::all();

        if ($communities->isEmpty()) {
            $this->command->info('No communities found. Seeder skipped.');
            return;
        }

        foreach ($communities as $community) {
            // Loop TP1 to TP10
            for ($tp = 1; $tp <= 10; $tp++) {

                // Prevent duplicate records
                $exists = CommunityTPSetting::where('community_id', $community->id)
                    ->where('tp_level', $tp)
                    ->exists();

                if (!$exists) {
                    CommunityTPSetting::create([
                        'community_id' => $community->id,
                        'tp_level'     => $tp,
                        'enabled'      => 1, // default = ON
                    ]);
                }
            }
        }

        $this->command->info('Community TP Settings seeded successfully.');
    }
}
