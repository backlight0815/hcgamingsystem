<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Event; // Make sure this model is correctly set up and imported
use Image;
class EventController extends Controller
{
    public function AllEvent(){

        $events = Event::all(); // You might want to consider pagination or ordering

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Event Management', 'url' => route('all.events')],

        ];



        return view('admin.event.events_all', compact('breadcrumbData','events'));
    }//End Method

    public function StoreEvents(Request $request)
    {
        // Get the authenticated user's ID
        $userId = Auth::id();

        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:online,offline', // Ensure it's either 'online' or 'offline'
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255', // For offline events
            'platform' => 'nullable|string|max:255', // For online events
            'organizer_name' => 'nullable|string|max:255',
            'event_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',


        ]);

        // Handle the event image upload if provided
        $image = $request->file('event_image');
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        Image::make($image)->resize(1000, 1000)->save('upload/events_image/' . $name_gen);
        $save_url = 'upload/events_image/' . $name_gen;


        // Create a new event record in the database
        Event::create([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'location' => $request->type == 'offline' ? $request->location : null, // Only save location if offline
            'platform' => $request->type == 'online' ? $request->platform : null, // Only save platform if online
            'organizer_name' => $request->organizer_name,
            'event_image' => $save_url, // Save the image path
            'user_id' => $userId, // Store the authenticated user's ID
            'status' => 0, // Default status as 'upcoming' (0)
        ]);

        // Notification message for successful insertion
        $notification = [
            'message' => 'Event Inserted Successfully',
            'alert-type' => 'success'
        ];

        // Redirect to the all events page with the notification
        return redirect()->route('all.events')->with($notification);
    }


    public function AddEvents(){
        return view('admin.event.events_add');

    }//End Method
    public function EditEvents($id){
        $events = Event::findOrFail($id);
        // $categories = Event::orderBy('','ASC')->get();

        return view('admin.event.events_edit',compact('events',));


    }//End Method
    //UpdateEvents




    public function UpdateEvents(Request $request, $id)
    {
        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:online,offline', // Ensure it's either 'online' or 'offline'
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255', // For offline events
            'platform' => 'nullable|string|max:255', // For online events
            'organizer_name' => 'nullable|string|max:255',
            'event_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Image is optional for updating
        ]);

        // Retrieve the event by ID
        $event = Event::findOrFail($id);

        // Handle image upload if a new image is provided
        if ($request->hasFile('event_image')) {
            $image = $request->file('event_image');
            $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                    // Handle the event image upload if provided


                    Image::make($image)->resize(1000, 1000)->save('upload/events_image/' . $name_gen);
                    $save_url = 'upload/events_image/' . $name_gen;

            // Delete the old image if exists
            if (file_exists($event->event_image)) {
                unlink($event->event_image);
            }

            // Update the event image in the database
            $event->event_image = $save_url;
        }

        // Update other event details
        $event->update([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'location' => $request->type == 'offline' ? $request->location : null, // Only update location if offline
            'platform' => $request->type == 'online' ? $request->platform : null, // Only update platform if online
            'organizer_name' => $request->organizer_name,
        ]);

        // Notification for successful update
        $notification = [
            'message' => 'Event Updated Successfully',
            'alert-type' => 'success'
        ];

        // Redirect to the events list page with a success message
        return redirect()->route('all.events')->with($notification);
    }
    public function DeleteEvents($id){

        Event::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Events  Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method
    public function HomeEvents(){

        $events = Event::latest()->get();
        return view('frontend.event',compact('events'));



        }//End Methods

        public function EventsDetails($id){
            $events = Event::findOrFail($id);
            return view('frontend.service_details',compact('events'));


        }//End Method
}
