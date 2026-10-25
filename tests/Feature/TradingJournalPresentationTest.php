<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingJournalPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trading_only_journal_keeps_its_controls_metrics_and_lower_sections(): void
    {
        FeatureToggle::updateOrCreate(
            ['feature_name' => FeatureToggle::MODULE_TRADING],
            ['enabled' => true]
        );
        FeatureToggle::updateOrCreate(
            ['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE],
            ['enabled' => false]
        );

        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->get(route('all.trading.journals'))->assertOk()
            ->assertSee('data-backoffice-mode="trading"', false)
            ->assertSee('trading-journal-contrast.css')
            ->assertSee('Performance snapshot')
            ->assertSee('journal-kpi-grid')
            ->assertSee('Record Trade')
            ->assertSee('Deposit')
            ->assertSee('Withdraw')
            ->assertSee('Performance Review')
            ->assertSee('Prop Firm Challenge')
            ->assertSee('Account Stage Comparison')
            ->assertSee('Weekly Behaviour Improvement')
            ->assertSee('Trade History')
            ->assertSee('No trades recorded')
            ->assertDontSee('colspan="21"', false)
            ->assertSee('ratingBreakdownModal')
            ->assertSee('scoreCalculationModal')
            ->assertSee('positionConsistencyModal')
            ->assertSee('behaviorDetectionModal');
    }
}
