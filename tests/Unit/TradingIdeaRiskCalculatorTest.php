<?php

namespace Tests\Unit;

use App\Services\TradingIdeaRiskCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TradingIdeaRiskCalculatorTest extends TestCase
{
    public function test_default_budget_and_missing_specifications_never_invent_size(): void
    {
        $risk = (new TradingIdeaRiskCalculator)->evaluate(['equity' => 10000, 'side' => 'buy',
            'entry' => 2650, 'stop' => 2640, 'opposing_level' => 2675]);
        $this->assertSame(50.0, $risk['risk_budget']);
        $this->assertNull($risk['position_size']);
        $this->assertSame(2.5, $risk['reward_ratio']);
    }

    public function test_nearby_resistance_forces_no_trade_and_related_risk_reduces_size(): void
    {
        $risk = (new TradingIdeaRiskCalculator)->evaluate(['equity' => 10000, 'risk_percent' => .5,
            'existing_idea_risk' => 20, 'side' => 'buy', 'entry' => 2650, 'stop' => 2640,
            'opposing_level' => 2664, 'tick_size' => .01, 'tick_value' => .1,
            'volume_step' => .01, 'minimum_volume' => .01]);
        $this->assertSame('No Trade', $risk['classification']);
        $this->assertSame(30.0, $risk['remaining_risk']);
        $this->assertSame(1.4, $risk['reward_ratio']);
        $this->assertSame(.3, $risk['position_size']);
        $this->assertSame(50.0, $risk['planned_loss']);
    }

    public function test_over_cap_and_minimum_volume_are_rejected(): void
    {
        $calculator = new TradingIdeaRiskCalculator;
        $this->expectException(InvalidArgumentException::class);
        $calculator->evaluate(['equity' => 10000, 'risk_percent' => 1.1]);
    }

    public function test_cost_adjusted_break_even_is_not_entry(): void
    {
        $calculator = new TradingIdeaRiskCalculator;
        $this->assertSame(101.0, $calculator->breakEven(100, 'buy', 5, 1, 5));
        $this->assertSame(99.0, $calculator->breakEven(100, 'sell', 5, 1, 5));
    }

    public function test_minimum_volume_can_force_no_trade(): void
    {
        $risk = (new TradingIdeaRiskCalculator)->evaluate(['equity' => 1000, 'risk_percent' => .5,
            'side' => 'sell', 'entry' => 100, 'stop' => 110, 'opposing_level' => 70,
            'tick_size' => 1, 'tick_value' => 10, 'volume_step' => .1, 'minimum_volume' => .1]);
        $this->assertSame('No Trade', $risk['classification']);
        $this->assertNull($risk['position_size']);
    }
}
