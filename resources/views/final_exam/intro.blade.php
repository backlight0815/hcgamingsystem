@extends('admin.admin_master')
@section('admin')

<title>Final Examination | HC Gaming Studio</title>

@php
    $hasEnoughQuestions = collect($requiredCounts)->every(fn ($required, $category) => (int) ($questionCounts[$category] ?? 0) >= (int) $required);
@endphp

<style>
    .final-exam-shell .exam-hero {
        background: #0f172a;
        border-radius: 8px;
        color: #fff;
        padding: 22px 24px;
    }
    .final-exam-shell .exam-hero h4 { color: #fff; font-weight: 800; margin: 0; }
    .final-exam-shell .exam-hero p { color: #cbd5e1; margin: 6px 0 0; }
    .final-exam-shell .exam-stat {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
    }
    .final-exam-shell .exam-stat strong {
        color: #0f172a;
        display: block;
        font-size: 24px;
    }
</style>

<div class="page-content final-exam-shell">
    <div class="container-fluid">
        <div class="exam-hero mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4>Final Examination</h4>
                    <p>Complete this one-time onboarding exam before unlocking the prop firm section.</p>
                </div>
                @if($user->final_exam_passed)
                    <span class="badge bg-success fs-6">Passed</span>
                @else
                    <span class="badge bg-warning text-dark fs-6">Required</span>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="exam-stat bg-white">
                    <span class="text-muted">Questions</span>
                    <strong>50</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="exam-stat bg-white">
                    <span class="text-muted">Passing Mark</span>
                    <strong>{{ $setting->passing_percentage }}%</strong>
                    <div class="small text-muted">{{ $passingScore }}/50 correct</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="exam-stat bg-white">
                    <span class="text-muted">Retake Rule</span>
                    <strong>{{ $setting->allow_immediate_retake ? 'Immediate' : $setting->retake_cooldown_hours . 'h' }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="exam-stat bg-white">
                    <span class="text-muted">Prop Firm</span>
                    <strong>{{ $user->prop_firm_unlocked ? 'Unlocked' : 'Locked' }}</strong>
                </div>
            </div>
        </div>

        @if($user->final_exam_passed)
            <div class="card mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Final Examination Completed</h5>
                        <p class="text-muted mb-0">You can select or update your preferred prop firm model.</p>
                    </div>
                    <a href="{{ route('final_exam.prop_firm_selection') }}" class="btn btn-primary">
                        <i class="ri-funds-line me-1"></i> Prop Firm Selection
                    </a>
                </div>
            </div>
        @else
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-2">{{ $attempt ? 'Resume Your Attempt' : 'Start Final Examination' }}</h5>
                            <p class="text-muted mb-0">The 50-question attempt keeps the same selected questions and charts after refresh.</p>
                        </div>
                        <form method="POST" action="{{ route('final_exam.start') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" {{ (! $hasEnoughQuestions || ! $retake['allowed']) ? 'disabled' : '' }}>
                                <i class="ri-play-circle-line me-1"></i> {{ $attempt ? 'Resume Exam' : 'Start Exam' }}
                            </button>
                        </form>
                    </div>

                    @if(! $hasEnoughQuestions)
                        <div class="alert alert-warning mt-3 mb-0">
                            The active question bank does not have enough questions yet. Please contact administration.
                        </div>
                    @elseif(! $retake['allowed'])
                        <div class="alert alert-warning mt-3 mb-0">{{ $retake['message'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Exam Structure</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th>Exam</th>
                                        <th>Active Bank</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requiredCounts as $category => $required)
                                        <tr>
                                            <td>{{ $category }}</td>
                                            <td>{{ $required }}</td>
                                            <td>
                                                <span class="badge bg-{{ (int) ($questionCounts[$category] ?? 0) >= $required ? 'success' : 'warning text-dark' }}">
                                                    {{ (int) ($questionCounts[$category] ?? 0) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Recent Attempts</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentAttempts as $recent)
                                        <tr>
                                            <td>{{ $recent->created_at?->format('Y-m-d H:i') }}</td>
                                            <td><span class="badge bg-{{ $recent->isPassed() ? 'success' : ($recent->isFailed() ? 'danger' : 'warning text-dark') }}">{{ ucfirst(str_replace('_', ' ', $recent->status)) }}</span></td>
                                            <td>{{ $recent->isInProgress() ? '-' : $recent->correct_answers . '/' . $recent->total_questions . ' (' . $recent->score_percentage . '%)' }}</td>
                                            <td class="text-end">
                                                @if(! $recent->isInProgress())
                                                    <a href="{{ route('final_exam.result', $recent) }}" class="btn btn-sm btn-light border">View</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No attempts yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
