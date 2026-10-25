import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../core/security/admin_permissions.dart';
import '../../../models/app_user.dart';
import '../../../repositories/trading_repository.dart';
import '../../../shared/analytics/analytics_widgets.dart';
import '../../../shared/formatters.dart';
import 'admin_performance_models.dart';
import 'admin_performance_review_dialog.dart';

part 'admin_performance_comparison_screen.dart';

class AdminPerformanceDetailScreen extends StatelessWidget {
  const AdminPerformanceDetailScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.record,
    required this.onReviewChanged,
  });

  final AppUser user;
  final TradingRepository repository;
  final AdminTraderPerformance record;
  final VoidCallback onReviewChanged;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(context.l10n.text('Trader performance')),
      actions: [
        if (user.hasPermission(AdminPermission.propFirmResultReview))
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: FilledButton.tonalIcon(
              onPressed: () => _review(context),
              icon: const Icon(Icons.fact_check_outlined),
              label: Text(context.l10n.text('Review')),
            ),
          ),
      ],
    ),
    body: LayoutBuilder(
      builder: (context, constraints) => CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1480),
                child: Padding(
                  padding: EdgeInsets.all(constraints.maxWidth < 600 ? 12 : 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _TraderSummary(record: record),
                      const SizedBox(height: 16),
                      _DetailMetrics(record: record),
                      const SizedBox(height: 16),
                      _DetailCharts(record: record),
                      const SizedBox(height: 16),
                      _BreakdownSection(record: record),
                      const SizedBox(height: 16),
                      _TradeHistory(record: record),
                      const SizedBox(height: 30),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _review(BuildContext context) async {
    final changed = await showAdminPerformanceReviewDialog(
      context,
      repository: repository,
      record: record,
    );
    if (changed) onReviewChanged();
  }
}

class _TraderSummary extends StatelessWidget {
  const _TraderSummary({required this.record});
  final AdminTraderPerformance record;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: LayoutBuilder(
      builder: (context, constraints) {
        final profile = Row(
          children: [
            CircleAvatar(radius: 30, child: Text(_initials(record.name))),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    record.name,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(record.email),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      Chip(
                        label: Text(
                          context.l10n.text(_pretty(record.reviewStatus)),
                        ),
                      ),
                      Chip(
                        label: Text(
                          context.l10n.text(
                            record.funded ? 'Funded' : 'Not funded',
                          ),
                        ),
                      ),
                      for (final source in record.dataSources)
                        Chip(
                          label: Text(context.l10n.text(_sourceLabel(source))),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        );
        final stats = Wrap(
          spacing: 26,
          runSpacing: 16,
          children: [
            _Fact('Current balance', formatMoney(record.balance)),
            _Fact('Starting capital', formatMoney(record.deposits)),
            _Fact('Net withdrawal', formatMoney(record.withdrawals)),
            _Fact('Net profit', formatSignedMoney(record.netProfit)),
            _Fact('Total return', formatPercent(record.returnPercent)),
            _Fact('Active trading days', '${record.raw['trading_days'] ?? 0}'),
            _Fact(
              'Last trade',
              record.lastTradeAt == null
                  ? context.l10n.text('Not available')
                  : DateFormat('d MMM yyyy, HH:mm').format(record.lastTradeAt!),
            ),
          ],
        );
        return constraints.maxWidth >= 900
            ? Row(
                children: [
                  Expanded(child: profile),
                  const SizedBox(width: 24),
                  Expanded(child: stats),
                ],
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [profile, const Divider(height: 28), stats],
              );
      },
    ),
  );
}

class _DetailMetrics extends StatelessWidget {
  const _DetailMetrics({required this.record});
  final AdminTraderPerformance record;
  @override
  Widget build(BuildContext context) {
    final metrics = [
      ('Total trades', '${record.totalTrades}'),
      ('Winning trades', '${record.wins}'),
      ('Losing trades', '${record.losses}'),
      ('Breakeven trades', '${record.breakEvens}'),
      ('Win rate', formatPercent(record.winRate)),
      ('Loss rate', formatPercent(_double(record.raw['loss_rate']))),
      ('Average win', formatMoney(_double(record.raw['average_win']))),
      ('Average loss', formatMoney(_double(record.raw['average_loss']))),
      ('Profit factor', record.profitFactorLabel),
      ('Payoff ratio', '${record.raw['payoff_ratio_label'] ?? 'N/A'}'),
      ('Expectancy', formatSignedMoney(record.expectancy)),
      (
        'Maximum drawdown',
        '${formatMoney(record.maxDrawdown)} · ${formatPercent(record.maxDrawdownPercent)}',
      ),
      ('Best day', formatSignedMoney(_double(record.raw['best_day']))),
      ('Worst day', formatSignedMoney(_double(record.raw['worst_day']))),
      ('Largest win', formatSignedMoney(_double(record.raw['largest_win']))),
      ('Largest loss', formatSignedMoney(_double(record.raw['largest_loss']))),
      ('Consecutive wins', '${record.raw['longest_win_streak'] ?? 0}'),
      ('Consecutive losses', '${record.raw['longest_loss_streak'] ?? 0}'),
    ];
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Heading(
            'Performance metrics',
            'Cash flow is excluded from all trading-result calculations.',
          ),
          const SizedBox(height: 14),
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 1000
                  ? 4
                  : constraints.maxWidth >= 620
                  ? 3
                  : 2;
              final width =
                  (constraints.maxWidth - (columns - 1) * 12) / columns;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (final metric in metrics)
                    SizedBox(
                      width: width,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surfaceContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: _Fact(metric.$1, metric.$2),
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 14),
          InsightBanner(
            title: context.l10n.text('Performance score'),
            message: context.l10n.text(
              'Score {score} ({grade}). This uses the existing explainable trading-report scoring service.',
              {
                'score': record.overallScore.toStringAsFixed(0),
                'grade': record.reportGrade,
              },
            ),
            tone: record.overallScore >= 70
                ? analyticsPositive
                : record.overallScore >= 45
                ? analyticsWarning
                : analyticsNegative,
            icon: Icons.speed_outlined,
          ),
        ],
      ),
    );
  }
}

class _DetailCharts extends StatelessWidget {
  const _DetailCharts({required this.record});
  final AdminTraderPerformance record;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Heading(
          'Profit and loss trend',
          'Realized daily results for the selected report period.',
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 280,
          child: record.daily.isEmpty
              ? Center(
                  child: Text(
                    context.l10n.text('No chart data for this period.'),
                  ),
                )
              : LineChart(
                  LineChartData(
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
                          interval: record.daily.length > 8
                              ? (record.daily.length / 5).ceilToDouble()
                              : 1,
                          getTitlesWidget: (value, meta) {
                            final index = value.round();
                            if (index < 0 || index >= record.daily.length) {
                              return const SizedBox.shrink();
                            }
                            return Text(
                              DateFormat(
                                'd MMM',
                              ).format(record.daily[index].date),
                              style: Theme.of(context).textTheme.labelSmall,
                            );
                          },
                        ),
                      ),
                      leftTitles: AxisTitles(
                        sideTitles: SideTitles(
                          showTitles: true,
                          reservedSize: 50,
                          getTitlesWidget: (value, meta) => Text(
                            formatCompactMoney(value),
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                        ),
                      ),
                    ),
                    lineBarsData: [
                      LineChartBarData(
                        spots: [
                          for (final entry in record.daily.indexed)
                            FlSpot(entry.$1.toDouble(), entry.$2.profitLoss),
                        ],
                        isCurved: record.daily.length > 2,
                        color: Theme.of(context).colorScheme.primary,
                        barWidth: 3,
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
      ],
    ),
  );
}

class _BreakdownSection extends StatelessWidget {
  const _BreakdownSection({required this.record});
  final AdminTraderPerformance record;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final cards = [
        _BreakdownCard(title: 'Monthly performance', rows: record.monthly),
        _BreakdownCard(title: 'Performance by instrument', rows: record.pairs),
        _BreakdownCard(title: 'Performance by session', rows: record.sessions),
      ];
      return constraints.maxWidth >= 1000
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final card in cards.indexed) ...[
                  Expanded(child: card.$2),
                  if (card.$1 < cards.length - 1) const SizedBox(width: 12),
                ],
              ],
            )
          : Column(
              children: [
                for (final card in cards) ...[card, const SizedBox(height: 12)],
              ],
            );
    },
  );
}

class _BreakdownCard extends StatelessWidget {
  const _BreakdownCard({required this.title, required this.rows});
  final String title;
  final List<AdminPerformanceBreakdown> rows;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(title),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        if (rows.isEmpty)
          Text(context.l10n.text('Not available'))
        else
          for (final row in rows.take(8))
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          row.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        formatSignedMoney(row.profitLoss),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: row.profitLoss >= 0
                              ? analyticsPositive
                              : analyticsNegative,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${row.trades} · ${formatPercent(row.winRate)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
      ],
    ),
  );
}

class _TradeHistory extends StatefulWidget {
  const _TradeHistory({required this.record});
  final AdminTraderPerformance record;
  @override
  State<_TradeHistory> createState() => _TradeHistoryState();
}

class _TradeHistoryState extends State<_TradeHistory> {
  final search = TextEditingController();
  String source = 'all';
  int page = 0;
  static const pageSize = 10;
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = search.text.trim().toLowerCase();
    final filtered =
        widget.record.recentTrades
            .where(
              (trade) =>
                  (query.isEmpty ||
                      '${trade.pair} ${trade.strategy}'.toLowerCase().contains(
                        query,
                      )) &&
                  (source == 'all' || trade.source == source),
            )
            .toList()
          ..sort(
            (a, b) => (b.closeDate ?? DateTime(1970)).compareTo(
              a.closeDate ?? DateTime(1970),
            ),
          );
    final start = (page * pageSize).clamp(0, filtered.length);
    final visible = filtered.skip(start).take(pageSize).toList();
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Heading(
            'Recent trade history',
            'Up to 50 recent eligible closed trades; cancelled and duplicate records are excluded.',
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) => constraints.maxWidth < 620
                ? Column(
                    children: [
                      _tradeSearch(context),
                      const SizedBox(height: 10),
                      _sourceDropdown(context),
                    ],
                  )
                : Row(
                    children: [
                      Expanded(child: _tradeSearch(context)),
                      const SizedBox(width: 10),
                      SizedBox(width: 220, child: _sourceDropdown(context)),
                    ],
                  ),
          ),
          const SizedBox(height: 12),
          if (visible.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Center(
                child: Text(
                  context.l10n.text('No completed trades match these filters.'),
                ),
              ),
            )
          else
            LayoutBuilder(
              builder: (context, constraints) => constraints.maxWidth < 760
                  ? Column(
                      children: [
                        for (final trade in visible)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              child: Text(trade.direction == 1 ? 'B' : 'S'),
                            ),
                            title: Text(
                              '${trade.pair} · ${formatSignedMoney(trade.profitLoss)}',
                            ),
                            subtitle: Text(
                              '${_sourceLabel(trade.source)} · ${trade.closeDate == null ? 'N/A' : DateFormat('d MMM, HH:mm').format(trade.closeDate!)}',
                            ),
                          ),
                      ],
                    )
                  : SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: DataTable(
                        columns: [
                          for (final label in const [
                            'Date',
                            'Instrument',
                            'Direction',
                            'Data source',
                            'Lot size',
                            'Pips',
                            'Net profit',
                          ])
                            DataColumn(label: Text(context.l10n.text(label))),
                        ],
                        rows: [
                          for (final trade in visible)
                            DataRow(
                              cells: [
                                DataCell(
                                  Text(
                                    trade.closeDate == null
                                        ? context.l10n.text('N/A')
                                        : DateFormat(
                                            'd MMM yyyy, HH:mm',
                                          ).format(trade.closeDate!),
                                  ),
                                ),
                                DataCell(Text(trade.pair)),
                                DataCell(
                                  Text(
                                    context.l10n.text(
                                      trade.direction == 1 ? 'Buy' : 'Sell',
                                    ),
                                  ),
                                ),
                                DataCell(
                                  Text(
                                    context.l10n.text(
                                      _sourceLabel(trade.source),
                                    ),
                                  ),
                                ),
                                DataCell(
                                  Text(trade.lotSize.toStringAsFixed(2)),
                                ),
                                DataCell(Text(trade.pips.toStringAsFixed(1))),
                                DataCell(
                                  Text(formatSignedMoney(trade.profitLoss)),
                                ),
                              ],
                            ),
                        ],
                      ),
                    ),
            ),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              Text(
                context.l10n.text('{count} trade(s)', {
                  'count': filtered.length,
                }),
              ),
              IconButton(
                onPressed: page > 0 ? () => setState(() => page--) : null,
                icon: const Icon(Icons.chevron_left),
              ),
              IconButton(
                onPressed: (page + 1) * pageSize < filtered.length
                    ? () => setState(() => page++)
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tradeSearch(BuildContext context) => TextField(
    controller: search,
    decoration: InputDecoration(
      prefixIcon: const Icon(Icons.search),
      labelText: context.l10n.text('Search symbol or strategy'),
    ),
    onChanged: (_) => setState(() => page = 0),
  );
  Widget _sourceDropdown(
    BuildContext context,
  ) => DropdownButtonFormField<String>(
    initialValue: source,
    isExpanded: true,
    decoration: InputDecoration(labelText: context.l10n.text('Data source')),
    items: [
      for (final value in const ['all', 'manual', 'excel_import', 'mt5_sync'])
        DropdownMenuItem(
          value: value,
          child: Text(
            context.l10n.text(_sourceLabel(value)),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
    ],
    onChanged: (value) => setState(() {
      source = value ?? source;
      page = 0;
    }),
  );
}

class _Fact extends StatelessWidget {
  const _Fact(this.label, this.value);
  final String label;
  final String value;
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
      const SizedBox(height: 3),
      Text(
        context.l10n.text(value),
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ],
  );
}

class _Heading extends StatelessWidget {
  const _Heading(this.title, this.subtitle);
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(title),
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 3),
      Text(
        context.l10n.text(subtitle),
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      ),
    ],
  );
}

String _initials(String value) {
  final words = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .take(2);
  return words.map((word) => word[0].toUpperCase()).join();
}

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((word) => word.isNotEmpty)
    .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
    .join(' ');
String _sourceLabel(String value) => switch (value) {
  'mt5_sync' => 'MT5 synced data',
  'manual' => 'Manual trading journal',
  'excel_import' => 'Imported Excel data',
  'all' => 'Combined data',
  _ => value,
};
String _periodLabel(AdminPerformancePeriod value) => switch (value) {
  AdminPerformancePeriod.sevenDays => 'Last 7 days',
  AdminPerformancePeriod.thirtyDays => 'Last 30 days',
  AdminPerformancePeriod.ninetyDays => 'Last 90 days',
  AdminPerformancePeriod.year => 'This year',
  AdminPerformancePeriod.all => 'All time',
};
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
