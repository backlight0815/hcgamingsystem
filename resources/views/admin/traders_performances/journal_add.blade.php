@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Add Trading Journal | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        {{-- Flash Message --}}
        @if(session('message'))
        <div class="alert alert-{{ session('alert-type') }} alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Record New Trade</h4><br><br>
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
                        <form method="POST" action="{{ route('store.trading.journal') }}">
                            @csrf

                            {{-- Open Trade --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Open Trade Time</label>
                                <div class="col-sm-9">
                                    <input name="open_date" type="datetime-local" class="form-control" value="{{ old('open_date') }}">
                                    @error('open_date')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Close Trade --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Close Trade Time</label>
                                <div class="col-sm-9">
                                    <input name="close_date" type="datetime-local" class="form-control" value="{{ old('close_date') }}">
                                    @error('close_date')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Pair --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Pair</label>
                                <div class="col-sm-9">
                                    <input name="pair" type="text" class="form-control" placeholder="e.g. XAUUSD" value="{{ old('pair') }}">
                                    @error('pair')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Direction --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Direction</label>
                                <div class="col-sm-9">
                                    <select name="direction" class="form-control">
                                        <option value="">Select Direction</option>
                                        <option value="1" {{ old('direction') == 1 ? 'selected' : '' }}>Buy</option>
                                        <option value="2" {{ old('direction') == 2 ? 'selected' : '' }}>Sell</option>
                                    </select>
                                    @error('direction')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Entry Price --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Entry Price</label>
                                <div class="col-sm-9">
                                    <input name="entry_price" type="number" step="0.01" class="form-control" value="{{ old('entry_price') }}">
                                    @error('entry_price')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Exit Price --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Exit Price</label>
                                <div class="col-sm-9">
                                    <input name="exit_price" type="number" step="0.01" class="form-control" value="{{ old('exit_price') }}">
                                    @error('exit_price')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Lot Size --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Lot Size</label>
                                <div class="col-sm-9">
                                    <input name="lot_size" type="number" step="0.01" class="form-control" value="{{ old('lot_size') }}">
                                    @error('lot_size')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Pips --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Pips</label>
                                <div class="col-sm-9">
                                    <input name="pips" type="number" step="0.1" readonly class="form-control" value="{{ old('pips') }}">
                                    @error('pips')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Profit / Loss --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Profit / Loss (USD)</label>
                                <div class="col-sm-9">
                                    <input name="profit_loss" type="number" step="0.01" readonly class="form-control" value="{{ old('profit_loss') }}">
                                    @error('profit_loss')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Result --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Result</label>
                                <div class="col-sm-9">
                                    <select name="result" class="form-control">
                                        <option value="">Select Result</option>
                                        <option value="1" {{ old('result') == 1 ? 'selected' : '' }}>Win</option>
                                        <option value="2" {{ old('result') == 2 ? 'selected' : '' }}>Loss</option>
                                        <option value="3" {{ old('result') == 3 ? 'selected' : '' }}>Break Even</option>
                                    </select>
                                    @error('result')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Notes --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Notes</label>
                                <div class="col-sm-9">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Optional">{{ old('notes') }}</textarea>
                                    @error('notes')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
{{-- Number of Duplicate Trades --}}
<div class="row mb-3">
    <label class="col-sm-3 col-form-label">Number of Trades</label>
    <div class="col-sm-9">
        <input name="duplicate_count" type="number" min="1" max="500" class="form-control" value="1">
        <small class="text-muted">Enter how many trades to create (default is 1)</small>
    </div>
</div>

                            {{-- Submit Button --}}
                            <div class="text-end">
                                <button type="submit" class="btn btn-info waves-effect waves-light">Save Trade</button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<script>
    $(document).ready(function () {
function calculatePips() {
    let entry = parseFloat($("input[name='entry_price']").val()) || 0;
    let exit = parseFloat($("input[name='exit_price']").val()) || 0;

    let pips = Math.abs(exit - entry) * 10; // always positive
    $("input[name='pips']").val(pips.toFixed(1));

    calculateProfitLoss(); // still needed if profit_loss is based on pips
}

$("input[name='entry_price'], input[name='exit_price']").on("input", function () {
    calculatePips();
});

        function calculateProfitLoss() {
            let lotSize = parseFloat($("input[name='lot_size']").val()) || 0;
            let pips = parseFloat($("input[name='pips']").val()) || 0;
            let resultType = $("select[name='result']").val(); // 1=Win, 2=Loss, 3=BE

            let profit = pips * lotSize * 10;

            if (resultType === "2") {
                profit = -Math.abs(profit); // ensure negative
            } else if (resultType === "1") {
                profit = Math.abs(profit); // ensure positive
            } else {
                profit = 0; // Break Even
            }

            $("input[name='profit_loss']").val(profit.toFixed(2));
        }

        $("input[name='lot_size'], input[name='pips']").on("input", function () {
            calculateProfitLoss();
        });

        $("select[name='result']").on("change", function () {
            calculateProfitLoss();
        });

    });
</script>


@endsection
