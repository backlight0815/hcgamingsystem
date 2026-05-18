@extends('admin.admin_master')
@section('admin')

<title>Trading Recording Classes | HC Gaming Studio</title>

<style>
    .recording-url {
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Trading Recording Classes</h4>
                    <a href="{{ route('admin.trading.recordings.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Recording
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

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white text-center p-3">
                    <h6 class="mb-1">Total Recordings</h6>
                    <h3 class="mb-0">{{ $totalRecordings }}</h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white text-center p-3">
                    <h6 class="mb-1">Active For Traders</h6>
                    <h3 class="mb-0">{{ $activeRecordings }}</h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-warning text-dark text-center p-3">
                    <h6 class="mb-1">Pending Approval</h6>
                    <h3 class="mb-0">{{ $pendingRecordings }}</h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>SI</th>
                                <th>Title</th>
                                <th>Source</th>
                                <th>Video Website</th>
                                <th>Download Website</th>
                                <th>Materials</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Uploaded By</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recordings as $recording)
                                <tr>
                                    <td>{{ $recordings->firstItem() + $loop->index }}</td>
                                    <td>
                                        <strong>{{ $recording->title }}</strong>
                                        @if($recording->description)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($recording->description, 80) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $recording->source_name ?? '-' }}</td>
                                    <td>
                                        <a class="d-inline-block recording-url" href="{{ $recording->video_url }}" target="_blank" rel="noopener">
                                            {{ $recording->video_url }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($recording->download_url)
                                            <a class="d-inline-block recording-url" href="{{ $recording->download_url }}" target="_blank" rel="noopener">
                                                {{ $recording->download_url }}
                                            </a>
                                        @else
                                            <span class="text-muted">Uses video website</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($recording->materials_count > 0)
                                            <span class="badge bg-info">{{ $recording->materials_count }} uploaded</span>
                                        @else
                                            <span class="text-muted">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($recording->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($recording->approval_status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @elseif($recording->approval_status === 'rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    </td>
                                    <td>{{ $recording->uploader?->username ?? '-' }}</td>
                                    <td>{{ $recording->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.trading.recordings.show', $recording->id) }}" class="btn btn-secondary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.trading.recordings.edit', $recording->id) }}" class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if($canApproveRecordings && $recording->approval_status !== 'approved')
                                            <form action="{{ route('admin.trading.recordings.approve', $recording->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this leader recording class?');">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('admin.trading.recordings.destroy', $recording->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this recording class?');">
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
                                    <td colspan="11" class="text-center text-muted py-4">No recording classes added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $recordings->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
