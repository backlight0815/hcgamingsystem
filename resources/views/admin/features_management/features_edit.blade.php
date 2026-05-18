@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $moduleFeatureNames = array_keys($moduleDefinitions ?? []);
    $definition = $featureDefinitions[$feature->feature_name] ?? [];
    $label = $definition['label'] ?? ucwords(str_replace('_', ' ', $feature->feature_name));
    $description = $definition['description'] ?? 'Custom feature toggle.';
    $isModule = in_array($feature->feature_name, $moduleFeatureNames, true);
@endphp

<title>Feature Management - Edit | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Company Setting</div>
                <h4 class="mb-0">Edit Feature</h4>
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
                    <span class="ops-icon {{ $isModule ? 'ops-icon-teal' : 'ops-icon-success' }}">
                        <i class="{{ $isModule ? 'ri-layout-grid-line' : 'ri-toggle-line' }}"></i>
                    </span>
                    <div>
                        <div class="ops-eyebrow">{{ $isModule ? 'Business Module' : 'Feature Toggle' }}</div>
                        <h3>{{ $label }}</h3>
                        <p class="mb-0">{{ $description }}</p>
                    </div>
                </div>
                <div class="text-md-end">
                    <span class="ops-badge {{ $feature->enabled ? 'ops-badge-success' : 'ops-badge-muted' }}">
                        {{ $feature->enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="ops-panel ops-form-shell">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Feature Details</h5>
                    <p class="ops-muted mb-0">Update the feature key and status used by sidebar and workflow checks.</p>
                </div>
                <a href="{{ route('all.features') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
            <div class="ops-panel-body">
                <form method="POST" action="{{ route('update.feature', $feature->id) }}">
                    @csrf

                    <div class="ops-form-grid">
                        <div class="ops-field">
                            <label for="feature_name">Feature Key</label>
                            <div>
                                <input id="feature_name" name="feature_name" class="form-control" type="text" value="{{ old('feature_name', $feature->feature_name) }}" required>
                                <div class="ops-help">Changing a key can affect code references. Only rename it when the system reference is also updated.</div>
                                @error('feature_name')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="enabled">Status</label>
                            <div>
                                <div class="ops-switch">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" id="enabled" {{ old('enabled', $feature->enabled) ? 'checked' : '' }}>
                                    <label for="enabled" class="mb-0">Feature is enabled</label>
                                </div>
                                <div class="ops-help">Disabled features are hidden or blocked wherever the toggle is checked.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <a href="{{ route('all.features') }}" class="btn btn-light">Cancel</a>
                        <a href="{{ route('admin.features.index') }}" class="btn btn-outline-secondary">
                            Configure Toggles
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-3-line me-1"></i> Update Feature
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
