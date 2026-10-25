<?php

namespace Tests\Unit;

use App\Http\Controllers\Trading\TradingStatisticsController;
use App\Services\TradingBehaviourDetectionService;
use App\Services\TradingJournalAnalytics;
use ReflectionMethod;
use Tests\TestCase;

class TradingBehaviourDetectionServiceTest extends TestCase
{
    private TradingBehaviourDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TradingBehaviourDetectionService::class);
    }

    public function test_planned_layer_normal_case_is_controlled(): void
    {
        $trade = [
            'trade_plan_id' => 1,
            'setup_id' => 1001,
            'is_layer' => true,
            'planned_layer' => true,
            'order_number' => 2,
            'risk_percent' => 0.4,
            'lot_size' => 0.05,
            'stop_loss' => 4560.00,
            'take_profit' => 4585.00,
            'entry_reason' => 'Planned second order to improve risk reward.',
            'strategy_id' => 7,
            'pair' => 'XAUUSD',
            'direction' => 2,
            'emotion_tag' => 'calm',
        ];

        $accountStats = [
            'actual_total_setup_risk_percent' => 0.8,
            'orders_today' => 2,
            'margin_used_percent' => 12.0,
            'daily_loss_percent' => 0.0,
            'last_trade_result' => 'win',
            'minutes_since_last_loss' => 1440,
            'loss_streak' => 0,
            'risk_reward_ratio' => 2.0,
            'setup_symbol' => 'XAUUSD',
            'setup_direction' => 2,
            'symbols_traded_short_time' => 1,
            'strategies_switched_short_time' => 1,
        ];

        $result = $this->service->detect($trade, null, $accountStats);

        $this->assertTrue($result['is_planned_layer']);
        $this->assertContains($result['tier'], ['normal', 'low']);
        $this->assertSame('Planned Layer / Controlled Risk', $result['label']);
        $this->assertContains('Trade is a valid planned layer within risk limit.', $result['reasons']);
        $this->assertContains('No revenge trading evidence detected.', $result['reasons']);
    }

    public function test_revenge_trading_high_risk_requires_loss_chasing_evidence(): void
    {
        $trade = [
            'trade_plan_id' => 1,
            'setup_id' => 2002,
            'risk_percent' => 0.9,
            'lot_size' => 0.10,
            'stop_loss' => 4550.00,
            'take_profit' => 4590.00,
            'entry_reason' => 'Re-entry after loss.',
            'strategy_id' => 8,
            'pair' => 'XAUUSD',
            'direction' => 2,
            'emotion_tag' => 'stress',
        ];

        $accountStats = [
            'actual_total_setup_risk_percent' => 0.9,
            'margin_used_percent' => 18.0,
            'daily_loss_percent' => 1.0,
            'last_trade_result' => 'loss',
            'minutes_since_last_loss' => 3,
            'previous_losing_trade_risk_percent' => 0.4,
            'previous_losing_trade_lot_size' => 0.05,
            'previous_losing_trade_symbol' => 'XAUUSD',
            'previous_losing_trade_direction' => 2,
            'previous_losing_trade_exit_reason' => 'stop_loss',
            'loss_streak' => 1,
            'risk_reward_ratio' => 2.0,
        ];

        $result = $this->service->detect($trade, null, $accountStats);

        $this->assertGreaterThanOrEqual(65, $result['revenge_score']);
        $this->assertContains($result['tier'], ['high', 'critical']);
        $this->assertSame('Possible Revenge Trading Behaviour', $result['label']);
        $this->assertContains('New trade opened during cooldown after a loss.', $result['reasons']);
        $this->assertContains('Risk increased after previous losing trade.', $result['reasons']);
    }

    public function test_gambling_behaviour_high_risk_detects_risk_discipline_breaks(): void
    {
        $trade = [
            'trade_plan_id' => null,
            'setup_id' => 3003,
            'standalone_trade' => false,
            'risk_percent' => 1.0,
            'lot_size' => 0.15,
            'stop_loss' => null,
            'take_profit' => 4600.00,
            'entry_reason' => 'Manual setup.',
            'strategy_id' => 9,
            'pair' => 'XAUUSD',
            'direction' => 1,
            'emotion_tag' => 'calm',
            'risk_reward_ratio' => 0.8,
        ];

        $accountStats = [
            'actual_total_setup_risk_percent' => 1.0,
            'orders_today' => 3,
            'margin_used_percent' => 20.0,
            'daily_loss_percent' => 0.5,
            'last_trade_result' => 'win',
            'minutes_since_last_loss' => 1000,
            'loss_streak' => 0,
        ];

        $result = $this->service->detect($trade, null, $accountStats);

        $this->assertGreaterThanOrEqual(65, $result['gambling_score']);
        $this->assertSame('high', $result['tier']);
        $this->assertSame('Possible Gambling Trading Behaviour', $result['label']);
        $this->assertContains('Trade has no clear plan.', $result['reasons']);
        $this->assertContains('Trade has no stop loss.', $result['reasons']);
        $this->assertContains('Order risk exceeded planned risk.', $result['reasons']);
    }

    public function test_daily_loss_limit_exceeded_forces_critical(): void
    {
        $trade = [
            'trade_plan_id' => 1,
            'setup_id' => 4004,
            'risk_percent' => 0.4,
            'lot_size' => 0.05,
            'stop_loss' => 4560.00,
            'take_profit' => 4590.00,
            'entry_reason' => 'Valid setup.',
            'strategy_id' => 10,
            'pair' => 'XAUUSD',
            'direction' => 1,
            'emotion_tag' => 'calm',
        ];

        $accountStats = [
            'actual_total_setup_risk_percent' => 0.7,
            'margin_used_percent' => 15.0,
            'daily_loss_percent' => 5.0,
            'last_trade_result' => 'win',
            'minutes_since_last_loss' => 999,
            'loss_streak' => 0,
            'risk_reward_ratio' => 2.0,
        ];

        $result = $this->service->detect($trade, null, $accountStats);

        $this->assertGreaterThanOrEqual(80, $result['revenge_score']);
        $this->assertSame('critical', $result['tier']);
        $this->assertSame('Critical Risk: Daily Loss Limit Exceeded', $result['label']);
        $this->assertContains('Daily loss limit exceeded.', $result['reasons']);
    }

    public function test_missing_data_lowers_confidence_without_forcing_high_tier(): void
    {
        $trade = [
            'risk_percent' => 0.4,
            'lot_size' => 0.05,
            'stop_loss' => 4560.00,
        ];

        $result = $this->service->detect($trade);

        $this->assertLessThan(50, $result['confidence_score']);
        $this->assertNotContains($result['tier'], ['high', 'critical']);
        $this->assertSame('Low Confidence Detection', $result['label']);
        $this->assertContains('Some trading data is missing, classification confidence is reduced.', $result['reasons']);
    }

    public function test_journal_analytics_uses_professional_detector_for_planned_layers(): void
    {
        $analytics = new TradingJournalAnalytics();
        $trades = collect([
            [
                'id' => 1,
                'user_id' => 10,
                'source' => 'journal',
                'trade_plan_id' => 1,
                'setup_id' => 501,
                'is_layer' => true,
                'planned_layer' => true,
                'order_number' => 1,
                'risk_percent' => 0.4,
                'stop_loss' => 4560.00,
                'take_profit' => 4590.00,
                'entry_reason' => 'Planned base order.',
                'strategy_id' => 3,
                'pair' => 'XAUUSD',
                'direction' => 2,
                'entry_price' => 4572.00,
                'exit_price' => 4579.00,
                'lot_size' => 0.05,
                'pips' => 70,
                'profit_loss' => -30.00,
                'open_date' => '2026-05-25 18:19:00',
                'close_date' => '2026-05-26 07:10:00',
            ],
            [
                'id' => 2,
                'user_id' => 10,
                'source' => 'journal',
                'trade_plan_id' => 1,
                'setup_id' => 501,
                'is_layer' => true,
                'planned_layer' => true,
                'order_number' => 2,
                'risk_percent' => 0.4,
                'stop_loss' => 4560.00,
                'take_profit' => 4590.00,
                'entry_reason' => 'Planned second layer within setup risk.',
                'strategy_id' => 3,
                'pair' => 'XAUUSD',
                'direction' => 2,
                'entry_price' => 4568.00,
                'exit_price' => 4579.00,
                'lot_size' => 0.05,
                'pips' => 110,
                'profit_loss' => 50.00,
                'open_date' => '2026-05-25 18:24:00',
                'close_date' => '2026-05-26 08:58:00',
            ],
        ]);

        $profile = $analytics->behavioralRiskProfile($trades, 10000);

        $this->assertSame('normal', $profile['tier']);
        $this->assertSame('Planned Layer / Controlled Risk', $profile['label']);
        $this->assertSame('Planned Layer / Controlled Risk', $profile['style_label']);
        $this->assertLessThan(25, $profile['risk_score']);
        $this->assertSame(2, $profile['planned_layer_count']);
        $this->assertFalse((bool) data_get($profile, 'revenge.detected'));
        $this->assertFalse((bool) data_get($profile, 'gambling.detected'));
    }

    public function test_score_evaluation_uses_provisional_score_for_small_controlled_recovery_sample(): void
    {
        $controller = app(TradingStatisticsController::class);
        $method = new ReflectionMethod($controller, 'buildScoreEvaluationProfile');
        $method->setAccessible(true);

        $summary = [
            'total_trades' => 3,
            'winning_trades' => 1,
            'losing_trades' => 2,
            'win_rate' => 33.33,
            'total_profit' => 100.0,
            'total_loss' => 90.0,
            'net_profit_loss' => 10.0,
            'average_win' => 100.0,
            'average_loss' => 45.0,
            'profit_factor' => 1.11,
            'expectancy' => 3.33,
            'recovery_factor' => 1.0,
            'max_drawdown_amount' => 90.0,
            'max_drawdown_percent' => 1.8,
            'max_daily_loss_percent' => 1.8,
            'overall_loss_percent' => 0.0,
            'best_day_rule' => [
                'total_generated_profit' => 100.0,
                'best_winning_day' => 100.0,
                'score_percent' => 100.0,
                'status' => 'Needs More Profit',
                'passed' => false,
            ],
        ];

        $behaviorProfile = [
            'risk_score' => 10.0,
            'final_score' => 10.0,
            'revenge' => ['tier_rank' => 0, 'status' => 'Normal'],
            'gambling' => ['tier_rank' => 0, 'status' => 'Normal'],
        ];

        $profile = $method->invoke($controller, $summary, $behaviorProfile);

        $this->assertTrue($profile['score_is_provisional']);
        $this->assertSame('Provisional - Controlled Recovery', $profile['sample_size_status']);
        $this->assertGreaterThan((float) $profile['raw_score'], (float) $profile['score']);
        $this->assertGreaterThanOrEqual(65, (float) $profile['score']);
        $this->assertTrue(collect($profile['components'])->contains(fn (array $component): bool => $component['metric'] === 'Small Sample Adjustment'));
        $this->assertStringContainsString('Raw formula score', $profile['meaning']);
    }

    public function test_score_evaluation_includes_position_consistency_as_five_percent_split(): void
    {
        $controller = app(TradingStatisticsController::class);
        $method = new ReflectionMethod($controller, 'buildScoreEvaluationProfile');
        $method->setAccessible(true);

        $summary = [
            'total_trades' => 12,
            'winning_trades' => 9,
            'losing_trades' => 3,
            'win_rate' => 75.0,
            'total_profit' => 900.0,
            'total_loss' => 300.0,
            'net_profit_loss' => 600.0,
            'average_win' => 100.0,
            'average_loss' => 100.0,
            'profit_factor' => 3.0,
            'expectancy' => 50.0,
            'recovery_factor' => 3.0,
            'max_drawdown_amount' => 200.0,
            'max_drawdown_percent' => 2.0,
            'max_daily_loss_percent' => 1.0,
            'overall_loss_percent' => 1.0,
            'best_day_rule' => [
                'total_generated_profit' => 900.0,
                'best_winning_day' => 100.0,
                'score_percent' => 11.11,
                'status' => 'Passed',
                'passed' => true,
            ],
        ];

        $behaviorProfile = [
            'risk_score' => 0.0,
            'final_score' => 0.0,
            'revenge' => ['tier_rank' => 0, 'status' => 'Normal'],
            'gambling' => ['tier_rank' => 0, 'status' => 'Normal'],
        ];

        $positionProfile = [
            'score' => 80.0,
            'grade' => 'A',
            'status' => 'Stable Position Sizing',
            'is_dynamic' => false,
        ];

        $profile = $method->invoke($controller, $summary, $behaviorProfile, $positionProfile);
        $bestDayComponent = collect($profile['components'])->firstWhere('metric', 'Best Day Consistency');
        $positionComponent = collect($profile['components'])->firstWhere('metric', 'Position Consistency');

        $this->assertFalse($profile['score_is_provisional']);
        $this->assertSame(10, $bestDayComponent['max_points']);
        $this->assertSame(5, $positionComponent['max_points']);
        $this->assertEquals(4.0, $positionComponent['points']);
        $this->assertStringContainsString('Position Consistency', $profile['formula']);
    }

    public function test_behavior_recovery_reduces_score_after_clean_eight_trade_block(): void
    {
        $analytics = new TradingJournalAnalytics();
        $trades = collect([
            [
                'id' => 1,
                'user_id' => 10,
                'source' => 'journal',
                'pair' => 'XAUUSD',
                'direction' => 1,
                'entry_price' => 4500,
                'exit_price' => 4480,
                'lot_size' => 0.50,
                'profit_loss' => -200,
                'risk_percent' => 2.0,
                'open_date' => '2026-05-01 09:00:00',
                'close_date' => '2026-05-01 10:00:00',
            ],
        ]);

        for ($i = 2; $i <= 9; $i++) {
            $trades->push([
                'id' => $i,
                'user_id' => 10,
                'source' => 'journal',
                'trade_plan_id' => 100 + $i,
                'setup_id' => 200 + $i,
                'standalone_trade' => false,
                'pair' => 'XAUUSD',
                'direction' => 1,
                'entry_price' => 4500 + $i,
                'exit_price' => 4510 + $i,
                'lot_size' => 0.05,
                'profit_loss' => 25,
                'risk_percent' => 0.2,
                'stop_loss' => 4490,
                'take_profit' => 4520,
                'entry_reason' => 'Planned continuation trade.',
                'strategy_id' => 7,
                'open_date' => '2026-05-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 12:00:00',
                'close_date' => '2026-05-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 13:00:00',
            ]);
        }

        $profile = $analytics->behavioralRiskProfile($trades, 10000);

        $this->assertGreaterThan((float) $profile['risk_score'], (float) $profile['raw_risk_score']);
        $this->assertEquals(25.0, (float) data_get($profile, 'behavior_recovery.total_reduction_percent'));
        $this->assertSame(1, (int) data_get($profile, 'behavior_recovery.completed_blocks'));
        $this->assertSame(1, (int) data_get($profile, 'behavior_recovery.applied_blocks'));
        $this->assertStringContainsString('Behavior recovery applied', data_get($profile, 'behavior_recovery.summary'));
    }

    public function test_behavior_recovery_tier_reduction_rules_follow_prior_peak_requirement(): void
    {
        $analytics = new TradingJournalAnalytics();
        $method = new ReflectionMethod($analytics, 'professionalBehaviorRecoveryReductionPercent');
        $method->setAccessible(true);

        $this->assertEquals(25.0, $method->invoke($analytics, 0, 1));
        $this->assertEquals(20.0, $method->invoke($analytics, 1, 1));
        $this->assertEquals(0.0, $method->invoke($analytics, 2, 2));
        $this->assertEquals(10.0, $method->invoke($analytics, 2, 3));
        $this->assertEquals(0.0, $method->invoke($analytics, 3, 3));
        $this->assertEquals(5.0, $method->invoke($analytics, 3, 4));
        $this->assertEquals(0.0, $method->invoke($analytics, 4, 4));
    }
}
