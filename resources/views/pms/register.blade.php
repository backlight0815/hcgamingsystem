@extends('pms.auth-layout')
@section('title', 'Request access')
@section('story-title', 'Great work starts with shared context.')
@section('story-description', 'Join the delivery workspace to collaborate across planning, development, quality assurance, and release.')

@section('panel')
    @php($invitedUser = auth('pms')->user() ?: auth('web')->user())
    <div class="pm-auth-heading">
        <span class="pm-auth-kicker">{{ $invite ? 'PROJECT INVITATION' : 'MEMBERSHIP REQUEST' }}</span>
        <h1 id="pm-auth-title">{{ $invite ? "You're invited to Project Management" : 'Request workspace access' }}</h1>
        <p>{{ $invite ? 'Confirm your invitation and create a dedicated Project Management account.' : 'Create your work account. An administrator will review your membership before private project data becomes available.' }}</p>
    </div>

    @if($invite)
        <div class="pm-auth-notice pm-auth-invitation" role="status">
            <strong>{{ config('app.name') }} Project Management</strong><br>
            @if($invite->invited_name)Invited member: {{ $invite->invited_name }}<br>@endif
            Department: {{ $inviteDepartment?->name ?? 'To be selected' }} · Job position: {{ $invitePosition?->name ?? 'To be selected' }}<br>
            Access role: {{ $invite->role }}<br>
            Project: {{ $invite->project_id ? \App\Models\PmProject::find($invite->project_id)?->name : 'Workspace' }}<br>
            Invited by: {{ \App\Models\User::find($invite->created_by)?->name ?? 'Workspace administrator' }}<br>
            Expires {{ \Carbon\Carbon::parse($invite->expires_at)->format('d M Y, H:i') }}
        </div>
    @endif

    @if($errors->any())
        <div class="pm-auth-notice pm-auth-notice-error" role="alert">
            <strong>Please check the details below.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($invite && $invitedUser)
        <p class="pm-auth-secondary">Signed in as {{ $invitedUser->projectManagementIdentity?->email ?? $invitedUser->email }}.</p>
        <form class="pm-auth-form" method="post" action="{{ route('pms.invitations.accept') }}">@csrf
            <input type="hidden" name="invite" value="{{ $inviteToken }}">
            <button class="pm-auth-submit" type="submit">Accept invitation</button>
        </form>
    @elseif($invite && $existingAccount)
        <p class="pm-auth-secondary">An account already uses this email. Sign in to accept the invitation without creating another identity.</p>
        <a class="pm-auth-submit" href="{{ route('pms.login', ['invite' => $inviteToken]) }}">Sign in and accept</a>
    @else
    <form class="pm-auth-form pm-auth-register-form" method="post" action="{{ route('pms.register.store') }}">
        @csrf
        @if($invite)<input type="hidden" name="invite" value="{{ $inviteToken }}">@endif
        <div class="pm-auth-field">
            <label for="pms-name">Full name</label>
            <input id="pms-name" name="name" value="{{ old('name', $invite?->invited_name) }}" placeholder="Your full name" autocomplete="name" required @if($errors->has('name')) aria-invalid="true" @endif>
        </div>
        <div class="pm-auth-field">
            <label for="pms-email">Work email</label>
            <input id="pms-email" type="email" name="email" value="{{ old('email', $invite?->email) }}" placeholder="name@company.com" autocomplete="email" inputmode="email" required @if($invite?->email && $invite?->email_restricted) readonly @endif @if($errors->has('email')) aria-invalid="true" @endif>
        </div>
        @if($inviteDepartment && $invitePosition)
            <div class="pm-auth-field"><label>Department</label><input value="{{ $inviteDepartment->name }}" readonly><small>Assigned by the inviter</small></div>
            <div class="pm-auth-field"><label>Job position</label><input value="{{ $invitePosition->name }}" readonly><small>Assigned by the inviter</small></div>
        @else
            @include('pms.partials.organization-picker', ['pickerId' => 'pms-department', 'pickerName' => 'department_id', 'pickerLabel' => 'Department', 'pickerPlaceholder' => 'Select your department', 'items' => $departments, 'selected' => old('department_id')])
            @include('pms.partials.organization-picker', ['pickerId' => 'pms-position', 'pickerName' => 'job_position_id', 'pickerLabel' => 'Job position', 'pickerPlaceholder' => 'Select your job position', 'items' => $positions, 'selected' => old('job_position_id'), 'dependsOn' => 'pms-department'])
        @endif
        <div class="pm-auth-field"><label for="pms-job-title">Professional job title (optional)</label><input id="pms-job-title" name="job_title" value="{{ old('job_title', $invitePosition?->name) }}" maxlength="255" placeholder="e.g. Senior Software Engineer"><small>This does not change your assigned position or permissions.</small></div>
        <div class="pm-auth-field">
            <label for="pms-password">Password</label>
            <div class="pm-auth-password">
                <input id="pms-password" type="password" name="password" data-label="password" placeholder="Create a strong password" autocomplete="new-password" minlength="10" required @if($errors->has('password')) aria-invalid="true" @endif>
                <button type="button" data-password-toggle="pms-password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>
        <div class="pm-auth-field">
            <label for="pms-password-confirmation">Confirm password</label>
            <div class="pm-auth-password">
                <input id="pms-password-confirmation" type="password" name="password_confirmation" data-label="confirmation password" placeholder="Repeat your password" autocomplete="new-password" minlength="10" required>
                <button type="button" data-password-toggle="pms-password-confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button>
            </div>
        </div>
        <p class="pm-auth-password-hint">Use at least 10 characters, including upper and lower case letters and a number.</p>
        <button class="pm-auth-submit" type="submit">{{ $invite ? 'Create account and accept invitation' : 'Submit request' }} <span aria-hidden="true">→</span></button>
    </form>
    @endif

    <p class="pm-auth-secondary">Already have access? <a href="{{ route('pms.login', $invite ? ['invite' => $inviteToken] : []) }}">Sign in</a></p>
    <script src="{{ asset('backend/assets/js/pms-org-picker.js') }}" defer></script>
@endsection
