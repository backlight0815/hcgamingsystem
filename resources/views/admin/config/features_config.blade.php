@extends('admin.admin_master')
@section('admin')

@include('admin.config.partials.management_styles')

@php
    $moduleFeatureNames = array_keys($moduleDefinitions ?? []);
    $moduleFeatures = $features->whereIn('feature_name', $moduleFeatureNames);
    $generalFeatures = $features->whereNotIn('feature_name', $moduleFeatureNames);
    $enabledCount = $features->where('enabled', true)->count();
    $disabledCount = $features->where('enabled', false)->count();
    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<title>Feature Configuration | HC Gaming Studio</title>

<div class="page-content ops-admin">
    <div class="container-fluid">
        <div class="ops-titlebar">
            <div>
                <div class="ops-eyebrow">Company Setting</div>
                <h4 class="mb-0">Module & Feature Configuration</h4>
            </div>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                <li class="breadcrumb-item active">Feature Configuration</li>
            </ol>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($viewErrors->any())
            <div class="alert alert-danger">{{ $viewErrors->first() }}</div>
        @endif

        <div class="ops-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="ops-eyebrow">Business Visibility Control</div>
                    <h3>Switch modules without exposing unrelated tools.</h3>
                    <p class="mb-0">
                        Trading controls trading dashboards, journals, signals, market resources, and trader tools.
                        Dealership E-Commerce controls storefront, products, stock, orders, wallet, commission, and sales tools.
                    </p>
                </div>
                <div class="ops-action-row align-self-lg-start">
                    <a href="{{ route('all.features') }}" class="btn btn-outline-light">
                        <i class="ri-list-settings-line me-1"></i> Manage Definitions
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                        <i class="ri-add-line me-1"></i> Add Feature
                    </button>
                </div>
            </div>
        </div>

        <div class="ops-stat-grid">
            <div class="ops-stat">
                <span>Business Modules</span>
                <strong>{{ number_format($moduleFeatures->count()) }}</strong>
                <small>Major sidebar and dashboard groups</small>
            </div>
            <div class="ops-stat">
                <span>Advanced Toggles</span>
                <strong>{{ number_format($generalFeatures->count()) }}</strong>
                <small>Individual workflow capabilities</small>
            </div>
            <div class="ops-stat">
                <span>Enabled</span>
                <strong>{{ number_format($enabledCount) }}</strong>
                <small>Available to eligible users</small>
            </div>
            <div class="ops-stat">
                <span>Disabled</span>
                <strong>{{ number_format($disabledCount) }}</strong>
                <small>Hidden or locked from workflows</small>
            </div>
        </div>

        <div class="ops-module-grid">
            @foreach($moduleFeatures as $feature)
                @php
                    $definition = $featureDefinitions[$feature->feature_name] ?? [];
                    $label = $definition['label'] ?? ucwords(str_replace('_', ' ', $feature->feature_name));
                    $description = $definition['description'] ?? 'Controls this feature area.';
                    $isTrading = $feature->feature_name === \App\Models\FeatureToggle::MODULE_TRADING;
                @endphp
                <div class="ops-module">
                    <div class="ops-module-top">
                        <div class="ops-module-copy">
                            <span class="ops-icon {{ $isTrading ? 'ops-icon-teal' : 'ops-icon-success' }}">
                                <i class="{{ $isTrading ? 'ri-line-chart-line' : 'ri-store-2-line' }}"></i>
                            </span>
                            <div>
                                <div class="ops-eyebrow">Primary Module</div>
                                <h5 class="mb-2">{{ $label }}</h5>
                                <p class="mb-3">{{ $description }}</p>
                                <span class="ops-badge {{ $feature->enabled ? 'ops-badge-success' : 'ops-badge-muted' }}">
                                    {{ $feature->enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                        <form action="{{ route('admin.features.update', $feature->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $feature->enabled ? 0 : 1 }}">
                            <button type="submit" class="btn btn-{{ $feature->enabled ? 'outline-danger' : 'success' }}">
                                {{ $feature->enabled ? 'Turn Off' : 'Turn On' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ops-panel">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Advanced Feature Toggles</h5>
                    <p class="ops-muted mb-0">Fine tune individual capabilities without changing the active business module.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                    <i class="ri-add-line me-1"></i> New Toggle
                </button>
            </div>
            <div class="ops-panel-body">
                <div class="table-responsive">
                    <table class="table table-hover ops-table">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Description</th>
                                <th style="width: 120px;" class="text-center">Status</th>
                                <th style="width: 140px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($generalFeatures as $feature)
                                @php
                                    $definition = $featureDefinitions[$feature->feature_name] ?? [];
                                    $label = $definition['label'] ?? ucwords(str_replace('_', ' ', $feature->feature_name));
                                    $description = $definition['description'] ?? 'Custom feature toggle.';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $label }}</div>
                                        <span class="ops-code">{{ $feature->feature_name }}</span>
                                    </td>
                                    <td>{{ $description }}</td>
                                    <td class="text-center">
                                        <span class="ops-badge {{ $feature->enabled ? 'ops-badge-success' : 'ops-badge-muted' }}">
                                            {{ $feature->enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('admin.features.update', $feature->id) }}" method="POST" class="d-inline-block">
                                            @csrf
                                            <input type="hidden" name="enabled" value="{{ $feature->enabled ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-sm btn-{{ $feature->enabled ? 'outline-danger' : 'success' }}">
                                                {{ $feature->enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="ops-empty">No advanced features configured yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addFeatureModal" tabindex="-1" aria-labelledby="addFeatureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('store.feature') }}">
            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
            <input type="hidden" name="enabled" value="1">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFeatureModalLabel">Add Feature Toggle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="feature_name" class="form-label">Feature Key</label>
                        <input type="text" class="form-control" id="feature_name" name="feature_name" placeholder="example_feature_name" required>
                        <div class="form-text">Use a stable key, usually lowercase words separated by underscores.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Feature</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
