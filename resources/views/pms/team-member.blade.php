@extends('pms.layout')
@section('title', $profile->user->name)
@section('content')
<div class="pm-heading pm-admin-heading"><div><p class="pm-eyebrow"><a href="{{ route('pms.team.index') }}">ACCOUNT MANAGEMENT</a> / USER</p>
    <h1>{{ $profile->user->name }}</h1><p>{{ $profile->user->projectManagementIdentity?->email ?? $profile->user->email }}</p></div>
    <div class="pm-heading-actions"><span class="pm-account-status" data-status="{{ $profile->status }}">{{ ucfirst($profile->status) }}</span>@if(auth()->user()->isGlobalSuperAdmin())<a class="pm-button secondary" href="#account-edit">Edit account</a>@endif</div></div>
<nav class="pm-account-tabs" aria-label="Account sections"><a href="#account-personal">Profile</a><a href="#account-access">Access</a><a href="#account-projects">Projects</a><a href="#account-activity">Activity</a></nav>
<div class="pm-account-detail-grid">
    <div>
        <section class="pm-panel" id="account-personal"><div class="pm-panel-head"><div><p class="pm-eyebrow">01 / PROFILE</p><h2>Personal information</h2></div></div>
            <dl class="pm-account-facts"><div><dt>Full name</dt><dd>{{ $profile->user->name }}</dd></div><div><dt>Email address</dt><dd>{{ $profile->user->projectManagementIdentity?->email ?? $profile->user->email }}</dd></div>
                <div><dt>Contact number</dt><dd>{{ $profile->user->projectManagementIdentity?->phone ?: 'Not recorded' }}</dd></div><div><dt>Department</dt><dd>{{ $profile->department ?: 'Not recorded' }}</dd></div>
                <div><dt>Job position</dt><dd>{{ $profile->job_position ?: 'Not recorded' }}</dd></div><div><dt>Job title</dt><dd>{{ $profile->job_title ?: 'Not recorded' }}</dd></div></dl>
        </section>
        <section class="pm-panel" id="account-projects"><div class="pm-panel-head"><div><p class="pm-eyebrow">03 / ASSIGNMENTS</p><h2>Project access</h2></div><span class="pm-count">{{ $projects->count() }}</span></div>
            @forelse($projects as $project)<div class="pm-account-project"><div><a href="{{ route('pms.projects.show',$project->id) }}"><strong>{{ $project->key }} · {{ $project->name }}</strong></a><small>Assigned {{ $project->assigned_at ? \Carbon\Carbon::parse($project->assigned_at)->format('d M Y') : 'date not recorded' }}</small></div><div><span class="pm-pill">{{ $project->role }}</span><small>{{ $project->archived_at ? 'Archived' : 'Active project' }}</small></div></div>
            @empty<div class="pm-empty">No project assignments in your scope.</div>@endforelse
            @if(auth()->user()->isGlobalSuperAdmin())
                <details class="pm-account-project-editor"><summary>Manage project membership</summary>
                    <form method="post" action="{{ route('pms.team.projects.store',$profile) }}" class="pm-form-grid">@csrf
                        <label>Project<select name="project_id" required><option value="">Choose project</option>@foreach($assignableProjects as $project)<option value="{{ $project->id }}">{{ $project->key }} · {{ $project->name }}</option>@endforeach</select></label>
                        <label>Project role<select name="role" required>@foreach(\App\Support\PmsAccess::ROLES as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select></label>
                        <button class="pm-button secondary">Assign or update</button>
                    </form>
                    @foreach($projects as $project)<form method="post" action="{{ route('pms.team.projects.destroy',[$profile,$project->id]) }}" data-confirm="Remove {{ $profile->user->name }} from {{ $project->name }}?">@csrf @method('DELETE')<button class="pm-link danger">Remove from {{ $project->name }}</button></form>@endforeach
                </details>
            @endif
        </section>
        <section class="pm-panel" id="account-activity"><div class="pm-panel-head"><div><p class="pm-eyebrow">04 / HISTORY</p><h2>Account activity</h2></div></div>
            @forelse($accountHistory as $event)<div class="pm-account-event"><strong>{{ ucwords(str_replace(['membership.','_'],['',' '],$event->action)) }}</strong><span>{{ $event->actor_name ?: 'Former administrator' }} · {{ \App\Support\PmsDateTime::format($event->created_at) }}</span></div>
            @empty<div class="pm-empty">No account changes have been recorded.</div>@endforelse
        </section>
    </div>
    <aside>
        <section class="pm-panel" id="account-access"><div class="pm-panel-head"><div><p class="pm-eyebrow">02 / ACCESS</p><h2>Permissions and status</h2></div></div>
            <dl class="pm-account-facts"><div><dt>Account status</dt><dd><span class="pm-account-status" data-status="{{ $profile->status }}">{{ ucfirst($profile->status) }}</span></dd></div>
                <div><dt>Workspace role</dt><dd>{{ $profile->workspace_role ?: 'Project scoped' }}</dd></div>
                <div><dt>Effective access</dt><dd>{{ auth()->user()->isGlobalSuperAdmin() && $profile->user->isGlobalSuperAdmin() ? 'System administration' : ($profile->can_create_all_tickets ? 'Ticket creation grant plus project roles' : 'Assigned project roles') }}</dd></div>
                <div><dt>Last sign-in</dt><dd>{{ $profile->user->projectManagementIdentity?->last_login_at ? \App\Support\PmsDateTime::format($profile->user->projectManagementIdentity->last_login_at) : 'Not recorded' }}</dd></div>
                <div><dt>Joined</dt><dd>{{ $profile->created_at?->format('d M Y') }}</dd></div><div><dt>Registration</dt><dd>{{ ucfirst($registrationSource) }}</dd></div></dl>
        </section>
        <section class="pm-panel"><div class="pm-panel-head"><h2>Assigned work</h2></div><div class="pm-account-work-stats"><div><strong>{{ $metrics['assigned'] }}</strong><span>Tickets</span></div><div><strong>{{ $metrics['completed'] }}</strong><span>Completed</span></div><div><strong>{{ $metrics['outstanding'] }}</strong><span>Open</span></div></div>
            @if($recentTickets->isNotEmpty())<ul class="pm-account-ticket-list">@foreach($recentTickets as $ticket)<li><a href="{{ route('pms.tickets.show',$ticket) }}">{{ $ticket->reference }} · {{ $ticket->summary }}</a><small>{{ $ticket->status }}</small></li>@endforeach</ul>@else<p class="pm-muted">No assigned tickets.</p>@endif
        </section>
    </aside>
</div>
@if(auth()->user()->isGlobalSuperAdmin())
<section class="pm-panel pm-account-edit" id="account-edit"><div class="pm-panel-head"><div><p class="pm-eyebrow">ADMINISTRATOR CONTROLS</p><h2>Edit account and access</h2></div></div>
    <form method="post" action="{{ route('pms.team.update',$profile) }}" data-confirm="Save changes to {{ $profile->user->name }}?" class="pm-account-edit-form">@csrf @method('PUT')
        <fieldset><legend>Personal information</legend><div class="pm-form-grid">
            <label>Full name <span aria-hidden="true">*</span><input name="name" value="{{ old('name',$profile->user->name) }}" required maxlength="255"></label>
            <label>Email address <span aria-hidden="true">*</span><input name="email" type="email" value="{{ old('email',$profile->user->projectManagementIdentity?->email ?? $profile->user->email) }}" required></label>
            <label>Contact number<input name="phone" type="tel" maxlength="40" value="{{ old('phone',$profile->user->projectManagementIdentity?->phone) }}"></label>
        </div></fieldset>
        <fieldset><legend>Organization</legend><div class="pm-form-grid">
            <label>Department<select name="department" data-account-department><option value="">Not assigned</option>@foreach($departments as $department)<option value="{{ $department->name }}" data-id="{{ $department->id }}" @selected(old('department',$profile->department)===$department->name)>{{ $department->name }}</option>@endforeach @if($profile->department && !$departments->contains('name',$profile->department))<option value="{{ $profile->department }}" data-id="{{ $profile->department_id }}" selected>{{ $profile->department }} (existing)</option>@endif</select></label>
            <label>Job position<select name="job_position" data-account-position><option value="">Not assigned</option>@foreach($positions->unique('id') as $position)<option value="{{ $position->name }}" data-departments="{{ $positions->where('id',$position->id)->pluck('department_id')->implode(',') }}" @selected(old('job_position',$profile->job_position)===$position->name)>{{ $position->name }}</option>@endforeach @if($profile->job_position && !$positions->contains('name',$profile->job_position))<option value="{{ $profile->job_position }}" data-departments="{{ $profile->department_id }}" selected>{{ $profile->job_position }} (existing)</option>@endif</select></label>
            <label>Job title<input name="job_title" maxlength="255" value="{{ old('job_title',$profile->job_title) }}"></label>
        </div></fieldset>
        <fieldset><legend>Account access</legend><div class="pm-form-grid">
            <label>Status<select name="status" required>@if($profile->status==='pending')<option value="" selected disabled>Pending — choose a decision</option>@endif @foreach(['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$profile->status)===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Workspace role<select name="workspace_role"><option value="">Project scoped</option>@foreach(['Business Analyst','Project Manager','QA'] as $role)<option value="{{ $role }}" @selected(old('workspace_role',$profile->workspace_role)===$role)>{{ $role }}</option>@endforeach</select></label>
            <label class="pm-check"><input type="hidden" name="can_create_all_tickets" value="0"><input type="checkbox" name="can_create_all_tickets" value="1" @checked(old('can_create_all_tickets',$profile->can_create_all_tickets))> Explicit ticket creation grant</label>
            <label class="wide">Review note<textarea name="review_note" rows="2">{{ old('review_note') }}</textarea></label>
        </div></fieldset>
        <div class="pm-form-actions"><button class="pm-button">Save account</button><a class="pm-button secondary" href="{{ route('pms.team.index') }}">Back to directory</a></div>
    </form>
</section>
@endif
@if($canAdmin)@include('pms.partials.team-organization')@endif
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-admin-refresh.js') }}"></script>@endsection
