<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarketInsight\MarketDataSyncService;
use App\Services\MarketInsight\MarketInsightService;
use App\Services\MarketInsight\TwelveDataMarketDataProvider;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealMarketInsightTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_real_mode_with_missing_key_never_reads_a_mock_snapshot(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $instrument = app(MarketInsightService::class)->instrument('XAUUSD');
        app(MarketInsightService::class)->regenerate($instrument, 'H1', 'rising', 123, null, $admin->id);
        config(['market_data.mode' => 'REAL', 'market_data.api_key' => null]);
        Http::fake();

        $this->assertSame('NOT_CONFIGURED', app(MarketDataSyncService::class)->sync()['status']);
        $this->assertNull(app(MarketInsightService::class)->latest($instrument, 'H1'));
        $this->assertDatabaseCount('market_insight_quotes', 0);
        Http::assertNothingSent();

        Sanctum::actingAs($admin);
        $this->getJson('/api/mobile/v1/market-insights/latest?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertJsonPath('data.source', 'REAL')->assertJsonPath('data.analysis', null)
            ->assertJsonPath('data.status', 'NOT_CONFIGURED');
    }

    public function test_verified_real_candles_feed_all_six_indicators_without_an_incomplete_bar(): void
    {
        Carbon::setTestNow('2026-10-14 07:20:00 UTC');
        CarbonImmutable::setTestNow('2026-10-14 07:20:00 UTC');
        config(['market_data.mode' => 'REAL', 'market_data.api_key' => 'test-only-key',
            'market_data.symbol' => 'XAU/USD', 'market_data.distribution_allowed' => false]);
        $admin = User::factory()->create(['role_id' => 1]);
        $daily = $this->series('D1', '1day');
        $hourly = $this->series('H1', '1h');
        Http::fake(function ($request) use ($daily, $hourly) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/quote') {
                return Http::response(['symbol' => 'XAU/USD', 'name' => 'Gold Spot / US Dollar',
                    'close' => '4150.25000', 'last_quote_at' => CarbonImmutable::now('UTC')->timestamp,
                    'is_market_open' => true], 200);
            }
            $interval = $request['interval'];

            return Http::response($interval === '1day' ? $daily : $hourly, 200);
        });

        $first = app(MarketDataSyncService::class)->sync('H1', $admin->id, true);
        $this->assertSame('CONNECTED', $first['status']);
        $this->assertSame('available', $first['frames']['H1']['analysis_status']);
        $analysis = app(MarketInsightService::class)->latest(
            app(MarketInsightService::class)->instrument('XAUUSD'), 'H1');
        $this->assertSame('REAL', $analysis['source']);
        $this->assertFalse($analysis['is_mock_data']);
        $this->assertCount(6, $analysis['indicators']);
        $this->assertSame('2026-10-14 07:00:00', $analysis['market_data_at']);
        $this->assertGreaterThan(0, $analysis['indicators']['adr']['values']['adr']);
        $this->assertSame(0, $analysis['contributions']['atr']['points'] ?? 0);
        $this->assertSame(0, $analysis['contributions']['adr']['points'] ?? 0);
        $this->assertDatabaseCount('market_insight_quotes', 1);
        $this->assertSame(2, DB::table('market_insight_analyses')->count());
        $this->assertSame(0, DB::table('market_insight_candles')
            ->where('source', 'REAL')->where('close_at', '>', CarbonImmutable::now('UTC'))->count());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'apikey test-only-key') &&
            ! str_contains($request->url(), 'test-only-key'));

        $second = app(MarketDataSyncService::class)->sync('H1', $admin->id, true);
        $this->assertSame(0, $second['frames']['H1']['written']);
        $this->assertSame($first['frames']['H1']['analysis_id'], $second['frames']['H1']['analysis_id']);
        $this->assertSame(2, DB::table('market_insight_analyses')->count());
        $requestCount = Http::recorded()->count();
        $this->assertSame('IDLE', app(MarketDataSyncService::class)->sync('H1')['status']);
        $this->assertSame($requestCount, Http::recorded()->count());

        Carbon::setTestNow('2026-10-14 08:10:00 UTC');
        CarbonImmutable::setTestNow('2026-10-14 08:10:00 UTC');
        $scheduled = app(MarketDataSyncService::class)->sync('H1');
        $this->assertSame('CONNECTED', $scheduled['status']);
        $this->assertArrayHasKey('H1', $scheduled['frames']);
        $this->assertSame('2026-10-14 08:00:00', $scheduled['frames']['H1']['analysis_candle_at']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/mobile/v1/market-insights/latest?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertJsonPath('data.analysis.source', 'REAL')
            ->assertJsonPath('data.quote.price', '4150.25');
        $this->getJson('/api/mobile/v1/market-insights/candles?symbol=XAUUSD&timeframe=H1&limit=5')
            ->assertOk()->assertJsonCount(5, 'data.candles');
        $this->getJson('/api/mobile/v1/market-insights/history?symbol=XAUUSD&timeframe=H1&page=2')
            ->assertOk()->assertJsonPath('data.page', 2)->assertJsonCount(0, 'data.history');
        $this->getJson('/api/mobile/v1/market-insights/admin/provider')
            ->assertOk()->assertDontSee('test-only-key');
        $this->actingAs($admin, 'web')->get('/market-insight?symbol=XAUUSD&timeframe=H1')
            ->assertOk()->assertSee('REAL DATA')->assertSee('Verified XAUUSD candlestick chart');

        $trader = User::factory()->create(['role_id' => 760]);
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/market-insights/latest?symbol=XAUUSD&timeframe=H1')->assertForbidden();
    }

    public function test_rate_limit_preserves_last_verified_data_and_never_falls_back(): void
    {
        config(['market_data.mode' => 'REAL', 'market_data.api_key' => 'test-only-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(
            ['status' => 'error', 'code' => 429, 'message' => 'Limit reached'], 429)]);
        $result = app(MarketDataSyncService::class)->sync(null, null, true);

        $this->assertSame('RATE_LIMITED', $result['status']);
        $this->assertCount(1, Http::recorded());
        $this->assertDatabaseCount('market_insight_datasets', 0);
        $this->assertDatabaseCount('market_insight_quotes', 0);
        $this->assertDatabaseHas('market_insight_provider_status', ['status' => 'RATE_LIMITED']);
        $this->assertDatabaseHas('market_insight_execution_logs', ['type' => 'sync', 'status' => 'failed']);
        $this->assertStringNotContainsString('test-only-key', (string) DB::table('market_insight_execution_logs')
            ->orderByDesc('id')->value('details'));
    }

    public function test_invalid_credential_is_reported_without_leaking_it(): void
    {
        config(['market_data.mode' => 'REAL', 'market_data.api_key' => 'test-only-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(
            ['status' => 'error', 'code' => 401, 'message' => 'Invalid key'], 401)]);

        $result = app(MarketDataSyncService::class)->sync(null, null, true);
        $this->assertSame('AUTH_ERROR', $result['status']);
        $this->assertStringNotContainsString('test-only-key', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertDatabaseCount('market_insight_quotes', 0);
    }

    public function test_provider_discards_a_repeated_daily_label_and_respects_short_h4_session(): void
    {
        CarbonImmutable::setTestNow('2026-10-14 12:00:00 UTC');
        config(['market_data.mode' => 'REAL', 'market_data.api_key' => 'test-only-key']);
        $daily = $this->series('D1', '1day');
        $duplicate = $daily['values'][100];
        $duplicate['close'] = number_format((float) $duplicate['close'] + 0.1, 5, '.', '');
        array_splice($daily['values'], 101, 0, [$duplicate]);
        $h4 = ['status' => 'ok', 'meta' => ['symbol' => 'XAU/USD', 'interval' => '4h'],
            'values' => [
                ['datetime' => '2026-10-14 00:00:00', 'open' => '4000', 'high' => '4002', 'low' => '3998', 'close' => '4001'],
                ['datetime' => '2026-10-14 02:00:00', 'open' => '4001', 'high' => '4003', 'low' => '3999', 'close' => '4002'],
                ['datetime' => '2026-10-14 06:00:00', 'open' => '4002', 'high' => '4004', 'low' => '4000', 'close' => '4003'],
            ]];
        Http::fake(function ($request) use ($daily, $h4) {
            return Http::response($request['interval'] === '1day' ? $daily : $h4, 200);
        });
        $provider = app(TwelveDataMarketDataProvider::class);
        $candles = $provider->candles('D1', 310);
        $this->assertSame(1, $provider->duplicatesDiscarded());
        $this->assertCount(304, $candles);
        $short = $provider->candles('H4', 3);
        $this->assertSame('2026-10-14 02:00:00', $short[0]['close_at']);
        $this->assertCount(3, $short);
    }

    private function series(string $timeframe, string $interval): array
    {
        $latest = $timeframe === 'D1'
            ? CarbonImmutable::parse('2026-10-14 00:00:00', 'Australia/Sydney')
            : CarbonImmutable::parse('2026-10-14 07:00:00', 'UTC');
        $values = [];
        for ($i = 304; $i >= 0; $i--) {
            $at = $timeframe === 'D1' ? $latest->subDays($i) : $latest->subHours($i);
            $base = 4000 + (304 - $i) * 0.5;
            $values[] = ['datetime' => $at->format($timeframe === 'D1' ? 'Y-m-d' : 'Y-m-d H:i:s'),
                'open' => number_format($base, 5, '.', ''),
                'high' => number_format($base + 2, 5, '.', ''),
                'low' => number_format($base - 2, 5, '.', ''),
                'close' => number_format($base + 0.5, 5, '.', '')];
        }

        return ['status' => 'ok', 'meta' => ['symbol' => 'XAU/USD', 'interval' => $interval], 'values' => $values];
    }
}
