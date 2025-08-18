@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Role Management - Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Role Page</h4><br><br>

                        <form method="POST" id="submitroleform" action="{{ route('store.role') }}">
                            @csrf

                            {{-- Role ID --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Role ID</label>
                                <div class="col-sm-9">
                                    <input name="id" class="form-control" type="number" value="{{ old('id') }}">
                                    @error('id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Role Name --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Role Name</label>
                                <div class="col-sm-9">
                                    <input name="name" class="form-control" type="text" value="{{ old('name') }}">
                                    @error('name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Description</label>
                                <div class="col-sm-9">
                                    <textarea name="description" class="form-control">{{ old('description') }}</textarea>
                                    @error('description')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <input type="submit" 
                                   class="btn btn-info waves-effect waves-light" 
                                   id="submitButton" 
                                   value="Insert Role Data" 
                                   onclick="disableButton()">
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
            document.getElementById('submitroleform').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }
</script>

@endsection
