<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\TradingHubAssistantService;
use App\Services\TradingIdeaRiskCalculator;
use App\Services\MarketStructureEvaluator;
use App\Support\ModuleAccess;
use App\Support\TradingAiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class GeminiAssistantController extends Controller
{
    private function authorized(Request $request): void
    {
        abort_unless(ModuleAccess::allows($request->user(), ModuleAccess::TRADINGHUB), 403);
    }

    public function index(Request $request)
    {
        $this->authorized($request);
        return view('ai.assistant.index');
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorized($request);
        $settings = TradingAiSettings::get();
        return response()->json([
            'build' => $settings['build'],
            'mode' => $settings['operation_mode'],
            'knowledge_available' => \App\Models\AiKnowledgeBase::query()->active()->exists(),
            'external_generation_enabled' => $settings['external_enabled'] && $settings['operation_mode'] !== 'knowledge_only',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function conversations(Request $request): JsonResponse
    {
        $this->authorized($request);
        $query = DB::table('ai_chat_conversations')->where('user_id', $request->user()->id)
            ->where('module', 'tradinghub');
        if ($request->filled('search')) {
            $term = mb_substr(trim((string) $request->query('search')), 0, 80);
            $query->where('title', 'like', '%'.$term.'%');
        }
        return response()->json($query->orderByDesc('updated_at')->paginate(20));
    }

    public function createConversation(Request $request): JsonResponse
    {
        $this->authorized($request);
        $id = DB::table('ai_chat_conversations')->insertGetId([
            'user_id' => $request->user()->id, 'module' => 'tradinghub', 'title' => 'New conversation',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['id' => $id, 'title' => 'New conversation'], 201);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation);
        return response()->json(DB::table('ai_chat_messages')->where('conversation_id', $conversation)
            ->orderByDesc('id')->paginate(30));
    }

    public function rename(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation);
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        DB::table('ai_chat_conversations')->where('id', $conversation)->update(['title' => $data['title'], 'updated_at' => now()]);
        return response()->json(['title' => $data['title']]);
    }

    public function delete(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation);
        DB::table('ai_chat_conversations')->where('id', $conversation)->delete();
        return response()->json(['deleted' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $this->authorized($request);
        return response()->json($this->getPreferences($request));
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate([
            'experience' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'style' => ['required', Rule::in(['scalping', 'intraday', 'swing', 'position'])],
            'timeframe' => ['required', Rule::in(['M5', 'M15', 'M30', 'H1', 'H4', 'D1'])],
            'risk_percent' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'maximum_risk_percent' => ['nullable', 'numeric', 'min:0.01', 'max:1', 'gte:risk_percent'],
            'preferred_rr' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'preferred_instruments' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+(?:\s*,\s*[A-Za-z0-9._-]+)*$/'],
            'language' => ['nullable', Rule::in(['en', 'zh', 'ms'])],
            'answer_detail' => ['required', Rule::in(['concise', 'standard', 'detailed'])],
        ]);
        if ((float) $data['risk_percent'] > TradingAiSettings::get()['maximum_risk_percent']
            || (float) ($data['maximum_risk_percent'] ?? 1) > TradingAiSettings::get()['maximum_risk_percent']) {
            return response()->json(['message' => 'Risk preference exceeds the configured maximum.'], 422);
        }
        if (isset($data['preferred_rr'])) $data['preferred_rr'] = max(TradingAiSettings::get()['minimum_rr'], (float) $data['preferred_rr']);
        if (isset($data['preferred_instruments'])) {
            $data['preferred_instruments'] = collect(explode(',', $data['preferred_instruments']))
                ->map(fn ($symbol) => strtoupper(trim($symbol)))->take(10)->implode(', ');
        }
        DB::table('ai_chat_preferences')->updateOrInsert(['user_id' => $request->user()->id],
            $data + ['updated_at' => now(), 'created_at' => now()]);
        return response()->json($this->getPreferences($request));
    }

    public function risk(Request $request, TradingIdeaRiskCalculator $calculator): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate($this->riskRules());
        try {
            $settings = TradingAiSettings::get();
            $data['risk_percent'] ??= $this->getPreferences($request)['risk_percent'];
            $preferred = $this->getPreferences($request)['preferred_rr'];
            $maximum = min($settings['maximum_risk_percent'], $this->getPreferences($request)['maximum_risk_percent']);
            return response()->json($calculator->evaluate($data, $maximum, max($settings['minimum_rr'], $preferred)));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function breakEven(Request $request, TradingIdeaRiskCalculator $calculator): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate([
            'entry' => ['required', 'numeric', 'gt:0'], 'side' => ['required', Rule::in(['buy', 'sell'])],
            'total_costs' => ['required', 'numeric', 'min:0'], 'units' => ['required', 'numeric', 'gt:0'],
            'value_per_price_unit' => ['required', 'numeric', 'gt:0'],
        ]);
        return response()->json([
            'nominal_break_even' => (float) $data['entry'],
            'cost_adjusted_break_even' => $calculator->breakEven((float) $data['entry'], $data['side'],
                (float) $data['total_costs'], (float) $data['units'], (float) $data['value_per_price_unit']),
            'warning' => 'A stop at either level cannot guarantee a zero-loss fill because of spread changes, slippage or gaps.',
        ]);
    }

    public function structure(Request $request, MarketStructureEvaluator $evaluator): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate([
            'h4' => ['nullable', Rule::in(['bullish', 'bearish', 'ranging', 'unknown'])],
            'h1' => ['nullable', Rule::in(['bullish', 'bearish', 'ranging', 'unknown'])],
            'm15' => ['nullable', Rule::in(['bullish', 'bearish', 'ranging', 'bullish_choch', 'bearish_choch', 'unknown'])],
            'h1_invalidated' => ['nullable', 'boolean'], 'at_h1_zone' => ['nullable', 'boolean'],
            'm15_reaction_confirmed' => ['nullable', 'boolean'], 'range_middle' => ['nullable', 'boolean'],
        ]);
        return response()->json($evaluator->evaluate($data));
    }

    public function ask(Request $request, TradingHubAssistantService $assistant): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'client_message_id' => ['nullable', 'uuid'],
            'idea' => ['nullable', 'array'],
        ]);
        $question = trim(strip_tags($data['message']));
        if (mb_strlen($question) < 2) return response()->json(['message' => 'Please enter a question.'], 422);
        $conversationId = $data['conversation_id'] ?? null;
        if ($conversationId) $this->owned($request, $conversationId);
        $clientId = $data['client_message_id'] ?? (string) Str::uuid();
        $existing = DB::table('ai_chat_messages')->join('ai_chat_conversations',
            'ai_chat_conversations.id', '=', 'ai_chat_messages.conversation_id')
            ->where('ai_chat_messages.client_message_id', $clientId)
            ->where('ai_chat_messages.role', 'user')
            ->select('ai_chat_messages.*', 'ai_chat_conversations.user_id', 'ai_chat_conversations.module')->first();
        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $request->user()->id && $existing->module === 'tradinghub', 409);
            if ($existing->content !== $question || ($conversationId && (int) $existing->conversation_id !== (int) $conversationId)) {
                return response()->json(['message' => 'This message identifier was already used for a different question.'], 409);
            }
            $reply = DB::table('ai_chat_messages')->where('reply_to_message_id', $existing->id)->first();
            if ($reply) return response()->json([
                'answer' => $reply->content, 'sources' => json_decode($reply->sources ?? '[]', true) ?: [],
                'response_source' => $reply->response_source, 'unanswered' => in_array($reply->status, ['unanswered', 'failed'], true),
                'conversation_id' => $existing->conversation_id, 'question_id' => $existing->id,
                'message_id' => $reply->id, 'duplicate' => true,
            ])->header('Cache-Control', 'private, no-store');
            return response()->json(['message' => 'Your question is saved and still being processed.',
                'conversation_id' => $existing->conversation_id, 'question_id' => $existing->id,
                'status' => 'pending'], 202);
        }
        $idea = null;
        if (isset($data['idea'])) {
            $idea = validator($data['idea'], $this->riskRules())->validate();
            $idea['risk_percent'] ??= $this->getPreferences($request)['risk_percent'];
        }
        $history = $conversationId ? DB::table('ai_chat_messages')->where('conversation_id', $conversationId)
            ->orderByDesc('id')->limit(6)->get(['role', 'content'])->reverse()->values()->toArray() : [];
        $limit = TradingAiSettings::get()['daily_request_limit'];
        if (DB::table('ai_chat_usage')->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->startOfDay())->count() >= $limit) {
            return response()->json(['message' => 'Daily AI request limit reached. Please try again tomorrow.'], 429);
        }
        $preferences = $this->getPreferences($request);
        try {
            [$conversationId, $questionId, $usageId] = DB::transaction(function () use ($request, $conversationId, $question, $clientId, $preferences): array {
                if (! $conversationId) {
                    $conversationId = DB::table('ai_chat_conversations')->insertGetId([
                        'user_id' => $request->user()->id, 'module' => 'tradinghub',
                        'title' => mb_substr($question, 0, 90), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $questionId = DB::table('ai_chat_messages')->insertGetId([
                    'conversation_id' => $conversationId, 'role' => 'user', 'content' => $question,
                    'client_message_id' => $clientId, 'status' => 'pending',
                    'language' => $preferences['language'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                $usageId = DB::table('ai_chat_usage')->insertGetId(['user_id' => $request->user()->id,
                    'status' => 'started', 'created_at' => now(), 'updated_at' => now()]);
                return [$conversationId, $questionId, $usageId];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            return response()->json(['message' => 'This question is already being processed. Please refresh the conversation.'], 409);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Your question could not be saved. Please retry.'], 503);
        }
        $started = microtime(true);
        try {
            $result = $assistant->answer($question, $request->user(), $preferences, $history, $idea);
        } catch (Throwable $exception) {
            report($exception);
            $result = ['answer' => "I don't have enough verified information to answer that question accurately yet.",
                'sources' => [], 'response_source' => 'fallback', 'unanswered' => true,
                'provider_failure_code' => 'internal_error', 'intent' => 'unanswered'];
        }
        $unansweredRecorded = false;
        if ($result['unanswered'] ?? false) {
            try {
                $this->recordUnanswered($question, ($result['provider_failure_code'] ?? null) === 'internal_error' ? 'failed' : 'unanswered');
                $unansweredRecorded = true;
            } catch (Throwable $exception) {
                Log::error('TradingHub AI unanswered question aggregation failed', ['exception' => get_class($exception)]);
            }
            $result['answer'] .= $unansweredRecorded
                ? ' Your question has been recorded for our team to review.'
                : ' Your question is saved in this conversation, but the review queue could not be updated. Please retry later or contact support.';
        }
        $elapsedMs = (int) round((microtime(true) - $started) * 1000);
        $source = $result['response_source'] ?? (($result['guarded'] ?? false) ? 'fallback' : 'external_ai');
        $status = ($result['provider_failure_code'] ?? null) === 'internal_error'
            ? 'failed' : (($result['unanswered'] ?? false) ? 'unanswered' : 'answered');
        try {
            $assistantMessageId = DB::transaction(function () use ($conversationId, $questionId, $result, $source, $status, $elapsedMs, $preferences, $usageId, $question): int {
                $assistantMessageId = DB::table('ai_chat_messages')->insertGetId([
                    'conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $result['answer'],
                    'sources' => empty($result['sources']) ? null : json_encode($result['sources']),
                    'reply_to_message_id' => $questionId, 'status' => $status,
                    'response_source' => $source, 'language' => $preferences['language'],
                    'response_time_ms' => $elapsedMs,
                    'provider_failure_code' => $result['provider_failure_code'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('ai_chat_messages')->where('id', $questionId)->update(['status' => $status, 'updated_at' => now()]);
                DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => $status === 'answered' ? 'completed' : $status,
                    'response_source' => $source, 'provider_failure_code' => $result['provider_failure_code'] ?? null,
                    'response_time_ms' => $elapsedMs, 'updated_at' => now()]);
            if (DB::table('ai_chat_conversations')->where('id', $conversationId)->value('title') === 'New conversation') {
                    DB::table('ai_chat_conversations')->where('id', $conversationId)->update(['title' => mb_substr($question, 0, 90)]);
                }
                DB::table('ai_chat_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);
                return $assistantMessageId;
            });
        } catch (Throwable $exception) {
            report($exception);
            DB::table('ai_chat_messages')->where('id', $questionId)->update(['status' => 'failed', 'updated_at' => now()]);
            DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => 'failed', 'updated_at' => now()]);
            return response()->json(['message' => 'Your question was saved, but the response could not be saved. Please refresh the conversation.',
                'conversation_id' => $conversationId, 'question_id' => $questionId], 503);
        }
        return response()->json($result + ['conversation_id' => $conversationId,
            'question_id' => $questionId, 'message_id' => $assistantMessageId])
            ->header('Cache-Control', 'private, no-store');
    }

    private function recordUnanswered(string $question, string $aiStatus): void
    {
        $normalized = \App\Models\AiKnowledgeBase::normalizeQuestion($question);
        $hash = hash('sha256', $normalized);
        $redacted = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email removed]', $question);
        $redacted = preg_replace('/\b(?:\+?\d[\d\s()-]{8,}\d)\b/', '[number removed]', $redacted);
        DB::table('ai_unanswered_questions')->insertOrIgnore([
            'question_hash' => $hash, 'question' => mb_substr($redacted, 0, 2000),
            'category' => $this->questionCategory($question), 'ai_status' => $aiStatus,
            'frequency' => 0, 'status' => 'pending', 'last_asked_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_unanswered_questions')->where('question_hash', $hash)
            ->increment('frequency', 1, ['ai_status' => $aiStatus, 'last_asked_at' => now(), 'updated_at' => now()]);
    }

    private function questionCategory(string $question): string
    {
        return match (true) {
            (bool) preg_match('/\b(?:break.even|stop.loss|take.profit|position|trailing|partial)\b/i', $question) => 'trade_management',
            (bool) preg_match('/\b(?:risk|size|equity|drawdown|leverage)\b/i', $question) => 'risk_management',
            (bool) preg_match('/\b(?:structure|trend|support|resistance|bos|choch)\b/i', $question) => 'market_structure',
            (bool) preg_match('/\b(?:fomo|revenge|emotion|discipline)\b/i', $question) => 'trading_psychology',
            default => 'general',
        };
    }

    public function feedback(Request $request): JsonResponse
    {
        $this->authorized($request);
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'rating' => ['required', Rule::in(['helpful', 'not_helpful'])],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $owned = DB::table('ai_chat_messages')->join('ai_chat_conversations',
            'ai_chat_conversations.id', '=', 'ai_chat_messages.conversation_id')
            ->where('ai_chat_messages.id', $data['message_id'])->where('ai_chat_messages.role', 'assistant')
            ->where('ai_chat_conversations.user_id', $request->user()->id)
            ->where('ai_chat_conversations.module', 'tradinghub')->exists();
        abort_unless($owned, 404);
        DB::table('ai_chat_feedback')->updateOrInsert(['user_id' => $request->user()->id,
            'message_id' => $data['message_id']], ['rating' => $data['rating'],
            'comment' => $data['comment'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['saved' => true]);
    }

    private function owned(Request $request, int $id): void
    {
        $this->authorized($request);
        abort_unless(DB::table('ai_chat_conversations')->where('id', $id)
            ->where('user_id', $request->user()->id)->where('module', 'tradinghub')->exists(), 404);
    }

    private function getPreferences(Request $request): array
    {
        $row = DB::table('ai_chat_preferences')->where('user_id', $request->user()->id)->first();
        $settings = TradingAiSettings::get();
        return [
            'experience' => $row->experience ?? 'beginner', 'style' => $row->style ?? 'intraday',
            'timeframe' => $row->timeframe ?? 'M15', 'risk_percent' => min($settings['maximum_risk_percent'], (float) ($row->risk_percent ?? $settings['default_risk_percent'])),
            'preferred_rr' => max($settings['minimum_rr'], (float) ($row->preferred_rr ?? $settings['minimum_rr'])),
            'preferred_instruments' => $row->preferred_instruments ?? '', 'language' => $row->language ?? 'en',
            'answer_detail' => $row->answer_detail ?? 'standard', 'major_timeframe' => 'H4',
            'internal_timeframe' => 'H1', 'maximum_risk_percent' => min($settings['maximum_risk_percent'], (float) ($row->maximum_risk_percent ?? $settings['maximum_risk_percent'])), 'minimum_rr' => $settings['minimum_rr'],
        ];
    }

    private function riskRules(): array
    {
        return [
            'equity' => ['required', 'numeric', 'gt:0'], 'risk_percent' => ['nullable', 'numeric', 'gt:0', 'max:1'],
            'existing_idea_risk' => ['nullable', 'numeric', 'min:0'],
            'side' => ['nullable', Rule::in(['buy', 'sell'])], 'entry' => ['nullable', 'numeric', 'gt:0'],
            'stop' => ['nullable', 'numeric', 'gt:0'], 'opposing_level' => ['nullable', 'numeric', 'gt:0'],
            'tick_size' => ['nullable', 'numeric', 'gt:0'], 'tick_value' => ['nullable', 'numeric', 'gt:0'],
            'contract_size' => ['nullable', 'numeric', 'gt:0'],
            'currency_conversion' => ['nullable', 'numeric', 'gt:0'], 'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'volume_step' => ['nullable', 'numeric', 'gt:0'], 'minimum_volume' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
