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
            <h4 class="mb-sm-0">All Event Listing</h4>



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
                <button class="btn btn-success waves-effect waves-light btn" type="submit" onclick="redirectToPage()">Add Events</button>


            </div>
        </div>
    </div>
</div>
<table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
    <thead>
        <tr>
            <th width="7%">SI</th>
            <th width="12%">Image</th>
            <th width="20%">Title</th>
            <th width="15%">Type</th>
            <th width="15%">Start Time</th>
            <th width="15%">End Time</th>
            <th width="15%">Action</th>
        </tr>
    </thead>
    <tbody>
        @php($i = 1)
        @foreach($events as $event)
            <tr>
                <td width="7%">{{ $i++ }}</td>
                <td width="12%">
                    @if($event->event_image)
                        <a href="{{ asset($event->event_image) }}" data-lightbox="event-images">
                            <img src="{{ asset($event->event_image) }}" style="width: 60px; height: 60px;">
                        </a>
                    @else
                        <img src="{{ asset('default_image_path.jpg') }}" style="width: 60px; height: 60px;"> <!-- Default image if none -->
                    @endif
                </td>
                <td width="20%">
                    <div class="long">{{ $event->title }}</div>
                </td>
                <td width="15%">
                    {{ ucfirst($event->type) }} <!-- Capitalize first letter -->
                </td>
                <td width="15%">{{ \Carbon\Carbon::parse($event->start_time)->format('Y-m-d H:i') }}</td>
                <td width="15%">{{ \Carbon\Carbon::parse($event->end_time)->format('Y-m-d H:i') }}</td>
                <td width="15%">
                    <a href="{{ route('edit.events', $event->id) }}" class="btn btn-info sm" title="Edit Data"><i class="fas fa-edit"></i></a>
                    <a href="{{ route('delete.events', $event->id) }}" class="btn btn-danger sm" title="Delete Data" id="delete"><i class="fas fa-trash-alt"></i></a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>


    </div>
</div>

<script>
    function redirectToPage() {
        window.location.href = "{{ route('add.events') }}";
    }
</script>

@endsection
