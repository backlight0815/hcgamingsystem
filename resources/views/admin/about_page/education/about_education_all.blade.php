@extends('admin.admin_master')
@section('admin')
<head>
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

</head>

<div class="page-content">
<div class="container-fluid">
<style>
    .btn {
        float: right;
        padding: 10px;
    }
</style>
<!-- start page title -->

<title>About Page - Educational  | HC Gaming Studio</title>

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">My Educational Background</h4>
            <br>
            <button class="btn btn-success waves-effect waves-light btn" type="submit" onclick="redirectToPage()">Add New Educational Background</button>



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

                <h4 class="card-title">My Educational Background Data</h4>


                <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                    <thead>
                    <tr>
                        <th>SI</th>
                        <th>Education Title</th>
                        <th>Period</th>
                        {{-- <th>Description</th> --}}
                        <th>Action</th>

                    </tr>
                    </thead>


                    <tbody>
                        @php($i=1)
                        @foreach($educations as $item)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->period }}</td>
                        {{-- <td>{{ $item->long_description }}</td> --}}


                        <td>

                            <a href="{{ route('edit.education',$item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i>   </a>
<a href="{{ route('delete.education',$item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i>   </a>


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
<script>
    function redirectToPage() {
        window.location.href = "{{ route('add.education') }}";
    }
</script>
@endsection
