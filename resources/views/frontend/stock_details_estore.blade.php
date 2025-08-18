@extends('frontend.main_master')
@section('main')

 @section('title')
Product-Details | HC_Gaming Studio Websites

@endsection

<style>


    .quantity {
        display: inline-block;
        /* margin-left:40%; */
        /* border:solid; */

    }

    .quantity .input-text.qty {
        width: 60px;
        height: 39px;
        padding: 0 5px;
        margin-bottom:10px;
        text-align: center;
        background-color: transparent;
        border: 1px solid black;
        /* margin-left:40%; */


    }

    .quantity .minus,
    .quantity .plus {
        padding: 7px 10px 8px;
        height: 41px;
        /* background-color: #ffffff; */
        border: 1px solid #efefef;
        cursor: pointer;
        /* margin-left:40%; */

    }

    .quantity .minus {
        border-right: 0;
        font-size:24px;
        /* margin-left:40%; */

    }

    .quantity .plus {
        border-left: 0;
        font-size:24px;
        /* margin-left:40%; */


    }

    .quantity .minus:hover,
    .quantity .plus:hover {
        background: #eeeeee;
    }
    /* Style the tab */
    .tab {
      overflow: hidden;
      border: 1px solid #ccc;
      background-color: #f1f1f1;
      margin-top:10px;
    }

    /* Style the buttons inside the tab */
    .tab button {
      background-color: inherit;
      float: left;
      border: none;
      outline: none;
      cursor: pointer;
      padding: 14px 16px;
      transition: 0.3s;
      font-size: 17px;
    }

    /* Change background color of buttons on hover */
    .tab button:hover {
      background-color: #ddd;
    }

    /* Create an active/current tablink class */
    .tab button.active {
      background-color: #ccc;
    }

    /* Style the tab content */
    .tabcontent {
      display: none;
      padding: 6px 12px;
      border: 1px solid black;
      border-top: none;
    }



    * {
      box-sizing: border-box;
    }

    /* Create two equal columns that floats next to each other */
    .column{
      float: left;
      width: 50%;
      padding: 10px;
      box-sizing: border-box;
      font-size:16px;
      color:black;
    }
    /* Clear floats after the columns */
    .row:after {
      content: "";
      display: table;
      clear: both;
    }

    .carts{
    text-align:center;
    margin-top:20px;
    margin-left:40%;
    font-size:24px;
    width:100%;

    }

    #carts{
    text-align:center;
    margin-top:20px;
    margin-left:40%;
    font-size:24px;
    width:100%;

    }


    /* .inline{
      display:inline;
    } */

    #row{
        content: "";
      display: table;
      clear: both;
    }
.column_margin{
    margin-left:10%;
}
    #column{
        float: left;
      width: 50%;
      padding: 10px;
      box-sizing: border-box;
      font-size:16px;
      color:black;
    }
    #columntwo{
        float: left;
      width: 50%;
      padding: 10px;
      box-sizing: border-box;
      font-size:16px;
      color:black;
    }

    /* .out-of-stock {
        background-color: red;
        color: white;
        padding: 5px;
        top: 300px;
        left: 40px;
    } */


        </style>

<main>

    <!-- breadcrumb-area -->
     <section class="breadcrumb__wrap">
        <div class="container custom-container">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-8 col-md-10">
                    <div class="breadcrumb__wrap__content">
                        <h2 class="title">Product Details Page</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Product Details</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

    </section>



    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
    <div class="row" id="row">
        <div class="column_margin">
        <div class="column" id="column">
            <img src="{{ asset($stock->product_image) }}" alt="Product Image" style="width: 100%">
            {{-- @if ($stock->product_stock <= 0) --}}

            {{-- @endif --}}
        </div>

          <div  id="columntwo">
            <p class="inline"><strong>Product Name:</strong> {{ $stock->product_name }}</p>
            <p class="inline"><strong>Product Category:</strong>

                @if ($stock['productcategory'] && !$stock['productcategory']->trashed())
                {{ $stock['productcategory']['product_category'] }}
            @else
                {{-- Category Not Available --}}
            @endif

        </p>
            <p class="inline"><strong>Stock Available:</strong> {{ $stock->product_stock }} Units</p>
            <p><strong>Price:</strong> MYR{{ $stock->product_price }}</p></b>
<div id="column">
    <form action="{{ route('guest.cart.add') }}" method="POST">
        @csrf
        <input type="hidden" name="product_id" value="{{ $stock->id }}">
       Quantity
       <div class="quantity">
        <input type="button" value="-" class="minus" data-product-id="{{ $stock->id }}">
        <input type="number" step="1" min="1" max="" name="quantity" value="1" title="Qty" class="input-text qty" id="quantity_{{ $stock->id }}">
        <input type="button" value="+" class="plus" data-product-id="{{ $stock->id }}">
    </div>
    @if ($stock->product_stock <= 0)
    <button type="submit" id="carts" style="width:100%" class="btn btn-warning btn-rounded waves-effect waves-light carts" disabled>Out Of Stock</button>
   @else
   <button type="submit" id="carts" style="width:100%" class="btn btn-warning btn-rounded waves-effect waves-light carts">Add To Carts</button>

   @endif
</form>
      </div>

</div>
        </div>
</div>

</form>
    <div class="row">
        <div class="column">
            <div class="tab">
                <button class="tablinks" onclick="openCity(event, 'Specification')">Specification</button>
                <button class="tablinks" onclick="openCity(event, 'Description')">Product Description</button>
            </div>
        </div>
    </div>

<div id="Specification" class="tabcontent">
<p class="inline"><strong>Stock Availability:</strong> {{ $stock->product_stock }} Units</p>
<p>Weight : {{ $stock->weight }} KG</p>
<p>SKU:{{ $stock->sku }}</p>

</div>


<div id="Description" class="tabcontent">
<p>{!! $stock->long_description !!}</p>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
function openCity(evt, cityName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(cityName).style.display = "block";
  evt.currentTarget.className += " active";
}


//Quantity Javascript

$(document).ready(function() {
        $('.plus').click(function() {
            var productId = $(this).data('product-id');
            var quantity = parseInt($('#quantity_' + productId).val());
            $('#quantity_' + productId).val(quantity + 1);
        });

        $('.minus').click(function() {
            var productId = $(this).data('product-id');
            var quantity = parseInt($('#quantity_' + productId).val());
            if (quantity > 1) {
                $('#quantity_' + productId).val(quantity - 1);
            }
        });
    });

    $(document).ready(function() {
  function updateCartTotal() {
    $.get('/cart-total', function(data) {
      $('#cart-total').text(data.total);
    });
  }

  // Call this function whenever an item is added, updated, or removed from the cart
  updateCartTotal();
});


</script>





