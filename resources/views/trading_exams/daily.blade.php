@extends('admin.admin_master')
@section('admin')

<title>Daily Trading Exam | HC Gaming Studio</title>

<style>
    .exam-shell .card { border-radius: 8px; }
    .exam-hero {
        background: #0f172a;
        border-radius: 8px;
        color: #fff;
        padding: 22px 24px;
    }
    .exam-hero h4 { color: #fff; font-weight: 800; margin: 0; }
    .exam-hero p { color: #cbd5e1; margin: 6px 0 0; }
    .exam-question {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 14px;
    }
    .exam-question-title { color: #0f172a; font-weight: 800; }
    .exam-option {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        display: block;
        margin-top: 8px;
        padding: 10px 12px;
    }
    .exam-option.is-correct { background: #ecfdf5; border-color: #86efac; }
    .exam-option.is-wrong { background: #fef2f2; border-color: #fecaca; }
    .exam-score {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 16px;
    }
    .exam-score strong { color: #0f172a; font-size: 30px; }
</style>

<div class="page-content exam-shell">
    <div class="container-fluid">
        <div class="exam-hero mb-4">
            <h4>Daily Trading Knowledge Check</h4>
            <p>Five multiple-choice questions a day. Optional, lightweight, and built for steady trading knowledge absorption.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if($approvedQuestionCount < 5)
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5 class="mb-2">Question pool is warming up</h5>
                    <p class="text-muted mb-0">At least 5 approved questions are required before the daily exam can open. Current approved questions: {{ $approvedQuestionCount }}.</p>
                </div>
            </div>
        @elseif($attempt && $attempt->isCompleted())
            <div class="exam-score mb-4">
                <div>
                    <h5 class="mb-1">Today's Result</h5>
                    <p class="text-muted mb-0">Completed {{ $attempt->completed_at?->format('Y-m-d H:i') }}</p>
                </div>
                <strong>{{ $attempt->score }}/{{ $attempt->total_questions }} <span class="text-muted" style="font-size:16px;">({{ $attempt->percentage() }}%)</span></strong>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Answers & Review</h5>
                    @foreach($attempt->answers as $index => $answer)
                        @php
                            $question = $answer->question;
                            $correctOption = $question?->correctOption;
                        @endphp
                        <div class="exam-question">
                            <div class="exam-question-title">{{ $index + 1 }}. {{ $question?->question_text }}</div>
                            <div class="text-muted small mt-1">{{ $question?->difficultyLabel() }} @if($question?->category) / {{ $question->category }} @endif</div>

                            @foreach($question?->options ?? [] as $option)
                                @php
                                    $isSelected = (int) $answer->selected_option_id === (int) $option->id;
                                    $optionClass = $option->is_correct ? 'is-correct' : ($isSelected ? 'is-wrong' : '');
                                @endphp
                                <div class="exam-option {{ $optionClass }}">
                                    {{ chr(64 + $option->position) }}. {{ $option->option_text }}
                                    @if($option->is_correct)
                                        <span class="badge bg-success ms-2">Correct</span>
                                    @elseif($isSelected)
                                        <span class="badge bg-danger ms-2">Your answer</span>
                                    @endif
                                </div>
                            @endforeach

                            @if($question?->explanation)
                                <div class="alert alert-info mt-3 mb-0">
                                    <strong>Explanation:</strong> {{ $question->explanation }}
                                </div>
                            @elseif($correctOption)
                                <div class="text-muted small mt-3">Correct answer: {{ chr(64 + $correctOption->position) }}. {{ $correctOption->option_text }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif($attempt)
            <form method="POST" action="{{ route('trading.exams.submit', $attempt->id) }}">
                @csrf
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Today's 5 Questions</h5>
                                <p class="text-muted mb-0">Answer when you are ready. This is for learning, not punishment.</p>
                            </div>
                            <span class="badge bg-primary">{{ $attempt->exam_date?->format('Y-m-d') }}</span>
                        </div>

                        @foreach($attempt->answers as $index => $answer)
                            <div class="exam-question">
                                <div class="exam-question-title">{{ $index + 1 }}. {{ $answer->question?->question_text }}</div>
                                <div class="text-muted small mt-1">{{ $answer->question?->difficultyLabel() }} @if($answer->question?->category) / {{ $answer->question->category }} @endif</div>

                                @foreach($answer->question?->options ?? [] as $option)
                                    <label class="exam-option">
                                        <input type="radio" name="answers[{{ $answer->id }}]" value="{{ $option->id }}" required>
                                        {{ chr(64 + $option->position) }}. {{ $option->option_text }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit today\'s answers and view the result?');">Submit Answers</button>
                    </div>
                </div>
            </form>
        @endif

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="mb-3">Recent Attempts</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAttempts as $recent)
                                <tr>
                                    <td>{{ $recent->exam_date?->format('Y-m-d') }}</td>
                                    <td><span class="badge bg-{{ $recent->isCompleted() ? 'success' : 'warning' }}">{{ $recent->isCompleted() ? 'Completed' : 'In Progress' }}</span></td>
                                    <td>{{ $recent->isCompleted() ? $recent->score . '/' . $recent->total_questions . ' (' . $recent->percentage() . '%)' : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No previous attempts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
