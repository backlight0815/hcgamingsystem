<?php

namespace Tests\Unit;

use App\Services\TradingRiskAlertService;
use Tests\TestCase;

class TradingRiskAlertServiceTest extends TestCase
{
    private TradingRiskAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trading_detection.risk_alerts.enabled' => true,
            'trading_detection.risk_alerts.min_final_score' => 65.0,
            'trading_detection.risk_alerts.score_jump_threshold' => 20.0,
            'trading_detection.risk_alerts.behavior_score_jump_threshold' => 20.0,
            'trading_detection.risk_alerts.tier_rank_jump_threshold' => 1,
            'trading_detection.risk_alerts.minimum_confidence_score' => 50.0,
        ]);

        $this->service = app(TradingRiskAlertService::class);
    }

    public function test_high_gambling_risk_jump_triggers_admin_alert(): void
    {
        $increase = $this->service->riskIncreaseProfile(
            [
                'risk_score' => 20,
                'tier' => 'normal',
                'confidence_score' => 95,
                'gambling' => ['score' => 15],
                'revenge' => ['score' => 5],
            ],
            [
                'risk_score' => 75,
                'tier' => 'high',
                'confidence_score' => 90,
                'gambling' => ['score' => 75],
                'revenge' => ['score' => 20],
                'reasons' => ['Order risk exceeded planned risk.'],
            ],
            [['id' => 10]]
        );

        $this->assertTrue($this->service->shouldNotify($increase));
        $this->assertSame('gambling', $increase['primary_behaviour']);
        $this->assertSame(55.0, $increase['score_delta']);
    }

    public function test_low_confidence_high_risk_does_not_alert_without_critical_evidence(): void
    {
        $increase = $this->service->riskIncreaseProfile(
            [
                'risk_score' => 30,
                'tier' => 'low',
                'confidence_score' => 90,
                'gambling' => ['score' => 30],
                'revenge' => ['score' => 10],
            ],
            [
                'risk_score' => 70,
                'tier' => 'high',
                'confidence_score' => 35,
                'gambling' => ['score' => 70],
                'revenge' => ['score' => 10],
            ],
            [['id' => 11]]
        );

        $this->assertFalse($this->service->shouldNotify($increase));
    }

    public function test_critical_risk_alerts_even_when_confidence_is_low(): void
    {
        $increase = $this->service->riskIncreaseProfile(
            [
                'risk_score' => 60,
                'tier' => 'mid',
                'confidence_score' => 90,
                'gambling' => ['score' => 20],
                'revenge' => ['score' => 60],
            ],
            [
                'risk_score' => 85,
                'tier' => 'critical',
                'confidence_score' => 35,
                'gambling' => ['score' => 25],
                'revenge' => ['score' => 85],
            ],
            [['id' => 12]]
        );

        $this->assertTrue($this->service->shouldNotify($increase));
        $this->assertSame('revenge', $increase['primary_behaviour']);
    }

    public function test_strong_revenge_evidence_alerts_even_when_confidence_is_low(): void
    {
        $increase = $this->service->riskIncreaseProfile(
            [
                'risk_score' => 35,
                'tier' => 'low',
                'confidence_score' => 90,
                'gambling' => ['score' => 10],
                'revenge' => ['score' => 35],
            ],
            [
                'risk_score' => 70,
                'tier' => 'high',
                'confidence_score' => 35,
                'gambling' => ['score' => 10],
                'revenge' => ['score' => 70],
                'reasons' => [
                    'New trade opened during cooldown after a loss.',
                    'Lot size increased after previous losing trade.',
                ],
            ],
            [['id' => 14]]
        );

        $this->assertTrue($this->service->shouldNotify($increase));
    }

    public function test_existing_high_risk_without_meaningful_increase_does_not_repeat_alert(): void
    {
        $increase = $this->service->riskIncreaseProfile(
            [
                'risk_score' => 70,
                'tier' => 'high',
                'confidence_score' => 90,
                'gambling' => ['score' => 70],
                'revenge' => ['score' => 20],
            ],
            [
                'risk_score' => 72,
                'tier' => 'high',
                'confidence_score' => 90,
                'gambling' => ['score' => 72],
                'revenge' => ['score' => 21],
            ],
            [['id' => 13]]
        );

        $this->assertFalse($this->service->shouldNotify($increase));
    }
}
