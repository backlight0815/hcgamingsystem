@extends('admin.admin_master')
@section('admin')
@php
    $totalResources = $knowledge->count();
    $globalResources = $knowledge->whereNull('community_id')->count();
    $leaderResources = $knowledge->filter(fn ($item) => ! in_array((int) ($item->uploader?->role_id ?? 0), [1, 2], true))->count();
    $fileResources = $knowledge->whereNotNull('file_path')->count();

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
    .knowledge-library {
        color: #172033;
    }

    .library-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .library-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(16, 185, 129, 0.16);
        border-radius: 50%;
    }

    .library-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .library-hero p,
    .library-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .library-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .library-stat,
    .library-toolbar,
    .knowledge-card {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .library-stat {
        height: 100%;
        padding: 18px;
    }

    .library-stat span,
    .knowledge-muted {
        color: #617188;
        font-size: 13px;
    }

    .library-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .library-toolbar {
        padding: 18px;
    }

    .library-toolbar .form-control {
        border-color: #cbd5e1;
        color: #111827;
        min-height: 42px;
    }

    .knowledge-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 22px;
    }

    .knowledge-card h5 {
        color: #0f172a;
        font-weight: 800;
        line-height: 1.35;
    }

    .knowledge-description {
        color: #64748b;
        display: -webkit-box;
        min-height: 44px;
        overflow: hidden;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .resource-pill,
    .community-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .resource-pill {
        background: #e0f2fe;
        color: #075985;
    }

    .community-pill {
        background: #eef2ff;
        color: #3730a3;
    }

    .empty-state {
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #617188;
        padding: 44px;
        text-align: center;
    }

    @media (max-width: 575px) {
        .library-hero,
        .library-toolbar,
        .knowledge-card {
            padding: 18px;
        }

        .library-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content knowledge-library">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Knowledge Centre</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Trading</a></li>
                            <li class="breadcrumb-item active">Knowledge Centre</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="library-hero mb-4">
            <div class="position-relative" style="z-index: 1;">
                <div class="eyebrow">Trader Learning Library</div>
                <h1>Knowledge Centre</h1>
                <p>Access approved learning resources from HC administration and, when applicable, materials shared by your trading leader.</p>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="library-stat">
                    <span>Available Resources</span>
                    <strong>{{ number_format($totalResources) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="library-stat">
                    <span>Admin Resources</span>
                    <strong>{{ number_format(max(0, $totalResources - $leaderResources)) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="library-stat">
                    <span>Leader Resources</span>
                    <strong>{{ number_format($leaderResources) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-4">
                <div class="library-stat">
                    <span>Files Attached</span>
                    <strong>{{ number_format($fileResources) }}</strong>
                </div>
            </div>
        </div>

        <div class="library-toolbar mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <input type="search" id="knowledgeSearch" class="form-control" placeholder="Search by title, description, uploader, or community">
                </div>
                <div class="col-lg-4 text-lg-right">
                    <span class="knowledge-muted">{{ number_format($globalResources) }} global resources available</span>
                </div>
            </div>
        </div>

        <div class="row" id="knowledgeGrid">
            @forelse($knowledge as $item)
                @php
                    $searchText = strtolower(trim(($item->title ?? '') . ' ' . ($item->description ?? '') . ' ' . ($item->uploader?->username ?? '') . ' ' . ($item->community?->name ?? 'all communities')));
                @endphp
                <div class="col-xl-4 col-md-6 mb-4 knowledge-item" data-search="{{ $searchText }}">
                    <div class="knowledge-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="resource-pill">{{ $resourceType($item->file_path) }}</span>
                            <span class="community-pill">{{ $item->community?->name ?? 'All Communities' }}</span>
                        </div>
                        <h5>{{ $item->title }}</h5>
                        <p class="knowledge-description">{{ $item->description ?: 'No description provided.' }}</p>

                        <div class="knowledge-muted mt-auto mb-3">
                            Uploaded by {{ $item->uploader?->username ?? 'HC Admin' }}<br>
                            {{ $item->created_at?->format('Y-m-d') ?? '-' }}
                        </div>

                        @if($item->file_path)
                            <a href="{{ asset($item->file_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="ri-external-link-line mr-1"></i> Open Resource
                            </a>
                        @else
                            <button type="button" class="btn btn-light" disabled>No file attached</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <h5>No Knowledge Resources Yet</h5>
                        <p class="mb-0">Approved resources will appear here when administration or your leader publishes them.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div id="knowledgeEmptySearch" class="empty-state d-none">
            No resources match your search.
        </div>
    </div>
</div>

<script>
    $(function () {
        $('#knowledgeSearch').on('input', function () {
            const query = this.value.trim().toLowerCase();
            let visible = 0;

            $('.knowledge-item').each(function () {
                const match = $(this).data('search').includes(query);
                $(this).toggle(match);
                if (match) {
                    visible++;
                }
            });

            $('#knowledgeEmptySearch').toggleClass('d-none', visible !== 0 || $('.knowledge-item').length === 0);
        });
    });
</script>
@endsection
