@extends('admin.admin_master')
@section('admin')

<title>Marketing Resources | HC Gaming Studio</title>

<style>
    .resource-shell { color: #1f2937; }
    .resource-header {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }
    .resource-header h4 { margin: 0; color: #0f172a; font-weight: 700; }
    .resource-header p { margin: 6px 0 0; color: #64748b; }
    .resource-metric { min-width: 150px; border-left: 1px solid #e5e7eb; padding-left: 20px; text-align: right; }
    .resource-metric span { display: block; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    .resource-metric strong { display: block; margin-top: 4px; color: #0f172a; font-size: 26px; }
    .security-strip {
        margin: 18px 0;
        border: 1px solid #bfd7ea;
        background: #eef7ff;
        color: #075985;
        border-radius: 8px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .resource-list { display: grid; gap: 14px; }
    .resource-item {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
        overflow: hidden;
    }
    .resource-main {
        padding: 18px 20px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
    }
    .resource-title-row { display: flex; align-items: flex-start; gap: 12px; }
    .resource-icon {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        background: #0f172a;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex: 0 0 auto;
    }
    .resource-title { margin: 0; color: #0f172a; font-weight: 700; }
    .resource-meta { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
    .meta-pill {
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #475569;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
    }
    .resource-description { margin: 0; color: #64748b; max-width: 900px; }
    .resource-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
    .access-panel {
        display: none;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        padding: 16px 20px;
    }
    .access-panel.is-open { display: block; }
    .access-form {
        display: grid;
        grid-template-columns: minmax(220px, 360px) auto auto;
        gap: 10px;
        align-items: end;
    }
    .access-copy { margin: 0 0 12px; color: #475569; font-weight: 600; }
    .empty-state {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
    }
    .empty-state h5 { color: #0f172a; font-weight: 700; margin-bottom: 8px; }
    @media (max-width: 991px) {
        .resource-header { align-items: flex-start; flex-direction: column; }
        .resource-main { grid-template-columns: 1fr; }
        .resource-actions { justify-content: flex-start; }
        .resource-metric { border-left: 0; border-top: 1px solid #e5e7eb; padding-left: 0; padding-top: 14px; text-align: left; width: 100%; }
    }
    @media (max-width: 640px) {
        .access-form { grid-template-columns: 1fr; }
    }
</style>

<div class="page-content resource-shell">
    <div class="container-fluid">
        <div class="resource-header">
            <div>
                <h4>Marketing Resources</h4>
                <p>View and download the latest approved marketing materials with password verification.</p>
            </div>
            <div class="resource-metric">
                <span>Available Files</span>
                <strong>{{ $resources->total() }}</strong>
            </div>
        </div>

        <div class="breadcrumb my-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="security-strip">
            <i class="fas fa-lock"></i>
            <span>Your login password is required before opening or downloading a marketing resource.</span>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('marketing.resources.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-10">
                        <label for="search" class="form-label">Search Resources</label>
                        <input type="text" name="search" id="search" class="form-control" value="{{ $search }}" placeholder="Title, description, or filename">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="resource-list">
            @forelse($resources as $resource)
                <div class="resource-item">
                    <div class="resource-main">
                        <div>
                            <div class="resource-title-row">
                                <span class="resource-icon">{{ $resource->file_extension }}</span>
                                <div>
                                    <h5 class="resource-title">{{ $resource->title }}</h5>
                                    <div class="resource-meta">
                                        <span class="meta-pill">{{ $resource->original_filename }}</span>
                                        <span class="meta-pill">{{ $resource->file_size_label }}</span>
                                        <span class="meta-pill">Uploaded {{ $resource->created_at?->format('M d, Y') }}</span>
                                        <span class="meta-pill">Password required</span>
                                    </div>
                                </div>
                            </div>
                            <p class="resource-description">
                                {{ $resource->description ? \Illuminate\Support\Str::limit($resource->description, 180) : 'No description added for this resource.' }}
                            </p>
                        </div>

                        <div class="resource-actions">
                            <button type="button"
                                    class="btn btn-outline-primary access-toggle"
                                    data-panel="access-panel-{{ $resource->id }}"
                                    data-action="{{ route('marketing.resources.view', $resource->id) }}"
                                    data-label="View Resource"
                                    data-button-class="btn btn-primary"
                                    data-copy="Confirm your password to open this resource.">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button type="button"
                                    class="btn btn-outline-success access-toggle"
                                    data-panel="access-panel-{{ $resource->id }}"
                                    data-action="{{ route('marketing.resources.download', $resource->id) }}"
                                    data-label="Download Resource"
                                    data-button-class="btn btn-success"
                                    data-copy="Confirm your password to download this resource.">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>

                    <div class="access-panel" id="access-panel-{{ $resource->id }}">
                        <p class="access-copy">Confirm your password to continue.</p>
                        <form method="POST" class="access-form">
                            @csrf
                            <div>
                                <label class="form-label" for="password-{{ $resource->id }}">Login Password</label>
                                <input type="password"
                                       name="password"
                                       id="password-{{ $resource->id }}"
                                       class="form-control"
                                       autocomplete="current-password"
                                       required>
                            </div>
                            <button type="submit" class="btn btn-primary access-submit">Continue</button>
                            <button type="button" class="btn btn-light access-cancel" data-panel="access-panel-{{ $resource->id }}">Cancel</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <h5>No active marketing resources</h5>
                    <p class="mb-0">Approved materials will appear here after administration uploads them.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $resources->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function closePanels() {
        document.querySelectorAll('.access-panel').forEach(function (panel) {
            panel.classList.remove('is-open');
        });
    }

    document.querySelectorAll('.access-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(this.dataset.panel);
            const form = panel.querySelector('form');
            const submit = panel.querySelector('.access-submit');
            const copy = panel.querySelector('.access-copy');
            const password = panel.querySelector('input[name="password"]');

            closePanels();

            form.action = this.dataset.action;
            submit.textContent = this.dataset.label;
            submit.className = this.dataset.buttonClass + ' access-submit';
            copy.textContent = this.dataset.copy;
            password.value = '';
            panel.classList.add('is-open');

            setTimeout(function () {
                password.focus();
            }, 100);
        });
    });

    document.querySelectorAll('.access-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(this.dataset.panel).classList.remove('is-open');
        });
    });
});
</script>

@endsection
