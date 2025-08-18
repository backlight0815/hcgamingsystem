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

                        <h4 class="card-title">Add My Skill Page</h4><br><br>






<form method="POST" id="submitskill" action="{{ route('store.skill') }}" >
    @csrf

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Skill Type</label>
                            <div class="col-sm-10">
                                <input name="skill" class="form-control" type="text"  id="example-text-input">
                        @error('skill')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                            </div>

                        </div>

                            <div class="row mb-3">
                                <div class="col-sm-10">
                                <label for="example-text-input" class="col-sm-2 col-form-label">Skill Level</label>
                                <input type="text" id="range_02" class="form-control" name="level" >
                            </div>
                        </div>

<input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Insert  Skill  Data" onclick="disableButton()">
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
                document.getElementById('submitskill').submit();
            }, 500);

            formSubmitted = true;
            return true;
        }


        </script>
@endsection
