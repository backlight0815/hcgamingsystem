@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Shipping Orders | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Order Centre</div>
                <h1>Shipping Orders</h1>
                <p>Review buyer orders, verify payment proof, confirm fulfilment, and move each shipment through the delivery workflow.</p>
            </div>
        </section>

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Orders</span>
                <strong>{{ $shippingordersCount }}</strong>
                <small>All submitted shipping orders</small>
            </div>
            <div class="commerce-stat">
                <span>Confirmed</span>
                <strong>{{ $ApproveCount }}</strong>
                <small>Approved for fulfilment</small>
            </div>
            <div class="commerce-stat">
                <span>Delivery</span>
                <strong>{{ $DeliveryCount }}</strong>
                <small>Marked for delivery</small>
            </div>
            <div class="commerce-stat">
                <span>Completed</span>
                <strong>{{ $CompleteCount }}</strong>
                <small>Closed order records</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Administration Fulfilment Queue</h2>
                    <p class="commerce-panel__subtitle">Use the action buttons to approve, reject, send to delivery, or complete each order.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="shippingorder" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Buyer</th>
                            <th>Grand Total</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Payment Proof</th>
                            <th>Transaction Date</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shippingorders as $item)
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
                                <td>{{ $loop->iteration }}</td>
                                <th>
                                    <div class="commerce-product-name">{{ optional($item->user)->username ?? 'Unknown buyer' }}</div>
                                    <div class="commerce-muted">Order #{{ $item->id }}</div>
                                </th>
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
                                        @if((string) $item->status === '0')
                                            <a href="{{ route('update.shipping.to.approve.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Approve order" onclick="return confirm('Do you want to approve this order?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="{{ route('update.shipping.to.reject.status', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Reject order" onclick="return confirm('Do you want to reject this order?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        @elseif((string) $item->status === '1')
                                            <a href="{{ route('update.shipping.to.delivery.status', $item->id) }}" class="btn btn-primary commerce-icon-btn" title="Send to delivery" onclick="return confirm('Do you want to mark this order as delivery?')">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        @elseif((string) $item->status === '2')
                                            <a href="{{ route('update.shipping.to.complete.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Complete order" onclick="return confirm('Do you want to complete this order?')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        @endif

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
            $('#shippingorder').DataTable({
                order: [[6, 'desc']],
                columnDefs: [{ orderable: false, targets: [5, 7] }]
            });
        }
    });
</script>
@endsection
