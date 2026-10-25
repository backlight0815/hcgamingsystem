@extends('frontend.main_master')
@section('title', 'Journal | HC Gaming')
@section('meta_description', 'Explore the latest articles and stories from HC Gaming.')
@section('main')
<div class="es-page">
    <header class="es-page-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><span>Journal</span></nav><span class="es-eyebrow">The HC Gaming journal</span><h1 class="es-title">Stories worth<br>exploring.</h1><p class="es-lead">Ideas, updates, and perspectives from HC Gaming.</p></div></header>
    <div class="es-shell es-blog-controls">
        <form class="es-blog-search" action="{{ request()->routeIs('category.blog') ? route('category.blog', $categoryId) : route('home.blog') }}" method="GET" role="search">
            @if($categoryId && !request()->routeIs('category.blog'))<input type="hidden" name="category" value="{{ $categoryId }}">@endif
            <label class="visually-hidden" for="blog-search">Search articles</label><input id="blog-search" name="q" type="search" value="{{ $search }}" maxlength="80" placeholder="Search articles"><button type="submit" aria-label="Search articles"><i class="fas fa-search" aria-hidden="true"></i></button>
            @if($search !== '')<a class="es-clear" href="{{ $categoryId ? route('category.blog', $categoryId) : route('home.blog') }}">Clear</a>@endif
        </form>
        <nav class="es-category-nav" aria-label="Article categories"><a href="{{ route('home.blog', $search !== '' ? ['q' => $search] : []) }}" @if(!$categoryId) aria-current="page" @endif>All articles</a>@foreach($categories as $category)<a href="{{ route('category.blog', ['id' => $category->id] + ($search !== '' ? ['q' => $search] : [])) }}" @if($categoryId === $category->id) aria-current="page" @endif>{{ $category->blog_category }}</a>@endforeach</nav>
    </div>
    <section class="es-section" style="padding-top:40px"><div class="es-shell">
        @php($archiveItems = $featured && $search === '' ? $allblogs->getCollection()->slice(1) : $allblogs->getCollection())
        @if($featured && $search === '')
        <article class="es-featured-blog"><a href="{{ route('blog.details', $featured->id) }}" aria-label="Read {{ $featured->blog_title }}">@if($featured->blog_image && is_file(public_path($featured->blog_image)))<img src="{{ asset($featured->blog_image) }}" alt="" width="900" height="600">@else<span class="es-article-fallback" aria-hidden="true">HC / Journal</span>@endif</a><div class="es-featured-copy"><span class="es-eyebrow">Latest story</span><div class="es-article-meta">@if($featured->category && !$featured->category->trashed() && !in_array($featured->category->blog_category, ['?', '??', ''])){{ $featured->category->blog_category }} · @endif<time datetime="{{ $featured->created_at?->toDateString() }}">{{ $featured->created_at?->format('d M Y') }}</time></div><h2><a href="{{ route('blog.details', $featured->id) }}" style="color:inherit">{{ $featured->blog_title }}</a></h2><p>{{ \Illuminate\Support\Str::limit(strip_tags($featured->blog_description ?? ''), 230) }}</p><a class="es-text-link" href="{{ route('blog.details', $featured->id) }}">Read article <span aria-hidden="true">↗</span></a></div></article>
        @endif
        @if($archiveItems->isNotEmpty() || $search !== '' || $categoryId)<div class="es-archive-head"><h2>{{ $search !== '' ? 'Search results' : ($categoryId ? 'Category articles' : 'All articles') }}</h2><span>{{ $allblogs->total() }} {{ \Illuminate\Support\Str::plural('article', $allblogs->total()) }}</span></div>@endif
        @if($archiveItems->isNotEmpty())<div class="es-article-grid @if($archiveItems->count() < 3) es-article-grid-simple @endif">@foreach($archiveItems as $item)@include('frontend.partials.article_card')@endforeach</div><div class="es-pagination">{{ $allblogs->links('pagination::bootstrap-5') }}</div>
        @elseif($featured && $search === '')
        <div class="es-pagination">{{ $allblogs->links('pagination::bootstrap-5') }}</div>
        @else<div class="es-empty"><h2>No articles found.</h2><p>Try another search or browse all articles.</p><a class="es-button" href="{{ route('home.blog') }}">Browse all articles</a></div>@endif
    </div></section>
</div>
@endsection
