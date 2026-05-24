@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Dealer Commission | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Commission Centre</div>
                <h1>Dealer Commission Ledger</h1>
                <p>Monitor commission generated from downline sales and reconcile payout points across the dealership network.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('admin.commission.setup') }}" class="btn btn-info">
                    <i class="fas fa-sliders-h"></i>
                    Commission Setup
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Commission</span>
                <strong>{{ number_format((float) $totalCommission, 2) }} pts</strong>
                <small>Accumulated commission points</small>
            </div>
            <div class="commerce-stat">
                <span>Entries</span>
                <strong>{{ $dealercommission->count() }}</strong>
                <small>Visible commission records</small>
            </div>
            <div class="commerce-stat">
                <span>Review Scope</span>
                <strong>Network</strong>
                <small>Dealer and downline order activity</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Commission Records</h2>
                    <p class="commerce-panel__subtitle">Each row links a downline user, order, commission value, and earning date.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dealerCommissionTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Downline User</th>
                            <th>Order ID</th>
                            <th>Username</th>
                            <th>Commission Earned</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dealercommission as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>#{{ $item->downline_user_id }}</td>
                                <td>#{{ $item->order_id }}</td>
                                <th>{{ optional($item->downlineUserbane)->username ?? 'Unknown user' }}</th>
                                <td><strong>{{ number_format((float) $item->commission_amount, 2) }} pts</strong></td>
                                <td>{{ optional($item->updated_at)->format('Y-m-d H:i') ?? $item->updated_at }}</td>
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
            $('#dealerCommissionTable').DataTable({
                order: [[5, 'desc']]
            });
        }
    });
</script>
@endsection
