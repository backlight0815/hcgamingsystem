@extends('experiences.trading.layout')
@section('title', 'HC Trading Hub | Research and Trading Tools')
@section('description', 'Explore trading research and member tools from HC Trading Hub.')
@section('content')
@php($modules = \App\Support\TradingSubmodules::states())
<section class="th-hero"><div class="th-container th-hero-grid"><div class="th-hero-copy">
    <span class="th-kicker th-accent">Research / Education / Signals</span>
    <h1>Navigate markets with <em>perspective.</em></h1>
    <p>Explore the trading research, learning resources, and member tools available through HC Trading Hub.</p>
    <div class="th-actions">
        @if($modules['trading_blog'])<a class="th-button" href="{{ route('trading.hub.insights') }}">Explore insights <span aria-hidden="true">↗</span></a>@endif
        @if($modules['trading_signals'])<a class="th-button th-button-outline" href="{{ route('trading.hub.signals') }}">Trading signals</a>@endif
    </div>
</div><div class="th-hero-art" aria-label="Abstract financial research illustration"><div class="th-hero-orbit"></div><div class="th-hero-art-top"><span>HC / RESEARCH DESK</span><span>01 — 03</span></div><div class="th-hero-art-main"><span>Clarity for<br>every decision.</span><div class="th-hero-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div><div class="th-hero-art-bottom"><span>Ideas are explored.<br>Risk is considered.</span><span class="th-cross" aria-hidden="true">✳</span></div></div></div></section>
@if($modules['trading_blog'])
<section class="th-section th-section-border"><div class="th-container"><div class="th-section-head"><div><span class="th-kicker">Research</span><h2>Published market insights.</h2><p>Read what the editorial team has published.</p></div><a class="th-text-link" href="{{ route('trading.hub.insights') }}">All insights ↗</a></div>
    @if($blogs->isNotEmpty())<div class="th-article-grid">@foreach($blogs as $article)@include('experiences.trading.partials.article')@endforeach</div>
    @else<div class="th-empty"><h3>No research published yet.</h3><p>Check back when a market insight has been approved for publication.</p></div>@endif
</div></section>
@endif
@if($modules['trading_signals'])
<section class="th-section th-section-contrast"><div class="th-container"><div class="th-section-head"><div><span class="th-kicker">Member signals</span><h2>Signals with context.</h2><p>Published signal details are available to permitted members.</p></div><a class="th-text-link" href="{{ route('trading.hub.signals') }}">Browse signals ↗</a></div>
    @if($signals->isNotEmpty())<div class="th-signal-grid">@foreach($signals as $signal)@include('experiences.trading.partials.signal')@endforeach</div>
    @else<div class="th-empty"><h3>{{ auth()->check() ? 'No published signals available.' : 'Member access required.' }}</h3><p>{{ auth()->check() ? 'Published signals will appear here when available.' : 'Sign in to view published signal data, subject to your account permissions.' }}</p>@guest<a class="th-text-link" href="{{ route('login') }}">Sign in ↗</a>@endguest</div>@endif
</div></section>
@endif
@if($modules['trading_blog'] || $modules['trading_signals'])
<section class="th-section"><div class="th-container th-split"><div><span class="th-kicker">Learn</span><h2>Build your understanding.</h2><p>Explore published trading education and research by topic. Material is informational and should be considered alongside your own risk assessment.</p>@if($modules['trading_blog'])<a class="th-button th-button-outline" href="{{ route('trading.hub.insights') }}">Browse research</a>@endif</div><div class="th-paths">
    @if($modules['trading_blog'])<a href="{{ route('trading.hub.insights') }}"><span>01</span><strong>Market research</strong><span aria-hidden="true">↗</span></a>@endif
    @if($modules['trading_signals'])<a href="{{ route('trading.hub.signals') }}"><span>02</span><strong>Published signals</strong><span aria-hidden="true">↗</span></a>@endif
    <a href="{{ route('trading.hub.services') }}"><span>03</span><strong>Platform tools</strong><span aria-hidden="true">↗</span></a>
</div></div></section>
@endif
<section class="th-section th-section-contrast th-section-border"><div class="th-container th-split"><div><span class="th-kicker">About HC Trading Hub</span><h2>A place for informed exploration.</h2></div><div class="th-prose"><p>Explore the tools currently available on the platform and use them with care.</p><a class="th-text-link" href="{{ route('trading.hub.about') }}">About the platform ↗</a></div></div></section>
<section class="th-risk"><div class="th-container th-risk-inner"><span class="th-kicker">Risk disclosure</span><p>Trading carries financial risk. Published analysis and signals describe market views and historical records; they do not guarantee future results.</p></div></section>
@if($modules['trading_blog'] || $modules['trading_signals'])
<section class="th-section th-section-border"><div class="th-container th-section-head"><div><span class="th-kicker th-accent">Continue exploring</span><h2>Find your next perspective.</h2><p>Browse the latest published research or review the signals available to your account.</p></div><div class="th-actions">
    @if($modules['trading_blog'])<a class="th-button" href="{{ route('trading.hub.insights') }}">Read insights</a>@endif
    @if($modules['trading_signals'])<a class="th-button th-button-outline" href="{{ route('trading.hub.signals') }}">View signals</a>@endif
</div></div></section>
@endif
@endsection
