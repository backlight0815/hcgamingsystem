@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Sales Performances | HC Gaming Studio</title>

@php
    $totalSales = $salesPerformances->sum('total_sales');
    $totalOrders = $salesPerformances->sum('order_count');
    $totalRecruitments = $downlineData->sum('downline_count');
    $topPerformer = $salesPerformances->first();
    $maxSales = max((float) ($topPerformer->total_sales ?? 0), 1);
    $averageSales = $salesPerformances->count() ? $totalSales / $salesPerformances->count() : 0;
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Sales Performance</div>
                <h1>Dealer Sales Leaderboard</h1>
                <p>Review dealership ecommerce sales contribution, order volume, average order value, and recruitment strength for dealer accounts only.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('Sales.Performances') }}" class="btn btn-outline-light">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </a>
            </div>
        </section>

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Sales</span>
                <strong>RM {{ number_format((float) $totalSales, 2) }}</strong>
                <small>Confirmed, delivery, and completed orders</small>
            </div>
            <div class="commerce-stat">
                <span>Active Dealers</span>
                <strong>{{ $salesPerformances->count() }}</strong>
                <small>Dealers with qualified sales</small>
            </div>
            <div class="commerce-stat">
                <span>Total Orders</span>
                <strong>{{ number_format((int) $totalOrders) }}</strong>
                <small>Qualified ecommerce orders</small>
            </div>
            <div class="commerce-stat">
                <span>Recruitments</span>
                <strong>{{ number_format((int) $totalRecruitments) }}</strong>
                <small>{{ $AgentCount }} active recruiting uplines</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Dealer Snapshot</h2>
                    <p class="commerce-panel__subtitle">A quick management view of the current top dealer and average dealer sales benchmark.</p>
                </div>
            </div>

            <div class="commerce-stats three mb-0">
                <div class="commerce-stat">
                    <span>Top Dealer</span>
                    <strong>{{ optional($topPerformer?->user)->username ?? 'No data' }}</strong>
                    <small>{{ $topPerformer ? 'RM ' . number_format((float) $topPerformer->total_sales, 2) : 'No qualified dealer sales yet' }}</small>
                </div>
                <div class="commerce-stat">
                    <span>Average Sales / Dealer</span>
                    <strong>RM {{ number_format((float) $averageSales, 2) }}</strong>
                    <small>Sales total divided by active dealers</small>
                </div>
                <div class="commerce-stat">
                    <span>Average Order Value</span>
                    <strong>RM {{ $totalOrders ? number_format((float) ($totalSales / $totalOrders), 2) : '0.00' }}</strong>
                    <small>Overall qualified order average</small>
                </div>
            </div>
        </section>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Sales Performance Records</h2>
                    <p class="commerce-panel__subtitle">Rankings are sorted by dealer total sales. Recruitment count is based on each dealer's direct downline data.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="salesperformances" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Dealer</th>
                            <th>Account Type</th>
                            <th>Total Sales</th>
                            <th>Orders</th>
                            <th>Average Order</th>
                            <th>Recruitments</th>
                            <th>Last Order</th>
                            <th>Contribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesPerformances as $item)
                            @php
                                $recruitmentCount = optional($downlineData->firstWhere('upline_user_id', optional($item->user)->id))->downline_count ?? 0;
                                $roleId = (int) (optional($item->user)->role_id ?? 0);
                                $roleName = $roleOptions[$roleId] ?? 'Role #' . ($roleId ?: 'N/A');
                                $contribution = min(100, round(((float) $item->total_sales / $maxSales) * 100));
                                $lastOrderAt = $item->last_order_at ? \Carbon\Carbon::parse($item->last_order_at)->format('Y-m-d H:i') : 'N/A';
                                $rankTone = $loop->iteration === 1 ? 'status-approved' : ($loop->iteration <= 3 ? 'status-delivery' : 'status-pending');
                            @endphp
                            <tr>
                                <td data-order="{{ $loop->iteration }}"><span class="commerce-status {{ $rankTone }}">#{{ $loop->iteration }}</span></td>
                                <th>
                                    <div class="commerce-product-name">{{ optional($item->user)->username ?? 'Unknown user' }}</div>
                                    <div class="commerce-muted">{{ optional($item->user)->email ?? 'No email available' }}</div>
                                </th>
                                <td>{{ $roleName }}</td>
                                <td data-order="{{ (float) $item->total_sales }}"><strong>RM {{ number_format((float) $item->total_sales, 2) }}</strong></td>
                                <td data-order="{{ (int) $item->order_count }}">{{ number_format((int) $item->order_count) }}</td>
                                <td data-order="{{ (float) $item->average_order_value }}">RM {{ number_format((float) $item->average_order_value, 2) }}</td>
                                <td data-order="{{ (int) $recruitmentCount }}">{{ number_format((int) $recruitmentCount) }}</td>
                                <td data-order="{{ $item->last_order_at ?: '' }}">{{ $lastOrderAt }}</td>
                                <td>
                                    <div class="commerce-muted mb-1">{{ $contribution }}% of leader</div>
                                    <div style="height:8px;border-radius:999px;background:#e2e8f0;overflow:hidden;min-width:140px;">
                                        <div style="width:{{ $contribution }}%;height:100%;background:#2563eb;border-radius:999px;"></div>
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
            $('#salesperformances').DataTable({
                order: [[3, 'desc']],
                columnDefs: [{ orderable: false, targets: [8] }]
            });
        }
    });
</script>
@endsection
