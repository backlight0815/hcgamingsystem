@extends('admin.admin_master')
@section('admin')
<div class="page-content">
<div class="container-fluid">

    <title>Service Management | HC Gaming Studio</title>
<head>
        <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
</head>
    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Service Management</h4>



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

                    <h4 class="card-title mb-2" > All Service Data</h4>

                    <div class="row text-center " >
                        <div class="col-12 col-sm-6 col-md-4 border border-dark pt-3 mb-3">
                            <h5 class="mb-0">{{ $service_index }}</h5>
                            <p class="text-muted text-truncate">Total Services</p>
                        </div>
                        {{-- <div class="col-4 border border-dark pt-3">
                            <h5 class="mb-0">8489</h5>
                            <p class="text-muted text-truncate">Pending</p>
                        </div>
                        <div class="col-4 border border-dark pt-3">
                            <h5 class="mb-0">985412</h5>
                            <p class="text-muted text-truncate">Deactivated</p>
                        </div> --}}
                    </div>
                <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                    <thead>
                    <tr>
                        <th>SI</th>
                        <th>Service Name</th>
                        <th>Short Description</th>
                        <th>Services Image</th>
                        <th>Action</th>

                    </tr>
                    </thead>


                    <tbody>
                        @php($i=1)
                        @foreach($service as $item)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td>{{ $item->service_title }}</td>
                        <td>{{ $item->service_title }}</td>

                        <td>
                            <a href="{{ asset($item->service_image) }}" data-lightbox="service-images">
                                <img src="{{ asset($item->service_image) }}" style="width: 60px; height: 60px;">
                            </a>
                        </td>
                        <td>
<a href="{{ route('edit.service',$item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i>   </a>
<a href="{{ route('delete.service',$item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i>   </a>


                        </td>

                    </tr>
@endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div> <!-- end col -->
</div> <!-- end row -->

</div> <!-- container-fluid -->
</div>

@endsection
