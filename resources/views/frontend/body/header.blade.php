@php

    $route = Route::current()->getName();
@endphp

<header>
<div id="sticky-header" class="menu__area transparent-header">
<div class="container custom-container">
<div class="row">
<div class="col-12">
<div class="mobile__nav__toggler"><i class="fas fa-bars"></i></div>
<div class="menu__wrap">
<nav class="menu__nav">
    <div class="logo">
        <a href="{{ route('home') }}" class="logo__black"><img src="{{ asset('frontend/assets/img/logo/hclogo_black.png') }}" alt=""></a>
        <a href="{{ route('home') }}" class="logo__white"><img src="{{ asset('frontend/assets/img/logo/hclogo_white.png') }}" alt=""></a>
    </div>
    <div class="navbar__wrap main__menu d-none d-xl-flex">
        <ul class="navigation">
            @if(Auth::check())
            {{-- If the user is authenticated, redirect to a different route --}}
            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">Dashboard</a></li>
        @else
            {{-- If the user is not authenticated, redirect to the regular "Home" route --}}
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        @endif




        @if(Auth::check())
        {{-- If the user is authenticated, redirect to a different route --}}
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    @else
        {{-- If the user is not authenticated, redirect to the regular "Home" route --}}
        {{-- <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li> --}}
    @endif


            {{-- <li class="{{ ($route == 'home')?'active':''}}"><a href="{{ route('home') }}">Home</a></li> --}}
            <li class="{{ ($route == 'home.about')?'active':''}}"><a href="{{ route('home.about') }}">About Me</a></li>


            <li class="{{ ($route == 'home.event')?'active':''}}"><a href="{{ route('home.event') }}">Event</a></li>
            {{-- <li class="{{ ($route == 'home.portfolio')?'active':''}}"><a href="{{ route('home.portfolio') }}">My Portfolio</a> --}}

            </li>


            @if(auth()->check())
    <li class="{{ ($route == 'my.stock') ? 'active' : '' }}">
        <a href="{{ route('my.stock') }}">My Stock</a>
    </li>
@else
    <li class="{{ ($route == 'home.product') ? 'active' : '' }}">
        <a href="{{ route('home.product') }}">Product</a>
    </li>
@endif


            {{-- <li class="{{ ($route == 'home.blog')?'active':''}}"><a href="{{ route('home.blog') }}">Blogs</a>

            </li> --}}
            <li class="{{ ($route == 'contact.me')?'active':''}}"><a href="{{ route('contact.me') }}">Contact Me</a></li>
        </ul>
    </div>
    <div class="header__btn d-none d-md-block">
        @auth
        <!-- Show something if the user is logged in -->
        <span>Welcome, {{ Auth::user()->username }}!</span>

    @else
        <!-- Show the login button if the user is not logged in -->
        <a href="{{ route('login') }}" class="btn">Login</a>
    @endauth

</div>
</nav>
</div>
<!-- Mobile Menu  -->
<div class="mobile__menu">
<nav class="menu__box">
    <div class="close__btn"><i class="fal fa-times"></i></div>
    <div class="nav-logo">
        <a href="{{ route('home') }}" class="logo__black"><img src="{{ asset('frontend/assets/img/logo/hclogo_black.png') }}" alt=""></a>
        <a href="{{ route('home') }}" class="logo__white"><img src="{{ asset('frontend/assets/img/logo/hclogo_white.png') }}" alt=""></a>
    </div>
    <div class="menu__outer">
        <ul class="navigation">
            <!-- Other menu items -->

            <!-- Include the login button in the mobile menu -->
            @auth
                <!-- Show something if the user is logged in -->
                <li><span>Welcome, {{ Auth::user()->username }}!</span></li>
            @else
                <!-- Show the login button if the user is not logged in -->
                <li><a href="{{ route('login') }}">Login</a></li>
            @endauth

        </ul>
        </div>
    {{-- <div class="social-links">
        <ul class="clearfix">
            <li><a href="#"><span class="fab fa-twitter"></span></a></li>
            <li><a href="#"><span class="fab fa-facebook-square"></span></a></li>
            <li><a href="#"><span class="fab fa-pinterest-p"></span></a></li>
            <li><a href="#"><span class="fab fa-instagram"></span></a></li>
            <li><a href="#"><span class="fab fa-youtube"></span></a></li>
        </ul>
    </div> --}}
</nav>
</div>
<div class="menu__backdrop"></div>
<!-- End Mobile Menu -->
</div>
</div>
</div>
</div>
</header>
@php

    $route = Route::current()->getName();
@endphp

<header>
<div id="sticky-header" class="menu__area transparent-header">
<div class="container custom-container">
<div class="row">
<div class="col-12">
<div class="mobile__nav__toggler"><i class="fas fa-bars"></i></div>
<div class="menu__wrap">
<nav class="menu__nav">
    <div class="logo">
        <a href="{{ route('home') }}" class="logo__black"><img src="{{ asset('frontend/assets/img/logo/hclogo_black.png') }}" alt=""></a>
        <a href="{{ route('home') }}" class="logo__white"><img src="{{ asset('frontend/assets/img/logo/hclogo_white.png') }}" alt=""></a>
    </div>
    <div class="navbar__wrap main__menu d-none d-xl-flex">
        <ul class="navigation">
            @if(Auth::check())
            {{-- If the user is authenticated, redirect to a different route --}}
            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">Dashboard</a></li>
        @else
            {{-- If the user is not authenticated, redirect to the regular "Home" route --}}
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        @endif




        @if(Auth::check())
        {{-- If the user is authenticated, redirect to a different route --}}
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    @else
        {{-- If the user is not authenticated, redirect to the regular "Home" route --}}
        {{-- <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li> --}}
    @endif


            {{-- <li class="{{ ($route == 'home')?'active':''}}"><a href="{{ route('home') }}">Home</a></li> --}}
            <li class="{{ ($route == 'home.about')?'active':''}}"><a href="{{ route('home.about') }}">About Me</a></li>


            <li class="{{ ($route == 'home.event')?'active':''}}"><a href="{{ route('home.event') }}">Event</a></li>
            {{-- <li class="{{ ($route == 'home.portfolio')?'active':''}}"><a href="{{ route('home.portfolio') }}">My Portfolio</a> --}}

            </li>


            @if(auth()->check())
    <li class="{{ ($route == 'my.stock') ? 'active' : '' }}">
        <a href="{{ route('my.stock') }}">My Stock</a>
    </li>
@else
    <li class="{{ ($route == 'home.product') ? 'active' : '' }}">
        <a href="{{ route('home.product') }}">Product</a>
    </li>
@endif


            {{-- <li class="{{ ($route == 'home.blog')?'active':''}}"><a href="{{ route('home.blog') }}">Blogs</a>

            </li> --}}
            <li class="{{ ($route == 'contact.me')?'active':''}}"><a href="{{ route('contact.me') }}">Contact Me</a></li>
        </ul>
    </div>
    <div class="header__btn d-none d-md-block">
        @auth
        <!-- Show something if the user is logged in -->
        <span>Welcome, {{ Auth::user()->username }}!</span>

    @else
        <!-- Show the login button if the user is not logged in -->
        <a href="{{ route('login') }}" class="btn">Login</a>
    @endauth

</div>
</nav>
</div>
<!-- Mobile Menu  -->
<div class="mobile__menu">
<nav class="menu__box">
    <div class="close__btn"><i class="fal fa-times"></i></div>
    <div class="nav-logo">
        <a href="{{ route('home') }}" class="logo__black"><img src="{{ asset('frontend/assets/img/logo/hclogo_black.png') }}" alt=""></a>
        <a href="{{ route('home') }}" class="logo__white"><img src="{{ asset('frontend/assets/img/logo/hclogo_white.png') }}" alt=""></a>
    </div>
    <div class="menu__outer">
        <ul class="navigation">
            <!-- Other menu items -->

            <!-- Include the login button in the mobile menu -->
            @auth
                <!-- Show something if the user is logged in -->
                <li><span>Welcome, {{ Auth::user()->username }}!</span></li>
            @else
                <!-- Show the login button if the user is not logged in -->
                <li><a href="{{ route('login') }}">Login</a></li>
            @endauth

        </ul>
        </div>
    {{-- <div class="social-links">
        <ul class="clearfix">
            <li><a href="#"><span class="fab fa-twitter"></span></a></li>
            <li><a href="#"><span class="fab fa-facebook-square"></span></a></li>
            <li><a href="#"><span class="fab fa-pinterest-p"></span></a></li>
            <li><a href="#"><span class="fab fa-instagram"></span></a></li>
            <li><a href="#"><span class="fab fa-youtube"></span></a></li>
        </ul>
    </div> --}}
</nav>
</div>
<div class="menu__backdrop"></div>
<!-- End Mobile Menu -->
</div>
</div>
</div>
</div>
</header>

