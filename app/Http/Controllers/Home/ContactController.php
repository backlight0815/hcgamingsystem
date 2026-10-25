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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Contact::Insert([
       'name' => $data['name'],
       'email' => $data['email'],
       'subject' => $data['subject'],
       'phone' => $data['phone'] ?? null,
       'message' => $data['message'],
       'created_at' => Carbon::now(),



        ]);

        $notification = array(
            'message' =>'Your message was sent successfully.',
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
