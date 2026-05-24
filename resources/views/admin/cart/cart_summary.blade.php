@extends('admin.admin_master')
@section('admin')

@php
    $breadcrumbData = [
        ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'Shopping Cart', 'url' => route('cart.summary')],
    ];

    $roleId = optional(Auth::user())->role_id;
    $rows = [];
    $total = 0;
    $itemCount = 0;

    foreach ($allCartItems as $item) {
        $isProduct = ($item->type ?? 'product') === 'product';
        $source = $isProduct ? $item->product : $item->dealerStock;
        $price = 0;

        if ($source) {
            $price = ($roleId == 700 && $isProduct && $source->customer_price)
                ? $source->customer_price
                : $source->product_price;
        }

        $quantity = max(1, (int) $item->quantity);
        $subtotal = (float) $price * $quantity;
        $total += $subtotal;
        $itemCount += $quantity;

        $rows[] = [
            'id' => $item->id,
            'type' => $item->type ?? 'product',
            'name' => optional($source)->product_name ?? 'Unavailable product',
            'sku' => optional($source)->sku ?? 'N/A',
            'image' => optional($source)->product_image,
            'stock' => optional($source)->product_stock,
            'price' => (float) $price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'is_available' => (bool) $source,
        ];
    }

    $hasProducts = count($rows) > 0;
    $qrPath = optional($paymentQrCode ?? null)->qr_code ?: optional($paymentQrCode ?? null)->file_path;
    $qrSrc = null;

    if ($qrPath) {
        $qrSrc = \Illuminate\Support\Str::startsWith($qrPath, ['http://', 'https://', 'upload/', 'backend/'])
            ? asset($qrPath)
            : asset('storage/' . $qrPath);
    }
@endphp

<title>Shopping Cart | HC Gaming Studio</title>
@include('admin.ecommerce._styles')

<style>
    .cart-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(360px, .75fr);
        gap: 18px;
        align-items: start;
    }

    .cart-summary-bar {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .cart-summary-bar__item {
        padding: 14px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fafc;
    }

    .cart-summary-bar__item span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .cart-summary-bar__item strong {
        display: block;
        margin-top: 6px;
        color: #0f172a;
        font-size: 20px;
        font-weight: 800;
    }

    .cart-product-cell {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        min-width: 280px;
    }

    .cart-product-cell__image,
    .cart-product-cell__placeholder {
        width: 72px;
        height: 72px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f1f5f9;
        object-fit: cover;
    }

    .cart-product-cell__placeholder {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 22px;
    }

    .cart-product-cell strong {
        display: block;
        color: #0f172a;
        font-weight: 800;
        line-height: 1.35;
    }

    .cart-qty-form {
        display: inline-flex;
        gap: 7px;
        align-items: center;
    }

    .cart-qty-input {
        width: 76px;
        min-height: 38px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        color: #0f172a;
        font-weight: 800;
        text-align: center;
    }

    .cart-total-panel {
        position: sticky;
        top: 92px;
    }

    .cart-total-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #e5edf6;
        color: #475569;
        font-weight: 700;
    }

    .cart-total-row strong {
        color: #0f172a;
        font-size: 22px;
        font-weight: 900;
    }

    .payment-methods {
        display: grid;
        gap: 10px;
        margin: 18px 0;
    }

    .payment-option {
        position: relative;
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 14px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .payment-option:hover {
        border-color: #93c5fd;
        box-shadow: 0 10px 24px rgba(37, 99, 235, .08);
    }

    .payment-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .payment-option__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 20px;
    }

    .payment-option__body strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
    }

    .payment-option__body span {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .payment-option__badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .payment-option:has(input:checked) {
        border-color: #2563eb;
        background: #f8fbff;
        box-shadow: 0 12px 28px rgba(37, 99, 235, .1);
    }

    .payment-option.is-disabled {
        cursor: not-allowed;
        opacity: .72;
    }

    .payment-details {
        display: grid;
        gap: 12px;
        margin-bottom: 18px;
        padding: 14px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fafc;
    }

    .payment-details__qr {
        display: grid;
        grid-template-columns: 130px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
    }

    .payment-qr-frame {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 130px;
        height: 130px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
    }

    .payment-qr-frame img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .payment-qr-placeholder {
        padding: 14px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.4;
        text-align: center;
    }

    .payment-data-list {
        display: grid;
        gap: 8px;
        margin: 0;
    }

    .payment-data-list div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
    }

    .payment-data-list strong {
        color: #0f172a;
        text-align: right;
    }

    .payment-upload {
        padding: 14px;
        border: 1px dashed #bfdbfe;
        border-radius: 8px;
        background: #eff6ff;
    }

    .payment-upload label {
        color: #0f172a;
        font-weight: 900;
    }

    .payment-note {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }

    @media (max-width: 1199px) {
        .cart-layout {
            grid-template-columns: 1fr;
        }

        .cart-total-panel {
            position: static;
        }
    }

    @media (max-width: 767px) {
        .cart-summary-bar,
        .payment-details__qr {
            grid-template-columns: 1fr;
        }

        .payment-option {
            grid-template-columns: 38px minmax(0, 1fr);
        }

        .payment-option__badge {
            grid-column: 2;
            justify-self: start;
        }
    }
</style>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs', ['breadcrumbData' => $breadcrumbData])

        <div class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Ecommerce Checkout</div>
                <h1>Shopping Cart</h1>
                <p>Review cart items, confirm payment method, and upload the payment screenshot before sending the order for administration review.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('home.product') }}" class="btn btn-outline-light">
                    <i class="ri-store-2-line"></i>
                    Continue Shopping
                </a>
                @if($hasProducts)
                    <form action="{{ route('cart.empty') }}" method="post" onsubmit="return confirm('Empty all items from this cart?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-delete-bin-6-line"></i>
                            Empty Cart
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger commerce-alert">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="cart-layout">
            <section class="commerce-panel">
                <div class="commerce-panel__header">
                    <div>
                        <h2 class="commerce-panel__title">Cart Items</h2>
                        <p class="commerce-panel__subtitle">Quantities can be updated here before checkout. Dealer stock and product stock are handled separately.</p>
                    </div>
                </div>

                <div class="cart-summary-bar">
                    <div class="cart-summary-bar__item">
                        <span>Line Items</span>
                        <strong>{{ count($rows) }}</strong>
                    </div>
                    <div class="cart-summary-bar__item">
                        <span>Total Quantity</span>
                        <strong>{{ $itemCount }}</strong>
                    </div>
                    <div class="cart-summary-bar__item">
                        <span>Order Total</span>
                        <strong>RM {{ number_format($total, 2) }}</strong>
                    </div>
                </div>

                @if($hasProducts)
                    <div class="table-responsive">
                        <table class="table commerce-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td>
                                            <div class="cart-product-cell">
                                                @if($row['image'])
                                                    <img src="{{ asset($row['image']) }}" class="cart-product-cell__image" alt="{{ $row['name'] }}">
                                                @else
                                                    <span class="cart-product-cell__placeholder">
                                                        <i class="ri-image-line"></i>
                                                    </span>
                                                @endif
                                                <div>
                                                    <strong>{{ $row['name'] }}</strong>
                                                    <div class="commerce-muted">SKU: {{ $row['sku'] }} | {{ $row['type'] === 'dealer_stock' ? 'Dealer Stock' : 'Product' }}</div>
                                                    @if(! $row['is_available'])
                                                        <div class="text-danger fw-bold mt-1">This product record is no longer available.</div>
                                                    @elseif($row['stock'] !== null)
                                                        <div class="commerce-muted">Available stock: {{ $row['stock'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>RM {{ number_format($row['price'], 2) }}</td>
                                        <td>
                                            <form action="{{ route('cart.update', $row['id']) }}" method="post" class="cart-qty-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="cart_type" value="{{ $row['type'] }}">
                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    value="{{ $row['quantity'] }}"
                                                    min="1"
                                                    @if($row['stock'] !== null) max="{{ $row['stock'] }}" @endif
                                                    class="cart-qty-input"
                                                    aria-label="Quantity for {{ $row['name'] }}"
                                                >
                                                <button type="submit" class="btn btn-outline-primary commerce-icon-btn" title="Update quantity">
                                                    <i class="ri-save-3-line"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-end fw-bold">RM {{ number_format($row['subtotal'], 2) }}</td>
                                        <td class="text-end">
                                            <a
                                                href="{{ route('remove.cart', ['id' => $row['id'], 'type' => $row['type']]) }}"
                                                class="btn btn-outline-danger commerce-icon-btn"
                                                title="Remove item"
                                                onclick="return confirm('Remove this item from the cart?');"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="commerce-empty">
                        Your shopping cart is empty. Add products from the product catalogue before checkout.
                    </div>
                @endif
            </section>

            <aside class="commerce-panel cart-total-panel">
                <div class="commerce-panel__header">
                    <div>
                        <h2 class="commerce-panel__title">Payment Review</h2>
                        <p class="commerce-panel__subtitle">Choose one available manual payment method. FPX is displayed for presentation only.</p>
                    </div>
                </div>

                <div class="cart-total-row">
                    <span>Items</span>
                    <span>{{ $itemCount }}</span>
                </div>
                <div class="cart-total-row">
                    <span>Payment Status</span>
                    <span class="commerce-status status-pending">Pending Review</span>
                </div>
                <div class="cart-total-row">
                    <span>Total Payable</span>
                    <strong>RM {{ number_format($total, 2) }}</strong>
                </div>

                @if($hasProducts)
                    <form action="{{ route('checkout') }}" method="post" enctype="multipart/form-data" id="checkoutForm" class="mt-3">
                        @csrf
                        <input type="hidden" name="total_amount" value="{{ number_format($total, 2, '.', '') }}">

                        <div class="payment-methods" role="radiogroup" aria-label="Payment method">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="qr_code" checked data-method-label="QR Code payment">
                                <span class="payment-option__icon"><i class="ri-qr-code-line"></i></span>
                                <span class="payment-option__body">
                                    <strong>QR Code Payment</strong>
                                    <span>Scan company QR, then upload the paid screenshot.</span>
                                </span>
                                <span class="payment-option__badge">Available</span>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="duitnow_id" data-method-label="DuitNow ID transfer">
                                <span class="payment-option__icon"><i class="ri-bank-card-line"></i></span>
                                <span class="payment-option__body">
                                    <strong>DuitNow ID Transfer</strong>
                                    <span>Transfer to the listed ID or account and upload proof.</span>
                                </span>
                                <span class="payment-option__badge">Available</span>
                            </label>

                            <label class="payment-option is-disabled">
                                <input type="radio" name="payment_method" value="fpx_demo" disabled data-method-label="FPX">
                                <span class="payment-option__icon"><i class="ri-secure-payment-line"></i></span>
                                <span class="payment-option__body">
                                    <strong>FPX Online Banking</strong>
                                    <span>Shown for interface illustration only.</span>
                                </span>
                                <span class="payment-option__badge">Demo</span>
                            </label>
                        </div>

                        <div class="payment-details" id="paymentDetails">
                            <div class="payment-details__qr" id="qrPaymentDetails">
                                <div class="payment-qr-frame">
                                    @if($qrSrc)
                                        <img src="{{ $qrSrc }}" alt="Payment QR code">
                                    @else
                                        <div class="payment-qr-placeholder">QR code will appear here after administration uploads it.</div>
                                    @endif
                                </div>
                                <dl class="payment-data-list">
                                    <div>
                                        <dt>Method</dt>
                                        <dd><strong>QR Code Payment</strong></dd>
                                    </div>
                                    <div>
                                        <dt>Reference</dt>
                                        <dd><strong>Use your order total as amount</strong></dd>
                                    </div>
                                    <div>
                                        <dt>Amount</dt>
                                        <dd><strong>RM {{ number_format($total, 2) }}</strong></dd>
                                    </div>
                                </dl>
                            </div>

                            <dl class="payment-data-list d-none" id="duitnowPaymentDetails">
                                <div>
                                    <dt>DuitNow ID</dt>
                                    <dd><strong>5040011321</strong></dd>
                                </div>
                                <div>
                                    <dt>Account Name</dt>
                                    <dd><strong>Sua Kai Young</strong></dd>
                                </div>
                                <div>
                                    <dt>Amount</dt>
                                    <dd><strong>RM {{ number_format($total, 2) }}</strong></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="payment-upload">
                            <label for="receipt" id="receiptLabel">Upload QR Code payment screenshot</label>
                            <input type="file" name="receipt" id="receipt" class="form-control mt-2" accept=".jpg,.jpeg,.png,.pdf" required>
                            <p class="payment-note">Accepted format: JPG, PNG, or PDF. Maximum file size: 2 MB. Administration will review the proof before confirming the order.</p>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3" id="checkoutButton">
                            <i class="ri-check-double-line"></i>
                            Submit Order for Review
                        </button>
                    </form>
                @else
                    <a href="{{ route('home.product') }}" class="btn btn-primary w-100 mt-3">
                        <i class="ri-store-2-line"></i>
                        Browse Products
                    </a>
                @endif
            </aside>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkoutForm = document.getElementById('checkoutForm');
        const receiptLabel = document.getElementById('receiptLabel');
        const qrDetails = document.getElementById('qrPaymentDetails');
        const duitnowDetails = document.getElementById('duitnowPaymentDetails');
        const methodInputs = document.querySelectorAll('input[name="payment_method"]');

        function refreshPaymentDetails() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            const method = selected ? selected.value : 'qr_code';

            if (qrDetails) {
                qrDetails.classList.toggle('d-none', method !== 'qr_code');
            }

            if (duitnowDetails) {
                duitnowDetails.classList.toggle('d-none', method !== 'duitnow_id');
            }

            if (receiptLabel) {
                receiptLabel.textContent = method === 'duitnow_id'
                    ? 'Upload DuitNow transfer screenshot'
                    : 'Upload QR Code payment screenshot';
            }
        }

        methodInputs.forEach(function (input) {
            input.addEventListener('change', refreshPaymentDetails);
        });

        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function (event) {
                const selected = document.querySelector('input[name="payment_method"]:checked');

                if (selected && selected.value === 'fpx_demo') {
                    event.preventDefault();
                    alert('FPX is currently shown for illustration only. Please choose QR Code or DuitNow ID transfer.');
                }
            });
        }

        refreshPaymentDetails();
    });
</script>

@endsection
