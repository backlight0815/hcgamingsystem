@extends('admin.admin_master')
@section('admin')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<style>
    #datatable {
        table-layout: fixed;
    }
    .long {
        overflow-x: hidden;
        width: 150px;
    }
    .long:hover {
        position: absolute;
        z-index: 10;
        top: 0;
        left: 0;
        width: 200px;
        background-color: #f0f0f0;
        border: 1px solid #000;
        overflow-x: visible;
    }
    body {
        overflow-y: auto !important;
    }
    .modal {
        scroll-behavior: auto !important;
    }
    body.modal-open {
        overflow: hidden;
        position: fixed;
        width: 100%;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
<script>
    function redirectToPage() {
        window.location.href = "{{ route('add.trading.journal') }}";
    }
</script>
@endpush

<title>Traders Performances | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <!-- Title and Action Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">All Traders Performance</h4>
            <div class="d-flex gap-2">
   
<a href="{{ route('traders-performance.export', ['user_id' => $selectedTraderId, 'month' => $selectedMonth, 'year' => $selectedYear]) }}" class="btn btn-primary">
    Export Report
</a>


</div>
        </div>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                @foreach ($breadcrumbData as $breadcrumb)
                    <li class="breadcrumb-item">
                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>

       <!-- Summary Statistics -->
<div class="row mb-4">
    @php
        $cards = [
            ['title' => 'Balance', 'value' => number_format($currentBalance, 2) . 'u', 'border' => 'border-dark', 'text' => ''],
            ['title' => 'Win Rate', 'value' => $winRate . '%', 'border' => 'border-dark', 'text' => ''],
            ['title' => 'Total Profit', 'value' => number_format($totalProfit, 2) . 'u', 'border' => 'border-dark', 'text' => 'text-success'],
            ['title' => 'Total Loss', 'value' => number_format($totalLoss, 2) . 'u', 'border' => 'border-dark', 'text' => 'text-danger'],
            ['title' => 'Net Profit / Loss', 'value' => number_format($totalProfit - $totalLoss, 2) . 'u', 'border' => 'border-dark', 'text' => $totalProfit - $totalLoss >= 0 ? 'text-success' : 'text-danger'],
            ['title' => 'Risk-Reward Ratio', 'value' => is_numeric($averageRRR) ? $averageRRR : 'N/A', 'border' => 'border-dark', 'text' => 'text-primary'],
            ['title' => 'Growth %', 'value' => $growthPercent . '%', 'border' => 'border-success', 'text' => 'text-success'],
            ['title' => 'Drawdown %', 'value' => $drawdownPercent . '%', 'border' => 'border-danger', 'text' => 'text-danger'],
            ['title' => 'Consistency Score (σ)', 'value' => '(' . number_format($stdDeviation, 2) . ')', 'border' => 'border-secondary', 'text' => 'text-secondary'],
            ['title' => 'Expectancy', 'value' => '(' . number_format($expectancy, 2) . ')', 'border' => 'border-warning', 'text' => 'text-warning'],
            ['title' => 'Grade', 'value' => $rating, 'border' => 'border-danger', 'text' => 'text-danger', 'extra' => true],
        ];
    @endphp

    @foreach ($cards as $card)
        <div class="col-md-3 mb-3">
            <div class="card {{ $card['border'] }} shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title {{ $card['text'] }}">{!! $card['value'] !!}</h5>
                    <p class="text-muted mb-0">{{ $card['title'] }}</p>
                    
                    @if (!empty($card['extra']))
                        <button class="btn btn-info btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#ratingBreakdownModal">
                            View Rating Breakdown
                        </button>
                        <button class="btn btn-warning mt-2" data-bs-toggle="modal" data-bs-target="#propFirmModal">
    View Prop Firm Evaluation
</button>

                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.trader.journals.index') }}" class="row mb-4">
    <div class="col-md-4">
        <label for="user_id" class="form-label">Select Trader</label>
        <select name="user_id" id="user_id" class="form-select" required>
            <option value="">-- Select Trader --</option>
            @foreach ($traders as $trader)
                <option value="{{ $trader->id }}" {{ request('user_id') == $trader->id ? 'selected' : '' }}>
                    {{ $trader->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label for="month" class="form-label">Select Month</label>
        <select name="month" id="month" class="form-select">
            <option value="">All Months</option>
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
            @endfor
        </select>
    </div>

    <div class="col-md-3">
        <label for="year" class="form-label">Select Year</label>
        <select name="year" id="year" class="form-select">
            <option value="">All Years</option>
            @for ($y = date('Y'); $y >= 2022; $y--)
                <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
                    {{ $y }}
                </option>
            @endfor
        </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">Filter</button>
        <a href="{{ route('admin.trader.journals.index') }}" class="btn btn-secondary">Reset</a>
    </div>
</form>


        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Start Date</th>
                                <th>Close Date</th>
                                <th>Pair</th>
                                <th>Direction</th>
                                <th>Entry</th>
                                <th>Exit</th>
                                <th>Pips</th>
                                <th>Lot</th>
                                <th>Profit/Loss</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journals as $index => $journal)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($journal->open_date)->format('Y-m-d H:i') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($journal->close_date)->format('Y-m-d H:i') }}</td>
                                    <td>{{ strtoupper($journal->pair) }}</td>
                                    <td>
                                        @if($journal->direction == 1)
                                            <span class="badge bg-primary">Buy</span>
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
                                    <td class="{{ $journal->profit_loss >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $journal->profit_loss }}
                                    </td>
                                    <td>
                                        @if($journal->result == 1)
                                            <span class="badge bg-success">Win</span>
                                        @elseif($journal->result == 2)
                                            <span class="badge bg-danger">Loss</span>
                                        @elseif($journal->result == 3)
                                            <span class="badge bg-warning text-dark">Break Even</span>
                                        @else
                                            <span class="badge bg-secondary">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rating Breakdown Modal -->
        <div class="modal fade" id="ratingBreakdownModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Performance Rating Breakdown</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                🎯 Win Rate:
                                <div>
                                    <strong>{{ $winRatePoints }} pts</strong>
                                    <span class="badge bg-primary ms-2">Grade: {{ $winRateGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                📊 RRR:
                                <div>
                                    <strong>{{ $rrrPoints }} pts</strong>
                                    <span class="badge bg-success ms-2">Grade: {{ $rrrGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                📈 Growth:
                                <div>
                                    <strong>{{ $growthPoints }} pts</strong>
                                    <span class="badge bg-info text-dark ms-2">Grade: {{ $growthGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                📉 Drawdown Penalty:
                                <div>
                                    <strong>-{{ $drawdownPenalty }} pts</strong>
                                    <span class="badge bg-danger ms-2">Grade: {{ $drawdownGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                📏 Consistency (σ):
                                <div>
                                    <strong>{{ $consistencyPoints }} pts</strong>
                                    <span class="badge bg-secondary ms-2">Grade: {{ $consistencyGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                📐 Expectancy:
                                <div>
                                    <strong>{{ $expectancyPoints }} pts</strong>
                                    <span class="badge bg-warning text-dark ms-2">Grade: {{ $expectancyGrade }}</span>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                🧮 Total Score:
                                <strong>{{ $totalScore }} pts</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                🏅 Final Rating:
                                <strong>{{ $rating }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<div class="modal fade" id="propFirmModal" tabindex="-1" aria-labelledby="propFirmEvaluationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title text-dark" id="propFirmModal">
          🏛️ Prop Firm Evaluation — {{ $evaluation['status'] ?? 'N/A' }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        @if(!empty($evaluation) && isset($evaluation['starting_balance']))
          <div class="row g-3">

            <!-- Account Info -->
            <div class="col-md-6">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="card-title">💰 Account</h6>
                  <ul class="list-unstyled mb-0">
                    <li>💵 Starting Balance: <strong>{{ number_format($evaluation['starting_balance'], 2) }}</strong></li>
                    <li>💳 Current Balance: <strong>{{ number_format($evaluation['current_balance'], 2) }}</strong></li>
                    <li>📊 Net P&L: <strong>{{ number_format($evaluation['net_pnl'], 2) }}</strong></li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Time Info -->
            <div class="col-md-6">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="card-title">⏱️ Time</h6>
                  <ul class="list-unstyled mb-0">
                    <li>📅 Days Passed: <strong>{{ $evaluation['time']['days_passed'] ?? 'N/A' }}</strong></li>
                    <li>🗓️ Max Days: <strong>{{ $evaluation['time']['max_days'] ?? 'N/A' }}</strong></li>
                    <li>✅ Status: {!! $evaluation['time']['within_time'] ?? false
                        ? '<span class="text-success">Within Limit</span>'
                        : '<span class="text-danger">Exceeded</span>' !!}</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Profit Target -->
            <div class="col-md-4">
              <div class="card border-success h-100">
                <div class="card-body">
                  <h6 class="card-title">🎯 Profit Target</h6>
                  <ul class="list-unstyled mb-0">
                    <li>Target ({{ $evaluation['rules']['profit_target'] ?? 'N/A' }}%): <strong>{{ number_format($evaluation['profit_target']['target_amount'] ?? 0, 2) }}</strong></li>
                    <li>Achieved: <strong>{{ number_format($evaluation['profit_target']['achieved'] ?? 0, 2) }}</strong></li>
                    <li>Status: {!! $evaluation['profit_target']['passed'] ?? false
                        ? '<span class="text-success">✅ Passed</span>'
                        : '<span class="text-danger">❌ Not yet</span>' !!}</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Max Daily Loss -->
            <div class="col-md-4">
              <div class="card border-danger h-100">
                <div class="card-body">
                  <h6 class="card-title">⚠️ Max Daily Loss</h6>
                  <ul class="list-unstyled mb-0">
                    <li>Limit ({{ $evaluation['rules']['max_daily_loss'] ?? 'N/A' }}%): <strong>{{ number_format($evaluation['max_daily_loss']['limit_amount'] ?? 0, 2) }}</strong></li>
                    <li>Worst Day P&L: <strong>{{ number_format($evaluation['max_daily_loss']['worst_day_pnl'] ?? 0, 2) }}</strong></li>
                    <li>Status: {!! $evaluation['max_daily_loss']['breached'] ?? false
                        ? '<span class="text-danger">❌ Breached</span>'
                        : '<span class="text-success">✅ OK</span>' !!}</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Max Total Loss -->
            <div class="col-md-4">
              <div class="card border-danger h-100">
                <div class="card-body">
                  <h6 class="card-title">🛑 Max Overall Loss</h6>
                  <ul class="list-unstyled mb-0">
                    <li>Limit ({{ $evaluation['rules']['max_total_loss'] ?? 'N/A' }}%): <strong>{{ number_format($evaluation['max_total_loss']['limit_amount'] ?? 0, 2) }}</strong></li>
                    <li>Overall P&L: <strong>{{ number_format($evaluation['max_total_loss']['overall_pnl'] ?? 0, 2) }}</strong></li>
                    <li>Status: {!! $evaluation['max_total_loss']['breached'] ?? false
                        ? '<span class="text-danger">❌ Breached</span>'
                        : '<span class="text-success">✅ OK</span>' !!}</li>
                  </ul>
                </div>
              </div>
            </div>

          </div>
        @else
          <p class="text-muted mb-0">⚠️ Prop Firm Evaluation not available yet. Please add deposits and trades to see the evaluation.</p>
        @endif
      </div>

    <div class="modal-footer">
    <span class="me-auto">
      🏆 Final Status:
      @if(isset($evaluation['status']) && $evaluation['status'] !== 'N/A')
        @if($evaluation['status'] === 'PASS')
          <span class="badge bg-success">PASS</span>
        @elseif($evaluation['status'] === 'FAIL')
          <span class="badge bg-danger">FAIL</span>
        @elseif($evaluation['status'] === 'PENDING')
          <span class="badge bg-warning text-dark">PENDING</span>
        @endif
      @else
        <span class="badge bg-secondary">N/A</span>
      @endif
    </span>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>


    </div>
  </div>
</div>


@endsection
