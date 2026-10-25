<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\Community;
use App\Models\MarketAnalysis;
use App\Models\User;
use App\Services\TradingPlanGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class MarketAnalystAiPlanTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeResponsePlan = [];
    private bool $httpFaked = false;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'test-model');
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
    }

    private function input(string $trend = 'uptrend'): array
    {
        return [
            'market' => 'XAUUSD',
            'trend_structure' => $trend,
            'trend_strength' => '',
            'rsi_level' => 'RSI 位于中位偏上，多头动能占优。',
            'order_block' => '下方支撑区存在多头 OB/FVG，边界未提供。',
            'market_overview' => '市场维持多头结构，趋势强度中等。',
            'supports' => '4461, 4410, 4361',
            'resistances' => '4600, 4670, 4750',
            'key_zones' => "关键阻力区：4600, 4670, 4750\n关键支撑区：4461, 4410, 4361",
            'entry_zones_description' => '观察关键区域反应。',
            'analyst_view' => '等待确认。',
            'strategy' => '优先顺势。',
            'chart_signals' => '等待收盘确认。',
        ];
    }

    private function plan(string $direction = 'bullish'): array
    {
        $bearish = $direction === 'bearish';
        $neutral = in_array($direction, ['neutral', 'no_trade'], true);
        return [
            'bias' => [
                'direction' => $direction,
                'summary' => $neutral ? '中性，等待方向确认。' : ($bearish ? '偏空，等待空头确认。' : '偏多，等待多头确认。'),
                'primaryStructure' => $neutral ? '区间结构尚无方向。' : ($bearish ? '高低点下移，4600 为阻力，4461 为支撑。' : '高低点上移，4461 为支撑，4600 为阻力。'),
                'invalidationCondition' => $neutral ? '若区间结构改变，重新评估。' : ($bearish ? '若有效突破 4600 并确认，则空头观点失效。' : '若有效跌破 4461 并确认，则多头观点失效。'),
            ],
            'planA' => [
                'entry' => $neutral ? '若方向和动能未确认，等待，不执行。' : ($bearish ? '若跌破 4461 并回测确认，才考虑顺势做空。' : '若突破 4600 并回测确认，才考虑顺势做多。'),
                'stopLoss' => '止损须依据确认后的实际结构设置，目前不能确定精确价格。',
                'takeProfit' => $neutral ? '等待方向确认后再评估目标。' : ($bearish ? '确认后观察 4410，再评估 4361。' : '确认后观察 4670，再评估 4750。'),
                'reason' => $neutral ? '动能混合，不应强迫进场。' : '结构与所述动能一致，但需要价格确认。',
            ],
            'planB' => [
                'entry' => $neutral ? '若边界反应和方向未确认，继续等待。' : ($bearish ? '若反弹至 4600 并出现承压确认，才考虑回调做空。' : '若回调至 4461 并出现支撑确认，才考虑回调做多。'),
                'stopLoss' => '止损须依据实际回调结构与确认信号设置。',
                'takeProfit' => $neutral ? '未确认突破前不设固定目标。' : ($bearish ? '确认后观察 4461。' : '确认后观察 4600。'),
                'reason' => '此方案需区域反应和收盘确认。',
            ],
            'planC' => [
                'conditions' => '若突破失败、动能冲突或缺少实时价格与收盘确认，则拒绝进场。',
                'action' => '保持观望，等待下一次有效确认。',
            ],
            'riskManagement' => [
                'maximumRisk' => '请先确认账户既定风险上限；未配置时不得计算仓位。',
                'confirmationSignals' => '确认结构、关键位反应与收盘信号。',
                'tradingSessionNotes' => '根据交易品种实际流动性选择时段，未核实日历前不指定事件。',
            ],
            'numericLevels' => [
                'planA' => ['entry' => '', 'stopLoss' => '', 'takeProfit' => ''],
                'planB' => ['entry' => '', 'stopLoss' => '', 'takeProfit' => ''],
            ],
        ];
    }

    private function fakePlan(array $plan): void
    {
        $this->fakeResponsePlan = $plan;
        if (! $this->httpFaked) {
            Http::fake(function () {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => json_encode($this->fakeResponsePlan, JSON_UNESCAPED_UNICODE)]]]]],
                ]);
            });
            $this->httpFaked = true;
        }
    }

    public function test_unsaved_bullish_form_generates_complete_reviewable_plan_without_creating_record(): void
    {
        $this->fakePlan($this->plan());
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $response = $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('plan.bias.direction', 'bullish')
            ->assertJsonPath('validation_status', 'passed')
            ->assertJsonPath('requires_analyst_review', true)
            ->assertJsonPath('has_verified_live_price', false)
            ->assertJsonCount(5, 'plan')
            ->assertJsonCount(4, 'plan.planA');
        $this->assertStringContainsString('趋势强度未选择', implode(' ', $response->json('warnings')));
        $this->assertStringContainsString('实时价格', implode(' ', $response->json('warnings')));
        $this->assertSame(0, MarketAnalysis::count());
        Http::assertSent(function ($request) {
            $content = json_decode($request['contents'][0]['parts'][0]['text'], true);
            return $content['source']['supports'] === ['4461', '4410', '4361']
                && $content['source']['resistances'] === ['4600', '4670', '4750']
                && $content['source']['selectedTrendStrength'] === null
                && $content['source']['narrativeTrendStrength'] === 'medium'
                && $content['source']['verifiedCurrentPrice'] === null
                && $request['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_bearish_and_neutral_trends_are_not_forced_bullish(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => 501, 'status' => 1]));
        $this->fakePlan($this->plan('bearish'));
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), array_replace($this->input('downtrend'), [
            'rsi_level' => 'RSI 动能偏空。', 'market_overview' => '高低点持续下移。',
        ]))->assertOk()->assertJsonPath('plan.bias.direction', 'bearish')
            ->assertJsonPath('plan.planB.entry', '若反弹至 4600 并出现承压确认，才考虑回调做空。');

        $this->fakePlan($this->plan('neutral'));
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), array_replace($this->input('ranging'), [
            'rsi_level' => '动能混合。', 'market_overview' => '市场震荡，方向未定。',
        ]))->assertOk()->assertJsonPath('plan.bias.direction', 'neutral')
            ->assertJsonPath('plan.planC.action', '保持观望，等待下一次有效确认。');
    }

    public function test_section_generation_returns_only_requested_section_and_missing_levels_stay_conditional(): void
    {
        $plan = $this->plan();
        $plan['planB']['entry'] = '若回调区域形成明确支撑确认，才考虑做多，当前无可核验的支撑价格。';
        $plan['planB']['takeProfit'] = '确认后再根据实际阻力区域评估目标。';
        $this->fakePlan(array_intersect_key($plan, array_flip(['planB', 'numericLevels'])));
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $input = $this->input();
        $input['supports'] = '';
        $input['key_zones'] = '';
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $input + ['section' => 'planB'])
            ->assertOk()->assertJsonCount(1, 'plan')
            ->assertJsonMissingPath('plan.planA')
            ->assertJsonPath('plan.planB.entry', '若回调区域形成明确支撑确认，才考虑做多，当前无可核验的支撑价格。');
    }

    public function test_support_and_resistance_are_separated_when_key_zones_share_one_line(): void
    {
        $this->fakePlan($this->plan());
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $input = $this->input();
        $input['supports'] = '';
        $input['resistances'] = '';
        $input['key_zones'] = '关键阻力区：4600, 4670, 4750；关键支撑区：4461, 4410, 4361';
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $input)->assertOk();
        Http::assertSent(function ($request) {
            $source = json_decode($request['contents'][0]['parts'][0]['text'], true)['source'];
            return $source['supports'] === ['4461', '4410', '4361']
                && $source['resistances'] === ['4600', '4670', '4750'];
        });
    }

    public function test_invalid_ai_levels_and_live_price_claims_are_rejected_without_writes(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        $plan = $this->plan();
        $plan['planA']['entry'] = '若突破 4601 并确认，才考虑做多。';
        $this->fakePlan($plan);
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())->assertStatus(503);

        $plan = $this->plan();
        $plan['numericLevels']['planA'] = ['entry' => '4600', 'stopLoss' => '4670', 'takeProfit' => '4750'];
        $this->fakePlan($plan);
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())->assertStatus(503);

        $plan = $this->plan();
        $plan['planA']['entry'] = '现价：4600，若确认后做多。';
        $this->fakePlan($plan);
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())->assertStatus(503);

        $plan = $this->plan();
        $plan['planA']['entry'] = '若突破 4600，进场价：4600。';
        $this->fakePlan($plan);
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())->assertStatus(503);

        $plan = $this->plan();
        $plan['planB']['entry'] = '若回调至 4600 并确认，才考虑做多。';
        $this->fakePlan($plan);
        $this->postJson(route('market-analyst.ai-generate-trading-plan'), $this->input())->assertStatus(503);
        $this->assertSame(0, MarketAnalysis::count());
    }

    public function test_permissions_module_gate_configuration_and_provider_failure(): void
    {
        $url = route('market-analyst.ai-generate-trading-plan');
        $this->postJson($url, $this->input())->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => 700, 'status' => 1]))
            ->postJson($url, $this->input())->assertForbidden();

        $this->actingAs(User::factory()->create(['role_id' => 1, 'status' => 1]));
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        $this->postJson($url, $this->input())->assertNotFound();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);

        config()->set('services.gemini.key', '');
        $this->postJson($url, $this->input())->assertStatus(503);
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->postJson($url, $this->input())->assertStatus(503)
            ->assertJsonPath('message', 'AI generation is temporarily unavailable. Your form has not changed. Please retry.');
    }

    public function test_numeric_relationships_validate_long_and_short_independently(): void
    {
        $service = app(TradingPlanGenerationService::class);
        $service->validateNumericRelationships('uptrend', ['entry' => '4600', 'stopLoss' => '4461', 'takeProfit' => '4670']);
        $service->validateNumericRelationships('downtrend', ['entry' => '4461', 'stopLoss' => '4600', 'takeProfit' => '4410']);
        $this->expectException(UnexpectedValueException::class);
        $service->validateNumericRelationships('downtrend', ['entry' => '4461', 'stopLoss' => '4410', 'takeProfit' => '4600']);
    }

    public function test_create_and_edit_forms_offer_generation_while_existing_save_flow_stays_draft(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $this->actingAs($admin)->get(route('market-analyst.create'))->assertOk()
            ->assertSee('AI 自动生成交易计划')
            ->assertSee('market-analyst-ai-plan.js');

        $this->post(route('market-analyst.store'), [
            'community_id' => $community->id,
            'title' => 'Draft outlook',
            'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(),
            'trend_structure' => 'uptrend',
            'market_overview' => 'Manual market overview',
            'trading_plan' => "【AI交易执行计划】\n方向 Bias：偏多，等待确认。",
        ])->assertRedirect(route('market-analyst.index'));
        $outlook = MarketAnalysis::sole();
        $this->assertSame('draft', $outlook->publication_status);
        $this->assertStringContainsString('方向 Bias：偏多', $outlook->trading_plan);
        $this->get(route('market-analyst.edit', $outlook->id))->assertOk()
            ->assertSee('AI 自动生成交易计划');

        $this->post(route('market-analyst.update', $outlook->id), [
            'community_id' => $community->id,
            'title' => 'Updated draft outlook',
            'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(),
            'trend_structure' => 'uptrend',
            'market_overview' => 'Revised market overview',
            'trading_plan' => "【AI交易执行计划】\n方向 Bias：更新后的偏多观点。",
        ])->assertRedirect(route('market-analyst.index'));
        $outlook->refresh();
        $this->assertSame('draft', $outlook->publication_status);
        $this->assertStringContainsString('更新后的偏多观点', $outlook->trading_plan);
    }
}
