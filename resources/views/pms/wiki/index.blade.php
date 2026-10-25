@extends('pms.layout')
@section('title', 'Knowledge base')
@section('content')
@php
    $featuredPage = $pages->getCollection()->first(fn ($item) => $item->status === 'published');
    $previewPage = $featuredPage ?? $pages->getCollection()->first();
    $currentView = request('view', 'all');
    $activeCategory = request('category', '');
@endphp
<div class="pm-kb pm-kb-library">
    <div class="pm-kb-heading">
        <div><p class="pm-kb-eyebrow">KNOWLEDGE BASE</p><h1>Knowledge Base</h1><p>Guides, processes and shared knowledge to help our teams deliver better projects.</p></div>
        <a class="pm-kb-primary" href="{{ route('pms.wiki.pages.create') }}"><span aria-hidden="true">＋</span> New article</a>
    </div>

    <div class="pm-kb-layout">
        <aside class="pm-kb-rail" aria-label="Knowledge Base categories">
            <p class="pm-kb-rail-title">CATEGORIES</p>
            <a @class(['active' => $activeCategory === '']) href="{{ route('pms.wiki.index', request()->except(['category', 'page'])) }}"><span aria-hidden="true">▦</span> All categories</a>
            @foreach($categories as $category)
                <a @class(['active' => $activeCategory === $category]) href="{{ route('pms.wiki.index', array_merge(request()->except(['category', 'page']), ['category' => $category])) }}"><span aria-hidden="true">▤</span> {{ $category }}</a>
            @endforeach
            <div class="pm-kb-rail-divider"></div>
            <p class="pm-kb-rail-title">QUICK LINKS</p>
            <a href="{{ route('pms.wiki.pages.create') }}">＋ Create article</a>
            <a href="{{ route('pms.wiki.pages.create', ['template' => 'procedure']) }}">▤ Procedure template</a>
            <a href="{{ route('pms.wiki.pages.create', ['template' => 'api']) }}">▤ API reference template</a>
            <a href="{{ route('pms.wiki.pages.create', ['template' => 'incident']) }}">▤ Incident review template</a>
        </aside>

        <section class="pm-kb-library-main" aria-label="Articles">
            <form class="pm-kb-search" method="get" action="{{ route('pms.wiki.index') }}" role="search" data-page-url-template="{{ route('pms.wiki.pages.show', '__id__') }}">
                <span aria-hidden="true">⌕</span>
                <input id="pm-kb-search-input" name="q" value="{{ request('q') }}" placeholder="Search knowledge base..." aria-label="Search knowledge base" aria-controls="pm-kb-suggestions" aria-expanded="false" autocomplete="off">
                @if($activeCategory)<input type="hidden" name="category" value="{{ $activeCategory }}">@endif
                @if(request('space'))<input type="hidden" name="space" value="{{ request('space') }}">@endif
                @if(request('project'))<input type="hidden" name="project" value="{{ request('project') }}">@endif
                @if($currentView !== 'all')<input type="hidden" name="view" value="{{ $currentView }}">@endif
                <button type="submit">Search</button>
                <div class="pm-kb-suggestions" id="pm-kb-suggestions" role="listbox" hidden></div>
            </form>

            <div class="pm-kb-view-tabs" aria-label="Article views">
                @foreach(['all' => 'All articles', 'mine' => 'My articles', 'favorites' => 'Favorites', 'drafts' => 'Drafts'] as $key => $label)
                    <a @class(['active' => $currentView === $key]) href="{{ route('pms.wiki.index', array_merge(request()->except(['view', 'page']), $key === 'all' ? [] : ['view' => $key])) }}">{{ $label }}</a>
                @endforeach
            </div>

            <details class="pm-kb-filter-panel" @if(request('space') || request('project')) open @endif>
                <summary>Filter by space or project <span aria-hidden="true">⌄</span></summary>
                <form method="get" action="{{ route('pms.wiki.index') }}" class="pm-kb-filter-grid">
                    @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    @if($currentView !== 'all')<input type="hidden" name="view" value="{{ $currentView }}">@endif
                    <label>Category<select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected($activeCategory === $category)>{{ $category }}</option>@endforeach</select></label>
                    <label>Space<select name="space"><option value="">All spaces</option>@foreach($spaces as $space)<option value="{{ $space->id }}" @selected(request('space') == $space->id)>{{ $space->name }}</option>@endforeach</select></label>
                    <label>Project<select name="project"><option value="">All accessible projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>@endforeach</select></label>
                    <button class="pm-kb-secondary" type="submit">Apply filters</button>
                </form>
            </details>

            @if($featuredPage && !$activeCategory && !request('q') && $currentView === 'all')
                <p class="pm-kb-section-label">FEATURED GUIDE</p>
                <a class="pm-kb-featured" href="{{ route('pms.wiki.pages.show', $featuredPage) }}">
                    <span class="pm-kb-featured-icon" aria-hidden="true">▤</span>
                    <span><small>FEATURED GUIDE</small><strong>{{ $featuredPage->title }}</strong><span>{{ $featuredPage->summary ?: mb_strimwidth(strip_tags($featuredPage->content ?? ''), 0, 150, '…') }}</span></span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif

            <div class="pm-kb-list-head"><h2>{{ $activeCategory ?: ($currentView === 'all' ? 'All articles' : ucfirst($currentView)) }}</h2><span>{{ $pages->total() }} pages</span></div>
            <div class="pm-kb-article-list">
                @forelse($pages as $page)
                    <a @class(['pm-kb-article-row', 'selected' => $previewPage?->id === $page->id]) href="{{ route('pms.wiki.pages.show', $page) }}" data-kb-preview data-preview-title="{{ $page->title }}" data-preview-category="{{ $page->category ?: $page->space?->name }}" data-preview-author="{{ $page->author?->name ?: 'Team documentation' }}" data-preview-date="{{ $page->updated_at->format('j M Y') }} · {{ ucfirst($page->status) }}" data-preview-summary="{{ $page->summary ?: mb_strimwidth(strip_tags($page->content ?? ''), 0, 220, '…') }}">
                        <span class="pm-kb-file-icon" aria-hidden="true">▤</span>
                        <span class="pm-kb-row-body"><strong>{{ $page->title }}</strong><span>{{ $page->summary ?: mb_strimwidth(strip_tags($page->content ?? ''), 0, 150, '…') }}</span><small>{{ $page->category ?: $page->space?->name }} · {{ ucfirst($page->status) }}</small></span>
                        <span class="pm-kb-row-meta"><strong>{{ $page->author?->name ?: 'Team documentation' }}</strong><small>{{ $page->updated_at->format('j M Y') }}</small></span>
                    </a>
                @empty
                    <div class="pm-kb-empty"><span aria-hidden="true">⌕</span><strong>No articles found</strong><p>Try another keyword or category.</p><a href="{{ route('pms.wiki.index') }}">Clear filters</a></div>
                @endforelse
            </div>
            {{ $pages->links() }}

            <section class="pm-kb-spaces" aria-labelledby="pm-kb-spaces-title"><div class="pm-kb-list-head"><h2 id="pm-kb-spaces-title">Documentation spaces</h2><span>{{ $spaces->count() }} available</span></div><div class="pm-kb-space-grid">
                @foreach($spaces as $space)<a href="{{ route('pms.wiki.spaces.show', $space) }}"><span class="pm-kb-space-icon">{{ $space->icon ?: mb_substr($space->name, 0, 1) }}</span><strong>{{ $space->name }}</strong><small>{{ $space->pages_count }} pages · {{ ucfirst($space->visibility) }}</small></a>@endforeach
            </div><details class="pm-kb-create-space"><summary>Create a documentation space</summary><form method="post" action="{{ route('pms.wiki.spaces.store') }}">@csrf<label>Name<input name="name" required></label><label>Key<input name="key" maxlength="32" required placeholder="ENGINEERING"></label><label>Description<textarea name="description"></textarea></label><label>Visibility<select name="visibility"><option value="workspace">PMS workspace</option><option value="restricted">Restricted members</option></select></label><button class="pm-kb-primary">Create space</button></form></details></section>
        </section>

        @if($previewPage)<aside class="pm-kb-preview" aria-label="Article preview"><div class="pm-kb-preview-icon" aria-hidden="true">▤</div><p class="pm-kb-eyebrow" data-kb-preview-category>{{ $previewPage->category ?: $previewPage->space?->name }}</p><h2 data-kb-preview-title>{{ $previewPage->title }}</h2><p class="pm-kb-preview-byline"><span data-kb-preview-author>{{ $previewPage->author?->name ?: 'Team documentation' }}</span><br><span data-kb-preview-date>{{ $previewPage->updated_at->format('j M Y') }} · {{ ucfirst($previewPage->status) }}</span></p><hr><p data-kb-preview-summary>{{ $previewPage->summary ?: mb_strimwidth(strip_tags($previewPage->content ?? ''), 0, 220, '…') }}</p><p>Find practical steps and guidance written by your team.</p><a class="pm-kb-primary" data-kb-preview-link href="{{ route('pms.wiki.pages.show', $previewPage) }}">Open full article <span aria-hidden="true">→</span></a></aside>@endif
    </div>
</div>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-wiki-library.js') }}?v=20261021a"></script>@endsection
