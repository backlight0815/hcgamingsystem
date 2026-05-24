@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Add Product | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Centre</div>
                <h1>Add Product</h1>
                <p>Create a professional product listing with clean pricing, category, inventory, SKU, and product image details.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('all.product') }}" class="btn btn-outline-light">
                    <i class="fas fa-boxes"></i>
                    Product List
                </a>
                <a href="{{ route('add.product.category') }}" class="btn btn-info">
                    <i class="fas fa-tag"></i>
                    New Category
                </a>
            </div>
        </section>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Product Information</h2>
                    <p class="commerce-panel__subtitle">Fields marked by validation are required before the product can be published.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('store.product') }}" id="submitproduct" enctype="multipart/form-data" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="product_name">Product Name</label>
                        <input name="product_name" class="form-control" type="text" id="product_name" value="{{ old('product_name') }}" required>
                        @error('product_name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="product_category">Product Category</label>
                        <select name="product_category_id" class="form-select" id="product_category" required>
                            <option value="">Select category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('product_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->product_category }}</option>
                            @endforeach
                        </select>
                        @error('product_category_id')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stock">Stock</label>
                            <input class="form-control" type="number" min="0" id="stock" name="product_stock" value="{{ old('product_stock') }}" required>
                            @error('product_stock')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="weight">Weight (KG)</label>
                            <input class="form-control" type="number" min="0" step="0.01" id="weight" name="weight" value="{{ old('weight') }}" required>
                            @error('weight')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="elm1">Product Description</label>
                        <textarea id="elm1" name="long_description" class="form-control" rows="8">{{ old('long_description') }}</textarea>
                        @error('long_description')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="sku">SKU</label>
                        <input name="sku" class="form-control" type="text" id="sku" value="{{ old('sku') }}" required>
                        @error('sku')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_price">Dealer Price (RM)</label>
                            <input class="form-control" type="number" min="0" step="0.01" id="product_price" name="product_price" value="{{ old('product_price') }}" required>
                            @error('product_price')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_price">Customer Price (RM)</label>
                            <input class="form-control" type="number" min="0" step="0.01" id="customer_price" name="customer_price" value="{{ old('customer_price') }}" required>
                            @error('customer_price')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="image">Product Image</label>
                        <input name="product_image" class="form-control" type="file" id="image" accept="image/*">
                        @error('product_image')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info" id="submitButton">
                            <i class="fas fa-save"></i>
                            Save Product
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <img id="showImages" class="commerce-preview__image" src="{{ asset('upload/default.jpg') }}" alt="Product preview">
                    <div class="commerce-preview__body">
                        <strong>Listing Preview</strong>
                        <p class="commerce-muted mb-0">Use a square or high-resolution product image for the best catalogue presentation.</p>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>

<script>
    (function () {
        var image = document.getElementById('image');
        var preview = document.getElementById('showImages');
        var form = document.getElementById('submitproduct');
        var submitButton = document.getElementById('submitButton');
        var submitted = false;

        if (image && preview) {
            image.addEventListener('change', function (event) {
                var file = event.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function (readerEvent) {
                    preview.src = readerEvent.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (window.tinymce) {
                    tinymce.triggerSave();
                }

                if (submitted) {
                    event.preventDefault();
                    return;
                }

                submitted = true;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            });
        }
    })();
</script>
@endsection
