<?php

namespace Tests\Unit;

use App\Http\Controllers\Trading\FundedTraderController;
use App\Http\Controllers\Trading\TradingJournalController;
use ReflectionMethod;
use Tests\TestCase;

class PropFirmProfitTargetReviewTest extends TestCase
{
    public function test_phase_one_profit_target_with_one_profitable_day_needs_admin_review(): void
    {
        $controller = new TradingJournalController();
        $method = new ReflectionMethod($controller, 'needsPhaseProfitTargetDecision');
        $method->setAccessible(true);

        $evaluation = [
            'profit_target' => ['passed' => true],
            'profitable_day' => [
                'profitable_days' => 1,
                'required_days' => 3,
                'has_profitable_day' => false,
            ],
            'gross_profit_rule' => ['passed' => true],
        ];

        $this->assertTrue($method->invoke($controller, $evaluation, 1));
    }

    public function test_phase_two_profit_target_without_three_profitable_days_needs_admin_review(): void
    {
        $controller = new TradingJournalController();
        $method = new ReflectionMethod($controller, 'needsPhaseProfitTargetDecision');
        $method->setAccessible(true);

        $evaluation = [
            'profit_target' => ['passed' => true],
            'profitable_day' => ['has_profitable_day' => false],
            'gross_profit_rule' => ['passed' => true],
        ];

        $this->assertTrue($method->invoke($controller, $evaluation, 2));
    }

    public function test_phase_two_profit_target_review_waits_when_profitable_days_are_complete(): void
    {
        $controller = new TradingJournalController();
        $method = new ReflectionMethod($controller, 'needsPhaseProfitTargetDecision');
        $method->setAccessible(true);

        $evaluation = [
            'profit_target' => ['passed' => true],
            'profitable_day' => ['has_profitable_day' => true],
            'gross_profit_rule' => ['passed' => true],
        ];

        $this->assertFalse($method->invoke($controller, $evaluation, 2));
    }

    public function test_phase_two_uses_separate_review_statuses(): void
    {
        $controller = new TradingJournalController();

        $reviewStatus = new ReflectionMethod($controller, 'profitTargetReviewStatusForPhase');
        $reviewStatus->setAccessible(true);
        $requiredDaysStatus = new ReflectionMethod($controller, 'profitableDaysRequiredStatusForPhase');
        $requiredDaysStatus->setAccessible(true);

        $this->assertSame('phase2_profit_target_review', $reviewStatus->invoke($controller, 2));
        $this->assertSame('phase2_profitable_days_required', $requiredDaysStatus->invoke($controller, 2));
    }

    public function test_admin_required_profitable_days_can_be_one_more_day_from_current_progress(): void
    {
        $controller = new FundedTraderController();
        $method = new ReflectionMethod($controller, 'normalizeRequiredProfitableDays');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($controller, 2, 1));
    }

    public function test_admin_required_profitable_days_can_be_two_more_days_from_current_progress(): void
    {
        $controller = new FundedTraderController();
        $method = new ReflectionMethod($controller, 'normalizeRequiredProfitableDays');
        $method->setAccessible(true);

        $this->assertSame(3, $method->invoke($controller, 3, 1));
    }
}
