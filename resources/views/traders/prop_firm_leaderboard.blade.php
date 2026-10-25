@extends('admin.admin_master')
@section('admin')
@php
    $formatNumber = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $formatRrr = fn ($value) => is_numeric($value) ? number_format((float) $value, 2) : $value;
    $scoreWidth = fn ($value) => min(100, max(0, (float) $value));
    $selectedMonth = $filters['month'] ?? 'all';
    $selectedYear = $filters['year'] ?? 'all';
    $currentUserRole = (int) (auth()->user()->role_id ?? 0);
    $isAdminView = in_array($currentUserRole, [1, 2], true);
    $avatarUrl = function ($user) {
        $path = $user->profile_image ?? null;

        return $path && file_exists(public_path($path)) ? asset($path) : null;
    };
    $initials = function ($name) {
        $parts = preg_split('/\s+/', trim((string) $name));
        $letters = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= strtoupper(substr($part, 0, 1));
            }

            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters ?: 'PF';
    };
    $reviewUrl = function ($item) use ($isAdminView, $selectedMonth, $selectedYear) {
        if ($isAdminView) {
            return route('admin.trader.journals.index', array_filter([
                'user_id' => $item['user']->id,
                'journal_source' => 'all',
                'account_stage' => $item['stage'],
                'month' => $selectedMonth === 'all' ? null : $selectedMonth,
                'year' => $selectedYear === 'all' ? null : $selectedYear,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        if ((int) auth()->id() === (int) $item['user']->id) {
            return route('all.trading.statistics', [
                'account_stage' => $item['stage'],
                'month' => $selectedMonth,
                'year' => $selectedYear,
            ]);
        }

        return null;
    };
@endphp

<title>Prop Firm Leaderboard | HC Gaming</title>

<style>
    .prop-leaderboard {
        color: #172033;
    }

    .prop-hero,
    .prop-panel,
    .prop-summary,
    .prop-podium {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }

    .prop-hero {
        padding: 24px;
    }

    .prop-eyebrow {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .prop-hero h1 {
        color: #0f172a;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .prop-hero p,
    .prop-muted {
        color: #607086;
    }

    .prop-stage-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .prop-stage-tab {
        align-items: center;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        color: #334155;
        display: inline-flex;
        font-weight: 800;
        gap: 8px;
        padding: 9px 14px;
    }

    .prop-stage-tab.active {
        background: #0f766e;
        border-color: #0f766e;
        color: #fff;
    }

    .prop-filter {
        align-items: end;
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(2, minmax(140px, 1fr)) auto auto;
    }

    .prop-filter label {
        color: #344256;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .prop-filter .form-control {
        border-color: #cbd5e1;
        min-height: 42px;
    }

    .prop-summary {
        height: 100%;
        padding: 18px;
    }

    .prop-summary span,
    .prop-podium span,
    .prop-table .muted {
        color: #607086;
        font-size: 13px;
    }

    .prop-summary strong {
        color: #101827;
        display: block;
        font-size: 24px;
        line-height: 1.2;
        margin-top: 6px;
    }

    .prop-panel {
        padding: 22px;
    }

    .prop-podium {
        height: 100%;
        padding: 18px;
    }

    .prop-rank {
        align-items: center;
        background: #e5e7eb;
        border-radius: 999px;
        color: #111827;
        display: inline-flex;
        font-weight: 800;
        min-width: 54px;
        padding: 7px 10px;
        justify-content: center;
    }

    .prop-rank.gold { background: #fff7d6; color: #8a5b00; }
    .prop-rank.silver { background: #eef2f7; color: #435063; }
    .prop-rank.bronze { background: #fff1e5; color: #894d14; }

    .prop-avatar-wrap {
        align-items: center;
        display: flex;
        gap: 12px;
        min-width: 230px;
    }

    .prop-avatar {
        align-items: center;
        background: #dbeafe;
        border: 1px solid #bfdbfe;
        border-radius: 50%;
        color: #1d4ed8;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 13px;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        object-fit: cover;
        width: 42px;
    }

    .prop-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 9px;
    }

    .prop-badge.info { background: #e0f2fe; color: #075985; }
    .prop-badge.success { background: #dcfce7; color: #166534; }
    .prop-badge.warning { background: #fef3c7; color: #92400e; }
    .prop-badge.danger { background: #fee2e2; color: #991b1b; }
    .prop-badge.secondary { background: #f1f5f9; color: #334155; }

    .score-meter {
        background: #e5e7eb;
        border-radius: 999px;
        height: 7px;
        margin-top: 8px;
        overflow: hidden;
        width: 116px;
    }

    .score-meter span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .prop-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .prop-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .prop-table tbody tr.is-current-user td {
        background: #ecfdf5;
        border-bottom-color: #9de7c2;
        border-top: 2px solid #10b981;
    }

    .metric-positive {
        color: #047857;
        font-weight: 800;
    }

    .metric-negative {
        color: #b91c1c;
        font-weight: 800;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        color: #607086;
        padding: 34px;
        text-align: center;
    }

    @media (max-width: 991px) {
        .prop-filter {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 575px) {
        .prop-filter {
            grid-template-columns: 1fr;
        }

        .prop-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content prop-leaderboard">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Prop Firm Leaderboard</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            @foreach ($breadcrumbData as $breadcrumb)
                                <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                                    @if ($loop->last)
                                        {{ $breadcrumb['label'] }}
                                    @else
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="prop-hero mb-4">
            <div class="row align-items-center g-3">
                <div class="col-xl-7">
                    <div class="prop-eyebrow">Prop Firm Evaluation</div>
                    <h1>{{ $selectedStageMeta['label'] }}</h1>
                    <p class="mb-0">{{ $selectedStageMeta['description'] }}</p>
                </div>
                <div class="col-xl-5">
                    <div class="prop-stage-tabs justify-content-xl-end mb-3">
                        @foreach($stageOptions as $stageKey => $stage)
                            <a href="{{ route('propfirm.leaderboard.index', array_filter(['stage' => $stageKey, 'month' => $selectedMonth, 'year' => $selectedYear], fn($value) => $value !== null && $value !== '')) }}"
                               class="prop-stage-tab {{ $selectedStage === $stageKey ? 'active' : '' }}">
                                {{ $stage['short_label'] }}
                                <small>{{ $stage['target'] }}</small>
                            </a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ route('propfirm.leaderboard.index') }}" class="prop-filter">
                        <input type="hidden" name="stage" value="{{ $selectedStage }}">
                        <div>
                            <label for="month">Month</label>
                            <select id="month" name="month" class="form-control">
                                <option value="all" {{ $selectedMonth === 'all' ? 'selected' : '' }}>All months</option>
                                @foreach ($monthOptions as $month => $label)
                                    <option value="{{ $month }}" {{ $selectedMonth === $month ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="year">Year</label>
                            <select id="year" name="year" class="form-control">
                                <option value="all" {{ $selectedYear === 'all' ? 'selected' : '' }}>All years</option>
                                @foreach ($availableYears as $year)
                                    <option value="{{ $year }}" {{ (string) $selectedYear === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="ri-filter-3-line mr-1"></i> Apply
                        </button>
                        <a href="{{ route('propfirm.leaderboard.index', ['stage' => $selectedStage]) }}" class="btn btn-light">Reset</a>
                    </form>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="prop-summary">
                    <span>Ranked Traders</span>
                    <strong>{{ number_format($summary['ranked_traders']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="prop-summary">
                    <span>Total Trades</span>
                    <strong>{{ number_format($summary['total_trades']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="prop-summary">
                    <span>Average Score</span>
                    <strong>{{ $formatNumber($summary['average_score']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="prop-summary">
                    <span>Net P/L</span>
                    <strong class="{{ $summary['total_net_pl'] >= 0 ? 'metric-positive' : 'metric-negative' }}">
                        {{ $formatNumber($summary['total_net_pl']) }}
                    </strong>
                </div>
            </div>
        </div>

        @if($currentPlacement)
            <div class="prop-panel mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="prop-eyebrow">Your Position</div>
                        <h5 class="mb-1">#{{ $currentPlacement['rank'] }} - {{ $currentPlacement['user']->name ?: $currentPlacement['user']->username }}</h5>
                        <div class="prop-muted">{{ $currentPlacement['stage_label'] }} | {{ $currentPlacement['rule_status'] }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <div><span class="prop-muted d-block">Score</span><strong>{{ $formatNumber($currentPlacement['score']) }}</strong></div>
                        <div><span class="prop-muted d-block">Progress</span><strong>{{ $formatNumber($currentPlacement['profit_target_progress']) }}%</strong></div>
                        <div><span class="prop-muted d-block">Trades</span><strong>{{ number_format($currentPlacement['total_trades']) }}</strong></div>
                    </div>
                    <button type="button" id="jumpMyRank" class="btn btn-success">
                        <i class="ri-focus-3-line mr-1"></i> Jump to my rank
                    </button>
                </div>
            </div>
        @endif

        @if(count($topThree) > 0)
            <div class="row mb-4">
                @foreach($topThree as $item)
                    @php
                        $user = $item['user'];
                        $name = $user->name ?: $user->username;
                        $avatar = $avatarUrl($user);
                    @endphp
                    <div class="col-xl-4 mb-4 mb-xl-0">
                        <div class="prop-podium">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="prop-rank {{ $item['rank'] === 1 ? 'gold' : ($item['rank'] === 2 ? 'silver' : 'bronze') }}">#{{ $item['rank'] }}</span>
                                <span class="prop-badge info">{{ $item['rating'] }}</span>
                            </div>
                            <div class="prop-avatar-wrap mb-3">
                                @if($avatar)
                                    <img src="{{ $avatar }}" class="prop-avatar" alt="{{ $name }}">
                                @else
                                    <span class="prop-avatar">{{ $initials($name) }}</span>
                                @endif
                                <div>
                                    <h5 class="mb-1">{{ $name }}</h5>
                                    <span class="prop-badge secondary">{{ $item['rule_status'] }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <span>Score</span>
                                    <h5 class="mb-0">{{ $formatNumber($item['score']) }}</h5>
                                </div>
                                <div class="col-4">
                                    <span>Progress</span>
                                    <h5 class="mb-0">{{ $formatNumber($item['profit_target_progress']) }}%</h5>
                                </div>
                                <div class="col-4">
                                    <span>Trades</span>
                                    <h5 class="mb-0">{{ number_format($item['total_trades']) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="prop-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                <div>
                    <h5 class="mb-1">Prop Firm Performance Table</h5>
                    <div class="prop-muted">Score ranks prop firm traders by trading quality, target progress, drawdown control, rule compliance, and behaviour risk.</div>
                </div>
                <span class="prop-badge {{ $summary['rule_breaches'] > 0 ? 'warning' : 'success' }}">
                    {{ number_format($summary['rule_breaches']) }} rule breach{{ $summary['rule_breaches'] === 1 ? '' : 'es' }}
                </span>
            </div>

            <div class="table-responsive">
                <table id="propFirmLeaderboard" class="table table-hover align-middle prop-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Trader</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Trades</th>
                            <th>Net P/L</th>
                            <th>Target</th>
                            <th>Win Rate</th>
                            <th>Avg RRR</th>
                            <th>Growth</th>
                            <th>Drawdown</th>
                            <th>Daily Loss</th>
                            <th>Total Loss</th>
                            <th>Behaviour</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaderboard as $item)
                            @php
                                $user = $item['user'];
                                $name = $user->name ?: $user->username;
                                $avatar = $avatarUrl($user);
                                $rankClass = match ($item['rank']) {
                                    1 => 'gold',
                                    2 => 'silver',
                                    3 => 'bronze',
                                    default => '',
                                };
                                $targetTone = $item['profit_target_passed'] ? 'success' : 'warning';
                                $dailyTone = $item['daily_loss_breached'] ? 'metric-negative' : 'metric-positive';
                                $totalTone = $item['total_loss_breached'] ? 'metric-negative' : 'metric-positive';
                                $url = $reviewUrl($item);
                            @endphp
                            <tr class="{{ $item['is_current_user'] ? 'is-current-user' : '' }}">
                                <td data-order="{{ $item['rank'] }}"><span class="prop-rank {{ $rankClass }}">#{{ $item['rank'] }}</span></td>
                                <td>
                                    <div class="prop-avatar-wrap">
                                        @if($avatar)
                                            <img src="{{ $avatar }}" class="prop-avatar" alt="{{ $name }}">
                                        @else
                                            <span class="prop-avatar">{{ $initials($name) }}</span>
                                        @endif
                                        <div>
                                            <strong>{{ $name }}</strong>
                                            @if($item['is_current_user'])
                                                <span class="prop-badge success ms-1">You</span>
                                            @endif
                                            <div class="muted">{{ $user->username ?? $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-order="{{ $item['score'] }}">
                                    <strong>{{ $formatNumber($item['score']) }}</strong>
                                    <span class="prop-badge info ms-1">{{ $item['rating'] }}</span>
                                    <div class="score-meter"><span style="width: {{ $scoreWidth($item['score']) }}%;"></span></div>
                                </td>
                                <td>
                                    <span class="prop-badge {{ $item['daily_loss_breached'] || $item['total_loss_breached'] ? 'danger' : 'success' }}">
                                        {{ $item['rule_status'] }}
                                    </span>
                                    <div class="muted">Rule adj {{ $item['rule_score_adjustment'] >= 0 ? '+' : '' }}{{ $formatNumber($item['rule_score_adjustment']) }}</div>
                                </td>
                                <td data-order="{{ $item['total_trades'] }}">
                                    <strong>{{ number_format($item['total_trades']) }}</strong>
                                    <div class="muted">{{ $item['confidence'] }}</div>
                                </td>
                                <td data-order="{{ $item['net_pl'] }}" class="{{ $item['net_pl'] >= 0 ? 'metric-positive' : 'metric-negative' }}">
                                    {{ $formatNumber($item['net_pl']) }}
                                </td>
                                <td data-order="{{ $item['profit_target_progress'] }}">
                                    <span class="prop-badge {{ $targetTone }}">{{ $formatNumber($item['profit_target_progress']) }}%</span>
                                    <div class="muted">
                                        @if($item['profit_target_percent'] > 0)
                                            {{ $formatNumber($item['profit_target_percent']) }}% target
                                        @else
                                            Funded growth
                                        @endif
                                    </div>
                                </td>
                                <td data-order="{{ $item['win_rate'] }}">{{ $formatNumber($item['win_rate']) }}%</td>
                                <td data-order="{{ is_numeric($item['avg_rrr']) ? $item['avg_rrr'] : 999 }}">{{ $formatRrr($item['avg_rrr']) }}</td>
                                <td data-order="{{ $item['growth'] }}" class="{{ $item['growth'] >= 0 ? 'metric-positive' : 'metric-negative' }}">{{ $formatNumber($item['growth']) }}%</td>
                                <td data-order="{{ $item['drawdown'] }}" class="{{ $item['drawdown'] <= 10 ? 'metric-positive' : 'metric-negative' }}">{{ $formatNumber($item['drawdown']) }}%</td>
                                <td data-order="{{ $item['daily_loss_percent'] }}" class="{{ $dailyTone }}">{{ $formatNumber($item['daily_loss_percent']) }}%</td>
                                <td data-order="{{ $item['total_loss_percent'] }}" class="{{ $totalTone }}">{{ $formatNumber($item['total_loss_percent']) }}%</td>
                                <td data-order="{{ $item['behaviour_score'] }}">
                                    {{ $formatNumber($item['behaviour_score']) }}/100
                                    <div class="muted">{{ $item['behaviour_tier'] }}</div>
                                </td>
                                <td>
                                    @if($url)
                                        <a href="{{ $url }}" class="btn btn-sm btn-outline-primary">Review</a>
                                    @else
                                        <span class="muted">{{ $item['last_trade_at'] ?? '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15">
                                    <div class="empty-state">No prop firm journal records found for this stage and date filter.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const currentRank = @json($currentPlacement['rank'] ?? null);
        const table = $('#propFirmLeaderboard').DataTable({
            order: [[0, 'asc']],
            pageLength: 25,
            responsive: false,
            language: {
                search: 'Search prop firm leaderboard:',
                lengthMenu: 'Show _MENU_ traders',
                emptyTable: 'No prop firm traders found'
            }
        });

        $('#jumpMyRank').on('click', function () {
            if (!currentRank) {
                return;
            }

            const pageLength = table.page.info().length || 25;
            const targetPage = Math.max(0, Math.floor((currentRank - 1) / pageLength));
            table.page(targetPage).draw('page');

            setTimeout(function () {
                const row = document.querySelector('#propFirmLeaderboard tbody tr.is-current-user');
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        });
    });
</script>
@endsection
