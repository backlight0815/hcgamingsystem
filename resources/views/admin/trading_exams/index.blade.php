@extends('admin.admin_master')
@section('admin')

<title>Trading Exam Question Bank | HC Gaming Studio</title>

<style>
    .exam-bank .card { border-radius: 8px; }
    .exam-option-list { margin: 8px 0 0; padding-left: 18px; }
    .exam-option-list li { margin-bottom: 4px; }
    .exam-review-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        min-width: 280px;
    }
</style>

<div class="page-content exam-bank">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trading Exam Question Bank</h4>
                        <p class="text-muted mb-0">Manage multiple-choice questions for the daily 5-question trader knowledge check.</p>
                    </div>
                    @if(in_array((int) auth()->user()->role_id, [750, 760, 770], true))
                        <a href="{{ route('trading.exams.index') }}" class="btn btn-outline-primary">Daily Exam</a>
                    @endif
                </div>
            </div>
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

        <div class="row mb-4">
            <div class="col-md-3 mb-3"><div class="card bg-success text-white text-center p-3"><h6 class="mb-1">Approved</h6><h3 class="mb-0">{{ $metrics['approved'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-warning text-dark text-center p-3"><h6 class="mb-1">Pending Review</h6><h3 class="mb-0">{{ $metrics['pending'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-danger text-white text-center p-3"><h6 class="mb-1">Rejected</h6><h3 class="mb-0">{{ $metrics['rejected'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-primary text-white text-center p-3"><h6 class="mb-1">Attempts Today</h6><h3 class="mb-0">{{ $metrics['attempts_today'] }}</h3></div></div>
        </div>

        @if($isLeader)
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">Leader Question Limit</h5>
                            <p class="text-muted mb-0">{{ $leaderQuestionCount }} / {{ $leaderLimit }} questions used. Leader questions require administration approval before entering the pool.</p>
                        </div>
                        @if($leaderQuestionCount >= $leaderLimit && ! $hasPendingQuotaRequest)
                            <form method="POST" action="{{ route('admin.trading.exams.quota.request') }}" class="d-flex flex-wrap gap-2 align-items-end">
                                @csrf
                                <div>
                                    <label class="form-label small">Requested Limit</label>
                                    <input type="number" name="requested_limit" class="form-control form-control-sm" min="{{ $leaderLimit + 1 }}" value="{{ $leaderLimit + 25 }}" required>
                                </div>
                                <div>
                                    <label class="form-label small">Reason</label>
                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="More advanced content planned" required>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm">Request Increase</button>
                            </form>
                        @elseif($hasPendingQuotaRequest)
                            <span class="badge bg-warning text-dark">Quota request pending review</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Add Multiple-Choice Question</h5>
                <form method="POST" action="{{ route('admin.trading.exams.questions.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" value="{{ old('category') }}" placeholder="Risk, entries, psychology">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Difficulty / Topic</label>
                            <select name="difficulty" class="form-select" required>
                                @foreach($difficulties as $value => $label)
                                    <option value="{{ $value }}" {{ old('difficulty', 'foundation') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <textarea name="question_text" rows="3" class="form-control" required>{{ old('question_text') }}</textarea>
                    </div>
                    <div class="row">
                        @for($i = 0; $i < 4; $i++)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Option {{ chr(65 + $i) }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <input type="radio" name="correct_option" value="{{ $i }}" {{ (string) old('correct_option', '0') === (string) $i ? 'checked' : '' }} title="Correct answer">
                                    </span>
                                    <input type="text" name="options[]" class="form-control" value="{{ old('options.' . $i) }}" required>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Answer Explanation</label>
                        <textarea name="explanation" rows="3" class="form-control" placeholder="Explain the lesson after the trader submits.">{{ old('explanation') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ $isAdmin ? 'Publish Question' : 'Submit For Review' }}</button>
                </form>
            </div>
        </div>

        @if($isAdmin)
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Question Review Queue</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Question</th>
                                    <th>Submitted By</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingQuestions as $question)
                                    <tr>
                                        <td>
                                            <strong>{{ \Illuminate\Support\Str::limit($question->question_text, 140) }}</strong>
                                            <div class="text-muted small">{{ $question->difficultyLabel() }} @if($question->category) / {{ $question->category }} @endif</div>
                                            <ol class="exam-option-list">
                                                @foreach($question->options as $option)
                                                    <li>{{ $option->option_text }} @if($option->is_correct)<span class="badge bg-success">Correct</span>@endif</li>
                                                @endforeach
                                            </ol>
                                        </td>
                                        <td>{{ $question->creator?->name ?: $question->creator?->username }}</td>
                                        <td>
                                            <div class="exam-review-box">
                                                <form method="POST" action="{{ route('admin.trading.exams.questions.approve', $question->id) }}" class="mb-2">
                                                    @csrf
                                                    <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" placeholder="Optional approval note"></textarea>
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.trading.exams.questions.reject', $question->id) }}">
                                                    @csrf
                                                    <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" required placeholder="Reason for rejection"></textarea>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">No questions are waiting for review.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">{{ $isAdmin ? 'Question Bank' : 'My Submitted Questions' }}</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Question</th>
                                @if($isAdmin)<th>Owner</th>@endif
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($questions as $question)
                                <tr>
                                    <td>
                                        <strong>{{ \Illuminate\Support\Str::limit($question->question_text, 150) }}</strong>
                                        <div class="text-muted small">{{ $question->difficultyLabel() }} @if($question->category) / {{ $question->category }} @endif</div>
                                        @if($question->review_note)
                                            <div class="text-muted small mt-1">Review note: {{ \Illuminate\Support\Str::limit($question->review_note, 120) }}</div>
                                        @endif
                                    </td>
                                    @if($isAdmin)<td>{{ $question->creator?->name ?: $question->creator?->username }}</td>@endif
                                    <td><span class="badge bg-{{ $question->statusTone() }}">{{ $question->statusLabel() }}</span></td>
                                    <td>{{ $question->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('admin.trading.exams.questions.edit', $question->id) }}" class="btn btn-info btn-sm">Edit</a>
                                            <form method="POST" action="{{ route('admin.trading.exams.questions.destroy', $question->id) }}" onsubmit="return confirm('Remove this question?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $isAdmin ? 5 : 4 }}" class="text-center text-muted py-4">No questions found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $questions->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">{{ $isAdmin ? 'Quota Requests' : 'My Quota Requests' }}</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                @if($isAdmin)<th>Leader</th>@endif
                                <th>Limit</th>
                                <th>Reason</th>
                                <th>Status</th>
                                @if($isAdmin)<th>Review</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quotaRequests as $quotaRequest)
                                <tr>
                                    @if($isAdmin)<td>{{ $quotaRequest->leader?->name ?: $quotaRequest->leader?->username }}</td>@endif
                                    <td>{{ $quotaRequest->current_limit }} to {{ $quotaRequest->requested_limit }}</td>
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($quotaRequest->reason, 120) }}
                                        @if($quotaRequest->review_note)
                                            <div class="text-muted small">Review note: {{ \Illuminate\Support\Str::limit($quotaRequest->review_note, 100) }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $quotaRequest->statusTone() }}">{{ $quotaRequest->statusLabel() }}</span></td>
                                    @if($isAdmin)
                                        <td>
                                            @if($quotaRequest->isPending())
                                                <form method="POST" action="{{ route('admin.trading.exams.quota.approve', $quotaRequest->id) }}" class="mb-2">
                                                    @csrf
                                                    <input type="text" name="review_note" class="form-control form-control-sm mb-2" placeholder="Optional approval note">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.trading.exams.quota.reject', $quotaRequest->id) }}">
                                                    @csrf
                                                    <input type="text" name="review_note" class="form-control form-control-sm mb-2" required placeholder="Reason">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                </form>
                                            @else
                                                <span class="text-muted small">{{ $quotaRequest->reviewed_at?->format('Y-m-d H:i') }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $isAdmin ? 5 : 3 }}" class="text-center text-muted py-4">No quota requests found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
