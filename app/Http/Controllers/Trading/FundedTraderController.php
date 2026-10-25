<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\PropFirmEvaluationQuestion;
use App\Models\PropFirmModel;
use App\Models\PropFirmPhaseRule;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use App\Services\TradingJournalAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FundedTraderController extends Controller
{
    public const STATUS_PENDING = 0;

    public const STATUS_APPROVED = 1;

    public const STATUS_REJECTED = 2;

    public const REVIEW_PHASE1_PROFIT_TARGET = 'phase1_profit_target_review';

    public const REVIEW_PHASE1_PROFITABLE_DAYS_REQUIRED = 'phase1_profitable_days_required';

    public const REVIEW_PHASE2_PROFIT_TARGET = 'phase2_profit_target_review';

    public const REVIEW_PHASE2_PROFITABLE_DAYS_REQUIRED = 'phase2_profitable_days_required';

    private const RISK_CATEGORY_ALL = 'all';

    private const RISK_CATEGORY_HIGH = 'high';

    private const RISK_CATEGORY_MID = 'mid';

    private const RISK_CATEGORY_LOW = 'low';

    public function AllFundedTrader(Request $request)
    {
        $this->ensureAdmin();
        $selectedRiskCategory = $this->normalizeReviewRiskCategory($request->query('risk_category'));

        $reviewTraders = User::withCount([
            'propFirmEvaluationQuestions as open_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_OPEN),
            'propFirmEvaluationQuestions as answered_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_ANSWERED),
        ])
            ->where('role_id', 750)
            ->whereIn('prop_firm_review_status', [self::REVIEW_PHASE1_PROFIT_TARGET, self::REVIEW_PHASE2_PROFIT_TARGET, 'pending_phase2', 'pending_funded', 'daily_loss_review', 'total_loss_review'])
            ->orderByDesc('prop_firm_review_requested_at')
            ->get();

        $this->attachReviewQueueRiskProfiles($reviewTraders);
        $allReviewTradersCount = $reviewTraders->count();
        $reviewRiskSummary = $this->reviewRiskSummary($reviewTraders);
        $reviewTraders = $this->filterAndSortReviewTradersByRisk($reviewTraders, $selectedRiskCategory);

        $fundedTraders = User::where('role_id', 750)
            ->where('prop_firm_phase', 3)
            ->where('funded_status', self::STATUS_APPROVED)
            ->orderBy('username')
            ->get();

        $rejectedTraders = User::where('role_id', 750)
            ->where('funded_status', self::STATUS_REJECTED)
            ->orderByDesc('updated_at')
            ->get();

        $questions = PropFirmEvaluationQuestion::with(['trader', 'asker'])
            ->latest()
            ->take(30)
            ->get();

        return view('admin.funded_trader.funded_all', compact(
            'reviewTraders',
            'allReviewTradersCount',
            'reviewRiskSummary',
            'selectedRiskCategory',
            'fundedTraders',
            'rejectedTraders',
            'questions'
        ));
    }

    public function PropFirmConfig()
    {
        $this->ensureAdmin();

        $models = PropFirmModel::with(['phaseRules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.funded_trader.prop_firm_config', compact('models'));
    }

    public function UpdatePropFirmModel(Request $request, PropFirmModel $model)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $model->update([
            'provider' => $data['provider'] ?? 'FundingPips',
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Prop firm model updated.');
    }

    public function UpdatePropFirmPhaseRule(Request $request, PropFirmPhaseRule $phaseRule)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'phase_key' => ['required', 'string', 'max:50'],
            'phase_number' => ['required', 'integer', 'min:1', 'max:3'],
            'display_name' => ['required', 'string', 'max:150'],
            'profit_target_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_daily_loss_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_overall_loss_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_trading_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_trading_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'daily_reset_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'max:100'],
            'leverage' => ['nullable', 'string', 'max:50'],
            'news_trading_rule' => ['nullable', 'string', 'max:500'],
            'overnight_holding_rule' => ['nullable', 'string', 'max:500'],
            'weekend_holding_rule' => ['nullable', 'string', 'max:500'],
            'inactivity_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'risk_per_trade_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reward_split' => ['nullable', 'string', 'max:100'],
            'reward_cycle' => ['nullable', 'string', 'max:100'],
            'consistency_rule' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $phaseRule->update([
            'phase_key' => $data['phase_key'],
            'phase_number' => $data['phase_number'],
            'display_name' => $data['display_name'],
            'profit_target_percent' => $data['profit_target_percent'],
            'max_daily_loss_percent' => $data['max_daily_loss_percent'],
            'max_overall_loss_percent' => $data['max_overall_loss_percent'],
            'minimum_trading_days' => $data['minimum_trading_days'],
            'max_trading_days' => $data['max_trading_days'] ?? 0,
            'daily_reset_time' => $data['daily_reset_time'].':00',
            'timezone' => $data['timezone'],
            'leverage' => $data['leverage'] ?? null,
            'news_trading_rule' => $data['news_trading_rule'] ?? null,
            'overnight_holding_rule' => $data['overnight_holding_rule'] ?? null,
            'weekend_holding_rule' => $data['weekend_holding_rule'] ?? null,
            'inactivity_days' => $data['inactivity_days'] ?? null,
            'risk_per_trade_percent' => $data['risk_per_trade_percent'] ?? null,
            'reward_split' => $data['reward_split'] ?? null,
            'reward_cycle' => $data['reward_cycle'] ?? null,
            'consistency_rule' => $data['consistency_rule'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Prop firm phase rule updated.');
    }

    public function approve($id)
    {
        $this->ensureAdmin();

        $user = User::where('role_id', 750)->findOrFail($id);
        $reviewStatus = (string) ($user->prop_firm_review_status ?? 'none');

        if ($reviewStatus === self::REVIEW_PHASE1_PROFIT_TARGET || $reviewStatus === 'pending_phase2') {
            $phaseTwoStartBalance = $this->initialCapitalForUser($user);
            $this->archiveCurrentJournals($user, 1, 'phase_1_approved_to_phase_2');
            TradingJournal::where('user_id', $user->id)->chunkById(200, function ($journals): void {
                $journals->each->delete();
            });

            $user->prop_firm_phase = 2;
            if (Schema::hasColumn('users', 'phase2_start_balance')) {
                $user->phase2_start_balance = $phaseTwoStartBalance;
            }
            $user->funded_status = self::STATUS_PENDING;
            $user->prop_firm_review_status = 'approved_phase2';
            $user->prop_firm_review_phase = null;
            $this->setRequiredProfitableDays($user, null);
            $user->prop_firm_trade_locked = false;
            $user->prop_firm_review_note = $reviewStatus === self::REVIEW_PHASE1_PROFIT_TARGET
                ? 'Phase 2 approved by administration without waiting for 3 profitable days.'
                : 'Phase 2 approved by administration.';
            $user->prop_firm_review_approved_at = now();
            $user->save();

            $message = $reviewStatus === self::REVIEW_PHASE1_PROFIT_TARGET
                ? 'Phase 1 profit target review approved instantly. Trader has been moved to Phase 2 and trading is unlocked.'
                : 'Phase 1 review approved. Trader has been moved to Phase 2 and trading is unlocked.';

            return back()->with('success', $message);
        }

        if ($reviewStatus === self::REVIEW_PHASE2_PROFIT_TARGET || $reviewStatus === 'pending_funded') {
            $this->archiveCurrentJournals($user, 2, 'phase_2_approved_to_funded');
            TradingJournal::where('user_id', $user->id)->chunkById(200, function ($journals): void {
                $journals->each->delete();
            });

            $user->prop_firm_phase = 3;
            $user->funded_status = self::STATUS_APPROVED;
            $user->status = 1;
            $user->prop_firm_review_status = 'funded_approved';
            $user->prop_firm_review_phase = null;
            $this->setRequiredProfitableDays($user, null);
            $user->prop_firm_trade_locked = false;
            $user->prop_firm_review_note = $reviewStatus === self::REVIEW_PHASE2_PROFIT_TARGET
                ? 'Funded account approved by administration without waiting for 3 profitable days.'
                : 'Funded account approved by administration.';
            $user->prop_firm_review_approved_at = now();
            $user->save();

            $message = $reviewStatus === self::REVIEW_PHASE2_PROFIT_TARGET
                ? 'Phase 2 profit target review approved instantly. Trader has been moved to funded account status.'
                : 'Phase 2 review approved. Trader has been moved to funded account status.';

            return back()->with('success', $message);
        }

        return back()->with('info', 'No pending prop firm review was found for this trader.');
    }

    public function requirePhaseOneProfitableDays(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'required_profitable_days' => ['required', 'integer', 'min:1', 'max:3'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== self::REVIEW_PHASE1_PROFIT_TARGET) {
            return back()->with('info', 'No Phase 1 profit target review was found for this trader.');
        }

        $profitableDays = $this->currentProfitableDays($user);
        $requiredDays = $this->normalizeRequiredProfitableDays((int) $data['required_profitable_days'], $profitableDays);
        $remainingDays = max(0, $requiredDays - $profitableDays);

        $user->prop_firm_review_status = self::REVIEW_PHASE1_PROFITABLE_DAYS_REQUIRED;
        $user->prop_firm_review_phase = 1;
        $this->setRequiredProfitableDays($user, $requiredDays);
        $user->prop_firm_trade_locked = false;
        $user->prop_firm_review_note = $data['review_note']
            ?? sprintf(
                'Phase 1 profit target reached. Administration requires %d total profitable day(s) before Phase 2 approval. Current progress: %d/%d; %d more day(s) needed.',
                $requiredDays,
                $profitableDays,
                $requiredDays,
                $remainingDays
            );
        $user->prop_firm_review_approved_at = null;
        $user->save();

        return back()->with('success', sprintf('Trader has been unlocked and must complete %d total profitable day(s) before Phase 2 approval.', $requiredDays));
    }

    public function requirePhaseTwoProfitableDays(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'required_profitable_days' => ['required', 'integer', 'min:1', 'max:3'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== self::REVIEW_PHASE2_PROFIT_TARGET) {
            return back()->with('info', 'No Phase 2 profit target review was found for this trader.');
        }

        $profitableDays = $this->currentProfitableDays($user);
        $requiredDays = $this->normalizeRequiredProfitableDays((int) $data['required_profitable_days'], $profitableDays);
        $remainingDays = max(0, $requiredDays - $profitableDays);

        $user->prop_firm_review_status = self::REVIEW_PHASE2_PROFITABLE_DAYS_REQUIRED;
        $user->prop_firm_review_phase = 2;
        $this->setRequiredProfitableDays($user, $requiredDays);
        $user->prop_firm_trade_locked = false;
        $user->prop_firm_review_note = $data['review_note']
            ?? sprintf(
                'Phase 2 profit target reached. Administration requires %d total profitable day(s) before funded account approval. Current progress: %d/%d; %d more day(s) needed.',
                $requiredDays,
                $profitableDays,
                $requiredDays,
                $remainingDays
            );
        $user->prop_firm_review_approved_at = null;
        $user->save();

        return back()->with('success', sprintf('Trader has been unlocked and must complete %d total profitable day(s) before funded account approval.', $requiredDays));
    }

    public function reject(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        $user->funded_status = self::STATUS_REJECTED;
        $user->prop_firm_review_status = 'rejected';
        $this->setRequiredProfitableDays($user, null);
        $user->prop_firm_trade_locked = true;
        $user->prop_firm_review_note = $data['review_note'] ?? 'Rejected by administration review.';
        $user->save();

        return back()->with('error', 'Prop firm review rejected and trading remains locked.');
    }

    public function keepActiveAfterDailyLoss(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== 'daily_loss_review') {
            return back()->with('info', 'No open daily loss review was found for this trader.');
        }

        $user->prop_firm_review_status = 'daily_loss_allowed';
        $user->prop_firm_review_phase = null;
        $user->prop_firm_trade_locked = false;
        $user->prop_firm_review_note = $data['review_note'] ?? 'Daily loss breach reviewed. Trader kept active by administration.';
        $user->prop_firm_review_approved_at = now();
        $user->save();

        return back()->with('success', 'Daily loss review cleared. Trader remains active and unlocked.');
    }

    public function banAfterDailyLoss(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== 'daily_loss_review') {
            return back()->with('info', 'No open daily loss review was found for this trader.');
        }

        $user->status = 0;
        $user->funded_status = self::STATUS_REJECTED;
        $user->prop_firm_trade_locked = true;
        $user->prop_firm_review_status = 'daily_loss_banned';
        $user->prop_firm_review_phase = null;
        $this->setRequiredProfitableDays($user, null);
        $user->prop_firm_review_note = $data['review_note'] ?? 'Account banned by administration after daily loss breach review.';
        $user->prop_firm_review_approved_at = now();
        $user->save();

        return back()->with('error', 'Trader account has been suspended after daily loss breach review.');
    }

    public function keepActiveAfterTotalLoss(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== 'total_loss_review') {
            return back()->with('info', 'No open total loss review was found for this trader.');
        }

        $user->prop_firm_review_status = 'total_loss_allowed';
        $user->prop_firm_review_phase = null;
        $user->prop_firm_trade_locked = false;
        $user->prop_firm_review_note = $data['review_note'] ?? 'Total loss breach reviewed. Trader kept active by administration.';
        $user->prop_firm_review_approved_at = now();
        $user->save();

        return back()->with('success', 'Total loss review cleared. Trader remains active and unlocked.');
    }

    public function banAfterTotalLoss(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== 'total_loss_review') {
            return back()->with('info', 'No open total loss review was found for this trader.');
        }

        $user->status = 0;
        $user->funded_status = self::STATUS_REJECTED;
        $user->prop_firm_trade_locked = true;
        $user->prop_firm_review_status = 'total_loss_banned';
        $user->prop_firm_review_phase = null;
        $this->setRequiredProfitableDays($user, null);
        $user->prop_firm_review_note = $data['review_note'] ?? 'Account banned by administration after total loss breach review.';
        $user->prop_firm_review_approved_at = now();
        $user->save();

        return back()->with('error', 'Trader account has been suspended after total loss breach review.');
    }

    public function suspend(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        $user->funded_status = self::STATUS_PENDING;
        $user->prop_firm_trade_locked = true;
        $user->prop_firm_review_status = 'suspended';
        $this->setRequiredProfitableDays($user, null);
        $user->prop_firm_review_note = $data['review_note'] ?? 'Funded account suspended for administration review.';
        $user->save();

        return back()->with('warning', 'Trader has been suspended and trading is locked.');
    }

    public function askQuestion(Request $request, $id)
    {
        $this->ensureAdmin();

        $user = User::where('role_id', 750)->findOrFail($id);
        $data = $request->validate([
            'phase' => ['nullable', 'integer', 'in:1,2,3'],
            'title' => ['nullable', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:5000'],
        ]);

        PropFirmEvaluationQuestion::create([
            'user_id' => $user->id,
            'asked_by' => auth()->id(),
            'phase' => $data['phase'] ?? $user->prop_firm_phase,
            'title' => $data['title'] ?? 'Prop firm evaluation question',
            'question' => $data['question'],
            'status' => PropFirmEvaluationQuestion::STATUS_OPEN,
        ]);

        $user->prop_firm_trade_locked = true;
        $user->prop_firm_review_note = 'Suspicious trading activity question requires trader response.';
        if (! in_array((string) $user->prop_firm_review_status, ['pending_phase2', 'pending_funded'], true)) {
            $user->prop_firm_review_status = 'question_required';
        }
        $user->save();

        return back()->with('success', 'Evaluation question sent. Trader will see a pop-up on the trading journal page.');
    }

    public function resolveQuestion(PropFirmEvaluationQuestion $question)
    {
        $this->ensureAdmin();

        $question->update([
            'status' => PropFirmEvaluationQuestion::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        $trader = $question->trader;
        if ($trader && ! in_array((string) $trader->prop_firm_review_status, ['pending_phase2', 'pending_funded'], true)) {
            $hasActiveQuestions = $trader->propFirmEvaluationQuestions()
                ->whereIn('status', [
                    PropFirmEvaluationQuestion::STATUS_OPEN,
                    PropFirmEvaluationQuestion::STATUS_ANSWERED,
                ])
                ->exists();

            if (! $hasActiveQuestions) {
                $trader->prop_firm_trade_locked = false;
                $trader->prop_firm_review_status = 'none';
                $trader->prop_firm_review_note = null;
                $trader->save();
            }
        }

        return back()->with('success', 'Evaluation question marked as resolved.');
    }

    private function currentProfitableDays(User $user): int
    {
        $journals = TradingJournal::where('user_id', $user->id)->get();
        $rule = app(TradingJournalAnalytics::class)->profitableDayRule($journals, 3);

        return (int) ($rule['profitable_days'] ?? 0);
    }

    private function attachReviewQueueRiskProfiles(Collection $reviewTraders): void
    {
        if ($reviewTraders->isEmpty()) {
            return;
        }

        $traderIds = $reviewTraders
            ->pluck('id')
            ->filter()
            ->values();

        $journalsByTrader = TradingJournal::whereIn('user_id', $traderIds)
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->orderByRaw('COALESCE(open_date, close_date, created_at) asc')
            ->get()
            ->groupBy('user_id');

        $capitalByTrader = Capital::whereIn('user_id', $traderIds)
            ->where('type', 1)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $capitalRows): float => round((float) $capitalRows->sum('amount'), 2));

        $analytics = app(TradingJournalAnalytics::class);

        $reviewTraders->each(function (User $trader) use ($analytics, $journalsByTrader, $capitalByTrader): void {
            $journals = $journalsByTrader->get($trader->id, collect())->values();
            $accountBalance = (float) $capitalByTrader->get($trader->id, 0.0);
            $behaviorProfile = $analytics->behavioralRiskProfile($journals, $accountBalance);
            $riskProfile = $this->reviewRiskProfile($trader, $behaviorProfile, $journals);

            $trader->setAttribute('admin_risk_profile', $riskProfile);
            $trader->setAttribute('admin_risk_category', $riskProfile['category']);
            $trader->setAttribute('admin_risk_rank', $riskProfile['rank']);

            if (! in_array((string) $trader->prop_firm_review_status, [self::REVIEW_PHASE1_PROFIT_TARGET, self::REVIEW_PHASE2_PROFIT_TARGET], true)) {
                return;
            }

            $profitableDayRule = $analytics->profitableDayRule($journals, 3);
            $trader->setAttribute('profit_target_profitable_day_rule', $profitableDayRule);
            $trader->setAttribute('phase1_profitable_day_rule', $profitableDayRule);
        });
    }

    private function reviewRiskProfile(User $trader, array $behaviorProfile, Collection $journals): array
    {
        $status = (string) ($trader->prop_firm_review_status ?? 'none');
        $tier = strtolower((string) data_get($behaviorProfile, 'tier', 'normal'));
        $category = self::RISK_CATEGORY_LOW;
        $priorityNote = 'Low priority: no high-risk review flag is currently detected.';

        if (in_array($status, ['daily_loss_review', 'total_loss_review'], true)) {
            $category = self::RISK_CATEGORY_HIGH;
            $priorityNote = $status === 'daily_loss_review'
                ? 'Review first: daily loss breach is waiting for an admin decision.'
                : 'Review first: total loss breach is waiting for an admin decision.';
        } elseif (in_array($status, [self::REVIEW_PHASE1_PROFIT_TARGET, self::REVIEW_PHASE2_PROFIT_TARGET], true)) {
            $category = self::RISK_CATEGORY_HIGH;
            $priorityNote = 'Review first: profit target has been reached and needs an admin decision.';
        } elseif (in_array($tier, ['critical', 'high'], true)) {
            $category = self::RISK_CATEGORY_HIGH;
            $priorityNote = 'Review first: behaviour score is in the high-risk range.';
        } elseif ($tier === self::RISK_CATEGORY_MID) {
            $category = self::RISK_CATEGORY_MID;
            $priorityNote = 'Review after high-risk cases: behaviour score is on the watchlist.';
        } elseif (((int) ($trader->open_questions_count ?? 0) + (int) ($trader->answered_questions_count ?? 0)) > 0) {
            $category = self::RISK_CATEGORY_MID;
            $priorityNote = 'Review after high-risk cases: an evaluation question is still active.';
        }

        return [
            'category' => $category,
            'rank' => $this->reviewRiskRank($category),
            'label' => $this->reviewRiskLabel($category),
            'tone' => $this->reviewRiskTone($category),
            'priority_note' => $priorityNote,
            'score' => round((float) data_get($behaviorProfile, 'risk_score', data_get($behaviorProfile, 'final_score', 0)), 2),
            'tier' => $tier,
            'tier_label' => (string) data_get($behaviorProfile, 'tier_label', ucfirst($tier)),
            'risk_level' => (string) data_get($behaviorProfile, 'risk_level', 'Controlled'),
            'style_label' => (string) data_get($behaviorProfile, 'style_label', 'Controlled Trader'),
            'trade_count' => $journals->count(),
            'reasons' => collect(data_get($behaviorProfile, 'reasons', []))
                ->filter()
                ->take(3)
                ->values()
                ->all(),
        ];
    }

    private function reviewRiskSummary(Collection $reviewTraders): array
    {
        return [
            self::RISK_CATEGORY_ALL => [
                'label' => 'All Reviews',
                'count' => $reviewTraders->count(),
                'tone' => 'secondary',
            ],
            self::RISK_CATEGORY_HIGH => [
                'label' => 'High Risk',
                'count' => $reviewTraders->where('admin_risk_category', self::RISK_CATEGORY_HIGH)->count(),
                'tone' => 'danger',
            ],
            self::RISK_CATEGORY_MID => [
                'label' => 'Mid Risk',
                'count' => $reviewTraders->where('admin_risk_category', self::RISK_CATEGORY_MID)->count(),
                'tone' => 'warning',
            ],
            self::RISK_CATEGORY_LOW => [
                'label' => 'Low Risk',
                'count' => $reviewTraders->where('admin_risk_category', self::RISK_CATEGORY_LOW)->count(),
                'tone' => 'success',
            ],
        ];
    }

    private function filterAndSortReviewTradersByRisk(Collection $reviewTraders, string $selectedRiskCategory): Collection
    {
        if ($selectedRiskCategory !== self::RISK_CATEGORY_ALL) {
            $reviewTraders = $reviewTraders
                ->filter(fn (User $trader): bool => (string) $trader->admin_risk_category === $selectedRiskCategory)
                ->values();
        }

        return $reviewTraders
            ->sort(function (User $first, User $second): int {
                $firstProfile = (array) $first->getAttribute('admin_risk_profile');
                $secondProfile = (array) $second->getAttribute('admin_risk_profile');
                $riskComparison = ((int) ($second->admin_risk_rank ?? 0)) <=> ((int) ($first->admin_risk_rank ?? 0));

                if ($riskComparison !== 0) {
                    return $riskComparison;
                }

                $scoreComparison = ((float) ($secondProfile['score'] ?? 0)) <=> ((float) ($firstProfile['score'] ?? 0));

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                $secondRequestedAt = $second->prop_firm_review_requested_at?->getTimestamp() ?? 0;
                $firstRequestedAt = $first->prop_firm_review_requested_at?->getTimestamp() ?? 0;

                return $secondRequestedAt <=> $firstRequestedAt;
            })
            ->values();
    }

    private function normalizeReviewRiskCategory($category): string
    {
        $category = strtolower((string) $category);

        return in_array($category, [self::RISK_CATEGORY_HIGH, self::RISK_CATEGORY_MID, self::RISK_CATEGORY_LOW], true)
            ? $category
            : self::RISK_CATEGORY_ALL;
    }

    private function reviewRiskRank(string $category): int
    {
        return match ($category) {
            self::RISK_CATEGORY_HIGH => 3,
            self::RISK_CATEGORY_MID => 2,
            self::RISK_CATEGORY_LOW => 1,
            default => 0,
        };
    }

    private function reviewRiskLabel(string $category): string
    {
        return match ($category) {
            self::RISK_CATEGORY_HIGH => 'High Risk',
            self::RISK_CATEGORY_MID => 'Mid Risk',
            self::RISK_CATEGORY_LOW => 'Low Risk',
            default => 'All Reviews',
        };
    }

    private function reviewRiskTone(string $category): string
    {
        return match ($category) {
            self::RISK_CATEGORY_HIGH => 'danger',
            self::RISK_CATEGORY_MID => 'warning',
            self::RISK_CATEGORY_LOW => 'success',
            default => 'secondary',
        };
    }

    private function normalizeRequiredProfitableDays(int $requestedRequiredDays, int $currentProfitableDays): int
    {
        $requestedRequiredDays = max(1, min(3, $requestedRequiredDays));
        $currentProfitableDays = max(0, $currentProfitableDays);

        if ($requestedRequiredDays <= $currentProfitableDays && $currentProfitableDays < 3) {
            return $currentProfitableDays + 1;
        }

        return max(1, min(3, $requestedRequiredDays));
    }

    private function setRequiredProfitableDays(User $user, ?int $requiredDays): void
    {
        if (! Schema::hasColumn('users', 'prop_firm_required_profitable_days')) {
            return;
        }

        $user->prop_firm_required_profitable_days = $requiredDays ? max(1, min(3, $requiredDays)) : null;
    }

    private function archiveCurrentJournals(User $user, ?int $phase = null, string $reason = 'phase_transition'): void
    {
        if (! Schema::hasTable('trading_journals_backup')) {
            return;
        }

        $journals = TradingJournal::where('user_id', $user->id)->get();
        if ($journals->isEmpty()) {
            return;
        }

        $phase = $phase ?? (int) ($user->prop_firm_phase ?? 1);
        $archiveBatchUuid = (string) Str::uuid();
        $archivedAt = now();
        $backupHasColumn = fn (string $column): bool => Schema::hasColumn('trading_journals_backup', $column);

        foreach ($journals as $journal) {
            if ($backupHasColumn('original_journal_id') && TradingJournalBackup::where('original_journal_id', $journal->id)->exists()) {
                continue;
            }

            $payload = [
                'user_id' => $journal->user_id,
                'type' => $journal->type,
                'open_date' => $this->databaseDate($journal->getRawOriginal('open_date') ?: $journal->open_date),
                'close_date' => $this->databaseDate($journal->getRawOriginal('close_date') ?: $journal->close_date),
                'pair' => $journal->pair,
                'direction' => $journal->direction,
                'entry_price' => $journal->entry_price,
                'exit_price' => $journal->exit_price,
                'lot_size' => $journal->lot_size,
                'pips' => $journal->pips,
                'profit_loss' => $journal->profit_loss,
                'result' => $journal->result,
                'notes' => $journal->notes,
                'capital' => $journal->capital,
            ];

            if ($backupHasColumn('original_journal_id')) {
                $payload['original_journal_id'] = $journal->id;
            }

            if ($backupHasColumn('time_input_timezone')) {
                $payload['time_input_timezone'] = $journal->time_input_timezone ?? 'malaysia';
            }

            if ($backupHasColumn('time_input_offset_minutes')) {
                $payload['time_input_offset_minutes'] = $journal->time_input_offset_minutes;
            }

            if ($backupHasColumn('prop_firm_phase')) {
                $payload['prop_firm_phase'] = $phase;
            }

            if ($backupHasColumn('archive_batch_uuid')) {
                $payload['archive_batch_uuid'] = $archiveBatchUuid;
            }

            if ($backupHasColumn('archive_reason')) {
                $payload['archive_reason'] = $reason;
            }

            if ($backupHasColumn('archived_at')) {
                $payload['archived_at'] = $archivedAt;
            }

            foreach ([
                'status',
                'close_handling',
                'trading_reason_id',
                'emotion_before_entry',
                'emotion_during_trade',
                'emotion_after_trade',
                'confidence_level',
                'discipline_rating',
                'mistake_tags',
                'psychology_notes',
                'prop_firm_model_id',
                'prop_firm_phase_rule_id',
            ] as $column) {
                if ($backupHasColumn($column)) {
                    $payload[$column] = $journal->{$column};
                }
            }

            TradingJournalBackup::create($payload);
        }
    }

    private function initialCapitalForUser(User $user): float
    {
        return round((float) Capital::where('user_id', $user->id)->where('type', 1)->sum('amount'), 2);
    }

    private function databaseDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }
}
