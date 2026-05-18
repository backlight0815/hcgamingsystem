@extends('admin.admin_master')
@section('admin')

<title>Signal Dashboard | HC Gaming Studio</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .signal-dashboard {
        color: #1f2937;
    }

    .signal-hero,
    .metric-card,
    .panel,
    .signal-card,
    .table-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .signal-hero {
        padding: 22px 24px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
    }

    .hero-eyebrow,
    .section-eyebrow {
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .signal-hero h4 {
        margin: 4px 0 6px;
        color: #0f172a;
        font-weight: 800;
    }

    .signal-hero p {
        margin: 0;
        color: #64748b;
    }

    .hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
    }

    .metric-card {
        padding: 17px;
        min-height: 124px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .metric-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .metric-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        flex: 0 0 auto;
    }

    .metric-icon.teal { background: #0f766e; }
    .metric-icon.blue { background: #2563eb; }
    .metric-icon.green { background: #16a34a; }
    .metric-icon.red { background: #dc2626; }
    .metric-icon.amber { background: #d97706; }
    .metric-icon.gray { background: #475569; }

    .metric-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .metric-value {
        margin-top: 8px;
        color: #0f172a;
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
    }

    .metric-note {
        color: #64748b;
        font-size: 12px;
        margin-top: 10px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(340px, .8fr);
        gap: 16px;
    }

    .panel {
        padding: 18px;
    }

    .panel-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        margin: 0;
    }

    .panel-subtitle {
        color: #64748b;
        margin: 3px 0 0;
        font-size: 13px;
    }

    .chart-wrap {
        position: relative;
        height: 260px;
        width: 100%;
    }

    .spotlight {
        border: 1px solid #dfe5ec;
        background: #f8fafc;
        border-radius: 8px;
        padding: 16px;
    }

    .spotlight-pair {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .pair-title {
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        line-height: 1;
    }

    .signal-levels {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .level-box {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px;
    }

    .level-box span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .level-box strong {
        display: block;
        color: #0f172a;
        margin-top: 4px;
        font-size: 15px;
    }

    .progress-rail {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: #0f766e;
        border-radius: 999px;
    }

    .signal-card {
        padding: 14px;
        height: 100%;
    }

    .signal-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .signal-code {
        color: #64748b;
        font-size: 12px;
    }

    .signal-pair {
        color: #0f172a;
        font-size: 18px;
        font-weight: 800;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
    }

    .status-active { background: #dbeafe; color: #1d4ed8; }
    .status-tp { background: #dcfce7; color: #15803d; }
    .status-sl { background: #fee2e2; color: #b91c1c; }
    .status-cancel { background: #fef3c7; color: #92400e; }
    .status-be { background: #cffafe; color: #0e7490; }
    .status-done { background: #e5e7eb; color: #374151; }

    .signal-mini-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    .mini-stat {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px;
        background: #f8fafc;
    }

    .mini-stat span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .mini-stat strong {
        display: block;
        color: #111827;
        margin-top: 3px;
        font-size: 13px;
    }

    .analytics-list {
        display: grid;
        gap: 10px;
    }

    .analytics-row {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px;
        background: #f8fafc;
    }

    .analytics-row-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }

    .analytics-row strong {
        color: #0f172a;
    }

    .analytics-row span {
        color: #64748b;
        font-size: 12px;
    }

    .table-panel {
        padding: 18px;
    }

    #signalsTable {
        width: 100% !important;
    }

    #signalsTable th {
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    #signalsTable td {
        vertical-align: middle;
    }

    @media (max-width: 1400px) {
        .metric-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1100px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .signal-hero {
            grid-template-columns: 1fr;
        }

        .hero-actions {
            justify-content: flex-start;
        }

        .metric-grid,
        .signal-levels,
        .signal-mini-grid {
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

    $statusText = function ($signal) use ($statusMap) {
        $status = (int) $signal->status;

        if ((int) $signal->IsDone === 1 || $status === 14) {
            return 'Done';
        }

        if ((int) $signal->IsBE === 1 || $status === 15) {
            return 'BE';
        }

        return $statusMap[$status] ?? 'Active';
    };
@endphp

<div class="page-content signal-dashboard">
    <div class="container-fluid">

        <div class="signal-hero mb-3">
            <div>
                <div class="hero-eyebrow">Signal Command Center</div>
                <h4>Trading Signal Dashboard</h4>
                <p>Monitor live signal quality, TP progress, risk exposure, and recent signal flow in one workspace.</p>
            </div>
            <div class="hero-actions">
                <a href="{{ route('member.signals.active') }}" class="btn btn-primary">
                    <i class="fas fa-bolt"></i> Active Signals
                </a>
                <a href="{{ route('member.signals.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-clock"></i> History
                </a>
                @if(in_array((int) auth()->user()->role_id, [1, 2, 201, 202], true))
                    <a href="{{ route('add.trading.signal') }}" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Signal
                    </a>
                @endif
            </div>
        </div>

        <div class="metric-grid mb-3">
            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Total Signals</div>
                        <div class="metric-value">{{ $totalSignals }}</div>
                    </div>
                    <span class="metric-icon blue"><i class="fas fa-signal"></i></span>
                </div>
                <div class="metric-note">{{ $todaySignals }} published today</div>
            </div>

            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Active Now</div>
                        <div class="metric-value">{{ $totalActive }}</div>
                    </div>
                    <span class="metric-icon teal"><i class="fas fa-wave-square"></i></span>
                </div>
                <div class="metric-note">Open opportunities being monitored</div>
            </div>

            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">TP Achieved</div>
                        <div class="metric-value">{{ $totalTP }}</div>
                    </div>
                    <span class="metric-icon green"><i class="fas fa-bullseye"></i></span>
                </div>
                <div class="metric-note">{{ $tpRate }}% of tracked signals</div>
            </div>

            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">SL Hit</div>
                        <div class="metric-value">{{ $totalSL }}</div>
                    </div>
                    <span class="metric-icon red"><i class="fas fa-shield-alt"></i></span>
                </div>
                <div class="metric-note">{{ $slRate }}% risk events</div>
            </div>

            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Break Even</div>
                        <div class="metric-value">{{ $totalBE }}</div>
                    </div>
                    <span class="metric-icon amber"><i class="fas fa-balance-scale"></i></span>
                </div>
                <div class="metric-note">{{ $beRate }}% protected outcomes</div>
            </div>

            <div class="metric-card">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Completed</div>
                        <div class="metric-value">{{ $totalDone }}</div>
                    </div>
                    <span class="metric-icon gray"><i class="fas fa-check-double"></i></span>
                </div>
                <div class="metric-note">{{ $completionRate }}% marked done</div>
            </div>
        </div>

        <div class="dashboard-grid mb-3">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="section-eyebrow">Live Focus</div>
                        <h5 class="panel-title">Latest Signal Snapshot</h5>
                        <p class="panel-subtitle">Most recent signal with core execution levels.</p>
                    </div>
                    <span class="status-pill {{ $latestSignal ? $statusClass($latestSignal) : 'status-done' }}">
                        {{ $latestSignal ? $statusText($latestSignal) : 'No Signal' }}
                    </span>
                </div>

                @if($latestSignal)
                    <div class="spotlight">
                        <div class="spotlight-pair">
                            <div>
                                <div class="signal-code">{{ $latestSignal->signal_code ?? 'No code' }}</div>
                                <div class="pair-title">{{ $latestSignal->trading_pair }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Risk Level</div>
                                <strong>{{ $latestSignal->risk_level ?? 'Unrated' }}</strong>
                            </div>
                        </div>

                        <div class="signal-levels">
                            <div class="level-box">
                                <span>Entry</span>
                                <strong>{{ $latestSignal->entry_price ?? '-' }}</strong>
                            </div>
                            <div class="level-box">
                                <span>Stop Loss</span>
                                <strong>{{ $latestSignal->stop_loss ?? '-' }}</strong>
                            </div>
                            <div class="level-box">
                                <span>Nearest TP</span>
                                <strong>{{ $latestSignal->target_1 ?? '-' }}</strong>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Best TP Level Across Dashboard</span>
                                <strong>TP{{ $bestTpLevel ?: 0 }}</strong>
                            </div>
                            <div class="progress-rail">
                                <div class="progress-fill" style="width: {{ $tpProgressPercent }}%;"></div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a href="{{ route('member.signals.view', $latestSignal->id) }}" class="btn btn-primary">
                                View Signal Details
                            </a>
                        </div>
                    </div>
                @else
                    <div class="spotlight text-center text-muted py-5">No signal data available yet.</div>
                @endif
            </div>

            <div class="panel">
                <div class="mb-3">
                    <div class="section-eyebrow">Outcome Mix</div>
                    <h5 class="panel-title">Signal Status Distribution</h5>
                    <p class="panel-subtitle">Current mix of active, TP, BE, SL, cancelled, and done signals.</p>
                </div>
                <div class="chart-wrap">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-grid mb-3">
            <div class="panel">
                <div class="mb-3">
                    <div class="section-eyebrow">TP Ladder</div>
                    <h5 class="panel-title">Take Profit Achievement Progress</h5>
                    <p class="panel-subtitle">How signals are distributed across TP1 to TP10.</p>
                </div>
                <div class="chart-wrap">
                    <canvas id="tpChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <div class="mb-3">
                    <div class="section-eyebrow">Signal Flow</div>
                    <h5 class="panel-title">7-Day Signal Activity</h5>
                    <p class="panel-subtitle">Daily signal creation trend.</p>
                </div>
                <div class="chart-wrap">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-grid mb-3">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="section-eyebrow">Market Coverage</div>
                        <h5 class="panel-title">Top Trading Pairs</h5>
                    </div>
                    <a href="{{ route('member.signals.history') }}" class="btn btn-sm btn-outline-secondary">Full History</a>
                </div>

                <div class="analytics-list">
                    @forelse($pairStats as $pair)
                        @php
                            $pairPercent = $totalSignals > 0 ? min(100, round(($pair['total'] / $totalSignals) * 100)) : 0;
                        @endphp
                        <div class="analytics-row">
                            <div class="analytics-row-head">
                                <strong>{{ $pair['pair'] }}</strong>
                                <span>{{ $pair['total'] }} signals</span>
                            </div>
                            <div class="progress-rail mb-2">
                                <div class="progress-fill" style="width: {{ $pairPercent }}%;"></div>
                            </div>
                            <div class="d-flex gap-3 flex-wrap">
                                <span>Active: {{ $pair['active'] }}</span>
                                <span>TP: {{ $pair['tp'] }}</span>
                                <span>SL: {{ $pair['sl'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">No pair data available.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="mb-3">
                    <div class="section-eyebrow">Risk Profile</div>
                    <h5 class="panel-title">Risk Level Mix</h5>
                </div>

                <div class="analytics-list">
                    @forelse($riskStats as $risk)
                        @php
                            $riskPercent = $totalSignals > 0 ? min(100, round(($risk['total'] / $totalSignals) * 100)) : 0;
                        @endphp
                        <div class="analytics-row">
                            <div class="analytics-row-head">
                                <strong>{{ $risk['risk'] }}</strong>
                                <span>{{ $risk['total'] }} signals</span>
                            </div>
                            <div class="progress-rail">
                                <div class="progress-fill" style="width: {{ $riskPercent }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">No risk level data available.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="panel mb-3">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="section-eyebrow">Live Monitor</div>
                    <h5 class="panel-title">Recent Signal Cards</h5>
                    <p class="panel-subtitle">Fast scan of the newest signal levels and status.</p>
                </div>
                <a href="{{ route('member.signals.active') }}" class="btn btn-sm btn-outline-primary">Open Active List</a>
            </div>

            <div class="row">
                @forelse($recentSignals->take(6) as $signal)
                    <div class="col-xl-4 col-md-6 mb-3">
                        <div class="signal-card">
                            <div class="signal-card-header">
                                <div>
                                    <div class="signal-code">{{ $signal->signal_code ?? 'No code' }}</div>
                                    <div class="signal-pair">{{ $signal->trading_pair }}</div>
                                </div>
                                <span class="status-pill {{ $statusClass($signal) }}">{{ $statusText($signal) }}</span>
                            </div>

                            <div class="signal-mini-grid">
                                <div class="mini-stat">
                                    <span>Entry</span>
                                    <strong>{{ $signal->entry_price ?? '-' }}</strong>
                                </div>
                                <div class="mini-stat">
                                    <span>SL</span>
                                    <strong>{{ $signal->stop_loss ?? '-' }}</strong>
                                </div>
                                <div class="mini-stat">
                                    <span>TP1</span>
                                    <strong>{{ $signal->target_1 ?? '-' }}</strong>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="text-muted small">{{ $signal->created_at->format('Y-m-d H:i') }}</span>
                                <a href="{{ route('member.signals.view', $signal->id) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-muted">No recent signals available.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="table-panel">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="section-eyebrow">Signal Ledger</div>
                    <h5 class="panel-title">Recent Signals Table</h5>
                    <p class="panel-subtitle">Detailed table view for review and comparison.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="signalsTable">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery !== 'undefined') {
        $('#signalsTable').DataTable({
            responsive: true,
            pageLength: 15,
            order: [[8, 'desc']]
        });
    }

    const statusLabels = @json(array_keys($statusCounts));
    const statusValues = @json(array_values($statusCounts));
    const tpLabels = @json(array_keys($tpCounts));
    const tpValues = @json(array_values($tpCounts));
    const trendLabels = @json($dailySignalTrend->pluck('label')->values());
    const trendValues = @json($dailySignalTrend->pluck('total')->values());

    const chartColors = {
        blue: '#2563eb',
        teal: '#0f766e',
        green: '#16a34a',
        red: '#dc2626',
        amber: '#d97706',
        gray: '#64748b',
        grid: '#e5e7eb'
    };

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: [
                        chartColors.blue,
                        chartColors.green,
                        chartColors.teal,
                        chartColors.red,
                        chartColors.amber,
                        chartColors.gray
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }

    const tpCanvas = document.getElementById('tpChart');
    if (tpCanvas) {
        new Chart(tpCanvas, {
            type: 'bar',
            data: {
                labels: tpLabels,
                datasets: [{
                    label: 'Signals',
                    data: tpValues,
                    backgroundColor: chartColors.teal,
                    borderRadius: 6,
                    maxBarThickness: 34
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: chartColors.grid }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Signals',
                    data: trendValues,
                    borderColor: chartColors.blue,
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: chartColors.blue
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: chartColors.grid }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>

@endsection
