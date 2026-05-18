@extends('admin.admin_master')
@section('admin')

<title>{{ $blog->title }} | Trading Blog</title>

<style>
    .article-shell {
        color: #1f2937;
    }

    .article-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 22px;
        align-items: start;
    }

    .article-main,
    .article-side {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .article-main {
        overflow: hidden;
    }

    .article-cover {
        min-height: 330px;
        background: #111827;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .article-cover img {
        width: 100%;
        height: 360px;
        object-fit: cover;
    }

    .article-content {
        padding: 30px;
    }

    .article-kicker {
        color: #0f766e;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .article-content h1 {
        color: #0f172a;
        font-weight: 800;
        margin: 10px 0;
    }

    .article-meta {
        color: #64748b;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .article-body {
        color: #334155;
        font-size: 16px;
        line-height: 1.75;
    }

    .article-body img {
        max-width: 100%;
        border-radius: 8px;
    }

    .article-side {
        padding: 20px;
    }

    .side-title {
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .related-item {
        display: block;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        color: #1f2937;
        margin-bottom: 10px;
    }

    .related-item:hover {
        border-color: #0f172a;
        color: #0f172a;
    }

    @media (max-width: 991px) {
        .article-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content article-shell">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Trading Blog</h4>
                <p class="text-muted mb-0 mt-1">Focused trading content for your growth.</p>
            </div>
            <a href="{{ route('trading.blogs.index') }}" class="btn btn-secondary">Back to Blog</a>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        <div class="article-layout">
            <article class="article-main">
                <div class="article-cover">
                    @if($blog->cover_image)
                        <img src="{{ asset($blog->cover_image) }}" alt="{{ $blog->title }}">
                    @else
                        <i class="fas fa-chart-line fa-4x"></i>
                    @endif
                </div>

                <div class="article-content">
                    <div class="article-kicker">{{ $blog->category_label }}</div>
                    <h1>{{ $blog->title }}</h1>
                    <div class="article-meta">
                        <span>{{ $blog->published_at?->format('M d, Y') }}</span>
                        <span>{{ $blog->reading_minutes }} min read</span>
                        <span>{{ number_format($blog->views) }} views</span>
                        @if($blog->author)
                            <span>By {{ $blog->author->username ?? $blog->author->name }}</span>
                        @endif
                    </div>

                    @if($blog->excerpt)
                        <p class="lead text-muted">{{ $blog->excerpt }}</p>
                    @endif

                    <div class="article-body">
                        {!! $blog->content !!}
                    </div>

                    @if($blog->tags)
                        <div class="mt-4">
                            <strong>Tags:</strong>
                            <span class="text-muted">{{ $blog->tags }}</span>
                        </div>
                    @endif
                </div>
            </article>

            <aside class="article-side">
                <h5 class="side-title">Related Posts</h5>
                @forelse($relatedBlogs as $related)
                    <a href="{{ route('trading.blogs.show', $related->slug) }}" class="related-item">
                        <strong>{{ $related->title }}</strong>
                        <div class="text-muted small mt-1">{{ $related->published_at?->format('M d, Y') }} · {{ $related->reading_minutes }} min read</div>
                    </a>
                @empty
                    <p class="text-muted mb-0">No related posts yet.</p>
                @endforelse

                <hr>

                <h5 class="side-title">Category</h5>
                <p class="text-muted mb-0">{{ $blog->category_label }}</p>
            </aside>
        </div>
    </div>
</div>

@endsection
