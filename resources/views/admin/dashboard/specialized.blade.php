@extends('admin.admin_master')

@section('admin')
@php
    $roleId = (int) auth()->user()->role_id;
    $isAdmin = in_array($roleId, [1, 2], true);
    $isDealer = $backofficeMode === \App\Support\BackofficeExperience::DEALERSHIP;
    $commerceRole = in_array($roleId, [1, 2, 350, 700], true);
    $tradingRole = in_array($roleId, [1, 2, 201, 202, 501, 502, 750, 760, 770], true);
    $signalRole = in_array($roleId, [1, 2, 201, 202, 750, 760, 770], true);
    $metrics = ($isDealer && ! $commerceRole) || (! $isDealer && ! $tradingRole)
        ? [] : ($isAdmin ? ($isDealer ? $dealershipSummary : $tradingSummary) : $dashboardMetrics);
    $inventoryRoute = $isAdmin ? 'all.product' : ($roleId === 350 ? 'all.dealer.products' : 'my.stock');
    $orderRoute = $isAdmin ? 'all.shipping.order' : ($roleId === 350 ? 'all.dealers.shipping.orders' : 'my.shipping.order');
    $signalRoute = in_array($roleId, [1, 201, 202], true) ? 'all.trading.signals' : 'member.signals.dashboard';
@endphp

<div class="page-content bo-dashboard">
    <div class="container-fluid">
        <div class="bo-hero">
            <div class="bo-hero-content">
                <span class="bo-kicker">{{ $isDealer ? 'HC / Commerce operations' : 'HC / Trading operations' }}</span>
                <h1 class="bo-title">{{ $isDealer ? 'Dealership Overview' : 'Trading Hub Control Center' }}</h1>
                <p class="bo-lead mb-0">{{ $isDealer
                    ? 'A clear view of inventory, orders, and the sales workflow for your account.'
                    : 'Published signals, research, and the trading tools available to your account.' }}</p>
                <div class="bo-hero-actions">
                    @if($isDealer && $commerceRole)
                        <a class="bo-button" href="{{ route($inventoryRoute) }}"><i class="ri-box-3-line" aria-hidden="true"></i> Inventory</a>
                        <a class="bo-button bo-button-secondary" href="{{ route($orderRoute) }}"><i class="ri-file-list-3-line" aria-hidden="true"></i> Orders</a>
                    @elseif(! $isDealer)
                        @if($signalRole)
                        <a class="bo-button" href="{{ route($signalRoute) }}"><i class="ri-signal-tower-line" aria-hidden="true"></i> Signal workspace</a>
                        @endif
                        <a class="bo-button bo-button-secondary" href="{{ route('trading.hub.insights') }}"><i class="ri-article-line" aria-hidden="true"></i> Research</a>
                    @endif
                </div>
            </div>
        </div>

        <section aria-labelledby="bo-snapshot-heading">
            <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
                <div><span class="bo-kicker">{{ $isDealer ? 'Business snapshot' : 'Publication snapshot' }}</span><h2 id="bo-snapshot-heading" class="h5 mt-2 mb-0">{{ $isDealer ? 'Your operations at a glance' : 'Recorded platform activity' }}</h2></div>
                <small class="text-muted">From current database records</small>
            </div>
            <div class="bo-stat-grid">
                @forelse($metrics as $metric)
                    <article class="bo-stat">
                        <span class="bo-stat-label">{{ $metric['label'] }}</span>
                        <i class="bo-stat-icon {{ $metric['icon'] }}" aria-hidden="true"></i>
                        <div class="bo-stat-value">{{ $metric['value'] }}</div>
                        <div class="bo-stat-caption">{{ $metric['caption'] }}</div>
                    </article>
                @empty
                    <div class="bo-empty">No metrics are available for this account.</div>
                @endforelse
            </div>
        </section>

        @if($isDealer)
            @if($isAdmin)
                <section class="bo-panel" aria-labelledby="bo-order-flow-heading">
                    <div class="bo-panel-head"><h2 id="bo-order-flow-heading">Platform order flow</h2><a href="{{ route('all.shipping.order') }}">Open order centre <span aria-hidden="true">↗</span></a></div>
                    <div class="bo-panel-body"><div class="bo-pipeline">
                        <div><span>Processing</span><strong>{{ number_format($ProcessingCount) }}</strong></div>
                        <div><span>Confirmed</span><strong>{{ number_format($ApproveCount) }}</strong></div>
                        <div><span>Delivery</span><strong>{{ number_format($DeliveryCount) }}</strong></div>
                        <div><span>Completed</span><strong>{{ number_format($CompleteCount) }}</strong></div>
                    </div></div>
                </section>
            @endif
            @if($commerceRole)
            <div class="bo-panel-grid">
                <section class="bo-panel" aria-labelledby="bo-orders-heading">
                    <div class="bo-panel-head"><h2 id="bo-orders-heading">{{ $isAdmin ? 'Recent platform orders' : 'Recent orders' }}</h2><a href="{{ route($orderRoute) }}">Open order centre <span aria-hidden="true">↗</span></a></div>
                    <div class="bo-panel-body">
                        @if($shippingorders->isNotEmpty())
                            <div class="table-responsive"><table class="table align-middle">
                                <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Value</th><th scope="col">Status</th><th scope="col">Placed</th></tr></thead>
                                <tbody>
                                    @foreach($shippingorders->take(6) as $order)
                                        @php($statusLabels = [-1 => 'Rejected', 0 => 'Processing', 1 => 'Confirmed', 2 => 'Delivery', 3 => 'Completed'])
                                        <tr><td class="fw-semibold">#{{ $order->id }}</td><td>{{ $order->user?->username ?? '—' }}</td><td>RM {{ number_format((float) $order->total_amount, 2) }}</td><td><span class="badge bg-{{ match ((int) $order->status) {-1 => 'danger', 0 => 'secondary', 1 => 'warning', 2 => 'info', 3 => 'success', default => 'secondary'} }}">{{ $statusLabels[(int) $order->status] ?? 'Unknown' }}</span></td><td><time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('d M Y') ?? '—' }}</time></td></tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        @else
                            <div class="bo-empty">No orders are available for this account yet.</div>
                        @endif
                    </div>
                </section>
                <aside class="bo-panel" aria-labelledby="bo-actions-heading">
                    <div class="bo-panel-head"><h2 id="bo-actions-heading">Quick actions</h2></div>
                    <div class="bo-panel-body"><div class="bo-action-list">
                        @forelse($dashboardActions as $action)
                            <a class="bo-action" href="{{ $action['url'] }}"><i class="{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}</a>
                        @empty
                            <div class="bo-empty">No direct actions are configured for this role.</div>
                        @endforelse
                    </div></div>
                </aside>
            </div>
            @endif
        @else
            <div class="bo-panel-grid">
                <section class="bo-panel" aria-labelledby="bo-signals-heading">
                    <div class="bo-panel-head"><h2 id="bo-signals-heading">Recent published signals</h2>@if($signalRole)<a href="{{ route($signalRoute) }}">Signal workspace <span aria-hidden="true">↗</span></a>@endif</div>
                    <div class="bo-panel-body">
                        @forelse($recentSignals as $signal)
                            <div class="bo-row"><div class="bo-row-main"><strong>{{ $signal->trading_pair ?: ($signal->signal_title ?: 'Instrument unavailable') }}</strong><small>{{ strtoupper($signal->immediate_action ?: 'Direction unavailable') }} · <time datetime="{{ $signal->published_at?->toIso8601String() }}">{{ $signal->published_at?->format('d M Y, H:i') ?? 'No publication time' }}</time></small></div><span class="bo-row-value">{{ ucfirst(strtolower($signal->workflow_status ?: 'Unknown')) }}</span></div>
                        @empty
                            <div class="bo-empty">No published signal records are available.</div>
                        @endforelse
                    </div>
                </section>
                <aside class="bo-panel" aria-labelledby="bo-research-heading">
                    <div class="bo-panel-head"><h2 id="bo-research-heading">Latest research</h2><a href="{{ $isAdmin ? route('admin.trading.blogs.index') : route('trading.hub.insights') }}">{{ $isAdmin ? 'Manage articles' : 'Browse insights' }} <span aria-hidden="true">↗</span></a></div>
                    <div class="bo-panel-body">
                        @forelse($recentTradingBlogs as $article)
                            <div class="bo-row"><div class="bo-row-main"><strong>{{ $article->title }}</strong><small>{{ $article->category_label }} · <time datetime="{{ $article->published_at?->toIso8601String() }}">{{ $article->published_at?->format('d M Y') ?? 'No date' }}</time></small></div></div>
                        @empty
                            <div class="bo-empty">No approved website research has been published yet.</div>
                        @endforelse
                    </div>
                </aside>
            </div>
            <section class="bo-panel" aria-labelledby="bo-workflow-heading">
                <div class="bo-panel-head"><h2 id="bo-workflow-heading">Your workspace</h2></div>
                <div class="bo-panel-body"><div class="bo-action-list" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr))">
                    @forelse($dashboardActions as $action)
                        <a class="bo-action" href="{{ $action['url'] }}"><i class="{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}</a>
                    @empty
                        <div class="bo-empty">No direct actions are configured for this role.</div>
                    @endforelse
                </div></div>
            </section>
        @endif
    </div>
</div>
@endsection
