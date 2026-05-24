@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>My Stock Management | HC Gaming Studio</title>

@php
    $totalDealerStock = $dealerProducts->sum('product_stock');
    $publishedCount = $dealerProducts->where('publish_status', 1)->count();
@endphp

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Stock</div>
                <h1>My Stock Management</h1>
                <p>Manage purchased stock records, dealer pricing, publish status, and product data before listing items to the catalogue.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('all.dealer.product.category') }}" class="btn btn-outline-light">
                    <i class="fas fa-tags"></i>
                    Dealer Categories
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Products</span>
                <strong>{{ $product_index }}</strong>
                <small>Purchased product records</small>
            </div>
            <div class="commerce-stat">
                <span>Total Stock</span>
                <strong>{{ number_format((int) $totalDealerStock) }}</strong>
                <small>Units owned in dealer stock</small>
            </div>
            <div class="commerce-stat">
                <span>Published</span>
                <strong>{{ $publishedCount }}</strong>
                <small>Visible in ecommerce catalogue</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Dealer Product Records</h2>
                    <p class="commerce-panel__subtitle">Unpublished products can be edited. Published products are locked to protect active catalogue consistency.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dealerProductTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dealerProducts as $item)
                            @php
                                $dealerCategory = $item->dealerproductcategory;
                                $platformCategory = $item->productcategory;
                                $categoryName = $dealerCategory && ! $dealerCategory->trashed()
                                    ? $dealerCategory->name
                                    : ($platformCategory && ! $platformCategory->trashed() ? $platformCategory->product_category : 'Uncategorised');
                                $image = $item->product_image ?: optional($item->product)->product_image;
                                $publishStatus = [
                                    '0' => ['label' => 'Pending', 'class' => 'status-pending'],
                                    '1' => ['label' => 'Published', 'class' => 'status-published'],
                                    '2' => ['label' => 'Out of Stock', 'class' => 'status-out'],
                                    '3' => ['label' => 'Withdrawn', 'class' => 'status-withdrawn'],
                                ][(string) $item->publish_status] ?? ['label' => 'Unknown', 'class' => 'status-pending'];
                            @endphp
                            <tr>
                                <td>#{{ $item->id }}</td>
                                <td>
                                    <a href="{{ asset($image ?: 'upload/default.jpg') }}" target="_blank" rel="noopener">
                                        <img src="{{ asset($image ?: 'upload/default.jpg') }}" class="commerce-thumb" alt="{{ $item->product_name }}">
                                    </a>
                                </td>
                                <th>
                                    <div class="commerce-product-name">{{ $item->product_name }}</div>
                                    <div class="commerce-muted">SKU: {{ $item->sku ?: 'N/A' }}</div>
                                </th>
                                <td>{{ $categoryName }}</td>
                                <td><strong>{{ number_format((int) $item->product_stock) }}</strong></td>
                                <td><strong>RM {{ number_format((float) $item->product_price, 2) }}</strong></td>
                                <td><span class="commerce-status {{ $publishStatus['class'] }}">{{ $publishStatus['label'] }}</span></td>
                                <td>
                                    <div class="commerce-actions">
                                        @if((int) $item->publish_status !== 1)
                                            <a href="{{ route('edit.dealer.product', ['id' => $item->id]) }}" class="btn btn-info commerce-icon-btn" title="Edit product">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('delete.dealer.product', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Delete product" id="delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        @endif

                                        @if((string) $item->publish_status === '0')
                                            <a href="{{ route('update.product.to.publish.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Publish product" onclick="return confirm('Do you want to publish this product?')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        @endif
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
            $('#dealerProductTable').DataTable({
                order: [[0, 'desc']],
                columnDefs: [{ orderable: false, targets: [1, 7] }]
            });
        }
    });
</script>
@endsection
