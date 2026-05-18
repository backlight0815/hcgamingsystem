@extends('admin.admin_master')
@section('admin')

@php
    $prefill = ($latestApplication && $latestApplication->canResubmit()) ? $latestApplication : null;
    $fieldLocked = (bool) $prefill;
    $isHardClosed = $latestApplication && $latestApplication->isHardClosed();
    $isNewApplicationAllowed = $latestApplication && $latestApplication->canStartNewApplication();
@endphp

<title>Trader Verification | HC Gaming Studio</title>

<style>
    .onboarding-shell {
        color: #172033;
    }

    .onboarding-hero,
    .onboarding-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
    }

    .onboarding-hero {
        padding: 26px;
    }

    .onboarding-eyebrow {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .onboarding-hero h3,
    .onboarding-card h4,
    .onboarding-card h5 {
        color: #0f172a;
    }

    .onboarding-muted {
        color: #64748b;
    }

    .onboarding-status {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 12px;
    }

    .onboarding-status.info { background: #dbeafe; color: #1d4ed8; }
    .onboarding-status.success { background: #dcfce7; color: #166534; }
    .onboarding-status.warning { background: #fef3c7; color: #92400e; }
    .onboarding-status.danger { background: #fee2e2; color: #991b1b; }

    .onboarding-detail {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px 14px;
        height: 100%;
    }

    .onboarding-detail span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .onboarding-detail strong {
        color: #111827;
        font-size: 15px;
    }
</style>

<div class="page-content onboarding-shell">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Trader Verification</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('trader.onboarding.show') }}">HC Trading</a></li>
                            <li class="breadcrumb-item active">Verification</li>
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

        <div class="onboarding-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="onboarding-eyebrow">Account access locked</div>
                    <h3 class="mt-2 mb-2">Submit your trader verification before using trading tools.</h3>
                    <p class="onboarding-muted mb-0">
                        Administration will verify client status, deposit record, Discord identity, broker UID, broker email, and supporting documentation.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    @if($latestApplication)
                        <span class="onboarding-status {{ $latestApplication->statusTone() }}">
                            {{ $latestApplication->statusLabel() }}
                        </span>
                    @else
                        <span class="onboarding-status info">Not Submitted</span>
                    @endif
                </div>
            </div>
        </div>

        @if($latestApplication)
            <div class="onboarding-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Latest Application</h4>
                            <p class="onboarding-muted mb-0">
                                Submitted {{ $latestApplication->submitted_at?->format('Y-m-d H:i') ?? '-' }}
                            </p>
                        </div>
                        <span class="onboarding-status {{ $latestApplication->statusTone() }}">
                            {{ $latestApplication->statusLabel() }}
                        </span>
                    </div>

                    @if($latestApplication->isPending())
                        <div class="alert alert-info mb-3">
                            Your application is waiting for administration review. Access will unlock automatically after approval.
                        </div>
                    @elseif($latestApplication->canResubmit())
                        <div class="alert alert-warning mb-3">
                            Your application needs supporting documents that align with the information already submitted. Please review the reason and resubmit.
                        </div>
                    @elseif($isNewApplicationAllowed)
                        <div class="alert alert-warning mb-3">
                            Your previous application was closed because the information was not aligned. You may submit a new application with more accurate information.
                        </div>
                    @elseif($isHardClosed)
                        <div class="alert alert-danger mb-3">
                            This application will not proceed. Please contact the HC person in charge to know more.
                        </div>
                    @elseif($latestApplication->isApproved())
                        <div class="alert alert-success mb-3">
                            Your trader verification is approved. Trading access is unlocked.
                        </div>
                    @endif

                    @if($latestApplication->isRejected())
                        <div class="mb-3">
                            <strong>Reason:</strong>
                            {{ $latestApplication->rejectionReasonLabel() ?? 'Administration review' }}
                            @if($latestApplication->rejection_note)
                                <div class="onboarding-muted mt-1">{{ $latestApplication->rejection_note }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="onboarding-detail">
                                <span>HC Client</span>
                                <strong>{{ $latestApplication->is_client ? 'Yes' : 'No' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="onboarding-detail">
                                <span>Deposit</span>
                                <strong>{{ $latestApplication->has_deposit ? 'Yes' : 'No' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="onboarding-detail">
                                <span>Deposit Amount</span>
                                <strong>{{ $latestApplication->deposit_amount ? 'USD '.number_format((float) $latestApplication->deposit_amount, 2) : '-' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="onboarding-detail">
                                <span>Discord</span>
                                <strong>{{ $latestApplication->discord_username }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="onboarding-detail">
                                <span>Broker UID</span>
                                <strong>{{ $latestApplication->broker_uid }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="onboarding-detail">
                                <span>Broker Email</span>
                                <strong>{{ $latestApplication->broker_email }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($canSubmit)
            <div class="onboarding-card mb-4">
                <div class="card-body">
                    <h4 class="mb-1">{{ $prefill ? 'Resubmit Supporting Documents' : 'New Trader Application' }}</h4>
                    <p class="onboarding-muted mb-4">
                        {{ $prefill ? 'Upload proof that matches the information already submitted.' : 'Submit accurate trader information and clear proof for administration review.' }}
                    </p>

                    <form method="POST" action="{{ route('trader.onboarding.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Are you an HC client?</label>
                                @if($fieldLocked)
                                    <input type="hidden" name="is_client" value="{{ $prefill->is_client ? 1 : 0 }}">
                                @endif
                                <select name="{{ $fieldLocked ? 'is_client_display' : 'is_client' }}" class="form-control" required {{ $fieldLocked ? 'disabled' : '' }}>
                                    <option value="">Select</option>
                                    <option value="1" {{ old('is_client', $prefill?->is_client) === true || old('is_client') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('is_client', $prefill?->is_client) === false || old('is_client') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Do you have a deposit?</label>
                                @if($fieldLocked)
                                    <input type="hidden" name="has_deposit" value="{{ $prefill->has_deposit ? 1 : 0 }}">
                                @endif
                                <select name="{{ $fieldLocked ? 'has_deposit_display' : 'has_deposit' }}" class="form-control" required {{ $fieldLocked ? 'disabled' : '' }}>
                                    <option value="">Select</option>
                                    <option value="1" {{ old('has_deposit', $prefill?->has_deposit) === true || old('has_deposit') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('has_deposit', $prefill?->has_deposit) === false || old('has_deposit') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Deposit Amount</label>
                                <input type="number" name="deposit_amount" step="0.01" min="0" class="form-control" value="{{ old('deposit_amount', $prefill?->deposit_amount) }}" placeholder="Example: 500.00" {{ $fieldLocked ? 'readonly' : '' }}>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Discord Username</label>
                                <input type="text" name="discord_username" class="form-control" value="{{ old('discord_username', $prefill?->discord_username) }}" required placeholder="Example: hc_trader#1234" {{ $fieldLocked ? 'readonly' : '' }}>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Broker UID</label>
                                <input type="text" name="broker_uid" class="form-control" value="{{ old('broker_uid', $prefill?->broker_uid) }}" required {{ $fieldLocked ? 'readonly' : '' }}>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Broker Email Address</label>
                                <input type="email" name="broker_email" class="form-control" value="{{ old('broker_email', $prefill?->broker_email) }}" required {{ $fieldLocked ? 'readonly' : '' }}>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Information / Documentation</label>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="form-text">Accepted: PDF, JPG, JPEG, PNG. Maximum 5MB.</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Additional Note</label>
                                <textarea name="trader_note" rows="3" class="form-control" placeholder="Any deposit reference, account detail, or note for administration.">{{ old('trader_note', $prefill?->trader_note) }}</textarea>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-send-plane-line"></i> Submit for Review
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($applications->count() > 1)
            <div class="onboarding-card">
                <div class="card-body">
                    <h5 class="mb-3">Application History</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Reviewed</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $application)
                                    <tr>
                                        <td>#{{ $application->id }}</td>
                                        <td>
                                            <span class="onboarding-status {{ $application->statusTone() }}">
                                                {{ $application->statusLabel() }}
                                            </span>
                                        </td>
                                        <td>{{ $application->submitted_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>{{ $application->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>{{ $application->rejectionReasonLabel() ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@if($latestApplication && $latestApplication->isRejected())
    <div class="modal fade" id="traderApplicationNoticeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-{{ $isHardClosed ? 'danger' : 'warning' }}">
                    <h5 class="modal-title {{ $isHardClosed ? 'text-white' : 'text-dark' }}">
                        @if($isHardClosed)
                            Application Closed
                        @elseif($isNewApplicationAllowed)
                            New Application Allowed
                        @else
                            Application Requires Document Resubmission
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($isHardClosed)
                        <p class="mb-2">Your trader verification application will not proceed.</p>
                        <p class="mb-0">Please contact the HC person in charge to know more.</p>
                    @elseif($isNewApplicationAllowed)
                        <p class="mb-2">Administration closed your previous application because the submitted information was not aligned.</p>
                        <p class="mb-0">You may submit a new application with accurate information and documentation.</p>
                    @else
                        <p class="mb-2">Administration has requested documents that align with the information you submitted.</p>
                        <p class="mb-0"><strong>Reason:</strong> {{ $latestApplication->rejectionReasonLabel() ?? 'Administration review' }}</p>
                    @endif

                    @if($latestApplication->rejection_note)
                        <hr>
                        <p class="mb-0">{{ $latestApplication->rejection_note }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-{{ $isHardClosed ? 'danger' : 'warning' }}" data-bs-dismiss="modal">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalElement = document.getElementById('traderApplicationNoticeModal');
            if (window.bootstrap && modalElement) {
                new bootstrap.Modal(modalElement).show();
            }
        });
    </script>
@endif

@endsection
