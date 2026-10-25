@php($routeName = optional(Route::current())->getName())
<a class="es-skip" href="#main-content">Skip to content</a>
<header class="es-header" id="site-header">
    <div class="es-shell es-header-inner">
        <a class="es-brand" href="{{ route('store.home') }}" aria-label="HC Gaming store home">
            <img src="{{ asset('logo.png') }}" width="38" height="38" alt="">
            <span>HC<span class="es-brand-accent">/</span>GAMING</span>
        </a>
        <nav class="es-desktop-nav" aria-label="Main navigation">
            @if(\App\Support\FrontendExperience::homepage() === 'combined')<a href="{{ route('home') }}">Portal</a>@endif
            <a @if(in_array($routeName, ['home', 'store.home'])) aria-current="page" @endif href="{{ route('store.home') }}">Home</a>
            <a @if(in_array($routeName, ['home.product', 'stock.details'])) aria-current="page" @endif href="{{ route('home.product') }}">Shop</a>
            <a @if($routeName === 'home.about') aria-current="page" @endif href="{{ route('home.about') }}">About</a>
            <a @if(in_array($routeName, ['home.service', 'service.details'])) aria-current="page" @endif href="{{ route('home.service') }}">Services</a>
            <a @if($routeName === 'home.blog') aria-current="page" @endif href="{{ route('home.blog') }}">Journal</a>
            <a @if($routeName === 'contact.me') aria-current="page" @endif href="{{ route('contact.me') }}">Contact</a>
            @if(\App\Support\ModuleAccess::publicVisible('tradinghub'))<a href="{{ route('trading.hub.home') }}">Trading Hub</a>@endif
            @if(\App\Support\ModuleAccess::publicVisible('pms'))<a href="{{ route('pms.login') }}">Project Management</a>@endif
        </nav>
        <div class="es-header-actions">
            @auth<a class="es-icon-link es-account-link" href="{{ route('all.statistics') }}" aria-label="My dashboard"><i class="far fa-user" aria-hidden="true"></i></a>
            @else<a class="es-icon-link es-account-link" href="{{ route('dealership.login') }}" aria-label="Sign in to Dealership"><i class="far fa-user" aria-hidden="true"></i></a>@endauth
            <a class="es-cart-link" href="{{ auth()->check() ? route('cart.summary') : route('guest.cart.summary') }}" aria-label="View cart">
                <i class="fas fa-shopping-bag" aria-hidden="true"></i><span class="es-cart-count">{{ auth()->check() ? session('cartTotal', 0) : session('GuestCartTotal', 0) }}</span>
            </a>
            <button class="es-menu-toggle" type="button" aria-label="Open menu" aria-controls="es-mobile-nav" aria-expanded="false"><span></span><span></span></button>
        </div>
    </div>
    <nav class="es-mobile-nav" id="es-mobile-nav" aria-label="Mobile navigation" hidden>
        @if(\App\Support\FrontendExperience::homepage() === 'combined')<a href="{{ route('home') }}">Portal</a>@endif<a href="{{ route('store.home') }}">Home</a><a href="{{ route('home.product') }}">Shop</a><a href="{{ route('home.about') }}">About</a><a href="{{ route('home.service') }}">Services</a><a href="{{ route('home.blog') }}">Journal</a><a href="{{ route('contact.me') }}">Contact</a>@if(\App\Support\ModuleAccess::publicVisible('tradinghub'))<a href="{{ route('trading.hub.home') }}">Trading Hub</a>@endif @if(\App\Support\ModuleAccess::publicVisible('pms'))<a href="{{ route('pms.login') }}">Project Management</a>@endif
        @auth<a href="{{ route('all.statistics') }}">My dashboard</a>@else<a href="{{ route('dealership.login') }}">Sign in</a>@endauth
    </nav>
</header>
