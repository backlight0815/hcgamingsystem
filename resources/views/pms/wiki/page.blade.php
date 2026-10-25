@extends('pms.layout')
@section('title', $page->title)
@section('content')
<div class="pm-kb pm-kb-reader">
    <div class="pm-kb-reader-layout">
        <aside class="pm-kb-rail" aria-label="Knowledge Base navigation">
            <p class="pm-kb-rail-title">KNOWLEDGE BASE</p>
            <a href="{{ route('pms.wiki.index') }}"><span aria-hidden="true">▦</span> All articles</a>
            @foreach(\App\Models\PmWikiPage::CATEGORIES as $category)
                <a @class(['active' => $page->category === $category]) href="{{ route('pms.wiki.index', ['category' => $category]) }}"><span aria-hidden="true">▤</span> {{ $category }}</a>
            @endforeach
            <div class="pm-kb-rail-divider"></div>
            <p class="pm-kb-rail-title">IN THIS SPACE</p>
            <a href="{{ route('pms.wiki.spaces.show', $page->space) }}">{{ $page->space->name }}</a>
            @if($page->parent)<a href="{{ route('pms.wiki.pages.show', $page->parent) }}">← {{ $page->parent->title }}</a>@endif
            @foreach($children as $child)<a href="{{ route('pms.wiki.pages.show', $child) }}">↳ {{ $child->title }}</a>@endforeach
        </aside>

        <div class="pm-kb-reader-main">
            <nav class="pm-kb-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('pms.wiki.index') }}">Knowledge Base</a><span>/</span><a href="{{ route('pms.wiki.spaces.show', $page->space) }}">{{ $page->space->name }}</a>@foreach($breadcrumbs as $ancestor)<span>/</span><a href="{{ route('pms.wiki.pages.show', $ancestor) }}">{{ $ancestor->title }}</a>@endforeach<span>/</span><span>{{ $page->title }}</span></nav>
            <header class="pm-kb-article-header">
                <p class="pm-kb-eyebrow">{{ $page->category ?: 'TEAM DOCUMENTATION' }}</p>
                <h1>{{ $page->title }}</h1>
                @if($page->summary)<p class="pm-kb-article-summary">{{ $page->summary }}</p>@endif
                <div class="pm-kb-byline"><span>By {{ $page->system_key ? 'Team training material' : ($page->author?->name ?: 'Team documentation') }}</span><span class="pm-kb-byline-dot" aria-hidden="true"></span><span>Updated {{ $page->updated_at->format('j M Y') }}</span><span class="pm-kb-status {{ $page->status }}">{{ ucfirst($page->status) }}</span></div>
                <div class="pm-kb-article-actions">
                    @if(\App\Support\PmsWikiAccess::editPage(auth()->user(), $page))<a class="pm-kb-secondary" href="{{ route('pms.wiki.pages.edit', $page) }}">▤ Edit article</a>@endif
                    <form method="post" action="{{ route('pms.wiki.pages.favorite', $page) }}">@csrf<button type="submit" class="pm-kb-secondary">{{ $favorite ? '★ Saved' : '☆ Save article' }}</button></form>
                    <a class="pm-kb-secondary" href="{{ route('pms.wiki.pages.revisions', $page) }}">Revision {{ $page->revision_number }}</a>
                </div>
            </header>

            <article class="pm-kb-prose pm-wiki-article" id="pm-kb-article-content">{!! $page->safe_html !!}</article>

            @if($relatedPages->isNotEmpty())<section class="pm-kb-related" aria-labelledby="pm-kb-related-title"><h2 id="pm-kb-related-title">Related articles</h2><div class="pm-kb-related-grid">@foreach($relatedPages as $related)<a href="{{ route('pms.wiki.pages.show', $related) }}"><span>{{ $related->category ?: $related->space?->name }}</span><strong>{{ $related->title }}</strong><small>{{ $related->summary }}</small><span aria-hidden="true">→</span></a>@endforeach</div></section>@endif

            <div class="pm-kb-reader-extras">
                @if(($page->tags ?? []) !== [])<div class="pm-kb-tags" aria-label="Article tags">@foreach($page->tags as $tag)<span>{{ $tag }}</span>@endforeach</div>@endif
                <section class="pm-kb-extra-card" id="pm-kb-references"><div class="pm-kb-extra-heading"><h2>References</h2><span>{{ $tickets->count() + $projects->count() }}</span></div>
                    @foreach($tickets as $ticket)<p>Ticket <a href="{{ route('pms.tickets.show', $ticket) }}">{{ $ticket->reference }}</a> · {{ $ticket->summary }}</p>@endforeach
                    @foreach($projects as $project)<p>Project <a href="{{ route('pms.projects.show', $project) }}">{{ $project->name }}</a></p>@endforeach
                    @if($tickets->isEmpty() && $projects->isEmpty())<p class="pm-kb-muted">No ticket or project references.</p>@endif
                    @if(\App\Support\PmsWikiAccess::editPage(auth()->user(), $page))<details><summary>Link a ticket or project</summary><form method="post" action="{{ route('pms.wiki.pages.links.store', $page) }}" class="pm-kb-inline-form">@csrf<label>Ticket reference<input name="ticket_key" placeholder="sfqat0001"></label><label>Project ID<input name="project_id" type="number" min="1"></label><button class="pm-kb-secondary">Link reference</button></form></details>@endif
                </section>
                <section class="pm-kb-extra-card" id="pm-kb-attachments"><div class="pm-kb-extra-heading"><h2>Attachments</h2><span>{{ $attachments->count() }}</span></div>
                    @forelse($attachments as $file)<a class="pm-kb-attachment" href="{{ route('pms.wiki.pages.attachments.show', [$page, $file->id]) }}"><span aria-hidden="true">▤</span><span><strong>{{ $file->original_name }}</strong><small>{{ number_format($file->size_bytes / 1024, 1) }} KB</small></span><span aria-hidden="true">↓</span></a>@empty<p class="pm-kb-muted">No attachments.</p>@endforelse
                    @if(\App\Support\PmsWikiAccess::editPage(auth()->user(), $page))<form method="post" action="{{ route('pms.wiki.pages.attachments.store', $page) }}" enctype="multipart/form-data" class="pm-kb-inline-form">@csrf<label>Add attachment<input type="file" name="file" required></label><button class="pm-kb-secondary">Upload</button></form>@endif
                </section>
                <section class="pm-kb-extra-card" id="pm-kb-discussion"><div class="pm-kb-extra-heading"><h2>Discussion</h2><span>{{ $comments->count() }}</span></div>
                    @forelse($comments as $comment)<article class="pm-kb-comment"><strong>{{ $comment->author_name }}</strong><small>{{ \Carbon\Carbon::parse($comment->created_at)->diffForHumans() }}</small><p>{{ $comment->body }}</p></article>@empty<p class="pm-kb-muted">No comments yet.</p>@endforelse
                    <form method="post" action="{{ route('pms.wiki.pages.comments.store', $page) }}" class="pm-kb-comment-form">@csrf<label for="pm-kb-comment-body">Add a comment</label><textarea id="pm-kb-comment-body" name="body" rows="3" required></textarea><button class="pm-kb-primary">Post comment</button></form>
                </section>
                @if(\App\Support\PmsAccess::administrator(auth()->user()))<form method="post" action="{{ route('pms.wiki.pages.destroy', $page) }}" onsubmit="return confirm('Archive this page?')">@csrf @method('DELETE')<button class="pm-kb-danger">Archive article</button></form>@endif
            </div>
        </div>

        <aside class="pm-kb-toc" aria-label="On this page"><h2>▤ On this page</h2><nav id="pm-kb-toc-list"></nav><div><strong>Need to update this guide?</strong><p>Keep your team’s knowledge current.</p>@if(\App\Support\PmsWikiAccess::editPage(auth()->user(), $page))<a href="{{ route('pms.wiki.pages.edit', $page) }}">Edit article →</a>@endif</div></aside>
    </div>
</div>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-wiki-reader.js') }}?v=20261021a"></script>@endsection
