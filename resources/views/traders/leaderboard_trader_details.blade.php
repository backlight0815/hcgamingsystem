@extends('admin.admin_master')
@section('admin')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<style>
  /* … your modal + table styling unchanged … */
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

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">All Trading History</h4>
      <div class="d-flex gap-2">
        <a href="{{ route('add.trading.journal') }}" class="btn btn-success">Record New Trade</a>
        <a href="{{ route('capital.create', ['type' => 1]) }}" class="btn btn-info">Record Deposit</a>
        <a href="{{ route('capital.create', ['type' => 2]) }}" class="btn btn-warning">Record Withdraw</a>
        <a href="{{ route('trading-journal.export') }}" class="btn btn-primary">Export to Excel</a>
        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importTradesModal">
          Import from Excel
        </button>
      </div>
    </div>

    {{-- Breadcrumb --}}
    @if(!empty($breadcrumbData))
    <nav aria-label="breadcrumb" class="mb-4">
      <ol class="breadcrumb">
        @foreach ($breadcrumbData as $breadcrumb)
          <li class="breadcrumb-item">
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
          </li>
        @endforeach
      </ol>
    </nav>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card border-dark shadow-sm text-center">
          <div class="card-body">
            <h5 class="card-title">{{ number_format($currentBalance ?? 0, 2) }}u</h5>
            <p class="text-muted mb-0">Balance</p>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card border-dark shadow-sm text-center">
          <div class="card-body">
            <h5 class="card-title">{{ $winRate ?? 0 }}%</h5>
            <p class="text-muted mb-0">Win Rate</p>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card border-dark shadow-sm text-center">
          <div class="card-body">
            <h5 class="card-title text-success">{{ number_format($totalProfit ?? 0, 2) }}u</h5>
            <p class="text-muted mb-0">Total Profit</p>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card border-dark shadow-sm text-center">
          <div class="card-body">
            <h5 class="card-title text-danger">{{ number_format($totalLoss ?? 0, 2) }}u</h5>
            <p class="text-muted mb-0">Total Loss</p>
          </div>
        </div>
      </div>

      {{-- Net Profit --}}
      <div class="col-md-3 mb-3">
        <div class="card border-dark shadow-sm text-center">
          <div class="card-body">
            <h5 class="card-title {{ ($totalProfit - $totalLoss ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
              {{ number_format(($totalProfit - $totalLoss) ?? 0, 2) }}u
            </h5>
            <p class="text-muted mb-0">Net Profit / Loss</p>
          </div>
        </div>
      </div>

      {{-- Other metrics … --}}
      {{-- Similar guards applied: $averageRRR ?? 'N/A', $growthPercent ?? 0, etc. --}}
    </div>

    {{-- Trades Table --}}
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="GET" class="row mb-4">
          {{-- Month + Year filters --}}
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
              @php($i=1)
              @forelse($journals ?? [] as $journal)
                <tr>
                  <td>{{ $i++ }}</td>
                  <td>{{ optional($journal->open_date)->format('Y-m-d H:i') ?? '-' }}</td>
                  <td>{{ optional($journal->close_date)->format('Y-m-d H:i') ?? '-' }}</td>
                  <td>{{ strtoupper($journal->pair ?? '-') }}</td>
                  <td>
                    @if($journal->direction == 1)
                      <span class="badge bg-primary">Buy</span>
                    @elseif($journal->direction == 2)
                      <span class="badge bg-danger">Sell</span>
                    @else
                      <span class="badge bg-secondary">Unknown</span>
                    @endif
                  </td>
                  <td>{{ $journal->entry_price ?? '-' }}</td>
                  <td>{{ $journal->exit_price ?? '-' }}</td>
                  <td>{{ $journal->pips ?? '-' }}</td>
                  <td>{{ $journal->lot_size ?? '-' }}</td>
                  <td class="{{ ($journal->profit_loss ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $journal->profit_loss ?? 0 }}
                  </td>
                  <td>
                    @switch($journal->result)
                      @case(1) <span class="badge bg-success">Win</span> @break
                      @case(2) <span class="badge bg-danger">Loss</span> @break
                      @case(3) <span class="badge bg-warning text-dark">Break Even</span> @break
                      @default <span class="badge bg-secondary">N/A</span>
                    @endswitch
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="11" class="text-center text-muted">No trades found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Deposit / Withdraw Modals … (fix name attributes to "amount" not depositAmount) --}}

    {{-- Rating Breakdown Modal … --}}
    {{-- Prop Firm Modal … (wrap evaluation data_get calls with ?? defaults) --}}

  </div>
</div>
@endsection
