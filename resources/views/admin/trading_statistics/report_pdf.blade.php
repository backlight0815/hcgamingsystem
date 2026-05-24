@php
    $journalTime = app(\App\Services\TradingJournalTimeService::class);
    $traderName = $selectedTrader
        ? ($selectedTrader->name ?: ($selectedTrader->username ?: 'Selected Trader'))
        : ($canViewGlobal ? 'All Traders' : ($currentUser->name ?: ($currentUser->username ?: 'Trader')));
    $monthLabel = $selectedMonth
        ? \Carbon\Carbon::create(2000, (int) $selectedMonth, 1)->format('F')
        : 'All Months';
    $yearLabel = $selectedYear ?: 'All Years';
    $periodLabel = trim($monthLabel . ' ' . $yearLabel);
    $timeLabel = $journalTime->shortLabel($selectedTimeView, $selectedTimeViewOffset);
    $money = fn ($value) => number_format((float) $value, 2) . 'u';
    $percent = fn ($value) => number_format((float) $value, 2) . '%';
    $toneClass = fn ($tone) => 'tone-' . preg_replace('/[^a-z0-9_-]+/', '', strtolower((string) $tone));
    $dailyMax = max(1, (float) $dailyStats->max(fn ($day) => abs((float) ($day['cumulative_profit_loss'] ?? 0))));
    $hedgeExamples = collect($hedgingProfile['examples'] ?? [])->take(8);
    $revengeTradeMap = collect(data_get($traderStyleProfile, 'revenge.trade_map', []));
    $gamblingTradeMap = collect(data_get($traderStyleProfile, 'gambling.trade_map', []));
    $behaviorScorePenalty = data_get($scoreEvaluationProfile, 'behavior_score_penalty', []);
    $behaviorWeeklyMetrics = collect(data_get($behaviorWeeklyProfile ?? [], 'metrics', []));
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Advanced Trading Journal Report</title>
    <style>
        @page {
            margin: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.38;
            margin: 0;
        }

        .header {
            background: #111827;
            color: #ffffff;
            padding: 18px 20px;
        }

        .kicker {
            color: #5eead4;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            font-size: 22px;
            line-height: 1.15;
            margin: 6px 0 7px;
        }

        h2 {
            color: #0f172a;
            font-size: 13px;
            margin: 15px 0 7px;
        }

        h3 {
            color: #0f172a;
            font-size: 11px;
            margin: 0 0 6px;
        }

        .muted {
            color: #64748b;
        }

        .header .muted {
            color: #cbd5e1;
        }

        .content {
            padding: 16px 18px 18px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d9e3ef;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            color: #475569;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }

        .summary-table td {
            width: 16.66%;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-top: 3px;
        }

        .hero-grid td {
            width: 25%;
        }

        .score-card {
            background: #ecfeff;
            border: 1px solid #99f6e4;
            padding: 10px;
        }

        .score-number {
            color: #0f766e;
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
        }

        .badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            margin-top: 5px;
            padding: 4px 7px;
            text-transform: uppercase;
        }

        .tone-success {
            background: #dcfce7;
            color: #166534;
        }

        .tone-danger {
            background: #ffe4e6;
            color: #be123c;
        }

        .tone-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .tone-primary,
        .tone-info,
        .tone-indigo {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .tone-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .profit {
            color: #0f766e;
            font-weight: bold;
        }

        .loss {
            color: #e11d48;
            font-weight: bold;
        }

        .panel {
            page-break-inside: avoid;
        }

        .section-table {
            margin-bottom: 8px;
        }

        .two-col td {
            width: 50%;
        }

        .three-col td {
            width: 33.33%;
        }

        .bar-wrap {
            background: #e2e8f0;
            height: 7px;
            width: 100%;
        }

        .bar {
            background: #0f766e;
            height: 7px;
        }

        .bar.loss-bar {
            background: #e11d48;
        }

        .meaning {
            background: #f8fafc;
            border: 1px solid #d9e3ef;
            margin: 8px 0 12px;
            padding: 9px;
        }

        .small {
            font-size: 8px;
        }

        .footer {
            color: #64748b;
            font-size: 8px;
            margin-top: 12px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="kicker">HC Gaming Studio</div>
        <h1>Advanced Trading Journal Report</h1>
        <div class="muted">
            Trader: {{ $traderName }} |
            Period: {{ $periodLabel }} |
            Source: {{ ucfirst($selectedSource) }} |
            Time: {{ $timeLabel }} |
            Generated: {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="content">
        <table class="hero-grid">
            <tr>
                <td class="score-card">
                    <span class="label">Trading Score</span>
                    <span class="score-number">{{ number_format((float) $scoreEvaluationProfile['score'], 2) }}</span>
                    <span class="badge tone-primary">{{ $scoreEvaluationProfile['rating'] }} / 100 max</span>
                    <span class="muted">Behaviour penalty -{{ number_format((float) data_get($behaviorScorePenalty, 'points', 0), 2) }} pts</span>
                </td>
                <td>
                    <span class="label">Performance Status</span>
                    <span class="value">{{ $performanceProfile['status'] }}</span>
                    <span class="muted">{{ $performanceProfile['headline'] }}</span>
                </td>
                <td>
                    <span class="label">Trader Style</span>
                    <span class="value">{{ $traderStyleProfile['style_label'] }}</span>
                    <span class="muted">{{ $traderStyleProfile['risk_level'] }} | {{ number_format((float) $traderStyleProfile['risk_score'], 2) }}/100</span>
                </td>
                <td>
                    <span class="label">Net P/L</span>
                    <span class="value {{ $summary['net_profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($summary['net_profit_loss']) }}</span>
                    <span class="muted">Growth {{ $percent($summary['growth_percent']) }}</span>
                </td>
            </tr>
        </table>

        <div class="meaning">
            <strong>Executive insight:</strong>
            {{ $scoreEvaluationProfile['meaning'] }}
            {{ $performanceProfile['detail'] }}
            {{ $traderStyleProfile['summary'] }}
        </div>

        <h2>Trader Resume Summary</h2>
        <div class="meaning">
            <strong>{{ $traderResumeProfile['verdict'] }}:</strong>
            {{ $traderResumeProfile['paragraph'] }}
        </div>
        <table class="summary-table section-table">
            <tr>
                <td><span class="label">Technical Skill</span><span class="value">{{ $traderResumeProfile['technical_skill'] }}</span><span class="muted">Score {{ number_format((float) $scoreEvaluationProfile['score'], 2) }}/100</span></td>
                <td><span class="label">Risk Profile</span><span class="value">{{ $traderResumeProfile['risk_profile'] }}</span><span class="muted">Behavior {{ number_format((float) $traderStyleProfile['risk_score'], 2) }}/100</span></td>
                <td colspan="4"><span class="label">Administration Action</span><span class="value">{{ $traderResumeProfile['administration_action'] }}</span><span class="muted">Verdict is generated from score, behavior, risk, consistency, sizing, timing, and profitability.</span></td>
            </tr>
        </table>
        <table class="three-col section-table">
            <tr>
                <td class="panel">
                    <h3>Strengths</h3>
                    @foreach($traderResumeProfile['strengths'] as $item)
                        <div class="small">&bull; {{ $item }}</div>
                    @endforeach
                </td>
                <td class="panel">
                    <h3>Risks</h3>
                    @foreach($traderResumeProfile['risks'] as $item)
                        <div class="small">&bull; {{ $item }}</div>
                    @endforeach
                </td>
                <td class="panel">
                    <h3>Coaching Focus</h3>
                    @foreach($traderResumeProfile['coaching_focus'] as $item)
                        <div class="small">&bull; {{ $item }}</div>
                    @endforeach
                </td>
            </tr>
        </table>

        <h2>Core Metrics</h2>
        <table class="summary-table section-table">
            <tr>
                <td><span class="label">Trades</span><span class="value">{{ number_format($summary['total_trades']) }}</span><span class="muted">{{ $summary['winning_trades'] }} wins / {{ $summary['losing_trades'] }} losses</span></td>
                <td><span class="label">Win Rate</span><span class="value">{{ $percent($summary['win_rate']) }}</span><span class="muted">Daily {{ $percent($summary['daily_win_rate']) }}</span></td>
                <td><span class="label">Risk Reward</span><span class="value">{{ is_numeric($summary['profit_factor']) ? number_format((float) $summary['profit_factor'], 2) : $summary['profit_factor'] }}</span><span class="muted">Payoff {{ is_numeric($summary['payoff_ratio']) ? number_format((float) $summary['payoff_ratio'], 2) : $summary['payoff_ratio'] }}</span></td>
                <td><span class="label">Expectancy</span><span class="value">{{ $money($summary['expectancy']) }}</span><span class="muted">Avg win {{ $money($summary['average_win']) }}</span></td>
                <td><span class="label">Pips</span><span class="value">{{ number_format($summary['total_pips'], 2) }}</span><span class="muted">Avg {{ number_format($summary['average_pips'], 2) }}</span></td>
                <td><span class="label">Holding</span><span class="value">{{ $summary['average_holding_label'] }}</span><span class="muted">Median {{ $summary['median_holding_label'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Current Balance</span><span class="value">{{ $money($summary['current_balance']) }}</span><span class="muted">Deposits {{ $money($capitalSummary['total_deposits']) }}</span></td>
                <td><span class="label">Best Day</span><span class="value profit">{{ $money($summary['best_day']) }}</span><span class="muted">{{ $summary['best_weekday'] }}</span></td>
                <td><span class="label">Worst Day</span><span class="value loss">{{ $money($summary['worst_day']) }}</span><span class="muted">Daily loss {{ $percent($summary['max_daily_loss_percent']) }}</span></td>
                <td><span class="label">Best Day Rule</span><span class="value">{{ $percent($summary['consistency_percent']) }}</span><span class="muted">{{ $summary['best_day_rule']['status'] }}</span></td>
                <td><span class="label">Position Score</span><span class="value">{{ number_format((float) $positionProfile['score'], 2) }}</span><span class="muted">{{ $positionProfile['status'] }}</span></td>
                <td><span class="label">Behavior Risk</span><span class="value">{{ number_format((float) $traderStyleProfile['risk_score'], 2) }}/100</span><span class="muted">{{ $traderStyleProfile['risk_level'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Revenge Trading</span><span class="value">{{ $traderStyleProfile['revenge']['status'] }}</span><span class="muted">{{ data_get($traderStyleProfile, 'revenge.tier_label', 'N/A') }} tier | {{ number_format((float) $traderStyleProfile['revenge']['score'], 2) }}/100</span></td>
                <td><span class="label">Layering</span><span class="value">{{ $traderStyleProfile['layering']['status'] }}</span><span class="muted">{{ number_format((float) $traderStyleProfile['layering']['score'], 2) }}/100</span></td>
                <td><span class="label">Gambling Behavior</span><span class="value">{{ $traderStyleProfile['gambling']['status'] }}</span><span class="muted">{{ data_get($traderStyleProfile, 'gambling.tier_label', 'N/A') }} tier | Margin {{ number_format((float) data_get($traderStyleProfile, 'gambling.max_margin_percent', 0), 2) }}% | Layers {{ number_format((float) data_get($traderStyleProfile, 'gambling.layering_exposure_event_count', 0)) }}</span></td>
                <td><span class="label">Weekly Behaviour</span><span class="value">{{ data_get($behaviorWeeklyMetrics->get('overall'), 'change_label', '0.00 pts') }}</span><span class="muted">{{ data_get($behaviorWeeklyMetrics->get('overall'), 'trend_label', 'N/A') }}</span></td>
                <td><span class="label">Weekly Revenge</span><span class="value">{{ data_get($behaviorWeeklyMetrics->get('revenge'), 'change_label', '0.00 pts') }}</span><span class="muted">{{ data_get($behaviorWeeklyMetrics->get('revenge'), 'trend_label', 'N/A') }}</span></td>
                <td><span class="label">Weekly Gambling</span><span class="value">{{ data_get($behaviorWeeklyMetrics->get('gambling'), 'change_label', '0.00 pts') }}</span><span class="muted">{{ data_get($behaviorWeeklyMetrics->get('gambling'), 'trend_label', 'N/A') }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Behaviour Penalty</span><span class="value">-{{ number_format((float) data_get($behaviorScorePenalty, 'points', 0), 2) }} pts</span><span class="muted">{{ data_get($behaviorScorePenalty, 'status', 'No Behaviour Penalty') }}</span></td>
                <td><span class="label">Layered Trades</span><span class="value">{{ number_format((float) $traderStyleProfile['layering']['layered_trade_percent'], 2) }}%</span><span class="muted">{{ $traderStyleProfile['layering']['layered_trade_count'] }} affected</span></td>
                <td><span class="label">Max Layers</span><span class="value">{{ $traderStyleProfile['layering']['max_active_layers'] }}</span><span class="muted">Same pair/direction</span></td>
                <td colspan="3"><span class="label">Weekly Comparison Window</span><span class="value">{{ data_get($behaviorWeeklyProfile ?? [], 'current_period_label', 'Current week') }}</span><span class="muted">Compared with {{ data_get($behaviorWeeklyProfile ?? [], 'previous_period_label', 'Previous week') }}</span></td>
            </tr>
        </table>

        <h2>Score Matrix</h2>
        <table class="section-table">
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
                @foreach($scoreEvaluationProfile['components'] as $component)
                    <tr>
                        <td><strong>{{ $component['metric'] }}</strong><div class="muted small">{{ $component['formula'] }}</div></td>
                        <td>{{ $component['value'] }}</td>
                        <td>{{ $component['grade'] }}</td>
                        <td class="{{ $component['points'] < 0 ? 'loss' : ($component['points'] > 0 ? 'profit' : '') }}">{{ $component['points'] > 0 ? '+' : '' }}{{ number_format((float) $component['points'], 2) }}</td>
                        <td>{{ $component['is_penalty'] ? 'Penalty' : number_format((float) $component['max_points'], 0) }}</td>
                        <td><span class="badge {{ $toneClass($component['tone']) }}">{{ $component['status'] }}</span></td>
                        <td>{{ $component['calculation'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Trader Style Intelligence</h2>
        <table class="two-col">
            <tr>
                <td class="panel">
                    <h3>Revenge Trading Detection</h3>
                    <table>
                        <tbody>
                            <tr><th>Status</th><td>{{ $traderStyleProfile['revenge']['status'] }}</td><th>Score</th><td>{{ number_format((float) $traderStyleProfile['revenge']['score'], 2) }}/100</td></tr>
                            <tr><th>Tier</th><td>{{ data_get($traderStyleProfile, 'revenge.tier_label', 'N/A') }}</td><th>Method</th><td>{{ data_get($traderStyleProfile, 'revenge.tier_description', '-') }}</td></tr>
                            <tr><th>Events</th><td>{{ $traderStyleProfile['revenge']['event_count'] }}</td><th>Avg Delay</th><td>{{ $traderStyleProfile['revenge']['average_delay_label'] }}</td></tr>
                            <tr><th>Fast Re-entry</th><td>{{ $traderStyleProfile['revenge']['quick_reentry_count'] }}</td><th>Lot Increase</th><td>{{ $traderStyleProfile['revenge']['lot_increase_count'] }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="panel">
                    <h3>Gambling-style Risk Detection</h3>
                    <table>
                        <tbody>
                            <tr><th>Status</th><td>{{ $traderStyleProfile['gambling']['status'] }}</td><th>Score</th><td>{{ number_format((float) $traderStyleProfile['gambling']['score'], 2) }}/100</td></tr>
                            <tr><th>Tier</th><td>{{ data_get($traderStyleProfile, 'gambling.tier_label', 'N/A') }}</td><th>Method</th><td>{{ data_get($traderStyleProfile, 'gambling.tier_description', '-') }}</td></tr>
                            <tr><th>Overtrade Days</th><td>{{ $traderStyleProfile['gambling']['overtrading_days'] }}</td><th>Max Trades/Day</th><td>{{ $traderStyleProfile['gambling']['max_trades_per_day'] }}</td></tr>
                            <tr><th>Rapid Entries</th><td>{{ $traderStyleProfile['gambling']['rapid_trade_count'] }}</td><th>Oversized Losses</th><td>{{ $traderStyleProfile['gambling']['oversized_loss_count'] }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="panel" colspan="2">
                    <h3>Layering Detection</h3>
                    <table>
                        <tbody>
                            <tr><th>Status</th><td>{{ $traderStyleProfile['layering']['status'] }}</td><th>Score</th><td>{{ number_format((float) $traderStyleProfile['layering']['score'], 2) }}/100</td></tr>
                            <tr><th>Events</th><td>{{ $traderStyleProfile['layering']['event_count'] }}</td><th>Affected Trades</th><td>{{ $traderStyleProfile['layering']['layered_trade_count'] }}</td></tr>
                            <tr><th>Overlaps</th><td>{{ $traderStyleProfile['layering']['overlap_count'] }}</td><th>Adverse Layers</th><td>{{ $traderStyleProfile['layering']['adverse_layer_count'] }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Behavior Check</th>
                    <th>Value</th>
                    <th>Status</th>
                    <th>Points</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($traderStyleProfile['revenge']['checks'] as $check)
                    <tr>
                        <td><strong>{{ $check['name'] }}</strong></td>
                        <td>{{ $check['value'] }}</td>
                        <td><span class="badge {{ $toneClass($check['tone']) }}">{{ $check['status'] }}</span></td>
                        <td>-</td>
                        <td>{{ $check['description'] }}</td>
                    </tr>
                @endforeach
                @foreach($traderStyleProfile['layering']['checks'] as $check)
                    <tr>
                        <td><strong>{{ $check['name'] }}</strong></td>
                        <td>{{ $check['value'] }}</td>
                        <td><span class="badge {{ $toneClass($check['tone']) }}">{{ $check['status'] }}</span></td>
                        <td>-</td>
                        <td>{{ $check['description'] }}</td>
                    </tr>
                @endforeach
                @foreach($traderStyleProfile['gambling']['checks'] as $check)
                    <tr>
                        <td><strong>{{ $check['name'] }}</strong></td>
                        <td>{{ $check['value'] }}</td>
                        <td><span class="badge {{ $toneClass($check['tone']) }}">{{ $check['status'] }}</span></td>
                        <td>{{ number_format((float) $check['points'], 2) }}/{{ number_format((float) $check['max_points'], 0) }}</td>
                        <td>{{ $check['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Revenge Tier Evidence Examples</h2>
        <table>
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Loss Trade</th>
                    <th>Next Trade</th>
                    <th>Pair</th>
                    <th>Delay</th>
                    <th>Lot Change</th>
                    <th>Signals</th>
                </tr>
            </thead>
            <tbody>
                @forelse($traderStyleProfile['revenge']['examples'] as $event)
                    <tr>
                        <td><span class="badge {{ $toneClass(data_get($event, 'tier_tone', 'secondary')) }}">{{ data_get($event, 'tier_label', 'N/A') }}</span></td>
                        <td>{{ $event['trigger_trade_label'] }} (-{{ number_format((float) $event['loss_amount'], 2) }}u)</td>
                        <td>{{ $event['response_trade_label'] }} ({{ number_format((float) $event['response_profit_loss'], 2) }}u)</td>
                        <td>{{ $event['pair'] }}</td>
                        <td>{{ $event['delay_label'] }}</td>
                        <td>{{ number_format((float) $event['previous_lot'], 4) }} -> {{ number_format((float) $event['response_lot'], 4) }}</td>
                        <td>{{ $event['signals']->implode(', ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No revenge evidence detected for this report.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Gambling Tier Evidence Examples</h2>
        <table>
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Trade</th>
                    <th>Pair</th>
                    <th>Side</th>
                    <th>Lot</th>
                    <th>P/L</th>
                    <th>Why Flagged</th>
                </tr>
            </thead>
            <tbody>
                @forelse($traderStyleProfile['gambling']['examples'] as $event)
                    <tr>
                        <td><span class="badge {{ $toneClass(data_get($event, 'tier_tone', 'secondary')) }}">{{ data_get($event, 'tier_label', 'N/A') }}</span></td>
                        <td>{{ data_get($event, 'trade_label', 'N/A') }}</td>
                        <td>{{ data_get($event, 'pair', 'N/A') }}</td>
                        <td>{{ data_get($event, 'direction', 'N/A') }}</td>
                        <td>{{ number_format((float) data_get($event, 'lot_size', 0), 4) }}</td>
                        <td>{{ number_format((float) data_get($event, 'profit_loss', 0), 2) }}u</td>
                        <td>{{ data_get($event, 'reason', 'N/A') }}<div class="muted small">{{ data_get($event, 'evidence_label', '-') }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No gambling-tier evidence detected for this report.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Layering Evidence Examples</h2>
        <table>
            <thead>
                <tr>
                    <th>Base Trade</th>
                    <th>Layer Trade</th>
                    <th>Pair</th>
                    <th>Side</th>
                    <th>Delay</th>
                    <th>Lot Change</th>
                    <th>Signals</th>
                </tr>
            </thead>
            <tbody>
                @forelse($traderStyleProfile['layering']['examples'] as $event)
                    <tr>
                        <td>{{ $event['base_trade_label'] }} ({{ number_format((float) $event['base_profit_loss'], 2) }}u)</td>
                        <td>{{ $event['layer_trade_label'] }} ({{ number_format((float) $event['layer_profit_loss'], 2) }}u)</td>
                        <td>{{ $event['pair'] }}</td>
                        <td>{{ $event['direction'] }}</td>
                        <td>{{ $event['delay_label'] }}</td>
                        <td>{{ number_format((float) $event['base_lot'], 4) }} -> {{ number_format((float) $event['layer_lot'], 4) }}</td>
                        <td>{{ $event['signals']->implode(', ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No layering evidence detected for this report.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="two-col">
            <tr>
                <td class="panel">
                    <h3>Rule Monitor</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ruleMonitor as $rule)
                                <tr>
                                    <td><strong>{{ $rule['title'] }}</strong><div class="muted small">{{ $rule['caption'] }}</div></td>
                                    <td>{{ $rule['value'] }}</td>
                                    <td><span class="badge {{ $toneClass($rule['tone']) }}">{{ $rule['status'] }}</span></td>
                                    <td>
                                        <div class="bar-wrap"><div class="bar {{ $rule['tone'] === 'danger' ? 'loss-bar' : '' }}" style="width: {{ min(100, max(0, (float) $rule['progress'])) }}%;"></div></div>
                                        <span class="muted small">{{ number_format((float) $rule['progress'], 2) }}%</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td class="panel">
                    <h3>Position Consistency</h3>
                    <table>
                        <tbody>
                            <tr><th>Grade</th><td>{{ $positionProfile['grade'] }}</td><th>Main Lot</th><td>{{ number_format((float) $positionProfile['anchor_lot'], 4) }}</td></tr>
                            <tr><th>Main Lot Share</th><td>{{ $percent($positionProfile['anchor_lot_share']) }}</td><th>Near Main Lot</th><td>{{ $percent($positionProfile['near_anchor_share']) }}</td></tr>
                            <tr><th>Avg / Median</th><td>{{ number_format((float) $positionProfile['average_lot'], 4) }} / {{ number_format((float) $positionProfile['median_lot'], 4) }}</td><th>Range Ratio</th><td>{{ number_format((float) $positionProfile['range_ratio'], 2) }}x</td></tr>
                            <tr><th>Interpretation</th><td colspan="3">{{ $positionProfile['description'] }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <h2>Market, Timing, And Behavior</h2>
        <table class="two-col">
            <tr>
                <td class="panel">
                    <h3>Top Pairs</h3>
                    <table>
                        <thead><tr><th>Pair</th><th>Trades</th><th>Win Rate</th><th>P/L</th><th>Pips</th></tr></thead>
                        <tbody>
                            @foreach($pairStats->take(8) as $pair)
                                <tr>
                                    <td><strong>{{ $pair['pair'] }}</strong></td>
                                    <td>{{ $pair['trades'] }}</td>
                                    <td>{{ $percent($pair['win_rate']) }}</td>
                                    <td class="{{ $pair['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($pair['profit_loss']) }}</td>
                                    <td>{{ number_format((float) $pair['pips'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td class="panel">
                    <h3>Sessions And Bias</h3>
                    <table>
                        <thead><tr><th>Segment</th><th>Trades</th><th>Win Rate</th><th>P/L</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach($sessionStats as $session)
                                <tr>
                                    <td><strong>{{ $session['name'] }}</strong></td>
                                    <td>{{ $session['trades'] }}</td>
                                    <td>{{ $percent($session['win_rate']) }}</td>
                                    <td class="{{ $session['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($session['profit_loss']) }}</td>
                                    <td>{{ $timeLabel }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td><strong>Directional Bias</strong></td>
                                <td>{{ $behavioralProfile['buy_trades'] + $behavioralProfile['sell_trades'] }}</td>
                                <td colspan="2">{{ $behavioralProfile['label'] }}</td>
                                <td>Buy {{ $percent($behavioralProfile['buy_share']) }} / Sell {{ $percent($behavioralProfile['sell_share']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <table class="two-col">
            <tr>
                <td class="panel">
                    <h3>Weekday Profile</h3>
                    <table>
                        <thead><tr><th>Day</th><th>Trades</th><th>Win Rate</th><th>P/L</th></tr></thead>
                        <tbody>
                            @foreach($weekdayStats as $day)
                                <tr>
                                    <td>{{ $day['day'] }}</td>
                                    <td>{{ $day['trades'] }}</td>
                                    <td>{{ $percent($day['win_rate']) }}</td>
                                    <td class="{{ $day['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($day['profit_loss']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td class="panel">
                    <h3>Monthly P/L</h3>
                    <table>
                        <thead><tr><th>Month</th><th>Trades</th><th>Win Rate</th><th>P/L</th></tr></thead>
                        <tbody>
                            @forelse($monthlyStats->take(10) as $month)
                                <tr>
                                    <td>{{ $month['month'] }}</td>
                                    <td>{{ $month['trades'] }}</td>
                                    <td>{{ $percent($month['win_rate']) }}</td>
                                    <td class="{{ $month['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($month['profit_loss']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">No monthly data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <h2>Equity Curve Evidence</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Trades</th>
                    <th>Daily P/L</th>
                    <th>Cumulative P/L</th>
                    <th>Curve</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dailyStats->take(18) as $day)
                    @php
                        $width = min(100, max(4, (abs((float) $day['cumulative_profit_loss']) / $dailyMax) * 100));
                    @endphp
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['trades'] }}</td>
                        <td class="{{ $day['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($day['profit_loss']) }}</td>
                        <td class="{{ $day['cumulative_profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($day['cumulative_profit_loss']) }}</td>
                        <td><div class="bar-wrap"><div class="bar {{ $day['cumulative_profit_loss'] < 0 ? 'loss-bar' : '' }}" style="width: {{ $width }}%;"></div></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Hedging Evidence</h2>
        <table>
            <thead>
                <tr>
                    <th>Pair</th>
                    <th>Buy Trade</th>
                    <th>Sell Trade</th>
                    <th>Overlap</th>
                    <th>Started</th>
                </tr>
            </thead>
            <tbody>
                @forelse($hedgeExamples as $hedge)
                    <tr>
                        <td><strong>{{ $hedge['pair'] }}</strong></td>
                        <td>{{ $hedge['buy_trade_label'] }} / {{ number_format((float) $hedge['buy_lot'], 4) }} lot</td>
                        <td>{{ $hedge['sell_trade_label'] }} / {{ number_format((float) $hedge['sell_lot'], 4) }} lot</td>
                        <td>{{ $hedge['overlap_label'] }}</td>
                        <td>{{ $journalTime->formatForDisplay($hedge['started_at'], $selectedTimeView, $selectedTimeViewOffset) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No same-pair opposite-direction overlaps detected.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Recent Trades Included</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Close Time ({{ $timeLabel }})</th>
                    <th>Source</th>
                    <th>Pair</th>
                    <th>Side</th>
                    <th>Lot</th>
                    <th>Pips</th>
                    <th>P/L</th>
                    <th>Signals</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentTrades as $index => $trade)
                    @php
                        $tradeKey = $trade['source'] . ':' . $trade['id'];
                        $revengeInsight = $revengeTradeMap->get($tradeKey);
                        $gamblingInsight = $gamblingTradeMap->get($tradeKey);
                        $signalLabels = collect([
                            $revengeInsight ? 'Revenge ' . data_get($revengeInsight, 'tier_short', 'L') : null,
                            $gamblingInsight ? 'Gambling ' . data_get($gamblingInsight, 'tier_short', 'L') : null,
                        ])->filter()->implode(', ');
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $journalTime->formatForDisplay($trade['closed_at'], $selectedTimeView, $selectedTimeViewOffset) }}</td>
                        <td>{{ $trade['source'] }}</td>
                        <td><strong>{{ $trade['pair'] }}</strong></td>
                        <td>{{ $trade['direction'] === 1 ? 'Buy' : ($trade['direction'] === 2 ? 'Sell' : 'N/A') }}</td>
                        <td>{{ number_format((float) $trade['lot_size'], 4) }}</td>
                        <td>{{ number_format((float) $trade['pips'], 2) }}</td>
                        <td class="{{ $trade['profit_loss'] >= 0 ? 'profit' : 'loss' }}">{{ $money($trade['profit_loss']) }}</td>
                        <td>{{ $signalLabels ?: 'Normal setup' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) ($trade['notes'] ?? ''), 70) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Report generated from trading journal records. Score formula: {{ $scoreEvaluationProfile['formula'] }}.
        </div>
    </div>
</body>
</html>
