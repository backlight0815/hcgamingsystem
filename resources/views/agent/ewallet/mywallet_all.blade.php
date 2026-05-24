@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>My E-Wallet | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        @include('admin.ecommerce._breadcrumbs')

        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Wallet Centre</div>
                <h1>My E-Wallet</h1>
                <p>Track available balance, approved credits, and top-up requests submitted for administration review.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('add.wallet') }}" class="btn btn-info">
                    <i class="fas fa-plus-circle"></i>
                    Top Up
                </a>
                <a href="{{ route('My.Wallet.History') }}" class="btn btn-outline-light">
                    <i class="fas fa-history"></i>
                    History
                </a>
            </div>
        </section>

        <div class="commerce-stats three">
            <div class="commerce-stat">
                <span>Available Balance</span>
                <strong>RM {{ number_format((float) $totalAmount, 2) }}</strong>
                <small>Usable wallet balance</small>
            </div>
            <div class="commerce-stat">
                <span>Realised Amount</span>
                <strong>RM {{ number_format((float) $approvedTotal, 2) }}</strong>
                <small>Approved by administration</small>
            </div>
            <div class="commerce-stat">
                <span>Unrealised Amount</span>
                <strong>RM {{ number_format((float) $processingTotal, 2) }}</strong>
                <small>Waiting for review</small>
            </div>
        </div>

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Top-Up Requests</h2>
                    <p class="commerce-panel__subtitle">Use this table to monitor every top-up request and payment proof you have submitted.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table id="mywalletrequest" class="table commerce-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ewalletData as $item)
                            @php
                                $walletStatus = [
                                    '0' => ['label' => 'Processing', 'class' => 'status-processing'],
                                    '1' => ['label' => 'Approved', 'class' => 'status-approved'],
                                    '2' => ['label' => 'Delivery', 'class' => 'status-delivery'],
                                    '3' => ['label' => 'Completed', 'class' => 'status-completed'],
                                    '-1' => ['label' => 'Rejected', 'class' => 'status-rejected'],
                                ][(string) $item->status] ?? ['label' => 'Unknown', 'class' => 'status-pending'];
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>RM {{ number_format((float) $item->amount, 2) }}</strong></td>
                                <td>
                                    @if($item->receipt)
                                        <a href="{{ asset($item->receipt) }}" target="_blank" rel="noopener">
                                            <img src="{{ asset($item->receipt) }}" class="commerce-thumb" alt="Payment proof">
                                        </a>
                                    @else
                                        <span class="commerce-muted">No receipt</span>
                                    @endif
                                </td>
                                <td><span class="commerce-status {{ $walletStatus['class'] }}">{{ $walletStatus['label'] }}</span></td>
                                <td>{{ optional($item->created_at)->format('Y-m-d H:i') ?? $item->created_at }}</td>
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
            $('#mywalletrequest').DataTable({
                order: [[4, 'desc']],
                columnDefs: [{ orderable: false, targets: [2] }]
            });
        }
    });
</script>
@endsection
