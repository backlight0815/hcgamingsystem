@extends('admin.admin_master')
@section('admin')

<title>Edit Notification | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit Notification</h4>
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.notifications.update', $notification->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $notification->title) }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <input type="text" name="type" id="type" class="form-control" value="{{ old('type', $notification->type) }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea name="message" id="message" rows="5" class="form-control" required>{{ old('message', $notification->message) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="action_url" class="form-label">Action URL</label>
                                <input type="url" name="action_url" id="action_url" class="form-control" value="{{ old('action_url', $notification->action_url) }}" placeholder="https://...">
                            </div>
                            <div class="mb-4">
                                @php($selectedRoles = old('target_roles', $notification->target_roles ?? []))
                                <label class="form-label">Target Roles</label>
                                <div class="row">
                                    @foreach($roleOptions as $role)
                                        <div class="col-md-4 mb-2">
                                            <label class="form-check">
                                                <input type="checkbox" name="target_roles[]" value="{{ $role->id }}" class="form-check-input" {{ in_array($role->id, $selectedRoles) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ $role->id }} - {{ $role->name }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-text">Leave all unchecked to publish to every logged-in user.</div>
                            </div>
                            <button type="submit" class="btn btn-info">Update Notification</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
