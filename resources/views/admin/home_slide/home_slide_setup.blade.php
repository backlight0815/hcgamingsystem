@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Home Slide Management | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Home Slide Page</h4>





<form method="POST" action="{{ route('update.slider',$setuphomeslide->id) }}" enctype="multipart/form-data">
    @csrf

    <input type="hidden" name="id" value="{{ $setuphomeslide->id }}">
                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Title</label>
                            <div class="col-sm-10">
                                <input name="title" class="form-control" type="text" value="{{ $setuphomeslide->title }}" id="example-text-input">
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Short Title</label>
                            <div class="col-sm-10">
                                <input name="short_title" class="form-control" type="text" value="{{ $setuphomeslide->short_title }}" id="example-text-input">
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Youtube URL</label>
                            <div class="col-sm-10">
                                <input name="video_url" class="form-control" type="text" value="{{ $setuphomeslide->video_url }}" id="example-text-input">
                            </div>

                        </div>
                        <!-- end row -->


                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Slider Image</label>
                            <div class="col-sm-10">
                                <input name="home_slide" class="form-control" type="file"  id="image">
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">

                            <label for="example-text-input" class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-10">
                                <img id="showImages" class="rounded avatar-lg" src="{{ (!empty($setuphomeslide->home_slide))? url($setuphomeslide->home_slide):url('upload/no_image.jpg')}}" alt="Card image cap">
                            </div>
                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" value="Update Slide">
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
