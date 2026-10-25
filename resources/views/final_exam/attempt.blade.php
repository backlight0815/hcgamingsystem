@extends('admin.admin_master')
@section('admin')

<title>Final Examination Attempt | HC Gaming Studio</title>

<style>
    .final-attempt .attempt-topbar {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        position: sticky;
        top: 72px;
        z-index: 3;
    }
    .final-attempt .question-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 16px;
        padding: 18px;
    }
    .final-attempt .question-title {
        color: #0f172a;
        font-weight: 800;
    }
    .final-attempt .answer-option {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        display: block;
        margin-top: 10px;
        padding: 11px 12px;
    }
    .final-attempt .answer-option:has(input:checked) {
        background: #eef6ff;
        border-color: #3b82f6;
    }
    .final-attempt .question-nav {
        display: grid;
        gap: 6px;
        grid-template-columns: repeat(5, 1fr);
    }
    .final-attempt .question-nav a {
        border: 1px solid #dbe3ec;
        border-radius: 6px;
        color: #475569;
        padding: 7px 0;
        text-align: center;
    }
    .final-attempt .question-nav a.is-answered {
        background: #dcfce7;
        border-color: #86efac;
        color: #166534;
        font-weight: 700;
    }
    @media (max-width: 991px) {
        .final-attempt .attempt-topbar {
            position: static;
        }
    }
</style>

<div class="page-content final-attempt">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Final Examination</h4>
                <p class="text-muted mb-0">Answer all {{ $attempt->total_questions }} questions before final submission.</p>
            </div>
            <a href="{{ route('final_exam.intro') }}" class="btn btn-light border">
                <i class="ri-arrow-left-line me-1"></i> Exam Intro
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('final_exam.submit', $attempt) }}" id="finalExamForm">
            @csrf
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="attempt-topbar">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Progress</strong>
                            <span id="answeredCount">0/{{ $attempt->total_questions }}</span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" id="examProgressBar" style="width: 0%;"></div>
                        </div>
                        <div class="question-nav">
                            @foreach($attempt->attemptQuestions as $index => $attemptQuestion)
                                <a href="#question_{{ $attemptQuestion->id }}" data-question-id="{{ $attemptQuestion->id }}">{{ $index + 1 }}</a>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3" id="submitFinalExam">
                            <i class="ri-send-plane-line me-1"></i> Submit Exam
                        </button>
                    </div>
                </div>
                <div class="col-lg-9">
                    @foreach($requiredCounts as $category => $count)
                        <div class="mb-3">
                            <h5 class="mb-1">{{ $category }}</h5>
                            <p class="text-muted mb-0">{{ $count }} questions</p>
                        </div>

                        @foreach($attempt->attemptQuestions->filter(fn ($item) => $item->question?->category === $category) as $attemptQuestion)
                            @php $question = $attemptQuestion->question; @endphp
                            <div class="question-card" id="question_{{ $attemptQuestion->id }}">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div class="question-title">{{ $attemptQuestion->position }}. {{ $question?->question }}</div>
                                    <span class="badge bg-light text-dark">{{ $question?->difficultyLabel() }}</span>
                                </div>
                                @if($question?->subcategory)
                                    <div class="small text-muted mt-1">{{ $question->subcategory }}</div>
                                @endif

                                @if($question?->isChartQuestion())
                                    @include('final_exam.partials.chart', ['question' => $question])
                                @endif

                                @if($question?->scenario_text)
                                    <div class="alert alert-warning mt-3 mb-3">
                                        <strong>Scenario:</strong> {{ $question->scenario_text }}
                                    </div>
                                @endif

                                @foreach(['A', 'B', 'C', 'D'] as $letter)
                                    <label class="answer-option">
                                        <input type="radio" name="answers[{{ $attemptQuestion->id }}]" value="{{ $letter }}" required>
                                        <strong>{{ $letter }}.</strong> {{ $question?->optionText($letter) }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('finalExamForm');
        var total = {{ (int) $attempt->total_questions }};
        var countLabel = document.getElementById('answeredCount');
        var bar = document.getElementById('examProgressBar');
        var submitButton = document.getElementById('submitFinalExam');

        function updateProgress() {
            var answered = new Set();
            document.querySelectorAll('input[type="radio"][name^="answers"]:checked').forEach(function (input) {
                answered.add(input.name);
                var id = input.name.replace('answers[', '').replace(']', '');
                var nav = document.querySelector('.question-nav a[data-question-id="' + id + '"]');
                if (nav) {
                    nav.classList.add('is-answered');
                }
            });

            document.querySelectorAll('.question-nav a').forEach(function (nav) {
                if (!document.querySelector('input[name="answers[' + nav.dataset.questionId + ']"]:checked')) {
                    nav.classList.remove('is-answered');
                }
            });

            var count = answered.size;
            countLabel.textContent = count + '/' + total;
            bar.style.width = Math.round((count / total) * 100) + '%';
        }

        document.querySelectorAll('input[type="radio"][name^="answers"]').forEach(function (input) {
            input.addEventListener('change', updateProgress);
        });

        form.addEventListener('submit', function (event) {
            var answered = document.querySelectorAll('input[type="radio"][name^="answers"]:checked').length;
            var message = answered < total
                ? 'You still have ' + (total - answered) + ' unanswered question(s). Submit anyway?'
                : 'Submit your Final Examination answers now?';

            if (!confirm(message)) {
                event.preventDefault();
                return;
            }

            submitButton.disabled = true;
            submitButton.innerText = 'Submitting...';
        });

        updateProgress();
    });
</script>

@endsection
