@extends('admin.admin_master')
@section('admin')

<head>
    <!-- Add the Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
</head>

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <title>Trading Reason Management | HC Gaming Studio</title>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Trading Reason Management</h4>
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

    <h4 class="card-title d-flex justify-content-between align-items-center">
        <span>All Trading Reason Data</span>
        <a href="{{ route('add.trading.reason') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Reason
        </a>
    </h4>
                        <table id="datatable" class="table table-bordered dt-responsive nowrap"
                            style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>SI</th>
                                    <th>Reason Name</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($reasons as $key => $item)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>
                                            <a href="{{ route('edit.trading.reason', $item->id) }}"
                                                class="btn btn-info sm" title="Edit Data"><i
                                                    class="fas fa-edit"></i></a>
                                            <a href="{{ route('delete.trading.reason', $item->id) }}"
                                                class="btn btn-danger sm" title="Delete Data" id="delete"><i
                                                    class="fas fa-trash-alt"></i></a>
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
