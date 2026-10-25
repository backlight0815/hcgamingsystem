@extends('admin.admin_master')
@section('admin')

<title>Final Examination Result | HC Gaming Studio</title>

<style>
    .final-result .result-hero {
        border-radius: 8px;
        color: #fff;
        padding: 22px 24px;
    }
    .final-result .result-hero h4 { color: #fff; font-weight: 800; margin: 0; }
    .final-result .review-question {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 14px;
        padding: 16px;
    }
    .final-result .answer-line {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-top: 8px;
        padding: 9px 11px;
    }
    .final-result .answer-line.is-correct { background: #ecfdf5; border-color: #86efac; }
    .final-result .answer-line.is-wrong { background: #fef2f2; border-color: #fecaca; }
</style>

<div class="page-content final-result">
    <div class="container-fluid">
        <div class="result-hero mb-4 {{ $attempt->isPassed() ? 'bg-success' : ($attempt->isFailed() ? 'bg-danger' : 'bg-secondary') }}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4>{{ $attempt->isPassed() ? 'Final Examination Passed' : ($attempt->isFailed() ? 'Final Examination Failed' : 'Final Examination In Progress') }}</h4>
                    <p class="mb-0 text-white-50">Passing requirement: {{ $passingScore }}/{{ $attempt->total_questions }} correct or {{ $setting->passing_percentage }}%.</p>
                </div>
                <strong style="font-size: 32px;">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }} ({{ $attempt->score_percentage }}%)</strong>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            @if(auth()->user()?->role_id === 1 || auth()->user()?->role_id === 2)
                <a href="{{ route('admin.final_exam.attempts.index') }}" class="btn btn-light border">
                    <i class="ri-arrow-left-line me-1"></i> Attempt Records
                </a>
            @else
                <a href="{{ route('final_exam.intro') }}" class="btn btn-light border">
                    <i class="ri-arrow-left-line me-1"></i> Exam Intro
                </a>
            @endif

            @if($attempt->isPassed() && (int) auth()->id() === (int) $attempt->user_id)
                <a href="{{ route('final_exam.prop_firm_selection') }}" class="btn btn-primary">
                    <i class="ri-funds-line me-1"></i> Select Prop Firm Model
                </a>
            @elseif($attempt->isFailed() && (int) auth()->id() === (int) $attempt->user_id)
                <form method="POST" action="{{ route('final_exam.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-restart-line me-1"></i> Retake Exam
                    </button>
                </form>
            @endif
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><span class="text-muted">Correct</span><h3 class="mb-0">{{ $attempt->correct_answers }}</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><span class="text-muted">Wrong</span><h3 class="mb-0">{{ $attempt->wrongAnswers() }}</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><span class="text-muted">Percentage</span><h3 class="mb-0">{{ $attempt->score_percentage }}%</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><span class="text-muted">Submitted</span><h5 class="mb-0">{{ $attempt->submitted_at?->format('Y-m-d H:i') ?: '-' }}</h5></div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Category Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Total</th>
                                <th>Correct</th>
                                <th>Wrong</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($breakdown as $row)
                                <tr>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['correct'] }}</td>
                                    <td>{{ $row['wrong'] }}</td>
                                    <td>{{ $row['percentage'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($showReview)
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Answer Review</h5>
                    @foreach($attempt->attemptQuestions as $attemptQuestion)
                        @php $question = $attemptQuestion->question; @endphp
                        <div class="review-question">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $attemptQuestion->position }}. {{ $question?->question }}</strong>
                                <span class="badge bg-{{ $attemptQuestion->is_correct ? 'success' : 'danger' }}">{{ $attemptQuestion->is_correct ? 'Correct' : 'Wrong' }}</span>
                            </div>
                            <div class="text-muted small mt-1">{{ $question?->category }} / {{ $question?->subcategory }} / {{ $question?->difficultyLabel() }}</div>

                            @if($question?->isChartQuestion())
                                @include('final_exam.partials.chart', ['question' => $question])
                            @endif

                            @if($question?->scenario_text)
                                <div class="alert alert-warning mt-3 mb-3">
                                    <strong>Scenario:</strong> {{ $question->scenario_text }}
                                </div>
                            @endif

                            @foreach(['A', 'B', 'C', 'D'] as $letter)
                                @php
                                    $isCorrect = $letter === $question?->correct_answer;
                                    $isSelected = $letter === $attemptQuestion->selected_answer;
                                @endphp
                                <div class="answer-line {{ $isCorrect ? 'is-correct' : ($isSelected ? 'is-wrong' : '') }}">
                                    <strong>{{ $letter }}.</strong> {{ $question?->optionText($letter) }}
                                    @if($isCorrect)
                                        <span class="badge bg-success ms-2">Correct answer</span>
                                    @elseif($isSelected)
                                        <span class="badge bg-danger ms-2">Your answer</span>
                                    @endif
                                </div>
                            @endforeach

                            @if($question?->explanation)
                                <div class="alert alert-info mt-3 mb-0">
                                    <strong>Explanation:</strong> {{ $question->explanation }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
