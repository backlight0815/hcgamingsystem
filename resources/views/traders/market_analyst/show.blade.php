@extends('admin.admin_master')
@section('admin')

<title>{{ $analysis->title }} | Market Analyst</title>

<style>
    .ma-show-shell {
        color: #1f2937;
    }

    .ma-show-hero,
    .ma-panel,
    .ma-side-box,
    .ma-related-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .ma-show-hero {
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

    .ma-show-hero h4,
    .ma-panel h5,
    .ma-side-box strong {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }

    .ma-show-hero p,
    .ma-muted {
        color: #64748b;
        margin: 4px 0 0;
    }

    .ma-panel {
        padding: 20px;
        margin-bottom: 16px;
    }

    .ma-panel-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }

    .ma-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #0f172a;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .ma-content {
        color: #334155;
        line-height: 1.75;
        white-space: pre-wrap;
    }

    .ma-plan {
        background: #0f172a;
        border-radius: 8px;
        color: #e2e8f0;
        font-family: Consolas, Monaco, "Courier New", monospace;
        padding: 18px;
        line-height: 1.65;
        white-space: pre-wrap;
    }

    .ma-outlook-image {
        width: 100%;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .ma-side-box {
        padding: 16px;
        margin-bottom: 12px;
    }

    .ma-side-box span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .ma-side-box strong {
        display: block;
        margin-top: 5px;
    }

    .ma-related-card {
        padding: 14px;
        margin-bottom: 12px;
    }

    .ma-related-card h6 {
        color: #0f172a;
        font-weight: 800;
        margin: 0 0 6px;
    }

    @media (max-width: 991px) {
        .ma-show-hero {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

@php
    $sections = [
        ['icon' => 'fas fa-book-open', 'title' => 'Market Overview', 'content' => $analysis->market_overview],
        ['icon' => 'fas fa-project-diagram', 'title' => 'Trend and Structure', 'content' => $analysis->trend_structure],
        ['icon' => 'fas fa-map-marker-alt', 'title' => 'Key Zones', 'content' => $analysis->key_zones],
        ['icon' => 'fas fa-crosshairs', 'title' => 'Entry / Risk Zones', 'content' => $analysis->entry_zones_description],
        ['icon' => 'fas fa-user-tie', 'title' => 'Analyst View', 'content' => $analysis->analyst_view],
        ['icon' => 'fas fa-bullseye', 'title' => 'Strategy / Recommendations', 'content' => $analysis->strategy],
        ['icon' => 'fas fa-chart-bar', 'title' => 'Chart Signals Summary', 'content' => $analysis->chart_signals],
    ];
@endphp

<div class="page-content ma-show-shell">
    <div class="container-fluid">

        <div class="ma-show-hero mb-3">
            <div>
                <div class="ma-eyebrow">{{ $analysis->market }} Outlook</div>
                <h4>{{ $analysis->title }}</h4>
                <p>{{ $analysis->analysis_date?->format('Y-m-d') ?? '-' }} by the Market Analyst desk</p>
            </div>
            <a href="{{ route('trading.market-analyst.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Outlooks
            </a>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        <div class="row">
            <div class="col-xl-8">
                @if($analysis->outlook_image && file_exists(public_path($analysis->outlook_image)))
                    <div class="ma-panel">
                        <img src="{{ asset($analysis->outlook_image) }}" alt="{{ $analysis->title }}" class="ma-outlook-image">
                    </div>
                @endif

                @foreach($sections as $section)
                    @if($section['content'])
                        <div class="ma-panel">
                            <div class="ma-panel-head">
                                <span class="ma-icon"><i class="{{ $section['icon'] }}"></i></span>
                                <h5>{{ $section['title'] }}</h5>
                            </div>
                            <div class="ma-content">{{ $section['content'] }}</div>
                        </div>
                    @endif
                @endforeach

                @if($analysis->trading_plan)
                    <div class="ma-panel">
                        <div class="ma-panel-head">
                            <span class="ma-icon"><i class="fas fa-tasks"></i></span>
                            <h5>Trading Plan</h5>
                        </div>
                        <div class="ma-plan">{{ $analysis->trading_plan }}</div>
                    </div>
                @endif
            </div>

            <div class="col-xl-4">
                <div class="ma-side-box">
                    <span>Outlook Code</span>
                    <strong>{{ $analysis->Outlook_Code ?? '-' }}</strong>
                </div>
                <div class="ma-side-box">
                    <span>Market</span>
                    <strong>{{ $analysis->market }}</strong>
                </div>
                <div class="ma-side-box">
                    <span>Community</span>
                    <strong>{{ $analysis->community?->name ?? '-' }}</strong>
                </div>
                <div class="ma-side-box">
                    <span>Trend Strength</span>
                    <strong>{{ $analysis->trend_strength ? ucfirst($analysis->trend_strength) : '-' }}</strong>
                </div>
                <div class="ma-side-box">
                    <span>RSI / Momentum</span>
                    <strong>{{ $analysis->rsi_level ?? '-' }}</strong>
                </div>
                <div class="ma-side-box">
                    <span>Order Block / FVG</span>
                    <strong>{{ $analysis->order_block ?? '-' }}</strong>
                </div>

                @if($relatedAnalyses->isNotEmpty())
                    <div class="ma-panel mt-3">
                        <div class="ma-eyebrow mb-2">Related Outlooks</div>
                        @foreach($relatedAnalyses as $related)
                            <div class="ma-related-card">
                                <h6>{{ $related->title }}</h6>
                                <div class="ma-muted small">{{ $related->analysis_date?->format('Y-m-d') ?? '-' }}</div>
                                <a href="{{ route('trading.market-analyst.show', $related->id) }}" class="btn btn-sm btn-outline-primary mt-2">
                                    Open
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
