<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HomeSlide;
use Image;

class HomeSliderController extends Controller
{
    public function HomeSlider(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Home Slide Management', 'url' => route('home.slide')],

        ];
        $homeslide = HomeSlide::first();

        if (!$homeslide) {
            // Create a new instance of the About model with default values
            $homeslide = new HomeSlide();
            $homeslide->title = '';
            $homeslide->short_title = '';
            $homeslide->video_url = '';
            $homeslide->save_url = '';
        }

        return view('admin.home_slide.home_slide_all',compact('homeslide','breadcrumbData'));

    }//End Method


    public function SetupHomeSlider() {
        $setuphomeslide = HomeSlide::first();

        if (!$setuphomeslide) {
            // Create a new instance of the About model with default values
            $setuphomeslide = new HomeSlide();
            $setuphomeslide->title = '';
            $setuphomeslide->short_title = '';
            $setuphomeslide->video_url = '';
            $setuphomeslide->save_url = '';
        }
            return view('admin.home_slide.home_slide_setup',compact('setuphomeslide'));
    }


    public function UpdateSlider(Request $request)
    {
        $slide_id = $request->id;
        $homeSlide = HomeSlide::find($slide_id);

        // If the record is found (update)
        if ($homeSlide) {
            if ($request->hasFile('home_slide')) {
                $image = $request->file('home_slide');
                $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                Image::make($image)->resize(636, 852)->save('upload/home_slide/' . $name_gen);
                $save_url = 'upload/home_slide/' . $name_gen;
                $homeSlide->home_slide = $save_url;
            }

            $homeSlide->title = $request->title;
            $homeSlide->short_title = $request->short_title;
            $homeSlide->video_url = $request->video_url;
            $homeSlide->save();

            $notification = [
                'message' => 'Home Slide Updated ' . ($request->hasFile('home_slide') ? 'with' : 'without') . ' Image Successfully',
                'alert-type' => 'success'
            ];
        } else {
            // If the record is not found (insert)
            $homeSlide = new HomeSlide();
            $homeSlide->title = $request->title;
            $homeSlide->short_title = $request->short_title;
            $homeSlide->video_url = $request->video_url;

            if ($request->hasFile('home_slide')) {
                $image = $request->file('home_slide');
                $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                Image::make($image)->resize(636, 852)->save('upload/home_slide/' . $name_gen);
                $save_url = 'upload/home_slide/' . $name_gen;
                $homeSlide->home_slide = $save_url;
            }

            $homeSlide->save();

            $notification = [
                'message' => 'Home Slide Inserted ' . ($request->hasFile('home_slide') ? 'with' : 'without') . ' Image Successfully',
                'alert-type' => 'success'
            ];
        }

        return redirect()->route('home.slide')->with($notification);
    }
}
