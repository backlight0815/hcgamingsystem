@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Product Management | HC Gaming Studio</title>

@php
    $totalStock = $product->sum('product_stock');
    $lowStockCount = $product->filter(function ($item) {
        return (int) $item->product_stock <= 5;
    })->count();
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Centre</div>
                <h1>Product Management</h1>
                <p>Create and maintain the platform product catalogue, pricing, stock, category, SKU, and product imagery.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('add.product') }}" class="btn btn-info">
                    <i class="fas fa-plus-circle"></i>
                    Add Product
                </a>
                <a href="{{ route('all.product.category') }}" class="btn btn-outline-light">
                    <i class="fas fa-tags"></i>
                    Categories
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Products</span>
                <strong>{{ $product_index }}</strong>
                <small>Active catalogue records</small>
            </div>
            <div class="commerce-stat">
                <span>Total Stock</span>
                <strong>{{ number_format((int) $totalStock) }}</strong>
                <small>Units available across products</small>
            </div>
            <div class="commerce-stat">
                <span>Low Stock</span>
                <strong>{{ $lowStockCount }}</strong>
                <small>Products at 5 units or below</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Catalogue Records</h2>
                    <p class="commerce-panel__subtitle">Use edit for product content or delete only when a product should be removed from the catalogue.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="productTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Dealer Price</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product as $item)
                            @php
                                $category = $item->productcategory;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ asset($item->product_image ?: 'upload/default.jpg') }}" target="_blank" rel="noopener">
                                        <img src="{{ asset($item->product_image ?: 'upload/default.jpg') }}" class="commerce-thumb" alt="{{ $item->product_name }}">
                                    </a>
                                </td>
                                <th>
                                    <div class="commerce-product-name">{{ $item->product_name }}</div>
                                    <div class="commerce-muted">SKU: {{ $item->sku ?: 'N/A' }}</div>
                                </th>
                                <td>{{ $category && ! $category->trashed() ? $category->product_category : 'Uncategorised' }}</td>
                                <td><strong>{{ number_format((int) $item->product_stock) }}</strong></td>
                                <td><strong>RM {{ number_format((float) $item->product_price, 2) }}</strong></td>
                                <td>
                                    <div class="commerce-actions">
                                        <a href="{{ route('edit.product', $item->id) }}" class="btn btn-info commerce-icon-btn" title="Edit product">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('delete.product', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Delete product" id="delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (window.jQuery && $.fn.DataTable) {
            $('#productTable').DataTable({
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: [1, 6] }]
            });
        }
    });
</script>
@endsection
