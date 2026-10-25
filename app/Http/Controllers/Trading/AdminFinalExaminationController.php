<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\FinalExamAttempt;
use App\Models\FinalExamAttemptQuestion;
use App\Models\FinalExamQuestion;
use App\Models\FinalExamSetting;
use App\Services\FinalExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminFinalExaminationController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $filters = $request->only(['category', 'subcategory', 'difficulty', 'question_type', 'status', 'search']);
        $query = FinalExamQuestion::query()->latest('id');

        foreach (['category', 'difficulty', 'question_type', 'status'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['subcategory'])) {
            $query->where('subcategory', 'like', '%'.$filters['subcategory'].'%');
        }

        if (! empty($filters['search'])) {
            $query->where('question', 'like', '%'.$filters['search'].'%');
        }

        $questions = $query->paginate(20)->withQueryString();
        $metrics = [
            'total' => FinalExamQuestion::count(),
            'active' => FinalExamQuestion::where('status', FinalExamQuestion::STATUS_ACTIVE)->count(),
            'inactive' => FinalExamQuestion::where('status', FinalExamQuestion::STATUS_INACTIVE)->count(),
            'attempts' => FinalExamAttempt::count(),
            'passed' => FinalExamAttempt::where('status', FinalExamAttempt::STATUS_PASSED)->count(),
            'failed' => FinalExamAttempt::where('status', FinalExamAttempt::STATUS_FAILED)->count(),
            'chart_questions' => FinalExamQuestion::whereIn('question_type', [FinalExamQuestion::TYPE_CHART_MCQ, FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ])->count(),
        ];
        $categoryCounts = FinalExamQuestion::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        return view('admin.final_exam.questions.index', [
            'questions' => $questions,
            'metrics' => $metrics,
            'categoryCounts' => $categoryCounts,
            'categories' => FinalExamQuestion::CATEGORIES,
            'difficulties' => FinalExamQuestion::DIFFICULTIES,
            'questionTypes' => FinalExamQuestion::QUESTION_TYPES,
            'statuses' => FinalExamQuestion::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('admin.final_exam.questions.form', [
            'question' => new FinalExamQuestion(['status' => FinalExamQuestion::STATUS_ACTIVE]),
            'categories' => FinalExamQuestion::CATEGORIES,
            'difficulties' => FinalExamQuestion::DIFFICULTIES,
            'questionTypes' => FinalExamQuestion::QUESTION_TYPES,
            'statuses' => FinalExamQuestion::STATUSES,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        FinalExamQuestion::create($this->validatedQuestion($request));

        return redirect()
            ->route('admin.final_exam.questions.index')
            ->with('success', 'Final Examination question created.');
    }

    public function edit(Request $request, FinalExamQuestion $question): View
    {
        $this->ensureAdmin($request);

        return view('admin.final_exam.questions.form', [
            'question' => $question,
            'categories' => FinalExamQuestion::CATEGORIES,
            'difficulties' => FinalExamQuestion::DIFFICULTIES,
            'questionTypes' => FinalExamQuestion::QUESTION_TYPES,
            'statuses' => FinalExamQuestion::STATUSES,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, FinalExamQuestion $question): RedirectResponse
    {
        $this->ensureAdmin($request);

        $question->update($this->validatedQuestion($request, $question));

        return redirect()
            ->route('admin.final_exam.questions.index')
            ->with('success', 'Final Examination question updated.');
    }

    public function destroy(Request $request, FinalExamQuestion $question): RedirectResponse
    {
        $this->ensureAdmin($request);

        if (FinalExamAttemptQuestion::where('question_id', $question->id)->exists()) {
            $question->update(['status' => FinalExamQuestion::STATUS_INACTIVE]);

            return back()->with('success', 'Question is used in exam history, so it was deactivated instead of deleted.');
        }

        $question->delete();

        return back()->with('success', 'Final Examination question deleted.');
    }

    public function toggleStatus(Request $request, FinalExamQuestion $question): RedirectResponse
    {
        $this->ensureAdmin($request);

        $question->update([
            'status' => $question->status === FinalExamQuestion::STATUS_ACTIVE
                ? FinalExamQuestion::STATUS_INACTIVE
                : FinalExamQuestion::STATUS_ACTIVE,
        ]);

        return back()->with('success', 'Question status updated.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'questions_file' => ['required', 'file', 'mimes:csv,txt,json'],
        ]);

        $path = $request->file('questions_file')->getRealPath();
        $extension = strtolower($request->file('questions_file')->getClientOriginalExtension());

        if ($extension === 'json') {
            return $this->importJson($path);
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            return back()->withErrors(['questions_file' => 'Unable to read the uploaded CSV file.']);
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return back()->withErrors(['questions_file' => 'The CSV file must include a header row.']);
        }

        $columns = collect($header)
            ->map(fn ($column): string => strtolower(str_replace([' ', '-'], '_', trim((string) $column))))
            ->all();

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($columns, array_slice(array_pad($row, count($columns), null), 0, count($columns)));

            if (! $record || empty(trim((string) ($record['question'] ?? '')))) {
                $skipped++;

                continue;
            }

            $payload = [
                'category' => trim((string) ($record['category'] ?? '')),
                'subcategory' => trim((string) ($record['subcategory'] ?? '')),
                'question_type' => trim((string) ($record['question_type'] ?? FinalExamQuestion::TYPE_TEXT_MCQ)),
                'question' => trim((string) ($record['question'] ?? '')),
                'scenario_text' => trim((string) ($record['scenario_text'] ?? '')),
                'chart_image_path' => trim((string) ($record['chart_image_path'] ?? '')),
                'chart_data_json' => $this->decodeChartData($record['chart_data_json'] ?? null),
                'chart_template_key' => trim((string) ($record['chart_template_key'] ?? '')),
                'chart_timeframe' => trim((string) ($record['chart_timeframe'] ?? '')),
                'option_a' => trim((string) ($record['option_a'] ?? $record['a'] ?? '')),
                'option_b' => trim((string) ($record['option_b'] ?? $record['b'] ?? '')),
                'option_c' => trim((string) ($record['option_c'] ?? $record['c'] ?? '')),
                'option_d' => trim((string) ($record['option_d'] ?? $record['d'] ?? '')),
                'correct_answer' => strtoupper(trim((string) ($record['correct_answer'] ?? ''))),
                'explanation' => trim((string) ($record['explanation'] ?? '')),
                'difficulty' => strtolower(trim((string) ($record['difficulty'] ?? FinalExamQuestion::DIFFICULTY_BEGINNER))),
                'status' => strtolower(trim((string) ($record['status'] ?? FinalExamQuestion::STATUS_ACTIVE))),
            ];

            $validator = Validator::make($payload, $this->questionRules());

            if ($validator->fails()) {
                $skipped++;

                continue;
            }

            FinalExamQuestion::updateOrCreate(
                ['question' => $payload['question']],
                $payload
            );
            $imported++;
        }

        fclose($handle);

        return back()->with('success', 'Imported '.$imported.' questions. Skipped '.$skipped.' invalid rows.');
    }

    public function export(Request $request)
    {
        $this->ensureAdmin($request);

        $questions = FinalExamQuestion::orderBy('category')
            ->orderBy('question_type')
            ->orderBy('subcategory')
            ->get();
        $filename = 'final_exam_questions_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($questions): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, [
                'category',
                'subcategory',
                'question_type',
                'question',
                'scenario_text',
                'chart_image_path',
                'chart_data_json',
                'chart_template_key',
                'chart_timeframe',
                'option_a',
                'option_b',
                'option_c',
                'option_d',
                'correct_answer',
                'explanation',
                'difficulty',
                'status',
            ]);

            foreach ($questions as $question) {
                fputcsv($out, [
                    $question->category,
                    $question->subcategory,
                    $question->question_type,
                    $question->question,
                    $question->scenario_text,
                    $question->chart_image_path,
                    json_encode($question->chart_data_json),
                    $question->chart_template_key,
                    $question->chart_timeframe,
                    $question->option_a,
                    $question->option_b,
                    $question->option_c,
                    $question->option_d,
                    $question->correct_answer,
                    $question->explanation,
                    $question->difficulty,
                    $question->status,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    public function attempts(Request $request): View
    {
        $this->ensureAdmin($request);

        $query = FinalExamAttempt::with('user')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $attempts = $query->paginate(20)->withQueryString();

        return view('admin.final_exam.attempts', [
            'attempts' => $attempts,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function showAttempt(Request $request, FinalExamAttempt $attempt, FinalExamService $examService): View
    {
        $this->ensureAdmin($request);

        $attempt->load('attemptQuestions.question', 'user');
        $breakdown = $examService->categoryBreakdown($attempt);
        $setting = FinalExamSetting::current();
        $showReview = true;
        $passingScore = $examService->passingScore((int) $attempt->total_questions);

        return view('final_exam.result', compact('attempt', 'breakdown', 'setting', 'showReview', 'passingScore'));
    }

    public function settings(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('admin.final_exam.settings', [
            'setting' => FinalExamSetting::current(),
            'requiredCounts' => FinalExamService::CATEGORY_COUNTS,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'passing_percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'allow_immediate_retake' => ['nullable', 'boolean'],
            'retake_cooldown_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'show_review_after_submission' => ['nullable', 'boolean'],
            'existing_users_passed_by_default' => ['nullable', 'boolean'],
        ]);

        FinalExamSetting::current()->update([
            'is_active' => (bool) ($data['is_active'] ?? false),
            'passing_percentage' => $data['passing_percentage'],
            'allow_immediate_retake' => (bool) ($data['allow_immediate_retake'] ?? false),
            'retake_cooldown_hours' => $data['retake_cooldown_hours'],
            'show_review_after_submission' => (bool) ($data['show_review_after_submission'] ?? false),
            'existing_users_passed_by_default' => (bool) ($data['existing_users_passed_by_default'] ?? false),
        ]);

        return back()->with('success', 'Final Examination settings updated.');
    }

    private function validatedQuestion(Request $request, ?FinalExamQuestion $question = null): array
    {
        $data = $request->validate($this->questionRules());
        $chartData = $this->decodeChartData($data['chart_data_json'] ?? null);
        $data['chart_data_json'] = $chartData;

        if ($request->hasFile('chart_image')) {
            $data['chart_image_path'] = 'storage/'.$request->file('chart_image')->store('final_exam_charts', 'public');
        } elseif ($question && $question->chart_image_path && empty($data['chart_image_path'])) {
            $data['chart_image_path'] = $question->chart_image_path;
        }

        if (! in_array($data['question_type'], [FinalExamQuestion::TYPE_CHART_MCQ, FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ], true)) {
            $data['chart_data_json'] = null;
            $data['chart_image_path'] = null;
            $data['chart_template_key'] = null;
            $data['chart_timeframe'] = null;
        } elseif (empty($data['chart_data_json']) && empty($data['chart_image_path'])) {
            throw ValidationException::withMessages([
                'chart_data_json' => 'Chart questions require chart data JSON or an uploaded chart image.',
            ]);
        }

        unset($data['chart_image']);

        return $data;
    }

    private function questionRules(): array
    {
        return [
            'category' => ['required', Rule::in(FinalExamQuestion::CATEGORIES)],
            'subcategory' => ['nullable', 'string', 'max:160'],
            'question_type' => ['required', Rule::in(array_keys(FinalExamQuestion::QUESTION_TYPES))],
            'question' => ['required', 'string', 'max:10000'],
            'scenario_text' => ['nullable', 'string', 'max:10000'],
            'chart_image' => ['nullable', 'image', 'max:4096'],
            'chart_image_path' => ['nullable', 'string', 'max:255'],
            'chart_data_json' => ['nullable'],
            'chart_template_key' => ['nullable', 'string', 'max:120'],
            'chart_timeframe' => ['nullable', 'string', 'max:20'],
            'option_a' => ['required', 'string', 'max:5000'],
            'option_b' => ['required', 'string', 'max:5000'],
            'option_c' => ['required', 'string', 'max:5000'],
            'option_d' => ['required', 'string', 'max:5000'],
            'correct_answer' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'difficulty' => ['required', Rule::in(array_keys(FinalExamQuestion::DIFFICULTIES))],
            'status' => ['required', Rule::in(array_keys(FinalExamQuestion::STATUSES))],
        ];
    }

    private function importJson(string $path): RedirectResponse
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return back()->withErrors(['questions_file' => 'The uploaded JSON file could not be decoded.']);
        }

        $rows = array_is_list($decoded) ? $decoded : ($decoded['questions'] ?? []);
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }

            $payload = [
                'category' => $row['category'] ?? '',
                'subcategory' => $row['subcategory'] ?? null,
                'question_type' => $row['question_type'] ?? FinalExamQuestion::TYPE_TEXT_MCQ,
                'question' => $row['question'] ?? '',
                'scenario_text' => $row['scenario_text'] ?? null,
                'chart_image_path' => $row['chart_image_path'] ?? null,
                'chart_data_json' => $row['chart_data_json'] ?? null,
                'chart_template_key' => $row['chart_template_key'] ?? null,
                'chart_timeframe' => $row['chart_timeframe'] ?? null,
                'option_a' => $row['option_a'] ?? '',
                'option_b' => $row['option_b'] ?? '',
                'option_c' => $row['option_c'] ?? '',
                'option_d' => $row['option_d'] ?? '',
                'correct_answer' => strtoupper((string) ($row['correct_answer'] ?? '')),
                'explanation' => $row['explanation'] ?? '',
                'difficulty' => $row['difficulty'] ?? FinalExamQuestion::DIFFICULTY_BEGINNER,
                'status' => $row['status'] ?? FinalExamQuestion::STATUS_ACTIVE,
            ];

            $validator = Validator::make($payload, $this->questionRules());

            if ($validator->fails()) {
                $skipped++;

                continue;
            }

            $payload['chart_data_json'] = $this->decodeChartData($payload['chart_data_json']);
            FinalExamQuestion::updateOrCreate(['question' => $payload['question']], $payload);
            $imported++;
        }

        return back()->with('success', 'Imported '.$imported.' JSON questions. Skipped '.$skipped.' invalid rows.');
    }

    private function decodeChartData($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(in_array((int) ($request->user()->role_id ?? 0), FinalExamService::ADMIN_ROLES, true), 403);
    }
}
