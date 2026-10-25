import 'dart:async';

import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../core/security/admin_permissions.dart';
import '../core/security/access_policy.dart';
import '../core/security/permission_service.dart';
import '../core/storage/local_app_storage.dart';
import '../features/access/access_status_screen.dart';
import '../features/account/account_screen.dart';
import '../features/admin/admin_management_screen.dart';
import '../features/admin/admin_overview_screen.dart';
import '../features/announcements/announcement_screens.dart';
import '../features/dashboard/dashboard_screen.dart';
import '../features/examination/final_exam_screen.dart';
import '../features/hub/trading_hub_screen.dart';
import '../features/hub/trade_call_detail_screen.dart';
import '../features/journal/journal_screen.dart';
import '../features/more/more_screen.dart';
import '../features/notifications/notification_center_screen.dart';
import '../features/psychology/psychology_screen.dart';
import '../features/risk/risk_calculator_screen.dart';
import '../features/statistics/statistics_screen.dart';
import '../features/tools/tools_screen.dart';
import '../features/trading_plan/trading_plan_screen.dart';
import '../features/stocks/stock_information_screen.dart';
import '../models/app_notification.dart';
import '../models/app_user.dart';
import '../repositories/auth_repository.dart';
import '../repositories/trading_repository.dart';
import '../repositories/leaderboard_repository.dart';
import '../repositories/notification_repository.dart';
import '../repositories/stock_repository.dart';
import '../services/push_notification_service.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({
    super.key,
    required this.user,
    required this.authRepository,
    required this.tradingRepository,
    required this.storage,
    required this.firebaseEnabled,
  });

  final AppUser user;
  final AuthRepository authRepository;
  final TradingRepository tradingRepository;
  final LocalAppStorage storage;
  final bool firebaseEnabled;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  var _selectedIndex = 0;
  StreamSubscription<String?>? _announcementOpenSubscription;
  StreamSubscription<String>? _signalOpenSubscription;
  StreamSubscription<String>? _stockOpenSubscription;
  late final StockRepository _stockRepository;
  late final NotificationRepository _notificationRepository;
  late final LeaderboardRepository _leaderboardRepository;

  @override
  void initState() {
    super.initState();
    _stockRepository = StockRepository(
      firebaseEnabled: widget.tradingRepository.firebaseEnabled,
    );
    _notificationRepository = NotificationRepository(
      firebaseEnabled: widget.tradingRepository.firebaseEnabled,
    );
    _leaderboardRepository = LeaderboardRepository(
      firebaseEnabled: widget.tradingRepository.firebaseEnabled,
    );
    _announcementOpenSubscription = PushNotificationService
        .instance
        .announcementOpenRequests
        .listen((_) => _openAnnouncements());
    _signalOpenSubscription = PushNotificationService
        .instance
        .signalOpenRequests
        .listen(_openSignal);
    _stockOpenSubscription = PushNotificationService.instance.stockOpenRequests
        .listen(_openStock);
    final pendingAnnouncement = PushNotificationService.instance
        .takePendingAnnouncementId();
    if (pendingAnnouncement != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _openAnnouncements());
    }
    final pendingSignal = PushNotificationService.instance
        .takePendingSignalId();
    if (pendingSignal != null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _openSignal(pendingSignal),
      );
    }
    final pendingStock = PushNotificationService.instance
        .takePendingStockSymbol();
    if (pendingStock != null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _openStock(pendingStock),
      );
    }
    unawaited(_configureNotifications());
    unawaited(
      widget.tradingRepository
          .synchronizeJournal(widget.user.uid)
          .catchError((_) {}),
    );
  }

  @override
  void didUpdateWidget(covariant HomeShell oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.uid != widget.user.uid) {
      unawaited(_configureNotifications());
    }
  }

  Future<void> _configureNotifications() async {
    try {
      await PushNotificationService.instance.configureUser(widget.user);
    } catch (_) {
      // Connectivity or phone permission issues must not block app startup.
    }
  }

  @override
  void dispose() {
    unawaited(_announcementOpenSubscription?.cancel());
    unawaited(_signalOpenSubscription?.cancel());
    unawaited(_stockOpenSubscription?.cancel());
    unawaited(PushNotificationService.instance.unregisterUser(widget.user.uid));
    super.dispose();
  }

  void _openAnnouncements() {
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AnnouncementListScreen(
          user: widget.user,
          repository: widget.tradingRepository,
        ),
      ),
    );
  }

  void _openNotificationCenter() {
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => NotificationCenterScreen(
          user: widget.user,
          repository: _notificationRepository,
          onOpen: _openInAppNotification,
        ),
      ),
    );
  }

  void _openInAppNotification(AppNotificationRecord notification) {
    switch (notification.category) {
      case AppNotificationCategory.tradingSignals:
        unawaited(_openSignal(notification.entityId));
      case AppNotificationCategory.announcements:
        _openAnnouncements();
      case AppNotificationCategory.stockReports:
      case AppNotificationCategory.stockWatchlist:
        if (notification.symbol.isNotEmpty) _openStock(notification.symbol);
      default:
        break;
    }
  }

  void _openStock(String symbol) {
    if (!mounted || symbol.trim().isEmpty) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => StockDetailScreen(
          user: widget.user,
          repository: _stockRepository,
          symbol: symbol,
        ),
      ),
    );
  }

  Future<void> _openSignal(String signalId) async {
    if (!mounted || signalId.trim().isEmpty) return;
    try {
      final signal = await widget.tradingRepository
          .tradingSignalStream(signalId)
          .first;
      if (!mounted) return;
      if (signal == null) {
        setState(() => _selectedIndex = 2);
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TradeCallDetailScreen(
            signal: signal,
            repository: widget.tradingRepository,
          ),
        ),
      );
    } catch (_) {
      if (mounted) setState(() => _selectedIndex = 2);
    }
  }

  void _openMore() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => Scaffold(
          appBar: AppBar(title: Text(context.l10n.text('Profile & more'))),
          body: MoreScreen(
            user: widget.user,
            authRepository: widget.authRepository,
            tradingRepository: widget.tradingRepository,
            storage: widget.storage,
            firebaseEnabled: widget.firebaseEnabled,
            stockRepository: _stockRepository,
            notificationRepository: _notificationRepository,
            leaderboardRepository: _leaderboardRepository,
            notificationOpen: _openInAppNotification,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final items = _items();
    if (_selectedIndex >= items.length) {
      _selectedIndex = 0;
    }
    final selected = items[_selectedIndex];

    return Scaffold(
      appBar: selected.showShellAppBar
          ? AppBar(
              toolbarHeight: 64,
              titleSpacing: 16,
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    selected.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    selected.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
              actions: [
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: IconButton(
                    tooltip: context.l10n.text('Profile and more'),
                    onPressed: _openMore,
                    icon: CircleAvatar(
                      radius: 18,
                      foregroundImage: widget.user.avatarUrl.trim().isEmpty
                          ? null
                          : NetworkImage(widget.user.avatarUrl),
                      child: widget.user.avatarUrl.trim().isEmpty
                          ? Text(
                              _initials(widget.user.label),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            )
                          : null,
                    ),
                  ),
                ),
              ],
            )
          : null,
      body: SafeArea(
        child: Column(
          children: [
            if (ClientAccessPolicy.hasFullAccess(widget.user) &&
                _selectedIndex == 0 &&
                (widget.user.requiresOnboarding ||
                    widget.user.requiresFinalExam))
              _SetupProgressBanner(
                user: widget.user,
                onVerification: () => setState(() => _selectedIndex = 3),
                onExam: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) =>
                        FinalExamScreen(repository: widget.tradingRepository),
                  ),
                ),
              ),
            Expanded(
              child: IndexedStack(
                index: _selectedIndex,
                children: [for (final item in items) item.screen],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) =>
            setState(() => _selectedIndex = index),
        destinations: [for (final item in items) item.destination],
      ),
    );
  }

  List<_ShellItem> _items() {
    if (!ClientAccessPolicy.hasFullAccess(widget.user)) {
      return [
        _ShellItem(
          title: context.l10n.text('Home'),
          subtitle: context.l10n.text('Your manual trading performance'),
          screen: DashboardScreen(
            user: widget.user,
            repository: widget.tradingRepository,
            onOpenJournal: () => setState(() => _selectedIndex = 1),
            onOpenStatistics: () => setState(() => _selectedIndex = 2),
            onOpenNotifications: _openNotificationCenter,
            onOpenAccounts: () => setState(() => _selectedIndex = 4),
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home_rounded),
            label: context.l10n.text('Home'),
          ),
        ),
        _ShellItem(
          title: context.l10n.text('Journal'),
          subtitle: context.l10n.text('Your manual trading journal'),
          screen: JournalScreen(
            user: widget.user,
            repository: widget.tradingRepository,
            storage: widget.storage,
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.menu_book_outlined),
            selectedIcon: const Icon(Icons.menu_book_rounded),
            label: context.l10n.text('Journal'),
          ),
        ),
        _ShellItem(
          title: context.l10n.text('Trading Statistics'),
          subtitle: context.l10n.text('Advanced private account analytics'),
          screen: StatisticsScreen(
            user: widget.user,
            repository: widget.tradingRepository,
            onOpenJournal: () => setState(() => _selectedIndex = 1),
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.analytics_outlined),
            selectedIcon: const Icon(Icons.analytics_rounded),
            label: context.l10n.text('Statistics'),
          ),
          showShellAppBar: false,
        ),
        _ShellItem(
          title: context.l10n.text('Trading Toolkit'),
          subtitle: context.l10n.text('Plan, calculate and check readiness'),
          screen: ToolsScreen(
            user: widget.user,
            repository: widget.tradingRepository,
            storage: widget.storage,
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.calculate_outlined),
            selectedIcon: const Icon(Icons.calculate),
            label: context.l10n.text('Toolkit'),
          ),
          showShellAppBar: false,
        ),
        _ShellItem(
          title: context.l10n.text('Client access'),
          subtitle: context.l10n.text('Application status'),
          screen: AccessStatusScreen(
            user: widget.user,
            authRepository: widget.authRepository,
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.verified_user_outlined),
            selectedIcon: const Icon(Icons.verified_user),
            label: context.l10n.text('Access'),
          ),
        ),
        _ShellItem(
          title: context.l10n.text('Trading Blog'),
          subtitle: context.l10n.text('Public trading education'),
          screen: TradingHubScreen(
            user: widget.user,
            repository: widget.tradingRepository,
            authRepository: widget.authRepository,
            publicOnly: true,
          ),
          destination: NavigationDestination(
            icon: const Icon(Icons.article_outlined),
            selectedIcon: const Icon(Icons.article),
            label: context.l10n.text('Blog'),
          ),
        ),
      ];
    }

    return [
      _ShellItem(
        title: context.l10n.text('Home'),
        subtitle: context.l10n.text('Your trading performance'),
        screen: DashboardScreen(
          user: widget.user,
          repository: widget.tradingRepository,
          onOpenJournal: () => setState(() => _selectedIndex = 1),
          onOpenStatistics: () => setState(() => _selectedIndex = 2),
          onOpenTradingPlan: () => _push(
            TradingPlanScreen(
              user: widget.user,
              repository: widget.tradingRepository,
              storage: widget.storage,
            ),
          ),
          onOpenPsychology: () => _push(
            PsychologyScreen(
              user: widget.user,
              repository: widget.tradingRepository,
              storage: widget.storage,
            ),
          ),
          onOpenRiskCalculator: () => _push(
            RiskCalculatorScreen(
              user: widget.user,
              repository: widget.tradingRepository,
              storage: widget.storage,
            ),
          ),
          onOpenAccounts: () => setState(() => _selectedIndex = 4),
          onOpenNotifications: _openNotificationCenter,
        ),
        destination: NavigationDestination(
          icon: const Icon(Icons.home_outlined),
          selectedIcon: const Icon(Icons.home_rounded),
          label: context.l10n.text('Home'),
        ),
      ),
      _ShellItem(
        title: context.l10n.text('Journal'),
        subtitle: context.l10n.text('Record, review and improve'),
        screen: JournalScreen(
          user: widget.user,
          repository: widget.tradingRepository,
          storage: widget.storage,
        ),
        destination: NavigationDestination(
          icon: const Icon(Icons.menu_book_outlined),
          selectedIcon: const Icon(Icons.menu_book_rounded),
          label: context.l10n.text('Journal'),
        ),
      ),
      _ShellItem(
        title: context.l10n.text('Trading Statistics'),
        subtitle: context.l10n.text('Advanced private account analytics'),
        screen: StatisticsScreen(
          user: widget.user,
          repository: widget.tradingRepository,
          onOpenJournal: () => setState(() => _selectedIndex = 1),
          onOpenAccounts: () => setState(() => _selectedIndex = 4),
        ),
        destination: NavigationDestination(
          icon: const Icon(Icons.analytics_outlined),
          selectedIcon: const Icon(Icons.analytics_rounded),
          label: context.l10n.text('Statistics'),
        ),
        showShellAppBar: false,
      ),
      _ShellItem(
        title: context.l10n.text('Trading Hub'),
        subtitle: context.l10n.text('Signals, analysis and learning'),
        screen: TradingHubScreen(
          user: widget.user,
          repository: widget.tradingRepository,
          authRepository: widget.authRepository,
        ),
        destination: NavigationDestination(
          icon: const Icon(Icons.hub_outlined),
          selectedIcon: const Icon(Icons.hub_rounded),
          label: context.l10n.text('Trading Hub'),
        ),
      ),
      _ShellItem(
        title: context.l10n.text('Accounts'),
        subtitle: context.l10n.text('Verification, brokers and capital'),
        screen: AccountScreen(
          user: widget.user,
          repository: widget.tradingRepository,
          onViewAccessStatus: () => _push(
            AccessStatusScreen(
              user: widget.user,
              authRepository: widget.authRepository,
            ),
          ),
        ),
        destination: NavigationDestination(
          icon: const Icon(Icons.account_balance_wallet_outlined),
          selectedIcon: const Icon(Icons.account_balance_wallet_rounded),
          label: context.l10n.text('Accounts'),
        ),
      ),
      if (_canOpenManageBackend)
        _ShellItem(
          title: context.l10n.text('Manage Backend'),
          subtitle: context.l10n.text('Admin tools and content management'),
          screen: widget.user.isAdmin
              ? AdminOverviewScreen(
                  user: widget.user,
                  repository: widget.tradingRepository,
                )
              : AdminManagementScreen(
                  user: widget.user,
                  repository: widget.tradingRepository,
                ),
          destination: NavigationDestination(
            icon: const Icon(Icons.admin_panel_settings_outlined),
            selectedIcon: const Icon(Icons.admin_panel_settings),
            label: context.l10n.text('Manage Backend'),
          ),
          showShellAppBar: false,
        ),
    ];
  }

  bool get _canOpenManageBackend =>
      PermissionService(widget.user).canAny(const {
        AppPermission.usersReadAll,
        AppPermission.rolesRead,
        AppPermission.signalsCreateOwn,
        AppPermission.signalsManageAll,
        AppPermission.marketAnalysisCreateOwn,
        AppPermission.marketAnalysisManageAll,
      }) ||
      widget.user.hasPermission(AdminPermission.knowledgeView) ||
      widget.user.hasPermission(AdminPermission.recordingManage);

  void _push(Widget screen) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return 'HC';
    return parts.take(2).map((part) => part[0].toUpperCase()).join();
  }
}

class _SetupProgressBanner extends StatelessWidget {
  const _SetupProgressBanner({
    required this.user,
    required this.onVerification,
    required this.onExam,
  });

  final AppUser user;
  final VoidCallback onVerification;
  final VoidCallback onExam;

  @override
  Widget build(BuildContext context) {
    final needsVerification = user.requiresOnboarding;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 2),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(
            needsVerification
                ? Icons.verified_user_outlined
                : Icons.school_outlined,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  needsVerification
                      ? context.l10n.text('Complete account verification')
                      : context.l10n.text('Final examination ready'),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(
                  needsVerification
                      ? context.l10n.text(
                          'Submit your broker details while you explore the app.',
                        )
                      : context.l10n.text(
                          'Complete the exam to unlock funded-trader progression.',
                        ),
                  maxLines: 2,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          FilledButton.tonal(
            onPressed: needsVerification ? onVerification : onExam,
            child: Text(
              context.l10n.text(needsVerification ? 'Verify' : 'Open'),
            ),
          ),
        ],
      ),
    );
  }
}

class _ShellItem {
  const _ShellItem({
    required this.title,
    required this.subtitle,
    required this.screen,
    required this.destination,
    this.showShellAppBar = true,
  });

  final String title;
  final String subtitle;
  final Widget screen;
  final NavigationDestination destination;
  final bool showShellAppBar;
}
