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
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="assetClasses" class="form-label">Asset classes</label><input id="assetClasses" name="asset_classes" class="form-control" value="{{ old('asset_classes',implode(', ',$knowledgeBase->asset_classes ?? [])) }}"></div>
                                <div class="col-md-4 mb-3"><label for="sourceType" class="form-label">Source type</label><select id="sourceType" name="source_type" class="form-control">@foreach(['editorial'=>'HC editorial','primary_source'=>'Primary source','platform_documentation'=>'Platform documentation','owner_specification'=>'Owner specification'] as $value=>$label)<option value="{{ $value }}" @selected(old('source_type',$knowledgeBase->source_type)===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-4 mb-3"><label for="contentOwner" class="form-label">Content owner</label><input id="contentOwner" name="content_owner" class="form-control" maxlength="100" value="{{ old('content_owner',$knowledgeBase->content_owner) }}"></div>
                                <div class="col-md-6 mb-3"><label for="sourceUrl" class="form-label">Original source URL</label><input id="sourceUrl" name="source_url" type="url" class="form-control" value="{{ old('source_url',$knowledgeBase->source_url) }}"></div>
                                <div class="col-md-3 mb-3"><label for="lastVerified" class="form-label">Last verified</label><input id="lastVerified" name="last_verified_at" type="date" class="form-control" value="{{ old('last_verified_at',$knowledgeBase->last_verified_at?->format('Y-m-d')) }}"></div>
                                <div class="col-md-3 mb-3"><label for="effectiveUntil" class="form-label">Expires</label><input id="effectiveUntil" name="effective_until" type="date" class="form-control" value="{{ old('effective_until',$knowledgeBase->effective_until?->format('Y-m-d')) }}"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="conceptId" class="form-label">Canonical concept</label><select id="conceptId" name="canonical_concept_id" class="form-select"><option value="">None / new concept</option>@foreach(array_keys(config('trading_ai_concepts.concepts', [])) as $concept)<option value="{{ $concept }}" @selected(old('canonical_concept_id',$knowledgeBase->canonical_concept_id) === $concept)>{{ str_replace('_',' ',ucfirst($concept)) }}</option>@endforeach</select></div>
                                <div class="col-md-4 mb-3"><label for="sourceOwner" class="form-label">Original source owner</label><input id="sourceOwner" name="source_owner" class="form-control" value="{{ old('source_owner',$knowledgeBase->source_owner) }}" maxlength="150"></div>
                                <div class="col-md-4 mb-3"><label for="licenseStatus" class="form-label">Permission / use</label><select id="licenseStatus" name="license_status" class="form-select">@foreach(['owner_original'=>'Owner original','permission_confirmed'=>'Permission confirmed','link_only'=>'Link and original summary only','unknown'=>'Unknown, review needed'] as $value=>$label)<option value="{{ $value }}" @selected(old('license_status',$knowledgeBase->license_status) === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-4 mb-3"><label for="sourcePublished" class="form-label">Source published / updated</label><input id="sourcePublished" name="source_published_at" type="date" class="form-control" value="{{ old('source_published_at',$knowledgeBase->source_published_at?->format('Y-m-d')) }}"></div>
                                <div class="col-md-4 mb-3"><label for="sourceRetrieved" class="form-label">Source retrieved</label><input id="sourceRetrieved" name="source_retrieved_at" type="date" class="form-control" value="{{ old('source_retrieved_at',$knowledgeBase->source_retrieved_at?->format('Y-m-d')) }}"></div>
                                <div class="col-md-4 mb-3"><label for="reviewAfter" class="form-label">Review after</label><input id="reviewAfter" name="review_after_at" type="date" class="form-control" value="{{ old('review_after_at',$knowledgeBase->review_after_at?->format('Y-m-d')) }}"></div>
                            </div>
                            <button type="submit" class="btn btn-info">Save changes</button>
                        </form>
                        @if(in_array($knowledgeBase->status,['draft','pending_review','approved']))
                        <form method="POST" action="{{ route('admin.ai_knowledge_base.transition',$knowledgeBase->id) }}" class="mt-3">@csrf
                            <input type="hidden" name="action" value="{{ ['draft'=>'submit','pending_review'=>'approve','approved'=>'publish'][$knowledgeBase->status] }}">
                            <button class="btn btn-outline-primary">{{ ['draft'=>'Submit for review','pending_review'=>'Approve','approved'=>'Publish'][$knowledgeBase->status] }}</button>
                        </form>@endif
                        <div class="mt-4"><h5>Previous versions</h5>@forelse($versions as $version)<details class="border rounded p-2 mb-2"><summary>Version {{ $version->version }} · {{ $version->created_at }} · {{ $version->source }}</summary><pre class="mt-2" style="white-space:pre-wrap">{{ $version->content }}</pre></details>@empty<p class="text-muted">No previous versions.</p>@endforelse</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
