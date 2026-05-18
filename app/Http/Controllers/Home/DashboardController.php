<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\DealerStock;
use App\Models\EWalletTransaction;
use App\Models\MarketAnalysis;
use App\Models\order_items;
use App\Models\orders;
use App\Models\Portfolio;
use App\Models\Product;
use App\Models\Referral;
use App\Models\Service;
use App\Models\SignalPerformance;
use App\Models\TradingJournal;
use App\Models\TradingPositionApplication;
use App\Models\TradingSignal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function AllStatistics()
    {
        $user = Auth::user();
        $userId = (int) $user->id;
        $roleId = (int) $user->role_id;
        $tradingEnabled = module_enabled('trading');
        $ecommerceEnabled = module_enabled('dealership_ecommerce');

        $users = User::count();
        $products = Product::count();
        $portfolio = Portfolio::count();
        $service = Service::count();
        $orders = orders::count();

        $salesPerformances = orders::with('user')
            ->where('status', '>=', 1)
            ->whereHas('user', function (Builder $query): void {
                $query->where('role_id', '!=', 700);
            })
            ->selectRaw('user_id, SUM(total_amount) as total_sales')
            ->groupBy('user_id')
            ->orderByDesc('total_sales')
            ->get();

        $agentTotalSales = (float) $salesPerformances->where('user_id', $userId)->sum('total_sales');
        $systemTotalSales = (float) $salesPerformances->sum('total_sales');

        $downlines = Referral::where('upline_user_id', $userId)->with('agent')->get();
        $downlinePurchasedProductsCount = $this->downlinePurchasedProductsCount($userId);

        $ProcessingCount = orders::where('status', '0')->count();
        $ApproveCount = orders::where('status', '1')->count();
        $DeliveryCount = orders::where('status', '2')->count();
        $CompleteCount = orders::where('status', '3')->count();
        $shippingordersCount = orders::count();

        $userstatistics = $roleId === 1 || $roleId === 2
            ? ['total' => $users, 'label' => 'Total Users']
            : ['total' => $downlines->count(), 'label' => 'Total Downlines'];

        $orderstatistics = [
            'total' => $this->orderCountForRole($userId, $roleId),
            'label' => $roleId === 700 ? 'Total Order' : 'Total Orders Received',
        ];

        $productstatistics = [
            'total' => $products,
            'label' => 'Total Product',
        ];

        $salestatistics = [
            'total' => $roleId === 350 ? $agentTotalSales : $systemTotalSales,
            'label' => 'Total Sales',
        ];

        $shippingorders = $this->recentOrdersForRole($userId, $roleId);
        $dashboardProfile = $this->dashboardProfile($roleId);
        $dashboardModules = module_statuses();
        $dashboardMetrics = $this->dashboardMetrics($userId, $roleId, [
            'users' => $users,
            'products' => $products,
            'orders' => $orders,
            'system_total_sales' => $systemTotalSales,
            'agent_total_sales' => $agentTotalSales,
            'downlines' => $downlines->count(),
            'downline_products' => $downlinePurchasedProductsCount,
            'shippingorders_count' => $shippingordersCount,
            'processing_orders' => $ProcessingCount,
            'approved_orders' => $ApproveCount,
            'delivery_orders' => $DeliveryCount,
            'completed_orders' => $CompleteCount,
            'trading_enabled' => $tradingEnabled,
            'ecommerce_enabled' => $ecommerceEnabled,
        ]);
        $dashboardActions = $this->dashboardActions($roleId, $tradingEnabled, $ecommerceEnabled);
        $recentActivity = $this->recentActivity($userId, $roleId, $tradingEnabled, $ecommerceEnabled, $shippingorders);

        return view('admin.dashboard.dashboard', compact(
            'shippingordersCount',
            'shippingorders',
            'ProcessingCount',
            'ApproveCount',
            'DeliveryCount',
            'CompleteCount',
            'users',
            'products',
            'portfolio',
            'service',
            'userstatistics',
            'orderstatistics',
            'productstatistics',
            'salestatistics',
            'salesPerformances',
            'downlinePurchasedProductsCount',
            'dashboardProfile',
            'dashboardModules',
            'dashboardMetrics',
            'dashboardActions',
            'recentActivity',
            'tradingEnabled',
            'ecommerceEnabled'
        ));
    }

    public function TradingStatistics()
    {
        $journals = TradingJournal::select('close_date', 'profit_loss')
            ->orderBy('close_date', 'asc')
            ->get();

        $labels = $journals->pluck('close_date')->map(function ($date) {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        });

        $data = $journals->pluck('profit_loss');

        return view('admin.dashboard.dashboard', compact('labels', 'data'));
    }

    private function dashboardProfile(int $roleId): array
    {
        return match ($roleId) {
            1 => [
                'eyebrow' => 'Admin Workspace',
                'title' => 'Business Control Center',
                'subtitle' => 'Monitor enabled modules, users, sales, trading activity, and operational queues from one focused overview.',
                'accent' => 'primary',
            ],
            2 => [
                'eyebrow' => 'Admin Workspace',
                'title' => 'Operations Dashboard',
                'subtitle' => 'Track platform health, module activity, and team workflows without mixing unrelated tools.',
                'accent' => 'info',
            ],
            350 => [
                'eyebrow' => 'Agent Workspace',
                'title' => 'Dealership Growth Desk',
                'subtitle' => 'Stay close to stock, orders, wallet movement, downlines, and sales performance.',
                'accent' => 'success',
            ],
            750 => [
                'eyebrow' => 'Trader Workspace',
                'title' => 'Trader Performance Desk',
                'subtitle' => 'Review journal performance, risk discipline, active signals, and trading resources.',
                'accent' => 'warning',
            ],
            760 => [
                'eyebrow' => 'Leadership Workspace',
                'title' => 'Leader Development Desk',
                'subtitle' => 'Monitor trading performance, downlines, class uploads, knowledge resources, and client development.',
                'accent' => 'success',
            ],
            770 => [
                'eyebrow' => 'Recruiter Workspace',
                'title' => 'Recruiter Growth Desk',
                'subtitle' => 'Manage referrals, client onboarding, follow-up activity, and trading performance.',
                'accent' => 'info',
            ],
            501 => [
                'eyebrow' => 'Market Analyst Workspace',
                'title' => 'Market Intelligence Desk',
                'subtitle' => 'Prepare analysis, monitor publishing cadence, and keep trader-facing insights current.',
                'accent' => 'purple',
            ],
            201, 202 => [
                'eyebrow' => 'Signal Provider Workspace',
                'title' => $roleId === 202 ? 'Senior Signal Command Desk' : 'Signal Provider Desk',
                'subtitle' => 'Manage signal quality, active calls, performance reporting, and certificate visibility.',
                'accent' => 'danger',
            ],
            700 => [
                'eyebrow' => 'Customer Workspace',
                'title' => 'Order Activity Desk',
                'subtitle' => 'Review orders, wallet history, and product activity in a compact view.',
                'accent' => 'secondary',
            ],
            default => [
                'eyebrow' => 'Workspace',
                'title' => 'Dashboard',
                'subtitle' => 'Your available tools are controlled by role access and enabled business modules.',
                'accent' => 'primary',
            ],
        };
    }

    private function dashboardMetrics(int $userId, int $roleId, array $context): array
    {
        $metrics = [];

        if (in_array($roleId, [1, 2], true)) {
            if ($context['ecommerce_enabled']) {
                $metrics[] = $this->metric('Users', $this->formatNumber($context['users']), 'Total registered accounts', 'ri-user-3-line', 'primary');
                $metrics[] = $this->metric('Orders', $this->formatNumber($context['orders']), 'All dealership orders', 'ri-file-list-3-line', 'success');
                $metrics[] = $this->metric('Sales', $this->formatCurrency($context['system_total_sales']), 'Approved order value', 'ri-money-dollar-circle-line', 'success');
                $metrics[] = $this->metric('Wallet Queue', $this->formatNumber(EWalletTransaction::where('type', 'credit')->count()), 'Approved wallet credits', 'ri-wallet-3-line', 'info');
            }

            if ($context['trading_enabled']) {
                $tradeSummary = $this->tradeSummary();
                $metrics[] = $this->metric('Traders', $this->formatNumber(User::where('role_id', 750)->count()), 'Trader accounts', 'ri-user-star-line', 'warning');
                $metrics[] = $this->metric('Leaders', $this->formatNumber(User::where('role_id', TradingPositionApplication::ROLE_LEADERSHIP)->count()), 'Trading leadership', 'ri-user-follow-line', 'success');
                $metrics[] = $this->metric('Recruiters', $this->formatNumber(User::where('role_id', TradingPositionApplication::ROLE_RECRUITER)->count()), 'Trading recruiters', 'ri-user-add-line', 'info');
                $metrics[] = $this->metric('Active Signals', $this->formatNumber($this->activeSignalsCount()), 'Signals currently in play', 'ri-signal-tower-line', 'danger');
                $metrics[] = $this->metric('Market Analyses', $this->formatNumber(MarketAnalysis::count()), 'Published analysis records', 'ri-line-chart-line', 'info');
                $metrics[] = $this->metric('Trading P/L', $this->formatCurrency($tradeSummary['net_profit_loss']), 'All journal records', 'ri-funds-line', $tradeSummary['net_profit_loss'] >= 0 ? 'success' : 'danger');
            }

            return $metrics;
        }

        if ($roleId === 350) {
            return [
                $this->metric('Downlines', $this->formatNumber($context['downlines']), 'Recruitment network', 'ri-team-line', 'primary'),
                $this->metric('Sales', $this->formatCurrency($context['agent_total_sales']), 'Approved sales under your account', 'ri-money-dollar-circle-line', 'success'),
                $this->metric('Products Launched', $this->formatNumber($context['downline_products']), 'Distinct purchased products', 'ri-box-3-line', 'info'),
                $this->metric('Wallet Balance', $this->formatCurrency($this->walletBalance($userId)), 'Credits minus debits', 'ri-wallet-3-line', 'warning'),
            ];
        }

        if ($roleId === 700) {
            $customerOrders = orders::where('user_id', $userId);

            return [
                $this->metric('Orders', $this->formatNumber((clone $customerOrders)->count()), 'Orders submitted', 'ri-shopping-cart-line', 'primary'),
                $this->metric('Completed', $this->formatNumber((clone $customerOrders)->where('status', 3)->count()), 'Completed orders', 'ri-checkbox-circle-line', 'success'),
                $this->metric('Spend', $this->formatCurrency((clone $customerOrders)->where('status', '>=', 1)->sum('total_amount')), 'Confirmed order value', 'ri-money-dollar-circle-line', 'info'),
                $this->metric('Wallet Balance', $this->formatCurrency($this->walletBalance($userId)), 'Credits minus debits', 'ri-wallet-3-line', 'warning'),
            ];
        }

        if (in_array($roleId, [750, 760, 770], true)) {
            $tradeSummary = $this->tradeSummary($userId);

            return [
                $this->metric('Trades', $this->formatNumber($tradeSummary['total_trades']), 'Journal records', 'ri-file-chart-line', 'primary'),
                $this->metric('Net P/L', $this->formatCurrency($tradeSummary['net_profit_loss']), 'Closed trade performance', 'ri-funds-line', $tradeSummary['net_profit_loss'] >= 0 ? 'success' : 'danger'),
                $this->metric('Win Rate', $tradeSummary['win_rate'] . '%', 'Winning trades against closed trades', 'ri-percent-line', 'info'),
                $this->metric(in_array($roleId, [760, 770], true) ? 'Downlines' : 'Active Signals', in_array($roleId, [760, 770], true) ? $this->formatNumber($context['downlines']) : $this->formatNumber($this->activeSignalsCount()), in_array($roleId, [760, 770], true) ? 'Direct trading referrals' : 'Market calls to monitor', in_array($roleId, [760, 770], true) ? 'ri-team-line' : 'ri-signal-tower-line', 'warning'),
            ];
        }

        if ($roleId === 501) {
            return [
                $this->metric('Analyses', $this->formatNumber(MarketAnalysis::count()), 'Total market outlooks', 'ri-line-chart-line', 'primary'),
                $this->metric('This Month', $this->formatNumber(MarketAnalysis::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()), 'Fresh analysis cadence', 'ri-calendar-check-line', 'success'),
                $this->metric('Discord Sent', $this->formatNumber(MarketAnalysis::where('discord_sent', true)->count()), 'Distributed insights', 'ri-discord-line', 'info'),
                $this->metric('Trader View', $this->formatNumber(MarketAnalysis::latest()->take(10)->count()), 'Latest visible analyses', 'ri-eye-line', 'warning'),
            ];
        }

        if (in_array($roleId, [201, 202], true)) {
            $signalSummary = $this->signalSummary($userId);

            return [
                $this->metric('Signals', $this->formatNumber($signalSummary['total']), 'Signals created by you', 'ri-signal-tower-line', 'primary'),
                $this->metric('Active', $this->formatNumber($signalSummary['active']), 'Signals currently active', 'ri-pulse-line', 'success'),
                $this->metric('Closed', $this->formatNumber($signalSummary['closed']), 'Completed or closed signals', 'ri-checkbox-circle-line', 'info'),
                $this->metric('Performance Pips', $this->formatNumber($signalSummary['profit_pips']), 'Reported performance', 'ri-bar-chart-2-line', $signalSummary['profit_pips'] >= 0 ? 'success' : 'danger'),
            ];
        }

        return [
            $this->metric('Trading Module', module_enabled('trading') ? 'On' : 'Off', 'Configured by admin', 'ri-line-chart-line', module_enabled('trading') ? 'success' : 'secondary'),
            $this->metric('E-Commerce Module', module_enabled('dealership_ecommerce') ? 'On' : 'Off', 'Configured by admin', 'ri-store-2-line', module_enabled('dealership_ecommerce') ? 'success' : 'secondary'),
        ];
    }

    private function dashboardActions(int $roleId, bool $tradingEnabled, bool $ecommerceEnabled): array
    {
        $actions = [];

        if (in_array($roleId, [1, 2], true)) {
            $actions[] = $this->action('Configure Modules', 'admin.features.index', 'ri-toggle-line');
            $actions[] = $this->action('Manage Accounts', 'all.account', 'ri-user-settings-line');

            if ($tradingEnabled) {
                $actions[] = $this->action('Trading Statistics', 'all.trading.statistics', 'ri-file-chart-line');
                $actions[] = $this->action('Add Market Analysis', 'market-analyst.create', 'ri-line-chart-line');
            }

            if ($ecommerceEnabled) {
                $actions[] = $this->action('Shipping Orders', 'all.shipping.order', 'ri-truck-line');
                $actions[] = $this->action('Product Management', 'all.product', 'ri-box-3-line');
            }

            return $this->cleanActions($actions);
        }

        if ($roleId === 350 && $ecommerceEnabled) {
            return $this->cleanActions([
                $this->action('Product Catalogue', 'my.stock', 'ri-shopping-bag-3-line'),
                $this->action('Stock Management', 'all.dealer.products', 'ri-stack-line'),
                $this->action('My Wallet', 'My.Wallet', 'ri-wallet-3-line'),
                $this->action('My Commission', 'My.Commission', 'ri-hand-coin-line'),
            ]);
        }

        if ($roleId === 700 && $ecommerceEnabled) {
            return $this->cleanActions([
                $this->action('Product Catalogue', 'my.stock', 'ri-shopping-bag-3-line'),
                $this->action('My Orders', 'my.shipping.order', 'ri-shopping-cart-line'),
                $this->action('My Wallet', 'My.Wallet', 'ri-wallet-3-line'),
            ]);
        }

        if (in_array($roleId, [750, 760, 770], true) && $tradingEnabled) {
            return $this->cleanActions([
                $this->action('Trading Journal', 'all.trading.journals', 'ri-file-chart-line'),
                $this->action('Trading Statistics', 'all.trading.statistics', 'ri-funds-line'),
                $this->action('Position Centre', 'trading.positions.index', 'ri-user-star-line'),
                $this->action('Active Signals', 'member.signals.active', 'ri-signal-tower-line'),
                $this->action('Market Analyst', 'trading.market-analyst.index', 'ri-line-chart-line'),
                $roleId === 760 ? $this->action('Upload Classes', 'admin.trading.recordings.index', 'ri-video-upload-line') : null,
            ]);
        }

        if ($roleId === 501 && $tradingEnabled) {
            return $this->cleanActions([
                $this->action('All Analyses', 'market-analyst.index', 'ri-line-chart-line'),
                $this->action('Add Analysis', 'market-analyst.create', 'ri-add-circle-line'),
                $this->action('Trader View', 'trading.market-analyst.index', 'ri-eye-line'),
            ]);
        }

        if (in_array($roleId, [201, 202], true) && $tradingEnabled) {
            return $this->cleanActions([
                $this->action('All Signals', 'all.trading.signals', 'ri-signal-tower-line'),
                $this->action('Add Signal', 'add.trading.signal', 'ri-add-circle-line'),
                $this->action('Signal Performance', 'signal.performance.index', 'ri-bar-chart-2-line'),
                $this->action('My Certificates', 'provider.certificate.index', 'ri-medal-line'),
            ]);
        }

        return [];
    }

    private function recentActivity(int $userId, int $roleId, bool $tradingEnabled, bool $ecommerceEnabled, Collection $shippingorders): array
    {
        if ($tradingEnabled && in_array($roleId, [750, 760, 770], true)) {
            return TradingJournal::where('user_id', $userId)
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (TradingJournal $trade): array => [
                    'title' => strtoupper($trade->pair ?? 'Trading Journal'),
                    'meta' => optional($trade->close_date ?? $trade->created_at)->format('d M Y') ?? 'No date',
                    'value' => $this->formatCurrency((float) ($trade->profit_loss ?? 0)),
                    'tone' => (float) ($trade->profit_loss ?? 0) >= 0 ? 'success' : 'danger',
                ])
                ->all();
        }

        if ($tradingEnabled && in_array($roleId, [201, 202], true)) {
            return TradingSignal::where('user_id', $userId)
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (TradingSignal $signal): array => [
                    'title' => $signal->signal_code ?: ($signal->trading_pair ?? 'Trading Signal'),
                    'meta' => $signal->trading_pair ?: 'Signal',
                    'value' => $signal->progress,
                    'tone' => (int) $signal->status === 1 ? 'success' : 'secondary',
                ])
                ->all();
        }

        if ($tradingEnabled && $roleId === 501) {
            return MarketAnalysis::latest()
                ->take(6)
                ->get()
                ->map(fn (MarketAnalysis $analysis): array => [
                    'title' => $analysis->title,
                    'meta' => optional($analysis->analysis_date ?? $analysis->created_at)->format('d M Y') ?? 'No date',
                    'value' => $analysis->discord_sent ? 'Sent' : 'Draft',
                    'tone' => $analysis->discord_sent ? 'success' : 'warning',
                ])
                ->all();
        }

        if ($ecommerceEnabled && $shippingorders->isNotEmpty()) {
            return $shippingorders
                ->take(6)
                ->map(fn (orders $order): array => [
                    'title' => 'Order #' . $order->id,
                    'meta' => optional($order->created_at)->format('d M Y, h:i A') ?? 'No date',
                    'value' => $this->orderStatusLabel((int) $order->status),
                    'tone' => $this->orderStatusTone((int) $order->status),
                ])
                ->all();
        }

        if ($tradingEnabled) {
            return TradingSignal::latest()
                ->take(6)
                ->get()
                ->map(fn (TradingSignal $signal): array => [
                    'title' => $signal->signal_code ?: ($signal->trading_pair ?? 'Trading Signal'),
                    'meta' => $signal->trading_pair ?: 'Signal',
                    'value' => $signal->progress,
                    'tone' => (int) $signal->status === 1 ? 'success' : 'secondary',
                ])
                ->all();
        }

        return [];
    }

    private function downlinePurchasedProductsCount(int $userId): int
    {
        $downlineOrderIds = orders::where('user_id', $userId)
            ->where('status', '3')
            ->pluck('id');

        $dealerStockProductIds = DealerStock::where('user_id', $userId)
            ->pluck('product_id');

        return order_items::whereIn('order_id', $downlineOrderIds)
            ->whereIn('product_id', $dealerStockProductIds)
            ->distinct('product_id')
            ->count('product_id');
    }

    private function orderCountForRole(int $userId, int $roleId): int
    {
        if (in_array($roleId, [1, 2], true)) {
            return orders::count();
        }

        if ($roleId === 700) {
            return orders::where('user_id', $userId)->count();
        }

        $sellerProducts = Product::where('user_id', $userId)->pluck('id');

        return order_items::whereIn('product_id', $sellerProducts)
            ->pluck('order_id')
            ->unique()
            ->count();
    }

    private function recentOrdersForRole(int $userId, int $roleId): Collection
    {
        if (in_array($roleId, [1, 2], true)) {
            return orders::with(['user', 'orderItems'])
                ->withSum('orderItems', 'quantity')
                ->latest()
                ->take(15)
                ->get();
        }

        if ($roleId === 700) {
            return orders::where('user_id', $userId)
                ->with(['user', 'orderItems'])
                ->withSum('orderItems', 'quantity')
                ->latest()
                ->take(15)
                ->get();
        }

        $sellerProducts = Product::where('user_id', $userId)->pluck('id');
        $orderIds = order_items::whereIn('product_id', $sellerProducts)
            ->pluck('order_id')
            ->unique()
            ->toArray();

        return orders::whereIn('id', $orderIds)
            ->with(['user', 'orderItems'])
            ->withSum('orderItems', 'quantity')
            ->latest()
            ->take(15)
            ->get();
    }

    private function tradeSummary(?int $userId = null): array
    {
        $baseQuery = TradingJournal::query()
            ->where(function (Builder $query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            });

        if ($userId) {
            $baseQuery->where('user_id', $userId);
        }

        $totalTrades = (clone $baseQuery)->count();
        $wins = (clone $baseQuery)->where('profit_loss', '>', 0)->count();
        $losses = (clone $baseQuery)->where('profit_loss', '<', 0)->count();
        $closedTrades = $wins + $losses;

        return [
            'total_trades' => $totalTrades,
            'net_profit_loss' => (float) (clone $baseQuery)->sum('profit_loss'),
            'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 1) : 0,
        ];
    }

    private function signalSummary(int $userId): array
    {
        $baseQuery = TradingSignal::where('user_id', $userId);

        $doneColumn = $this->signalDoneColumn();

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 1)->count(),
            'closed' => (clone $baseQuery)
                ->where(function (Builder $query) use ($doneColumn): void {
                    if ($doneColumn) {
                        $query->where($doneColumn, 1)->orWhereIn('status', [12, 13, 14]);

                        return;
                    }

                    $query->whereIn('status', [12, 13, 14]);
                })
                ->count(),
            'profit_pips' => (float) SignalPerformance::whereHas('signal', function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId);
            })->sum('profit_pips'),
        ];
    }

    private function activeSignalsCount(): int
    {
        $query = TradingSignal::where('status', 1);
        $doneColumn = $this->signalDoneColumn();

        if ($doneColumn) {
            $query->where(function (Builder $query) use ($doneColumn): void {
                $query->where($doneColumn, 0)->orWhereNull($doneColumn);
            });
        }

        return $query->count();
    }

    private function signalDoneColumn(): ?string
    {
        foreach (['is_done', 'IsDone'] as $column) {
            if (Schema::hasColumn('trading_signals', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function walletBalance(int $userId): float
    {
        $totalIncome = EWalletTransaction::where('user_id', $userId)
            ->where('type', 'credit')
            ->sum('amount');

        $totalExpenses = EWalletTransaction::where('user_id', $userId)
            ->where('type', 'debit')
            ->sum('amount');

        return (float) $totalIncome - (float) $totalExpenses;
    }

    private function metric(string $label, string $value, string $caption, string $icon, string $tone): array
    {
        return compact('label', 'value', 'caption', 'icon', 'tone');
    }

    private function action(string $label, string $routeName, string $icon): ?array
    {
        if (!Route::has($routeName)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => route($routeName),
            'icon' => $icon,
        ];
    }

    private function cleanActions(array $actions): array
    {
        return array_values(array_filter($actions));
    }

    private function formatCurrency(float $value): string
    {
        return 'RM ' . number_format($value, 2);
    }

    private function formatNumber(float|int $value): string
    {
        return number_format((float) $value, is_float($value) && floor($value) !== $value ? 2 : 0);
    }

    private function orderStatusLabel(int $status): string
    {
        return match ($status) {
            -1 => 'Rejected',
            0 => 'Processing',
            1 => 'Confirmed',
            2 => 'Delivery',
            3 => 'Completed',
            default => 'Unknown',
        };
    }

    private function orderStatusTone(int $status): string
    {
        return match ($status) {
            -1 => 'danger',
            0 => 'secondary',
            1 => 'warning',
            2 => 'info',
            3 => 'success',
            default => 'secondary',
        };
    }
}
