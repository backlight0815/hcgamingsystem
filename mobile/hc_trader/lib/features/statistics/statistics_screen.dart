import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../core/security/admin_permissions.dart';
import '../../core/security/app_role_permissions.dart';
import '../../core/security/permission_service.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/trading_date_filter.dart';
import '../../models/trading_report.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_report_pdf_service.dart';
import '../../services/trading_report_service.dart';
import '../../shared/analytics/analytics_widgets.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';
import '../access/access_required_view.dart';
import '../performance/performance_dashboard_sections.dart';
import '../performance/performance_external_sections.dart';
import '../trading_analytics/domain/analytics_filter.dart';
import '../trading_analytics/domain/analytics_result.dart';
import '../trading_analytics/presentation/advanced_account_analyst_section.dart';
import '../trading_analytics/presentation/analytics_filter_bar.dart';
import '../trading_analytics/presentation/trading_analytics_controller.dart';
import 'statistics_analytics_data_builder.dart';
import 'statistics_view_model.dart';

class StatisticsScreen extends StatefulWidget {
  const StatisticsScreen({
    super.key,
    required this.user,
    required this.repository,
    this.onOpenJournal,
    this.onOpenAccounts,
  });

  final AppUser user;
  final TradingRepository repository;
  final VoidCallback? onOpenJournal;
  final VoidCallback? onOpenAccounts;

  @override
  State<StatisticsScreen> createState() => _StatisticsScreenState();
}

class _StatisticsScreenState extends State<StatisticsScreen> {
  final _reportService = TradingReportService();
  final _pdfService = TradingReportPdfService();
  final _analyticsController = TradingAnalyticsController();
  var _analyticsFilter = const AnalyticsFilter();
  var _sectionIndex = 0;
  var _exporting = false;

  @override
  Widget build(BuildContext context) {
    if (ClientAccessPolicy.isRestricted(
      widget.user,
      AppFeatureArea.statistics,
    )) {
      return const AccessRequiredView(featureName: 'Trading Statistics');
    }
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Statistics')),
        actions: [
          IconButton(
            tooltip: context.l10n.text('Refresh'),
            onPressed: widget.user.isDemo
                ? () => setState(() {})
                : widget.repository.refreshTradingData,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: StatisticsAnalyticsDataBuilder(
        user: widget.user,
        repository: widget.repository,
        filter: _analyticsFilter,
        builder: (context, bundle) {
          final previousFilter = _previousAnalyticsFilter(_analyticsFilter);
          final useLiveSnapshot = _canUseLiveSnapshot(_analyticsFilter);
          return FutureBuilder<TradingAnalyticsComputation>(
            future: _analyticsController.resolve(
              trades: bundle.trades,
              cashFlows: bundle.cashFlows,
              filter: _analyticsFilter,
              previousFilter: previousFilter,
              configuredStartingBalance: bundle.configuredStartingBalance,
              liveCurrentBalance: useLiveSnapshot
                  ? bundle.liveCurrentBalance
                  : null,
              liveCurrentEquity: useLiveSnapshot
                  ? bundle.liveCurrentEquity
                  : null,
              timezoneOffsetMinutes: widget.user.timezoneOffsetMinutes,
            ),
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Padding(
                  padding: const EdgeInsets.all(16),
                  child: AnalyticsEmptyState(
                    icon: Icons.error_outline_rounded,
                    title: context.l10n.text(
                      'Unable to calculate trading analytics',
                    ),
                    message: context.l10n.text(
                      'Retry the calculation. Your trading records have not been changed.',
                    ),
                    action: FilledButton.icon(
                      onPressed: () => setState(_analyticsController.clear),
                      icon: const Icon(Icons.refresh_rounded),
                      label: Text(context.l10n.text('Retry')),
                    ),
                  ),
                );
              }
              final computation = snapshot.data;
              if (computation == null) return const AnalyticsSkeleton();
              final result = computation.current;
              final report = _reportFor(
                context,
                bundle: bundle,
                result: result,
              );
              final previousResult = computation.previous;
              final previousReport = previousResult == null
                  ? null
                  : _reportFor(context, bundle: bundle, result: previousResult);
              final model = StatisticsViewModel.fromReport(
                report,
                previousReport: previousReport,
              );
              return _PerformanceWorkspace(
                user: widget.user,
                repository: widget.repository,
                filter: _analyticsFilter,
                bundle: bundle,
                result: result,
                model: model,
                sectionIndex: _sectionIndex,
                exporting: _exporting,
                onSectionChanged: (value) =>
                    setState(() => _sectionIndex = value),
                onFilterChanged: (value) => setState(() {
                  _analyticsFilter = value;
                  _sectionIndex = 0;
                }),
                onOpenJournal: widget.onOpenJournal,
                onOpenAccounts: widget.onOpenAccounts,
                onExploreDemo: _openDemoAnalytics,
                onExport: () => _export(report),
              );
            },
          );
        },
      ),
    );
  }

  bool _canUseLiveSnapshot(AnalyticsFilter filter) {
    return filter.symbol == null &&
        filter.direction == null &&
        filter.strategy == null &&
        filter.setup == null &&
        filter.session == null &&
        filter.outcome == AnalyticsOutcomeFilter.all;
  }

  TradingReport _reportFor(
    BuildContext context, {
    required StatisticsAnalyticsBundle bundle,
    required TradingAnalyticsResult result,
  }) {
    final tradeIds = result.trades.map((trade) => trade.id).toSet();
    final journals = bundle.rawJournals
        .where((journal) => tradeIds.contains(journal.id))
        .toList(growable: false);
    final capitals = bundle.rawCapitals
        .where((capital) => _capitalMatches(capital, bundle, result.filter))
        .toList(growable: false);
    final mt5Account = result.filter.accountId == '*'
        ? null
        : bundle.mt5Accounts
              .where((account) => account.id == result.filter.accountId)
              .firstOrNull;
    return _reportService.build(
      journals: journals,
      capitals: capitals,
      filter: TradingReportFilter(
        dateFilter: TradingDateFilter.allTime(
          timezone: widget.user.timezone,
          timezoneOffsetMinutes: widget.user.timezoneOffsetMinutes,
        ),
        source: TradingReportSource.all,
        ownerUid: widget.user.uid,
        timezone: widget.user.timezone,
        languageCode: Localizations.localeOf(context).languageCode,
      ),
      traderLabel: widget.user.label,
      account: mt5Account == null
          ? null
          : TradingReportAccount(
              id: mt5Account.id,
              name: mt5Account.label,
              login: '${mt5Account.login}',
              broker: mt5Account.brokerServer.split('-').first,
              server: mt5Account.brokerServer,
              currency: mt5Account.currency,
              balance: mt5Account.balance,
              equity: mt5Account.equity,
              isMain: mt5Account.isPrimary,
            ),
      currentBalanceOverride: result.monetaryMetricsAvailable
          ? result.summary.currentBalance
          : null,
    );
  }

  bool _capitalMatches(
    CapitalTransaction capital,
    StatisticsAnalyticsBundle bundle,
    AnalyticsFilter filter,
  ) {
    final flow = bundle.cashFlows
        .where((item) => item.id == capital.id)
        .firstOrNull;
    if (flow == null || !filter.sources.contains(flow.source)) return false;
    if (filter.accountId != '*' && flow.accountId != filter.accountId) {
      return false;
    }
    if (filter.startUtc != null &&
        flow.timestampUtc.isBefore(filter.startUtc!)) {
      return false;
    }
    if (filter.endUtcExclusive != null &&
        !flow.timestampUtc.isBefore(filter.endUtcExclusive!)) {
      return false;
    }
    return true;
  }

  AnalyticsFilter? _previousAnalyticsFilter(AnalyticsFilter filter) {
    final start = filter.startUtc;
    final end = filter.endUtcExclusive;
    if (start == null || end == null || !end.isAfter(start)) return null;
    final duration = end.difference(start);
    return filter.copyWith(
      startUtc: start.subtract(duration),
      endUtcExclusive: start,
    );
  }

  void _openDemoAnalytics() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => StatisticsScreen(
          user: const AppUser(
            uid: 'guest-analytics',
            email: '',
            displayName: 'Guest analyst',
            isDemo: true,
          ),
          repository: widget.repository,
        ),
      ),
    );
  }

  Future<void> _export(TradingReport report) async {
    if (_exporting || !report.summary.hasTrades) return;
    setState(() => _exporting = true);
    try {
      final bytes = await _pdfService.build(report: report);
      if (!mounted) return;
      await Printing.layoutPdf(
        name: _pdfService.filename(report: report),
        onLayout: (_) async => bytes,
      );
    } catch (error) {
      if (kDebugMode) debugPrint('[Performance Export] $error');
      if (mounted) {
        AppFeedback.error(context, 'Unable to generate the PDF report.');
      }
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }
}

enum _PerformanceSection {
  analyst,
  overview,
  growth,
  calendar,
  breakdown,
  risk,
  consistency,
  psychology,
  propFirm,
  signals,
  rankings,
}

class _PerformanceWorkspace extends StatelessWidget {
  const _PerformanceWorkspace({
    required this.user,
    required this.repository,
    required this.filter,
    required this.bundle,
    required this.result,
    required this.model,
    required this.sectionIndex,
    required this.exporting,
    required this.onSectionChanged,
    required this.onFilterChanged,
    required this.onOpenJournal,
    required this.onOpenAccounts,
    required this.onExploreDemo,
    required this.onExport,
  });

  final AppUser user;
  final TradingRepository repository;
  final AnalyticsFilter filter;
  final StatisticsAnalyticsBundle bundle;
  final TradingAnalyticsResult result;
  final StatisticsViewModel model;
  final int sectionIndex;
  final bool exporting;
  final ValueChanged<int> onSectionChanged;
  final ValueChanged<AnalyticsFilter> onFilterChanged;
  final VoidCallback? onOpenJournal;
  final VoidCallback? onOpenAccounts;
  final VoidCallback onExploreDemo;
  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    final sections = _sections();
    final activeIndex = sectionIndex.clamp(0, sections.length - 1);
    return LayoutBuilder(
      builder: (context, constraints) {
        final desktop = constraints.maxWidth >= 1050;
        final content = CustomScrollView(
          key: PageStorageKey(sections[activeIndex].name),
          slivers: [
            SliverPadding(
              padding: EdgeInsets.fromLTRB(
                desktop ? 24 : 16,
                14,
                desktop ? 24 : 16,
                32,
              ),
              sliver: SliverList.list(
                children: [
                  _Header(
                    user: user,
                    bundle: bundle,
                    result: result,
                    exporting: exporting,
                    canExport: PermissionService(
                      user,
                    ).can(AppPermission.reportsExportOwn),
                    onExport: onExport,
                  ),
                  const SizedBox(height: 14),
                  AnalyticsFilterBar(
                    filter: filter,
                    accounts: bundle.accounts,
                    trades: bundle.trades,
                    canUseMt5: bundle.canUseMt5,
                    timezoneOffsetMinutes: user.timezoneOffsetMinutes,
                    onChanged: onFilterChanged,
                  ),
                  if (!desktop) ...[
                    const SizedBox(height: 12),
                    _SectionTabs(
                      sections: sections,
                      selected: activeIndex,
                      onChanged: onSectionChanged,
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (sections[activeIndex] != _PerformanceSection.analyst &&
                      !model.report.summary.hasTrades &&
                      sections[activeIndex] != _PerformanceSection.propFirm &&
                      sections[activeIndex] != _PerformanceSection.signals &&
                      sections[activeIndex] !=
                          _PerformanceSection.rankings) ...[
                    AnalyticsEmptyState(
                      title: context.l10n.text('No completed trades'),
                      message: context.l10n.text(
                        'Choose another account, source, or period to build performance analytics.',
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  _section(context, sections[activeIndex]),
                ],
              ),
            ),
          ],
        );
        if (!desktop) return content;
        return Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _PerformanceRail(
              sections: sections,
              selected: activeIndex,
              onChanged: onSectionChanged,
            ),
            const VerticalDivider(width: 1),
            Expanded(child: content),
          ],
        );
      },
    );
  }

  List<_PerformanceSection> _sections() {
    final sections = <_PerformanceSection>[_PerformanceSection.analyst];
    if (result.monetaryMetricsAvailable) {
      sections.addAll(const [
        _PerformanceSection.overview,
        _PerformanceSection.growth,
        _PerformanceSection.calendar,
        _PerformanceSection.breakdown,
        _PerformanceSection.risk,
        _PerformanceSection.consistency,
        _PerformanceSection.psychology,
      ]);
    }
    if (ClientAccessPolicy.canAccess(user, AppFeatureArea.propFirm)) {
      sections.add(_PerformanceSection.propFirm);
    }
    if (_canViewSignalStatistics(user)) {
      sections.add(_PerformanceSection.signals);
    }
    if (user.hasPermission(AdminPermission.performanceManage)) {
      sections.add(_PerformanceSection.rankings);
    }
    return sections;
  }

  Widget _section(BuildContext context, _PerformanceSection section) =>
      switch (section) {
        _PerformanceSection.analyst => AdvancedAccountAnalystSection(
          result: result,
          emptyAction: Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              if (onOpenJournal != null)
                FilledButton.tonalIcon(
                  onPressed: onOpenJournal,
                  icon: const Icon(Icons.add),
                  label: Text(context.l10n.text('Add manual trade')),
                ),
              if (onOpenJournal != null)
                OutlinedButton.icon(
                  onPressed: onOpenJournal,
                  icon: const Icon(Icons.upload_file_outlined),
                  label: Text(context.l10n.text('Import Excel')),
                ),
              if (onOpenAccounts != null)
                OutlinedButton.icon(
                  onPressed: onOpenAccounts,
                  icon: const Icon(Icons.link),
                  label: Text(context.l10n.text('Connect MT5')),
                ),
              TextButton.icon(
                onPressed: onExploreDemo,
                icon: const Icon(Icons.science_outlined),
                label: Text(context.l10n.text('Explore demo analytics')),
              ),
            ],
          ),
        ),
        _PerformanceSection.overview => PerformanceOverviewSection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.growth => PerformanceGrowthSection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.calendar => PerformanceCalendarSection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.breakdown => PerformanceBreakdownSection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.risk => PerformanceRiskSection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.consistency => PerformanceConsistencySection(
          model: model,
          currency: result.currency,
        ),
        _PerformanceSection.psychology => PerformancePsychologySection(
          model: model,
        ),
        _PerformanceSection.propFirm => PerformancePropFirmSection(
          user: user,
          repository: repository,
        ),
        _PerformanceSection.signals => PerformanceSignalsSection(
          user: user,
          repository: repository,
        ),
        _PerformanceSection.rankings => PerformanceRankingsSection(
          repository: repository,
        ),
      };
}

class _Header extends StatelessWidget {
  const _Header({
    required this.user,
    required this.bundle,
    required this.result,
    required this.exporting,
    required this.canExport,
    required this.onExport,
  });

  final AppUser user;
  final StatisticsAnalyticsBundle bundle;
  final TradingAnalyticsResult result;
  final bool exporting;
  final bool canExport;
  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    final accountLabel = result.filter.accountId == '*'
        ? context.l10n.text('All accounts')
        : bundle.accounts
                  .where((account) => account.id == result.filter.accountId)
                  .map((account) => context.l10n.text(account.label))
                  .firstOrNull ??
              context.l10n.text('Unassigned account');
    final statusLabel = bundle.isGuest
        ? 'Demo analytics'
        : bundle.lastRefreshUtc == null
        ? 'Private account data'
        : 'Synchronized private data';
    final statusColor = bundle.isGuest
        ? analyticsInformation
        : bundle.lastRefreshUtc == null
        ? analyticsWarning
        : analyticsPositive;
    return AnalyticsPageHeader(
      eyebrow: context.l10n.text('Performance analytics'),
      title: context.l10n.text('Advanced Account Analyst'),
      subtitle: context.l10n
          .text('{account} · {count} eligible trade(s) · {timezone}', {
            'account': accountLabel,
            'count': result.trades.length,
            'timezone': user.timezone,
          }),
      actions: [
        Tooltip(
          message: context.l10n.text(statusLabel),
          child: Chip(
            avatar: Icon(Icons.circle, size: 11, color: statusColor),
            label: Text(context.l10n.text(statusLabel)),
          ),
        ),
        if (canExport)
          IconButton.filledTonal(
            tooltip: context.l10n.text('Export report'),
            onPressed: exporting || result.closedTrades.isEmpty
                ? null
                : onExport,
            icon: exporting
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.picture_as_pdf_outlined),
          ),
      ],
    );
  }
}

class _SectionTabs extends StatelessWidget {
  const _SectionTabs({
    required this.sections,
    required this.selected,
    required this.onChanged,
  });

  final List<_PerformanceSection> sections;
  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final indexed in sections.indexed) ...[
            ChoiceChip(
              selected: selected == indexed.$1,
              avatar: Icon(indexed.$2.icon, size: 17),
              label: Text(context.l10n.text(indexed.$2.label)),
              onSelected: (_) => onChanged(indexed.$1),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _PerformanceRail extends StatelessWidget {
  const _PerformanceRail({
    required this.sections,
    required this.selected,
    required this.onChanged,
  });

  final List<_PerformanceSection> sections;
  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return NavigationRail(
      selectedIndex: selected,
      onDestinationSelected: onChanged,
      extended: MediaQuery.sizeOf(context).width >= 1350,
      labelType: MediaQuery.sizeOf(context).width >= 1350
          ? NavigationRailLabelType.none
          : NavigationRailLabelType.all,
      destinations: [
        for (final section in sections)
          NavigationRailDestination(
            icon: Icon(section.icon),
            label: Text(context.l10n.text(section.label)),
          ),
      ],
    );
  }
}

extension on _PerformanceSection {
  String get label => switch (this) {
    _PerformanceSection.analyst => 'Account Analyst',
    _PerformanceSection.overview => 'Overview',
    _PerformanceSection.growth => 'Growth',
    _PerformanceSection.calendar => 'Calendar',
    _PerformanceSection.breakdown => 'Breakdown',
    _PerformanceSection.risk => 'Risk',
    _PerformanceSection.consistency => 'Consistency',
    _PerformanceSection.psychology => 'Psychology',
    _PerformanceSection.propFirm => 'Prop Firm',
    _PerformanceSection.signals => 'Signals',
    _PerformanceSection.rankings => 'Rankings',
  };

  IconData get icon => switch (this) {
    _PerformanceSection.analyst => Icons.analytics_outlined,
    _PerformanceSection.overview => Icons.space_dashboard_outlined,
    _PerformanceSection.growth => Icons.show_chart,
    _PerformanceSection.calendar => Icons.calendar_month_outlined,
    _PerformanceSection.breakdown => Icons.account_tree_outlined,
    _PerformanceSection.risk => Icons.shield_outlined,
    _PerformanceSection.consistency => Icons.equalizer_rounded,
    _PerformanceSection.psychology => Icons.psychology_alt_outlined,
    _PerformanceSection.propFirm => Icons.flag_outlined,
    _PerformanceSection.signals => Icons.cell_tower_outlined,
    _PerformanceSection.rankings => Icons.leaderboard_outlined,
  };
}

bool _canViewSignalStatistics(AppUser user) => user.effectiveRoleIds.any(
  const {
    RolePermissionMatrix.superAdmin,
    RolePermissionMatrix.subAdmin,
    RolePermissionMatrix.founder,
    RolePermissionMatrix.signalProvider,
    RolePermissionMatrix.seniorSignalProvider,
    RolePermissionMatrix.signalProviderManagement,
  }.contains,
);
