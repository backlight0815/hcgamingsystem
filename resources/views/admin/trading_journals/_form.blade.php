@php
    $journal = $journal ?? null;
    $isEdit = (bool) $journal;
    $selectedPair = old('pair', $journal->pair ?? '');
    $selectedDirection = old('direction', $journal->direction ?? '');
    $selectedResult = old('result', $journal->result ?? '');
    $formatDateTimeLocal = function ($value) {
        return $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
    };
@endphp

<style>
    .journal-page {
        color: #1f2937;
    }

    .journal-page .page-title-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .journal-page .page-title-box h4 {
        margin: 0;
        font-weight: 700;
        color: #111827;
    }

    .journal-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .journal-panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid #edf0f4;
    }

    .journal-panel-header h5,
    .journal-section-title {
        margin: 0;
        color: #111827;
        font-weight: 700;
    }

    .journal-panel-body {
        padding: 24px;
    }

    .journal-section-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 16px;
    }

    .journal-field {
        margin-bottom: 18px;
    }

    .journal-field label {
        display: block;
        margin-bottom: 7px;
        color: #4b5563;
        font-weight: 600;
    }

    .journal-field .form-control,
    .journal-field .form-select {
        border-color: #d8dee9;
        border-radius: 7px;
        min-height: 42px;
    }

    .journal-field .form-control:focus,
    .journal-field .form-select:focus {
        border-color: #3155d4;
        box-shadow: 0 0 0 .16rem rgba(49, 85, 212, .14);
    }

    .journal-segment {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .journal-result-segment {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .journal-segment input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .journal-choice {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        margin: 0;
        border: 1px solid #d8dee9;
        border-radius: 7px;
        background: #f9fafb;
        color: #374151;
        cursor: pointer;
        font-weight: 700;
        transition: all .16s ease;
    }

    .journal-segment input:checked + .journal-choice {
        background: #eef3ff;
        border-color: #3155d4;
        color: #1d3fbf;
        box-shadow: inset 0 0 0 1px rgba(49, 85, 212, .2);
    }

    .journal-segment input[value="1"]:checked + .journal-choice {
        background: #ecfdf3;
        border-color: #16a34a;
        color: #15803d;
    }

    .journal-segment input[value="2"]:checked + .journal-choice {
        background: #fef2f2;
        border-color: #dc2626;
        color: #b91c1c;
    }

    .journal-result-segment input[value="3"]:checked + .journal-choice {
        background: #fffbeb;
        border-color: #d97706;
        color: #92400e;
    }

    .journal-summary {
        position: sticky;
        top: 92px;
    }

    .journal-summary-row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid #edf0f4;
    }

    .journal-summary-row:last-child {
        border-bottom: 0;
    }

    .journal-summary-label {
        color: #6b7280;
        font-weight: 600;
    }

    .journal-summary-value {
        color: #111827;
        font-weight: 800;
        text-align: right;
    }

    .journal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 18px;
        border-top: 1px solid #edf0f4;
    }

    @media (max-width: 991.98px) {
        .journal-summary {
            position: static;
        }
    }

    @media (max-width: 575.98px) {
        .journal-page .page-title-box,
        .journal-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .journal-result-segment {
            grid-template-columns: 1fr;
        }
    }
</style>

<form method="POST" action="{{ $action }}" id="journalTradeForm" novalidate>
    @csrf

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="journal-panel mb-4">
                <div class="journal-panel-header">
                    <h5>Trade Timeline</h5>
                </div>
                <div class="journal-panel-body">
                    <div class="row">
                        <div class="col-md-6 journal-field">
                            <label for="open_date">Open Trade Time</label>
                            <input id="open_date" name="open_date" type="datetime-local" class="form-control @error('open_date') is-invalid @enderror" value="{{ old('open_date', $formatDateTimeLocal($journal->open_date ?? null)) }}" required>
                            @error('open_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 journal-field">
                            <label for="close_date">Close Trade Time</label>
                            <input id="close_date" name="close_date" type="datetime-local" class="form-control @error('close_date') is-invalid @enderror" value="{{ old('close_date', $formatDateTimeLocal($journal->close_date ?? null)) }}" required>
                            @error('close_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="journal-panel mb-4">
                <div class="journal-panel-header">
                    <h5>Trade Setup</h5>
                </div>
                <div class="journal-panel-body">
                    <div class="row">
                        <div class="col-md-7 journal-field">
                            <label for="pair">Pair</label>
                            <select id="pair" name="pair" class="form-control @error('pair') is-invalid @enderror" data-journal-pair required>
                                <option value="">Select Pair</option>
                                @foreach($tradingPairs as $tradingPair)
                                    <option value="{{ $tradingPair->symbol }}"
                                        data-pip-factor="{{ $tradingPair->pip_factor ?? 1 }}"
                                        data-pip-decimal="{{ $tradingPair->pip_decimal ?? 0 }}"
                                        {{ strtoupper($selectedPair) === strtoupper($tradingPair->symbol) ? 'selected' : '' }}>
                                        {{ strtoupper($tradingPair->symbol) }}{{ $tradingPair->description ? ' - ' . $tradingPair->description : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('pair')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-5 journal-field">
                            <label>Direction</label>
                            <div class="journal-segment">
                                <input type="radio" name="direction" id="direction_buy" value="1" {{ (string) $selectedDirection === '1' ? 'checked' : '' }}>
                                <label class="journal-choice" for="direction_buy"><i class="mdi mdi-arrow-up-bold"></i> Buy</label>

                                <input type="radio" name="direction" id="direction_sell" value="2" {{ (string) $selectedDirection === '2' ? 'checked' : '' }}>
                                <label class="journal-choice" for="direction_sell"><i class="mdi mdi-arrow-down-bold"></i> Sell</label>
                            </div>
                            @error('direction')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 journal-field">
                            <label for="entry_price">Entry Price</label>
                            <input id="entry_price" name="entry_price" type="number" step="0.00001" class="form-control @error('entry_price') is-invalid @enderror" value="{{ old('entry_price', $journal->entry_price ?? '') }}" required>
                            @error('entry_price')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 journal-field">
                            <label for="exit_price">Exit Price</label>
                            <input id="exit_price" name="exit_price" type="number" step="0.00001" class="form-control @error('exit_price') is-invalid @enderror" value="{{ old('exit_price', $journal->exit_price ?? '') }}" required>
                            @error('exit_price')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 journal-field">
                            <label for="lot_size">Lot Size</label>
                            <input id="lot_size" name="lot_size" type="number" step="0.00001" min="0" class="form-control @error('lot_size') is-invalid @enderror" value="{{ old('lot_size', $journal->lot_size ?? '') }}" required>
                            @error('lot_size')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="journal-panel">
                <div class="journal-panel-header">
                    <h5>Outcome</h5>
                </div>
                <div class="journal-panel-body">
                    <div class="row">
                        <div class="col-md-6 journal-field">
                            <label>Result</label>
                            <div class="journal-segment journal-result-segment">
                                <input type="radio" name="result" id="result_win" value="1" {{ (string) $selectedResult === '1' ? 'checked' : '' }}>
                                <label class="journal-choice" for="result_win">Win</label>

                                <input type="radio" name="result" id="result_loss" value="2" {{ (string) $selectedResult === '2' ? 'checked' : '' }}>
                                <label class="journal-choice" for="result_loss">Loss</label>

                                <input type="radio" name="result" id="result_be" value="3" {{ (string) $selectedResult === '3' ? 'checked' : '' }}>
                                <label class="journal-choice" for="result_be">Break Even</label>
                            </div>
                            @error('result')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 journal-field">
                            <label for="pips">Pips</label>
                            <input id="pips" name="pips" type="number" step="0.1" class="form-control @error('pips') is-invalid @enderror" value="{{ old('pips', $journal->pips ?? '') }}" readonly>
                            @error('pips')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 journal-field">
                            <label for="profit_loss">Profit / Loss</label>
                            <input id="profit_loss" name="profit_loss" type="number" step="0.01" class="form-control @error('profit_loss') is-invalid @enderror" value="{{ old('profit_loss', $journal->profit_loss ?? '') }}" readonly>
                            @error('profit_loss')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="journal-field mb-0">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $journal->notes ?? '') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="journal-panel journal-summary">
                <div class="journal-panel-header">
                    <h5>Trade Summary</h5>
                </div>
                <div class="journal-panel-body">
                    <div class="journal-summary-row">
                        <span class="journal-summary-label">Pair</span>
                        <span class="journal-summary-value" data-summary-pair>{{ $selectedPair ? strtoupper($selectedPair) : '-' }}</span>
                    </div>
                    <div class="journal-summary-row">
                        <span class="journal-summary-label">Direction</span>
                        <span class="journal-summary-value" data-summary-direction>-</span>
                    </div>
                    <div class="journal-summary-row">
                        <span class="journal-summary-label">Pips</span>
                        <span class="journal-summary-value" data-summary-pips>{{ old('pips', $journal->pips ?? '-') }}</span>
                    </div>
                    <div class="journal-summary-row">
                        <span class="journal-summary-label">Profit / Loss</span>
                        <span class="journal-summary-value" data-summary-profit>{{ old('profit_loss', $journal->profit_loss ?? '-') }}</span>
                    </div>

                    @unless($isEdit)
                        <div class="journal-field mt-4">
                            <label for="duplicate_count">Number of Trades</label>
                            <input id="duplicate_count" name="duplicate_count" type="number" min="1" max="500" class="form-control @error('duplicate_count') is-invalid @enderror" value="{{ old('duplicate_count', 1) }}">
                            @error('duplicate_count')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    @endunless

                    <div class="journal-actions">
                        <a href="{{ route('all.trading.journals') }}" class="btn btn-light">
                            <i class="mdi mdi-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save me-1"></i> {{ $isEdit ? 'Update Trade' : 'Save Trade' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('journalTradeForm');
    if (!form) return;

    var pair = form.querySelector('[data-journal-pair]');
    var entry = form.querySelector('[name="entry_price"]');
    var exit = form.querySelector('[name="exit_price"]');
    var lot = form.querySelector('[name="lot_size"]');
    var pips = form.querySelector('[name="pips"]');
    var profit = form.querySelector('[name="profit_loss"]');
    var openDate = form.querySelector('[name="open_date"]');
    var closeDate = form.querySelector('[name="close_date"]');
    var summaryPair = form.querySelector('[data-summary-pair]');
    var summaryDirection = form.querySelector('[data-summary-direction]');
    var summaryPips = form.querySelector('[data-summary-pips]');
    var summaryProfit = form.querySelector('[data-summary-profit]');

    function selectedPairData() {
        var option = pair.options[pair.selectedIndex];
        return {
            symbol: option && option.value ? option.value.toUpperCase() : '-',
            pipFactor: option ? parseFloat(option.getAttribute('data-pip-factor')) || 1 : 1,
            pipDecimal: option ? parseInt(option.getAttribute('data-pip-decimal'), 10) || 0 : 0
        };
    }

    function selectedResult() {
        var input = form.querySelector('[name="result"]:checked');
        return input ? input.value : '';
    }

    function selectedDirection() {
        var input = form.querySelector('[name="direction"]:checked');
        if (!input) return '-';
        return input.value === '1' ? 'Buy' : 'Sell';
    }

    function calculate() {
        var pairData = selectedPairData();
        var entryValue = parseFloat(entry.value);
        var exitValue = parseFloat(exit.value);
        var lotValue = parseFloat(lot.value) || 0;
        var result = selectedResult();
        var calculatedPips = '';
        var calculatedProfit = '';

        if (!isNaN(entryValue) && !isNaN(exitValue)) {
            calculatedPips = Math.abs(exitValue - entryValue) / pairData.pipFactor;
            calculatedPips = calculatedPips.toFixed(pairData.pipDecimal);
            pips.value = calculatedPips;
        }

        if (calculatedPips !== '' && result !== '') {
            calculatedProfit = parseFloat(calculatedPips) * lotValue * 10;

            if (result === '2') {
                calculatedProfit = -Math.abs(calculatedProfit);
            } else if (result === '1') {
                calculatedProfit = Math.abs(calculatedProfit);
            } else {
                calculatedProfit = 0;
            }

            profit.value = calculatedProfit.toFixed(2);
        }

        summaryPair.textContent = pairData.symbol;
        summaryDirection.textContent = selectedDirection();
        summaryPips.textContent = pips.value || '-';
        summaryProfit.textContent = profit.value || '-';
        summaryProfit.classList.toggle('text-success', parseFloat(profit.value) > 0);
        summaryProfit.classList.toggle('text-danger', parseFloat(profit.value) < 0);
    }

    function syncCloseMinimum() {
        if (openDate.value) {
            closeDate.min = openDate.value;
        }
    }

    [pair, entry, exit, lot, openDate, closeDate].forEach(function (field) {
        field.addEventListener('input', calculate);
        field.addEventListener('change', function () {
            syncCloseMinimum();
            calculate();
        });
    });

    form.querySelectorAll('[name="direction"], [name="result"]').forEach(function (field) {
        field.addEventListener('change', calculate);
    });

    syncCloseMinimum();
    calculate();
});
</script>
