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


    #datatable {
        table-layout: fixed;
    }
    #datatable td:nth-child(5) {
        /* Selects the third column (Product Name) in the table */
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 20%;
    }
</style>
<div class="page-content">
    <div class="container-fluid">
        <title>About Page - About Me  | HC Gaming Studio</title>
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">About Me</h4>
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
                        <button class="btn btn-success waves-effect waves-light btn" type="submit"
                            onclick="redirectToPage()">About Page Setup</button>

                        <h4 class="card-title">My Personal Information</h4>

                        <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th width="7%">SI</th>
                                    <th width="12%">Title</th>
                                    <th width="17%" >Short Title</th>
                                    <th width="17%">Short Description</th>
                                    <th width="20%">My Resume</th>
                                    <th width="17%">Image</th>
                                    {{-- <th>Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @if($aboutpage)
                                <tr>
                                    <td>1</td>
                                    <td>{{ $aboutpage->title }}</td>
                                    <td>{{ $aboutpage->short_title }}</td>
                                    <td>{{ $aboutpage->short_description }}</td>
                                    <td>{{ $aboutpage->long_description }}</td>
                                 <td>
                                     @if(!empty($aboutpage->about_image))
                                    <img src="{{ url($aboutpage->about_image) }}" weight="100px" height="100px" alt="About Image">
                                @else
                                    {{-- <img src="{{ url('upload/default.jpg') }}" alt="Default Image"> --}}
                                @endif

                                 </td>
                                </tr>
                                @endif
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
        window.location.href = "{{ route('setup.about.page') }}";
    }
</script>
@endsection
