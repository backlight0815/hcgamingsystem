<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeBase;
use App\Models\Community;
use App\Models\MarketAnalysis;
use App\Models\User;
use App\Services\TradingHubAssistantService;
use App\Models\FeatureToggle;
use App\Support\TradingSubmodules;
use Database\Seeders\TradingCoachKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradingHubAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_trading_account_cannot_use_assistant(): void
    {
        $this->getJson(route('ai.assistant.preferences'))->assertUnauthorized();
        $dealer = User::factory()->create(['role_id' => 700, 'status' => 1]);
        $this->actingAs($dealer)->getJson(route('ai.assistant.preferences'))->assertForbidden();
    }

    public function test_conversations_and_preferences_are_isolated_and_risk_is_capped(): void
    {
        $a = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $b = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $id = $this->actingAs($a)->postJson(route('ai.assistant.conversations.create'))->assertCreated()->json('id');
        $this->actingAs($b)->getJson(route('ai.assistant.messages', $id))->assertNotFound();
        $this->patchJson(route('ai.assistant.rename', $id), ['title' => 'Stolen'])->assertNotFound();
        $this->putJson(route('ai.assistant.preferences.update'), ['experience' => 'advanced',
            'style' => 'swing', 'timeframe' => 'H1', 'risk_percent' => 1.1,
            'answer_detail' => 'standard'])->assertUnprocessable();
        $this->getJson(route('ai.assistant.preferences'))->assertJsonPath('risk_percent', 0.5);
        $this->actingAs($a)->putJson(route('ai.assistant.preferences.update'), [
            'experience' => 'intermediate', 'style' => 'intraday', 'timeframe' => 'M15',
            'risk_percent' => 0.25, 'maximum_risk_percent' => 0.5, 'preferred_rr' => 2.5,
            'preferred_instruments' => 'XAUUSD, EURUSD', 'language' => 'en', 'answer_detail' => 'standard',
        ])->assertOk()->assertJsonPath('maximum_risk_percent', 0.5);
        $this->postJson(route('ai.assistant.risk'), ['equity' => 10000, 'risk_percent' => .75])->assertUnprocessable();
    }

    public function test_only_active_curated_knowledge_is_retrieved(): void
    {
        $this->seed(TradingCoachKnowledgeSeeder::class);
        AiKnowledgeBase::create(['module' => AiKnowledgeBase::MODULE_MARKET_STRUCTURE,
            'title' => 'Secret trendline instruction', 'content' => 'Unpublished content', 'status' => 'inactive']);
        $rows = app(TradingHubAssistantService::class)->retrieve('Explain trendline confirmation');
        $this->assertTrue($rows->contains('title', 'Trendlines and Confluence'));
        $this->assertFalse($rows->contains('title', 'Secret trendline instruction'));
        $this->assertLessThanOrEqual(4, $rows->count());
    }

    public function test_answer_uses_only_published_outlook_with_timestamp(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Historical outlook only. H4 coverage is missing.']]]]]], 200)]);
        $user = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        MarketAnalysis::create(['community_id' => $community->id, 'title' => 'Public gold view',
            'market' => 'XAUUSD', 'analysis_date' => now()->toDateString(), 'publication_status' => 'published',
            'published_at' => now()->subHour(), 'market_overview' => 'A published observation']);
        MarketAnalysis::create(['community_id' => $community->id, 'title' => 'Secret gold view',
            'market' => 'XAUUSD', 'analysis_date' => now()->toDateString(), 'publication_status' => 'draft',
            'market_overview' => 'Private draft']);
        $response = $this->actingAs($user)->postJson(route('ai.assistant.ask'), ['message' => 'Explain XAUUSD structure']);
        $response->assertOk()->assertJsonPath('market.title', 'Public gold view');
        Http::assertSent(fn ($request) => str_contains($request->body(), 'Public gold view')
            && ! str_contains($request->body(), 'Secret gold view'));
    }

    public function test_module_switch_and_admin_settings_keep_risk_conservative(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $member = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $this->actingAs($member)->get(route('admin.ai_settings.index'))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.ai_settings.update'), [
            'default_risk_percent' => .25, 'maximum_risk_percent' => .5, 'minimum_rr' => 2.5,
            'daily_request_limit' => 5, 'retrieval_limit' => 3,
            'retention_days' => 90,
            'model' => config('services.gemini.model'),
        ])->assertRedirect();
        $this->postJson(route('ai.assistant.risk'), ['equity' => 10000, 'risk_percent' => .75])
            ->assertUnprocessable();
        $this->postJson(route('ai.assistant.risk'), ['equity' => 10000])
            ->assertOk()->assertJsonPath('risk_budget', 25);
        FeatureToggle::updateOrCreate(['feature_name' => TradingSubmodules::featureName('ai_assistant')], ['enabled' => false]);
        $this->getJson(route('ai.assistant.preferences'))->assertStatus(503)->assertJsonPath('code', 'MODULE_DISABLED');
    }

    public function test_feedback_cannot_reference_another_members_message(): void
    {
        $a = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $b = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $id = $this->actingAs($a)->postJson(route('ai.assistant.conversations.create'))->json('id');
        $message = \Illuminate\Support\Facades\DB::table('ai_chat_messages')->insertGetId([
            'conversation_id' => $id, 'role' => 'assistant', 'content' => 'Educational answer',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($b)->postJson(route('ai.assistant.feedback'), [
            'message_id' => $message, 'rating' => 'helpful',
        ])->assertNotFound();
        $this->actingAs($a)->postJson(route('ai.assistant.feedback'), [
            'message_id' => $message, 'rating' => 'helpful',
        ])->assertOk();
    }

    public function test_knowledge_edits_keep_a_previous_version_and_unpublish_preserves_record(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $row = AiKnowledgeBase::create(['module' => AiKnowledgeBase::MODULE_MARKET_STRUCTURE,
            'title' => 'Protected swings', 'content' => 'Original approved explanation',
            'status' => 'active', 'source' => 'HC editorial']);
        $this->actingAs($admin)->put(route('admin.ai_knowledge_base.update', $row->id), [
            'module' => AiKnowledgeBase::MODULE_MARKET_STRUCTURE, 'title' => 'Protected swings',
            'content' => 'Revised approved explanation', 'status' => 'active',
            'source' => 'HC review', 'difficulty' => 'intermediate', 'language' => 'en',
        ])->assertRedirect();
        $this->assertDatabaseHas('ai_knowledge_versions', ['knowledge_id' => $row->id,
            'version' => 1, 'content' => 'Original approved explanation']);
        $this->delete(route('admin.ai_knowledge_base.destroy', $row->id))->assertRedirect();
        $this->assertDatabaseHas('ai_knowledge_bases', ['id' => $row->id, 'status' => 'inactive', 'version' => 2]);
    }

    public function test_workspace_renders_and_live_quote_and_excess_risk_are_guarded_before_provider(): void
    {
        Http::fake();
        $user = User::factory()->create(['role_id' => 501, 'status' => 1]);
        $this->actingAs($user)->get(route('ai.assistant.index'))->assertOk()
            ->assertSee('Your Trading Knowledge & Market Structure Assistant', false)
            ->assertSee('Risk worksheet');
        $this->postJson(route('ai.assistant.ask'), ['message' => 'What is the current price of XAUUSD?'])
            ->assertOk()->assertJsonPath('intent', 'live_quote_unavailable');
        $this->postJson(route('ai.assistant.ask'), ['message' => 'Can I risk 5% per idea?'])
            ->assertOk()->assertJsonPath('intent', 'risk_limit');
        $this->postJson(route('ai.assistant.ask'), ['message' => 'Should I increase my lot size to recover losses?'])
            ->assertOk()->assertJsonPath('intent', 'loss_chasing');
        $this->postJson(route('ai.assistant.ask'), ['message' => 'Analyze EURUSD today'])
            ->assertOk()->assertJsonPath('intent', 'market_data_unavailable');
        Http::assertNothingSent();
    }
}
