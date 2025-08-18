@extends('admin.admin_master')
@section('admin')

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

</head>
<style>
    #datatable {
        table-layout: fixed;
    }
    #datatable td:nth-child(2) {
        /* Selects the second column (Product Name) in the table */
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 20%;
    }

    @media screen and (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
}
    </style>
<div class="page-content">
<div class="container-fluid">


    <title>Blog Management | HC Gaming Studio</title>

<!-- start page title -->
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">All Blogs</h4>



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

                <h4 class="card-title">All Blogs Data</h4>

                <div class="table-responsive">

                <table class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                    <thead>
                    <tr>
                        <th width="7%">SI</th>
                        <th width="7%">Blog Category</th>
                        <th width="7%">Blog Title</th>
                        <th width="7%">Blog Tags</th>
                        <th width="7%">Blog Image</th>
                        <th width="7%">View Count</th>

                        <th width="7%">Action</th>

                    </tr>
                    </thead>


                    <tbody>
                        @php($i=1)
                        @foreach($blogs as $item)
                    <tr>
                        <td>{{ $i++ }}</td>
                        {{-- <td>{{ $item['category']['blog_category'] }}</td> --}}
                        <td>

                            @if ($item['category'] && !$item['category']->trashed())
                            {{ $item['category']['blog_category'] }}
                        @else
                            {{-- Category Not Available --}}
                        @endif


                        </td>

                        <td>{{ $item->blog_title }}</td>
                        <td>{{ $item->blog_tags }}</td>

                        <td>

                            <a href="{{ asset($item->blog_image) }}" data-lightbox="blog-images">
                                <img src="{{ asset($item->blog_image) }}" style="width: 60px; height: 60px;">
                            </a>
                        </td>
                        <td>{{ $item->page_views }}</td>

                            <td>
{{-- <a href="{{ route('edit.blog',$item->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i>   </a> --}}
{{-- <a href="{{ route('delete.blog',$item->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i>   </a> --}}


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
</div>

@endsection
