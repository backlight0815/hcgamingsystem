@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Agent Management | HC Gaming Studio</title>

@php
    $totalCommission = $userData->sum(function ($item) {
        return (float) ($item->commission_earned ?? 0);
    });
    $registeredThisMonth = $userData->filter(function ($item) {
        return $item->created_at && $item->created_at->isCurrentMonth();
    })->count();
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Recruitment Centre</div>
                <h1>Dealer Agent Management</h1>
                <p>Monitor recruited dealers, account status, commission contribution, upline ownership, and registration activity from one professional workspace.</p>
            </div>
            <div class="commerce-hero__actions">
                @if(feature_enabled('referral_dealer'))
                    <button type="button" class="btn btn-info" id="openInviteModal">
                        <i class="fas fa-user-plus"></i>
                        Invite Dealer
                    </button>
                @else
                    <button type="button" class="btn btn-secondary" disabled title="Feature Disabled">
                        <i class="fas fa-lock"></i>
                        Invite Dealer Disabled
                    </button>
                @endif
            </div>
        </section>

        <input type="hidden" id="dynamicReferralCode" value="{{ Auth::user()->referral_code ?? '' }}">

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Dealers</span>
                <strong>{{ number_format((int) $AgentCount) }}</strong>
                <small>Direct recruited dealer accounts</small>
            </div>
            <div class="commerce-stat">
                <span>Active Dealers</span>
                <strong>{{ number_format((int) $activeAgent) }}</strong>
                <small>Currently active accounts</small>
            </div>
            <div class="commerce-stat">
                <span>Inactive Dealers</span>
                <strong>{{ number_format((int) $inactiveCount) }}</strong>
                <small>Accounts requiring follow-up</small>
            </div>
            <div class="commerce-stat">
                <span>Commission Points</span>
                <strong>{{ number_format($totalCommission, 2) }}</strong>
                <small>{{ $registeredThisMonth }} dealer(s) joined this month</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Dealer Directory</h2>
                    <p class="commerce-panel__subtitle">Review direct dealer recruitment performance, contact information, and account status.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dealerRecruitmentTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Dealer</th>
                            <th>Contact</th>
                            <th>Commission Earned</th>
                            <th>Upline</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($userData as $item)
                            @php
                                $agent = $item->agent;
                                $status = (int) optional($agent)->status === 1
                                    ? ['label' => 'Active', 'class' => 'status-approved']
                                    : ['label' => 'Inactive', 'class' => 'status-rejected'];
                                $upline = optional(optional($agent)->upline)->username ?: 'HC Gaming Sdn Bhd';
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <th>
                                    <div class="commerce-product-name">{{ optional($agent)->username ?? 'Unknown dealer' }}</div>
                                    <div class="commerce-muted">{{ optional($agent)->name ?: 'No name provided' }}</div>
                                </th>
                                <td>
                                    <div>{{ optional($agent)->email ?: 'No email available' }}</div>
                                    <div class="commerce-muted">User ID: {{ optional($agent)->id ?: '-' }}</div>
                                </td>
                                <td data-order="{{ (float) ($item->commission_earned ?? 0) }}">
                                    <strong>{{ number_format((float) ($item->commission_earned ?? 0), 2) }} pts</strong>
                                </td>
                                <td>{{ $upline }}</td>
                                <td data-order="{{ $item->created_at ? $item->created_at->timestamp : 0 }}">
                                    {{ optional($item->created_at)->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                                <td><span class="commerce-status {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inviteModalLabel">Invite New Dealer</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <label class="commerce-muted" for="registrationLink">Dealer registration link</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="registrationLink" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-info" type="button" id="copyLinkBtn">
                            <i class="fas fa-copy"></i>
                            Copy
                        </button>
                    </div>
                </div>
                <p class="commerce-muted mb-0">This link uses your dealer referral code and will connect the new dealer under your recruitment network.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (window.jQuery && jQuery.fn.DataTable) {
            jQuery('#dealerRecruitmentTable').DataTable({
                order: [[5, 'desc']],
                columnDefs: [{ orderable: false, targets: [6] }]
            });
        }

        const inviteButton = document.getElementById('openInviteModal');
        const copyButton = document.getElementById('copyLinkBtn');
        const linkInput = document.getElementById('registrationLink');
        const referralInput = document.getElementById('dynamicReferralCode');
        const registerUrl = @json(route('register'));

        function invitationLink() {
            const separator = registerUrl.indexOf('?') === -1 ? '?' : '&';
            return registerUrl + separator + 'referral_code=' + encodeURIComponent(referralInput ? referralInput.value : '');
        }

        function showInviteModal() {
            linkInput.value = invitationLink();

            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('inviteModal')).show();
                return;
            }

            if (window.jQuery && typeof jQuery.fn.modal === 'function') {
                jQuery('#inviteModal').modal('show');
            }
        }

        if (inviteButton) {
            inviteButton.addEventListener('click', showInviteModal);
        }

        if (copyButton) {
            copyButton.addEventListener('click', function () {
                linkInput.select();

                const copied = function () {
                    if (window.toastr) {
                        toastr.success('Dealer invitation link copied.');
                    } else {
                        alert('Link copied to clipboard!');
                    }
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(linkInput.value).then(copied);
                } else {
                    document.execCommand('copy');
                    copied();
                }
            });
        }
    });
</script>
@endsection
