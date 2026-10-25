<?php

namespace Tests\Unit;

use App\Services\MarketInsight\CandleValidator;
use App\Services\MarketInsight\MarketInsightSettings;
use App\Services\MarketInsight\MarketInsightSignalEngine;
use App\Services\MarketInsight\RuleBasedInsightExplanationGenerator;
use App\Services\MarketInsight\RuleBasedRiskContextEvaluator;
use App\Services\MarketInsight\TechnicalIndicatorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MarketInsightIndicatorTest extends TestCase
{
    private function signalEngine(): MarketInsightSignalEngine
    {
        return new MarketInsightSignalEngine(new RuleBasedRiskContextEvaluator,
            new RuleBasedInsightExplanationGenerator);
    }

    private function closes(array $values): array
    {
        return array_map(fn ($close) => ['open' => $close, 'high' => $close + 1,
            'low' => max(0.000001, $close - 1), 'close' => $close], $values);
    }

    public function test_bollinger_uses_population_deviation_and_neutral_flat_band(): void
    {
        $service = new TechnicalIndicatorService;
        $rule = ['period' => 2, 'multiplier' => 2, 'enabled' => true];
        $out = $service->bollinger($this->closes([1, 3, 5]), $rule);
        $this->assertEqualsWithDelta(4, $out['values']['middle'], 1e-9);
        $this->assertEqualsWithDelta(6, $out['values']['upper'], 1e-9);
        $this->assertEqualsWithDelta(2, $out['values']['lower'], 1e-9);
        $this->assertEqualsWithDelta(.75, $out['values']['percent_b'], 1e-9);
        $flat = $service->bollinger($this->closes([10, 10, 10]), $rule);
        $this->assertNull($flat['values']['percent_b']);
        $this->assertSame(0, $flat['direction']);
    }

    public function test_ema_and_macd_match_hand_calculated_sma_seeds(): void
    {
        $service = new TechnicalIndicatorService;
        $this->assertEqualsWithDelta(3.5, $service->emaSeries([1, 2, 3, 4], 2)[3], 1e-9);
        $macd = $service->macd($this->closes([1, 2, 3, 4, 8]),
            ['fast' => 2, 'slow' => 3, 'signal' => 2, 'enabled' => true]);
        $this->assertEqualsWithDelta(1, $macd['values']['line'], 1e-9);
        $this->assertEqualsWithDelta(5 / 6, $macd['values']['signal'], 1e-9);
        $this->assertEqualsWithDelta(1 / 6, $macd['values']['histogram'], 1e-9);
        $this->assertSame(1, $macd['direction']);
    }

    public function test_wilder_rsi_and_atr_edge_cases(): void
    {
        $service = new TechnicalIndicatorService;
        $rule = ['period' => 14, 'oversold' => 30, 'overbought' => 70, 'enabled' => true];
        $this->assertEqualsWithDelta(100, $service->rsi($this->closes(range(1, 20)), $rule)['values']['rsi'], 1e-9);
        $this->assertEqualsWithDelta(50, $service->rsi($this->closes(array_fill(0, 20, 100)), $rule)['values']['rsi'], 1e-9);
        $atr = $service->atr($this->closes(array_fill(0, 40, 100)),
            ['period' => 14, 'baseline_period' => 20, 'low_ratio' => .7, 'high_ratio' => 1.5, 'enabled' => true]);
        $this->assertEqualsWithDelta(2, $atr['values']['atr'], 1e-9);
        $this->assertSame('Normal Volatility', $atr['classification']);
    }

    public function test_adr_is_average_of_completed_daily_ranges_and_has_no_directional_vote(): void
    {
        $daily = array_fill(0, 14, ['high' => 104, 'low' => 100, 'close' => 102, 'is_complete' => true]);
        $chart = [['open_at' => '2026-10-14 00:00:00', 'timeframe' => 'H1', 'high' => 103, 'low' => 101]];
        $out = (new TechnicalIndicatorService)->adr($daily, $chart,
            ['period' => 14, 'low_ratio' => .7, 'extended_ratio' => 1.25, 'enabled' => true]);
        $this->assertEqualsWithDelta(4, $out['values']['adr'], 1e-9);
        $this->assertEqualsWithDelta(50, $out['values']['session_usage_percent'], 1e-9);
        $this->assertSame(0, $out['direction']);
    }

    public function test_weight_normalization_thresholds_and_risk_are_separate(): void
    {
        $settings = MarketInsightSettings::defaults();
        $settings['indicators']['bollinger']['enabled'] = false;
        $settings['indicators']['rsi']['enabled'] = false;
        $items = [];
        foreach ($settings['indicators'] as $key => $rule) {
            $items[$key] = ['name' => strtoupper($key), 'enabled' => $rule['enabled'], 'available' => true,
                'direction' => in_array($key, ['ema', 'macd']) ? 1 : 0,
                'classification' => $key === 'atr' ? 'High Volatility'
                    : ($key === 'adr' ? 'Extended Daily Range' : 'Bullish')];
        }
        $out = $this->signalEngine()->combine($items, $settings);
        $this->assertSame('STRONG BUY', $out['signal']);
        $this->assertSame(100.0, $out['directional_score']);
        $this->assertSame(50.0, $out['contributions']['ema']['weight']);
        $this->assertSame(0, $out['contributions']['atr']['points'] ?? 0);
        $this->assertSame('Elevated', $out['risk']);
        $this->assertStringContainsString('MACD', $out['explanation']);
        $this->assertStringContainsString('ATR', $out['explanation']);
        $items['macd']['available'] = false;
        $this->assertSame('unavailable', $this->signalEngine()->combine($items, $settings)['status']);
    }

    public function test_candle_validator_rejects_bad_prices_and_timestamps(): void
    {
        $valid = ['open' => 100, 'high' => 102, 'low' => 99, 'close' => 101,
            'open_at' => '2026-10-14 10:00:00', 'close_at' => '2026-10-14 11:00:00',
            'source' => 'MOCK', 'is_complete' => true];
        (new CandleValidator)->validate([$valid], 'H1');
        foreach ([['high' => 98], ['open_at' => ''], ['close_at' => '2026-10-14 10:30:00'], ['source' => 'LIVE'], ['volume' => INF]] as $fault) {
            try {
                (new CandleValidator)->validate([array_replace($valid, $fault)], 'H1');
                $this->fail('Invalid candle was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
