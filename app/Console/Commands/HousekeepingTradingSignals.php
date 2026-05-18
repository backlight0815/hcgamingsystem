<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TradingSignal;
use App\Models\SignalPerformance;
use Illuminate\Support\Facades\DB;

class HousekeepingTradingSignals extends Command
{
    protected $signature = 'signals:housekeeping';
    protected $description = 'Delete all trading signals (for testing)';

    public function handle()
    {
        $count = TradingSignal::count();

        // 1️⃣ Delete related performances first
        SignalPerformance::query()->delete();

        // 2️⃣ Delete trading signals
        TradingSignal::query()->delete();

        // 3️⃣ Reset auto-increment (optional)
        DB::statement('ALTER TABLE trading_signals AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE signal_performances AUTO_INCREMENT = 1');

        $this->info("Housekeeping complete. Deleted {$count} records from trading_signals and related performances.");
    }
}
