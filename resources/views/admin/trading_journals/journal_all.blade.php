@extends('admin.admin_master')
@section('admin')

@php
    $selectedMonth = request('month', 'all');
    $selectedYear = request('year', 'all');
    $journalTime = app(\App\Services\TradingJournalTimeService::class);
    $selectedTimeView = $journalTime->normalizeMode($selectedTimeView ?? request('time_view'));
    $selectedTimeViewOffset = $journalTime->normalizeOffset($selectedTimeViewOffset ?? request('mt5_offset_minutes'), $selectedTimeView);
    $selectedMt5ViewOffset = $journalTime->normalizeOffset(request('mt5_offset_minutes'), \App\Services\TradingJournalTimeService::TIMEZONE_MT5);
    $timeModes = $journalTime->modes();
    $mt5OffsetOptions = $journalTime->mt5OffsetOptions();
    $journalReportQuery = [
        'month' => $selectedMonth,
        'year' => $selectedYear,
        'source' => 'current',
        'time_view' => $selectedTimeView,
        'mt5_offset_minutes' => $selectedMt5ViewOffset,
    ];
    $revengeTradeMap = collect(data_get($traderStyleProfile, 'revenge.trade_map', []));
    $gamblingTradeMap = collect(data_get($traderStyleProfile, 'gambling.trade_map', []));
    $behaviorScorePenalty = $behaviorScorePenalty ?? data_get($scoreEvaluationProfile, 'behavior_score_penalty', []);
    $behaviorWeeklyProfile = $behaviorWeeklyProfile ?? [];
    $behaviorWeeklyMetrics = collect(data_get($behaviorWeeklyProfile, 'metrics', []));
    $behaviorToneBadgeClass = function ($tone) {
        return match ($tone) {
            'danger' => 'bg-danger',
            'warning' => 'bg-warning text-dark',
            'success' => 'bg-success',
            'primary' => 'bg-primary',
            default => 'bg-secondary',
        };
    };
    $netProfitLoss = (float) $totalProfit - (float) $totalLoss;
    $summaryCards = [
        ['label' => 'Balance', 'value' => number_format((float) $currentBalance, 2) . 'u', 'icon' => 'mdi-wallet-outline', 'tone' => 'primary'],
        ['label' => 'Win Rate', 'value' => number_format((float) $winRate, 2) . '%', 'icon' => 'mdi-target', 'tone' => 'info'],
        ['label' => 'Total Profit', 'value' => number_format((float) $totalProfit, 2) . 'u', 'icon' => 'mdi-trending-up', 'tone' => 'success'],
        ['label' => 'Total Loss', 'value' => number_format((float) $totalLoss, 2) . 'u', 'icon' => 'mdi-trending-down', 'tone' => 'danger'],
        ['label' => 'Net P/L', 'value' => number_format($netProfitLoss, 2) . 'u', 'icon' => 'mdi-scale-balance', 'tone' => $netProfitLoss >= 0 ? 'success' : 'danger'],
        ['label' => 'Risk Reward', 'value' => is_numeric($averageRRR) ? $averageRRR : 'N/A', 'icon' => 'mdi-chart-timeline-variant', 'tone' => 'secondary'],
        ['label' => 'Growth', 'value' => number_format((float) $growthPercent, 2) . '%', 'icon' => 'mdi-finance', 'tone' => 'success'],
        ['label' => 'Drawdown', 'value' => number_format((float) $drawdownPercent, 2) . '%', 'icon' => 'mdi-alert-outline', 'tone' => 'danger'],
        ['label' => 'Best Day Rule', 'value' => number_format((float) $consistencyPercent, 2) . '%', 'icon' => 'mdi-ruler-square-compass', 'tone' => ($bestDayRule['passed'] ?? false) ? 'success' : 'warning'],
        ['label' => '2% Gross Profit', 'value' => number_format((float) data_get($grossProfitRule, 'achieved_percent', 0), 2) . '%', 'icon' => 'mdi-cash-plus', 'tone' => data_get($grossProfitRule, 'passed', false) ? 'success' : 'warning'],
        ['label' => 'Trader Style', 'value' => data_get($traderStyleProfile, 'style_label', 'N/A'), 'icon' => 'mdi-brain', 'tone' => data_get($traderStyleProfile, 'tone', 'secondary')],
        ['label' => 'Revenge Tier', 'value' => data_get($traderStyleProfile, 'revenge.status', 'N/A'), 'icon' => 'mdi-emoticon-angry-outline', 'tone' => data_get($traderStyleProfile, 'revenge.tier_tone', data_get($traderStyleProfile, 'revenge.tone', 'secondary'))],
        ['label' => 'Gambling Tier', 'value' => data_get($traderStyleProfile, 'gambling.status', 'N/A'), 'icon' => 'mdi-dice-multiple-outline', 'tone' => data_get($traderStyleProfile, 'gambling.tier_tone', data_get($traderStyleProfile, 'gambling.tone', 'secondary'))],
        ['label' => 'Behaviour Penalty', 'value' => '-' . number_format((float) data_get($behaviorScorePenalty, 'points', 0), 2) . ' pts', 'icon' => 'mdi-shield-alert-outline', 'tone' => data_get($behaviorScorePenalty, 'tone', 'success')],
        ['label' => 'Layering', 'value' => data_get($traderStyleProfile, 'layering.status', 'N/A'), 'icon' => 'mdi-layers-triple-outline', 'tone' => data_get($traderStyleProfile, 'layering.tone', 'secondary')],
        ['label' => 'Hedging', 'value' => $hedgingProfile['status'] ?? 'N/A', 'icon' => 'mdi-swap-horizontal-bold', 'tone' => data_get($hedgingProfile, 'detected', false) ? 'danger' : 'info'],
        ['label' => 'Position Consistency', 'value' => number_format((float) data_get($positionProfile, 'score', 0), 2) . '/100', 'icon' => 'mdi-chart-bell-curve-cumulative', 'tone' => data_get($positionProfile, 'is_dynamic', false) ? 'warning' : 'info'],
        ['label' => 'Avg Duration', 'value' => $durationProfile['average_label'] ?? '0m', 'icon' => 'mdi-timer-outline', 'tone' => 'secondary'],
        ['label' => 'Expectancy', 'value' => number_format((float) $expectancy, 2), 'icon' => 'mdi-calculator-variant-outline', 'tone' => 'info'],
        ['label' => 'Grade', 'value' => $rating, 'icon' => 'mdi-medal-outline', 'tone' => 'dark'],
    ];
@endphp

<title>Trading Journal Report | HC Gaming Studio</title>

<style>
    .journal-dashboard {
        color: #1f2937;
    }

    .journal-dashboard .page-title-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .journal-dashboard .page-title-box h4 {
        margin: 0;
        color: #111827;
        font-weight: 700;
    }

    .journal-actions-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .journal-stat {
        display: flex;
        align-items: center;
        gap: 14px;
        height: 100%;
        padding: 18px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .journal-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #f3f6fb;
        color: #3155d4;
        font-size: 22px;
        flex: 0 0 auto;
    }

    .journal-stat-value {
        margin: 0;
        color: #111827;
        font-size: 19px;
        font-weight: 800;
    }

    .journal-stat-label {
        margin: 2px 0 0;
        color: #6b7280;
        font-size: 13px;
        font-weight: 600;
    }

    .journal-stat.tone-success .journal-stat-icon,
    .journal-stat.tone-success .journal-stat-value {
        color: #15803d;
    }

    .journal-stat.tone-danger .journal-stat-icon,
    .journal-stat.tone-danger .journal-stat-value {
        color: #b91c1c;
    }

    .journal-stat.tone-warning .journal-stat-icon,
    .journal-stat.tone-warning .journal-stat-value {
        color: #b45309;
    }

    .journal-stat.tone-info .journal-stat-icon,
    .journal-stat.tone-info .journal-stat-value {
        color: #0369a1;
    }

    .journal-stat.tone-dark .journal-stat-icon,
    .journal-stat.tone-dark .journal-stat-value {
        color: #111827;
    }

    .journal-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
    }

    .journal-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid #edf0f4;
    }

    .journal-panel-header h5 {
        margin: 0;
        color: #111827;
        font-weight: 700;
    }

    .journal-panel-body {
        padding: 22px;
    }

    .journal-filter {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr)) auto;
        gap: 14px;
        align-items: end;
    }

    .journal-filter label {
        color: #4b5563;
        font-weight: 700;
    }

    .journal-filter .form-control {
        border-color: #d8dee9;
        border-radius: 7px;
        min-height: 42px;
    }

    .journal-table {
        margin-bottom: 0;
    }

    .journal-table thead th {
        border-bottom: 1px solid #e5e7eb;
        color: #4b5563;
        font-size: 12px;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .journal-table td {
        vertical-align: middle;
        white-space: nowrap;
    }

    .journal-table .pair-cell {
        color: #111827;
        font-weight: 800;
    }

    .journal-table .note-cell {
        max-width: 220px;
        white-space: normal;
        color: #6b7280;
    }

    .journal-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 7px;
    }

    .trade-signal-icons {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .trade-signal-icon {
        align-items: center;
        border-radius: 7px;
        display: inline-flex;
        font-size: 16px;
        height: 26px;
        justify-content: center;
        position: relative;
        width: 26px;
    }

    .trade-signal-icon.hedge { background: #fef3c7; color: #92400e; }
    .trade-signal-icon.revenge { background: #fee2e2; color: #991b1b; }
    .trade-signal-icon.gambling { background: #ffedd5; color: #9a3412; }
    .trade-signal-icon.layering { background: #e0e7ff; color: #3730a3; }
    .trade-signal-icon.clear { background: #f1f5f9; color: #64748b; }

    .trade-signal-icon[data-tier]::after {
        align-items: center;
        background: #ffffff;
        border: 1px solid currentColor;
        border-radius: 999px;
        content: attr(data-tier);
        display: inline-flex;
        font-size: 8px;
        font-weight: 900;
        height: 13px;
        justify-content: center;
        line-height: 1;
        position: absolute;
        right: -5px;
        top: -6px;
        width: 13px;
    }

    .trade-signal-icon.tier-low { box-shadow: inset 0 0 0 1px rgba(37, 99, 235, .35); }
    .trade-signal-icon.tier-medium { box-shadow: inset 0 0 0 1px rgba(217, 119, 6, .45); }
    .trade-signal-icon.tier-high { box-shadow: inset 0 0 0 1px rgba(185, 28, 28, .55); }

    .journal-empty {
        padding: 42px 20px;
        text-align: center;
        color: #6b7280;
    }

    .journal-modal-list {
        display: grid;
        gap: 10px;
    }

    .journal-modal-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border: 1px solid #edf0f4;
        border-radius: 8px;
        background: #fbfcfe;
    }

    .evaluation-header-panel {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px;
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #f8fbff;
    }

    .evaluation-header-panel strong {
        display: block;
        color: #111827;
        font-size: 24px;
        line-height: 1.2;
    }

    .evaluation-kpi {
        height: 100%;
        padding: 14px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
    }

    .evaluation-kpi span,
    .evaluation-rule-row span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .evaluation-kpi strong {
        display: block;
        margin-top: 6px;
        color: #111827;
        font-size: 18px;
    }

    .evaluation-rule-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 13px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .evaluation-rule-row:last-child {
        border-bottom: 0;
    }

    .evaluation-rule-row strong {
        display: block;
        margin-top: 3px;
    }

    .evaluation-table th {
        color: #475569;
        font-size: 12px;
        letter-spacing: .03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .evaluation-table td {
        vertical-align: middle;
    }

    .behavior-week-card {
        height: 100%;
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-left: 4px solid #64748b;
        border-radius: 8px;
        background: #fff;
    }

    .behavior-week-card.tone-success { border-left-color: #15803d; }
    .behavior-week-card.tone-danger { border-left-color: #b91c1c; }
    .behavior-week-card.tone-warning { border-left-color: #b45309; }
    .behavior-week-card.tone-primary { border-left-color: #3155d4; }

    .behavior-week-score {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin: 14px 0;
    }

    .behavior-week-score div {
        padding: 10px;
        border-radius: 8px;
        background: #f8fafc;
    }

    .behavior-week-score span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .behavior-week-score strong {
        color: #111827;
        font-size: 18px;
    }

    @media (max-width: 991.98px) {
        .journal-dashboard .page-title-box,
        .journal-panel-header {
            align-items: stretch;
            flex-direction: column;
        }

        .journal-actions-bar {
            justify-content: flex-start;
        }

        .journal-filter {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content journal-dashboard">
    <div class="container-fluid">
        <div class="page-title-box">
            <div>
                <h4>Trading Journal</h4>
                <ol class="breadcrumb m-0 mt-2">
                    @foreach ($breadcrumbData as $breadcrumb)
                        <li class="breadcrumb-item">
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="journal-actions-bar">
                @if($propFirmLockMessage)
                    <button type="button" class="btn btn-success" disabled>
                        <i class="mdi mdi-lock-outline me-1"></i> Record Locked
                    </button>
                @else
                    <a href="{{ route('add.trading.journal') }}" class="btn btn-success">
                        <i class="mdi mdi-plus me-1"></i> Record Trade
                    </a>
                @endif
                <a href="{{ route('capital.create', ['type' => 1]) }}" class="btn btn-info">
                    <i class="mdi mdi-plus-circle-outline me-1"></i> Deposit
                </a>
                <a href="{{ route('capital.create', ['type' => 2]) }}" class="btn btn-warning">
                    <i class="mdi mdi-minus-circle-outline me-1"></i> Withdraw
                </a>
                <a href="{{ route('trading-journal.export') }}" class="btn btn-primary">
                    <i class="mdi mdi-file-excel-outline me-1"></i> Export
                </a>
                <a href="{{ route('trading-journal.report.pdf', $journalReportQuery) }}" class="btn btn-danger">
                    <i class="mdi mdi-file-pdf-box me-1"></i> PDF Report
                </a>
                @if($propFirmLockMessage)
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="mdi mdi-lock-outline me-1"></i> Import Locked
                    </button>
                @else
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importTradesModal">
                        <i class="mdi mdi-upload-outline me-1"></i> Import
                    </button>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($propFirmLockMessage)
            <div class="alert alert-warning">
                <strong>Prop firm review in progress.</strong> {{ $propFirmLockMessage }}
            </div>
        @endif

        <div class="row g-3 mb-4">
            @foreach($summaryCards as $card)
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="journal-stat tone-{{ $card['tone'] }}">
                        <span class="journal-stat-icon">
                            <i class="mdi {{ $card['icon'] }}"></i>
                        </span>
                        <div>
                            <h5 class="journal-stat-value">{{ $card['value'] }}</h5>
                            <p class="journal-stat-label">{{ $card['label'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="journal-panel mb-4">
            <div class="journal-panel-header">
                <h5>Performance Review</h5>
                <div class="journal-actions-bar">
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ratingBreakdownModal">
                        <i class="mdi mdi-chart-box-outline me-1"></i> Rating Breakdown
                    </button>
                    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#scoreCalculationModal">
                        <i class="mdi mdi-calculator-variant-outline me-1"></i> Score Calculation
                    </button>
                    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#positionConsistencyModal">
                        <i class="mdi mdi-chart-bell-curve-cumulative me-1"></i> Position Score
                    </button>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#behaviorDetectionModal">
                        <i class="mdi mdi-brain me-1"></i> Behavior Detection
                    </button>
                    @if($featureEnabled)
                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#propFirmModal">
                            <i class="mdi mdi-clipboard-check-outline me-1"></i> Prop Firm Evaluation
                        </button>
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="mdi mdi-lock-outline me-1"></i> Prop Firm Disabled
                        </button>
                    @endif
                </div>
            </div>
            <div class="journal-panel-body">
                <form method="GET" action="{{ route('all.trading.journals') }}" class="journal-filter">
                    <div>
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-control">
                            <option value="all" {{ $selectedMonth == 'all' ? 'selected' : '' }}>All Months</option>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ (string) $selectedMonth === (string) $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="year" class="form-label">Year</label>
                        <select name="year" id="year" class="form-control">
                            <option value="all" {{ $selectedYear == 'all' ? 'selected' : '' }}>All Years</option>
                            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}" {{ (string) $selectedYear === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="time_view" class="form-label">Time Display</label>
                        <select name="time_view" id="time_view" class="form-control">
                            @foreach($timeModes as $value => $mode)
                                <option value="{{ $value }}" {{ $selectedTimeView === $value ? 'selected' : '' }}>
                                    {{ $mode['label'] }} ({{ $mode['description'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mt5_offset_minutes" class="form-label">MT5 Offset</label>
                        <select name="mt5_offset_minutes" id="mt5_offset_minutes" class="form-control">
                            @foreach($mt5OffsetOptions as $offset => $label)
                                <option value="{{ $offset }}" {{ (int) $selectedMt5ViewOffset === (int) $offset ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-filter-outline me-1"></i> Filter
                        </button>
                        <a href="{{ route('all.trading.journals') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="journal-panel mb-4">
            <div class="journal-panel-header">
                <h5>Weekly Behaviour Improvement</h5>
                <span class="badge bg-light text-dark">
                    {{ data_get($behaviorWeeklyProfile, 'current_period_label', 'Current week') }} vs {{ data_get($behaviorWeeklyProfile, 'previous_period_label', 'Previous week') }}
                </span>
            </div>
            <div class="journal-panel-body">
                <div class="row g-3">
                    @foreach(['overall', 'revenge', 'gambling'] as $behaviorMetricKey)
                        @php
                            $behaviorMetric = $behaviorWeeklyMetrics->get($behaviorMetricKey, []);
                        @endphp
                        <div class="col-lg-4">
                            <div class="behavior-week-card tone-{{ data_get($behaviorMetric, 'trend_tone', 'secondary') }}">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <h6 class="mb-1">{{ data_get($behaviorMetric, 'label', 'Behaviour') }}</h6>
                                        <div class="small text-muted">{{ data_get($behaviorMetric, 'current_tier', 'N/A') }} now / {{ data_get($behaviorMetric, 'previous_tier', 'N/A') }} previous</div>
                                    </div>
                                    <span class="badge {{ $behaviorToneBadgeClass(data_get($behaviorMetric, 'trend_tone', 'secondary')) }}">
                                        {{ data_get($behaviorMetric, 'trend_label', 'N/A') }}
                                    </span>
                                </div>
                                <div class="behavior-week-score">
                                    <div>
                                        <span>Current 7 Days</span>
                                        <strong>{{ number_format((float) data_get($behaviorMetric, 'current_score', 0), 2) }}</strong>
                                        <div class="small text-muted">{{ data_get($behaviorMetric, 'current_status', 'N/A') }}</div>
                                    </div>
                                    <div>
                                        <span>Previous 7 Days</span>
                                        <strong>{{ number_format((float) data_get($behaviorMetric, 'previous_score', 0), 2) }}</strong>
                                        <div class="small text-muted">{{ data_get($behaviorMetric, 'previous_status', 'N/A') }}</div>
                                    </div>
                                </div>
                                <div class="small">
                                    <strong>{{ data_get($behaviorMetric, 'direction', 'No change') }}:</strong>
                                    {{ data_get($behaviorMetric, 'change_label', '0.00 pts') }}.
                                    {{ data_get($behaviorMetric, 'summary', '') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="journal-panel">
            <div class="journal-panel-header">
                <h5>Trade History</h5>
                <span class="badge bg-light text-dark">{{ $totalTrades }} trades | signal icons</span>
            </div>
            <div class="journal-panel-body">
                <div class="table-responsive">
                    <table id="datatable" class="table table-hover align-middle journal-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Open ({{ $journalTime->shortLabel($selectedTimeView, $selectedTimeViewOffset) }})</th>
                                <th>Close ({{ $journalTime->shortLabel($selectedTimeView, $selectedTimeViewOffset) }})</th>
                                <th>Saved From</th>
                                <th>Pair</th>
                                <th>Direction</th>
                                <th>Entry</th>
                                <th>Exit</th>
                                <th>Pips</th>
                                <th>Lot</th>
                                <th>Profit / Loss</th>
                                <th>Result</th>
                                <th>Signals</th>
                                <th>Notes</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($journals as $journal)
                                @php
                                    $journalProfit = (float) $journal->profit_loss;
                                    $resultLabel = match ((int) $journal->result) {
                                        1 => 'Win',
                                        2 => 'Loss',
                                        3 => 'Break Even',
                                        default => 'N/A',
                                    };
                                    $resultClass = match ((int) $journal->result) {
                                        1 => 'bg-success',
                                        2 => 'bg-danger',
                                        3 => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                    $tradeKey = 'journal:' . $journal->id;
                                    $hedgeInsight = ($hedgingProfile['trade_map'] ?? collect())->get($tradeKey);
                                    $layeringInsight = data_get($traderStyleProfile, 'layering.trade_map', collect())->get($tradeKey);
                                    $revengeInsight = $revengeTradeMap->get($tradeKey);
                                    $gamblingInsight = $gamblingTradeMap->get($tradeKey);
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $journalTime->formatForDisplay($journal->open_date, $selectedTimeView, $selectedTimeViewOffset) }}</td>
                                    <td>{{ $journalTime->formatForDisplay($journal->close_date, $selectedTimeView, $selectedTimeViewOffset) }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $journalTime->shortLabel($journal->time_input_timezone ?? null, $journal->time_input_offset_minutes ?? null) }}</span></td>
                                    <td class="pair-cell">{{ strtoupper($journal->pair) }}</td>
                                    <td>
                                        @if($journal->direction == 1)
                                            <span class="badge bg-success">Buy</span>
                                        @elseif($journal->direction == 2)
                                            <span class="badge bg-danger">Sell</span>
                                        @else
                                            <span class="badge bg-secondary">Unknown</span>
                                        @endif
                                    </td>
                                    <td>{{ $journal->entry_price }}</td>
                                    <td>{{ $journal->exit_price }}</td>
                                    <td>{{ $journal->pips }}</td>
                                    <td>{{ $journal->lot_size }}</td>
                                    <td class="{{ $journalProfit >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                        {{ number_format($journalProfit, 2) }}u
                                    </td>
                                    <td><span class="badge {{ $resultClass }}">{{ $resultLabel }}</span></td>
                                    <td>
                                        <div class="trade-signal-icons">
                                            @if($hedgeInsight)
                                                <span class="trade-signal-icon hedge" title="Hedging signal" aria-label="Hedging signal"><i class="mdi mdi-swap-horizontal-bold"></i></span>
                                            @endif
                                            @if($revengeInsight)
                                                <span class="trade-signal-icon revenge tier-{{ data_get($revengeInsight, 'tier', 'low') }}" data-tier="{{ data_get($revengeInsight, 'tier_short', 'L') }}" title="Normal setup flagged: {{ data_get($revengeInsight, 'status', 'Revenge trading tier') }} - {{ data_get($revengeInsight, 'reason_labels', 'Review post-loss reaction') }}" aria-label="{{ data_get($revengeInsight, 'status', 'Revenge trading tier') }}"><i class="mdi mdi-emoticon-angry-outline"></i></span>
                                            @endif
                                            @if($gamblingInsight)
                                                <span class="trade-signal-icon gambling tier-{{ data_get($gamblingInsight, 'tier', 'low') }}" data-tier="{{ data_get($gamblingInsight, 'tier_short', 'L') }}" title="Normal setup flagged: {{ data_get($gamblingInsight, 'status', 'Gambling behavior tier') }} - {{ data_get($gamblingInsight, 'reason_labels', 'Review high-variance execution') }}" aria-label="{{ data_get($gamblingInsight, 'status', 'Gambling behavior tier') }}"><i class="mdi mdi-dice-multiple-outline"></i></span>
                                            @endif
                                            @if($layeringInsight)
                                                <span class="trade-signal-icon layering" title="Layering signal" aria-label="Layering signal"><i class="mdi mdi-layers-triple-outline"></i></span>
                                            @endif
                                            @if(! $hedgeInsight && ! $revengeInsight && ! $gamblingInsight && ! $layeringInsight)
                                                <span class="trade-signal-icon clear" title="Normal setup / no behavior signal" aria-label="Normal setup / no behavior signal"><i class="mdi mdi-minus"></i></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="note-cell">{{ $journal->notes ? \Illuminate\Support\Str::limit($journal->notes, 70) : '-' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('trading.journal.details', ['id' => $journal->id, 'time_view' => $selectedTimeView, 'mt5_offset_minutes' => $selectedMt5ViewOffset]) }}" class="btn btn-light journal-action-btn" title="View">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </a>
                                            @if(! $propFirmLockMessage)
                                                <a href="{{ route('edit.trading.journal', $journal->id) }}" class="btn btn-light journal-action-btn" title="Edit">
                                                    <i class="mdi mdi-pencil-outline"></i>
                                                </a>
                                                <a href="{{ route('delete.trading.journal', $journal->id) }}" class="btn btn-light text-danger journal-action-btn" title="Delete" onclick="return confirm('Delete this trade?')">
                                                    <i class="mdi mdi-trash-can-outline"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15">
                                        <div class="journal-empty">
                                            <h5 class="mb-2">No trades recorded</h5>
                                            @if(! $propFirmLockMessage)
                                                <a href="{{ route('add.trading.journal') }}" class="btn btn-primary btn-sm">Record Trade</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.trading_journals.partials.behavior_detection_modal', ['behaviorModalId' => 'behaviorDetectionModal'])

@if($pendingEvaluationQuestions->isNotEmpty())
    <div class="modal fade" id="propFirmQuestionModal" tabindex="-1" aria-labelledby="propFirmQuestionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark" id="propFirmQuestionModalLabel">Prop Firm Evaluation Question</h5>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Administration has requested clarification before your prop firm review can continue.</p>

                    @foreach($pendingEvaluationQuestions as $question)
                        <form action="{{ route('trading.propfirm.questions.answer', $question->id) }}" method="POST" class="mb-4">
                            @csrf
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                    <div>
                                        <strong>{{ $question->title ?: 'Evaluation question' }}</strong>
                                        <div class="text-muted small">Phase {{ $question->phase ?? 'N/A' }}</div>
                                    </div>
                                    <span class="badge bg-warning text-dark">Answer Required</span>
                                </div>
                                <p class="mb-3">{{ $question->question }}</p>
                                <label for="answer-{{ $question->id }}" class="form-label">Your Answer</label>
                                <textarea name="answer" id="answer-{{ $question->id }}" rows="5" class="form-control" required placeholder="Explain your trading rationale, risk decision, account activity, or evidence requested by administration."></textarea>
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary">Submit Answer</button>
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="ratingBreakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Performance Rating Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="journal-modal-list">
                    <div class="journal-modal-row">
                        <span>Win Rate</span>
                        <strong>{{ $winRatePoints }} pts <span class="badge bg-primary ms-2">{{ $winRateGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Risk Reward</span>
                        <strong>{{ $rrrPoints }} pts <span class="badge bg-success ms-2">{{ $rrrGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Recovery Factor</span>
                        <strong>{{ $recoveryPoints }} pts <span class="badge bg-info ms-2">{{ $recoveryGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Drawdown Penalty</span>
                        <strong>-{{ $drawdownPenalty }} pts <span class="badge bg-danger ms-2">{{ $drawdownGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Consistency</span>
                        <strong>{{ $consistencyPoints }} pts <span class="badge bg-secondary ms-2">{{ $consistencyGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Position Consistency</span>
                        <strong>{{ number_format((float) data_get($positionProfile, 'score', 0), 2) }}/100 <span class="badge bg-secondary ms-2">{{ data_get($positionProfile, 'grade', 'N/A') }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Trader Style</span>
                        <strong>{{ data_get($traderStyleProfile, 'style_label', 'N/A') }} <span class="badge bg-secondary ms-2">{{ number_format((float) data_get($traderStyleProfile, 'risk_score', 0), 2) }}/100</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Revenge Trading</span>
                        <strong>{{ data_get($traderStyleProfile, 'revenge.status', 'N/A') }} <span class="badge bg-warning text-dark ms-2">{{ number_format((float) data_get($traderStyleProfile, 'revenge.score', 0), 2) }}/100</span></strong>
                    </div>
                    <div class="small text-muted px-2">{{ data_get($traderStyleProfile, 'revenge.tier_description', '-') }}</div>
                    <div class="journal-modal-row">
                        <span>Gambling Behavior</span>
                        <strong>{{ data_get($traderStyleProfile, 'gambling.status', 'N/A') }} <span class="badge bg-danger ms-2">{{ number_format((float) data_get($traderStyleProfile, 'gambling.score', 0), 2) }}/100</span></strong>
                    </div>
                    <div class="small text-muted px-2">{{ data_get($traderStyleProfile, 'gambling.tier_description', '-') }}</div>
                    <div class="journal-modal-row">
                        <span>Layering</span>
                        <strong>{{ data_get($traderStyleProfile, 'layering.status', 'N/A') }} <span class="badge bg-danger ms-2">{{ number_format((float) data_get($traderStyleProfile, 'layering.score', 0), 2) }}/100</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Expectancy</span>
                        <strong>{{ $expectancyPoints }} pts <span class="badge bg-warning text-dark ms-2">{{ $expectancyGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Base Score Before Behaviour Penalty</span>
                        <strong>{{ number_format((float) data_get($behaviorScorePenalty, 'base_score', $totalScore), 2) }} pts</strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Behaviour Tier Penalty</span>
                        <strong class="{{ (float) data_get($behaviorScorePenalty, 'points', 0) > 0 ? 'text-danger' : 'text-success' }}">
                            -{{ number_format((float) data_get($behaviorScorePenalty, 'points', 0), 2) }} pts
                            <span class="badge {{ $behaviorToneBadgeClass(data_get($behaviorScorePenalty, 'tone', 'success')) }} ms-2">{{ data_get($behaviorScorePenalty, 'status', 'No Behaviour Penalty') }}</span>
                        </strong>
                    </div>
                    <div class="small text-muted px-2">{{ data_get($behaviorScorePenalty, 'summary', 'Clear and low revenge/gambling tiers do not reduce the score.') }}</div>
                    <div class="journal-modal-row">
                        <span>Adjusted Total Score</span>
                        <strong>{{ $totalScore }} pts</strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Final Grade</span>
                        <strong>{{ $rating }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scoreCalculationModal" tabindex="-1" aria-labelledby="scoreCalculationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoreCalculationModalLabel">Trading Score Calculation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Total Score</span>
                            <strong>{{ number_format((float) data_get($scoreEvaluationProfile, 'score', 0), 2) }} pts</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Behaviour Penalty</span>
                            <strong>-{{ number_format((float) data_get($scoreEvaluationProfile, 'behavior_score_penalty.points', 0), 2) }} pts</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Final Grade</span>
                            <strong>{{ data_get($scoreEvaluationProfile, 'rating', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Max Positive Points</span>
                            <strong>{{ number_format((float) data_get($scoreEvaluationProfile, 'max_positive_points', 100)) }} pts</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Recovery Factor</span>
                            <strong>{{ data_get($scoreEvaluationProfile, 'recovery_factor', 'N/A') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>{{ data_get($scoreEvaluationProfile, 'formula') }}</strong>
                    <div class="mt-1">{{ data_get($scoreEvaluationProfile, 'meaning') }}</div>
                    <div class="mt-1">{{ data_get($scoreEvaluationProfile, 'behavior_score_penalty.summary', '') }}</div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table journal-table align-middle">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Grade</th>
                                <th>Points</th>
                                <th>Max</th>
                                <th>Status</th>
                                <th>Calculation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(data_get($scoreEvaluationProfile, 'components', []) as $component)
                                <tr>
                                    <td>
                                        <strong>{{ $component['metric'] }}</strong>
                                        <div class="small text-muted">{{ $component['formula'] }}</div>
                                    </td>
                                    <td>{{ $component['value'] }}</td>
                                    <td>{{ $component['grade'] }}</td>
                                    <td class="{{ $component['points'] < 0 ? 'text-danger' : ($component['points'] > 0 ? 'text-success' : '') }}">
                                        {{ $component['points'] > 0 ? '+' : '' }}{{ number_format((float) $component['points'], 2) }}
                                    </td>
                                    <td>{{ $component['max_points'] > 0 ? number_format((float) $component['max_points']) : 'Penalty' }}</td>
                                    <td><span class="badge {{ $component['badge_class'] }}">{{ $component['status'] }}</span></td>
                                    <td>{{ $component['calculation'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3">
                    <div class="col-lg-7">
                        <h6 class="mb-3">Score Bands</h6>
                        <div class="table-responsive">
                            <table class="table journal-table">
                                <thead>
                                    <tr>
                                        <th>Metric</th>
                                        <th>Max</th>
                                        <th>Evaluation Bands</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(data_get($scoreEvaluationProfile, 'criteria_bands', []) as $criteria)
                                        <tr>
                                            <td><strong>{{ $criteria['metric'] }}</strong></td>
                                            <td>{{ $criteria['max_points'] }}</td>
                                            <td>{{ $criteria['bands'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <h6 class="mb-3">Grade Ranking</h6>
                        <div class="table-responsive">
                            <table class="table journal-table">
                                <thead>
                                    <tr>
                                        <th>Grade</th>
                                        <th>Range</th>
                                        <th>Meaning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(data_get($scoreEvaluationProfile, 'grade_ranking', []) as $rank)
                                        <tr class="{{ $rank['grade'] === data_get($scoreEvaluationProfile, 'rating') ? 'table-primary' : '' }}">
                                            <td><strong>{{ $rank['grade'] }}</strong></td>
                                            <td>{{ $rank['range'] }}</td>
                                            <td>{{ $rank['description'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="journal-modal-row mt-3">
                            <span>Recovery Quality Progress</span>
                            <strong>{{ number_format((float) data_get($scoreEvaluationProfile, 'recovery_factor_progress', 0), 2) }}% toward 3.00 recovery factor</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="positionConsistencyModal" tabindex="-1" aria-labelledby="positionConsistencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="positionConsistencyModalLabel">Position Consistency Score</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100"><span>Score</span><strong>{{ number_format((float) data_get($positionProfile, 'score', 0), 2) }}/100</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100"><span>Grade</span><strong>{{ data_get($positionProfile, 'grade', 'N/A') }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100"><span>Main Lot</span><strong>{{ number_format((float) data_get($positionProfile, 'anchor_lot', 0), 4) }} lots</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100"><span>Sample</span><strong>{{ number_format((int) data_get($positionProfile, 'trade_count', 0)) }} trades</strong></div>
                    </div>
                </div>

                <div class="alert alert-info">{{ data_get($positionProfile, 'description', 'No position consistency description is available yet.') }}</div>

                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table journal-table">
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th>Points</th>
                                        <th>Max</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(data_get($positionProfile, 'score_breakdown', []) as $criteria)
                                        <tr>
                                            <td><strong>{{ $criteria['criteria'] }}</strong></td>
                                            <td>{{ number_format($criteria['points'], 2) }}</td>
                                            <td>{{ number_format($criteria['max_points'], 0) }}</td>
                                            <td>{{ $criteria['description'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="journal-modal-list mb-3">
                            <div class="journal-modal-row"><span>Main Lot Usage</span><strong>{{ number_format((float) data_get($positionProfile, 'anchor_lot_share', 0), 2) }}%</strong></div>
                            <div class="journal-modal-row"><span>Near Main Lot</span><strong>{{ number_format((float) data_get($positionProfile, 'near_anchor_share', 0), 2) }}%</strong></div>
                            <div class="journal-modal-row"><span>Lot Range</span><strong>{{ number_format((float) data_get($positionProfile, 'min_lot', 0), 4) }} - {{ number_format((float) data_get($positionProfile, 'max_lot', 0), 4) }}</strong></div>
                            <div class="journal-modal-row"><span>Variation</span><strong>{{ number_format((float) data_get($positionProfile, 'coefficient_of_variation', 0), 2) }}% CV</strong></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table journal-table">
                                <thead>
                                    <tr>
                                        <th>Grade</th>
                                        <th>Range</th>
                                        <th>Meaning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(data_get($positionProfile, 'grade_ranking', []) as $rank)
                                        <tr class="{{ $rank['grade'] === data_get($positionProfile, 'grade') ? 'table-primary' : '' }}">
                                            <td><strong>{{ $rank['grade'] }}</strong></td>
                                            <td>{{ $rank['range'] }}</td>
                                            <td>{{ $rank['description'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="propFirmModal" tabindex="-1" aria-labelledby="propFirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            @php
                $journalEvaluationStatus = $evaluation['status'] ?? 'N/A';
                $journalEvaluationBadge = match ($journalEvaluationStatus) {
                    'PASS', 'APPROVED' => 'bg-success',
                    'FAIL', 'REJECTED', 'SUSPENDED' => 'bg-danger',
                    'PENDING', 'UNDER_REVIEW', 'QUESTION_REQUIRED' => 'bg-warning text-dark',
                    'REVIEWED' => 'bg-primary',
                    'DISABLED', 'N/A' => 'bg-secondary',
                    default => 'bg-info',
                };
                $journalDailyBreakdown = collect(data_get($evaluation, 'profitable_day.daily_breakdown', []));
                $journalProfitTargetPassed = (bool) data_get($evaluation, 'profit_target.passed', false);
                $journalProfitableDayPassed = (bool) data_get($evaluation, 'profitable_day.has_profitable_day', false);
                $journalGrossProfitPassed = (bool) data_get($evaluation, 'gross_profit_rule.passed', false);
                $journalDailyLossBreached = (bool) data_get($evaluation, 'max_daily_loss.breached', false);
                $journalTotalLossBreached = (bool) data_get($evaluation, 'max_total_loss.breached', false);
                $journalTimeWithin = (bool) data_get($evaluation, 'time.within_time', false);
            @endphp
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="propFirmModalLabel">Prop Firm Evaluation Detail</h5>
                    <div class="text-muted small">Phase {{ data_get($evaluation, 'phase', 'N/A') }} rules and closed-day evidence</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(!empty($evaluation) && isset($evaluation['starting_balance']))
                    @if(!empty($evaluation['message']))
                        <div class="alert alert-warning">{{ $evaluation['message'] }}</div>
                    @endif

                    <div class="evaluation-header-panel mb-3">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Evaluation Status</span>
                            <strong>{{ str_replace('_', ' ', $journalEvaluationStatus) }}</strong>
                            <div class="text-muted small">Profitable day rule: {{ data_get($evaluation, 'profitable_day.threshold_label', 'Net daily P/L > 0 after all closed trades') }}</div>
                        </div>
                        <span class="badge {{ $journalEvaluationBadge }}">{{ str_replace('_', ' ', $journalEvaluationStatus) }}</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="evaluation-kpi">
                                <span>Starting Balance</span>
                                <strong>{{ number_format((float) data_get($evaluation, 'starting_balance', 0), 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="evaluation-kpi">
                                <span>Current Balance</span>
                                <strong>{{ number_format((float) data_get($evaluation, 'current_balance', 0), 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="evaluation-kpi">
                                <span>Net P/L</span>
                                <strong>{{ number_format((float) data_get($evaluation, 'net_pnl', 0), 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="evaluation-kpi">
                                <span>Time Window</span>
                                <strong>{{ data_get($evaluation, 'time.days_passed', 0) }} / {{ data_get($evaluation, 'time.max_days', 0) }} days</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="mb-2">Gate Checks</h6>
                            <div class="border rounded-3 px-3">
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>Profit Target</span>
                                        <strong>{{ number_format((float) data_get($evaluation, 'profit_target.achieved', 0), 2) }} / {{ number_format((float) data_get($evaluation, 'profit_target.target_amount', 0), 2) }}</strong>
                                    </div>
                                    <span class="badge {{ $journalProfitTargetPassed ? 'bg-success' : 'bg-warning text-dark' }}">{{ $journalProfitTargetPassed ? 'Passed' : 'Pending' }}</span>
                                </div>
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>Net Profitable Days</span>
                                        <strong>{{ data_get($evaluation, 'profitable_day.profitable_days', 0) }} / {{ data_get($evaluation, 'profitable_day.required_days', 0) }}</strong>
                                    </div>
                                    <span class="badge {{ $journalProfitableDayPassed ? 'bg-success' : 'bg-warning text-dark' }}">{{ data_get($evaluation, 'profitable_day.status_label', 'Pending') }}</span>
                                </div>
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>Max Daily Loss</span>
                                        <strong>{{ number_format((float) data_get($evaluation, 'max_daily_loss.worst_day_pnl', 0), 2) }} / {{ number_format((float) data_get($evaluation, 'max_daily_loss.limit_amount', 0), 2) }}</strong>
                                    </div>
                                    <span class="badge {{ $journalDailyLossBreached ? 'bg-danger' : 'bg-success' }}">{{ $journalDailyLossBreached ? 'Breached' : 'OK' }}</span>
                                </div>
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>Max Total Loss</span>
                                        <strong>{{ number_format((float) data_get($evaluation, 'max_total_loss.overall_pnl', 0), 2) }} / {{ number_format((float) data_get($evaluation, 'max_total_loss.limit_amount', 0), 2) }}</strong>
                                    </div>
                                    <span class="badge {{ $journalTotalLossBreached ? 'bg-danger' : 'bg-success' }}">{{ $journalTotalLossBreached ? 'Breached' : 'OK' }}</span>
                                </div>
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>2% Gross Profit</span>
                                        <strong>{{ number_format((float) data_get($evaluation, 'gross_profit_rule.gross_profit', 0), 2) }} / {{ number_format((float) data_get($evaluation, 'gross_profit_rule.required_amount', 0), 2) }}</strong>
                                    </div>
                                    <span class="badge {{ $journalGrossProfitPassed ? 'bg-success' : 'bg-warning text-dark' }}">{{ $journalGrossProfitPassed ? 'Passed' : 'Pending' }}</span>
                                </div>
                                <div class="evaluation-rule-row">
                                    <div>
                                        <span>Time Window</span>
                                        <strong>{{ data_get($evaluation, 'time.days_passed', 0) }} / {{ data_get($evaluation, 'time.max_days', 0) }} days</strong>
                                    </div>
                                    <span class="badge {{ $journalTimeWithin ? 'bg-success' : 'bg-warning text-dark' }}">{{ $journalTimeWithin ? 'Within Time' : 'Pending' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2">Net Profitable Day Breakdown</h6>
                            <div class="table-responsive border rounded-3">
                                <table class="table table-sm table-hover mb-0 evaluation-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Close Date</th>
                                            <th>Trades</th>
                                            <th>Daily P/L</th>
                                            <th>Counted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($journalDailyBreakdown as $day)
                                            <tr>
                                                <td>{{ data_get($day, 'date', 'N/A') }}</td>
                                                <td>{{ data_get($day, 'trade_count', 0) }}</td>
                                                <td class="{{ (float) data_get($day, 'profit_loss', 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format((float) data_get($day, 'profit_loss', 0), 2) }}
                                                </td>
                                                <td>
                                                    <span class="badge {{ data_get($day, 'is_profitable', false) ? 'bg-success' : 'bg-secondary' }}">
                                                        {{ data_get($day, 'is_profitable', false) ? 'Yes' : 'No' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-muted text-center py-3">No closed trading days available.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted mb-0">No evaluation data is available yet.</p>
                @endif
            </div>
            <div class="modal-footer">
                <span class="me-auto">
                    Final status: <span class="badge {{ $journalEvaluationBadge }}">{{ str_replace('_', ' ', $journalEvaluationStatus) }}</span>
                </span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importTradesModal" tabindex="-1" aria-labelledby="importTradesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('import.trading.journal') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importTradesModalLabel">Import Trading Journal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file" class="form-label">Excel File</label>
                        <input id="file" type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                        @error('file')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <a href="{{ route('download.trades.template') }}">Download import template</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->has('file'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalElement = document.getElementById('importTradesModal');
            if (window.bootstrap && modalElement) {
                new bootstrap.Modal(modalElement).show();
            }
        });
    </script>
@endif

@if($pendingEvaluationQuestions->isNotEmpty())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalElement = document.getElementById('propFirmQuestionModal');
            if (window.bootstrap && modalElement) {
                new bootstrap.Modal(modalElement).show();
            }
        });
    </script>
@endif

@endsection
