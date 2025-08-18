@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<style type="text/css">
    .bootstrap-tagsinput .tag{
        margin-right: 2px;
        color: #b70000;
        font-weight: 700px;
    }
</style>

<title>Blog Management - Add | HC Gaming Studio</title>


<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Blog Page</h4>






<form method="POST" action="{{ route('store.blog') }}" id="submitblog" enctype="multipart/form-data">
    @csrf

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Blog Category</label>
                        <div class="col-sm-10">

                    <select name="blog_category_id" class="form-select" aria-label="Default select example">
                        <option value="">Open this select menu</option>
                       @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->blog_category }}</option>
@endforeach
                        </select>
                        @error('blog_category_id')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                        </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Blog Title</label>
                            <div class="col-sm-10">
                                <input name="blog_title" class="form-control" type="text"  id="example-text-input">

                                @error('blog_title')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->



                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Blog Tags</label>
                            <div class="col-sm-10">
                                <input name="blog_tags" class="form-control" type="text"  data-role="tagsinput" value="home,tech">
                                @error('blog_tags')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror

                            </div>

                        </div>
                        <!-- end row -->




                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Blog Description</label>
                            <div class="col-sm-10">
                                <textarea id="elm1" name="blog_description">



                                </textarea>
                                @error('blog_description')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            </div>

                        </div>



                   <!-- end row -->

                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Blog Image</label>
                    <div class="col-sm-10">
                        <input name="blog_image" class="form-control" type="file"  id="image" accept="image/*">
                        @error('blog_image')
                        <div class="text-danger">{{ $message }}</div>
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
<input type="submit" class="btn btn-info waves-effect waves-light" value="Insert  Blog Data" id="submitbutton" onclick="disableButton()">
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
        var submitButton = document.getElementById('submitbutton');
        submitButton.disabled = true;
        submitButton.value = 'Submitting...'; // Show a loading text on the button

        // Submit the form after a short delay (e.g., 0.5 seconds) to give the disabled visual effect
        setTimeout(function () {
            document.getElementById('submitblog').submit();
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
