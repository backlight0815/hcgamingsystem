@extends('frontend.main_master')
@section('title', 'HC Gaming | Discover the Collection')
@section('meta_description', 'Explore products and stories from HC Gaming.')
@section('main')
<div class="es-page">
    <section class="es-hero" aria-labelledby="home-title">
        <div class="es-shell es-hero-grid">
            <div class="es-hero-copy">
                <span class="es-eyebrow">The HC Gaming edit</span>
                <h1 id="home-title">{{ $slide?->title ?: 'Find your next favourite.' }}</h1>
                <p>{{ $slide?->short_title ?: 'Discover the products and stories that make up the HC Gaming collection.' }}</p>
                <div class="es-hero-actions"><a class="es-button" href="{{ route('home.product') }}">Explore the shop <span aria-hidden="true">↗</span></a><a class="es-text-link" href="{{ route('home.about') }}">Our story <span aria-hidden="true">↗</span></a></div>
            </div>
            <div class="es-hero-visual @if(!$slide?->home_slide || !is_file(public_path($slide->home_slide))) es-hero-art @endif">
                @if($slide?->home_slide && is_file(public_path($slide->home_slide)))<img src="{{ asset($slide->home_slide) }}" alt="{{ $slide->title ?: 'HC Gaming feature' }}" width="900" height="1050" fetchpriority="high">@else<span aria-hidden="true">HC.</span>@endif
                <span class="es-hero-index">Discover · HC Gaming</span>
            </div>
        </div>
    </section>

    @if($categories->isNotEmpty())
    <section class="es-section" aria-labelledby="category-title"><div class="es-shell">
        <div class="es-section-head"><div><span class="es-eyebrow">Find your direction</span><h2 class="es-title" id="category-title">Explore by category.</h2></div><a class="es-text-link" href="{{ route('home.product') }}">Shop all <span aria-hidden="true">↗</span></a></div>
        <div class="es-category-grid @if($categories->count() < 4) es-category-grid-small @endif">@foreach($categories as $category)<a class="es-category-tile" href="{{ route('home.product', ['category' => $category->id]) }}"><span class="es-category-number">0{{ $loop->iteration }} / Collection</span><h3>{{ $category->product_category }}</h3><i class="fas fa-arrow-up-right" aria-hidden="true">↗</i></a>@endforeach</div>
    </div></section>
    @endif

    <section class="es-section es-product-section" aria-labelledby="product-title"><div class="es-shell">
        <div class="es-section-head"><div><span class="es-eyebrow">The collection</span><h2 class="es-title" id="product-title">Objects worth exploring.</h2></div><a class="es-text-link" href="{{ route('home.product') }}">View all products <span aria-hidden="true">↗</span></a></div>
        @if($featuredProducts->isNotEmpty())<div class="es-product-grid">@foreach($featuredProducts as $item)@include('frontend.partials.product_card')@endforeach</div>
        @else<div class="es-empty"><h2>The collection is being prepared.</h2><p>Come back to see what is available.</p><a class="es-button" href="{{ route('contact.me') }}">Contact us</a></div>@endif
    </div></section>

    @if($about && ($about->title || $about->short_description))
    <section class="es-section es-story" aria-labelledby="story-title"><div class="es-shell es-story-grid">
        <div class="es-story-media">@if($about->about_image && is_file(public_path($about->about_image)))<img src="{{ asset($about->about_image) }}" alt="HC Gaming story" loading="lazy" width="850" height="950">@else<div class="es-story-mark" aria-hidden="true">HC.</div>@endif</div>
        <div class="es-story-copy"><span class="es-eyebrow">Behind the brand</span><h2 class="es-title" id="story-title">{{ $about->title ?: 'A closer look at HC Gaming.' }}</h2>
            @if($about->short_title)<p class="es-lead">{{ $about->short_title }}</p>@endif
            @if($about->short_description)<p class="es-lead" style="margin-top:18px">{{ \Illuminate\Support\Str::limit(strip_tags($about->short_description), 330) }}</p>@endif
            <a class="es-text-link" href="{{ route('home.about') }}">Read our story <span aria-hidden="true">↗</span></a></div>
    </div></section>
    @endif

    @if($services->isNotEmpty())
    <section class="es-section" aria-labelledby="services-title"><div class="es-shell"><div class="es-section-head"><div><span class="es-eyebrow">What we do</span><h2 class="es-title" id="services-title">Explore our services.</h2></div></div>
        <div class="es-services-list">@foreach($services as $service)<a class="es-service-row" href="{{ route('service.details', $service->id) }}"><span class="es-eyebrow">0{{ $loop->iteration }}</span><h3>{{ $service->service_title }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags($service->short_description ?? ''), 145) }}</p><i aria-hidden="true">↗</i></a>@endforeach</div>
    </div></section>
    @endif

    @if($latestBlogs->isNotEmpty())
    <section class="es-section es-journal" aria-labelledby="journal-title"><div class="es-shell"><div class="es-section-head"><div><span class="es-eyebrow">Notes & ideas</span><h2 class="es-title" id="journal-title">From the journal.</h2></div><a class="es-text-link" href="{{ route('home.blog') }}">All articles <span aria-hidden="true">↗</span></a></div><div class="es-article-grid @if($latestBlogs->count() < 3) es-article-grid-simple @endif">@foreach($latestBlogs as $item)@include('frontend.partials.article_card')@endforeach</div></div></section>
    @endif

    <section class="es-endcap"><div class="es-shell"><div><span class="es-eyebrow">Keep exploring</span><h2>There's more to discover.</h2></div><a class="es-button" href="{{ route('home.product') }}">Shop the collection <span aria-hidden="true">↗</span></a></div></section>
</div>
@endsection
