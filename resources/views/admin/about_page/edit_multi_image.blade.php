@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>


<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Update Multi Image</h4>






<form method="POST" action="{{ route('update.multi.image') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="id" value="{{ $multiImage->id }}">

                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">About Multi Image</label>
                    <div class="col-sm-10">
                        <input name="multi_image" class="form-control" type="file"  id="image" >
                    </div>

                </div>
                <!-- end row -->


                        <!-- end row -->

                        <div class="row mb-3">

                            <label for="example-text-input" class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-10">
                                                                {{-- <img class="rounded-circle avatar-xl" src="{{ (File::exists(public_path('upload/admin_images/' . $editData->profile_image))) ? url('upload/admin_images/' . $editData->profile_image) : url('upload/default.jpg') }}" alt="Personal Profile"> --}}
                                                                <img id="showImages" class="rounded avatar-lg" src="{{ (File::exists(public_path('upload/multi/'.$multiImage->multi_image)))?url('upload/multi/'.$multiImage->multi_image):url('upload/default.jpg') }}" alt="Card image cap">

                                {{-- <img id="showImages" class="rounded avatar-lg" src="{{ asset($multiImage->multi_image) }}" alt="Card image cap"> --}}
                            </div>
                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" value="Add Multi Image">
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
