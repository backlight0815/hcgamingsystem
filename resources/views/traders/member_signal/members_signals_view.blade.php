@extends('admin.admin_master')
@section('admin')

<title>Signal Details | HC Gaming Studio</title>

<style>
    .signal-detail-shell {
        color: #1f2937;
    }

    .detail-hero,
    .detail-panel,
    .level-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .detail-hero {
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .detail-eyebrow {
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .detail-hero h4 {
        margin: 4px 0 6px;
        color: #0f172a;
        font-weight: 800;
    }

    .detail-hero p {
        margin: 0;
        color: #64748b;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 7px 12px;
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

    .detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 16px;
        align-items: start;
    }

    .detail-panel {
        padding: 20px;
    }

    .panel-title {
        color: #0f172a;
        font-weight: 800;
        margin-bottom: 14px;
    }

    .setup-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .level-card {
        padding: 14px;
        box-shadow: none;
    }

    .level-card span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .level-card strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        margin-top: 6px;
    }

    .target-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .target-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f8fafc;
        padding: 12px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    .target-item span {
        color: #64748b;
        font-weight: 700;
    }

    .target-item strong {
        color: #15803d;
    }

    .side-row {
        border-bottom: 1px solid #e5e7eb;
        padding: 11px 0;
    }

    .side-row:first-of-type {
        padding-top: 0;
    }

    .side-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .side-row span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .side-row strong {
        display: block;
        color: #0f172a;
        margin-top: 4px;
    }

    .signal-image {
        max-height: 520px;
        object-fit: contain;
        width: 100%;
        border-radius: 8px;
        background: #0f172a;
    }

    @media (max-width: 1100px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .setup-grid,
        .target-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $status = (int) $signal->status;
    $isDone = (int) $signal->IsDone === 1 || $status === 14;
    $isBE = (int) $signal->IsBE === 1 || $status === 15;

    if ($isDone) {
        $statusLabel = 'Done';
        $statusClass = 'status-done';
    } elseif ($isBE) {
        $statusLabel = 'BE';
        $statusClass = 'status-be';
    } elseif ($status === 13) {
        $statusLabel = 'SL';
        $statusClass = 'status-sl';
    } elseif ($status === 12) {
        $statusLabel = 'Cancelled';
        $statusClass = 'status-cancel';
    } elseif ($status >= 2 && $status <= 11) {
        $statusLabel = $statusMap[$status] ?? 'TP';
        $statusClass = 'status-tp';
    } else {
        $statusLabel = $statusMap[$status] ?? 'Active';
        $statusClass = 'status-active';
    }
@endphp

<div class="page-content signal-detail-shell">
    <div class="container-fluid">

        <div class="detail-hero mb-3">
            <div>
                <div class="detail-eyebrow">Signal Details</div>
                <h4>{{ $signal->trading_pair }} {{ $signal->immediate_action ? '- ' . $signal->immediate_action : '' }}</h4>
                <p>{{ $signal->signal_code ?? 'No signal code' }} · Created {{ $signal->created_at->format('Y-m-d H:i') }}</p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                <a href="{{ route('member.signals.dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
                <a href="{{ route('member.signals.active') }}" class="btn btn-primary">Active Signals</a>
            </div>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        <div class="detail-grid">
            <div>
                <div class="detail-panel mb-3">
                    <h5 class="panel-title">Trade Setup</h5>
                    <div class="setup-grid">
                        <div class="level-card">
                            <span>Entry Price</span>
                            <strong>{{ $signal->entry_price ?? '-' }}</strong>
                        </div>
                        <div class="level-card">
                            <span>Stop Loss</span>
                            <strong class="text-danger">{{ $signal->stop_loss ?? '-' }}</strong>
                        </div>
                        <div class="level-card">
                            <span>Risk Level</span>
                            <strong>{{ $signal->risk_level ?? 'Unrated' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="detail-panel mb-3">
                    <h5 class="panel-title">Take Profit Ladder</h5>
                    <div class="target-grid">
                        @php($hasTarget = false)
                        @for($i = 1; $i <= 10; $i++)
                            @php($target = 'target_'.$i)
                            @if(!empty($signal->$target))
                                @php($hasTarget = true)
                                <div class="target-item">
                                    <span>TP{{ $i }}</span>
                                    <strong>{{ $signal->$target }}</strong>
                                </div>
                            @endif
                        @endfor

                        @unless($hasTarget)
                            <div class="text-muted">No take profit targets available.</div>
                        @endunless
                    </div>
                </div>

                @if($signal->signal_image && file_exists(public_path($signal->signal_image)))
                    <div class="detail-panel mb-3">
                        <h5 class="panel-title">Signal Chart</h5>
                        <img src="{{ asset($signal->signal_image) }}" alt="Signal chart" class="signal-image">
                    </div>
                @endif

                @if($signal->disclaimer)
                    <div class="detail-panel">
                        <h5 class="panel-title">Disclaimer</h5>
                        <div class="text-muted">{!! nl2br(e($signal->disclaimer)) !!}</div>
                    </div>
                @endif
            </div>

            <aside class="detail-panel">
                <h5 class="panel-title">Signal Summary</h5>
                <div class="side-row">
                    <span>Signal Code</span>
                    <strong>{{ $signal->signal_code ?? '-' }}</strong>
                </div>
                <div class="side-row">
                    <span>Trading Pair</span>
                    <strong>{{ $signal->trading_pair ?? '-' }}</strong>
                </div>
                <div class="side-row">
                    <span>Action</span>
                    <strong>{{ $signal->immediate_action ?? '-' }}</strong>
                </div>
                <div class="side-row">
                    <span>Current Progress</span>
                    <strong>{{ $statusLabel }}</strong>
                </div>
                <div class="side-row">
                    <span>Community</span>
                    <strong>{{ $signal->community?->name ?? '-' }}</strong>
                </div>
                <div class="side-row">
                    <span>Published By</span>
                    <strong>{{ $signal->user?->username ?? $signal->user?->name ?? '-' }}</strong>
                </div>
                <div class="side-row">
                    <span>Created At</span>
                    <strong>{{ $signal->created_at->format('Y-m-d H:i') }}</strong>
                </div>
            </aside>
        </div>
    </div>
</div>

@endsection
