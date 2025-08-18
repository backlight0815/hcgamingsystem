@extends('admin.admin_master')
@section('admin')
<head>
  <!-- Add the Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
  <title>My Stock | HC Gaming Studio</title>
</head>
<style>
    .clearfix:after {
      content: "";
      display: table;
      clear: both;
    }
    .quantity {
        display: inline-block;
    }
    .quantity .input-text.qty {
        width: 60px;
        height: 39px;
        padding: 0 5px;
        text-align: center;
        background-color: transparent;
        border: 1px solid black;
    }
    .quantity .minus,
    .quantity .plus {
        padding: 7px 10px 8px;
        height: 41px;
        background-color: #ffffff;
        border: 1px solid #efefef;
        cursor: pointer;
    }
    .quantity .minus {
        border-right: 0;
        font-size: 24px;
    }
    .quantity .plus {
        border-left: 0;
        font-size: 24px;
    }
    .quantity .minus:hover,
    .quantity .plus:hover {
        background: #eeeeee;
    }
    .card {
        padding: 5%;
    }
    #add-to-cart-btn,
    #buy-now-btn {
        font-size: 18px;
        margin-top: 15px;
        margin-left: 50px;
        width: 50%;
    }
    #cart-total {
        font-size: 16px;
        margin-bottom: 20px;
    }
    .out-of-stock {
        background-color: red;
        color: white;
        padding: 5px;
        position: absolute;
        top: 0px;
        left: 0px;
    }
    .message {
        color: #888;
        font-size: 24px;
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Product Catalogue</h4>
                </div>
            </div>
        </div>
        <div class="breadcrumb">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span> / </span>
                @endif
            @endforeach
        </div>

        <div id="cart-total" style="text-align: right;">
            <a href="{{ route('cart.summary') }}" style="display: inline-block;">
                <i class="fa fa-shopping-cart"></i> View items in cart
            </a>
            ({{ session('cartTotal', 0) }})
        </div>

        <div class="container">
            <div class="row">
                @php
                $allOutOfStock = true;
                @endphp

                @if ($mergedData->isEmpty())
                    <div class="card" style="text-align:center">
                        <div class="col-md-12 message">
                            <h5 class="message">Waiting for admin to upload their product, Once uploaded, the product will be released.</h5>
                        </div>
                    </div>
                @else
                @foreach($mergedData as $item)
                @php
                $stock = isset($item->product_stock) ? $item->product_stock : $item->stock;
                @endphp

                @if ($stock > 0)
                <div class="col-md-4">
                    <div class="card">
                        <a class="card" href="{{ url('product_details', $item->id) }}">
                            @if ($item->product_image)
                                <img src="{{ $item->product_image }}" alt="Product_Image" height="250px" width="250px">
                            @else
                                <img src="path/to/default/image.jpg" alt="Default_Image" height="250px" width="250px">
                            @endif
                            @if ($stock <= 0)
                                <div class="out-of-stock">Out of Stock</div>
                            @endif
                        </a>
                        <div class="card-body">
                            <h5 style="font-size:14px">{{ $item->product_name }}</h5>
                            @php
                            $role_id = Auth::user()->role_id;
                            $priceToShow = $role_id == 700 && $item->customer_price ? $item->customer_price : $item->product_price;
                            @endphp
                            @if ($role_id == 350 || $role_id == 700)
                                <small>Price: RM {{ $priceToShow }}</small>
                            @endif

                            <small style="float:right;font-size:10px">
                                @if ($item['productcategory'] && !$item['productcategory']->trashed())
                                    {{ $item['productcategory']['product_category'] }}
                                @else
                                    {{-- Category Not Available --}}
                                @endif
                            </small>
                            <br>
                            @if ($item->user)
                                <small>Seller: {{ $item->user->username }}</small>
                            @else
                                <p>Dealer User: Unknown</p>
                            @endif
                        </div>
                        <form action="{{ route('cart.add') }}" method="POST" id="buynow">
                            @csrf
                            <input type="hidden" name="submission_type" class="submission-type" value="buy-now-details">
                            <input type="hidden" name="product_id" value="{{ isset($item->product_id) ? $item->product_id : '' }}">
                            <input type="hidden" name="dealer_stock_id" value="{{ isset($item->dealer_stock_id) ? $item->dealer_stock_id : '' }}">

                            Quantity
                            <div class="quantity">
                                <input type="button" value="-" class="minus" data-product-id="{{ $item->id }}">
                                <input type="number" step="1" min="1" max="{{ $stock }}" name="quantity" value="1" title="Qty" class="input-text qty" id="quantity_{{ $item->id }}" data-stock="{{ $stock }}">
                                <input type="button" value="+" class="plus" data-product-id="{{ $item->id }}">
                            </div>

                            @if ($stock <= 0)
                                <button type="submit" id="add-to-cart-btn" class="btn btn-warning btn-rounded waves-effect waves-light" disabled>Out of Stock</button>
                                <input type="hidden" name="redirect" value="summary">
                                <button type="button" id="buy-now-btn" class="btn btn-secondary btn-rounded waves-effect waves-light mt-2" style="margin-left: 65px; width: 130px" disabled>Out Of Stock</button>
                            @else
                                <button type="submit" name="submission_type" value="add-to-cart" id="add-to-cart-btn" class="btn btn-warning btn-rounded waves-effect waves-light add-to-cart-btn" style="margin-left: 70px;">Add to Cart</button>
                                <input type="hidden" name="redirect" value="summary">
                                <button type="submit" name="submission_type" value="buy-now-details" id="buy-now-btn" class="btn btn-secondary btn-rounded waves-effect waves-light mt-3 buy-now-btn" style="width: 180px;">Buy Now</button>
                            @endif
                        </form>



                    </div>
                </div>
                @endif
            @endforeach


                    @if ($allOutOfStock && count($mergedData) < 0)
                        <div class="card" style="text-align:center">
                            <div class="col-md-12 message">
                                <h5 class="message">All products are currently out of stock. Please check back later.</h5>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        $('.plus').click(function() {
            var productId = $(this).data('product-id');
            var quantity = parseInt($('#quantity_' + productId).val());
            var maxStock = parseInt($('#quantity_' + productId).data('stock'));
            if (quantity < maxStock) {
                $('#quantity_' + productId).val(quantity + 1);
            }
        });

        $('.minus').click(function() {
            var productId = $(this).data('product-id');
            var quantity = parseInt($('#quantity_' + productId).val());
            if (quantity > 1) {
                $('#quantity_' + productId).val(quantity - 1);
            }
        });

        // Handle form submission for Buy Now and Add to Cart buttons
        $('.buy-now-btn, .add-to-cart-btn').click(function(event) {
            event.preventDefault(); // Prevent default form submission

            var button = $(this);
            var form = button.closest('form');
            var productId = form.find('input[name="product_id"]').val();
            var dealerStockId = form.find('input[name="dealer_stock_id"]').val();
            var quantity = form.find('input[name="quantity"]').val();
            var submissionType = button.val(); // Get the value of the clicked button

            // Set the submission type in a hidden input field
            form.find('.submission-type').val(submissionType);

            button.prop('disabled', true).text('Processing...');

            setTimeout(function() {
                form.submit();
            }, 500);
        });
    });
</script>

@endsection
