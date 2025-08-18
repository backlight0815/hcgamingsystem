@extends('admin.admin_master')
@section('admin')

<style>
    .btn {
        float: right;
        padding: 10px;
    }
</style>

<title>Product Category Management | HC Gaming Studio</title>

<div class="page-content">
<div class="container-fluid">

<!-- start page title -->


<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Product Category Managemenbt</h4>



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
                <button class="btn btn-success waves-effect waves-light btn" type="submit" onclick="redirectToPage()">Add New Product Category</button>

                <h4 class="card-title">Product Category Data</h4>


                <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                    <thead>
                    <tr>
                        <th>SI</th>
                        <th>Product Category Name</th>
                        <th>Action</th>

                    </tr>
                    </thead>


                    <tbody>
                        @php($i=1)
                        @foreach($productcategory as $key=> $item)
                    <tr>
                        <td>{{ $key+1 }}</td>
                        <td>

                     {{ $item->name }}

                        </td>

                        <td>
                            <a href="{{ route('edit.dealer.product.category',$item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i>   </a>
                   <a href="{{ route('delete.dealer.product.category',$item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i>   </a>



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
        window.location.href = "{{ route('add.dealer.product.category') }}";
    }
</script>
@endsection
