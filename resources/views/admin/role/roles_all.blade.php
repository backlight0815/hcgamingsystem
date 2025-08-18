@extends('admin.admin_master')
@section('admin')

<style>
    .btn {
        float: right;
        padding: 10px;
    }
</style>

<title>Role Management | HC Gaming Studio</title>

<div class="page-content">
<div class="container-fluid">

<!-- start page title -->
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">All Roles</h4>
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
                <button class="btn btn-success waves-effect waves-light btn" type="submit" onclick="redirectToPage()">Add New Role</button>

                <h4 class="card-title">Role Data</h4>

              <table id="datatable" class="table table-striped table-hover table-bordered dt-responsive nowrap" 
       style="border-collapse: collapse; border-spacing: 0; width: 100%;">
    <thead class="table-dark">
        <tr>
            <th style="width: 50px;">SI</th>
            <th style="width: 100px;">Role ID</th>
            <th>Role Name</th>
            <th>Description</th>
            <th style="width: 140px; text-align: center;">Action</th>
        </tr>
    </thead>

    <tbody>
        @foreach($roles as $key => $role)
        <tr>
            <td class="align-middle text-center">{{ $key + 1 }}</td>
            <td class="align-middle text-center">{{ $role->id }}</td>
            <td class="align-middle">{{ $role->name }}</td>
            <td class="align-middle">{{ $role->description }}</td>
            <td class="align-middle text-center">
                <a href="{{ route('edit.role', $role->id) }}" 
                   class="btn btn-info btn-sm me-1" 
                   title="Edit Role">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="{{ route('delete.role', $role->id) }}" 
                   class="btn btn-danger btn-sm" 
                   title="Delete Role" 
                   onclick="return confirm('Are you sure you want to delete this role?')">
                    <i class="fas fa-trash-alt"></i>
                </a>
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
        window.location.href = "{{ route('add.role') }}";
    }
</script>

@endsection
