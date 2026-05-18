@extends('admin.admin_master')
@section('admin')

<title>Trading Statistics | HC Gaming Studio</title>

<style>
    .evaluation-matrix {
        background: #edf2f7;
        color: #1f2937;
        min-height: 100vh;
        overflow-x: clip;
    }

    .matrix-wrap {
        box-sizing: border-box;
        max-width: 1780px;
        margin: 0 auto;
        padding: 26px 30px 40px;
        width: 100%;
        container-type: inline-size;
    }

    .matrix-hero {
        background: #101827;
        border: 1px solid #1d2939;
        border-radius: 14px;
        color: #f8fafc;
        display: grid;
        gap: 22px;
        grid-template-columns: minmax(0, 1fr) 360px;
        margin-bottom: 18px;
        min-width: 0;
        padding: 28px;
    }

    .matrix-kicker {
        color: #5eead4;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .matrix-hero h3 {
        color: #ffffff;
        font-size: 32px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.15;
        margin: 8px 0;
    }

    .matrix-hero p {
        color: #a8b3c4;
        font-size: 14px;
        margin: 0;
        max-width: 940px;
    }

    .hero-status {
        background: #172033;
        border: 1px solid #2b374c;
        border-radius: 12px;
        min-width: 0;
        padding: 18px;
    }

    .status-label {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        gap: 6px;
        line-height: 1;
        padding: 8px 10px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .status-label.success { background: rgba(20, 184, 166, .14); color: #5eead4; }
    .status-label.primary { background: rgba(99, 102, 241, .16); color: #a5b4fc; }
    .status-label.warning { background: rgba(217, 119, 6, .16); color: #fbbf24; }
    .status-label.danger { background: rgba(225, 29, 72, .16); color: #fda4af; }
    .status-label.secondary { background: rgba(148, 163, 184, .16); color: #cbd5e1; }

    .hero-balance {
        color: #ffffff;
        font-size: 34px;
        font-weight: 900;
        line-height: 1.05;
        margin-top: 16px;
    }

    .hero-meta {
        color: #94a3b8;
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, 1fr);
        margin-top: 18px;
    }

    .hero-meta span {
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .hero-meta strong {
        color: #e5e7eb;
        display: block;
        font-size: 15px;
        margin-top: 2px;
    }

    .filter-dock {
        background: #ffffff;
        border: 1px solid #d8e1ec;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(16, 24, 39, .06);
        margin-bottom: 18px;
        min-width: 0;
        padding: 16px;
    }

    .filter-dock .form-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .matrix-btn {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        font-weight: 800;
        gap: 7px;
        justify-content: center;
        min-height: 38px;
        padding: 8px 14px;
    }

    .matrix-btn.primary {
        background: #0f766e;
        border: 1px solid #0f766e;
        color: #ffffff;
    }

    .matrix-btn.light {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
    }

    .metric-ribbon {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin-bottom: 18px;
        min-width: 0;
    }

    .metric-cell {
        background: #ffffff;
        border: 1px solid #d8e1ec;
        border-radius: 12px;
        min-height: 116px;
        min-width: 0;
        padding: 16px;
        position: relative;
    }

    .metric-cell::before {
        background: #0f766e;
        border-radius: 999px;
        content: "";
        height: 4px;
        left: 16px;
        position: absolute;
        right: 16px;
        top: 0;
    }

    .metric-cell.danger::before { background: #e11d48; }
    .metric-cell.warning::before { background: #d97706; }
    .metric-cell.indigo::before { background: #4f46e5; }
    .metric-cell.slate::before { background: #64748b; }

    .metric-name {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        margin-bottom: 9px;
        text-transform: uppercase;
    }

    .metric-number {
        color: #0f172a;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 8px;
        word-break: break-word;
    }

    .metric-note {
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
        margin: 0;
    }

    .matrix-grid {
        align-items: start;
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.45fr) minmax(360px, .8fr);
        margin-bottom: 18px;
        min-width: 0;
    }

    .matrix-panel {
        background: #ffffff;
        border: 1px solid #d8e1ec;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(16, 24, 39, .05);
        min-width: 0;
        padding: 18px;
    }

    .panel-head {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .panel-head h5 {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .panel-head span {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .chart-box {
        height: 300px;
        min-width: 0;
        position: relative;
    }

    .timeline-layout {
        display: grid;
        gap: 16px;
        min-width: 0;
    }

    .timeline-empty {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #64748b;
        display: flex;
        font-weight: 700;
        height: 100%;
        justify-content: center;
        padding: 18px;
        text-align: center;
    }

    .trade-calendar {
        border-top: 1px solid #e2e8f0;
        min-width: 0;
        overflow-x: auto;
        padding-top: 16px;
    }

    .calendar-head {
        align-items: flex-start;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .calendar-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .calendar-title {
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        line-height: 1.15;
        margin: 4px 0 0;
    }

    .calendar-summary {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(auto-fit, minmax(124px, 1fr));
        min-width: 0;
    }

    .calendar-summary-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        min-width: 0;
        padding: 8px 10px;
    }

    .calendar-summary-item span {
        color: #64748b;
        display: block;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .calendar-summary-item strong {
        color: #0f172a;
        display: block;
        font-size: 13px;
        font-weight: 900;
        margin-top: 2px;
        white-space: nowrap;
    }

    .calendar-grid {
        display: grid;
        gap: 6px;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        min-width: 0;
    }

    .calendar-weekday {
        color: #64748b;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .04em;
        padding: 0 3px;
        text-transform: uppercase;
    }

    .calendar-day {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 68px;
        min-width: 0;
        overflow-wrap: anywhere;
        padding: 8px;
        position: relative;
    }

    .calendar-day.outside {
        background: #f8fafc;
        opacity: .58;
    }

    .calendar-day.has-trade {
        box-shadow: inset 0 3px 0 #64748b;
    }

    .calendar-day.has-trade.win {
        background: #ecfdf5;
        border-color: #99f6e4;
        box-shadow: inset 0 3px 0 #14b8a6;
    }

    .calendar-day.has-trade.loss {
        background: #fff1f2;
        border-color: #fecdd3;
        box-shadow: inset 0 3px 0 #e11d48;
    }

    .calendar-day.has-trade.flat {
        background: #eef2ff;
        border-color: #c7d2fe;
        box-shadow: inset 0 3px 0 #4f46e5;
    }

    .calendar-date {
        color: #334155;
        font-size: 12px;
        font-weight: 900;
    }

    .calendar-pnl {
        font-size: 13px;
        font-weight: 900;
        line-height: 1.15;
        word-break: break-word;
    }

    .calendar-trades {
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        margin-top: 4px;
    }

    .rule-stack {
        display: grid;
        gap: 12px;
    }

    .rule-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 13px;
    }

    .rule-row-top {
        align-items: flex-start;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .rule-title {
        color: #172033;
        font-size: 13px;
        font-weight: 900;
    }

    .rule-value {
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        margin-top: 3px;
    }

    .rule-caption {
        color: #64748b;
        font-size: 12px;
        margin-top: 8px;
    }

    .progress-line {
        background: #e2e8f0;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }

    .progress-line span {
        display: block;
        height: 100%;
    }

    .fill-success { background: #14b8a6; }
    .fill-primary { background: #4f46e5; }
    .fill-warning { background: #d97706; }
    .fill-danger { background: #e11d48; }
    .fill-secondary { background: #64748b; }

    .text-profit { color: #0f766e !important; }
    .text-loss { color: #e11d48 !important; }

    .subgrid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-bottom: 18px;
        min-width: 0;
    }

    .bias-meter {
        align-items: center;
        display: grid;
        gap: 16px;
        grid-template-columns: 82px 1fr 82px;
    }

    .bias-node {
        align-items: center;
        background: #eef6ff;
        border-radius: 14px;
        color: #4f46e5;
        display: flex;
        height: 82px;
        justify-content: center;
    }

    .bias-node i {
        font-size: 34px;
    }

    .bias-center {
        text-align: center;
    }

    .bias-center strong {
        color: #0f172a;
        display: block;
        font-size: 22px;
        font-weight: 900;
    }

    .split-track {
        background: #e2e8f0;
        border-radius: 999px;
        height: 12px;
        margin-top: 12px;
        overflow: hidden;
    }

    .split-track span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .weekday-matrix {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(7, 1fr);
    }

    .weekday-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        min-height: 86px;
        padding: 10px;
        text-align: center;
    }

    .weekday-box.win {
        background: #ecfdf5;
        border-color: #a7f3d0;
    }

    .weekday-box.loss {
        background: #fff1f2;
        border-color: #fecdd3;
    }

    .weekday-box .day {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .weekday-box .pnl {
        font-size: 16px;
        font-weight: 900;
    }

    .session-list {
        display: grid;
        gap: 13px;
    }

    .session-row {
        display: grid;
        gap: 12px;
        grid-template-columns: 92px 1fr 58px;
        align-items: center;
    }

    .session-name {
        color: #334155;
        font-weight: 900;
    }

    .session-track {
        background: #e2e8f0;
        border-radius: 999px;
        height: 12px;
        overflow: hidden;
    }

    .session-track span {
        background: #4f46e5;
        display: block;
        height: 100%;
    }

    .session-rate {
        color: #0f172a;
        font-weight: 900;
        text-align: right;
    }

    .level-layout {
        display: grid;
        gap: 14px;
        grid-template-columns: 1fr 1fr;
    }

    .level-card {
        background: #0f172a;
        border-radius: 12px;
        color: #ffffff;
        padding: 18px;
    }

    .level-card .label {
        color: #94a3b8;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .level-card .level {
        font-size: 30px;
        font-weight: 900;
        margin-top: 8px;
    }

    .reward-list {
        display: grid;
        gap: 10px;
    }

    .reward-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
    }

    .reward-item span {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .reward-item strong {
        color: #0f172a;
        display: block;
        font-size: 18px;
        margin-top: 3px;
    }

    .detail-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        min-width: 0;
    }

    .matrix-table {
        margin-bottom: 0;
    }

    .matrix-table thead th {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .matrix-table td {
        border-color: #e2e8f0;
        color: #334155;
        font-size: 13px;
        vertical-align: middle;
    }

    @container (max-width: 1700px) {
        .matrix-grid {
            grid-template-columns: 1fr;
        }

        .calendar-head {
            align-items: stretch;
            flex-direction: column;
        }
    }

    @container (max-width: 1500px) {
        .metric-ribbon {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .subgrid,
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @container (max-width: 1100px) {
        .matrix-hero,
        .level-layout {
            grid-template-columns: 1fr;
        }

        .metric-ribbon {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 2050px) {
        .matrix-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1500px) {
        .metric-ribbon {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .calendar-head {
            align-items: stretch;
            flex-direction: column;
        }

        .calendar-summary {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .subgrid,
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1100px) {
        .matrix-hero,
        .matrix-grid,
        .level-layout {
            grid-template-columns: 1fr;
        }

        .metric-ribbon {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .calendar-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .matrix-wrap {
            padding: 18px 12px 28px;
        }

        .metric-ribbon,
        .bias-meter,
        .weekday-matrix {
            grid-template-columns: 1fr;
        }

        .chart-box {
            height: 260px;
        }

        .calendar-grid {
            gap: 4px;
        }

        .calendar-day {
            border-radius: 6px;
            min-height: 58px;
            padding: 6px;
        }

        .calendar-pnl {
            font-size: 11px;
        }

        .calendar-trades {
            font-size: 9px;
        }
    }

    @media (max-width: 520px) {
        .calendar-summary {
            grid-template-columns: 1fr;
        }

        .calendar-day {
            min-height: 48px;
        }
    }
</style>

@php
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($month) => [
        $month => \Carbon\Carbon::create(2000, $month, 1)->format('F'),
    ]);
    $yearOptions = $availableYears->isNotEmpty() ? $availableYears : collect(range(now()->year, now()->year - 5));
    $displayName = $currentUser->name ?: ($currentUser->username ?: 'Trader');
    $tone = $performanceProfile['tone'] ?? 'primary';
    $topPairs = $pairStats->take(5);
    $metricCells = [
        ['name' => 'Equity Balance', 'value' => number_format($summary['current_balance'], 2) . 'u', 'note' => 'Capital adjusted account value', 'tone' => $summary['current_balance'] >= 0 ? '' : 'danger'],
        ['name' => 'Net P/L', 'value' => number_format($summary['net_profit_loss'], 2) . 'u', 'note' => 'Growth ' . number_format($summary['growth_percent'], 2) . '%', 'tone' => $summary['net_profit_loss'] >= 0 ? '' : 'danger'],
        ['name' => 'Max Drawdown', 'value' => number_format($summary['max_drawdown_percent'], 2) . '%', 'note' => number_format($summary['max_drawdown_amount'], 2) . 'u peak-to-trough', 'tone' => $summary['max_drawdown_percent'] > 10 ? 'danger' : 'warning'],
        ['name' => 'Consistency', 'value' => number_format($summary['consistency_percent'], 2) . '%', 'note' => 'Largest winning day share', 'tone' => 'indigo'],
        ['name' => 'Win Rate', 'value' => number_format($summary['win_rate'], 2) . '%', 'note' => $summary['winning_trades'] . 'W / ' . $summary['losing_trades'] . 'L', 'tone' => 'slate'],
        ['name' => 'Profit Factor', 'value' => is_numeric($summary['profit_factor']) ? number_format($summary['profit_factor'], 2) : $summary['profit_factor'], 'note' => 'Gross profit divided by gross loss', 'tone' => 'slate'],
    ];
    $calendarYear = $selectedYear ?: (optional($summary['last_trade_date'])->year ?: now()->year);
    $calendarMonth = $selectedMonth ?: (optional($summary['last_trade_date'])->month ?: now()->month);
    $calendarBase = \Carbon\Carbon::create($calendarYear, $calendarMonth, 1)->startOfMonth();
    $calendarStart = $calendarBase->copy()->startOfMonth()->startOfWeek(0);
    $calendarEnd = $calendarBase->copy()->endOfMonth()->endOfWeek(6);
    $dailyStatMap = $dailyStats->keyBy('date');
    $calendarDays = collect();
    $calendarWalker = $calendarStart->copy();

    while ($calendarWalker->lte($calendarEnd)) {
        $key = $calendarWalker->toDateString();
        $stat = $dailyStatMap->get($key, ['profit_loss' => 0, 'trades' => 0]);

        $calendarDays->push([
            'date' => $calendarWalker->copy(),
            'is_current_month' => $calendarWalker->month === $calendarBase->month,
            'profit_loss' => (float) ($stat['profit_loss'] ?? 0),
            'trades' => (int) ($stat['trades'] ?? 0),
        ]);

        $calendarWalker->addDay();
    }

    $currentMonthDays = $calendarDays->filter(fn ($day) => $day['is_current_month']);
    $calendarMonthProfit = round($currentMonthDays->sum('profit_loss'), 2);
    $calendarMonthTrades = $currentMonthDays->sum('trades');
    $calendarActiveDays = $currentMonthDays->filter(fn ($day) => $day['trades'] > 0)->count();
    $calendarWinDays = $currentMonthDays->filter(fn ($day) => $day['profit_loss'] > 0)->count();
    $calendarLossDays = $currentMonthDays->filter(fn ($day) => $day['profit_loss'] < 0)->count();
@endphp

<div class="page-content evaluation-matrix">
    <div class="container-fluid matrix-wrap">
        <div class="matrix-hero">
            <div>
                <div class="matrix-kicker">HC Prop Evaluation Matrix</div>
                <h3>{{ $displayName }} Trading Statistics</h3>
                <p>{{ $performanceProfile['headline'] }} {{ $performanceProfile['detail'] }}</p>
            </div>
            <div class="hero-status">
                <span class="status-label {{ $tone }}">
                    <i class="ri-pulse-line"></i>{{ $performanceProfile['status'] }}
                </span>
                <div class="hero-balance">{{ number_format($summary['current_balance'], 2) }}u</div>
                <div class="hero-meta">
                    <div>
                        <span>Allocation</span>
                        <strong>{{ number_format($capitalSummary['total_deposits'], 2) }}u</strong>
                    </div>
                    <div>
                        <span>Level</span>
                        <strong>{{ $levelProfile['level'] }}</strong>
                    </div>
                    <div>
                        <span>Best Day</span>
                        <strong>{{ $summary['best_weekday'] }}</strong>
                    </div>
                    <div>
                        <span>Avg Hold</span>
                        <strong>{{ $summary['average_holding_minutes'] }}m</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-dock">
            <form method="GET" action="{{ route('all.trading.statistics') }}" class="row g-3">
                @if($canViewAll)
                    <div class="col-xl-3 col-md-6">
                        <label for="user_id" class="form-label">Trader</label>
                        <select name="user_id" id="user_id" class="form-select">
                            <option value="all" {{ empty($selectedTraderId) ? 'selected' : '' }}>All Traders</option>
                            @foreach($traders as $trader)
                                <option value="{{ $trader->id }}" {{ (int) $selectedTraderId === (int) $trader->id ? 'selected' : '' }}>
                                    {{ $trader->name ?: $trader->username }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-xl-2 col-md-6">
                    <label for="source" class="form-label">Journal Source</label>
                    <select name="source" id="source" class="form-select">
                        <option value="all" {{ $selectedSource === 'all' ? 'selected' : '' }}>All Sources</option>
                        <option value="current" {{ $selectedSource === 'current' ? 'selected' : '' }}>Current Journal</option>
                        <option value="backup" {{ $selectedSource === 'backup' ? 'selected' : '' }}>Backup Journal</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-6">
                    <label for="month" class="form-label">Month</label>
                    <select name="month" id="month" class="form-select">
                        <option value="all" {{ empty($selectedMonth) ? 'selected' : '' }}>All Months</option>
                        @foreach($monthNames as $month => $monthName)
                            <option value="{{ $month }}" {{ (int) $selectedMonth === (int) $month ? 'selected' : '' }}>
                                {{ $monthName }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-2 col-md-6">
                    <label for="year" class="form-label">Year</label>
                    <select name="year" id="year" class="form-select">
                        <option value="all" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                        @foreach($yearOptions as $year)
                            <option value="{{ $year }}" {{ (int) $selectedYear === (int) $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-3 col-md-12 d-flex align-items-end gap-2">
                    <button type="submit" class="matrix-btn primary"><i class="ri-filter-3-line"></i>Apply</button>
                    <a href="{{ route('all.trading.statistics') }}" class="matrix-btn light"><i class="ri-refresh-line"></i>Reset</a>
                </div>
            </form>
        </div>

        <div class="metric-ribbon">
            @foreach($metricCells as $metric)
                <div class="metric-cell {{ $metric['tone'] }}">
                    <div class="metric-name">{{ $metric['name'] }}</div>
                    <div class="metric-number">{{ $metric['value'] }}</div>
                    <p class="metric-note">{{ $metric['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="matrix-grid">
            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Equity And Risk Timeline</h5>
                    <span>{{ optional($summary['first_trade_date'])->format('M d, Y') ?? 'N/A' }} to {{ optional($summary['last_trade_date'])->format('M d, Y') ?? 'N/A' }}</span>
                </div>
                <div class="timeline-layout">
                    <div class="chart-box">
                        @if($dailyStats->isEmpty())
                            <div class="timeline-empty">No trade data found for the selected filters.</div>
                        @else
                            <canvas id="equityCurveChart"></canvas>
                        @endif
                    </div>

                    <div class="trade-calendar">
                        <div class="calendar-head">
                            <div>
                                <div class="calendar-label">Trading Calendar</div>
                                <div class="calendar-title">{{ $calendarBase->format('F Y') }}</div>
                            </div>
                            <div class="calendar-summary">
                                <div class="calendar-summary-item">
                                    <span>Month P/L</span>
                                    <strong class="{{ $calendarMonthProfit >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($calendarMonthProfit, 2) }}u</strong>
                                </div>
                                <div class="calendar-summary-item">
                                    <span>Trades</span>
                                    <strong>{{ number_format($calendarMonthTrades) }}</strong>
                                </div>
                                <div class="calendar-summary-item">
                                    <span>Active Days</span>
                                    <strong>{{ number_format($calendarActiveDays) }}</strong>
                                </div>
                                <div class="calendar-summary-item">
                                    <span>W / L Days</span>
                                    <strong>{{ $calendarWinDays }} / {{ $calendarLossDays }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="calendar-grid">
                            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                                <div class="calendar-weekday">{{ $dayName }}</div>
                            @endforeach

                            @foreach($calendarDays as $calendarDay)
                                @php
                                    $calendarTone = $calendarDay['profit_loss'] > 0
                                        ? 'win'
                                        : ($calendarDay['profit_loss'] < 0 ? 'loss' : ($calendarDay['trades'] > 0 ? 'flat' : ''));
                                    $calendarSign = $calendarDay['profit_loss'] > 0 ? '+' : '';
                                @endphp
                                <div class="calendar-day {{ $calendarDay['is_current_month'] ? '' : 'outside' }} {{ $calendarDay['trades'] > 0 ? 'has-trade' : '' }} {{ $calendarTone }}">
                                    <div class="calendar-date">{{ $calendarDay['date']->day }}</div>
                                    @if($calendarDay['trades'] > 0)
                                        <div>
                                            <div class="calendar-pnl {{ $calendarDay['profit_loss'] >= 0 ? 'text-profit' : 'text-loss' }}">{{ $calendarSign }}{{ number_format($calendarDay['profit_loss'], 0) }}u</div>
                                            <div class="calendar-trades">{{ $calendarDay['trades'] }} trade{{ $calendarDay['trades'] === 1 ? '' : 's' }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Evaluation Rules</h5>
                    <span>Prop firm guardrails</span>
                </div>
                <div class="rule-stack">
                    @foreach($ruleMonitor as $rule)
                        <div class="rule-row">
                            <div class="rule-row-top">
                                <div>
                                    <div class="rule-title">{{ $rule['title'] }}</div>
                                    <div class="rule-value">{{ $rule['value'] }}</div>
                                </div>
                                <span class="status-label {{ $rule['tone'] }}">{{ $rule['status'] }}</span>
                            </div>
                            <div class="progress-line">
                                <span class="fill-{{ $rule['tone'] }}" style="width: {{ $rule['progress'] }}%;"></span>
                            </div>
                            <div class="rule-caption">{{ $rule['caption'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="subgrid">
            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Behavior Profile</h5>
                    <span>Long vs short exposure</span>
                </div>
                <div class="bias-meter">
                    <div class="bias-node"><i class="ri-arrow-up-line"></i></div>
                    <div class="bias-center">
                        <strong>{{ $behavioralProfile['label'] }}</strong>
                        <div class="split-track"><span style="width: {{ $behavioralProfile['buy_share'] }}%;"></span></div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>{{ number_format($behavioralProfile['buy_share'], 1) }}% Buy</span>
                            <span>{{ number_format($behavioralProfile['sell_share'], 1) }}% Sell</span>
                        </div>
                    </div>
                    <div class="bias-node"><i class="ri-arrow-down-line"></i></div>
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Weekday Quality</h5>
                    <span>Best: {{ $summary['best_weekday'] }}</span>
                </div>
                <div class="weekday-matrix">
                    @foreach($weekdayStats as $day)
                        <div class="weekday-box {{ $day['profit_loss'] > 0 ? 'win' : ($day['profit_loss'] < 0 ? 'loss' : '') }}">
                            <div class="day">{{ $day['short_day'] }}</div>
                            <div class="pnl {{ $day['profit_loss'] >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($day['profit_loss'], 0) }}u</div>
                            <div class="small text-muted">{{ $day['trades'] }} trades</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Session Efficiency</h5>
                    <span>Win rate by close time</span>
                </div>
                <div class="session-list">
                    @foreach($sessionStats as $session)
                        <div class="session-row">
                            <div class="session-name">{{ $session['name'] }}</div>
                            <div class="session-track"><span style="width: {{ $session['win_rate'] }}%;"></span></div>
                            <div class="session-rate">{{ number_format($session['win_rate'], 1) }}%</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="subgrid">
            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Progress Level</h5>
                    <span>{{ $levelProfile['score'] }}/100 desk score</span>
                </div>
                <div class="level-layout">
                    <div class="level-card">
                        <div class="label">Current Level</div>
                        <div class="level">{{ $levelProfile['level'] }}</div>
                    </div>
                    <div class="reward-list">
                        <div class="reward-item"><span>Total Reward Estimate</span><strong>{{ number_format($levelProfile['reward'], 2) }}u</strong></div>
                        <div class="reward-item"><span>Highest Reward Estimate</span><strong>{{ number_format($levelProfile['highest_reward'], 2) }}u</strong></div>
                    </div>
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Instrument Leaderboard</h5>
                    <span>Top five by net P/L</span>
                </div>
                <div class="table-responsive">
                    <table class="table matrix-table">
                        <thead>
                            <tr>
                                <th>Pair</th>
                                <th>Trades</th>
                                <th>W/L</th>
                                <th>Win Rate</th>
                                <th>Net P/L</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topPairs as $pair)
                                <tr>
                                    <td><strong>{{ $pair['pair'] }}</strong></td>
                                    <td>{{ number_format($pair['trades']) }}</td>
                                    <td>{{ $pair['wins'] }} / {{ $pair['losses'] }}</td>
                                    <td>{{ number_format($pair['win_rate'], 2) }}%</td>
                                    <td class="{{ $pair['profit_loss'] >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($pair['profit_loss'], 2) }}u</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No pair statistics available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Profitability Snapshot</h5>
                    <span>{{ number_format($summary['total_trades']) }} trades</span>
                </div>
                <div class="reward-list">
                    <div class="reward-item"><span>Won</span><strong class="text-profit">{{ number_format($summary['win_rate'], 2) }}% ({{ $summary['winning_trades'] }})</strong></div>
                    <div class="reward-item"><span>Lost</span><strong class="text-loss">{{ number_format($summary['loss_rate'], 2) }}% ({{ $summary['losing_trades'] }})</strong></div>
                    <div class="reward-item"><span>Average Holding</span><strong>{{ $summary['average_holding_minutes'] }} minutes</strong></div>
                </div>
            </div>
        </div>

        <div class="detail-grid">
            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Full Pair Performance</h5>
                    <span>Ranked by net P/L</span>
                </div>
                <div class="table-responsive">
                    <table class="table matrix-table">
                        <thead>
                            <tr>
                                <th>Pair</th>
                                <th>Trades</th>
                                <th>Pips</th>
                                <th>Win Rate</th>
                                <th>Net P/L</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pairStats as $pair)
                                <tr>
                                    <td><strong>{{ $pair['pair'] }}</strong></td>
                                    <td>{{ number_format($pair['trades']) }}</td>
                                    <td>{{ number_format($pair['pips'], 2) }}</td>
                                    <td>{{ number_format($pair['win_rate'], 2) }}%</td>
                                    <td class="{{ $pair['profit_loss'] >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($pair['profit_loss'], 2) }}u</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No pair statistics available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="matrix-panel">
                <div class="panel-head">
                    <h5>Recent Trades Included</h5>
                    <span>Latest 15 records</span>
                </div>
                <div class="table-responsive">
                    <table class="table matrix-table">
                        <thead>
                            <tr>
                                <th>Close Date</th>
                                <th>Source</th>
                                <th>Pair</th>
                                <th>Direction</th>
                                <th>P/L</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTrades as $trade)
                                <tr>
                                    <td>{{ optional($trade['closed_at'])->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                    <td>{{ $trade['source'] }}</td>
                                    <td><strong>{{ $trade['pair'] }}</strong></td>
                                    <td>{{ $trade['direction'] === 1 ? 'Buy' : ($trade['direction'] === 2 ? 'Sell' : 'N/A') }}</td>
                                    <td class="{{ $trade['profit_loss'] >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($trade['profit_loss'], 2) }}u</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No trades matched the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('backend/assets/libs/chart.js/Chart.bundle.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var chartData = @json($chartData);
        var equityCanvas = document.getElementById('equityCurveChart');

        if (!equityCanvas) {
            return;
        }

        new Chart(equityCanvas, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Daily P/L',
                        data: chartData.daily_pnl,
                        backgroundColor: chartData.daily_pnl.map(function (value) {
                            return value >= 0 ? 'rgba(15, 118, 110, .28)' : 'rgba(225, 29, 72, .24)';
                        }),
                        borderColor: chartData.daily_pnl.map(function (value) {
                            return value >= 0 ? '#0f766e' : '#e11d48';
                        }),
                        borderWidth: 1,
                        yAxisID: 'y-axis-1'
                    },
                    {
                        type: 'line',
                        label: 'Cumulative P/L',
                        data: chartData.cumulative_pnl,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, .08)',
                        borderWidth: 3,
                        pointRadius: 2,
                        tension: 0.24,
                        fill: false,
                        yAxisID: 'y-axis-2'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                tooltips: { mode: 'index', intersect: false },
                scales: {
                    xAxes: [{
                        gridLines: { color: 'rgba(148, 163, 184, .14)' },
                        ticks: { fontColor: '#64748b', maxTicksLimit: 7 }
                    }],
                    yAxes: [
                        {
                            id: 'y-axis-1',
                            position: 'left',
                            gridLines: { color: 'rgba(148, 163, 184, .14)' },
                            ticks: { fontColor: '#64748b' }
                        },
                        {
                            id: 'y-axis-2',
                            position: 'right',
                            gridLines: { drawOnChartArea: false },
                            ticks: { fontColor: '#64748b' }
                        }
                    ]
                }
            }
        });
    });
</script>
@endsection
