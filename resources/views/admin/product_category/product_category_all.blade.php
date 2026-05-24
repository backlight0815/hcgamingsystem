@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Product Category Management | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Product Centre</div>
                <h1>Product Categories</h1>
                <p>Maintain platform product categories so administrators and dealers can classify ecommerce listings cleanly.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('add.product.category') }}" class="btn btn-info">
                    <i class="fas fa-plus-circle"></i>
                    Add Category
                </a>
                <a href="{{ route('all.product') }}" class="btn btn-outline-light">
                    <i class="fas fa-boxes"></i>
                    Products
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Categories</span>
                <strong>{{ $productcategory->count() }}</strong>
                <small>Available for product assignment</small>
            </div>
            <div class="commerce-stat">
                <span>Owner</span>
                <strong>Platform</strong>
                <small>Administration catalogue control</small>
            </div>
            <div class="commerce-stat">
                <span>Usage</span>
                <strong>Products</strong>
                <small>Shown in product management and catalogue</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Category Records</h2>
                    <p class="commerce-panel__subtitle">Keep category names short and consistent for cleaner catalogue filtering.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="productCategoryTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category Name</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productcategory as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <th>{{ $item->product_category }}</th>
                                <td>
                                    <div class="commerce-actions">
                                        <a href="{{ route('edit.product.category', $item->id) }}" class="btn btn-info commerce-icon-btn" title="Edit category">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('delete.product.category', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Delete category" id="delete">
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
            $('#productCategoryTable').DataTable({
                columnDefs: [{ orderable: false, targets: [2] }]
            });
        }
    });
</script>
@endsection
