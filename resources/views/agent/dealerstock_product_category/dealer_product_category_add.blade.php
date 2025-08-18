@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Product Category Management - Add | HC Gaming Studio</title>


<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add Product Category Page</h4><br><br>



<form method="POST" id="submitproductcategory" action="{{ route('store.dealer.product.category') }}" >
    @csrf

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-3 col-form-label">Product Category Name</label>
                            <div class="col-sm-9">
                                <input name="name" class="form-control" type="text"  id="example-text-input">
                        @error('name')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                            </div>

                        </div>

<input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton"value="Insert  Product Category  Data" onclick="disableButton()">
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
        submitButton.value = 'Submitting...'; // Show a loading text on the button

        // Submit the form after a short delay (e.g., 0.5 seconds) to give the disabled visual effect
        setTimeout(function () {
            document.getElementById('submitproductcategory').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }
    </script>

@endsection
