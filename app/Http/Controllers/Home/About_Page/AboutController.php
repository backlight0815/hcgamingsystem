<?php

namespace App\Http\Controllers\Home\About_Page;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\About;
use App\Models\Skill;
use App\Models\Acknowledgement;
use App\Models\Education;
use App\Models\MultiImage;
use Illuminate\Support\Carbon;

use Image;


class AboutController extends Controller
{

    public function AboutPage()
    {

        $breadcrumbData = [
        ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'About Me', 'url' => route('about.page')],

    ];
        $aboutpage = About::first();

        if (!$aboutpage) {
            // Create a new instance of the About model with default values
            $aboutpage = new About();
            $aboutpage->title = '';
            $aboutpage->short_title = '';
            $aboutpage->short_description = '';
            $aboutpage->long_description = '';
            $aboutpage->about_image = ''; // Set the default image path or use 'null'
        }

        return view('admin.about_page.about_page.about_us_all', compact('aboutpage','breadcrumbData'));
    }
public function SetupAboutPage() {
    $setupaboutpage = About::first();

    if (!$setupaboutpage) {
        // Create a new instance of the About model with default values
        $setupaboutpage = new About();
        $setupaboutpage->title = '';
        $setupaboutpage->short_title = '';
        $setupaboutpage->short_description = '';
        $setupaboutpage->long_description = '';
        $setupaboutpage->about_image = ''; // Set the default image path or use 'null'
    }
        return view('admin.about_page.about_page_all',compact('setupaboutpage'));
}

public function UpdateAbout(Request $request)
{
    $about_id = $request->id;
    $about = About::find($about_id);

    // If the record is found (update)
    if ($about) {
        if ($request->hasFile('about_image')) {
            $image = $request->file('about_image');
            $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
            $image->move('upload/home_about', $name_gen);
            $save_url = 'upload/home_about/' . $name_gen;
            $about->about_image = $save_url;
        }

        $about->title = $request->title;
        $about->short_title = $request->short_title;
        $about->short_description = $request->short_description;
        $about->long_description = $request->long_description;
        $about->save();

        $notification = [
            'message' => 'About Page Updated ' . ($request->hasFile('about_image') ? 'with' : 'without') . ' Image Successfully',
            'alert-type' => 'success'
        ];
    } else {
        // If the record is not found (insert)
        $about = new About();
        $about->title = $request->title;
        $about->short_title = $request->short_title;
        $about->short_description = $request->short_description;
        $about->long_description = $request->long_description;

        if ($request->hasFile('about_image')) {
            $image = $request->file('about_image');
            $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
            $image->move('upload/home_about', $name_gen);
            $save_url = 'upload/home_about/' . $name_gen;
            $about->about_image = $save_url;
        }

        $about->save();

        $notification = [
            'message' => 'About Page Inserted ' . ($request->hasFile('about_image') ? 'with' : 'without') . ' Image Successfully',
            'alert-type' => 'success'
        ];
    }

    return redirect()->route('about.page')->with($notification);
}
    public function HomeAbout(){
        $aboutpage = About::find(1);
        $educationpage = Education::latest()->get();
        $skillpage = Skill::latest()->get();
        $acknowledgementpage = Acknowledgement::latest()->get();

return view('frontend.about_page',compact('aboutpage','skillpage','acknowledgementpage','educationpage'));

    }//End Method


public function AboutMultiImage(){

    return view('admin.about_page.multimage');
}//End method

public function StoreMultiImage(Request $request){
    $image = $request->file('multi_image');

    foreach ($image as $multi_image ){

        $name_gen = hexdec(uniqid()).'.'.$multi_image->getClientOriginalExtension();//34343443.jpg

        Image::make($multi_image)->resize(220,220)->save('upload/multi/'.$name_gen);
        $save_url = 'upload/multi/'.$name_gen;


        MultiImage::insert([

            'multi_image'=> $save_url,
            'created_at' => Carbon::now()


        ]);
    }//End of the foreach
        $notification = array(
            'message' =>'Multi Image Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.multi.image')->with($notification);


}//End Method

public function AllMultiImage(){
    $breadcrumbData = [
        ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'About MultiImage', 'url' => route('all.multi.image')],

    ];
$allMultiImage = MultiImage::all();
return view('admin.about_page.all_multiimage',compact('allMultiImage','breadcrumbData'));
}//End Method

public function EditMultiImage($id){
    $multiImage = MultiImage::findOrFail($id);
    return view('admin.about_page.edit_multi_image',compact('multiImage'));

}//End Method

public function UpdateMultiImage(Request $request){


    $multi_iamge_id = $request->id;

    if($request->file('multi_image')){
        $image = $request->file('multi_image');
        $name_gen = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();//34343443.jpg

        Image::make($image)->resize(220,220)->save('upload/multi/'.$name_gen);
        $save_url = 'upload/multi/'.$name_gen;


        MultiImage::findOrFail($multi_iamge_id)->update([

            'multi_image'=> $save_url,


        ]);

        $notification = array(
            'message' =>'Multi Image Updated Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.multi.image')->with($notification);
    }





}//End Methods

public function DeleteMultiImage($id){
    $multi = MultiImage::findOrFail($id);
    $img = $multi->multi_image;
    unlink($img);

    MultiImage::findOrFail($id)->delete();


    $notification = array(
        'message' =>'Multi Image Deleted Successfully',
        'alert-type' => 'success'
    );
    return redirect()->back()->with($notification);
}



}


