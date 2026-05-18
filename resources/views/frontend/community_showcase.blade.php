@extends('frontend.main_master')

@section('title', $page->hero_title . ' | HC Gaming Studio')

@section('main')
@php
    $posterUrl = $page->poster_image ? asset($page->poster_image) : null;
    $primaryUrl = $page->primary_cta_url ?: route('contact.me');
    $secondaryUrl = $page->secondary_cta_url ?: route('register');
    $primaryHref = \Illuminate\Support\Str::startsWith($primaryUrl, ['http://', 'https://', '#']) ? $primaryUrl : url($primaryUrl);
    $secondaryHref = \Illuminate\Support\Str::startsWith($secondaryUrl, ['http://', 'https://', '#']) ? $secondaryUrl : url($secondaryUrl);
@endphp

<style>
    .hc-community-page {
        background: #f6f7f9;
        color: #1d2630;
        font-family: "Inter", "Noto Sans SC", Arial, sans-serif;
    }

    .hc-community-hero {
        min-height: 92vh;
        padding: 160px 0 82px;
        position: relative;
        overflow: hidden;
        background-color: #101418;
        background-image:
            linear-gradient(90deg, rgba(16, 20, 24, .96) 0%, rgba(16, 20, 24, .86) 42%, rgba(16, 20, 24, .45) 70%, rgba(16, 20, 24, .20) 100%),
            url('{{ $posterUrl }}');
        background-repeat: no-repeat;
        background-size: cover;
        background-position: center;
    }

    .hc-community-hero::after {
        content: "";
        position: absolute;
        inset: auto 0 0;
        height: 120px;
        background: linear-gradient(180deg, rgba(246, 247, 249, 0), #f6f7f9);
        pointer-events: none;
    }

    .hc-community-hero-inner {
        position: relative;
        z-index: 1;
        max-width: 760px;
    }

    .hc-kicker {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #f3c969;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 18px;
    }

    .hc-kicker::before {
        content: "";
        width: 44px;
        height: 2px;
        background: #f3c969;
    }

    .hc-community-hero h1 {
        color: #ffffff;
        font-size: 56px;
        line-height: 1.08;
        margin: 0 0 18px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .hc-community-subtitle {
        color: #a8f0d5;
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 18px;
    }

    .hc-community-intro {
        color: rgba(255, 255, 255, .82);
        font-size: 18px;
        line-height: 1.8;
        margin: 0 0 30px;
        max-width: 680px;
    }

    .hc-community-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .hc-community-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0 22px;
        border-radius: 6px;
        font-weight: 800;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .hc-community-btn:hover {
        transform: translateY(-1px);
    }

    .hc-community-btn-primary {
        background: #f3c969;
        color: #151515;
        box-shadow: 0 16px 34px rgba(243, 201, 105, .24);
    }

    .hc-community-btn-primary:hover {
        background: #ffd978;
        color: #151515;
    }

    .hc-community-btn-secondary {
        border: 1px solid rgba(255, 255, 255, .36);
        color: #ffffff;
        background: rgba(255, 255, 255, .08);
    }

    .hc-community-btn-secondary:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, .16);
    }

    .hc-section {
        padding: 74px 0;
    }

    .hc-section-tight {
        padding-top: 42px;
    }

    .hc-section-title {
        margin-bottom: 28px;
    }

    .hc-section-title span {
        display: block;
        color: #19715d;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-size: 12px;
        margin-bottom: 8px;
    }

    .hc-section-title h2 {
        color: #111827;
        font-size: 34px;
        line-height: 1.2;
        margin: 0;
        font-weight: 800;
        letter-spacing: 0;
    }

    .hc-section-title p {
        color: #64707d;
        margin: 12px 0 0;
        line-height: 1.7;
        max-width: 720px;
    }

    .hc-requirement-grid,
    .hc-service-grid,
    .hc-secondary-grid {
        display: grid;
        gap: 18px;
    }

    .hc-requirement-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hc-service-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .hc-secondary-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .hc-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 24px;
        min-height: 100%;
        box-shadow: 0 12px 30px rgba(18, 26, 38, .06);
    }

    .hc-requirement-value {
        color: #c78b15;
        font-size: 34px;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 12px;
    }

    .hc-card h3 {
        color: #111827;
        font-size: 19px;
        font-weight: 800;
        margin: 0 0 10px;
        letter-spacing: 0;
    }

    .hc-card p {
        color: #65717e;
        line-height: 1.72;
        margin: 0;
    }

    .hc-service-index {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #19715d;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        margin-bottom: 16px;
    }

    .hc-poster-layout {
        display: grid;
        grid-template-columns: minmax(280px, 440px) minmax(0, 1fr);
        gap: 38px;
        align-items: center;
    }

    .hc-poster-frame {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 12px;
        box-shadow: 0 20px 44px rgba(18, 26, 38, .12);
    }

    .hc-poster-frame img {
        display: block;
        width: 100%;
        border-radius: 4px;
    }

    .hc-principle-panel {
        background: #111827;
        border-radius: 8px;
        padding: 34px;
        color: #ffffff;
    }

    .hc-principle-panel h2 {
        color: #ffffff;
        font-size: 30px;
        margin: 0 0 16px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .hc-principle-panel p {
        color: rgba(255, 255, 255, .82);
        line-height: 1.82;
        margin-bottom: 18px;
    }

    .hc-risk-note {
        border-top: 1px solid rgba(255, 255, 255, .16);
        padding-top: 18px;
        color: #f3c969;
        font-weight: 700;
    }

    @media (max-width: 1199px) {
        .hc-service-grid,
        .hc-secondary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .hc-community-hero {
            min-height: auto;
            padding: 130px 0 72px;
            background-position: center top;
        }

        .hc-community-hero h1 {
            font-size: 38px;
        }

        .hc-community-subtitle {
            font-size: 18px;
        }

        .hc-community-intro {
            font-size: 16px;
        }

        .hc-section {
            padding: 48px 0;
        }

        .hc-section-title h2 {
            font-size: 28px;
        }

        .hc-requirement-grid,
        .hc-service-grid,
        .hc-secondary-grid,
        .hc-poster-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="hc-community-page">
    <section class="hc-community-hero">
        <div class="container">
            <div class="hc-community-hero-inner">
                @if($page->hero_kicker)
                    <div class="hc-kicker">{{ $page->hero_kicker }}</div>
                @endif
                <h1>{{ $page->hero_title }}</h1>
                @if($page->hero_subtitle)
                    <div class="hc-community-subtitle">{{ $page->hero_subtitle }}</div>
                @endif
                @if($page->hero_intro)
                    <p class="hc-community-intro">{{ $page->hero_intro }}</p>
                @endif
                <div class="hc-community-actions">
                    @if($page->primary_cta_label)
                        <a href="{{ $primaryHref }}" class="hc-community-btn hc-community-btn-primary">{{ $page->primary_cta_label }}</a>
                    @endif
                    @if($page->secondary_cta_label)
                        <a href="{{ $secondaryHref }}" class="hc-community-btn hc-community-btn-secondary">{{ $page->secondary_cta_label }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="hc-section hc-section-tight">
        <div class="container">
            <div class="hc-poster-layout">
                @if($posterUrl)
                    <div class="hc-poster-frame">
                        <img src="{{ $posterUrl }}" alt="{{ $page->hero_title }} poster">
                    </div>
                @endif
                <div>
                    <div class="hc-section-title">
                        <span>Entry Requirements</span>
                        <h2>加入条件清晰透明</h2>
                        <p>社群设置门槛，是为了让成员和运营资源都集中在认真学习、愿意长期成长的人身上。</p>
                    </div>
                    <div class="hc-requirement-grid">
                        @foreach($page->entry_requirements ?? [] as $requirement)
                            <article class="hc-card">
                                @if(!empty($requirement['value']))
                                    <div class="hc-requirement-value">{{ $requirement['value'] }}</div>
                                @endif
                                <h3>{{ $requirement['label'] ?? '' }}</h3>
                                <p>{{ $requirement['description'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hc-section">
        <div class="container">
            <div class="hc-section-title">
                <span>Core Services</span>
                <h2>核心服务围绕学习、实战与复盘</h2>
                <p>从课程咨询、市场分析到工具支持，页面内容由后台维护，可随社群服务升级持续更新。</p>
            </div>
            <div class="hc-service-grid">
                @foreach($page->core_services ?? [] as $service)
                    <article class="hc-card">
                        <div class="hc-service-index">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                        <h3>{{ $service['title'] ?? '' }}</h3>
                        @if(!empty($service['description']))
                            <p>{{ $service['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="hc-section">
        <div class="container">
            <div class="hc-section-title">
                <span>Secondary Services</span>
                <h2>延伸服务补充学习体验</h2>
            </div>
            <div class="hc-secondary-grid">
                @foreach($page->secondary_services ?? [] as $service)
                    <article class="hc-card">
                        <h3>{{ $service['title'] ?? '' }}</h3>
                        @if(!empty($service['description']))
                            <p>{{ $service['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="hc-section">
        <div class="container">
            <div class="hc-principle-panel">
                <h2>服务原则</h2>
                @if($page->service_principle)
                    <p>{{ $page->service_principle }}</p>
                @endif
                @if($page->risk_disclaimer)
                    <div class="hc-risk-note">{{ $page->risk_disclaimer }}</div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
