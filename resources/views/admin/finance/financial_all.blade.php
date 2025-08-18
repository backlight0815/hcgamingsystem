@extends('admin.admin_master')
@section('admin')
<script src="assets/libs/pdfmake/build/pdfmake.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<title>Dealer E-Wallet Request | HC Gaming Studio</title>
<head>

   <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.min.css">

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
                    <h4 class="mb-sm-0">Dealer Wallet Request</h4>
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

                                <div class="col-md-4 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">{{ $WalletRequestCount }}</h5>

                                    <p class="text-muted text-truncate">Total E-Wallet Request</p>
                                </div>
                                <div class="col-md-4 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">RM {{ $processingTotal }}</h5>

                                    <p class="text-muted text-truncate">Total Pending Amount</p>
                                </div>
                                <div class="col-md-4 col-sm-6 border border-dark pt-3 mb-3">
                                    <h5 class="mb-0">RM {{ $ApprovedTotal }}</h5>
                                    <p class="text-muted text-truncate">Total Approved Amount</p>
                                </div>


                            </div>
                        </div>
                                                <div class="table-responsive">

                        <table id="walletrequest" class="table table-bordered" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>

                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Transaction Date</th>
                                    <th>Action</th>

                                    <th>Payment Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($i=1)
                                @foreach($ewalletrequest as $item)
                                <tr>
                                    <td>{{ $i++ }}</td>
                                    <th>{{ $item->user->username }}</th>

                                    <td>RM {{ $item->amount }}</td>                                    @if($item->status==0)
                                    <td style="color:grey"> Pending </td>
                                    @elseif($item->status==1)
                                    <td style="color:orange"> Approved </td>
                                    @elseif($item->status==-1)
                                    <td style="color:red"> Rejected </td>

                                    @endif

                                    <td>{{ $item->created_at }}</td>
                                    <td>
                                        @if($item->status === '0')
                                            <a href="{{ route('update.wallets.to.approve.status', $item->id) }}" class="btn btn-info sm" title="Approve Request" onclick="return confirm('Do you want to proceed with approving this request?')">
                                                <i class="fas fa-check"></i>
                                            </a>

                                            <a href="{{ route('update.wallets.to.reject.status', $item->id) }}" class="btn btn-danger sm" title="Reject Request" onclick="return confirm('Do you want to proceed with rejecting this request?')">
                                                <i class="fas fa-times"></i>
                                            </a>

                                        @endif
                                    </td>

                                <td>
                                    <a href="{{ asset($item->receipt) }}" data-lightbox="image" data-title="Payment Proof">
                                        <img src="{{ asset($item->receipt) }}" style="width: 60px; height: 60px;" alt="Payment Proof">
                                    </a>
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
    </div>
</div>
<script>
  $(document).ready(function() {
        $('#walletrequest').DataTable({
            // Other DataTable options...
            "columnDefs": [
                {
                    "orderable": false, "targets": 2
                    } // Disable sorting for the third column (index 2)
            ]
        });
    });
    </script>
@endsection
