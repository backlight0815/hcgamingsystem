@extends('admin.admin_master')
@section('admin')

<title>Trading Position Reviews | HC Gaming Studio</title>

<style>
    .tp-shell { color: #172033; }
    .tp-card {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }
    .tp-metric { padding: 18px; }
    .tp-metric span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .tp-metric strong {
        color: #0f172a;
        display: block;
        font-size: 28px;
        margin-top: 4px;
    }
    .tp-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 10px;
    }
    .tp-badge.success { background: #dcfce7; color: #166534; }
    .tp-badge.warning { background: #fef3c7; color: #92400e; }
    .tp-badge.danger { background: #fee2e2; color: #991b1b; }
    .tp-review-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        min-width: 320px;
    }
</style>

<div class="page-content tp-shell">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trading Position Reviews</h4>
                        <p class="text-muted mb-0">Review Recruiter and Leadership applications after 60 trades and 30 days from the trader's first recorded trade open date.</p>
                    </div>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                            <li class="breadcrumb-item active">Trading Positions</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row mb-4">
            <div class="col mb-3"><div class="tp-card tp-metric"><span>Pending</span><strong>{{ $metrics['pending'] }}</strong></div></div>
            <div class="col mb-3"><div class="tp-card tp-metric"><span>Approved</span><strong>{{ $metrics['approved'] }}</strong></div></div>
            <div class="col mb-3"><div class="tp-card tp-metric"><span>Rejected</span><strong>{{ $metrics['rejected'] }}</strong></div></div>
            <div class="col mb-3"><div class="tp-card tp-metric"><span>Leaders</span><strong>{{ $metrics['leaders'] }}</strong></div></div>
            <div class="col mb-3"><div class="tp-card tp-metric"><span>Recruiters</span><strong>{{ $metrics['recruiters'] }}</strong></div></div>
        </div>

        <div class="tp-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Pending Applications</h5>
                    <span class="text-muted small">{{ $pendingApplications->count() }} waiting</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Applicant</th>
                                <th>Position</th>
                                <th>Trade History</th>
                                <th>Evaluation Notes</th>
                                <th>Document</th>
                                <th>Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingApplications as $application)
                                <tr>
                                    <td>
                                        <strong>{{ $application->applicant?->name ?: $application->applicant?->username }}</strong>
                                        <div class="text-muted small">{{ $application->applicant?->email }}</div>
                                    </td>
                                    <td>{{ $application->requestedPositionLabel() }}</td>
                                    <td>
                                        <div>First trade: <strong>{{ $application->first_trade_date?->format('Y-m-d') ?? '-' }}</strong></div>
                                        <div>Trade records: <strong>{{ $application->trade_count_snapshot }}</strong></div>
                                    </td>
                                    <td>
                                        @if($application->isLeadership())
                                            <div><strong>Strategy:</strong> {{ \Illuminate\Support\Str::limit($application->strategy_summary, 90) }}</div>
                                            <div><strong>Trade:</strong> {{ \Illuminate\Support\Str::limit($application->trade_history_summary, 90) }}</div>
                                            <div><strong>Personality:</strong> {{ \Illuminate\Support\Str::limit($application->personality_summary, 90) }}</div>
                                        @else
                                            <div><strong>Marketing:</strong> {{ \Illuminate\Support\Str::limit($application->marketing_plan, 110) }}</div>
                                        @endif
                                        <div><strong>Support:</strong> {{ \Illuminate\Support\Str::limit($application->client_support_plan, 110) }}</div>
                                    </td>
                                    <td>
                                        @if($application->supporting_document_path)
                                            <a href="{{ route('admin.trading_positions.download', $application->id) }}" class="btn btn-outline-primary btn-sm">Download</a>
                                        @else
                                            <span class="text-muted">No document</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="tp-review-box">
                                            <form method="POST" action="{{ route('admin.trading_positions.approve', $application->id) }}" class="mb-2">
                                                @csrf
                                                <label class="form-label small">Approval Note</label>
                                                <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" placeholder="Passed strategy/personality evaluation."></textarea>
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve and upgrade this member?');">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.trading_positions.reject', $application->id) }}">
                                                @csrf
                                                <label class="form-label small">Reject Note</label>
                                                <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" required placeholder="Explain what needs improvement."></textarea>
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this application?');">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No position application is waiting for review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tp-card">
            <div class="card-body">
                <h5 class="mb-3">Recent Reviewed Applications</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Applicant</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Review Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reviewedApplications as $application)
                                <tr>
                                    <td>#{{ $application->id }}</td>
                                    <td>
                                        <strong>{{ $application->applicant?->name ?: $application->applicant?->username }}</strong>
                                        <div class="text-muted small">{{ $application->applicant?->email }}</div>
                                    </td>
                                    <td>{{ $application->requestedPositionLabel() }}</td>
                                    <td><span class="tp-badge {{ $application->statusTone() }}">{{ $application->statusLabel() }}</span></td>
                                    <td>{{ $application->reviewer?->username ?? '-' }}</td>
                                    <td>{{ $application->review_note ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No reviewed application yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
