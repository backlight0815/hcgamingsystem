import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../core/security/permission_service.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/standalone_models.dart';
import '../../models/trading_data_source.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/trading_report.dart';
import '../../repositories/trading_repository.dart';
import '../../services/psychology_score_service.dart';
import '../../services/trading_data_source_service.dart';
import '../../services/trading_report_service.dart';
import '../../shared/analytics/analytics_performance_chart.dart';
import '../../shared/analytics/analytics_widgets.dart';
import '../../shared/formatters.dart';
import '../../shared/trading_data_builder.dart';
import '../../shared/trading_data_source_selector.dart';
import '../access/access_required_view.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.user,
    required this.repository,
    this.onOpenJournal,
    this.onOpenStatistics,
    this.onOpenTradingPlan,
    this.onOpenPsychology,
    this.onOpenRiskCalculator,
    this.onOpenAccounts,
    this.onOpenNotifications,
  });

  final AppUser user;
  final TradingRepository repository;
  final VoidCallback? onOpenJournal;
  final VoidCallback? onOpenStatistics;
  final VoidCallback? onOpenTradingPlan;
  final VoidCallback? onOpenPsychology;
  final VoidCallback? onOpenRiskCalculator;
  final VoidCallback? onOpenAccounts;
  final VoidCallback? onOpenNotifications;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _reportService = TradingReportService();
  final _psychologyService = const PsychologyScoreService();
  var _plans = const <TradingPlanRecord>[];
  var _checkIns = const <PsychologyCheckIn>[];
  var _supportingDataLoading = true;

  @override
  void initState() {
    super.initState();
    unawaited(_loadSupportingData());
  }

  @override
  void didUpdateWidget(covariant DashboardScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.uid != widget.user.uid) {
      unawaited(_loadSupportingData());
    }
  }

  Future<void> _loadSupportingData() async {
    if (mounted) setState(() => _supportingDataLoading = true);
    try {
      final results = await Future.wait<Object>([
        widget.repository.loadTradingPlanRecords(widget.user.uid),
        widget.repository.loadPsychologyCheckIns(widget.user.uid),
      ]);
      if (!mounted) return;
      setState(() {
        _plans = results[0] as List<TradingPlanRecord>;
        _checkIns = results[1] as List<PsychologyCheckIn>;
        _supportingDataLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _supportingDataLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (ClientAccessPolicy.isRestricted(
      widget.user,
      AppFeatureArea.dashboard,
    )) {
      return const AccessRequiredView(featureName: 'Home');
    }
    return TradingDataBuilder(
      user: widget.user,
      repository: widget.repository,
      builder: (context, journals, capitals, pairs, mode) {
        final canUseMt5 = PermissionService(
          widget.user,
        ).can(AppPermission.mt5SyncOwn);
        if (!canUseMt5) {
          return _buildCommandCentre(
            context,
            journals: journals,
            capitals: capitals,
            mode: mode,
          );
        }
        return StreamBuilder<Mt5Account?>(
          stream: widget.repository.mainMt5AccountFor(widget.user.uid),
          builder: (context, accountSnapshot) {
            if (accountSnapshot.connectionState == ConnectionState.waiting &&
                !accountSnapshot.hasData) {
              return const AnalyticsSkeleton();
            }
            return _buildCommandCentre(
              context,
              journals: journals,
              capitals: capitals,
              mode: mode,
              account: accountSnapshot.data,
            );
          },
        );
      },
    );
  }

  Widget _buildCommandCentre(
    BuildContext context, {
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
    required TradingDataDisplayMode mode,
    Mt5Account? account,
  }) {
    final source = mode == TradingDataDisplayMode.mt5Only
        ? TradingReportSource.mt5
        : TradingReportSource.manual;
    final report = _reportService.build(
      journals: journals,
      capitals: capitals,
      filter: TradingReportFilter(
        source: source,
        ownerUid: widget.user.uid,
        mainMt5AccountId: source == TradingReportSource.mt5
            ? account?.id ?? _firstAccountId(journals)
            : '',
        timezone: widget.user.timezone,
        languageCode: Localizations.localeOf(context).languageCode,
      ),
      account: account == null
          ? null
          : TradingReportAccount(
              id: account.id,
              name: account.label,
              login: account.login.toString(),
              broker: account.brokerServer.split('-').first,
              server: account.brokerServer,
              currency: account.currency,
              balance: mt5AccountCapitalBalance(account),
              equity: account.equity,
            ),
      currentBalanceOverride: source == TradingReportSource.mt5
          ? mt5AccountCapitalBalance(account)
          : null,
    );
    final todayTrades = _todayTrades(journals);
    final todayProfit = todayTrades.fold<double>(
      0,
      (sum, trade) => sum + trade.profitLoss,
    );
    final activeTrades = journals.where((trade) => trade.status != 'closed');
    final plan = _activePlan();
    final checkIn = _todayCheckIn();
    final psychology = checkIn == null
        ? null
        : _psychologyService.evaluate(checkIn);
    final currency = account?.currency ?? '';

    return RefreshIndicator(
      onRefresh: () async {
        await Future.wait([
          widget.repository.refreshTradingData(),
          _loadSupportingData(),
        ]);
      },
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
            sliver: SliverList.list(
              children: [
                _PersonalHeader(
                  user: widget.user,
                  onNotifications: widget.onOpenNotifications,
                ),
                const SizedBox(height: 18),
                _AccountCapitalHero(
                  account: account,
                  report: report,
                  source: source,
                  onManage: widget.onOpenAccounts,
                ),
                const SizedBox(height: 12),
                TradingDataSourceSelector(
                  user: widget.user,
                  repository: widget.repository,
                  mode: mode,
                ),
                const SizedBox(height: 22),
                AnalyticsSectionHeader(
                  title: context.l10n.text("Today's trading snapshot"),
                  subtitle: context.l10n.text(
                    'Completed trades only; cash flow is excluded from performance.',
                  ),
                ),
                const SizedBox(height: 11),
                TradingMetricStrip(
                  metrics: [
                    AnalyticsMetricData(
                      label: context.l10n.text("Today's realized P/L"),
                      value: formatSignedCurrencyMoney(todayProfit, currency),
                      icon: todayProfit >= 0
                          ? Icons.trending_up
                          : Icons.trending_down,
                      tone: _financialTone(todayProfit),
                    ),
                    AnalyticsMetricData(
                      label: context.l10n.text('Closed trades'),
                      value: '${todayTrades.length}',
                      icon: Icons.task_alt_outlined,
                      detail: context.l10n.text('{count} active', {
                        'count': activeTrades.length,
                      }),
                    ),
                    AnalyticsMetricData(
                      label: context.l10n.text('Win rate'),
                      value: formatPercent(_winRate(todayTrades)),
                      icon: Icons.percent,
                      tone: analyticsInformation,
                    ),
                    AnalyticsMetricData(
                      label: context.l10n.text('Discipline score'),
                      value: todayTrades.isEmpty
                          ? context.l10n.text('N/A')
                          : formatPercent(_disciplineScore(todayTrades)),
                      icon: Icons.rule_outlined,
                      tone: _scoreTone(_disciplineScore(todayTrades)),
                    ),
                    AnalyticsMetricData(
                      label: context.l10n.text('Psychology'),
                      value: psychology == null
                          ? context.l10n.text('Not checked')
                          : context.l10n.text(
                              _readinessLabel(psychology.score),
                            ),
                      icon: Icons.psychology_alt_outlined,
                      tone: psychology == null
                          ? analyticsWarning
                          : _scoreTone(psychology.score),
                    ),
                    AnalyticsMetricData(
                      label: context.l10n.text('Plan usage'),
                      value: plan == null
                          ? context.l10n.text('Not configured')
                          : '${todayTrades.length}/${plan.maxTradesPerDay}',
                      icon: Icons.checklist_rtl_outlined,
                    ),
                  ],
                ),
                const SizedBox(height: 22),
                AnalyticsPerformanceChart(
                  dailyStats: report.dailyStats,
                  title: 'Seven-day trading performance',
                  compact: true,
                ),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: widget.onOpenStatistics,
                    icon: const Icon(Icons.arrow_forward),
                    label: Text(context.l10n.text('View full statistics')),
                  ),
                ),
                const SizedBox(height: 14),
                _DisciplinePanel(
                  plan: plan,
                  todayTrades: todayTrades,
                  currentBalance: report.summary.currentBalance,
                  onOpenPlan: widget.onOpenTradingPlan,
                ),
                const SizedBox(height: 14),
                _PsychologyPanel(
                  checkIn: checkIn,
                  loading: _supportingDataLoading,
                  score: psychology,
                  onOpen: widget.onOpenPsychology,
                ),
                const SizedBox(height: 22),
                _QuickActions(
                  onRecordTrade: widget.onOpenJournal,
                  onImport: widget.onOpenJournal,
                  onJournal: widget.onOpenJournal,
                  onPlan: widget.onOpenTradingPlan,
                  onRisk: widget.onOpenRiskCalculator,
                  onPsychology: widget.onOpenPsychology,
                ),
                const SizedBox(height: 22),
                _PendingActions(
                  account: account,
                  plan: plan,
                  checkIn: checkIn,
                  loading: _supportingDataLoading,
                  onAccounts: widget.onOpenAccounts,
                  onPlan: widget.onOpenTradingPlan,
                  onPsychology: widget.onOpenPsychology,
                ),
                const SizedBox(height: 22),
                _RecentActivity(
                  trades: journals,
                  plans: _plans,
                  checkIns: _checkIns,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _firstAccountId(List<TradingJournalEntry> journals) {
    for (final journal in journals) {
      final value = journal.tradingAccountId?.trim() ?? '';
      if (value.isNotEmpty) return value;
    }
    return '';
  }

  List<TradingJournalEntry> _todayTrades(List<TradingJournalEntry> journals) {
    final now = DateTime.now();
    return journals
        .where(
          (trade) =>
              trade.status == 'closed' &&
              trade.closeDate.year == now.year &&
              trade.closeDate.month == now.month &&
              trade.closeDate.day == now.day,
        )
        .toList(growable: false);
  }

  TradingPlan? _activePlan() {
    for (final record in _plans) {
      if (record.plan.isActive && record.plan.isDefault) return record.plan;
    }
    for (final record in _plans) {
      if (record.plan.isActive) return record.plan;
    }
    return null;
  }

  PsychologyCheckIn? _todayCheckIn() {
    final now = DateTime.now();
    for (final item in _checkIns) {
      if (item.createdAt.year == now.year &&
          item.createdAt.month == now.month &&
          item.createdAt.day == now.day) {
        return item;
      }
    }
    return null;
  }

  double _winRate(List<TradingJournalEntry> trades) {
    final decided = trades.where((trade) => trade.profitLoss != 0).toList();
    if (decided.isEmpty) return 0;
    return decided.where((trade) => trade.profitLoss > 0).length /
        decided.length *
        100;
  }

  double _disciplineScore(List<TradingJournalEntry> trades) {
    if (trades.isEmpty) return 0;
    final checks = <bool>[
      for (final trade in trades) ...[
        trade.followedTradingPlan,
        trade.followedRiskRules,
        trade.followedSessionRules,
        trade.followedSlRule,
      ],
    ];
    return checks.where((value) => value).length / checks.length * 100;
  }

  String _readinessLabel(double score) {
    if (score >= 80) return 'Ready';
    if (score >= 60) return 'Cautious';
    if (score >= 40) return 'Review recommended';
    return 'High emotional risk';
  }
}

class _PersonalHeader extends StatelessWidget {
  const _PersonalHeader({required this.user, this.onNotifications});

  final AppUser user;
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final greeting = switch (now.hour) {
      < 12 => 'Good morning',
      < 18 => 'Good afternoon',
      _ => 'Good evening',
    };
    final locale = Localizations.localeOf(context).toLanguageTag();
    return AnalyticsPageHeader(
      leading: CircleAvatar(
        radius: 24,
        foregroundImage: user.avatarUrl.trim().isEmpty
            ? null
            : NetworkImage(user.avatarUrl),
        child: user.avatarUrl.trim().isEmpty
            ? Text(
                _initials(user.label),
                style: const TextStyle(fontWeight: FontWeight.w900),
              )
            : null,
      ),
      eyebrow: DateFormat.yMMMMEEEEd(locale).format(now),
      title: context.l10n.text('{greeting}, {name}', {
        'greeting': context.l10n.text(greeting),
        'name': user.label,
      }),
      subtitle: context.l10n.text('Here is your trading overview for today.'),
      actions: [
        IconButton.filledTonal(
          tooltip: context.l10n.text('Notifications'),
          onPressed: onNotifications,
          icon: const Icon(Icons.notifications_none_rounded),
        ),
      ],
    );
  }

  String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return 'HC';
    return parts.take(2).map((part) => part[0].toUpperCase()).join();
  }
}

class _AccountCapitalHero extends StatelessWidget {
  const _AccountCapitalHero({
    required this.account,
    required this.report,
    required this.source,
    this.onManage,
  });

  final Mt5Account? account;
  final TradingReport report;
  final TradingReportSource source;
  final VoidCallback? onManage;

  @override
  Widget build(BuildContext context) {
    final balance = account == null
        ? report.summary.currentBalance
        : mt5AccountCapitalBalance(account);
    final equity = account?.equity ?? balance;
    final floating = equity - balance;
    final status = _status(context);
    final currency = account?.currency ?? '';
    final accountLabel = account == null
        ? context.l10n.text('Manual trading account')
        : '${account!.label} · ${maskAccountIdentifier(account!.login)}';
    final lastSync = account?.lastSuccessfulSyncAt ?? account?.lastSyncTime;
    final caption = account == null
        ? context.l10n.text(
            'Select a main MT5 account to add live capital context.',
          )
        : context.l10n.text('{server} · Last sync {time}', {
            'server': account!.brokerServer,
            'time': lastSync == null
                ? context.l10n.text('Not synced yet')
                : formatDateTime(lastSync),
          });

    return PerformanceHero(
      label: accountLabel,
      value: formatCurrencyMoney(balance, currency),
      caption: caption,
      tone: status.$2,
      statusLabel: status.$1,
      statusIcon: status.$3,
      metrics: [
        AnalyticsHeroMetric(
          label: context.l10n.text('Equity'),
          value: formatCurrencyMoney(equity, currency),
          icon: Icons.account_balance_wallet_outlined,
        ),
        AnalyticsHeroMetric(
          label: context.l10n.text('Floating P/L'),
          value: formatSignedCurrencyMoney(floating, currency),
          icon: floating >= 0 ? Icons.arrow_upward : Icons.arrow_downward,
          tone: _financialTone(floating),
        ),
        AnalyticsHeroMetric(
          label: context.l10n.text('Available margin'),
          value: account?.freeMargin == null
              ? context.l10n.text('N/A')
              : formatCurrencyMoney(account!.freeMargin!, currency),
          icon: Icons.shield_outlined,
        ),
        AnalyticsHeroMetric(
          label: context.l10n.text('Data source'),
          value: context.l10n.text(
            source == TradingReportSource.mt5 ? 'MT5' : 'Manual',
          ),
          icon: source == TradingReportSource.mt5
              ? Icons.sync_alt
              : Icons.edit_note_outlined,
        ),
      ],
      action: TextButton.icon(
        onPressed: onManage,
        icon: const Icon(Icons.manage_accounts_outlined),
        label: Text(context.l10n.text('Manage account')),
      ),
    );
  }

  (String, Color, IconData) _status(BuildContext context) {
    if (account == null) {
      return (
        context.l10n.text('No main account'),
        analyticsWarning,
        Icons.account_balance_outlined,
      );
    }
    if (account!.lastError.trim().isNotEmpty) {
      return (
        context.l10n.text('Connection error'),
        analyticsNegative,
        Icons.error_outline,
      );
    }
    if (account!.isConnected && account!.isConnectionFresh) {
      return (
        context.l10n.text('Connected'),
        analyticsPositive,
        Icons.cloud_done_outlined,
      );
    }
    if (account!.isConnected) {
      return (
        context.l10n.text('Stale'),
        analyticsWarning,
        Icons.schedule_outlined,
      );
    }
    return (
      context.l10n.text('Disconnected'),
      analyticsNegative,
      Icons.cloud_off_outlined,
    );
  }
}

class _DisciplinePanel extends StatelessWidget {
  const _DisciplinePanel({
    required this.plan,
    required this.todayTrades,
    required this.currentBalance,
    this.onOpenPlan,
  });

  final TradingPlan? plan;
  final List<TradingJournalEntry> todayTrades;
  final double currentBalance;
  final VoidCallback? onOpenPlan;

  @override
  Widget build(BuildContext context) {
    if (plan == null) {
      return AnalyticsEmptyState(
        icon: Icons.rule_folder_outlined,
        title: context.l10n.text('No active trading plan'),
        message: context.l10n.text(
          'Create a plan to monitor trade limits, risk, and rule compliance.',
        ),
        action: FilledButton.tonal(
          onPressed: onOpenPlan,
          child: Text(context.l10n.text('Create trading plan')),
        ),
      );
    }
    final losses = todayTrades
        .where((trade) => trade.profitLoss < 0)
        .fold<double>(0, (sum, trade) => sum + trade.profitLoss.abs());
    final lossPercent = currentBalance > 0 ? losses / currentBalance * 100 : 0;
    final compliant = todayTrades.isEmpty
        ? 0.0
        : todayTrades.where((trade) => trade.followedRiskRules).length /
              todayTrades.length;
    final slCompliant = todayTrades.isEmpty
        ? 0.0
        : todayTrades.where((trade) => trade.followedSlRule).length /
              todayTrades.length;

    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            icon: Icons.track_changes_outlined,
            title: context.l10n.text('Daily plan progress'),
            subtitle: plan!.planName.trim().isEmpty
                ? context.l10n.text('Active trading plan')
                : plan!.planName,
            action: TextButton(
              onPressed: onOpenPlan,
              child: Text(context.l10n.text('Review')),
            ),
          ),
          const SizedBox(height: 16),
          AnalyticsProgressRow(
            label: context.l10n.text('Daily loss limit usage'),
            valueLabel:
                '${lossPercent.toStringAsFixed(1)}% / ${plan!.dailyMaxLossPercent.toStringAsFixed(1)}%',
            progress: plan!.dailyMaxLossPercent <= 0
                ? 0
                : lossPercent / plan!.dailyMaxLossPercent,
            tone: lossPercent > plan!.dailyMaxLossPercent
                ? analyticsNegative
                : analyticsWarning,
            caption: context.l10n.text(
              'A limit is a safety boundary, not a target.',
            ),
          ),
          const SizedBox(height: 15),
          AnalyticsProgressRow(
            label: context.l10n.text('Trades used'),
            valueLabel: '${todayTrades.length}/${plan!.maxTradesPerDay}',
            progress: plan!.maxTradesPerDay <= 0
                ? 0
                : todayTrades.length / plan!.maxTradesPerDay,
            tone: todayTrades.length > plan!.maxTradesPerDay
                ? analyticsNegative
                : analyticsInformation,
          ),
          const SizedBox(height: 15),
          AnalyticsProgressRow(
            label: context.l10n.text('Risk-rule compliance'),
            valueLabel: todayTrades.isEmpty
                ? context.l10n.text('No trades')
                : formatPercent(compliant * 100),
            progress: compliant,
            tone: compliant >= .8 ? analyticsPositive : analyticsWarning,
          ),
          const SizedBox(height: 15),
          AnalyticsProgressRow(
            label: context.l10n.text('Stop-loss compliance'),
            valueLabel: todayTrades.isEmpty
                ? context.l10n.text('No trades')
                : formatPercent(slCompliant * 100),
            progress: slCompliant,
            tone: slCompliant >= .8 ? analyticsPositive : analyticsWarning,
          ),
        ],
      ),
    );
  }
}

class _PsychologyPanel extends StatelessWidget {
  const _PsychologyPanel({
    required this.checkIn,
    required this.loading,
    required this.score,
    this.onOpen,
  });

  final PsychologyCheckIn? checkIn;
  final bool loading;
  final PsychologyScore? score;
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) {
    if (loading) return const AnalyticsSkeleton(compact: true);
    if (checkIn == null || score == null) {
      return InsightBanner(
        icon: Icons.psychology_alt_outlined,
        tone: analyticsWarning,
        title: context.l10n.text("Today's psychology check is incomplete"),
        message: context.l10n.text(
          'A brief check-in can help you decide whether to trade, reduce risk, or pause.',
        ),
        action: IconButton(
          tooltip: context.l10n.text('Complete check'),
          onPressed: onOpen,
          icon: const Icon(Icons.arrow_forward),
        ),
      );
    }
    final label = score!.score >= 80
        ? 'Ready'
        : score!.score >= 60
        ? 'Cautious'
        : score!.score >= 40
        ? 'Review recommended'
        : 'High emotional risk';
    return AnalyticsSurface(
      child: Row(
        children: [
          PerformanceScoreRing(
            score: score!.score,
            label: context.l10n.text('Readiness'),
            size: 104,
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text(label),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: _scoreTone(score!.score),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  score!.warnings.isEmpty
                      ? context.l10n.text(
                          'Your check-in does not show a major emotional warning.',
                        )
                      : context.l10n.text(score!.warnings.first),
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 7),
                TextButton(
                  onPressed: onOpen,
                  child: Text(context.l10n.text('Review psychology check')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    this.onRecordTrade,
    this.onImport,
    this.onJournal,
    this.onPlan,
    this.onRisk,
    this.onPsychology,
  });

  final VoidCallback? onRecordTrade;
  final VoidCallback? onImport;
  final VoidCallback? onJournal;
  final VoidCallback? onPlan;
  final VoidCallback? onRisk;
  final VoidCallback? onPsychology;

  @override
  Widget build(BuildContext context) {
    final actions = [
      _Command(Icons.add_chart_outlined, 'Record trade', onRecordTrade),
      _Command(Icons.upload_file_outlined, 'Import MT5 report', onImport),
      _Command(Icons.menu_book_outlined, 'Open journal', onJournal),
      _Command(Icons.rule_folder_outlined, 'Trading plan', onPlan),
      _Command(Icons.calculate_outlined, 'Risk calculator', onRisk),
      _Command(Icons.psychology_alt_outlined, 'Psychology check', onPsychology),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AnalyticsSectionHeader(
          title: context.l10n.text('Quick actions'),
          subtitle: context.l10n.text('Move directly to your next task.'),
        ),
        const SizedBox(height: 11),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 900
                ? 6
                : constraints.maxWidth >= 520
                ? 3
                : 2;
            final width =
                (constraints.maxWidth - ((columns - 1) * 9)) / columns;
            return Wrap(
              spacing: 9,
              runSpacing: 9,
              children: [
                for (final action in actions)
                  SizedBox(
                    width: width,
                    child: _CommandButton(command: action),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _Command {
  const _Command(this.icon, this.label, this.onPressed);

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
}

class _CommandButton extends StatelessWidget {
  const _CommandButton({required this.command});

  final _Command command;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: command.onPressed,
        borderRadius: BorderRadius.circular(18),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 104),
          child: Padding(
            padding: const EdgeInsets.all(13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Icon(command.icon, color: scheme.primary),
                const SizedBox(height: 10),
                Text(
                  context.l10n.text(command.label),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(
                    context,
                  ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PendingActions extends StatelessWidget {
  const _PendingActions({
    required this.account,
    required this.plan,
    required this.checkIn,
    required this.loading,
    this.onAccounts,
    this.onPlan,
    this.onPsychology,
  });

  final Mt5Account? account;
  final TradingPlan? plan;
  final PsychologyCheckIn? checkIn;
  final bool loading;
  final VoidCallback? onAccounts;
  final VoidCallback? onPlan;
  final VoidCallback? onPsychology;

  @override
  Widget build(BuildContext context) {
    if (loading) return const AnalyticsSkeleton(compact: true);
    final items = <_PendingItem>[
      if (account == null)
        _PendingItem(
          'Select a main MT5 account',
          'Live capital and synchronized analytics need one selected account.',
          Icons.account_balance_outlined,
          onAccounts,
        )
      else if (!account!.isConnected || !account!.isConnectionFresh)
        _PendingItem(
          'MT5 connection needs attention',
          'Open account management to review the connection and last sync.',
          Icons.cloud_off_outlined,
          onAccounts,
        ),
      if (plan == null)
        _PendingItem(
          'Trading plan not configured',
          'Set risk and trade limits before your next session.',
          Icons.rule_folder_outlined,
          onPlan,
        ),
      if (checkIn == null)
        _PendingItem(
          'Psychology check incomplete',
          'Check your readiness before taking risk today.',
          Icons.psychology_alt_outlined,
          onPsychology,
        ),
    ];
    if (items.isEmpty) {
      return InsightBanner(
        title: context.l10n.text('You are all caught up'),
        message: context.l10n.text(
          'No account, plan, or psychology action needs attention right now.',
        ),
        icon: Icons.verified_outlined,
        tone: analyticsPositive,
      );
    }
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Pending actions'),
            subtitle: context.l10n.text(
              'Only items that need your attention are shown.',
            ),
          ),
          const SizedBox(height: 8),
          for (final item in items)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(
                backgroundColor: analyticsWarning.withValues(alpha: .12),
                child: Icon(item.icon, color: analyticsWarning),
              ),
              title: Text(
                context.l10n.text(item.title),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(context.l10n.text(item.message)),
              trailing: const Icon(Icons.chevron_right),
              onTap: item.onTap,
            ),
        ],
      ),
    );
  }
}

class _PendingItem {
  const _PendingItem(this.title, this.message, this.icon, this.onTap);

  final String title;
  final String message;
  final IconData icon;
  final VoidCallback? onTap;
}

class _RecentActivity extends StatelessWidget {
  const _RecentActivity({
    required this.trades,
    required this.plans,
    required this.checkIns,
  });

  final List<TradingJournalEntry> trades;
  final List<TradingPlanRecord> plans;
  final List<PsychologyCheckIn> checkIns;

  @override
  Widget build(BuildContext context) {
    final events = <_ActivityEvent>[
      for (final trade in trades.take(6))
        _ActivityEvent(
          trade.closeDate,
          'Trade recorded',
          '${trade.pair} · ${formatSignedMoney(trade.profitLoss)}',
          Icons.candlestick_chart_outlined,
        ),
      for (final item in checkIns.take(3))
        _ActivityEvent(
          item.createdAt,
          'Psychology check completed',
          item.emotion,
          Icons.psychology_alt_outlined,
        ),
      for (final item in plans.take(2))
        _ActivityEvent(
          item.updatedAt,
          'Trading plan updated',
          item.plan.planName,
          Icons.rule_folder_outlined,
        ),
    ]..sort((left, right) => right.time.compareTo(left.time));
    final visible = events.take(5).toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AnalyticsSectionHeader(
          title: context.l10n.text('Recent activity'),
          subtitle: context.l10n.text('Your latest meaningful trading events.'),
        ),
        const SizedBox(height: 10),
        if (visible.isEmpty)
          AnalyticsEmptyState(
            icon: Icons.history,
            title: context.l10n.text('No recent activity'),
            message: context.l10n.text(
              'Trades, check-ins, and plan updates will appear here.',
            ),
          )
        else
          AnalyticsSurface(
            child: Column(
              children: [
                for (final indexed in visible.indexed)
                  _TimelineRow(
                    event: indexed.$2,
                    showLine: indexed.$1 != visible.length - 1,
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

class _ActivityEvent {
  const _ActivityEvent(this.time, this.title, this.detail, this.icon);

  final DateTime time;
  final String title;
  final String detail;
  final IconData icon;
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.event, required this.showLine});

  final _ActivityEvent event;
  final bool showLine;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 36,
            child: Column(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: scheme.primaryContainer,
                  child: Icon(event.icon, size: 17),
                ),
                if (showLine)
                  Expanded(
                    child: Container(width: 1, color: scheme.outlineVariant),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 17),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text(event.title),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    event.detail.trim().isEmpty
                        ? context.l10n.text('Updated')
                        : event.detail,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  Text(
                    formatDateTime(event.time),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Color _financialTone(double value) {
  if (value > 0) return analyticsPositive;
  if (value < 0) return analyticsNegative;
  return analyticsInformation;
}

Color _scoreTone(double value) {
  if (value >= 75) return analyticsPositive;
  if (value >= 45) return analyticsWarning;
  return analyticsNegative;
}
