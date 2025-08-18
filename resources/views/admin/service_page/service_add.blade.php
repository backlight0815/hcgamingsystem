@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Service Management-Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Service Page</h4>






<form method="POST" action="{{ route('store.service') }}" id="submitservice"enctype="multipart/form-data">
    @csrf

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Service Title</label>
                            <div class="col-sm-10">
                                <input name="service_title" class="form-control" type="text"  id="example-text-input">
                        @error('service_title')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Service Description</label>
                            <div class="col-sm-10">
                                <textarea id="elm1" name="short_description">



                                </textarea>
                                @error('short_description')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->








                   <!-- end row -->

                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Service Image</label>
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
                                <img id="showImages" class="rounded avatar-lg" src="{{url('upload/default.jpg')}}" alt="Card image cap">
                            </div>
                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Insert  Service Data" onclick="disableButton()">
</form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>

<script type="text/javascript">

var formSubmitted = false;
    function disableButton() {
        if (formSubmitted) {
            return false;
        }
        var submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.value = 'Submitting...'; // Show a loading text on the button

        // Submit the form after a short delay (e.g., 0.5 seconds) to give the disabled visual effect
        setTimeout(function () {
            document.getElementById('submitservice').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }

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
