<?php

namespace App\Console\Commands;

use App\Services\MarketInsight\MarketInsightService;
use App\Services\MarketInsight\MarketInsightSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SeedMarketInsightDemo extends Command
{
    protected $signature = 'market-insight:seed-demo {--symbol=XAUUSD : Enabled demo instrument} {--timeframe=H1 : M15, M30, H1, H4 or D1} {--scenario=rising : rising, falling, sideways or volatile} {--seed=41821 : Repeatable seed} {--all : Seed all enabled instruments and all supported timeframes}';

    protected $description = 'Explicitly generate and analyze persisted Phase 1 simulated market candles.';

    public function handle(MarketInsightService $insights): int
    {
        $timeframe = strtoupper((string) $this->option('timeframe'));
        $scenario = strtolower((string) $this->option('scenario'));
        $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);
        if (! isset(MarketInsightSettings::TIMEFRAMES[$timeframe]) ||
            ! in_array($scenario, MarketInsightSettings::SCENARIOS, true) || $seed === false ||
            $seed < 1 || $seed > 2147483646) {
            $this->error('Use a supported timeframe/scenario and seed between 1 and 2147483646.');

            return self::FAILURE;
        }
        $symbols = $this->option('all')
            ? DB::table('market_insight_instruments')->where('is_enabled', true)->pluck('symbol')->all()
            : [strtoupper((string) $this->option('symbol'))];
        $timeframes = $this->option('all') ? array_keys(MarketInsightSettings::TIMEFRAMES) : [$timeframe];
        $anchor = CarbonImmutable::now('UTC');
        foreach ($symbols as $symbol) {
            try {
                $instrument = $insights->instrument($symbol);
                foreach ($timeframes as $selectedTimeframe) {
                    $result = $insights->regenerate($instrument, $selectedTimeframe, $scenario,
                        $seed, $anchor, null);
                    $this->line("$symbol $selectedTimeframe: ".$result['status'].', dataset '.$result['dataset_id'].'.');
                }
            } catch (\Throwable $error) {
                $this->error($symbol.': '.$error->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
