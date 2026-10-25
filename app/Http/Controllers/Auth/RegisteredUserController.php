<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralLinks;
use App\Models\Role;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Providers\RouteServiceProvider;
use App\Services\FinalExamService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Set the application timezone before creating the user
$appTimezone = config('app.timezone');
app()->timezone = $appTimezone;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Position roles are application-only and cannot be selected during public registration.
        $allowed = [];
        if (ModuleAccess::registrationAllowed(ModuleAccess::TRADINGHUB)) $allowed[] = 750;
        if (ModuleAccess::registrationAllowed(ModuleAccess::DEALERSHIP)) $allowed = array_merge($allowed, [350, 700]);
        $roles = Role::whereIn('id', $allowed)->get();

        return view('auth.register', compact('roles'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'unique:'.User::class,
                'regex:/^[a-z0-9_]+$/',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:'.User::class,
                'regex:/gmail|ymail]|yahoo|hotmail|outlook/',
            ],
            'password' => [
                'required',
                'confirmed',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'user_role' => ['required', Rule::in([350, 700, 750])],
        ], [
            'username.unique' => 'The username is already taken.',
            'username.regex' => 'The username should not include capital letters or spaces.',
            'email.unique' => 'The email address is already taken.',
            'email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.regex' => 'The password format is invalid. It should contain at least one uppercase letter, one lowercase letter, and one number.',
            'user_role.in' => 'Invalid user role selected.',
        ]);

        // Generate referral codes
        $AgentReferralCode = ReferralLinks::generateUniqueCode();
        $CustomerReferralCode = Str::random(8);
        $SignalProviderReferralCode = ReferralLinks::generateUniqueCode(); // ⭐ New

        $invitedByUser = null;

        // Default role from dropdown
        $userRoleID = (int) $request->user_role;

        if (isset($request->referral_code)) {

            // Special code for sub admin
            if ($request->referral_code === 'estkaiyg') {
                return back()->withErrors(['referral_code' => 'This referral code is unavailable.'])->withInput();
            } else {

                // Customer referral code
                $invitedByUser = User::where('customer_referral_code', $request->referral_code)->first();

                if (! $invitedByUser) {

                    // Agent referral code
                    $agentUser = User::where('referral_code', $request->referral_code)
                        ->where('role_id', 350)
                        ->first();

                    if ($agentUser) {
                        $userRoleID = 350;
                        $invitedByUser = $agentUser;
                    } else {

                        // Trading position referral
                        $tradingPositionUpline = User::where('referral_code', $request->referral_code)
                            ->whereIn('role_id', TradingPositionApplication::recruiterRoles())
                            ->first();

                        if (! $tradingPositionUpline) {
                            $tradingPositionLink = ReferralLinks::where('referral_code', $request->referral_code)
                                ->whereIn('role_id', TradingPositionApplication::recruiterRoles())
                                ->first();

                            if ($tradingPositionLink) {
                                $tradingPositionUpline = User::where('id', $tradingPositionLink->user_id)
                                    ->whereIn('role_id', TradingPositionApplication::recruiterRoles())
                                    ->first();
                            }
                        }

                        if ($tradingPositionUpline) {
                            $userRoleID = 750;
                            $invitedByUser = $tradingPositionUpline;
                        } else {
                            $signalProvider = User::where('signal_provider_referral_code', $request->referral_code)
                                ->where('role_id', 202)
                                ->first();

                            if ($signalProvider) {
                                $userRoleID = 201;
                                $invitedByUser = $signalProvider;
                            } else {
                                $notification = [
                                    'message' => 'Please enter a valid referral code!',
                                    'alert-type' => 'error',
                                ];

                                return back()->withInput()->with($notification);
                            }
                        }
                    }
                } else {
                    $userRoleID = 700; // customer
                }
            }
        }

        $module = ModuleAccess::legacyModuleForRole($userRoleID);
        if (! $module || ! ModuleAccess::registrationAllowed($module)) {
            return back()->withErrors(['user_role' => 'Registration is unavailable for this module.'])->withInput();
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referral_code' => $AgentReferralCode,
            'customer_referral_code' => $CustomerReferralCode,
            'signal_provider_referral_code' => $SignalProviderReferralCode, // ⭐
            'invited_by' => $invitedByUser ? $invitedByUser->id : null,
            'role_id' => $userRoleID,
            'status' => 1,
            'prop_firm_phase' => $userRoleID === 750 ? 1 : null,
        ]);

        // Create referral link (agent)
        ReferralLinks::create([
            'role_id' => 350,
            'user_id' => $user->id,
            'referral_code' => $AgentReferralCode,
        ]);

        // Create referral link (signal provider)
        ReferralLinks::create([
            'role_id' => 201,
            'user_id' => $user->id,
            'referral_code' => $SignalProviderReferralCode,
        ]);

        // Save referral chain
        if ($invitedByUser) {
            Referral::create([
                'user_id' => $user->id,
                'upline_user_id' => $invitedByUser->id,
                'referral_code' => $request->referral_code,
            ]);
        } else {
            Referral::create([
                'user_id' => $user->id,
                'upline_user_id' => null,
                'referral_code' => $request->referral_code,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        if (app(FinalExamService::class)->requiresExam($user)) {
            return redirect()
                ->route('final_exam.intro')
                ->with([
                    'message' => 'Please complete the Final Examination before accessing the prop firm section.',
                    'alert-type' => 'info',
                ]);
        }

        if ($userRoleID === 750) {
            return redirect()
                ->route('trader.onboarding.show')
                ->with([
                    'message' => 'Your trader account has been created. Please submit the verification details before access is unlocked.',
                    'alert-type' => 'info',
                ]);
        }

        return redirect(RouteServiceProvider::HOME);
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $userExists = User::where('email', $email)->exists();

        return response()->json(['exists' => $userExists]);
    }

    public function checkUsername(Request $request)
    {
        $username = $request->input('username');
        $userExists = User::where('username', $username)->exists();

        return response()->json(['exists' => $userExists]);
    }
}
