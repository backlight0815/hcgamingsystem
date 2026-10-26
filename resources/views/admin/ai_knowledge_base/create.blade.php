@extends('admin.admin_master')
@section('admin')

<title>New AI Knowledge Base Record | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">New AI Knowledge Base Record</h4>
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
                        <form action="{{ route('admin.ai_knowledge_base.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="module" class="form-label">Module</label>
                                <select name="module" id="module" class="form-control" required>
                                    @foreach($modules as $value => $label)
                                        <option value="{{ $value }}" {{ old('module') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea name="content" id="content" rows="12" class="form-control" required>{{ old('content') }}</textarea>
                            </div>
                            <div class="mb-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-control" required>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'draft') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="difficulty" class="form-label">Learning level</label><select name="difficulty" id="difficulty" class="form-control"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div>
                                <div class="col-md-8 mb-3"><label for="source" class="form-label">Source / provenance</label><input name="source" id="source" class="form-control" maxlength="255" value="{{ old('source', 'HC TradingHub editorial') }}"></div>
                                <div class="col-md-4 mb-3"><label for="language" class="form-label">Language</label><input name="language" id="language" class="form-control" maxlength="12" value="{{ old('language', 'en') }}"></div>
                                <div class="col-md-4 mb-3"><label for="reviewed_at" class="form-label">Last reviewed</label><input type="date" name="reviewed_at" id="reviewed_at" class="form-control" value="{{ old('reviewed_at') }}"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="assetClasses" class="form-label">Asset classes</label><input id="assetClasses" name="asset_classes" class="form-control" value="{{ old('asset_classes') }}" placeholder="forex, metals"></div>
                                <div class="col-md-4 mb-3"><label for="sourceType" class="form-label">Source type</label><select id="sourceType" name="source_type" class="form-control"><option value="editorial">HC editorial</option><option value="primary_source">Primary source</option><option value="platform_documentation">Platform documentation</option><option value="owner_specification">Owner specification</option></select></div>
                                <div class="col-md-4 mb-3"><label for="contentOwner" class="form-label">Content owner</label><input id="contentOwner" name="content_owner" class="form-control" maxlength="100" value="{{ old('content_owner','HC TradingHub') }}"></div>
                                <div class="col-md-6 mb-3"><label for="sourceUrl" class="form-label">Original source URL, if applicable</label><input id="sourceUrl" name="source_url" type="url" class="form-control" value="{{ old('source_url') }}"></div>
                                <div class="col-md-3 mb-3"><label for="lastVerified" class="form-label">Last verified</label><input id="lastVerified" name="last_verified_at" type="date" class="form-control" value="{{ old('last_verified_at') }}"></div>
                                <div class="col-md-3 mb-3"><label for="effectiveUntil" class="form-label">Expires</label><input id="effectiveUntil" name="effective_until" type="date" class="form-control" value="{{ old('effective_until') }}"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="conceptId" class="form-label">Canonical concept</label><select id="conceptId" name="canonical_concept_id" class="form-select"><option value="">None / new concept</option>@foreach(array_keys(config('trading_ai_concepts.concepts', [])) as $concept)<option value="{{ $concept }}" @selected(old('canonical_concept_id') === $concept)>{{ str_replace('_',' ',ucfirst($concept)) }}</option>@endforeach</select></div>
                                <div class="col-md-4 mb-3"><label for="sourceOwner" class="form-label">Original source owner</label><input id="sourceOwner" name="source_owner" class="form-control" value="{{ old('source_owner') }}" maxlength="150"></div>
                                <div class="col-md-4 mb-3"><label for="licenseStatus" class="form-label">Permission / use</label><select id="licenseStatus" name="license_status" class="form-select">@foreach(['owner_original'=>'Owner original','permission_confirmed'=>'Permission confirmed','link_only'=>'Link and original summary only','unknown'=>'Unknown, review needed'] as $value=>$label)<option value="{{ $value }}" @selected(old('license_status','owner_original') === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-4 mb-3"><label for="sourcePublished" class="form-label">Source published / updated</label><input id="sourcePublished" name="source_published_at" type="date" class="form-control" value="{{ old('source_published_at') }}"></div>
                                <div class="col-md-4 mb-3"><label for="sourceRetrieved" class="form-label">Source retrieved</label><input id="sourceRetrieved" name="source_retrieved_at" type="date" class="form-control" value="{{ old('source_retrieved_at') }}"></div>
                                <div class="col-md-4 mb-3"><label for="reviewAfter" class="form-label">Review after</label><input id="reviewAfter" name="review_after_at" type="date" class="form-control" value="{{ old('review_after_at') }}"></div>
                            </div>
                            <p class="text-muted small">New articles begin as Draft and require review, approval and publication.</p><button type="submit" class="btn btn-primary">Create Draft</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
