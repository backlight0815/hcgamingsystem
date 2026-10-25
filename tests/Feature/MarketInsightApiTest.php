<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarketInsight\MarketInsightService;
use App\Services\MarketInsight\MarketInsightSettings;
use App\Services\MarketInsight\MockMarketDataProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketInsightApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_reads_and_refreshes_single_persisted_snapshot(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $trader = User::factory()->create(['role_id' => 760]);
        $instrument = app(MarketInsightService::class)->instrument('XAUUSD');
        $first = app(MarketInsightService::class)->regenerate($instrument, 'H1', 'rising', 12345, null, $admin->id);
        $this->assertSame('available', $first['status']);
        $this->assertCount(6, $first['indicators']);
        $this->assertTrue($first['is_mock_data']);
        $this->assertSame('MOCK', $first['source']);
        $this->assertSame(0, $first['contributions']['atr']['points'] ?? 0);
        $this->assertSame(0, $first['contributions']['adr']['points'] ?? 0);
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/market-insights/latest?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertJsonPath('data.analysis.id', $first['id'])
            ->assertJsonPath('data.analysis.contract_version', 1)
            ->assertJsonPath('data.is_mock_data', true);
        $this->getJson('/api/mobile/v1/market-insights/latest')
            ->assertOk()->assertJsonPath('data.analysis.id', $first['id']);
        $this->postJson('/api/mobile/v1/market-insights/refresh', ['symbol' => 'XAUUSD', 'timeframe' => 'H1'])
            ->assertOk()->assertJsonPath('data.analysis.id', $first['id']);
        $this->assertDatabaseCount('market_insight_analyses', 1);
        $this->getJson('/api/mobile/v1/market-insights/history?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertJsonCount(1, 'data.history');
        $this->actingAs($trader, 'web')->get('/market-insight?symbol=XAUUSD&timeframe=H1')->assertOk()
            ->assertSee('DEMO / SIMULATED DATA')->assertSee('Bollinger Bands')->assertSee($first['signal']);
        $this->get('/market-insight')->assertOk()->assertSee($first['signal']);
    }

    public function test_admin_mutation_is_protected_and_versions_preserve_history(): void
    {
        $trader = User::factory()->create(['role_id' => 760]);
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/market-insights/admin/settings')->assertForbidden();
        $this->postJson('/api/mobile/v1/market-insights/admin/generate', [
            'symbol' => 'XAUUSD', 'timeframe' => 'H1', 'scenario' => 'rising', 'seed' => 55,
        ])->assertForbidden();
        $this->actingAs($trader, 'web')->get('/admin/market-insight')->assertForbidden();

        $admin = User::factory()->create(['role_id' => 1]);
        Sanctum::actingAs($admin);
        $this->actingAs($admin, 'web')->get('/admin/market-insight')->assertOk()->assertSee('AI Market Insight Management');
        $settings = MarketInsightSettings::defaults();
        $settings['thresholds']['buy'] = 20;
        $this->putJson('/api/mobile/v1/market-insights/admin/settings', ['settings' => $settings])
            ->assertOk()->assertJsonPath('data.version', 2);
        $bad = $settings;
        $bad['thresholds']['buy'] = -20;
        $this->putJson('/api/mobile/v1/market-insights/admin/settings', ['settings' => $bad])
            ->assertUnprocessable();
        $this->putJson('/api/mobile/v1/market-insights/admin/settings', ['settings' => 'bad'])
            ->assertUnprocessable();
        $this->postJson('/api/mobile/v1/market-insights/admin/generate', [
            'symbol' => 'UNKNOWN', 'timeframe' => 'H1', 'scenario' => 'rising', 'seed' => 55,
        ])->assertNotFound();
        $generated = $this->postJson('/api/mobile/v1/market-insights/admin/generate', [
            'symbol' => 'XAUUSD', 'timeframe' => 'H1', 'scenario' => 'falling', 'seed' => 55,
        ])->assertCreated()->json('data.analysis');
        $this->assertSame(2, $generated['configuration_version']);
        $this->putJson('/api/mobile/v1/market-insights/admin/settings', ['settings' => $settings])
            ->assertOk()->assertJsonPath('data.version', 3);
        $recalculated = $this->postJson('/api/mobile/v1/market-insights/admin/recalculate', [
            'symbol' => 'XAUUSD', 'timeframe' => 'H1',
        ])->assertOk()->json('data.analysis');
        $this->assertSame(3, $recalculated['configuration_version']);
        $this->assertNotSame($generated['id'], $recalculated['id']);
        $this->getJson('/api/mobile/v1/market-insights/'.$generated['id'])
            ->assertOk()->assertJsonPath('data.analysis.configuration_version', 2);
    }

    public function test_mock_generation_is_repeatable_and_stale_data_is_unavailable(): void
    {
        $instrument = app(MarketInsightService::class)->instrument('BTCUSD');
        $provider = app(MockMarketDataProvider::class);
        $anchor = CarbonImmutable::now('UTC')->startOfHour();
        $a = $provider->generate($instrument, 'M15', 'volatile', 9876, $anchor);
        $b = $provider->generate($instrument, 'M15', 'volatile', 9876, $anchor);
        $first = $provider->load($a);
        $second = $provider->load($b);
        $this->assertSame($first['chart'], $second['chart']);
        $this->assertSame($first['daily'], $second['daily']);
        $this->assertGreaterThan(150, count($first['chart']));
        $this->assertGreaterThanOrEqual(60, count($first['daily']));
        $this->assertSame('unavailable', app(MarketInsightService::class)->analyze(
            $provider->generate($instrument, 'H1', 'sideways', 1, $anchor->subDays(10)), null)['status']);
        $this->assertDatabaseHas('market_insight_execution_logs', ['status' => 'unavailable']);
        $this->assertSame(2, DB::table('market_insight_datasets')->where('timeframe', 'M15')->count());
    }

    public function test_visibility_and_snapshot_archive_are_enforced_for_standard_users(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $trader = User::factory()->create(['role_id' => 760]);
        $instrument = app(MarketInsightService::class)->instrument('XAUUSD');
        $analysis = app(MarketInsightService::class)->regenerate($instrument, 'H1', 'sideways', 81, null, $admin->id);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/mobile/v1/market-insights/admin/history/'.$analysis['id'].'/archive',
            ['archived' => true])->assertOk();
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/market-insights/'.$analysis['id'])->assertNotFound();
        $this->getJson('/api/mobile/v1/market-insights/history?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertJsonCount(0, 'data.history');
        $this->postJson('/api/mobile/v1/market-insights/refresh', ['symbol' => 'XAUUSD', 'timeframe' => 'H1'])
            ->assertOk()->assertJsonPath('data.status', 'unavailable');
        $this->actingAs($trader, 'web')->getJson('/market-insight/history/'.$analysis['id'])->assertNotFound();
        $settings = MarketInsightSettings::defaults();
        $settings['enabled_for_users'] = false;
        MarketInsightSettings::save($settings, $admin->id);
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/market-insights/instruments')->assertForbidden();
        $this->actingAs($trader, 'web')->getJson('/market-insight')->assertForbidden();
    }

    public function test_seed_command_generates_persisted_demo_candles_explicitly(): void
    {
        $this->artisan('market-insight:seed-demo', [
            '--symbol' => 'BTCUSD', '--timeframe' => 'D1', '--scenario' => 'falling', '--seed' => '27',
        ])->assertSuccessful();
        $this->assertDatabaseHas('market_insight_datasets', [
            'timeframe' => 'D1', 'scenario' => 'falling', 'seed' => 27, 'source' => 'MOCK',
        ]);
        $this->assertDatabaseCount('market_insight_analyses', 1);
        $this->assertGreaterThanOrEqual(180, DB::table('market_insight_candles')->count());
    }

    public function test_invalid_saved_ohlc_returns_unavailable_with_diagnostic_log(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $instrument = app(MarketInsightService::class)->instrument('XAUUSD');
        $datasetId = app(MockMarketDataProvider::class)->generate($instrument, 'H1', 'rising', 143);
        DB::table('market_insight_candles')->where('dataset_id', $datasetId)->where('timeframe', 'H1')
            ->orderByDesc('id')->limit(1)->update(['high' => 0.00000001]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/mobile/v1/market-insights/refresh', ['symbol' => 'XAUUSD', 'timeframe' => 'H1'])
            ->assertOk()->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.analysis.is_mock_data', true);
        $this->assertDatabaseHas('market_insight_execution_logs', ['type' => 'calculate', 'status' => 'unavailable']);
        $this->assertDatabaseCount('market_insight_analyses', 0);
    }

    public function test_intraday_demo_steps_are_timeframe_scaled_and_adr_uses_comparable_prices(): void
    {
        $instrument = app(MarketInsightService::class)->instrument('EURUSD');
        $datasetId = app(MockMarketDataProvider::class)->generate($instrument, 'M15', 'rising', 311);
        $data = app(MockMarketDataProvider::class)->load($datasetId);
        $first = $data['chart'][0]['close'];
        $last = end($data['chart'])['close'];
        $dailyLast = end($data['daily'])['close'];
        $this->assertLessThan(.05, abs($last / $first - 1));
        $this->assertEqualsWithDelta($last, $dailyLast, .00000001);
        $analysis = app(MarketInsightService::class)->analyze($datasetId, null);
        $this->assertSame('available', $analysis['status']);
        $this->assertGreaterThan(0, $analysis['indicators']['adr']['values']['adr']);
    }

    public function test_admin_web_forms_save_settings_instruments_and_generate_a_demo(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $this->actingAs($admin, 'web')->post('/admin/market-insight/settings', [
            'settings' => MarketInsightSettings::defaults(),
        ])->assertRedirect();
        $this->assertSame(2, MarketInsightSettings::current()['version']);
        $this->post('/admin/market-insight/instruments', [
            'symbol' => 'testusd', 'name' => 'Test Demo Pair', 'category' => 'other',
            'demo_base_price' => 10, 'session_calendar' => 'all_days', 'is_enabled' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('market_insight_instruments', ['symbol' => 'TESTUSD']);
        $this->post('/admin/market-insight/generate', [
            'symbol' => 'TESTUSD', 'timeframe' => 'H1', 'scenario' => 'sideways', 'seed' => 812,
        ])->assertRedirect();
        $this->get('/market-insight?symbol=TESTUSD&timeframe=H1')->assertOk()
            ->assertSee('Test Demo Pair')->assertSee('DEMO / SIMULATED DATA');
    }
}
