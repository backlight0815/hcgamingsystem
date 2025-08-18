<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use Image;
use Illuminate\Support\Carbon;
use Validator;

class ServiceController extends Controller
{
    public function AllService(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Service Management', 'url' => route('all.service')],

        ];
        $service_index = Service::count();
        $service = Service::latest()->get();
        return view('admin.service_page.service_all',compact('service','service_index','breadcrumbData'));
    }// End Method

    public function AddService(){
        return view('admin.service_page.service_add');

    }//End Method

    public function StoreService(Request $request){

        $request -> validate([
            'service_title' => 'required',
            'short_description' => 'required',
            'service_image' => 'required',


        ],[
            'service_title.required' => 'Service Title is Required',
            'short_description.required' => 'Description is Required',


        ]);


        $image = $request->file('service_image');
        $name_gen = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();//34343443.jpg

        Image::make($image)->resize(600,519)->save('upload/service/'.$name_gen);
        $save_url = 'upload/service/'.$name_gen;


        Service::insert([
            'service_title'=> $request ->service_title,
            'short_description'=> $request ->short_description,
            'service_image'=> $save_url,
            'created_at' => Carbon::now(),


        ]);

        $notification = array(
            'message' =>'Service Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.service')->with($notification);


    }//End Method}

    public function EditService($id){
        $service = Service::findOrFail($id);
        return view('admin.service_page.service_edit',compact('service'));

    }//End Method
    public function UpdateService(Request $request){

        $request -> validate([
            'service_title' => 'required',
            'short_description' => 'required',
            'service_image' => 'required',


        ],[
            'service_title.required' => 'Service Title is Required',
            'short_description.required' => 'Description is Required',


        ]);

        $service_id = $request->id;

        if($request->file('service_image')){
            $image = $request->file('service_image');
            $name_gen = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();//34343443.jpg

            Image::make($image)->resize(600,519)->save('upload/service/'.$name_gen);
            $save_url = 'upload/service/'.$name_gen;


            Service::findOrFail($service_id)->update([
                'service_title'=> $request ->service_title,
                'short_description'=> $request ->short_description,
                'service_image'=> $save_url,


            ]);

            $notification = array(
                'message' =>'Service Updated with Image Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.service')->with($notification);
        }else{


            Service::findOrFail($service_id)->update([
                'service_title'=> $request ->service_title,
                'short_description'=> $request ->short_description,


            ]);

            $notification = array(
                'message' =>'Service without Image Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.service')->with($notification);





        }//end Else

    }//End Method

    public function DeleteService($id){
        $service = Service::findOrFail($id);
        $img = $service->service_image;
        unlink($img);

        Service::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Service Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method


    // public function HomeService(){

    //     $service = Service::latest()->get();
    //     return view('frontend.service',compact('service'));



    //     }//End Methods

    public function ServiceDetails($id){
        $service = Service::findOrFail($id);
        return view('frontend.service_details',compact('service'));


    }//End Method
}
