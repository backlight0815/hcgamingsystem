<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\Capital;
use App\Models\TradingAccount;
use App\Models\TradingExamAnswer;
use App\Models\TradingExamAttempt;
use App\Models\TradingExamOption;
use App\Models\TradingExamQuestion;
use App\Models\TradingJournal;
use App\Models\TradingPair;
use App\Models\TradingPositionApplication;
use App\Models\TradingReason;
use App\Services\PropFirmRuleService;
use App\Services\TradingJournalAnalytics;
use App\Services\TradingJournalTimeService;
use App\Services\TradingRiskAlertService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingDataController extends MobileController
{
    private const TRADING_ROLES = [750, 760, 770];

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $journals = TradingJournal::where('user_id', $user->id)->get();
        $capitals = Capital::where('user_id', $user->id)->get();

        return $this->success([
            'profile' => $user->only(['id', 'name', 'username', 'email', 'role_id', 'status']),
            'metrics' => $this->summary($journals, $capitals),
            'trading_enabled' => in_array((int) $user->role_id, self::TRADING_ROLES, true)
                && ! (bool) ($user->prop_firm_trade_locked ?? false),
            'requires_onboarding' => method_exists($user, 'requiresTraderOnboarding')
                ? $user->requiresTraderOnboarding()
                : false,
            'requires_final_exam' => app(\App\Services\FinalExamService::class)->requiresExam($user),
        ], 'Dashboard loaded.');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $journals = TradingJournal::with(['reason', 'propFirmModel', 'propFirmPhaseRule', 'tradingAccount:id,name,broker,currency', 'attachments'])
            ->where('user_id', $user->id)
            ->latest('open_date')
            ->get();
        $capitals = Capital::where('user_id', $user->id)->latest('deposit_date')->get();
        $ruleService = app(PropFirmRuleService::class);

        return $this->success([
            'trading_pairs' => TradingPair::orderBy('symbol')->get(),
            'trading_reasons' => TradingReason::where(function ($query): void {
                $query->where('is_active', true)->orWhereNull('is_active');
            })->orderBy('sort_order')->orderBy('name')->get(),
            'journals' => $journals,
            'capitals' => $capitals,
            'trading_accounts' => TradingAccount::where('user_id', $user->id)
                ->orderByDesc('is_primary')
                ->latest('id')
                ->get(),
            'summary' => $this->summary($journals, $capitals),
            'prop_firm' => [
                'phase' => $user->prop_firm_phase,
                'review_status' => $user->prop_firm_review_status,
                'trade_locked' => (bool) $user->prop_firm_trade_locked,
                'review_note' => $user->prop_firm_review_note,
                'model' => $ruleService->modelForUser($user),
                'phase_rule' => $ruleService->phaseRuleForUser($user),
            ],
        ], 'Trading data loaded.');
    }

    public function storeJournal(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);
        $this->ensureTradingUnlocked($request);
        $user = $request->user();
        $validated = $this->validateJournal($request);
        $balance = $this->balance((int) $user->id);
        if ($balance <= 0) {
            return $this->failure('You cannot record a trade without any available capital.', [
                'capital' => ['Add capital before recording a trade.'],
            ], 422);
        }

        $pair = TradingPair::whereRaw('UPPER(symbol) = ?', [strtoupper($validated['pair'])])->first();
        if (! $pair) {
            return $this->failure('Selected trading pair was not found.', ['pair' => ['Selected trading pair was not found.']], 422);
        }
        $payload = $this->journalPayload($validated, $pair, $user);
        if (($payload['status'] ?? null) === TradingJournal::STATUS_CLOSED && $balance + (float) $payload['profit_loss'] < 0) {
            return $this->failure('This trade would result in a negative account balance.', ['profit_loss' => ['Insufficient balance.']], 422);
        }

        $journal = TradingJournal::create(['user_id' => $user->id, 'type' => 'trade', ...$payload]);
        if ($journal->isClosed()) {
            app(TradingRiskAlertService::class)->evaluateAndNotify($user, collect([$journal]), 'mobile_trade_created');
        }

        return $this->success($journal->load(['reason', 'propFirmModel', 'propFirmPhaseRule']), 'Trade journal added successfully.', 201);
    }

    public function updateJournal(Request $request, TradingJournal $journal): JsonResponse
    {
        $this->ensureOwner($request, $journal->user_id);
        $this->ensureTradingUnlocked($request);
        $validated = $this->validateJournal($request);
        $pair = TradingPair::whereRaw('UPPER(symbol) = ?', [strtoupper($validated['pair'])])->first();
        if (! $pair) {
            return $this->failure('Selected trading pair was not found.', ['pair' => ['Selected trading pair was not found.']], 422);
        }
        $payload = $this->journalPayload($validated, $pair, $request->user());
        $balanceWithoutTrade = $this->balance((int) $request->user()->id) - (float) ($journal->profit_loss ?? 0);
        if (($payload['status'] ?? null) === TradingJournal::STATUS_CLOSED && $balanceWithoutTrade + (float) $payload['profit_loss'] < 0) {
            return $this->failure('This update would result in a negative account balance.', ['profit_loss' => ['Insufficient balance.']], 422);
        }
        $journal->update($payload);
        $fresh = $journal->fresh(['reason', 'propFirmModel', 'propFirmPhaseRule']);
        if ($fresh?->isClosed()) {
            app(TradingRiskAlertService::class)->evaluateAndNotify($request->user(), collect([$fresh]), 'mobile_trade_updated');
        }

        return $this->success($fresh, 'Trade journal updated successfully.');
    }

    public function destroyJournal(Request $request, TradingJournal $journal): JsonResponse
    {
        $this->ensureOwner($request, $journal->user_id);
        $this->ensureTradingUnlocked($request);
        $journal->delete();

        return $this->success(null, 'Trade journal deleted successfully.');
    }

    public function storeCapital(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trading_account_id' => [
                'nullable',
                'integer',
                Rule::exists('trading_accounts', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'type' => ['required', Rule::in([1, 2, '1', '2'])],
            'depositAmount' => ['required', 'numeric', 'min:0.01'],
            'deposit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $amount = (float) $data['depositAmount'];
        if ((int) $data['type'] === 2) {
            if ($amount > $this->balance((int) $request->user()->id)) {
                return $this->failure('Withdrawal failed: insufficient balance.', ['depositAmount' => ['Insufficient balance.']], 422);
            }
            if ($message = $this->withdrawalBlockMessage((int) $request->user()->id)) {
                return $this->failure($message, ['depositAmount' => [$message]], 422);
            }
            $amount = -$amount;
        }
        $capital = Capital::create([
            'user_id' => $request->user()->id,
            'trading_account_id' => $data['trading_account_id']
                ?? TradingAccount::where('user_id', $request->user()->id)->where('is_primary', true)->value('id'),
            'type' => (int) $data['type'],
            'amount' => $amount,
            'deposit_date' => $data['deposit_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success($capital, 'Capital record created successfully.', 201);
    }

    public function updateCapital(Request $request, Capital $capital): JsonResponse
    {
        $this->ensureOwner($request, $capital->user_id);
        $data = $request->validate([
            'trading_account_id' => [
                'nullable',
                'integer',
                Rule::exists('trading_accounts', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'depositAmount' => ['required', 'numeric', 'min:0.01'],
            'deposit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $amount = (float) $data['depositAmount'];
        if ((int) $capital->type === 2) {
            $balanceWithoutRecord = $this->balance((int) $request->user()->id) - (float) $capital->amount;
            if ($amount > $balanceWithoutRecord) {
                return $this->failure('Withdrawal failed: insufficient balance.', ['depositAmount' => ['Insufficient balance.']], 422);
            }
            $amount = -$amount;
        }
        $capital->update([
            'trading_account_id' => $data['trading_account_id'] ?? $capital->trading_account_id,
            'amount' => $amount,
            'deposit_date' => $data['deposit_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success($capital->fresh(), 'Capital record updated successfully.');
    }

    public function destroyCapital(Request $request, Capital $capital): JsonResponse
    {
        $this->ensureOwner($request, $capital->user_id);
        $capital->delete();

        return $this->success(null, 'Capital record deleted successfully.');
    }

    public function positions(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);
        $eligibility = $this->positionEligibility((int) $request->user()->id);

        return $this->success([
            'eligibility' => $eligibility,
            'applications' => TradingPositionApplication::where('user_id', $request->user()->id)->latest('id')->get(),
            'positions' => $this->availablePositions((int) $request->user()->role_id),
            'referral' => null,
        ], 'Trading positions loaded.');
    }

    public function storePosition(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);
        $positions = array_keys($this->availablePositions((int) $request->user()->role_id));
        $data = $request->validate([
            'requested_position' => ['required', Rule::in($positions)],
            'strategy_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'trade_history_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'personality_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'marketing_plan' => ['nullable', 'required_if:requested_position,recruiter', 'string', 'max:5000'],
            'client_support_plan' => ['required', 'string', 'max:5000'],
        ]);
        $eligibility = $this->positionEligibility((int) $request->user()->id);
        if (! $eligibility['eligible']) {
            return $this->failure('Position application requires a 30-day trade history and at least 60 trades.', [], 422);
        }
        if (TradingPositionApplication::where('user_id', $request->user()->id)->where('status', TradingPositionApplication::STATUS_PENDING)->exists()) {
            return $this->failure('You already have a position application pending review.', [], 422);
        }
        $application = TradingPositionApplication::create([
            'user_id' => $request->user()->id,
            'requested_position' => $data['requested_position'],
            'requested_role_id' => TradingPositionApplication::roleForPosition($data['requested_position']),
            'status' => TradingPositionApplication::STATUS_PENDING,
            'first_trade_date' => $eligibility['first_trade_date'],
            'trade_count_snapshot' => $eligibility['trade_count'],
            'strategy_summary' => $data['strategy_summary'] ?? null,
            'trade_history_summary' => $data['trade_history_summary'] ?? null,
            'personality_summary' => $data['personality_summary'] ?? null,
            'marketing_plan' => $data['marketing_plan'] ?? null,
            'client_support_plan' => $data['client_support_plan'],
            'submitted_at' => now(),
        ]);

        return $this->success($application, 'Position application submitted.', 201);
    }

    public function exam(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);
        $approved = TradingExamQuestion::where('status', TradingExamQuestion::STATUS_APPROVED)->count();
        $attempt = $approved >= 5 ? $this->dailyAttempt($request->user()) : null;
        $attempt?->load(['answers.question.options', 'answers.selectedOption']);

        return $this->success([
            'attempt' => $attempt ? [
                'id' => (string) $attempt->id,
                'exam_date' => $attempt->exam_date,
                'status' => $attempt->status,
                'total_questions' => (int) $attempt->total_questions,
                'score' => (int) $attempt->score,
                'completed_at' => $attempt->completed_at,
            ] : null,
            'questions' => $attempt?->answers->map(fn ($answer): array => [
                'id' => (string) $answer->id,
                'question_id' => (string) $answer->trading_exam_question_id,
                'category' => $answer->question->category,
                'difficulty' => $answer->question->difficulty,
                'question_text' => $answer->question->question_text,
                'options' => $answer->question->options->map(fn ($option): array => [
                    'id' => (string) $option->id,
                    'option_text' => $option->option_text,
                    'position' => (int) $option->position,
                ])->values(),
            ])->values() ?? collect(),
            'approved_question_count' => $approved,
            'recent_attempts' => TradingExamAttempt::where('user_id', $request->user()->id)->latest('exam_date')->limit(10)->get(),
        ], 'Daily trading examination loaded.');
    }

    public function submitExam(Request $request, TradingExamAttempt $attempt): JsonResponse
    {
        $this->ensureTradingMember($request);
        $this->ensureOwner($request, $attempt->user_id);
        if ($attempt->isCompleted()) {
            return $this->success($attempt->load('answers.selectedOption'), 'This daily examination is already completed.');
        }
        $attempt->load('answers.question.options');
        $data = $request->validate([
            'answers' => ['required', 'array', 'size:'.$attempt->answers->count()],
            'answers.*' => ['required', 'integer'],
        ]);
        $selected = collect($data['answers'])->mapWithKeys(fn ($optionId, $answerId): array => [(int) $answerId => (int) $optionId]);
        $answerIds = $attempt->answers->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(empty(array_diff($answerIds, $selected->keys()->all())), 422);
        $score = 0;
        DB::transaction(function () use ($attempt, $selected, &$score): void {
            foreach ($attempt->answers as $answer) {
                $option = TradingExamOption::where('id', $selected[(int) $answer->id] ?? 0)
                    ->where('trading_exam_question_id', $answer->trading_exam_question_id)
                    ->firstOrFail();
                $answer->update(['selected_option_id' => $option->id, 'is_correct' => (bool) $option->is_correct]);
                if ($option->is_correct) {
                    $score++;
                }
            }
            $attempt->update(['status' => TradingExamAttempt::STATUS_COMPLETED, 'score' => $score, 'completed_at' => now()]);
        });

        return $this->success($attempt->fresh('answers.selectedOption'), 'Daily trading knowledge check completed.');
    }

    private function validateJournal(Request $request): array
    {
        $validated = $request->validate([
            'trading_account_id' => [
                'nullable',
                'integer',
                Rule::exists('trading_accounts', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'status' => ['nullable', Rule::in(['draft', 'open', 'closed'])],
            'close_handling' => ['nullable', Rule::in(['manual_close', 'follow_pre_set'])],
            'trading_reason_id' => ['nullable', 'integer', 'exists:trading_reason,id'],
            'time_input_timezone' => ['nullable', Rule::in(['malaysia', 'mt5'])],
            'time_input_offset_minutes' => ['nullable', 'integer', Rule::in([120, 180])],
            'open_date' => ['required', 'date'],
            'close_date' => ['nullable', 'date'],
            'pair' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in([1, 2, '1', '2'])],
            'entry_price' => ['required', 'numeric'],
            'exit_price' => ['nullable', 'numeric'],
            'lot_size' => ['required', 'numeric', 'min:0'],
            'profit_loss' => ['nullable', 'numeric'],
            'result' => ['nullable', Rule::in([1, 2, 3, '1', '2', '3'])],
            'notes' => ['nullable', 'string'],
            'emotion_before_entry' => ['nullable', 'string', 'max:100'],
            'emotion_during_trade' => ['nullable', 'string', 'max:100'],
            'emotion_after_trade' => ['nullable', 'string', 'max:100'],
            'confidence_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'discipline_rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'mistake_tags' => ['nullable', 'array', 'max:20'],
            'mistake_tags.*' => ['string', 'max:100'],
            'psychology_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $closeFields = collect(['close_date', 'exit_price', 'result']);
        $complete = $closeFields->every(fn (string $field): bool => isset($validated[$field]) && $validated[$field] !== '');
        $any = collect(['close_date', 'exit_price', 'result', 'profit_loss'])
            ->contains(fn (string $field): bool => isset($validated[$field]) && $validated[$field] !== '');
        if (($validated['status'] ?? 'open') === 'closed' && ! $complete) {
            throw ValidationException::withMessages(['close_date' => 'Closed trades require close time, exit price, and result.']);
        }
        if (($validated['status'] ?? 'open') !== 'closed' && $any && ! $complete) {
            throw ValidationException::withMessages(['close_date' => 'Complete every close field or keep the trade open.']);
        }
        $validated['status'] = $complete ? 'closed' : (($validated['status'] ?? 'open') === 'draft' ? 'draft' : 'open');
        $validated['close_handling'] ??= 'manual_close';

        return $validated;
    }

    private function journalPayload(array $data, TradingPair $pair, $user): array
    {
        $time = app(TradingJournalTimeService::class);
        $mode = $time->normalizeMode($data['time_input_timezone'] ?? null);
        $offset = $time->normalizeOffset($data['time_input_offset_minutes'] ?? null, $mode);
        $rule = app(PropFirmRuleService::class)->phaseRuleForUser($user);
        $payload = [
            'trading_account_id' => $data['trading_account_id'] ?? null,
            'status' => $data['status'],
            'close_handling' => $data['close_handling'],
            'time_input_timezone' => $mode,
            'time_input_offset_minutes' => $offset,
            'prop_firm_model_id' => $rule?->prop_firm_model_id,
            'prop_firm_phase_rule_id' => $rule?->id,
            'pair' => $pair->symbol,
            'trading_reason_id' => $data['trading_reason_id'] ?? null,
            'direction' => (int) $data['direction'],
            'entry_price' => (float) $data['entry_price'],
            'lot_size' => (float) $data['lot_size'],
            'notes' => $data['notes'] ?? null,
            'emotion_before_entry' => $data['emotion_before_entry'] ?? null,
            'emotion_during_trade' => $data['emotion_during_trade'] ?? null,
            'emotion_after_trade' => $data['emotion_after_trade'] ?? null,
            'confidence_level' => $data['confidence_level'] ?? null,
            'discipline_rating' => $data['discipline_rating'] ?? null,
            'mistake_tags' => isset($data['mistake_tags']) ? array_values(array_unique($data['mistake_tags'])) : null,
            'psychology_notes' => $data['psychology_notes'] ?? null,
        ];
        if ($data['status'] !== TradingJournal::STATUS_CLOSED) {
            $open = $time->toMalaysiaDatabase($data['open_date'], $mode, $offset);
            if (! $open) {
                throw ValidationException::withMessages(['open_date' => 'The open trade time is invalid.']);
            }

            return [...$payload, 'open_date' => $open, 'close_date' => null, 'exit_price' => null, 'pips' => null, 'profit_loss' => null, 'result' => null];
        }
        $timeline = $time->toMalaysiaTimeline($data['open_date'], $data['close_date'], $mode, $offset);
        if (! $timeline['is_valid']) {
            throw ValidationException::withMessages(['close_date' => 'The close time must be after the open time.']);
        }
        $pips = round(abs((float) $data['exit_price'] - (float) $data['entry_price']) / max((float) $pair->pip_factor, 0.00000001), (int) $pair->pip_decimal);
        $profitLoss = match ((int) $data['result']) {
            1 => round(abs($pips * (float) $data['lot_size'] * 10), 2),
            2 => round(-abs($pips * (float) $data['lot_size'] * 10), 2),
            default => 0.0,
        };

        return [...$payload, 'open_date' => $timeline['open_date'], 'close_date' => $timeline['close_date'], 'exit_price' => (float) $data['exit_price'], 'pips' => $pips, 'profit_loss' => $profitLoss, 'result' => (int) $data['result']];
    }

    private function balance(int $userId): float
    {
        return (float) Capital::where('user_id', $userId)->sum('amount')
            + (float) TradingJournal::where('user_id', $userId)->sum('profit_loss');
    }

    private function withdrawalBlockMessage(int $userId): ?string
    {
        $trades = TradingJournal::where('user_id', $userId)->where(fn ($query) => $query->where('type', 'trade')->orWhereNull('type'))->get();
        $deposits = (float) Capital::where('user_id', $userId)->where('type', 1)->sum('amount');
        $analytics = app(TradingJournalAnalytics::class);
        $best = $analytics->bestDayRule($trades);
        $gross = $analytics->grossProfitRule($trades, $deposits);
        if (($best['passed'] ?? false) && ($gross['passed'] ?? false)) {
            return null;
        }

        return 'Withdrawal failed: complete the 40% Best Day Rule and 2% gross profit rule first.';
    }

    private function positionEligibility(int $userId): array
    {
        $query = TradingJournal::where('user_id', $userId)->where(fn ($builder) => $builder->where('type', 'trade')->orWhereNull('type'));
        $first = (clone $query)->min('open_date');
        $count = (clone $query)->count();
        $firstDate = $first ? Carbon::parse($first)->startOfDay() : null;
        $eligibleFrom = $firstDate?->copy()->addDays(30);
        $ageReady = $eligibleFrom && today()->greaterThanOrEqualTo($eligibleFrom);

        return [
            'eligible' => (bool) $ageReady && $count >= 60,
            'first_trade_date' => $firstDate?->toDateString(),
            'trade_count' => $count,
            'eligible_from' => $eligibleFrom?->toDateString(),
            'days_since_first_trade' => $firstDate ? $firstDate->diffInDays(today()) : 0,
            'required_days' => 30,
            'required_trade_count' => 60,
            'remaining_trade_count' => max(0, 60 - $count),
            'has_required_trade_age' => (bool) $ageReady,
            'has_required_trade_count' => $count >= 60,
        ];
    }

    private function availablePositions(int $roleId): array
    {
        return match ($roleId) {
            760 => [],
            770 => ['leadership' => 'Leadership'],
            default => ['recruiter' => 'Recruiter', 'leadership' => 'Leadership'],
        };
    }

    private function dailyAttempt($user): TradingExamAttempt
    {
        return DB::transaction(function () use ($user): TradingExamAttempt {
            $attempt = TradingExamAttempt::where('user_id', $user->id)->whereDate('exam_date', today())->lockForUpdate()->first();
            if ($attempt) {
                return $attempt;
            }
            $questions = TradingExamQuestion::where('status', TradingExamQuestion::STATUS_APPROVED)->inRandomOrder()->limit(5)->get();
            $attempt = TradingExamAttempt::create(['user_id' => $user->id, 'exam_date' => today(), 'status' => TradingExamAttempt::STATUS_IN_PROGRESS, 'total_questions' => $questions->count()]);
            foreach ($questions as $question) {
                TradingExamAnswer::create(['trading_exam_attempt_id' => $attempt->id, 'trading_exam_question_id' => $question->id]);
            }

            return $attempt;
        });
    }

    private function summary($journals, $capitals): array
    {
        $closed = $journals->filter(fn (TradingJournal $journal): bool => $journal->isClosed());
        $wins = $closed->where('profit_loss', '>', 0)->count();
        $losses = $closed->where('profit_loss', '<', 0)->count();
        $net = (float) $closed->sum('profit_loss');
        $capital = (float) $capitals->sum('amount');

        return [
            'total_trades' => $journals->count(),
            'closed_trades' => $closed->count(),
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $wins + $losses > 0 ? round($wins / ($wins + $losses) * 100, 2) : 0,
            'net_profit_loss' => round($net, 2),
            'capital' => round($capital, 2),
            'balance' => round($capital + $net, 2),
        ];
    }

    private function ensureOwner(Request $request, mixed $ownerId): void
    {
        abort_unless((int) $ownerId === (int) $request->user()->id, 404);
    }

    private function ensureTradingMember(Request $request): void
    {
        abort_unless(in_array((int) $request->user()->role_id, self::TRADING_ROLES, true), 403);
    }

    private function ensureTradingUnlocked(Request $request): void
    {
        if ((bool) ($request->user()->prop_firm_trade_locked ?? false)) {
            abort(423, 'Trading activity is locked pending administration review.');
        }
    }
}
