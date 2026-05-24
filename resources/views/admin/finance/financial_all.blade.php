@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Dealer E-Wallet Request | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Financial Centre</div>
                <h1>Dealer E-Wallet Requests</h1>
                <p>Review dealer top-up submissions, verify receipt proof, and approve or reject wallet credits from one controlled workspace.</p>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Requests</span>
                <strong>{{ $WalletRequestCount }}</strong>
                <small>All dealer wallet submissions</small>
            </div>
            <div class="commerce-stat">
                <span>Pending Amount</span>
                <strong>RM {{ number_format((float) $processingTotal, 2) }}</strong>
                <small>Awaiting administration review</small>
            </div>
            <div class="commerce-stat">
                <span>Approved Amount</span>
                <strong>RM {{ number_format((float) $ApprovedTotal, 2) }}</strong>
                <small>Credited into dealer wallets</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Top-Up Review Queue</h2>
                    <p class="commerce-panel__subtitle">Only pending requests can be approved or rejected. Receipt images open in a new tab for inspection.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="walletrequest" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Dealer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Receipt</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ewalletrequest as $item)
                            @php
                                $walletStatus = [
                                    '0' => ['label' => 'Pending', 'class' => 'status-pending'],
                                    '1' => ['label' => 'Approved', 'class' => 'status-approved'],
                                    '-1' => ['label' => 'Rejected', 'class' => 'status-rejected'],
                                ][(string) $item->status] ?? ['label' => 'Unknown', 'class' => 'status-pending'];
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <th>
                                    <div class="commerce-product-name">{{ optional($item->user)->username ?? 'Unknown dealer' }}</div>
                                    <div class="commerce-muted">Request #{{ $item->id }}</div>
                                </th>
                                <td><strong>RM {{ number_format((float) $item->amount, 2) }}</strong></td>
                                <td><span class="commerce-status {{ $walletStatus['class'] }}">{{ $walletStatus['label'] }}</span></td>
                                <td>{{ optional($item->created_at)->format('Y-m-d H:i') ?? $item->created_at }}</td>
                                <td>
                                    @if($item->receipt)
                                        <a href="{{ asset($item->receipt) }}" target="_blank" rel="noopener">
                                            <img src="{{ asset($item->receipt) }}" class="commerce-thumb" alt="Payment proof">
                                        </a>
                                    @else
                                        <span class="commerce-muted">No receipt</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="commerce-actions">
                                        @if((string) $item->status === '0')
                                            <a href="{{ route('update.wallets.to.approve.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Approve request" onclick="return confirm('Do you want to approve this wallet request?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="{{ route('update.wallets.to.reject.status', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Reject request" onclick="return confirm('Do you want to reject this wallet request?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        @else
                                            <span class="commerce-muted">Reviewed</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (window.jQuery && $.fn.DataTable) {
            $('#walletrequest').DataTable({
                order: [[4, 'desc']],
                columnDefs: [{ orderable: false, targets: [5, 6] }]
            });
        }
    });
</script>
@endsection
