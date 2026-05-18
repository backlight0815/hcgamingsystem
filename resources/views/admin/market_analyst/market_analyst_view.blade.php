@extends('admin.admin_master')
@section('admin')

<title>{{ $analysis->title }} | Market Analysis</title>

<style>
    .ma-detail-shell {
        color: #1f2937;
    }

    .ma-detail-hero,
    .ma-detail-panel,
    .ma-meta-box {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .ma-detail-hero {
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

    .ma-detail-hero h4,
    .ma-detail-panel h5 {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }

    .ma-detail-hero p,
    .ma-muted {
        color: #64748b;
        margin: 4px 0 0;
    }

    .ma-detail-panel {
        padding: 20px;
        margin-bottom: 16px;
    }

    .ma-section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }

    .ma-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: #0f172a;
        flex: 0 0 auto;
    }

    .ma-content {
        color: #334155;
        line-height: 1.7;
        white-space: pre-wrap;
    }

    .ma-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .ma-meta-box {
        padding: 14px;
    }

    .ma-meta-box span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .ma-meta-box strong {
        display: block;
        margin-top: 5px;
        color: #0f172a;
        word-break: break-word;
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

    .ma-image {
        width: 100%;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
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

    @media (max-width: 991px) {
        .ma-detail-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .ma-meta-grid {
            grid-template-columns: 1fr;
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

    $discordEnabled = feature_enabled('DiscordIntegration');
@endphp

<div class="page-content ma-detail-shell">
    <div class="container-fluid">

        <div class="ma-detail-hero mb-3">
            <div>
                <div class="ma-eyebrow">Analyst Report</div>
                <h4>{{ $analysis->title }}</h4>
                <p>{{ $analysis->market }} outlook for {{ $analysis->community?->name ?? 'No community' }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('market-analyst.edit', $analysis->id) }}" class="btn btn-info">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <form action="{{ route('market-analyst.sendDiscord', $analysis->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning" {{ $discordEnabled ? '' : 'disabled' }}>
                        <i class="fab fa-discord"></i> {{ $analysis->discord_sent ? 'Update Discord' : 'Send Discord' }}
                    </button>
                </form>
                <a href="{{ route('market-analyst.index') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
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

        <div class="row">
            <div class="col-xl-8">
                @foreach($sections as $section)
                    @if($section['content'])
                        <div class="ma-detail-panel">
                            <div class="ma-section-head">
                                <span class="ma-section-icon"><i class="{{ $section['icon'] }}"></i></span>
                                <h5>{{ $section['title'] }}</h5>
                            </div>
                            <div class="ma-content">{{ $section['content'] }}</div>
                        </div>
                    @endif
                @endforeach

                @if($analysis->trading_plan)
                    <div class="ma-detail-panel">
                        <div class="ma-section-head">
                            <span class="ma-section-icon"><i class="fas fa-tasks"></i></span>
                            <h5>Trading Plan</h5>
                        </div>
                        <div class="ma-plan">{{ $analysis->trading_plan }}</div>
                    </div>
                @endif
            </div>

            <div class="col-xl-4">
                <div class="ma-detail-panel">
                    <div class="ma-section-head">
                        <span class="ma-section-icon"><i class="fas fa-info-circle"></i></span>
                        <h5>Report Details</h5>
                    </div>

                    <div class="ma-meta-grid">
                        <div class="ma-meta-box">
                            <span>Code</span>
                            <strong>{{ $analysis->Outlook_Code ?? '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Status</span>
                            <strong>
                                @if($analysis->discord_sent)
                                    <span class="ma-status sent">Sent</span>
                                @else
                                    <span class="ma-status pending">Pending</span>
                                @endif
                            </strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Market</span>
                            <strong>{{ $analysis->market }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Community</span>
                            <strong>{{ $analysis->community?->name ?? '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Analysis Date</span>
                            <strong>{{ $analysis->analysis_date?->format('Y-m-d') ?? '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Updated</span>
                            <strong>{{ $analysis->updated_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Trend Strength</span>
                            <strong>{{ $analysis->trend_strength ? ucfirst($analysis->trend_strength) : '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>RSI / Momentum</span>
                            <strong>{{ $analysis->rsi_level ?? '-' }}</strong>
                        </div>
                        <div class="ma-meta-box">
                            <span>Order Block / FVG</span>
                            <strong>{{ $analysis->order_block ?? '-' }}</strong>
                        </div>
                    </div>
                </div>

                @if($analysis->outlook_image && file_exists(public_path($analysis->outlook_image)))
                    <div class="ma-detail-panel">
                        <div class="ma-section-head">
                            <span class="ma-section-icon"><i class="fas fa-image"></i></span>
                            <h5>Outlook Image</h5>
                        </div>
                        <img src="{{ asset($analysis->outlook_image) }}" alt="{{ $analysis->title }}" class="ma-image">
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
