@extends('admin.admin_master')
@section('admin')

<title>Trading Signal Details | HC Gaming Studio</title>

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

    $status = (int) $signal->status;
    $isDone = (int) ($signal->IsDone ?? $signal->is_done ?? 0) === 1 || $status === 14;
    $isBE = (int) ($signal->IsBE ?? $signal->is_be ?? 0) === 1 || $status === 15;
    $isSetBE = (int) ($signal->IsSetBE ?? $signal->is_set_be ?? 0) === 1;
    $statusText = $isDone ? 'Done' : ($isBE ? 'BE' : ($statusMap[$status] ?? '-'));
    $statusClass = $isDone ? 'is-done' : ($isBE ? 'is-be' : match (true) {
        $status === 13 => 'is-sl',
        $status === 12 => 'is-cancel',
        $status >= 2 && $status <= 11 => 'is-tp',
        $status === 1 => 'is-active',
        default => 'is-pending',
    });

    $targets = collect(range(1, 10))->map(function ($index) use ($signal) {
        return [
            'index' => $index,
            'price' => $signal->{'target_'.$index},
        ];
    })->filter(fn ($target) => filled($target['price']))->values();

    $tpHitCount = $status >= 2 && $status <= 11 ? $status - 1 : ($isDone ? $targets->count() : 0);
    $targetCount = max($targets->count(), 1);
    $progressPercent = min(100, round(($tpHitCount / $targetCount) * 100));
    $nextTpIndex = isset($tpRoutes[$status]) && !$isBE && !$isDone ? $status : null;
    $providerName = $signal->user?->username ?? $signal->user?->name ?? 'Unassigned';
    $providerLevel = $signal->user?->role_id == 202 ? 'Senior Signal Provider' : 'Signal Provider';
    $reasonList = method_exists($signal, 'reasons') ? $signal->reasons() : collect();
    $triggerTime = $signal->trigger_time ? \Carbon\Carbon::parse($signal->trigger_time)->format('Y-m-d H:i') : '-';
@endphp

<style>
    .signal-detail {
        background: #eef3f8;
        color: #172033;
        min-height: 100vh;
    }

    .detail-shell {
        margin: 0 auto;
        max-width: 1580px;
        padding: 26px 30px 42px;
    }

    .detail-hero {
        background: #111827;
        border: 1px solid #22304a;
        border-radius: 12px;
        color: #ffffff;
        display: grid;
        gap: 22px;
        grid-template-columns: minmax(0, 1fr) auto;
        margin-bottom: 18px;
        padding: 28px;
    }

    .detail-kicker {
        color: #2dd4bf;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .detail-hero h3 {
        color: #ffffff;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.15;
        margin: 8px 0;
    }

    .detail-hero p {
        color: #aab7ca;
        font-size: 14px;
        margin: 0;
    }

    .hero-actions,
    .action-row {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .status-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        padding: 8px 11px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .status-pill.is-pending { background: #e2e8f0; color: #475569; }
    .status-pill.is-active { background: #dbeafe; color: #1d4ed8; }
    .status-pill.is-tp { background: #ccfbf1; color: #0f766e; }
    .status-pill.is-be { background: #cffafe; color: #0e7490; }
    .status-pill.is-sl { background: #ffe4e6; color: #be123c; }
    .status-pill.is-cancel { background: #fef3c7; color: #b45309; }
    .status-pill.is-done { background: #e2e8f0; color: #0f172a; }

    .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .metric-tile,
    .detail-panel {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        min-width: 0;
    }

    .metric-tile {
        padding: 17px;
    }

    .metric-tile span,
    .field-label {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .05em;
        margin-bottom: 7px;
        text-transform: uppercase;
    }

    .metric-tile strong {
        color: #0f172a;
        display: block;
        font-size: 22px;
        font-weight: 900;
        line-height: 1.15;
        word-break: break-word;
    }

    .detail-layout {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.35fr) minmax(330px, .65fr);
    }

    .detail-panel {
        margin-bottom: 18px;
        padding: 20px;
    }

    .panel-title {
        align-items: center;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .panel-title h5 {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .setup-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .setup-box {
        background: #f8fafc;
        border: 1px solid #dbe4ef;
        border-radius: 10px;
        padding: 14px;
    }

    .setup-box strong {
        color: #111827;
        display: block;
        font-size: 17px;
        font-weight: 900;
        word-break: break-word;
    }

    .target-list {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .target-item {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #dbe4ef;
        border-radius: 10px;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding: 12px 14px;
    }

    .target-item.is-hit {
        background: #ecfdf5;
        border-color: #99f6e4;
    }

    .target-item span {
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
    }

    .target-item strong {
        color: #0f172a;
        font-size: 15px;
        font-weight: 900;
        word-break: break-word;
    }

    .progress-track {
        background: #e2e8f0;
        border-radius: 999px;
        height: 10px;
        overflow: hidden;
        width: 100%;
    }

    .progress-track span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .info-list {
        display: grid;
        gap: 12px;
    }

    .info-row {
        display: grid;
        gap: 6px;
    }

    .info-row strong {
        color: #0f172a;
        font-weight: 800;
        word-break: break-word;
    }

    .note-box {
        background: #f8fafc;
        border: 1px solid #dbe4ef;
        border-radius: 10px;
        color: #334155;
        line-height: 1.6;
        padding: 14px;
        white-space: pre-line;
    }

    .reason-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .reason-chip {
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        color: #3730a3;
        font-size: 12px;
        font-weight: 800;
        padding: 7px 10px;
    }

    .chart-preview {
        background: #f8fafc;
        border: 1px solid #dbe4ef;
        border-radius: 10px;
        overflow: hidden;
    }

    .chart-preview img {
        display: block;
        max-height: 520px;
        object-fit: contain;
        width: 100%;
    }

    @@media (max-width: 1180px) {
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .detail-layout {
            grid-template-columns: 1fr;
        }
    }

    @@media (max-width: 768px) {
        .detail-shell {
            padding: 18px 14px 30px;
        }

        .detail-hero {
            grid-template-columns: 1fr;
            padding: 22px;
        }

        .hero-actions,
        .action-row {
            justify-content: flex-start;
        }

        .metric-grid,
        .setup-grid,
        .target-list {
            grid-template-columns: 1fr;
        }

        .detail-hero h3 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content signal-detail">
    <div class="detail-shell">
        <div class="detail-hero">
            <div>
                <div class="detail-kicker">Signal Ticket</div>
                <h3>{{ $signal->trading_pair }} {{ strtoupper($signal->immediate_action ?? '') }}</h3>
                <p>{{ $signal->signal_code }} managed by {{ $providerName }} for {{ $signal->community?->name ?? 'selected communities' }}.</p>
            </div>
            <div class="hero-actions">
                <span class="status-pill {{ $statusClass }}">{{ $statusText }}</span>
                <a href="{{ route('all.trading.signals') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line"></i> Back
                </a>
                @if(!$isDone && !$isBE)
                    <a href="{{ route('edit.trading.signal', $signal->id) }}" class="btn btn-primary btn-sm">
                        <i class="ri-edit-line"></i> Edit
                    </a>
                @endif
                <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-delete-url="{{ route('delete.trading.signal', $signal->id) }}">
                    <i class="ri-delete-bin-line"></i> Delete
                </button>
            </div>
        </div>

        <div class="metric-grid">
            <div class="metric-tile">
                <span>Entry Price</span>
                <strong>{{ $signal->entry_price ?: '-' }}</strong>
            </div>
            <div class="metric-tile">
                <span>Stop Loss</span>
                <strong>{{ $signal->stop_loss ?: '-' }}</strong>
            </div>
            <div class="metric-tile">
                <span>Risk Level</span>
                <strong>{{ $signal->risk_level ?: 'Unrated' }}</strong>
            </div>
            <div class="metric-tile">
                <span>TP Progress</span>
                <strong>{{ $tpHitCount }} / {{ $targets->count() }}</strong>
            </div>
        </div>

        <div class="detail-layout">
            <main>
                <div class="detail-panel">
                    <div class="panel-title">
                        <h5>Execution Setup</h5>
                        <span class="status-pill {{ $statusClass }}">{{ $statusText }}</span>
                    </div>
                    <div class="setup-grid">
                        <div class="setup-box">
                            <span class="field-label">Action</span>
                            <strong>{{ strtoupper($signal->immediate_action ?? '-') }}</strong>
                        </div>
                        <div class="setup-box">
                            <span class="field-label">Entry</span>
                            <strong>{{ $signal->entry_price ?: '-' }}</strong>
                        </div>
                        <div class="setup-box">
                            <span class="field-label">Stop Loss</span>
                            <strong>{{ $signal->stop_loss ?: '-' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="detail-panel">
                    <div class="panel-title">
                        <h5>Take Profit Ladder</h5>
                        <span>{{ $progressPercent }}% completed</span>
                    </div>
                    <div class="progress-track mb-3">
                        <span style="width: {{ $progressPercent }}%"></span>
                    </div>
                    <div class="target-list">
                        @forelse($targets as $target)
                            <div class="target-item {{ $target['index'] <= $tpHitCount ? 'is-hit' : '' }}">
                                <span>TP {{ $target['index'] }}</span>
                                <strong>{{ $target['price'] }}</strong>
                            </div>
                        @empty
                            <div class="note-box">No take profit targets have been configured.</div>
                        @endforelse
                    </div>
                </div>

                @if($signal->signal_image && file_exists(public_path($signal->signal_image)))
                    <div class="detail-panel">
                        <div class="panel-title">
                            <h5>Chart Attachment</h5>
                        </div>
                        <div class="chart-preview">
                            <img src="{{ asset($signal->signal_image) }}" alt="Trading signal chart">
                        </div>
                    </div>
                @endif

                <div class="detail-panel">
                    <div class="panel-title">
                        <h5>Rationale</h5>
                    </div>
                    @if($reasonList->count())
                        <div class="reason-stack mb-3">
                            @foreach($reasonList as $reason)
                                <span class="reason-chip">{{ $reason->name ?? $reason->reason ?? 'Reason' }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="note-box">{{ $signal->disclaimer ?: 'No additional disclaimer or trade note was supplied.' }}</div>
                </div>
            </main>

            <aside>
                <div class="detail-panel">
                    <div class="panel-title">
                        <h5>Lifecycle Actions</h5>
                    </div>
                    <div class="action-row justify-content-start">
                        @if($isDone)
                            <span class="status-pill is-done">Locked</span>
                        @elseif($isBE)
                            <span class="status-pill is-be">Break-even</span>
                            <form action="{{ route('close.trading.signal', $signal->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="ri-check-double-line"></i> Done
                                </button>
                            </form>
                        @else
                            @if($status === 0)
                                <button type="button" class="btn btn-success btn-sm activate-btn" data-activate-url="{{ route('activate.trading.signal', $signal->id) }}">
                                    <i class="ri-play-circle-line"></i> Activate
                                </button>
                                <button type="button" class="btn btn-warning btn-sm cancel-btn" data-cancel-url="{{ route('cancel.trading.signal', $signal->id) }}">
                                    <i class="ri-close-circle-line"></i> Cancel
                                </button>
                            @endif

                            @if(in_array($status, range(1, 11), true))
                                @if($nextTpIndex)
                                    <form action="{{ route($tpRoutes[$nextTpIndex], $signal->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="ri-focus-3-line"></i> Mark TP {{ $nextTpIndex }}
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('sl.trading.signal', $signal->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="ri-shield-cross-line"></i> SL
                                    </button>
                                </form>

                                @if(!$isSetBE)
                                    <form action="{{ route('setbe.trading.signal', $signal->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="ri-scales-line"></i> Set BE
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('behitted.trading.signal', $signal->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="ri-scales-2-line"></i> BE Hit
                                        </button>
                                    </form>
                                @endif
                            @endif

                            <form action="{{ route('close.trading.signal', $signal->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="ri-check-double-line"></i> Done
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="detail-panel">
                    <div class="panel-title">
                        <h5>Signal Metadata</h5>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="field-label">Provider</span>
                            <strong>{{ $providerName }}</strong>
                            <small class="text-muted">{{ $providerLevel }}</small>
                        </div>
                        <div class="info-row">
                            <span class="field-label">Community</span>
                            <strong>{{ $signal->community?->name ?? $signal->community_target ?? '-' }}</strong>
                        </div>
                        <div class="info-row">
                            <span class="field-label">Category</span>
                            <strong>{{ $signal->community_category ?? $signal->category ?? '-' }}</strong>
                        </div>
                        <div class="info-row">
                            <span class="field-label">Created</span>
                            <strong>{{ optional($signal->created_at)->format('Y-m-d H:i') }}</strong>
                        </div>
                        <div class="info-row">
                            <span class="field-label">Trigger Time</span>
                            <strong>{{ $triggerTime }}</strong>
                        </div>
                        <div class="info-row">
                            <span class="field-label">Discord Messages</span>
                            <strong>{{ $signal->discordMessages->count() }}</strong>
                        </div>
                    </div>
                </div>

                @if($signal->link)
                    <div class="detail-panel">
                        <div class="panel-title">
                            <h5>Reference Link</h5>
                        </div>
                        <a href="{{ $signal->link }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100">
                            <i class="ri-external-link-line"></i> Open Reference
                        </a>
                    </div>
                @endif
            </aside>
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
                    <textarea class="form-control" id="cancel_reason_input" name="reason" rows="4" required placeholder="Explain why this signal should be cancelled.">{{ $signal->cancel_reason }}</textarea>
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
                <p class="mb-0">This removes the signal from the management desk. Continue only when the ticket should no longer be tracked.</p>
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
        var cancelElement = document.getElementById('cancelModal');
        var activateElement = document.getElementById('activateModal');
        var deleteElement = document.getElementById('deleteModal');
        var cancelForm = document.getElementById('cancelForm');
        var activateForm = document.getElementById('activateForm');
        var deleteConfirmLink = document.getElementById('deleteConfirmLink');

        if (cancelElement && activateElement && deleteElement && window.bootstrap) {
            var cancelModal = new bootstrap.Modal(cancelElement);
            var activateModal = new bootstrap.Modal(activateElement);
            var deleteModal = new bootstrap.Modal(deleteElement);

            document.querySelectorAll('.cancel-btn').forEach(function (button) {
                button.addEventListener('click', function () {
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
        }
    });
</script>

@endsection
