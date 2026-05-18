@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<title>Role Management - Edit | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Account Management</div>
                <h4 class="mb-0">Edit Role</h4>
            </div>
            <ol class="breadcrumb">
                @foreach ($breadcrumbData ?? [] as $breadcrumb)
                    <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                        @if ($loop->last)
                            {{ $breadcrumb['label'] }}
                        @else
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="ops-hero ops-form-shell">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <span class="ops-icon ops-icon-warning">
                        <i class="ri-user-settings-line"></i>
                    </span>
                    <div>
                        <div class="ops-eyebrow">Access Profile</div>
                        <h3>{{ $role->name }}</h3>
                        <p class="mb-0">Update the display name and description used by account management workflows.</p>
                    </div>
                </div>
                <div class="text-md-end">
                    <span class="ops-badge ops-badge-info">Role ID {{ $role->id }}</span>
                    <div class="ops-muted small mt-2">{{ number_format($assignedUsers ?? 0) }} assigned users</div>
                </div>
            </div>
        </div>

        <div class="ops-panel ops-form-shell">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Role Details</h5>
                    <p class="ops-muted mb-0">Role IDs are kept stable to protect existing account routing and permissions.</p>
                </div>
                <a href="{{ route('all.roles') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
            <div class="ops-panel-body">
                <form method="POST" action="{{ route('update.role', $role->id) }}">
                    @csrf

                    <div class="ops-form-grid">
                        <div class="ops-field">
                            <label for="role_id">Role ID</label>
                            <div>
                                <input id="role_id" class="form-control" type="number" value="{{ $role->id }}" readonly>
                                <div class="ops-help">Create a new role if a different ID is required.</div>
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="role_name">Role Name</label>
                            <div>
                                <input id="role_name" name="name" class="form-control" type="text" value="{{ old('name', $role->name) }}" required>
                                @error('name')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="role_description">Description</label>
                            <div>
                                <textarea id="role_description" name="description" class="form-control" rows="5">{{ old('description', $role->description) }}</textarea>
                                @error('description')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <a href="{{ route('all.roles') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-3-line me-1"></i> Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
