@extends('admin.admin_master')
@section('admin')

<title>{{ $recording->title }} | Trading Recording Class</title>

<style>
    .recording-frame {
        width: 100%;
        min-height: 520px;
        border: 0;
        border-radius: 8px;
        background: #111827;
    }

    .recording-video {
        width: 100%;
        max-height: 620px;
        border-radius: 8px;
        background: #111827;
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{ $recording->title }}</h4>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.trading.recordings.edit', $recording->id) }}" class="btn btn-info">Edit</a>
                        <a href="{{ route('admin.trading.recordings.index') }}" class="btn btn-secondary">Back</a>
                    </div>
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

        <div class="row">
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-body">
                        @if($recording->is_direct_video)
                            <video class="recording-video" controls controlsList="nodownload">
                                <source src="{{ $recording->video_url }}">
                            </video>
                        @else
                            <iframe class="recording-frame" src="{{ $recording->embed_url }}" allowfullscreen></iframe>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recording Details</h5>
                        <p class="mb-2"><strong>Status:</strong>
                            @if($recording->status)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </p>
                        <p class="mb-2"><strong>Source:</strong> {{ $recording->source_name ?? '-' }}</p>
                        <p class="mb-2"><strong>Uploaded By:</strong> {{ $recording->uploader?->username ?? '-' }}</p>
                        <p class="mb-2"><strong>Created At:</strong> {{ $recording->created_at?->format('Y-m-d H:i') }}</p>
                        <p class="mb-3"><strong>Description:</strong><br>{{ $recording->description ?? '-' }}</p>

                        <a href="{{ $recording->video_url }}" target="_blank" rel="noopener" class="btn btn-primary w-100 mb-2">
                            Open Video Website
                        </a>
                        <a href="{{ $recording->effective_download_url }}" target="_blank" rel="noopener" class="btn btn-success w-100">
                            Open Download Website
                        </a>
                    </div>
                </div>

                @if($recording->materials->isNotEmpty())
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Class Materials</h5>
                            <div class="list-group">
                                @foreach($recording->materials as $material)
                                    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                        <div>
                                            <strong>{{ $material->title }}</strong>
                                            <div class="text-muted small">
                                                {{ $material->file_extension }} - {{ $material->file_size_label }} - Downloads: {{ $material->download_count }}
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.trading.recordings.materials.download', [$recording->id, $material->id]) }}" class="btn btn-sm btn-outline-primary">
                                            Download
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
