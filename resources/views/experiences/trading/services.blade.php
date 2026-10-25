@extends('experiences.trading.layout')
@section('title', 'Platform Areas | HC Trading Hub')
@section('description', 'Explore available trading research, education, and signal tools from HC Trading Hub.')
@section('content')
@php($modules = \App\Support\TradingSubmodules::states())
<header class="th-page-hero"><div class="th-container"><span class="th-kicker th-accent">Platform areas</span><h1>Tools for a more<br><em>informed process.</em></h1><p>Explore existing research and member features. Availability of member tools depends on your account permissions.</p></div></header>
<section class="th-section"><div class="th-container th-service-list">
    @if($modules['trading_blog'])
        <article><span>01 / Research</span><div><h2>Market insights</h2><p>Published trading articles and market commentary, organised by the Trading Blog's configured topics.</p><a class="th-text-link" href="{{ route('trading.hub.insights') }}">Browse research ↗</a></div></article>
    @endif
    @if($modules['trading_signals'])
        <article><span>02 / Signals</span><div><h2>Trading signals</h2><p>Published signal records with instrument, direction, status, and timestamp for permitted members.</p><a class="th-text-link" href="{{ route('trading.hub.signals') }}">Explore signals ↗</a></div></article>
    @endif
    @if($modules['trading_blog'])
        <article><span>03 / Learning</span><div><h2>Trading education</h2><p>Education and risk management topics are available through published Trading Blog content.</p><a class="th-text-link" href="{{ route('trading.hub.insights', ['category' => 'risk_management']) }}">Explore learning ↗</a></div></article>
    @endif
</div></section>
<section class="th-risk"><div class="th-container th-risk-inner"><span class="th-kicker">Important</span><p>These are information and platform features. HC Trading Hub does not promise outcomes through research or signals.</p></div></section>
@endsection
