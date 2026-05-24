@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Product Details | HC Gaming Studio</title>

@php
    $stock = (int) ($normalizedStock['stock'] ?? 0);
    $roleId = Auth::user()->role_id;
    $priceToShow = $normalizedStock['price'] ?? 0;
    $image = !empty($normalizedStock['image']) ? asset($normalizedStock['image']) : asset('upload/default.jpg');
    $isDealerStock = ($normalizedStock['type'] ?? 'product') === 'dealer';
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

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Catalogue</div>
                <h1>{{ $normalizedStock['name'] }}</h1>
                <p>Review stock, SKU, seller, price, specification, and description before adding this product to cart.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('my.stock') }}" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i>
                    Catalogue
                </a>
                <a href="{{ route('cart.summary') }}" class="btn btn-info">
                    <i class="fas fa-shopping-cart"></i>
                    Cart
                </a>
            </div>
        </section>

        <section class="commerce-panel">
            <div class="commerce-form-grid">
                <div>
                    <div style="position:relative;">
                        <img src="{{ $image }}" alt="{{ $normalizedStock['name'] }}" class="commerce-preview__image" style="border-radius:8px;border:1px solid #dbe3ef;">
                        @if ($stock <= 0)
                            <span class="commerce-status status-out" style="position:absolute;top:14px;left:14px;">Out of Stock</span>
                        @endif
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body commerce-form-section">
                        <div>
                            <span class="commerce-muted">Product</span>
                            <strong>{{ $normalizedStock['name'] }}</strong>
                        </div>

                        <div class="commerce-product-meta">
                            <span>{{ ucfirst($normalizedStock['type'] ?? 'product') }}</span>
                            <span>{{ $normalizedStock['user'] ?? 'Unknown seller' }}</span>
                            <span>{{ number_format($stock) }} units</span>
                        </div>

                        @if ($roleId == 350 || $roleId == 700)
                            <div>
                                <span class="commerce-muted">Price</span>
                                <strong>RM {{ number_format((float) $priceToShow, 2) }}</strong>
                            </div>
                        @endif

                        <form action="{{ route('cart.add') }}" method="POST" id="productDetailsCartForm">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $isDealerStock ? '' : $normalizedStock['id'] }}">
                            <input type="hidden" name="dealer_stock_id" value="{{ $isDealerStock ? $normalizedStock['id'] : '' }}">
                            <input type="hidden" name="submission_type" class="submission-type" value="add-to-cart">
                            <input type="hidden" name="redirect" value="summary">

                            <label for="quantity_{{ $normalizedStock['id'] }}">Quantity</label>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="quantity-control">
                                    <button type="button" class="minus" data-target="quantity_{{ $normalizedStock['id'] }}">-</button>
                                    <input type="number" step="1" min="1" max="{{ max($stock, 1) }}" name="quantity" value="1" id="quantity_{{ $normalizedStock['id'] }}" data-stock="{{ max($stock, 1) }}">
                                    <button type="button" class="plus" data-target="quantity_{{ $normalizedStock['id'] }}">+</button>
                                </div>
                            </div>

                            <div style="display:grid;gap:8px;">
                                @if ($stock <= 0)
                                    <button type="button" class="btn btn-secondary" disabled>Out of Stock</button>
                                @else
                                    <button type="submit" value="add-to-cart" class="btn btn-warning product-action-btn">
                                        <i class="fas fa-cart-plus"></i>
                                        Add to Cart
                                    </button>
                                    <button type="submit" value="buy-now-details" class="btn btn-info product-action-btn">
                                        <i class="fas fa-bolt"></i>
                                        Buy Now
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                </aside>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Stock Availability</span>
                <strong>{{ number_format($stock) }}</strong>
                <small>Units currently available</small>
            </div>
            <div class="commerce-stat">
                <span>Weight</span>
                <strong>{{ $normalizedStock['weight'] ?? 'N/A' }} KG</strong>
                <small>Shipping weight</small>
            </div>
            <div class="commerce-stat">
                <span>SKU</span>
                <strong>{{ $normalizedStock['sku'] ?? 'N/A' }}</strong>
                <small>Product stock keeping unit</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Product Description</h2>
                    <p class="commerce-panel__subtitle">Detailed information provided by the seller or administration.</p>
                </div>
            </div>
            <div class="commerce-muted" style="font-size:15px;line-height:1.75;">
                {!! $normalizedStock['long_description'] ?? 'No description available.' !!}
            </div>
        </section>
    </div>
</div>

<script>
    (function () {
        document.querySelectorAll('.plus, .minus').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var current = parseInt(input.value || 1, 10);
                var max = parseInt(input.dataset.stock || 1, 10);
                input.value = button.classList.contains('plus') ? Math.min(current + 1, max) : Math.max(current - 1, 1);
            });
        });

        document.querySelectorAll('.product-action-btn').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var form = document.getElementById('productDetailsCartForm');
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
