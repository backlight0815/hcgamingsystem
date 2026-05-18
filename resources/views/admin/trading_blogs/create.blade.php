@extends('admin.admin_master')
@section('admin')

<title>Add Trading Blog Post | HC Gaming Studio</title>

<style>
    .tb-form-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .tb-preview {
        width: 180px;
        height: 120px;
        border-radius: 8px;
        object-fit: cover;
        background: #e5e7eb;
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Add Trading Blog Post</h4>
                        <p class="text-muted mb-0 mt-1">Create a focused trading article for traders.</p>
                    </div>
                    <a href="{{ route('admin.trading.blogs.index') }}" class="btn btn-secondary">Back</a>
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

        <form action="{{ route('admin.trading.blogs.store') }}" method="POST" enctype="multipart/form-data" id="tradingBlogForm">
            @csrf

            <div class="row">
                <div class="col-xl-8 mb-3">
                    <div class="card tb-form-card">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="excerpt" class="form-label">Short Summary</label>
                                <textarea name="excerpt" id="excerpt" rows="3" class="form-control @error('excerpt') is-invalid @enderror" placeholder="A concise takeaway shown in the trading blog list.">{{ old('excerpt') }}</textarea>
                                @error('excerpt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="elm1" class="form-label">Content <span class="text-danger">*</span></label>
                                <textarea name="content" id="elm1" rows="12" class="form-control @error('content') is-invalid @enderror">{{ old('content') }}</textarea>
                                @error('content') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 mb-3">
                    <div class="card tb-form-card">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category" id="category" class="form-control @error('category') is-invalid @enderror" required>
                                    <option value="">Select category</option>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags</label>
                                <input type="text" name="tags" id="tags" class="form-control @error('tags') is-invalid @enderror" value="{{ old('tags') }}" placeholder="risk, mindset, prop firm">
                                @error('tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="trading_blog_status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="trading_blog_status" class="form-control @error('status') is-invalid @enderror" required>
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" {{ old('status', \App\Models\TradingBlog::STATUS_PUBLISHED) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label for="published_at" class="form-label">Publish Time</label>
                                <input type="datetime-local" name="published_at" id="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at') }}">
                                @error('published_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="is_featured" value="0">
                                <input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured') == '1' ? 'checked' : '' }}>
                                <label for="is_featured" class="form-check-label">Feature on top of reader page</label>
                            </div>

                            <div class="mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <input type="file" name="cover_image" id="cover_image" class="form-control @error('cover_image') is-invalid @enderror" accept="image/*">
                                @error('cover_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-4">
                                <img id="coverPreview" src="{{ url('upload/default.jpg') }}" alt="Cover preview" class="tb-preview">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Save Trading Post</button>
                            <a href="{{ route('admin.trading.blogs.index') }}" class="btn btn-light w-100 mt-2">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('tradingBlogForm');
    const input = document.getElementById('cover_image');
    const preview = document.getElementById('coverPreview');

    form.addEventListener('submit', function () {
        if (window.tinymce) {
            tinymce.triggerSave();
        }
    });

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (event) {
            preview.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>

@endsection
