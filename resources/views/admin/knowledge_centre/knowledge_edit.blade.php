@extends('admin.admin_master')
@section('admin')

@php
    $errors = $errors ?? session()->get('errors', new \Illuminate\Support\ViewErrorBag);
@endphp

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Knowledge Centre - Edit | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Edit Knowledge Centre</h4>

@if(auth()->user()->isTradingLeader())
    <div class="alert alert-info">
        Editing leader knowledge sends it back for administration approval before clients can view it.
    </div>
@endif

<form action="{{ route('knowledge.centre.update', $knowledge->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="id" value="{{ $knowledge->id }}">

    {{-- Title --}}
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label">Title <span class="text-danger">*</span></label>
        <div class="col-sm-10">
            <input type="text" name="title" class="form-control" value="{{ old('title', $knowledge->title) }}" required>
            @error('title') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Description --}}
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label">Description</label>
        <div class="col-sm-10">
            <textarea name="description" class="form-control" rows="4">{{ old('description', $knowledge->description) }}</textarea>
            @error('description') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- PDF File --}}
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label">PDF File</label>
        <div class="col-sm-10">
            <input type="file" name="pdf" class="form-control" accept="application/pdf">

            @if($knowledge->file_path)
                <small>Current File: <a href="{{ asset('storage/' . $knowledge->file_path) }}" target="_blank">View PDF</a></small>
            @endif

            @error('pdf') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Community --}}
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label">Community</label>
        <div class="col-sm-10">
            <select name="community_id" class="form-control">
                <option value="">All Communities</option>
                @foreach($communities as $community)
                    <option value="{{ $community->id }}" {{ old('community_id', $knowledge->community_id) == $community->id ? 'selected' : '' }}>
                        {{ $community->name }}
                    </option>
                @endforeach
            </select>
            @error('community_id') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
    </div>

    

    {{-- Submit --}}
    <div class="row mb-3">
        <div class="col-sm-2"></div>
        <div class="col-sm-10">
            <button type="submit" class="btn btn-info waves-effect waves-light">
                Update Knowledge
            </button>
            <a href="{{ route('knowledge.centre.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

</form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
