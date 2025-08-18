@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Service Management - EDIT | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Service Edit Page</h4>






<form method="POST" action="{{ route('update.service') }}" enctype="multipart/form-data">
    @csrf
<input type="hidden" name="id" value="{{ $service->id }}">

    <div class="row mb-3">
    <label for="example-text-input" class="col-sm-2 col-form-label">Title</label>
    <div class="col-sm-10">
        <input name="service_title" class="form-control" type="text"  id="example-text-input" value="{{ $service->service_title }}">

        @error('service_title')
        <span class="text-danger">{{ $message }}</span>
        @enderror
    </div>

</div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Description</label>
                            <div class="col-sm-10">
                                <textarea id="elm1" name="short_description">
                                    {{ $service->short_description }}


                                </textarea>

                                @error('short_description')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->








                   <!-- end row -->

                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Image</label>
                    <div class="col-sm-10">
                        <input name="service_image" class="form-control" type="file"  id="image" accept="image/*">
                        @error('service_image')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                </div>
                <!-- end row -->


                        <!-- end row -->

                        <div class="row mb-3">

                            <label for="example-text-input" class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-10">
                                <img id="showImages" class="rounded avatar-lg" src="{{ asset($service->service_image)}}" alt="Card image cap">
                            </div>
                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" value="Update  Service Data">
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
