@extends('admin.admin_master')
@section('admin')
<head>
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

</head>
<style>
    .btn {
        float: right;
        padding: 10px;
    }
</style>
<div class="page-content">
<div class="container-fluid">

<title>About Page - Skill  | HC Gaming Studio</title>
<!-- start page title -->
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">All Skill</h4>



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
                <button class="btn btn-success waves-effect waves-light btn" type="submit" onclick="redirectToPage()">Add New Skill</button>

                <h4 class="card-title">My Skill Data</h4>


                <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                    <thead>
                    <tr>
                        <th>SI</th>
                        <th>Skill</th>
                        <th>Skill Level</th>

                        <th>Action</th>

                    </tr>
                    </thead>


                    <tbody>
                        @php($i=1)
                        @foreach($skills as $item)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td>{{ $item->skill }}</td>
                        <td>{{ $item->level }}%</td>

                        <td>

                            <a href="{{ route('edit.skill',$item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i>   </a>
<a href="{{ route('delete.skill',$item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i>   </a>


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
        window.location.href = "{{ route('add.skill') }}";
    }
</script>
@endsection
