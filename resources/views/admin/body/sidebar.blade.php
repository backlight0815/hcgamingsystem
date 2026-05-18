@php
    $user = auth()->user();
    $roleId = (int) ($user->role_id ?? 0);
    $adminData = $user;
    $tradingEnabled = module_enabled('trading');
    $ecommerceEnabled = module_enabled('dealership_ecommerce');
    $traderOnboardingLocked = false;
    $traderOnboardingApplication = null;

    if ($roleId === 750 && \Illuminate\Support\Facades\Schema::hasTable('trader_onboarding_applications')) {
        $traderOnboardingApplication = \App\Models\TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();
        $traderOnboardingLocked = ! ($traderOnboardingApplication && $traderOnboardingApplication->isApproved());
    }

    $routeUrl = function (string $routeName, array $params = []) {
        return \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName, $params) : '#';
    };

    $roleIn = fn (array $roles): bool => in_array($roleId, $roles, true);
    $visibleItems = fn (array $items): array => array_values(array_filter($items));
    $menus = [];

    $menus[] = ['heading' => 'Menu'];

    if ($roleIn([1, 2, 350, 700, 750, 760, 770, 501, 201, 202])) {
        $menus[] = [
            'label' => 'Dashboard',
            'icon' => 'ri-dashboard-line',
            'url' => $routeUrl('all.statistics'),
        ];
    }

    if ($roleIn([1, 2])) {
        $menus[] = [
            'label' => 'Company Setting',
            'icon' => 'ri-toggle-line',
            'children' => [
                ['label' => 'Module & Feature Config', 'url' => $routeUrl('admin.features.index')],
                ['label' => 'Manage Feature', 'url' => $routeUrl('all.features')],
                ['label' => 'Manage Role', 'url' => $routeUrl('all.roles')],
            ],
        ];

        $menus[] = [
            'label' => 'Account Management',
            'icon' => 'ri-user-settings-line',
            'children' => $visibleItems([
                ['label' => 'All Account Details', 'url' => $routeUrl('all.account')],
                $roleId === 1 ? ['label' => 'Admin Management', 'url' => $routeUrl('all.admin.account')] : null,
                $ecommerceEnabled ? ['label' => 'Agent Management', 'url' => $routeUrl('all.agent.account')] : null,
                $ecommerceEnabled ? ['label' => 'Customer Management', 'url' => $routeUrl('all.customer.account')] : null,
                $tradingEnabled ? ['label' => 'Traders Management', 'url' => $routeUrl('all.traders.account')] : null,
                $tradingEnabled ? ['label' => 'Trader Verification Reviews', 'url' => $routeUrl('admin.trader_onboarding.index')] : null,
                $tradingEnabled ? ['label' => 'Trading Position Reviews', 'url' => $routeUrl('admin.trading_positions.index')] : null,
            ]),
        ];
    }

    if ($tradingEnabled) {
        $menus[] = ['heading' => 'Trading'];

        if ($roleIn([1, 2, 760, 770])) {
            $menus[] = [
                'label' => 'Marketing Resources',
                'icon' => 'ri-folder-download-line',
                'children' => $visibleItems([
                    $roleIn([1, 2]) ? ['label' => 'Manage Resources', 'url' => $routeUrl('admin.marketing.resources.index')] : null,
                    $roleIn([1, 2]) ? ['label' => 'Upload Resource', 'url' => $routeUrl('admin.marketing.resources.create')] : null,
                    $roleIn([760, 770]) ? ['label' => 'Resource Library', 'url' => $routeUrl('marketing.resources.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2, 201, 202, 501, 502, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Support Tickets',
                'icon' => 'ri-customer-service-2-line',
                'children' => [
                    ['label' => $roleIn([1, 2]) ? 'Ticket Desk' : 'My Tickets', 'url' => $routeUrl('support.tickets.index')],
                    ['label' => 'Open Ticket', 'url' => $routeUrl('support.tickets.create')],
                ],
            ];
        }

        if ($roleIn([1, 2, 201, 202, 501, 502, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Appointments',
                'icon' => 'ri-calendar-check-line',
                'children' => $visibleItems([
                    ['label' => 'My Appointments', 'url' => $routeUrl('trading.appointments.index')],
                    $roleIn([1, 2]) ? ['label' => 'Manage Slots', 'url' => $routeUrl('admin.trading.appointments.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Trading Exams',
                'icon' => 'ri-question-answer-line',
                'children' => $visibleItems([
                    $roleIn([750, 760, 770]) ? ['label' => 'Daily Exam', 'url' => $routeUrl('trading.exams.index')] : null,
                    $roleIn([1, 2, 760]) ? ['label' => 'Question Bank', 'url' => $routeUrl('admin.trading.exams.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2, 201, 202, 501, 502, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Notifications',
                'icon' => 'ri-notification-3-line',
                'children' => $visibleItems([
                    ['label' => 'Inbox', 'url' => $routeUrl('notifications.index')],
                    $roleIn([1, 2]) ? ['label' => 'Manage Notifications', 'url' => $routeUrl('admin.notifications.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2])) {
            $menus[] = [
                'label' => 'Community',
                'icon' => 'ri-team-line',
                'children' => [
                    ['label' => 'All Communities', 'url' => $routeUrl('communities.index')],
                    ['label' => 'Add New Community', 'url' => $routeUrl('communities.create')],
                    ['label' => 'Community Documents', 'url' => $routeUrl('communities.documents.index')],
                    ['label' => 'Community Showcase', 'url' => $routeUrl('admin.community.showcase.edit')],
                ],
            ];

            $menus[] = [
                'label' => 'Trading News',
                'icon' => 'ri-newspaper-line',
                'children' => [
                    ['label' => 'All News', 'url' => $routeUrl('trading.news.index')],
                    ['label' => 'Add News', 'url' => $routeUrl('trading.news.create')],
                ],
            ];
        }

        if ($roleIn([1, 201, 202])) {
            $menus[] = [
                'label' => 'Signal Provider Desk',
                'icon' => 'ri-bar-chart-line',
                'children' => [
                    ['label' => 'All Signals', 'url' => $routeUrl('all.trading.signals')],
                    ['label' => 'Add Signal', 'url' => $routeUrl('add.trading.signal')],
                    ['label' => 'Trading Reasons', 'url' => $routeUrl('all.trading.reason')],
                    ['label' => 'Signal Performance', 'url' => $routeUrl('signal.performance.index')],
                ],
            ];
        }

        if ($roleIn([1, 2])) {
            $menus[] = [
                'label' => 'Trading Certificates',
                'icon' => 'ri-award-line',
                'children' => [
                    ['label' => 'All Certificates', 'url' => $routeUrl('certificate.index')],
                    ['label' => 'Generate Certificate', 'url' => $routeUrl('certificate.create')],
                ],
            ];
        }

        if ($roleIn([1, 2, 201, 202, 501, 750, 760, 770])) {
            $menus[] = [
                'label' => 'My Certificates',
                'icon' => 'ri-medal-line',
                'children' => [
                    ['label' => 'All Certificates', 'url' => $routeUrl('provider.certificate.index')],
                ],
            ];
        }

        if ($roleIn([1, 2, 760])) {
            $menus[] = [
                'label' => 'Knowledge Centre',
                'icon' => 'ri-book-open-line',
                'children' => $visibleItems([
                    ['label' => 'All Knowledge', 'url' => $routeUrl('knowledge.centre.index')],
                    ['label' => 'Add Knowledge', 'url' => $routeUrl('knowledge.centre.create')],
                    $roleIn([750, 760, 770]) ? ['label' => 'Trader View', 'url' => $routeUrl('trading.knowledge.centre.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2, 501])) {
            $menus[] = [
                'label' => 'Market Analyst',
                'icon' => 'ri-line-chart-line',
                'children' => [
                    ['label' => 'All Analyses', 'url' => $routeUrl('market-analyst.index')],
                    ['label' => 'Add Analysis', 'url' => $routeUrl('market-analyst.create')],
                    ['label' => 'Trader View', 'url' => $routeUrl('trading.market-analyst.index')],
                ],
            ];
        }

        if ($roleIn([1, 2, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Trader Centre',
                'icon' => 'ri-funds-line',
                'children' => $visibleItems([
                    ['label' => 'Trading Journal', 'url' => $routeUrl('all.trading.journals')],
                    $roleIn([750, 760, 770]) ? ['label' => 'Readiness Checklist', 'url' => $routeUrl('trader.readiness.index')] : null,
                    ['label' => 'Trading Statistics', 'url' => $routeUrl('all.trading.statistics')],
                    ['label' => 'Backtest Lab', 'url' => $routeUrl('trading.backtest.index')],
                    $roleIn([750, 760, 770]) ? ['label' => 'Position Centre', 'url' => $routeUrl('trading.positions.index')] : null,
                    ['label' => 'Market Analyst', 'url' => $routeUrl('trading.market-analyst.index')],
                    $roleId === 760 ? ['label' => 'Upload Recording Classes', 'url' => $routeUrl('admin.trading.recordings.index')] : null,
                    $roleIn([1, 2]) ? ['label' => 'Recording Classes', 'url' => $routeUrl('admin.trading.recordings.index')] : ['label' => 'Recording Classes', 'url' => $routeUrl('trading.recordings.index')],
                    $roleIn([750, 760, 770]) ? ['label' => 'Knowledge Centre', 'url' => $routeUrl('trading.knowledge.centre.index')] : null,
                    $roleIn([1, 2]) ? ['label' => 'Trading Blog', 'url' => $routeUrl('admin.trading.blogs.index')] : ['label' => 'Trading Blog', 'url' => $routeUrl('trading.blogs.index')],
                    ['label' => 'Trading Pair', 'url' => $routeUrl('all.trading.pairs')],
                    $roleIn([1, 2]) ? ['label' => 'Traders Performance', 'url' => $routeUrl('admin.trader.journals.index')] : null,
                    $roleIn([1, 2]) ? ['label' => 'Funded Traders', 'url' => $routeUrl('admin.funded_traders.index')] : null,
                ]),
            ];
        }

        if ($roleIn([1, 2, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Trading Signals',
                'icon' => 'ri-signal-tower-line',
                'children' => [
                    ['label' => 'Signal Dashboard', 'url' => $routeUrl('member.signals.dashboard')],
                    ['label' => 'Active Signals', 'url' => $routeUrl('member.signals.active')],
                    ['label' => 'Closed Signals', 'url' => $routeUrl('member.signals.closed')],
                    ['label' => 'Signal History', 'url' => $routeUrl('member.signals.history')],
                ],
            ];
        }

        if ($roleId === 202) {
            $menus[] = [
                'label' => 'Provider Accounts',
                'icon' => 'ri-user-star-line',
                'children' => [
                    ['label' => 'Signal Provider Details', 'url' => $routeUrl('all.signal_provider')],
                ],
            ];
        }

        if ($roleIn([1, 2, 201, 202, 350, 501, 502, 700, 750, 760, 770])) {
            $menus[] = [
                'label' => 'Leaderboard',
                'icon' => 'ri-bar-chart-grouped-line',
                'url' => $routeUrl('trading.leaderboard.index'),
            ];
        }
    }

    if ($ecommerceEnabled) {
        $menus[] = ['heading' => 'Dealership E-Commerce'];

        if ($roleIn([1, 2, 350, 700])) {
            $menus[] = [
                'label' => 'E-Store Page',
                'icon' => 'ri-home-4-line',
                'url' => $routeUrl('home'),
            ];
        }

        if ($roleIn([1, 2])) {
            $menus[] = [
                'label' => 'Order Centre',
                'icon' => 'ri-file-list-3-line',
                'children' => [
                    ['label' => 'Shipping Order', 'url' => $routeUrl('all.shipping.order')],
                ],
            ];

            $menus[] = [
                'label' => 'Financial Centre',
                'icon' => 'ri-wallet-3-line',
                'children' => [
                    ['label' => 'Topup E-Wallet Request', 'url' => $routeUrl('all.dealer.wallets')],
                ],
            ];

            $menus[] = [
                'label' => 'Commission Centre',
                'icon' => 'ri-hand-coin-line',
                'children' => [
                    ['label' => 'Commission Setup', 'url' => $routeUrl('admin.commission.setup')],
                    ['label' => 'Dealer Commission', 'url' => $routeUrl('all.dealer.commission')],
                ],
            ];

            $menus[] = [
                'label' => 'Event Management',
                'icon' => 'ri-calendar-event-line',
                'children' => [
                    ['label' => 'All Event Details', 'url' => $routeUrl('all.events')],
                ],
            ];

            $menus[] = [
                'label' => 'Product Centre',
                'icon' => 'ri-box-3-line',
                'children' => [
                    ['label' => 'Product Management', 'url' => $routeUrl('all.product')],
                    ['label' => 'Category Management', 'url' => $routeUrl('all.product.category')],
                ],
            ];
        }

        if ($roleIn([350, 700])) {
            $menus[] = [
                'label' => 'Place an Order',
                'icon' => 'ri-shopping-bag-3-line',
                'children' => [
                    ['label' => 'Product Catalogue', 'url' => $routeUrl('my.stock')],
                ],
            ];

            $menus[] = [
                'label' => 'Financial Centre',
                'icon' => 'ri-wallet-3-line',
                'children' => [
                    ['label' => 'My E-Wallet', 'url' => $routeUrl('My.Wallet')],
                    ['label' => 'My E-Wallet History', 'url' => $routeUrl('My.Wallet.History')],
                ],
            ];

            $menus[] = [
                'label' => 'My Orders',
                'icon' => 'ri-shopping-cart-line',
                'children' => [
                    ['label' => 'My Shipping Order', 'url' => $routeUrl('my.shipping.order')],
                ],
            ];
        }

        if ($roleId === 350) {
            $menus[] = [
                'label' => 'Commission Centre',
                'icon' => 'ri-hand-coin-line',
                'children' => [
                    ['label' => 'My Commission', 'url' => $routeUrl('My.Commission')],
                ],
            ];

            $menus[] = [
                'label' => 'Recruitment Centre',
                'icon' => 'ri-user-add-line',
                'children' => [
                    ['label' => 'Agent Management', 'url' => $routeUrl('all.agent')],
                    ['label' => 'Customer Management', 'url' => $routeUrl('all.customer')],
                ],
            ];

            $menus[] = [
                'label' => 'My Stock Centre',
                'icon' => 'ri-stack-line',
                'children' => [
                    ['label' => 'Stock Management', 'url' => $routeUrl('all.dealer.products')],
                    ['label' => 'Product Category Management', 'url' => $routeUrl('all.dealer.product.category')],
                ],
            ];

            $menus[] = [
                'label' => 'Dealer Order Centre',
                'icon' => 'ri-truck-line',
                'children' => [
                    ['label' => 'Shipping Order', 'url' => $routeUrl('all.dealers.shipping.orders')],
                ],
            ];
        }

        if ($roleIn([1, 2, 350, 700])) {
            $menus[] = [
                'label' => 'Sales Performances',
                'icon' => 'ri-trophy-line',
                'url' => $routeUrl('Sales.Performances'),
            ];
        }

        if ($roleId === 1) {
            $menus[] = ['heading' => 'E-Store Pages'];

            $menus[] = [
                'label' => 'Storefront Content',
                'icon' => 'ri-layout-4-line',
                'children' => [
                    ['label' => 'Home Slide', 'url' => $routeUrl('home.slide')],
                    ['label' => 'About Page', 'url' => $routeUrl('about.page')],
                    ['label' => 'Skill Setup', 'url' => $routeUrl('all.skill')],
                    ['label' => 'Acknowledgement Setup', 'url' => $routeUrl('all.acknowledgement')],
                    ['label' => 'Education Setup', 'url' => $routeUrl('all.education')],
                    ['label' => 'About Multi Image', 'url' => $routeUrl('about.multi.image')],
                    ['label' => 'All Multi Image', 'url' => $routeUrl('all.multi.image')],
                    ['label' => 'All Service', 'url' => $routeUrl('all.service')],
                    ['label' => 'Add Service', 'url' => $routeUrl('add.service')],
                    ['label' => 'All Portfolio', 'url' => $routeUrl('all.portfolio')],
                    ['label' => 'Add Portfolio', 'url' => $routeUrl('add.portfolio')],
                    ['label' => 'All Blog Category', 'url' => $routeUrl('all.blog.category')],
                    ['label' => 'Add Blog Category', 'url' => $routeUrl('add.blog.category')],
                    ['label' => 'All Blog', 'url' => $routeUrl('all.blog')],
                    ['label' => 'Add Blog', 'url' => $routeUrl('add.blog')],
                    ['label' => 'Contact Message', 'url' => $routeUrl('contact.message')],
                ],
            ];
        }
    }

    if (!$tradingEnabled && !$ecommerceEnabled) {
        $menus[] = ['heading' => 'Modules'];
        $menus[] = [
            'label' => 'No Module Enabled',
            'icon' => 'ri-alert-line',
            'url' => $roleIn([1, 2]) ? $routeUrl('admin.features.index') : '#',
        ];
    }

    if ($traderOnboardingLocked) {
        $menus = [
            ['heading' => 'Trader Review'],
            [
                'label' => 'Trader Verification',
                'icon' => 'ri-shield-check-line',
                'url' => $routeUrl('trader.onboarding.show'),
            ],
            [
                'label' => 'Readiness Checklist',
                'icon' => 'ri-list-check-3',
                'url' => $routeUrl('trader.readiness.index'),
            ],
        ];
    }
@endphp

<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div class="user-profile text-center mt-3">
            <div>
                <img src="{{ url('upload/admin_images/' . $adminData->profile_image) }}" onerror="this.src='{{ url('upload/default.jpg') }}'" alt="" class="avatar-md rounded-circle">
            </div>
            <div class="mt-3">
                <h4 class="font-size-16 mb-1">{{ $adminData->username }}</h4>
                <span class="text-muted"><i class="ri-record-circle-line align-middle font-size-14 text-success"></i> Online</span>
            </div>
        </div>

        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                @foreach($menus as $menu)
                    @if(isset($menu['heading']))
                        <li class="menu-title">{{ $menu['heading'] }}</li>
                    @elseif(!empty($menu['children']))
                        <li>
                            <a href="javascript:void(0);" class="has-arrow waves-effect">
                                <i class="{{ $menu['icon'] }}"></i>
                                <span>{{ $menu['label'] }}</span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                @foreach($menu['children'] as $child)
                                    <li><a href="{{ $child['url'] }}">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li>
                            <a href="{{ $menu['url'] }}" class="waves-effect">
                                <i class="{{ $menu['icon'] }}"></i>
                                <span>{{ $menu['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
</div>
