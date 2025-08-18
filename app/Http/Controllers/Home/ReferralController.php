<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use App\Providers\RouteServiceProvider;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class ReferralController extends Controller
{
    public function showRegistrationForm($referral_code)
    {
        return view('auth.register',compact('referral_code'));
        // return view()


    }//End Methods

    public function register(Request $request,$referral_code)
    {
    //     $request->validate([
    //         'name' => ['required', 'string', 'max:255'],
    //         'username' => ['required', 'string', 'max:255', 'unique:'.User::class],
    //         'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],

    //         'password' => ['required', 'confirmed', Rules\Password::defaults()],
    //     ]);


    //     $user = User::create([
    //         'name' => $request->name,
    //         'username' => $request->username,

    //         'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'referral_code' => $request->referral_code,
    //     ]);

    //     event(new Registered($user));

    //     Auth::login($user);

    //     return redirect(RouteServiceProvider::HOME);
    // }
    }
}
