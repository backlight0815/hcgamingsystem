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
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

<!-- Add jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- Add the Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.min.js"></script>

</head>
<title>My E-Wallet History |HC Gaming</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">My E Wallet History</h4>
                    <button class="btn btn-success waves-effect waves-light" type="submit" onclick="redirectToPage()">Top Up </button>

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


    <div class="row text-center " >
        <div class="row">
            <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                <h5 class="mb-0">RM {{ $totalCreditAmount }}</h5>
                <p class="text-muted text-truncate">Total Credit (RM)</p>
            </div>

            <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                <h5 class="mb-0">RM {{ $totalDebitAmount }}</h5>
            <p class="text-muted text-truncate">Total Debit (RM)</p>
        </div>

        <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
            <h5 class="mb-0">RM{{ $currentBalance }}</h5>
            <p class="text-muted text-truncate">Current Balance</p>
        </div>

    </div>

    </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="myshippingorder" class="table table-bordered" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Amount</th>
                                            <th>Type of Usage</th>
                                            <th>Remarks</th>
                                            <th>Transaction Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php($i=1)
                                        @foreach($walletHistoryData as $item)
                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td>RM {{ $item->amount }}</td>
                                            <td> {{ $item->type }}</td>
                                            <td> {{ $item->remarks }}</td>
                                            <td>{{ $item->created_at }}</td>
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
    function redirectToPage() {
        window.location.href = "{{ route('add.wallet') }}";
    }
</script>
@endsection

