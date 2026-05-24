<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\PropFirmEvaluationQuestion;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use App\Services\TradingJournalAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FundedTraderController extends Controller
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const REVIEW_PHASE1_PROFIT_TARGET = 'phase1_profit_target_review';
    public const REVIEW_PHASE1_PROFITABLE_DAYS_REQUIRED = 'phase1_profitable_days_required';

    public function AllFundedTrader()
    {
        $this->ensureAdmin();

        $reviewTraders = User::withCount([
                'propFirmEvaluationQuestions as open_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_OPEN),
                'propFirmEvaluationQuestions as answered_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_ANSWERED),
            ])
            ->where('role_id', 750)
            ->whereIn('prop_firm_review_status', [self::REVIEW_PHASE1_PROFIT_TARGET, 'pending_phase2', 'pending_funded', 'daily_loss_review', 'total_loss_review'])
            ->orderByDesc('prop_firm_review_requested_at')
            ->get();

        $analytics = app(TradingJournalAnalytics::class);
        $reviewTraders->each(function (User $trader) use ($analytics): void {
            if ((string) $trader->prop_firm_review_status !== self::REVIEW_PHASE1_PROFIT_TARGET) {
                return;
            }

            $journals = TradingJournal::where('user_id', $trader->id)->get();
            $trader->setAttribute('phase1_profitable_day_rule', $analytics->profitableDayRule($journals, 3));
        });

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
            'fundedTraders',
            'rejectedTraders',
            'questions'
        ));
    }

    public function approve($id)
    {
        $this->ensureAdmin();

        $user = User::where('role_id', 750)->findOrFail($id);
        $reviewStatus = (string) ($user->prop_firm_review_status ?? 'none');

        if ($reviewStatus === self::REVIEW_PHASE1_PROFIT_TARGET || $reviewStatus === 'pending_phase2') {
            $this->archiveCurrentJournals($user, 1, 'phase_1_approved_to_phase_2');
            TradingJournal::where('user_id', $user->id)->delete();

            $user->prop_firm_phase = 2;
            $user->funded_status = self::STATUS_PENDING;
            $user->prop_firm_review_status = 'approved_phase2';
            $user->prop_firm_review_phase = null;
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

        if ($reviewStatus === 'pending_funded') {
            $this->archiveCurrentJournals($user, 2, 'phase_2_approved_to_funded');
            TradingJournal::where('user_id', $user->id)->delete();

            $user->prop_firm_phase = 3;
            $user->funded_status = self::STATUS_APPROVED;
            $user->status = 1;
            $user->prop_firm_review_status = 'funded_approved';
            $user->prop_firm_review_phase = null;
            $user->prop_firm_trade_locked = false;
            $user->prop_firm_review_note = 'Funded account approved by administration.';
            $user->prop_firm_review_approved_at = now();
            $user->save();

            return back()->with('success', 'Phase 2 review approved. Trader has been moved to funded account status.');
        }

        return back()->with('info', 'No pending prop firm review was found for this trader.');
    }

    public function requirePhaseOneProfitableDays(Request $request, $id)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('role_id', 750)->findOrFail($id);
        if ((string) ($user->prop_firm_review_status ?? 'none') !== self::REVIEW_PHASE1_PROFIT_TARGET) {
            return back()->with('info', 'No Phase 1 profit target review was found for this trader.');
        }

        $user->prop_firm_review_status = self::REVIEW_PHASE1_PROFITABLE_DAYS_REQUIRED;
        $user->prop_firm_review_phase = 1;
        $user->prop_firm_trade_locked = false;
        $user->prop_firm_review_note = $data['review_note']
            ?? 'Phase 1 profit target reached. Administration requires 3 profitable days before Phase 2 approval.';
        $user->prop_firm_review_approved_at = null;
        $user->save();

        return back()->with('success', 'Trader has been unlocked and must complete 3 profitable days before Phase 2 approval.');
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

            TradingJournalBackup::create($payload);
        }
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
