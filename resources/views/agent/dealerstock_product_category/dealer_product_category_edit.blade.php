@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Edit Dealer Product Category | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Stock</div>
                <h1>Edit Dealer Category</h1>
                <p>Update your dealer category name for stock organisation and catalogue clarity.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('all.dealer.product.category') }}" class="btn btn-outline-light">
                    <i class="fas fa-tags"></i>
                    Dealer Categories
                </a>
            </div>
        </section>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">{{ $productcategory->name }}</h2>
                    <p class="commerce-panel__subtitle">Editing dealer category #{{ $productcategory->id }}.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('update.dealer.product.category', $productcategory->id) }}" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="name">Product Category Name</label>
                        <input name="name" class="form-control" type="text" id="name" value="{{ old('name', $productcategory->name) }}" required>
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i>
                            Update Dealer Category
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body">
                        <strong>Before Saving</strong>
                        <p class="commerce-muted mb-0">This category can be reused across your dealer stock records.</p>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>
@endsection
