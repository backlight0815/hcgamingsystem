@extends('admin.admin_master')
@section('admin')

@php
    $hasPendingApplication = $latestApplication && $latestApplication->isPending();
    $hasAvailablePositions = ! empty($positions);
    $canApply = $eligibility['eligible'] && $hasAvailablePositions && ! $hasPendingApplication;
    $currentPositionLabel = match ((int) auth()->user()->role_id) {
        \App\Models\TradingPositionApplication::ROLE_LEADERSHIP => 'Leadership',
        \App\Models\TradingPositionApplication::ROLE_RECRUITER => 'Recruiter',
        default => 'Trader',
    };
@endphp

<title>Trading Position Centre | HC Gaming Studio</title>

<style>
    .position-shell { color: #172033; }
    .position-card {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }
    .position-hero { padding: 24px; }
    .position-eyebrow {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
    }
    .position-card h3,
    .position-card h4,
    .position-card h5 { color: #0f172a; }
    .position-muted { color: #64748b; }
    .position-status {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 12px;
    }
    .position-status.success { background: #dcfce7; color: #166534; }
    .position-status.warning { background: #fef3c7; color: #92400e; }
    .position-status.danger { background: #fee2e2; color: #991b1b; }
    .position-status.secondary { background: #e5e7eb; color: #374151; }
    .position-referral {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
        background: #f8fafc;
    }
    .position-referral code {
        white-space: normal;
        word-break: break-all;
    }
</style>

<div class="page-content position-shell">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Trading Position Centre</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Trading</a></li>
                            <li class="breadcrumb-item active">Positions</li>
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

        <div class="position-card position-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="position-eyebrow">Founder -> Partner -> Leader -> Recruiter -> Traders -> Public</div>
                    <h3 class="mt-2 mb-2">Apply for Recruiter or Leadership after 60 trades and 30 days from your first trade.</h3>
                    <p class="position-muted mb-0">
                        Recruiters focus on recruitment, client onboarding, and follow-up. Leadership adds strategy evaluation, trade-history review, personality evaluation, classes, and client monitoring.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    @if($canApply)
                        <span class="position-status success">Ready to Apply</span>
                    @elseif($hasPendingApplication)
                        <span class="position-status warning">Pending Review</span>
                    @elseif($eligibility['eligible'] && ! $hasAvailablePositions)
                        <span class="position-status success">{{ $currentPositionLabel }}</span>
                    @else
                        <span class="position-status secondary">Apply Button Locked</span>
                    @endif
                </div>
            </div>
        </div>

        @if($referralData)
            <div class="position-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Referral Links</h4>
                            <p class="position-muted mb-0">Use these links to invite traders under your trading structure.</p>
                        </div>
                        <span class="position-status success">{{ $referralData['direct_downlines'] }} Direct Downlines</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="position-referral">
                                <strong>Referral Code</strong>
                                <div class="mt-2"><code>{{ $referralData['referral_code'] }}</code></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="position-referral">
                                <strong>Web Registration</strong>
                                <div class="mt-2"><code>{{ $referralData['web_registration_url'] }}</code></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="position-referral">
                                <strong>Broker Registration</strong>
                                <div class="mt-2">
                                    @if($referralData['broker_registration_url'])
                                        <code>{{ $referralData['broker_registration_url'] }}</code>
                                    @else
                                        <span class="position-muted">Set BROKER_REGISTRATION_URL in environment settings.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-xl-5 mb-4">
                <div class="position-card h-100">
                    <div class="card-body">
                        <h4 class="mb-3">Eligibility</h4>
                        <div class="mb-2">Current position: <strong>{{ $currentPositionLabel }}</strong></div>
                        <div class="mb-2">First trade date: <strong>{{ $eligibility['first_trade_date'] ?? '-' }}</strong></div>
                        <div class="mb-2">Trade records: <strong>{{ $eligibility['trade_count'] }} / {{ $eligibility['required_trade_count'] ?? 60 }}</strong></div>
                        <div class="mb-2">Days from first trade: <strong>{{ $eligibility['days_since_first_trade'] ?? 0 }} / {{ $eligibility['required_days'] ?? 30 }}</strong></div>
                        <div class="mb-3">Eligible from: <strong>{{ $eligibility['eligible_from'] ?? 'After first trade' }}</strong></div>

                        @if(! $eligibility['eligible'])
                            <div class="alert alert-warning mb-0">
                                The apply button will show after your first recorded trade open date is at least 30 days old and you have at least 60 trade records.
                                @if(! ($eligibility['has_required_trade_count'] ?? false))
                                    You still need {{ $eligibility['remaining_trade_count'] ?? 0 }} more trade records.
                                @endif
                            </div>
                        @elseif(! $hasAvailablePositions)
                            <div class="alert alert-success mb-0">
                                You already hold the highest trading position available in this workflow, so there is no new application button.
                            </div>
                        @else
                            <div class="alert alert-success mb-0">
                                You can submit a position application for administration review.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7 mb-4">
                <div class="position-card">
                    <div class="card-body">
                        <h4 class="mb-1">Position Application</h4>
                        <p class="position-muted mb-4">Leadership requires strategy, trade-history, and personality evaluation. Recruiter requires marketing and client support readiness.</p>

                        @if($hasPendingApplication)
                            <div class="alert alert-info mb-0">Your latest application is waiting for administration review.</div>
                        @elseif($canApply)
                            <form method="POST" action="{{ route('trading.positions.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Apply Position</label>
                                    <select name="requested_position" class="form-control" required>
                                        <option value="">Select position</option>
                                        @foreach($positions as $value => $label)
                                            <option value="{{ $value }}" {{ old('requested_position') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Strategy Summary</label>
                                    <textarea name="strategy_summary" rows="3" class="form-control" placeholder="Required for Leadership. Explain strategy, risk parameters, analysis process, and trading plan.">{{ old('strategy_summary') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Trade History Summary</label>
                                    <textarea name="trade_history_summary" rows="3" class="form-control" placeholder="Required for Leadership. Summarize your trading history and consistency.">{{ old('trade_history_summary') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Personality / Leadership Summary</label>
                                    <textarea name="personality_summary" rows="3" class="form-control" placeholder="Required for Leadership. Explain how you guide clients responsibly.">{{ old('personality_summary') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Marketing Plan</label>
                                    <textarea name="marketing_plan" rows="3" class="form-control" placeholder="Required for Recruiter. Explain marketing, posting, and lead follow-up plan.">{{ old('marketing_plan') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Client Support Plan</label>
                                    <textarea name="client_support_plan" rows="3" class="form-control" required placeholder="Explain how you will follow up, support, and monitor clients.">{{ old('client_support_plan') }}</textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Supporting Document</label>
                                    <input type="file" name="supporting_document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <div class="form-text">Optional: trading plan, report, proof, or evaluation document. Max 10MB.</div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-send-plane-line"></i> Submit Application
                                </button>
                            </form>
                        @elseif($eligibility['eligible'] && ! $hasAvailablePositions)
                            <div class="alert alert-success mb-0">
                                You are already marked as {{ $currentPositionLabel }}. There is no higher trading position to apply for from this page.
                            </div>
                        @else
                            <div class="alert alert-secondary mb-0">
                                No application action is available right now. Complete the eligibility requirements above to unlock the form.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="position-card">
            <div class="card-body">
                <h5 class="mb-3">Application History</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                                <th>Review Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $application)
                                <tr>
                                    <td>#{{ $application->id }}</td>
                                    <td>{{ $application->requestedPositionLabel() }}</td>
                                    <td><span class="position-status {{ $application->statusTone() }}">{{ $application->statusLabel() }}</span></td>
                                    <td>{{ $application->submitted_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $application->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $application->review_note ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No position application yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($latestApplication && in_array($latestApplication->status, [\App\Models\TradingPositionApplication::STATUS_APPROVED, \App\Models\TradingPositionApplication::STATUS_REJECTED], true))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Swal) {
                Swal.fire({
                    title: @json($latestApplication->statusLabel()),
                    text: @json($latestApplication->review_note ?: 'Administration has reviewed your trading position application.'),
                    icon: @json($latestApplication->status === \App\Models\TradingPositionApplication::STATUS_APPROVED ? 'success' : 'warning')
                });
            }
        });
    </script>
@endif

@endsection
