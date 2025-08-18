<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Carbon;

class ContactController extends Controller
{
    public function Contact(){

        return view('frontend.contact');

    }//End Methods
    public function StoreMessage(Request $request){

        Contact::Insert([
       'name' => $request->name,
       'email' => $request->email,
       'subject' => $request->subject,
       'phone' => $request->phone,
       'message' => $request->message,
       'created_at' => Carbon::now(),



        ]);

        $notification = array(
            'message' =>'Your Meesage Submited Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);

    }//End Method

    public function ContactMessage(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Contact Management', 'url' => route('contact.message')],

        ];
        $contacts = Contact::latest()->get();
        return view('admin.contact.allcontact',compact('contacts','breadcrumbData'));
    }//End Method

    public function DeleteMessage($id){

        Contact::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Your Meessage Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method

}
