@extends('frontend.main_master')
@section('main')


@section('title')
Product | HC_Gaming Studio Websites

@endsection

<style>
    /* div.gallery {
      border: 1px solid #ccc;
    }

    div.gallery:hover {
      border: 1px solid #777;
    }

    div.gallery img {
      width: 100%;
      height: auto;
    }

    div.desc {
      padding: 15px;
      text-align: center;
    }

    * {
      box-sizing: border-box;
    }

    .responsive {
      padding: 0 6px;
      float: left;
      width: 24.99999%;
    }

    @media only screen and (max-width: 700px) {
      .responsive {
        width: 49.99999%;
        margin: 6px 0;
      }
    }

    @media only screen and (max-width: 500px) {
      .responsive {
        width: 100%;
      }
    } */

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
    font-size:24px;
}

.quantity .plus {
    border-left: 0;
    font-size:24px;

}

.quantity .minus:hover,
.quantity .plus:hover {
    background: #eeeeee;
}

.card{
    padding:5%;
}
#carts{
    font-size:14px;
    margin-top:15px;
    margin-left:20px;
    width:80%;
}


#cart-total{
    font-size:16px;
    margin-bottom:20px;

}

.out-of-stock {
        background-color: red;
        color: white;
        padding: 5px;
        position: absolute;
        top: 12px;
        left: 40px; 
    }
    </style>
    <head>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    </head>
<main>


        <!-- breadcrumb-area -->
        <section class="breadcrumb__wrap">
            <div class="container custom-container">
                <div class="row justify-content-center">
                    <div class="col-xl-6 col-lg-8 col-md-10">
                        <div class="breadcrumb__wrap__content">
                            <h2 class="title">Product Page</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Product</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

        </section>


        <div id="cart-total" style="text-align: right;">
            @auth
                <i class="fa fa-shopping-cart"></i> View items in cart
            </a>        ({{ session('cartTotal', 0) }})

            @endauth
            @guest
                <i class="fa fa-shopping-cart"></i> View items in cart ({{ session('GuestCartTotal', 0) }})
            @endguest
        </div>


    <div class="py-5">
        <div class="container">

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
                @foreach($product as $item)
    <div class="col-md-3">
        <div class="card">
            <a class="card" href="{{ url('stock_details',$item->id) }}">

            <img src="{{ $item->product_image }}" alt="Product_Image" height="250px">
            @if ($item->product_stock <= 0)

            <div class="out-of-stock">Out of Stock</div>
            @endif
            </a>
            <div class="card-body">
              <h5>{{ $item->product_name }}</h5>
              <small>RM {{  $item->product_price}}</small>
              <small style="float:right;font-size:10px">

                @if ($item['productcategory'] && !$item['productcategory']->trashed())
                {{ $item['productcategory']['product_category'] }}
            @else
                {{-- Category Not Available --}}
            @endif



            </small>

            </div>
            <form action="{{ route('guest.cart.add') }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" value="{{ $item->id }}">
               Quantity
               <div class="quantity">

                <input type="button" value="-" class="minus" data-product-id="{{ $item->id }}">
                <input type="number" step="1" min="1" max="" name="quantity" value="1" title="Qty" class="input-text qty" id="quantity_{{ $item->id }}">
                <input type="button" value="+" class="plus" data-product-id="{{ $item->id }}">
            </div>
            @if ($item->product_stock <= 0)

            <button type="submit" id="carts" class="btn btn-warning btn-rounded waves-effect waves-light"disabled>Out Of Stock</button>
            @else

            <button type="submit" id="carts" class="btn btn-warning btn-rounded waves-effect waves-light">Add to Carts</button>
@endif
        </form>
        </div>
    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>
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
    $.get('/guest.cart.total', function(data) {
      $('#guest-cart-total').text(data.total);
    });
  }

  // Call this function whenever an item is added, updated, or removed from the cart
  updateCartTotal();
});


</script>
    {{-- <a href="{{ url('product_details',$item->id) }}" style="display: inline-block;"> --}}
