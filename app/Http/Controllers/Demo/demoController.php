<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class demoController extends Controller
{

    public function HomeMain(){
        return view('frontend.index');
    }//End Method
    public function Index(){
        return view('about');
    }
public function ContactMethod(){
    return view('contact');
}

}
