@php
    $behaviorModalId = $behaviorModalId ?? 'behaviorDetectionModal';
    $behaviorProfile = $traderStyleProfile ?? [];
    $behaviorTime = $journalTime ?? app(\App\Services\TradingJournalTimeService::class);
    $behaviorTimeView = $selectedTimeView ?? request('time_view');
    $behaviorTimeOffset = $selectedTimeViewOffset ?? request('mt5_offset_minutes');
    $revengeChecks = collect(data_get($behaviorProfile, 'revenge.checks', []));
    $revengeExamples = collect(data_get($behaviorProfile, 'revenge.examples', []))->take(5);
    $layeringChecks = collect(data_get($behaviorProfile, 'layering.checks', []));
    $layeringExamples = collect(data_get($behaviorProfile, 'layering.examples', []))->take(6);
    $gamblingChecks = collect(data_get($behaviorProfile, 'gambling.checks', []));
    $gamblingExamples = collect(data_get($behaviorProfile, 'gambling.examples', []))->take(6);
    $badgeClass = function ($tone) {
        return match ($tone) {
            'danger' => 'bg-danger',
            'warning' => 'bg-warning text-dark',
            'success' => 'bg-success',
            'primary' => 'bg-primary',
            default => 'bg-secondary',
        };
    };
    $formatBehaviorTime = function ($value) use ($behaviorTime, $behaviorTimeView, $behaviorTimeOffset) {
        return $value ? $behaviorTime->formatForDisplay($value, $behaviorTimeView, $behaviorTimeOffset) : 'N/A';
    };
@endphp

<style>
    .behavior-detection-modal .journal-modal-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border: 1px solid #edf0f4;
        border-radius: 8px;
        background: #fbfcfe;
    }

    .behavior-detection-modal .journal-modal-row span {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .behavior-detection-modal .journal-modal-row strong {
        text-align: right;
    }

    .behavior-detection-modal .journal-table th {
        color: #475569;
        font-size: 12px;
        letter-spacing: .03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .behavior-detection-modal .journal-table td {
        vertical-align: middle;
    }
</style>

<div class="modal fade behavior-detection-modal" id="{{ $behaviorModalId }}" tabindex="-1" aria-labelledby="{{ $behaviorModalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $behaviorModalId }}Label">Behavior Detection Explanation</h5>
                    <div class="text-muted small">Tiered revenge trading, layering, and gambling-style signals from the selected journal records.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    These results are coaching indicators, not accusations. The system now tiers revenge and gambling-style behavior as Low, Medium, or High based on how many risk markers stack together in timing, lot sizing, trade frequency, losses, and holding period.
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Trader Style</span>
                            <strong>{{ data_get($behaviorProfile, 'style_label', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Behavior Risk</span>
                            <strong>{{ number_format((float) data_get($behaviorProfile, 'risk_score', 0), 2) }}/100</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Revenge Trading</span>
                            <strong>{{ data_get($behaviorProfile, 'revenge.status', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Gambling Behavior</span>
                            <strong>{{ data_get($behaviorProfile, 'gambling.status', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="journal-modal-row h-100">
                            <span>Revenge Tier Method</span>
                            <strong>{{ data_get($behaviorProfile, 'revenge.tier_description', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="journal-modal-row h-100">
                            <span>Gambling Tier Method</span>
                            <strong>{{ data_get($behaviorProfile, 'gambling.tier_description', 'N/A') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Layering</span>
                            <strong>{{ data_get($behaviorProfile, 'layering.status', 'N/A') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="mb-2">Gambling Behaviour Tier Rules</h6>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Detected Pattern</th>
                                    <th>Example Signals</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-primary">Low</span></td>
                                    <td>Mild high-variance evidence only, without clear oversized exposure.</td>
                                    <td>Rapid entry within 10 minutes while lot size is same/reduced, or a small same-direction layer without large active risk.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">Medium</span></td>
                                    <td>Multiple gambling-style markers combine, lot size increases meaningfully above the trader's recent or account-average baseline, or the trade idea is already using too much of the account risk budget.</td>
                                    <td>Lot size 1.5x+ above normal sizing, XAUUSD margin pressure at 1:30 leverage, layered exposure, oversized realized loss, rapid entry plus size increase, or a much shorter hold than the account normally shows.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">High</span></td>
                                    <td>Stacked pressure where active lot/layer exposure uses high XAUUSD margin, lot size is far above the account average, or larger sizing combines with speed/short holding/oversized realized loss.</td>
                                    <td>3+ same-direction layers, high XAUUSD active margin use at 1:30 leverage, lot size 2x+ above the account average/median, lot size doubled above baseline with speed/overtrading, or oversized loss combined with rapid entry.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        Overall gambling tier can also rise when several lower-tier gambling examples repeat across the selected journal records.
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="mb-2">Revenge Behaviour Tier Rules</h6>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Detected Pattern</th>
                                    <th>Example Signals</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-primary">Low</span></td>
                                    <td>Quick re-entry after a losing trade, but without meaningful lot increase or stacked emotional markers.</td>
                                    <td>New order opened within 60 minutes after a loss, with same/reduced lot size and limited extra signals.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">Medium</span></td>
                                    <td>Post-loss reaction has stronger evidence but is not the most aggressive pattern.</td>
                                    <td>Lot size up 1.5x, immediate re-entry on the same pair, direction flip after loss, or reaction after a loss streak.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">High</span></td>
                                    <td>Aggressive post-loss reaction where speed and larger sizing or repeated loss pressure appear together.</td>
                                    <td>Re-entry within 15 minutes with 1.5x+ lot increase, lot size doubled, or loss-streak pressure followed by fast larger sizing.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        Overall revenge tier can also rise when repeated post-loss reactions appear across the selected journal records.
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="mb-2">How Revenge Trading Is Detected</h6>
                    <p class="text-muted mb-3">
                        The system checks what happens after a losing trade. Low tier usually means quick re-entry without clear lot escalation. Medium tier adds stronger evidence such as lot increase, same-pair pressure, direction flip, or loss streak reaction. High tier appears when fast post-loss reaction stacks with aggressive lot sizing or repeated loss pressure.
                    </p>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Current Value</th>
                                    <th>Status</th>
                                    <th>Meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revengeChecks as $check)
                                    <tr>
                                        <td><strong>{{ data_get($check, 'name', 'N/A') }}</strong></td>
                                        <td>{{ data_get($check, 'value', 'N/A') }}</td>
                                        <td><span class="badge {{ $badgeClass(data_get($check, 'tone')) }}">{{ data_get($check, 'status', 'N/A') }}</span></td>
                                        <td>{{ data_get($check, 'description', '-') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted">No revenge-trading checks are available yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2 mt-4">Sample Orders Used For Revenge Signals</h6>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Loss Order</th>
                                    <th>Next Order</th>
                                    <th>Tier</th>
                                    <th>Pair</th>
                                    <th>Delay</th>
                                    <th>Lot Change</th>
                                    <th>Signals</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revengeExamples as $event)
                                    <tr>
                                        <td>
                                            <strong>{{ data_get($event, 'trigger_trade_label', 'N/A') }}</strong>
                                            <div class="small text-danger">Loss {{ number_format((float) data_get($event, 'loss_amount', 0), 2) }}u</div>
                                            <div class="small text-muted">{{ $formatBehaviorTime(data_get($event, 'trigger_closed_at')) }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ data_get($event, 'response_trade_label', 'N/A') }}</strong>
                                            <div class="small {{ (float) data_get($event, 'response_profit_loss', 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                P/L {{ number_format((float) data_get($event, 'response_profit_loss', 0), 2) }}u
                                            </div>
                                            <div class="small text-muted">{{ $formatBehaviorTime(data_get($event, 'response_opened_at')) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $badgeClass(data_get($event, 'tier_tone')) }}">{{ data_get($event, 'tier_label', 'N/A') }}</span>
                                            <div class="small text-muted">{{ data_get($event, 'tier_description', '-') }}</div>
                                        </td>
                                        <td>{{ data_get($event, 'pair', 'N/A') }}</td>
                                        <td>{{ data_get($event, 'delay_label', 'N/A') }}</td>
                                        <td>{{ number_format((float) data_get($event, 'previous_lot', 0), 4) }} -> {{ number_format((float) data_get($event, 'response_lot', 0), 4) }}</td>
                                        <td>{{ collect(data_get($event, 'signals', []))->implode(', ') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted">No revenge-trading example orders were flagged for the selected records.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h6 class="mb-2">How Layering Is Detected</h6>
                    <p class="text-muted mb-3">
                        The system checks for same-pair, same-direction orders that are stacked close together or overlap. It highlights higher concern when the trader adds larger lots, keeps multiple layers active, or averages into an adverse price move.
                    </p>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Current Value</th>
                                    <th>Status</th>
                                    <th>Meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($layeringChecks as $check)
                                    <tr>
                                        <td><strong>{{ data_get($check, 'name', 'N/A') }}</strong></td>
                                        <td>{{ data_get($check, 'value', 'N/A') }}</td>
                                        <td><span class="badge {{ $badgeClass(data_get($check, 'tone')) }}">{{ data_get($check, 'status', 'N/A') }}</span></td>
                                        <td>{{ data_get($check, 'description', '-') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted">No layering checks are available yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2 mt-4">Sample Orders Used For Layering Signals</h6>
                    <div class="table-responsive mb-4">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Base Order</th>
                                    <th>Layer Order</th>
                                    <th>Pair</th>
                                    <th>Side</th>
                                    <th>Delay</th>
                                    <th>Lot Change</th>
                                    <th>Entry Change</th>
                                    <th>Signals</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($layeringExamples as $event)
                                    <tr>
                                        <td>
                                            <strong>{{ data_get($event, 'base_trade_label', 'N/A') }}</strong>
                                            <div class="small {{ (float) data_get($event, 'base_profit_loss', 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                P/L {{ number_format((float) data_get($event, 'base_profit_loss', 0), 2) }}u
                                            </div>
                                            <div class="small text-muted">{{ $formatBehaviorTime(data_get($event, 'base_opened_at')) }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ data_get($event, 'layer_trade_label', 'N/A') }}</strong>
                                            <div class="small {{ (float) data_get($event, 'layer_profit_loss', 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                P/L {{ number_format((float) data_get($event, 'layer_profit_loss', 0), 2) }}u
                                            </div>
                                            <div class="small text-muted">{{ $formatBehaviorTime(data_get($event, 'layer_opened_at')) }}</div>
                                        </td>
                                        <td>{{ data_get($event, 'pair', 'N/A') }}</td>
                                        <td>{{ data_get($event, 'direction', 'N/A') }}</td>
                                        <td>{{ data_get($event, 'delay_label', 'N/A') }}</td>
                                        <td>{{ number_format((float) data_get($event, 'base_lot', 0), 4) }} -> {{ number_format((float) data_get($event, 'layer_lot', 0), 4) }}</td>
                                        <td>{{ number_format((float) data_get($event, 'base_entry', 0), 5) }} -> {{ number_format((float) data_get($event, 'layer_entry', 0), 5) }}</td>
                                        <td>{{ collect(data_get($event, 'signals', []))->implode(', ') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No layering example orders were flagged for the selected records.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2">How Gambling-style Behavior Is Detected</h6>
                    <p class="text-muted mb-3">
                        The system looks for high-variance behavior: too many trades in one day, unstable position sizing, lot size far above the account average/median, losses above 3% of capital, rapid-fire entries, profit concentration, duration that is much shorter than the trader's normal holding time, layered same-direction exposure, and XAUUSD margin pressure at 1:30 leverage. Lot-size increases are compared with both the trader's recent baseline and the overall account average, so returning from a temporary smaller lot back toward normal size is not treated as high gambling behavior by itself. High-impact news-window speculation is a manual review factor unless precise news timestamps and account model rules are available; when those fields are available, short trades opened around the restricted window should be treated as additional gambling evidence.
                    </p>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Current Value</th>
                                    <th>Points</th>
                                    <th>Status</th>
                                    <th>Meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gamblingChecks as $check)
                                    <tr>
                                        <td><strong>{{ data_get($check, 'name', 'N/A') }}</strong></td>
                                        <td>{{ data_get($check, 'value', 'N/A') }}</td>
                                        <td>{{ number_format((float) data_get($check, 'points', 0), 2) }} / {{ number_format((float) data_get($check, 'max_points', 0), 0) }}</td>
                                        <td><span class="badge {{ $badgeClass(data_get($check, 'tone')) }}">{{ data_get($check, 'status', 'N/A') }}</span></td>
                                        <td>{{ data_get($check, 'description', '-') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">No gambling-behavior checks are available yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2 mt-4">Sample Orders Used For Gambling-style Signals</h6>
                    <div class="table-responsive">
                        <table class="table journal-table align-middle">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Open Time</th>
                                    <th>Tier</th>
                                    <th>Pair</th>
                                    <th>Side</th>
                                    <th>Lot</th>
                                    <th>P/L</th>
                                    <th>Why Flagged</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gamblingExamples as $example)
                                    <tr>
                                        <td><strong>{{ data_get($example, 'trade_label', 'N/A') }}</strong></td>
                                        <td>{{ $formatBehaviorTime(data_get($example, 'opened_at')) }}</td>
                                        <td>
                                            <span class="badge {{ $badgeClass(data_get($example, 'tier_tone')) }}">{{ data_get($example, 'tier_label', 'N/A') }}</span>
                                            <div class="small text-muted">{{ data_get($example, 'tier_description', '-') }}</div>
                                        </td>
                                        <td>{{ data_get($example, 'pair', 'N/A') }}</td>
                                        <td>{{ data_get($example, 'direction', 'N/A') }}</td>
                                        <td>{{ number_format((float) data_get($example, 'lot_size', 0), 4) }}</td>
                                        <td class="{{ (float) data_get($example, 'profit_loss', 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format((float) data_get($example, 'profit_loss', 0), 2) }}u
                                        </td>
                                        <td>
                                            <strong>{{ data_get($example, 'reason', 'N/A') }}</strong>
                                            <div class="small text-muted">{{ data_get($example, 'evidence_label', '-') }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No gambling-style example orders were flagged for the selected records.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
