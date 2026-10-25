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

    public function conversations(Request $request): JsonResponse
    {
        $this->authorized($request);
        $query = DB::table('ai_chat_conversations')->where('user_id', $request->user()->id);
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
            'user_id' => $request->user()->id, 'title' => 'New conversation',
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
            'idea' => ['nullable', 'array'],
        ]);
        $conversationId = $data['conversation_id'] ?? null;
        if ($conversationId) $this->owned($request, $conversationId);
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
        $usageId = DB::table('ai_chat_usage')->insertGetId(['user_id' => $request->user()->id,
            'status' => 'started', 'created_at' => now(), 'updated_at' => now()]);
        try {
            $result = $assistant->answer(trim(strip_tags($data['message'])), $request->user(),
                $this->getPreferences($request), $history, $idea);
            if (! $conversationId) {
                $conversationId = DB::table('ai_chat_conversations')->insertGetId([
                    'user_id' => $request->user()->id, 'title' => mb_substr(trim(strip_tags($data['message'])), 0, 90),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $assistantMessageId = null;
            foreach ([['user', $data['message'], null], ['assistant', $result['answer'], $result['sources']]] as [$role, $content, $sources]) {
                $messageId = DB::table('ai_chat_messages')->insertGetId(['conversation_id' => $conversationId, 'role' => $role,
                    'content' => $content, 'sources' => $sources ? json_encode($sources) : null,
                    'created_at' => now(), 'updated_at' => now()]);
                if ($role === 'assistant') $assistantMessageId = $messageId;
            }
            if (DB::table('ai_chat_conversations')->where('id', $conversationId)->value('title') === 'New conversation') {
                DB::table('ai_chat_conversations')->where('id', $conversationId)->update(['title' => mb_substr(trim(strip_tags($data['message'])), 0, 90)]);
            }
            DB::table('ai_chat_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);
            DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => 'completed', 'updated_at' => now()]);
            return response()->json($result + ['conversation_id' => $conversationId, 'message_id' => $assistantMessageId])->header('Cache-Control', 'private, no-store');
        } catch (InvalidArgumentException $e) {
            DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => 'rejected', 'updated_at' => now()]);
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => 'failed', 'updated_at' => now()]);
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            DB::table('ai_chat_usage')->where('id', $usageId)->update(['status' => 'failed', 'updated_at' => now()]);
            report($e);
            return response()->json(['message' => 'AI assistant is unavailable. Please retry.'], 500);
        }
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
            ->where('ai_chat_conversations.user_id', $request->user()->id)->exists();
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
            ->where('user_id', $request->user()->id)->exists(), 404);
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
