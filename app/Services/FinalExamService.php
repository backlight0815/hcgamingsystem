<?php

namespace App\Services;

use App\Models\FinalExamAttempt;
use App\Models\FinalExamAttemptQuestion;
use App\Models\FinalExamQuestion;
use App\Models\FinalExamSetting;
use App\Models\PropFirmModel;
use App\Models\PropFirmPhaseRule;
use App\Models\User;
use App\Models\UserPropFirmPreference;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FinalExamService
{
    public const ADMIN_ROLES = [1, 2];

    public const CATEGORY_COUNTS = [
        FinalExamQuestion::CATEGORY_FOUNDATION => 10,
        FinalExamQuestion::CATEGORY_TECHNIQUE => 25,
        FinalExamQuestion::CATEGORY_PSYCHOLOGY => 15,
    ];

    public const MIN_TECHNIQUE_CHART_QUESTIONS = 12;

    public const MIN_PSYCHOLOGY_CHART_SCENARIO_QUESTIONS = 10;

    public function requiresExam(?User $user): bool
    {
        if (! $user || $this->isAdmin($user) || ! $this->schemaReady()) {
            return false;
        }

        $settings = FinalExamSetting::current();

        if (! $settings->is_active) {
            return false;
        }

        return ! ((bool) $user->final_exam_passed && (bool) $user->prop_firm_unlocked);
    }

    public function canStartOrRetake(User $user): array
    {
        if ((bool) $user->final_exam_passed) {
            return [
                'allowed' => false,
                'message' => 'You have already passed the Final Examination.',
                'available_at' => null,
            ];
        }

        if ($this->currentAttempt($user)) {
            return [
                'allowed' => true,
                'message' => null,
                'available_at' => null,
            ];
        }

        $settings = FinalExamSetting::current();

        if ((bool) $settings->allow_immediate_retake) {
            return [
                'allowed' => true,
                'message' => null,
                'available_at' => null,
            ];
        }

        $lastFailed = FinalExamAttempt::where('user_id', $user->id)
            ->where('status', FinalExamAttempt::STATUS_FAILED)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if (! $lastFailed) {
            return [
                'allowed' => true,
                'message' => null,
                'available_at' => null,
            ];
        }

        $availableAt = $lastFailed->submitted_at?->copy()->addHours((int) $settings->retake_cooldown_hours);

        if ($availableAt instanceof CarbonInterface && now()->lt($availableAt)) {
            return [
                'allowed' => false,
                'message' => 'Your next retake will be available after '.$availableAt->format('Y-m-d H:i').'.',
                'available_at' => $availableAt,
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
            'available_at' => null,
        ];
    }

    public function currentAttempt(User $user): ?FinalExamAttempt
    {
        return FinalExamAttempt::with('attemptQuestions.question')
            ->where('user_id', $user->id)
            ->where('status', FinalExamAttempt::STATUS_IN_PROGRESS)
            ->where('total_questions', $this->expectedQuestionTotal())
            ->latest('id')
            ->first();
    }

    public function startOrResumeAttempt(User $user): FinalExamAttempt
    {
        $retake = $this->canStartOrRetake($user);

        if (! $retake['allowed']) {
            throw new RuntimeException($retake['message'] ?? 'Final Examination is not available yet.');
        }

        return DB::transaction(function () use ($user): FinalExamAttempt {
            FinalExamAttempt::where('user_id', $user->id)
                ->where('status', FinalExamAttempt::STATUS_IN_PROGRESS)
                ->where('total_questions', '!=', $this->expectedQuestionTotal())
                ->delete();

            $attempt = FinalExamAttempt::where('user_id', $user->id)
                ->where('status', FinalExamAttempt::STATUS_IN_PROGRESS)
                ->where('total_questions', $this->expectedQuestionTotal())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($attempt) {
                return $attempt->load('attemptQuestions.question');
            }

            $questions = $this->selectQuestions();

            $attempt = FinalExamAttempt::create([
                'user_id' => $user->id,
                'total_questions' => $questions->count(),
                'status' => FinalExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            foreach ($questions->values() as $index => $question) {
                FinalExamAttemptQuestion::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'position' => $index + 1,
                    'sort_order' => $index + 1,
                ]);
            }

            return $attempt->load('attemptQuestions.question');
        });
    }

    public function submitAttempt(FinalExamAttempt $attempt, array $answers): FinalExamAttempt
    {
        if (! $attempt->isInProgress()) {
            return $attempt->load('attemptQuestions.question');
        }

        return DB::transaction(function () use ($attempt, $answers): FinalExamAttempt {
            $attempt->load('attemptQuestions.question');
            $correctAnswers = 0;

            foreach ($attempt->attemptQuestions as $attemptQuestion) {
                $answer = strtoupper((string) ($answers[$attemptQuestion->id] ?? ''));
                $question = $attemptQuestion->question;
                $isCorrect = $question && $answer === strtoupper((string) $question->correct_answer);

                $attemptQuestion->update([
                    'selected_answer' => $answer,
                    'is_correct' => $isCorrect,
                ]);

                if ($isCorrect) {
                    $correctAnswers++;
                }
            }

            $totalQuestions = max(1, (int) $attempt->total_questions);
            $wrongAnswers = max(0, $totalQuestions - $correctAnswers);
            $scorePercentage = round(($correctAnswers / $totalQuestions) * 100, 2);
            $settings = FinalExamSetting::current();
            $status = $scorePercentage >= (int) $settings->passing_percentage
                ? FinalExamAttempt::STATUS_PASSED
                : FinalExamAttempt::STATUS_FAILED;

            $attempt->update([
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'score_percentage' => $scorePercentage,
                'status' => $status,
                'submitted_at' => now(),
            ]);

            if ($status === FinalExamAttempt::STATUS_PASSED) {
                $attempt->user?->update([
                    'final_exam_passed' => true,
                    'final_exam_passed_at' => now(),
                    'prop_firm_unlocked' => true,
                ]);
            }

            return $attempt->fresh('attemptQuestions.question', 'user');
        });
    }

    public function categoryBreakdown(FinalExamAttempt $attempt): array
    {
        $attempt->loadMissing('attemptQuestions.question');

        return $attempt->attemptQuestions
            ->groupBy(fn (FinalExamAttemptQuestion $attemptQuestion): string => $attemptQuestion->question?->category ?: 'Uncategorized')
            ->map(function (Collection $questions, string $category): array {
                $total = $questions->count();
                $correct = $questions->where('is_correct', true)->count();

                return [
                    'category' => $category,
                    'total' => $total,
                    'correct' => $correct,
                    'wrong' => max(0, $total - $correct),
                    'percentage' => $total > 0 ? (int) round(($correct / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    public function passingScore(int $totalQuestions = 50): int
    {
        $settings = $this->schemaReady() ? FinalExamSetting::current() : new FinalExamSetting(['passing_percentage' => 60]);

        return (int) ceil($totalQuestions * ((int) $settings->passing_percentage / 100));
    }

    public function activeQuestionCounts(): array
    {
        if (! Schema::hasTable('final_exam_questions')) {
            return [];
        }

        return FinalExamQuestion::active()
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function savePropFirmPreference(User $user, PropFirmModel $model): UserPropFirmPreference
    {
        $phaseRule = PropFirmPhaseRule::where('prop_firm_model_id', $model->id)
            ->where('is_active', true)
            ->orderBy('phase_number')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $user->forceFill([
            'prop_firm_model_id' => $model->id,
            'prop_firm_phase_rule_id' => $phaseRule?->id,
            'prop_firm_phase' => $phaseRule?->phase_number ?? 1,
            'prop_firm_unlocked' => true,
        ])->save();

        return UserPropFirmPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'prop_firm_model_id' => $model->id,
                'prop_firm_phase_rule_id' => $phaseRule?->id,
                'prop_firm_model' => $model->name,
                'selected_at' => now(),
            ]
        );
    }

    private function selectQuestions(): Collection
    {
        $selected = collect();
        $selectedIds = [];

        foreach (self::CATEGORY_COUNTS as $category => $requiredCount) {
            if ($category === FinalExamQuestion::CATEGORY_TECHNIQUE) {
                $questions = $this->selectCategoryWithTypeMinimum(
                    $category,
                    $requiredCount,
                    FinalExamQuestion::TYPE_CHART_MCQ,
                    self::MIN_TECHNIQUE_CHART_QUESTIONS,
                    $selectedIds
                );
            } elseif ($category === FinalExamQuestion::CATEGORY_PSYCHOLOGY) {
                $questions = $this->selectCategoryWithTypeMinimum(
                    $category,
                    $requiredCount,
                    FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ,
                    self::MIN_PSYCHOLOGY_CHART_SCENARIO_QUESTIONS,
                    $selectedIds
                );
            } else {
                $questions = FinalExamQuestion::active()
                    ->where('category', $category)
                    ->whereNotIn('id', $selectedIds)
                    ->inRandomOrder()
                    ->limit($requiredCount)
                    ->get();
            }

            if ($questions->count() < $requiredCount) {
                throw new RuntimeException(sprintf(
                    'Not enough active Final Examination questions for %s. Required %d, available %d.',
                    $category,
                    $requiredCount,
                    $questions->count()
                ));
            }

            $selectedIds = array_merge($selectedIds, $questions->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $selected = $selected->merge($questions);
        }

        return $selected->values();
    }

    private function expectedQuestionTotal(): int
    {
        return array_sum(self::CATEGORY_COUNTS);
    }

    private function selectCategoryWithTypeMinimum(string $category, int $requiredCount, string $questionType, int $minimumTypeCount, array $excludedIds): Collection
    {
        $availableTypeCount = FinalExamQuestion::active()
            ->where('category', $category)
            ->where('question_type', $questionType)
            ->whereNotIn('id', $excludedIds)
            ->count();
        $typeCount = min($minimumTypeCount, $availableTypeCount, $requiredCount);

        $typedQuestions = FinalExamQuestion::active()
            ->where('category', $category)
            ->where('question_type', $questionType)
            ->whereNotIn('id', $excludedIds)
            ->inRandomOrder()
            ->limit($typeCount)
            ->get();

        $remaining = $requiredCount - $typedQuestions->count();
        $remainingExcluded = array_merge($excludedIds, $typedQuestions->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $otherQuestions = FinalExamQuestion::active()
            ->where('category', $category)
            ->whereNotIn('id', $remainingExcluded)
            ->inRandomOrder()
            ->limit($remaining)
            ->get();

        return $typedQuestions->merge($otherQuestions)->values();
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('final_exam_settings')
            && Schema::hasColumn('users', 'final_exam_passed')
            && Schema::hasColumn('users', 'prop_firm_unlocked');
    }

    private function isAdmin(User $user): bool
    {
        return in_array((int) $user->role_id, self::ADMIN_ROLES, true);
    }
}
