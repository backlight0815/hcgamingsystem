@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Role Management - Edit | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Edit Role Page</h4><br><br>

                        <form method="POST" action="{{ route('update.role', $role->id) }}">
                            @csrf

                            {{-- Role ID --}}
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Role ID</label>
                                <div class="col-sm-10">
                                    <input name="id" class="form-control" type="number" value="{{ $role->id }}">
                                    @error('id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Role Name --}}
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Role Name</label>
                                <div class="col-sm-10">
                                    <input name="name" class="form-control" type="text" value="{{ $role->name }}">
                                    @error('name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Description</label>
                                <div class="col-sm-10">
                                    <textarea name="description" class="form-control">{{ $role->description }}</textarea>
                                    @error('description')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <input type="submit" class="btn btn-info waves-effect waves-light" value="Update Role Data">
                        </form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>

@endsection
