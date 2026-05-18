@extends('admin.admin_master')
@section('admin')

<title>Prop Firm Reviews | HC Gaming Studio</title>

<style>
    .prop-shell .prop-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .prop-shell .metric {
        padding: 18px;
    }

    .prop-shell .metric span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .prop-shell .metric strong {
        color: #0f172a;
        display: block;
        font-size: 30px;
        margin-top: 4px;
    }

    .review-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 10px;
    }

    .review-badge.phase2 { background: #dbeafe; color: #1d4ed8; }
    .review-badge.funded { background: #dcfce7; color: #166534; }
    .review-badge.locked { background: #fee2e2; color: #991b1b; }

    .question-box {
        display: none;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
        padding: 14px;
    }

    .question-box.is-open {
        display: block;
    }

    .review-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
</style>

<div class="page-content prop-shell">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Prop Firm Review Centre</h4>
                        <p class="text-muted mb-0">Manual approval gates for Phase 2 and funded-account access.</p>
                    </div>
                </div>
            </div>
        </div>

        @foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $class)
            @if(session($key))
                <div class="alert alert-{{ $class }}">{{ session($key) }}</div>
            @endif
        @endforeach

        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="prop-card metric">
                    <span>Waiting Review</span>
                    <strong>{{ $reviewTraders->count() }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="prop-card metric">
                    <span>Funded Approved</span>
                    <strong>{{ $fundedTraders->count() }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="prop-card metric">
                    <span>Rejected</span>
                    <strong>{{ $rejectedTraders->count() }}</strong>
                </div>
            </div>
        </div>

        <div class="prop-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Manual Review Queue</h5>
                    <span class="text-muted small">Approval unlocks the next stage.</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trader</th>
                                <th>Current Phase</th>
                                <th>Review Gate</th>
                                <th>Lock</th>
                                <th>Questions</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reviewTraders as $trader)
                                @php
                                    $reviewStatus = $trader->prop_firm_review_status;
                                    $isPhase2Review = $reviewStatus === 'pending_phase2';
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $trader->name ?: $trader->username }}</strong>
                                        <div class="text-muted small">{{ $trader->email }}</div>
                                    </td>
                                    <td>Phase {{ $trader->prop_firm_phase ?? 1 }}</td>
                                    <td>
                                        <span class="review-badge {{ $isPhase2Review ? 'phase2' : 'funded' }}">
                                            {{ $isPhase2Review ? 'Approve Phase 2' : 'Approve Funded Account' }}
                                        </span>
                                        @if($trader->prop_firm_review_note)
                                            <div class="text-muted small mt-1">{{ $trader->prop_firm_review_note }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($trader->prop_firm_trade_locked)
                                            <span class="review-badge locked">Trading Locked</span>
                                        @else
                                            <span class="badge bg-success">Unlocked</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small">Open: {{ $trader->open_questions_count }}</div>
                                        <div class="small">Answered: {{ $trader->answered_questions_count }}</div>
                                    </td>
                                    <td>{{ $trader->prop_firm_review_requested_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        <div class="review-actions">
                                            <a href="{{ route('admin.trader.journals.index', ['user_id' => $trader->id]) }}" class="btn btn-outline-primary btn-sm">
                                                Review Trades
                                            </a>
                                        <form action="{{ route('admin.funded_traders.approve', $trader->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this review gate?');">
                                                Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.funded_traders.reject', $trader->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this prop firm review?');">
                                                Reject
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-warning btn-sm question-toggle" data-target="question-{{ $trader->id }}">
                                            Ask Question
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <div class="question-box" id="question-{{ $trader->id }}">
                                            <form action="{{ route('admin.funded_traders.questions.store', $trader->id) }}" method="POST">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-md-2">
                                                        <label class="form-label">Phase</label>
                                                        <select name="phase" class="form-control">
                                                            <option value="{{ $trader->prop_firm_phase }}">Current Phase {{ $trader->prop_firm_phase }}</option>
                                                            <option value="1">Phase 1</option>
                                                            <option value="2">Phase 2</option>
                                                            <option value="3">Funded</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" placeholder="Suspicious activity review">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Question</label>
                                                        <textarea name="question" class="form-control" rows="2" required placeholder="Ask the trader to explain the activity, entry logic, risk decision, or evidence required."></textarea>
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-warning btn-sm">Send Pop-up Question</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No trader is waiting for manual review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-7 mb-4">
                <div class="prop-card">
                    <div class="card-body">
                        <h5 class="mb-3">Approved Funded Accounts</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Trader</th>
                                        <th>Phase</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($fundedTraders as $trader)
                                        <tr>
                                            <td>
                                                <strong>{{ $trader->name ?: $trader->username }}</strong>
                                                <div class="text-muted small">{{ $trader->email }}</div>
                                            </td>
                                            <td>Funded</td>
                                            <td><span class="badge bg-success">Approved</span></td>
                                            <td>
                                                <div class="review-actions">
                                                    <a href="{{ route('admin.trader.journals.index', ['user_id' => $trader->id]) }}" class="btn btn-outline-primary btn-sm">
                                                        Review Trades
                                                    </a>
                                                <form action="{{ route('admin.funded_traders.suspend', $trader->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('Suspend and lock this funded trader?');">
                                                        Suspend
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-outline-warning btn-sm question-toggle" data-target="funded-question-{{ $trader->id }}">
                                                    Ask
                                                </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="p-0">
                                                <div class="question-box" id="funded-question-{{ $trader->id }}">
                                                    <form action="{{ route('admin.funded_traders.questions.store', $trader->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="phase" value="3">
                                                        <div class="row g-2">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Title</label>
                                                                <input type="text" name="title" class="form-control" placeholder="Funded account activity review">
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label">Question</label>
                                                                <textarea name="question" class="form-control" rows="2" required placeholder="Ask the funded trader to explain suspicious activity or provide supporting evidence."></textarea>
                                                            </div>
                                                            <div class="col-12 text-end">
                                                                <button type="submit" class="btn btn-warning btn-sm">Send Pop-up Question</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No funded accounts approved yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5 mb-4">
                <div class="prop-card">
                    <div class="card-body">
                        <h5 class="mb-3">Recent Evaluation Questions</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Trader</th>
                                        <th>Status</th>
                                        <th>Question</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($questions as $question)
                                        <tr>
                                            <td>{{ $question->trader?->username ?? '-' }}</td>
                                            <td>{{ ucfirst($question->status) }}</td>
                                            <td>
                                                <strong>{{ $question->title ?: 'Evaluation question' }}</strong>
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($question->question, 90) }}</div>
                                                @if($question->answer)
                                                    <div class="small mt-1"><strong>Answer:</strong> {{ \Illuminate\Support\Str::limit($question->answer, 90) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="review-actions">
                                                @if($question->trader)
                                                    <a href="{{ route('admin.trader.journals.index', ['user_id' => $question->trader->id]) }}" class="btn btn-sm btn-outline-primary">
                                                        Trades
                                                    </a>
                                                @endif
                                                @if($question->status !== \App\Models\PropFirmEvaluationQuestion::STATUS_RESOLVED)
                                                    <form action="{{ route('admin.funded_traders.questions.resolve', $question->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Resolve</button>
                                                    </form>
                                                @else
                                                    <span class="text-muted">Resolved</span>
                                                @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No evaluation questions yet.</td>
                                        </tr>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.question-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            if (target) {
                target.classList.toggle('is-open');
            }
        });
    });
});
</script>

@endsection
