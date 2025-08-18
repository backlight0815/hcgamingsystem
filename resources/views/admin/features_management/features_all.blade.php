@extends('admin.admin_master')
@section('admin')

<style>
    .btn {
        float: right;
        padding: 10px;
    }

  .status-yes {
    color: #0f5132;              /* Dark green text */
    background-color: #d1e7dd;   /* Light green background */
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
    text-align: center;
    display: inline-block;
    min-width: 40px;
}

.status-no {
    color: #842029;              /* Dark red text */
    background-color: #f8d7da;   /* Light red background */
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
    text-align: center;
    display: inline-block;
    min-width: 40px;
}

</style>

<title>Feature Management | HC Gaming Studio</title>

<div class="page-content">
<div class="container-fluid">

<!-- start page title -->
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">All Features</h4>
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
                <button class="btn btn-success waves-effect waves-light btn" type="button" onclick="redirectToPage()">Add New Feature</button>

                <h4 class="card-title">Feature Data</h4>

                <table id="datatable" class="table table-striped table-hover table-bordered dt-responsive nowrap" 
       style="border-collapse: collapse; border-spacing: 0; width: 100%;">
    <thead class="table-dark">
        <tr>
            <th style="width: 5%; text-align: center;">SI</th>
            <th style="width: 55%;">Feature Name</th>
            <th style="width: 15%; text-align: center;">Enabled</th>
            <th style="width: 25%; text-align: center;">Action</th>
        </tr>
    </thead>

    <tbody>
        @foreach($features as $key => $feature)
        <tr>
            <td class="text-center align-middle">{{ $key + 1 }}</td>
            <td class="align-middle">{{ $feature->feature_name }}</td>
            <td class="text-center align-middle">
                <span class="{{ $feature->enabled ? 'status-yes' : 'status-no' }}">
                    {{ $feature->enabled ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="text-center align-middle">
                <a href="{{ route('edit.feature', $feature->id) }}" 
                   class="btn btn-info btn-sm me-1" title="Edit Feature">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="{{ route('delete.feature', $feature->id) }}" 
                   class="btn btn-danger btn-sm" 
                   title="Delete Feature" 
                   id="delete"
                   onclick="return confirm('Are you sure to delete this feature?')">
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
        window.location.href = "{{ route('add.feature') }}";
    }
</script>

@endsection
