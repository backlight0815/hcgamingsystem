@php
    $providerName = $selectedProvider?->username ?: ($selectedProvider?->name ?: 'All Providers');
    $dateLabel = ($filters['from_date'] || $filters['to_date'])
        ? (($filters['from_date'] ?: 'Start') . ' to ' . ($filters['to_date'] ?: $generatedAt->format('Y-m-d')))
        : 'All available records';
    $groupedPerformances = $performances->groupBy(function ($perf) {
        return ($perf->signal ? 'main_' : 'backup_') . $perf->signal_id;
    });
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Signal Performance Report</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.42;
            margin: 0;
        }

        .header {
            background: #111827;
            color: #ffffff;
            padding: 22px 24px;
        }

        .kicker {
            color: #5eead4;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            font-size: 24px;
            line-height: 1.15;
            margin: 7px 0 8px;
        }

        .muted {
            color: #64748b;
        }

        .header .muted {
            color: #cbd5e1;
        }

        .content {
            padding: 20px 24px 24px;
        }

        .summary-table,
        .criteria-table,
        .trade-table {
            border-collapse: collapse;
            width: 100%;
        }

        .summary-table td {
            border: 1px solid #d9e3ef;
            padding: 10px;
            vertical-align: top;
            width: 25%;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            display: block;
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
        }

        .level {
            background: #e0f2fe;
            border-radius: 999px;
            color: #075985;
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            margin-top: 6px;
            padding: 5px 8px;
            text-transform: uppercase;
        }

        h2 {
            color: #0f172a;
            font-size: 15px;
            margin: 20px 0 8px;
        }

        .criteria-table th,
        .criteria-table td,
        .trade-table th,
        .trade-table td {
            border: 1px solid #d9e3ef;
            padding: 7px;
            vertical-align: top;
        }

        .criteria-table th,
        .trade-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        .criteria-table td strong {
            color: #0f172a;
        }

        .meaning {
            background: #f8fafc;
            border: 1px solid #d9e3ef;
            margin-top: 10px;
            padding: 11px;
        }

        .profit {
            color: #0f766e;
            font-weight: bold;
        }

        .loss {
            color: #e11d48;
            font-weight: bold;
        }

        .footer {
            color: #64748b;
            font-size: 9px;
            margin-top: 18px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="kicker">HC Gaming Studio</div>
        <h1>Signal Performance Report</h1>
        <div class="muted">
            Provider: {{ $providerName }} | Period: {{ $dateLabel }} | Generated: {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="content">
        <table class="summary-table">
            <tr>
                <td>
                    <span class="label">Total Score</span>
                    <span class="value">{{ $summary['totalScore'] ?? 0 }}/100</span>
                    <span class="level">{{ $summary['providerLevel'] ?? $summary['grade'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="label">Total Trades</span>
                    <span class="value">{{ number_format($summary['totalTrades'] ?? 0) }}</span>
                    <span class="muted">{{ number_format($summary['totalWinTrades'] ?? 0) }} wins / {{ number_format($summary['totalLoseTrades'] ?? 0) }} losses</span>
                </td>
                <td>
                    <span class="label">Total Pips</span>
                    <span class="value">{{ number_format($summary['totalPips'] ?? 0, 2) }}</span>
                    <span class="muted">PF {{ number_format($summary['profitFactor'] ?? 0, 2) }}</span>
                </td>
                <td>
                    <span class="label">Win Rate</span>
                    <span class="value">{{ number_format($summary['winRate'] ?? 0, 2) }}%</span>
                    <span class="muted">RR {{ number_format($summary['rrRatio'] ?? 0, 2) }} : 1</span>
                </td>
            </tr>
        </table>

        <h2>Score Breakdown And Criteria</h2>
        <table class="criteria-table">
            <thead>
                <tr>
                    <th>Criteria</th>
                    <th>Measured Value</th>
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($scoreBreakdown as $criteria)
                    <tr>
                        <td><strong>{{ $criteria['name'] }}</strong></td>
                        <td>{{ $criteria['value'] }}</td>
                        <td>{{ $criteria['points'] }}/{{ $criteria['max'] }}</td>
                        <td>{{ $criteria['grade'] }}</td>
                        <td>{{ $criteria['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="meaning">
            <strong>Evaluation meaning:</strong>
            {{ $summary['performanceMeaning'] ?? 'No evaluation available for this report.' }}
        </div>

        <h2>Included Signal Results</h2>
        <table class="trade-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Signal</th>
                    <th>Pair</th>
                    <th>Action</th>
                    <th>TP Hit</th>
                    <th>Outcome</th>
                    <th>Pips</th>
                    <th>Provider</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupedPerformances as $signalGroup)
                    @php
                        $perf = $signalGroup->first();
                        $signal = $perf->signal ?? $perf->backupSignal;
                        $rowPips = $signalGroup->sum('profit_pips');
                        $tpHit = $signalGroup->max('tp_hit') ?? 0;
                        $totalTp = $signal ? collect([
                            $signal->target_1, $signal->target_2, $signal->target_3, $signal->target_4, $signal->target_5,
                            $signal->target_6, $signal->target_7, $signal->target_8, $signal->target_9, $signal->target_10,
                        ])->filter(fn ($tp) => !is_null($tp))->count() : 0;
                        $isSL = $signalGroup->contains('is_sl', true);
                        $outcomeLabel = $isSL ? 'Loss' : ($tpHit > 0 ? 'Win' : 'Pending');
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $signal->signal_code ?? '-' }}</td>
                        <td>{{ strtoupper($signal->trading_pair ?? '-') }}</td>
                        <td>{{ strtoupper($signal->immediate_action ?? '-') }}</td>
                        <td>{{ $tpHit }}/{{ $totalTp }}</td>
                        <td>{{ $outcomeLabel }}</td>
                        <td class="{{ $rowPips >= 0 ? 'profit' : 'loss' }}">{{ number_format($rowPips, 2) }}</td>
                        <td>{{ $signal?->user?->username ?? '-' }}</td>
                        <td>{{ optional($perf->created_at)->format('Y-m-d') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">No signal performances found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            This report is generated from the selected Signal Performance filters.
        </div>
    </div>
</body>
</html>
