@extends('admin.admin_master')
@section('admin')

<title>Edit Trading Signal | HC Gaming Studio</title>

@php
    $currentTargets = collect(range(1, 10))
        ->map(fn ($i) => $signal->{'target_'.$i})
        ->filter()
        ->count();
@endphp

<style>
    .signal-form-page {
        background: #eef3f8;
        color: #172033;
        min-height: 100vh;
    }

    .signal-form-shell {
        container-type: inline-size;
        margin: 0 auto;
        max-width: 1560px;
        padding: 26px 30px 42px;
    }

    .form-hero {
        background: #111827;
        border: 1px solid #22304a;
        border-radius: 12px;
        color: #ffffff;
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 18px;
        padding: 28px;
    }

    .form-kicker {
        color: #2dd4bf;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .form-hero h3 {
        color: #ffffff;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: 0;
        margin: 8px 0;
    }

    .form-hero p {
        color: #aab7ca;
        margin: 0;
        max-width: 820px;
    }

    .signal-layout {
        align-items: start;
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1fr) 340px;
    }

    .signal-panel,
    .signal-side {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        min-width: 0;
    }

    .signal-panel {
        margin-bottom: 18px;
        padding: 20px;
    }

    .signal-side {
        padding: 18px;
        position: sticky;
        top: 88px;
    }

    .panel-head {
        align-items: flex-start;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .panel-head h5 {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .panel-head p {
        color: #64748b;
        font-size: 12px;
        margin: 4px 0 0;
    }

    .form-label {
        color: #475569;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .required::after {
        color: #e11d48;
        content: "*";
        margin-left: 3px;
    }

    .target-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .target-field {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
    }

    .target-insight {
        color: #0f766e;
        display: block;
        font-size: 12px;
        font-weight: 800;
        margin-top: 7px;
    }

    .side-row {
        border-bottom: 1px solid #e2e8f0;
        padding: 12px 0;
    }

    .side-row:first-of-type {
        padding-top: 0;
    }

    .side-row span {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .side-row strong {
        color: #0f172a;
        display: block;
        font-size: 15px;
        margin-top: 4px;
    }

    .submit-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 18px;
    }

    @container (max-width: 1180px) {
        .signal-layout {
            grid-template-columns: 1fr;
        }

        .signal-side {
            position: static;
        }
    }

    @@media (max-width: 700px) {
        .signal-form-shell {
            padding: 18px 12px 30px;
        }

        .target-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content signal-form-page">
    <div class="container-fluid signal-form-shell">
        <div class="form-hero">
            <div>
                <div class="form-kicker">Revise Signal Ticket</div>
                <h3>Edit {{ $signal->signal_code ?? 'Trading Signal' }}</h3>
                <p>Update the execution plan and rationale. Saved changes can update Discord records when the integration is enabled.</p>
            </div>
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <a href="{{ route('view.trading.signal', $signal->id) }}" class="btn btn-outline-light">
                    <i class="ri-eye-line"></i> View
                </a>
                <a href="{{ route('all.trading.signals') }}" class="btn btn-outline-light">
                    <i class="ri-arrow-left-line"></i> Back
                </a>
            </div>
        </div>

        <form action="{{ route('update.trading.signal', $signal->id) }}" method="POST" id="editSignal" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" value="{{ $signal->id }}">

            <div class="signal-layout">
                <div>
                    <div class="signal-panel">
                        <div class="panel-head">
                            <div>
                                <h5>Execution Setup</h5>
                                <p>Adjust pair, action, entry, stop loss, and risk classification.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="form-label required">Trading Pair</label>
                                <input name="trading_pair" class="form-control" type="text" value="{{ old('trading_pair', $signal->trading_pair) }}" required>
                                @error('trading_pair') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label required">Signal Type</label>
                                <select name="immediate_action" class="form-select" required>
                                    <option value="">Select Type</option>
                                    @foreach(['Buy Now', 'Sell Now', 'Buy Limit', 'Sell Limit', 'Buy Stop', 'Sell Stop'] as $action)
                                        <option value="{{ $action }}" {{ old('immediate_action', $signal->immediate_action) == $action ? 'selected' : '' }}>{{ $action }}</option>
                                    @endforeach
                                </select>
                                @error('immediate_action') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Risk Level</label>
                                <input name="risk_level" class="form-control" type="text" value="{{ old('risk_level', $signal->risk_level) }}" placeholder="Low / Medium / High">
                                @error('risk_level') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label required">Entry Price</label>
                                <input name="entry_price" class="form-control" type="text" value="{{ old('entry_price', $signal->entry_price) }}" required>
                                @error('entry_price') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label required">Stop Loss</label>
                                <input name="stop_loss" class="form-control" type="text" value="{{ old('stop_loss', $signal->stop_loss) }}" required>
                                @error('stop_loss') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="signal-panel">
                        <div class="panel-head">
                            <div>
                                <h5>Take Profit Ladder</h5>
                                <p>Review all targets before updating the published signal.</p>
                            </div>
                        </div>
                        <div class="target-grid">
                            @for ($t = 1; $t <= 10; $t++)
                                <div class="target-field">
                                    <label class="form-label {{ $t <= 2 ? 'required' : '' }}">Target {{ $t }}</label>
                                    <input name="target_{{ $t }}" class="form-control" type="text" value="{{ old('target_'.$t, $signal->{'target_'.$t}) }}" placeholder="TP{{ $t }} price" {{ $t <= 2 ? 'required' : '' }}>
                                    @error('target_'.$t) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    <small class="target-insight" data-target-insight="{{ $t }}">0.01 lot estimate: -</small>
                                </div>
                            @endfor
                        </div>
                    </div>

                    <div class="signal-panel">
                        <div class="panel-head">
                            <div>
                                <h5>Rationale And Reference</h5>
                                <p>Keep the reason set, link, and disclaimer aligned with the updated plan.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label">Trading Reasons</label>
                                <select name="trading_reasons[]" class="form-select" multiple>
                                    @foreach($reasons as $reason)
                                        <option value="{{ $reason->id }}" {{ in_array($reason->id, old('trading_reasons', $signal->trading_reasons ?? [])) ? 'selected' : '' }}>
                                            {{ $reason->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('trading_reasons') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">TradingView Link</label>
                                <input name="link" class="form-control" type="url" value="{{ old('link', $signal->link) }}" placeholder="https://www.tradingview.com/x/...">
                                @error('link') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-12">
                                <label class="form-label">Disclaimer</label>
                                <textarea name="disclaimer" class="form-control" rows="3" placeholder="Optional disclaimer">{{ old('disclaimer', $signal->disclaimer) }}</textarea>
                                @error('disclaimer') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="signal-side">
                    <div class="panel-head">
                        <div>
                            <h5>Signal Summary</h5>
                            <p>Current database record before update.</p>
                        </div>
                    </div>
                    <div class="side-row">
                        <span>Code</span>
                        <strong>{{ $signal->signal_code ?? '-' }}</strong>
                    </div>
                    <div class="side-row">
                        <span>Current Pair</span>
                        <strong>{{ $signal->trading_pair ?? '-' }}</strong>
                    </div>
                    <div class="side-row">
                        <span>Targets Set</span>
                        <strong>{{ $currentTargets }}/10</strong>
                    </div>
                    <div class="side-row">
                        <span>Discord Integration</span>
                        <strong>{{ feature_enabled('DiscordIntegration') ? 'Enabled' : 'Disabled' }}</strong>
                    </div>
                    <div class="submit-row">
                        <a href="{{ route('all.trading.signals') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="updatebutton">
                            <i class="ri-save-3-line"></i> Update Signal
                        </button>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>

<script>
    const tradingPairMeta = @json(App\Models\TradingPair::select('symbol', 'pip_factor', 'pip_decimal')->get());

    function normalizePairSymbol(pair) {
        return String(pair || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function parseSignalPrice(value) {
        const match = String(value || '').replace(/,/g, '').match(/-?\d+(\.\d+)?/);
        return match ? parseFloat(match[0]) : 0;
    }

    function resolvePipFactor(pair) {
        const normalizedPair = normalizePairSymbol(pair);
        const matchedPair = tradingPairMeta.find((item) => normalizePairSymbol(item.symbol) === normalizedPair);

        if (matchedPair && parseFloat(matchedPair.pip_factor) > 0) {
            return parseFloat(matchedPair.pip_factor);
        }

        if (normalizedPair.includes('XAU') || normalizedPair.includes('GOLD')) {
            return 0.1;
        }

        if (normalizedPair.includes('JPY')) {
            return 0.01;
        }

        return 0.0001;
    }

    function updateTargetInsights() {
        const pair = document.querySelector('input[name="trading_pair"]').value;
        const entryPrice = parseSignalPrice(document.querySelector('input[name="entry_price"]').value);
        const pipFactor = resolvePipFactor(pair);
        const lotSize = 0.01;
        const usdPerPipPerLot = 10;

        for (let i = 1; i <= 10; i++) {
            const targetInput = document.querySelector(`input[name="target_${i}"]`);
            const insight = document.querySelector(`[data-target-insight="${i}"]`);
            const targetPrice = parseSignalPrice(targetInput ? targetInput.value : '');

            if (!insight || entryPrice <= 0 || targetPrice <= 0 || pipFactor <= 0) {
                if (insight) insight.textContent = '0.01 lot estimate: -';
                continue;
            }

            const pips = Math.abs(targetPrice - entryPrice) / pipFactor;
            const usd = pips * lotSize * usdPerPipPerLot;
            insight.textContent = `0.01 lot estimate: $${usd.toFixed(2)} (${pips.toFixed(1)} pips)`;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document
            .querySelectorAll('input[name="trading_pair"], input[name="entry_price"], input[name^="target_"]')
            .forEach((input) => input.addEventListener('input', updateTargetInsights));

        updateTargetInsights();

        document.getElementById('editSignal').addEventListener('submit', function () {
            document.getElementById('updatebutton').disabled = true;
            document.getElementById('updatebutton').textContent = 'Updating...';
        });
    });
</script>

@endsection
