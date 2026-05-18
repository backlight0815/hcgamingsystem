<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TradingReason;

class TradingReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['name' => 'Breakout', 'description' => 'Price breaks a key support or resistance level.'],
            ['name' => 'Reversal', 'description' => 'Market shows signs of trend reversal.'],
            ['name' => 'Trend Continuation', 'description' => 'Trend is likely to continue after pullback.'],
            ['name' => 'Support Bounce', 'description' => 'Price bounces from support level.'],
            ['name' => 'Resistance Rejection', 'description' => 'Price rejected at resistance level.'],
            ['name' => 'Technical Indicator', 'description' => 'Signal generated based on technical indicator.'],
        ];

        foreach ($reasons as $reason) {
            TradingReason::create($reason);
        }
    }
}
