<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\FeatureToggle;
use App\Models\MarketAnalysis;
use App\Models\User;
use App\Support\TradingSubmodules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingHubModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_switch_blocks_market_analyst_web_api_and_navigation_then_restores_it(): void
    {
        $admin = User::factory()->create(['role_id' => User::ROLE_SUPER_ADMIN, 'status' => 1]);
        $companyAdmin = User::factory()->create(['role_id' => User::ROLE_COMPANY_ADMIN, 'status' => 1]);
        $community = Community::create(['name' => 'Archive', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        MarketAnalysis::create(['community_id' => $community->id, 'title' => 'Existing report',
            'market' => 'XAUUSD', 'analysis_date' => today()->toDateString()]);
        $this->actingAs($companyAdmin)->get(route('admin.trading-modules.index'))->assertForbidden();
        $this->actingAs($companyAdmin)->post(route('admin.trading-modules.update', 'market_analyst'), ['enabled' => '0'])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.trading-modules.index'))->assertOk()->assertSee('Market Analyst');
        $this->post(route('admin.trading-modules.update', 'market_analyst'), ['enabled' => '0'])
            ->assertRedirect(route('admin.trading-modules.index'));
        $this->assertDatabaseHas('feature_toggles', ['feature_name' => TradingSubmodules::featureName('market_analyst'), 'enabled' => 0]);
        $this->get(route('market-analyst.create'))->assertStatus(503)->assertSee('Module Unavailable');
        $this->getJson(route('api.market_outlooks.index'))->assertStatus(503)->assertJsonPath('code', 'MODULE_DISABLED');
        $this->get(route('trading.hub.home'))->assertOk()->assertDontSee('>Market Analyst</a>', false);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'module.disabled']);
        $this->post(route('admin.trading-modules.update', 'market_analyst'), ['enabled' => '1'])->assertRedirect();
        $this->get(route('market-analyst.create'))->assertOk();
        $this->assertDatabaseCount('market_analyses', 1);
    }

    public function test_parent_switch_disables_child_without_changing_child_setting(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => TradingSubmodules::featureName('trader_centre')], ['enabled' => false]);
        $this->assertFalse(TradingSubmodules::enabled('trading_journal'));
        $this->assertTrue(FeatureToggle::isEnabled(TradingSubmodules::featureName('trading_journal')));
        $this->assertSame('trading_journal', TradingSubmodules::keyForRoute('api.mobile.v1.journal_sync.index'));
    }

    public function test_knowledge_and_signals_switches_block_their_routes_and_api(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->post(route('admin.trading-modules.update', 'knowledge_centre'), ['enabled' => '0'])->assertRedirect();
        $this->get(route('knowledge.centre.index'))->assertStatus(503);
        $this->get(route('trading.knowledge.centre.index'))->assertStatus(503);
        $this->post(route('admin.trading-modules.update', 'trading_signals'), ['enabled' => '0'])->assertRedirect();
        $this->get(route('member.signals.dashboard'))->assertStatus(503);
        $this->getJson(route('api.mobile.v1.signals.index'))->assertStatus(503)->assertJsonPath('code', 'MODULE_DISABLED');
        $this->get(route('trading.hub.home'))->assertOk()->assertDontSee('>Signals</a>', false);
    }

    public function test_position_review_shortcut_follows_position_centre_switch(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => TradingSubmodules::featureName('position_centre')], ['enabled' => false]);

        $this->assertSame('position_centre', TradingSubmodules::keyForRoute('admin.trading_positions.index'));
        $this->assertSame([], TradingSubmodules::filterMenus([
            ['label' => 'Trading Position Reviews', 'url' => '/admin/trading-positions'],
        ]));
    }

    public function test_management_payload_contains_every_registered_module_once_with_market_analyst_in_workspace(): void
    {
        $admin = User::factory()->create(['role_id' => User::ROLE_SUPER_ADMIN, 'status' => 1]);
        $response = $this->actingAs($admin)->getJson(route('admin.trading-modules.index'))->assertOk();
        $modules = collect($response->json('modules'));

        $this->assertCount(count(TradingSubmodules::definitions()), $modules);
        $this->assertEqualsCanonicalizing(array_keys(TradingSubmodules::definitions()), $modules->pluck('key')->all());
        $this->assertSame('workspace', $modules->firstWhere('key', 'market_analyst')['domain']);
        $this->assertSame('Trading Workspace', $response->json('domains.workspace.label'));
        $this->assertSame(1, $modules->where('key', 'market_analyst')->count());
        $this->assertLessThan(
            $modules->firstWhere('key', 'community')['order'],
            $modules->firstWhere('key', 'trading_news')['order']
        );
    }

    public function test_json_update_persists_only_tradinghub_key_and_rejects_stale_updates(): void
    {
        $admin = User::factory()->create(['role_id' => User::ROLE_SUPER_ADMIN, 'status' => 1]);
        $companyAdmin = User::factory()->create(['role_id' => User::ROLE_COMPANY_ADMIN, 'status' => 1]);
        $url = route('admin.trading-modules.update', 'market_analyst');
        $module = collect($this->actingAs($admin)->getJson(route('admin.trading-modules.index'))->json('modules'))
            ->firstWhere('key', 'market_analyst');
        $payload = [
            'enabled' => '0',
            'expected_enabled' => '1',
            'expected_updated_at' => $module['updated_at'],
        ];

        $this->actingAs($companyAdmin)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($admin)->postJson($url, $payload)->assertOk()->assertJsonPath('enabled', false);
        $this->postJson($url, $payload)->assertStatus(409);
        $this->assertDatabaseHas('feature_toggles', ['feature_name' => TradingSubmodules::featureName('market_analyst'), 'enabled' => 0]);
        $this->assertDatabaseHas('feature_toggles', ['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE, 'enabled' => 1]);
        $this->assertDatabaseHas('feature_toggles', ['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT, 'enabled' => 0]);
        $updated = collect($this->getJson(route('admin.trading-modules.index'))->json('modules'))
            ->firstWhere('key', 'market_analyst');
        $this->assertFalse($updated['enabled']);
    }
}
