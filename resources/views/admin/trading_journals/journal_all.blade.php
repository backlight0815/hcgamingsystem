@extends('admin.admin_master')
@section('admin')

@php
    $selectedMonth = request('month', 'all');
    $selectedYear = request('year', 'all');
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
        ['label' => 'Consistency', 'value' => number_format((float) $consistencyPercent, 2) . '%', 'icon' => 'mdi-ruler-square-compass', 'tone' => 'warning'],
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
        grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) auto;
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

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-filter-outline me-1"></i> Filter
                        </button>
                        <a href="{{ route('all.trading.journals') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="journal-panel">
            <div class="journal-panel-header">
                <h5>Trade History</h5>
                <span class="badge bg-light text-dark">{{ $totalTrades }} trades</span>
            </div>
            <div class="journal-panel-body">
                <div class="table-responsive">
                    <table id="datatable" class="table table-hover align-middle journal-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Open</th>
                                <th>Close</th>
                                <th>Pair</th>
                                <th>Direction</th>
                                <th>Entry</th>
                                <th>Exit</th>
                                <th>Pips</th>
                                <th>Lot</th>
                                <th>Profit / Loss</th>
                                <th>Result</th>
                                <th>Notes</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($journals as $index => $journal)
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
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ optional($journal->open_date)->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ optional($journal->close_date)->format('Y-m-d H:i') ?? '-' }}</td>
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
                                    <td class="note-cell">{{ $journal->notes ? \Illuminate\Support\Str::limit($journal->notes, 70) : '-' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('trading.journal.details', $journal->id) }}" class="btn btn-light journal-action-btn" title="View">
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
                                    <td colspan="13">
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
                        <span>Growth</span>
                        <strong>{{ $growthPoints }} pts <span class="badge bg-info ms-2">{{ $growthGrade }}</span></strong>
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
                        <span>Expectancy</span>
                        <strong>{{ $expectancyPoints }} pts <span class="badge bg-warning text-dark ms-2">{{ $expectancyGrade }}</span></strong>
                    </div>
                    <div class="journal-modal-row">
                        <span>Total Score</span>
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

<div class="modal fade" id="propFirmModal" tabindex="-1" aria-labelledby="propFirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark" id="propFirmModalLabel">
                    Prop Firm Evaluation - Phase {{ $evaluation['phase'] ?? 'N/A' }} - {{ $evaluation['status'] ?? 'N/A' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(!empty($evaluation) && isset($evaluation['starting_balance']))
                    @if(!empty($evaluation['message']))
                        <div class="alert alert-warning">{{ $evaluation['message'] }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Starting Balance</span>
                                <strong>{{ number_format((float) $evaluation['starting_balance'], 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Current Balance</span>
                                <strong>{{ number_format((float) $evaluation['current_balance'], 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Net P/L</span>
                                <strong>{{ number_format((float) $evaluation['net_pnl'], 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Profit Target</span>
                                <strong>
                                    {{ number_format((float) data_get($evaluation, 'profit_target.achieved', 0), 2) }} /
                                    {{ number_format((float) data_get($evaluation, 'profit_target.target_amount', 0), 2) }}
                                </strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Max Daily Loss</span>
                                <strong>{{ data_get($evaluation, 'max_daily_loss.breached', false) ? 'Breached' : 'OK' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Max Total Loss</span>
                                <strong>{{ data_get($evaluation, 'max_total_loss.breached', false) ? 'Breached' : 'OK' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Profitable Days</span>
                                <strong>{{ data_get($evaluation, 'profitable_day.profitable_days', 0) }} / {{ data_get($evaluation, 'profitable_day.required_days', 'N/A') }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="journal-modal-row h-100">
                                <span>Consistency</span>
                                <strong>{{ number_format((float) data_get($evaluation, 'consistency.score_percent', 0), 2) }}%</strong>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted mb-0">No evaluation data is available yet.</p>
                @endif
            </div>
            <div class="modal-footer">
                <span class="me-auto">
                    Status:
                    @if(($evaluation['status'] ?? '') === 'PASS')
                        <span class="badge bg-success">PASS</span>
                    @elseif(($evaluation['status'] ?? '') === 'PENDING')
                        <span class="badge bg-warning text-dark">PENDING</span>
                    @elseif(($evaluation['status'] ?? '') === 'UNDER_REVIEW')
                        <span class="badge bg-info">UNDER REVIEW</span>
                    @elseif(($evaluation['status'] ?? '') === 'DISABLED')
                        <span class="badge bg-secondary">DISABLED</span>
                    @else
                        <span class="badge bg-danger">{{ $evaluation['status'] ?? 'N/A' }}</span>
                    @endif
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
