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

<title>Trading Journal Report | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

    <!-- Page Title -->
       <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">All Trading History</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('add.trading.journal') }}" class="btn btn-success waves-effect waves-light">
            Record New Trade
        </a>
        <a href="{{ route('capital.create', ['type' => 1]) }}" class="btn btn-info">
            Record Deposit
        </a>
        <a href="{{ route('capital.create', ['type' => 2]) }}" class="btn btn-warning">
            Record Withdraw
        </a>
        <a href="{{ route('trading-journal.export') }}" class="btn btn-primary">
            Export to Excel
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">{{ number_format($currentBalance, 2) }}u</h5>
                        <p class="text-muted mb-0">Balance</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">{{ $winRate }}%</h5>
                        <p class="text-muted mb-0">Win Rate</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success">{{ number_format($totalProfit, 2) }}u</h5>
                        <p class="text-muted mb-0">Total Profit</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-danger">{{ number_format($totalLoss, 2) }}u</h5>
                        <p class="text-muted mb-0">Total Loss</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title {{ $totalProfit - $totalLoss >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($totalProfit - $totalLoss, 2) }}u
                        </h5>
                        <p class="text-muted mb-0">Net Profit / Loss</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-dark shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary">{{ is_numeric($averageRRR) ? $averageRRR : 'N/A' }}</h5>
                        <p class="text-muted mb-0">Risk-Reward Ratio</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-success shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success">{{ $growthPercent }}%</h5>
                        <p class="text-muted mb-0">Growth %</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card border-danger shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-danger">{{ $drawdownPercent }}%</h5>
                        <p class="text-muted mb-0">Drawdown %</p>

                    </div>
                </div>
            </div>
            <!-- Consistency Card -->
<div class="col-md-3 mb-3">
    <div class="card border-secondary shadow-sm">
        <div class="card-body text-center">
            <h5 class="card-title text-secondary">
                {{-- {{ $consistencyGrade }} --}}
                 ({{ number_format($stdDeviation, 2) }})
            </h5>
            <p class="text-muted mb-0">Consistency Score (σ)</p>
        </div>
    </div>
</div>
<!-- Expectancy Card -->
<div class="col-md-3 mb-3">
    <div class="card border-warning shadow-sm">
        <div class="card-body text-center">
            <h5 class="card-title text-warning">
                {{-- {{ $expectancyGrade }} --}}
                 ({{ number_format($expectancy, 2) }})
            </h5>
            <p class="text-muted mb-0">Expectancy</p>
        </div>
    </div>
</div>
         <div class="col-md-3 mb-3">
                <div class="card border-danger shadow-sm">
                    <div class="card-body text-center">
        <h5 class="card-title text-danger">{{ $rating }}</h5>
        <p class="text-muted mb-0">Grade</p>
        <!-- Button to open breakdown modal -->
<button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#ratingBreakdownModal">
    View Rating Breakdown
</button>
<button class="btn btn-warning mt-2" data-bs-toggle="modal" data-bs-target="#propFirmModal">
    View Prop Firm Evaluation
</button>

    </div>
</div>
        </div>

        <!-- Table Section -->
        <div class="card shadow-sm">
            <div class="card-body">
          <form method="GET" class="row mb-4">
    @php
        $selectedMonth = request('month', now()->month);
        $selectedYear = request('year', now()->year);
    @endphp

    <div class="col-md-3">
        <label for="month" class="form-label">Select Month</label>
        <select name="month" id="month" class="form-select">
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
            @endfor
        </select>
    </div>

    <div class="col-md-3">
        <label for="year" class="form-label">Select Year</label>
        <select name="year" id="year" class="form-select">
            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">Filter</button>
        <a href="{{ route('all.trading.journals') }}" class="btn btn-secondary">Reset Filter</a>
    </div>
</form>


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
                            @php($i = 1)
                            @foreach($journals as $journal)
                                <tr>
                                    <td>{{ $i++ }}</td>
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
<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('capital.store') }}">
            @csrf
            <input type="hidden" name="type" value="1"> {{-- 1 = Deposit --}}
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="depositAmount" class="form-label">Deposit Amount (u)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="depositAmount" name="depositAmount" required>
                    </div>
                    <div class="mb-3">
                        <label for="depositDate" class="form-label">Deposit Date</label>
                        <input type="datetime-local" class="form-control" id="depositDate" name="deposit_date" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="depositNote" class="form-label">Note (Optional)</label>
                        <textarea class="form-control" id="depositNote" name="notes" rows="2" placeholder="e.g. Monthly Top-up or Initial Capital"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Deposit</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('capital.store') }}">
            @csrf
            <input type="hidden" name="type" value="2"> {{-- 2 = Withdraw --}}
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="withdrawAmount" class="form-label">Withdraw Amount (u)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="withdrawAmount" name="depositAmount" required>
                    </div>
                    <div class="mb-3">
                        <label for="withdrawDate" class="form-label">Withdraw Date</label>
                        <input type="datetime-local" class="form-control" id="withdrawDate" name="deposit_date" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="withdrawNote" class="form-label">Note (Optional)</label>
                        <textarea class="form-control" id="withdrawNote" name="notes" rows="2" placeholder="e.g. Emergency fund or manual adjustment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Withdraw</button>
                </div>
            </div>
        </form>
    </div>
</div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="ratingBreakdownModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Performance Rating Breakdown</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group">

          <!-- Win Rate -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            🎯 Win Rate:
            <div>
              <strong>{{ $winRatePoints }} pts</strong>
              <span class="badge bg-primary ms-2">Grade: {{ $winRateGrade }}</span>
            </div>
          </li>

          <!-- RRR -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            📊 RRR:
            <div>
              <strong>{{ $rrrPoints }} pts</strong>
              <span class="badge bg-success ms-2">Grade: {{ $rrrGrade }}</span>
            </div>
          </li>

          <!-- Growth -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            📈 Growth:
            <div>
              <strong>{{ $growthPoints }} pts</strong>
              <span class="badge bg-info text-dark ms-2">Grade: {{ $growthGrade }}</span>
            </div>
          </li>

          <!-- Drawdown -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            📉 Drawdown Penalty:
            <div>
              <strong>-{{ $drawdownPenalty }} pts</strong>
              <span class="badge bg-danger ms-2">Grade: {{ $drawdownGrade }}</span>
            </div>
          </li>
<!-- Consistency -->
<li class="list-group-item d-flex justify-content-between align-items-center">
  📏 Consistency (σ):
  <div>
    <strong>{{ $consistencyPoints }} pts</strong>
    <span class="badge bg-secondary ms-2">Grade: {{ $consistencyGrade }}</span>
    {{-- <span class="ms-2 small text-muted">σ: {{ number_format($stdDeviation, 2) }}</span> --}}
  </div>
</li>

<!-- Expectancy -->
<li class="list-group-item d-flex justify-content-between align-items-center">
  📐 Expectancy:
  <div>
    <strong>{{ $expectancyPoints }} pts</strong>
    <span class="badge bg-warning text-dark ms-2">Grade: {{ $expectancyGrade }}</span>
    {{-- <span class="ms-2 small text-muted">Value: {{ number_format($expectancy, 2) }}</span> --}}
  </div>
</li>

          <!-- Total Score -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            🧮 Total Score:
            <strong>{{ $totalScore }} pts</strong>
          </li>

          <!-- Final Rating -->
          <li class="list-group-item d-flex justify-content-between align-items-center">
            🏅 Final Rating:
            <strong>{{ $rating }}</strong>
          </li>

        </ul>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="propFirmModal" tabindex="-1" aria-labelledby="propFirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title text-dark" id="propFirmModalLabel">
          Prop Firm Evaluation — 
          Phase {{ $evaluation['phase'] ?? 'N/A' }} — 
          {{ $evaluation['status'] ?? 'N/A' }}
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
                <h6 class="card-title">Account (Phase {{ $evaluation['phase'] }})</h6>
                <ul class="list-unstyled mb-0">
                  <li>Starting Balance: <strong>{{ number_format($evaluation['starting_balance'], 2) }}</strong></li>
                  <li>Current Balance: <strong>{{ number_format($evaluation['current_balance'], 2) }}</strong></li>
                  <li>Net P&L: <strong>{{ number_format($evaluation['net_pnl'], 2) }}</strong></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Time Info -->
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title">Time</h6>
                <ul class="list-unstyled mb-0">
                  <li>Days Passed: <strong>{{ $evaluation['time']['days_passed'] }}</strong></li>
                  <li>Max Days: <strong>{{ $evaluation['rules']['max_days'] }}</strong></li>
                  <li>Status: {!! $evaluation['time']['within_time'] 
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
                <h6 class="card-title">Profit Target (Phase {{ $evaluation['phase'] }})</h6>
                <ul class="list-unstyled mb-0">
                  <li>Target ({{ $evaluation['rules']['profit_target'] }}%): 
                      <strong>{{ number_format($evaluation['profit_target']['target_amount'], 2) }}</strong></li>
                  <li>Achieved: <strong>{{ number_format($evaluation['net_pnl'], 2) }}</strong></li>
                  <li>Status: {!! $evaluation['profit_target']['passed'] 
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
                <h6 class="card-title">Max Daily Loss</h6>
                <ul class="list-unstyled mb-0">
                  <li>Limit ({{ $evaluation['rules']['max_daily_loss'] }}%): 
                      <strong>{{ number_format($evaluation['max_daily_loss']['limit_amount'], 2) }}</strong></li>
                  <li>Worst Day P&L: <strong>{{ number_format($evaluation['max_daily_loss']['worst_day_pnl'], 2) }}</strong></li>
                  <li>Status: {!! $evaluation['max_daily_loss']['breached'] 
                        ? '<span class="text-danger">❌ Breached</span>' 
                        : '<span class="text-success">✅ OK</span>' !!}</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Max Overall Loss -->
          <div class="col-md-4">
            <div class="card border-danger h-100">
              <div class="card-body">
                <h6 class="card-title">Max Overall Loss</h6>
                <ul class="list-unstyled mb-0">
                  <li>Limit ({{ $evaluation['rules']['max_total_loss'] }}%): 
                      <strong>{{ number_format($evaluation['max_total_loss']['limit_amount'], 2) }}</strong></li>
                  <li>Overall P&L: <strong>{{ number_format($evaluation['max_total_loss']['overall_pnl'], 2) }}</strong></li>
                  <li>Status: {!! $evaluation['max_total_loss']['breached'] 
                        ? '<span class="text-danger">❌ Breached</span>' 
                        : '<span class="text-success">✅ OK</span>' !!}</li>
                </ul>
              </div>
            </div>
          </div>

        </div>
        @else
          <p class="text-muted mb-0">No evaluation data.</p>
        @endif
      </div>

      <div class="modal-footer">
  <span class="me-auto">
    Final Status:
    @if(($evaluation['phase'] ?? 0) == 1 && ($evaluation['status'] ?? '') === 'PASS')
      <span class="badge bg-info">✅ Phase 1 Completed</span>
    @elseif(($evaluation['phase'] ?? 0) == 2 && ($evaluation['status'] ?? '') === 'PASS')
      <span class="badge bg-success">🎉 Phase 2 Completed — Funded!</span>
    @elseif(($evaluation['phase'] ?? 0) == 3 && ($evaluation['status'] ?? '') === 'PASS')
      <span class="badge bg-success">🎉 Funded!</span>
    @else
      {!! ($evaluation['status'] ?? 'FAIL') === 'PASS'
          ? '<span class="badge bg-success">PASS</span>'
          : '<span class="badge bg-danger">FAIL</span>' !!}
    @endif
  </span>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>


    </div>
  </div>
</div>

@endsection
