@extends('frontend.main_master')
@section('main')


@section('title')
Cart Summary | HC_Gaming Studio Websites

@endsection

<style>
    #cart{
        border:solid 1px;
    }

    table{
        border:solid 1px;

    }

    th{
        text-align:center;
        font-size:18px;

    }

    td{
    padding-top:2%;
    padding-right:5%;
    padding-bottom:2%;
    font-size:17px;

    }


        </style>
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

<P>Testing</P>


{{-- <table class="cart">
    <thead>
        <tr>
            <th style="width:23%;border:solid 1px">Product Picture</th>
            <th style="width: 25%;border:solid 1px">Product Name</th>
            <th style="width: 11%;border:solid 1px">Price</th>
            <th style="width: 11%;border:solid 1px">Quantity</th>
            <th style="width: 13%;border:solid 1px">Subtotal</th>
            <th style="width: 17%;border:solid 1px">Action</th>

        </tr>
    </thead>
    <tbody>
        @foreach($cartItem as $item)
        <tr>
            <td data-th="Product" class="text-center" style="border:solid 1px;width: 10%">
                <div class="row">
                    <div class="col-sm-2 hidden-xs">
                        <img src="{{ asset($item->product->product_image) }}" alt="Product Image" height="150px" class="img-responsive"/>
                    </div>

                </div>
            </td>
            <td data-th="Name" class="text-center" style="width: 10%;border:solid 1px;padding-left:30px">{{ $item->product->product_name }}</td>

            <td data-th="Price" class="text-center" style="width: 10%;border:solid 1px;padding-left:20px">RM{{ $item->product->product_price }}</td>
            <td data-th="Quantity"  class="text-center"style="width: 8%;border:solid 1px;padding-left:40px">{{ $item->quantity }}</td>
            <td data-th="Subtotal" class="text-center" style="width: 8%;border:solid 1px" >RM {{ $item->product->product_price * $item->quantity }}</td>
            <td class="actions" data-th="" style="width: 8%;border:solid 1px" >
                <!-- Add your actions here, such as removing the item from the cart -->
            </td>
        </tr>
        @php
        $total += ($item->product->product_price * $item->quantity);
        @endphp

        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-right"style="width: 10%;padding-left:20px">Total:</td>
            <td class="text-center"style="width: 10%;padding-left:20px">RM {{ $total  }}</td>
            <td></td>
        </tr>
    </tfoot>
</table> --}}
