@extends('pms.layout')
@section('title', isset($page) ? 'Edit '.$page->title : 'Create article')
@section('content')
@php $articleStatus = old('status', $page->status ?? 'draft'); @endphp
<div class="pm-kb pm-kb-studio">
    <form id="pm-kb-editor-form" method="post" action="{{ isset($page) ? route('pms.wiki.pages.update', $page) : route('pms.wiki.pages.store') }}">
        @csrf
        @isset($page) @method('PUT')<input type="hidden" name="lock_version" value="{{ $page->lock_version }}"> @endisset
        <div class="pm-kb-studio-heading">
            <a class="pm-kb-back" href="{{ isset($page) ? route('pms.wiki.pages.show', $page) : route('pms.wiki.index') }}">← Back to Knowledge Base</a>
            <div class="pm-kb-studio-title"><div><p class="pm-kb-eyebrow">PUBLISHING STUDIO</p><h1>{{ isset($page) ? 'Edit article' : 'New article' }}</h1><p>Write a guide your team can find, follow and maintain.</p></div><div class="pm-kb-studio-actions"><button type="button" class="pm-kb-secondary" id="pm-wiki-preview-toggle" aria-expanded="false" aria-controls="pm-wiki-preview">Preview</button><button type="submit" class="pm-kb-primary" @disabled($spaces->isEmpty())>{{ isset($page) ? 'Save new revision' : 'Create article' }}</button></div></div>
        </div>

        <div class="pm-kb-workflow" aria-label="Publication status"><div @class(['active' => $articleStatus === 'draft'])><span>1</span><strong>Draft</strong><small>Work on your article</small></div><div @class(['active' => $articleStatus === 'published'])><span>2</span><strong>Published</strong><small>Available to readers</small></div></div>

        @if($spaces->isEmpty())<div class="pm-kb-no-space" role="status">Create a documentation space before writing an article. <a href="{{ route('pms.wiki.index') }}">Go to Knowledge Base</a></div>@endif

        <div class="pm-kb-studio-grid">
            <section class="pm-kb-editor-card" aria-label="Article editor">
                <div class="pm-kb-editor-intro"><label for="pm-kb-title">Article title</label><input id="pm-kb-title" name="title" maxlength="255" required value="{{ old('title', $page->title ?? '') }}" placeholder="Give this article a clear title"><label for="pm-kb-summary">Short description</label><textarea id="pm-kb-summary" name="summary" maxlength="500" rows="2" placeholder="What will your team learn?">{{ old('summary', $page->summary ?? '') }}</textarea></div>
                <div class="pm-wiki-toolbar pm-kb-toolbar" aria-label="Markdown formatting tools"><button type="button" data-wiki-insert="# " title="Heading 1">H1</button><button type="button" data-wiki-insert="## " title="Heading 2">H2</button><button type="button" data-wiki-insert="### " title="Heading 3">H3</button><span aria-hidden="true"></span><button type="button" data-wiki-wrap="**" title="Bold"><b>B</b></button><button type="button" data-wiki-wrap="*" title="Italic"><i>I</i></button><button type="button" data-wiki-wrap="++" title="Underline"><u>U</u></button><span aria-hidden="true"></span><button type="button" data-wiki-insert="- " title="Bulleted list">≡</button><button type="button" data-wiki-insert="1. " title="Numbered list">1.</button><button type="button" data-wiki-insert="- [ ] " title="Checklist">☑</button><button type="button" data-wiki-insert="> " title="Quote">❝</button><button type="button" data-wiki-wrap="`" title="Inline code">&lt;/&gt;</button><button type="button" data-wiki-before="&#10;```&#10;" data-wiki-after="&#10;```&#10;" title="Code block">{ }</button><button type="button" data-wiki-before="[" data-wiki-after="](https://)" title="Link">🔗</button><button type="button" data-wiki-insert="![image description](https://)" title="Image">▧</button><button type="button" data-wiki-insert="| Column | Column |&#10;| --- | --- |&#10;| Value | Value |" title="Table">▦</button><button type="button" data-wiki-insert="---" title="Rule">—</button><button type="button" data-wiki-insert="> **Note:** " title="Callout">!</button></div>
                <label class="pm-kb-content-label" for="pm-wiki-body">Article content <span>Markdown supported</span></label><textarea id="pm-wiki-body" name="content" rows="22" placeholder="Start writing your article...">{{ old('content', $page->content ?? $templateText ?? '') }}</textarea>
                <p class="pm-kb-editor-help">Use the toolbar to insert Markdown. Preview shows how the article will appear to readers.</p>
                <section class="pm-kb-preview-panel" id="pm-wiki-preview" data-preview-url="{{ route('pms.wiki.pages.preview') }}" hidden><div class="pm-kb-extra-heading"><h2>Article preview</h2><span>Reader view</span></div><div class="pm-wiki-article pm-kb-prose"></div></section>
            </section>

            <aside class="pm-kb-settings" aria-label="Article settings"><h2>Article settings</h2>
                <label>Space<select name="space_id" required>@foreach($spaces as $space)<option value="{{ $space->id }}" @selected(old('space_id', $page->space_id ?? request('space')) == $space->id)>{{ $space->name }}</option>@endforeach</select><small>Choose where this article belongs.</small></label>
                <label>Parent page<select name="parent_id"><option value="">Top level</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $page->parent_id ?? null) == $parent->id)>{{ $parent->title }}</option>@endforeach</select></label>
                <label>Category<select name="category"><option value="">Uncategorised</option>@foreach(\App\Models\PmWikiPage::CATEGORIES as $category)<option value="{{ $category }}" @selected(old('category', $page->category ?? '') === $category)>{{ $category }}</option>@endforeach</select></label>
                <label>Audience<input name="audience" maxlength="255" value="{{ old('audience', $page->audience ?? '') }}" placeholder="QA Engineers, Business Analysts"></label>
                <label>Project<select name="project_id"><option value="">Workspace-wide</option>@if(isset($page) && $page->project_id && !$projects->contains('id', $page->project_id))<option value="{{ $page->project_id }}" selected>{{ $page->project?->name ?: 'Current project' }}</option>@endif @foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id', $page->project_id ?? '') == $project->id)>{{ $project->name }}</option>@endforeach</select><small>Project articles are visible only to assigned members unless shared workspace-wide.</small></label>
                <label>Tags<input name="tags" value="{{ old('tags', implode(', ', $page->tags ?? [])) }}" placeholder="deployment, api, qat"><small>Separate tags with commas.</small></label>
                <div class="pm-kb-settings-divider"></div>
                <label>Publication status<select name="status" id="pm-kb-status"><option value="draft" @selected($articleStatus === 'draft')>Save draft</option><option value="published" @selected($articleStatus === 'published')>Publish article</option></select><small>Published articles appear in Knowledge Base search for readers with access.</small></label>
                @isset($page)<p class="pm-kb-settings-note">Revision {{ $page->revision_number }} · Updated {{ $page->updated_at->format('j M Y') }}</p>@endisset
            </aside>
        </div>
    </form>
</div>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-wiki-editor.js') }}?v=20261021a"></script>@endsection
