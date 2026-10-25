<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketAnalystAiReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'test-model');
    }

    private function fake(array $draft): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($draft, JSON_UNESCAPED_UNICODE)]]]]]])]);
    }

    private function seedVerifiedInsight(string $observedAt): void
    {
        $instrumentId = DB::table('market_insight_instruments')->where('symbol', 'XAUUSD')->value('id');
        $datasetId = DB::table('market_insight_datasets')->insertGetId([
            'instrument_id' => $instrumentId, 'timeframe' => 'H1', 'version' => 1,
            'scenario' => 'provider', 'seed' => 0, 'anchor_at' => $observedAt,
            'source' => 'REAL', 'provider' => 'twelvedata', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $result = [
            'status' => 'available', 'source' => 'REAL', 'is_mock_data' => false,
            'market_data_at' => $observedAt,
            'indicators' => [
                'rsi' => ['available' => true, 'classification' => 'Positive momentum', 'values' => ['rsi' => 52.35]],
                'ema' => ['available' => true, 'classification' => 'Bullish'],
            ],
        ];
        DB::table('market_insight_analyses')->insert([
            'dataset_id' => $datasetId, 'dataset_revision' => 1, 'instrument_id' => $instrumentId,
            'timeframe' => 'H1', 'configuration_version' => 1, 'engine_version' => 'test',
            'candle_at' => $observedAt, 'calculated_at' => now(), 'signal' => 'BUY',
            'directional_score' => 25, 'risk' => 'normal', 'result' => json_encode($result),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
        FeatureToggle::updateOrCreate(['feature_name' => 'tradinghub_ai_market_insight'], ['enabled' => true]);
        config()->set('market_data.mode', 'REAL');
    }

    public function test_fresh_verified_candle_results_can_support_rsi_generation(): void
    {
        $this->seedVerifiedInsight(now('UTC')->subMinutes(15)->format('Y-m-d H:i:s'));
        $this->fake(['fields' => ['rsi_level' => 'H1 RSI 52.35 indicates positive momentum.']]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));

        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'field', 'field' => 'rsi_level', 'market' => 'XAUUSD',
        ])->assertOk()->assertJsonPath('has_verified_market_data', true)
            ->assertJsonPath('verified_market_data.frames.H1.indicators.rsi.value', '52.35')
            ->assertJsonPath('fields.rsi_level', 'H1 RSI 52.35 indicates positive momentum.');
        Http::assertSentCount(1);
    }

    public function test_stale_verified_data_is_not_used_to_generate_rsi(): void
    {
        $this->seedVerifiedInsight(now('UTC')->subDay()->format('Y-m-d H:i:s'));
        Http::fake();
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));

        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'field', 'field' => 'rsi_level', 'market' => 'XAUUSD',
        ])->assertUnprocessable()->assertJsonValidationErrors('field');
        Http::assertNothingSent();
    }

    public function test_improving_existing_rsi_keeps_its_manual_reading(): void
    {
        $this->fake(['fields' => ['rsi_level' => 'RSI 55 shows improving momentum.']]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));

        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'improve', 'field' => 'rsi_level', 'market' => 'XAUUSD',
            'rsi_level' => 'RSI 55 suggests improving momentum.', 'improve_style' => 'clarity',
        ])->assertOk()->assertJsonPath('fields.rsi_level', 'RSI 55 shows improving momentum.');
    }

    public function test_single_field_draft_is_reviewable_and_does_not_write_a_report(): void
    {
        $this->fake(['fields' => ['market_overview' => '若缺少已验证价格，应等待结构确认。']]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'field', 'field' => 'market_overview', 'market' => 'XAUUSD', 'trend_structure' => 'uptrend',
        ])->assertOk()->assertJsonPath('fields.market_overview', '若缺少已验证价格，应等待结构确认。')
            ->assertJsonPath('requires_analyst_review', true)->assertJsonPath('plan', null);
        $this->assertDatabaseCount('market_analyses', 0);
        Http::assertSentCount(1);
    }

    public function test_generated_unverified_number_is_rejected(): void
    {
        $this->fake(['fields' => ['market_overview' => '现价：4600。']]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'field', 'field' => 'market_overview', 'market' => 'XAUUSD', 'trend_structure' => 'uptrend',
        ])->assertStatus(503);
    }

    public function test_fill_empty_requests_only_missing_eligible_fields(): void
    {
        $this->fake(['fields' => ['title' => 'XAUUSD 条件式分析']]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $this->postJson(route('market-analyst.ai-generate-report'), [
            'mode' => 'empty', 'market' => 'XAUUSD', 'trend_structure' => 'uptrend',
            'market_overview' => '手工市场概况', 'entry_zones_description' => '手工入场区域',
            'analyst_view' => '手工观点', 'strategy' => '手工策略', 'trading_plan' => '手工交易计划',
        ])->assertOk()->assertJsonPath('requested_fields', ['title'])
            ->assertJsonMissingPath('fields.market_overview');
        Http::assertSentCount(1);
    }

    public function test_full_report_uses_one_structured_request_with_conditional_plan(): void
    {
        $section = fn (array $fields) => $fields;
        $this->fake(['fields' => [
            'title' => 'XAUUSD 条件式分析', 'market_overview' => '缺少已验证行情，等待结构确认。',
            'entry_zones_description' => '若结构确认，再评估入场区域。',
            'analyst_view' => '暂时观望，等待有效确认。', 'strategy' => '若确认信号不足，应避免交易。',
        ], 'plan' => [
            'bias' => $section(['direction' => 'no_trade', 'summary' => '观望，等待确认。',
                'primaryStructure' => '缺少已验证结构，保持条件式判断。',
                'invalidationCondition' => '若结构发生变化，应重新评估。']),
            'planA' => $section(['entry' => '若趋势延续得到确认，再评估。', 'stopLoss' => '止损须依据确认后的实际结构。',
                'takeProfit' => '确认后再评估目标。', 'reason' => '缺少实时价格，暂不执行。']),
            'planB' => $section(['entry' => '若回调形成有效确认，再评估。', 'stopLoss' => '止损须依据回调结构。',
                'takeProfit' => '等待确认后再评估。', 'reason' => '回调方案需要进一步验证。']),
            'planC' => $section(['conditions' => '若证据不足，保持观望。', 'action' => '等待有效确认，不交易。']),
            'riskManagement' => $section(['maximumRisk' => '先确认账户风险上限。',
                'confirmationSignals' => '确认实际结构与收盘信号。', 'tradingSessionNotes' => '根据实际流动性选择时段。']),
            'numericLevels' => ['planA' => ['entry' => '', 'stopLoss' => '', 'takeProfit' => ''],
                'planB' => ['entry' => '', 'stopLoss' => '', 'takeProfit' => '']],
        ]]);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $this->postJson(route('market-analyst.ai-generate-report'), ['mode' => 'full', 'market' => 'XAUUSD'])
            ->assertOk()->assertJsonPath('plan.bias.direction', 'no_trade')
            ->assertJsonPath('has_verified_live_price', false);
        Http::assertSentCount(1);
    }

    public function test_section_generation_and_permissions_are_enforced(): void
    {
        $url = route('market-analyst.ai-generate-report');
        $this->postJson($url, ['mode' => 'section', 'section' => 'report'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => 700, 'status' => 1]))
            ->postJson($url, ['mode' => 'section', 'section' => 'report'])->assertForbidden();
        $this->fake(['fields' => ['title' => 'XAUUSD 条件分析']]);
        $this->actingAs(User::factory()->create(['role_id' => 501, 'status' => 1]))
            ->postJson($url, ['mode' => 'section', 'section' => 'report', 'market' => 'XAUUSD'])
            ->assertOk()->assertJsonPath('requested_fields', ['title']);
    }

    public function test_provider_timeout_and_invalid_json_leave_form_unchanged(): void
    {
        $url = route('market-analyst.ai-generate-report');
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $input = ['mode' => 'field', 'field' => 'market_overview', 'market' => 'XAUUSD'];
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->postJson($url, $input)->assertStatus(503);
        $invalid = ['candidates' => [['content' => ['parts' => [['text' => '{invalid']]]]]];
        Http::fake(['*' => Http::response($invalid)]);
        $this->postJson($url, $input)->assertStatus(503);
        $this->assertDatabaseCount('market_analyses', 0);
    }
}
