@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Feature Management - Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Feature Page</h4><br><br>

                        <form method="POST" id="submitFeatureForm" action="{{ route('store.feature') }}">
                            @csrf

                            {{-- Feature Name --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Feature Name</label>
                                <div class="col-sm-9">
                                    <input name="feature_name" class="form-control" type="text" value="{{ old('feature_name') }}" required>
                                    @error('feature_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Enabled (default checked) --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Enabled</label>
                                <div class="col-sm-9 d-flex align-items-center">
                                    <input type="checkbox" name="enabled" value="1" id="enabled" checked>
                                    <label for="enabled" class="ms-2 mb-0">Yes</label>
                                </div>
                            </div>

                            <input type="submit" 
                                   class="btn btn-info waves-effect waves-light" 
                                   id="submitButton" 
                                   value="Add Feature" 
                                   onclick="return disableButton()">
                        </form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>

<script>
    var formSubmitted = false;
    function disableButton() {
        if (formSubmitted) {
            return false;
        }
        var submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.value = 'Submitting...';

        setTimeout(function () {
            document.getElementById('submitFeatureForm').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }
</script>

@endsection
