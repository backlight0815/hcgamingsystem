<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Services\PmsInvitationService;
use App\Support\PmsAccess;
use App\Support\PmsModule;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, PmsInvitationService $invitations)
    {
        $inviteToken = (string) $request->query('invite', '');
        if ($inviteToken && ! $invitations->valid($inviteToken)) return response()->view('pms.invitation-error', ['status' => $invitations->status($inviteToken)], 410);
        abort_unless(module_enabled('project_management'), 404);
        $user = Auth::guard('pms')->user() ?: $request->user();
        if (! $request->session()->get('pms_expired') && $user && (PmsAccess::workspace($user) || $user->pmsProfile?->status === 'pending')) {
            if ($inviteToken) return redirect()->route('pms.register', ['invite' => $inviteToken]);
            return redirect()->route(PmsAccess::workspace($user) ? 'pms.dashboard' : 'pms.home');
        }

        return view('pms.login', ['registrationAllowed' => PmsModule::registrationAllowed(), 'inviteToken' => $inviteToken]);
    }

    public function authenticate(Request $request, PmsInvitationService $invitations)
    {
        $inviteToken = (string) $request->input('invite', '');
        $invite = $inviteToken ? $invitations->valid($inviteToken) : null;
        abort_unless(module_enabled('project_management'), 404);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'nullable|boolean']);
        [$user, $identity] = $this->credentials($data['email'], $data['password'], (bool) $invite);
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        if (! $invite && ! PmsAccess::workspace($user) && $user->pmsProfile?->status !== 'pending') {
            throw ValidationException::withMessages(['email' => 'Unable to sign in to this module. Please verify your credentials and access permissions.']);
        }
        if ((string) $user->status !== '1' || in_array($identity?->status, ['inactive', 'suspended'], true)
            || in_array($user->pmsProfile?->status, ['inactive', 'suspended'], true)) {
            throw ValidationException::withMessages(['email' => 'Unable to sign in to this module. Please contact support if you need access.']);
        }
        Auth::guard($user->isGlobalSuperAdmin() ? 'pms' : 'web')->logout();
        Auth::guard($user->isGlobalSuperAdmin() ? 'web' : 'pms')->login($user, $request->boolean('remember'));
        Auth::shouldUse($user->isGlobalSuperAdmin() ? 'web' : 'pms');
        if ($user->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record($user, 'auth.login', 'user', $user->id, ['guard' => 'web', 'source' => 'pms']);
        }
        $identity?->update(['last_login_at' => now()]);
        $request->session()->regenerate();
        $request->session()->forget(['pms_expired', 'pms_expired_user_id', 'pms_lease_id']);

        if ($invite) {
            return redirect()->route('pms.register', ['invite' => $inviteToken]);
        }

        $destination = PmsAccess::workspace($user)
            ? ModuleAccess::safeIntended($request, ModuleAccess::PMS) ?? route('pms.dashboard')
            : route('pms.home');
        $request->session()->forget('url.intended');
        return redirect($destination);
    }

    public function register(Request $request, PmsInvitationService $invitations)
    {
        $inviteToken = (string) $request->query('invite', '');
        $invite = $inviteToken ? $invitations->valid($inviteToken) : null;
        if ($inviteToken && ! $invite) return response()->view('pms.invitation-error', ['status' => $invitations->status($inviteToken)], 410);
        abort_unless($invite || PmsModule::registrationAllowed(), 404);
        $existingAccount = $invite?->email && (ProjectManagementUser::where('email', $invite->email)->exists()
            || User::where('email', $invite->email)->exists());

        $departments = DB::table('pms_departments')->where('active', true)->orderBy('name')->get();
        $positions = DB::table('pms_job_positions')->join('pms_department_positions', 'pms_department_positions.position_id', '=', 'pms_job_positions.id')
            ->where('pms_job_positions.active', true)->orderBy('pms_job_positions.name')
            ->get(['pms_job_positions.id', 'pms_job_positions.name', 'pms_department_positions.department_id'])
            ->groupBy('id')->map(function ($links) {
                $position = $links->first();
                $position->department_ids = $links->pluck('department_id')->implode(',');
                return $position;
            })->values();
        $inviteDepartment = $invite?->department_id ? $departments->firstWhere('id', $invite->department_id) : null;
        $invitePosition = $invite?->job_position_id ? $positions->firstWhere('id', $invite->job_position_id) : null;

        return view('pms.register', compact('invite', 'inviteToken', 'existingAccount', 'departments', 'positions', 'inviteDepartment', 'invitePosition'));
    }

    public function invitation(Request $request, string $token, PmsInvitationService $invitations)
    {
        $request->query->set('invite', $token);
        return $this->register($request, $invitations);
    }

    public function store(Request $request, PmsInvitationService $invitations)
    {
        $inviteToken = (string) $request->input('invite', '');
        if ($inviteToken) {
            abort_unless($invitations->valid($inviteToken), 404);
            $data = $this->registrationData($request);
            if (User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already uses this email. Sign in to accept the invitation.']);
            }
            $user = $invitations->accept($inviteToken, fn () => $this->createInvitedIdentity($data), $data);
            $profileStatus = 'active';
        } else {
            abort_unless(PmsModule::registrationAllowed(), 404);
            [$user, $identity, $profileStatus] = $this->createRegistration($this->registrationData($request));
        }
        Auth::guard('pms')->login($user);
        Auth::shouldUse('pms');
        $request->session()->regenerate();

        return redirect()->route($inviteToken ? 'pms.dashboard' : 'pms.home')
            ->with('success', $profileStatus === 'active' ? 'Your PMS account is ready.' : 'Registration received. An administrator must approve your PMS membership.');
    }

    public function forgot()
    {
        abort_unless(module_enabled('project_management'), 404);

        return view('pms.forgot-password');
    }

    public function sendReset(Request $request)
    {
        abort_unless(module_enabled('project_management'), 404);
        $request->validate(['email' => 'required|email']);
        $identity = ProjectManagementUser::where('email', strtolower($request->input('email')))->first();
        $legacy = $identity ? null : User::where('email', strtolower($request->input('email')))->first();
        $status = $identity
            ? Password::broker('project_management_users')->sendResetLink(['email' => $identity->email])
            : ($legacy && ($legacy->pmsProfile || PmsAccess::administrator($legacy))
                ? Password::broker('users')->sendResetLink(['email' => $legacy->email]) : Password::INVALID_USER);

        if ($request->expectsJson()) {
            return $status === Password::RESET_LINK_SENT
                ? response()->json(['message' => __($status)])
                : response()->json(['message' => __($status), 'errors' => ['email' => [__($status)]]], 422);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request)
    {
        $actor = Auth::guard('web')->user() ?: Auth::guard('pms')->user();
        app(\App\Services\PmsSessionLease::class)->close($request, $actor, 'USER_LOGOUT');
        if ($actor?->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record($actor, 'auth.logout', 'user', $actor->id);
        }
        Auth::guard('pms')->logout();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(module_enabled('project_management') ? route('pms.login') : '/');
    }

    public function resetForm(Request $request, string $token)
    {
        abort_unless(module_enabled('project_management'), 404);

        return view('pms.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function resetPassword(Request $request)
    {
        abort_unless(module_enabled('project_management'), 404);
        $data = $request->validate([
            'token' => 'required|string', 'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()],
        ]);
        $status = Password::broker('project_management_users')->reset($data,
            function (ProjectManagementUser $identity, string $password): void {
                DB::transaction(function () use ($identity, $password): void {
                    $hash = Hash::make($password);
                    $identity->forceFill(['password' => $hash, 'remember_token' => Str::random(60)])->save();
                    if ($identity->legacy_shared_auth) {
                        $identity->user?->forceFill(['password' => $hash, 'remember_token' => Str::random(60)])->save();
                    }
                });
            });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('pms.login')->with('success', 'Your password has been reset.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function apiLogin(Request $request)
    {
        abort_unless(module_enabled('project_management'), 404);
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);
        [$user, $identity] = $this->credentials($data['email'], $data['password']);
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        if (! PmsAccess::workspace($user)) {
            return response()->json([
                'message' => 'An active PMS membership is required.',
                'membership_status' => $user->pmsProfile?->status ?? 'not_provisioned',
            ], 403);
        }
        $identity?->update(['last_login_at' => now()]);

        if ($user->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record($user, 'auth.login', 'user', $user->id, ['guard' => 'sanctum', 'source' => 'pms']);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'pms-workspace', ['pms'], now()->addDays(30))->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->identity($user),
        ]);
    }

    public function apiRegister(Request $request)
    {
        abort_unless(PmsModule::registrationAllowed(), 404);
        [$user, $identity, $profileStatus] = $this->createRegistration($this->registrationData($request));

        $payload = [
            'message' => $profileStatus === 'active'
                ? 'Your PMS account is ready.'
                : 'Registration received. An administrator must approve your PMS membership.',
            'membership_status' => $profileStatus,
            'user' => $this->identity($user),
        ];
        if ($profileStatus === 'active') {
            $payload['token'] = $user->createToken($request->string('device_name')->value() ?: 'pms-workspace', ['pms'], now()->addDays(30))->plainTextToken;
            $payload['token_type'] = 'Bearer';
        }

        return response()->json($payload, 201);
    }

    public function apiProfile(Request $request)
    {
        return response()->json(['user' => $this->identity($request->user())]);
    }

    public function apiLogout(Request $request)
    {
        if ($request->user()->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record($request->user(), 'auth.logout', 'user', $request->user()->id,
                ['guard' => 'sanctum', 'source' => 'pms']);
        }
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'PMS API token revoked.']);
    }

    private function credentials(string $email, string $password, bool $invited = false): array
    {
        $email = strtolower(trim($email));
        // A trusted global administrator authenticates with the primary credential even
        // when a separate PMS identity happens to use the same public email address.
        $global = User::where('email', $email)->first();
        if ($global?->isGlobalSuperAdmin() && Hash::check($password, $global->password)) {
            return [$global, null];
        }
        $identity = ProjectManagementUser::where('email', $email)->first();
        if ($identity) {
            $user = $identity->user;
            if ($user?->isGlobalSuperAdmin()) return [null, $identity];
            if ($user && $identity->legacy_shared_auth) {
                if (! Hash::check($password, $user->password)) return [null, $identity];
                if ($identity->password !== $user->password) $identity->update(['password' => $user->password]);

                return [$user, $identity];
            }
            return $user && Hash::check($password, $identity->password)
                ? [$user, $identity] : [null, $identity];
        }
        // Compatibility for known PMS profiles provisioned before the dedicated identity table.
        $legacy = User::where('email', $email)->first();
        if ($legacy && ($invited || $legacy->pmsProfile || PmsAccess::administrator($legacy))
            && Hash::check($password, $legacy->password)) {
            return [$legacy, null];
        }

        return [null, null];
    }

    private function registrationData(Request $request): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|string|email|max:255|unique:project_management_users,email',
            'password' => ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()],
            'department' => 'nullable|string|max:255', 'job_title' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|required_with:job_position_id|exists:pms_departments,id',
            'job_position_id' => 'nullable|integer|required_with:department_id|exists:pms_job_positions,id',
            'device_name' => 'nullable|string|max:100',
        ]);
        if (! empty($data['department_id'])) {
            OrganizationSettingsController::validatePair((int) $data['department_id'], (int) $data['job_position_id']);
        }
        return $data;
    }

    private function createRegistration(array $data): array
    {
        $profileStatus = PmsModule::settings()->registration_policy === 'open' ? 'active' : 'pending';
        [$user, $identity] = DB::transaction(function () use ($data, $profileStatus): array {
            $bridgeKey = Str::lower(Str::random(32));
            $user = User::create([
                'name' => $data['name'], 'username' => 'pms_'.$bridgeKey,
                'email' => 'pms-'.$bridgeKey.'@internal.invalid',
                'password' => Hash::make(Str::random(64)), 'role_id' => null, 'status' => 1,
            ]);
            $identity = ProjectManagementUser::create([
                'user_id' => $user->id, 'name' => $data['name'],
                'email' => strtolower($data['email']), 'password' => Hash::make($data['password']),
                'status' => $profileStatus,
            ]);
            $department = ! empty($data['department_id']) ? DB::table('pms_departments')->find($data['department_id']) : null;
            $position = ! empty($data['job_position_id']) ? DB::table('pms_job_positions')->find($data['job_position_id']) : null;
            PmsProfile::create([
                'user_id' => $user->id, 'department' => $department?->name ?? $data['department'] ?? null,
                'job_title' => $data['job_title'] ?? $position?->name,
                'job_position' => $position?->name, 'department_id' => $department?->id,
                'job_position_id' => $position?->id,
                'status' => $profileStatus, 'approved_at' => $profileStatus === 'active' ? now() : null,
            ]);
            DB::table('pms_registrations')->insert([
                'user_id' => $user->id, 'source' => 'public', 'status' => $profileStatus === 'active' ? 'approved' : 'pending',
                'status_code' => \App\Support\PmsStatusRegistry::code('registration', $profileStatus === 'active' ? 'approved' : 'pending'),
                'reviewed_at' => $profileStatus === 'active' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return [$user, $identity];
        });

        return [$user->load('pmsProfile'), $identity, $profileStatus];
    }

    private function createInvitedIdentity(array $data): User
    {
        $bridgeKey = Str::lower(Str::random(32));
        $user = User::create([
            'name' => $data['name'], 'username' => 'pms_'.$bridgeKey,
            'email' => 'pms-'.$bridgeKey.'@internal.invalid',
            'password' => Hash::make(Str::random(64)), 'role_id' => null, 'status' => 1,
        ]);
        ProjectManagementUser::create([
            'user_id' => $user->id, 'name' => $data['name'],
            'email' => strtolower($data['email']), 'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);
        return $user;
    }

    private function identity(User $user): array
    {
        $profile = $user->relationLoaded('pmsProfile') ? $user->pmsProfile : $user->pmsProfile()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => ProjectManagementUser::where('user_id', $user->id)->value('email') ?: $user->email,
            'membership' => $profile ? [
                'status' => $profile->status,
                'department' => $profile->department,
                'job_title' => $profile->job_title,
                'approved_at' => $profile->approved_at,
            ] : null,
            'workspace_role' => PmsAccess::administrator($user) ? 'System Administrator' : 'Member',
        ];
    }
}
