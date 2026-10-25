@extends('admin.admin_master')
@section('admin')

<title>Final Exam Attempts | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Final Exam Attempts</h4>
                <p class="text-muted mb-0">Review user pass/fail history and scores.</p>
            </div>
            <a href="{{ route('admin.final_exam.questions.index') }}" class="btn btn-light border">
                <i class="ri-questionnaire-line me-1"></i> Question Bank
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.final_exam.attempts.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @foreach(['in_progress' => 'In Progress', 'passed' => 'Passed', 'failed' => 'Failed'] as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">User Search</label>
                        <input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Started</th>
                                <th>Submitted</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attempts as $attempt)
                                <tr>
                                    <td>
                                        <strong>{{ $attempt->user?->name ?: $attempt->user?->username }}</strong>
                                        <div class="small text-muted">{{ $attempt->user?->email }}</div>
                                    </td>
                                    <td><span class="badge bg-{{ $attempt->isPassed() ? 'success' : ($attempt->isFailed() ? 'danger' : 'warning text-dark') }}">{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</span></td>
                                    <td>{{ $attempt->isInProgress() ? '-' : $attempt->correct_answers . '/' . $attempt->total_questions . ' (' . $attempt->score_percentage . '%)' }}</td>
                                    <td>{{ $attempt->started_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ $attempt->submitted_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.final_exam.attempts.show', $attempt) }}" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No attempts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $attempts->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

@endsection
