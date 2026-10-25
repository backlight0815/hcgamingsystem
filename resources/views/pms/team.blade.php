@extends('pms.layout')
@section('title', 'Account Management')
@section('content')
<div class="pm-heading pm-admin-heading">
    <div><p class="pm-eyebrow">ADMINISTRATION / ACCOUNTS</p><h1>Account Management</h1>
        <p>Manage people, project roles, and access across the delivery workspace.</p></div>
    <div class="pm-heading-actions"><a class="pm-button" href="{{ route('pms.invitations.index') }}">+ Add user</a></div>
</div>
<section class="pm-account-stats" aria-label="Account summary">
    @foreach(['total' => 'Total users', 'active' => 'Active users', 'inactive' => 'Inactive users', 'suspended' => 'Suspended users'] as $key => $label)
        <div class="pm-account-stat"><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong><small>{{ $canAdmin ? 'Workspace wide' : 'In your managed projects' }}</small></div>
    @endforeach
</section>
<div class="pm-account-layout">
    <section class="pm-panel pm-account-directory">
        <div class="pm-panel-head"><div><p class="pm-eyebrow">DIRECTORY</p><h2>People and access</h2>
            <p class="pm-muted">{{ $profiles->total() }} {{ $profiles->total() === 1 ? 'account' : 'accounts' }} match your filters.</p></div>
            <a href="{{ route('pms.invitations.index') }}">{{ $metrics['pending_invitations'] }} pending invitations</a>
        </div>
        <form method="get" class="pm-account-filters" aria-label="Filter accounts">
            <label class="pm-account-search">Search people<input name="q" type="search" value="{{ request('q') }}" placeholder="Name or email"></label>
            <label>Status<select name="status"><option value="">All statuses</option>@foreach(['active'=>'Active','pending'=>'Pending','inactive'=>'Inactive','suspended'=>'Suspended','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Role<select name="role"><option value="">All roles</option>@foreach(\App\Support\PmsAccess::ROLES as $role)<option value="{{ $role }}" @selected(request('role')===$role)>{{ $role }}</option>@endforeach</select></label>
            <label>Department<select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id')==$department->id)>{{ $department->name }}</option>@endforeach</select></label>
            <label>Job position<select name="job_position_id"><option value="">All positions</option>@foreach($positions->unique('id') as $position)<option value="{{ $position->id }}" @selected(request('job_position_id')==$position->id)>{{ $position->name }}</option>@endforeach</select></label>
            <label>Project<select name="project_id"><option value="">All visible projects</option>@foreach($filterProjects as $project)<option value="{{ $project->id }}" @selected(request('project_id')==$project->id)>{{ $project->name }}</option>@endforeach</select></label>
            <label>Sort by<select name="sort"><option value="newest" @selected(request('sort','newest')==='newest')>Newest joined</option><option value="oldest" @selected(request('sort')==='oldest')>Oldest joined</option><option value="name" @selected(request('sort')==='name')>Name A–Z</option><option value="name_desc" @selected(request('sort')==='name_desc')>Name Z–A</option><option value="last_login" @selected(request('sort')==='last_login')>Recent sign-in</option><option value="status" @selected(request('sort')==='status')>Account status</option></select></label>
            <div class="pm-account-filter-actions"><button class="pm-button secondary">Apply filters</button><a href="{{ route('pms.team.index') }}">Clear filters</a></div>
        </form>
        @if($profiles->isEmpty())
            <div class="pm-empty"><strong>No accounts found.</strong><p>Try a different search or clear the filters.</p><a href="{{ route('pms.team.index') }}">Clear filters</a></div>
        @else
            <div class="pm-table-wrap"><table class="pm-table pm-account-table"><thead><tr><th>User</th><th>Job position</th><th>Department</th><th>Role</th><th>Projects</th><th>Status</th><th>Last sign-in</th><th>Actions</th></tr></thead><tbody>
                @foreach($profiles as $profile)
                    @php($memberProjects = $memberships[$profile->user_id] ?? collect())
                    @php($roles = $memberProjects->pluck('role')->unique())
                    <tr><td data-label="User"><a class="pm-account-user" href="{{ route('pms.team.show', $profile) }}"><span class="pm-avatar" aria-hidden="true">{{ strtoupper(mb_substr($profile->user->name,0,1)) }}</span><span><strong>{{ $profile->user->name }}</strong><small>{{ $profile->user->projectManagementIdentity?->email ?? $profile->user->email }}</small></span></a></td>
                        <td data-label="Job position">{{ $profile->job_position ?: 'Not set' }}</td>
                        <td data-label="Department">{{ $profile->department ?: 'Not set' }}</td>
                        <td data-label="Role"><strong>{{ $profile->workspace_role ?: ($roles->first() ?: 'Project scoped') }}</strong>@if($roles->count()>1)<small>+{{ $roles->count()-1 }} project roles</small>@endif</td>
                        <td data-label="Projects">{{ $memberProjects->pluck('project_id')->unique()->count() }}</td>
                        <td data-label="Status"><span class="pm-account-status" data-status="{{ $profile->status }}">{{ ucfirst($profile->status) }}</span></td>
                        <td data-label="Last sign-in">{{ $profile->user->projectManagementIdentity?->last_login_at ? \Carbon\Carbon::parse($profile->user->projectManagementIdentity->last_login_at)->diffForHumans() : 'Not recorded' }}</td>
                        <td data-label="Actions"><a href="{{ route('pms.team.show',$profile) }}">View details</a>
                            @if(auth()->user()->isGlobalSuperAdmin() && $profile->user_id !== auth()->id())
                                <details class="pm-account-row-actions"><summary>Access actions</summary>
                                    @foreach($profile->status === 'active' ? ['inactive'=>'Deactivate','suspended'=>'Suspend'] : ['active'=>'Activate'] as $status => $label)
                                        <form method="post" action="{{ route('pms.team.update',$profile) }}" data-confirm="{{ $label }} {{ $profile->user->name }}? Their PMS access will {{ $status === 'active' ? 'resume' : 'stop' }}.">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $status }}"><button class="pm-link {{ $status === 'suspended' ? 'danger' : '' }}">{{ $label }}</button></form>
                                    @endforeach
                                </details>
                            @endif
                        </td></tr>
                @endforeach
            </tbody></table></div><div class="pm-pagination">{{ $profiles->links() }}</div>
        @endif
    </section>
    @if($canAdmin)
    <aside class="pm-account-aside"><details class="pm-panel pm-form-panel" @if($errors->any()) open @endif><summary>Activate an existing user</summary>
        <p class="pm-muted">Already has a back-office account? Give that person access to PMS here. New people join through an invitation.</p>
        <form method="post" action="{{ route('pms.team.provision') }}" class="pm-account-form">@csrf
            <label>Existing account email <span aria-hidden="true">*</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Department<select name="department" data-account-department><option value="">Not assigned</option>@foreach($departments->where('active',1) as $department)<option value="{{ $department->name }}" data-id="{{ $department->id }}" @selected(old('department')===$department->name)>{{ $department->name }}</option>@endforeach</select></label>
            <label>Job position<select name="job_position" data-account-position><option value="">Not assigned</option>@foreach($positions->where('active',1)->unique('id') as $position)<option value="{{ $position->name }}" data-departments="{{ $positions->where('id',$position->id)->pluck('department_id')->implode(',') }}" @selected(old('job_position')===$position->name)>{{ $position->name }}</option>@endforeach</select></label>
            <label>Job title<input name="job_title" value="{{ old('job_title') }}" maxlength="255"></label>
            <label>Workspace role<select name="workspace_role"><option value="">Project scoped</option>@foreach(['Business Analyst','Project Manager','QA'] as $role)<option value="{{ $role }}" @selected(old('workspace_role')===$role)>{{ $role }}</option>@endforeach</select></label>
            <label class="pm-check"><input type="checkbox" name="can_create_all_tickets" value="1" @checked(old('can_create_all_tickets'))> Explicit ticket creation grant</label>
            <button class="pm-button">Activate membership</button>
        </form>
    </details></aside>
    @endif
</div>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-admin-refresh.js') }}"></script>@endsection
