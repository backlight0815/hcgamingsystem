@extends('admin.admin_master')
@section('admin')

<title>Community Documents | HC Gaming Studio</title>

<style>
    .docs-page {
        color: #1f2937;
    }

    .docs-header {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .docs-header h4 {
        margin: 0;
        color: #0f172a;
        font-weight: 700;
    }

    .docs-header p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .docs-security {
        border: 1px solid #bfd7ea;
        background: #eef7ff;
        color: #075985;
        border-radius: 8px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 18px 0;
    }

    .docs-stat {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        background: #ffffff;
        padding: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .docs-stat span {
        display: block;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .docs-stat strong {
        display: block;
        margin-top: 6px;
        color: #0f172a;
        font-size: 28px;
    }

    .docs-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .docs-panel-header {
        padding: 18px 20px;
        border-bottom: 1px solid #e5e7eb;
    }

    .docs-panel-header h5 {
        margin: 0;
        color: #0f172a;
        font-weight: 700;
    }

    .docs-panel-header p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .docs-panel-body {
        padding: 20px;
    }

    .docs-item {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
    }

    .docs-item + .docs-item {
        margin-top: 14px;
    }

    .docs-item-main {
        padding: 18px 20px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
    }

    .docs-title-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .docs-file-icon {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        background: #111827;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        flex: 0 0 auto;
    }

    .docs-title {
        margin: 0;
        color: #0f172a;
        font-weight: 700;
    }

    .docs-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 8px 0;
    }

    .docs-pill {
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #475569;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
    }

    .docs-description {
        margin: 0;
        color: #64748b;
        max-width: 900px;
    }

    .docs-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .docs-access-panel {
        display: none;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        padding: 16px 20px;
    }

    .docs-access-panel.is-open {
        display: block;
    }

    .docs-access-form {
        display: grid;
        grid-template-columns: minmax(220px, 360px) auto auto;
        gap: 10px;
        align-items: end;
    }

    .docs-access-copy {
        margin: 0 0 12px;
        color: #475569;
        font-weight: 600;
    }

    .docs-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 44px 20px;
        text-align: center;
        color: #64748b;
    }

    .docs-empty h5 {
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 8px;
    }

    @media (max-width: 991px) {
        .docs-header,
        .docs-item-main {
            align-items: flex-start;
            grid-template-columns: 1fr;
        }

        .docs-header {
            flex-direction: column;
        }

        .docs-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .docs-access-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content docs-page">
    <div class="container-fluid">
        <div class="docs-header">
            <div>
                <h4>Community Documents</h4>
                <p>Upload and retain community documentation for founder and partner review.</p>
            </div>
            <a href="{{ route('communities.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Communities
            </a>
        </div>

        <div class="breadcrumb my-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="docs-security">
            <i class="fas fa-lock"></i>
            <span>Founder and partner users must confirm their login password before uploading, viewing, downloading, or deleting documents.</span>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="docs-stat">
                    <span>Total Documents</span>
                    <strong>{{ $totalDocuments }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="docs-stat">
                    <span>Communities With Files</span>
                    <strong>{{ $totalCommunitiesWithDocuments }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="docs-stat">
                    <span>Verified Downloads</span>
                    <strong>{{ $totalDownloads }}</strong>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 mb-4">
                <div class="docs-panel">
                    <div class="docs-panel-header">
                        <h5>Upload Documentation</h5>
                        <p>Attach a document to the correct community with a clear description.</p>
                    </div>
                    <div class="docs-panel-body">
                        <form method="POST" action="{{ route('communities.documents.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label for="community_id" class="form-label fw-semibold">Community</label>
                                <select name="community_id" id="community_id" class="form-select" required>
                                    <option value="">Select community</option>
                                    @foreach($communities as $community)
                                        <option value="{{ $community->id }}" {{ (string) old('community_id', request('community_id')) === (string) $community->id ? 'selected' : '' }}>
                                            {{ $community->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="title" class="form-label fw-semibold">Document Title</label>
                                <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" maxlength="255" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label fw-semibold">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="5" maxlength="3000" placeholder="Summarise the purpose, version, or review notes for this document.">{{ old('description') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label for="document" class="form-label fw-semibold">Document File</label>
                                <input type="file" name="document" id="document" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp" required>
                                <small class="text-muted d-block mt-1">PDF, Office, text, CSV, or image files up to 20 MB.</small>
                            </div>

                            <div class="mb-3">
                                <label for="upload_password" class="form-label fw-semibold">Password Verification</label>
                                <input type="password" name="password" id="upload_password" class="form-control" autocomplete="current-password" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="docs-panel mb-4">
                    <div class="docs-panel-header">
                        <h5>Document Library</h5>
                        <p>Review, filter, and access the documents retained on the website.</p>
                    </div>
                    <div class="docs-panel-body">
                        <form method="GET" action="{{ route('communities.documents.index') }}" class="row g-2 align-items-end mb-4">
                            <div class="col-md-5">
                                <label for="filter_community_id" class="form-label">Community</label>
                                <select name="community_id" id="filter_community_id" class="form-select">
                                    <option value="">All communities</option>
                                    @foreach($communities as $community)
                                        <option value="{{ $community->id }}" {{ (string) $selectedCommunityId === (string) $community->id ? 'selected' : '' }}>
                                            {{ $community->name }} ({{ $community->documents_count }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control" value="{{ $search }}" placeholder="Title, description, or filename">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>

                        @forelse($documents as $document)
                            <div class="docs-item">
                                <div class="docs-item-main">
                                    <div>
                                        <div class="docs-title-row">
                                            <span class="docs-file-icon">{{ $document->file_extension }}</span>
                                            <div>
                                                <h5 class="docs-title">{{ $document->title }}</h5>
                                                <div class="docs-meta">
                                                    <span class="docs-pill">{{ $document->community?->name ?? 'No community' }}</span>
                                                    <span class="docs-pill">{{ $document->original_filename }}</span>
                                                    <span class="docs-pill">{{ $document->file_size_label }}</span>
                                                    <span class="docs-pill">Uploaded {{ $document->created_at?->format('M d, Y') }}</span>
                                                    <span class="docs-pill">{{ $document->download_count }} downloads</span>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="docs-description">
                                            {{ $document->description ? \Illuminate\Support\Str::limit($document->description, 220) : 'No description added for this document.' }}
                                        </p>
                                        <p class="text-muted small mt-2 mb-0">
                                            Uploaded by {{ $document->uploader?->username ?? $document->uploader?->name ?? 'System' }}
                                        </p>
                                    </div>

                                    <div class="docs-actions">
                                        <button type="button"
                                                class="btn btn-outline-primary docs-access-toggle"
                                                data-panel="docs-access-panel-{{ $document->id }}"
                                                data-action="{{ route('communities.documents.view', $document->id) }}"
                                                data-method="POST"
                                                data-label="View Document"
                                                data-button-class="btn btn-primary"
                                                data-copy="Confirm your password to open this document.">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-success docs-access-toggle"
                                                data-panel="docs-access-panel-{{ $document->id }}"
                                                data-action="{{ route('communities.documents.download', $document->id) }}"
                                                data-method="POST"
                                                data-label="Download Document"
                                                data-button-class="btn btn-success"
                                                data-copy="Confirm your password to download this document.">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-danger docs-access-toggle"
                                                data-panel="docs-access-panel-{{ $document->id }}"
                                                data-action="{{ route('communities.documents.destroy', $document->id) }}"
                                                data-method="DELETE"
                                                data-label="Delete Document"
                                                data-button-class="btn btn-danger"
                                                data-copy="Confirm your password to permanently delete this document.">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </div>
                                </div>

                                <div class="docs-access-panel" id="docs-access-panel-{{ $document->id }}">
                                    <p class="docs-access-copy">Confirm your password to continue.</p>
                                    <form method="POST" class="docs-access-form">
                                        @csrf
                                        <input type="hidden" name="_method" value="DELETE" disabled>
                                        <div>
                                            <label class="form-label" for="password-{{ $document->id }}">Login Password</label>
                                            <input type="password"
                                                   name="password"
                                                   id="password-{{ $document->id }}"
                                                   class="form-control"
                                                   autocomplete="current-password"
                                                   required>
                                        </div>
                                        <button type="submit" class="btn btn-primary docs-access-submit">Continue</button>
                                        <button type="button" class="btn btn-light docs-access-cancel" data-panel="docs-access-panel-{{ $document->id }}">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="docs-empty">
                                <h5>No documents found</h5>
                                <p class="mb-0">Uploaded community documentation will appear here for founder and partner verification.</p>
                            </div>
                        @endforelse

                        <div class="mt-3">
                            {{ $documents->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function closeDocumentPanels() {
        document.querySelectorAll('.docs-access-panel').forEach(function (panel) {
            panel.classList.remove('is-open');
        });
    }

    document.querySelectorAll('.docs-access-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(this.dataset.panel);
            const form = panel.querySelector('form');
            const submit = panel.querySelector('.docs-access-submit');
            const copy = panel.querySelector('.docs-access-copy');
            const password = panel.querySelector('input[name="password"]');
            const methodInput = panel.querySelector('input[name="_method"]');

            closeDocumentPanels();

            form.action = this.dataset.action;
            submit.textContent = this.dataset.label;
            submit.className = this.dataset.buttonClass + ' docs-access-submit';
            copy.textContent = this.dataset.copy;
            password.value = '';

            if (this.dataset.method === 'DELETE') {
                methodInput.disabled = false;
                methodInput.value = 'DELETE';
            } else {
                methodInput.disabled = true;
                methodInput.value = '';
            }

            panel.classList.add('is-open');

            setTimeout(function () {
                password.focus();
            }, 100);
        });
    });

    document.querySelectorAll('.docs-access-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(this.dataset.panel).classList.remove('is-open');
        });
    });
});
</script>

@endsection
