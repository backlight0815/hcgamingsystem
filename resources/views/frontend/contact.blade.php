@extends('frontend.main_master')
@section('title', 'Contact | HC Gaming')
@section('meta_description', 'Get in touch with HC Gaming.')
@section('main')
@php($footer = App\Models\Footer::first())
@php($footerIsDemo = $footer && \Illuminate\Support\Str::contains(strtolower(($footer->email ?? '').' '.($footer->address ?? '').' '.($footer->short_description ?? '')), ['hcdemo.local', 'demo operations', 'demo environment', 'demo data', 'example.com', 'placeholder']))
<div class="es-page"><header class="es-page-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><span>Contact</span></nav><span class="es-eyebrow">Get in touch</span><h1 class="es-title">Let's talk.</h1><p class="es-lead">Questions about a product or something else? Send us a message.</p></div></header>
<section class="es-section"><div class="es-shell es-contact-grid"><div class="es-contact-info"><span class="es-eyebrow">Contact details</span><h2 class="es-title" style="margin:18px 0 30px">We're here to help.</h2>
    @if(!$footerIsDemo && $footer?->email)<a href="mailto:{{ $footer->email }}">{{ $footer->email }}</a>@endif
    @if(!$footerIsDemo && $footer?->number)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $footer->number) }}">{{ $footer->number }}</a>@endif
    @if(!$footerIsDemo && $footer?->address)<p>{{ $footer->address }}</p>@endif</div>
<form class="es-contact-form" method="POST" action="{{ route('store.message') }}">@csrf
    @if($errors->any())<div class="es-form-error" role="alert">Please check the form and try again.</div>@endif
    @if(session('message'))<div class="es-form-success" role="status">{{ session('message') }}</div>@endif
    <label><span>Name</span><input class="es-field" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120">@error('name')<small>{{ $message }}</small>@enderror</label>
    <label><span>Email</span><input class="es-field" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="190">@error('email')<small>{{ $message }}</small>@enderror</label>
    <label><span>Phone</span><input class="es-field" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" maxlength="40">@error('phone')<small>{{ $message }}</small>@enderror</label>
    <label><span>Subject</span><input class="es-field" name="subject" value="{{ old('subject') }}" required maxlength="190">@error('subject')<small>{{ $message }}</small>@enderror</label>
    <label class="full"><span>Message</span><textarea class="es-field" name="message" required maxlength="5000">{{ old('message') }}</textarea>@error('message')<small>{{ $message }}</small>@enderror</label>
    <button class="es-button" type="submit">Send message <span aria-hidden="true">↗</span></button>
</form></div></section></div>
@endsection
