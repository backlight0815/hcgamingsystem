<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1220">
    <title>@yield('title', 'HC Trading Hub')</title>
    <meta name="description" content="@yield('description', 'Trading research, education, and member tools from HC Trading Hub.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'HC Trading Hub')">
    <meta property="og:description" content="@yield('description', 'Trading research, education, and member tools from HC Trading Hub.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @stack('head')
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/trading-hub.css') }}">
    @if(request()->routeIs('trading.hub.outlooks.*'))<link rel="stylesheet" href="{{ asset('frontend/assets/css/trading-outlooks.css') }}">@endif
</head>
<body class="th-body">
    <a class="th-skip" href="#main-content">Skip to content</a>
    <header class="th-header">
        <div class="th-container th-header-inner">
            <a class="th-brand" href="{{ route('trading.hub.home') }}" aria-label="HC Trading Hub home"><img src="{{ asset('logo.png') }}" alt="" width="34" height="34"><span>HC <b>TRADING HUB</b></span></a>
            <nav class="th-nav" aria-label="Trading Hub navigation">
                @if(\App\Support\FrontendExperience::homepage() === 'combined')<a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Portal</a>@endif
                <a href="{{ route('trading.hub.home') }}" @if(request()->routeIs('trading.hub.home')) aria-current="page" @endif>Overview</a>
                @if(\App\Support\TradingSubmodules::enabled('trading_signals'))<a href="{{ route('trading.hub.signals') }}" @if(request()->routeIs('trading.hub.signals')) aria-current="page" @endif>Signals</a>@endif
                @if(\App\Support\TradingSubmodules::enabled('trading_blog'))<a href="{{ route('trading.hub.insights') }}" @if(request()->routeIs('trading.hub.insights')) aria-current="page" @endif>Insights</a>@endif
                @if(\App\Support\TradingSubmodules::enabled('market_analyst'))<a href="{{ route('trading.hub.outlooks.index') }}" @if(request()->routeIs('trading.hub.outlooks.*')) aria-current="page" @endif>Market Analyst</a>@endif
                <a href="{{ route('trading.hub.about') }}" @if(request()->routeIs('trading.hub.about')) aria-current="page" @endif>About</a>
                <a href="{{ route('trading.hub.services') }}" @if(request()->routeIs('trading.hub.services')) aria-current="page" @endif>Platform</a>
                <a href="{{ route('trading.hub.contact') }}" @if(request()->routeIs('trading.hub.contact')) aria-current="page" @endif>Contact</a>
                @if(\App\Support\ModuleAccess::publicVisible('dealership'))<a class="th-shop-link" href="{{ route('store.home') }}">Visit shop <span aria-hidden="true">↗</span></a>@endif
                @if(\App\Support\ModuleAccess::publicVisible('pms'))<a href="{{ route('pms.login') }}">Project Management</a>@endif
            </nav>
            <div class="th-header-actions">
                @auth<a class="th-account" href="{{ route('all.statistics') }}">Dashboard</a>@else<a class="th-account" href="{{ route('tradinghub.login') }}">Sign in</a>@endauth
                <button class="th-menu-button" type="button" aria-controls="th-mobile-nav" aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
            </div>
        </div>
        <nav class="th-mobile-nav" id="th-mobile-nav" aria-label="Mobile navigation" hidden>
            @if(\App\Support\FrontendExperience::homepage() === 'combined')<a href="{{ route('home') }}">Portal</a>@endif
            <a href="{{ route('trading.hub.home') }}">Overview</a>
            @if(\App\Support\TradingSubmodules::enabled('trading_signals'))<a href="{{ route('trading.hub.signals') }}">Signals</a>@endif
            @if(\App\Support\TradingSubmodules::enabled('trading_blog'))<a href="{{ route('trading.hub.insights') }}">Insights</a>@endif
            @if(\App\Support\TradingSubmodules::enabled('market_analyst'))<a href="{{ route('trading.hub.outlooks.index') }}">Market Analyst</a>@endif
            <a href="{{ route('trading.hub.about') }}">About</a><a href="{{ route('trading.hub.services') }}">Platform</a><a href="{{ route('trading.hub.contact') }}">Contact</a>
            @if(\App\Support\ModuleAccess::publicVisible('dealership'))<a href="{{ route('store.home') }}">Visit shop</a>@endif
            @if(\App\Support\ModuleAccess::publicVisible('pms'))<a href="{{ route('pms.login') }}">Project Management</a>@endif
            @auth<a href="{{ route('all.statistics') }}">Dashboard</a>@else<a href="{{ route('tradinghub.login') }}">Sign in</a>@endauth
        </nav>
    </header>
    <main id="main-content">@yield('content')</main>
    <footer class="th-footer"><div class="th-container th-footer-grid"><div><a class="th-brand" href="{{ route('trading.hub.home') }}">HC <b>TRADING HUB</b></a><p>Research, education, and trading tools in one place.</p></div><nav aria-label="Footer">@if(\App\Support\TradingSubmodules::enabled('trading_blog'))<a href="{{ route('trading.hub.insights') }}">Trading Blog</a>@endif @if(\App\Support\TradingSubmodules::enabled('market_analyst'))<a href="{{ route('trading.hub.outlooks.index') }}">Market Analyst</a>@endif @if(\App\Support\TradingSubmodules::enabled('trading_signals'))<a href="{{ route('trading.hub.signals') }}">Trading signals</a>@endif<a href="{{ route('trading.hub.contact') }}">Contact</a>@if(\App\Support\ModuleAccess::publicVisible('dealership'))<a href="{{ route('store.home') }}">Shop</a>@endif</nav><div><strong>Risk matters.</strong><p>Trading involves risk. Information shown here does not guarantee a result or replace your own assessment.</p></div></div><div class="th-container th-footer-bottom"><span>© {{ date('Y') }} HC Gaming</span><a href="{{ route('privacy.policy') }}">Privacy policy</a></div></footer>
    <script defer src="{{ asset('frontend/assets/js/trading-hub.js') }}"></script>
</body>
</html>
