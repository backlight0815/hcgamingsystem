@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>My E-Wallet History | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Wallet Ledger</div>
                <h1>My E-Wallet History</h1>
                <p>Audit every wallet credit and debit movement with remarks for reconciliation.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('add.wallet') }}" class="btn btn-info">
                    <i class="fas fa-plus-circle"></i>
                    Top Up
                </a>
                <a href="{{ route('My.Wallet') }}" class="btn btn-outline-light">
                    <i class="fas fa-wallet"></i>
                    Wallet
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Credit</span>
                <strong>RM {{ number_format((float) $totalCreditAmount, 2) }}</strong>
                <small>Incoming wallet value</small>
            </div>
            <div class="commerce-stat">
                <span>Total Debit</span>
                <strong>RM {{ number_format((float) $totalDebitAmount, 2) }}</strong>
                <small>Outgoing wallet usage</small>
            </div>
            <div class="commerce-stat">
                <span>Current Balance</span>
                <strong>RM {{ number_format((float) $currentBalance, 2) }}</strong>
                <small>Latest available amount</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Wallet Ledger</h2>
                    <p class="commerce-panel__subtitle">A chronological record of credits, debits, and system remarks.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="walletHistoryTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Remarks</th>
                            <th>Transaction Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($walletHistoryData as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>RM {{ number_format((float) $item->amount, 2) }}</strong></td>
                                <td>{{ $item->type }}</td>
                                <td>{{ $item->remarks ?: 'No remarks' }}</td>
                                <td>{{ optional($item->created_at)->format('Y-m-d H:i') ?? $item->created_at }}</td>
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
            $('#walletHistoryTable').DataTable({
                order: [[4, 'desc']]
            });
        }
    });
</script>
@endsection
