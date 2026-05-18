<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TradingSignalDiscordCategorySeeder extends Seeder
{
    public function run()
    {
        // Example logic:
        // If community_id exists → executive
        // Else → public
        // (Adjust rule if you have a better condition)

        DB::table('trading_signal_discord')
            ->whereNotNull('community_id')
            ->update([
                'category' => 'executive',
            ]);

        DB::table('trading_signal_discord')
            ->whereNull('community_id')
            ->update([
                'category' => 'public',
            ]);
    }
}
