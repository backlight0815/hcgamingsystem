@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>About Page - Skill  | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">About Page - Skill Update</h4><br><br>






<form method="POST" action="{{ route('update.skill',$skills->id) }}" >
    @csrf



    <div class="row mb-3">
        <label for="example-text-input" class="col-sm-2 col-form-label">Skill Type</label>
        <div class="col-sm-10">
            <input name="skill" class="form-control" type="text" value="{{ $skills->skill }}" id="example-text-input">
    @error('skill')
    <span class="text-danger">{{ $message }}</span>
    @enderror
        </div>

    </div>


    <div class="row mb-3">
        <div class="col-sm-10">
        <label for="example-text-input" class="col-sm-2 col-form-label">Skill Level</label>
        <input type="text" id="range_02" class="form-control" name="level" value="{{ $skills->level }}" >
    </div>
</div>


<input type="submit" class="btn btn-info waves-effect waves-light" value="Update  Skill  Data">
</form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>


@endsection
