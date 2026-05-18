@extends('admin.admin_master')
@section('admin')
@php
    $formatNumber = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $formatRrr = fn ($value) => is_numeric($value) ? number_format((float) $value, 2) : $value;
    $scoreWidth = fn ($value) => min(100, max(0, (float) $value));
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

        return $letters ?: 'HC';
    };
    $selectedRole = $filters['role'] ?? 'all';
    $selectedMonth = $filters['month'] ?? 'all';
    $selectedYear = $filters['year'] ?? 'all';
@endphp

<title>Trading Leaderboard | HC Gaming</title>

<style>
    .leaderboard-page {
        color: #172033;
    }

    .leaderboard-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .leaderboard-hero::after {
        content: "";
        position: absolute;
        right: -80px;
        top: -120px;
        width: 280px;
        height: 280px;
        background: rgba(45, 212, 191, 0.16);
        border-radius: 50%;
    }

    .leaderboard-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .leaderboard-hero p,
    .leaderboard-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .leaderboard-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .leaderboard-summary,
    .leaderboard-panel,
    .leaderboard-rank-card,
    .leaderboard-podium {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .leaderboard-summary {
        padding: 18px;
        height: 100%;
    }

    .leaderboard-summary span,
    .leaderboard-panel .muted,
    .leaderboard-rank-card .muted,
    .leaderboard-podium .muted {
        color: #607086;
        font-size: 13px;
    }

    .leaderboard-summary strong {
        color: #101827;
        display: block;
        font-size: 24px;
        line-height: 1.2;
        margin-top: 6px;
    }

    .leaderboard-rank-card {
        padding: 22px;
    }

    .rank-number {
        align-items: center;
        background: #0f766e;
        border-radius: 10px;
        color: #fff;
        display: inline-flex;
        font-size: 24px;
        font-weight: 800;
        height: 64px;
        justify-content: center;
        min-width: 86px;
        padding: 0 16px;
    }

    .leaderboard-filter {
        align-items: end;
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(160px, 1fr) repeat(2, minmax(130px, 180px)) auto auto;
    }

    .leaderboard-filter label {
        color: #344256;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .leaderboard-filter .form-control {
        border-color: #cbd5e1;
        color: #111827;
        height: 42px;
    }

    .leaderboard-panel {
        padding: 22px;
    }

    .role-pill {
        align-items: center;
        background: #eef6ff;
        border: 1px solid #cfe4ff;
        border-radius: 999px;
        color: #164a7a;
        display: inline-flex;
        font-size: 13px;
        font-weight: 700;
        gap: 8px;
        margin: 0 8px 8px 0;
        padding: 8px 12px;
    }

    .leaderboard-podium {
        padding: 18px;
        height: 100%;
    }

    .leaderboard-podium.top-1 {
        border-color: #f4c542;
    }

    .leaderboard-podium.top-2 {
        border-color: #b8c1cc;
    }

    .leaderboard-podium.top-3 {
        border-color: #d69b67;
    }

    .podium-rank {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 13px;
        font-weight: 800;
        height: 34px;
        justify-content: center;
        padding: 0 12px;
    }

    .top-1 .podium-rank {
        background: #fff7d6;
        color: #8a5b00;
    }

    .top-2 .podium-rank {
        background: #eef2f7;
        color: #435063;
    }

    .top-3 .podium-rank {
        background: #fff1e5;
        color: #894d14;
    }

    .avatar-wrap {
        align-items: center;
        display: flex;
        gap: 12px;
        min-width: 220px;
    }

    .leaderboard-avatar {
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

    .leaderboard-table {
        border-collapse: separate;
        border-spacing: 0;
        color: #172033;
        width: 100%;
    }

    .leaderboard-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .leaderboard-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .leaderboard-table tbody tr.is-current-user td {
        background: #ecfdf5;
        border-bottom-color: #9de7c2;
        border-top: 2px solid #10b981;
    }

    .leaderboard-table tbody tr.is-current-user td:first-child {
        border-left: 4px solid #10b981;
    }

    .rank-chip {
        align-items: center;
        background: #e5e7eb;
        border-radius: 999px;
        color: #111827;
        display: inline-flex;
        font-weight: 800;
        height: 32px;
        justify-content: center;
        min-width: 54px;
        padding: 0 10px;
    }

    .rank-chip.rank-gold {
        background: #fff7d6;
        color: #8a5b00;
    }

    .rank-chip.rank-silver {
        background: #eef2f7;
        color: #435063;
    }

    .rank-chip.rank-bronze {
        background: #fff1e5;
        color: #894d14;
    }

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

    .rating-badge,
    .you-badge,
    .role-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 9px;
    }

    .rating-badge {
        background: #e0f2fe;
        color: #075985;
    }

    .you-badge {
        background: #dcfce7;
        color: #166534;
        margin-left: 8px;
    }

    .role-badge {
        background: #f1f5f9;
        color: #334155;
    }

    .metric-positive {
        color: #047857;
        font-weight: 800;
    }

    .metric-negative {
        color: #b91c1c;
        font-weight: 800;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #111827;
        margin-left: 8px;
        min-height: 34px;
        padding: 6px 10px;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
        color: #475569;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #607086;
        padding: 34px;
        text-align: center;
    }

    @media (max-width: 991px) {
        .leaderboard-filter {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 575px) {
        .leaderboard-hero,
        .leaderboard-panel,
        .leaderboard-rank-card {
            padding: 18px;
        }

        .leaderboard-filter {
            grid-template-columns: 1fr;
        }

        .leaderboard-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content leaderboard-page">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Trading Leaderboard</h4>
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

        <section class="leaderboard-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-xl-7">
                    <div class="eyebrow">{{ $viewerContext['eyebrow'] }}</div>
                    <h1>{{ $viewerContext['headline'] }}</h1>
                    <p>{{ $viewerContext['description'] }}</p>
                </div>
                <div class="col-xl-5 mt-4 mt-xl-0">
                    <form method="GET" action="{{ route('trading.leaderboard.index') }}" class="leaderboard-filter">
                        <div>
                            <label for="role">Role</label>
                            <select id="role" name="role" class="form-control">
                                <option value="all" {{ $selectedRole === 'all' ? 'selected' : '' }}>All ranked roles</option>
                                @foreach ($roleOptions as $roleId => $label)
                                    <option value="{{ $roleId }}" {{ (string) $selectedRole === (string) $roleId ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
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
                        <a href="{{ route('trading.leaderboard.index') }}" class="btn btn-light">Reset</a>
                    </form>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="leaderboard-summary">
                    <span>Ranked Members</span>
                    <strong>{{ number_format($summary['ranked_members']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="leaderboard-summary">
                    <span>Trades Logged</span>
                    <strong>{{ number_format($summary['total_trades']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="leaderboard-summary">
                    <span>Average Score</span>
                    <strong>{{ $formatNumber($summary['average_score']) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="leaderboard-summary">
                    <span>Top Rating</span>
                    <strong>{{ $summary['top_rating'] }} / {{ $formatNumber($summary['best_score']) }}</strong>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-xl-5 mb-4 mb-xl-0">
                <div class="leaderboard-rank-card h-100">
                    <div class="d-flex align-items-start justify-content-between flex-wrap">
                        <div>
                            <span class="muted">Your Position</span>
                            @if ($currentPlacement)
                                <h4 class="mt-2 mb-1">{{ $currentPlacement['user']->name ?? $currentPlacement['user']->username }}</h4>
                                <div class="muted">{{ $currentPlacement['role_label'] }} ranking under current filter</div>
                            @else
                                <h4 class="mt-2 mb-1">Not ranked in this view</h4>
                                <div class="muted">Record trades in your journal or adjust the filters to see your placement.</div>
                            @endif
                        </div>
                        @if ($currentPlacement)
                            <div class="rank-number mt-3 mt-sm-0">#{{ $currentPlacement['rank'] }}</div>
                        @endif
                    </div>

                    @if ($currentPlacement)
                        <div class="row mt-4">
                            <div class="col-6 col-md-3">
                                <span class="muted">Score</span>
                                <h5 class="mb-0">{{ $formatNumber($currentPlacement['score']) }}</h5>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="muted">Rating</span>
                                <h5 class="mb-0">{{ $currentPlacement['rating'] }}</h5>
                            </div>
                            <div class="col-6 col-md-3 mt-3 mt-md-0">
                                <span class="muted">Win Rate</span>
                                <h5 class="mb-0">{{ $formatNumber($currentPlacement['win_rate']) }}%</h5>
                            </div>
                            <div class="col-6 col-md-3 mt-3 mt-md-0">
                                <span class="muted">Trades</span>
                                <h5 class="mb-0">{{ $currentPlacement['total_trades'] }}</h5>
                            </div>
                        </div>
                        <button type="button" id="jumpMyRank" class="btn btn-success mt-4">
                            <i class="ri-focus-3-line mr-1"></i> Jump to my rank
                        </button>
                    @endif
                </div>
            </div>

            <div class="col-xl-7">
                <div class="leaderboard-panel h-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                        <div>
                            <h5 class="mb-1">Role Coverage</h5>
                            <div class="muted">Ranks are generated from trading journal data in the selected period.</div>
                        </div>
                    </div>
                    @forelse ($roleBreakdown as $role)
                        <span class="role-pill">
                            {{ $role['label'] }}
                            <strong>{{ $role['count'] }}</strong>
                            <span>{{ $formatNumber($role['average_score']) }} avg</span>
                        </span>
                    @empty
                        <div class="empty-state">No ranked roles found for the selected filters.</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if (count($topThree) > 0)
            <div class="row mb-4">
                @foreach ($topThree as $item)
                    @php
                        $user = $item['user'];
                        $avatar = $avatarUrl($user);
                        $name = $user->name ?: $user->username;
                    @endphp
                    <div class="col-xl-4 mb-4 mb-xl-0">
                        <div class="leaderboard-podium top-{{ $item['rank'] }}">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="podium-rank">Rank #{{ $item['rank'] }}</span>
                                <span class="rating-badge">{{ $item['rating'] }}</span>
                            </div>
                            <div class="avatar-wrap mb-3">
                                @if ($avatar)
                                    <img src="{{ $avatar }}" class="leaderboard-avatar" alt="{{ $name }}">
                                @else
                                    <span class="leaderboard-avatar">{{ $initials($name) }}</span>
                                @endif
                                <div>
                                    <h5 class="mb-1">{{ $name }}</h5>
                                    <span class="role-badge">{{ $item['role_label'] }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <span class="muted">Score</span>
                                    <h5 class="mb-0">{{ $formatNumber($item['score']) }}</h5>
                                </div>
                                <div class="col-4">
                                    <span class="muted">Win</span>
                                    <h5 class="mb-0">{{ $formatNumber($item['win_rate']) }}%</h5>
                                </div>
                                <div class="col-4">
                                    <span class="muted">Trades</span>
                                    <h5 class="mb-0">{{ $item['total_trades'] }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="leaderboard-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                <div>
                    <h5 class="mb-1">Ranked Performance Table</h5>
                    <div class="muted">Score combines win rate, reward-to-risk, growth, drawdown, expectancy, consistency, and trade sample size context.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="leaderboard" class="leaderboard-table table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Score</th>
                            <th>Rating</th>
                            <th>Trades</th>
                            <th>Win Rate</th>
                            <th>Avg RRR</th>
                            <th>Growth</th>
                            <th>Drawdown</th>
                            <th>Expectancy</th>
                            <th>Consistency</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaderboard as $item)
                            @php
                                $user = $item['user'];
                                $name = $user->name ?: $user->username;
                                $avatar = $avatarUrl($user);
                                $rankClass = match ($item['rank']) {
                                    1 => 'rank-gold',
                                    2 => 'rank-silver',
                                    3 => 'rank-bronze',
                                    default => '',
                                };
                            @endphp
                            <tr class="{{ $item['is_current_user'] ? 'is-current-user' : '' }}">
                                <td data-order="{{ $item['rank'] }}">
                                    <span class="rank-chip {{ $rankClass }}">#{{ $item['rank'] }}</span>
                                </td>
                                <td>
                                    <div class="avatar-wrap">
                                        @if ($avatar)
                                            <img src="{{ $avatar }}" class="leaderboard-avatar" alt="{{ $name }}">
                                        @else
                                            <span class="leaderboard-avatar">{{ $initials($name) }}</span>
                                        @endif
                                        <div>
                                            <strong>{{ $name }}</strong>
                                            @if ($item['is_current_user'])
                                                <span class="you-badge">You</span>
                                            @endif
                                            <div class="muted">{{ $user->username ?? $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="role-badge">{{ $item['role_label'] }}</span></td>
                                <td data-order="{{ $item['score'] }}">
                                    <strong>{{ $formatNumber($item['score']) }}</strong>
                                    <div class="score-meter">
                                        <span style="width: {{ $scoreWidth($item['score']) }}%;"></span>
                                    </div>
                                </td>
                                <td><span class="rating-badge">{{ $item['rating'] }}</span></td>
                                <td data-order="{{ $item['total_trades'] }}">
                                    <strong>{{ $item['total_trades'] }}</strong>
                                    <div class="muted">{{ $item['confidence'] }}</div>
                                </td>
                                <td data-order="{{ $item['win_rate'] }}">{{ $formatNumber($item['win_rate']) }}%</td>
                                <td data-order="{{ is_numeric($item['avg_rrr']) ? $item['avg_rrr'] : 999 }}">{{ $formatRrr($item['avg_rrr']) }}</td>
                                <td data-order="{{ $item['growth'] }}" class="{{ $item['growth'] >= 0 ? 'metric-positive' : 'metric-negative' }}">
                                    {{ $formatNumber($item['growth']) }}%
                                </td>
                                <td data-order="{{ $item['drawdown'] }}" class="{{ $item['drawdown'] <= 10 ? 'metric-positive' : 'metric-negative' }}">
                                    {{ $formatNumber($item['drawdown']) }}%
                                </td>
                                <td data-order="{{ $item['expectancy'] }}" class="{{ $item['expectancy'] >= 0 ? 'metric-positive' : 'metric-negative' }}">
                                    {{ $formatNumber($item['expectancy']) }}
                                </td>
                                <td data-order="{{ $item['consistency_percent'] }}">
                                    <strong>{{ $item['consistency'] }}</strong>
                                    <div class="muted">{{ $formatNumber($item['consistency_percent']) }}%</div>
                                </td>
                                <td>
                                    <a href="{{ route('trading.leaderboard.showTrader', $user->id) }}" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">
                                    <div class="empty-state">No trading journal records found for the selected leaderboard filters.</div>
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
        const table = $('#leaderboard').DataTable({
            order: [[0, 'asc']],
            pageLength: 25,
            responsive: false,
            language: {
                search: 'Search leaderboard:',
                lengthMenu: 'Show _MENU_ ranked members',
                emptyTable: 'No ranked members found'
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
                const row = document.querySelector('#leaderboard tbody tr.is-current-user');
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        });
    });
</script>
@endsection
