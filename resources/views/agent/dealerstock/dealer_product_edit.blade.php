@extends('admin.admin_master')
@section('admin')


<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<style>
    #showImages {
        width: 300px;
        height: 300px;
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
<title>Product Management - Edit | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Product Edit Page</h4>









                        <form method="POST" action="{{ route('update.dealer.product') }}" enctype="multipart/form-data">
                            @csrf
                        <input type="hidden" name="id" value="{{ $product->id }}">
                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Name</label>
                                                    <div class="col-sm-10">
                                                        <input name="product_name" class="form-control" type="text"    id="example-text-input" value="{{ $product->product_name }}">
                                                @error('product_name')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                                    </div>

                                                </div>
                                                <!-- end row -->


                        <div class="category">
                                                <a href="{{ route('add.dealer.product.category') }}">Add New Category</a>
                        </div>
                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Category</label>
                                                <div class="col-sm-10">

                                            <select name="product_category_id" class="form-select" id="name" aria-label="Default select example">
                                                <option value="" {{ $product->name == null ? 'selected' : '' }}>--Open this select menu--</option>
                                             @foreach($categories as $cat)
                                                    @if(!$cat->trashed())
                                                        <option value="{{ $cat->id }}" {{ $cat->id == $product->name ? 'selected' : '' }}>{{ $cat->name }}


                                                        </option>
                                                    @endif
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
                                                        {{-- <input name="product_stock" class="form-control" type="text"  id="example-text-input" value="{{ $product->product_stock }}"> --}}
                                                        <input class="form-control" type="number" value="{{ $product->product_stock }}" id="stock" name="product_stock">

                                                        @error('product_stock')
                                                        <span class="text-danger">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>
                                                <!-- end row -->



                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Weight</label>
                                                    <div class="col-sm-10">
                                                        <input class="form-control" type="number" value="{{$product->weight  }}" step="0.01" id="stock" name="weight">
                                                        @error('weight')
                                                        <span class="text-danger">{{ $message }}</span>
                                                        @enderror

                                                    </div>

                                                </div>






                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Description</label>
                                                    <div class="col-sm-10">
                                                        <textarea id="elm1" name="long_description">

                        {!! $product->long_description !!}

                                                        </textarea>

                                                    </div>

                                                </div>


                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">SKU</label>
                                                    <div class="col-sm-10">
                                                        {{-- <input name="product_price" class="form-control" type="text"  id="example-text-input" value="{{ $product->product_price }}"> --}}
                                                        <input class="form-control" type="text" value="{{ $product->sku }}" s id="sku" name="sku">

                                                        @error('sku')
                                                        <span class="text-danger">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>
                                                <!-- end row -->






                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Product Price</label>
                                                    <div class="col-sm-10">
                                                        {{-- <input name="product_price" class="form-control" type="text"  id="example-text-input" value="{{ $product->product_price }}"> --}}
                                                        <input class="form-control" type="number" value="{{ $product->product_price }}" step="0.01" id="price" name="product_price">

                                                        @error('product_price')
                                                        <span class="text-danger">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>
                                                <!-- end row -->
                                                <div class="row mb-3">
                                                    <label for="example-text-input" class="col-sm-2 col-form-label">Customer Price</label>
                                                    <div class="col-sm-10">
                                                        {{-- <input name="product_price" class="form-control" type="text"  id="example-text-input" value="{{ $product->product_price }}"> --}}
                                                        <input class="form-control" type="number" value="{{ $product->customer_price }}" step="0.01" id="price" name="customer_price">

                                                        @error('customer_price')
                                                        <span class="text-danger">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>
                                                <!-- end row -->

                                           <!-- end row -->

                                           <div class="row mb-3">
                                            <label for="example-text-input" class="col-sm-2 col-form-label">Product Image</label>
                                            <div class="col-sm-10">
                                                <input name="product_image" class="form-control" type="file"  id="image" accept="image/*">
                                            </div>

                                        </div>
                                        <!-- end row -->


                                                <!-- end row -->

                                                <div class="row mb-3">

                                                    <label for="example-text-input" class="col-sm-2 col-form-label"></label>
                                                    <div class="col-sm-10">
                                                        <img id="showImages" class="rounded avatar-lg" height="1500px" weight="1500px" src="{{ asset($product->product_image)}}" alt="Card image cap">
                                                    </div>
                                                </div>
                                                <!-- end row -->
                        <input type="submit" class="btn btn-info waves-effect waves-light" value="Update  Product Data">
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
                $('#showImages').attr('src',e.target.result);
            }
            reader.readAsDataURL(e.target.files['0']);
        });
    });

    //    function redirectToPage() {
    //         window.location.href = "{{ route('add.product.category') }}";
    //     }
    </script>

@endsection
