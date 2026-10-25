<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeBase;
use App\Models\User;
use Database\Seeders\TradingCoachKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradingAiTrainingTest extends TestCase
{
    use RefreshDatabase;

    private function trainingData(string $question = 'What is Break Even?'): array
    {
        return ['question' => $question,
            'answer' => 'Break Even moves the stop toward entry. Costs and slippage can still cause a loss.',
            'alternative_questions' => "What is BE?\nExplain break even",
            'keywords' => 'break even, stop loss', 'module' => AiKnowledgeBase::MODULE_TRADE_MANAGEMENT,
            'language' => 'en', 'priority' => 50];
    }

    private function member(): User
    {
        return User::factory()->create(['role_id' => 501, 'status' => 1]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => 1, 'status' => 1]);
    }

    public function test_question_is_saved_before_provider_failure_and_remains_in_history(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*generateContent' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        $member = $this->member();
        $clientId = (string) Str::uuid();
        $response = $this->actingAs($member)->postJson(route('ai.assistant.ask'), [
            'message' => 'How can I manage five positions together?', 'client_message_id' => $clientId,
        ])->assertOk()->assertJsonPath('unanswered', true)
            ->assertJsonPath('provider_failure_code', 'provider_server');
        $conversation = $response->json('conversation_id');
        $this->assertDatabaseHas('ai_chat_messages', ['id' => $response->json('question_id'),
            'client_message_id' => $clientId, 'role' => 'user', 'status' => 'unanswered']);
        $this->assertDatabaseHas('ai_unanswered_questions', ['frequency' => 1, 'status' => 'pending']);
        $this->getJson(route('ai.assistant.messages', $conversation))->assertOk()->assertJsonCount(2, 'data');
        $this->postJson(route('ai.assistant.ask'), [
            'message' => 'How can I manage five positions together?', 'client_message_id' => $clientId,
        ])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('question_id', $response->json('question_id'));
        $this->assertSame(2, DB::table('ai_chat_messages')->where('conversation_id', $conversation)->count());
        $this->assertSame(1, DB::table('ai_unanswered_questions')->first()->frequency);
    }

    public function test_queue_failure_does_not_claim_review_recording_or_erase_saved_question(): void
    {
        Http::fake();
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        Schema::drop('ai_unanswered_questions');
        $response = $this->actingAs($this->member())->postJson(route('ai.assistant.ask'), [
            'message' => 'A question absent from approved knowledge',
        ])->assertOk()->assertJsonPath('unanswered', true);
        $this->assertStringContainsString('review queue could not be updated', $response->json('answer'));
        $this->assertStringNotContainsString('recorded for our team', $response->json('answer'));
        $this->assertDatabaseHas('ai_chat_messages', ['id' => $response->json('question_id'),
            'role' => 'user', 'status' => 'unanswered']);
        Http::assertNothingSent();
    }

    public function test_training_rejects_punctuation_only_or_normalized_duplicate_variations(): void
    {
        $this->actingAs($this->admin());
        $data = $this->trainingData();
        $data['alternative_questions'] = '???';
        $this->post(route('admin.ai_training.store'), $data)->assertSessionHasErrors('question');
        $data['alternative_questions'] = "What is BE?\nwhat is be!!!";
        $this->post(route('admin.ai_training.store'), $data)->assertSessionHasErrors('question');
        $this->assertDatabaseCount('ai_knowledge_bases', 0);
    }

    public function test_future_effective_date_uses_malaysia_time_and_delays_public_answer(): void
    {
        Http::fake();
        $localDate = now('Asia/Kuala_Lumpur')->addDay()->format('Y-m-d\TH:i');
        $data = $this->trainingData();
        $data['effective_at'] = $localDate;
        $this->actingAs($this->admin())->post(route('admin.ai_training.store'), $data)->assertRedirect();
        $entry = AiKnowledgeBase::where('entry_type', 'faq')->firstOrFail();
        $this->assertSame(\Carbon\Carbon::parse($localDate, 'Asia/Kuala_Lumpur')->utc()->format('Y-m-d H:i'),
            $entry->effective_at->format('Y-m-d H:i'));
        foreach (['submit', 'approve', 'publish'] as $action) {
            $this->post(route('admin.ai_training.transition', $entry), ['action' => $action])->assertRedirect();
        }
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->member())->postJson(route('ai.assistant.ask'), [
            'message' => 'What is BE?',
        ])->assertOk()->assertJsonPath('unanswered', true);
        Http::assertNothingSent();
    }

    public function test_admin_training_saves_then_publishes_exact_answer_without_provider(): void
    {
        Http::fake();
        $admin = $this->admin();
        $member = $this->member();
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->post(route('admin.ai_training.store'), $this->trainingData())->assertRedirect();
        $entry = AiKnowledgeBase::where('entry_type', 'faq')->firstOrFail();
        $this->assertSame('draft', $entry->status);
        $this->get(route('admin.ai_training.edit', $entry))->assertOk()->assertSee('What is Break Even?');
        $this->actingAs($member)->postJson(route('ai.assistant.ask'), ['message' => 'What is BE?'])
            ->assertOk()->assertJsonPath('unanswered', true);
        $this->actingAs($admin)->post(route('admin.ai_training.transition', $entry), ['action' => 'submit'])->assertRedirect();
        $this->post(route('admin.ai_training.transition', $entry), ['action' => 'approve'])->assertRedirect();
        $this->post(route('admin.ai_training.transition', $entry), ['action' => 'publish'])->assertRedirect();
        $answer = $this->actingAs($member)->postJson(route('ai.assistant.ask'), ['message' => 'What is BE?'])
            ->assertOk()->assertJsonPath('response_source', 'approved_faq');
        $this->assertSame($entry->content, $answer->json('answer'));
        $this->assertDatabaseHas('ai_chat_messages', ['id' => $answer->json('message_id'),
            'response_source' => 'approved_faq']);
        Http::assertNothingSent();
        $this->assertDatabaseHas('ai_unanswered_questions', ['training_id' => $entry->id, 'status' => 'resolved']);
    }

    public function test_published_faq_answers_in_hybrid_mode_even_when_provider_would_fail(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*generateContent' => Http::response([], 503)]);
        AiKnowledgeBase::create(['entry_type' => 'faq', 'module' => 'trade_management',
            'title' => 'What is Break Even?',
            'normalized_question' => AiKnowledgeBase::normalizeQuestion('What is Break Even?'),
            'alternative_questions' => ['What is BE?'], 'alternative_normalized' => ['what is be'],
            'content' => 'The approved break even answer.', 'status' => 'published',
            'approved_at' => now(), 'language' => 'en']);
        $this->actingAs($this->member())->postJson(route('ai.assistant.ask'), ['message' => 'What is BE?'])
            ->assertOk()->assertJsonPath('answer', 'The approved break even answer.')
            ->assertJsonPath('response_source', 'approved_faq');
        Http::assertNothingSent();
    }

    public function test_configured_language_fallback_is_labeled(): void
    {
        Http::fake();
        AiKnowledgeBase::create(['entry_type' => 'faq', 'module' => 'trade_management',
            'title' => 'What is Break Even?',
            'normalized_question' => AiKnowledgeBase::normalizeQuestion('What is Break Even?'),
            'content' => 'The approved English answer.', 'status' => 'published',
            'approved_at' => now(), 'language' => 'en']);
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        $member = $this->member();
        $this->actingAs($member)->putJson(route('ai.assistant.preferences.update'), [
            'experience' => 'beginner', 'style' => 'intraday', 'timeframe' => 'M15',
            'risk_percent' => .5, 'answer_detail' => 'standard', 'language' => 'zh',
        ])->assertOk();
        $this->postJson(route('ai.assistant.ask'), ['message' => 'What is Break Even?'])
            ->assertOk()->assertJsonPath('answer', 'Approved answer available in English: The approved English answer.');
    }

    public function test_edit_requires_reapproval_and_versions_can_be_restored_as_draft(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.ai_training.store'), $this->trainingData())->assertRedirect();
        $entry = AiKnowledgeBase::where('entry_type', 'faq')->firstOrFail();
        foreach (['submit', 'approve', 'publish'] as $action) {
            $this->post(route('admin.ai_training.transition', $entry), ['action' => $action])->assertRedirect();
        }
        $changed = $this->trainingData();
        $changed['answer'] = 'Updated reviewed explanation with execution costs.';
        $this->put(route('admin.ai_training.update', $entry), $changed)->assertRedirect();
        $entry->refresh();
        $this->assertSame('draft', $entry->status);
        $this->assertNull($entry->approved_at);
        $this->assertDatabaseHas('ai_knowledge_versions', ['knowledge_id' => $entry->id,
            'content' => $this->trainingData()['answer']]);
        $this->post(route('admin.ai_training.rollback', [$entry, $entry->version - 1]))->assertRedirect();
        $this->assertSame($this->trainingData()['answer'], $entry->fresh()->content);
        $this->assertSame('draft', $entry->fresh()->status);
    }

    public function test_duplicate_training_and_customer_access_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.ai_training.store'), $this->trainingData())->assertRedirect();
        $this->post(route('admin.ai_training.store'), $this->trainingData())
            ->assertSessionHasErrors('question');
        $member = $this->member();
        $this->actingAs($member)->get(route('admin.ai_training.index'))->assertForbidden();
        $this->post(route('admin.ai_training.store'), $this->trainingData())->assertForbidden();
        $this->put(route('admin.ai_settings.update'), ['operation_mode' => 'knowledge_only'])->assertForbidden();
    }

    public function test_admin_pages_render_and_response_rules_persist(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.ai_training.index'))->assertOk()->assertSee('AI Training');
        $this->get(route('admin.ai_training.index', ['tab' => 'analytics']))->assertOk();
        $this->get(route('admin.ai_training.index', ['tab' => 'unanswered']))->assertOk();
        $this->get(route('admin.ai_training.create'))->assertOk()->assertSee('Customer question');
        $this->get(route('admin.ai_settings.index'))->assertOk()->assertSee('Response Rules');
        $this->put(route('admin.ai_settings.update'), [
            'default_risk_percent' => .5, 'maximum_risk_percent' => 1,
            'minimum_rr' => 2, 'daily_request_limit' => 100, 'retrieval_limit' => 4,
            'retention_days' => 90, 'model' => config('services.gemini.model'),
            'operation_mode' => 'knowledge_only', 'external_enabled' => 0,
            'ai_name' => 'HC Coach', 'ai_role' => 'Trading educator',
            'tone' => 'friendly', 'response_length' => 'short',
            'language_fallback' => 'none', 'response_rules' => 'Ask for clarification when uncertain.',
        ])->assertRedirect();
        $this->assertDatabaseHas('ai_chat_response_settings', ['key' => 'response_rules',
            'value' => 'Ask for clarification when uncertain.']);
        $this->get(route('admin.ai_settings.index'))->assertSee('Ask for clarification when uncertain.');
    }

    public function test_weak_keyword_overlap_does_not_return_an_unrelated_answer(): void
    {
        Http::fake();
        $member = $this->member();
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        AiKnowledgeBase::create(['entry_type' => 'faq', 'module' => 'trade_management',
            'title' => 'How do I move one stop loss?',
            'normalized_question' => AiKnowledgeBase::normalizeQuestion('How do I move one stop loss?'),
            'content' => 'Single position instructions', 'status' => 'published',
            'approved_at' => now(), 'language' => 'en']);
        $this->actingAs($member)->postJson(route('ai.assistant.ask'), [
            'message' => 'How do I move the stop losses for five positions?',
        ])->assertOk()->assertJsonPath('unanswered', true)->assertJsonPath('response_source', 'fallback');
        Http::assertNothingSent();
    }

    public function test_general_break_even_article_does_not_answer_multiple_position_operation(): void
    {
        Http::fake();
        $this->seed(TradingCoachKnowledgeSeeder::class);
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->member())->postJson(route('ai.assistant.ask'), [
            'message' => 'How do I set Break Even for five XAUUSD positions at the same time?',
        ])->assertOk()->assertJsonPath('unanswered', true);
        Http::assertNothingSent();
    }

    public function test_conversation_history_is_private_and_module_scoped(): void
    {
        $one = $this->member();
        $two = $this->member();
        $conversation = $this->actingAs($one)->postJson(route('ai.assistant.conversations.create'))
            ->assertCreated()->json('id');
        $this->actingAs($two)->getJson(route('ai.assistant.messages', $conversation))->assertNotFound();
        DB::table('ai_chat_conversations')->where('id', $conversation)->update(['module' => 'dealership']);
        $this->actingAs($one)->getJson(route('ai.assistant.messages', $conversation))->assertNotFound();
    }

    public function test_conversation_messages_reload_in_chronological_order(): void
    {
        Http::fake();
        DB::table('ai_chat_response_settings')->insert(['key' => 'operation_mode', 'value' => 'knowledge_only',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->member());
        $first = $this->postJson(route('ai.assistant.ask'), ['message' => 'First training question'])
            ->assertOk();
        $conversation = $first->json('conversation_id');
        $this->postJson(route('ai.assistant.ask'), ['message' => 'Second training question',
            'conversation_id' => $conversation])->assertOk();
        $rows = $this->getJson(route('ai.assistant.messages', $conversation))
            ->assertOk()->json('data');
        $this->assertSame(['First training question', 'assistant', 'Second training question', 'assistant'],
            array_map(fn ($row) => $row['role'] === 'user' ? $row['content'] : 'assistant', array_reverse($rows)));
        Http::assertNothingSent();
    }
}
