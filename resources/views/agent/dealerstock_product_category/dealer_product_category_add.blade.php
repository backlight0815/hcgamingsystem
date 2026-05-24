@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Add Dealer Product Category | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Stock</div>
                <h1>Add Dealer Category</h1>
                <p>Create a category for organising your own dealer stock listings.</p>
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
                    <h2 class="commerce-panel__title">Category Details</h2>
                    <p class="commerce-panel__subtitle">This category is attached to your dealer account and can be used when editing dealer stock.</p>
                </div>
            </div>

            <form method="POST" id="submitproductcategory" action="{{ route('store.dealer.product.category') }}" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="name">Product Category Name</label>
                        <input name="name" class="form-control" type="text" id="name" value="{{ old('name') }}" required>
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info" id="submitButton">
                            <i class="fas fa-save"></i>
                            Save Dealer Category
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body">
                        <strong>Dealer Category</strong>
                        <p class="commerce-muted mb-0">Example: Premium Tools, Books, Limited Items, Training Packages.</p>
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
