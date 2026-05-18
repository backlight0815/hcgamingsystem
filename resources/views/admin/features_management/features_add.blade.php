@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<title>Feature Management - Add | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Company Setting</div>
                <h4 class="mb-0">Add Feature</h4>
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
                <span class="ops-icon ops-icon-success">
                    <i class="ri-toggle-line"></i>
                </span>
                <div>
                    <div class="ops-eyebrow">New Toggle</div>
                    <h3>Create a feature key</h3>
                    <p class="mb-0">Use stable keys because menu visibility, dashboards, and middleware checks may depend on them.</p>
                </div>
            </div>
        </div>

        <div class="ops-panel ops-form-shell">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Feature Details</h5>
                    <p class="ops-muted mb-0">Add a key now, then manage visibility from the feature configuration page.</p>
                </div>
                <a href="{{ route('all.features') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
            <div class="ops-panel-body">
                <form method="POST" id="submitFeatureForm" action="{{ route('store.feature') }}">
                    @csrf

                    <div class="ops-form-grid">
                        <div class="ops-field">
                            <label for="feature_name">Feature Key</label>
                            <div>
                                <input id="feature_name" name="feature_name" class="form-control" type="text" value="{{ old('feature_name') }}" placeholder="example_feature_name" required>
                                <div class="ops-help">Recommended format: lowercase words separated by underscores.</div>
                                @error('feature_name')
                                    <span class="text-danger d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="ops-field">
                            <label for="enabled">Default Status</label>
                            <div class="ops-switch">
                                <input type="hidden" name="enabled" value="0">
                                <input type="checkbox" name="enabled" value="1" id="enabled" {{ old('enabled', '1') ? 'checked' : '' }}>
                                <label for="enabled" class="mb-0">Enable immediately</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <a href="{{ route('all.features') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitButton">
                            <i class="ri-save-3-line me-1"></i> Create Feature
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('submitFeatureForm')?.addEventListener('submit', function () {
        const button = document.getElementById('submitButton');
        if (button) {
            button.disabled = true;
            button.innerHTML = 'Saving...';
        }
    });
</script>

@endsection
