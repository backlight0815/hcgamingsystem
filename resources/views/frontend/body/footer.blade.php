@php($footer = App\Models\Footer::first())
@php($footerIsDemo = $footer && \Illuminate\Support\Str::contains(strtolower(($footer->email ?? '').' '.($footer->address ?? '').' '.($footer->short_description ?? '').' '.($footer->copyright ?? '')), ['hcdemo.local', 'demo operations', 'demo environment', 'demo data', 'example.com', 'placeholder']))
<footer class="es-footer">
    <div class="es-shell">
        <div class="es-footer-top">
            <div class="es-footer-intro">
                <a class="es-brand es-brand-footer" href="{{ route('store.home') }}"><img src="{{ asset('logo.png') }}" width="42" height="42" alt=""><span>HC<span class="es-brand-accent">/</span>GAMING</span></a>
                @if($footer?->short_description && !$footerIsDemo)<p>{{ $footer->short_description }}</p>@else<p>Explore the latest from HC Gaming.</p>@endif
            </div>
            <div><h2>Explore</h2><a href="{{ route('home.product') }}">Shop</a><a href="{{ route('home.about') }}">About</a><a href="{{ route('home.service') }}">Services</a><a href="{{ route('home.blog') }}">Journal</a><a href="{{ route('contact.me') }}">Contact</a>@if(\App\Support\ModuleAccess::publicVisible('tradinghub'))<a href="{{ route('trading.hub.home') }}">Trading Hub</a>@endif</div>
            <div><h2>Support</h2><a href="{{ auth()->check() ? route('cart.summary') : route('guest.cart.summary') }}">Your cart</a><a href="{{ route('privacy.policy') }}">Privacy policy</a>@guest<a href="{{ route('login') }}">Account</a>@endguest</div>
            @if($footer && !$footerIsDemo && ($footer->email || $footer->number || $footer->address))
                <div><h2>Get in touch</h2>
                    @if($footer->email)<a href="mailto:{{ $footer->email }}">{{ $footer->email }}</a>@endif
                    @if($footer->number)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $footer->number) }}">{{ $footer->number }}</a>@endif
                    @if($footer->address)<p>{{ $footer->address }}</p>@endif
                </div>
            @endif
        </div>
        <div class="es-footer-bottom"><small>{{ !$footerIsDemo && $footer?->copyright ? $footer->copyright : '© '.date('Y').' HC Gaming' }}</small><span>Made for the curious.</span></div>
    </div>
</footer>
