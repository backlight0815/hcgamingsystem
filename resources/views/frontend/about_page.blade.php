@extends('frontend.main_master')
@section('title', 'About | HC Gaming')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($aboutpage?->short_description ?: 'Learn more about HC Gaming.'), 155))
@section('main')
<div class="es-page">
    <header class="es-page-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><span>About</span></nav><span class="es-eyebrow">The story so far</span><h1 class="es-title">A closer look at<br>HC Gaming.</h1><p class="es-lead">{{ $aboutpage?->short_title ?: 'Get to know the people and ideas behind the collection.' }}</p></div></header>
    <section class="es-section"><div class="es-shell es-about-intro">
        <div class="es-about-photo">@if($aboutpage?->about_image && is_file(public_path($aboutpage->about_image)))<img src="{{ asset($aboutpage->about_image) }}" alt="About HC Gaming" width="900" height="820">@else<div class="es-story-mark" aria-hidden="true">HC.</div>@endif</div>
        <div><span class="es-eyebrow">Introduction</span><h2 class="es-title">{{ $aboutpage?->title ?: 'About us' }}</h2>@if($aboutpage?->short_description)<p class="es-lead">{{ $aboutpage->short_description }}</p>@else<p class="es-lead">More about HC Gaming will be shared here soon.</p>@endif</div>
    </div></section>
    @if($aboutpage?->long_description)
    <section class="es-section es-about-story"><div class="es-shell"><span class="es-eyebrow">In our own words</span><h2>The full story.</h2><div class="es-richtext">{!! \App\Support\StorefrontHtml::clean($aboutpage->long_description) !!}</div></div></section>
    @endif
    @if($skillpage->isNotEmpty())
    <section class="es-section"><div class="es-shell"><div class="es-section-head"><div><span class="es-eyebrow">Experience</span><h2 class="es-title">Skills & expertise.</h2></div></div><div class="es-about-notes">@foreach($skillpage as $skill)<div class="es-about-note"><span>0{{ $loop->iteration }}</span><h3>{{ $skill->skill }}</h3>@if($skill->level !== null)<p>{{ $skill->level }}% proficiency</p>@endif</div>@endforeach</div></div></section>
    @endif
    @if($educationpage->isNotEmpty())
    <section class="es-section es-about-story"><div class="es-shell"><div class="es-section-head"><div><span class="es-eyebrow">Background</span><h2 class="es-title">Education.</h2></div></div><div class="es-about-notes">@foreach($educationpage as $education)<div class="es-about-note"><span>{{ $education->period }}</span><h3>{{ $education->title }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags($education->long_description ?? ''), 220) }}</p></div>@endforeach</div></div></section>
    @endif
    <section class="es-endcap"><div class="es-shell"><div><span class="es-eyebrow">Explore further</span><h2>Discover the collection.</h2></div><a class="es-button" href="{{ route('home.product') }}">Shop now <span aria-hidden="true">↗</span></a></div></section>
</div>
@endsection
