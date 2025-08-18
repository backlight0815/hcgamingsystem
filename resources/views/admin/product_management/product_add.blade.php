@extends('admin.admin_master')
@section('admin')
<style>

#elm1 {
        white-space: pre-wrap;
    }

    .category {
    margin-left: 17%; /* Original margin for desktop view */
}

/* Media query for screens with a max-width of 769px */
@media (max-width: 769px) {
    .category {
        margin-left: 10px; /* Adjusted margin for mobile view */
        margin-right: auto; /* Clear the right margin for mobile view */
    }
}

        </style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Product Management - Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Product Page</h4>






<form method="POST" action="{{ route('store.product') }}" id="submitproduct" enctype="multipart/form-data">
    @csrf

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Product Name</label>
                            <div class="col-sm-10">
                                <input name="product_name" class="form-control" type="text"  id="example-text-input">
                        @error('product_name')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                            </div>

                        </div>
                        <!-- end row -->

<div class="category">
                        <a href="{{ route('add.product.category') }}">Add New Category</a>
</div>
                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Category</label>
                <div class="col-sm-10">

            <select name="product_category_id" class="form-select" id="product_category" aria-label="Default select example">
                <option value="">--Open this select menu--</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->product_category }}</option>
@endforeach
                </select>

                @error('product_category_id')
                <div class="text-danger">{{ $message }}</div>
            @enderror
                </div>

                </div>

                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Stock</label>
                    <div class="col-sm-10">
                        <input class="form-control" type="number" value="" id="stock" name="product_stock">
                        @error('product_stock')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror

                    </div>

                </div>

                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Weight (KG)</label>
                    <div class="col-sm-10">
                        <input class="form-control" type="number" value="" step="0.01" id="weight" name="weight">
                        @error('weight')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror

                    </div>

                </div>
                <!--end row-->
                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Description</label>
                    <div class="col-sm-10">
                        <textarea id="elm1" name="long_description">



                        </textarea>
                        @error('long_description')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                </div>
                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">SKU

                    </label>
                    <div class="col-sm-10">
                        <input name="sku" class="form-control" type="text"  id="example-text-input">

                        {{-- <input class="form-control" type="number" value="" id="sku" name="sku"> --}}
                        @error('sku')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror

                    </div>

                </div>


                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Price</label>
                    <div class="col-sm-10">
                        <input class="form-control" type="number" value="" id="price" name="product_price"  step="0.01" pattern="\d+(\.\d{1,2})?">
                        @error('product_price')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror

                    </div>

                </div>

                <!--end row-->

                <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Customer Price</label>
                    <div class="col-sm-10">
                        <input class="form-control" type="number" value="" id="price" name="customer_price"  step="0.01" pattern="\d+(\.\d{1,2})?">
                        @error('customer_price')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror

                    </div>

                </div>

                <!--end row-->


                   <div class="row mb-3">
                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Image</label>
                    <div class="col-sm-10">
                        <input name="product_image" class="form-control" type="file" id="image" accept="image/*">
                        @error('product_image')
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
                        <input type="submit" class="btn btn-info waves-effect waves-light" value="Insert Product Data" id="submitButton" onclick="disableButton()">
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
            document.getElementById('submitproduct').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }

    $(document).ready(function(){
        $('#image').change(function(e){
            var reader = new FileReader();
            reader.onload = function(e){
                $('#showImages').attr('src', e.target.result);
            }
            reader.readAsDataURL(e.target.files['0']);
        });
    });


    function redirectToPage() {
        window.location.href = "{{ route('add.product.category') }}";
    }

                            </script>
                            @endsection

