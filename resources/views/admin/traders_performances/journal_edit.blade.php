@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Event Management- Edit | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Edit Event Details</h4><br><br>




\
                        <form method="POST" action="{{ route('update.events', $events->id) }}" enctype="multipart/form-data">
                            @csrf

                            <!-- Event Title -->
                            <div class="row mb-3">
                                <label for="title" class="col-sm-2 col-form-label">Event Title</label>
                                <div class="col-sm-10">
                                    <input name="title" class="form-control" type="text" id="title" value="{{ old('title', $events->title) }}">
                                    @error('title')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Description -->
                            <div class="row mb-3">
                                <label for="description" class="col-sm-2 col-form-label">Event Description</label>
                                <div class="col-sm-10">
                                    <textarea name="description" class="form-control" id="description">{{ old('description', $events->description) }}</textarea>
                                    @error('description')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Type (Online/Offline) -->
                            <div class="row mb-3">
                                <label for="type" class="col-sm-2 col-form-label">Event Type</label>
                                <div class="col-sm-10">
                                    <select name="type" class="form-control" id="type">
                                        <option value="online" {{ old('type', $events->type) == 'online' ? 'selected' : '' }}>Online</option>
                                        <option value="offline" {{ old('type', $events->type) == 'offline' ? 'selected' : '' }}>Offline</option>
                                    </select>
                                    @error('type')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Start and End Time -->
                            <div class="row mb-3">
                                <label for="start_time" class="col-sm-2 col-form-label">Start Time</label>
                                <div class="col-sm-10">
                                    <input name="start_time" class="form-control" type="datetime-local" id="start_time" value="{{ old('start_time', \Carbon\Carbon::parse($events->start_time)->format('Y-m-d\TH:i')) }}">
                                    @error('start_time')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="end_time" class="col-sm-2 col-form-label">End Time</label>
                                <div class="col-sm-10">
                                    <input name="end_time" class="form-control" type="datetime-local" id="end_time" value="{{ old('end_time', \Carbon\Carbon::parse($events->end_time)->format('Y-m-d\TH:i')) }}">
                                    @error('end_time')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Location (Only for Offline Events) -->
                            <div class="row mb-3" id="location-input" style="display: {{ old('type', $events->type) == 'offline' ? 'block' : 'none' }}">
                                <label for="location" class="col-sm-2 col-form-label">Location</label>
                                <div class="col-sm-10">
                                    <input name="location" class="form-control" type="text" id="location" value="{{ old('location', $events->location) }}">
                                    @error('location')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Platform (Only for Online Events) -->
                            <div class="row mb-3" id="platform-input" style="display: {{ old('type', $events->type) == 'online' ? 'block' : 'none' }}">
                                <label for="platform" class="col-sm-2 col-form-label">Platform</label>
                                <div class="col-sm-10">
                                    <input name="platform" class="form-control" type="text" id="platform" value="{{ old('platform', $events->platform) }}">
                                    @error('platform')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Organizer Name -->
                            <div class="row mb-3">
                                <label for="organizer_name" class="col-sm-2 col-form-label">Organizer Name</label>
                                <div class="col-sm-10">
                                    <input name="organizer_name" class="form-control" type="text" id="organizer_name" value="{{ old('organizer_name', $events->organizer_name) }}">
                                    @error('organizer_name')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Event Image -->
                            <div class="row mb-3">
                                <label for="event_image" class="col-sm-2 col-form-label">Event Image</label>
                                <div class="col-sm-10">
                                    <input name="event_image" class="form-control" type="file" id="event_image">
                                    <img src="{{ asset($events->event_image) }}" alt="Event Image" class="mt-2" width="100">
                                    @error('event_image')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <input type="submit" class="btn btn-info waves-effect waves-light" value="Update Event">
                        </form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>

<script type="text/javascript">

$(document).ready(function(){
    $('#image').change(function(e){
        var reader = new FileReader();
        reader.onload = function(e){
            $('#showImages').attr('src',e.target.result);
        }
        reader.readAsDataURL(e.target.files['0']);
    });
});
</script>
@endsection
