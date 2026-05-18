@extends('admin.admin_master')
@section('admin')

<title>Edit Trading Recording Class | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit Trading Recording Class</h4>
                    <a href="{{ route('admin.trading.recordings.index') }}" class="btn btn-secondary">Back</a>
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
            <div class="col-lg-8">
                @if(auth()->user()->isTradingLeader())
                    <div class="alert alert-info">
                        Editing a leader upload sends it back for administration approval before clients can view it.
                    </div>
                @endif
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.trading.recordings.update', $recording->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $recording->title) }}" required>
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="source_name" class="form-label">Video Website Name</label>
                                <input type="text" name="source_name" id="source_name" class="form-control @error('source_name') is-invalid @enderror" value="{{ old('source_name', $recording->source_name) }}" placeholder="Example: YouTube, Vimeo, Google Drive">
                                @error('source_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="video_url" class="form-label">Video Website URL <span class="text-danger">*</span></label>
                                <input type="url" name="video_url" id="video_url" class="form-control @error('video_url') is-invalid @enderror" value="{{ old('video_url', $recording->video_url) }}" required>
                                @error('video_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="download_url" class="form-label">Download Website URL</label>
                                <input type="url" name="download_url" id="download_url" class="form-control @error('download_url') is-invalid @enderror" value="{{ old('download_url', $recording->download_url) }}" placeholder="Leave blank to use the video URL">
                                @error('download_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="materials" class="form-label">Add Class Materials</label>
                                <input type="file"
                                       name="materials[]"
                                       id="materials"
                                       class="form-control @error('materials') is-invalid @enderror @error('materials.*') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp,.zip"
                                       multiple>
                                <div class="form-text">Optional. Add PDF files, slides, spreadsheets, notes, images, or ZIP resources. Existing materials remain unless removed below.</div>
                                @error('materials') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                @error('materials.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            @if($recording->materials->isNotEmpty())
                                <div class="mb-3">
                                    <label class="form-label">Existing Class Materials</label>
                                    <div class="list-group">
                                        @foreach($recording->materials as $material)
                                            <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                                <div>
                                                    <strong>{{ $material->title }}</strong>
                                                    <div class="text-muted small">{{ $material->file_extension }} - {{ $material->file_size_label }}</div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('admin.trading.recordings.materials.download', [$recording->id, $material->id]) }}" class="btn btn-sm btn-outline-primary">
                                                        Download
                                                    </a>
                                                    <form action="{{ route('admin.trading.recordings.materials.destroy', [$recording->id, $material->id]) }}" method="POST" onsubmit="return confirm('Remove this class material?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $recording->description) }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Trader Access</label>
                                <select name="status" id="status" class="form-control @error('status') is-invalid @enderror">
                                    <option value="1" {{ old('status', $recording->status ? '1' : '0') == '1' ? 'selected' : '' }}>Active - traders can view and download</option>
                                    <option value="0" {{ old('status', $recording->status ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive - hidden from traders</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-info">Update Recording</button>
                                <a href="{{ route('admin.trading.recordings.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
