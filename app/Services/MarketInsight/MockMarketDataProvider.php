<?php

namespace App\Services\MarketInsight;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MockMarketDataProvider implements MarketDataProvider
{
    public function __construct(private readonly CandleValidator $validator)
    {
    }

    public function generate(object $instrument, string $timeframe, string $scenario, int $seed, ?CarbonImmutable $anchor = null): int
    {
        $minutes = MarketInsightSettings::TIMEFRAMES[$timeframe] ?? null;
        if (! $minutes || ! in_array($scenario, MarketInsightSettings::SCENARIOS, true) || $seed < 1 || $seed > 2147483646) {
            throw new InvalidArgumentException('Invalid mock dataset selection.');
        }
        $anchor = ($anchor ?? CarbonImmutable::now('UTC'))->startOfMinute();
        $anchor = $anchor->subMinutes(($anchor->hour * 60 + $anchor->minute) % $minutes);
        $settings = MarketInsightSettings::current()['settings']['indicators'];
        $count = max(180, (int) $settings['macd']['slow'] * 3 + (int) $settings['macd']['signal'] + 25,
            (int) $settings['bollinger']['period'] + 40, (int) $settings['atr']['period'] + (int) $settings['atr']['baseline_period'] + 40);
        $dailyCount = max(60, (int) $settings['adr']['period'] + 20);
        $state = $seed;
        $chart = $this->makeCandles((float) $instrument->demo_base_price, $timeframe, $scenario,
            $count, $anchor, $instrument->session_calendar, $state);
        // ADR always receives its own completed daily sessions. Keep them in
        // the same versioned dataset without pretending they are live quotes.
        $dailyAnchor = $anchor->startOfDay();
        $daily = $timeframe === 'D1' ? $chart : $this->makeCandles(
            (float) $instrument->demo_base_price, 'D1', $scenario, $dailyCount,
            $dailyAnchor, $instrument->session_calendar, $state);
        if ($timeframe !== 'D1') {
            // Align the independently generated daily history to the current
            // demo price level so ADR remains comparable with intraday range.
            $scale = end($chart)['close'] / end($daily)['close'];
            $daily = array_map(static function (array $candle) use ($scale): array {
                foreach (['open', 'high', 'low', 'close'] as $field) {
                    $candle[$field] = round($candle[$field] * $scale, 8);
                }

                return $candle;
            }, $daily);
        }
        $this->validator->validate($chart, $timeframe);
        if ($timeframe !== 'D1') {
        $this->validator->validate($daily, 'D1');
        }

        return DB::transaction(function () use ($instrument, $timeframe, $scenario, $seed, $anchor, $chart, $daily): int {
            // Locking the instrument serializes versions even under repeated
            // regenerate clicks by the same administrator.
            DB::table('market_insight_instruments')->where('id', $instrument->id)->lockForUpdate()->first();
            $version = (int) DB::table('market_insight_datasets')
                ->where('instrument_id', $instrument->id)->where('timeframe', $timeframe)->max('version') + 1;
            $id = DB::table('market_insight_datasets')->insertGetId([
                'instrument_id' => $instrument->id, 'timeframe' => $timeframe,
                'version' => $version, 'scenario' => $scenario, 'seed' => $seed,
                'anchor_at' => $anchor->toDateTimeString(), 'source' => 'MOCK',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($timeframe === 'D1' ? $chart : [...$chart, ...$daily], 200) as $chunk) {
                DB::table('market_insight_candles')->insert(array_map(
                    fn (array $candle): array => ['dataset_id' => $id, ...$candle], $chunk));
            }

            return $id;
        });
    }

    public function load(int $datasetId): array
    {
        $dataset = DB::table('market_insight_datasets')->find($datasetId);
        if (! $dataset || $dataset->source !== 'MOCK') {
        throw new InvalidArgumentException('Mock dataset not found.');
        }
        $raw = DB::table('market_insight_candles')->where('dataset_id', $datasetId)
            ->orderBy('open_at')->get();
        $groups = [];
        foreach ($raw as $row) {
            $groups[$row->timeframe][] = [
                'timeframe' => $row->timeframe,
                'open_at' => $row->open_at, 'close_at' => $row->close_at,
                'open' => (float) $row->open, 'high' => (float) $row->high,
                'low' => (float) $row->low, 'close' => (float) $row->close,
                'volume' => $row->volume === null ? null : (float) $row->volume,
                'source' => $row->source, 'is_complete' => (bool) $row->is_complete,
            ];
        }

        return ['dataset' => $dataset, 'chart' => $groups[$dataset->timeframe] ?? [], 'daily' => $groups['D1'] ?? []];
    }

    private function makeCandles(float $base, string $timeframe, string $scenario, int $count,
        CarbonImmutable $anchor, string $calendar, int &$state): array
    {
        $minutes = MarketInsightSettings::TIMEFRAMES[$timeframe];
        $timeScale = $minutes / 1440;
        $rangeScale = sqrt($timeScale);
        $slots = [];
        $cursor = $anchor;
        while (count($slots) < $count) {
            $open = $cursor->subMinutes($minutes);
            if ($calendar === 'all_days' || ! in_array($open->dayOfWeekIso, [6, 7], true)) {
                $slots[] = $open;
            }
            $cursor = $open;
        }
        $slots = array_reverse($slots);
        $previous = $base;
        $candles = [];
        foreach ($slots as $index => $openAt) {
            $noise = $this->random($state) - 0.5;
            $wave = sin($index / 5.0) * 0.0009 * $rangeScale;
            $step = match ($scenario) {
                'rising' => 0.0018 * $timeScale + $wave + $noise * 0.0014 * $rangeScale,
                'falling' => -0.0018 * $timeScale + $wave + $noise * 0.0014 * $rangeScale,
                'sideways' => $wave + $noise * 0.0015 * $rangeScale,
                'volatile' => (sin($index / 2.7) * 0.012 + $noise * 0.020) * $rangeScale,
            };
            $close = max($base * 0.02, $previous * (1 + $step));
            $spread = max($base * 0.00002 * $rangeScale, $previous * $rangeScale * (
                $scenario === 'volatile' ? 0.003 + $this->random($state) * 0.009 : 0.0005 + $this->random($state) * 0.0015));
            $candles[] = [
                'timeframe' => $timeframe,
                'open_at' => $openAt->toDateTimeString(), 'close_at' => $openAt->addMinutes($minutes)->toDateTimeString(),
                'open' => round($previous, 8), 'high' => round(max($previous, $close) + $spread, 8),
                'low' => round(max(0.00000001, min($previous, $close) - $spread), 8),
                'close' => round($close, 8), 'volume' => round(100 + $this->random($state) * 900, 4),
                'is_complete' => true, 'source' => 'MOCK',
            ];
            $previous = $close;
        }

        return $candles;
    }

    private function random(int &$state): float
    {
        $state = ($state * 48271) % 2147483647;

        return $state / 2147483647;
    }
}
