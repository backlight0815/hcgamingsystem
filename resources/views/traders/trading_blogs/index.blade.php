@extends('admin.admin_master')
@section('admin')

<title>Trading Blog | HC Gaming Studio</title>

<style>
    .trading-blog-shell {
        color: #1f2937;
    }

    .tb-reader-header {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 22px 24px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .tb-reader-header h4 {
        margin: 0;
        color: #0f172a;
        font-weight: 700;
    }

    .tb-reader-header p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .tb-filter-bar {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .category-scroll {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .category-chip {
        border: 1px solid #d1d5db;
        color: #475569;
        background: #ffffff;
        border-radius: 999px;
        padding: 7px 12px;
        white-space: nowrap;
        font-size: 13px;
    }

    .category-chip.active,
    .category-chip:hover {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
    }

    .featured-post,
    .blog-card,
    .empty-state {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .featured-post {
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(280px, 42%) 1fr;
    }

    .featured-media {
        min-height: 280px;
        background: #111827;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .featured-media img {
        width: 100%;
        height: 100%;
        min-height: 280px;
        object-fit: cover;
    }

    .featured-content {
        padding: 28px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .tb-kicker {
        color: #0f766e;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .featured-content h3,
    .blog-card h5 {
        color: #0f172a;
        font-weight: 700;
    }

    .blog-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .blog-card {
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .blog-card-media {
        height: 155px;
        background: #111827;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .blog-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .blog-card-body {
        padding: 18px;
        display: flex;
        flex: 1;
        flex-direction: column;
    }

    .tb-meta {
        color: #64748b;
        font-size: 13px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 10px 0;
    }

    .empty-state {
        padding: 44px 24px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 1100px) {
        .blog-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .featured-post,
        .blog-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content trading-blog-shell">
    <div class="container-fluid">

        <div class="tb-reader-header mb-3">
            <h4>Trading Blog</h4>
            <p>Trading sharing, knowledge sharing, psychology, prop firm preparation, and funded trader lessons.</p>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        <div class="tb-filter-bar mb-4">
            <form method="GET" action="{{ route('trading.blogs.index') }}" class="row g-2 align-items-center">
                <div class="col-xl-8">
                    <div class="category-scroll">
                        <a href="{{ route('trading.blogs.index', request()->except('category', 'page')) }}"
                           class="category-chip {{ $activeCategory ? '' : 'active' }}">
                            All
                        </a>
                        @foreach($categories as $key => $label)
                            <a href="{{ route('trading.blogs.index', array_merge(request()->except('page'), ['category' => $key])) }}"
                               class="category-chip {{ $activeCategory === $key ? 'active' : '' }}">
                                {{ $label }} ({{ $categoryCounts[$key] ?? 0 }})
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Search trading posts">
                        @if($activeCategory)
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        <button type="submit" class="btn btn-primary">Search</button>
                        @if($search)
                            <a href="{{ route('trading.blogs.index', request()->except('search', 'page')) }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        @if($featuredBlog)
            <div class="featured-post mb-4">
                <div class="featured-media">
                    @if($featuredBlog->cover_image)
                        <img src="{{ asset($featuredBlog->cover_image) }}" alt="{{ $featuredBlog->title }}">
                    @else
                        <i class="fas fa-chart-line fa-3x"></i>
                    @endif
                </div>
                <div class="featured-content">
                    <div class="tb-kicker">Featured {{ $featuredBlog->category_label }}</div>
                    <h3 class="mt-2">{{ $featuredBlog->title }}</h3>
                    <div class="tb-meta">
                        <span>{{ $featuredBlog->published_at?->format('M d, Y') }}</span>
                        <span>{{ $featuredBlog->reading_minutes }} min read</span>
                        <span>{{ number_format($featuredBlog->views) }} views</span>
                    </div>
                    <p class="text-muted">{{ $featuredBlog->excerpt }}</p>
                    <div>
                        <a href="{{ route('trading.blogs.show', $featuredBlog->slug) }}" class="btn btn-primary">
                            Read Featured Post
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="blog-grid">
            @forelse($blogs as $blog)
                <article class="blog-card">
                    <div class="blog-card-media">
                        @if($blog->cover_image)
                            <img src="{{ asset($blog->cover_image) }}" alt="{{ $blog->title }}">
                        @else
                            <i class="fas fa-book-open fa-2x"></i>
                        @endif
                    </div>
                    <div class="blog-card-body">
                        <div class="tb-kicker">{{ $blog->category_label }}</div>
                        <h5 class="mt-2">{{ $blog->title }}</h5>
                        <div class="tb-meta">
                            <span>{{ $blog->published_at?->format('M d, Y') }}</span>
                            <span>{{ $blog->reading_minutes }} min read</span>
                        </div>
                        <p class="text-muted">{{ \Illuminate\Support\Str::limit($blog->excerpt, 130) }}</p>
                        <a href="{{ route('trading.blogs.show', $blog->slug) }}" class="btn btn-outline-primary mt-auto">
                            Read Post
                        </a>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <h5>No trading posts found</h5>
                    <p class="mb-0">Try another category or search keyword.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $blogs->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>

@endsection
