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
                        Trading controls the public Trading Hub and trading member pages.
                        E-commerce controls the public storefront and shopping actions. Administrative tools and mobile APIs remain separately available.
                    </p>
                </div>
                <div class="ops-action-row align-self-lg-start">
                    @if(auth()->user()?->isGlobalSuperAdmin())
                    <a href="{{ route('admin.trading-modules.index') }}" class="btn btn-outline-light">TradingHub modules</a>
                    @endif
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
                    $isPms = $feature->feature_name === \App\Models\FeatureToggle::MODULE_PROJECT_MANAGEMENT;
                @endphp
                <div class="ops-module">
                    <div class="ops-module-top">
                        <div class="ops-module-copy">
                            <span class="ops-icon {{ $isTrading ? 'ops-icon-teal' : 'ops-icon-success' }}">
                                <i class="{{ $isTrading ? 'ri-line-chart-line' : 'ri-store-2-line' }}"></i>
                            </span>
                            <div>
                                <div class="ops-eyebrow">{{ $isPms ? 'Internal Module' : 'Primary Module' }}</div>
                                <h5 class="mb-2">{{ $label }}</h5>
                                <p class="mb-3">{{ $description }}</p>
                                <span class="ops-badge {{ $feature->enabled ? 'ops-badge-success' : 'ops-badge-muted' }}">
                                    {{ $feature->enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                                <small class="d-block mt-2 ops-muted">Last updated {{ $feature->updated_at?->format('d M Y, H:i') ?? 'not recorded' }} - {{ $feature->updater?->name ?? 'actor not recorded' }}</small>
                            </div>
                        </div>
                        <form action="{{ route('admin.features.update', $feature->id) }}" method="POST" @if($feature->enabled) onsubmit="return confirm('Disable {{ $label }}? Administrators can re-enable it here.')" @endif>
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

        <div class="ops-panel mt-4">
            <div class="ops-panel-header"><div><h5 class="mb-1">Module access and visibility</h5><p class="ops-muted mb-0">Login stays available for enabled modules, including private Project Management.</p></div></div>
            <div class="ops-panel-body">
                <div class="row g-3">
                    @foreach(\App\Support\ModuleAccess::definitions() as $key => $definition)
                        @php
                            $access = $moduleAccess[$key];
                            $policies = $key === 'pms'
                                ? ['approval' => 'Administrator approval', 'open' => 'Open', 'invitation' => 'Invitation only', 'disabled' => 'Disabled']
                                : ['public' => 'Public', 'admin' => 'Admin-created only', 'disabled' => 'Disabled'];
                        @endphp
                        <div class="col-12 col-lg-4">
                            <form method="POST" action="{{ route('admin.features.module_access', $key) }}" class="border rounded p-3 h-100">
                                @csrf
                                <h6>{{ $definition['name'] }}</h6>
                                <p class="ops-muted">{{ \App\Support\ModuleAccess::enabled($key) ? 'Operational' : 'Disabled' }} · <a href="{{ route($definition['login']) }}">Login URL</a></p>
                                <label class="form-label" for="visible-{{ $key }}">Public visibility</label>
                                <select id="visible-{{ $key }}" name="public_visible" class="form-select mb-3">
                                    <option value="1" @selected($access['public_visible'])>Show on public pages</option>
                                    <option value="0" @selected(!$access['public_visible'])>Hide from public pages</option>
                                </select>
                                <label class="form-label" for="login-entry-{{ $key }}">Show login entry</label>
                                <select id="login-entry-{{ $key }}" name="show_login_entry" class="form-select" aria-describedby="login-entry-help-{{ $key }}">
                                    <option value="1" @selected($access['show_login_entry'])>Show in sign-in navigation</option>
                                    <option value="0" @selected(!$access['show_login_entry'])>Hide from sign-in navigation</option>
                                </select>
                                <p id="login-entry-help-{{ $key }}" class="form-text mb-3">The direct login URL works while this module is enabled. Showing its link does not allow registration or grant access.</p>
                                <label class="form-label" for="registration-{{ $key }}">Registration policy</label>
                                <select id="registration-{{ $key }}" name="registration_policy" class="form-select mb-3">
                                    @foreach($policies as $value => $label)
                                        <option value="{{ $value }}" @selected($access['registration_policy'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="d-block ops-muted mb-3">Updated {{ $access['updated_at'] ?? 'not recorded' }} · {{ $updaters[$access['updated_by']] ?? 'actor not recorded' }}</small>
                                <button type="submit" class="btn btn-primary">Save {{ $definition['name'] }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                @if(auth()->user()?->isGlobalSuperAdmin())
                    <form method="POST" action="{{ route('admin.features.module_membership') }}" class="border rounded p-3 mt-3 row g-3 align-items-end">
                        @csrf
                        <div class="col-12"><h6>Explicit cross-module membership</h6><p class="ops-muted mb-0">Use a verified user ID. Project Management access is managed through its own team and invitation workflow.</p></div>
                        <div class="col-md-3"><label class="form-label" for="member-user">User ID</label><input id="member-user" class="form-control" name="user_id" type="number" min="1" required></div>
                        <div class="col-md-3"><label class="form-label" for="member-module">Module</label><select id="member-module" class="form-select" name="module_key"><option value="tradinghub">TradingHub</option><option value="dealership">Dealership</option></select></div>
                        <div class="col-md-3"><label class="form-label" for="member-status">Status</label><select id="member-status" class="form-select" name="status"><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
                        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Save membership</button></div>
                    </form>
                @endif
            </div>
        </div>

        <div class="ops-panel mt-4">
            <div class="ops-panel-header">
                <div>
                    <h5 class="mb-1">Default Homepage</h5>
                    <p class="ops-muted mb-0">Used when TradingHub and E-commerce are both enabled. If only one is on, that module becomes the homepage automatically.</p>
                </div>
            </div>
            <div class="ops-panel-body">
                <form method="POST" action="{{ route('admin.features.homepage') }}" class="d-flex flex-column flex-md-row gap-3 align-items-md-end">
                    @csrf
                    <div class="flex-grow-1">
                        <label for="homepage_experience" class="form-label">Preferred experience</label>
                        <select id="homepage_experience" name="homepage_experience" class="form-select" required>
                            <option value="trading" @selected($experience['preference'] === 'trading')>Trading Hub</option>
                            <option value="ecommerce" @selected($experience['preference'] === 'ecommerce')>E-commerce</option>
                            <option value="combined" @selected($experience['preference'] === 'combined')>Combined Portal</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save homepage</button>
                </form>
                <p class="ops-muted mt-3 mb-0">Current public homepage: <strong>{{ ucfirst(\App\Support\FrontendExperience::homepage()) }}</strong>. With both public modules off, PMS uses a private sign-in landing when enabled.</p>
            </div>
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
