@extends('admin.admin_master')
@section('admin')

<title>Community Showcase | HC Gaming Studio</title>

@php
    $requirements = old('entry_requirements', $page->entry_requirements ?? []);
    $coreServices = old('core_services', $page->core_services ?? []);
    $secondaryServices = old('secondary_services', $page->secondary_services ?? []);
@endphp

<style>
    .showcase-editor .panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
        margin-bottom: 20px;
    }

    .showcase-editor .panel-header {
        padding: 18px 20px;
        border-bottom: 1px solid #e5e7eb;
    }

    .showcase-editor .panel-header h5 {
        color: #0f172a;
        font-weight: 700;
        margin: 0;
    }

    .showcase-editor .panel-header p {
        color: #64748b;
        margin: 6px 0 0;
    }

    .showcase-editor .panel-body {
        padding: 20px;
    }

    .repeat-row {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        background: #f8fafc;
    }

    .poster-preview {
        max-width: 220px;
        border-radius: 8px;
        border: 1px solid #dfe5ec;
        background: #ffffff;
        padding: 6px;
    }
</style>

<div class="page-content showcase-editor">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Community Showcase</h4>
                    <a href="{{ route('community.showcase') }}" class="btn btn-light" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i> Preview Public Page
                    </a>
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

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.community.showcase.update') }}" enctype="multipart/form-data">
            @csrf

            <div class="panel">
                <div class="panel-header">
                    <h5>Hero Content</h5>
                    <p>This is the first content guests see on the public community page.</p>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="hero_kicker">Kicker</label>
                                <input type="text" name="hero_kicker" id="hero_kicker" class="form-control" value="{{ old('hero_kicker', $page->hero_kicker) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="hero_title">Title</label>
                                <input type="text" name="hero_title" id="hero_title" class="form-control" value="{{ old('hero_title', $page->hero_title) }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="hero_subtitle">Subtitle</label>
                                <input type="text" name="hero_subtitle" id="hero_subtitle" class="form-control" value="{{ old('hero_subtitle', $page->hero_subtitle) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="hero_intro">Intro</label>
                                <textarea name="hero_intro" id="hero_intro" class="form-control" rows="4">{{ old('hero_intro', $page->hero_intro) }}</textarea>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="poster_image">Poster Image</label>
                                <input type="file" name="poster_image" id="poster_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                            @if($page->poster_image)
                                <img src="{{ asset($page->poster_image) }}" class="poster-preview" alt="Current poster">
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h5>Call To Action</h5>
                    <p>Set the two buttons shown in the public hero area.</p>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold" for="primary_cta_label">Primary Label</label>
                            <input type="text" name="primary_cta_label" id="primary_cta_label" class="form-control" value="{{ old('primary_cta_label', $page->primary_cta_label) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold" for="primary_cta_url">Primary URL</label>
                            <input type="text" name="primary_cta_url" id="primary_cta_url" class="form-control" value="{{ old('primary_cta_url', $page->primary_cta_url) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold" for="secondary_cta_label">Secondary Label</label>
                            <input type="text" name="secondary_cta_label" id="secondary_cta_label" class="form-control" value="{{ old('secondary_cta_label', $page->secondary_cta_label) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold" for="secondary_cta_url">Secondary URL</label>
                            <input type="text" name="secondary_cta_url" id="secondary_cta_url" class="form-control" value="{{ old('secondary_cta_url', $page->secondary_cta_url) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Entry Requirements</h5>
                        <p>These appear as pricing and capital requirement blocks.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm add-repeat" data-target="requirements" data-type="requirement">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div class="panel-body" id="requirements">
                    @forelse($requirements as $index => $item)
                        <div class="repeat-row requirement-row">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Label</label>
                                    <input type="text" name="entry_requirements[{{ $index }}][label]" class="form-control" value="{{ $item['label'] ?? '' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Value</label>
                                    <input type="text" name="entry_requirements[{{ $index }}][value]" class="form-control" value="{{ $item['value'] ?? '' }}">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="entry_requirements[{{ $index }}][description]" class="form-control" rows="2">{{ $item['description'] ?? '' }}</textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-light btn-sm remove-repeat">Remove</button>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Core Services</h5>
                        <p>Main services displayed in the service grid.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm add-repeat" data-target="core_services" data-type="core">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div class="panel-body" id="core_services">
                    @forelse($coreServices as $index => $item)
                        <div class="repeat-row service-row">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="core_services[{{ $index }}][title]" class="form-control" value="{{ $item['title'] ?? '' }}">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="core_services[{{ $index }}][description]" class="form-control" rows="2">{{ $item['description'] ?? '' }}</textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-light btn-sm remove-repeat">Remove</button>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Secondary Services</h5>
                        <p>Additional benefits and supporting services.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm add-repeat" data-target="secondary_services" data-type="secondary">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div class="panel-body" id="secondary_services">
                    @forelse($secondaryServices as $index => $item)
                        <div class="repeat-row service-row">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="secondary_services[{{ $index }}][title]" class="form-control" value="{{ $item['title'] ?? '' }}">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="secondary_services[{{ $index }}][description]" class="form-control" rows="2">{{ $item['description'] ?? '' }}</textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-light btn-sm remove-repeat">Remove</button>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h5>Principle And Risk Note</h5>
                </div>
                <div class="panel-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="service_principle">Service Principle</label>
                        <textarea name="service_principle" id="service_principle" class="form-control" rows="4">{{ old('service_principle', $page->service_principle) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="risk_disclaimer">Risk Disclaimer</label>
                        <textarea name="risk_disclaimer" id="risk_disclaimer" class="form-control" rows="3">{{ old('risk_disclaimer', $page->risk_disclaimer) }}</textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_published">Published for guests</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Showcase Page
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = {
        requirements: document.querySelectorAll('#requirements .repeat-row').length,
        core_services: document.querySelectorAll('#core_services .repeat-row').length,
        secondary_services: document.querySelectorAll('#secondary_services .repeat-row').length
    };

    function requirementTemplate(index) {
        return `
            <div class="repeat-row requirement-row">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Label</label>
                        <input type="text" name="entry_requirements[${index}][label]" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Value</label>
                        <input type="text" name="entry_requirements[${index}][value]" class="form-control">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="entry_requirements[${index}][description]" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <button type="button" class="btn btn-light btn-sm remove-repeat">Remove</button>
            </div>`;
    }

    function serviceTemplate(group, index) {
        return `
            <div class="repeat-row service-row">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="${group}[${index}][title]" class="form-control">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="${group}[${index}][description]" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <button type="button" class="btn btn-light btn-sm remove-repeat">Remove</button>
            </div>`;
    }

    document.querySelectorAll('.add-repeat').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            const index = counters[this.dataset.target]++;
            const html = this.dataset.type === 'requirement'
                ? requirementTemplate(index)
                : serviceTemplate(this.dataset.target, index);

            target.insertAdjacentHTML('beforeend', html);
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-repeat')) {
            event.target.closest('.repeat-row').remove();
        }
    });
});
</script>

@endsection
