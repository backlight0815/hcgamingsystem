import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/trading_report.dart';
import '../../shared/analytics/analytics_performance_chart.dart';
import '../../shared/analytics/analytics_widgets.dart';
import '../../shared/formatters.dart';
import '../statistics/statistics_view_model.dart';

class PerformanceOverviewSection extends StatelessWidget {
  const PerformanceOverviewSection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final report = model.report;
    final summary = report.summary;
    final hasScore = summary.totalTrades >= 3;
    final tone = _performanceTone(model);
    final metrics = <_MetricSpec>[
      _MetricSpec(
        key: 'netProfit',
        label: 'Net profit',
        value: _money(summary.netProfitLoss, currency, signed: true),
        icon: Icons.account_balance_wallet_outlined,
        tone: _financialTone(summary.netProfitLoss),
        help:
            'Gross profit minus gross loss. Funding transactions are excluded.',
      ),
      _MetricSpec(
        key: 'winRate',
        label: 'Win rate',
        value: _percent(summary.winRate, 1),
        icon: Icons.track_changes_outlined,
        tone: analyticsInformation,
        help: 'Winning trades divided by winning and losing closed trades.',
      ),
      _MetricSpec(
        key: 'profitFactor',
        label: 'Profit factor',
        value: context.l10n.text(summary.profitFactorLabel),
        icon: Icons.balance_outlined,
        tone: summary.profitFactor != null && summary.profitFactor! >= 1
            ? analyticsPositive
            : analyticsWarning,
        help: 'Gross profit divided by the absolute value of gross loss.',
      ),
      _MetricSpec(
        key: 'drawdown',
        label: 'Maximum drawdown',
        value: _percent(summary.maxDrawdownPercent, 1),
        detail: _money(summary.maxDrawdownAmount, currency),
        icon: Icons.waterfall_chart,
        tone: summary.maxDrawdownPercent >= 10
            ? analyticsNegative
            : analyticsWarning,
        help: 'Largest peak-to-trough decline in the realized balance curve.',
      ),
      _MetricSpec(
        key: 'expectancy',
        label: 'Expectancy',
        value: _money(summary.expectancy, currency, signed: true),
        icon: Icons.insights_outlined,
        tone: _financialTone(summary.expectancy),
        help:
            'Expected result per trade from win probability and average outcomes.',
      ),
      _MetricSpec(
        label: 'Average risk-to-reward',
        value: context.l10n.text('Insufficient data'),
        icon: Icons.compare_arrows,
        help:
            'A ratio is shown only when numeric stop-loss risk and target reward are available for eligible trades.',
      ),
    ];

    return Column(
      children: [
        PerformanceHero(
          label: context.l10n.text('Overall performance score'),
          value: hasScore
              ? report.scoreProfile.score.toStringAsFixed(0)
              : context.l10n.text('Insufficient data'),
          caption: context.l10n.text(
            hasScore
                ? 'The score blends results, drawdown, consistency, discipline, and behaviour evidence.'
                : 'At least three eligible closed trades are needed for a meaningful score.',
          ),
          statusLabel: context.l10n.text(_performanceLevel(model)),
          statusIcon: hasScore
              ? Icons.auto_graph_rounded
              : Icons.hourglass_empty,
          tone: tone,
          metrics: [
            AnalyticsHeroMetric(
              label: context.l10n.text('Net profit'),
              value: _money(summary.netProfitLoss, currency, signed: true),
              icon: Icons.payments_outlined,
              tone: _financialTone(summary.netProfitLoss),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Account growth'),
              value: _percent(summary.growthPercent, 1, signed: true),
              icon: Icons.trending_up,
              tone: _financialTone(summary.growthPercent),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Consistency'),
              value: summary.hasTrades
                  ? _percent(model.consistencyScore, 0)
                  : context.l10n.text('N/A'),
              icon: Icons.equalizer_rounded,
              tone: _scoreTone(model.consistencyScore),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Risk status'),
              value: context.l10n.text(model.riskStatus),
              icon: Icons.shield_outlined,
              tone: _riskTone(model.riskStatus),
            ),
          ],
        ),
        const SizedBox(height: 16),
        _MetricGrid(metrics: metrics, model: model),
        const SizedBox(height: 16),
        _responsivePair(
          context,
          first: _OutcomeSurface(report: report, currency: currency),
          second: _InsightSurface(model: model),
        ),
        const SizedBox(height: 16),
        _SecondaryMetrics(report: report, model: model, currency: currency),
      ],
    );
  }
}

class PerformanceGrowthSection extends StatelessWidget {
  const PerformanceGrowthSection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final report = model.report;
    return Column(
      children: [
        AnalyticsPerformanceChart(
          dailyStats: report.dailyStats,
          title: 'Account growth and cumulative profit',
        ),
        const SizedBox(height: 16),
        _MetricGrid(
          model: model,
          metrics: [
            _MetricSpec(
              label: 'Current balance',
              value: _money(report.summary.currentBalance, currency),
              icon: Icons.account_balance_outlined,
              help:
                  'Balance after eligible realized trading results and separate cash flow.',
            ),
            if (report.account?.equity != null)
              _MetricSpec(
                label: 'Current equity',
                value: _money(report.account!.equity!, currency),
                icon: Icons.show_chart,
                help:
                    'Latest synchronized account equity. Historical equity snapshots are not available in the current data model.',
              ),
            _MetricSpec(
              label: 'Account growth',
              value: _percent(report.summary.growthPercent, 1, signed: true),
              icon: Icons.stacked_line_chart,
              tone: _financialTone(report.summary.growthPercent),
              help:
                  'Net trading result divided by the report starting balance.',
            ),
            _MetricSpec(
              label: 'Best day',
              value: _money(report.summary.bestDay, currency, signed: true),
              icon: Icons.north_east,
              tone: analyticsPositive,
              help: 'Highest summed realized result for one trading day.',
            ),
            _MetricSpec(
              label: 'Worst day',
              value: _money(report.summary.worstDay, currency, signed: true),
              icon: Icons.south_east,
              tone: analyticsNegative,
              help: 'Lowest summed realized result for one trading day.',
            ),
          ],
        ),
        const SizedBox(height: 16),
        _DrawdownTimelineCard(model: model, currency: currency),
        const SizedBox(height: 16),
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Funding activity'),
                subtitle: context.l10n.text(
                  'Deposits and withdrawals are marked separately and never counted as trading profit.',
                ),
                icon: Icons.swap_vert_circle_outlined,
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 24,
                runSpacing: 12,
                children: [
                  _LabelValue(
                    label: 'Deposits',
                    value: _money(report.capital.totalDeposits, currency),
                  ),
                  _LabelValue(
                    label: 'Withdrawals',
                    value: _money(report.capital.totalWithdrawals, currency),
                  ),
                  _LabelValue(
                    label: 'Funding transactions',
                    value: '${report.capital.transactions}',
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class PerformanceCalendarSection extends StatefulWidget {
  const PerformanceCalendarSection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  State<PerformanceCalendarSection> createState() =>
      _PerformanceCalendarSectionState();
}

class _PerformanceCalendarSectionState
    extends State<PerformanceCalendarSection> {
  late DateTime _month = _initialMonth();
  var _listMode = false;

  DateTime _initialMonth() {
    final last = widget.model.report.summary.lastTradeDate;
    final value = last ?? DateTime.now();
    return DateTime(value.year, value.month);
  }

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final monthEntries =
        widget.model.tradesByDay.entries
            .where(
              (entry) =>
                  entry.key.year == _month.year &&
                  entry.key.month == _month.month,
            )
            .toList()
          ..sort((left, right) => left.key.compareTo(right.key));
    final monthTotal = monthEntries.fold<double>(
      0,
      (sum, entry) =>
          sum +
          entry.value.fold<double>(
            0,
            (value, trade) => value + trade.profitLoss,
          ),
    );
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Profit and loss calendar'),
            subtitle: context.l10n.text(
              'Select a day to inspect completed trades and recorded behaviour.',
            ),
            icon: Icons.calendar_month_outlined,
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              IconButton(
                tooltip: context.l10n.text('Previous month'),
                onPressed: () => setState(() {
                  _month = DateTime(_month.year, _month.month - 1);
                }),
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  DateFormat.yMMMM(locale).format(_month),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                tooltip: context.l10n.text('Next month'),
                onPressed: () => setState(() {
                  _month = DateTime(_month.year, _month.month + 1);
                }),
                icon: const Icon(Icons.chevron_right),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                tooltip: context.l10n.text(
                  _listMode ? 'Calendar view' : 'List view',
                ),
                onPressed: () => setState(() => _listMode = !_listMode),
                icon: Icon(_listMode ? Icons.calendar_view_month : Icons.list),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (_listMode)
            _CalendarList(
              entries: monthEntries,
              currency: widget.currency,
              onTap: _showDay,
            )
          else
            _CalendarGrid(
              month: _month,
              tradesByDay: widget.model.tradesByDay,
              currency: widget.currency,
              onTap: _showDay,
            ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 22,
            runSpacing: 8,
            children: [
              _LabelValue(
                label: 'Monthly total',
                value: _money(monthTotal, widget.currency, signed: true),
                tone: _financialTone(monthTotal),
              ),
              _LabelValue(
                label: 'Trading days',
                value: '${monthEntries.length}',
              ),
              _LabelValue(
                label: 'Most active day',
                value: monthEntries.isEmpty
                    ? context.l10n.text('N/A')
                    : DateFormat.MMMd(locale).format(
                        (monthEntries.toList()..sort(
                              (a, b) =>
                                  b.value.length.compareTo(a.value.length),
                            ))
                            .first
                            .key,
                      ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showDay(DateTime date, List<TradingJournalEntry> trades) {
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      builder: (context) => _DayDetailsSheet(
        date: date,
        trades: trades,
        currency: widget.currency,
      ),
    );
  }
}

class PerformanceBreakdownSection extends StatefulWidget {
  const PerformanceBreakdownSection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  State<PerformanceBreakdownSection> createState() =>
      _PerformanceBreakdownSectionState();
}

class _PerformanceBreakdownSectionState
    extends State<PerformanceBreakdownSection> {
  var _group = 0;
  var _sort = _BreakdownSort.profitHigh;

  @override
  Widget build(BuildContext context) {
    final groups = <(String, List<TradingBreakdownStat>)>[
      ('Symbol', widget.model.report.pairStats),
      ('Strategy', widget.model.strategyStats),
      ('Session', widget.model.report.sessionStats),
      ('Weekday', widget.model.report.weekdayStats),
      ('Direction', widget.model.report.directionStats),
      ('Account', widget.model.accountStats),
    ];
    final rows = [...groups[_group].$2]..sort(_sort.compare);
    final totalProfit = widget.model.report.summary.netProfitLoss;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Performance breakdown'),
            subtitle: context.l10n.text(
              'Compare contribution, win rate, activity, and average outcome.',
            ),
            icon: Icons.account_tree_outlined,
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: SegmentedButton<int>(
              showSelectedIcon: false,
              segments: [
                for (final indexed in groups.indexed)
                  ButtonSegment(
                    value: indexed.$1,
                    label: Text(context.l10n.text(indexed.$2.$1)),
                  ),
              ],
              selected: {_group},
              onSelectionChanged: (value) =>
                  setState(() => _group = value.first),
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: DropdownButton<_BreakdownSort>(
              value: _sort,
              items: [
                for (final value in _BreakdownSort.values)
                  DropdownMenuItem(
                    value: value,
                    child: Text(context.l10n.text(value.label)),
                  ),
              ],
              onChanged: (value) => setState(() {
                _sort = value ?? _sort;
              }),
            ),
          ),
          if (rows.isEmpty)
            AnalyticsEmptyState(
              title: context.l10n.text('No breakdown data available'),
              message: context.l10n.text(
                'Choose a period with eligible completed trades.',
              ),
            )
          else
            for (final row in rows)
              _BreakdownRow(
                row: row,
                currency: widget.currency,
                totalProfit: totalProfit,
              ),
        ],
      ),
    );
  }
}

class PerformanceRiskSection extends StatelessWidget {
  const PerformanceRiskSection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final summary = model.report.summary;
    return Column(
      children: [
        InsightBanner(
          title: context.l10n.text('Account risk status: {status}', {
            'status': context.l10n.text(model.riskStatus),
          }),
          message: context.l10n.text(
            'Risk status combines drawdown and recorded rule compliance; profitability alone cannot produce a safe status.',
          ),
          icon: Icons.health_and_safety_outlined,
          tone: _riskTone(model.riskStatus),
        ),
        const SizedBox(height: 16),
        _MetricGrid(
          model: model,
          metrics: [
            _MetricSpec(
              key: 'drawdown',
              label: 'Maximum drawdown',
              value: _percent(summary.maxDrawdownPercent, 1),
              detail: _money(summary.maxDrawdownAmount, currency),
              icon: Icons.waterfall_chart,
              tone: analyticsNegative,
              help: 'Largest realized peak-to-trough balance decline.',
            ),
            _MetricSpec(
              label: 'Current drawdown',
              value: _percent(model.currentDrawdownPercent, 1),
              detail: _money(model.currentDrawdownAmount, currency),
              icon: Icons.show_chart,
              tone: analyticsWarning,
              help: 'Latest realized balance below its prior peak.',
            ),
            _MetricSpec(
              label: 'Maximum daily loss',
              value: _percent(summary.maxDailyLossPercent, 1),
              detail: _money(summary.maxDailyLossAmount, currency),
              icon: Icons.event_busy_outlined,
              tone: summary.maxDailyLossPercent >= 5
                  ? analyticsNegative
                  : analyticsWarning,
              help:
                  'Largest losing trading day as a share of starting balance.',
            ),
            _MetricSpec(
              label: 'Consecutive losses',
              value: '${summary.longestLossStreak}',
              icon: Icons.keyboard_double_arrow_down,
              tone: analyticsWarning,
              help: 'Longest uninterrupted sequence of losing trades.',
            ),
            _MetricSpec(
              label: 'Trades without stop-loss compliance',
              value: '${model.stopLossViolations.length}',
              icon: Icons.gpp_bad_outlined,
              tone: model.stopLossViolations.isEmpty
                  ? analyticsPositive
                  : analyticsNegative,
              help:
                  'Closed trades recorded as not following the stop-loss rule.',
            ),
            _MetricSpec(
              label: 'Risk-rule violations',
              value: '${model.riskViolations.length}',
              icon: Icons.warning_amber_outlined,
              tone: model.riskViolations.isEmpty
                  ? analyticsPositive
                  : analyticsNegative,
              help: 'Closed trades recorded as not following risk rules.',
            ),
          ],
        ),
        const SizedBox(height: 16),
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Risk-rule monitor'),
                subtitle: context.l10n.text(
                  'Generic limits are used here; Prop Firm accounts use their saved challenge rules.',
                ),
              ),
              const SizedBox(height: 16),
              for (final item in model.report.ruleMonitor) ...[
                AnalyticsProgressRow(
                  label: context.l10n.text(item.title),
                  valueLabel: context.l10n.text(item.value),
                  progress: item.progress / 100,
                  caption: context.l10n.text(item.caption),
                  tone: _ruleTone(item.tone),
                ),
                const SizedBox(height: 16),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _DrawdownTimelineCard extends StatelessWidget {
  const _DrawdownTimelineCard({required this.model, required this.currency});

  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final points = model.drawdownTimeline;
    final drawdownPoints = points.where((point) => point.amount > 0).toList();
    if (drawdownPoints.isEmpty) {
      return AnalyticsSurface(
        child: AnalyticsEmptyState(
          icon: Icons.timeline_outlined,
          title: context.l10n.text('Drawdown timeline'),
          message: context.l10n.text(
            'No realized drawdown was recorded in this period.',
          ),
        ),
      );
    }

    var lowestIndex = 0;
    for (var index = 1; index < points.length; index++) {
      if (points[index].amount > points[lowestIndex].amount) {
        lowestIndex = index;
      }
    }
    var startIndex = lowestIndex;
    while (startIndex > 0 && points[startIndex - 1].amount > 0) {
      startIndex--;
    }
    int? recoveryIndex;
    for (var index = lowestIndex + 1; index < points.length; index++) {
      if (points[index].amount == 0) {
        recoveryIndex = index;
        break;
      }
    }
    final start = points[startIndex].date;
    final lowest = points[lowestIndex];
    final end = recoveryIndex == null
        ? points.last.date
        : points[recoveryIndex].date;
    final duration = math.max(1, end.difference(start).inDays + 1);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final date = DateFormat.MMMd(locale);
    final summary = context.l10n.text(
      '{depth} maximum realized drawdown over {days} day(s).',
      {'depth': _percent(lowest.percent, 1), 'days': duration},
    );

    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Drawdown timeline'),
            subtitle: context.l10n.text(
              'Tracks peak-to-trough decline in the realized balance curve.',
            ),
            icon: Icons.timeline_outlined,
          ),
          const SizedBox(height: 16),
          Semantics(
            label: summary,
            image: true,
            child: ExcludeSemantics(
              child: SizedBox(
                height: 112,
                width: double.infinity,
                child: CustomPaint(
                  painter: _DrawdownTimelinePainter(
                    points: points,
                    tone: analyticsNegative,
                    grid: Theme.of(context).colorScheme.outlineVariant,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(summary, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 12),
          Wrap(
            spacing: 24,
            runSpacing: 12,
            children: [
              _TimelineFact(label: 'Drawdown start', value: date.format(start)),
              _TimelineFact(
                label: 'Lowest point',
                value:
                    '${date.format(lowest.date)} · ${_money(lowest.amount, currency)}',
              ),
              _TimelineFact(
                label: 'Recovery point',
                value: recoveryIndex == null
                    ? context.l10n.text('Not recovered')
                    : date.format(points[recoveryIndex].date),
              ),
              _TimelineFact(
                label: 'Duration',
                value: context.l10n.text('{count} day(s)', {'count': duration}),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TimelineFact extends StatelessWidget {
  const _TimelineFact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 180,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text(label),
            style: Theme.of(context).textTheme.labelMedium,
          ),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _DrawdownTimelinePainter extends CustomPainter {
  const _DrawdownTimelinePainter({
    required this.points,
    required this.tone,
    required this.grid,
  });

  final List<PerformanceDrawdownPoint> points;
  final Color tone;
  final Color grid;

  @override
  void paint(Canvas canvas, Size size) {
    final gridPaint = Paint()
      ..color = grid
      ..strokeWidth = 1;
    for (var index = 0; index <= 4; index++) {
      final y = size.height * index / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), gridPaint);
    }
    final maximum = points.fold<double>(
      0,
      (value, point) => math.max(value, point.amount),
    );
    if (maximum <= 0 || points.isEmpty) return;
    final line = Path();
    final area = Path()..moveTo(0, 0);
    for (final indexed in points.indexed) {
      final x = points.length == 1
          ? size.width
          : size.width * indexed.$1 / (points.length - 1);
      final y = size.height * indexed.$2.amount / maximum;
      if (indexed.$1 == 0) {
        line.moveTo(x, y);
        area.lineTo(x, y);
      } else {
        line.lineTo(x, y);
        area.lineTo(x, y);
      }
    }
    area
      ..lineTo(size.width, 0)
      ..close();
    canvas.drawPath(area, Paint()..color = tone.withValues(alpha: 0.12));
    canvas.drawPath(
      line,
      Paint()
        ..color = tone
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(covariant _DrawdownTimelinePainter oldDelegate) =>
      oldDelegate.points != points ||
      oldDelegate.tone != tone ||
      oldDelegate.grid != grid;
}

class PerformanceConsistencySection extends StatelessWidget {
  const PerformanceConsistencySection({
    super.key,
    required this.model,
    required this.currency,
  });

  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AnalyticsSurface(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final details = Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _LabelValue(
                    label: 'Strongest consistency area',
                    value: context.l10n.text(model.strongestConsistencyArea),
                    tone: analyticsPositive,
                  ),
                  const SizedBox(height: 12),
                  _LabelValue(
                    label: 'Weakest consistency area',
                    value: context.l10n.text(model.weakestConsistencyArea),
                    tone: analyticsWarning,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    context.l10n.text(
                      'Consistency blends daily-result stability, position sizing, risk compliance, and profit concentration.',
                    ),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              );
              if (constraints.maxWidth < 520) {
                return Column(
                  children: [
                    PerformanceScoreRing(
                      score: model.consistencyScore,
                      label: context.l10n.text('Consistency'),
                    ),
                    const SizedBox(height: 18),
                    details,
                  ],
                );
              }
              return Row(
                children: [
                  PerformanceScoreRing(
                    score: model.consistencyScore,
                    label: context.l10n.text('Consistency'),
                  ),
                  const SizedBox(width: 24),
                  Expanded(child: details),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 16),
        _MetricGrid(
          model: model,
          metrics: [
            _MetricSpec(
              label: 'Stable trading days',
              value: '${model.stableTradingDays}',
              icon: Icons.calendar_today_outlined,
              help:
                  'Days whose result is within one standard deviation of the period average.',
            ),
            _MetricSpec(
              label: 'Profit concentration',
              value: _percent(model.profitConcentration, 1),
              icon: Icons.pie_chart_outline,
              tone: model.profitConcentration > 40
                  ? analyticsWarning
                  : analyticsPositive,
              help: 'Largest winning trade divided by gross profit.',
            ),
            _MetricSpec(
              label: 'Average daily result',
              value: _money(model.averageDailyResult, currency, signed: true),
              icon: Icons.today_outlined,
              tone: _financialTone(model.averageDailyResult),
              help: 'Mean realized result across days with completed trades.',
            ),
            _MetricSpec(
              label: 'Result standard deviation',
              value: _money(model.dailyResultDeviation, currency),
              icon: Icons.multiline_chart,
              help: 'Population standard deviation of daily realized results.',
            ),
          ],
        ),
        if (model.profitConcentration > 40) ...[
          const SizedBox(height: 16),
          InsightBanner(
            title: context.l10n.text('Profit concentration warning'),
            message: context.l10n.text(
              'A large percentage of total gross profit came from one trade.',
            ),
            tone: analyticsWarning,
            icon: Icons.center_focus_strong_outlined,
          ),
        ],
      ],
    );
  }
}

class PerformancePsychologySection extends StatelessWidget {
  const PerformancePsychologySection({super.key, required this.model});

  final StatisticsViewModel model;

  @override
  Widget build(BuildContext context) {
    if (model.psychologyCompletion == 0) {
      return AnalyticsSurface(
        child: AnalyticsEmptyState(
          icon: Icons.psychology_alt_outlined,
          title: context.l10n.text('No psychology records'),
          message: context.l10n.text(
            'Add journal psychology notes or ratings to build this analysis.',
          ),
        ),
      );
    }
    return Column(
      children: [
        AnalyticsSurface(
          child: Wrap(
            spacing: 28,
            runSpacing: 20,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              PerformanceScoreRing(
                score: model.report.behaviourProfile.finalScore,
                label: context.l10n.text('Discipline'),
              ),
              PerformanceScoreRing(
                score: model.averagePsychologyScore,
                label: context.l10n.text('Psychology'),
              ),
              PerformanceScoreRing(
                score: model.planCompliance,
                label: context.l10n.text('Plan compliance'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _MetricGrid(
          model: model,
          metrics: [
            _MetricSpec(
              label: 'Psychology record coverage',
              value: _percent(model.psychologyCompletion, 1),
              icon: Icons.fact_check_outlined,
              help:
                  'Share of included trades with psychology notes or self-ratings.',
            ),
            _MetricSpec(
              label: 'Revenge-trading incidents',
              value: '${model.revengeTradingIncidents}',
              icon: Icons.replay_circle_filled_outlined,
              tone: model.revengeTradingIncidents > 0
                  ? analyticsWarning
                  : analyticsPositive,
              help:
                  'Evidence-based rapid re-entry patterns detected after a loss.',
            ),
            _MetricSpec(
              label: 'FOMO incidents',
              value: '${model.fomoIncidents}',
              icon: Icons.directions_run_outlined,
              tone: model.fomoIncidents > 0
                  ? analyticsWarning
                  : analyticsPositive,
              help: 'Trades explicitly tagged as FOMO in the journal.',
            ),
            _MetricSpec(
              label: 'Overtrading days',
              value: '${model.overtradingIncidents}',
              icon: Icons.speed_outlined,
              tone: model.overtradingIncidents > 0
                  ? analyticsWarning
                  : analyticsPositive,
              help:
                  'Days flagged by the existing behaviour engine for excessive frequency.',
            ),
            _MetricSpec(
              label: 'Unplanned trades',
              value: '${model.unplannedTrades}',
              icon: Icons.rule_folder_outlined,
              tone: model.unplannedTrades > 0
                  ? analyticsWarning
                  : analyticsPositive,
              help:
                  'Included trades recorded as not following the trading plan.',
            ),
            _MetricSpec(
              label: 'Take-profit compliance',
              value: _percent(model.takeProfitCompliance, 1),
              icon: Icons.flag_outlined,
              tone: _scoreTone(model.takeProfitCompliance),
              help:
                  'Included trades recorded as following the take-profit rule.',
            ),
          ],
        ),
        const SizedBox(height: 16),
        AnalyticsSurface(
          child: ExpansionTile(
            tilePadding: EdgeInsets.zero,
            childrenPadding: const EdgeInsets.only(top: 8),
            title: Text(
              context.l10n.text('How the scores were calculated'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              context.l10n.text(
                'These are neutral trading-performance labels, not medical diagnoses.',
              ),
            ),
            children: [
              for (final component in model.report.scoreProfile.components)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text(component.metric)),
                  subtitle: Text(context.l10n.text(component.calculation)),
                  trailing: Text(
                    '${component.points.toStringAsFixed(1)} / ${component.maximumPoints.toStringAsFixed(0)}',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MetricSpec {
  const _MetricSpec({
    required this.label,
    required this.value,
    required this.icon,
    required this.help,
    this.key,
    this.detail,
    this.tone,
  });

  final String? key;
  final String label;
  final String value;
  final String? detail;
  final IconData icon;
  final Color? tone;
  final String help;
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.metrics, required this.model});

  final List<_MetricSpec> metrics;
  final StatisticsViewModel model;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1050
            ? 4
            : constraints.maxWidth >= 680
            ? 3
            : 2;
        final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final metric in metrics)
              SizedBox(
                width: width,
                child: _PerformanceMetricCard(
                  metric: metric,
                  comparison: metric.key == null
                      ? null
                      : model.comparison(metric.key!),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _PerformanceMetricCard extends StatelessWidget {
  const _PerformanceMetricCard({required this.metric, this.comparison});

  final _MetricSpec metric;
  final PerformanceMetricComparison? comparison;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tone = metric.tone ?? theme.colorScheme.primary;
    final trend = comparison;
    return Semantics(
      container: true,
      label: '${context.l10n.text(metric.label)}, ${metric.value}',
      child: Material(
        color: theme.colorScheme.surfaceContainerLow,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: theme.colorScheme.outlineVariant),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(metric.icon, size: 19, color: tone),
                  const SizedBox(width: 7),
                  Expanded(
                    child: Text(
                      context.l10n.text(metric.label),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Tooltip(
                    message: context.l10n.text(metric.help),
                    child: const Icon(Icons.info_outline, size: 17),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  metric.value,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: tone,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (metric.detail != null) ...[
                const SizedBox(height: 3),
                Text(metric.detail!, style: theme.textTheme.bodySmall),
              ],
              const SizedBox(height: 8),
              if (trend == null)
                Text(
                  context.l10n.text('No previous-period comparison'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall,
                )
              else
                _TrendBadge(comparison: trend),
            ],
          ),
        ),
      ),
    );
  }
}

class _TrendBadge extends StatelessWidget {
  const _TrendBadge({required this.comparison});

  final PerformanceMetricComparison comparison;

  @override
  Widget build(BuildContext context) {
    final neutral = comparison.difference.abs() < .005;
    final tone = neutral
        ? analyticsInformation
        : comparison.improved
        ? analyticsPositive
        : analyticsNegative;
    final icon = neutral
        ? Icons.remove
        : comparison.difference > 0
        ? Icons.arrow_upward
        : Icons.arrow_downward;
    return Tooltip(
      message: context.l10n.text(
        'Compared with the previous equivalent period',
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: tone),
          const SizedBox(width: 3),
          Flexible(
            child: Text(
              '${comparison.difference.abs().toStringAsFixed(1)} ${context.l10n.text('vs previous')}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: tone,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OutcomeSurface extends StatelessWidget {
  const _OutcomeSurface({required this.report, required this.currency});

  final TradingReport report;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    final total = math.max(1, summary.totalTrades);
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Trade outcome mix'),
            subtitle: context.l10n.text(
              'Break-even trades remain visible but are excluded from the win-rate denominator.',
            ),
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: SizedBox(
              height: 14,
              child: Row(
                children: [
                  if (summary.winningTrades > 0)
                    Expanded(
                      flex: summary.winningTrades,
                      child: const ColoredBox(color: analyticsPositive),
                    ),
                  if (summary.losingTrades > 0)
                    Expanded(
                      flex: summary.losingTrades,
                      child: const ColoredBox(color: analyticsNegative),
                    ),
                  if (summary.breakevenTrades > 0)
                    Expanded(
                      flex: summary.breakevenTrades,
                      child: const ColoredBox(color: analyticsInformation),
                    ),
                  if (!summary.hasTrades)
                    const Expanded(
                      child: ColoredBox(color: Colors.transparent),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 18,
            runSpacing: 10,
            children: [
              _OutcomeLegend(
                label: 'Wins',
                count: summary.winningTrades,
                share: summary.winningTrades / total * 100,
                tone: analyticsPositive,
              ),
              _OutcomeLegend(
                label: 'Losses',
                count: summary.losingTrades,
                share: summary.losingTrades / total * 100,
                tone: analyticsNegative,
              ),
              _OutcomeLegend(
                label: 'Break even',
                count: summary.breakevenTrades,
                share: summary.breakevenTrades / total * 100,
                tone: analyticsInformation,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            context.l10n.text('Gross profit {profit} · Gross loss {loss}', {
              'profit': _money(summary.totalProfit, currency),
              'loss': _money(summary.totalLoss, currency),
            }),
          ),
        ],
      ),
    );
  }
}

class _OutcomeLegend extends StatelessWidget {
  const _OutcomeLegend({
    required this.label,
    required this.count,
    required this.share,
    required this.tone,
  });

  final String label;
  final int count;
  final double share;
  final Color tone;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(Icons.circle, size: 10, color: tone),
      const SizedBox(width: 6),
      Text('${context.l10n.text(label)} $count (${share.toStringAsFixed(0)}%)'),
    ],
  );
}

class _InsightSurface extends StatelessWidget {
  const _InsightSurface({required this.model});

  final StatisticsViewModel model;

  @override
  Widget build(BuildContext context) {
    final insights = model.insights;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Performance insights'),
            subtitle: context.l10n.text(
              'Deterministic observations from the selected data only.',
            ),
            icon: Icons.lightbulb_outline,
          ),
          const SizedBox(height: 10),
          if (insights.isEmpty)
            Text(
              context.l10n.text(
                'More completed trades are needed before a reliable insight can be generated.',
              ),
            )
          else
            for (final insight in insights.take(3))
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                childrenPadding: const EdgeInsets.only(bottom: 12),
                leading: Icon(
                  insight.kind == PerformanceInsightKind.attention
                      ? Icons.warning_amber_outlined
                      : Icons.insights_outlined,
                  color: insight.kind == PerformanceInsightKind.attention
                      ? analyticsWarning
                      : analyticsInformation,
                ),
                title: Text(context.l10n.text(insight.title)),
                subtitle: Text(
                  context.l10n.text(insight.message, insight.values),
                ),
                children: [
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      '${context.l10n.text('How this was calculated')}: ${context.l10n.text(insight.calculation)}',
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

class _SecondaryMetrics extends StatelessWidget {
  const _SecondaryMetrics({
    required this.report,
    required this.model,
    required this.currency,
  });

  final TradingReport report;
  final StatisticsViewModel model;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    return AnalyticsSurface(
      child: ExpansionTile(
        tilePadding: EdgeInsets.zero,
        title: Text(
          context.l10n.text('Detailed performance metrics'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(
          context.l10n.text(
            'Holding time, streaks, outcomes, and recovery quality.',
          ),
        ),
        children: [
          _MetricGrid(
            model: model,
            metrics: [
              _MetricSpec(
                label: 'Gross profit',
                value: _money(summary.totalProfit, currency),
                icon: Icons.add_circle_outline,
                tone: analyticsPositive,
                help: 'Sum of positive realized results.',
              ),
              _MetricSpec(
                label: 'Gross loss',
                value: _money(summary.totalLoss, currency),
                icon: Icons.remove_circle_outline,
                tone: analyticsNegative,
                help: 'Absolute sum of negative realized results.',
              ),
              _MetricSpec(
                label: 'Average win',
                value: _money(summary.averageWin, currency),
                icon: Icons.north_east,
                help: 'Gross profit divided by winning trades.',
              ),
              _MetricSpec(
                label: 'Average loss',
                value: _money(summary.averageLoss, currency),
                icon: Icons.south_east,
                help: 'Gross loss divided by losing trades.',
              ),
              _MetricSpec(
                label: 'Best trade',
                value: _money(summary.largestWin, currency, signed: true),
                icon: Icons.emoji_events_outlined,
                tone: analyticsPositive,
                help: 'Largest eligible realized winning result.',
              ),
              _MetricSpec(
                label: 'Worst trade',
                value: _money(summary.largestLoss, currency, signed: true),
                icon: Icons.report_problem_outlined,
                tone: analyticsNegative,
                help: 'Largest eligible realized losing result.',
              ),
              _MetricSpec(
                label: 'Recovery factor',
                value: context.l10n.text(summary.recoveryFactorLabel),
                icon: Icons.restore,
                help: 'Net profit divided by maximum drawdown amount.',
              ),
              _MetricSpec(
                label: 'Payoff ratio',
                value: context.l10n.text(summary.payoffRatioLabel),
                icon: Icons.compare_arrows,
                help:
                    'Average winning amount divided by average losing amount; this is not a planned risk-to-reward ratio.',
              ),
              _MetricSpec(
                label: 'Average holding time',
                value: summary.averageHoldingLabel,
                icon: Icons.schedule_outlined,
                help: 'Average elapsed time between entry and close.',
              ),
              _MetricSpec(
                label: 'Winning streak',
                value: '${summary.longestWinStreak}',
                icon: Icons.local_fire_department_outlined,
                help: 'Longest uninterrupted sequence of winning trades.',
              ),
              _MetricSpec(
                label: 'Losing streak',
                value: '${summary.longestLossStreak}',
                icon: Icons.pause_circle_outline,
                help: 'Longest uninterrupted sequence of losing trades.',
              ),
              _MetricSpec(
                label: 'Total trades',
                value: '${summary.totalTrades}',
                icon: Icons.receipt_long_outlined,
                help: 'Eligible, unique, closed trade records.',
              ),
              _MetricSpec(
                label: 'Duplicate trades excluded',
                value: '${report.duplicateTradesExcluded}',
                icon: Icons.filter_alt_off_outlined,
                help: 'Repeated source, account, and stable trade identifiers.',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CalendarGrid extends StatelessWidget {
  const _CalendarGrid({
    required this.month,
    required this.tradesByDay,
    required this.currency,
    required this.onTap,
  });

  final DateTime month;
  final Map<DateTime, List<TradingJournalEntry>> tradesByDay;
  final String currency;
  final void Function(DateTime, List<TradingJournalEntry>) onTap;

  @override
  Widget build(BuildContext context) {
    final first = DateTime(month.year, month.month);
    final days = DateTime(month.year, month.month + 1, 0).day;
    final leading = first.weekday - 1;
    final labels = const ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return Column(
      children: [
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 2.2,
          children: [
            for (final label in labels)
              Center(
                child: Text(
                  context.l10n.text(label),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ),
          ],
        ),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: leading + days,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 7,
            childAspectRatio: .86,
            crossAxisSpacing: 4,
            mainAxisSpacing: 4,
          ),
          itemBuilder: (context, index) {
            if (index < leading) return const SizedBox.shrink();
            final date = DateTime(month.year, month.month, index - leading + 1);
            final trades = tradesByDay[date] ?? const <TradingJournalEntry>[];
            return _CalendarDay(
              date: date,
              trades: trades,
              currency: currency,
              onTap: trades.isEmpty ? null : () => onTap(date, trades),
            );
          },
        ),
      ],
    );
  }
}

class _CalendarDay extends StatelessWidget {
  const _CalendarDay({
    required this.date,
    required this.trades,
    required this.currency,
    this.onTap,
  });

  final DateTime date;
  final List<TradingJournalEntry> trades;
  final String currency;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final result = trades.fold<double>(
      0,
      (sum, trade) => sum + trade.profitLoss,
    );
    final tone = _financialTone(result);
    final theme = Theme.of(context);
    return Material(
      color: trades.isEmpty
          ? theme.colorScheme.surfaceContainerLowest
          : tone.withValues(
              alpha: theme.brightness == Brightness.dark ? .16 : .08,
            ),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(5),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${date.day}', style: theme.textTheme.labelMedium),
              const Spacer(),
              if (trades.isEmpty)
                Icon(Icons.remove, size: 12, color: theme.colorScheme.outline)
              else ...[
                Icon(
                  result > 0
                      ? Icons.arrow_upward
                      : result < 0
                      ? Icons.arrow_downward
                      : Icons.horizontal_rule,
                  size: 12,
                  color: tone,
                ),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    _compact(result, currency),
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: tone,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                Text('${trades.length}×', style: theme.textTheme.labelSmall),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _CalendarList extends StatelessWidget {
  const _CalendarList({
    required this.entries,
    required this.currency,
    required this.onTap,
  });

  final List<MapEntry<DateTime, List<TradingJournalEntry>>> entries;
  final String currency;
  final void Function(DateTime, List<TradingJournalEntry>) onTap;

  @override
  Widget build(BuildContext context) {
    if (entries.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 28),
        child: Center(
          child: Text(context.l10n.text('No trading days this month')),
        ),
      );
    }
    return Column(
      children: [
        for (final entry in entries)
          ListTile(
            contentPadding: EdgeInsets.zero,
            onTap: () => onTap(entry.key, entry.value),
            leading: CircleAvatar(child: Text('${entry.key.day}')),
            title: Text(DateFormat.yMMMMd().format(entry.key)),
            subtitle: Text(
              context.l10n.text('{count} completed trade(s)', {
                'count': entry.value.length,
              }),
            ),
            trailing: Text(
              _money(
                entry.value.fold<double>(
                  0,
                  (sum, trade) => sum + trade.profitLoss,
                ),
                currency,
                signed: true,
              ),
              style: TextStyle(
                color: _financialTone(
                  entry.value.fold<double>(
                    0,
                    (sum, trade) => sum + trade.profitLoss,
                  ),
                ),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
      ],
    );
  }
}

class _DayDetailsSheet extends StatelessWidget {
  const _DayDetailsSheet({
    required this.date,
    required this.trades,
    required this.currency,
  });

  final DateTime date;
  final List<TradingJournalEntry> trades;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final net = trades.fold<double>(0, (sum, trade) => sum + trade.profitLoss);
    final wins = trades.where((trade) => trade.profitLoss > 0).length;
    final losses = trades.where((trade) => trade.profitLoss < 0).length;
    final decided = wins + losses;
    final riskViolations = trades
        .where((trade) => !trade.followedRiskRules)
        .length;
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .72,
      maxChildSize: .94,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 24),
        children: [
          AnalyticsSectionHeader(
            title: DateFormat.yMMMMd().format(date),
            subtitle: context.l10n.text('Completed-trade detail'),
            action: IconButton(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.close),
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 22,
            runSpacing: 12,
            children: [
              _LabelValue(
                label: 'Net profit',
                value: _money(net, currency, signed: true),
                tone: _financialTone(net),
              ),
              _LabelValue(label: 'Total trades', value: '${trades.length}'),
              _LabelValue(
                label: 'Win rate',
                value: _percent(decided == 0 ? 0 : wins / decided * 100, 1),
              ),
              _LabelValue(
                label: 'Risk-rule violations',
                value: '$riskViolations',
                tone: riskViolations > 0 ? analyticsWarning : analyticsPositive,
              ),
            ],
          ),
          const Divider(height: 28),
          for (final trade in trades)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(
                child: Text(trade.pair.characters.take(2).toString()),
              ),
              title: Text(
                '${trade.pair} · ${context.l10n.text(trade.directionLabel)}',
              ),
              subtitle: Text(
                '${formatTime(trade.closeDate)} · ${trade.strategyName ?? context.l10n.text('Unspecified')}',
              ),
              trailing: Text(
                _money(trade.profitLoss, currency, signed: true),
                style: TextStyle(
                  color: _financialTone(trade.profitLoss),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

enum _BreakdownSort { profitHigh, profitLow, winRate, trades }

extension on _BreakdownSort {
  String get label => switch (this) {
    _BreakdownSort.profitHigh => 'Highest profit',
    _BreakdownSort.profitLow => 'Lowest profit',
    _BreakdownSort.winRate => 'Highest win rate',
    _BreakdownSort.trades => 'Most trades',
  };

  int compare(TradingBreakdownStat left, TradingBreakdownStat right) =>
      switch (this) {
        _BreakdownSort.profitHigh => right.profitLoss.compareTo(
          left.profitLoss,
        ),
        _BreakdownSort.profitLow => left.profitLoss.compareTo(right.profitLoss),
        _BreakdownSort.winRate => right.winRate.compareTo(left.winRate),
        _BreakdownSort.trades => right.trades.compareTo(left.trades),
      };
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({
    required this.row,
    required this.currency,
    required this.totalProfit,
  });

  final TradingBreakdownStat row;
  final String currency;
  final double totalProfit;

  @override
  Widget build(BuildContext context) {
    final contribution = totalProfit == 0
        ? 0.0
        : row.profitLoss / totalProfit * 100;
    final tone = _financialTone(row.profitLoss);
    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: Border(
          bottom: BorderSide(color: Theme.of(context).dividerColor),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            flex: 3,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text(row.label),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                Text(
                  context.l10n.text('{count} trade(s) · {rate} win rate', {
                    'count': row.trades,
                    'rate': _percent(row.winRate, 1),
                  }),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Expanded(
            flex: 2,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  _money(row.profitLoss, currency, signed: true),
                  style: TextStyle(color: tone, fontWeight: FontWeight.w900),
                ),
                Text(
                  context.l10n.text('{value} contribution', {
                    'value': _percent(contribution, 1, signed: true),
                  }),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LabelValue extends StatelessWidget {
  const _LabelValue({required this.label, required this.value, this.tone});

  final String label;
  final String value;
  final Color? tone;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      ),
      const SizedBox(height: 2),
      Text(
        value,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
          color: tone,
          fontWeight: FontWeight.w900,
        ),
      ),
    ],
  );
}

Widget _responsivePair(
  BuildContext context, {
  required Widget first,
  required Widget second,
}) => LayoutBuilder(
  builder: (context, constraints) {
    if (constraints.maxWidth < 900) {
      return Column(children: [first, const SizedBox(height: 12), second]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: first),
        const SizedBox(width: 12),
        Expanded(child: second),
      ],
    );
  },
);

String _money(double value, String currency, {bool signed = false}) => signed
    ? formatSignedCurrencyMoney(value, currency)
    : formatCurrencyMoney(value, currency);

String _compact(double value, String currency) {
  final symbol = currency.trim().toUpperCase();
  final amount = NumberFormat.compact().format(value);
  return symbol.isEmpty ? amount : '$amount $symbol';
}

String _percent(double value, int decimals, {bool signed = false}) {
  final sign = signed && value > 0 ? '+' : '';
  return '$sign${value.toStringAsFixed(decimals)}%';
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

Color _riskTone(String status) => switch (status) {
  'Safe' => analyticsPositive,
  'Caution' => analyticsWarning,
  'High Risk' || 'Critical' => analyticsNegative,
  _ => analyticsInformation,
};

Color _ruleTone(String tone) => switch (tone) {
  'success' => analyticsPositive,
  'warning' => analyticsWarning,
  'danger' => analyticsNegative,
  _ => analyticsInformation,
};

Color _performanceTone(StatisticsViewModel model) {
  if (model.riskStatus == 'Critical' || model.riskStatus == 'High Risk') {
    return analyticsNegative;
  }
  return _scoreTone(model.report.scoreProfile.score);
}

String _performanceLevel(StatisticsViewModel model) {
  if (model.report.summary.totalTrades < 3) return 'Needs more data';
  if (model.riskStatus == 'Critical' || model.riskStatus == 'High Risk') {
    return 'High Risk';
  }
  final score = model.report.scoreProfile.score;
  if (score >= 85) return 'Excellent';
  if (score >= 75) return 'Strong';
  if (score >= 60) return 'Stable';
  if (score >= 45) return 'Developing';
  return 'Needs Improvement';
}
