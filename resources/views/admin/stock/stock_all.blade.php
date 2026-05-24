@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Product Catalogue | HC Gaming Studio</title>

@php
    $visibleProducts = $mergedData->count();
    $availableProducts = $mergedData->filter(function ($item) {
        $stock = isset($item->product_stock) ? $item->product_stock : ($item->stock ?? 0);
        return (int) $stock > 0;
    })->count();
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger commerce-alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Catalogue</div>
                <h1>Ecommerce Product Catalogue</h1>
                <p>Browse available platform and dealer products, check seller details, and add items to cart with live stock limits.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('cart.summary') }}" class="btn btn-info">
                    <i class="fas fa-shopping-cart"></i>
                    Cart ({{ session('cartTotal', 0) }})
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Catalogue Items</span>
                <strong>{{ $visibleProducts }}</strong>
                <small>Platform and dealer listings</small>
            </div>
            <div class="commerce-stat">
                <span>Available Now</span>
                <strong>{{ $availableProducts }}</strong>
                <small>Products with stock above zero</small>
            </div>
            <div class="commerce-stat">
                <span>Cart Items</span>
                <strong>{{ session('cartTotal', 0) }}</strong>
                <small>Items currently in cart</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-catalogue-toolbar">
                <div>
                    <h2 class="commerce-panel__title">Available Products</h2>
                    <p class="commerce-panel__subtitle">Quantity controls are capped by live stock for each listing.</p>
                </div>
                <a href="{{ route('cart.summary') }}" class="commerce-cart-pill">
                    <i class="fas fa-shopping-cart"></i>
                    View Cart
                </a>
            </div>

            @if ($mergedData->isEmpty())
                <div class="commerce-empty">No products are available yet. Please check back after administration uploads the catalogue.</div>
            @else
                <div class="commerce-catalogue-grid">
                    @foreach($mergedData as $item)
                        @php
                            $stock = isset($item->product_stock) ? $item->product_stock : ($item->stock ?? 0);
                            $roleId = Auth::user()->role_id;
                            $priceToShow = $roleId == 700 && $item->customer_price ? $item->customer_price : $item->product_price;
                            $category = $item->productcategory ?? null;
                            $seller = optional($item->user)->username ?? 'HC Gaming';
                            $image = $item->product_image ? asset($item->product_image) : asset('upload/default.jpg');
                        @endphp

                        <article class="commerce-product-card">
                            <a class="commerce-product-card__image" href="{{ url('product_details', $item->id) }}">
                                <img src="{{ $image }}" alt="{{ $item->product_name }}">
                                @if((int) $stock <= 0)
                                    <span class="commerce-status status-out" style="position:absolute;top:12px;left:12px;">Out of Stock</span>
                                @endif
                            </a>

                            <div class="commerce-product-card__body">
                                <h3>{{ $item->product_name }}</h3>
                                <div class="commerce-product-meta">
                                    <span>{{ $category && ! $category->trashed() ? $category->product_category : 'Uncategorised' }}</span>
                                    <span>{{ $seller }}</span>
                                    <span>{{ number_format((int) $stock) }} units</span>
                                </div>

                                @if ($roleId == 350 || $roleId == 700)
                                    <div>
                                        <div class="commerce-muted">Price</div>
                                        <strong>RM {{ number_format((float) $priceToShow, 2) }}</strong>
                                    </div>
                                @endif

                                <form action="{{ route('cart.add') }}" method="POST" class="mt-auto catalogue-cart-form">
                                    @csrf
                                    <input type="hidden" name="submission_type" class="submission-type" value="buy-now-details">
                                    <input type="hidden" name="product_id" value="{{ isset($item->product_id) ? $item->product_id : '' }}">
                                    <input type="hidden" name="dealer_stock_id" value="{{ isset($item->dealer_stock_id) ? $item->dealer_stock_id : '' }}">

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="commerce-muted font-weight-bold">Quantity</span>
                                        <div class="quantity-control">
                                            <button type="button" class="minus" data-target="quantity_{{ $item->type }}_{{ $item->id }}">-</button>
                                            <input type="number" step="1" min="1" max="{{ max((int) $stock, 1) }}" name="quantity" value="1" id="quantity_{{ $item->type }}_{{ $item->id }}" data-stock="{{ max((int) $stock, 1) }}">
                                            <button type="button" class="plus" data-target="quantity_{{ $item->type }}_{{ $item->id }}">+</button>
                                        </div>
                                    </div>

                                    <div class="d-grid" style="display:grid;gap:8px;">
                                        @if ((int) $stock <= 0)
                                            <button type="button" class="btn btn-secondary" disabled>Out of Stock</button>
                                            <button type="button" class="btn btn-light" disabled>Unavailable</button>
                                        @else
                                            <button type="submit" name="submission_type" value="add-to-cart" class="btn btn-warning add-to-cart-btn">
                                                <i class="fas fa-cart-plus"></i>
                                                Add to Cart
                                            </button>
                                            <input type="hidden" name="redirect" value="summary">
                                            <button type="submit" name="submission_type" value="buy-now-details" class="btn btn-info buy-now-btn">
                                                <i class="fas fa-bolt"></i>
                                                Buy Now
                                            </button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>

<script>
    (function () {
        document.querySelectorAll('.plus').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var maxStock = parseInt(input.dataset.stock, 10);
                var quantity = parseInt(input.value || 1, 10);
                if (quantity < maxStock) {
                    input.value = quantity + 1;
                }
            });
        });

        document.querySelectorAll('.minus').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var quantity = parseInt(input.value || 1, 10);
                if (quantity > 1) {
                    input.value = quantity - 1;
                }
            });
        });

        document.querySelectorAll('.buy-now-btn, .add-to-cart-btn').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var form = button.closest('form');
                form.querySelector('.submission-type').value = button.value;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                setTimeout(function () {
                    form.submit();
                }, 300);
            });
        });
    })();
</script>
@endsection
