@extends('pms.layout')
@section('title','Revisions · '.$page->title)
@section('content')
<div class="pm-heading"><div><p class="pm-eyebrow"><a href="{{ route('pms.wiki.pages.show',$page) }}">{{ $page->title }}</a> / HISTORY</p><h1>Revision history</h1><p>Every save creates a version. Restoring creates a new version and keeps the earlier record.</p></div></div>
<section class="pm-panel">@foreach($revisions as $revision)<details class="pm-wiki-revision"><summary>Version {{ $revision->version }} · {{ $page->system_key && $revision->version === 1 ? 'Team training material' : ($revision->editor_name ?? 'Former member') }} · {{ \App\Support\PmsDateTime::format($revision->created_at) }} · {{ ucfirst($revision->status) }}</summary><h3>{{ $revision->title }}</h3><pre>{{ $revision->content }}</pre>@if(\App\Support\PmsWikiAccess::editPage(auth()->user(),$page))<form method="post" action="{{ route('pms.wiki.pages.restore',[$page,$revision->version]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $page->lock_version }}"><button class="pm-button secondary">Restore this version</button></form>@endif</details>@endforeach{{ $revisions->links() }}</section>
@endsection
