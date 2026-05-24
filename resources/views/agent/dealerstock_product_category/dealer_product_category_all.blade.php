@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Dealer Product Categories | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Stock</div>
                <h1>Dealer Product Categories</h1>
                <p>Create and manage your own categories for dealer stock listings and product organisation.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('add.dealer.product.category') }}" class="btn btn-info">
                    <i class="fas fa-plus-circle"></i>
                    Add Category
                </a>
                <a href="{{ route('all.dealer.products') }}" class="btn btn-outline-light">
                    <i class="fas fa-boxes"></i>
                    My Stock
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Categories</span>
                <strong>{{ $productcategory->count() }}</strong>
                <small>Your dealer category records</small>
            </div>
            <div class="commerce-stat">
                <span>Owner</span>
                <strong>Dealer</strong>
                <small>Private category control</small>
            </div>
            <div class="commerce-stat">
                <span>Usage</span>
                <strong>Stock</strong>
                <small>Used in dealer product edits</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">My Category Records</h2>
                    <p class="commerce-panel__subtitle">Dealer categories help buyers understand your product grouping after publication.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dealerCategoryTable" class="table commerce-table">
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
                                <th>{{ $item->name }}</th>
                                <td>
                                    <div class="commerce-actions">
                                        <a href="{{ route('edit.dealer.product.category', $item->id) }}" class="btn btn-info commerce-icon-btn" title="Edit category">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('delete.dealer.product.category', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Delete category" id="delete">
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
            $('#dealerCategoryTable').DataTable({
                columnDefs: [{ orderable: false, targets: [2] }]
            });
        }
    });
</script>
@endsection
