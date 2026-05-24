@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Dealer Shipping Orders | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Dealer Fulfilment</div>
                <h1>Buyer Shipping Orders</h1>
                <p>Manage orders placed against your dealer stock, confirm payment proof, and update delivery progress.</p>
            </div>
        </section>

        <div class="commerce-stats">
            <div class="commerce-stat">
                <span>Total Orders</span>
                <strong>{{ $orderCount }}</strong>
                <small>Buyer orders assigned to you</small>
            </div>
            <div class="commerce-stat">
                <span>Confirmed</span>
                <strong>{{ $ApproveCount }}</strong>
                <small>Approved for fulfilment</small>
            </div>
            <div class="commerce-stat">
                <span>Delivery</span>
                <strong>{{ $DeliveryCount }}</strong>
                <small>Delivery stage orders</small>
            </div>
            <div class="commerce-stat">
                <span>Completed</span>
                <strong>{{ $CompleteCount }}</strong>
                <small>Closed buyer orders</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Dealer Fulfilment Queue</h2>
                    <p class="commerce-panel__subtitle">Approve valid orders, reject problematic submissions, and keep buyers updated through status changes.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dealersShippingOrder" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
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
                                <th>{{ optional($item->user)->username ?? 'Unknown buyer' }}</th>
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
                                            <a href="{{ route('update.dealer.shipping.to.approve.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Approve order" onclick="return confirm('Do you want to approve this order?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="{{ route('update.dealer.shipping.to.reject.status', $item->id) }}" class="btn btn-danger commerce-icon-btn" title="Reject order" onclick="return confirm('Do you want to reject this order?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        @elseif((string) $item->status === '1')
                                            <a href="{{ route('update.dealer.shipping.to.delivery.status', $item->id) }}" class="btn btn-primary commerce-icon-btn" title="Send to delivery" onclick="return confirm('Do you want to mark this order as delivery?')">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        @elseif((string) $item->status === '2')
                                            <a href="{{ route('update.dealer.shipping.to.complete.status', $item->id) }}" class="btn btn-success commerce-icon-btn" title="Complete order" onclick="return confirm('Do you want to complete this order?')">
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
            $('#dealersShippingOrder').DataTable({
                order: [[6, 'desc']],
                columnDefs: [{ orderable: false, targets: [5, 7] }]
            });
        }
    });
</script>
@endsection
