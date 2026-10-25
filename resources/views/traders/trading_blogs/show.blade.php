@extends('experiences.trading.layout')
@section('title', $blog->title.' | HC Trading Hub')
@section('description', \Illuminate\Support\Str::limit(strip_tags($blog->excerpt ?: $blog->content), 155))
@section('robots', 'noindex,follow')
@section('og_type', 'article')
@push('head')
    @if($blog->cover_image && is_file(public_path($blog->cover_image)))<meta property="og:image" content="{{ asset($blog->cover_image) }}">@endif
    <meta property="article:published_time" content="{{ $blog->published_at?->toIso8601String() }}">
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $blog->title,
        'description' => $blog->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($blog->content), 155),
        'datePublished' => $blog->published_at?->toIso8601String(),
        'image' => $blog->cover_image && is_file(public_path($blog->cover_image)) ? asset($blog->cover_image) : null,
        'author' => $blog->author ? ['@type' => 'Person', 'name' => $blog->author->username ?? $blog->author->name] : null,
    ]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<div class="th-reading-progress" aria-hidden="true"></div>
<article><header class="th-article-hero"><div class="th-container th-article-hero-inner"><nav class="th-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('trading.hub.home') }}">Trading Hub</a><span>/</span><a href="{{ route('trading.blogs.index') }}">Research</a><span>/</span><span>Article</span></nav><span class="th-kicker th-accent">{{ $blog->category_label }}</span><h1>{{ $blog->title }}</h1>@if($blog->excerpt)<p>{{ $blog->excerpt }}</p>@endif<div class="th-article-meta"><time datetime="{{ $blog->published_at?->toIso8601String() }}">{{ $blog->published_at?->format('d M Y') }}</time><span>{{ $blog->reading_minutes }} min read</span>@if($blog->author)<span>By {{ $blog->author->username ?? $blog->author->name }}</span>@endif</div></div></header>
@if($blog->cover_image && is_file(public_path($blog->cover_image)))<div class="th-container th-article-cover"><img src="{{ asset($blog->cover_image) }}" alt="{{ $blog->title }}" width="1200" height="650"></div>@endif
<div class="th-container th-reader-layout"><div class="th-richtext">{!! \App\Support\StorefrontHtml::clean($blog->content) !!}</div><aside class="th-reader-side"><span class="th-kicker">Article tools</span><button type="button" data-share-article>Share this article ↗</button><a href="{{ route('trading.blogs.index') }}">Back to research ↗</a><div class="th-side-risk"><strong>Trading risk</strong><p>Research reflects a view at publication time. It does not guarantee future market movement.</p></div></aside></div></article>
@if($relatedBlogs->isNotEmpty())<section class="th-section th-section-contrast"><div class="th-container"><div class="th-section-head"><div><span class="th-kicker">Continue reading</span><h2>Related research.</h2></div><a class="th-text-link" href="{{ route('trading.blogs.index') }}">All research ↗</a></div><div class="th-article-grid">@foreach($relatedBlogs as $article)@include('experiences.trading.partials.article')@endforeach</div></div></section>@endif
@endsection
