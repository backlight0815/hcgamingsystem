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

<title>Sales Performances |HC Gaming</title>
<div class="page-content">
    <div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Sales Performances</h4>



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
                        <div class="table-responsive">

                            <table id="salesperformances" class="table table-bordered " style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="text-align:center">SI</th>
                                        <th style="text-align:center">Username</th>
                                        <th style="text-align:center">Total Sales (RM)</th>
                                        <th style="text-align:center">Number of Recruitment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($i=1)
                                    @foreach($salesPerformances as $item)
                                        <tr>
                                            <td style="text-align: center">{{ $i++ }}</td>
                                            <td style="text-align: center">{{ $item->user->username }}</td>
                                            <td style="text-align: center">RM {{ $item->total_sales }}</td>
                                            <td style="text-align: center">
                                                {{ $downlineData->where('upline_user_id', $item->user->id)->first() ? $downlineData->where('upline_user_id', $item->user->id)->first()->downline_count : 0 }}

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
        $('#salesperformances').DataTable({
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

