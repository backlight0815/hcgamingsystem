@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>My Shipping Orders | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Order Centre</div>
                <h1>My Shipping Orders</h1>
                <p>Track your ecommerce orders from processing to delivery and review every item in each order.</p>
            </div>
        </section>

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Orders</span>
                <strong>{{ $orderCount }}</strong>
                <small>Your submitted orders</small>
            </div>
            <div class="commerce-stat">
                <span>Confirmed</span>
                <strong>{{ $ApproveCount }}</strong>
                <small>Approved by seller/admin</small>
            </div>
            <div class="commerce-stat">
                <span>Delivery</span>
                <strong>{{ $DelivertCount }}</strong>
                <small>Currently in delivery workflow</small>
            </div>
            <div class="commerce-stat">
                <span>Completed</span>
                <strong>{{ $ConmpleteCount }}</strong>
                <small>Finished order records</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Order Timeline</h2>
                    <p class="commerce-panel__subtitle">Open details to review the product items attached to each order.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="myshippingorder" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Grand Total</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Payment Proof</th>
                            <th>Transaction Date</th>
                            <th class="text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shippingData as $item)
                            @php
                                $orderStatus = [
                                    '0' => ['label' => 'Processing', 'class' => 'status-processing'],
                                    '1' => ['label' => 'Confirmed', 'class' => 'status-confirmed'],
                                    '2' => ['label' => 'Delivery', 'class' => 'status-delivery'],
                                    '3' => ['label' => 'Completed', 'class' => 'status-completed'],
                                    '-1' => ['label' => 'Rejected', 'class' => 'status-rejected'],
                                ][(string) $item->status] ?? ['label' => 'Unknown', 'class' => 'status-pending'];
                                $itemQuantity = (int) ($item->order_items_sum_quantity ?? $item->orderItems->sum('quantity'));
                            @endphp
                            <tr>
                                <td>#{{ $item->id }}</td>
                                <td><strong>RM {{ number_format((float) $item->total_amount, 2) }}</strong></td>
                                <td>
                                    @if($itemQuantity > 0)
                                        {{ $itemQuantity }}
                                    @else
                                        <span class="commerce-status status-pending">Pending sync</span>
                                    @endif
                                </td>
                                <td><span class="commerce-status {{ $orderStatus['class'] }}">{{ $orderStatus['label'] }}</span></td>
                                <td>
                                    @include('admin.ecommerce._payment_proof_thumb', ['proofPath' => $item->payment_proof])
                                </td>
                                <td>{{ optional($item->created_at)->format('Y-m-d H:i') ?? $item->created_at }}</td>
                                <td>
                                    <div class="commerce-actions">
                                        <button type="button" class="btn btn-info commerce-icon-btn view-order-details" title="View order details" data-id="{{ $item->id }}">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @include('admin.ecommerce._order_details_modal')
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (window.jQuery && $.fn.DataTable) {
            $('#myshippingorder').DataTable({
                order: [[5, 'desc']],
                columnDefs: [{ orderable: false, targets: [4, 6] }]
            });
        }
    });
</script>
@endsection
