@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $moduleFeatureNames = array_keys($moduleDefinitions ?? []);
    $enabledCount = $features->where('enabled', true)->count();
    $disabledCount = $features->where('enabled', false)->count();
    $moduleCount = $features->whereIn('feature_name', $moduleFeatureNames)->count();
    $customCount = $features->filter(fn ($feature) => ! isset($featureDefinitions[$feature->feature_name]))->count();

    $typeFor = function ($featureName) use ($moduleFeatureNames, $featureDefinitions) {
        if (in_array($featureName, $moduleFeatureNames, true)) {
            return ['label' => 'Business Module', 'class' => 'ops-badge-info'];
        }

        if (isset($featureDefinitions[$featureName])) {
            return ['label' => 'System Toggle', 'class' => 'ops-badge-success'];
        }

        return ['label' => 'Custom Toggle', 'class' => 'ops-badge-warning'];
    };
@endphp

<title>Feature Management | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Company Setting</div>
                <h4 class="mb-0">Feature Management</h4>
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
                    <div class="ops-eyebrow">Feature Registry</div>
                    <h3>Manage the toggles behind modules and workflows.</h3>
                    <p class="mb-0">
                        Keep feature keys organized, visible, and ready for module configuration without editing code.
                    </p>
                </div>
                <div class="ops-action-row align-self-lg-start">
                    <a href="{{ route('admin.features.index') }}" class="btn btn-outline-light">
                        <i class="ri-sliders-line me-1"></i> Configure Toggles
                    </a>
                    <a href="{{ route('add.feature') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i> Add Feature
                    </a>
                </div>
            </div>
        </div>

        <div class="ops-stat-grid">
            <div class="ops-stat">
                <span>Total Features</span>
                <strong>{{ number_format($features->count()) }}</strong>
                <small>Registered feature keys</small>
            </div>
            <div class="ops-stat">
                <span>Business Modules</span>
                <strong>{{ number_format($moduleCount) }}</strong>
                <small>Major sidebar visibility groups</small>
            </div>
            <div class="ops-stat">
                <span>Enabled</span>
                <strong>{{ number_format($enabledCount) }}</strong>
                <small>Currently active features</small>
            </div>
            <div class="ops-stat">
                <span>Custom Toggles</span>
                <strong>{{ number_format($customCount) }}</strong>
                <small>Created outside defaults</small>
            </div>
        </div>

        <div class="ops-panel">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Feature Registry</h5>
                    <p class="ops-muted mb-0">Edit names, review status, and jump to the live toggle configuration page.</p>
                </div>
                <div class="ops-action-row">
                    <span class="ops-badge ops-badge-success">{{ number_format($enabledCount) }} Enabled</span>
                    <span class="ops-badge ops-badge-muted">{{ number_format($disabledCount) }} Disabled</span>
                    <a href="{{ route('add.feature') }}" class="btn btn-success">
                        <i class="ri-add-line me-1"></i> New Feature
                    </a>
                </div>
            </div>
            <div class="ops-panel-body">
                <div class="table-responsive">
                    <table id="datatable" class="table table-hover dt-responsive nowrap ops-table" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">No.</th>
                                <th>Feature</th>
                                <th style="width: 180px;">Type</th>
                                <th style="width: 140px;" class="text-center">Status</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($features as $key => $feature)
                                @php
                                    $definition = $featureDefinitions[$feature->feature_name] ?? [];
                                    $label = $definition['label'] ?? ucwords(str_replace('_', ' ', $feature->feature_name));
                                    $description = $definition['description'] ?? 'Custom feature toggle.';
                                    $type = $typeFor($feature->feature_name);
                                @endphp
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $label }}</div>
                                        <div class="ops-muted small mb-1">{{ $description }}</div>
                                        <span class="ops-code">{{ $feature->feature_name }}</span>
                                    </td>
                                    <td>
                                        <span class="ops-badge {{ $type['class'] }}">{{ $type['label'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="ops-badge {{ $feature->enabled ? 'ops-badge-success' : 'ops-badge-muted' }}">
                                            {{ $feature->enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="ops-actions">
                                            <a href="{{ route('edit.feature', $feature->id) }}" class="btn btn-outline-primary btn-sm" title="Edit Feature">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('delete.feature', $feature->id) }}"
                                               class="btn btn-outline-danger btn-sm"
                                               title="Delete Feature"
                                               onclick="return confirm('Are you sure you want to delete this feature?')">
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
