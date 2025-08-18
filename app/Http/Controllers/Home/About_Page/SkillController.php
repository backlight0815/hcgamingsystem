<?php

namespace App\Http\Controllers\Home\About_Page;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Skill;
use App\Models\MultiImage;
use Illuminate\Support\Carbon;

use Image;

class SkillController extends Controller
{


    public function AllSkill(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My Skill', 'url' => route('all.skill')],

        ];
        $skills = Skill::latest()->get();
        return view('admin.about_page.skill.about_skill_all',compact('skills','breadcrumbData'));
    }//End Methods

    public function AddSkill(){
        return view('admin.about_page.skill.about_skill_add');

    }
    public function StoreSkill(Request $request){

        $request -> validate([
            'skill' => 'required',



        ],[
            'skill.required' =>'Skill Type is Required',

        ]);




        Skill::insert([

            'skill'=> $request ->skill,
            'level'=>$request->level,



        ]);

        $notification = array(
            'message' =>'My Skill Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.skill')->with($notification);
    }//End Methods


    public function EditSkill($id){
        $skills = Skill::findOrFail($id);

        return view('admin.about_page.skill.about_skill_edit',compact('skills'));


    }//End Method



    public function UpdateSkill(Request $request){
        $skill_id = $request->id;


        Skill::findOrFail($skill_id)->update([
                'skill'=> $request ->skill,
                'level'=> $request ->level,



            ]);

            $notification = array(
                'message' =>'Skill Updated with Image Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.skill')->with($notification);


    }//End Method

    public function DeleteSkill($id){




        Skill::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Skill Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method

}
