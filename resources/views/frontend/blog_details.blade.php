@extends('frontend.main_master')
@section('title', $blogs->blog_title.' | HC Gaming Journal')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($blogs->blog_description ?? ''), 155))
@section('main')
<div class="es-page">
    <div class="es-progress" aria-hidden="true"></div>
    <header class="es-article-header"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><a href="{{ route('home.blog') }}">Journal</a><span>/</span><span>Article</span></nav><div class="es-article-meta">@if($blogs->category && !$blogs->category->trashed() && !in_array($blogs->category->blog_category, ['?', '??', '']))<a href="{{ route('category.blog', $blogs->category->id) }}">{{ $blogs->category->blog_category }}</a> · @endif<time datetime="{{ $blogs->created_at?->toDateString() }}">{{ $blogs->created_at?->format('d M Y') }}</time></div><h1>{{ $blogs->blog_title }}</h1><p>{{ \Illuminate\Support\Str::limit(trim(strip_tags($blogs->blog_description ?? '')), 230) }}</p></div></header>
    @if($blogs->blog_image && is_file(public_path($blogs->blog_image)))<div class="es-article-cover"><img src="{{ asset($blogs->blog_image) }}" alt="{{ $blogs->blog_title }}" width="1160" height="640"></div>@endif
    <div class="es-shell es-article-layout"><article class="es-richtext es-article-body">{!! \App\Support\StorefrontHtml::clean($blogs->blog_description) !!}</article><aside class="es-article-side" aria-label="Article tools"><h2>Article</h2><button type="button" data-copy-link>Share this article ↗</button><a href="{{ route('home.blog') }}">Back to journal ↗</a></aside></div>
    @if($relatedBlogs->isNotEmpty())<section class="es-section es-related"><div class="es-shell"><div class="es-section-head"><div><span class="es-eyebrow">Continue reading</span><h2 class="es-title">More from the journal.</h2></div><a class="es-text-link" href="{{ route('home.blog') }}">All articles <span aria-hidden="true">↗</span></a></div><div class="es-article-grid">@foreach($relatedBlogs as $item)@include('frontend.partials.article_card')@endforeach</div></div></section>@endif
</div>
@endsection
