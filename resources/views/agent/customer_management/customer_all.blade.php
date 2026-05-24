@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Customer Management | HC Gaming Studio</title>

@php
    $registeredThisMonth = $customerUsers->filter(function ($item) {
        return $item->created_at && $item->created_at->isCurrentMonth();
    })->count();
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Recruitment Centre</div>
                <h1>Customer Management</h1>
                <p>Manage customers recruited under your dealership network, review account status, and share your customer registration link.</p>
            </div>
            <div class="commerce-hero__actions">
                @if(feature_enabled('referral_customer'))
                    <button type="button" class="btn btn-info" id="openInviteModal">
                        <i class="fas fa-user-plus"></i>
                        Invite Customer
                    </button>
                @else
                    <button type="button" class="btn btn-secondary" disabled title="Feature Disabled">
                        <i class="fas fa-lock"></i>
                        Invite Customer Disabled
                    </button>
                @endif
            </div>
        </section>

        <input type="hidden" id="dynamicReferralCode" value="{{ Auth::user()->customer_referral_code ?? '' }}">

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Customers</span>
                <strong>{{ number_format((int) $CustomerCount) }}</strong>
                <small>Direct customer accounts</small>
            </div>
            <div class="commerce-stat">
                <span>Active Customers</span>
                <strong>{{ number_format((int) $activeCustomer) }}</strong>
                <small>Currently active accounts</small>
            </div>
            <div class="commerce-stat">
                <span>Inactive Customers</span>
                <strong>{{ number_format((int) $inactiveCustomer) }}</strong>
                <small>Accounts requiring follow-up</small>
            </div>
            <div class="commerce-stat">
                <span>New This Month</span>
                <strong>{{ number_format((int) $registeredThisMonth) }}</strong>
                <small>Customer registrations this month</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Customer Directory</h2>
                    <p class="commerce-panel__subtitle">Track customer ownership, contact information, registration timing, and current account status.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="customerRecruitmentTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Upline</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customerUsers as $item)
                            @php
                                $customer = $item->agent;
                                $status = (int) optional($customer)->status === 1
                                    ? ['label' => 'Active', 'class' => 'status-approved']
                                    : ['label' => 'Inactive', 'class' => 'status-rejected'];
                                $upline = optional(optional($customer)->upline)->username ?: 'HC Gaming Sdn Bhd';
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <th>
                                    <div class="commerce-product-name">{{ optional($customer)->username ?? 'Unknown customer' }}</div>
                                    <div class="commerce-muted">{{ optional($customer)->name ?: 'No name provided' }}</div>
                                </th>
                                <td>
                                    <div>{{ optional($customer)->email ?: 'No email available' }}</div>
                                    <div class="commerce-muted">User ID: {{ optional($customer)->id ?: '-' }}</div>
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
                <h5 class="modal-title" id="inviteModalLabel">Invite New Customer</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <label class="commerce-muted" for="registrationLink">Customer registration link</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="registrationLink" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-info" type="button" id="copyLinkBtn">
                            <i class="fas fa-copy"></i>
                            Copy
                        </button>
                    </div>
                </div>
                <p class="commerce-muted mb-0">This link uses your customer referral code and will connect the new customer under your dealership network.</p>
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
            jQuery('#customerRecruitmentTable').DataTable({
                order: [[4, 'desc']],
                columnDefs: [{ orderable: false, targets: [5] }]
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
                        toastr.success('Customer invitation link copied.');
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
