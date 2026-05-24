@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>My Commission | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Commission Centre</div>
                <h1>My Commission</h1>
                <p>Track commission earned from qualified downline sales and use the calculator to estimate future earnings.</p>
            </div>
            <div class="commerce-hero__actions">
                <button type="button" class="btn btn-outline-light" data-toggle="modal" data-target="#tutorialModal">
                    <i class="fas fa-book-open"></i>
                    Guide
                </button>
                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#calculatorModal">
                    <i class="fas fa-calculator"></i>
                    Calculator
                </button>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Total Commission</span>
                <strong>{{ number_format((float) $totalCommission, 2) }} pts</strong>
                <small>Accumulated earning points</small>
            </div>
            <div class="commerce-stat">
                <span>Records</span>
                <strong>{{ $commissions->count() }}</strong>
                <small>Commission events listed below</small>
            </div>
            <div class="commerce-stat">
                <span>Source</span>
                <strong>Downline</strong>
                <small>Qualified direct network orders</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Earning Records</h2>
                    <p class="commerce-panel__subtitle">A clear ledger of the users and orders that generated your commission.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="myCommissionTable" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Downline User</th>
                            <th>Order ID</th>
                            <th>Username</th>
                            <th>Commission Earned</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commissions as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>#{{ $item->downline_user_id }}</td>
                                <td>#{{ $item->order_id }}</td>
                                <th>{{ optional($item->downlineUserbane)->username ?? 'Unknown user' }}</th>
                                <td><strong>{{ number_format((float) $item->commission_amount, 2) }} pts</strong></td>
                                <td>{{ optional($item->updated_at)->format('Y-m-d H:i') ?? $item->updated_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="tutorialModal" tabindex="-1" role="dialog" aria-labelledby="tutorialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tutorialModalLabel">Commission Guide</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="commerce-order-items">
                    <div class="commerce-order-item">
                        <img src="{{ asset('commission_tutorial/Guideline.jpg') }}" alt="Commission guideline">
                        <div>
                            <strong>Direct downline earning</strong>
                            <div class="commerce-muted">Commission is generated from eligible direct downline sales inside your dealership structure.</div>
                        </div>
                    </div>
                    <div class="commerce-order-item">
                        <img src="{{ asset('commission_tutorial/step2.jpg') }}" alt="Commission calculation">
                        <div>
                            <strong>Percentage based calculation</strong>
                            <div class="commerce-muted">Example: a RM 2,000 order at 5% generates 100 commission points. Extra commission applies only when configured and eligible.</div>
                        </div>
                    </div>
                    <div class="commerce-order-item">
                        <img src="{{ asset('commission_tutorial/step3.jpg') }}" alt="Commission accumulation">
                        <div>
                            <strong>Accumulation and conversion</strong>
                            <div class="commerce-muted">Earned points accumulate in your account and can be converted according to the platform's active policy.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="calculatorModal" tabindex="-1" role="dialog" aria-labelledby="calculatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calculatorModalLabel">Commission Calculator</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body commerce-form-section">
                <div>
                    <label for="orderAmount">Order Amount (RM)</label>
                    <input type="number" class="form-control" id="orderAmount" min="0" step="0.01" placeholder="Enter order amount">
                </div>
                <div>
                    <label for="commissionRate">Commission Rate (%)</label>
                    <input type="number" class="form-control" id="commissionRate" min="0" step="0.01" value="5">
                </div>
                <div>
                    <label for="commissionEarn">Estimated Commission (pts)</label>
                    <input type="number" class="form-control" id="commissionEarn" readonly>
                </div>
                <button type="button" class="btn btn-info" id="calculateCommission">
                    <i class="fas fa-calculator"></i>
                    Calculate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (window.jQuery && $.fn.DataTable) {
            $('#myCommissionTable').DataTable({
                order: [[5, 'desc']]
            });
        }

        $('#calculateCommission').on('click', function () {
            var orderAmount = parseFloat($('#orderAmount').val() || 0);
            var commissionRate = parseFloat($('#commissionRate').val() || 0);
            $('#commissionEarn').val(((orderAmount * commissionRate) / 100).toFixed(2));
        });
    });
</script>
@endsection
