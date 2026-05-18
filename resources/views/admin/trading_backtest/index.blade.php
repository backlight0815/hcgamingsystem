@extends('admin.admin_master')
@section('admin')

<title>XAUUSD Backtest Lab | HC Gaming Studio</title>

<style>
    .backtest-lab {
        background: #101215;
        color: #e5e7eb;
        min-height: 100vh;
    }

    .backtest-wrap {
        max-width: 1780px;
        padding: 24px 28px 38px;
    }

    .backtest-topbar {
        align-items: stretch;
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1fr) 420px;
        margin-bottom: 16px;
    }

    .backtest-hero,
    .upload-panel,
    .desk-panel,
    .metric-tile,
    .trade-table-panel {
        background: #171b22;
        border: 1px solid #2a303a;
        border-radius: 12px;
        box-shadow: 0 18px 44px rgba(0, 0, 0, .28);
    }

    .backtest-hero {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1fr) 310px;
        overflow: hidden;
        padding: 22px;
        position: relative;
    }

    .backtest-kicker {
        color: #2dd4bf;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .09em;
        text-transform: uppercase;
    }

    .backtest-hero h3 {
        color: #ffffff;
        font-size: 31px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.12;
        margin: 8px 0 10px;
    }

    .backtest-hero p {
        color: #9ca3af;
        font-size: 13px;
        line-height: 1.55;
        margin: 0;
        max-width: 880px;
    }

    .instrument-card {
        background: #0f131a;
        border: 1px solid #333b49;
        border-radius: 10px;
        padding: 16px;
    }

    .instrument-card span {
        color: #8d96a5;
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .instrument-card strong {
        color: #ffffff;
        display: block;
        font-size: 32px;
        font-weight: 900;
        line-height: 1;
        margin-top: 8px;
    }

    .instrument-meta {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(2, 1fr);
        margin-top: 14px;
    }

    .instrument-meta div {
        background: #151a22;
        border: 1px solid #2f3745;
        border-radius: 8px;
        padding: 10px;
    }

    .instrument-meta b {
        color: #f3f4f6;
        display: block;
        font-size: 14px;
        margin-top: 2px;
    }

    .upload-panel {
        padding: 18px;
    }

    .upload-panel label,
    .journal-filter label {
        color: #8d96a5;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .upload-panel .form-control,
    .upload-panel .form-select,
    .journal-filter .form-select {
        background: #0f131a;
        border: 1px solid #333b49;
        color: #e5e7eb;
    }

    .upload-panel .form-control:focus,
    .upload-panel .form-select:focus,
    .journal-filter .form-select:focus {
        border-color: #2dd4bf;
        box-shadow: 0 0 0 .16rem rgba(45, 212, 191, .16);
    }

    .backtest-btn {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        font-weight: 900;
        gap: 7px;
        justify-content: center;
        min-height: 38px;
        padding: 8px 13px;
    }

    .backtest-btn.primary {
        background: #0f766e;
        border: 1px solid #14b8a6;
        color: #ffffff;
    }

    .backtest-btn.secondary {
        background: #242b36;
        border: 1px solid #394252;
        color: #f3f4f6;
    }

    .source-pill {
        align-items: center;
        background: rgba(45, 212, 191, .12);
        border: 1px solid rgba(45, 212, 191, .28);
        border-radius: 999px;
        color: #99f6e4;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        gap: 6px;
        margin-bottom: 12px;
        padding: 7px 10px;
        text-transform: uppercase;
    }

    .column-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
    }

    .column-strip span {
        background: #242b36;
        border: 1px solid #394252;
        border-radius: 999px;
        color: #b7c0cf;
        font-size: 11px;
        font-weight: 800;
        padding: 5px 8px;
    }

    .journal-filter {
        border-top: 1px solid #2a303a;
        margin-top: 14px;
        padding-top: 14px;
    }

    .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        margin-bottom: 16px;
    }

    .metric-tile {
        min-height: 104px;
        padding: 14px;
        position: relative;
    }

    .metric-tile::before {
        background: #2dd4bf;
        border-radius: 999px;
        content: "";
        height: 3px;
        left: 14px;
        position: absolute;
        right: 14px;
        top: 0;
    }

    .metric-tile.loss::before { background: #fb7185; }
    .metric-tile.warn::before { background: #f59e0b; }
    .metric-tile.neutral::before { background: #94a3b8; }
    .metric-tile.blue::before { background: #60a5fa; }

    .metric-label {
        color: #8d96a5;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .06em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .metric-value {
        color: #ffffff;
        font-size: 22px;
        font-weight: 900;
        line-height: 1.12;
        word-break: break-word;
    }

    .metric-note {
        color: #8d96a5;
        font-size: 11px;
        line-height: 1.35;
        margin-top: 8px;
    }

    .text-gain { color: #2dd4bf !important; }
    .text-draw { color: #fb7185 !important; }
    .text-warn { color: #fbbf24 !important; }

    .desk-grid {
        align-items: start;
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.55fr) minmax(330px, .75fr);
        margin-bottom: 16px;
    }

    .tv-market-panel {
        background: #171b22;
        border: 1px solid #2a303a;
        border-radius: 12px;
        box-shadow: 0 18px 44px rgba(0, 0, 0, .28);
        margin-bottom: 16px;
        padding: 18px;
    }

    .tv-chart-shell {
        background: #0d1117;
        border: 1px solid #2a303a;
        border-radius: 10px;
        height: 680px;
        overflow: hidden;
        position: relative;
    }

    .tv-chart-shell .tradingview-widget-container,
    .tv-chart-shell .tradingview-widget-container__widget {
        height: 100%;
        width: 100%;
    }

    .tv-chart-shell .tradingview-widget-copyright {
        background: #0d1117;
        border-top: 1px solid #2a303a;
        bottom: 0;
        font-size: 11px;
        left: 0;
        padding: 6px 10px;
        position: absolute;
        right: 0;
    }

    .tv-chart-shell .tradingview-widget-copyright a {
        color: #60a5fa;
    }

    .desk-panel {
        padding: 18px;
    }

    .panel-head {
        align-items: flex-start;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .panel-head h5 {
        color: #ffffff;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .panel-head span {
        color: #8d96a5;
        font-size: 12px;
        font-weight: 800;
    }

    .chart-shell {
        background: #0d1117;
        border: 1px solid #2a303a;
        border-radius: 10px;
        display: grid;
        grid-template-rows: 42px minmax(0, 1fr);
        height: 620px;
        overflow: hidden;
        position: relative;
    }

    .replay-chart-topbar {
        align-items: center;
        background: #111720;
        border-bottom: 1px solid #29313d;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        min-width: 0;
        padding: 0 10px;
    }

    .chart-timeframes,
    .chart-action-group {
        align-items: center;
        display: flex;
        gap: 4px;
        min-width: 0;
    }

    .chart-timeframes button,
    .chart-action-btn {
        align-items: center;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 6px;
        color: #c8d0dc;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        height: 28px;
        justify-content: center;
        min-width: 30px;
        padding: 0 8px;
    }

    .chart-timeframes button.active,
    .chart-action-btn.active {
        background: #263241;
        border-color: #445267;
        color: #ffffff;
    }

    .chart-action-btn i {
        font-size: 15px;
    }

    .chart-action-divider {
        background: #334155;
        height: 22px;
        margin: 0 4px;
        width: 1px;
    }

    .chart-terminal-badge {
        background: rgba(45, 212, 191, .12);
        border: 1px solid rgba(45, 212, 191, .28);
        border-radius: 999px;
        color: #99f6e4;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
        padding: 7px 9px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .chart-stage {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        min-height: 0;
    }

    .chart-drawing-rail {
        align-items: center;
        background: #111720;
        border-right: 1px solid #29313d;
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 8px 6px;
    }

    .drawing-tool {
        align-items: center;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 7px;
        color: #c8d0dc;
        display: inline-flex;
        height: 28px;
        justify-content: center;
        width: 28px;
    }

    .drawing-tool.active,
    .drawing-tool:hover {
        background: #263241;
        border-color: #445267;
        color: #ffffff;
    }

    .drawing-tool i {
        font-size: 15px;
    }

    .chart-canvas-wrap {
        min-width: 0;
        overflow: hidden;
        position: relative;
    }

    .chart-canvas-wrap canvas {
        display: block;
        height: 100%;
        inset: 0;
        position: absolute;
        width: 100%;
    }

    .chart-symbol-strip {
        align-items: flex-start;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        left: 10px;
        pointer-events: none;
        position: absolute;
        right: 96px;
        top: 10px;
        z-index: 3;
    }

    .chart-symbol-title {
        color: #e5e7eb;
        font-size: 12px;
        font-weight: 900;
        line-height: 1.25;
    }

    .chart-symbol-title span {
        color: #8d96a5;
        font-weight: 800;
    }

    .chart-ohlc-line {
        color: #2dd4bf;
        font-size: 11px;
        font-weight: 900;
        margin-top: 2px;
        white-space: nowrap;
    }

    .chart-ohlc-line.negative {
        color: #fb7185;
    }

    .chart-mode-chip {
        background: rgba(96, 165, 250, .14);
        border: 1px solid rgba(96, 165, 250, .28);
        border-radius: 999px;
        color: #bfdbfe;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
        padding: 7px 9px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .chart-watermark {
        bottom: 10px;
        color: rgba(148, 163, 184, .22);
        font-size: 24px;
        font-weight: 900;
        left: 14px;
        letter-spacing: .08em;
        pointer-events: none;
        position: absolute;
        text-transform: uppercase;
        z-index: 2;
    }

    .chart-empty {
        align-items: center;
        color: #8d96a5;
        display: flex;
        font-weight: 800;
        height: 100%;
        justify-content: center;
        padding: 20px;
        text-align: center;
    }

    .replay-toolbar {
        align-items: center;
        background: #0f131a;
        border: 1px solid #2a303a;
        border-radius: 10px;
        display: grid;
        gap: 14px;
        grid-template-columns: auto minmax(0, 1fr);
        margin-bottom: 12px;
        padding: 10px;
    }

    .replay-controls {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .replay-icon-btn,
    .replay-play-btn {
        align-items: center;
        background: #202632;
        border: 1px solid #374151;
        border-radius: 8px;
        color: #e5e7eb;
        display: inline-flex;
        font-weight: 900;
        gap: 6px;
        height: 38px;
        justify-content: center;
        min-width: 38px;
        padding: 0 11px;
    }

    .replay-icon-btn:hover,
    .replay-play-btn:hover {
        background: #293241;
        border-color: #4b5563;
        color: #ffffff;
    }

    .replay-play-btn {
        background: #0f766e;
        border-color: #14b8a6;
        color: #ffffff;
        min-width: 96px;
    }

    .replay-speed {
        background: #0d1117;
        border: 1px solid #374151;
        border-radius: 8px;
        color: #e5e7eb;
        font-size: 12px;
        font-weight: 900;
        height: 38px;
        padding: 0 10px;
    }

    .replay-progress {
        display: grid;
        gap: 7px;
    }

    .replay-readout {
        align-items: center;
        display: flex;
        gap: 12px;
        justify-content: space-between;
    }

    .replay-readout span,
    .replay-readout strong {
        color: #8d96a5;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .replay-readout strong {
        color: #e5e7eb;
        text-align: right;
        text-transform: none;
    }

    .replay-range {
        accent-color: #14b8a6;
        height: 18px;
        width: 100%;
    }

    .legend-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }

    .legend-row span {
        align-items: center;
        color: #b7c0cf;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 6px;
    }

    .legend-dot {
        border-radius: 999px;
        display: inline-block;
        height: 9px;
        width: 9px;
    }

    .trade-inspector {
        display: grid;
        gap: 12px;
    }

    .inspector-price {
        background: #0f131a;
        border: 1px solid #333b49;
        border-radius: 10px;
        padding: 14px;
    }

    .inspector-price span {
        color: #8d96a5;
        display: block;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .inspector-price strong {
        color: #ffffff;
        display: block;
        font-size: 20px;
        margin-top: 4px;
    }

    .inspector-grid {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .outcome-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        padding: 8px 10px;
        text-transform: uppercase;
    }

    .outcome-badge.win { background: rgba(45, 212, 191, .12); color: #99f6e4; }
    .outcome-badge.loss { background: rgba(251, 113, 133, .12); color: #fecdd3; }
    .outcome-badge.flat { background: rgba(251, 191, 36, .12); color: #fde68a; }

    .trade-table-panel {
        padding: 18px;
    }

    .backtest-table {
        color: #d1d5db;
        margin-bottom: 0;
    }

    .backtest-table thead th {
        background: #0f131a;
        border-color: #2a303a;
        color: #8d96a5;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .backtest-table td {
        border-color: #2a303a;
        color: #d1d5db;
        font-size: 13px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .backtest-table tbody tr {
        cursor: pointer;
    }

    .backtest-table tbody tr:hover,
    .backtest-table tbody tr.active {
        background: rgba(45, 212, 191, .08);
    }

    .price-tag {
        color: #ffffff;
        font-weight: 900;
    }

    .muted-tag {
        color: #8d96a5;
        font-size: 11px;
        font-weight: 800;
    }

    @media (max-width: 1500px) {
        .metric-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 1200px) {
        .backtest-topbar,
        .backtest-hero,
        .desk-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .backtest-wrap {
            padding: 18px 12px 28px;
        }

        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-shell {
            height: 520px;
        }

        .tv-chart-shell {
            height: 520px;
        }

        .replay-toolbar {
            grid-template-columns: 1fr;
        }

        .replay-chart-topbar {
            align-items: flex-start;
            flex-direction: column;
            height: auto;
            padding: 8px;
        }

        .chart-shell {
            grid-template-rows: auto minmax(0, 1fr);
        }

        .chart-symbol-strip {
            align-items: flex-start;
            flex-direction: column;
            right: 76px;
        }
    }

    @media (max-width: 520px) {
        .metric-grid,
        .instrument-meta,
        .inspector-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $displayName = $currentUser->name ?: ($currentUser->username ?: 'Trader');
    $profitClass = $summary['net_profit'] >= 0 ? 'text-gain' : 'text-draw';
    $metricCards = [
        ['label' => 'Trades', 'value' => number_format($summary['total_trades']), 'note' => $summary['wins'] . 'W / ' . $summary['losses'] . 'L / ' . $summary['breakeven'] . 'BE', 'tone' => 'neutral'],
        ['label' => 'Net P/L', 'value' => number_format($summary['net_profit'], 2) . 'u', 'note' => 'Expectancy ' . number_format($summary['expectancy'], 2) . 'u', 'tone' => $summary['net_profit'] >= 0 ? '' : 'loss'],
        ['label' => 'Win Rate', 'value' => number_format($summary['win_rate'], 2) . '%', 'note' => 'Closed trades only', 'tone' => ''],
        ['label' => 'Profit Factor', 'value' => is_numeric($summary['profit_factor']) ? number_format($summary['profit_factor'], 2) : $summary['profit_factor'], 'note' => 'Gross profit / loss', 'tone' => 'blue'],
        ['label' => 'Max Drawdown', 'value' => number_format($summary['max_drawdown'], 2) . 'u', 'note' => 'Backtest equity dip', 'tone' => $summary['max_drawdown'] > 0 ? 'warn' : 'neutral'],
        ['label' => 'Average R', 'value' => is_numeric($summary['average_r']) ? number_format($summary['average_r'], 2) . 'R' : 'N/A', 'note' => 'Requires SL column', 'tone' => 'blue'],
        ['label' => 'TP Hit Rate', 'value' => number_format($summary['tp_rate'], 2) . '%', 'note' => $summary['tp_hits'] . ' TP hits', 'tone' => ''],
        ['label' => 'SL Hit Rate', 'value' => number_format($summary['sl_rate'], 2) . '%', 'note' => $summary['sl_hits'] . ' SL hits', 'tone' => 'loss'],
    ];
@endphp

<div class="page-content backtest-lab">
    <div class="container-fluid backtest-wrap">
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="backtest-topbar">
            <div class="backtest-hero">
                <div>
                    <div class="backtest-kicker">Prop Desk Backtest Lab</div>
                    <h3>{{ $pair }} Execution Replay</h3>
                    <p>{{ $displayName }} can validate XAUUSD setups against journal history or an uploaded backtest sheet with TP and SL price levels.</p>
                </div>
                <div class="instrument-card">
                    <span>Instrument</span>
                    <strong>{{ $pair }}</strong>
                    <div class="instrument-meta">
                        <div>
                            <span>Source</span>
                            <b>{{ $sourceLabel }}</b>
                        </div>
                        <div>
                            <span>Avg Hold</span>
                            <b>{{ $summary['average_hold_minutes'] }}m</b>
                        </div>
                        <div>
                            <span>Avg R:R</span>
                            <b>{{ is_numeric($summary['average_rr']) ? number_format($summary['average_rr'], 2) : 'N/A' }}</b>
                        </div>
                        <div>
                            <span>File</span>
                            <b>{{ $uploadName ? \Illuminate\Support\Str::limit($uploadName, 14) : 'Journal' }}</b>
                        </div>
                        <div>
                            <span>Candles</span>
                            <b>{{ $hasRealCandles ? number_format($replayCandles->count()) : 'Simulated' }}</b>
                        </div>
                    </div>
                </div>
            </div>

            <div class="upload-panel">
                <div class="source-pill"><i class="ri-file-chart-line"></i>{{ $hasRealCandles ? 'Real OHLC Replay' : ($uploadName ? 'Uploaded Backtest' : 'Journal Baseline') }}</div>
                <form method="POST" action="{{ route('trading.backtest.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="pair" value="{{ $pair }}">
                    @if($canViewAll && $selectedTraderId)
                        <input type="hidden" name="user_id" value="{{ $selectedTraderId }}">
                    @endif
                    <div class="mb-3">
                        <label for="backtest_file" class="form-label">Backtest Excel</label>
                        <input id="backtest_file" name="backtest_file" type="file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                    </div>
                    <div class="mb-3">
                        <label for="candle_file" class="form-label">XAUUSD Candle History</label>
                        <input id="candle_file" name="candle_file" type="file" class="form-control" accept=".xlsx,.xls,.csv,.txt">
                        <div class="small text-muted mt-1">Optional OHLC file: time, open, high, low, close, volume.</div>
                    </div>
                    <button type="submit" class="backtest-btn primary w-100"><i class="ri-upload-cloud-2-line"></i>Upload Backtest</button>
                </form>

                <div class="column-strip">
                    <span>entry_price</span>
                    <span>exit_price</span>
                    <span>stop_loss / sl</span>
                    <span>take_profit / tp</span>
                    <span>direction</span>
                    <span>result</span>
                    <span>OHLC candles</span>
                </div>

                <form method="GET" action="{{ route('trading.backtest.index') }}" class="journal-filter">
                    @if($canViewAll)
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Journal Trader</label>
                            <select id="user_id" name="user_id" class="form-select">
                                <option value="all" {{ empty($selectedTraderId) ? 'selected' : '' }}>All Traders</option>
                                @foreach($traders as $trader)
                                    <option value="{{ $trader->id }}" {{ (int) $selectedTraderId === (int) $trader->id ? 'selected' : '' }}>
                                        {{ $trader->name ?: $trader->username }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button type="submit" class="backtest-btn secondary w-100"><i class="ri-history-line"></i>Load Journal Baseline</button>
                </form>
            </div>
        </div>

        <div class="metric-grid">
            @foreach($metricCards as $metric)
                <div class="metric-tile {{ $metric['tone'] }}">
                    <div class="metric-label">{{ $metric['label'] }}</div>
                    <div class="metric-value {{ $metric['label'] === 'Net P/L' ? $profitClass : '' }}">{{ $metric['value'] }}</div>
                    <div class="metric-note">{{ $metric['note'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="tv-market-panel">
            <div class="panel-head">
                <div>
                    <h5>{{ $pair }} TradingView Market Chart</h5>
                    <span>Full TradingView chart for market context before reviewing journal replay</span>
                </div>
                <span>Default: OANDA:XAUUSD</span>
            </div>
            <div class="tv-chart-shell">
                <div class="tradingview-widget-container">
                    <div class="tradingview-widget-container__widget"></div>
                    <div class="tradingview-widget-copyright">
                        <a href="https://www.tradingview.com/symbols/XAUUSD/" rel="noopener nofollow" target="_blank">
                            <span>XAUUSD chart by TradingView</span>
                        </a>
                    </div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
                    {
                        "autosize": true,
                        "symbol": "OANDA:XAUUSD",
                        "interval": "60",
                        "timezone": "Asia/Singapore",
                        "theme": "dark",
                        "style": "1",
                        "locale": "en",
                        "backgroundColor": "rgba(13, 17, 23, 1)",
                        "gridColor": "rgba(42, 48, 58, 0.8)",
                        "withdateranges": true,
                        "hide_side_toolbar": false,
                        "allow_symbol_change": false,
                        "save_image": true,
                        "calendar": false,
                        "studies": [
                            "STD;Volume"
                        ],
                        "support_host": "https://www.tradingview.com"
                    }
                    </script>
                </div>
            </div>
        </div>

        <div class="desk-grid">
            <div class="desk-panel">
                <div class="panel-head">
                    <div>
                        <h5>Journal Replay Engine</h5>
                        <span>{{ $hasRealCandles ? 'Real uploaded XAUUSD candles with journal entry, TP and SL levels' : 'Simulated bars from journal entry, exit, TP and SL levels' }}</span>
                    </div>
                    <span id="replayHeaderCount">{{ $hasRealCandles ? number_format($replayCandles->count()) . ' candles' : number_format($summary['total_trades']) . ' trades' }}</span>
                </div>

                @if($chartTrades->isNotEmpty())
                    <div class="replay-toolbar">
                        <div class="replay-controls">
                            <button type="button" id="replayRestart" class="replay-icon-btn" title="Restart replay"><i class="ri-restart-line"></i></button>
                            <button type="button" id="replayStepBack" class="replay-icon-btn" title="Previous bar"><i class="ri-arrow-left-s-line"></i></button>
                            <button type="button" id="replayPlay" class="replay-play-btn" title="Play replay"><i class="ri-play-fill"></i><span>Play</span></button>
                            <button type="button" id="replayStepForward" class="replay-icon-btn" title="Next bar"><i class="ri-arrow-right-s-line"></i></button>
                            <select id="replaySpeed" class="replay-speed" title="Replay speed">
                                <option value="900">0.5x</option>
                                <option value="600" selected>1x</option>
                                <option value="320">2x</option>
                                <option value="160">4x</option>
                            </select>
                        </div>
                        <div class="replay-progress">
                            <div class="replay-readout">
                                <span id="replayStatus">Replay ready</span>
                                <strong id="replayTime">-</strong>
                            </div>
                            <input type="range" id="replayRange" class="replay-range" min="0" value="0">
                        </div>
                    </div>
                @endif

                <div class="chart-shell">
                    @if($chartTrades->isEmpty())
                        <div class="chart-empty">No XAUUSD backtest trades found.</div>
                    @else
                        <div class="replay-chart-topbar">
                            <div class="chart-timeframes" aria-label="Replay timeframe controls">
                                <button type="button">1m</button>
                                <button type="button">5m</button>
                                <button type="button">15m</button>
                                <button type="button" class="active">1h</button>
                                <button type="button">4h</button>
                                <button type="button">D</button>
                            </div>
                            <div class="chart-action-group">
                                <button type="button" class="chart-action-btn active" title="Candles"><i class="ri-bar-chart-box-line"></i></button>
                                <button type="button" class="chart-action-btn" title="Crosshair"><i class="ri-crosshair-2-line"></i></button>
                                <button type="button" class="chart-action-btn" title="Replay markers"><i class="ri-pulse-line"></i></button>
                                <span class="chart-action-divider"></span>
                                <button type="button" class="chart-action-btn active" title="Indicators"><i class="ri-line-chart-line"></i> Indicators</button>
                                <button type="button" class="chart-action-btn" title="Snapshot"><i class="ri-camera-line"></i></button>
                            </div>
                            <span class="chart-terminal-badge">HC Replay</span>
                        </div>

                        <div class="chart-stage">
                            <div class="chart-drawing-rail" aria-label="Replay drawing tools">
                                <button type="button" class="drawing-tool active" title="Cursor"><i class="ri-cursor-line"></i></button>
                                <button type="button" class="drawing-tool" title="Trend line"><i class="ri-slash-commands-2"></i></button>
                                <button type="button" class="drawing-tool" title="Horizontal level"><i class="ri-subtract-line"></i></button>
                                <button type="button" class="drawing-tool" title="Risk box"><i class="ri-rectangle-line"></i></button>
                                <button type="button" class="drawing-tool" title="Text"><i class="ri-text"></i></button>
                                <button type="button" class="drawing-tool" title="Measure"><i class="ri-ruler-line"></i></button>
                                <button type="button" class="drawing-tool" title="Zoom"><i class="ri-zoom-in-line"></i></button>
                            </div>
                            <div class="chart-canvas-wrap">
                                <div class="chart-symbol-strip">
                                    <div>
                                        <div class="chart-symbol-title">{{ $pair }} Replay <span>1h · Journal Engine</span></div>
                                        <div id="chartOhlcLine" class="chart-ohlc-line">O - H - L - C -</div>
                                    </div>
                                    <span id="chartModeBadge" class="chart-mode-chip">{{ $hasRealCandles ? 'Uploaded OHLC' : 'Simulated Bars' }}</span>
                                </div>
                                <canvas id="xauusdBacktestChart"></canvas>
                                <div class="chart-watermark">XAUUSD</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="legend-row">
                    <span><i class="legend-dot" style="background:#f8fafc;"></i>{{ $hasRealCandles ? 'Uploaded OHLC' : 'Simulated Bars' }}</span>
                    <span><i class="legend-dot" style="background:#60a5fa;"></i>Entry</span>
                    <span><i class="legend-dot" style="background:#2dd4bf;"></i>Take Profit</span>
                    <span><i class="legend-dot" style="background:#fb7185;"></i>Stop Loss</span>
                    <span><i class="legend-dot" style="background:#f59e0b;"></i>Exit</span>
                </div>
            </div>

            <div class="desk-panel">
                <div class="panel-head">
                    <h5>Execution Inspector</h5>
                    <span id="inspectorCounter">Selected trade</span>
                </div>
                <div id="tradeInspector" class="trade-inspector">
                    <div class="chart-empty">Select a trade from the chart or table.</div>
                </div>
            </div>
        </div>

        <div class="trade-table-panel">
            <div class="panel-head">
                <div>
                    <h5>Backtest Trade Tape</h5>
                    <span>{{ $sourceLabel }} records used in this replay</span>
                </div>
                <a href="{{ route('download.trades.template') }}" class="backtest-btn secondary"><i class="ri-download-2-line"></i>Journal Template</a>
            </div>

            <div class="table-responsive">
                <table class="table backtest-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Open Time</th>
                            <th>Side</th>
                            <th>Entry</th>
                            <th>TP</th>
                            <th>SL</th>
                            <th>Exit</th>
                            <th>Pips</th>
                            <th>R</th>
                            <th>P/L</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backtestTrades as $trade)
                            <tr class="backtest-trade-row" data-index="{{ $loop->index }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ optional($trade['opened_at'])->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                <td>
                                    <span class="outcome-badge {{ $trade['direction_label'] === 'Buy' ? 'win' : 'loss' }}">{{ $trade['direction_label'] }}</span>
                                </td>
                                <td class="price-tag">{{ number_format($trade['entry_price'], 2) }}</td>
                                <td>
                                    @if(is_numeric($trade['take_profit']))
                                        <span class="text-gain">{{ number_format($trade['take_profit'], 2) }}</span>
                                        @if($trade['tp_inferred']) <div class="muted-tag">Inferred</div> @endif
                                    @else
                                        <span class="muted-tag">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if(is_numeric($trade['stop_loss']))
                                        <span class="text-draw">{{ number_format($trade['stop_loss'], 2) }}</span>
                                        @if($trade['sl_inferred']) <div class="muted-tag">Inferred</div> @endif
                                    @else
                                        <span class="muted-tag">N/A</span>
                                    @endif
                                </td>
                                <td class="price-tag">{{ number_format($trade['exit_price'], 2) }}</td>
                                <td>{{ number_format($trade['pips'], 2) }}</td>
                                <td>{{ is_numeric($trade['r_multiple']) ? number_format($trade['r_multiple'], 2) . 'R' : 'N/A' }}</td>
                                <td class="{{ $trade['profit_loss'] >= 0 ? 'text-gain' : 'text-draw' }}">{{ number_format($trade['profit_loss'], 2) }}u</td>
                                <td>
                                    <span class="outcome-badge {{ $trade['result'] === 1 ? 'win' : ($trade['result'] === 2 ? 'loss' : 'flat') }}">{{ $trade['result_label'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No backtest trades available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var trades = @json($chartTrades->values());
    var realCandles = @json($replayCandles->values());
    var hasRealCandles = realCandles.length > 0;
    var canvas = document.getElementById('xauusdBacktestChart');
    var inspector = document.getElementById('tradeInspector');
    var counter = document.getElementById('inspectorCounter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.backtest-trade-row'));
    var playButton = document.getElementById('replayPlay');
    var restartButton = document.getElementById('replayRestart');
    var stepBackButton = document.getElementById('replayStepBack');
    var stepForwardButton = document.getElementById('replayStepForward');
    var speedSelect = document.getElementById('replaySpeed');
    var replayRange = document.getElementById('replayRange');
    var replayStatus = document.getElementById('replayStatus');
    var replayTime = document.getElementById('replayTime');
    var chartOhlcLine = document.getElementById('chartOhlcLine');
    var chartModeBadge = document.getElementById('chartModeBadge');
    var bars = hasRealCandles ? buildRealReplayBars(realCandles, trades) : buildReplayBars(trades);
    var replayStartCursor = trades.length > 0 && trades[0].firstBar !== undefined ? trades[0].firstBar : 0;
    var replayCursor = bars.length > 0 ? replayStartCursor : 0;
    var selectedIndex = trades.length > 0 ? 0 : 0;
    var replayTimer = null;
    var isPlaying = false;
    var lastVisibleStart = 0;
    var lastVisibleLength = 0;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function money(value) {
        var number = Number(value || 0);
        return number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function valueOrDash(value) {
        return value === null || value === undefined ? 'N/A' : money(value);
    }

    function numeric(value, fallback) {
        var parsed = Number(value);
        return isNaN(parsed) ? fallback : parsed;
    }

    function buildRealReplayBars(candles, items) {
        var normalized = candles.map(function (candle, index) {
            return {
                globalIndex: index,
                tradeIndex: null,
                tradeNumber: '',
                localIndex: index + 1,
                localTotal: candles.length,
                open: numeric(candle.open, 0),
                high: numeric(candle.high, 0),
                low: numeric(candle.low, 0),
                close: numeric(candle.close, 0),
                volume: numeric(candle.volume, 0),
                timestamp: numeric(candle.timestamp, index),
                time: candle.time || ('Bar ' + (index + 1)),
                event: 'market',
                direction: '',
                result: ''
            };
        });

        if (normalized.length === 0) {
            return normalized;
        }

        items.forEach(function (trade, tradeIndex) {
            var openTimestamp = numeric(trade.opened_timestamp, null);
            var closeTimestamp = numeric(trade.closed_timestamp, openTimestamp);
            var firstBar = openTimestamp === null ? Math.min(tradeIndex * 12, normalized.length - 1) : findCandleIndex(normalized, openTimestamp);
            var lastBar = closeTimestamp === null ? firstBar : findCandleIndex(normalized, closeTimestamp);

            if (lastBar < firstBar) {
                lastBar = firstBar;
            }

            trade.firstBar = firstBar;
            trade.lastBar = lastBar;
        });

        normalized.forEach(function (bar, index) {
            var activeIndex = null;

            items.forEach(function (trade, tradeIndex) {
                if (index >= trade.firstBar && index <= trade.lastBar) {
                    activeIndex = tradeIndex;
                }
            });

            if (activeIndex !== null) {
                var activeTrade = items[activeIndex];
                bar.tradeIndex = activeIndex;
                bar.tradeNumber = activeIndex + 1;
                bar.localIndex = index - activeTrade.firstBar + 1;
                bar.localTotal = Math.max(activeTrade.lastBar - activeTrade.firstBar + 1, 1);
                bar.direction = activeTrade.direction;
                bar.result = activeTrade.result;
            }
        });

        return normalized;
    }

    function findCandleIndex(candles, timestamp) {
        var nearest = 0;
        var nearestDistance = Infinity;

        candles.forEach(function (candle, index) {
            var distance = Math.abs(Number(candle.timestamp) - Number(timestamp));

            if (distance < nearestDistance) {
                nearest = index;
                nearestDistance = distance;
            }
        });

        return nearest;
    }

    function buildReplayBars(items) {
        var generatedBars = [];

        items.forEach(function (trade, tradeIndex) {
            var entry = numeric(trade.entry, 0);
            var exit = numeric(trade.exit, entry);
            var tp = trade.tp === null || trade.tp === undefined ? null : numeric(trade.tp, null);
            var sl = trade.sl === null || trade.sl === undefined ? null : numeric(trade.sl, null);
            var result = String(trade.result || '').toLowerCase();
            var levels = [entry, exit];

            if (tp !== null) {
                levels.push(tp);
            }

            if (sl !== null) {
                levels.push(sl);
            }

            var levelHigh = Math.max.apply(null, levels);
            var levelLow = Math.min.apply(null, levels);
            var levelRange = Math.max(levelHigh - levelLow, 1);
            var firstBar = generatedBars.length;
            var path = replayPath(entry, exit, tp, sl, result, levelRange);

            for (var i = 1; i < path.length; i++) {
                var open = path[i - 1].price;
                var close = path[i].price;
                var spread = Math.max(Math.abs(close - open) * .28, levelRange * .04, .18);
                var high = Math.max(open, close) + spread;
                var low = Math.min(open, close) - spread;

                if (path[i].event === 'tp' && tp !== null) {
                    high = Math.max(high, tp);
                    low = Math.min(low, tp);
                }

                if (path[i].event === 'sl' && sl !== null) {
                    high = Math.max(high, sl);
                    low = Math.min(low, sl);
                }

                generatedBars.push({
                    globalIndex: generatedBars.length,
                    tradeIndex: tradeIndex,
                    tradeNumber: tradeIndex + 1,
                    localIndex: i,
                    localTotal: path.length - 1,
                    open: roundPrice(open),
                    high: roundPrice(high),
                    low: roundPrice(low),
                    close: roundPrice(close),
                    event: path[i].event,
                    time: trade.time || ('Trade ' + (tradeIndex + 1)),
                    direction: trade.direction,
                    result: trade.result
                });
            }

            trade.firstBar = firstBar;
            trade.lastBar = generatedBars.length - 1;
        });

        return generatedBars;
    }

    function replayPath(entry, exit, tp, sl, result, levelRange) {
        var noise = Math.max(levelRange * .16, .3);
        var favorable = tp !== null ? entry + ((tp - entry) * .55) : entry + ((exit - entry) * .55);
        var adverse = sl !== null ? entry + ((sl - entry) * .38) : entry - ((exit - entry) * .24);

        if (result.indexOf('loss') !== -1) {
            favorable = tp !== null ? entry + ((tp - entry) * .28) : entry - ((exit - entry) * .18);
            adverse = sl !== null ? entry + ((sl - entry) * .72) : entry + ((exit - entry) * .72);

            return [
                { price: entry, event: 'entry' },
                { price: favorable, event: 'probe' },
                { price: adverse, event: 'drawdown' },
                { price: exit, event: sl !== null ? 'sl' : 'exit' }
            ];
        }

        if (result.indexOf('break') !== -1 || result.indexOf('even') !== -1 || result.indexOf('flat') !== -1) {
            return [
                { price: entry, event: 'entry' },
                { price: entry + noise, event: 'probe' },
                { price: entry - noise, event: 'drawdown' },
                { price: exit, event: 'exit' }
            ];
        }

        return [
            { price: entry, event: 'entry' },
            { price: adverse, event: 'drawdown' },
            { price: favorable, event: 'push' },
            { price: exit, event: tp !== null ? 'tp' : 'exit' }
        ];
    }

    function roundPrice(value) {
        return Math.round(Number(value) * 100) / 100;
    }

    function activeTrade() {
        if (bars.length === 0) {
            return null;
        }

        if (bars[replayCursor].tradeIndex !== null && bars[replayCursor].tradeIndex !== undefined) {
            selectedIndex = bars[replayCursor].tradeIndex;
        }

        return trades[selectedIndex] || trades[0] || null;
    }

    function stopReplay() {
        isPlaying = false;

        if (replayTimer) {
            clearInterval(replayTimer);
            replayTimer = null;
        }

        updatePlayButton();
    }

    function startReplay() {
        if (bars.length === 0) {
            return;
        }

        if (replayCursor >= bars.length - 1) {
            replayCursor = replayStartCursor;
        }

        isPlaying = true;
        updatePlayButton();
        replayTimer = setInterval(function () {
            if (replayCursor >= bars.length - 1) {
                stopReplay();
                updateReplay();
                return;
            }

            replayCursor += 1;
            updateReplay();
        }, Number(speedSelect ? speedSelect.value : 600));
    }

    function updatePlayButton() {
        if (!playButton) {
            return;
        }

        playButton.innerHTML = isPlaying
            ? '<i class="ri-pause-fill"></i><span>Pause</span>'
            : '<i class="ri-play-fill"></i><span>Play</span>';
    }

    function setCursor(nextCursor) {
        replayCursor = Math.max(0, Math.min(bars.length - 1, nextCursor));
        updateReplay();
    }

    function drawChart() {
        if (!canvas || bars.length === 0) {
            return;
        }

        var parent = canvas.parentElement;
        var width = Math.max(parent.clientWidth, 320);
        var height = Math.max(parent.clientHeight, 320);
        var ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);

        var context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        var padding = { left: 64, right: 104, top: 58, bottom: 54 };
        var plotWidth = width - padding.left - padding.right;
        var plotHeight = height - padding.top - padding.bottom;
        var volumePaneHeight = Math.min(118, Math.max(72, plotHeight * .22));
        var paneGap = 18;
        var pricePlotHeight = Math.max(180, plotHeight - volumePaneHeight - paneGap);
        var volumeTop = padding.top + pricePlotHeight + paneGap;
        var volumeBase = volumeTop + volumePaneHeight;
        var selectedTrade = activeTrade();
        var visibleWindow = 74;
        var visibleStart = Math.max(0, replayCursor - visibleWindow + 1);
        var visibleBars = bars.slice(visibleStart, replayCursor + 1);
        var prices = [];
        lastVisibleStart = visibleStart;
        lastVisibleLength = visibleBars.length;

        visibleBars.forEach(function (bar) {
            prices.push(bar.high, bar.low);
        });

        if (selectedTrade) {
            ['entry', 'exit', 'tp', 'sl'].forEach(function (key) {
                if (selectedTrade[key] !== null && selectedTrade[key] !== undefined) {
                    prices.push(Number(selectedTrade[key]));
                }
            });
        }

        var minPrice = Math.min.apply(null, prices);
        var maxPrice = Math.max.apply(null, prices);
        var range = Math.max(maxPrice - minPrice, 1);
        minPrice -= range * .14;
        maxPrice += range * .14;
        range = maxPrice - minPrice;

        function y(price) {
            return padding.top + ((maxPrice - Number(price)) / range) * pricePlotHeight;
        }

        function x(globalIndex) {
            var visibleIndex = globalIndex - visibleStart;

            if (visibleBars.length === 1) {
                return padding.left + plotWidth / 2;
            }

            return padding.left + (plotWidth / Math.max(visibleBars.length - 1, 1)) * visibleIndex;
        }

        context.fillStyle = '#0d1117';
        context.fillRect(0, 0, width, height);
        context.strokeStyle = 'rgba(148, 163, 184, .15)';
        context.lineWidth = 1;
        context.font = '11px Arial';
        context.textBaseline = 'middle';

        for (var grid = 0; grid <= 5; grid++) {
            var gridY = padding.top + (pricePlotHeight / 5) * grid;
            var price = maxPrice - (range / 5) * grid;
            context.beginPath();
            context.moveTo(padding.left, gridY);
            context.lineTo(width - padding.right, gridY);
            context.stroke();
            context.fillStyle = '#8d96a5';
            context.fillText(price.toFixed(2), width - padding.right + 12, gridY);
        }

        context.strokeStyle = 'rgba(148, 163, 184, .18)';
        context.beginPath();
        context.moveTo(padding.left, volumeTop - 8);
        context.lineTo(width - padding.right, volumeTop - 8);
        context.stroke();

        context.fillStyle = '#8d96a5';
        context.font = 'bold 10px Arial';
        context.textAlign = 'left';
        context.fillText('VOL', padding.left + 4, volumeTop + 10);

        if (selectedTrade) {
            var levelStart = x(Math.max(selectedTrade.firstBar || 0, visibleStart));
            var rightEdge = width - padding.right;

            drawZone(context, y, levelStart, rightEdge, selectedTrade.entry, selectedTrade.tp, 'rgba(45, 212, 191, .07)');
            drawZone(context, y, levelStart, rightEdge, selectedTrade.entry, selectedTrade.sl, 'rgba(251, 113, 133, .07)');
        }

        var candleWidth = Math.max(7, Math.min(22, (plotWidth / Math.max(visibleBars.length, 1)) * .46));
        var maxVolume = visibleBars.reduce(function (max, bar) {
            var volume = Number(bar.volume || Math.abs(Number(bar.close) - Number(bar.open)) * 1000 || 0);
            return Math.max(max, volume);
        }, 1);

        visibleBars.forEach(function (bar) {
            var centerX = x(bar.globalIndex);
            var isUp = Number(bar.close) >= Number(bar.open);
            var volume = Number(bar.volume || Math.abs(Number(bar.close) - Number(bar.open)) * 1000 || 0);
            var volumeHeight = Math.max((volume / maxVolume) * (volumePaneHeight - 12), 2);

            context.globalAlpha = .34;
            context.fillStyle = isUp ? '#2dd4bf' : '#fb7185';
            context.fillRect(centerX - candleWidth / 2, volumeBase - volumeHeight, candleWidth, volumeHeight);
            context.globalAlpha = 1;
        });

        visibleBars.forEach(function (bar) {
            var centerX = x(bar.globalIndex);
            var highY = y(bar.high);
            var lowY = y(bar.low);
            var openY = y(bar.open);
            var closeY = y(bar.close);
            var isSelected = bar.tradeIndex === selectedIndex;
            var isUp = Number(bar.close) >= Number(bar.open);
            var profitColor = isUp ? '#2dd4bf' : '#fb7185';
            var bodyTop = Math.min(openY, closeY);
            var bodyHeight = Math.max(Math.abs(closeY - openY), 4);

            if (bar.globalIndex === replayCursor) {
                context.fillStyle = 'rgba(96, 165, 250, .09)';
                context.fillRect(centerX - candleWidth * 1.25, padding.top, candleWidth * 2.5, pricePlotHeight + paneGap + volumePaneHeight);
            }

            context.globalAlpha = isSelected ? 1 : .46;
            context.strokeStyle = isSelected ? '#f8fafc' : 'rgba(209, 213, 219, .68)';
            context.beginPath();
            context.moveTo(centerX, highY);
            context.lineTo(centerX, lowY);
            context.stroke();

            context.fillStyle = profitColor;
            context.fillRect(centerX - candleWidth / 2, bodyTop, candleWidth, bodyHeight);

            context.strokeStyle = isSelected ? '#ffffff' : profitColor;
            context.strokeRect(centerX - candleWidth / 2, bodyTop, candleWidth, bodyHeight);
            context.globalAlpha = 1;
        });

        if (selectedTrade) {
            var fromX = x(Math.max(selectedTrade.firstBar || 0, visibleStart));
            drawLevel(context, y, fromX, width - padding.right + 2, selectedTrade.entry, 'ENTRY', '#60a5fa', false);
            drawLevel(context, y, fromX, width - padding.right + 2, selectedTrade.tp, 'TP', '#2dd4bf', true);
            drawLevel(context, y, fromX, width - padding.right + 2, selectedTrade.sl, 'SL', '#fb7185', true);

            if (replayCursor >= selectedTrade.lastBar) {
                drawLevel(context, y, fromX, width - padding.right + 2, selectedTrade.exit, 'EXIT', '#f59e0b', false);
            }

            drawTradeTag(context, x(Math.max(selectedTrade.firstBar || 0, visibleStart)), y(selectedTrade.entry), selectedTrade.direction);
        }

        var cursorBar = bars[replayCursor];
        var cursorX = x(cursorBar.globalIndex);
        context.strokeStyle = 'rgba(248, 250, 252, .55)';
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(cursorX, padding.top);
        context.lineTo(cursorX, volumeBase);
        context.stroke();

        context.fillStyle = '#8d96a5';
        context.font = '11px Arial';
        context.textAlign = 'center';

        visibleBars.forEach(function (bar) {
            if (visibleBars.length > 18 && bar.globalIndex % Math.ceil(visibleBars.length / 8) !== 0 && bar.globalIndex !== replayCursor) {
                return;
            }

            var bottomLabel = bar.tradeNumber ? (bar.tradeNumber + '.' + bar.localIndex) : bar.time;
            context.fillText(bottomLabel, x(bar.globalIndex), height - 26);
        });
    }

    function drawZone(context, y, startX, endX, entry, target, color) {
        if (target === null || target === undefined) {
            return;
        }

        var top = Math.min(y(entry), y(target));
        var height = Math.max(Math.abs(y(entry) - y(target)), 2);
        context.fillStyle = color;
        context.fillRect(startX, top, endX - startX, height);
    }

    function drawLevel(context, y, startX, endX, price, label, color, dashed) {
        if (price === null || price === undefined) {
            return;
        }

        var levelY = y(price);
        context.save();
        context.setLineDash(dashed ? [8, 7] : []);
        context.strokeStyle = color;
        context.lineWidth = 1.5;
        context.beginPath();
        context.moveTo(startX, levelY);
        context.lineTo(endX, levelY);
        context.stroke();
        context.restore();

        context.fillStyle = color;
        context.font = 'bold 11px Arial';
        context.textAlign = 'left';
        context.textBaseline = 'middle';
        context.fillText(label + ' ' + Number(price).toFixed(2), endX + 10, levelY);
    }

    function drawTradeTag(context, x, y, direction) {
        var label = String(direction).toUpperCase();
        var color = label === 'SELL' ? '#fb7185' : '#2dd4bf';
        var width = label === 'SELL' ? 44 : 40;
        context.fillStyle = color;
        context.fillRect(x - width / 2, y - 28, width, 18);
        context.fillStyle = '#0f131a';
        context.font = 'bold 10px Arial';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(label, x, y - 19);
    }

    function updateInspector() {
        if (!inspector || trades.length === 0) {
            return;
        }

        var trade = activeTrade();
        var bar = bars[replayCursor];
        var outcomeClass = Number(trade.profit_loss) > 0 ? 'win' : (Number(trade.profit_loss) < 0 ? 'loss' : 'flat');
        var profitClass = Number(trade.profit_loss) >= 0 ? 'text-gain' : 'text-draw';
        var rValue = trade.r_multiple === null || trade.r_multiple === undefined ? 'N/A' : money(trade.r_multiple) + 'R';
        var rrValue = trade.risk_reward === null || trade.risk_reward === undefined ? 'N/A' : money(trade.risk_reward);

        if (counter) {
            counter.textContent = 'Trade ' + (selectedIndex + 1) + ' of ' + trades.length;
        }

        inspector.innerHTML = [
            '<div class="inspector-price">',
                '<span>Replay Position</span>',
                '<strong>' + escapeHtml(trade.direction) + ' ' + escapeHtml(trade.time) + '</strong>',
                '<div class="mt-2"><span class="outcome-badge ' + outcomeClass + '">' + escapeHtml(trade.result) + '</span></div>',
            '</div>',
            '<div class="inspector-grid">',
                '<div class="inspector-price"><span>Current Candle</span><strong>' + money(bar.close) + '</strong></div>',
                '<div class="inspector-price"><span>Bar Progress</span><strong>' + bar.localIndex + ' / ' + bar.localTotal + '</strong></div>',
                '<div class="inspector-price"><span>Entry</span><strong>' + valueOrDash(trade.entry) + '</strong></div>',
                '<div class="inspector-price"><span>Exit</span><strong>' + valueOrDash(trade.exit) + '</strong></div>',
            '</div>',
            '<div class="inspector-grid">',
                '<div class="inspector-price"><span>Take Profit</span><strong class="text-gain">' + valueOrDash(trade.tp) + '</strong></div>',
                '<div class="inspector-price"><span>Stop Loss</span><strong class="text-draw">' + valueOrDash(trade.sl) + '</strong></div>',
                '<div class="inspector-price"><span>Profit / Loss</span><strong class="' + profitClass + '">' + money(trade.profit_loss) + 'u</strong></div>',
                '<div class="inspector-price"><span>Pips</span><strong>' + money(trade.pips) + '</strong></div>',
                '<div class="inspector-price"><span>R Multiple</span><strong>' + rValue + '</strong></div>',
                '<div class="inspector-price"><span>Risk Reward</span><strong>' + rrValue + '</strong></div>',
            '</div>'
        ].join('');

        rows.forEach(function (row) {
            row.classList.toggle('active', Number(row.dataset.index) === selectedIndex);
        });

        drawChart();
    }

    function updateReplay() {
        if (bars.length === 0) {
            return;
        }

        var trade = activeTrade();
        var bar = bars[replayCursor];

        if (replayRange) {
            replayRange.value = replayCursor;
        }

        if (replayStatus) {
            replayStatus.textContent = isPlaying ? 'Replaying' : 'Replay paused';
        }

        if (replayTime) {
            replayTime.textContent = trade.time + ' | Bar ' + (replayCursor + 1) + ' / ' + bars.length;
        }

        if (chartOhlcLine) {
            var change = Number(bar.close) - Number(bar.open);
            var changePercent = Number(bar.open) !== 0 ? (change / Number(bar.open)) * 100 : 0;
            chartOhlcLine.classList.toggle('negative', change < 0);
            chartOhlcLine.textContent = 'O ' + money(bar.open)
                + '  H ' + money(bar.high)
                + '  L ' + money(bar.low)
                + '  C ' + money(bar.close)
                + '  ' + (change >= 0 ? '+' : '') + money(change)
                + ' (' + (changePercent >= 0 ? '+' : '') + changePercent.toFixed(2) + '%)'
                + '  Vol ' + Math.round(Number(bar.volume || 0)).toLocaleString();
        }

        if (chartModeBadge) {
            chartModeBadge.textContent = hasRealCandles ? 'Uploaded OHLC' : 'Simulated Bars';
        }

        updateInspector();
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            stopReplay();
            selectedIndex = Number(row.dataset.index);
            setCursor(trades[selectedIndex].firstBar || 0);
        });
    });

    if (canvas) {
        canvas.addEventListener('click', function (event) {
            if (bars.length === 0) {
                return;
            }

            stopReplay();
            var rect = canvas.getBoundingClientRect();
            var clickX = event.clientX - rect.left;
            var plotLeft = 64;
            var plotRight = rect.width - 104;
            var plotWidth = plotRight - plotLeft;
            var nearest = 0;
            var nearestDistance = Infinity;
            var visibleEnd = lastVisibleStart + lastVisibleLength - 1;

            for (var index = lastVisibleStart; index <= visibleEnd; index++) {
                var visibleIndex = index - lastVisibleStart;
                var tradeX = lastVisibleLength === 1 ? plotLeft + plotWidth / 2 : plotLeft + (plotWidth / Math.max(lastVisibleLength - 1, 1)) * visibleIndex;
                var distance = Math.abs(tradeX - clickX);

                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearest = index;
                }
            }

            setCursor(nearest);
        });
    }

    if (replayRange) {
        replayRange.max = Math.max(bars.length - 1, 0);
        replayRange.addEventListener('input', function () {
            stopReplay();
            setCursor(Number(replayRange.value));
        });
    }

    if (playButton) {
        playButton.addEventListener('click', function () {
            if (isPlaying) {
                stopReplay();
                updateReplay();
            } else {
                startReplay();
            }
        });
    }

    if (restartButton) {
        restartButton.addEventListener('click', function () {
            stopReplay();
            setCursor(replayStartCursor);
        });
    }

    if (stepBackButton) {
        stepBackButton.addEventListener('click', function () {
            stopReplay();
            setCursor(replayCursor - 1);
        });
    }

    if (stepForwardButton) {
        stepForwardButton.addEventListener('click', function () {
            stopReplay();
            setCursor(replayCursor + 1);
        });
    }

    if (speedSelect) {
        speedSelect.addEventListener('change', function () {
            if (isPlaying) {
                stopReplay();
                startReplay();
            }
        });
    }

    window.addEventListener('resize', drawChart);
    updatePlayButton();
    updateReplay();
});
</script>
@endsection
