@extends('frontend.main_master')
@section('title', $service->service_title.' | HC Gaming')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($service->short_description ?? ''), 155))
@section('main')
<div class="es-page"><header class="es-page-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><a href="{{ route('home.service') }}">Services</a><span>/</span><span>Detail</span></nav><span class="es-eyebrow">Our services</span><h1 class="es-title">{{ $service->service_title }}</h1></div></header>
<section class="es-section"><div class="es-shell es-about-intro">@if($service->service_image && is_file(public_path($service->service_image)))<div class="es-about-photo"><img src="{{ asset($service->service_image) }}" alt="{{ $service->service_title }}" width="900" height="800"></div>@endif<div><span class="es-eyebrow">Overview</span><h2 class="es-title">Explore the details.</h2><div class="es-richtext">{!! \App\Support\StorefrontHtml::clean($service->short_description) !!}</div><a class="es-button" style="margin-top:30px" href="{{ route('contact.me') }}">Get in touch ↗</a></div></div></section></div>
@endsection
