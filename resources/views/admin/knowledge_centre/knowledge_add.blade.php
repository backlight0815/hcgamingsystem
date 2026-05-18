@extends('admin.admin_master')
@section('admin')

@php
    $errors = $errors ?? session()->get('errors', new \Illuminate\Support\ViewErrorBag);
@endphp

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<title>Knowledge Centre - Add | HC Gaming Studio</title>

<style>
/* =========================
   Knowledge Card Styles
   ========================= */
.knowledge-card {
    background-color: #f8f9fa; /* light gray card */
    border-radius: 12px;
    border: 1px solid #ced4da;
    padding: 30px 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    position: relative;
}

/* Gradient top bar */
.knowledge-card::before {
    content: "";
    display: block;
    height: 5px;
    width: 100%;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    background: linear-gradient(90deg, #00b894, #0d6efd);
    margin-bottom: 15px;
}

.knowledge-card .card-header {
    font-weight: bold;
    font-size: 1.3rem;
    margin-bottom: 20px;
    padding-bottom: 0;
}

.knowledge-card .card-header small {
    font-weight: normal;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Form Inputs */
.knowledge-card .form-control,
.knowledge-card .form-select {
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 10px 12px;
}

.knowledge-card .form-control::placeholder {
    color: #6c757d;
}

.knowledge-card .form-control:focus,
.knowledge-card .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 6px rgba(13, 110, 253, 0.3);
    background-color: #fff;
}

/* File preview badge */
.knowledge-card .file-preview {
    margin-top: 5px;
    font-size: 0.85rem;
    color: #0d6efd;
    background-color: #e7f1ff;
    padding: 4px 8px;
    border-radius: 12px;
    display: inline-block;
}

/* Buttons */
.knowledge-card .btn-primary {
    background: linear-gradient(45deg,#00b894,#0d6efd);
    border-radius: 8px;
    border: none;
    transition: all 0.3s;
}

.knowledge-card .btn-primary:hover {
    background: linear-gradient(45deg,#0d6efd,#00b894);
}

.knowledge-card .btn-secondary {
    background-color: #6c757d;
    border: none;
    color: #fff;
    border-radius: 8px;
}

/* Validation */
.invalid-feedback {
    color: #dc3545;
}
</style>

<div class="page-content">
    <div class="container-fluid">

        <!-- Header with Back Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Add Knowledge Centre</h4>
            <a href="{{ route('knowledge.centre.index') }}" 
               class="btn btn-outline-primary px-3"
               style="border-width: 2px; font-weight: 600;">
                ← Back to List
            </a>
        </div>

        <!-- Knowledge Card Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if(auth()->user()->isTradingLeader())
                    <div class="alert alert-info">
                        Leader knowledge uploads are submitted for administration approval before clients can view them.
                    </div>
                @endif
                <div class="knowledge-card">

                    <div class="card-header">
                        <i class="fas fa-book-open me-2"></i> Add Knowledge Centre
                        <small class="d-block mt-1">Add trading insights for your community</small>
                    </div>

                    <form method="POST" action="{{ route('knowledge.centre.store') }}" 
                          id="submitKnowledge" 
                          enctype="multipart/form-data">
                        @csrf

                        {{-- Title --}}
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title"
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="Enter Knowledge Title" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description"
                                      class="form-control @error('description') is-invalid @enderror"
                                      rows="4" placeholder="Enter Description">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- File Upload: Image or PDF --}}
                        <div class="mb-3">
                            <label for="file" class="form-label">File (Image or PDF, Optional)</label>
                            <input type="file" name="file" id="file" 
                                   accept="application/pdf, image/*" 
                                   class="form-control @error('file') is-invalid @enderror">
                            <div id="filePreview" class="file-preview"></div>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Community --}}
                        <div class="mb-3">
                            <label for="community_id" class="form-label">Community</label>
                            <select name="community_id" id="community_id" class="form-select @error('community_id') is-invalid @enderror">
                                <option value="">All Communities</option>
                                @foreach($communities as $community)
                                    <option value="{{ $community->id }}" {{ old('community_id') == $community->id ? 'selected' : '' }}>
                                        {{ $community->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('community_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Submit --}}
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary me-2" id="submitbutton">
                                <i class="fas fa-book-open me-1"></i> Add Knowledge
                            </button>
                            <a href="{{ route('knowledge.centre.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times-circle me-1"></i> Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    // Prevent double submit
    $("#submitKnowledge").on("submit", function() {
        $("#submitbutton").prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-1"></i> Submitting...');
    });

    // File Preview: Image or PDF
    $('#file').on('change', function() {
        const file = this.files[0];
        if(file){
            let previewHTML = '';

            if(file.type.startsWith('image/')){
                // Image preview
                const reader = new FileReader();
                reader.onload = function(e){
                    previewHTML = `<img src="${e.target.result}" style="max-width:150px; max-height:150px; border-radius:8px; border:1px solid #ced4da; margin-top:5px;">`;
                    $('#filePreview').html(previewHTML);
                };
                reader.readAsDataURL(file);
            } else if(file.type === 'application/pdf'){
                // PDF preview
                previewHTML = `<i class="fas fa-file-pdf me-1"></i> ${file.name}`;
                $('#filePreview').html(previewHTML);
            } else {
                $('#filePreview').html(file.name);
            }

        } else {
            $('#filePreview').html('');
        }
    });
});
</script>

@endsection
