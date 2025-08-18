@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Event Management - Add Event | Your CRM Platform</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Event</h4><br><br>

                        <!-- Event Form -->
                        <form method="POST" id="submitEventForm" action="{{ route('store.events') }}" enctype="multipart/form-data">
                            @csrf

                            <!-- Event Title -->
                            <div class="row mb-3">
                                <label for="event-title-input" class="col-sm-3 col-form-label">Event Title</label>
                                <div class="col-sm-9">
                                    <input name="title" class="form-control" type="text" id="event-title-input" placeholder="Enter event title">
                                    @error('title')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Description -->
                            <div class="row mb-3">
                                <label for="event-description-input" class="col-sm-3 col-form-label">Event Description</label>
                                <div class="col-sm-9">
                                    <textarea name="description" class="form-control" rows="4" id="event-description-input" placeholder="Enter event description"></textarea>
                                    @error('description')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Type (Online/Offline) -->
                            <div class="row mb-3">
                                <label for="event-type-input" class="col-sm-3 col-form-label">Event Type</label>
                                <div class="col-sm-9">
                                    <select name="type" class="form-control" id="event-type-input">
                                        <option value="">Select Event Type</option>
                                        <option value="online">Online</option>
                                        <option value="offline">Offline</option>
                                    </select>
                                    @error('type')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Start Date and Time -->
                            <div class="row mb-3">
                                <label for="event-start-time-input" class="col-sm-3 col-form-label">Start Date & Time</label>
                                <div class="col-sm-9">
                                    <input name="start_time" class="form-control" type="datetime-local" id="event-start-time-input">
                                    @error('start_time')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event End Date and Time -->
                            <div class="row mb-3">
                                <label for="event-end-time-input" class="col-sm-3 col-form-label">End Date & Time</label>
                                <div class="col-sm-9">
                                    <input name="end_time" class="form-control" type="datetime-local" id="event-end-time-input">
                                    @error('end_time')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Location (For Offline Events) -->
                            <div class="row mb-3">
                                <label for="event-location-input" class="col-sm-3 col-form-label">Location (For Offline)</label>
                                <div class="col-sm-9">
                                    <input name="location" class="form-control" type="text" id="event-location-input" placeholder="Enter location for offline event">
                                    @error('location')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Platform (For Online Events) -->
                            <div class="row mb-3">
                                <label for="event-platform-input" class="col-sm-3 col-form-label">Platform (For Online)</label>
                                <div class="col-sm-9">
                                    <input name="platform" class="form-control" type="text" id="event-platform-input" placeholder="Enter platform for online event">
                                    @error('platform')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Organizer Name -->
                            <div class="row mb-3">
                                <label for="organizer-name-input" class="col-sm-3 col-form-label">Organizer Name</label>
                                <div class="col-sm-9">
                                    <input name="organizer_name" class="form-control" type="text" id="organizer-name-input" placeholder="Enter organizer's name">
                                    @error('organizer_name')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
 <!-- Event Image Upload -->
 <div class="row mb-3">
    <label for="event-image-input" class="col-sm-3 col-form-label">Event Poster</label>
    <div class="col-sm-9">
        <input name="event_image" class="form-control" type="file" id="event-image-input" accept="image/*">
        @error('event_image')
        <span class="text-danger">{{ $message }}</span>
        @enderror
    </div>
</div>

<!-- Image Preview -->
<div class="row mb-3">
    <label for="example-text-input" class="col-sm-3 col-form-label"></label>
    <div class="col-sm-9">
        <img id="showImages" class="rounded avatar-lg" src="{{url('upload/default.jpg')}}" alt="Card image cap">
    </div>
</div>

<!-- Submit Button -->
<div class="text-end">
    <input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Insert Event Data" onclick="disableButton()">
</div>
</form>

</div>
</div>
</div> <!-- end col -->
</div> <!-- end row -->
</div> <!-- end container -->
</div> <!-- end page-content -->

<!-- Disable button on form submission -->
<script>
var formSubmitted = false;
function disableButton() {
if (formSubmitted) {
return false;
}
var submitButton = document.getElementById('submitButton');
submitButton.disabled = true;
submitButton.value = 'Submitting...';

// Submit the form after a short delay
setTimeout(function () {
document.getElementById('submitEventForm').submit();
}, 500);

formSubmitted = true;
return true;
}

// Image preview on file selection
$(document).ready(function(){
$('#event-image-input').change(function(e){
var reader = new FileReader();
reader.onload = function(e){
$('#showImages').attr('src', e.target.result).show(); // Show the image preview
}
reader.readAsDataURL(e.target.files[0]);
});
});
</script>

@endsection
