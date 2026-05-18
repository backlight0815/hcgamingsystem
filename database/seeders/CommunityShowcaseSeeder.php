<?php

namespace Database\Seeders;

use App\Models\CommunityShowcasePage;
use Illuminate\Database\Seeder;

class CommunityShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        CommunityShowcasePage::updateOrCreate(
            ['slug' => CommunityShowcasePage::DEFAULT_SLUG],
            CommunityShowcasePage::defaultContent()
        );
    }
}
