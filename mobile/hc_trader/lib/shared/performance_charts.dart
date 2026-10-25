import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../models/trading_report.dart';
import 'formatters.dart';

class EquityCurveCard extends StatelessWidget {
  const EquityCurveCard({super.key, required this.dailyStats});

  final List<TradingDailyStat> dailyStats;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Equity curve'),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        context.l10n.text(
                          'Starting capital plus cumulative closed-trade P/L',
                        ),
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                if (dailyStats.isNotEmpty)
                  Text(
                    formatMoney(dailyStats.last.cumulativeProfitLoss),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: dailyStats.last.cumulativeProfitLoss >= 0
                          ? const Color(0xFF17855B)
                          : theme.colorScheme.error,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            if (dailyStats.isEmpty)
              const _EmptyChart()
            else
              SizedBox(
                height: 190,
                width: double.infinity,
                child: CustomPaint(
                  painter: _EquityCurvePainter(
                    values: dailyStats
                        .map((item) => item.cumulativeProfitLoss)
                        .toList(),
                    positiveColor: const Color(0xFF17855B),
                    negativeColor: theme.colorScheme.error,
                    gridColor: theme.colorScheme.outlineVariant,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class DailyPerformanceCard extends StatelessWidget {
  const DailyPerformanceCard({super.key, required this.dailyStats});

  final List<TradingDailyStat> dailyStats;

  @override
  Widget build(BuildContext context) {
    final recent = dailyStats.length > 14
        ? dailyStats.sublist(dailyStats.length - 14)
        : dailyStats;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Daily performance'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            Text(
              context.l10n.text('Most recent {count} trading days', {
                'count': recent.length,
              }),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            if (recent.isEmpty)
              const _EmptyChart()
            else
              SizedBox(
                height: 150,
                width: double.infinity,
                child: CustomPaint(
                  painter: _DailyBarsPainter(
                    values: recent.map((item) => item.profitLoss).toList(),
                    positiveColor: const Color(0xFF17855B),
                    negativeColor: Theme.of(context).colorScheme.error,
                    gridColor: Theme.of(context).colorScheme.outlineVariant,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class PerformanceCalendarCard extends StatefulWidget {
  const PerformanceCalendarCard({super.key, required this.dailyStats});

  final List<TradingDailyStat> dailyStats;

  @override
  State<PerformanceCalendarCard> createState() =>
      _PerformanceCalendarCardState();
}

class _PerformanceCalendarCardState extends State<PerformanceCalendarCard> {
  late DateTime _month;

  @override
  void initState() {
    super.initState();
    final anchor = widget.dailyStats.isEmpty
        ? DateTime.now()
        : widget.dailyStats.last.date;
    _month = DateTime(anchor.year, anchor.month);
  }

  void _changeMonth(int offset) {
    setState(() => _month = DateTime(_month.year, _month.month + offset));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final byDay = <int, TradingDailyStat>{
      for (final stat in widget.dailyStats)
        if (stat.date.year == _month.year && stat.date.month == _month.month)
          stat.date.day: stat,
    };
    final firstWeekday = DateTime(_month.year, _month.month, 1).weekday;
    final days = DateTime(_month.year, _month.month + 1, 0).day;
    final cells = (firstWeekday - 1) + days;
    final rows = (cells / 7).ceil();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Performance calendar'),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Previous month'),
                  onPressed: () => _changeMonth(-1),
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  '${_monthName(_month.month)} ${_month.year}',
                  style: theme.textTheme.labelLarge,
                ),
                IconButton(
                  tooltip: context.l10n.text('Next month'),
                  onPressed: () => _changeMonth(1),
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                for (final label in ['M', 'T', 'W', 'T', 'F', 'S', 'S'])
                  Expanded(
                    child: Center(
                      child: Text(
                        label,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: rows * 7,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 7,
                childAspectRatio: .82,
                crossAxisSpacing: 4,
                mainAxisSpacing: 4,
              ),
              itemBuilder: (context, index) {
                final day = index - (firstWeekday - 2);
                if (day < 1 || day > days) return const SizedBox.shrink();
                final stat = byDay[day];
                final profitLoss = stat?.profitLoss ?? 0;
                final color = stat == null
                    ? theme.colorScheme.surfaceContainerHighest
                    : profitLoss >= 0
                    ? const Color(0xFF17855B)
                    : theme.colorScheme.error;
                return Container(
                  padding: const EdgeInsets.symmetric(vertical: 5),
                  decoration: BoxDecoration(
                    color: stat == null ? color : color.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(8),
                    border: stat == null
                        ? null
                        : Border.all(color: color.withValues(alpha: .35)),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('$day', style: theme.textTheme.labelSmall),
                      if (stat != null)
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            _compactMoney(profitLoss),
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: color,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class TradingBreakdownCard extends StatelessWidget {
  const TradingBreakdownCard({
    super.key,
    required this.title,
    required this.stats,
  });

  final String title;
  final List<TradingBreakdownStat> stats;

  @override
  Widget build(BuildContext context) {
    final visible = stats.where((item) => item.trades > 0).take(6).toList();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            if (visible.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  context.l10n.text('No closed trades for this breakdown yet.'),
                ),
              )
            else
              for (final stat in visible) _BreakdownRow(stat: stat),
          ],
        ),
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({required this.stat});

  final TradingBreakdownStat stat;

  @override
  Widget build(BuildContext context) {
    final positive = stat.profitLoss >= 0;
    final color = positive
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.error;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  stat.shortLabel ?? stat.label,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(
                  context.l10n.text('{count} trades - {winRate}% win', {
                    'count': stat.trades,
                    'winRate': stat.winRate.toStringAsFixed(0),
                  }),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Text(
            formatMoney(stat.profitLoss),
            style: TextStyle(color: color, fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

class _EmptyChart extends StatelessWidget {
  const _EmptyChart();

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 120,
    child: Center(
      child: Text(
        context.l10n.text('Close a trade to start building this chart.'),
        style: Theme.of(context).textTheme.bodyMedium,
      ),
    ),
  );
}

class _EquityCurvePainter extends CustomPainter {
  _EquityCurvePainter({
    required this.values,
    required this.positiveColor,
    required this.negativeColor,
    required this.gridColor,
  });

  final List<double> values;
  final Color positiveColor;
  final Color negativeColor;
  final Color gridColor;

  @override
  void paint(Canvas canvas, Size size) {
    final all = [...values];
    final minimum = all.reduce(math.min);
    final maximum = all.reduce(math.max);
    final spread = math.max(maximum - minimum, 1.0);
    final padding = spread * .12;
    final low = minimum - padding;
    final high = maximum + padding;
    final plotHeight = size.height - 10;

    final gridPaint = Paint()
      ..color = gridColor.withValues(alpha: .55)
      ..strokeWidth = 1;
    for (var i = 0; i <= 4; i++) {
      final y = 5 + (plotHeight * i / 4);
      canvas.drawLine(Offset(0, y), Offset(size.width, y), gridPaint);
    }

    Offset point(int index) {
      final x = values.length == 1
          ? size.width / 2
          : size.width * index / (values.length - 1);
      final y = 5 + ((high - values[index]) / (high - low)) * plotHeight;
      return Offset(x, y);
    }

    final path = Path()..moveTo(point(0).dx, point(0).dy);
    for (var i = 1; i < values.length; i++) {
      path.lineTo(point(i).dx, point(i).dy);
    }
    final color = values.last >= 0 ? positiveColor : negativeColor;
    final fill = Path.from(path)
      ..lineTo(point(values.length - 1).dx, size.height)
      ..lineTo(point(0).dx, size.height)
      ..close();
    canvas.drawPath(
      fill,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [color.withValues(alpha: .28), color.withValues(alpha: .01)],
        ).createShader(Offset.zero & size),
    );
    canvas.drawPath(
      path,
      Paint()
        ..color = color
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..style = PaintingStyle.stroke,
    );
  }

  @override
  bool shouldRepaint(covariant _EquityCurvePainter oldDelegate) =>
      oldDelegate.values != values ||
      oldDelegate.positiveColor != positiveColor ||
      oldDelegate.negativeColor != negativeColor;
}

class _DailyBarsPainter extends CustomPainter {
  _DailyBarsPainter({
    required this.values,
    required this.positiveColor,
    required this.negativeColor,
    required this.gridColor,
  });

  final List<double> values;
  final Color positiveColor;
  final Color negativeColor;
  final Color gridColor;

  @override
  void paint(Canvas canvas, Size size) {
    final maximum = math.max(
      values.fold<double>(0, (value, item) => math.max(value, item.abs())),
      1,
    );
    final baseline = size.height / 2;
    canvas.drawLine(
      Offset(0, baseline),
      Offset(size.width, baseline),
      Paint()
        ..color = gridColor
        ..strokeWidth = 1,
    );
    final slot = size.width / values.length;
    final width = math.max(3.0, slot * .62);
    for (var index = 0; index < values.length; index++) {
      final value = values[index];
      final height = (value.abs() / maximum) * (baseline - 9);
      final left = (slot * index) + ((slot - width) / 2);
      final rect = value >= 0
          ? Rect.fromLTWH(left, baseline - height, width, height)
          : Rect.fromLTWH(left, baseline, width, height);
      canvas.drawRRect(
        RRect.fromRectAndRadius(rect, const Radius.circular(4)),
        Paint()..color = value >= 0 ? positiveColor : negativeColor,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _DailyBarsPainter oldDelegate) =>
      oldDelegate.values != values ||
      oldDelegate.positiveColor != positiveColor ||
      oldDelegate.negativeColor != negativeColor;
}

String _compactMoney(double value) {
  final sign = value > 0 ? '+' : '';
  final absolute = value.abs();
  if (absolute >= 1000) return '$sign${(value / 1000).toStringAsFixed(1)}k';
  return '$sign${value.toStringAsFixed(0)}';
}

String _monthName(int month) => const [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
][month - 1];
