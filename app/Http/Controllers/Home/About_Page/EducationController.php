<?php

namespace App\Http\Controllers\Home\About_Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Education;
use Illuminate\Support\Facades\Validator;

class EducationController extends Controller
{
    public function AllEducation(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My Education Background', 'url' => route('all.education')],

        ];
        $educations = Education::latest()->get();
        return view('admin.about_page.education.about_education_all',compact('educations','breadcrumbData'));
    }//End Methods

    public function AddEducation(){


        return view('admin.about_page.education.about_education_add');

    }//End Methods

    public function StoreEducation(Request $request){


    $validator = Validator::make($request->all(), [
        'title' => 'required',
        'period' =>'required',
        'long_description' => 'required',
    ], [
        'title.required' => 'Title is required.',
        'period.required' => 'Period is required',
        'long_description.required' => 'Description is required.',
    ]);

    if ($validator->fails()) {
        return redirect()
            ->back()
            ->withErrors($validator)
            ->withInput();
    }

    $validatedData = $validator->validated();
    $validatedData['long_description'] = strip_tags($validatedData['long_description']);

    Education::create($validatedData);

    $notification = [
        'message' => 'My Educational Background Inserted Successfully',
        'alert-type' => 'success'
    ];

    return redirect()->route('all.education')->with($notification);
    }//End Methods

    public function EditEducation($id){
        $Educations = Education::findOrFail($id);

        return view('admin.about_page.education.about_education_edit',compact('Educations'));


    }//End Method

    public function UpdateEducation(Request $request){
        $education_id = $request->id;

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'period' =>'required',
            'long_description' => 'required',
        ], [
            'title.required' => 'Title is required.',
            'period.required' => 'Period is required',
            'long_description.required' => 'Description is required.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validatedData = $validator->validated();
        $validatedData['long_description'] = strip_tags($validatedData['long_description']);

        $education = Education::findOrFail($education_id);
        $education->update($validatedData);
            $notification = array(
                'message' =>'Education Background Updated Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.education')->with($notification);


    }//End Method

    public function DeleteEducation($id){

        Education::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Educational Background Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method
}
