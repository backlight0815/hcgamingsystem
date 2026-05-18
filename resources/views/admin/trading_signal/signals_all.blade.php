@extends('admin.admin_master')
@section('admin')

<title>Trading Signals | HC Gaming Studio</title>

@php
    $statusMap = [
        0 => 'Pending',
        1 => 'Active',
        2 => 'TP1',
        3 => 'TP2',
        4 => 'TP3',
        5 => 'TP4',
        6 => 'TP5',
        7 => 'TP6',
        8 => 'TP7',
        9 => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
        15 => 'BE',
    ];

    $tpRoutes = [
        1 => 'tp1.trading.signal',
        2 => 'tp2.trading.signal',
        3 => 'tp3.trading.signal',
        4 => 'tp4.trading.signal',
        5 => 'tp5.trading.signal',
        6 => 'tp6.trading.signal',
        7 => 'tp7.trading.signal',
        8 => 'tp8.trading.signal',
        9 => 'tp9.trading.signal',
        10 => 'tp10.trading.signal',
    ];

    $statusClass = function ($signal) {
        $status = (int) $signal->status;

        if ((int) $signal->IsDone === 1 || $status === 14) {
            return 'is-done';
        }

        if ((int) $signal->IsBE === 1 || $status === 15) {
            return 'is-be';
        }

        if ($status === 13) {
            return 'is-sl';
        }

        if ($status === 12) {
            return 'is-cancel';
        }

        if ($status >= 2 && $status <= 11) {
            return 'is-tp';
        }

        if ($status === 1) {
            return 'is-active';
        }

        return 'is-pending';
    };

    $statusText = function ($signal) use ($statusMap) {
        $status = (int) $signal->status;

        if ((int) $signal->IsDone === 1 || $status === 14) {
            return 'Done';
        }

        if ((int) $signal->IsBE === 1 || $status === 15) {
            return 'BE';
        }

        return $statusMap[$status] ?? '-';
    };
@endphp

<style>
    .signal-console {
        background: #eef3f8;
        color: #172033;
        min-height: 100vh;
    }

    .signal-shell {
        container-type: inline-size;
        margin: 0 auto;
        max-width: 1780px;
        padding: 26px 30px 42px;
    }

    .signal-hero {
        background: #111827;
        border: 1px solid #22304a;
        border-radius: 12px;
        color: #ffffff;
        display: grid;
        gap: 24px;
        grid-template-columns: minmax(0, 1fr) auto;
        margin-bottom: 18px;
        padding: 28px;
    }

    .signal-kicker {
        color: #2dd4bf;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .signal-hero h3 {
        color: #ffffff;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.16;
        margin: 8px 0;
    }

    .signal-hero p {
        color: #aab7ca;
        font-size: 14px;
        margin: 0;
        max-width: 860px;
    }

    .hero-actions {
        align-items: flex-end;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .signal-panel,
    .signal-card {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        min-width: 0;
    }

    .signal-panel {
        margin-bottom: 18px;
        padding: 18px;
    }

    .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .signal-card {
        min-height: 102px;
        padding: 15px;
        position: relative;
    }

    .signal-card::before {
        background: #0f766e;
        border-radius: 999px;
        content: "";
        height: 4px;
        left: 15px;
        position: absolute;
        right: 15px;
        top: 0;
    }

    .signal-card.pending::before { background: #64748b; }
    .signal-card.active::before { background: #2563eb; }
    .signal-card.tp::before { background: #0f766e; }
    .signal-card.be::before { background: #0891b2; }
    .signal-card.sl::before { background: #e11d48; }
    .signal-card.cancel::before { background: #d97706; }
    .signal-card.done::before { background: #334155; }

    .signal-card span {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .signal-card strong {
        color: #0f172a;
        display: block;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.1;
    }

    .signal-card small {
        color: #64748b;
        display: block;
        font-weight: 700;
        margin-top: 8px;
    }

    .panel-title {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .panel-title h5 {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .panel-title span {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .filter-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(7, minmax(0, 1fr));
    }

    .filter-grid .form-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .status-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        padding: 7px 10px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .status-pill.is-pending { background: #e2e8f0; color: #475569; }
    .status-pill.is-active { background: #dbeafe; color: #1d4ed8; }
    .status-pill.is-tp { background: #dcfce7; color: #15803d; }
    .status-pill.is-be { background: #cffafe; color: #0e7490; }
    .status-pill.is-sl { background: #ffe4e6; color: #be123c; }
    .status-pill.is-cancel { background: #fef3c7; color: #92400e; }
    .status-pill.is-done { background: #e5e7eb; color: #374151; }

    .signal-table {
        margin-bottom: 0;
        width: 100% !important;
    }

    .signal-table thead th {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .signal-table td {
        border-color: #e2e8f0;
        color: #334155;
        font-size: 13px;
        vertical-align: middle;
    }

    .code-cell {
        color: #0f172a;
        display: block;
        font-weight: 900;
    }

    .subtle {
        color: #64748b;
        display: block;
        font-size: 12px;
        margin-top: 2px;
    }

    .level-grid {
        display: grid;
        gap: 6px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        min-width: 230px;
    }

    .level-chip {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 7px;
    }

    .level-chip span {
        color: #64748b;
        display: block;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .level-chip strong {
        color: #0f172a;
        display: block;
        font-size: 12px;
        margin-top: 2px;
        overflow-wrap: anywhere;
    }

    .action-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 250px;
    }

    .action-stack .btn {
        align-items: center;
        display: inline-flex;
        gap: 5px;
        justify-content: center;
    }

    @container (max-width: 1500px) {
        .metric-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @@media (max-width: 900px) {
        .signal-hero {
            grid-template-columns: 1fr;
        }

        .hero-actions {
            justify-content: flex-start;
        }

        .metric-grid,
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .signal-shell {
            padding: 18px 12px 30px;
        }
    }
</style>

<div class="page-content signal-console">
    <div class="container-fluid signal-shell">
        <div class="signal-hero">
            <div>
                <div class="signal-kicker">{{ $canManageAll ? 'Administrator Signal Desk' : 'Signal Provider Desk' }}</div>
                <h3>Trading Signal Operations</h3>
                <p>Publish, monitor, update, close, and audit trading signals from a single operational workspace.</p>
            </div>
            <div class="hero-actions">
                <a href="{{ route('member.signals.dashboard') }}" class="btn btn-outline-light">
                    <i class="ri-dashboard-line"></i> Dashboard
                </a>
                <a href="{{ route('add.trading.signal') }}" class="btn btn-success">
                    <i class="ri-add-circle-line"></i> Add Signal
                </a>
            </div>
        </div>

        <div class="metric-grid">
            <div class="signal-card">
                <span>Total</span>
                <strong>{{ number_format($totalSignals) }}</strong>
                <small>Signals in current scope</small>
            </div>
            <div class="signal-card pending">
                <span>Pending</span>
                <strong>{{ number_format($totalPending) }}</strong>
                <small>Waiting activation</small>
            </div>
            <div class="signal-card active">
                <span>Active</span>
                <strong>{{ number_format($totalActive) }}</strong>
                <small>Being monitored</small>
            </div>
            <div class="signal-card tp">
                <span>TP</span>
                <strong>{{ number_format($totalTP) }}</strong>
                <small>TP1 to TP10 hit</small>
            </div>
            <div class="signal-card be">
                <span>BE</span>
                <strong>{{ number_format($totalBE) }}</strong>
                <small>Break-even protected</small>
            </div>
            <div class="signal-card sl">
                <span>SL</span>
                <strong>{{ number_format($totalSL) }}</strong>
                <small>Stop loss hit</small>
            </div>
            <div class="signal-card cancel">
                <span>Cancelled</span>
                <strong>{{ number_format($totalCancel) }}</strong>
                <small>Invalidated setups</small>
            </div>
            <div class="signal-card done">
                <span>Done</span>
                <strong>{{ number_format($totalDone) }}</strong>
                <small>Closed records</small>
            </div>
        </div>

        <div class="signal-panel">
            <div class="panel-title">
                <h5>Signal Filters</h5>
                <span>{{ $signals->count() }} record(s) loaded</span>
            </div>
            <form method="GET" action="{{ route('all.trading.signals') }}">
                <div class="filter-grid">
                    <div>
                        <label for="quick_range" class="form-label">Quick Range</label>
                        <select id="quick_range" name="quick_range" class="form-control">
                            <option value="">All Time</option>
                            <option value="today" {{ request('quick_range') == 'today' ? 'selected' : '' }}>Today</option>
                            <option value="7days" {{ request('quick_range') == '7days' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="30days" {{ request('quick_range') == '30days' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="90days" {{ request('quick_range') == '90days' ? 'selected' : '' }}>Last 90 Days</option>
                        </select>
                    </div>
                    <div>
                        <label for="from_date" class="form-label">From</label>
                        <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date', $from_date ?? '') }}">
                    </div>
                    <div>
                        <label for="to_date" class="form-label">To</label>
                        <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date', $to_date ?? '') }}">
                    </div>
                    <div>
                        <label for="trading_pair" class="form-label">Pair</label>
                        <input type="text" id="trading_pair" name="trading_pair" class="form-control" value="{{ request('trading_pair') }}" placeholder="XAUUSD">
                    </div>
                    <div>
                        <label for="status_filter" class="form-label">Status</label>
                        <select id="status_filter" name="status_filter" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="0" {{ request('status_filter') === '0' ? 'selected' : '' }}>Pending</option>
                            <option value="1" {{ request('status_filter') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="tp" {{ request('status_filter') === 'tp' ? 'selected' : '' }}>Any TP</option>
                            <option value="be" {{ request('status_filter') === 'be' ? 'selected' : '' }}>Break Even</option>
                            <option value="12" {{ request('status_filter') === '12' ? 'selected' : '' }}>Cancelled</option>
                            <option value="13" {{ request('status_filter') === '13' ? 'selected' : '' }}>SL</option>
                            <option value="done" {{ request('status_filter') === 'done' ? 'selected' : '' }}>Done</option>
                        </select>
                    </div>
                    @if($canManageAll)
                        <div>
                            <label for="provider_id" class="form-label">Provider</label>
                            <select id="provider_id" name="provider_id" class="form-control">
                                <option value="">All Providers</option>
                                @foreach($providers as $provider)
                                    <option value="{{ $provider->id }}" {{ (string) request('provider_id') === (string) $provider->id ? 'selected' : '' }}>
                                        {{ $provider->username ?: $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ri-filter-3-line"></i> Filter
                        </button>
                        <a href="{{ route('all.trading.signals') }}" class="btn btn-light border">
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="signal-panel">
            <div class="panel-title">
                <h5>Signal Ledger</h5>
                <span>Manage lifecycle actions carefully; Discord updates may be triggered by these controls.</span>
            </div>
            <div class="table-responsive">
                <table class="table signal-table align-middle" id="datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Signal</th>
                            @if($canManageAll)
                                <th>Provider</th>
                            @endif
                            <th>Setup</th>
                            <th>Levels</th>
                            <th>Progress</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($signals as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <span class="code-cell">{{ $item->signal_code ?? '-' }}</span>
                                    <span class="subtle">{{ strtoupper($item->trading_pair ?? '-') }}</span>
                                </td>
                                @if($canManageAll)
                                    <td>
                                        <strong>{{ $item->user?->username ?? $item->user?->name ?? '-' }}</strong>
                                        <span class="subtle">{{ $item->user?->role_id == 202 ? 'Senior Provider' : 'Signal Provider' }}</span>
                                    </td>
                                @endif
                                <td>
                                    <strong>{{ strtoupper($item->immediate_action ?? '-') }}</strong>
                                    <span class="subtle">Risk: {{ $item->risk_level ?: 'Unrated' }}</span>
                                </td>
                                <td>
                                    <div class="level-grid">
                                        <div class="level-chip">
                                            <span>Entry</span>
                                            <strong>{{ $item->entry_price ?: '-' }}</strong>
                                        </div>
                                        <div class="level-chip">
                                            <span>SL</span>
                                            <strong>{{ $item->stop_loss ?: '-' }}</strong>
                                        </div>
                                        <div class="level-chip">
                                            <span>TP1</span>
                                            <strong>{{ $item->target_1 ?: '-' }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill {{ $statusClass($item) }}">{{ $statusText($item) }}</span>
                                    @if($item->IsSetBE && !$item->IsBE)
                                        <span class="subtle">BE has been set</span>
                                    @endif
                                </td>
                                <td>{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="action-stack">
                                        <a href="{{ route('view.trading.signal', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                            <i class="ri-eye-line"></i> View
                                        </a>

                                        @if($item->IsDone)
                                            <span class="status-pill is-done">Locked</span>
                                        @elseif($item->IsBE)
                                            @if($item->status == 15 && !$item->IsDone)
                                                <form action="{{ route('close.trading.signal', $item->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        <i class="ri-check-double-line"></i> Done
                                                    </button>
                                                </form>
                                            @endif
                                            <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-delete-url="{{ route('delete.trading.signal', $item->id) }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        @else
                                            <a href="{{ route('edit.trading.signal', $item->id) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="ri-edit-line"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-delete-url="{{ route('delete.trading.signal', $item->id) }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>

                                            @if($item->status == 0)
                                                <button type="button" class="btn btn-warning btn-sm cancel-btn" data-cancel-url="{{ route('cancel.trading.signal', $item->id) }}">
                                                    <i class="ri-close-circle-line"></i> Cancel
                                                </button>
                                                <button type="button" class="btn btn-success btn-sm activate-btn" data-activate-url="{{ route('activate.trading.signal', $item->id) }}">
                                                    <i class="ri-play-circle-line"></i> Activate
                                                </button>
                                            @endif

                                            @if(in_array((int) $item->status, range(1, 11), true))
                                                <form action="{{ route('sl.trading.signal', $item->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="ri-shield-cross-line"></i> SL
                                                    </button>
                                                </form>

                                                @if(!$item->IsSetBE)
                                                    <form action="{{ route('setbe.trading.signal', $item->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm">
                                                            <i class="ri-scales-line"></i> Set BE
                                                        </button>
                                                    </form>
                                                @elseif($item->IsSetBE && !$item->IsBE)
                                                    <form action="{{ route('behitted.trading.signal', $item->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm">
                                                            <i class="ri-scales-2-line"></i> BE Hit
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if(isset($tpRoutes[(int) $item->status]) && !$item->IsBE)
                                                <form action="{{ route($tpRoutes[(int) $item->status], $item->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="ri-focus-3-line"></i> TP {{ $item->status }}
                                                    </button>
                                                </form>
                                            @endif

                                            <form action="{{ route('close.trading.signal', $item->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    <i class="ri-check-double-line"></i> Done
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="cancelForm" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Trading Signal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="cancel_reason_input" class="form-label">Cancellation Reason</label>
                    <textarea class="form-control" id="cancel_reason_input" name="reason" rows="4" required placeholder="Explain why this signal should be cancelled."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Cancel Signal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="activateModal" tabindex="-1" aria-labelledby="activateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="activateForm" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activateModalLabel">Activate Signal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="trigger_time" class="form-label">Trigger Time</label>
                    <input type="datetime-local" class="form-control" id="trigger_time" name="trigger_time">
                    <p class="text-muted small mb-0 mt-3">Leave blank to activate with the current server time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Activate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Trading Signal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">This will remove the signal record from the dashboard. Continue only if this signal should no longer be managed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <a href="#" id="deleteConfirmLink" class="btn btn-danger">Delete Signal</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        var activateModal = new bootstrap.Modal(document.getElementById('activateModal'));
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        var cancelForm = document.getElementById('cancelForm');
        var activateForm = document.getElementById('activateForm');
        var deleteConfirmLink = document.getElementById('deleteConfirmLink');

        document.querySelectorAll('.cancel-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('cancel_reason_input').value = '';
                cancelForm.setAttribute('action', this.dataset.cancelUrl);
                cancelModal.show();
            });
        });

        document.querySelectorAll('.activate-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('trigger_time').value = '';
                activateForm.setAttribute('action', this.dataset.activateUrl);
                activateModal.show();
            });
        });

        document.querySelectorAll('.delete-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                deleteConfirmLink.setAttribute('href', this.dataset.deleteUrl);
                deleteModal.show();
            });
        });
    });
</script>

@endsection
