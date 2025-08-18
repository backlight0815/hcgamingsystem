@extends('admin.admin_master')
@section('admin')
<head>
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

</head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>About Page - Acknowledgement  | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Edit Acknowledgement Page</h4><br><br>






<form method="POST" action="{{ route('update.acknowledgement',$Acknowledgements->id) }}" >
    @csrf



    <div class="row mb-3">
        <label for="example-text-input" class="col-sm-2 col-form-label">Title</label>
        <div class="col-sm-10">
            <input name="title" class="form-control" type="text" value="{{ $Acknowledgements->title }}" id="example-text-input">
    @error('title')
    <span class="text-danger">{{ $message }}</span>
    @enderror
        </div>

    </div>




    <div class="row mb-3">
        <label for="example-text-input" class="col-sm-2 col-form-label">Description</label>
        <div class="col-sm-10">
            <textarea id="elm1" name="long_description">
{{ $Acknowledgements->long_description }}


            </textarea>
            @error('long_description')
            <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

    </div>


<input type="submit" class="btn btn-info waves-effect waves-light" value="Update  Acknowledgement  Data">
</form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>


@endsection
