@extends('pms.layout')
@section('title', 'Projects')
@section('content')
<div class="pm-heading"><div><p class="pm-eyebrow">PORTFOLIO</p><h1>{{ request()->boolean('trash') ? 'Recently deleted' : (request()->boolean('archived') ? 'Project archive' : 'Projects') }}</h1><p>Manage active delivery, retained history, and recoverable deletion separately.</p></div></div>
@if(\App\Support\PmsAccess::administrator(auth()->user()) && !request()->boolean('trash') && !request()->boolean('archived'))
<details class="pm-panel pm-form-panel"><summary>Create project</summary><form method="post" action="{{ route('pms.projects.store') }}" class="pm-form-grid">@csrf
    <label>Name<input name="name" value="{{ old('name') }}" required></label><label>Project key<input name="key" value="{{ old('key') }}" placeholder="PMS" required maxlength="12"></label>
    <label>Priority<select name="priority">@foreach(\App\Support\PmsPriority::OPTIONS as $code=>$label)<option value="{{ $code }}" @selected(old('priority','P3')===$code)>{{ $label }}</option>@endforeach</select></label>
    <label>Category<input name="category" value="{{ old('category') }}"></label>
    <label>Project Owner<input type="search" data-owner-search placeholder="Search active PMS users" autocomplete="off"><select name="owner_id" data-owner-select><option value="">Use project creator</option>@foreach($ownerCandidates as $candidate)<option value="{{ $candidate->id }}" @selected(old('owner_id')==$candidate->id)>{{ $candidate->name }} · {{ $candidate->pmsProfile?->job_position ?: ($candidate->isBackofficeAdministrator() ? 'Administrator' : 'PMS member') }} · Active</option>@endforeach</select><small>Ownership does not grant Project Manager access.</small></label>
    <label>Target date<input type="date" name="target_date" value="{{ old('target_date') }}"></label><label>Related module<select name="related_module"><option value="">General</option><option>TradingHub</option><option>E-commerce</option><option>PMS</option></select></label>
    <label class="wide">Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label><label class="wide">Objective<textarea name="objective" rows="2">{{ old('objective') }}</textarea></label><button class="pm-button">Create project</button>
</form></details>
@endif
<div class="pm-panel"><form class="pm-toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search name, key or PJ reference"><select name="status"><option value="">All statuses</option>@foreach(['Planned','Approved','Active','On Hold','Completed','Cancelled'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="pm-button secondary">Filter</button><a href="{{ route('pms.projects.index') }}">Active</a><a href="{{ route('pms.projects.index',['archived'=>1]) }}">Archive</a>@if(\App\Support\PmsAccess::administrator(auth()->user()))<a href="{{ route('pms.projects.index',['trash'=>1]) }}">Recently deleted</a>@endif</form>
@if($projects->isEmpty())<div class="pm-empty">No projects match this view.</div>@else
@php
    $projectSort = request('sort_by', 'updated_at');
    $projectDirection = request('sort_order', 'desc');
@endphp
<div class="pm-table-wrap"><table class="pm-table"><thead><tr>
@foreach(['project_reference'=>'Project ID','name'=>'Project','owner'=>'Project Owner','status'=>'Status','updated_at'=>'Updated'] as $column=>$label)
<th aria-sort="{{ $projectSort === $column ? ($projectDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ route('pms.projects.index', array_merge(request()->except('page'), ['sort_by'=>$column,'sort_order'=>$projectSort === $column && $projectDirection === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }} {{ $projectSort === $column ? ($projectDirection === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
@endforeach
<th>Key</th><th>Priority</th><th>Target</th><th>Action</th></tr></thead><tbody>
@foreach($projects as $project)<tr><td><strong>{{ $project->project_reference }}</strong></td><td><strong>{{ $project->name }}</strong><small>{{ $project->category }}</small></td><td>{{ $project->owner?->name ?: '—' }}</td><td><span class="pm-pill">{{ $project->status }}</span></td><td>{{ \App\Support\PmsDateTime::format($project->updated_at) }}</td><td>{{ $project->key }}</td><td>{{ \App\Support\PmsPriority::label($project->priority) }}</td><td>{{ $project->target_date?->toDateString() ?: '—' }}</td><td>
@if(request()->boolean('trash'))<div class="pm-actions"><form method="post" action="{{ route('pms.projects.restore',$project->id) }}">@csrf<button class="pm-link">Restore</button></form><form method="post" action="{{ route('pms.projects.purge',$project->id) }}" class="pm-confirm-inline" onsubmit="return confirm('Permanently purge this project and its project-owned records?')">@csrf @method('DELETE')<input name="confirmation" placeholder="Type {{ $project->key }}" required><button class="pm-link danger">Purge</button></form></div>
@elseif(request()->boolean('archived'))<div class="pm-actions"><form method="post" action="{{ route('pms.projects.unarchive',$project) }}">@csrf<button class="pm-link">Restore from archive</button></form><a href="{{ route('pms.projects.show',$project) }}">View history</a></div>
@else<a href="{{ route('pms.projects.show',$project) }}">Open →</a>@endif
</td></tr>@endforeach</tbody></table></div><div class="pm-pagination">{{ $projects->links() }}</div>@endif</div>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-project-owner.js') }}"></script>
@if(request()->boolean('create') && \App\Support\PmsAccess::administrator(auth()->user()))<script>const pmCreateProject=document.querySelector('details.pm-form-panel');if(pmCreateProject){pmCreateProject.open=true;pmCreateProject.querySelector('input[name="name"]')?.focus()}</script>@endif
@endsection
