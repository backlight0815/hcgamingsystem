<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\PropFirmEvaluationQuestion;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FundedTraderController extends Controller
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    public function AllFundedTrader()
    {
        $this->ensureAdmin();

        $reviewTraders = User::withCount([
                'propFirmEvaluationQuestions as open_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_OPEN),
                'propFirmEvaluationQuestions as answered_questions_count' => fn ($query) => $query->where('status', PropFirmEvaluationQuestion::STATUS_ANSWERED),
            ])
            ->where('role_id', 750)
            ->whereIn('prop_firm_review_status', ['pending_phase2', 'pending_funded'])
            ->orderByDesc('prop_firm_review_requested_at')
            ->get();

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

        if ($reviewStatus === 'pending_phase2') {
            $this->archiveCurrentJournals($user);
            TradingJournal::where('user_id', $user->id)->delete();

            $user->prop_firm_phase = 2;
            $user->funded_status = self::STATUS_PENDING;
            $user->prop_firm_review_status = 'approved_phase2';
            $user->prop_firm_review_phase = null;
            $user->prop_firm_trade_locked = false;
            $user->prop_firm_review_note = 'Phase 2 approved by administration.';
            $user->prop_firm_review_approved_at = now();
            $user->save();

            return back()->with('success', 'Phase 1 review approved. Trader has been moved to Phase 2 and trading is unlocked.');
        }

        if ($reviewStatus === 'pending_funded') {
            $this->archiveCurrentJournals($user);
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

    private function archiveCurrentJournals(User $user): void
    {
        $journals = TradingJournal::where('user_id', $user->id)->get();

        foreach ($journals as $journal) {
            TradingJournalBackup::create([
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
            ]);
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
