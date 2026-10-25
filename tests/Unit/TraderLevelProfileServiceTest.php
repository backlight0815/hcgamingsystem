<?php

namespace Tests\Unit;

use App\Services\TraderLevelProfileService;
use Tests\TestCase;

class TraderLevelProfileServiceTest extends TestCase
{
    private TraderLevelProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TraderLevelProfileService::class);
    }

    public function test_small_sample_can_be_marked_as_professional_candidate_when_current_evidence_is_clean(): void
    {
        $profile = $this->service->profile(
            $this->summary([
                'total_trades' => 6,
                'net_profit_loss' => 850.0,
                'win_rate' => 83.33,
                'profit_factor' => 3.25,
                'max_drawdown_percent' => 1.5,
                'max_daily_loss_percent' => 1.0,
            ]),
            [
                'score' => 80.0,
                'raw_score' => 92.0,
            ],
            $this->behaviorProfile(10.0),
            ['score' => 88.0]
        );

        $this->assertSame('small_sample', $profile['sample_mode']);
        $this->assertSame(10, $profile['level_number']);
        $this->assertSame('Small Sample Professional Candidate', $profile['label']);
        $this->assertSame('A', $profile['grade']);
        $this->assertSame(92.0, (float) $profile['score']);
        $this->assertStringContainsString('standard-sample professional grade', data_get($profile, 'next_level.requirement'));
    }

    public function test_high_gambling_risk_overrides_a_high_score_to_gambling_trader_profile(): void
    {
        $profile = $this->service->profile(
            $this->summary([
                'total_trades' => 24,
                'net_profit_loss' => 1200.0,
                'win_rate' => 75.0,
                'profit_factor' => 2.8,
            ]),
            [
                'score' => 92.0,
                'raw_score' => 92.0,
            ],
            $this->behaviorProfile(75.0, gamblingRank: 3, gamblingScore: 75.0),
            ['score' => 75.0]
        );

        $this->assertSame('standard_sample', $profile['sample_mode']);
        $this->assertSame(3, $profile['level_number']);
        $this->assertSame('Gambling Trader', $profile['label']);
        $this->assertSame('G', $profile['grade']);
        $this->assertTrue($profile['cap_active']);
        $this->assertStringContainsString('Gambling-style behavior', $profile['cap_reason']);
    }

    public function test_standard_clean_high_score_maps_to_professional_trader(): void
    {
        $profile = $this->service->profile(
            $this->summary([
                'total_trades' => 18,
                'net_profit_loss' => 900.0,
                'win_rate' => 70.0,
                'profit_factor' => 2.5,
                'max_drawdown_percent' => 3.0,
            ]),
            [
                'score' => 86.0,
                'raw_score' => 86.0,
            ],
            $this->behaviorProfile(12.0),
            ['score' => 82.0]
        );

        $this->assertSame('standard_sample', $profile['sample_mode']);
        $this->assertSame(9, $profile['level_number']);
        $this->assertSame('Professional Trader', $profile['label']);
        $this->assertSame('B', $profile['grade']);
        $this->assertFalse($profile['cap_active']);
    }

    public function test_high_score_can_be_capped_to_intermediate_when_position_and_best_day_caps_are_active(): void
    {
        $profile = $this->service->profile(
            $this->summary([
                'total_trades' => 20,
                'net_profit_loss' => 1531.18,
                'win_rate' => 70.0,
                'profit_factor' => 2.6,
                'best_day_rule' => [
                    'passed' => false,
                    'score_percent' => 68.5,
                ],
            ]),
            [
                'score' => 95.0,
                'raw_score' => 92.0,
            ],
            $this->behaviorProfile(24.0),
            ['score' => 38.0]
        );

        $this->assertSame(10, $profile['base_level_number']);
        $this->assertSame('Elite Professional Trader', $profile['base_level_label']);
        $this->assertSame(6, $profile['level_number']);
        $this->assertSame('Intermediate Trader', $profile['label']);
        $this->assertSame('E', $profile['grade']);
        $this->assertTrue($profile['cap_active']);
        $this->assertStringContainsString('classification score of 95.00/100 maps to L10', $profile['level_explanation']);
        $this->assertStringContainsString('Final trader level is L6', $profile['level_explanation']);
        $this->assertTrue(collect($profile['cap_details'])->contains(fn (array $cap): bool => $cap['label'] === 'Position Consistency Cap' && $cap['cap_level_number'] === 6));
        $this->assertTrue(collect($profile['cap_details'])->contains(fn (array $cap): bool => $cap['label'] === 'Best-Day Concentration Cap' && $cap['cap_level_number'] === 8));
    }

    private function summary(array $overrides = []): array
    {
        return array_merge([
            'total_trades' => 12,
            'net_profit_loss' => 500.0,
            'win_rate' => 65.0,
            'profit_factor' => 2.0,
            'max_drawdown_percent' => 2.0,
            'max_daily_loss_percent' => 1.0,
            'largest_win' => 150.0,
            'profitable_days' => 5,
            'best_day_rule' => [
                'passed' => true,
            ],
            'gross_profit_rule' => [
                'passed' => true,
            ],
        ], $overrides);
    }

    private function behaviorProfile(float $riskScore, int $gamblingRank = 0, float $gamblingScore = 0.0, int $revengeRank = 0, float $revengeScore = 0.0): array
    {
        return [
            'risk_score' => $riskScore,
            'final_score' => $riskScore,
            'gambling' => [
                'score' => $gamblingScore,
                'tier_rank' => $gamblingRank,
            ],
            'revenge' => [
                'score' => $revengeScore,
                'tier_rank' => $revengeRank,
            ],
        ];
    }
}
