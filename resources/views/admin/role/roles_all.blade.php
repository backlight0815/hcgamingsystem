@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $userCountsByRole = $userCountsByRole ?? collect();
    $activeUserCountsByRole = $activeUserCountsByRole ?? collect();
    $totalAssignedUsers = (int) $userCountsByRole->sum();
    $activeAssignedUsers = (int) $activeUserCountsByRole->sum();
    $unassignedRoles = $roles->filter(fn ($role) => (int) ($userCountsByRole[$role->id] ?? 0) === 0)->count();

    $roleGroups = [
        'Administration' => [1, 2],
        'Trading Desk' => [201, 202, 501, 750, 760, 770],
        'Dealership Commerce' => [350, 700],
    ];

    $roleGroupFor = function ($roleId) use ($roleGroups) {
        foreach ($roleGroups as $group => $ids) {
            if (in_array((int) $roleId, $ids, true)) {
                return $group;
            }
        }

        return 'Custom Access';
    };

    $roleBadgeFor = function ($group) {
        return match ($group) {
            'Administration' => 'ops-badge-danger',
            'Trading Desk' => 'ops-badge-info',
            'Dealership Commerce' => 'ops-badge-success',
            default => 'ops-badge-muted',
        };
    };
@endphp

<title>Role Management | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Account Management</div>
                <h4 class="mb-0">Role Management</h4>
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

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="ops-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="ops-eyebrow">Permission Architecture</div>
                    <h3>Operational roles, clean ownership, safer access.</h3>
                    <p class="mb-0">
                        Manage the IDs used across admin, dealership, trading, analyst, signal provider,
                        recruiter, leader, and trader workflows.
                    </p>
                </div>
                <div class="ops-action-row align-self-lg-start">
                    <a href="{{ route('add.role') }}" class="btn btn-primary">
                        <i class="ri-user-add-line me-1"></i> Add Role
                    </a>
                </div>
            </div>
        </div>

        <div class="ops-stat-grid">
            <div class="ops-stat">
                <span>Total Roles</span>
                <strong>{{ number_format($roles->count()) }}</strong>
                <small>Available account access profiles</small>
            </div>
            <div class="ops-stat">
                <span>Assigned Users</span>
                <strong>{{ number_format($totalAssignedUsers) }}</strong>
                <small>Users mapped through role_id</small>
            </div>
            <div class="ops-stat">
                <span>Active Assignments</span>
                <strong>{{ number_format($activeAssignedUsers) }}</strong>
                <small>Accounts currently marked active</small>
            </div>
            <div class="ops-stat">
                <span>Unassigned Roles</span>
                <strong>{{ number_format($unassignedRoles) }}</strong>
                <small>Safe to review before cleanup</small>
            </div>
        </div>

        <div class="ops-panel">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Role Registry</h5>
                    <p class="ops-muted mb-0">Review role IDs, assigned account volume, and access area before making changes.</p>
                </div>
                <div class="ops-action-row">
                    <a href="{{ route('all.features') }}" class="btn btn-outline-secondary">
                        <i class="ri-toggle-line me-1"></i> Manage Features
                    </a>
                    <a href="{{ route('add.role') }}" class="btn btn-success">
                        <i class="ri-add-line me-1"></i> New Role
                    </a>
                </div>
            </div>
            <div class="ops-panel-body">
                <div class="table-responsive">
                    <table id="datatable" class="table table-hover dt-responsive nowrap ops-table" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">No.</th>
                                <th style="width: 120px;">Role ID</th>
                                <th>Role</th>
                                <th style="width: 190px;">Access Area</th>
                                <th style="width: 150px;" class="text-center">Users</th>
                                <th style="width: 150px;" class="text-center">Active</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $key => $role)
                                @php
                                    $group = $roleGroupFor($role->id);
                                    $assignedUsers = (int) ($userCountsByRole[$role->id] ?? 0);
                                    $activeUsers = (int) ($activeUserCountsByRole[$role->id] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td><span class="ops-code">{{ $role->id }}</span></td>
                                    <td>
                                        <div class="fw-bold">{{ $role->name }}</div>
                                        <div class="ops-muted small">{{ $role->description ?: 'No description has been added yet.' }}</div>
                                    </td>
                                    <td>
                                        <span class="ops-badge {{ $roleBadgeFor($group) }}">{{ $group }}</span>
                                    </td>
                                    <td class="text-center fw-bold">{{ number_format($assignedUsers) }}</td>
                                    <td class="text-center">
                                        <span class="ops-badge {{ $activeUsers > 0 ? 'ops-badge-success' : 'ops-badge-muted' }}">
                                            {{ number_format($activeUsers) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="ops-actions">
                                            <a href="{{ route('edit.role', $role->id) }}" class="btn btn-outline-primary btn-sm" title="Edit Role">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('delete.role', $role->id) }}"
                                               class="btn btn-outline-danger btn-sm"
                                               title="Delete Role"
                                               onclick="return confirm('Are you sure you want to delete this role?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
