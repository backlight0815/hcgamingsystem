@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Add Product Category | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Centre</div>
                <h1>Add Product Category</h1>
                <p>Create a platform category for product management, stock setup, and catalogue display.</p>
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
                    <h2 class="commerce-panel__title">Category Details</h2>
                    <p class="commerce-panel__subtitle">Use a concise name that administrators and dealers can recognise quickly.</p>
                </div>
            </div>

            <form method="POST" id="submitproductcategory" action="{{ route('store.product.category') }}" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="product_category">Product Category Name</label>
                        <input name="product_category" class="form-control" type="text" id="product_category" value="{{ old('product_category') }}" required>
                        @error('product_category')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info" id="submitButton">
                            <i class="fas fa-save"></i>
                            Save Category
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body">
                        <strong>Category Standard</strong>
                        <p class="commerce-muted mb-0">Example: Trading Books, Merchandise, Account Tools, Training Materials.</p>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('submitproductcategory');
        var button = document.getElementById('submitButton');
        var submitted = false;

        if (form) {
            form.addEventListener('submit', function (event) {
                if (submitted) {
                    event.preventDefault();
                    return;
                }
                submitted = true;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            });
        }
    })();
</script>
@endsection
