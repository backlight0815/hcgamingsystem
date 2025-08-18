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
        <title>Home Slide Setup | HC Gaming Studio</title>
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Home Slide</h4>
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
                            onclick="redirectToPage()">Home Slide Setup</button>

                        <h4 class="card-title">Home Slide Information</h4>

                        <table id="datatable" class="table table-bordered dt-responsive nowrap"
                            style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>SI</th>
                                    <th>Title</th>
                                    <th>Short Title</th>
                                    <th>Youtube Link</th>
                                    <th>Image</th>
                                    {{-- <th>Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @if($homeslide)
                                <tr>
                                    <td>1</td>
                                    <td>{{ $homeslide->title }}</td>
                                    <td>{{ $homeslide->short_title }}</td>
                                    <td>{{ $homeslide->video_url }}</td>
                                    <td>
                                        @if(!empty($homeslide->home_slide))
                                        <img src="{{ url($homeslide->home_slide) }}" weight="100px" height="100px" alt="About Image">
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
        window.location.href = "{{ route('setup.home.slide') }}";
    }
</script>
@endsection
