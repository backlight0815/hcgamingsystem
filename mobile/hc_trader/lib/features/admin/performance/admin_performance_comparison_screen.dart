part of 'admin_performance_detail_screen.dart';

class AdminPerformanceComparisonScreen extends StatelessWidget {
  const AdminPerformanceComparisonScreen({
    super.key,
    required this.records,
    required this.period,
    required this.dataSource,
  });
  final List<AdminTraderPerformance> records;
  final AdminPerformancePeriod period;
  final String dataSource;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Performance comparison'))),
    body: Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1400),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            AnalyticsSurface(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _Heading(
                    'Comparison scope',
                    'All selected traders use the same period and data source.',
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    children: [
                      Chip(
                        label: Text(context.l10n.text(_periodLabel(period))),
                      ),
                      Chip(
                        label: Text(
                          context.l10n.text(_sourceLabel(dataSource)),
                        ),
                      ),
                      Chip(
                        label: Text(
                          context.l10n.text('{count} traders', {
                            'count': records.length,
                          }),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _ComparisonBar(records: records),
            const SizedBox(height: 14),
            AnalyticsSurface(
              padding: EdgeInsets.zero,
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: DataTable(
                  columns: [
                    DataColumn(label: Text(context.l10n.text('Metric'))),
                    for (final record in records)
                      DataColumn(
                        label: SizedBox(
                          width: 150,
                          child: Text(
                            record.name,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                  ],
                  rows: [
                    _comparisonRow(
                      context,
                      'Net profit',
                      records,
                      (r) => formatSignedMoney(r.netProfit),
                    ),
                    _comparisonRow(
                      context,
                      'Return',
                      records,
                      (r) => formatPercent(r.returnPercent),
                    ),
                    _comparisonRow(
                      context,
                      'Win rate',
                      records,
                      (r) => formatPercent(r.winRate),
                    ),
                    _comparisonRow(
                      context,
                      'Profit factor',
                      records,
                      (r) => r.profitFactorLabel,
                    ),
                    _comparisonRow(
                      context,
                      'Maximum drawdown',
                      records,
                      (r) => formatPercent(r.maxDrawdownPercent),
                    ),
                    _comparisonRow(
                      context,
                      'Total trades',
                      records,
                      (r) => '${r.totalTrades}',
                    ),
                    _comparisonRow(
                      context,
                      'Performance score',
                      records,
                      (r) => r.overallScore.toStringAsFixed(0),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _ComparisonBar extends StatelessWidget {
  const _ComparisonBar({required this.records});
  final List<AdminTraderPerformance> records;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Heading(
          'Net profit comparison',
          'Realized trading results; funding transactions are excluded.',
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 300,
          child: BarChart(
            BarChartData(
              borderData: FlBorderData(show: false),
              gridData: FlGridData(show: true, drawVerticalLine: false),
              titlesData: FlTitlesData(
                topTitles: const AxisTitles(
                  sideTitles: SideTitles(showTitles: false),
                ),
                rightTitles: const AxisTitles(
                  sideTitles: SideTitles(showTitles: false),
                ),
                bottomTitles: AxisTitles(
                  sideTitles: SideTitles(
                    showTitles: true,
                    getTitlesWidget: (value, meta) {
                      final index = value.round();
                      if (index < 0 || index >= records.length) {
                        return const SizedBox.shrink();
                      }
                      return SizedBox(
                        width: 80,
                        child: Text(
                          records[index].name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                      );
                    },
                  ),
                ),
              ),
              barGroups: [
                for (final item in records.indexed)
                  BarChartGroupData(
                    x: item.$1,
                    barRods: [
                      BarChartRodData(
                        toY: item.$2.netProfit,
                        width: 28,
                        borderRadius: BorderRadius.circular(5),
                        color: item.$2.netProfit >= 0
                            ? analyticsPositive
                            : analyticsNegative,
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

DataRow _comparisonRow(
  BuildContext context,
  String label,
  List<AdminTraderPerformance> records,
  String Function(AdminTraderPerformance) value,
) => DataRow(
  cells: [
    DataCell(
      Text(
        context.l10n.text(label),
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
    ),
    for (final record in records) DataCell(Text(value(record))),
  ],
);
