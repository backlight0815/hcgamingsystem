@extends('admin.admin_master')
@section('admin')

<title>Edit AI Knowledge Base Record | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit AI Knowledge Base Record</h4>
                    <a href="{{ route('admin.ai_knowledge_base.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.ai_knowledge_base.update', $knowledgeBase->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="module" class="form-label">Module</label>
                                <select name="module" id="module" class="form-control" required>
                                    @foreach($modules as $value => $label)
                                        <option value="{{ $value }}" {{ old('module', $knowledgeBase->module) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $knowledgeBase->title) }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea name="content" id="content" rows="12" class="form-control" required>{{ old('content', $knowledgeBase->content) }}</textarea>
                            </div>
                            <div class="mb-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-control" required>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', $knowledgeBase->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="difficulty" class="form-label">Learning level</label><select name="difficulty" id="difficulty" class="form-control">@foreach(['beginner','intermediate','advanced'] as $level)<option value="{{ $level }}" @selected(old('difficulty', $knowledgeBase->difficulty) === $level)>{{ ucfirst($level) }}</option>@endforeach</select></div>
                                <div class="col-md-8 mb-3"><label for="source" class="form-label">Source / provenance</label><input name="source" id="source" class="form-control" maxlength="255" value="{{ old('source', $knowledgeBase->source) }}"></div>
                                <div class="col-md-4 mb-3"><label for="language" class="form-label">Language</label><input name="language" id="language" class="form-control" maxlength="12" value="{{ old('language', $knowledgeBase->language) }}"></div>
                                <div class="col-md-4 mb-3"><label for="reviewed_at" class="form-label">Last reviewed</label><input type="date" name="reviewed_at" id="reviewed_at" class="form-control" value="{{ old('reviewed_at', $knowledgeBase->reviewed_at) }}"></div>
                            </div>
                            <button type="submit" class="btn btn-info">Update Record</button>
                        </form>
                        <div class="mt-4"><h5>Previous versions</h5>@forelse($versions as $version)<details class="border rounded p-2 mb-2"><summary>Version {{ $version->version }} · {{ $version->created_at }} · {{ $version->source }}</summary><pre class="mt-2" style="white-space:pre-wrap">{{ $version->content }}</pre></details>@empty<p class="text-muted">No previous versions.</p>@endforelse</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
