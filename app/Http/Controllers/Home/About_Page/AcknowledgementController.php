<?php

namespace App\Http\Controllers\Home\About_Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Acknowledgement;

class AcknowledgementController extends Controller
{
    public function AllAcknowledgement(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My Acknowledgement', 'url' => route('all.acknowledgement')],

        ];
        $acknowledgements = Acknowledgement::latest()->get();
        return view('admin.about_page.acknowledgement.about_acknowledgement_all',compact('acknowledgements','breadcrumbData'));
    }//End Methods

    public function AddAcknowledgement(){


        return view('admin.about_page.acknowledgement.about_acknowledgement_add');

    }//End Methods

    public function StoreAcknowledgement(Request $request){


    $validator = Validator::make($request->all(), [
        'title' => 'required',
        'long_description' => 'required',
    ], [
        'title.required' => 'Title is required.',
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

    Acknowledgement::create($validatedData);

    $notification = [
        'message' => 'My Acknowledgement Inserted Successfully',
        'alert-type' => 'success'
    ];

    return redirect()->route('all.acknowledgement')->with($notification);
    }//End Methods

    public function EditAcknowledgement($id){
        $Acknowledgements = Acknowledgement::findOrFail($id);

        return view('admin.about_page.acknowledgement.about_acknowledgement_edit',compact('Acknowledgements'));


    }//End Method

    public function UpdateAcknowledgement(Request $request){
        $acknowledgement_id = $request->id;

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'long_description' => 'required',
        ], [
            'title.required' => 'Title is required.',
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

        $acknowledgement = Acknowledgement::findOrFail($acknowledgement_id);
        $acknowledgement->update($validatedData);
            $notification = array(
                'message' =>'Acknowledgement Updated Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.acknowledgement')->with($notification);


    }//End Method

    public function DeleteAcknowledgement($id){

        Acknowledgement::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Acknowledgement Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method
}
