part of 'admin_performance_screen.dart';

class _AnalyticsGrid extends StatelessWidget {
  const _AnalyticsGrid({required this.records});
  final List<AdminTraderPerformance> records;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final sideBySide = constraints.maxWidth >= 900;
      final first = _ProfitTrendChart(records: records);
      final second = _ProfitabilityChart(records: records);
      return sideBySide
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 3, child: first),
                const SizedBox(width: 12),
                Expanded(flex: 2, child: second),
              ],
            )
          : Column(children: [first, const SizedBox(height: 12), second]);
    },
  );
}

class _ProfitTrendChart extends StatelessWidget {
  const _ProfitTrendChart({required this.records});
  final List<AdminTraderPerformance> records;
  @override
  Widget build(BuildContext context) {
    final values = aggregateAdminDailyProfit(records).entries.toList();
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(
            title: 'Combined profit trend',
            subtitle: 'Daily realized P/L across the filtered traders.',
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 260,
            child: values.isEmpty
                ? const _ChartEmpty()
                : Semantics(
                    label: context.l10n.text(
                      'Combined profit trend chart with {count} daily points.',
                      {'count': values.length},
                    ),
                    child: LineChart(
                      LineChartData(
                        minY:
                            values
                                .map((e) => e.value)
                                .reduce((a, b) => a < b ? a : b) *
                            1.15,
                        maxY:
                            values
                                .map((e) => e.value)
                                .reduce((a, b) => a > b ? a : b) *
                            1.15,
                        gridData: FlGridData(
                          show: true,
                          drawVerticalLine: false,
                          getDrawingHorizontalLine: (_) => FlLine(
                            color: Theme.of(
                              context,
                            ).colorScheme.outlineVariant.withValues(alpha: .45),
                            strokeWidth: 1,
                          ),
                        ),
                        borderData: FlBorderData(show: false),
                        titlesData: FlTitlesData(
                          topTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          rightTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          leftTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              reservedSize: 52,
                              getTitlesWidget: (value, meta) => Text(
                                formatCompactMoney(value),
                                style: Theme.of(context).textTheme.labelSmall,
                              ),
                            ),
                          ),
                          bottomTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              interval: values.length > 8
                                  ? (values.length / 5).ceilToDouble()
                                  : 1,
                              getTitlesWidget: (value, meta) {
                                final index = value.round();
                                if (index < 0 || index >= values.length) {
                                  return const SizedBox.shrink();
                                }
                                return Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(
                                    DateFormat(
                                      'd MMM',
                                    ).format(values[index].key),
                                    style: Theme.of(
                                      context,
                                    ).textTheme.labelSmall,
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
                                  '${DateFormat('d MMM').format(values[spot.x.round()].key)}\n${formatSignedMoney(spot.y)}',
                                  const TextStyle(fontWeight: FontWeight.w700),
                                ),
                            ],
                          ),
                        ),
                        lineBarsData: [
                          LineChartBarData(
                            spots: [
                              for (final entry in values.indexed)
                                FlSpot(entry.$1.toDouble(), entry.$2.value),
                            ],
                            isCurved: values.length > 2,
                            color: Theme.of(context).colorScheme.primary,
                            barWidth: 3,
                            dotData: FlDotData(show: values.length <= 14),
                            belowBarData: BarAreaData(
                              show: true,
                              color: Theme.of(
                                context,
                              ).colorScheme.primary.withValues(alpha: .1),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _ProfitabilityChart extends StatelessWidget {
  const _ProfitabilityChart({required this.records});
  final List<AdminTraderPerformance> records;
  @override
  Widget build(BuildContext context) {
    final profitable = records.where((r) => r.profitable).length;
    final losing = records.where((r) => r.losing).length;
    final neutral = records.length - profitable - losing;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(
            title: 'Profitability distribution',
            subtitle: 'Trader outcomes for the selected period.',
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 210,
            child: records.isEmpty
                ? const _ChartEmpty()
                : PieChart(
                    PieChartData(
                      centerSpaceRadius: 48,
                      sectionsSpace: 3,
                      sections: [
                        PieChartSectionData(
                          value: profitable.toDouble(),
                          color: analyticsPositive,
                          radius: 42,
                          title: '$profitable',
                          titleStyle: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        PieChartSectionData(
                          value: losing.toDouble(),
                          color: analyticsNegative,
                          radius: 42,
                          title: '$losing',
                          titleStyle: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        PieChartSectionData(
                          value: neutral.toDouble(),
                          color: _analyticsNeutral,
                          radius: 42,
                          title: '$neutral',
                          titleStyle: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
          Wrap(
            spacing: 16,
            runSpacing: 8,
            children: [
              _LegendDot('Profitable', analyticsPositive),
              _LegendDot('Losing', analyticsNegative),
              _LegendDot('Breakeven', _analyticsNeutral),
            ],
          ),
        ],
      ),
    );
  }
}

class _TopPerformers extends StatelessWidget {
  const _TopPerformers({required this.records});
  final List<AdminTraderPerformance> records;
  @override
  Widget build(BuildContext context) {
    final ranked = [...records]
      ..sort((a, b) => b.netProfit.compareTo(a.netProfit));
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(
            title: 'Top performers',
            subtitle: 'Ranked by realized net profit for the selected period.',
          ),
          const SizedBox(height: 12),
          for (final entry in ranked.take(5).indexed)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(child: Text('#${entry.$1 + 1}')),
              title: Text(
                entry.$2.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: Text(
                '${entry.$2.totalTrades} ${context.l10n.text('trades')} · ${formatPercent(entry.$2.winRate)}',
              ),
              trailing: Text(
                formatSignedMoney(entry.$2.netProfit),
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: entry.$2.netProfit >= 0
                      ? analyticsPositive
                      : analyticsNegative,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
