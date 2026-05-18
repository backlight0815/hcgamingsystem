<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TradingExamAnswer;
use App\Models\TradingExamAttempt;
use App\Models\TradingExamOption;
use App\Models\TradingExamQuestion;
use App\Models\TradingExamQuotaRequest;
use App\Models\TradingPositionApplication;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingExaminationController extends Controller
{
    private const ADMIN_ROLES = [1, 2];
    private const LEADER_ROLE = TradingPositionApplication::ROLE_LEADERSHIP;
    private const TRADING_ROLES = [750, 760, 770];
    private const DAILY_QUESTION_COUNT = 5;
    private const DEFAULT_LEADER_QUESTION_LIMIT = 50;

    public function index()
    {
        $this->ensureTradingMember();

        $user = auth()->user();
        $approvedQuestionCount = TradingExamQuestion::where('status', TradingExamQuestion::STATUS_APPROVED)->count();
        $attempt = null;
        $recentAttempts = TradingExamAttempt::where('user_id', $user->id)
            ->latest('exam_date')
            ->take(10)
            ->get();

        if ($approvedQuestionCount >= self::DAILY_QUESTION_COUNT) {
            $attempt = $this->dailyAttemptFor($user);
            $attempt->load([
                'answers.question.options',
                'answers.question.correctOption',
                'answers.selectedOption',
            ]);
        }

        return view('trading_exams.daily', compact('attempt', 'approvedQuestionCount', 'recentAttempts'));
    }

    public function submitDaily(Request $request, TradingExamAttempt $attempt)
    {
        $this->ensureTradingMember();
        abort_unless((int) $attempt->user_id === (int) auth()->id(), 403);

        if ($attempt->isCompleted()) {
            return redirect()->route('trading.exams.index')->with('success', 'You have already completed today\'s knowledge check.');
        }

        $attempt->load('answers.question.options');
        $answerIds = $attempt->answers->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $data = $request->validate([
            'answers' => ['required', 'array', 'size:' . $attempt->answers->count()],
            'answers.*' => ['required', 'integer'],
        ]);

        $selectedOptions = collect($data['answers'])
            ->mapWithKeys(fn ($optionId, $answerId): array => [(int) $answerId => (int) $optionId]);

        abort_unless(empty(array_diff($answerIds, $selectedOptions->keys()->all())), 422);

        $score = 0;

        DB::transaction(function () use ($attempt, $selectedOptions, &$score): void {
            foreach ($attempt->answers as $answer) {
                $option = TradingExamOption::where('id', $selectedOptions[(int) $answer->id] ?? 0)
                    ->where('trading_exam_question_id', $answer->trading_exam_question_id)
                    ->firstOrFail();

                $isCorrect = (bool) $option->is_correct;

                $answer->update([
                    'selected_option_id' => $option->id,
                    'is_correct' => $isCorrect,
                ]);

                if ($isCorrect) {
                    $score++;
                }
            }

            $attempt->update([
                'status' => TradingExamAttempt::STATUS_COMPLETED,
                'score' => $score,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('trading.exams.index')->with('success', 'Daily trading knowledge check completed.');
    }

    public function questionBank()
    {
        $this->ensureQuestionManager();

        $user = auth()->user();
        $isAdmin = $this->currentUserIsAdmin();
        $isLeader = $this->currentUserIsLeader();

        $questionQuery = TradingExamQuestion::with(['creator', 'reviewer', 'options'])
            ->latest();

        if (! $isAdmin) {
            $questionQuery->where('created_by', $user->id);
        }

        $questions = $questionQuery->paginate(12);
        $pendingQuestions = $isAdmin
            ? TradingExamQuestion::with(['creator', 'options'])->where('status', TradingExamQuestion::STATUS_PENDING)->latest()->get()
            : collect();
        $quotaRequests = $isAdmin
            ? TradingExamQuotaRequest::with(['leader', 'reviewer'])->latest()->take(30)->get()
            : TradingExamQuotaRequest::where('leader_id', $user->id)->latest()->take(10)->get();
        $leaderLimit = $isLeader ? $this->leaderQuestionLimit($user->id) : null;
        $leaderQuestionCount = $isLeader ? $this->leaderQuestionCount($user->id) : null;
        $hasPendingQuotaRequest = $isLeader
            ? TradingExamQuotaRequest::where('leader_id', $user->id)->where('status', TradingExamQuotaRequest::STATUS_PENDING)->exists()
            : false;
        $metrics = [
            'approved' => TradingExamQuestion::where('status', TradingExamQuestion::STATUS_APPROVED)->count(),
            'pending' => TradingExamQuestion::where('status', TradingExamQuestion::STATUS_PENDING)->count(),
            'rejected' => TradingExamQuestion::where('status', TradingExamQuestion::STATUS_REJECTED)->count(),
            'attempts_today' => TradingExamAttempt::whereDate('exam_date', today())->count(),
        ];
        $difficulties = TradingExamQuestion::DIFFICULTIES;

        return view('admin.trading_exams.index', compact(
            'questions',
            'pendingQuestions',
            'quotaRequests',
            'leaderLimit',
            'leaderQuestionCount',
            'hasPendingQuotaRequest',
            'metrics',
            'difficulties',
            'isAdmin',
            'isLeader'
        ));
    }

    public function storeQuestion(Request $request)
    {
        $this->ensureQuestionManager();

        $user = auth()->user();

        if ($this->currentUserIsLeader() && $this->leaderQuestionCount($user->id) >= $this->leaderQuestionLimit($user->id)) {
            return back()
                ->withErrors(['question_text' => 'You have reached your approved question limit. Please submit a quota increase request before adding more questions.'])
                ->withInput();
        }

        $data = $this->validatedQuestion($request);
        $status = $this->currentUserIsAdmin()
            ? TradingExamQuestion::STATUS_APPROVED
            : TradingExamQuestion::STATUS_PENDING;

        $question = DB::transaction(function () use ($data, $status, $user): TradingExamQuestion {
            $question = TradingExamQuestion::create([
                'created_by' => $user->id,
                'reviewed_by' => $status === TradingExamQuestion::STATUS_APPROVED ? $user->id : null,
                'category' => $data['category'] ?? null,
                'difficulty' => $data['difficulty'],
                'question_text' => $data['question_text'],
                'explanation' => $data['explanation'] ?? null,
                'status' => $status,
                'reviewed_at' => $status === TradingExamQuestion::STATUS_APPROVED ? now() : null,
            ]);

            $this->syncOptions($question, $data['options'], (int) $data['correct_option']);

            return $question;
        });

        if ($question->isPending()) {
            AppNotificationService::notifyAdmins(
                'Trading exam question pending review',
                $user->name . ' submitted a multiple-choice trading exam question.',
                route('admin.trading.exams.index'),
                'trading_exam'
            );
        }

        return redirect()
            ->route('admin.trading.exams.index')
            ->with('success', $question->isApproved() ? 'Question published to the trading exam pool.' : 'Question submitted for administration review.');
    }

    public function editQuestion(TradingExamQuestion $question)
    {
        $this->ensureCanManageQuestion($question);

        $question->load('options');
        $difficulties = TradingExamQuestion::DIFFICULTIES;
        $isAdmin = $this->currentUserIsAdmin();

        return view('admin.trading_exams.edit', compact('question', 'difficulties', 'isAdmin'));
    }

    public function updateQuestion(Request $request, TradingExamQuestion $question)
    {
        $this->ensureCanManageQuestion($question);

        $data = $this->validatedQuestion($request);
        $status = $this->currentUserIsAdmin()
            ? TradingExamQuestion::STATUS_APPROVED
            : TradingExamQuestion::STATUS_PENDING;

        DB::transaction(function () use ($question, $data, $status): void {
            $question->update([
                'category' => $data['category'] ?? null,
                'difficulty' => $data['difficulty'],
                'question_text' => $data['question_text'],
                'explanation' => $data['explanation'] ?? null,
                'status' => $status,
                'reviewed_by' => $status === TradingExamQuestion::STATUS_APPROVED ? auth()->id() : null,
                'reviewed_at' => $status === TradingExamQuestion::STATUS_APPROVED ? now() : null,
                'review_note' => null,
            ]);

            $question->options()->delete();
            $this->syncOptions($question, $data['options'], (int) $data['correct_option']);
        });

        if ($status === TradingExamQuestion::STATUS_PENDING) {
            AppNotificationService::notifyAdmins(
                'Trading exam question updated for review',
                auth()->user()->name . ' updated a trading exam question that needs approval.',
                route('admin.trading.exams.index'),
                'trading_exam'
            );
        }

        return redirect()
            ->route('admin.trading.exams.index')
            ->with('success', $status === TradingExamQuestion::STATUS_APPROVED ? 'Question updated and approved.' : 'Question updated and sent for review.');
    }

    public function approveQuestion(Request $request, TradingExamQuestion $question)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $question->update([
            'status' => TradingExamQuestion::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? 'Approved for the trading examination pool.',
        ]);

        AppNotificationService::notifyUser(
            (int) $question->created_by,
            'Trading exam question approved',
            'Your question has been approved and added to the daily exam pool.',
            route('admin.trading.exams.index'),
            'trading_exam'
        );

        return back()->with('success', 'Question approved.');
    }

    public function rejectQuestion(Request $request, TradingExamQuestion $question)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:3000'],
        ]);

        $question->update([
            'status' => TradingExamQuestion::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        AppNotificationService::notifyUser(
            (int) $question->created_by,
            'Trading exam question reviewed',
            'Your question was not approved. Please review the admin note.',
            route('admin.trading.exams.index'),
            'trading_exam'
        );

        return back()->with('success', 'Question rejected with review note.');
    }

    public function destroyQuestion(TradingExamQuestion $question)
    {
        $this->ensureCanManageQuestion($question);

        if (TradingExamAnswer::where('trading_exam_question_id', $question->id)->exists()) {
            return back()->withErrors(['question_text' => 'This question already appears in trader exam history, so it cannot be deleted. You may edit or reject it instead.']);
        }

        $question->delete();

        return back()->with('success', 'Question removed from the bank.');
    }

    public function requestQuota(Request $request)
    {
        $this->ensureLeader();

        $user = auth()->user();
        $currentLimit = $this->leaderQuestionLimit($user->id);

        $data = $request->validate([
            'requested_limit' => ['required', 'integer', 'min:' . ($currentLimit + 1), 'max:1000'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        if (TradingExamQuotaRequest::where('leader_id', $user->id)->where('status', TradingExamQuotaRequest::STATUS_PENDING)->exists()) {
            return back()->withErrors(['requested_limit' => 'You already have a quota increase request pending review.']);
        }

        TradingExamQuotaRequest::create([
            'leader_id' => $user->id,
            'current_limit' => $currentLimit,
            'requested_limit' => $data['requested_limit'],
            'reason' => $data['reason'],
            'status' => TradingExamQuotaRequest::STATUS_PENDING,
        ]);

        AppNotificationService::notifyAdmins(
            'Trading exam quota increase requested',
            $user->name . ' requested a question bank limit increase to ' . $data['requested_limit'] . '.',
            route('admin.trading.exams.index'),
            'trading_exam'
        );

        return back()->with('success', 'Question quota increase request submitted.');
    }

    public function approveQuota(Request $request, TradingExamQuotaRequest $quotaRequest)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $quotaRequest->update([
            'status' => TradingExamQuotaRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? 'Quota increase approved.',
        ]);

        AppNotificationService::notifyUser(
            (int) $quotaRequest->leader_id,
            'Trading exam question quota approved',
            'Your question bank limit has been increased to ' . $quotaRequest->requested_limit . '.',
            route('admin.trading.exams.index'),
            'trading_exam'
        );

        return back()->with('success', 'Quota request approved.');
    }

    public function rejectQuota(Request $request, TradingExamQuotaRequest $quotaRequest)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:3000'],
        ]);

        $quotaRequest->update([
            'status' => TradingExamQuotaRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        AppNotificationService::notifyUser(
            (int) $quotaRequest->leader_id,
            'Trading exam question quota reviewed',
            'Your question bank limit request was not approved. Please review the admin note.',
            route('admin.trading.exams.index'),
            'trading_exam'
        );

        return back()->with('success', 'Quota request rejected.');
    }

    private function dailyAttemptFor($user): TradingExamAttempt
    {
        return DB::transaction(function () use ($user): TradingExamAttempt {
            $attempt = TradingExamAttempt::where('user_id', $user->id)
                ->whereDate('exam_date', today())
                ->lockForUpdate()
                ->first();

            if ($attempt) {
                return $attempt;
            }

            $questions = TradingExamQuestion::where('status', TradingExamQuestion::STATUS_APPROVED)
                ->inRandomOrder()
                ->limit(self::DAILY_QUESTION_COUNT)
                ->get();

            $attempt = TradingExamAttempt::create([
                'user_id' => $user->id,
                'exam_date' => today(),
                'status' => TradingExamAttempt::STATUS_IN_PROGRESS,
                'total_questions' => $questions->count(),
            ]);

            foreach ($questions as $question) {
                TradingExamAnswer::create([
                    'trading_exam_attempt_id' => $attempt->id,
                    'trading_exam_question_id' => $question->id,
                ]);
            }

            return $attempt;
        });
    }

    private function validatedQuestion(Request $request): array
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['required', Rule::in(array_keys(TradingExamQuestion::DIFFICULTIES))],
            'question_text' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['nullable', 'string', 'max:2000'],
            'correct_option' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $data['options'] = collect($data['options'])
            ->map(fn ($option): string => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        if (count($data['options']) < 2) {
            throw ValidationException::withMessages([
                'options' => 'At least two answer choices are required.',
            ]);
        }

        if (! array_key_exists((int) $data['correct_option'], $data['options'])) {
            throw ValidationException::withMessages([
                'correct_option' => 'The correct answer must match one of the answer choices.',
            ]);
        }

        return $data;
    }

    private function syncOptions(TradingExamQuestion $question, array $options, int $correctIndex): void
    {
        foreach ($options as $index => $optionText) {
            TradingExamOption::create([
                'trading_exam_question_id' => $question->id,
                'option_text' => $optionText,
                'is_correct' => $index === $correctIndex,
                'position' => $index + 1,
            ]);
        }
    }

    private function leaderQuestionLimit(int $leaderId): int
    {
        $approvedLimit = TradingExamQuotaRequest::where('leader_id', $leaderId)
            ->where('status', TradingExamQuotaRequest::STATUS_APPROVED)
            ->max('requested_limit');

        return max(self::DEFAULT_LEADER_QUESTION_LIMIT, (int) $approvedLimit);
    }

    private function leaderQuestionCount(int $leaderId): int
    {
        return TradingExamQuestion::where('created_by', $leaderId)->count();
    }

    private function ensureQuestionManager(): void
    {
        abort_unless($this->currentUserIsAdmin() || $this->currentUserIsLeader(), 403);
    }

    private function ensureCanManageQuestion(TradingExamQuestion $question): void
    {
        $this->ensureQuestionManager();

        if ($this->currentUserIsAdmin()) {
            return;
        }

        abort_unless((int) $question->created_by === (int) auth()->id(), 403);
    }

    private function ensureTradingMember(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, self::TRADING_ROLES, true), 403);
    }

    private function ensureLeader(): void
    {
        abort_unless($this->currentUserIsLeader(), 403);
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->currentUserIsAdmin(), 403);
    }

    private function currentUserIsAdmin(): bool
    {
        return auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true);
    }

    private function currentUserIsLeader(): bool
    {
        return auth()->check() && (int) auth()->user()->role_id === self::LEADER_ROLE;
    }
}
