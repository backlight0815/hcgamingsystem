@extends('admin.admin_master')
@section('admin')

<style>
    @media screen and (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
    }
    </style>
    <head>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.6/js/jquery.dataTables.min.js"></script>
    </head>

        <title>Dashboard | HC Gaming</title>

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Dashboard</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript: void(0);">Hc Gaming</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>



                </div>
            </div>
        </div>
        <!-- end page title -->

        <div class="row">
            @if(Auth::user()->role_id != 700)

            <div class="col-xl-3 col-md-6">

                <div class="card">

                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-truncate font-size-14 mb-2">{{ $userstatistics['label'] }}</p>
                                <h4 class="mb-2">{{ $userstatistics['total'] }}</h4>
                                <p class="text-muted mb-0"><span class="text-success fw-bold font-size-12 me-2"><i class="ri-arrow-right-up-line me-1 align-middle"></i>9.23%</span>from previous period</p>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-light text-primary rounded-3">
                                    <i class="ri-user-3-line font-size-24"></i>

                                </span>
                            </div>
                        </div>
                    </div><!-- end cardbody -->

                </div><!-- end card -->

            </div><!-- end col -->
            @endif

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-truncate font-size-14 mb-2">{{ $orderstatistics['label'] }}</p>
                                <h4 class="mb-2">{{ $orderstatistics['total'] }}</h4>
                                <p class="text-muted mb-0"><span class="text-danger fw-bold font-size-12 me-2"><i class="ri-arrow-right-down-line me-1 align-middle"></i>1.09%</span>from previous period</p>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-light text-success rounded-3">
                                    <i class="ri-file-list-3-line font-size-24"></i>

                                </span>
                            </div>
                        </div>
                    </div><!-- end cardbody -->
                </div><!-- end card -->
            </div><!-- end col -->
            @if(Auth::user()->role_id == '1'||Auth::user()->role_id=='2')
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-truncate font-size-14 mb-2">Total Product</p>
                                <h4 class="mb-2">{{ $productstatistics['total'] }}</h4>
                                <p class="text-muted mb-0"><span class="text-success fw-bold font-size-12 me-2"><i class="ri-arrow-right-up-line me-1 align-middle"></i>16.2%</span>from previous period</p>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-light text-primary rounded-3">
                                    <i class="ri-shopping-cart-2-line font-size-24"></i>

                                </span>
                            </div>
                        </div>
                    </div><!-- end cardbody -->
                </div><!-- end card -->
            </div><!-- end col -->
            @endif
            @if(Auth::user()->role_id=='350')
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-truncate font-size-14 mb-2">Total Product Launch</p>
                                <h4 class="mb-2">{{ $downlinePurchasedProductsCount }}</h4>
                                <p class="text-muted mb-0"><span class="text-success fw-bold font-size-12 me-2"><i class="ri-arrow-right-up-line me-1 align-middle"></i>16.2%</span>from previous period</p>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-light text-primary rounded-3">
                                    <i class="ri-shopping-cart-2-line font-size-24"></i>

                                </span>
                            </div>
                        </div>
                    </div><!-- end cardbody -->
                </div><!-- end card -->
            </div><!-- end col -->
            @endif
<!--Latest-->

@if (Auth::user()->role_id == '1' || Auth::user()->role_id == '2' || Auth::user()->role_id == '350')
    <div class="col-xl-3 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <p class="text-truncate font-size-14 mb-2">Total Sales (RM)</p>
                        @if (Auth::user()->role_id == '1' || Auth::user()->role_id == '2')
                            @if (isset($salesPerformances) && $salesPerformances->isNotEmpty())
                                <h4 class="mb-2">{{ $salesPerformances->sum('total_sales') }}</h4>
                            @else
                                <h4 class="mb-2">0</h4>
                            @endif
                        @elseif (Auth::user()->role_id == '350')
                            @if (isset($salesPerformances) && $salesPerformances->isNotEmpty())
                                <h4 class="mb-2">{{ $salesPerformances->where('user_id', Auth::user()->id)->sum('total_sales') }}</h4>
                            @else
                                <h4 class="mb-2">0</h4>
                            @endif
                        @endif
                        <p class="text-muted mb-0"><span class="text-success fw-bold font-size-12 me-2"><i class="ri-arrow-right-up-line me-1 align-middle"></i>8.5%</span>from previous period</p>
                    </div>
                    <div class="avatar-sm">
                        {{-- <span class="avatar-title bg-light text-primary rounded-3"> --}}
                            {{-- <i class="ri-settings-2-line font-size-24"></i> --}}
                        {{-- </span> --}}
                    </div>
                </div>
            </div><!-- end cardbody -->
        </div><!-- end card -->
    </div><!-- end col -->
@endif







        </div><!-- end row -->

        <div class="row">

        <!-- end row -->

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body">
                        <div class="dropdown float-end">
                            <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="mdi mdi-dots-vertical"></i>
                            </a>

                        </div>

                        <h4 class="card-title mb-4">Latest Transactions</h4>

                        <div class="table-responsive">
                            <table id="myshippingorder" class="table table-bordered" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>

                                        <th>Grand Total</th>
                                        <th>Stocks</th>
                                        <th>Status</th>
                                        <th>Transaction Date</th>
                                        {{-- <th>Action</th> --}}

                                        <th>Payment Proof</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($i=1)
                                    @foreach($shippingorders  as $item)
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
                                        <a href="{{ asset($item->payment_proof) }}" data-lightbox="image" data-title="Payment Proof">
                                            <img src="{{ asset($item->payment_proof) }}" style="width: 60px; height: 60px;" alt="Payment Proof">
                                        </a>
                                    </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div><!-- end card -->
                </div><!-- end card -->
            </div>
            <!-- end col -->

        </div>
        <!-- end row -->
    </div>

</div>
<script>
  $(document).ready(function() {
    $('#myshippingorder').DataTable({
        // Other DataTable options...
        "columnDefs": [
            { "orderable": false, "targets": [2, 6] } // Disable sorting for columns 3 (index 2) and 5 (index 6)
        ]
    });
});
      </script>
@endsection
