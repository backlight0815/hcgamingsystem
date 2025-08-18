<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Network;
use App\Models\User;

class UserController extends Controller
{
    public function loadRegister()
    {
        return view('auth.register');
    }

    // public function Check(Request $request){
    //     $referralCode = $request->input('referral_code');
    // }

    public function register($referralCode){


    }
}
