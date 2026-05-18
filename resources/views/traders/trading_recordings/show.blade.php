@extends('admin.admin_master')
@section('admin')

<title>{{ $recording->title }} | Recording Class</title>

<style>
    .recording-frame {
        width: 100%;
        min-height: 560px;
        border: 0;
        border-radius: 8px;
        background: #111827;
    }

    .recording-video {
        width: 100%;
        max-height: 640px;
        border-radius: 8px;
        background: #111827;
    }

    .material-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 12px;
        background: #ffffff;
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{ $recording->title }}</h4>
                    <a href="{{ route('trading.recordings.index') }}" class="btn btn-secondary">Back to Recordings</a>
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
                        <h5 class="card-title">Class Details</h5>
                        <p class="text-muted mb-2">{{ $recording->source_name ?? 'Video website' }}</p>
                        <p>{{ $recording->description ?? 'No description added.' }}</p>

                        <hr>

                        <form action="{{ route('trading.recordings.download', $recording->id) }}" method="POST">
                            @csrf
                            <label for="download_password" class="form-label">Password Required To Download</label>
                            <input type="password" name="password" id="download_password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror

                            <button type="submit" class="btn btn-success w-100 mt-3">
                                <i class="fas fa-download"></i> Download Recording
                            </button>
                        </form>

                        @if($recording->materials->isNotEmpty())
                            <hr>

                            <h5 class="card-title mb-3">Class Materials</h5>

                            @foreach($recording->materials as $material)
                                <div class="material-item">
                                    <strong>{{ $material->title }}</strong>
                                    <div class="text-muted small mb-2">{{ $material->file_extension }} - {{ $material->file_size_label }}</div>

                                    <form action="{{ route('trading.recordings.materials.download', [$recording->id, $material->id]) }}" method="POST">
                                        @csrf
                                        <label for="material_password_{{ $material->id }}" class="form-label small">Confirm Login Password</label>
                                        <input type="password"
                                               name="password"
                                               id="material_password_{{ $material->id }}"
                                               class="form-control form-control-sm @error('password') is-invalid @enderror"
                                               autocomplete="current-password"
                                               required>
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2">
                                            <i class="fas fa-file-download"></i> Download Material
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
