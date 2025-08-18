@extends('admin.admin_master')
@section('admin')
<style>
    @media screen and (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
    }

    /* CSS for larger modal and enlarged image */
.modal-lg {
    max-width: 90%; /* Set maximum width for the larger modal */
}

.img-enlarge {
    cursor: pointer; /* Change cursor to pointer when hovering over image */
}

.img-enlarge.enlarged {
    max-width: 100%; /* Set maximum width to 100% when image is enlarged */
    max-height: 100%; /* Set maximum height to 100% when image is enlarged */
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    margin: auto;
    z-index: 1050; /* Ensure that the enlarged image is displayed above other elements */
    background-color: rgba(0, 0, 0, 0.5); /* Add semi-transparent background */
}

</style>
<head>
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

<!-- Add jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- Add the Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.min.js"></script>
<title>HC Gaming | My Commission</title>
</head>


        <div class="page-content">
            <div class="container-fluid">

                            <!-- end page title -->
    <div class="breadcrumb">
        @foreach ($breadcrumbData as $breadcrumb)
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
            @if (!$loop->last)
                <span> / </span>
            @endif
        @endforeach
    </div>

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">My Commission</h4>
                            <div>
                                <button class="btn btn-success waves-effect waves-light" type="button" data-bs-toggle="modal" data-bs-target="#tutorialModal">How to earn commission</button>
                                <button class="btn btn-success waves-effect waves-light ms-2" type="button" data-bs-toggle="modal" data-bs-target="#calculatorModal">Commission Calculator</button>
                            </div>
                        </div>
                        <div class="row text-center " >
                            <div class="row">

                                <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $totalCommission }} pts</h5>
                                <p class="text-muted text-truncate">Total Commission Earnr</p>
                            </div>



                        </div>

                        </div>

                    </div>
                </div>
                <!-- end page title -->





    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">

                        <table id="salesperformances" class="table table-bordered " style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th style="text-align:center">SI</th>
                                    <th style="text-align:center">UserID</th>
                                    <th style="text-align:center">OrderID</th>
                                    {{-- <th style="text-align:center">OrderID</th> --}}

                                    <th style="text-align:center">Username</th>

                                    <th style="text-align:center">Commission Earn</th>
                                    <th style="text-align:center">Date</th>

                                </tr>
                            </thead>
                            <tbody>
                                @php($i=1)
                                @foreach($dealercommission as $item)
                                    <tr>
                                        <td style="text-align: center">{{ $i++ }}</td>
                                        <td style="text-align: center">{{ $item->downline_user_id }}</td>
                                        <td style="text-align: center">{{ $item->order_id }}</td>
                                        {{-- <td style="text-align: center">{{ $item->orderItems->product_id }}</td> --}}
                                        <td style="text-align: center">{{ $item->downlineUserbane->username }}</td>

                                        <td style="text-align: center">{{ $item->commission_amount }}</td>
                                        <td style="text-align: center">
{{ $item->updated_at }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>







                </div>


                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->




<!-- Tutorial Modal -->
<div class="modal fade" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Added modal-lg class for larger modal -->

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tutorialModalLabel">Commission Tutorial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tutorial Steps -->
                <div class="tutorial-step">
                    <h6>Introduction</h6>
                    <p>Welcome to our commission earning tutorial! In this guide, you will learn how to earn commissions from downline sales within our platform. By following these steps, you can maximize your earnings through our direct agent commission structure.

                    </p>
                    <img src="/commission_tutorial/Guideline.jpg" class="img-fluid img-enlarge"  alt="Step 3 Image">
                </div>
                <br>
                <div class="tutorial-step">
                    <h6>  Step 2: Understanding Commission Structure

                    </h6>
                    <p>Now, let's see how commission is calculated. For example, if the total order amount is RM2000 in one order ID, your commission would be RM2000 * 0.05 = RM100. This commission is earned solely from your direct downline sales.

                    </p>
                    <br>
                    <p>Not only that, if the buyer is your's downline and purchase your product. It will earn 2% commission extra,  therefore you will earn total of 7% commission</p>
                    <img src="/commission_tutorial/step2.jpg" class="img-fluid img-enlarge"  alt="Step 3 Image">
                </div>
                <br>

                <div class="tutorial-step">
                    <h6>Step 3: Accumulating Commission

                    </h6>
                    <p>As you earn commissions from downline sales, they accumulate in your account. When your commission reaches RM100, it is automatically added to your E-wallet as RM10. Additionally, for every 200 score accumulated, RM20 is added to your E-wallet.


                    </p>
                    <img src="/commission_tutorial/step3.jpg" class="img-fluid img-enlarge"  alt="Step 3 Image">
                </div>

                                <br>

                <div class="tutorial-step">
                    <h6>Step 4: Limitations on Commission
                    </h6>
                    <p>It's important to note that you can only earn commissions from direct downline sales. For instance, if Agent A recruits Agent B, Agent A earns commissions from B's sales but cannot earn commissions from Agent C. However, Agent B can earn commissions from Agent C and so forth.


                    </p>
                 <!--   <img src="/commission_tutorial/step3.jpg" class="img-fluid img-enlarge"  alt="Step 3 Image">-->
                </div>

                                <br>

                <div class="tutorial-step">
                    <h6>Conclusion:
                    </h6>
                    <p>Congratulations! You've completed the tutorial on earning commissions from downline sales. By understanding the commission structure, calculating commissions, and accumulating earnings, you can effectively maximize your earnings as a direct agent in our platform. Happy earning!







                    </p>
                   <!-- <img src="https://example.com/step2-image.jpg" alt="Step 2 Image"> -->
                </div>
                <!-- Add more steps as needed -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    </div>
</div>
<!-- Commission Calculator Modal -->
<div class="modal fade" id="calculatorModal" tabindex="-1" aria-labelledby="calculatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calculatorModalLabel">Commission Calculator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Commission Calculator Form -->
                <form id="commissionCalculatorForm">
                    <div class="mb-3">
                        <label for="orderAmount" class="form-label">Order Amount (RM)</label>
                        <input type="number" class="form-control" id="orderAmount" name="orderAmount" placeholder="Enter order amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="commissionRate" class="form-label">Commission Rate (%)</label>
                        <input type="number" class="form-control" id="commissionRate" value="5" readonly name="commissionRate" placeholder="Enter commission rate" required>
                    </div>
                    <div class="mb-3">
                        <label for="commissionEarn" class="form-label">Total Commission Earn (pts)</label>
                        <input type="number" class="form-control" id="commissionEarn" name="commissionEarn" readonly>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" id="calculateCommission">Calculate</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


    <script>

        // JavaScript to handle image enlargement and minimization
$(document).ready(function() {
    $('.img-enlarge').click(function() {
        $(this).toggleClass('enlarged'); // Toggle 'enlarged' class on image click
    });
});
$(document).ready(function() {
        $('#calculateCommission').click(function() {
            // Get order amount and commission rate values
            var orderAmount = parseFloat($('#orderAmount').val());
            var commissionRate = parseFloat($('#commissionRate').val());

            // Calculate total commission earn
            var totalCommissionEarn = (orderAmount * commissionRate) / 100;

            // Set total commission earn value
            $('#commissionEarn').val(totalCommissionEarn.toFixed(2)); // Limit to 2 decimal places
        });
    });
    </script>


    @endsection
