<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\Referral;
use App\Models\ReferralLinks;
use App\Models\Role;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Services\FinalExamService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends MobileController
{
    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->whereNotIn('id', [1, 2, 201, 202, 501, TradingPositionApplication::ROLE_LEADERSHIP, TradingPositionApplication::ROLE_RECRUITER])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return $this->success($roles, 'Registration roles loaded.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->failure('Invalid username or password.', ['login' => ['Invalid username or password.']], 422);
        }

        if (! ModuleAccess::allows($user, ModuleAccess::TRADINGHUB)) {
            return $this->failure('Unable to sign in to this module. Please verify your credentials and access permissions.', [], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'hc-trader-mobile', [ModuleAccess::TRADINGHUB])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'User login successful.');
    }

    public function register(Request $request): JsonResponse
    {
        if (! ModuleAccess::registrationAllowed(ModuleAccess::TRADINGHUB)) {
            return $this->failure('TradingHub registration is unavailable.', [], 403);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-z0-9_]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'regex:/gmail|ymail]|yahoo|hotmail|outlook/'],
            'password' => ['required', 'confirmed', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            'user_role' => ['required', 'integer', Rule::notIn([1, 2, 201, 202, 501, TradingPositionApplication::ROLE_LEADERSHIP, TradingPositionApplication::ROLE_RECRUITER]), 'exists:roles,id'],
            'referral_code' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'username.regex' => 'The username should not include capital letters or spaces.',
            'email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        [$roleId, $invitedBy] = $this->resolveRegistrationRole((int) $data['user_role'], $data['referral_code'] ?? null);
        if (ModuleAccess::legacyModuleForRole($roleId) !== ModuleAccess::TRADINGHUB) {
            return $this->failure('TradingHub registration is unavailable for this role.', [], 403);
        }
        if (($data['referral_code'] ?? null) && ! $invitedBy) {
            return $this->failure('Please enter a valid referral code.', ['referral_code' => ['Please enter a valid referral code.']], 422);
        }

        $user = DB::transaction(function () use ($data, $roleId, $invitedBy): User {
            $agentCode = ReferralLinks::generateUniqueCode();
            $customerCode = Str::random(8);
            $signalProviderCode = ReferralLinks::generateUniqueCode();

            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'referral_code' => $agentCode,
                'customer_referral_code' => $customerCode,
                'signal_provider_referral_code' => $signalProviderCode,
                'invited_by' => $invitedBy?->id,
                'role_id' => $roleId,
                'status' => 1,
                'prop_firm_phase' => $roleId === 750 ? 1 : null,
            ]);

            ReferralLinks::create(['role_id' => 350, 'user_id' => $user->id, 'referral_code' => $agentCode]);
            ReferralLinks::create(['role_id' => 201, 'user_id' => $user->id, 'referral_code' => $signalProviderCode]);
            Referral::create([
                'user_id' => $user->id,
                'upline_user_id' => $invitedBy?->id,
                'referral_code' => $data['referral_code'] ?? null,
            ]);

            return $user;
        });

        event(new Registered($user));
        $token = $user->createToken($data['device_name'] ?? 'hc-trader-mobile', [ModuleAccess::TRADINGHUB])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Account created successfully.', 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->failure(__($status), ['email' => [__($status)]], 422);
        }

        return $this->success(null, __($status));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->userPayload($request->user()), 'Authenticated user loaded.');
    }

    public function verifyPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            return $this->failure('The password is incorrect.', ['password' => ['The password is incorrect.']], 422);
        }

        return $this->success(null, 'Password verified.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Signed out successfully.');
    }

    private function resolveRegistrationRole(int $defaultRole, ?string $referralCode): array
    {
        if (! $referralCode) {
            return [$defaultRole, null];
        }

        if ($referralCode === 'estkaiyg') return [$defaultRole, null];

        if ($user = User::where('customer_referral_code', $referralCode)->first()) {
            return [700, $user];
        }

        if ($user = User::where('referral_code', $referralCode)->where('role_id', 350)->first()) {
            return [350, $user];
        }

        $tradingUpline = User::where('referral_code', $referralCode)->whereIn('role_id', TradingPositionApplication::recruiterRoles())->first();
        if (! $tradingUpline) {
            $link = ReferralLinks::where('referral_code', $referralCode)->whereIn('role_id', TradingPositionApplication::recruiterRoles())->first();
            $tradingUpline = $link ? User::whereKey($link->user_id)->whereIn('role_id', TradingPositionApplication::recruiterRoles())->first() : null;
        }
        if ($tradingUpline) {
            return [750, $tradingUpline];
        }

        if ($user = User::where('signal_provider_referral_code', $referralCode)->where('role_id', 202)->first()) {
            return [201, $user];
        }

        return [$defaultRole, null];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'role_id' => (int) $user->role_id,
            'is_admin' => in_array((int) $user->role_id, [1, 2], true),
            'status' => (int) $user->status,
            'final_exam_passed' => (bool) $user->final_exam_passed,
            'requires_final_exam' => app(FinalExamService::class)->requiresExam($user),
            'prop_firm_unlocked' => (bool) $user->prop_firm_unlocked,
            'requires_onboarding' => (int) $user->role_id === 750 && ! $user->hasApprovedTraderOnboarding(),
        ];
    }
}
