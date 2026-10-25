<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\FinalExamAttempt;
use App\Models\FinalExamSetting;
use App\Models\PropFirmModel;
use App\Services\FinalExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class FinalExamController extends MobileController
{
    public function status(Request $request, FinalExamService $service): JsonResponse
    {
        $user = $request->user();
        $current = $service->currentAttempt($user);

        return $this->success([
            'required' => $service->requiresExam($user),
            'passed' => (bool) $user->final_exam_passed,
            'unlocked' => (bool) $user->prop_firm_unlocked,
            'availability' => $service->canStartOrRetake($user),
            'settings' => FinalExamSetting::current()->only([
                'passing_percentage',
                'allow_immediate_retake',
                'retake_cooldown_hours',
                'show_review_after_submission',
            ]),
            'current_attempt' => $current ? $this->attemptPayload($current) : null,
            'recent_attempts' => FinalExamAttempt::where('user_id', $user->id)
                ->where('status', '!=', FinalExamAttempt::STATUS_IN_PROGRESS)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (FinalExamAttempt $attempt): array => $this->attemptPayload($attempt, false))
                ->values(),
            'prop_firm_models' => $this->propFirmModels(),
        ], 'Final Examination status loaded.');
    }

    public function start(Request $request, FinalExamService $service): JsonResponse
    {
        try {
            $attempt = $service->startOrResumeAttempt($request->user());
        } catch (RuntimeException $exception) {
            return $this->failure($exception->getMessage(), [], 422);
        }

        return $this->success($this->attemptPayload($attempt), 'Final Examination started.');
    }

    public function submit(Request $request, FinalExamAttempt $attempt, FinalExamService $service): JsonResponse
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 404);
        abort_unless($attempt->isInProgress(), 422, 'This examination has already been submitted.');

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'string', Rule::in(['A', 'B', 'C', 'D'])],
        ]);
        $attempt->loadMissing('attemptQuestions');
        if (count($data['answers']) !== $attempt->attemptQuestions->count()) {
            return $this->failure('Answer every question before submitting.', [
                'answers' => ['Answer every question before submitting.'],
            ], 422);
        }

        $submitted = $service->submitAttempt($attempt, $data['answers']);

        return $this->success([
            'attempt' => $this->attemptPayload($submitted, true),
            'category_breakdown' => $service->categoryBreakdown($submitted),
            'prop_firm_models' => $submitted->isPassed() ? $this->propFirmModels() : [],
        ], $submitted->isPassed() ? 'Congratulations, you passed the Final Examination.' : 'Final Examination submitted.');
    }

    public function selectPropFirm(Request $request, FinalExamService $service): JsonResponse
    {
        abort_unless((bool) $request->user()->final_exam_passed && (bool) $request->user()->prop_firm_unlocked, 403);
        $data = $request->validate([
            'prop_firm_model_id' => [
                'required',
                'integer',
                Rule::exists('prop_firm_models', 'id')->where('is_active', true),
            ],
        ]);
        $model = PropFirmModel::where('is_active', true)->findOrFail($data['prop_firm_model_id']);
        $preference = $service->savePropFirmPreference($request->user(), $model);

        return $this->success([
            'preference' => $preference,
            'model' => $model->load(['phaseRules' => fn ($query) => $query->where('is_active', true)]),
        ], 'Prop firm model selected.');
    }

    private function attemptPayload(FinalExamAttempt $attempt, bool $includeReview = false): array
    {
        $attempt->loadMissing('attemptQuestions.question');
        $showReview = $includeReview && (bool) FinalExamSetting::current()->show_review_after_submission;

        return [
            'id' => (string) $attempt->id,
            'status' => $attempt->status,
            'total_questions' => (int) $attempt->total_questions,
            'correct_answers' => (int) $attempt->correct_answers,
            'wrong_answers' => (int) $attempt->wrong_answers,
            'score_percentage' => (float) $attempt->score_percentage,
            'started_at' => $attempt->started_at,
            'submitted_at' => $attempt->submitted_at,
            'questions' => $attempt->attemptQuestions->map(function ($attemptQuestion) use ($showReview): array {
                $question = $attemptQuestion->question;

                return [
                    'attempt_question_id' => (string) $attemptQuestion->id,
                    'position' => (int) $attemptQuestion->position,
                    'category' => $question?->category,
                    'subcategory' => $question?->subcategory,
                    'question_type' => $question?->question_type,
                    'question' => $question?->question,
                    'scenario_text' => $question?->scenario_text,
                    'chart_image_url' => $question?->chart_image_path ? url($question->chart_image_path) : null,
                    'chart_data' => $question?->chart_data_json,
                    'chart_timeframe' => $question?->chart_timeframe,
                    'difficulty' => $question?->difficulty,
                    'options' => [
                        'A' => $question?->option_a,
                        'B' => $question?->option_b,
                        'C' => $question?->option_c,
                        'D' => $question?->option_d,
                    ],
                    'selected_answer' => $attemptQuestion->selected_answer,
                    'is_correct' => $showReview ? $attemptQuestion->is_correct : null,
                    'correct_answer' => $showReview ? $question?->correct_answer : null,
                    'explanation' => $showReview ? $question?->explanation : null,
                ];
            })->values(),
        ];
    }

    private function propFirmModels()
    {
        return PropFirmModel::with(['phaseRules' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
