@extends('admin.admin_master')
@section('admin')
<script src="assets/libs/pdfmake/build/pdfmake.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<title>Shipping Order | HC Gaming Studio</title>
<head>

   <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.min.css">
<!-- Include Bootstrap CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<!-- Include jQuery first, then Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.6/js/jquery.dataTables.min.js"></script>
</head>

<style>
@media screen and (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
}
</style>
<div class="page-content">
    <div class="container-fluid">
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Shipping Orders</h4>
                </div>
            </div>
        </div>
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
                <div class="card">
                    <div class="card-body">
                        {{-- <h4 class="card-title mb-2">Shipping Order Data</h4> --}}
                        <div class="row text-center " >
                             <div class="row">
                                <div class="col-md-3 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $shippingordersCount }}</h5>
                                    <p class="text-muted text-truncate">Total Order</p>
                                </div>

                                <div class="col-md-3 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $ApproveCount }}</h5>
                                    <p class="text-muted text-truncate">No Confirmed Order</p>
                                </div>

                                <div class="col-md-3 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $DeliveryCount }}</h5>
                                    <p class="text-muted text-truncate">No Delivery Order</p>
                                </div>

                                <div class="col-md-3 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $CompleteCount }}</h5>
                                    <p class="text-muted text-truncate">No Complete Order</p>
                                </div>
                            </div>
                        </div>
                                                <div class="table-responsive">

                        <table id="shippingorder" class="table table-bordered" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>

                                    <th>Grand Total</th>
                                    <th>Stocks</th>
                                    <th>Status</th>
                                    <th>Transaction Date</th>
                                    <th>Action</th>

                                    <th>Payment Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($i=1)
                                @foreach($shippingorders as $item)
                                <tr>
                                    <td>{{ $i++ }}</td>
                                    <th>{{ $item->user->username }}</th>

                                    <td>RM {{ $item->total_amount }}</td>
                                    <td>{{ $item->orderItems->sum('quantity') }}</td>
                                    @if($item->status==0)
                                    <td style="color:grey"> Processing </td>
                                    @elseif($item->status==1)
                                    <td style="color:orange"> Confirmed </td>
                                    @elseif($item->status==2)
                                    <td style="color:darkblue"> Delivery </td>
                                    @elseif($item->status==3)
                                    <td style="color:green"> Completed </td>
                                    @elseif($item->status==-1)
                                    <td style="color:red"> Rejected </td>

                                    @endif

                                    <td>{{ $item->created_at }}</td>
                                    <td>
                                        @if($item->status === '0')
                                            <a href="{{ route('update.shipping.to.approve.status', $item->id) }}" class="btn btn-info sm" title="Approve Order" onclick="return confirm('Do you want to proceed with approving this order?')">
                                                <i class="fas fa-check"></i>
                                            </a>

                                            <a href="{{ route('update.shipping.to.reject.status', $item->id) }}" class="btn btn-danger sm" title="Reject Order" onclick="return confirm('Do you want to proceed with rejecting this order?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        @elseif($item->status === '1')
                                            <a href="{{ route('update.shipping.to.delivery.status', $item->id) }}" class="btn btn-info sm" title="Delivery Order" onclick="return confirm('Do you want to proceed with marking this order as delivered?')">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        @elseif($item->status === '2')
                                            <a href="{{ route('update.shipping.to.complete.status', $item->id) }}" class="btn btn-info sm" title="Complete Order" onclick="return confirm('Do you want to proceed with marking this order as completed?')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        @endif

   <!-- View Details Button -->
   <a href="javascript:void(0);" class="btn btn-info sm view-order-details" title="View Order Details" data-id="{{ $item->id }}">
    <i class="fas fa-eye"></i>
</a>
                                    </td>

                                <td>
                                    <a href="{{ asset($item->payment_proof) }}" data-lightbox="image" data-title="Payment Proof">
                                        <img src="{{ asset($item->payment_proof) }}" style="width: 60px; height: 60px;" alt="Payment Proof">
                                    </a>
                                </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
                <div id="orderDetailsModal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">Order Details</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body" id="orderDetailsContent">
                          <!-- Order details will be populated here -->
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>





            </div> <!-- end col -->
        </div> <!-- end row -->


<script>
  $(document).ready(function() {
        $('#shippingorder').DataTable({
            // Other DataTable options...
            "columnDefs": [
                {
                    "orderable": false, "targets": 2
                    } // Disable sorting for the third column (index 2)
            ]
        });
    });
// Handle view order details button click
$(document).on('click', '.view-order-details', function() {
  var orderId = $(this).data('id');

  // Make an AJAX request to fetch order details
  $.ajax({
    url: '/order-items/' + orderId,
    method: 'GET',
    success: function(response) {
      // Construct the HTML for the order details
      var htmlContent = '';
      htmlContent += '<p>Order ID: ' + response.id + '</p>';
      htmlContent += '<h5>Product Items</h5>';
      htmlContent += '<ul>';

      response.order_items.forEach(function(orderItem) {
        var productImage = "{{ asset('') }}" + orderItem.product.product_image;
        htmlContent += '<li>';
        htmlContent += '<img src="' + productImage + '" class="rounded avatar-lg" height="150px" width="150px" alt="Product image">';
        htmlContent += '<br>';

        htmlContent += 'Product: ' + orderItem.product.product_name + '<br>';
        htmlContent += 'Quantity: ' + orderItem.quantity + '<br>';
        htmlContent += '</li><br>'; // Add a break after each product
      });

      htmlContent += '</ul>';

      // Populate the modal with the order details
      $('#orderDetailsContent').html(htmlContent);
      // Show the modal
      $('#orderDetailsModal').modal('show');
    },
    error: function(error) {
      console.error(error);
      alert('An error occurred while fetching order details.');
    }
  });
});


  // Close modal when the modal close button is clicked
  $('#orderDetailsModal .close').on('click', function() {
    $('#orderDetailsModal').modal('hide');
  });

  // Close modal when close button in modal footer is clicked
  $('#orderDetailsModal .modal-footer button').on('click', function() {
    $('#orderDetailsModal').modal('hide');
  });

  // Close modal when clicking outside the modal
  $(document).on('click', function(event) {
    if ($(event.target).hasClass('modal')) {
      $('#orderDetailsModal').modal('hide');
    }
  });


  </script>
@endsection
