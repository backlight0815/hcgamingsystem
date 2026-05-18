@extends('admin.admin_master')
@section('admin')

<title>Marketing Resources | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Marketing Resources</h4>
                        <p class="text-muted mb-0">Upload and manage marketing materials for leaders and recruiters.</p>
                    </div>
                    <a href="{{ route('admin.marketing.resources.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Upload Resource
                    </a>
                </div>
            </div>
        </div>

        <div class="breadcrumb mb-3">
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

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white text-center p-3">
                    <h6 class="mb-1">Total Resources</h6>
                    <h3 class="mb-0">{{ $totalResources }}</h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white text-center p-3">
                    <h6 class="mb-1">Active For Team</h6>
                    <h3 class="mb-0">{{ $activeResources }}</h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-info text-white text-center p-3">
                    <h6 class="mb-1">Verified Downloads</h6>
                    <h3 class="mb-0">{{ $totalDownloads }}</h3>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.marketing.resources.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" value="{{ $search }}" placeholder="Title, description, or filename">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="1" {{ (string) $status === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (string) $status === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>SI</th>
                                <th>Resource</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Downloads</th>
                                <th>Uploaded By</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resources as $resource)
                                <tr>
                                    <td>{{ $resources->firstItem() + $loop->index }}</td>
                                    <td>
                                        <strong>{{ $resource->title }}</strong>
                                        @if($resource->description)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($resource->description, 100) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-dark">{{ $resource->file_extension }}</span>
                                        <div class="small mt-1">{{ $resource->original_filename }}</div>
                                        <div class="text-muted small">{{ $resource->file_size_label }}</div>
                                    </td>
                                    <td>
                                        @if($resource->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $resource->download_count }}</td>
                                    <td>{{ $resource->uploader?->username ?? $resource->uploader?->name ?? '-' }}</td>
                                    <td>{{ $resource->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.marketing.resources.download', $resource->id) }}" class="btn btn-success btn-sm" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="{{ route('admin.marketing.resources.edit', $resource->id) }}" class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.marketing.resources.destroy', $resource->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this marketing resource?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No marketing resources uploaded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $resources->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
