@extends('admin.admin_master')
@section('admin')

<style>

    /* .col-12{
        padding-top:20px;
    } */
    .quantity {
        display: inline-block;
    }

    .input-text.qty {
        width: 10px;
    }

    .quantity .input-text.qty {
        width: 20px;
        height: 39px;
        padding: 0 5px;
        text-align: center;
        background-color: transparent;
        border: 1px solid black;
    }


    #cart {
        border: solid 1px;
    }

    table {
        border: solid 1px;
    }

    th {
        text-align: center;
        font-size: 18px;
    }

    td {
        padding-top: 2%;
        padding-right: 5%;
        padding-bottom: 2%;
        font-size: 17px;
    }

    #checkout{
text-align:center;
margin-top:20px;
font-size:24px;
width:30%;


   /* Style for the "Empty Cart" button */

}
.empty-cart-btn {
        background-color: black;
        color: white;
        float: right;
        margin-right:15px;
    }

 /* Base styles for the table */
 .cart {
        margin-left: 20px;
        width: 100%; /* Ensure the table takes full width */
        border-collapse: collapse;
    }

    /* Responsive styles for smaller screens */
    @media (max-width: 600px) {
        .cart th,
        .cart td {
            padding: 8px; /* Add some padding for better readability */
        }

        /* Increase the width of Product Name column for mobile view */
        .cart th:nth-child(2),
        .cart td:nth-child(2) {
            width: 30%; /* Adjust the width as needed */
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
@media (max-width: 769px) {
        #checkout {
            width: 100%;
        }

.empty-cart-btn {
        background-color: black;
        color: white;
        float: right;
        margin-right:15px;
    }


    /* Styles for smaller screens */
    .table-responsive {
        overflow-x: auto; /* Enable horizontal scrolling if necessary */
    }

    .table {
        font-size: 14px; /* Adjust font size for smaller screens */
    }

    .table th,
    .table td {
        padding: 8px; /* Adjust padding for better spacing */
    }

  /* Adjust the button alignment for mobile */
  /* .table td.actions {
        text-align: left; /* Align the cell content to the left */


        .table td.actions a {
        margin-left: 10px; /* Adjust the margin for the button */
    }
 /* Adjust the margin-right of the icon for mobile */
 .mobile-icon {
        margin-right: 20px;
    }
}



</style>
<head>
<link href="{{ asset('backend/assets/libs/dropzone/min/dropzone.min.css') }}" rel="stylesheet" type="text/css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.4.0/dropzone.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.2/min/dropzone.min.css" rel="stylesheet">


</head>
<title>My Shopping Cart | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">My Shopping Cart</h4>
            <br>



        </div>


        @php
        $hasProducts = count($allCartItems) > 0;
        $total = 0; // Initialize the total to 0 before the loop
    @endphp

    <form action="{{ route('cart.empty') }}" method="post" onsubmit="return confirm('Are you sure you want to empty the cart?');">
        @csrf
        <button type="submit" class="btn btn-danger btn-rounded waves-effect waves-light empty-cart-btn">Empty Cart</button>
    </form>

    <p style="margin-left:20px;font-size:14px"><b>Please note that if you want to update the quantity in the shopping cart instead of removing products, you should go back to the previous page to add new quantities.</b></p>

    <div class="table-responsive">
        <table class="cart" style="margin-left: 20px;">
            <thead>
                <tr>
                    <th style="width: 20.6%; border: solid 1px">Product Picture</th>
                    <th style="width: 20.6%; border: solid 1px">Product Name</th>
                    <th style="width: 15%; border: solid 1px">Price</th>
                    <th style="width: 10%; border: solid 1px">Quantity</th>
                    <th style="width: 10%; border: solid 1px">Subtotal</th>
                    <th style="width: 20%; border: solid 1px">Action</th>
                </tr>
            </thead>
            <tbody>
                @if($hasProducts)
                    @foreach($allCartItems as $item)
                        <tr>
                            <td data-th="Product" class="text-center" style="border: solid 1px; width: 20.6%">
                                <div class="row">
                                    <div class="col-sm-2 hidden-xs">
                                        @if($item->type == 'product')
                                            <img src="{{ asset($item->product->product_image) }}" alt="Product Image" height="150px" class="img-responsive" />
                                        @else
                                            <img src="{{ asset($item->dealerStock->product_image) }}" alt="Dealer Stock Image" height="150px" class="img-responsive" />
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-th="Name" class="text-center" style="width: 20.6%; border: solid 1px; padding-left: 20px">
                                @if($item->type == 'product')
                                    {{ $item->product->product_name }}
                                @else
                                    {{ $item->dealerStock->product_name }}
                                @endif
                            </td>
                            <td data-th="Price" class="text-center" style="width: 10%; border: solid 1px; padding-left: 20px">
                                @php
                                    $role_id = Auth::user()->role_id; // Get the user's role_id
                                    $priceToShow = ($role_id == 700 && $item->type == 'product' && $item->product->customer_price)
                                                    ? $item->product->customer_price
                                                    : ($item->type == 'product'
                                                        ? $item->product->product_price
                                                        : $item->dealerStock->product_price);
                                @endphp
                                <span id="price_{{ $item->id }}">RM{{ $priceToShow }}</span>
                            </td>
                            <td data-th="Quantity" class="text-center" style="width: 8%; border: solid 1px; padding-left: 20px">
                                <div class="quantity">
                                    <input type="number" style="width: 70px;" step="1" min="1" max=""
                                        name="quantity_{{ $item->id }}" value="{{ $item->quantity }}" title="Qty"
                                        class="input-text qty pr-5" id="quantity_{{ $item->id }}"
                                        data-stock="{{ $item->type == 'product' ? $item->product->product_stock : $item->dealerStock->product_stock }}"
                                        data-cart-id="{{ $item->id }}"
                                        oninput="updateHiddenInputAndCalculateSubtotal({{ $item->id }})">
                                </div>
                            </td>
                            <td data-th="Subtotal" class="text-center" style="width: 18%; border: solid 1px" id="subtotal_{{ $item->id }}">
                                <span class="subtotal_price">RM{{ $subtotal = $priceToShow * $item->quantity }}</span>
                            </td>
                            <td class="actions" data-th="" style="width: 8%; border: solid 1px">
                                <a href="{{ route('remove.cart', $item->id) }}" class="btn btn-danger sm mobile-icon"
                                    style="height: 55px; margin-left: 25px;" title="Remove Carts" id="delete">
                                    <i class="fas fa-trash-alt w-75 lg mobile-icon" style='font-size:28px'></i>
                                </a>
                            </td>
                        </tr>
                        @php
                            $total += $subtotal; // Accumulate subtotal to total
                        @endphp
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" style="text-align: center">No products in the cart</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                @if($hasProducts)
                    <tr>
                        <td colspan="4" class="text-right" style="width: 10%; padding-left: 20px">Total:</td>
                        <td class="text-center" style="width: 10%; padding-left: 20px; font-size: 18px"><b><span id="total">RM{{ $total }}</span></b></td>
                        <td></td>
                    </tr>
                @endif
            </tfoot>
        </table>

    </div>
    </div>
<div>
    <div class="row">
        <div class="col-12">

            <form action="{{ route('payment') }}" method="post" onsubmit="return validateForm()">
                @csrf
                <div class="mb-3">
                    <label for="total_amount" class="form-label">E-Wallet Amount</label>
                    <input type="number" class="form-control" id="total_amount" name="total_amount" required min="{{ $total }}" step="0.01">
                    <div class="invalid-feedback">
                        Please enter a valid E-wallet amount matching the total price.
                    </div>
                </div>
                <center>
                    <button type="submit" class="btn btn-warning btn-rounded waves-effect waves-light">Proceed to Checkout</button>
                </center>
            </form>

            <script>
                function validateForm() {
                    var ewalletAmount = parseFloat(document.getElementById('total_amount').value);
                    var totalPrice = parseFloat("{{ $total }}"); // Total price from PHP variable

                    if (isNaN(ewalletAmount) || ewalletAmount !== totalPrice) {
                        alert('Please enter a valid E-wallet amount matching the total price.');
                        return false;
                    }
                    return true;
                }
            </script>


        </div>
    </div>
        <div>
            <div class="row">

                <div class="col-12">
                    {{-- <h4 class="mb-sm-0">My Shipping Address</h4> --}}

                    <div class="card">
                        <div class="card-body">
                            {{-- <input type="hidden" name="id" value="{{ $myaddress->id }}"> --}}
                            {{-- <div class="row">
                                <div class="col-lg-6">
                                    <div>
                                        <div class="mb-4">
                                            <label class="form-label" for="input-date1">Full Name</label>
                                            <input id="input-date1" class="form-control" value="" name="name" placeholder="Full Name" required>
                                            {{-- <span class="text-muted">e.g "dd/mm/yyyy"</span> --}}
                                        {{-- </div>
                                        <div class="mb-4">
                                            <label class="form-label" for="input-date2">Address Line 1</label>
                                            <input id="input-date2" class="form-control input-mask" value="" name="address_line_1" placeholder="Address Line 1" required>
                                            {{-- <span class="text-muted">e.g "mm/dd/yyyy"</span> --}}
                                        {{-- </div>
                                        <div class="mb-4">
                                            <label class="form-label" for="input-datetime">Address Line 2</label>
                                            <input id="input-datetime" class="form-control input-mask" value="" name="address_line_2" placeholder="Address Line 2"> --}}
                                            {{-- <span class="text-muted">e.g "yyyy-mm-dd'T'HH:MM:ss"</span> --}}
                                        {{-- </div> --}}
                                        {{-- <div class="mb-0">
                                            <label class="form-label" for="input-currency">Currency:</label>
                                            <input id="input-currency" class="form-control input-mask text-left" data-inputmask="'alias': 'numeric', 'groupSeparator': ',', 'digits': 2, 'digitsOptional': false, 'prefix': '$ ', 'placeholder': '0'">
                                            <span class="text-muted">e.g "$ 0.00"</span>
                                        </div> --}}
                                    {{-- </div>
                                </div> --}}
                                {{-- <div class="col-lg-6">
                                    <div class="mt-4 mt-lg-0">
                                        <div class="mb-4">
                                            <label class="form-label" for="input-repeat">Zipcode:</label>
                                            <input id="input-repeat" class="form-control input-mask" value="" type="number"  name="zipcode" placeholder="Zipcode" required > --}}
                                            {{-- <span class="text-muted">e.g "9999999999"</span> --}}
                                        {{-- </div> --}}
                                        {{-- <div class="mb-4">
                                            <label class="form-label" for="input-mask">Country</label>
                                            <input id="input-mask" class="form-control input-mask" value="" name="country" placeholder="Country" required> --}}
                                            {{-- <span class="text-muted">e.g "99-9999999"</span> --}}
                                        {{-- </div>
                                        <div class="mb-4">
                                            <label class="form-label" for="input-ip">State</label>
                                            <input id="input-ip" class="form-control input-mask" value="" name="state" placeholder="State" required> --}}
                                            {{-- <span class="text-muted">e.g "99.99.99.99"</span> --}}

                                        </div>
                                        {{-- <div class="mb-0">
                                            <label class="form-label" for="input-email">Email address::</label>
                                            <input id="input-email" class="form-control input-mask" data-inputmask="'alias': 'email'">
                                            <span class="text-muted">_@_._</span>
                                        </div> --}}
                                    {{-- </div>
                                </div>
                            </div> --}}
                            {{-- <input type="submit" class="btn btn-info waves-effect waves-light" value="Save"> --}}
                            <h4 class="card-title" style="text-align:center;font-size:24px">Transaction Details</h4>
                            <p class="card-title-desc" style="text-align:center;font-size:18px"> Bank Account:5040011321 <br> Bank Account Name: Sua Kai Young
                            </p>

<form action="{{ route('checkout') }}" method="post" class="dropzone" id="receiptDropzone" name="receiptDropzone" enctype="multipart/form-data">
    @csrf



<div class="fallback">
    <input name="receipt" id="receipt" type="file" accept="image/*" required>
</div>
<div class="dz-message needsclick">
    <div class="mb-3">
        <i class="display-4 text-muted ri-upload-cloud-2-line"></i>
    </div>
    <h4>Please upload your receipts.</h4>
</div>
<input type="hidden" name="total_amount" id="totalAmountInput">
<input type="hidden" name="hidden_quantity_" id="hidden_quantity_">


<div class="text-center mt-4">

    <button type="submit" id="checkout" name="checkout"  class="btn btn-warning btn-rounded waves-effect waves-light"onclick="disableButton()">Proceed to Checkout</button>
</div>
</form>

                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
</div>
{{-- C:\xampp\htdocs\suakaiyoung-learning-project\public\backend\assets\libs\dropzone\min\dropzone.min.js --}}
<script src="{{ asset('backend/assets/js/app.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.2/min/dropzone.min.js"></script>

{{-- <script src="{{ asset('backend/assets/libs/dropzone/min/dropzone.min.js') }}"></script> --}}

{{-- src="assets/libs/dropzone/min/dropzone.min.js" --}}
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>

$(document).ready(function() {
    updateTotal(); // Call updateTotal() on document ready
});

function updateHiddenInputAndCalculateSubtotal(cartItemId) {
    var quantity = parseInt($('#quantity_' + cartItemId).val());
    $('#hidden_quantity_' + cartItemId).val(quantity);
    calculateSubtotal(cartItemId); // Call your calculateSubtotal function here if needed
}

function calculateSubtotal(cartItemId) {
    var quantity = parseInt($('#quantity_' + cartItemId).val());
    var price = parseFloat($('#price_' + cartItemId).text().replace('RM', ''));
    var subtotal = quantity * price;
    $('#subtotal_' + cartItemId).text('RM ' + subtotal.toFixed(2));


 // Update the hidden input fields with the new quantity and subtotal values
 $('#quantity_' + cartItemId).val(quantity);
    $('#subtotal_' + cartItemId).val(subtotal.toFixed(2));

    updateTotal();
}

function updateTotal() {
    var total = 0;

    $('.cart tbody tr').each(function() {
        var cartItemId = $(this).find('.qty').data('cart-id');
        var quantity = parseInt($('#quantity_' + cartItemId).val());
        var price = parseFloat($('#price_' + cartItemId).text().replace('RM', ''));
        var subtotal = quantity * price;

        $('#subtotal_' + cartItemId).text('RM ' + subtotal.toFixed(2));

        total += subtotal;
    });


    $('.cart-item').each(function () {
        var subtotal = parseFloat($(this).find('.subtotal').text().replace('RM', ''));
        total += subtotal;
    });
    $('#total').text('RM ' + total.toFixed(2));

    $('#totalAmountInput').val(total.toFixed(2));

}
// <!-- Dropzone configuration -->
Dropzone.autoDiscover = false;
document.addEventListener("DOMContentLoaded", function () {

    Dropzone.options.receiptDropzone = {
        autoProcessQueue: false, // Disable auto-upload
        maxFiles: 1, // Allow only one file to be uploaded
        addRemoveLinks: true,
        // parallelUploads: 100,
        renameFile: function(file) {
            var dt = new Date();
            var time = dt.getTime();
            return time + file.name;
        },
        // Other Dropzone options if needed
        init: function () {
            const submitButton = document.querySelector("#checkout");
            const myDropzone = this;

            submitButton.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                myDropzone.processQueue(); // Process the queue manually
            });

            // Listen for the success event when all files are uploaded
            this.on("success", function (file, response) {
                // Assuming the response contains the notification data
                const notification = response.notification;
                if (notification && notification.message) {
                    // Display the notification message using your preferred method
                    alert(notification.message);
                }

                // Redirect after successful checkout
                window.location.href = "{{ route('success.checkout') }}"; // Replace with your success page route
            });

            // Listen to the addedfile event to handle file upload and saving
            this.on("addedfile", function (file) {
                // Make sure to remove any previously uploaded file
                if (this.files.length > 1) {
                    this.removeFile(this.files[0]);
                }
            });

            updateTotal(); // Call updateTotal() on document ready
        },
    };


});






var formSubmitted = false;

function disableButton() {
    if (formSubmitted) {
        return false;
    }

    var submitButton = document.getElementById('checkout');
    submitButton.disabled = true;
    submitButton.textContent = 'Checking Out...'; // Show a loading text on the button

    // Submit the form after a short delay (e.g., 0.5 seconds) to give the disabled visual effect
    setTimeout(function () {
        document.getElementById('receiptDropzone').submit();
    }, 500);

    formSubmitted = true;
    return true;
}
function validateForm() {
        var ewalletAmount = parseFloat(document.getElementById('total_amount').value);
        var totalPrice = parseFloat("{{ $total }}"); // Total price from PHP variable

        if (isNaN(ewalletAmount) || ewalletAmount !== totalPrice) {
            alert('Please enter a valid E-wallet amount matching the total price.');
            return false;
        }
        return true;
    }

</script>

@endsection
