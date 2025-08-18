@extends('admin.admin_master')
@section('admin')


<title>My Profile | HC Gaming Studio</title>


<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Edit Profile Page</h4>






<form method="POST" action="{{ route('store.profile') }}" enctype="multipart/form-data">
    @csrf
                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Full Name</label>
                            <div class="col-sm-10">
                                <input name="name" class="form-control" type="text" value="{{ $editData->name }}" id="example-text-input">
                                @error('name')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->
                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Username</label>
                            <div class="col-sm-10">
                                <input name="username" class="form-control" type="text" value="{{ $editData->username }}" id="example-text-input">
                                @error('username')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label" for="input-email">Email</label>
                            <div class="col-sm-10">
                                <input name="email" class="form-control" type="text" id="input-email" data-inputmask="'alias': 'email'" value="{{ $editData->email }}" id="example-text-input">
                                <span class="text-muted">e.g "example@hotmail.com"</span>
                                <br>
                                @error('email')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>






                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Profile Picture</label>
                            <div class="col-sm-10">
                                <input name="profile_image" class="form-control" type="file"  id="image">
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">

                            <label for="example-text-input" class="col-sm-2 col-form-label"></label>
                            <div class="col-sm-10">
                                <img class="rounded-circle avatar-xl" src="{{ (File::exists(public_path('upload/admin_images/' . $editData->profile_image))) ? url('upload/admin_images/' . $editData->profile_image) : url('upload/default.jpg') }}" alt="Personal Profile">
                            </div>
                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" value="Update Profile">
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
            $('#showImage').attr('src',e.target.result);
        }
        reader.readAsDataURL(e.target.files['0']);
    });
});
</script>
@endsection
