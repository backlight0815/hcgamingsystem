<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Network;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Str;
use App\Models\ReferralLinks;
use Carbon\Carbon;
use App\Models\Role;

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
         // Get all roles except IDs 1 and 2
    $roles = Role::whereNotIn('id', [1, 2])->get();


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
                'unique:' . User::class,
                'regex:/^[a-z0-9_]+$/'
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class,'regex:/gmail|ymail]|yahoo|hotmail|outlook/'],
            'password' => ['required', 'confirmed', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
'user_role' => [
    'required',
    Rule::notIn([1, 2]) // 不允许 1 和 2
],
            // 'password' => ['required', 'confirmed', Rules\Password::defaults()],
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

            // 'password.regex' => 'The password format is invalid. It should contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);
        $AgentReferralCode = Str::random(8);
        $CustomerReferralCode = Str::random(8);

        $invitedByUser = null;

    // Use role from dropdown as default
    $userRoleID = (int) $request->user_role;

    
        if (isset($request->referral_code)) {
            if ($request->referral_code === 'estkaiyg') {
                $userRoleID = 2; // Set to sub admin if referral code is 'estkaiyg'
                $invitedByUser = User::where('referral_code', 'estkaiyg')->first();
            } else {
                $invitedByUser = User::where('customer_referral_code', $request->referral_code)->first();

                if (!$invitedByUser) {
                    // Check if the referral code is an agent referral code
                    $agentUser = User::where('referral_code', $request->referral_code)->where('role_id', 350)->first();

                    if ($agentUser) {
                        $userRoleID = 350; // Set to agent role ID
                        $invitedByUser = $agentUser;
                    } else {
                        $notification = [
                            'message' => 'Please enter a valid referral code!',
                            'alert-type' => 'error'
                        ];
                        return back()->withInput()->with($notification);
                    }
                } else {
                    $userRoleID = 700; // Set to customer role ID
                }
            }
        }
       // Create user record
       $user = User::create([
        'name' => $request->name,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'referral_code' => $AgentReferralCode,
        'customer_referral_code' => $CustomerReferralCode,
        'invited_by' => $invitedByUser ? $invitedByUser->id : null,
        'role_id' => $userRoleID,
            'prop_firm_phase' => $userRoleID === 750 ? 1 : null, // ✅ auto assign Phase 1 for traders


    ]);

    $uplineUserId = null; // Default value


     // Create referral link records for both agent and customer
     ReferralLinks::create([
        'role_id' => 350, // Agent role ID
        'user_id' => $user->id,
        'referral_code' => $AgentReferralCode,
    ]);

    // ReferralLinks::create([
    //     'role_id' => 700, // Customer role ID
    //     'user_id' => $user->id,
    //     'referral_code' => $CustomerReferralCode,
    // ]);
        if ($invitedByUser) {
            Referral::create([
                'user_id' => $user->id,
                'upline_user_id' => $invitedByUser->id,
                'referral_code' => $request->referral_code,
            ]);}else{
                Referral::create([
                    'user_id'=>$user->id,
                    'upline_yser_id' => null,
                    'referral_code' =>$request->referral_code
                ]);
            };


        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }



    public function checkEmail(Request $request)
    {
        $email = $request->input('email');

        // Check if the email already exists in the database
        $userExists = User::where('email', $email)->exists();

        return response()->json(['exists' => $userExists]);
    }//End Methods

    public function checkUsername(Request $request)
    {
        $username = $request->input('username');

        // Check if the email already exists in the database
        $userExists = User::where('username', $username)->exists();

        return response()->json(['exists' => $userExists]);
    }
}
