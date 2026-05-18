@extends('admin.admin_master')
@section('admin')

@php
    $rewardEligible = false;
    $rewardAmount = 0;
    $rewardReason = feature_enabled('signal_payout') ? 'Not eligible for reward' : 'Reward system is currently disabled';

    if (feature_enabled('signal_payout') && $score >= 60 && !in_array($grade, ['Not Qualified'], true)) {
        $rewardEligible = true;
        $rewardReason = 'Qualified based on total score';
        $rewardAmount = match ($grade) {
            'Expert Signal Provider' => 150,
            'Senior Signal Provider' => 100,
            'Junior Signal Provider' => 50,
            'Intern Signal Provider' => 20,
            default => 0,
        };
    }

    $providerName = $selectedProvider?->username ?: ($selectedProvider?->name ?: 'All Providers');
    $dateLabel = ($from_date || $to_date)
        ? (($from_date ?: 'Start') . ' to ' . ($to_date ?: now()->format('Y-m-d')))
        : 'All available records';
    $scoreTone = $score >= 85 ? 'expert' : ($score >= 75 ? 'senior' : ($score >= 60 ? 'junior' : ($score >= 50 ? 'intern' : 'danger')));
    $groupedPerformances = $performances->groupBy(function ($perf) {
        return ($perf->signal ? 'main_' : 'backup_') . $perf->signal_id;
    });
    $latestPerformance = $performances->sortByDesc('created_at')->first();
    $latestTradeDate = optional($latestPerformance?->created_at)->format('d M Y');
@endphp

<style>
    .signal-admin {
        background: #eef3f8;
        color: #172033;
        min-height: 100vh;
    }

    .signal-shell {
        container-type: inline-size;
        margin: 0 auto;
        max-width: 1760px;
        padding: 26px 30px 42px;
    }

    .signal-hero {
        background: #111827;
        border: 1px solid #22304a;
        border-radius: 12px;
        color: #f8fafc;
        display: grid;
        gap: 24px;
        grid-template-columns: minmax(0, 1fr) 330px;
        margin-bottom: 18px;
        padding: 28px;
    }

    .signal-kicker {
        color: #2dd4bf;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .signal-hero h3 {
        color: #ffffff;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.18;
        margin: 8px 0;
    }

    .signal-hero p {
        color: #aab7ca;
        font-size: 14px;
        margin: 0;
        max-width: 900px;
    }

    .score-card {
        background: #18243a;
        border: 1px solid #2f3d59;
        border-radius: 12px;
        min-width: 0;
        padding: 18px;
    }

    .score-top {
        align-items: center;
        display: flex;
        gap: 12px;
        justify-content: space-between;
    }

    .score-number {
        color: #ffffff;
        font-size: 46px;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1;
    }

    .score-label {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .level-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        justify-content: center;
        line-height: 1;
        padding: 8px 10px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .level-pill.expert,
    .level-pill.senior { background: rgba(45, 212, 191, .14); color: #5eead4; }
    .level-pill.junior { background: rgba(99, 102, 241, .18); color: #c7d2fe; }
    .level-pill.intern { background: rgba(245, 158, 11, .18); color: #fcd34d; }
    .level-pill.danger { background: rgba(225, 29, 72, .18); color: #fda4af; }

    .score-meta {
        color: #cbd5e1;
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin-top: 18px;
    }

    .score-meta span {
        color: #94a3b8;
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .signal-panel {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        margin-bottom: 18px;
        min-width: 0;
        padding: 18px;
    }

    .panel-title {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .panel-title h5 {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0;
    }

    .panel-title span {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .filter-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .filter-grid .form-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .action-row .btn {
        align-items: center;
        display: inline-flex;
        font-weight: 800;
        gap: 7px;
        justify-content: center;
        min-height: 38px;
    }

    .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .metric-card {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-radius: 10px;
        min-height: 104px;
        min-width: 0;
        padding: 15px;
        position: relative;
    }

    .metric-card::before {
        background: #0f766e;
        border-radius: 999px;
        content: "";
        height: 4px;
        left: 15px;
        position: absolute;
        right: 15px;
        top: 0;
    }

    .metric-card.indigo::before { background: #4f46e5; }
    .metric-card.amber::before { background: #d97706; }
    .metric-card.rose::before { background: #e11d48; }
    .metric-card.slate::before { background: #64748b; }

    .metric-card span {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .metric-card strong {
        color: #0f172a;
        display: block;
        font-size: 22px;
        font-weight: 900;
        line-height: 1.1;
        word-break: break-word;
    }

    .metric-card small {
        color: #64748b;
        display: block;
        font-weight: 700;
        margin-top: 7px;
    }

    .analysis-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.3fr) minmax(320px, .8fr);
    }

    .criteria-list {
        display: grid;
        gap: 12px;
    }

    .criteria-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
    }

    .criteria-head {
        align-items: flex-start;
        display: flex;
        gap: 12px;
        justify-content: space-between;
    }

    .criteria-head strong {
        color: #0f172a;
        display: block;
        font-size: 14px;
        font-weight: 900;
    }

    .criteria-head span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-top: 3px;
    }

    .criteria-score {
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        white-space: nowrap;
    }

    .score-track {
        background: #e2e8f0;
        border-radius: 999px;
        height: 9px;
        margin: 11px 0 8px;
        overflow: hidden;
    }

    .score-track span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .criteria-card p,
    .meaning-card p {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
        margin: 0;
    }

    .meaning-card {
        background: #101827;
        border-radius: 12px;
        color: #ffffff;
        padding: 18px;
    }

    .meaning-card h5 {
        color: #ffffff;
        font-size: 16px;
        font-weight: 900;
        margin: 0 0 10px;
    }

    .meaning-card p {
        color: #cbd5e1;
    }

    .level-ladder {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .level-step {
        align-items: center;
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 9px;
        display: flex;
        justify-content: space-between;
        padding: 10px 12px;
    }

    .level-step span {
        color: #cbd5e1;
        font-weight: 800;
    }

    .level-step strong {
        color: #ffffff;
    }

    .table-card {
        overflow: hidden;
    }

    .signal-table {
        margin-bottom: 0;
    }

    .signal-table thead th {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .signal-table td,
    .signal-table th {
        border-color: #e2e8f0;
        color: #334155;
        font-size: 13px;
        vertical-align: middle;
    }

    .outcome-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        padding: 7px 9px;
        text-transform: uppercase;
    }

    .outcome-win { background: #dcfce7; color: #15803d; }
    .outcome-loss { background: #ffe4e6; color: #be123c; }
    .outcome-pending { background: #e0e7ff; color: #4338ca; }

    .text-profit { color: #0f766e !important; }
    .text-loss { color: #e11d48 !important; }

    @container (max-width: 1380px) {
        .signal-hero,
        .analysis-grid {
            grid-template-columns: 1fr;
        }

        .filter-grid,
        .metric-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @container (max-width: 820px) {
        .filter-grid,
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .signal-shell {
            padding: 18px 12px 30px;
        }

        .signal-hero {
            padding: 20px;
        }

        .filter-grid,
        .metric-grid,
        .score-meta {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content signal-admin">
    <div class="container-fluid signal-shell">
        <div class="signal-hero">
            <div>
                <div class="signal-kicker">{{ $canViewAll ? 'Administration Console' : 'Signal Provider Console' }}</div>
                <h3>Signal Performance Report</h3>
                <p>
                    Professional performance review for {{ $providerName }} across {{ $dateLabel }}.
                    The report combines trade outcomes, pips, risk reward, profit factor, expectancy, level, and scoring criteria.
                </p>
            </div>

            <div class="score-card">
                <div class="score-top">
                    <div>
                        <div class="score-label">Total Score</div>
                        <div class="score-number">{{ $score }}</div>
                    </div>
                    <span class="level-pill {{ $scoreTone }}">{{ $grade }}</span>
                </div>
                <div class="score-meta">
                    <div>
                        <span>Provider</span>
                        <strong>{{ $providerName }}</strong>
                    </div>
                    <div>
                        <span>Latest Signal</span>
                        <strong>{{ $latestTradeDate ?: 'N/A' }}</strong>
                    </div>
                    <div>
                        <span>Reward</span>
                        <strong>{{ $rewardEligible ? 'RM ' . number_format($rewardAmount, 0) : 'RM 0' }}</strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong>{{ $rewardReason }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="signal-panel">
            <div class="panel-title">
                <h5>Filters And Report Actions</h5>
                <span>{{ $performances->count() }} raw performance rows</span>
            </div>

            <form method="GET" action="{{ route('signal.performance.index') }}">
                <div class="filter-grid">
                    <div>
                        <label for="quick_range" class="form-label">Quick Range</label>
                        <select id="quick_range" class="form-control">
                            <option value="">Custom</option>
                            <option value="today">Today</option>
                            <option value="7days">Last 7 Days</option>
                            <option value="30days">Last 30 Days</option>
                            <option value="90days">Last 90 Days</option>
                        </select>
                    </div>
                    <div>
                        <label for="from_date" class="form-label">From</label>
                        <input type="date" id="from_date" name="from_date" class="form-control" value="{{ $from_date ?? '' }}">
                    </div>
                    <div>
                        <label for="to_date" class="form-label">To</label>
                        <input type="date" id="to_date" name="to_date" class="form-control" value="{{ $to_date ?? '' }}">
                    </div>
                    <div>
                        <label for="community_id" class="form-label">Community</label>
                        <select name="community_id" id="community_id" class="form-control">
                            <option value="">All Communities</option>
                            @foreach($communities as $community)
                                <option value="{{ $community->id }}" {{ request('community_id') == $community->id ? 'selected' : '' }}>
                                    {{ $community->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="community_tag" class="form-label">Community Tag</label>
                        <input type="text" name="community_tag" id="community_tag" class="form-control" value="{{ request('community_tag') ?? '' }}" placeholder="Exact tag">
                    </div>
                    @if($canViewAll)
                        <div>
                            <label for="user_id" class="form-label">Provider</label>
                            <select name="user_id" id="user_id" class="form-control">
                                <option value="">All Providers</option>
                                @foreach($providers as $provider)
                                    <option value="{{ $provider->id }}" {{ request('user_id') == $provider->id ? 'selected' : '' }}>
                                        {{ $provider->username ?: $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="action-row">
                    <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line"></i>Filter</button>
                    <a href="{{ route('signal.performance.index') }}" class="btn btn-light border"><i class="ri-refresh-line"></i>Reset</a>
                    <a href="{{ route('signal.performance.export', request()->query()) }}" class="btn btn-dark"><i class="ri-file-excel-2-line"></i>Export Excel</a>
                    <a href="{{ route('signal.performance.report.pdf', request()->query()) }}" class="btn btn-danger"><i class="ri-file-pdf-line"></i>Download PDF</a>
                    <a href="{{ route('signal.performance.template') }}" class="btn btn-outline-dark"><i class="ri-download-cloud-line"></i>Template</a>
                </div>
            </form>

            @if($canViewAll)
                <div class="action-row">
                    <form method="POST" action="{{ route('signal.performance.sendDiscord') }}">
                        @csrf
                        @foreach($activeFilters as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="btn btn-success"><i class="ri-send-plane-line"></i>Send Discord Summary</button>
                    </form>
                    <form method="POST" action="{{ route('signal.performance.sendDiscordWeekly') }}">
                        @csrf
                        @foreach($activeFilters as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="btn btn-info text-white"><i class="ri-calendar-check-line"></i>Send Weekly Discord</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <span>Total Trades</span>
                <strong>{{ number_format($totalTrades) }}</strong>
                <small>{{ number_format($totalWinTrades) }} wins / {{ number_format($totalLoseTrades) }} losses</small>
            </div>
            <div class="metric-card {{ $totalPips >= 0 ? '' : 'rose' }}">
                <span>Total Pips</span>
                <strong class="{{ $totalPips >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($totalPips, 2) }}</strong>
                <small>{{ number_format($totalProfitPips, 2) }} win pips / {{ number_format($totalLossPips, 2) }} loss pips</small>
            </div>
            <div class="metric-card indigo">
                <span>Win Rate</span>
                <strong>{{ number_format($winRate, 2) }}%</strong>
                <small>{{ $scoreWinRate }}/30 points</small>
            </div>
            <div class="metric-card amber">
                <span>Risk Reward</span>
                <strong>{{ $riskRewardFormatted }}</strong>
                <small>{{ $scoreRR }}/30 points</small>
            </div>
            <div class="metric-card slate">
                <span>Profit Factor</span>
                <strong>{{ number_format($profitFactor, 2) }}</strong>
                <small>{{ $profitFactorPoints }}/20 points</small>
            </div>
            <div class="metric-card">
                <span>Expectancy</span>
                <strong>{{ number_format($expectancy, 2) }}</strong>
                <small>{{ $scoreExpectancy }}/20 points</small>
            </div>
        </div>

        <div class="analysis-grid">
            <div class="signal-panel">
                <div class="panel-title">
                    <h5>Score Breakdown</h5>
                    <span>Criteria used for Junior, Senior, and Expert levels</span>
                </div>
                <div class="criteria-list">
                    @foreach($scoreBreakdown as $criteria)
                        @php
                            $width = $criteria['max'] > 0 ? min(100, ($criteria['points'] / $criteria['max']) * 100) : 0;
                        @endphp
                        <div class="criteria-card">
                            <div class="criteria-head">
                                <div>
                                    <strong>{{ $criteria['name'] }}</strong>
                                    <span>{{ $criteria['value'] }} | Grade {{ $criteria['grade'] }}</span>
                                </div>
                                <div class="criteria-score">{{ $criteria['points'] }}/{{ $criteria['max'] }}</div>
                            </div>
                            <div class="score-track"><span style="width: {{ $width }}%;"></span></div>
                            <p>{{ $criteria['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="meaning-card">
                <h5>Evaluation Meaning</h5>
                <p>{{ $summary['performanceMeaning'] ?? 'No evaluation available for the selected filters.' }}</p>
                <div class="level-ladder">
                    <div class="level-step"><span>Junior</span><strong>60-74</strong></div>
                    <div class="level-step"><span>Senior</span><strong>75-84</strong></div>
                    <div class="level-step"><span>Expert</span><strong>85-100</strong></div>
                </div>
            </div>
        </div>

        <div class="signal-panel table-card">
            <div class="panel-title">
                <h5>Signal Performance Records</h5>
                <span>{{ $groupedPerformances->count() }} grouped signals</span>
            </div>
            <div class="table-responsive">
                <table id="signalPerformanceTable" class="table signal-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Pair</th>
                            <th>Action</th>
                            <th>Entry</th>
                            <th>SL</th>
                            <th>TP Hit</th>
                            <th>Outcome</th>
                            <th>Pips</th>
                            <th>USD</th>
                            <th>Provider</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedPerformances as $signalGroup)
                            @php
                                $perf = $signalGroup->first();
                                $signal = $perf->signal ?? $perf->backupSignal;
                                $rowPips = $signalGroup->sum('profit_pips');
                                $rowUsd = $signalGroup->sum('profit_usd');
                                $tpHit = $signalGroup->max('tp_hit') ?? 0;
                                $totalTp = $signal ? collect([
                                    $signal->target_1, $signal->target_2, $signal->target_3, $signal->target_4, $signal->target_5,
                                    $signal->target_6, $signal->target_7, $signal->target_8, $signal->target_9, $signal->target_10,
                                ])->filter(fn ($tp) => !is_null($tp))->count() : 0;
                                $isSL = $signalGroup->contains('is_sl', true);
                                $outcomeClass = $isSL ? 'outcome-loss' : ($tpHit > 0 ? 'outcome-win' : 'outcome-pending');
                                $outcomeLabel = $isSL ? 'Loss' : ($tpHit > 0 ? 'Win' : 'Pending');
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $signal->signal_code ?? '-' }}</strong></td>
                                <td>{{ strtoupper($signal->trading_pair ?? '-') }}</td>
                                <td>{{ strtoupper($signal->immediate_action ?? '-') }}</td>
                                <td>{{ $signal->entry_price ?? '-' }}</td>
                                <td>{{ $isSL ? ($signal->stop_loss ?? '-') : '-' }}</td>
                                <td>{{ $tpHit }}/{{ $totalTp }}</td>
                                <td><span class="outcome-badge {{ $outcomeClass }}">{{ $outcomeLabel }}</span></td>
                                <td class="{{ $rowPips >= 0 ? 'text-profit' : 'text-loss' }}">{{ number_format($rowPips, 2) }}</td>
                                <td>${{ number_format($rowUsd, 2) }}</td>
                                <td>{{ $signal?->user?->username ?? '-' }}</td>
                                <td>{{ optional($perf->created_at)->format('d M Y') ?? optional($signal?->created_at)->format('d M Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No signal performances found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var quickRange = document.getElementById('quick_range');
        var fromDate = document.getElementById('from_date');
        var toDate = document.getElementById('to_date');

        function formatDate(date) {
            return date.toISOString().slice(0, 10);
        }

        if (quickRange) {
            quickRange.addEventListener('change', function () {
                var today = new Date();
                var start = null;

                if (this.value === 'today') {
                    start = today;
                } else if (this.value === '7days') {
                    start = new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000);
                } else if (this.value === '30days') {
                    start = new Date(today.getTime() - 29 * 24 * 60 * 60 * 1000);
                } else if (this.value === '90days') {
                    start = new Date(today.getTime() - 89 * 24 * 60 * 60 * 1000);
                }

                if (start && fromDate && toDate) {
                    fromDate.value = formatDate(start);
                    toDate.value = formatDate(today);
                }
            });
        }

        if (window.jQuery && jQuery.fn.DataTable && document.getElementById('signalPerformanceTable')) {
            jQuery('#signalPerformanceTable').DataTable({
                pageLength: 25,
                order: [[11, 'desc']],
                responsive: true
            });
        }
    });
</script>

@endsection
