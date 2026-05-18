@extends('admin.admin_master')
@section('admin')

<title>Market Analyst Management | HC Gaming Studio</title>

<style>
    .ma-admin-shell {
        color: #1f2937;
    }

    .ma-hero,
    .ma-stat,
    .ma-filter,
    .ma-table-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .ma-hero {
        padding: 22px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .ma-eyebrow {
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ma-hero h4,
    .ma-panel-title {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }

    .ma-hero p,
    .ma-muted {
        color: #64748b;
        margin: 4px 0 0;
    }

    .ma-stat {
        padding: 18px;
        min-height: 116px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    .ma-stat span {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .ma-stat strong {
        display: block;
        color: #0f172a;
        font-size: 30px;
        line-height: 1;
        margin-top: 8px;
    }

    .ma-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .ma-stat-icon.blue { background: #2563eb; }
    .ma-stat-icon.green { background: #16a34a; }
    .ma-stat-icon.amber { background: #d97706; }
    .ma-stat-icon.slate { background: #475569; }

    .ma-filter,
    .ma-table-panel {
        padding: 18px;
    }

    .ma-table-panel th {
        color: #475569;
        font-size: 12px;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .ma-report-title {
        color: #0f172a;
        font-weight: 800;
        margin-bottom: 3px;
    }

    .ma-code {
        display: inline-flex;
        align-items: center;
        border: 1px solid #dbe3ea;
        border-radius: 999px;
        padding: 4px 10px;
        color: #334155;
        background: #f8fafc;
        font-size: 12px;
        font-weight: 700;
    }

    .ma-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
    }

    .ma-status.sent { background: #dcfce7; color: #15803d; }
    .ma-status.pending { background: #fef3c7; color: #92400e; }

    .ma-action-set {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .ma-empty {
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 768px) {
        .ma-hero {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

@php
    $trendLabel = function ($item) {
        $value = strtolower((string) $item->trend_structure);

        if (str_contains($value, 'uptrend') || str_contains($value, 'higher high') || str_contains($value, 'bullish')) {
            return 'Uptrend';
        }

        if (str_contains($value, 'downtrend') || str_contains($value, 'lower high') || str_contains($value, 'bearish')) {
            return 'Downtrend';
        }

        if (str_contains($value, 'ranging') || str_contains($value, 'range') || str_contains($value, 'consolidat')) {
            return 'Ranging';
        }

        return 'Unmapped';
    };

    $discordEnabled = feature_enabled('DiscordIntegration');
@endphp

<div class="page-content ma-admin-shell">
    <div class="container-fluid">

        <div class="ma-hero mb-3">
            <div>
                <div class="ma-eyebrow">Market Intelligence Desk</div>
                <h4>Market Analyst Management</h4>
                <p>Prepare, review, update, and publish analyst outlooks for trading communities.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('trading.market-analyst.index') }}" class="btn btn-outline-secondary" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Trader View
                </a>
                <a href="{{ route('market-analyst.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Analysis
                </a>
            </div>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="ma-stat">
                    <div>
                        <span>Total Reports</span>
                        <strong>{{ number_format($totalOutlook) }}</strong>
                        <div class="ma-muted small">All saved outlooks</div>
                    </div>
                    <span class="ma-stat-icon blue"><i class="fas fa-chart-area"></i></span>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="ma-stat">
                    <div>
                        <span>Sent</span>
                        <strong>{{ number_format($sentOutlook) }}</strong>
                        <div class="ma-muted small">Published to Discord</div>
                    </div>
                    <span class="ma-stat-icon green"><i class="fab fa-discord"></i></span>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="ma-stat">
                    <div>
                        <span>Pending</span>
                        <strong>{{ number_format($unsentOutlook) }}</strong>
                        <div class="ma-muted small">Ready for review</div>
                    </div>
                    <span class="ma-stat-icon amber"><i class="fas fa-clock"></i></span>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="ma-stat">
                    <div>
                        <span>Last 7 Days</span>
                        <strong>{{ number_format($weeklyOutlook) }}</strong>
                        <div class="ma-muted small">Recent research flow</div>
                    </div>
                    <span class="ma-stat-icon slate"><i class="fas fa-calendar-week"></i></span>
                </div>
            </div>
        </div>

        <div class="ma-filter mb-3">
            <form method="GET" action="{{ route('market-analyst.index') }}" class="row g-2 align-items-end">
                <div class="col-xl-4 col-lg-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" class="form-control" placeholder="Title, market, code, analyst notes">
                </div>
                <div class="col-xl-3 col-lg-6">
                    <label for="community_id" class="form-label">Community</label>
                    <select name="community_id" id="community_id" class="form-control">
                        <option value="">All Communities</option>
                        @foreach($communities as $community)
                            <option value="{{ $community->id }}" {{ (string) request('community_id') === (string) $community->id ? 'selected' : '' }}>
                                {{ $community->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-6">
                    <label for="market" class="form-label">Market</label>
                    <select name="market" id="market" class="form-control">
                        <option value="">All Markets</option>
                        @foreach($markets as $market)
                            <option value="{{ $market }}" {{ request('market') === $market ? 'selected' : '' }}>{{ $market }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-lg-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('market-analyst.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>

        <div class="ma-table-panel">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="ma-eyebrow">Research Ledger</div>
                    <h5 class="ma-panel-title">All Analyst Reports</h5>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Report</th>
                            <th>Market</th>
                            <th>Community</th>
                            <th>Structure</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($analyses as $item)
                            <tr>
                                <td>
                                    <div class="ma-report-title">{{ $item->title }}</div>
                                    <span class="ma-code">{{ $item->Outlook_Code ?? 'No code' }}</span>
                                    @if($item->market_overview)
                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($item->market_overview, 110) }}</div>
                                    @endif
                                </td>
                                <td><strong>{{ $item->market }}</strong></td>
                                <td>{{ $item->community?->name ?? 'No community' }}</td>
                                <td>{{ $trendLabel($item) }}</td>
                                <td>{{ $item->analysis_date?->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if($item->discord_sent)
                                        <span class="ma-status sent">Sent</span>
                                    @else
                                        <span class="ma-status pending">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ma-action-set">
                                        <a href="{{ route('market-analyst.show', $item->id) }}" class="btn btn-secondary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('market-analyst.edit', $item->id) }}" class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('market-analyst.sendDiscord', $item->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-warning btn-sm" title="{{ $item->discord_sent ? 'Update Discord message' : 'Send to Discord' }}" {{ $discordEnabled ? '' : 'disabled' }}>
                                                <i class="fab fa-discord"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('market-analyst.destroy', $item->id) }}"
                                           class="btn btn-danger btn-sm"
                                           title="Delete"
                                           onclick="return confirm('Delete this market analysis?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="ma-empty">
                                        <h5>No market analysis reports found</h5>
                                        <p class="mb-0">Create the first report or adjust the filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $analyses->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>
    </div>
</div>

@endsection
