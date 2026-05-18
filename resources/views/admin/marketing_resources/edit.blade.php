@extends('admin.admin_master')
@section('admin')

<title>Edit Marketing Resource | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit Marketing Resource</h4>
                    <a href="{{ route('admin.marketing.resources.index') }}" class="btn btn-secondary">Back</a>
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
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.marketing.resources.update', $resource->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $resource->title) }}" required>
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description', $resource->description) }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Current File</label>
                                <div class="border rounded p-3 bg-light">
                                    <strong>{{ $resource->original_filename }}</strong>
                                    <div class="text-muted small">{{ $resource->file_extension }} - {{ $resource->file_size_label }}</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="material_file" class="form-label">Replace Material</label>
                                <input type="file"
                                       name="material_file"
                                       id="material_file"
                                       class="form-control @error('material_file') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp,.zip">
                                <div class="form-text">Leave blank to keep the current file.</div>
                                @error('material_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Leader / Recruiter Access</label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="1" {{ old('status', $resource->status ? '1' : '0') == '1' ? 'selected' : '' }}>Active - visible to leaders and recruiters</option>
                                    <option value="0" {{ old('status', $resource->status ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive - hidden from leaders and recruiters</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-info">Update Resource</button>
                                <a href="{{ route('admin.marketing.resources.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
