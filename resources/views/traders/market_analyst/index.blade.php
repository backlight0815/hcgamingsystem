@extends('admin.admin_master')
@section('admin')

<title>Market Analyst | HC Gaming Studio</title>

<style>
    .ma-reader-shell {
        color: #1f2937;
    }

    .ma-reader-hero,
    .ma-reader-stat,
    .ma-filter-bar,
    .ma-featured,
    .ma-card,
    .ma-empty {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .ma-reader-hero {
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .ma-eyebrow {
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ma-reader-hero h4,
    .ma-panel-title,
    .ma-card h5 {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }

    .ma-reader-hero p,
    .ma-muted {
        color: #64748b;
        margin: 4px 0 0;
    }

    .ma-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .ma-reader-stat {
        padding: 16px;
    }

    .ma-reader-stat span {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .ma-reader-stat strong {
        display: block;
        color: #0f172a;
        font-size: 28px;
        margin-top: 6px;
    }

    .ma-filter-bar {
        padding: 16px;
    }

    .ma-featured {
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(280px, 40%) minmax(0, 1fr);
    }

    .ma-featured-media,
    .ma-card-media {
        background: #111827;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ma-featured-media {
        min-height: 300px;
    }

    .ma-featured-media img,
    .ma-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ma-featured-body {
        padding: 28px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .ma-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 12px 0;
    }

    .ma-pill {
        border: 1px solid #dbe3ea;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        padding: 5px 10px;
    }

    .ma-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .ma-card {
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 100%;
    }

    .ma-card-media {
        height: 155px;
    }

    .ma-card-body {
        padding: 18px;
        display: flex;
        flex: 1;
        flex-direction: column;
    }

    .ma-empty {
        padding: 46px 24px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 1100px) {
        .ma-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .ma-reader-hero,
        .ma-featured {
            align-items: flex-start;
            flex-direction: column;
            grid-template-columns: 1fr;
        }

        .ma-stat-grid,
        .ma-grid {
            grid-template-columns: 1fr;
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

        return 'Market Outlook';
    };
@endphp

<div class="page-content ma-reader-shell">
    <div class="container-fluid">

        <div class="ma-reader-hero mb-3">
            <div>
                <div class="ma-eyebrow">Market Analyst</div>
                <h4>Professional Market Outlooks</h4>
                <p>Review analyst bias, key zones, trading plans, and current market structure before execution.</p>
            </div>
            <div class="ma-stat-grid">
                <div class="ma-reader-stat">
                    <span>Total Outlooks</span>
                    <strong>{{ number_format($totalOutlook) }}</strong>
                </div>
                <div class="ma-reader-stat">
                    <span>Last 7 Days</span>
                    <strong>{{ number_format($weeklyOutlook) }}</strong>
                </div>
                <div class="ma-reader-stat">
                    <span>Markets</span>
                    <strong>{{ number_format($markets->count()) }}</strong>
                </div>
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

        <div class="ma-filter-bar mb-4">
            <form method="GET" action="{{ route('trading.market-analyst.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-5">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="Search title, market, code, notes">
                </div>
                <div class="col-lg-3">
                    <label for="market" class="form-label">Market</label>
                    <select name="market" id="market" class="form-control">
                        <option value="">All Markets</option>
                        @foreach($markets as $market)
                            <option value="{{ $market }}" {{ request('market') === $market ? 'selected' : '' }}>{{ $market }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label for="structure" class="form-label">Structure</label>
                    <select name="structure" id="structure" class="form-control">
                        <option value="">All</option>
                        <option value="uptrend" {{ request('structure') === 'uptrend' ? 'selected' : '' }}>Uptrend</option>
                        <option value="downtrend" {{ request('structure') === 'downtrend' ? 'selected' : '' }}>Downtrend</option>
                        <option value="ranging" {{ request('structure') === 'ranging' ? 'selected' : '' }}>Ranging</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('trading.market-analyst.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>

        @if($latestAnalysis)
            <div class="ma-featured mb-4">
                <div class="ma-featured-media">
                    @if($latestAnalysis->outlook_image && file_exists(public_path($latestAnalysis->outlook_image)))
                        <img src="{{ asset($latestAnalysis->outlook_image) }}" alt="{{ $latestAnalysis->title }}">
                    @else
                        <i class="fas fa-chart-line fa-3x"></i>
                    @endif
                </div>
                <div class="ma-featured-body">
                    <div class="ma-eyebrow">Latest Outlook</div>
                    <h3 class="mt-2">{{ $latestAnalysis->title }}</h3>
                    <div class="ma-meta">
                        <span class="ma-pill">{{ $latestAnalysis->market }}</span>
                        <span class="ma-pill">{{ $trendLabel($latestAnalysis) }}</span>
                        <span class="ma-pill">{{ $latestAnalysis->analysis_date?->format('Y-m-d') ?? '-' }}</span>
                    </div>
                    <p class="text-muted">{{ \Illuminate\Support\Str::limit($latestAnalysis->market_overview ?: $latestAnalysis->analyst_view, 220) }}</p>
                    <div>
                        <a href="{{ route('trading.market-analyst.show', $latestAnalysis->id) }}" class="btn btn-primary">
                            Read Outlook
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="ma-grid">
            @forelse($analyses as $analysis)
                <article class="ma-card">
                    <div class="ma-card-media">
                        @if($analysis->outlook_image && file_exists(public_path($analysis->outlook_image)))
                            <img src="{{ asset($analysis->outlook_image) }}" alt="{{ $analysis->title }}">
                        @else
                            <i class="fas fa-chart-area fa-2x"></i>
                        @endif
                    </div>
                    <div class="ma-card-body">
                        <div class="ma-eyebrow">{{ $analysis->market }}</div>
                        <h5 class="mt-2">{{ $analysis->title }}</h5>
                        <div class="ma-meta">
                            <span class="ma-pill">{{ $trendLabel($analysis) }}</span>
                            <span class="ma-pill">{{ $analysis->analysis_date?->format('Y-m-d') ?? '-' }}</span>
                        </div>
                        <p class="text-muted">{{ \Illuminate\Support\Str::limit($analysis->market_overview ?: $analysis->analyst_view, 135) }}</p>
                        <a href="{{ route('trading.market-analyst.show', $analysis->id) }}" class="btn btn-outline-primary mt-auto">
                            View Analysis
                        </a>
                    </div>
                </article>
            @empty
                <div class="ma-empty">
                    <h5>No market outlooks found</h5>
                    <p class="mb-0">Try another filter or check back after the analyst team publishes a new report.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $analyses->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>

@endsection
