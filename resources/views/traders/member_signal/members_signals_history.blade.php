@extends('admin.admin_master')
@section('admin')

<title>{{ $pageTitle }} | HC Gaming Studio</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .signal-list-shell {
        color: #1f2937;
    }

    .list-header,
    .summary-tile,
    .filter-panel,
    .table-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .list-header {
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .list-header h4 {
        margin: 0;
        color: #0f172a;
        font-weight: 800;
    }

    .list-header p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px;
    }

    .summary-tile {
        padding: 16px;
    }

    .summary-tile span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .summary-tile strong {
        display: block;
        color: #0f172a;
        font-size: 26px;
        margin-top: 6px;
    }

    .filter-panel,
    .table-panel {
        padding: 18px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-active { background: #dbeafe; color: #1d4ed8; }
    .status-tp { background: #dcfce7; color: #15803d; }
    .status-sl { background: #fee2e2; color: #b91c1c; }
    .status-cancel { background: #fef3c7; color: #92400e; }
    .status-be { background: #cffafe; color: #0e7490; }
    .status-done { background: #e5e7eb; color: #374151; }

    #memberSignalsTable {
        width: 100% !important;
    }

    #memberSignalsTable th {
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    #memberSignalsTable td {
        vertical-align: middle;
    }

    @media (max-width: 1300px) {
        .summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $statusClass = function ($signal) {
        $status = (int) $signal->status;

        if ((int) $signal->IsDone === 1 || $status === 14) {
            return 'status-done';
        }

        if ((int) $signal->IsBE === 1 || $status === 15) {
            return 'status-be';
        }

        if ($status === 13) {
            return 'status-sl';
        }

        if ($status === 12) {
            return 'status-cancel';
        }

        if ($status >= 2 && $status <= 11) {
            return 'status-tp';
        }

        return 'status-active';
    };

    $statusText = function ($signal) use ($statusLabels) {
        $status = (int) $signal->status;

        if ((int) $signal->IsDone === 1 || $status === 14) {
            return 'Done';
        }

        if ((int) $signal->IsBE === 1 || $status === 15) {
            return 'BE';
        }

        return $statusLabels[$status] ?? 'Active';
    };
@endphp

<div class="page-content signal-list-shell">
    <div class="container-fluid">

        <div class="list-header mb-3">
            <div>
                <h4>{{ $pageTitle }}</h4>
                <p>{{ $pageSubtitle }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('member.signals.dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
                <a href="{{ route('member.signals.active') }}" class="btn btn-primary">Active Signals</a>
            </div>
        </div>

        <div class="summary-grid mb-3">
            <div class="summary-tile">
                <span>Total</span>
                <strong>{{ $totalSignals }}</strong>
            </div>
            <div class="summary-tile">
                <span>TP</span>
                <strong>{{ $totalTP }}</strong>
            </div>
            <div class="summary-tile">
                <span>SL</span>
                <strong>{{ $totalSL }}</strong>
            </div>
            <div class="summary-tile">
                <span>Cancelled</span>
                <strong>{{ $totalCancel }}</strong>
            </div>
            <div class="summary-tile">
                <span>BE</span>
                <strong>{{ $totalBE }}</strong>
            </div>
            <div class="summary-tile">
                <span>Done</span>
                <strong>{{ $totalDone }}</strong>
            </div>
        </div>

        @if($showFilters)
            <div class="filter-panel mb-3">
                <form method="GET" action="{{ route('member.signals.history') }}" class="row g-2 align-items-end">
                    <div class="col-lg-3">
                        <label for="quick_range" class="form-label">Quick Range</label>
                        <select id="quick_range" name="quick_range" class="form-control">
                            <option value="">All Time</option>
                            <option value="today" {{ request('quick_range') === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="7days" {{ request('quick_range') === '7days' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="30days" {{ request('quick_range') === '30days' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="90days" {{ request('quick_range') === '90days' ? 'selected' : '' }}>Last 90 Days</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="from_date" class="form-label">From</label>
                        <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                    </div>
                    <div class="col-lg-3">
                        <label for="to_date" class="form-label">To</label>
                        <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                    </div>
                    <div class="col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ $resetRoute }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        @endif

        <div class="table-panel">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="memberSignalsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Pair</th>
                            <th>Entry</th>
                            <th>SL</th>
                            <th>TP1</th>
                            <th>TP2</th>
                            <th>Progress</th>
                            <th>Risk</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($signals as $signal)
                            <tr>
                                <td><strong>{{ $signal->signal_code ?? '-' }}</strong></td>
                                <td>{{ $signal->trading_pair }}</td>
                                <td>{{ $signal->entry_price ?? '-' }}</td>
                                <td>{{ $signal->stop_loss ?? '-' }}</td>
                                <td>{{ $signal->target_1 ?? '-' }}</td>
                                <td>{{ $signal->target_2 ?? '-' }}</td>
                                <td><span class="status-pill {{ $statusClass($signal) }}">{{ $statusText($signal) }}</span></td>
                                <td>{{ $signal->risk_level ?? '-' }}</td>
                                <td>{{ $signal->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('member.signals.view', $signal->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery !== 'undefined') {
        $('#memberSignalsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[8, 'desc']]
        });
    }
});
</script>

@endsection
