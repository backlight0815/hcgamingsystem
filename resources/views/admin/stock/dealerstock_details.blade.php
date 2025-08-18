@extends('admin.admin_master')
@section('admin')
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<style>

/* Additional styles for mobile responsiveness */
@media (max-width: 767px) {
    .column {
        width: 100%;
    }

    .out-of-stock {
        left: 0;
        right: 0;
        text-align: center;
        top: 10px;
    }

    #add-to-cart-btn,
    #buy-now-btn {
        margin-left: 0;
        width: 100%;
    }
}
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

#add-to-cart-btn{
text-align:center;
margin-top:20px;
margin-left:40%;
font-size:24px;
width:100%;

}
#buy-now-btn{
text-align:center;
margin-top:20px;
margin-left:40%;
font-size:24px;
width:100%;

}


.out-of-stock {
        background-color: red;
        color: white;
        padding: 15px;
        position: absolute;
        top: 0px;
        left: 10px;
    }


    @media (max-width: 768px) {
        #add-to-cart-btn {

        }

        .text-left.with-margin {
    margin-right: 100px;
}

img {
        /* Adjust the height for mobile view */
        height: 250px;
        weight:250px;/* or any specific height you prefer */
    }

    /* Additional styles for mobile view */
    .out-of-stock {
        font-size: 12px;
        width:60%;
        padding: 10px;
        position: absolute;
        top: 10px;
        left: 10px;

    }
    }



    </style>
         {{-- <div class="breadcrumb">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span> / </span>
                @endif
            @endforeach
        </div> --}}
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
        <div class="row">
            <div class="column" >
                <img src="{{ asset($product->product_image) }}" alt="Product Image" style="width: 100%">
                @if ($product->product_stock <= 0)

                <div class="out-of-stock">Out of Stock


                </div>
                @endif
            </div>

              <div class="column">
                <p class="inline"><strong>Product Name:</strong> {{ $product->product_name }}</p>
                <p class="inline"><strong>Product Category:</strong>


                    @if ($product['dealerproductcategory'] && !$product['dealerproductcategory']->trashed())
                    {{ $product['dealerproductcategory']['product_category'] }}
                @else
                    {{-- Category Not Available --}}
                @endif
                </p>
                <p class="inline"><strong>Stock Available:</strong> {{ $product->product_stock }} Units</p>
                @php
                $role_id = Auth::user()->role_id; // Assuming you have access to the user's role_id
                $priceToShow = $role_id == 700 && $product->customer_price ? $product->customer_price : $product->product_price;

            @endphp

            @if ($role_id == 350 || $role_id == 700)
            <p><strong>Price:</strong> RM {{ $priceToShow}}</p></b>
            @endif
                <div class="column">
                    <form action="{{ route('cart.add') }}" method="POST" id="buynow">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="submission_type" id="submission-type" value=""> <!-- Keep this hidden input -->

                        Quantity
                        <div class="quantity">
                            <input type="button" value="-" class="minus" data-product-id="{{ $product->id }}">
                            <input type="number" step="1" min="1" max="" name="quantity" value="1" title="Qty" class="input-text qty" id="quantity_{{ $product->id }}">
                            <input type="button" value="+" class="plus" data-product-id="{{ $product->id }}">
                        </div>
                        <div class="text-left with-margin">
                            @if ($product->product_stock <= 0)
                                <!-- Out of Stock Buttons -->
                                <button type="submit" id="carts" class="btn btn-warning btn-rounded waves-effect waves-light" style="width: 150px;" disabled>Out Of Stock</button>
                                <button type="submit" name="buy-now-details" class="btn btn-secondary btn-rounded waves-effect waves-light mt-3" style="width:180px" disabled>Out Of Stock</button>
                                <input type="hidden" name="redirect" value="summary">
                            @else
                                <!-- Add to Cart and Buy Now Buttons -->
                                <button type="submit" id="add-to-cart-btn" class="btn btn-warning btn-rounded waves-effect waves-light" style="width: 160px;">Add to Cart</button>
                                <button type="submit" name="buy-now-details" id="buy-now-btn" class="btn btn-secondary btn-rounded waves-effect waves-light mt-3" style="width:160px">Buy Now</button>
                                <input type="hidden" name="redirect" value="summary">
                            @endif
                        </div>
                    </form>
                </div>

  </div>
</div>

</div>

        <div class="row">
            <div class="col-12"> <!-- Use col-12 to make the column span the full width on all screen sizes -->
                <div class="tab">
                    <button class="tablinks" onclick="openCity(event, 'Specification')">Specification</button>
                    <button class="tablinks" onclick="openCity(event, 'Description')">Product Description</button>
                </div>
            </div>
        </div>

  <div id="Specification" class="tabcontent">
    <p class="inline"><strong>Stock Availability:</strong> {{ $product->product_stock }} Units</p>
    <p>Weight : {{ $product->weight }} KG</p>
    <p>SKU:{{ $product->sku }}</p>

  </div>


  <div id="Description" class="tabcontent">
    <p>{!! $product->long_description !!}</p>
  </div>

    </div>
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
            var inputField = $('#quantity_' + productId);
            var quantity = parseInt(inputField.val());
            var maxStock = parseInt(inputField.attr('max'));

            if (isNaN(quantity)) {
                quantity = 1;
            }

            if (!maxStock || quantity < maxStock) {
                inputField.val(quantity + 1);
            }
        });

        $('.minus').click(function() {
            var productId = $(this).data('product-id');
            var inputField = $('#quantity_' + productId);
            var quantity = parseInt(inputField.val());

            if (isNaN(quantity)) {
                quantity = 1;
            }

            if (quantity > 1) {
                inputField.val(quantity - 1);
            }
        });

        $('.input-text.qty').on('change', function() {
            var inputField = $(this);
            var currentValue = parseInt(inputField.val());
            var maxStock = parseInt(inputField.attr('max'));

            if (isNaN(currentValue)) {
                currentValue = 1;
            }

            if (currentValue > maxStock && maxStock) {
                alert('Quantity cannot exceed product stock!');
                inputField.val(maxStock);
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
$(document).ready(function() {
  var formSubmitted = false;

  // Disable Buy Now button on click
  $('#buy-now-btn').click(function(event) {
    if (formSubmitted) {
      event.preventDefault();
      return false;
    }

    var buyNowButton = $(this);
    buyNowButton.prop('disabled', true).text('Buy Now...');
    $('#submission-type').val('buy-now-details'); // Set the submission type

    setTimeout(function() {
      formSubmitted = true;
      $('#buynow').submit();
    }, 500);
  });

  // Disable Add to Cart button on click
  $('#add-to-cart-btn').click(function(event) {
    if (formSubmitted) {
      event.preventDefault();
      return false;
    }

    var addToCartButton = $(this);
    addToCartButton.prop('disabled', true).text('Adding to...');
    $('#submission-type').val('add-to-cart'); // Set the submission type

    setTimeout(function() {
      formSubmitted = true;
      $('#buynow').submit();
    }, 500);
  });
});
</script>





@endsection
