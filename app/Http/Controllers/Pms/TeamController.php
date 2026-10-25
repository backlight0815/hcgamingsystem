<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmsProfile;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $canAdmin = PmsAccess::administrator($request->user());
        $projects = PmProject::whereNull('archived_at');
        if (! $canAdmin) {
            $projects->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id)
                ->whereIn('pm_project_members.role', ['Project Manager', 'Project Administrator']));
        }
        $filterProjects = (clone $projects)->orderBy('name')->get(['id', 'name']);
        $projectIds = $filterProjects->pluck('id');
        abort_unless($canAdmin || $projectIds->isNotEmpty(), 403);
        $query = PmsProfile::query()->with('user:id,name,email,status', 'user.projectManagementIdentity:id,user_id,email,last_login_at')
            ->join('users', 'users.id', '=', 'pms_profiles.user_id')
            ->leftJoin('project_management_users as identities', 'identities.user_id', '=', 'users.id')
            ->select('pms_profiles.*');
        if (! $canAdmin) {
            $query->whereIn('pms_profiles.user_id', DB::table('pm_project_members')->whereIn('project_id', $projectIds)->select('user_id'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('users.name', 'like', '%'.$term.'%')
                ->orWhere('users.email', 'like', '%'.$term.'%')
                ->orWhereIn('users.id', DB::table('project_management_users')->where('email', 'like', '%'.$term.'%')->select('user_id')));
        }
        if (in_array($request->input('status'), ['active', 'pending', 'inactive', 'suspended', 'rejected'], true)) {
            $query->where('pms_profiles.status', $request->status);
        }
        if ($request->filled('department_id')) $query->where('pms_profiles.department_id', $request->integer('department_id'));
        if ($request->filled('job_position_id')) $query->where('pms_profiles.job_position_id', $request->integer('job_position_id'));
        if ($request->filled('project_id') && $projectIds->contains($request->integer('project_id'))) {
            $query->whereIn('pms_profiles.user_id', DB::table('pm_project_members')
                ->where('project_id', $request->integer('project_id'))->select('user_id'));
        }
        if (in_array($request->input('role'), PmsAccess::ROLES, true)) {
            $query->where(fn ($q) => $q->where('pms_profiles.workspace_role', $request->role)
                ->orWhereIn('pms_profiles.user_id', DB::table('pm_project_members')
                    ->whereIn('project_id', $projectIds)->where('role', $request->role)->select('user_id')));
        }
        match ($request->input('sort')) {
            'name' => $query->orderBy('users.name'),
            'name_desc' => $query->orderByDesc('users.name'),
            'status' => $query->orderBy('pms_profiles.status')->orderBy('users.name'),
            'last_login' => $query->orderByDesc('identities.last_login_at')->orderBy('users.name'),
            'oldest' => $query->orderBy('pms_profiles.created_at'),
            default => $query->orderByDesc('pms_profiles.created_at'),
        };
        $profiles = $query->paginate(20)->withQueryString();
        $base = PmsProfile::query();
        if (! $canAdmin) {
            $base->whereIn('user_id', DB::table('pm_project_members')->whereIn('project_id', $projectIds)->select('user_id'));
        }
        $metrics = [
            'total' => (clone $base)->count(), 'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
            'suspended' => (clone $base)->where('status', 'suspended')->count(),
            'roles' => count(PmsAccess::ROLES),
            'pending_invitations' => DB::table('pms_invitations')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->whereColumn('used_count', '<', 'max_uses')
                ->when(! $canAdmin, fn ($q) => $q->whereIn('project_id', $projectIds))->count(),
        ];
        $memberships = DB::table('pm_project_members')->join('pm_projects', 'pm_projects.id', '=', 'pm_project_members.project_id')
            ->whereIn('pm_project_members.user_id', $profiles->pluck('user_id'))
            ->whereIn('pm_project_members.project_id', $projectIds)->whereNull('pm_projects.deleted_at')
            ->get(['pm_project_members.user_id', 'pm_project_members.role', 'pm_projects.name as project_name', 'pm_projects.id as project_id'])
            ->groupBy('user_id');

        $departments = DB::table('pms_departments')->orderBy('name')->get(['id', 'name', 'active']);
        $positions = DB::table('pms_job_positions')
            ->leftJoin('pms_department_positions', 'pms_department_positions.position_id', '=', 'pms_job_positions.id')
            ->orderBy('pms_job_positions.name')->get(['pms_job_positions.id', 'pms_job_positions.name',
                'pms_job_positions.active', 'pms_department_positions.department_id']);

        return $request->expectsJson() ? response()->json(compact('profiles', 'metrics', 'memberships'))
            : view('pms.team', compact('profiles', 'metrics', 'memberships', 'canAdmin', 'filterProjects', 'departments', 'positions'));
    }

    public function show(Request $request, PmsProfile $profile)
    {
        $canAdmin = PmsAccess::administrator($request->user());
        $projectIds = $canAdmin ? PmProject::pluck('id') : PmProject::whereHas('members', fn ($q) => $q
            ->where('users.id', $request->user()->id)
            ->whereIn('pm_project_members.role', ['Project Manager', 'Project Administrator']))->pluck('id');
        abort_unless($canAdmin || DB::table('pm_project_members')->whereIn('project_id', $projectIds)
            ->where('user_id', $profile->user_id)->exists(), 403);
        $profile->load('user.projectManagementIdentity');
        $projects = DB::table('pm_project_members')->join('pm_projects', 'pm_projects.id', '=', 'pm_project_members.project_id')
            ->whereIn('pm_project_members.project_id', $projectIds)->where('pm_project_members.user_id', $profile->user_id)
            ->whereNull('pm_projects.deleted_at')->get(['pm_projects.id', 'pm_projects.key', 'pm_projects.name',
                'pm_projects.archived_at', 'pm_project_members.role', 'pm_project_members.created_at as assigned_at']);
        $tickets = PmWorkItem::whereIn('project_id', $projectIds)
            ->where(fn ($q) => $q->where('developer_id', $profile->user_id)->orWhere('qa_id', $profile->user_id)
                ->orWhere('project_manager_id', $profile->user_id));
        $metrics = [
            'assigned' => (clone $tickets)->count(),
            'completed' => (clone $tickets)->whereIn('status', ['Completed', 'QAT Done', 'Production Done'])->count(),
            'outstanding' => (clone $tickets)->whereNotIn('status', ['Completed', 'QAT Done', 'Production Done', 'Cancelled'])->count(),
        ];
        $recentTickets = $tickets->orderByDesc('updated_at')->limit(10)->get(['id', 'reference', 'issue_key', 'summary', 'status', 'project_id']);
        $registrationSource = DB::table('pms_registrations')->where('user_id', $profile->user_id)->orderBy('id')->value('source') ?: 'administrator';
        $accountHistory = DB::table('pm_audit_logs')->leftJoin('users as actors', 'actors.id', '=', 'pm_audit_logs.actor_id')
            ->where('pm_audit_logs.subject_type', 'membership')->where('pm_audit_logs.subject_id', $profile->id)
            ->orderByDesc('pm_audit_logs.id')->limit(20)->get(['pm_audit_logs.*', 'actors.name as actor_name']);
        $assignableProjects = $canAdmin ? PmProject::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'key']) : collect();
        $departments = $canAdmin ? DB::table('pms_departments')->where('active', true)->orderBy('name')->get() : collect();
        $positions = $canAdmin ? DB::table('pms_job_positions')->join('pms_department_positions', 'pms_department_positions.position_id', '=', 'pms_job_positions.id')
            ->where('pms_job_positions.active', true)->get(['pms_job_positions.id', 'pms_job_positions.name', 'pms_department_positions.department_id']) : collect();

        return $request->expectsJson() ? response()->json(compact('profile', 'projects', 'metrics', 'recentTickets', 'accountHistory'))
            : view('pms.team-member', compact('profile', 'projects', 'metrics', 'recentTickets', 'canAdmin', 'departments', 'positions', 'registrationSource', 'accountHistory', 'assignableProjects'));
    }

    public function provision(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'department' => ['nullable', 'string', Rule::exists('pms_departments', 'name')->where('active', true)],
            'job_title' => 'nullable|string|max:255',
            'job_position' => ['nullable', Rule::in(PmsPosition::options())],
            'workspace_role' => ['nullable', Rule::in(['Business Analyst', 'Project Manager', 'QA'])],
            'can_create_all_tickets' => 'nullable|boolean',
        ]);
        $user = User::where('email', $data['email'])->firstOrFail();
        $departmentId = isset($data['department']) ? DB::table('pms_departments')->where('name', $data['department'])->value('id') : null;
        $positionId = isset($data['job_position']) ? DB::table('pms_job_positions')->where('name', $data['job_position'])->value('id') : null;
        if ($departmentId && $positionId) OrganizationSettingsController::validatePair($departmentId, $positionId);
        abort_if($user->id === $request->user()->id && ! empty($data['workspace_role']),
            403, 'You cannot grant yourself a workspace role.');
        abort_if(ProjectManagementUser::where('email', strtolower($user->email))->where('user_id', '<>', $user->id)->exists(), 422, 'This email is already used by another PMS account.');
        $profile = DB::transaction(function () use ($request, $data, $user, $departmentId, $positionId) {
            $profile = PmsProfile::updateOrCreate(['user_id' => $user->id], [
                'department' => $data['department'] ?? null, 'job_title' => $data['job_title'] ?? null,
                'job_position' => $data['job_position'] ?? null,
                'department_id' => $departmentId, 'job_position_id' => $positionId,
                'workspace_role' => $data['workspace_role'] ?? null,
                'can_create_all_tickets' => $data['can_create_all_tickets'] ?? false,
                'status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'deactivated_at' => null,
            ]);
            ProjectManagementUser::firstOrCreate(['user_id' => $user->id], [
                'name' => $user->name, 'email' => strtolower($user->email), 'password' => $user->password,
                'status' => 'active', 'legacy_shared_auth' => true, 'email_verified_at' => $user->email_verified_at,
            ]);
            DB::table('pms_registrations')->insert([
                'user_id' => $user->id, 'source' => 'administrator', 'status' => 'approved',
                'status_code' => \App\Support\PmsStatusRegistry::code('registration', 'approved'), 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request, 'membership.provisioned', $profile->id, ['user_id' => $user->id]);
            return $profile;
        });

        return $request->expectsJson() ? response()->json($profile, 201) : back()->with('success', 'PMS membership activated.');
    }

    public function update(Request $request, PmsProfile $profile)
    {
        abort_unless($request->user()->isGlobalSuperAdmin(), 403);
        $profile->loadMissing('user.projectManagementIdentity');
        $currentEmail = $profile->user?->projectManagementIdentity?->email ?? $profile->user?->email;
        $emailRules = ['sometimes', 'required', 'email', 'max:255'];
        if ($request->filled('email') && strcasecmp((string) $request->input('email'), (string) $currentEmail) !== 0) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($profile->user_id);
            $emailRules[] = Rule::unique('project_management_users', 'email')->ignore($profile->user_id, 'user_id');
        }
        $departmentRules = ['nullable', 'string'];
        if ($request->exists('department') && $request->input('department') !== $profile->department) {
            $departmentRules[] = Rule::exists('pms_departments', 'name')->where('active', true);
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'rejected'])],
            'name' => 'sometimes|required|string|max:255',
            'email' => $emailRules,
            'department' => $departmentRules,
            'job_title' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:40',
            'review_note' => 'nullable|string|max:5000',
            'job_position' => ['nullable', Rule::in(array_unique(array_filter([...PmsPosition::options(), $profile->job_position])))],
            'workspace_role' => ['nullable', Rule::in(['Business Analyst', 'Project Manager', 'QA'])],
            'can_create_all_tickets' => 'nullable|boolean',
        ]);
        $departmentId = array_key_exists('department', $data)
            ? (filled($data['department']) ? DB::table('pms_departments')->where('name', $data['department'])->value('id') : null)
            : $profile->department_id;
        $positionId = array_key_exists('job_position', $data)
            ? (filled($data['job_position']) ? DB::table('pms_job_positions')->where('name', $data['job_position'])->value('id') : null)
            : $profile->job_position_id;
        if ($departmentId && $positionId && ((int) $departmentId !== (int) $profile->department_id
            || (int) $positionId !== (int) $profile->job_position_id)) {
            OrganizationSettingsController::validatePair($departmentId, $positionId);
        }
        DB::transaction(function () use ($request, $profile, $data, $departmentId, $positionId): void {
            $profile = PmsProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $profile->load('user.projectManagementIdentity');
            abort_if($profile->user_id === $request->user()->id
                && array_key_exists('workspace_role', $data) && $data['workspace_role'] !== $profile->workspace_role,
                403, 'You cannot change your own workspace role.');
            if ($profile->user_id === $request->user()->id && $data['status'] !== $profile->status) {
                throw ValidationException::withMessages(['status' => 'You cannot change your own account status.']);
            }
            if ($data['status'] !== 'active' && (int) $profile->user?->role_id === User::ROLE_SUPER_ADMIN) {
                $superAdminIds = User::where('role_id', User::ROLE_SUPER_ADMIN)->where('status', 1)
                    ->lockForUpdate()->pluck('id');
                $activeAdmins = PmsProfile::whereIn('user_id', $superAdminIds)->where('status', 'active')->count();
                abort_if($activeAdmins <= 1, 422, 'The last active Super Admin cannot be disabled.');
            }
            $previous = $profile->status;
            $previousRole = $profile->workspace_role;
            $previousName = $profile->user?->name;
            $previousEmail = $profile->user?->projectManagementIdentity?->email ?? $profile->user?->email;
            $previousFields = [
                'name' => $previousName, 'email' => $previousEmail, 'department' => $profile->department,
                'job_position' => $profile->job_position, 'job_title' => $profile->job_title,
                'phone' => $profile->user?->projectManagementIdentity?->phone,
            ];
            $changedFields = [];
            foreach ($previousFields as $field => $previousValue) {
                if (! array_key_exists($field, $data)) continue;
                $newValue = $field === 'email' ? strtolower($data[$field]) : $data[$field];
                if ($newValue !== $previousValue) $changedFields[] = $field;
            }
            if (array_key_exists('name', $data) || array_key_exists('email', $data)) {
                $userChanges = ['name' => $data['name'] ?? $profile->user->name];
                if (isset($data['email']) && strcasecmp($data['email'], (string) $previousEmail) !== 0) {
                    $userChanges['email'] = strtolower($data['email']);
                }
                $profile->user->update($userChanges);
            }
            $profile->update([
                'status' => $data['status'], 'department' => array_key_exists('department', $data) ? $data['department'] : $profile->department,
                'job_title' => array_key_exists('job_title', $data) ? $data['job_title'] : $profile->job_title,
                'job_position' => array_key_exists('job_position', $data) ? $data['job_position'] : $profile->job_position,
                'department_id' => $departmentId, 'job_position_id' => $positionId,
                'workspace_role' => array_key_exists('workspace_role', $data) ? $data['workspace_role'] : $profile->workspace_role,
                'can_create_all_tickets' => $data['can_create_all_tickets'] ?? $profile->can_create_all_tickets,
                'approved_by' => $data['status'] === 'active' ? $request->user()->id : $profile->approved_by,
                'approved_at' => $data['status'] === 'active' ? now() : $profile->approved_at,
                'deactivated_at' => in_array($data['status'], ['inactive', 'suspended'], true) ? now() : null,
            ]);
            ProjectManagementUser::where('user_id', $profile->user_id)->update([
                'name' => $data['name'] ?? $profile->user?->name,
                'email' => isset($data['email']) ? strtolower($data['email']) : $previousEmail,
                'status' => $data['status'], 'status_code' => \App\Support\PmsStatusRegistry::code('membership', $data['status']),
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $profile->user?->projectManagementIdentity?->phone,
                'remember_token' => $previous !== $data['status'] ? Str::random(60) : $profile->user?->projectManagementIdentity?->remember_token,
            ]);
            if (in_array($data['status'], ['inactive', 'suspended'], true)) {
                $profile->user?->tokens()->whereJsonContains('abilities', 'pms')->delete();
            }
            DB::table('pms_registrations')->where('user_id', $profile->user_id)->where('status', 'pending')->update([
                'status' => $data['status'] === 'active' ? 'approved' : $data['status'],
                'status_code' => \App\Support\PmsStatusRegistry::code('registration', $data['status'] === 'active' ? 'approved' : $data['status']),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null, 'updated_at' => now(),
            ]);
            if ($previous !== $data['status']) {
                $this->audit($request, 'membership.'.$data['status'], $profile->id, ['user_id' => $profile->user_id, 'from' => $previous]);
            }
            if ($previousRole !== (array_key_exists('workspace_role', $data) ? $data['workspace_role'] : $previousRole)) {
                $this->audit($request, 'membership.role_changed', $profile->id, ['user_id' => $profile->user_id, 'from' => $previousRole, 'to' => $data['workspace_role']]);
            }
            if ($changedFields) {
                $this->audit($request, 'membership.profile_updated', $profile->id,
                    ['user_id' => $profile->user_id, 'fields' => $changedFields]);
            }
        });

        return $request->expectsJson() ? response()->json($profile->fresh()) : back()->with('success', 'Membership updated.');
    }

    public function assignProject(Request $request, PmsProfile $profile)
    {
        abort_unless($request->user()->isGlobalSuperAdmin(), 403);
        abort_if($profile->user_id === $request->user()->id, 403, 'You cannot change your own project role.');
        abort_unless($profile->status === 'active', 422, 'Activate the account before assigning a project.');
        $data = $request->validate([
            'project_id' => ['required', Rule::exists('pm_projects', 'id')->whereNull('archived_at')],
            'role' => ['required', Rule::in(PmsAccess::ROLES)],
        ]);
        DB::transaction(function () use ($request, $profile, $data): void {
            DB::table('pm_project_members')->upsert([[
                'project_id' => $data['project_id'], 'user_id' => $profile->user_id,
                'role' => $data['role'], 'created_at' => now(), 'updated_at' => now(),
            ]], ['project_id', 'user_id'], ['role', 'updated_at']);
            $this->audit($request, 'membership.project_assigned', $profile->id, [
                'user_id' => $profile->user_id, 'project_id' => $data['project_id'], 'role' => $data['role'],
            ]);
        });

        return $request->expectsJson() ? response()->json(['assigned' => true]) : back()->with('success', 'Project membership saved.');
    }

    public function removeProject(Request $request, PmsProfile $profile, PmProject $project)
    {
        abort_unless($request->user()->isGlobalSuperAdmin(), 403);
        abort_if($profile->user_id === $request->user()->id || (int) $project->owner_id === $profile->user_id, 403);
        DB::transaction(function () use ($request, $profile, $project): void {
            DB::table('pm_project_members')->where('project_id', $project->id)->where('user_id', $profile->user_id)->delete();
            $this->audit($request, 'membership.project_removed', $profile->id, [
                'user_id' => $profile->user_id, 'project_id' => $project->id,
            ]);
        });

        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Project membership removed.');
    }

    private function audit(Request $request, string $action, int $profileId, array $details): void
    {
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'membership', 'subject_id' => $profileId,
            'details' => json_encode($details), 'created_at' => now(),
        ]);
    }
}
