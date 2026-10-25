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
    $behaviorRecovery = data_get($behaviorProfile, 'behavior_recovery', []);
    $behaviorRecoveryBlocks = collect(data_get($behaviorRecovery, 'blocks', []));
    $behaviorRecoveryApplied = (float) data_get($behaviorRecovery, 'total_reduction_percent', 0) > 0;
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
                    <div class="text-muted small">Professional tiered revenge and gambling scores from the selected journal records.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    These results are coaching indicators, not accusations. Gambling and revenge are scored separately from 0 to 100, then final behaviour score is max(gambling, revenge). Order count, two layers, position count, and holding time do not classify a trade by themselves. After a behavior event, every completed {{ number_format((int) data_get($behaviorRecovery, 'block_size', 8)) }}-trade block can reduce the behavior score when the latest block shows improvement.
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
                            <strong>{{ number_format((float) data_get($behaviorProfile, 'final_score', data_get($behaviorProfile, 'risk_score', 0)), 2) }}/100</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Raw Behavior Risk</span>
                            <strong>{{ number_format((float) data_get($behaviorProfile, 'raw_risk_score', data_get($behaviorProfile, 'final_score', 0)), 2) }}/100</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>8-Trade Recovery</span>
                            <strong>{{ $behaviorRecoveryApplied ? '-' . number_format((float) data_get($behaviorRecovery, 'total_reduction_percent', 0), 2) . '%' : data_get($behaviorRecovery, 'status', 'No Recovery') }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Tier</span>
                            <strong>{{ data_get($behaviorProfile, 'tier_label', 'Normal') }}</strong>
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
                    <div class="col-md-3">
                        <div class="journal-modal-row h-100">
                            <span>Confidence</span>
                            <strong>{{ number_format((float) data_get($behaviorProfile, 'confidence_score', 0), 2) }}/100</strong>
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
                    <h6 class="mb-2">8-Trade Behavior Recovery Rule</h6>
                    <div class="alert {{ $behaviorRecoveryApplied ? 'alert-success' : 'alert-light' }}">
                        {{ data_get($behaviorRecovery, 'summary', 'No behavior recovery adjustment is available yet.') }}
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="table-responsive">
                                <table class="table journal-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Block Tier</th>
                                            <th>Reduction Rule</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(data_get($behaviorRecovery, 'rules', []) as $ruleKey => $ruleText)
                                            @if($ruleKey !== 'block_size')
                                                <tr>
                                                    <td><strong>{{ ucfirst($ruleKey) }}</strong></td>
                                                    <td>{{ $ruleText }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="table-responsive">
                                <table class="table journal-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Completed Block</th>
                                            <th>Tier</th>
                                            <th>Reduction</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($behaviorRecoveryBlocks as $block)
                                            <tr>
                                                <td>
                                                    <strong>#{{ data_get($block, 'block_number') }}</strong>
                                                    <div class="small text-muted">{{ data_get($block, 'first_trade_label') }} - {{ data_get($block, 'last_trade_label') }}</div>
                                                </td>
                                                <td><span class="badge {{ $badgeClass(data_get($block, 'tier_tone', 'secondary')) }}">{{ data_get($block, 'tier_label', 'N/A') }}</span></td>
                                                <td>{{ number_format((float) data_get($block, 'reduction_percent', 0), 2) }}%</td>
                                                <td>{{ number_format((float) data_get($block, 'score_before', 0), 2) }} -> {{ number_format((float) data_get($block, 'score_after', 0), 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">No completed recovery block yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
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
                                <tr><td><span class="badge bg-success">Normal</span></td><td>0-24 score. Controlled risk evidence or no strong gambling evidence.</td><td>Planned layer within risk, safe margin, valid stop loss, setup risk within plan.</td></tr>
                                <tr><td><span class="badge bg-primary">Low</span></td><td>25-44 score. Mild discipline warning.</td><td>Minor plan or execution warning without dangerous risk stacking.</td></tr>
                                <tr><td><span class="badge bg-warning text-dark">Mid</span></td><td>45-64 score. Meaningful discipline warnings.</td><td>Missing plan details, poor RR, overtrading, or risk pressure begins to stack.</td></tr>
                                <tr><td><span class="badge bg-danger">High</span></td><td>65-79 score. Strong discipline break evidence.</td><td>No stop loss with high risk, setup risk breach, dangerous margin, or random trading markers.</td></tr>
                                <tr><td><span class="badge bg-danger">Critical</span></td><td>80-100 score. Severe account-risk evidence.</td><td>Daily loss breach, extreme risk above plan, or dangerous exposure with missing controls.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        A planned layer can reduce gambling score only when it follows the setup, risk, stop-loss, margin, and daily-loss rules.
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
                                <tr><td><span class="badge bg-success">Normal</span></td><td>0-24 score. No loss-chasing evidence.</td><td>Another order alone is not revenge trading.</td></tr>
                                <tr><td><span class="badge bg-primary">Low</span></td><td>25-44 score. Mild post-loss warning.</td><td>Previous loss evidence with limited follow-through.</td></tr>
                                <tr><td><span class="badge bg-warning text-dark">Mid</span></td><td>45-64 score. Meaningful revenge markers.</td><td>Cooldown pressure, same-symbol chasing, emotional tag, or loss streak pressure.</td></tr>
                                <tr><td><span class="badge bg-danger">High</span></td><td>65-79 score. Strong post-loss escalation.</td><td>Opened immediately after loss with bigger lot or increased risk.</td></tr>
                                <tr><td><span class="badge bg-danger">Critical</span></td><td>80-100 score. Severe account-risk evidence.</td><td>Daily loss limit exceeded or severe loss-chasing risk escalation.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">
                        Revenge trading requires post-loss evidence such as cooldown breach, risk increase, lot increase, daily-loss breach, or loss-streak pressure.
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="mb-2">How Revenge Trading Is Detected</h6>
                    <p class="text-muted mb-3">
                        The system checks whether the trader is chasing losses. It does not classify a trade as revenge only because it is another order or another layer.
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
