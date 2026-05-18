@extends('admin.admin_master')
@section('admin')

<title>Trader Verification Reviews | HC Gaming Studio</title>

<style>
    .review-shell {
        color: #172033;
    }

    .review-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .05);
    }

    .review-metric {
        padding: 18px;
    }

    .review-metric span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .review-metric strong {
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

    .review-badge.info { background: #dbeafe; color: #1d4ed8; }
    .review-badge.success { background: #dcfce7; color: #166534; }
    .review-badge.warning { background: #fef3c7; color: #92400e; }
    .review-badge.danger { background: #fee2e2; color: #991b1b; }

    .review-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .review-reject-box {
        background: #f8fafc;
        border-radius: 8px;
        padding: 12px;
        min-width: 320px;
    }

    .review-table td,
    .review-table th {
        vertical-align: middle;
    }
</style>

<div class="page-content review-shell">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trader Verification Reviews</h4>
                        <p class="text-muted mb-0">Review client status, deposits, Discord identity, broker UID, broker email, and uploaded proof.</p>
                    </div>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                            <li class="breadcrumb-item active">Trader Verification</li>
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
            <div class="col mb-3">
                <div class="review-card review-metric">
                    <span>Pending</span>
                    <strong>{{ $metrics['pending'] }}</strong>
                </div>
            </div>
            <div class="col mb-3">
                <div class="review-card review-metric">
                    <span>Approved</span>
                    <strong>{{ $metrics['approved'] }}</strong>
                </div>
            </div>
            <div class="col mb-3">
                <div class="review-card review-metric">
                    <span>Document Resubmit</span>
                    <strong>{{ $metrics['resubmission'] }}</strong>
                </div>
            </div>
            <div class="col mb-3">
                <div class="review-card review-metric">
                    <span>New App Allowed</span>
                    <strong>{{ $metrics['new_application'] }}</strong>
                </div>
            </div>
            <div class="col mb-3">
                <div class="review-card review-metric">
                    <span>Closed Contact HC</span>
                    <strong>{{ $metrics['closed'] }}</strong>
                </div>
            </div>
        </div>

        <div class="review-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Pending Review Queue</h5>
                    <span class="text-muted small">{{ $pendingApplications->count() }} waiting</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered review-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trader</th>
                                <th>Client / Deposit</th>
                                <th>Discord</th>
                                <th>Broker</th>
                                <th>Document</th>
                                <th>Submitted</th>
                                <th>Review Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingApplications as $application)
                                <tr>
                                    <td>
                                        <strong>{{ $application->trader?->name ?: $application->trader?->username }}</strong>
                                        <div class="text-muted small">{{ $application->trader?->email }}</div>
                                    </td>
                                    <td>
                                        <div>Client: <strong>{{ $application->is_client ? 'Yes' : 'No' }}</strong></div>
                                        <div>Deposit: <strong>{{ $application->has_deposit ? 'Yes' : 'No' }}</strong></div>
                                        <div>Amount: <strong>{{ $application->deposit_amount ? 'USD '.number_format((float) $application->deposit_amount, 2) : '-' }}</strong></div>
                                    </td>
                                    <td>{{ $application->discord_username }}</td>
                                    <td>
                                        <div><strong>{{ $application->broker_uid }}</strong></div>
                                        <div class="text-muted small">{{ $application->broker_email }}</div>
                                    </td>
                                    <td>
                                        @if($application->document_path)
                                            <a href="{{ route('admin.trader_onboarding.download', $application->id) }}" class="btn btn-sm btn-outline-primary">
                                                View Proof
                                            </a>
                                        @else
                                            <span class="text-muted">No document</span>
                                        @endif
                                        @if($application->trader_note)
                                            <div class="text-muted small mt-2">{{ \Illuminate\Support\Str::limit($application->trader_note, 90) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $application->submitted_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        <div class="review-actions mb-2">
                                            <form method="POST" action="{{ route('admin.trader_onboarding.approve', $application->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this trader and unlock access?');">
                                                    Approve
                                                </button>
                                            </form>
                                        </div>

                                        <form method="POST" action="{{ route('admin.trader_onboarding.reject', $application->id) }}" class="review-reject-box">
                                            @csrf
                                            <div class="mb-2">
                                                <label class="form-label small">Reject Reason</label>
                                                <select name="rejection_reason" class="form-control form-control-sm" required>
                                                    @foreach($rejectionReasons as $code => $label)
                                                        <option value="{{ $code }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Decision</label>
                                                <select name="rejection_decision" class="form-control form-control-sm" required>
                                                    <option value="resubmit_documents">Reject - request matching documents</option>
                                                    <option value="close_new_application">Close current - allow new application</option>
                                                    <option value="close_final">Close and do not allow new application</option>
                                                </select>
                                                <div class="text-muted small mt-1">
                                                    Document resubmit keeps the same information. New application is for inaccurate or not aligned information.
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Admin Note</label>
                                                <textarea name="rejection_note" rows="2" class="form-control form-control-sm" placeholder="Optional details for the trader"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this application?');">
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No trader verification application is waiting for review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="review-card">
            <div class="card-body">
                <h5 class="mb-3">Recent Reviewed Applications</h5>
                <div class="table-responsive">
                    <table class="table table-bordered review-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Trader</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Reviewed By</th>
                                    <th>Reviewed At</th>
                                    <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reviewedApplications as $application)
                                <tr>
                                    <td>#{{ $application->id }}</td>
                                    <td>
                                        <strong>{{ $application->trader?->name ?: $application->trader?->username }}</strong>
                                        <div class="text-muted small">{{ $application->trader?->email }}</div>
                                    </td>
                                    <td>
                                        <span class="review-badge {{ $application->statusTone() }}">
                                            {{ $application->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $application->rejectionReasonLabel() ?? '-' }}
                                        @if($application->rejection_note)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($application->rejection_note, 100) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $application->reviewer?->username ?? '-' }}</td>
                                    <td>{{ $application->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        @if($application->isHardClosed())
                                            <form method="POST" action="{{ route('admin.trader_onboarding.reopen', $application->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Reopen this application and allow the trader to submit a new application?');">
                                                    Reopen
                                                </button>
                                            </form>
                                        @elseif($application->canStartNewApplication())
                                            <span class="text-muted small">New application allowed</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No reviewed application yet.</td>
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
