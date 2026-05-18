@extends('admin.admin_master')
@section('admin')

<title>Traders Performance | HC Gaming Studio</title>

<style>
    .performance-shell {
        color: #0f172a;
    }

    .performance-hero,
    .performance-panel,
    .metric-tile {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }

    .performance-hero {
        padding: 24px;
    }

    .eyebrow {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .stage-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 12px;
    }

    .stage-badge.success { background: #dcfce7; color: #166534; }
    .stage-badge.warning { background: #fef3c7; color: #92400e; }
    .stage-badge.danger { background: #fee2e2; color: #991b1b; }
    .stage-badge.primary { background: #dbeafe; color: #1d4ed8; }
    .stage-badge.secondary { background: #e2e8f0; color: #334155; }

    .metric-tile {
        min-height: 118px;
        padding: 18px;
    }

    .metric-tile span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .metric-tile strong {
        display: block;
        font-size: 26px;
        line-height: 1.2;
        margin-top: 8px;
        word-break: break-word;
    }

    .performance-panel {
        padding: 20px;
    }

    .filter-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(240px, 2fr) repeat(2, minmax(150px, 1fr)) auto;
    }

    .status-list {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .status-item {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
    }

    .status-item span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-item strong {
        display: block;
        margin-top: 5px;
    }

    .trade-table th {
        color: #475569;
        font-size: 12px;
        letter-spacing: .03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .trade-table td {
        vertical-align: middle;
    }

    .trade-row-flagged {
        background: #fff1f2;
    }

    @media (max-width: 991px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .status-list {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $netProfit = (float) $totalProfit - (float) $totalLoss;
    $evaluationStatus = $evaluation['status'] ?? 'N/A';
    $evaluationBadgeClass = match ($evaluationStatus) {
        'PASS', 'APPROVED' => 'success',
        'FAIL', 'REJECTED', 'SUSPENDED' => 'danger',
        'PENDING', 'UNDER_REVIEW', 'QUESTION_REQUIRED' => 'warning',
        default => 'secondary',
    };
    $stageBadgeClass = $propFirmStage['badge_class'] ?? 'secondary';
    $sourceLabel = $journalSource === 'archived' ? 'Archived phase history' : 'Current trade history';
    $flaggedTradeIds = collect($riskFlags)->pluck('trade_id')->all();
    $phaseLabel = $propFirmStage['phase_label'] ?? 'N/A';
    $stageLabel = $propFirmStage['label'] ?? 'N/A';
    $lockLabel = $propFirmStage['lock_label'] ?? 'N/A';
@endphp

<div class="page-content performance-shell">
    <div class="container-fluid">
        <div class="performance-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-xl-7">
                    <div class="eyebrow mb-2">Admin Trader Review</div>
                    <h3 class="mb-2">Trader Performance Dashboard</h3>
                    <p class="text-muted mb-0">
                        @if($selectedTrader)
                            Reviewing {{ $selectedTrader->name ?: $selectedTrader->username }} with {{ strtolower($sourceLabel) }}.
                        @else
                            Select a trader to view performance, prop firm status, and trade history.
                        @endif
                    </p>
                </div>
                <div class="col-xl-5 text-xl-end">
                    <div class="mb-2">
                        <span class="stage-badge {{ $stageBadgeClass }}">{{ $stageLabel }}</span>
                    </div>
                    <div class="text-muted">{{ $phaseLabel }} | {{ $lockLabel }}</div>
                    @if(!empty($propFirmStage['note']))
                        <div class="small text-muted mt-1">{{ $propFirmStage['note'] }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="performance-panel mb-4">
            <form method="GET" action="{{ route('admin.trader.journals.index') }}" class="filter-grid align-items-end">
                <div>
                    <label for="user_id" class="form-label">Trader</label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">Select trader</option>
                        @foreach ($traders as $rowTrader)
                            <option value="{{ $rowTrader->id }}" {{ (int) request('user_id') === (int) $rowTrader->id ? 'selected' : '' }}>
                                {{ $rowTrader->name ?: $rowTrader->username }} ({{ $rowTrader->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="form-label">Month</label>
                    <select name="month" id="month" class="form-select">
                        <option value="">All months</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (string) request('month') === (string) $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label for="year" class="form-label">Year</label>
                    <select name="year" id="year" class="form-select">
                        <option value="">All years</option>
                        @for ($y = date('Y'); $y >= 2022; $y--)
                            <option value="{{ $y }}" {{ (string) request('year') === (string) $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.trader.journals.index') }}" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Current Balance', 'value' => number_format($currentBalance, 2), 'class' => ''],
                ['label' => 'Net P/L', 'value' => number_format($netProfit, 2), 'class' => $netProfit >= 0 ? 'text-success' : 'text-danger'],
                ['label' => 'Win Rate', 'value' => $winRate . '%', 'class' => ''],
                ['label' => 'Growth', 'value' => $growthPercent . '%', 'class' => 'text-success'],
                ['label' => 'Drawdown', 'value' => $drawdownPercent . '%', 'class' => 'text-danger'],
                ['label' => 'Risk Reward', 'value' => is_numeric($averageRRR) ? $averageRRR : $averageRRR, 'class' => ''],
                ['label' => 'Expectancy', 'value' => number_format($expectancy, 2), 'class' => ''],
                ['label' => 'Grade', 'value' => $rating, 'class' => 'text-primary'],
            ] as $metric)
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-tile">
                        <span>{{ $metric['label'] }}</span>
                        <strong class="{{ $metric['class'] }}">{{ $metric['value'] }}</strong>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="performance-panel h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">Prop Firm Status</h5>
                            <p class="text-muted mb-0">Account state is based on the trader workflow, not only the evaluation calculation.</p>
                        </div>
                        <span class="stage-badge {{ $evaluationBadgeClass }}">{{ str_replace('_', ' ', $evaluationStatus) }}</span>
                    </div>

                    <div class="status-list">
                        <div class="status-item">
                            <span>Stage</span>
                            <strong>{{ $stageLabel }}</strong>
                        </div>
                        <div class="status-item">
                            <span>Trade Source</span>
                            <strong>{{ $sourceLabel }}</strong>
                        </div>
                        <div class="status-item">
                            <span>Total Trades</span>
                            <strong>{{ $totalTrades }}</strong>
                        </div>
                        <div class="status-item">
                            <span>Total Score</span>
                            <strong>{{ $totalScore }} pts</strong>
                        </div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#propFirmModal">
                            View Evaluation Detail
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ratingBreakdownModal">
                            View Rating Breakdown
                        </button>
                        @if($selectedTraderId)
                            <a href="{{ route('traders-performance.export', ['user_id' => $selectedTraderId, 'month' => $selectedMonth, 'year' => $selectedYear]) }}" class="btn btn-primary">
                                Export Report
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="performance-panel h-100">
                    <h5 class="mb-3">Review Notes</h5>
                    <div class="mb-3">
                        <div class="eyebrow">Performance meaning</div>
                        <div>{{ $performanceMeaning }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="eyebrow">Risk flags</div>
                        <div>{{ count($riskFlags) }} trade{{ count($riskFlags) === 1 ? '' : 's' }} exceeded the 3% risk threshold.</div>
                    </div>
                    <div>
                        <div class="eyebrow">Deposits / Withdrawals</div>
                        <div>{{ number_format($totalDeposits, 2) }} / {{ number_format($totalWithdrawals, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="performance-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Trade History</h5>
                    <p class="text-muted mb-0">{{ $sourceLabel }}</p>
                </div>
                @if($journalSource === 'archived')
                    <span class="badge bg-secondary">Archived</span>
                @endif
            </div>

            <div class="table-responsive">
                <table id="datatable" class="table table-hover align-middle trade-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Open</th>
                            <th>Close</th>
                            <th>Pair</th>
                            <th>Side</th>
                            <th>Entry</th>
                            <th>Exit</th>
                            <th>Pips</th>
                            <th>Lot</th>
                            <th>P/L</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journals as $index => $journal)
                            @php
                                $isFlagged = in_array($journal->id, $flaggedTradeIds, true);
                            @endphp
                            <tr class="{{ $isFlagged ? 'trade-row-flagged' : '' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $journal->open_date ? \Carbon\Carbon::parse($journal->open_date)->format('Y-m-d H:i') : '-' }}</td>
                                <td>{{ $journal->close_date ? \Carbon\Carbon::parse($journal->close_date)->format('Y-m-d H:i') : '-' }}</td>
                                <td><strong>{{ strtoupper($journal->pair) }}</strong></td>
                                <td>
                                    @if((int) $journal->direction === 1)
                                        <span class="badge bg-primary">Buy</span>
                                    @elseif((int) $journal->direction === 2)
                                        <span class="badge bg-danger">Sell</span>
                                    @else
                                        <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </td>
                                <td>{{ $journal->entry_price }}</td>
                                <td>{{ $journal->exit_price }}</td>
                                <td>{{ $journal->pips }}</td>
                                <td>{{ $journal->lot_size }}</td>
                                <td class="{{ $journal->profit_loss >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format((float) $journal->profit_loss, 2) }}
                                </td>
                                <td>
                                    @if((int) $journal->result === 1)
                                        <span class="badge bg-success">Win</span>
                                    @elseif((int) $journal->result === 2)
                                        <span class="badge bg-danger">Loss</span>
                                    @elseif((int) $journal->result === 3)
                                        <span class="badge bg-warning text-dark">Break Even</span>
                                    @else
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No trade history found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ratingBreakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Performance Rating Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Metric</th>
                                <th>Points</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Win Rate</td><td>{{ $winRatePoints }}</td><td>{{ $winRateGrade }}</td></tr>
                            <tr><td>Risk Reward Ratio</td><td>{{ $rrrPoints }}</td><td>{{ $rrrGrade }}</td></tr>
                            <tr><td>Growth</td><td>{{ $growthPoints }}</td><td>{{ $growthGrade }}</td></tr>
                            <tr><td>Drawdown Penalty</td><td>-{{ $drawdownPenalty }}</td><td>{{ $drawdownGrade }}</td></tr>
                            <tr><td>Consistency</td><td>{{ $consistencyPoints }}</td><td>{{ $consistencyGrade }}</td></tr>
                            <tr><td>Expectancy</td><td>{{ $expectancyPoints }}</td><td>{{ $expectancyGrade }}</td></tr>
                            <tr class="table-light"><td><strong>Total</strong></td><td><strong>{{ $totalScore }}</strong></td><td><strong>{{ $rating }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="propFirmModal" tabindex="-1" aria-labelledby="propFirmEvaluationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="propFirmEvaluationModalLabel">Prop Firm Evaluation Detail</h5>
                    <div class="text-muted small">{{ $stageLabel }} | {{ $phaseLabel }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if(!empty($evaluation) && isset($evaluation['starting_balance']))
                    @if(($evaluation['phase'] ?? null) === 3)
                        <div class="alert alert-success">
                            This trader is approved for funded account status. Evaluation gates are complete; continue monitoring funded-account risk and admin questions.
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="status-item h-100">
                                <span>Starting Balance</span>
                                <strong>{{ number_format($evaluation['starting_balance'] ?? 0, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-item h-100">
                                <span>Current Balance</span>
                                <strong>{{ number_format($evaluation['current_balance'] ?? 0, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-item h-100">
                                <span>Evaluation Status</span>
                                <strong>{{ str_replace('_', ' ', $evaluationStatus) }}</strong>
                            </div>
                        </div>

                        @if(($evaluation['phase'] ?? null) !== 3)
                            <div class="col-md-4">
                                <div class="status-item h-100">
                                    <span>Profit Target</span>
                                    <strong>{{ number_format($evaluation['profit_target']['achieved'] ?? 0, 2) }} / {{ number_format($evaluation['profit_target']['target_amount'] ?? 0, 2) }}</strong>
                                    <div class="text-muted small mt-1">{{ ($evaluation['profit_target']['passed'] ?? false) ? 'Passed' : 'Not reached' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="status-item h-100">
                                    <span>Profitable Days</span>
                                    <strong>{{ $evaluation['profitable_day']['profitable_days'] ?? 0 }} / {{ $evaluation['profitable_day']['required_days'] ?? 0 }}</strong>
                                    <div class="text-muted small mt-1">Threshold {{ number_format($evaluation['profitable_day']['threshold'] ?? 0, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="status-item h-100">
                                    <span>Time Window</span>
                                    <strong>{{ $evaluation['time']['days_passed'] ?? 0 }} / {{ $evaluation['time']['max_days'] ?? 0 }} days</strong>
                                    <div class="text-muted small mt-1">{{ ($evaluation['time']['within_time'] ?? false) ? 'Within time' : 'Outside time or pending data' }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <div class="status-item h-100">
                                <span>Max Daily Loss</span>
                                <strong>{{ number_format($evaluation['max_daily_loss']['worst_day_pnl'] ?? 0, 2) }}</strong>
                                <div class="text-muted small mt-1">Limit {{ number_format($evaluation['max_daily_loss']['limit_amount'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-item h-100">
                                <span>Max Overall Loss</span>
                                <strong>{{ number_format($evaluation['max_total_loss']['overall_pnl'] ?? 0, 2) }}</strong>
                                <div class="text-muted small mt-1">Limit {{ number_format($evaluation['max_total_loss']['limit_amount'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted mb-0">Prop firm evaluation is not available yet. Add deposits and trades to activate the evaluation.</p>
                @endif
            </div>

            <div class="modal-footer">
                <span class="me-auto">
                    Final status:
                    <span class="stage-badge {{ $evaluationBadgeClass }}">{{ str_replace('_', ' ', $evaluationStatus) }}</span>
                </span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection
