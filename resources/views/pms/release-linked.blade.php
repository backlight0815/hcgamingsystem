@extends('pms.layout')
@section('title', $release->release_key)
@section('content')
<div class="pm-heading"><div><p class="pm-eyebrow"><a href="{{ route('pms.releases.index') }}">RELEASE MANAGEMENT</a> / {{ $release->release_key }}</p><h1>{{ $release->name }}</h1><p>Shared release planning link</p></div><span class="pm-pill">{{ $release->status }}</span></div>
<section class="pm-panel"><div class="pm-panel-head"><h2>Release association</h2></div><dl class="pm-facts"><dt>Release</dt><dd>{{ $release->release_key }}</dd><dt>Development Cycle</dt><dd>{{ $planning['cycle'] ?? 'Unassigned' }}</dd><dt>Planned deployment</dt><dd>{{ $planning['planned_deploy_date'] ?? 'Unassigned' }}</dd><dt>Associated Projects</dt><dd>@foreach($planning['projects'] as $project)<div><a href="{{ route('pms.projects.show',$project['id']) }}">{{ $project['reference'] }} · {{ $project['name'] }}</a></div>@endforeach</dd></dl><p class="pm-muted">Ticket scope and deployment controls belong to the release's owning project.</p></section>
@endsection
