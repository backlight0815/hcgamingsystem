@extends('experiences.trading.layout')
@section('title', 'About | HC Trading Hub')
@section('description', 'Learn how HC Trading Hub brings trading research, education, and member tools together.')
@section('content')
@php($modules = \App\Support\TradingSubmodules::states())
<header class="th-page-hero"><div class="th-container"><span class="th-kicker th-accent">About the platform</span><h1>Space to study<br><em>the market.</em></h1><p>HC Trading Hub brings trading research, education, signals, and member tools into one focused experience.</p></div></header>
<section class="th-section"><div class="th-container th-split"><div><span class="th-kicker">Our focus</span><h2>Information with context.</h2></div><div class="th-prose"><p>The platform supports published market research and educational articles, plus trading signals for permitted members. These areas are kept distinct so readers can see what is analysis, what is learning material, and what is a signal record.</p><p>Dates, status, and access limits are shown wherever data is presented. Financial information can change; users should assess risk independently.</p></div></div></section>
<section class="th-section th-section-contrast"><div class="th-container"><div class="th-section-head"><div><span class="th-kicker">Explore</span><h2>Choose a starting point.</h2></div></div><div class="th-paths th-paths-three">
    @if($modules['trading_blog'])<a href="{{ route('trading.hub.insights') }}"><span>01</span><strong>Research and education</strong><span aria-hidden="true">↗</span></a>@endif
    @if($modules['trading_signals'])<a href="{{ route('trading.hub.signals') }}"><span>02</span><strong>Published signals</strong><span aria-hidden="true">↗</span></a>@endif
    <a href="{{ route('trading.hub.contact') }}"><span>03</span><strong>Contact</strong><span aria-hidden="true">↗</span></a>
</div></div></section>
@endsection
