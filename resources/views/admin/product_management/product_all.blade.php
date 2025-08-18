@extends('admin.admin_master')
@section('admin')
<style>
    .btn {
        float: right;
        padding: 10px;
    }

    #datatable {
        table-layout: fixed;
    }


.long {
    overflow-x:hidden;
    width:150px;
}

.long:hover {
   position:absolute;
   z-index:10;
   top:0;
   left:0;
   width:200px;
   background-color:#c0c0c0;
   border:1px solid #000000;
   overflow-x:visible
}


</style>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
</head>
<title>Product Management | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Product Management</h4>
                    <button class="btn btn-success waves-effect waves-light" type="submit" onclick="redirectToPage()">Add New Product</button>

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
                        <script src="assets/libs/pdfmake/build/pdfmake.min.js"></script>

                        <h4 class="card-title mb-2">Manage Product</h4>

                        <div class="row text-center">
                            <div class="col-12 col-sm-6 col-md-4 border border-dark pt-3 mb-3">

                            {{-- <div class="col-4 border border-dark pt-3"> --}}
                                <h5 class="mb-0">{{ $product_index }}</h5>
                                <p class="text-muted text-truncate">Number of Product</p>
                            </div>
                        </div>

                        {{-- <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; "> --}}

                            <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                <thead>
                                <tr>
                                    <th width="7%">SI</th>
                                    <th width="12%">Image</th>
                                    <th width="20%">Name</th>
                                    <th width="15%">Category</th>
                                    <th width="10%">Stock</th>
                                    <th width="15%">Price (RM)</th>
                                    <!--<th width="15%">Customer Price (RM)</th>-->

                                    <th width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($i=1)
                                @foreach($product as $key=>$item)
                                <tr>
                                    <td width="7%">{{ $key+1 }}</td>
                                    <td width="12%">

                                        <a href="{{ asset($item->product_image) }}" data-lightbox="product-images">
                                            <img src="{{ asset($item->product_image) }}" style="width: 60px; height: 60px;">
                                        </a>
                                            <td width="25%">
                                    <div class="long">{{ $item->product_name }}</div>
                                    </td>34
                                    <td width="15%">
                                        <!--<div class="longcategory">-->
                                        @if ($item['productcategory'] && !$item['productcategory']->trashed())
                                        {{ $item['productcategory']['product_category'] }}
                                        <!--</div>-->
                                    @else
                                        {{-- Category Not Available --}}
                                    @endif

                                </td>
                                    <td width="10%">{{ $item->product_stock }}</td>
                                    <td width="15%">RM {{ $item->product_price }}</td>
                                    <!--<td width="15%">RM {{ $item->customer_price }}</td>-->

                                    <td width="10%">
                                        <a href="{{ route('edit.product', $item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i></a>
                                        <a href="{{ route('delete.product', $item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i></a>
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
</div> <!-- container-fluid -->
<script>
    function redirectToPage() {
        window.location.href = "{{ route('add.product') }}";
    }
</script>
@endsection

