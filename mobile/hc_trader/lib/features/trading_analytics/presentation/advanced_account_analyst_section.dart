import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../shared/analytics/analytics_widgets.dart';
import '../domain/analytics_result.dart';

class AdvancedAccountAnalystSection extends StatelessWidget {
  const AdvancedAccountAnalystSection({
    super.key,
    required this.result,
    this.emptyAction,
  });

  final TradingAnalyticsResult result;
  final Widget? emptyAction;

  @override
  Widget build(BuildContext context) {
    if (result.trades.isEmpty) {
      return AnalyticsEmptyState(
        icon: Icons.query_stats_outlined,
        title: context.l10n.text('No eligible analytics data'),
        message: context.l10n.text(
          'No trades match every selected account, source, date, and trade filter.',
        ),
        action: emptyAction,
      );
    }
    return Column(
      children: [
        if (!result.monetaryMetricsAvailable) ...[
          _MultiCurrencyWarning(result: result),
          const SizedBox(height: 16),
        ],
        _OverviewMetrics(result: result),
        if (result.monetaryMetricsAvailable) ...[
          const SizedBox(height: 16),
          _responsivePair(
            context,
            first: _TimeSeriesCard(
              title: 'Cumulative trading P/L',
              subtitle:
                  'Closed trades in close-time order. Deposits and withdrawals are excluded.',
              points: result.cumulativePnl,
              currency: result.currency,
              negativeBelowZero: true,
            ),
            second: _BalanceEquityCard(result: result),
          ),
          const SizedBox(height: 16),
          _responsivePair(
            context,
            first: _DrawdownCard(result: result),
            second: _AggregatedPnlCard(result: result),
          ),
          const SizedBox(height: 16),
          _responsivePair(
            context,
            first: _HistogramCard(
              title: 'Trade P/L histogram',
              subtitle:
                  'Percentile-clipped bins keep extreme outliers from flattening the distribution.',
              bins: result.pnlHistogram,
              currency: result.currency,
            ),
            second: _OutcomeDistributionCard(result: result),
          ),
        ],
        const SizedBox(height: 16),
        _LotAnalysis(
          result: result,
          includePnlComparisons: result.monetaryMetricsAvailable,
        ),
        const SizedBox(height: 16),
        _HoldingAnalysis(
          result: result,
          includePnlComparisons: result.monetaryMetricsAvailable,
        ),
        const SizedBox(height: 16),
        _RollingAnalysis(
          result: result,
          includeProfitFactor: result.monetaryMetricsAvailable,
        ),
        if (result.monetaryMetricsAvailable) ...[
          const SizedBox(height: 16),
          _RiskAdjustedMetrics(result: result),
          const SizedBox(height: 16),
          _BreakdownTable(result: result),
          const SizedBox(height: 16),
          _MonthlyTable(result: result),
          const SizedBox(height: 16),
          _InsightsPanel(result: result),
        ],
        const SizedBox(height: 16),
        _DataQualityPanel(result: result),
      ],
    );
  }
}

class _OverviewMetrics extends StatelessWidget {
  const _OverviewMetrics({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    final summary = result.summary;
    final moneyAvailable = result.monetaryMetricsAvailable;
    String money(double value, {bool signed = false}) => moneyAvailable
        ? _money(context, value, result.currency, signed: signed)
        : context.l10n.text('Per-currency totals below');
    return Column(
      children: [
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Account overview'),
                subtitle: context.l10n.text(
                  'Realized, floating, account, and outcome metrics use one normalized filtered dataset.',
                ),
                icon: Icons.space_dashboard_outlined,
              ),
              const SizedBox(height: 14),
              TradingMetricStrip(
                metrics: [
                  AnalyticsMetricData(
                    label: context.l10n.text('Starting balance'),
                    value: money(summary.startingBalance),
                    icon: Icons.flag_outlined,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Current balance'),
                    value: money(summary.currentBalance),
                    icon: Icons.account_balance_wallet_outlined,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Current equity'),
                    value: money(summary.currentEquity),
                    icon: Icons.show_chart,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Net trading P/L'),
                    value: money(summary.netTradingPnl, signed: true),
                    icon: Icons.payments_outlined,
                    tone: _financialTone(summary.netTradingPnl),
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Gross profit'),
                    value: money(summary.grossProfit),
                    icon: Icons.trending_up,
                    tone: analyticsPositive,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Gross loss'),
                    value: money(summary.grossLoss),
                    icon: Icons.trending_down,
                    tone: analyticsNegative,
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Floating P/L'),
                    value: money(summary.floatingPnl, signed: true),
                    icon: Icons.waves_outlined,
                    tone: _financialTone(summary.floatingPnl),
                  ),
                  AnalyticsMetricData(
                    label: context.l10n.text('Total return'),
                    value: !moneyAvailable
                        ? context.l10n.text('Per-currency totals below')
                        : summary.totalReturnPercent == null
                        ? context.l10n.text('Insufficient data')
                        : _percent(summary.totalReturnPercent!),
                    icon: Icons.percent,
                    tone: _financialTone(summary.totalReturnPercent ?? 0),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Win and loss analysis'),
                subtitle: context.l10n.text(
                  'Open trades are excluded from win rate, averages, and streaks.',
                ),
                icon: Icons.compare_arrows,
              ),
              const SizedBox(height: 14),
              TradingMetricStrip(
                metrics: [
                  _metric(
                    context,
                    'Closed trades',
                    '${summary.totalClosedTrades}',
                    Icons.task_alt,
                  ),
                  _metric(
                    context,
                    'Open trades',
                    '${summary.totalOpenTrades}',
                    Icons.timelapse,
                  ),
                  _metric(
                    context,
                    'Winning trades',
                    '${summary.winningTrades}',
                    Icons.arrow_upward,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Losing trades',
                    '${summary.losingTrades}',
                    Icons.arrow_downward,
                    tone: analyticsNegative,
                  ),
                  _metric(
                    context,
                    'Breakeven trades',
                    '${summary.breakevenTrades}',
                    Icons.remove,
                  ),
                  _metric(
                    context,
                    'Win rate',
                    _percent(summary.winRate),
                    Icons.track_changes,
                  ),
                  _metric(
                    context,
                    'Loss rate',
                    _percent(summary.lossRate),
                    Icons.south_east,
                  ),
                  _metric(
                    context,
                    'Average trade',
                    money(summary.averageTrade, signed: true),
                    Icons.functions,
                    tone: _financialTone(summary.averageTrade),
                  ),
                  _metric(
                    context,
                    'Average winning trade',
                    money(summary.averageWin),
                    Icons.add_chart,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Average losing trade',
                    money(-summary.averageLoss),
                    Icons.ssid_chart,
                    tone: analyticsNegative,
                  ),
                  _metric(
                    context,
                    'Largest winning trade',
                    money(summary.largestWin, signed: true),
                    Icons.north_east,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Largest losing trade',
                    money(summary.largestLoss, signed: true),
                    Icons.south_east,
                    tone: analyticsNegative,
                  ),
                  _metric(
                    context,
                    'Profit factor',
                    summary.profitFactor == null
                        ? summary.grossProfit > 0
                              ? context.l10n.text('No losing trades')
                              : context.l10n.text('N/A')
                        : summary.profitFactor!.toStringAsFixed(2),
                    Icons.balance_outlined,
                  ),
                  _metric(
                    context,
                    'Payoff ratio',
                    summary.payoffRatio?.toStringAsFixed(2) ??
                        context.l10n.text('N/A'),
                    Icons.compare_arrows,
                  ),
                  _metric(
                    context,
                    'Expected value per trade',
                    money(summary.expectancy, signed: true),
                    Icons.insights_outlined,
                    tone: _financialTone(summary.expectancy),
                  ),
                  _metric(
                    context,
                    'Average trades per day',
                    summary.averageTradesPerDay.toStringAsFixed(1),
                    Icons.today_outlined,
                  ),
                  _metric(
                    context,
                    'Current winning streak',
                    '${summary.currentWinningStreak}',
                    Icons.keyboard_double_arrow_up,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Current losing streak',
                    '${summary.currentLosingStreak}',
                    Icons.keyboard_double_arrow_down,
                    tone: analyticsNegative,
                  ),
                  _metric(
                    context,
                    'Maximum winning streak',
                    '${summary.maximumWinningStreak}',
                    Icons.trending_up,
                  ),
                  _metric(
                    context,
                    'Maximum losing streak',
                    '${summary.maximumLosingStreak}',
                    Icons.trending_down,
                  ),
                  _metric(
                    context,
                    'Best trading day',
                    money(summary.bestDay, signed: true),
                    Icons.event_available,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Worst trading day',
                    money(summary.worstDay, signed: true),
                    Icons.event_busy,
                    tone: analyticsNegative,
                  ),
                  _metric(
                    context,
                    'Best month',
                    money(summary.bestMonth, signed: true),
                    Icons.calendar_month,
                    tone: analyticsPositive,
                  ),
                  _metric(
                    context,
                    'Worst month',
                    money(summary.worstMonth, signed: true),
                    Icons.calendar_view_month,
                    tone: analyticsNegative,
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

class _MultiCurrencyWarning extends StatelessWidget {
  const _MultiCurrencyWarning({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Multiple account currencies'),
            subtitle: context.l10n.text(
              'Monetary totals are not combined because currency conversion is not configured.',
            ),
            icon: Icons.currency_exchange,
          ),
          const SizedBox(height: 12),
          for (final total in result.currencyTotals)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.account_balance_wallet_outlined),
              title: Text(total.currency),
              subtitle: Text(
                context.l10n.text('{closed} closed · {open} open', {
                  'closed': total.closedTrades,
                  'open': total.openTrades,
                }),
              ),
              trailing: Text(
                _money(
                  context,
                  total.netTradingPnl,
                  total.currency == 'Unknown' ? '' : total.currency,
                  signed: true,
                ),
                style: TextStyle(
                  color: _financialTone(total.netTradingPnl),
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _BalanceEquityCard extends StatefulWidget {
  const _BalanceEquityCard({required this.result});

  final TradingAnalyticsResult result;

  @override
  State<_BalanceEquityCard> createState() => _BalanceEquityCardState();
}

class _BalanceEquityCardState extends State<_BalanceEquityCard> {
  var _actualBalance = false;
  var _showBalance = true;
  var _showEquity = true;

  @override
  Widget build(BuildContext context) {
    final balance = _actualBalance
        ? widget.result.actualBalanceCurve
        : widget.result.tradingBalanceCurve;
    final equity = widget.result.equityCurve;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Balance versus equity'),
            subtitle: context.l10n.text(
              _actualBalance
                  ? 'Actual balance includes deposits and withdrawals.'
                  : 'Trading performance excludes deposits and withdrawals.',
            ),
            icon: Icons.multiline_chart,
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilterChip(
                selected: _actualBalance,
                label: Text(context.l10n.text('Actual balance')),
                onSelected: (value) => setState(() => _actualBalance = value),
              ),
              FilterChip(
                selected: _showBalance,
                label: Text(context.l10n.text('Balance')),
                onSelected: (value) => setState(() => _showBalance = value),
              ),
              FilterChip(
                selected: _showEquity,
                label: Text(context.l10n.text('Equity')),
                onSelected: equity.isEmpty
                    ? null
                    : (value) => setState(() => _showEquity = value),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (balance.isEmpty)
            _chartEmpty(context)
          else
            _DualLineChart(
              primary: _showBalance ? balance : const [],
              secondary: _showEquity ? equity : const [],
              currency: widget.result.currency,
            ),
          if (equity.isEmpty) ...[
            const SizedBox(height: 10),
            _Notice(
              icon: Icons.info_outline,
              text:
                  'Historical equity snapshots unavailable. The app does not fabricate an equity curve from the current equity value.',
            ),
          ],
        ],
      ),
    );
  }
}

class _DrawdownCard extends StatefulWidget {
  const _DrawdownCard({required this.result});

  final TradingAnalyticsResult result;

  @override
  State<_DrawdownCard> createState() => _DrawdownCardState();
}

class _DrawdownCardState extends State<_DrawdownCard> {
  var _percentage = true;

  @override
  Widget build(BuildContext context) {
    final summary = widget.result.summary;
    final points = [
      for (final item in widget.result.drawdownTimeline)
        AnalyticsPoint(
          timestampUtc: item.timestampUtc,
          value: -(_percentage ? item.percent : item.amount),
        ),
    ];
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Underwater drawdown'),
            subtitle: context.l10n.text(
              'Peak-to-trough decline in the realized trading balance curve.',
            ),
            icon: Icons.waterfall_chart,
            action: SegmentedButton<bool>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(
                  value: true,
                  label: Text(context.l10n.text('Percent')),
                ),
                ButtonSegment(
                  value: false,
                  label: Text(context.l10n.text('Currency')),
                ),
              ],
              selected: {_percentage},
              onSelectionChanged: (value) =>
                  setState(() => _percentage = value.first),
            ),
          ),
          const SizedBox(height: 12),
          _SimpleLineChart(
            points: points,
            currency: _percentage ? '%' : widget.result.currency,
            tone: analyticsNegative,
            height: 220,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 20,
            runSpacing: 8,
            children: [
              _labelValue(
                context,
                'Maximum drawdown',
                '${_money(context, summary.maximumDrawdown, widget.result.currency)} · ${_percent(summary.maximumDrawdownPercent)}',
              ),
              _labelValue(
                context,
                'Current drawdown',
                '${_money(context, summary.currentDrawdown, widget.result.currency)} · ${_percent(summary.currentDrawdownPercent)}',
              ),
              _labelValue(
                context,
                'Recovery',
                widget.result.maximumDrawdownPeriod.recovered
                    ? context.l10n.text('Recovered')
                    : context.l10n.text('Not recovered'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AggregatedPnlCard extends StatefulWidget {
  const _AggregatedPnlCard({required this.result});

  final TradingAnalyticsResult result;

  @override
  State<_AggregatedPnlCard> createState() => _AggregatedPnlCardState();
}

class _AggregatedPnlCardState extends State<_AggregatedPnlCard> {
  var _aggregation = 0;

  @override
  Widget build(BuildContext context) {
    final series = [
      widget.result.dailyPnl,
      widget.result.weeklyPnl,
      widget.result.monthlyPnl,
    ][_aggregation];
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Aggregated net P/L'),
            subtitle: context.l10n.text(
              'Bars include trade count, win rate, and lots in their tooltip.',
            ),
            icon: Icons.bar_chart,
          ),
          const SizedBox(height: 10),
          SegmentedButton<int>(
            showSelectedIcon: false,
            segments: [
              ButtonSegment(value: 0, label: Text(context.l10n.text('Day'))),
              ButtonSegment(value: 1, label: Text(context.l10n.text('Week'))),
              ButtonSegment(value: 2, label: Text(context.l10n.text('Month'))),
            ],
            selected: {_aggregation},
            onSelectionChanged: (value) =>
                setState(() => _aggregation = value.first),
          ),
          const SizedBox(height: 12),
          _PnlBarChart(points: series, currency: widget.result.currency),
        ],
      ),
    );
  }
}

class _OutcomeDistributionCard extends StatelessWidget {
  const _OutcomeDistributionCard({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    final win = result.winningDistribution;
    final loss = result.losingDistribution;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Winning and losing distribution'),
            subtitle: context.l10n.text(
              'Percentiles describe outcome size without relying only on the average.',
            ),
            icon: Icons.bar_chart_outlined,
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(context.l10n.text('Outcome'))),
                DataColumn(label: Text(context.l10n.text('Count'))),
                DataColumn(label: Text(context.l10n.text('25th percentile'))),
                DataColumn(label: Text(context.l10n.text('Median'))),
                DataColumn(label: Text(context.l10n.text('75th percentile'))),
                DataColumn(label: Text(context.l10n.text('90th percentile'))),
                DataColumn(label: Text(context.l10n.text('Average'))),
              ],
              rows: [
                _distributionRow(context, 'Winning trades', win),
                _distributionRow(context, 'Losing trades', loss),
              ],
            ),
          ),
        ],
      ),
    );
  }

  DataRow _distributionRow(
    BuildContext context,
    String label,
    DistributionSummary value,
  ) {
    String money(double amount) =>
        _money(context, amount, result.currency, signed: true);
    return DataRow(
      cells: [
        DataCell(Text(context.l10n.text(label))),
        DataCell(Text('${value.count}')),
        DataCell(Text(money(value.p25))),
        DataCell(Text(money(value.median))),
        DataCell(Text(money(value.p75))),
        DataCell(Text(money(value.p90))),
        DataCell(Text(money(value.mean))),
      ],
    );
  }
}

class _LotAnalysis extends StatelessWidget {
  const _LotAnalysis({
    required this.result,
    required this.includePnlComparisons,
  });

  final TradingAnalyticsResult result;
  final bool includePnlComparisons;

  @override
  Widget build(BuildContext context) {
    final summary = result.summary;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Lot size and exposure analysis'),
            subtitle: context.l10n.text(
              'Simultaneous exposure is calculated from overlapping open and close events.',
            ),
            icon: Icons.stacked_line_chart,
          ),
          const SizedBox(height: 14),
          TradingMetricStrip(
            metrics: [
              _metric(
                context,
                'Average lot size',
                summary.averageLot.toStringAsFixed(2),
                Icons.functions,
              ),
              _metric(
                context,
                'Median lot size',
                summary.medianLot.toStringAsFixed(2),
                Icons.horizontal_rule,
              ),
              _metric(
                context,
                'Maximum lot size',
                summary.maximumLot.toStringAsFixed(2),
                Icons.vertical_align_top,
              ),
              _metric(
                context,
                'Minimum lot size',
                summary.minimumLot.toStringAsFixed(2),
                Icons.vertical_align_bottom,
              ),
              _metric(
                context,
                'Total lots traded',
                summary.totalLots.toStringAsFixed(2),
                Icons.add_chart,
              ),
              _metric(
                context,
                'Maximum simultaneous lots',
                summary.maximumSimultaneousLots.toStringAsFixed(2),
                Icons.layers_outlined,
              ),
              _metric(
                context,
                'Maximum buy exposure',
                summary.maximumBuyExposure.toStringAsFixed(2),
                Icons.arrow_upward,
              ),
              _metric(
                context,
                'Maximum sell exposure',
                summary.maximumSellExposure.toStringAsFixed(2),
                Icons.arrow_downward,
              ),
              _metric(
                context,
                'Lot-size standard deviation',
                summary.lotStandardDeviation.toStringAsFixed(2),
                Icons.scatter_plot_outlined,
              ),
              _metric(
                context,
                'Lot change after a loss',
                _percent(summary.postLossLotChangePercent, signed: true),
                Icons.trending_up,
                tone: summary.postLossLotChangePercent > 15
                    ? analyticsWarning
                    : null,
              ),
              _metric(
                context,
                'Lot change after a win',
                _percent(summary.postWinLotChangePercent, signed: true),
                Icons.trending_flat,
              ),
              _metric(
                context,
                'Lot-size consistency',
                _percent(summary.lotConsistencyScore),
                Icons.equalizer,
              ),
            ],
          ),
          if (summary.postLossLotChangePercent > 15) ...[
            const SizedBox(height: 12),
            _Notice(
              icon: Icons.warning_amber_rounded,
              text:
                  'Possible post-loss risk escalation: lot size rose materially after losing trades. Review the sequence before drawing a behavioural conclusion.',
              tone: analyticsWarning,
            ),
          ],
          const SizedBox(height: 16),
          if (includePnlComparisons)
            _responsivePair(
              context,
              first: _TimeSeriesCard(
                title: 'Lot size over time',
                subtitle: 'Closed-trade lot size in chronological order.',
                points: result.lotOverTime,
                currency: 'lots',
              ),
              second: _ScatterCard(
                title: 'Lot size versus P/L',
                xLabel: 'Lot size',
                yLabel: 'Net P/L',
                points: [
                  for (final point in result.lotOverTime)
                    (x: point.value, y: point.secondaryValue ?? 0),
                ],
                currency: result.currency,
              ),
            )
          else
            _TimeSeriesCard(
              title: 'Lot size over time',
              subtitle: 'Closed-trade lot size in chronological order.',
              points: result.lotOverTime,
              currency: 'lots',
            ),
          const SizedBox(height: 16),
          _HistogramCard(
            title: 'Lot-size histogram',
            subtitle: 'Distribution of positive closed-trade lot sizes.',
            bins: result.lotHistogram,
            currency: 'lots',
          ),
        ],
      ),
    );
  }
}

class _HoldingAnalysis extends StatelessWidget {
  const _HoldingAnalysis({
    required this.result,
    required this.includePnlComparisons,
  });

  final TradingAnalyticsResult result;
  final bool includePnlComparisons;

  @override
  Widget build(BuildContext context) {
    final summary = result.summary;
    final holdingCategories = _holdingCategories(result);
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Holding-time analysis'),
            subtitle: context.l10n.text(
              'Holding time is close time minus open time; open trades are excluded.',
            ),
            icon: Icons.timer_outlined,
          ),
          const SizedBox(height: 14),
          TradingMetricStrip(
            metrics: [
              _metric(
                context,
                'Average holding time',
                _duration(summary.averageHoldingMinutes),
                Icons.schedule,
              ),
              _metric(
                context,
                'Median holding time',
                _duration(summary.medianHoldingMinutes),
                Icons.timelapse,
              ),
              _metric(
                context,
                'Average winning holding time',
                _duration(summary.averageWinningHoldingMinutes),
                Icons.arrow_upward,
                tone: analyticsPositive,
              ),
              _metric(
                context,
                'Average losing holding time',
                _duration(summary.averageLosingHoldingMinutes),
                Icons.arrow_downward,
                tone: analyticsNegative,
              ),
              _metric(
                context,
                'Longest holding time',
                _duration(summary.longestHoldingMinutes),
                Icons.hourglass_full,
              ),
              _metric(
                context,
                'Shortest holding time',
                _duration(summary.shortestHoldingMinutes),
                Icons.hourglass_bottom,
              ),
              _metric(
                context,
                'Holding-time consistency',
                _percent(summary.holdingConsistencyScore),
                Icons.equalizer,
              ),
            ],
          ),
          const SizedBox(height: 16),
          AnalyticsSectionHeader(
            title: context.l10n.text('Holding-time categories'),
            subtitle: context.l10n.text(
              'Closed trades are grouped by elapsed time from open to close.',
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final category in holdingCategories)
                Chip(
                  label: Text(
                    '${context.l10n.text(category.$1)} · ${category.$2}',
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          if (includePnlComparisons)
            _responsivePair(
              context,
              first: _HistogramCard(
                title: 'Holding-time histogram',
                subtitle: 'Closed-trade holding duration in minutes.',
                bins: result.holdingHistogram,
                currency: 'min',
              ),
              second: _ScatterCard(
                title: 'Holding time versus P/L',
                xLabel: 'Minutes',
                yLabel: 'Net P/L',
                points: [
                  for (final point in result.holdingOverTime)
                    (x: point.value, y: point.secondaryValue ?? 0),
                ],
                currency: result.currency,
              ),
            )
          else
            _HistogramCard(
              title: 'Holding-time histogram',
              subtitle: 'Closed-trade holding duration in minutes.',
              bins: result.holdingHistogram,
              currency: 'min',
            ),
        ],
      ),
    );
  }
}

class _RollingAnalysis extends StatefulWidget {
  const _RollingAnalysis({
    required this.result,
    required this.includeProfitFactor,
  });

  final TradingAnalyticsResult result;
  final bool includeProfitFactor;

  @override
  State<_RollingAnalysis> createState() => _RollingAnalysisState();
}

class _RollingAnalysisState extends State<_RollingAnalysis> {
  var _window = 20;

  @override
  Widget build(BuildContext context) {
    final points = switch (_window) {
      10 => widget.result.rollingWinRate10,
      50 => widget.result.rollingWinRate50,
      _ => widget.result.rollingWinRate20,
    };
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Rolling performance'),
            subtitle: context.l10n.text(
              'Early points are marked as small samples until the selected window is full.',
            ),
            icon: Icons.monitor_heart_outlined,
          ),
          const SizedBox(height: 10),
          SegmentedButton<int>(
            showSelectedIcon: false,
            segments: const [
              ButtonSegment(value: 10, label: Text('10')),
              ButtonSegment(value: 20, label: Text('20')),
              ButtonSegment(value: 50, label: Text('50')),
            ],
            selected: {_window},
            onSelectionChanged: (value) =>
                setState(() => _window = value.first),
          ),
          const SizedBox(height: 16),
          if (widget.includeProfitFactor)
            _responsivePair(
              context,
              first: _TimeSeriesCard(
                title: 'Rolling win rate',
                subtitle: 'Breakeven trades are excluded from the denominator.',
                points: points,
                currency: '%',
              ),
              second: _TimeSeriesCard(
                title: 'Rolling profit factor',
                subtitle:
                    'Windows with no losing trades are capped visually and identified in tooltips.',
                points: widget.result.rollingProfitFactor20,
                currency: 'ratio',
              ),
            )
          else
            _TimeSeriesCard(
              title: 'Rolling win rate',
              subtitle: 'Breakeven trades are excluded from the denominator.',
              points: points,
              currency: '%',
            ),
        ],
      ),
    );
  }
}

class _RiskAdjustedMetrics extends StatelessWidget {
  const _RiskAdjustedMetrics({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    final summary = result.summary;
    String ratio(double? value, {String unavailable = 'Insufficient data'}) =>
        value?.isFinite == true
        ? value!.toStringAsFixed(2)
        : context.l10n.text(unavailable);
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Risk and consistency metrics'),
            subtitle: context.l10n.text(
              'Sharpe and Sortino use trading-day returns and a 252-day annualization factor.',
            ),
            icon: Icons.shield_outlined,
          ),
          const SizedBox(height: 14),
          TradingMetricStrip(
            metrics: [
              _metric(
                context,
                'Maximum drawdown',
                '${_money(context, summary.maximumDrawdown, result.currency)} · ${_percent(summary.maximumDrawdownPercent)}',
                Icons.waterfall_chart,
                tone: analyticsNegative,
              ),
              _metric(
                context,
                'Current drawdown',
                '${_money(context, summary.currentDrawdown, result.currency)} · ${_percent(summary.currentDrawdownPercent)}',
                Icons.show_chart,
                tone: analyticsWarning,
              ),
              _metric(
                context,
                'Recovery factor',
                ratio(summary.recoveryFactor),
                Icons.replay,
              ),
              _metric(
                context,
                'Return-to-drawdown ratio',
                ratio(summary.returnToDrawdown),
                Icons.compare_arrows,
              ),
              _metric(
                context,
                'Sharpe ratio',
                ratio(summary.sharpeRatio),
                Icons.insights,
              ),
              _metric(
                context,
                'Sortino ratio',
                ratio(summary.sortinoRatio),
                Icons.south_east,
              ),
              _metric(
                context,
                'Calmar ratio',
                ratio(summary.calmarRatio),
                Icons.stacked_line_chart,
              ),
              _metric(
                context,
                'Daily return standard deviation',
                _percent(summary.dailyReturnStandardDeviation * 100),
                Icons.scatter_plot_outlined,
              ),
              _metric(
                context,
                'Downside deviation',
                _percent(summary.downsideDeviation * 100),
                Icons.trending_down,
              ),
              _metric(
                context,
                'Average R-multiple',
                ratio(
                  summary.averageRMultiple,
                  unavailable: 'Risk data not recorded',
                ),
                Icons.exposure,
              ),
              _metric(
                context,
                'Median R-multiple',
                ratio(
                  summary.medianRMultiple,
                  unavailable: 'Risk data not recorded',
                ),
                Icons.horizontal_rule,
              ),
              _metric(
                context,
                'P/L consistency',
                _percent(summary.pnlConsistencyScore),
                Icons.equalizer,
              ),
              _metric(
                context,
                'Trade-frequency consistency',
                _percent(summary.frequencyConsistencyScore),
                Icons.calendar_view_week,
              ),
              _metric(
                context,
                'Profitable days',
                _percent(summary.profitableDaysPercent),
                Icons.event_available,
                tone: analyticsPositive,
              ),
              _metric(
                context,
                'Losing days',
                _percent(summary.losingDaysPercent),
                Icons.event_busy,
                tone: analyticsNegative,
              ),
              _metric(
                context,
                'Breakeven days',
                _percent(summary.breakevenDaysPercent),
                Icons.event_note,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _BreakdownTable extends StatefulWidget {
  const _BreakdownTable({required this.result});

  final TradingAnalyticsResult result;

  @override
  State<_BreakdownTable> createState() => _BreakdownTableState();
}

class _BreakdownTableState extends State<_BreakdownTable> {
  var _group = 'symbol';
  var _query = '';
  var _ascending = false;
  var _page = 0;

  @override
  Widget build(BuildContext context) {
    final rows =
        [...?widget.result.breakdowns[_group]]
            .where(
              (row) => row.label.toLowerCase().contains(_query.toLowerCase()),
            )
            .toList()
          ..sort(
            (a, b) => _ascending
                ? a.netProfit.compareTo(b.netProfit)
                : b.netProfit.compareTo(a.netProfit),
          );
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Detailed statistics table'),
            subtitle: context.l10n.text(
              'Search and sort account, source, symbol, direction, strategy, setup, session, and weekday breakdowns.',
            ),
            icon: Icons.table_chart_outlined,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              SizedBox(
                width: 220,
                child: DropdownButtonFormField<String>(
                  initialValue: _group,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Breakdown'),
                  ),
                  items: [
                    for (final group in const [
                      'symbol',
                      'direction',
                      'strategy',
                      'setup',
                      'session',
                      'weekday',
                      'source',
                      'account',
                    ])
                      DropdownMenuItem(
                        value: group,
                        child: Text(context.l10n.text(_titleCase(group))),
                      ),
                  ],
                  onChanged: (value) => setState(() {
                    _group = value ?? _group;
                    _page = 0;
                  }),
                ),
              ),
              SizedBox(
                width: 260,
                child: TextField(
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Search'),
                    prefixIcon: const Icon(Icons.search),
                  ),
                  onChanged: (value) => setState(() {
                    _query = value;
                    _page = 0;
                  }),
                ),
              ),
              IconButton.filledTonal(
                tooltip: context.l10n.text(
                  _ascending ? 'Sort highest first' : 'Sort lowest first',
                ),
                onPressed: () => setState(() => _ascending = !_ascending),
                icon: Icon(
                  _ascending ? Icons.arrow_upward : Icons.arrow_downward,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (rows.isEmpty)
            AnalyticsEmptyState(
              title: context.l10n.text('No breakdown data available'),
              message: context.l10n.text('Adjust the search or filters.'),
            )
          else
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                columns: [
                  DataColumn(label: Text(context.l10n.text('Name'))),
                  DataColumn(label: Text(context.l10n.text('Trades'))),
                  DataColumn(label: Text(context.l10n.text('Net P/L'))),
                  DataColumn(label: Text(context.l10n.text('Gross profit'))),
                  DataColumn(label: Text(context.l10n.text('Gross loss'))),
                  DataColumn(label: Text(context.l10n.text('Win rate'))),
                  DataColumn(label: Text(context.l10n.text('Profit factor'))),
                  DataColumn(label: Text(context.l10n.text('Average trade'))),
                  DataColumn(label: Text(context.l10n.text('Average win'))),
                  DataColumn(label: Text(context.l10n.text('Average loss'))),
                  DataColumn(
                    label: Text(context.l10n.text('Maximum drawdown')),
                  ),
                  DataColumn(label: Text(context.l10n.text('Total lots'))),
                  DataColumn(
                    label: Text(context.l10n.text('Average lot size')),
                  ),
                  DataColumn(label: Text(context.l10n.text('Average holding'))),
                  DataColumn(label: Text(context.l10n.text('Buy P/L'))),
                  DataColumn(label: Text(context.l10n.text('Sell P/L'))),
                  DataColumn(label: Text(context.l10n.text('Best trade'))),
                  DataColumn(label: Text(context.l10n.text('Worst trade'))),
                ],
                rows: [
                  for (final row in rows.skip(_page * 25).take(25))
                    DataRow(
                      cells: [
                        DataCell(
                          Text(
                            '${context.l10n.text(row.label)} (n=${row.trades})',
                          ),
                        ),
                        DataCell(Text('${row.trades}')),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.netProfit,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.grossProfit,
                              widget.result.currency,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              -row.grossLoss,
                              widget.result.currency,
                            ),
                          ),
                        ),
                        DataCell(Text(_percent(row.winRate))),
                        DataCell(
                          Text(
                            row.profitFactor?.toStringAsFixed(2) ??
                                context.l10n.text('No losing trades'),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.averageTrade,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.averageWin,
                              widget.result.currency,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              -row.averageLoss,
                              widget.result.currency,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.maxDrawdown,
                              widget.result.currency,
                            ),
                          ),
                        ),
                        DataCell(Text(row.totalLots.toStringAsFixed(2))),
                        DataCell(Text(row.averageLots.toStringAsFixed(2))),
                        DataCell(Text(_duration(row.averageHoldingMinutes))),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.buyProfit,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.sellProfit,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.bestTrade,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              row.worstTrade,
                              widget.result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          if (rows.length > 25) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Text(
                  context.l10n.text('Page {page} of {pages}', {
                    'page': _page + 1,
                    'pages': (rows.length / 25).ceil(),
                  }),
                ),
                IconButton(
                  tooltip: context.l10n.text('Previous page'),
                  onPressed: _page == 0 ? null : () => setState(() => _page--),
                  icon: const Icon(Icons.chevron_left),
                ),
                IconButton(
                  tooltip: context.l10n.text('Next page'),
                  onPressed: (_page + 1) * 25 >= rows.length
                      ? null
                      : () => setState(() => _page++),
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _MonthlyTable extends StatelessWidget {
  const _MonthlyTable({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Monthly performance table'),
            subtitle: context.l10n.text(
              'Monthly return uses the selected period starting balance when available.',
            ),
            icon: Icons.calendar_view_month,
          ),
          const SizedBox(height: 12),
          if (result.monthlyTable.isEmpty)
            _chartEmpty(context)
          else
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                columns: [
                  DataColumn(label: Text(context.l10n.text('Month'))),
                  DataColumn(label: Text(context.l10n.text('Net P/L'))),
                  DataColumn(label: Text(context.l10n.text('Return'))),
                  DataColumn(label: Text(context.l10n.text('Trades'))),
                  DataColumn(label: Text(context.l10n.text('Win rate'))),
                  DataColumn(label: Text(context.l10n.text('Profit factor'))),
                  DataColumn(
                    label: Text(context.l10n.text('Maximum drawdown')),
                  ),
                  DataColumn(
                    label: Text(context.l10n.text('Average lot size')),
                  ),
                  DataColumn(label: Text(context.l10n.text('Best trade'))),
                  DataColumn(label: Text(context.l10n.text('Worst trade'))),
                ],
                rows: [
                  for (final item in result.monthlyTable)
                    DataRow(
                      cells: [
                        DataCell(
                          Text(
                            DateFormat.yMMM(
                              Localizations.localeOf(context).toLanguageTag(),
                            ).format(item.monthUtc),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              item.metrics.netProfit,
                              result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            item.returnPercent == null
                                ? context.l10n.text('N/A')
                                : _percent(item.returnPercent!),
                          ),
                        ),
                        DataCell(Text('${item.metrics.trades}')),
                        DataCell(Text(_percent(item.metrics.winRate))),
                        DataCell(
                          Text(
                            item.metrics.profitFactor?.toStringAsFixed(2) ??
                                context.l10n.text('No losing trades'),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              item.metrics.maxDrawdown,
                              result.currency,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(item.metrics.averageLots.toStringAsFixed(2)),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              item.metrics.bestTrade,
                              result.currency,
                              signed: true,
                            ),
                          ),
                        ),
                        DataCell(
                          Text(
                            _money(
                              context,
                              item.metrics.worstTrade,
                              result.currency,
                              signed: true,
                            ),
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

class _InsightsPanel extends StatelessWidget {
  const _InsightsPanel({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Account analyst insights'),
            subtitle: context.l10n.text(
              'Neutral observations derived from the selected data; they are not financial advice.',
            ),
            icon: Icons.lightbulb_outline,
          ),
          const SizedBox(height: 12),
          if (result.insights.isEmpty)
            AnalyticsEmptyState(
              title: context.l10n.text('Insufficient data'),
              message: context.l10n.text(
                'More eligible closed trades are needed for reliable observations.',
              ),
            )
          else
            for (final insight in result.insights)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: InsightBanner(
                  title: context.l10n.text(insight.title),
                  message:
                      '${context.l10n.text(insight.message, {'symbol': _valueFromSupport(insight.supportingValue), 'session': _valueFromSupport(insight.supportingValue)})}\n${context.l10n.text('Supporting value')}: ${insight.supportingValue} · n=${insight.sampleSize}',
                  icon: _insightIcon(insight.kind),
                  tone: _insightTone(insight.kind),
                ),
              ),
        ],
      ),
    );
  }
}

class _DataQualityPanel extends StatelessWidget {
  const _DataQualityPanel({required this.result});

  final TradingAnalyticsResult result;

  @override
  Widget build(BuildContext context) {
    final quality = result.dataQuality;
    final dateFormat = DateFormat.yMMMd(
      Localizations.localeOf(context).toLanguageTag(),
    ).add_Hm();
    final rows = <(String, String)>[
      ('Total raw records', '${quality.totalRawRecords}'),
      ('Records matching filters', '${quality.filteredRecords}'),
      ('Eligible closed trades', '${quality.eligibleClosedTrades}'),
      ('Open trades', '${quality.openTrades}'),
      ('Deposits', '${quality.deposits}'),
      ('Withdrawals', '${quality.withdrawals}'),
      ('Cancelled orders', '${quality.cancelledOrders}'),
      ('Pending orders', '${quality.pendingOrders}'),
      ('Rejected or expired orders', '${quality.rejectedOrExpiredOrders}'),
      ('Duplicate records ignored', '${quality.duplicateRecordsIgnored}'),
      ('Trades missing close time', '${quality.tradesMissingCloseTime}'),
      ('Trades missing lot size', '${quality.tradesMissingLotSize}'),
      ('Trades missing strategy', '${quality.tradesMissingStrategy}'),
      ('Trades missing risk data', '${quality.tradesMissingRiskData}'),
      (
        'Earliest trade date',
        quality.earliestTradeUtc == null
            ? context.l10n.text('N/A')
            : dateFormat.format(quality.earliestTradeUtc!.toLocal()),
      ),
      (
        'Latest trade date',
        quality.latestTradeUtc == null
            ? context.l10n.text('N/A')
            : dateFormat.format(quality.latestTradeUtc!.toLocal()),
      ),
      (
        'Last Excel import',
        quality.lastExcelImportUtc == null
            ? context.l10n.text('N/A')
            : dateFormat.format(quality.lastExcelImportUtc!.toLocal()),
      ),
      (
        'Last MT5 synchronization',
        quality.lastMt5SynchronizationUtc == null
            ? context.l10n.text('N/A')
            : dateFormat.format(quality.lastMt5SynchronizationUtc!.toLocal()),
      ),
      (
        'Equity snapshots',
        quality.equitySnapshotsAvailable
            ? context.l10n.text('Available')
            : context.l10n.text('Historical equity snapshots unavailable'),
      ),
    ];
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Data quality and synchronization'),
            subtitle: context.l10n.text(
              'These diagnostics explain why some statistics may be unavailable.',
            ),
            icon: Icons.fact_check_outlined,
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 900
                  ? 3
                  : constraints.maxWidth >= 560
                  ? 2
                  : 1;
              final width =
                  (constraints.maxWidth - (columns - 1) * 12) / columns;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (final row in rows)
                    SizedBox(
                      width: width,
                      child: _QualityItem(
                        label: context.l10n.text(row.$1),
                        value: row.$2,
                      ),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _TimeSeriesCard extends StatelessWidget {
  const _TimeSeriesCard({
    required this.title,
    required this.subtitle,
    required this.points,
    required this.currency,
    this.negativeBelowZero = false,
  });

  final String title;
  final String subtitle;
  final List<AnalyticsPoint> points;
  final String currency;
  final bool negativeBelowZero;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text(title),
            subtitle: context.l10n.text(subtitle),
          ),
          const SizedBox(height: 12),
          if (points.isEmpty)
            _chartEmpty(context)
          else
            _SimpleLineChart(
              points: points,
              currency: currency,
              tone: negativeBelowZero && points.last.value < 0
                  ? analyticsNegative
                  : analyticsInformation,
              height: 230,
            ),
        ],
      ),
    );
  }
}

class _HistogramCard extends StatelessWidget {
  const _HistogramCard({
    required this.title,
    required this.subtitle,
    required this.bins,
    required this.currency,
  });

  final String title;
  final String subtitle;
  final List<AnalyticsHistogramBin> bins;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text(title),
            subtitle: context.l10n.text(subtitle),
            icon: Icons.bar_chart,
          ),
          const SizedBox(height: 12),
          if (bins.isEmpty)
            _chartEmpty(context)
          else
            SizedBox(
              height: 230,
              child: BarChart(
                BarChartData(
                  borderData: FlBorderData(show: false),
                  gridData: const FlGridData(drawVerticalLine: false),
                  titlesData: const FlTitlesData(
                    topTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                    rightTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                  ),
                  barTouchData: BarTouchData(
                    touchTooltipData: BarTouchTooltipData(
                      getTooltipItem: (group, groupIndex, rod, rodIndex) {
                        final bin = bins[groupIndex];
                        return BarTooltipItem(
                          '${bin.label} $currency\n${bin.count} ${context.l10n.text('trade(s)')}',
                          TextStyle(
                            color: Theme.of(
                              context,
                            ).colorScheme.onInverseSurface,
                            fontWeight: FontWeight.w700,
                          ),
                        );
                      },
                    ),
                  ),
                  barGroups: [
                    for (final indexed in bins.indexed)
                      BarChartGroupData(
                        x: indexed.$1,
                        barRods: [
                          BarChartRodData(
                            toY: indexed.$2.count.toDouble(),
                            color: indexed.$2.upper <= 0
                                ? analyticsNegative
                                : analyticsInformation,
                            width: math.max(5, 22 - bins.length / 2),
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(4),
                            ),
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
}

class _ScatterCard extends StatelessWidget {
  const _ScatterCard({
    required this.title,
    required this.xLabel,
    required this.yLabel,
    required this.points,
    required this.currency,
  });

  final String title;
  final String xLabel;
  final String yLabel;
  final List<({double x, double y})> points;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text(title),
            subtitle:
                '${context.l10n.text(xLabel)} × ${context.l10n.text(yLabel)}',
            icon: Icons.scatter_plot_outlined,
          ),
          const SizedBox(height: 12),
          if (points.isEmpty)
            _chartEmpty(context)
          else
            SizedBox(
              height: 230,
              child: LineChart(
                LineChartData(
                  borderData: FlBorderData(show: false),
                  gridData: const FlGridData(),
                  titlesData: const FlTitlesData(
                    topTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                    rightTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: false),
                    ),
                  ),
                  lineTouchData: LineTouchData(
                    touchTooltipData: LineTouchTooltipData(
                      getTooltipItems: (spots) => [
                        for (final spot in spots)
                          LineTooltipItem(
                            '${spot.x.toStringAsFixed(2)}\n${spot.y.toStringAsFixed(2)} $currency',
                            TextStyle(
                              color: Theme.of(
                                context,
                              ).colorScheme.onInverseSurface,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                      ],
                    ),
                  ),
                  lineBarsData: [
                    LineChartBarData(
                      spots: [
                        for (final point in points) FlSpot(point.x, point.y),
                      ],
                      color: Colors.transparent,
                      barWidth: 0,
                      dotData: FlDotData(
                        show: true,
                        getDotPainter: (spot, percent, bar, index) =>
                            FlDotCirclePainter(
                              radius: 4,
                              color: spot.y >= 0
                                  ? analyticsPositive
                                  : analyticsNegative,
                              strokeWidth: 0,
                            ),
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

class _SimpleLineChart extends StatelessWidget {
  const _SimpleLineChart({
    required this.points,
    required this.currency,
    required this.tone,
    required this.height,
  });

  final List<AnalyticsPoint> points;
  final String currency;
  final Color tone;
  final double height;

  @override
  Widget build(BuildContext context) {
    if (points.isEmpty) return _chartEmpty(context);
    final values = points.map((point) => point.value).toList(growable: false);
    final min = math.min(0.0, values.reduce(math.min));
    final max = math.max(0.0, values.reduce(math.max));
    final spread = math.max(1.0, max - min);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final visiblePoints = points.length > 240
        ? [
            for (
              var index = 0;
              index < points.length;
              index += (points.length / 240).ceil()
            )
              points[index],
            if ((points.length - 1) % (points.length / 240).ceil() != 0)
              points.last,
          ]
        : points;
    return Semantics(
      label:
          '${context.l10n.text('Chart')}: ${visiblePoints.length} ${context.l10n.text('points')}, '
          '${context.l10n.text('latest')} ${visiblePoints.last.value.toStringAsFixed(2)} $currency',
      child: SizedBox(
        height: height,
        child: LineChart(
          LineChartData(
            minY: min - spread * .1,
            maxY: max + spread * .1,
            borderData: FlBorderData(show: false),
            gridData: FlGridData(
              drawVerticalLine: false,
              getDrawingHorizontalLine: (value) => FlLine(
                color: value == 0
                    ? Theme.of(context).colorScheme.outline
                    : Theme.of(
                        context,
                      ).colorScheme.outlineVariant.withValues(alpha: .5),
              ),
            ),
            titlesData: FlTitlesData(
              topTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              rightTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              bottomTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              leftTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  reservedSize: 48,
                  getTitlesWidget: (value, meta) => Text(
                    NumberFormat.compact(locale: locale).format(value),
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                ),
              ),
            ),
            lineTouchData: LineTouchData(
              touchTooltipData: LineTouchTooltipData(
                getTooltipItems: (spots) => [
                  for (final spot in spots)
                    () {
                      final point = visiblePoints[spot.x.round()];
                      final suffix = point.label == 'no-losing-trades'
                          ? '\n${context.l10n.text('No losing trades')}'
                          : point.label == 'small-sample'
                          ? '\n${context.l10n.text('Small sample')}'
                          : '';
                      return LineTooltipItem(
                        '${DateFormat.yMMMd(locale).add_Hm().format(point.timestampUtc.toLocal())}\n'
                        '${spot.y.toStringAsFixed(2)} $currency$suffix',
                        TextStyle(
                          color: Theme.of(context).colorScheme.onInverseSurface,
                          fontWeight: FontWeight.w700,
                        ),
                      );
                    }(),
                ],
              ),
            ),
            lineBarsData: [
              LineChartBarData(
                spots: [
                  for (final indexed in visiblePoints.indexed)
                    FlSpot(indexed.$1.toDouble(), indexed.$2.value),
                ],
                color: tone,
                barWidth: 2.4,
                isCurved: false,
                dotData: FlDotData(show: visiblePoints.length <= 20),
                belowBarData: BarAreaData(
                  show: true,
                  color: tone.withValues(alpha: .08),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DualLineChart extends StatelessWidget {
  const _DualLineChart({
    required this.primary,
    required this.secondary,
    required this.currency,
  });

  final List<AnalyticsPoint> primary;
  final List<AnalyticsPoint> secondary;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final combined = [...primary, ...secondary];
    if (combined.isEmpty) return _chartEmpty(context);
    final minimum = combined.map((point) => point.value).reduce(math.min);
    final maximum = combined.map((point) => point.value).reduce(math.max);
    final spread = math.max(1.0, maximum - minimum);
    return SizedBox(
      height: 240,
      child: LineChart(
        LineChartData(
          minY: minimum - spread * .1,
          maxY: maximum + spread * .1,
          borderData: FlBorderData(show: false),
          gridData: const FlGridData(drawVerticalLine: false),
          titlesData: const FlTitlesData(
            topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          lineBarsData: [
            if (primary.isNotEmpty)
              LineChartBarData(
                spots: [
                  for (final indexed in primary.indexed)
                    FlSpot(indexed.$1.toDouble(), indexed.$2.value),
                ],
                color: analyticsInformation,
                dotData: const FlDotData(show: false),
                barWidth: 2.5,
              ),
            if (secondary.isNotEmpty)
              LineChartBarData(
                spots: [
                  for (final indexed in secondary.indexed)
                    FlSpot(indexed.$1.toDouble(), indexed.$2.value),
                ],
                color: analyticsWarning,
                dotData: const FlDotData(show: false),
                barWidth: 2.5,
              ),
          ],
        ),
      ),
    );
  }
}

class _PnlBarChart extends StatelessWidget {
  const _PnlBarChart({required this.points, required this.currency});

  final List<AnalyticsPoint> points;
  final String currency;

  @override
  Widget build(BuildContext context) {
    if (points.isEmpty) return _chartEmpty(context);
    final visible = points.length > 90
        ? points.sublist(points.length - 90)
        : points;
    return SizedBox(
      height: 240,
      child: BarChart(
        BarChartData(
          borderData: FlBorderData(show: false),
          gridData: const FlGridData(drawVerticalLine: false),
          titlesData: const FlTitlesData(
            topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          barTouchData: BarTouchData(
            touchTooltipData: BarTouchTooltipData(
              getTooltipItem: (group, groupIndex, rod, rodIndex) {
                final point = visible[groupIndex];
                return BarTooltipItem(
                  '${point.label}\n${_money(context, point.value, currency, signed: true)}\n'
                  '${point.tradeCount} ${context.l10n.text('trade(s)')} · '
                  '${_percent(point.winRate)} · ${point.totalLots.toStringAsFixed(2)} ${context.l10n.text('lots')}',
                  TextStyle(
                    color: Theme.of(context).colorScheme.onInverseSurface,
                    fontWeight: FontWeight.w700,
                  ),
                );
              },
            ),
          ),
          barGroups: [
            for (final indexed in visible.indexed)
              BarChartGroupData(
                x: indexed.$1,
                barRods: [
                  BarChartRodData(
                    toY: indexed.$2.value,
                    color: _financialTone(indexed.$2.value),
                    width: math.max(3, 16 - visible.length / 10),
                    borderRadius: BorderRadius.vertical(
                      top: indexed.$2.value >= 0
                          ? const Radius.circular(3)
                          : Radius.zero,
                      bottom: indexed.$2.value < 0
                          ? const Radius.circular(3)
                          : Radius.zero,
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.text,
    this.tone = analyticsInformation,
  });

  final IconData icon;
  final String text;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: .09),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: tone, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(context.l10n.text(text))),
        ],
      ),
    );
  }
}

class _QualityItem extends StatelessWidget {
  const _QualityItem({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

List<(String, int)> _holdingCategories(TradingAnalyticsResult result) {
  final counts = List<int>.filled(9, 0);
  for (final trade in result.closedTrades) {
    final minutes = trade.holdingDuration?.inMinutes;
    if (minutes == null) continue;
    final index = switch (minutes) {
      < 5 => 0,
      < 15 => 1,
      < 30 => 2,
      < 60 => 3,
      < 240 => 4,
      < 720 => 5,
      < 1440 => 6,
      < 4320 => 7,
      _ => 8,
    };
    counts[index]++;
  }
  const labels = [
    'Under 5 minutes',
    '5–15 minutes',
    '15–30 minutes',
    '30–60 minutes',
    '1–4 hours',
    '4–12 hours',
    '12–24 hours',
    '1–3 days',
    'More than 3 days',
  ];
  return [for (final item in labels.indexed) (item.$2, counts[item.$1])];
}

Widget _responsivePair(
  BuildContext context, {
  required Widget first,
  required Widget second,
}) {
  return LayoutBuilder(
    builder: (context, constraints) {
      if (constraints.maxWidth < 780) {
        return Column(children: [first, const SizedBox(height: 16), second]);
      }
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: first),
          const SizedBox(width: 16),
          Expanded(child: second),
        ],
      );
    },
  );
}

AnalyticsMetricData _metric(
  BuildContext context,
  String label,
  String value,
  IconData icon, {
  Color? tone,
}) {
  return AnalyticsMetricData(
    label: context.l10n.text(label),
    value: value,
    icon: icon,
    tone: tone,
  );
}

Widget _labelValue(BuildContext context, String label, String value) => Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  children: [
    Text(
      context.l10n.text(label),
      style: Theme.of(context).textTheme.labelMedium,
    ),
    Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
  ],
);

Widget _chartEmpty(BuildContext context) => SizedBox(
  height: 210,
  child: AnalyticsEmptyState(
    title: context.l10n.text('No chart data'),
    message: context.l10n.text(
      'Adjust the filters or add eligible closed trades.',
    ),
  ),
);

String _money(
  BuildContext context,
  double value,
  String currency, {
  bool signed = false,
}) {
  final locale = Localizations.localeOf(context).toLanguageTag();
  final code = currency.trim().toUpperCase();
  final sign = signed && value > 0 ? '+' : '';
  if (code.isEmpty || code == '%' || code == 'LOTS' || code == 'MIN') {
    return '$sign${NumberFormat('#,##0.00', locale).format(value)}'
        '${code.isEmpty ? '' : ' $code'}';
  }
  try {
    return '$sign${NumberFormat.simpleCurrency(name: code, locale: locale).format(value)}';
  } catch (_) {
    return '$sign${NumberFormat('#,##0.00', locale).format(value)} $code';
  }
}

String _percent(double value, {bool signed = false}) {
  final sign = signed && value > 0 ? '+' : '';
  return '$sign${value.toStringAsFixed(1)}%';
}

String _duration(double rawMinutes) {
  final minutes = rawMinutes.round();
  if (minutes < 60) return '${minutes}m';
  final hours = minutes ~/ 60;
  final remainingMinutes = minutes % 60;
  if (hours < 24) return '${hours}h ${remainingMinutes}m';
  final days = hours ~/ 24;
  final remainingHours = hours % 24;
  return '${days}d ${remainingHours}h';
}

Color _financialTone(double value) {
  if (value > 0) return analyticsPositive;
  if (value < 0) return analyticsNegative;
  return analyticsInformation;
}

IconData _insightIcon(AnalyticsInsightKind kind) => switch (kind) {
  AnalyticsInsightKind.strength => Icons.trending_up,
  AnalyticsInsightKind.risk => Icons.warning_amber_rounded,
  AnalyticsInsightKind.pattern => Icons.insights_outlined,
  AnalyticsInsightKind.dataQuality => Icons.fact_check_outlined,
};

Color _insightTone(AnalyticsInsightKind kind) => switch (kind) {
  AnalyticsInsightKind.strength => analyticsPositive,
  AnalyticsInsightKind.risk => analyticsWarning,
  AnalyticsInsightKind.pattern => analyticsInformation,
  AnalyticsInsightKind.dataQuality => analyticsNegative,
};

String _valueFromSupport(String value) =>
    value.split(':').first.trim().isEmpty ? value : value.split(':').first;

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return '${value[0].toUpperCase()}${value.substring(1)}';
}
