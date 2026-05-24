@extends('admin.admin_master')
@section('admin')

@php
    $isProfit = (float) $journal->profit_loss >= 0;
    $directionLabel = $journal->direction == 1 ? 'Buy' : ($journal->direction == 2 ? 'Sell' : 'Unknown');
    $directionClass = $journal->direction == 1 ? 'bg-success' : ($journal->direction == 2 ? 'bg-danger' : 'bg-secondary');
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
    $journalTime = app(\App\Services\TradingJournalTimeService::class);
    $selectedTimeView = $journalTime->normalizeMode($selectedTimeView ?? request('time_view'));
    $selectedTimeViewOffset = $journalTime->normalizeOffset($selectedTimeViewOffset ?? request('mt5_offset_minutes', $journal->time_input_offset_minutes ?? null), $selectedTimeView);
    $selectedMt5ViewOffset = $journalTime->normalizeOffset(request('mt5_offset_minutes', $journal->time_input_offset_minutes ?? null), \App\Services\TradingJournalTimeService::TIMEZONE_MT5);
@endphp

<title>Trading Journal Details | HC Gaming Studio</title>

<style>
    .journal-detail-page {
        color: #1f2937;
    }

    .journal-detail-page .page-title-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .journal-detail-page .page-title-box h4 {
        margin: 0;
        color: #111827;
        font-weight: 700;
    }

    .journal-detail-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
    }

    .journal-detail-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 24px;
        border-bottom: 1px solid #edf0f4;
    }

    .journal-detail-symbol {
        margin: 0 0 8px;
        color: #111827;
        font-size: 28px;
        font-weight: 800;
    }

    .journal-detail-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .journal-detail-body {
        padding: 24px;
    }

    .journal-metric {
        padding: 16px;
        border: 1px solid #edf0f4;
        border-radius: 8px;
        background: #fbfcfe;
        height: 100%;
    }

    .journal-metric span {
        display: block;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .journal-metric strong {
        color: #111827;
        font-size: 20px;
    }

    .journal-notes {
        min-height: 130px;
        padding: 18px;
        border: 1px solid #edf0f4;
        border-radius: 8px;
        background: #fbfcfe;
        white-space: pre-line;
    }

    @media (max-width: 575.98px) {
        .journal-detail-page .page-title-box,
        .journal-detail-header {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>

<div class="page-content journal-detail-page">
    <div class="container-fluid">
        <div class="page-title-box">
            <div>
                <h4>Trade Details</h4>
                <ol class="breadcrumb m-0 mt-2">
                    <li class="breadcrumb-item"><a href="{{ route('all.trading.journals') }}">Trading Journal</a></li>
                    <li class="breadcrumb-item active">Trade Details</li>
                </ol>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('edit.trading.journal', $journal->id) }}" class="btn btn-primary">
                    <i class="mdi mdi-pencil-outline me-1"></i> Edit Trade
                </a>
                <a href="{{ route('all.trading.journals') }}" class="btn btn-light">
                    <i class="mdi mdi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="journal-detail-panel">
            <div class="journal-detail-header">
                <div>
                    <h3 class="journal-detail-symbol">{{ strtoupper($journal->pair) }}</h3>
                    <div class="journal-detail-meta">
                        <span class="badge {{ $directionClass }}">{{ $directionLabel }}</span>
                        <span class="badge {{ $resultClass }}">{{ $resultLabel }}</span>
                        <span class="badge bg-light text-dark">Saved from {{ $journalTime->label($journal->time_input_timezone ?? null, $journal->time_input_offset_minutes ?? null) }}</span>
                        @if($tradingPair && $tradingPair->description)
                            <span class="badge bg-light text-dark">{{ $tradingPair->description }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="text-muted">Profit / Loss</div>
                    <h3 class="mb-0 {{ $isProfit ? 'text-success' : 'text-danger' }}">
                        {{ number_format((float) $journal->profit_loss, 2) }}u
                    </h3>
                </div>
            </div>

            <div class="journal-detail-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Open Time ({{ $journalTime->shortLabel($selectedTimeView, $selectedTimeViewOffset) }})</span>
                            <strong>{{ $journalTime->formatForDisplay($journal->open_date, $selectedTimeView, $selectedTimeViewOffset) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Close Time ({{ $journalTime->shortLabel($selectedTimeView, $selectedTimeViewOffset) }})</span>
                            <strong>{{ $journalTime->formatForDisplay($journal->close_date, $selectedTimeView, $selectedTimeViewOffset) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Entry Price</span>
                            <strong>{{ $journal->entry_price }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Exit Price</span>
                            <strong>{{ $journal->exit_price }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Pips</span>
                            <strong>{{ $journal->pips }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Lot Size</span>
                            <strong>{{ $journal->lot_size }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Direction</span>
                            <strong>{{ $directionLabel }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="journal-metric">
                            <span>Result</span>
                            <strong>{{ $resultLabel }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="journal-metric">
                            <span>Malaysia Time</span>
                            <strong>Open: {{ $journalTime->formatForDisplay($journal->open_date, \App\Services\TradingJournalTimeService::TIMEZONE_MALAYSIA) }}</strong>
                            <strong>Close: {{ $journalTime->formatForDisplay($journal->close_date, \App\Services\TradingJournalTimeService::TIMEZONE_MALAYSIA) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="journal-metric">
                            <span>MT5 Platform Time</span>
                            <strong>Open: {{ $journalTime->formatForDisplay($journal->open_date, \App\Services\TradingJournalTimeService::TIMEZONE_MT5, $selectedMt5ViewOffset) }}</strong>
                            <strong>Close: {{ $journalTime->formatForDisplay($journal->close_date, \App\Services\TradingJournalTimeService::TIMEZONE_MT5, $selectedMt5ViewOffset) }}</strong>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3">Notes</h5>
                <div class="journal-notes">{{ $journal->notes ?: 'No notes recorded.' }}</div>
            </div>
        </div>
    </div>
</div>

@endsection
