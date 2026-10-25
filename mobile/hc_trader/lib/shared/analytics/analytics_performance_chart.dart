import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/trading_report.dart';
import '../formatters.dart';
import 'analytics_widgets.dart';

enum AnalyticsChartView { netProfit, dailyProfit, drawdown }

class AnalyticsPerformanceChart extends StatefulWidget {
  const AnalyticsPerformanceChart({
    super.key,
    required this.dailyStats,
    this.title = 'Performance trend',
    this.compact = false,
    this.initialView = AnalyticsChartView.netProfit,
  });

  final List<TradingDailyStat> dailyStats;
  final String title;
  final bool compact;
  final AnalyticsChartView initialView;

  @override
  State<AnalyticsPerformanceChart> createState() =>
      _AnalyticsPerformanceChartState();
}

class _AnalyticsPerformanceChartState extends State<AnalyticsPerformanceChart> {
  late AnalyticsChartView _view;

  @override
  void initState() {
    super.initState();
    _view = widget.initialView;
  }

  @override
  Widget build(BuildContext context) {
    final visible = widget.dailyStats.length > (widget.compact ? 7 : 45)
        ? widget.dailyStats.sublist(
            widget.dailyStats.length - (widget.compact ? 7 : 45),
          )
        : widget.dailyStats;
    final values = _values(visible);
    final tone = _view == AnalyticsChartView.drawdown
        ? analyticsNegative
        : _seriesTone(values);
    final chartHeight = widget.compact ? 176.0 : 270.0;

    return AnalyticsSurface(
      padding: const EdgeInsets.fromLTRB(16, 16, 12, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text(widget.title),
            subtitle: visible.isEmpty
                ? context.l10n.text('No completed trades in this period')
                : context.l10n.text('{count} trading day(s)', {
                    'count': visible.length,
                  }),
          ),
          if (!widget.compact) ...[
            const SizedBox(height: 12),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: SegmentedButton<AnalyticsChartView>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(
                    value: AnalyticsChartView.netProfit,
                    label: Text(context.l10n.text('Net P/L')),
                  ),
                  ButtonSegment(
                    value: AnalyticsChartView.dailyProfit,
                    label: Text(context.l10n.text('Daily P/L')),
                  ),
                  ButtonSegment(
                    value: AnalyticsChartView.drawdown,
                    label: Text(context.l10n.text('Drawdown')),
                  ),
                ],
                selected: {_view},
                onSelectionChanged: (selection) =>
                    setState(() => _view = selection.first),
              ),
            ),
          ],
          const SizedBox(height: 14),
          if (visible.isEmpty)
            SizedBox(
              height: chartHeight,
              child: AnalyticsEmptyState(
                title: context.l10n.text('No completed trades'),
                message: context.l10n.text(
                  'Record a completed trade or choose a period with trading activity.',
                ),
              ),
            )
          else
            Semantics(
              label: _semanticLabel(context, visible, values),
              child: SizedBox(
                height: chartHeight,
                child: LineChart(_chartData(context, visible, values, tone)),
              ),
            ),
        ],
      ),
    );
  }

  List<double> _values(List<TradingDailyStat> stats) {
    switch (_view) {
      case AnalyticsChartView.netProfit:
        return stats.map((item) => item.cumulativeProfitLoss).toList();
      case AnalyticsChartView.dailyProfit:
        return stats.map((item) => item.profitLoss).toList();
      case AnalyticsChartView.drawdown:
        var peak = 0.0;
        return [
          for (final item in stats)
            () {
              peak = math.max(peak, item.cumulativeProfitLoss);
              return item.cumulativeProfitLoss - peak;
            }(),
        ];
    }
  }

  LineChartData _chartData(
    BuildContext context,
    List<TradingDailyStat> stats,
    List<double> values,
    Color tone,
  ) {
    final minValue = math.min(0.0, values.reduce(math.min));
    final maxValue = math.max(0.0, values.reduce(math.max));
    final spread = math.max(1.0, maxValue - minValue);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final labelStep = math.max(1, (stats.length / 5).ceil());
    return LineChartData(
      minY: minValue - spread * .1,
      maxY: maxValue + spread * .1,
      gridData: FlGridData(
        drawVerticalLine: false,
        getDrawingHorizontalLine: (value) => FlLine(
          color: value == 0
              ? Theme.of(context).colorScheme.outline
              : Theme.of(
                  context,
                ).colorScheme.outlineVariant.withValues(alpha: .5),
          strokeWidth: value == 0 ? 1.2 : .7,
        ),
      ),
      borderData: FlBorderData(show: false),
      titlesData: FlTitlesData(
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(
          sideTitles: SideTitles(showTitles: false),
        ),
        leftTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 48,
            getTitlesWidget: (value, meta) => Text(
              formatCompactMoney(value),
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ),
        ),
        bottomTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 30,
            getTitlesWidget: (value, meta) {
              final index = value.round();
              if (index < 0 ||
                  index >= stats.length ||
                  (index % labelStep != 0 && index != stats.length - 1)) {
                return const SizedBox.shrink();
              }
              return Padding(
                padding: const EdgeInsets.only(top: 7),
                child: Text(
                  DateFormat.Md(locale).format(stats[index].date),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              );
            },
          ),
        ),
      ),
      lineTouchData: LineTouchData(
        touchTooltipData: LineTouchTooltipData(
          getTooltipItems: (spots) => [
            for (final spot in spots)
              LineTooltipItem(
                '${DateFormat.yMMMd(locale).format(stats[spot.x.round()].date)}\n${formatSignedMoney(spot.y)}',
                TextStyle(
                  color: Theme.of(context).colorScheme.onInverseSurface,
                  fontWeight: FontWeight.w800,
                ),
              ),
          ],
        ),
      ),
      lineBarsData: [
        LineChartBarData(
          spots: [
            for (final indexed in values.indexed)
              FlSpot(indexed.$1.toDouble(), indexed.$2),
          ],
          isCurved: false,
          color: tone,
          barWidth: 2.6,
          dotData: FlDotData(show: values.length <= 14),
          belowBarData: BarAreaData(
            show: true,
            color: tone.withValues(alpha: .09),
          ),
        ),
      ],
    );
  }

  Color _seriesTone(List<double> values) {
    if (values.isEmpty || values.last == 0) return analyticsInformation;
    return values.last > 0 ? analyticsPositive : analyticsNegative;
  }

  String _semanticLabel(
    BuildContext context,
    List<TradingDailyStat> stats,
    List<double> values,
  ) {
    final low = values.reduce(math.min);
    final high = values.reduce(math.max);
    return context.l10n.text(
      '{view} chart from {start} to {end}. Lowest {low}. Highest {high}. Latest {latest}.',
      {
        'view': context.l10n.text(switch (_view) {
          AnalyticsChartView.netProfit => 'Net P/L',
          AnalyticsChartView.dailyProfit => 'Daily P/L',
          AnalyticsChartView.drawdown => 'Drawdown',
        }),
        'start': DateFormat.yMMMd().format(stats.first.date),
        'end': DateFormat.yMMMd().format(stats.last.date),
        'low': formatSignedMoney(low),
        'high': formatSignedMoney(high),
        'latest': formatSignedMoney(values.last),
      },
    );
  }
}
