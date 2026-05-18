@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<title>Role Management - Add | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Account Management</div>
                <h4 class="mb-0">Add Role</h4>
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
            <div class="d-flex align-items-start gap-3">
                <span class="ops-icon ops-icon-teal">
                    <i class="ri-shield-user-line"></i>
                </span>
                <div>
                    <div class="ops-eyebrow">New Access Profile</div>
                    <h3>Create a system role</h3>
                    <p class="mb-0">Use stable role IDs because account routing, dashboards, and sidebar access depend on them.</p>
                </div>
            </div>
        </div>

        <div class="ops-panel ops-form-shell">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Role Details</h5>
                    <p class="ops-muted mb-0">Add a concise role name and description that administrators can understand quickly.</p>
                </div>
                <a href="{{ route('all.roles') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
            <div class="ops-panel-body">
                <form method="POST" id="submitRoleForm" action="{{ route('store.role') }}">
                    @csrf

                    <div class="ops-form-grid">
                        <div class="ops-field">
                            <label for="role_id">Role ID</label>
                            <div>
                                <input id="role_id" name="id" class="form-control" type="number" value="{{ old('id') }}" required>
                                <div class="ops-help">Example: 750 for traders, 760 for leaders, 770 for recruiters.</div>
                                @error('id')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="role_name">Role Name</label>
                            <div>
                                <input id="role_name" name="name" class="form-control" type="text" value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="role_description">Description</label>
                            <div>
                                <textarea id="role_description" name="description" class="form-control" rows="5" placeholder="Describe what this role can access.">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <a href="{{ route('all.roles') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitButton">
                            <i class="ri-save-3-line me-1"></i> Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('submitRoleForm')?.addEventListener('submit', function () {
        const button = document.getElementById('submitButton');
        if (button) {
            button.disabled = true;
            button.innerHTML = 'Saving...';
        }
    });
</script>

@endsection
