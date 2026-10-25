import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../models/trading_journal_entry.dart';
import '../../../models/trading_report.dart';
import '../../../shared/analytics/analytics_performance_chart.dart';
import '../../../shared/analytics/analytics_widgets.dart';
import '../../../shared/formatters.dart';
import '../../../shared/trading_date_filter_bar.dart';
import '../../statistics/statistics_view_model.dart';

enum ReportWorkspaceSection {
  overview,
  performance,
  risk,
  behaviour,
  breakdown,
  trades,
}

class TradingReportWorkspace extends StatefulWidget {
  const TradingReportWorkspace({
    super.key,
    required this.report,
    required this.rawRecordCount,
    required this.rawCapitalCount,
    this.onExport,
    this.exporting = false,
    this.exportStage,
  });

  final TradingReport report;
  final int rawRecordCount;
  final int rawCapitalCount;
  final VoidCallback? onExport;
  final bool exporting;
  final String? exportStage;

  @override
  State<TradingReportWorkspace> createState() => _TradingReportWorkspaceState();
}

class _TradingReportWorkspaceState extends State<TradingReportWorkspace> {
  var _section = ReportWorkspaceSection.overview;

  @override
  Widget build(BuildContext context) {
    final model = StatisticsViewModel.fromReport(widget.report);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        _ReportSummaryHeader(
          report: widget.report,
          exporting: widget.exporting,
          onExport: widget.onExport,
        ),
        if (widget.exporting) ...[
          const SizedBox(height: 10),
          _ExportProgress(stage: widget.exportStage),
        ],
        const SizedBox(height: 14),
        _SectionNavigation(
          selected: _section,
          onChanged: (value) => setState(() => _section = value),
        ),
        const SizedBox(height: 14),
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 180),
          child: KeyedSubtree(
            key: ValueKey(_section),
            child: switch (_section) {
              ReportWorkspaceSection.overview => _OverviewSection(
                report: widget.report,
                model: model,
                rawRecordCount: widget.rawRecordCount,
                rawCapitalCount: widget.rawCapitalCount,
              ),
              ReportWorkspaceSection.performance => _PerformanceSection(
                report: widget.report,
              ),
              ReportWorkspaceSection.risk => _RiskSection(
                report: widget.report,
                model: model,
              ),
              ReportWorkspaceSection.behaviour => _BehaviourSection(
                report: widget.report,
                model: model,
              ),
              ReportWorkspaceSection.breakdown => _BreakdownSection(
                report: widget.report,
                strategyStats: model.strategyStats,
              ),
              ReportWorkspaceSection.trades => _TradesSection(
                trades: widget.report.trades,
              ),
            },
          ),
        ),
      ],
    );
  }
}

class _ReportSummaryHeader extends StatelessWidget {
  const _ReportSummaryHeader({
    required this.report,
    required this.exporting,
    this.onExport,
  });

  final TradingReport report;
  final bool exporting;
  final VoidCallback? onExport;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    final accountLabel =
        report.account?.displayLabel ??
        (report.filter.source == TradingReportSource.manual
            ? context.l10n.text('Manual trading journal')
            : context.l10n.text('Main MT5 account {account}', {
                'account': maskAccountIdentifier(
                  report.filter.mainMt5AccountId,
                ),
              }));
    final sourceLabel = switch (report.filter.source) {
      TradingReportSource.manual => 'Manual journal',
      TradingReportSource.mt5 => 'MT5 synchronized journal',
      TradingReportSource.all => 'Combined journal',
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AnalyticsPageHeader(
          eyebrow: context.l10n.text('Trading Journal Report'),
          title: accountLabel,
          subtitle: context.l10n.text('{period} · {source}', {
            'period': tradingDateFilterLabel(
              context,
              report.filter.effectiveDateFilter,
            ),
            'source': context.l10n.text(sourceLabel),
          }),
          actions: [
            FilledButton.icon(
              onPressed: exporting ? null : onExport,
              icon: exporting
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined),
              label: Text(
                context.l10n.text(exporting ? 'Preparing PDF' : 'Export PDF'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        PerformanceHero(
          label: context.l10n.text('Net trading result'),
          value: formatSignedMoney(summary.netProfitLoss),
          caption: context.l10n.text(
            'Deposits and withdrawals are separated below and do not change this result.',
          ),
          tone: _financialTone(summary.netProfitLoss),
          statusLabel: context.l10n.text(report.performanceStatus),
          statusIcon: summary.netProfitLoss >= 0
              ? Icons.trending_up
              : Icons.trending_down,
          metrics: [
            AnalyticsHeroMetric(
              label: context.l10n.text('Gross profit'),
              value: formatMoney(summary.totalProfit),
              icon: Icons.add,
              tone: analyticsPositive,
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Gross loss'),
              value: formatMoney(summary.totalLoss),
              icon: Icons.remove,
              tone: analyticsNegative,
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Total trades'),
              value: '${summary.totalTrades}',
              icon: Icons.task_alt_outlined,
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Overall score'),
              value: '${report.scoreProfile.score.toStringAsFixed(0)}/100',
              icon: Icons.speed_outlined,
              tone: _scoreTone(report.scoreProfile.score),
            ),
          ],
        ),
      ],
    );
  }
}

class _ExportProgress extends StatelessWidget {
  const _ExportProgress({this.stage});

  final String? stage;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      label: context.l10n.text(stage ?? 'Preparing report'),
      child: AnalyticsSurface(
        padding: const EdgeInsets.all(14),
        color: Theme.of(context).colorScheme.primaryContainer,
        child: Row(
          children: [
            const SizedBox.square(
              dimension: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text(stage ?? 'Preparing report'),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  Text(
                    context.l10n.text(
                      'Keep this screen open. Duplicate export requests are disabled.',
                    ),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionNavigation extends StatelessWidget {
  const _SectionNavigation({required this.selected, required this.onChanged});

  final ReportWorkspaceSection selected;
  final ValueChanged<ReportWorkspaceSection> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: SegmentedButton<ReportWorkspaceSection>(
        showSelectedIcon: false,
        segments: [
          for (final section in ReportWorkspaceSection.values)
            ButtonSegment(
              value: section,
              icon: Icon(_icon(section), size: 18),
              label: Text(context.l10n.text(_label(section))),
            ),
        ],
        selected: {selected},
        onSelectionChanged: (value) => onChanged(value.first),
      ),
    );
  }

  String _label(ReportWorkspaceSection section) => switch (section) {
    ReportWorkspaceSection.overview => 'Overview',
    ReportWorkspaceSection.performance => 'Performance',
    ReportWorkspaceSection.risk => 'Risk',
    ReportWorkspaceSection.behaviour => 'Behaviour',
    ReportWorkspaceSection.breakdown => 'Breakdown',
    ReportWorkspaceSection.trades => 'Trades',
  };

  IconData _icon(ReportWorkspaceSection section) => switch (section) {
    ReportWorkspaceSection.overview => Icons.dashboard_outlined,
    ReportWorkspaceSection.performance => Icons.show_chart,
    ReportWorkspaceSection.risk => Icons.shield_outlined,
    ReportWorkspaceSection.behaviour => Icons.psychology_alt_outlined,
    ReportWorkspaceSection.breakdown => Icons.account_tree_outlined,
    ReportWorkspaceSection.trades => Icons.receipt_long_outlined,
  };
}

class _OverviewSection extends StatelessWidget {
  const _OverviewSection({
    required this.report,
    required this.model,
    required this.rawRecordCount,
    required this.rawCapitalCount,
  });

  final TradingReport report;
  final StatisticsViewModel model;
  final int rawRecordCount;
  final int rawCapitalCount;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    return Column(
      children: [
        TradingMetricStrip(
          metrics: [
            AnalyticsMetricData(
              label: context.l10n.text('Win rate'),
              value: formatPercent(summary.winRate),
              icon: Icons.percent,
              tone: analyticsInformation,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Profit factor'),
              value: context.l10n.text(summary.profitFactorLabel),
              icon: Icons.balance_outlined,
              tone: analyticsPositive,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Expectancy'),
              value: formatSignedMoney(summary.expectancy),
              icon: Icons.insights_outlined,
              tone: _financialTone(summary.expectancy),
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Payoff ratio'),
              value: context.l10n.text(summary.payoffRatioLabel),
              icon: Icons.compare_arrows,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Maximum drawdown'),
              value: formatPercent(summary.maxDrawdownPercent),
              icon: Icons.waterfall_chart,
              detail: formatMoney(summary.maxDrawdownAmount),
              tone: analyticsWarning,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Plan compliance'),
              value: summary.hasTrades
                  ? formatPercent(model.planCompliance)
                  : context.l10n.text('N/A'),
              icon: Icons.rule_outlined,
              tone: _scoreTone(model.planCompliance),
            ),
          ],
        ),
        const SizedBox(height: 14),
        _ScoreBreakdown(report: report),
        const SizedBox(height: 14),
        InsightBanner(
          title: context.l10n.text('Executive insight'),
          message: context.l10n.text(
            summary.hasTrades
                ? report.executiveInsight
                : 'More completed trades are needed before a reliable report insight can be generated.',
          ),
          icon: Icons.auto_awesome_outlined,
        ),
        const SizedBox(height: 14),
        _CashFlowPanel(report: report),
        const SizedBox(height: 14),
        _DataQualityPanel(
          report: report,
          rawRecordCount: rawRecordCount,
          rawCapitalCount: rawCapitalCount,
        ),
      ],
    );
  }
}

class _ScoreBreakdown extends StatelessWidget {
  const _ScoreBreakdown({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final score = report.scoreProfile;
    final components = score.components.take(6).toList(growable: false);
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Performance score breakdown'),
            subtitle: context.l10n.text(score.meaning),
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final rings = [
                PerformanceScoreRing(
                  score: score.score,
                  label: context.l10n.text('Overall'),
                ),
                PerformanceScoreRing(
                  score: report.behaviourProfile.finalScore,
                  label: context.l10n.text('Behaviour'),
                ),
                PerformanceScoreRing(
                  score: report.positionProfile.score,
                  label: context.l10n.text('Position'),
                ),
              ];
              return Wrap(
                alignment: WrapAlignment.spaceEvenly,
                spacing: 16,
                runSpacing: 16,
                children: rings,
              );
            },
          ),
          const SizedBox(height: 14),
          for (final component in components)
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 12),
              title: Text(
                context.l10n.text(component.metric),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(context.l10n.text(component.status)),
              trailing: Text(
                '${component.points.toStringAsFixed(1)}/${component.maximumPoints.toStringAsFixed(1)}',
                style: TextStyle(
                  color: component.isPenalty
                      ? analyticsNegative
                      : analyticsInformation,
                  fontWeight: FontWeight.w900,
                ),
              ),
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(context.l10n.text(component.calculation)),
                ),
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    context.l10n.text(component.formula),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _CashFlowPanel extends StatelessWidget {
  const _CashFlowPanel({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final capital = report.capital;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            icon: Icons.account_balance_outlined,
            title: context.l10n.text('Account cash flow'),
            subtitle: context.l10n.text(
              'Cash flow is reconciled separately from trading performance.',
            ),
          ),
          const SizedBox(height: 14),
          TradingMetricStrip(
            metrics: [
              AnalyticsMetricData(
                label: context.l10n.text('Deposits'),
                value: formatMoney(capital.totalDeposits),
                icon: Icons.south_west,
                tone: analyticsInformation,
              ),
              AnalyticsMetricData(
                label: context.l10n.text('Withdrawals'),
                value: formatMoney(capital.totalWithdrawals),
                icon: Icons.north_east,
                tone: analyticsWarning,
              ),
              AnalyticsMetricData(
                label: context.l10n.text('Net cash flow'),
                value: formatSignedMoney(capital.netCapital),
                icon: Icons.swap_vert,
                tone: analyticsInformation,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DataQualityPanel extends StatelessWidget {
  const _DataQualityPanel({
    required this.report,
    required this.rawRecordCount,
    required this.rawCapitalCount,
  });

  final TradingReport report;
  final int rawRecordCount;
  final int rawCapitalCount;

  @override
  Widget build(BuildContext context) {
    final outsideScope = math.max(0, rawRecordCount - report.trades.length);
    final incomplete = report.trades.where((trade) {
      return trade.notes.trim().isEmpty ||
          trade.strategyName?.trim().isEmpty != false ||
          trade.psychologyNotes.trim().isEmpty;
    }).length;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            icon: Icons.fact_check_outlined,
            title: context.l10n.text('Report data quality'),
            subtitle: context.l10n.text(
              'Understand what was included, separated, or filtered out.',
            ),
          ),
          const SizedBox(height: 10),
          _QualityRow('Included completed trades', report.trades.length),
          _QualityRow(
            'Records outside the selected report scope',
            outsideScope,
          ),
          _QualityRow(
            'Deposits and withdrawals separated',
            report.capital.transactions,
          ),
          _QualityRow('Incomplete journal context', incomplete),
          _QualityRow('Available capital records', rawCapitalCount),
          const SizedBox(height: 10),
          InsightBanner(
            title: context.l10n.text('Eligibility safeguards active'),
            message: context.l10n.text(
              'Cancelled, rejected, expired, unactivated, balance-operation, and duplicate MT5 lifecycle records are excluded before journal analytics are built.',
            ),
            icon: Icons.verified_user_outlined,
            tone: analyticsPositive,
          ),
        ],
      ),
    );
  }
}

class _QualityRow extends StatelessWidget {
  const _QualityRow(this.label, this.value);

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        const Icon(
          Icons.check_circle_outline,
          size: 18,
          color: analyticsPositive,
        ),
        const SizedBox(width: 9),
        Expanded(child: Text(context.l10n.text(label))),
        Text('$value', style: const TextStyle(fontWeight: FontWeight.w900)),
      ],
    ),
  );
}

class _PerformanceSection extends StatelessWidget {
  const _PerformanceSection({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    return Column(
      children: [
        AnalyticsPerformanceChart(
          dailyStats: report.dailyStats,
          title: 'Trading performance',
        ),
        const SizedBox(height: 14),
        TradingMetricStrip(
          metrics: [
            AnalyticsMetricData(
              label: context.l10n.text('Average win'),
              value: formatSignedMoney(summary.averageWin),
              icon: Icons.arrow_upward,
              tone: analyticsPositive,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Average loss'),
              value: formatSignedMoney(-summary.averageLoss),
              icon: Icons.arrow_downward,
              tone: analyticsNegative,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Largest win'),
              value: formatSignedMoney(summary.largestWin),
              icon: Icons.north_east,
              tone: analyticsPositive,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Largest loss'),
              value: formatSignedMoney(summary.largestLoss),
              icon: Icons.south_east,
              tone: analyticsNegative,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Consecutive wins'),
              value: '${summary.longestWinStreak}',
              icon: Icons.local_fire_department_outlined,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Consecutive losses'),
              value: '${summary.longestLossStreak}',
              icon: Icons.pause_circle_outline,
              tone: analyticsWarning,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Average holding'),
              value: summary.averageHoldingLabel,
              icon: Icons.timer_outlined,
            ),
            AnalyticsMetricData(
              label: context.l10n.text('Best weekday'),
              value: context.l10n.text(summary.bestWeekday),
              icon: Icons.calendar_month_outlined,
            ),
          ],
        ),
      ],
    );
  }
}

class _RiskSection extends StatelessWidget {
  const _RiskSection({required this.report, required this.model});

  final TradingReport report;
  final StatisticsViewModel model;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Risk controls'),
            subtitle: context.l10n.text(
              'Limits, drawdown, and affected-trade compliance.',
            ),
          ),
          const SizedBox(height: 16),
          for (final rule in report.ruleMonitor) ...[
            AnalyticsProgressRow(
              label: context.l10n.text(rule.title),
              valueLabel: context.l10n.text(rule.status),
              progress: rule.progress / 100,
              tone: rule.breached
                  ? analyticsNegative
                  : rule.status == 'Passed'
                  ? analyticsPositive
                  : analyticsWarning,
              caption: context.l10n.text(rule.caption),
            ),
            const SizedBox(height: 16),
          ],
          TradingMetricStrip(
            metrics: [
              AnalyticsMetricData(
                label: context.l10n.text('Maximum drawdown'),
                value: formatPercent(report.summary.maxDrawdownPercent),
                icon: Icons.waterfall_chart,
                detail: formatMoney(report.summary.maxDrawdownAmount),
                tone: analyticsWarning,
              ),
              AnalyticsMetricData(
                label: context.l10n.text('Risk-rule exceptions'),
                value: '${model.riskViolations.length}',
                icon: Icons.shield_outlined,
                tone: model.riskViolations.isEmpty
                    ? analyticsPositive
                    : analyticsNegative,
              ),
              AnalyticsMetricData(
                label: context.l10n.text('Stop-loss exceptions'),
                value: '${model.stopLossViolations.length}',
                icon: Icons.gpp_maybe_outlined,
                tone: model.stopLossViolations.isEmpty
                    ? analyticsPositive
                    : analyticsNegative,
              ),
              AnalyticsMetricData(
                label: context.l10n.text('Oversized losses'),
                value: '${report.behaviourProfile.oversizedLossCount}',
                icon: Icons.warning_amber_outlined,
                tone: report.behaviourProfile.oversizedLossCount == 0
                    ? analyticsPositive
                    : analyticsNegative,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _BehaviourSection extends StatelessWidget {
  const _BehaviourSection({required this.report, required this.model});

  final TradingReport report;
  final StatisticsViewModel model;

  @override
  Widget build(BuildContext context) {
    final profile = report.behaviourProfile;
    return Column(
      children: [
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Discipline and behaviour'),
                subtitle: context.l10n.text(
                  'Evidence-based checks from the selected completed trades.',
                ),
              ),
              const SizedBox(height: 16),
              Wrap(
                alignment: WrapAlignment.spaceEvenly,
                spacing: 16,
                runSpacing: 16,
                children: [
                  PerformanceScoreRing(
                    score: profile.finalScore,
                    label: context.l10n.text('Behaviour'),
                  ),
                  PerformanceScoreRing(
                    score: model.planCompliance,
                    label: context.l10n.text('Plan'),
                  ),
                  PerformanceScoreRing(
                    score: model.averagePsychologyScore,
                    label: context.l10n.text('Psychology'),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TradingMetricStrip(
                metrics: [
                  AnalyticsMetricData(
                    label: context.l10n.text('Revenge-trading events'),
                    value: '${profile.revenge.eventCount}',
                    icon: Icons.replay_circle_filled_outlined,
                    tone: profile.revenge.eventCount == 0
                        ? analyticsPositive
                        : analyticsNegative,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Gambling-pattern events'),
                    value: '${profile.gambling.eventCount}',
                    icon: Icons.casino_outlined,
                    tone: profile.gambling.eventCount == 0
                        ? analyticsPositive
                        : analyticsNegative,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Layered trades'),
                    value: '${profile.layeredTradeCount}',
                    icon: Icons.layers_outlined,
                    tone: profile.layeredTradeCount == 0
                        ? analyticsPositive
                        : analyticsWarning,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Overtrading days'),
                    value: '${profile.overtradingDays}',
                    icon: Icons.speed_outlined,
                    tone: profile.overtradingDays == 0
                        ? analyticsPositive
                        : analyticsWarning,
                  ),
                ],
              ),
              const SizedBox(height: 16),
              for (final check in profile.checks.take(12))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    check.status == 'Passed'
                        ? Icons.check_circle_outline
                        : Icons.info_outline,
                    color: check.status == 'Passed'
                        ? analyticsPositive
                        : analyticsWarning,
                  ),
                  title: Text(context.l10n.text(check.name)),
                  subtitle: Text(context.l10n.text(check.description)),
                  trailing: Text(
                    context.l10n.text(check.value),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        InsightBanner(
          title: context.l10n.text('Psychology record coverage'),
          message: context.l10n.text(
            '{percent} of included trades contain psychology context.',
            {'percent': formatPercent(model.psychologyCompletion)},
          ),
          icon: Icons.psychology_alt_outlined,
          tone: model.psychologyCompletion >= 80
              ? analyticsPositive
              : analyticsWarning,
        ),
      ],
    );
  }
}

class _BreakdownSection extends StatelessWidget {
  const _BreakdownSection({required this.report, required this.strategyStats});

  final TradingReport report;
  final List<TradingBreakdownStat> strategyStats;

  @override
  Widget build(BuildContext context) {
    final groups = [
      ('Performance by symbol', report.pairStats),
      ('Performance by strategy', strategyStats),
      ('Performance by session', report.sessionStats),
      ('Performance by weekday', report.weekdayStats),
      ('Buy versus sell performance', report.directionStats),
      ('Monthly performance', report.monthlyStats),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 850;
        final width = wide
            ? (constraints.maxWidth - 12) / 2
            : constraints.maxWidth;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (final group in groups)
              SizedBox(
                width: width,
                child: _BreakdownPanel(title: group.$1, stats: group.$2),
              ),
          ],
        );
      },
    );
  }
}

class _BreakdownPanel extends StatelessWidget {
  const _BreakdownPanel({required this.title, required this.stats});

  final String title;
  final List<TradingBreakdownStat> stats;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(title: context.l10n.text(title)),
          const SizedBox(height: 9),
          if (stats.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 18),
              child: Text(context.l10n.text('No breakdown data available')),
            )
          else
            for (final stat in stats.take(8))
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.l10n.text(stat.label),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          Text(
                            context.l10n.text('{count} trade(s) · {rate}', {
                              'count': stat.trades,
                              'rate': formatPercent(stat.winRate),
                            }),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      formatSignedMoney(stat.profitLoss),
                      style: TextStyle(
                        color: _financialTone(stat.profitLoss),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
        ],
      ),
    );
  }
}

class _TradesSection extends StatelessWidget {
  const _TradesSection({required this.trades});

  final List<TradingJournalEntry> trades;

  @override
  Widget build(BuildContext context) {
    if (trades.isEmpty) {
      return AnalyticsEmptyState(
        title: context.l10n.text('No completed trades'),
        message: context.l10n.text(
          'No eligible completed trades match the selected report filters.',
        ),
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth >= 760) {
          return _TradeTable(trades: trades);
        }
        return AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Included trades'),
                subtitle: context.l10n.text('{count} completed trade(s)', {
                  'count': trades.length,
                }),
              ),
              const SizedBox(height: 8),
              for (final trade in trades.reversed.take(100))
                _ExpandableTradeRow(trade: trade),
            ],
          ),
        );
      },
    );
  }
}

class _TradeTable extends StatelessWidget {
  const _TradeTable({required this.trades});

  final List<TradingJournalEntry> trades;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Included trades'),
            subtitle: context.l10n.text('{count} completed trade(s)', {
              'count': trades.length,
            }),
          ),
          const SizedBox(height: 10),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                for (final label in [
                  'Date',
                  'Symbol',
                  'Direction',
                  'Lot size',
                  'Pips',
                  'Profit / Loss',
                  'Strategy',
                ])
                  DataColumn(label: Text(context.l10n.text(label))),
              ],
              rows: [
                for (final trade in trades.reversed.take(200))
                  DataRow(
                    cells: [
                      DataCell(Text(formatDateTime(trade.closeDate))),
                      DataCell(Text(trade.pair)),
                      DataCell(
                        Text(
                          context.l10n.text(
                            trade.direction == 1 ? 'Buy' : 'Sell',
                          ),
                        ),
                      ),
                      DataCell(Text(trade.lotSize.toStringAsFixed(2))),
                      DataCell(Text(trade.pips.toStringAsFixed(1))),
                      DataCell(
                        Text(
                          formatSignedMoney(trade.profitLoss),
                          style: TextStyle(
                            color: _financialTone(trade.profitLoss),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      DataCell(
                        Text(
                          trade.strategyName?.trim().isNotEmpty == true
                              ? trade.strategyName!
                              : context.l10n.text('Unspecified'),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ExpandableTradeRow extends StatelessWidget {
  const _ExpandableTradeRow({required this.trade});

  final TradingJournalEntry trade;

  @override
  Widget build(BuildContext context) {
    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      childrenPadding: const EdgeInsets.only(bottom: 12),
      leading: CircleAvatar(
        backgroundColor: _financialTone(
          trade.profitLoss,
        ).withValues(alpha: .12),
        child: Icon(
          trade.direction == 1 ? Icons.arrow_upward : Icons.arrow_downward,
          color: _financialTone(trade.profitLoss),
        ),
      ),
      title: Row(
        children: [
          Expanded(
            child: Text(
              trade.pair,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          Text(
            formatSignedMoney(trade.profitLoss),
            style: TextStyle(
              color: _financialTone(trade.profitLoss),
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
      subtitle: Text(
        context.l10n.text('{direction} · {lot} lots · {time}', {
          'direction': context.l10n.text(trade.direction == 1 ? 'Buy' : 'Sell'),
          'lot': trade.lotSize.toStringAsFixed(2),
          'time': formatDateTime(trade.closeDate),
        }),
      ),
      children: [
        _TradeDetail('Pips', trade.pips.toStringAsFixed(1)),
        _TradeDetail(
          'Strategy',
          trade.strategyName?.trim().isNotEmpty == true
              ? trade.strategyName!
              : context.l10n.text('Unspecified'),
        ),
        _TradeDetail(
          'Trading plan',
          trade.followedTradingPlan ? 'Followed' : 'Not followed',
        ),
        _TradeDetail(
          'Risk rules',
          trade.followedRiskRules ? 'Followed' : 'Not followed',
        ),
        _TradeDetail(
          'Psychology',
          trade.psychologyNotes.trim().isEmpty
              ? context.l10n.text('Not recorded')
              : trade.psychologyNotes,
        ),
        _TradeDetail(
          'Notes',
          trade.notes.trim().isEmpty
              ? context.l10n.text('Not recorded')
              : trade.notes,
        ),
      ],
    );
  }
}

class _TradeDetail extends StatelessWidget {
  const _TradeDetail(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 110,
          child: Text(
            context.l10n.text(label),
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ),
        Expanded(child: Text(context.l10n.text(value))),
      ],
    ),
  );
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
