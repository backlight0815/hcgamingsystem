@extends('admin.admin_master')
@section('admin')
@php
    $approvedKnowledge = $knowledge->where('approval_status', 'approved')->count();
    $rejectedKnowledge = $knowledge->where('approval_status', 'rejected')->count();
    $resourceFiles = $knowledge->whereNotNull('file_path')->count();
    $communityCoverage = $knowledge->pluck('community_id')->filter()->unique()->count();
    $globalResources = $knowledge->whereNull('community_id')->count();

    $statusClass = fn ($status) => match ($status) {
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        default => 'status-pending',
    };

    $statusLabel = fn ($status) => match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };

    $resourceType = function (?string $path): string {
        if (! $path) {
            return 'No File';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'PDF',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'Image',
            default => strtoupper($extension ?: 'File'),
        };
    };
@endphp

<title>Knowledge Centre | HC Gaming Studio</title>

<style>
    .knowledge-admin {
        color: #172033;
    }

    .knowledge-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .knowledge-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(56, 189, 248, 0.15);
        border-radius: 50%;
    }

    .knowledge-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .knowledge-hero p,
    .knowledge-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .knowledge-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .knowledge-stat,
    .knowledge-panel {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .knowledge-stat {
        height: 100%;
        padding: 18px;
    }

    .knowledge-stat span,
    .knowledge-muted {
        color: #617188;
        font-size: 13px;
    }

    .knowledge-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .knowledge-panel {
        padding: 22px;
    }

    .knowledge-table {
        border-collapse: separate;
        border-spacing: 0;
        color: #172033;
        width: 100%;
    }

    .knowledge-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .knowledge-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .resource-title {
        min-width: 260px;
    }

    .resource-title strong {
        color: #111827;
        display: block;
        font-size: 15px;
    }

    .resource-description {
        color: #64748b;
        display: -webkit-box;
        font-size: 13px;
        margin-top: 5px;
        max-width: 520px;
        overflow: hidden;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .status-pill,
    .resource-pill,
    .community-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .status-approved {
        background: #dcfce7;
        color: #166534;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .resource-pill {
        background: #e0f2fe;
        color: #075985;
    }

    .community-pill {
        background: #eef2ff;
        color: #3730a3;
    }

    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 250px;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #617188;
        padding: 34px;
        text-align: center;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #111827;
        margin-left: 8px;
        min-height: 34px;
        padding: 6px 10px;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
        color: #475569;
    }

    @media (max-width: 575px) {
        .knowledge-hero,
        .knowledge-panel {
            padding: 18px;
        }

        .knowledge-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content knowledge-admin">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Knowledge Centre</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                            <li class="breadcrumb-item active">Knowledge Centre</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="knowledge-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-xl-8">
                    <div class="eyebrow">Learning Operations</div>
                    <h1>Knowledge Centre Library</h1>
                    <p>Manage technical lessons, leadership materials, approved resources, and community-specific learning files from one controlled workspace.</p>
                </div>
                <div class="col-xl-4 mt-4 mt-xl-0 text-xl-right">
                    <a href="{{ route('knowledge.centre.create') }}" class="btn btn-info mb-2">
                        <i class="ri-add-line mr-1"></i> Add Knowledge
                    </a>
                    <a href="{{ route('knowledge.centre.downloadZip') }}" class="btn btn-light mb-2">
                        <i class="ri-download-2-line mr-1"></i> Download ZIP
                    </a>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Total Resources</span>
                    <strong>{{ number_format($totalKnowledge) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Approved</span>
                    <strong>{{ number_format($approvedKnowledge) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Pending Review</span>
                    <strong>{{ number_format($pendingKnowledge) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Rejected</span>
                    <strong>{{ number_format($rejectedKnowledge) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Files Attached</span>
                    <strong>{{ number_format($resourceFiles) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="knowledge-stat">
                    <span>Global / Targeted</span>
                    <strong>{{ number_format($globalResources) }} / {{ number_format($communityCoverage) }}</strong>
                </div>
            </div>
        </div>

        <div class="knowledge-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                <div>
                    <h5 class="mb-1">Resource Governance</h5>
                    <div class="knowledge-muted">Approve leader uploads, maintain resource files, and optionally publish approved material into Discord communities.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="knowledge-table table table-hover align-middle" id="knowledgeCentreTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Resource</th>
                            <th>File</th>
                            <th>Community</th>
                            <th>Uploader</th>
                            <th>Approval</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($knowledge as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="resource-title">
                                        <strong>{{ $item->title }}</strong>
                                        <div class="resource-description">{{ $item->description ?: 'No description provided.' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="resource-pill">{{ $resourceType($item->file_path) }}</span>
                                    @if($item->file_path)
                                        <div class="mt-2">
                                            <a href="{{ asset($item->file_path) }}" target="_blank">Open file</a>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="community-pill">{{ $item->community?->name ?? 'All Communities' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $item->uploader?->username ?? 'HC Admin' }}</strong>
                                    <div class="knowledge-muted">{{ $item->uploader?->name ?? 'Administration' }}</div>
                                </td>
                                <td>
                                    <span class="status-pill {{ $statusClass($item->approval_status) }}">
                                        {{ $statusLabel($item->approval_status) }}
                                    </span>
                                </td>
                                <td data-order="{{ $item->created_at?->timestamp ?? 0 }}">{{ $item->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    <div class="action-row">
                                        <a href="{{ route('knowledge.centre.edit', $item->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>

                                        @if($canApproveKnowledge && $item->approval_status !== 'approved')
                                            <form action="{{ route('knowledge.centre.approve', $item->id) }}" method="POST" onsubmit="return confirm('Approve this knowledge item?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="ri-check-line"></i> Approve
                                                </button>
                                            </form>
                                        @endif

                                        @if(feature_enabled('DiscordIntegration'))
                                            <form action="{{ route('knowledge.centre.sendDiscord', $item->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info">
                                                    <i class="ri-discord-line"></i> Discord
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="ri-discord-line"></i> Discord
                                            </button>
                                        @endif

                                        <form action="{{ route('knowledge.centre.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this knowledge item?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="ri-delete-bin-line"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">No knowledge resources found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const table = $('#knowledgeCentreTable');

        if (table.find('tbody tr').length && table.find('.empty-state').length === 0) {
            table.DataTable({
                order: [[6, 'desc']],
                pageLength: 25,
                responsive: false,
                language: {
                    search: 'Search knowledge:',
                    lengthMenu: 'Show _MENU_ resources',
                    emptyTable: 'No resources found'
                }
            });
        }
    });
</script>
@endsection
