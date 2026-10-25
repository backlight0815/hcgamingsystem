<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\FinalExamAttempt;
use App\Models\FinalExamSetting;
use App\Models\PropFirmModel;
use App\Services\FinalExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class FinalExaminationController extends Controller
{
    public function intro(Request $request, FinalExamService $examService): View
    {
        $user = $request->user();
        $setting = FinalExamSetting::current();
        $attempt = $examService->currentAttempt($user);
        $retake = $examService->canStartOrRetake($user);
        $recentAttempts = FinalExamAttempt::where('user_id', $user->id)
            ->latest('id')
            ->take(8)
            ->get();
        $questionCounts = $examService->activeQuestionCounts();
        $requiredCounts = FinalExamService::CATEGORY_COUNTS;
        $passingScore = $examService->passingScore(50);

        return view('final_exam.intro', compact(
            'user',
            'setting',
            'attempt',
            'retake',
            'recentAttempts',
            'questionCounts',
            'requiredCounts',
            'passingScore'
        ));
    }

    public function start(Request $request, FinalExamService $examService): RedirectResponse
    {
        if ((bool) $request->user()->final_exam_passed) {
            return redirect()->route('final_exam.prop_firm_selection');
        }

        try {
            $examService->startOrResumeAttempt($request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['final_exam' => $exception->getMessage()]);
        }

        return redirect()->route('final_exam.attempt');
    }

    public function attempt(Request $request, FinalExamService $examService): View|RedirectResponse
    {
        $user = $request->user();

        if ((bool) $user->final_exam_passed) {
            return redirect()->route('final_exam.prop_firm_selection');
        }

        $attempt = $examService->currentAttempt($user);

        if (! $attempt) {
            return redirect()->route('final_exam.intro')
                ->with([
                    'message' => 'Start the Final Examination when you are ready.',
                    'alert-type' => 'info',
                ]);
        }

        $attempt->load('attemptQuestions.question');
        $requiredCounts = FinalExamService::CATEGORY_COUNTS;

        return view('final_exam.attempt', compact('attempt', 'requiredCounts'));
    }

    public function submit(Request $request, FinalExamAttempt $attempt, FinalExamService $examService): RedirectResponse
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);

        if (! $attempt->isInProgress()) {
            return redirect()->route('final_exam.result', $attempt);
        }

        $attempt->load('attemptQuestions');
        $attemptQuestionIds = $attempt->attemptQuestions->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $data = $request->validate([
            'answers' => ['required', 'array', 'size:'.count($attemptQuestionIds)],
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $submittedIds = collect($data['answers'])->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $expectedIds = collect($attemptQuestionIds)->sort()->values()->all();

        if ($submittedIds !== $expectedIds) {
            return back()
                ->withErrors(['answers' => 'Please answer all '.$attempt->total_questions.' Final Examination questions before submitting.'])
                ->withInput();
        }

        $attempt = $examService->submitAttempt($attempt, $data['answers']);

        return redirect()->route('final_exam.result', $attempt);
    }

    public function result(Request $request, FinalExamAttempt $attempt, FinalExamService $examService): View
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id || $this->isAdmin($request), 403);

        $attempt->load('attemptQuestions.question', 'user');
        $breakdown = $examService->categoryBreakdown($attempt);
        $setting = FinalExamSetting::current();
        $showReview = (bool) $setting->show_review_after_submission || $this->isAdmin($request);
        $passingScore = $examService->passingScore((int) $attempt->total_questions);

        return view('final_exam.result', compact('attempt', 'breakdown', 'setting', 'showReview', 'passingScore'));
    }

    public function propFirmSelection(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! (bool) $user->final_exam_passed) {
            return redirect()->route('final_exam.intro')
                ->with([
                    'message' => 'Please pass the Final Examination before selecting a prop firm model.',
                    'alert-type' => 'warning',
                ]);
        }

        $models = PropFirmModel::with(['phaseRules' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $preference = $user->userPropFirmPreference()->with('propFirmModel', 'propFirmPhaseRule')->first();

        return view('final_exam.prop_firm_selection', compact('user', 'models', 'preference'));
    }

    public function savePropFirmSelection(Request $request, FinalExamService $examService): RedirectResponse
    {
        $user = $request->user();

        if (! (bool) $user->final_exam_passed) {
            return redirect()->route('final_exam.intro')
                ->with([
                    'message' => 'Please pass the Final Examination before selecting a prop firm model.',
                    'alert-type' => 'warning',
                ]);
        }

        $data = $request->validate([
            'prop_firm_model_id' => ['required', 'integer', Rule::exists('prop_firm_models', 'id')->where('is_active', true)],
        ]);

        $model = PropFirmModel::where('is_active', true)->findOrFail($data['prop_firm_model_id']);
        $examService->savePropFirmPreference($user, $model);

        return redirect()
            ->route('all.trading.journals')
            ->with([
                'message' => 'Prop firm model selected. Your prop firm section is now unlocked.',
                'alert-type' => 'success',
            ]);
    }

    private function isAdmin(Request $request): bool
    {
        return in_array((int) ($request->user()->role_id ?? 0), FinalExamService::ADMIN_ROLES, true);
    }
}
