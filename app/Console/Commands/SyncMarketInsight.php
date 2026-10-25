<?php

namespace App\Console\Commands;

use App\Services\MarketInsight\MarketDataSyncService;
use Illuminate\Console\Command;

final class SyncMarketInsight extends Command
{
    protected $signature = 'market-insight:sync {--timeframe=} {--force}';

    protected $description = 'Synchronize verified Twelve Data XAU/USD quotes and completed candles.';

    public function handle(MarketDataSyncService $sync): int
    {
        if (!\App\Support\TradingSubmodules::enabled('ai_market_insight')) return self::SUCCESS;
        $result = $sync->sync($this->option('timeframe'), null, (bool) $this->option('force'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return in_array($result['status'], ['CONNECTED', 'IDLE'], true) ? self::SUCCESS : self::FAILURE;
    }
}
