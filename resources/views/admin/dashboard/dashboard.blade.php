@extends('admin.admin_master')
@section('admin')

@php
    $roleId = (int) auth()->user()->role_id;
    $profile = $dashboardProfile ?? [
        'eyebrow' => 'Workspace',
        'title' => 'Dashboard',
        'subtitle' => 'Your available tools are controlled by role access and enabled business modules.',
        'accent' => 'primary',
    ];

    $toneMap = [
        'primary' => ['soft' => 'rgba(15, 156, 243, .12)', 'text' => '#4bb3fd'],
        'success' => ['soft' => 'rgba(26, 188, 156, .12)', 'text' => '#36d0b6'],
        'warning' => ['soft' => 'rgba(245, 183, 77, .14)', 'text' => '#f5b74d'],
        'danger' => ['soft' => 'rgba(244, 106, 106, .14)', 'text' => '#f46a6a'],
        'info' => ['soft' => 'rgba(80, 165, 241, .14)', 'text' => '#50a5f1'],
        'secondary' => ['soft' => 'rgba(154, 166, 189, .12)', 'text' => '#9aa6bd'],
        'purple' => ['soft' => 'rgba(111, 132, 255, .14)', 'text' => '#8ea0ff'],
    ];
@endphp

<title>Dashboard | HC Gaming</title>

<style>
    .role-dashboard-hero,
    .role-dashboard-card,
    .role-dashboard-table {
        border: 1px solid #31384c;
        background: #252b3b;
        border-radius: 8px;
        color: #d6deeb;
    }

    .role-dashboard-hero {
        padding: 24px;
    }

    .role-dashboard-eyebrow {
        color: #b8c4d6;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: .08em;
        font-weight: 700;
    }

    .role-dashboard-hero h2 {
        color: #eef2f7;
        font-size: 28px;
        line-height: 1.25;
        margin: 8px 0;
    }

    .role-dashboard-card .card-title,
    .role-dashboard-card h4,
    .role-dashboard-metric h4,
    .role-dashboard-module .fw-semibold {
        color: #eef2f7;
    }

    .role-dashboard-muted,
    .role-dashboard-card p,
    .role-dashboard-activity-meta {
        color: #b8c4d6;
    }

    .role-dashboard-metric {
        height: 100%;
        border: 1px solid #31384c;
        background: #252b3b;
        border-radius: 8px;
        padding: 18px;
    }

    .role-dashboard-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .role-dashboard-value {
        color: #eef2f7;
        font-size: 24px;
        font-weight: 700;
        margin: 12px 0 4px;
    }

    .role-dashboard-action {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 8px;
        border: 1px solid #31384c;
        color: #d6deeb;
        transition: background .15s ease, border-color .15s ease;
    }

    .role-dashboard-action:hover {
        color: #fff;
        background: #2b3144;
        border-color: #3c465f;
    }

    .role-dashboard-action i {
        font-size: 18px;
        color: #50a5f1;
    }

    .role-dashboard-activity {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 13px 0;
        border-bottom: 1px solid #31384c;
    }

    .role-dashboard-activity:last-child {
        border-bottom: 0;
    }

    .role-dashboard-activity-title {
        color: #eef2f7;
        font-weight: 600;
    }

    .role-dashboard-module {
        border: 1px solid #31384c;
        border-radius: 8px;
        padding: 14px;
        height: 100%;
    }

    .role-dashboard-table th,
    .role-dashboard-table td {
        border-color: #31384c;
        color: #d6deeb;
        vertical-align: middle;
    }

    .role-dashboard-table tbody tr {
        background: #252b3b;
    }

    .role-dashboard-table thead th {
        background: #071d3d;
        color: #fff;
    }

    .role-dashboard-card .badge,
    .role-dashboard-table .badge {
        color: #fff;
    }

    @media (max-width: 767px) {
        .role-dashboard-hero h2 {
            font-size: 22px;
        }

        .role-dashboard-activity {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Dashboard</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="role-dashboard-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-xl-8">
                    <div class="role-dashboard-eyebrow">{{ $profile['eyebrow'] }}</div>
                    <h2>{{ $profile['title'] }}</h2>
                    <p class="role-dashboard-muted mb-0">{{ $profile['subtitle'] }}</p>
                </div>
                <div class="col-xl-4">
                    <div class="row g-2">
                        @foreach($dashboardModules ?? [] as $module)
                            <div class="col-12">
                                <div class="role-dashboard-module">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold text-light">{{ $module['label'] }}</div>
                                            <div class="small role-dashboard-muted">{{ $module['feature_name'] }}</div>
                                        </div>
                                        <span class="badge bg-{{ $module['enabled'] ? 'success' : 'secondary' }}">
                                            {{ $module['enabled'] ? 'On' : 'Off' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            @forelse($dashboardMetrics ?? [] as $metric)
                @php
                    $tone = $toneMap[$metric['tone']] ?? $toneMap['primary'];
                @endphp
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="role-dashboard-metric">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="role-dashboard-muted small">{{ $metric['label'] }}</div>
                                <div class="role-dashboard-value">{{ $metric['value'] }}</div>
                                <div class="role-dashboard-muted small">{{ $metric['caption'] }}</div>
                            </div>
                            <span class="role-dashboard-icon" style="background: {{ $tone['soft'] }}; color: {{ $tone['text'] }};">
                                <i class="{{ $metric['icon'] }}"></i>
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 mb-4">
                    <div class="alert alert-secondary mb-0">
                        No dashboard metrics are available for the current module configuration.
                    </div>
                </div>
            @endforelse
        </div>

        <div class="row">
            <div class="col-xl-4 mb-4">
                <div class="card role-dashboard-card mb-0">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Quick Actions</h4>
                        @forelse($dashboardActions ?? [] as $action)
                            <a href="{{ $action['url'] }}" class="role-dashboard-action mb-2">
                                <i class="{{ $action['icon'] }}"></i>
                                <span>{{ $action['label'] }}</span>
                            </a>
                        @empty
                            <p class="mb-0">No direct actions are available while the related module is turned off.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-8 mb-4">
                <div class="card role-dashboard-card mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <h4 class="card-title mb-0">Recent Activity</h4>
                            <span class="badge bg-{{ $profile['accent'] === 'purple' ? 'primary' : $profile['accent'] }}">
                                {{ $profile['eyebrow'] }}
                            </span>
                        </div>

                        @forelse($recentActivity ?? [] as $activity)
                            <div class="role-dashboard-activity">
                                <div>
                                    <div class="role-dashboard-activity-title">{{ $activity['title'] }}</div>
                                    <div class="role-dashboard-activity-meta small">{{ $activity['meta'] }}</div>
                                </div>
                                <span class="badge bg-{{ $activity['tone'] }}">{{ $activity['value'] }}</span>
                            </div>
                        @empty
                            <p class="mb-0">Recent activity will appear here once the enabled module has new records.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if($ecommerceEnabled && in_array($roleId, [1, 2, 350, 700], true))
            <div class="row">
                <div class="col-12">
                    <div class="card role-dashboard-card">
                        <div class="card-body">
                            <h4 class="card-title mb-3">Latest Orders</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered role-dashboard-table mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Total</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                            <th>Transaction Date</th>
                                            <th>Payment Proof</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($shippingorders as $item)
                                            @php
                                                $statusLabels = [-1 => 'Rejected', 0 => 'Processing', 1 => 'Confirmed', 2 => 'Delivery', 3 => 'Completed'];
                                                $statusTones = [-1 => 'danger', 0 => 'secondary', 1 => 'warning', 2 => 'info', 3 => 'success'];
                                                $status = (int) $item->status;
                                            @endphp
                                            <tr>
                                                <td>#{{ $item->id }}</td>
                                                <td>{{ optional($item->user)->username ?? '-' }}</td>
                                                <td>RM {{ number_format((float) $item->total_amount, 2) }}</td>
                                                <td>{{ $item->orderItems->sum('quantity') }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $statusTones[$status] ?? 'secondary' }}">
                                                        {{ $statusLabels[$status] ?? 'Unknown' }}
                                                    </span>
                                                </td>
                                                <td>{{ optional($item->created_at)->format('d M Y, h:i A') }}</td>
                                                <td>
                                                    @if($item->payment_proof)
                                                        <a href="{{ asset($item->payment_proof) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                            View
                                                        </a>
                                                    @else
                                                        <span class="role-dashboard-muted">No proof</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center role-dashboard-muted">No order records found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
