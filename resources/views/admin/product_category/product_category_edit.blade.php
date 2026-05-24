@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Edit Product Category | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Centre</div>
                <h1>Edit Product Category</h1>
                <p>Update a platform category name used by product management and ecommerce catalogue screens.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('all.product.category') }}" class="btn btn-outline-light">
                    <i class="fas fa-tags"></i>
                    Categories
                </a>
            </div>
        </section>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">{{ $productcategory->product_category }}</h2>
                    <p class="commerce-panel__subtitle">Editing category #{{ $productcategory->id }}.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('update.product.category', $productcategory->id) }}" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="product_category">Product Category Name</label>
                        <input name="product_category" class="form-control" type="text" id="product_category" value="{{ old('product_category', $productcategory->product_category) }}" required>
                        @error('product_category')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i>
                            Update Category
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body">
                        <strong>Before Saving</strong>
                        <p class="commerce-muted mb-0">Changing a category name affects how products assigned to this category are labelled.</p>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>
@endsection
