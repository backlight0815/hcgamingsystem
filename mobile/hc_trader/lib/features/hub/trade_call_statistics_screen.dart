import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/app_role_permissions.dart';
import '../../models/app_user.dart';
import '../../models/trade_call_statistics.dart';
import '../../models/trading_signal_item.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trade_call_statistics_service.dart';
import '../../services/trading_date_filter_session.dart';
import '../../shared/app_back_button.dart';
import '../../shared/empty_state.dart';
import '../../shared/trading_date_filter_bar.dart';
import 'trade_call_detail_screen.dart';

class TradeCallStatisticsScreen extends StatefulWidget {
  const TradeCallStatisticsScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<TradeCallStatisticsScreen> createState() =>
      _TradeCallStatisticsScreenState();
}

class _TradeCallStatisticsScreenState extends State<TradeCallStatisticsScreen> {
  final _service = const TradeCallStatisticsService();
  late TradeCallStatisticsFilter _filter;
  late Future<List<TradingSignalItem>> _callsFuture;

  @override
  void initState() {
    super.initState();
    _filter = TradeCallStatisticsFilter(
      dateFilter: TradingDateFilterSession.read(
        widget.user.uid,
        timezone: widget.user.timezone,
        timezoneOffsetMinutes: widget.user.timezoneOffsetMinutes,
      ),
    );
    _callsFuture = widget.repository.loadTradeCallStatisticsSignals();
  }

  Future<void> _reload() async {
    final future = widget.repository.loadTradeCallStatisticsSignals();
    setState(() => _callsFuture = future);
    await future;
  }

  bool get _isAdministrator => widget.user.effectiveRoleIds.any(
    const {
      RolePermissionMatrix.superAdmin,
      RolePermissionMatrix.subAdmin,
      RolePermissionMatrix.founder,
      RolePermissionMatrix.signalProviderManagement,
    }.contains,
  );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trade-Call Statistics')),
      ),
      body: FutureBuilder<List<TradingSignalItem>>(
        future: _callsFuture,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _StatisticsError(onRetry: _reload);
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final calls = snapshot.data!;
          late final TradeCallStatistics statistics;
          try {
            statistics = _service.calculate(
              calls: calls,
              viewer: widget.user,
              filter: _filter,
            );
          } on TradeCallAccessException catch (error) {
            return Center(child: Text(context.l10n.text(error.message)));
          }
          final instruments =
              calls
                  .map((call) => call.tradingPair.toUpperCase().trim())
                  .where((value) => value.isNotEmpty)
                  .toSet()
                  .toList()
                ..sort();
          final sources =
              calls
                  .map((call) => call.signalSource.trim())
                  .where((value) => value.isNotEmpty)
                  .toSet()
                  .toList()
                ..sort();
          final directions =
              calls
                  .map((call) => call.direction.trim())
                  .where((value) => value.isNotEmpty)
                  .toSet()
                  .toList()
                ..sort();
          final orderTypes =
              calls
                  .map((call) => call.orderType.trim())
                  .where((value) => value.isNotEmpty)
                  .toSet()
                  .toList()
                ..sort();
          final providers = <String, String>{
            for (final call in calls)
              if (call.providerId.trim().isNotEmpty)
                call.providerId: call.analyst.trim().isEmpty
                    ? call.providerId
                    : call.analyst,
          };
          final byId = {for (final call in calls) call.id: call};
          return RefreshIndicator(
            onRefresh: _reload,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                  sliver: SliverList.list(
                    children: [
                      _StatisticsHero(statistics: statistics),
                      const SizedBox(height: 14),
                      TradingDateFilterBar(
                        filter: _filter.dateFilter,
                        availableYears:
                            calls
                                .map(
                                  (call) =>
                                      (call.closedAt ??
                                              call.updatedAt ??
                                              call.createdAt)
                                          ?.year,
                                )
                                .whereType<int>()
                                .toSet()
                                .toList()
                              ..sort((left, right) => right.compareTo(left)),
                        onChanged: (value) {
                          TradingDateFilterSession.write(
                            widget.user.uid,
                            value,
                          );
                          setState(() {
                            _filter = _filter.copyWith(dateFilter: value);
                          });
                        },
                      ),
                      _StatisticsFilters(
                        filter: _filter,
                        instruments: instruments,
                        sources: sources,
                        directions: directions,
                        orderTypes: orderTypes,
                        providers: providers,
                        showProviders: _isAdministrator,
                        onChanged: (value) => setState(() => _filter = value),
                      ),
                      const SizedBox(height: 14),
                      if (statistics.totalCalls == 0)
                        EmptyState(
                          icon: Icons.query_stats_outlined,
                          title: context.l10n.text(
                            'No trade calls match the selected filters.',
                          ),
                          description: context.l10n.text(
                            'Change the period or clear a filter to review performance.',
                          ),
                        )
                      else ...[
                        _MetricGrid(statistics: statistics),
                        const SizedBox(height: 14),
                        _LifecyclePanel(statistics: statistics),
                        const SizedBox(height: 14),
                        _OutcomePanel(statistics: statistics),
                        const SizedBox(height: 14),
                        _RiskDurationPanel(statistics: statistics),
                        const SizedBox(height: 14),
                        _TwoColumnSections(
                          left: _BreakdownPanel(
                            title: 'Monthly performance',
                            values: statistics.monthlyPerformance,
                          ),
                          right: _BreakdownPanel(
                            title: 'Performance by instrument',
                            values: statistics.byInstrument,
                          ),
                        ),
                        const SizedBox(height: 14),
                        _TwoColumnSections(
                          left: _BreakdownPanel(
                            title: 'Performance by direction',
                            values: statistics.byDirection,
                          ),
                          right: _BreakdownPanel(
                            title: 'Performance by signal source',
                            values: statistics.bySignalSource,
                          ),
                        ),
                        const SizedBox(height: 14),
                        _TwoColumnSections(
                          left: _BreakdownPanel(
                            title: 'Weekly performance',
                            values: statistics.weeklyPerformance,
                          ),
                          right: _BreakdownPanel(
                            title: 'Performance by order type',
                            values: statistics.byOrderType,
                          ),
                        ),
                        if (_isAdministrator) ...[
                          const SizedBox(height: 14),
                          _ProviderComparison(values: statistics.byProvider),
                        ],
                        const SizedBox(height: 14),
                        _RecentCompleted(
                          repository: widget.repository,
                          calls: statistics.recentCompletedCallIds
                              .map((id) => byId[id])
                              .whereType<TradingSignalItem>()
                              .toList(growable: false),
                        ),
                        if (statistics.missingProviderCount > 0) ...[
                          const SizedBox(height: 12),
                          _QualityNotice(
                            count: statistics.missingProviderCount,
                          ),
                        ],
                      ],
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _StatisticsHero extends StatelessWidget {
  const _StatisticsHero({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [colors.primaryContainer, colors.tertiaryContainer],
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Wrap(
        spacing: 24,
        runSpacing: 14,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Icon(Icons.query_stats_outlined, size: 46, color: colors.primary),
          SizedBox(
            width: 520,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Trade-Call Performance'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  context.l10n.text(
                    'Win, loss, and break-even rates use {count} evaluated calls. Draft, pending, active, cancelled, rejected, test, duplicate, and never-activated expired calls are excluded.',
                    {'count': statistics.evaluatedCalls},
                  ),
                ),
              ],
            ),
          ),
          Chip(
            avatar: Icon(
              statistics.hasSufficientSample
                  ? Icons.verified_outlined
                  : Icons.info_outline,
              size: 18,
            ),
            label: Text(
              context.l10n.text(
                statistics.hasSufficientSample
                    ? 'Sufficient sample'
                    : 'Provisional sample',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatisticsFilters extends StatelessWidget {
  const _StatisticsFilters({
    required this.filter,
    required this.instruments,
    required this.sources,
    required this.directions,
    required this.orderTypes,
    required this.providers,
    required this.showProviders,
    required this.onChanged,
  });

  final TradeCallStatisticsFilter filter;
  final List<String> instruments;
  final List<String> sources;
  final List<String> directions;
  final List<String> orderTypes;
  final Map<String, String> providers;
  final bool showProviders;
  final ValueChanged<TradeCallStatisticsFilter> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          _FilterDropdown(
            label: 'Instrument',
            value: filter.instrument,
            values: instruments,
            onChanged: (value) => onChanged(filter.copyWith(instrument: value)),
          ),
          _FilterDropdown(
            label: 'Signal source',
            value: filter.signalSource,
            values: sources,
            onChanged: (value) =>
                onChanged(filter.copyWith(signalSource: value)),
          ),
          if (showProviders)
            _FilterDropdown(
              label: 'Provider',
              value: filter.providerId,
              values: providers.keys.toList(),
              labels: providers,
              onChanged: (value) =>
                  onChanged(filter.copyWith(providerId: value)),
            ),
          _FilterDropdown(
            label: 'Status',
            value: filter.status,
            values: const [
              'draft',
              'pending',
              'active',
              'closed',
              'cancelled',
              'expired',
              'excluded',
            ],
            onChanged: (value) => onChanged(filter.copyWith(status: value)),
          ),
          _FilterDropdown(
            label: 'Direction',
            value: filter.direction,
            values: directions,
            onChanged: (value) => onChanged(filter.copyWith(direction: value)),
          ),
          _FilterDropdown(
            label: 'Order type',
            value: filter.orderType,
            values: orderTypes,
            onChanged: (value) => onChanged(filter.copyWith(orderType: value)),
          ),
          ActionChip(
            avatar: const Icon(Icons.filter_alt_off_outlined, size: 18),
            label: Text(context.l10n.text('Clear filters')),
            onPressed: () => onChanged(
              TradeCallStatisticsFilter(dateFilter: filter.dateFilter),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterDropdown extends StatelessWidget {
  const _FilterDropdown({
    required this.label,
    required this.value,
    required this.values,
    required this.onChanged,
    this.labels = const {},
  });

  final String label;
  final String value;
  final List<String> values;
  final Map<String, String> labels;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 190,
      child: DropdownButtonFormField<String>(
        initialValue: values.contains(value) ? value : '',
        isExpanded: true,
        decoration: InputDecoration(labelText: context.l10n.text(label)),
        items: [
          DropdownMenuItem(value: '', child: Text(context.l10n.text('All'))),
          for (final item in values)
            DropdownMenuItem(
              value: item,
              child: Text(
                context.l10n.text(labels[item] ?? item),
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        onChanged: (value) => onChanged(value ?? ''),
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    final values = <(String, String, IconData)>[
      ('Total calls', '${statistics.totalCalls}', Icons.campaign_outlined),
      ('Evaluated calls', '${statistics.evaluatedCalls}', Icons.task_alt),
      (
        'Win rate',
        '${statistics.winRate.toStringAsFixed(1)}%',
        Icons.trending_up,
      ),
      (
        'Loss rate',
        '${statistics.lossRate.toStringAsFixed(1)}%',
        Icons.trending_down,
      ),
      (
        'Break-even rate',
        '${statistics.breakEvenRate.toStringAsFixed(1)}%',
        Icons.horizontal_rule,
      ),
      (
        'Average planned R:R',
        '1:${statistics.averagePlannedRiskReward.toStringAsFixed(2)}',
        Icons.balance_outlined,
      ),
      (
        'Total pips',
        statistics.totalPips.toStringAsFixed(1),
        Icons.stacked_line_chart,
      ),
      (
        'Average holding duration',
        _duration(statistics.averageHoldingDuration),
        Icons.timer_outlined,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1100
            ? 4
            : constraints.maxWidth >= 620
            ? 2
            : 1;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisExtent: 112,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
          ),
          itemCount: values.length,
          itemBuilder: (context, index) {
            final value = values[index];
            return DecoratedBox(
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surfaceContainerLow,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(child: Icon(value.$3)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(context.l10n.text(value.$1)),
                          Text(
                            value.$2,
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _OutcomePanel extends StatelessWidget {
  const _OutcomePanel({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Evaluated outcomes',
      child: Column(
        children: [
          _OutcomeBar(
            label: 'Winning calls',
            count: statistics.winningCalls,
            value: statistics.winRate / 100,
            color: const Color(0xFF17855B),
          ),
          _OutcomeBar(
            label: 'Losing calls',
            count: statistics.losingCalls,
            value: statistics.lossRate / 100,
            color: const Color(0xFFC64B55),
          ),
          _OutcomeBar(
            label: 'Break-even calls',
            count: statistics.breakEvenCalls,
            value: statistics.breakEvenRate / 100,
            color: const Color(0xFF6B7280),
          ),
          const Divider(height: 28),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              context.l10n.text(
                '{count} calls were excluded from the percentage denominator.',
                {'count': statistics.excludedFromRates},
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LifecyclePanel extends StatelessWidget {
  const _LifecyclePanel({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    final values = <(String, int)>[
      ('Draft calls', statistics.draftCalls),
      ('Pending calls', statistics.pendingCalls),
      ('Active calls', statistics.activeCalls),
      ('Closed calls', statistics.closedCalls),
      ('Cancelled calls', statistics.cancelledCalls),
      ('Expired calls', statistics.expiredCalls),
      ('Take-profit calls', statistics.takeProfitCalls),
      ('Stop-loss calls', statistics.stopLossCalls),
      ('Break-even calls', statistics.breakEvenCalls),
    ];
    return _Panel(
      title: 'Call lifecycle',
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (final value in values)
            Chip(label: Text('${context.l10n.text(value.$1)}: ${value.$2}')),
        ],
      ),
    );
  }
}

class _RiskDurationPanel extends StatelessWidget {
  const _RiskDurationPanel({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    final values = <(String, String)>[
      (
        'Average planned R:R',
        '1:${statistics.averagePlannedRiskReward.toStringAsFixed(2)}',
      ),
      (
        'Average realized R:R',
        statistics.averageRealizedRiskReward.toStringAsFixed(2),
      ),
      ('Average pips', statistics.averagePips.toStringAsFixed(1)),
      ('Positive pips', statistics.totalPositivePips.toStringAsFixed(1)),
      ('Negative pips', statistics.totalNegativePips.toStringAsFixed(1)),
      (
        'Shortest holding duration',
        _duration(statistics.shortestHoldingDuration),
      ),
      (
        'Average holding duration',
        _duration(statistics.averageHoldingDuration),
      ),
      (
        'Longest holding duration',
        _duration(statistics.longestHoldingDuration),
      ),
    ];
    return _Panel(
      title: 'Risk, pips, and holding duration',
      child: Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          for (final value in values)
            SizedBox(
              width: 210,
              child: ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text(value.$1)),
                subtitle: Text(
                  value.$2,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _OutcomeBar extends StatelessWidget {
  const _OutcomeBar({
    required this.label,
    required this.count,
    required this.value,
    required this.color,
  });

  final String label;
  final int count;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      children: [
        SizedBox(width: 150, child: Text(context.l10n.text(label))),
        Expanded(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: value.clamp(0, 1),
              minHeight: 12,
              color: color,
            ),
          ),
        ),
        const SizedBox(width: 12),
        SizedBox(
          width: 70,
          child: Text('$count · ${(value * 100).toStringAsFixed(1)}%'),
        ),
      ],
    ),
  );
}

class _TwoColumnSections extends StatelessWidget {
  const _TwoColumnSections({required this.left, required this.right});

  final Widget left;
  final Widget right;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) => constraints.maxWidth < 780
        ? Column(children: [left, const SizedBox(height: 14), right])
        : Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: left),
              const SizedBox(width: 14),
              Expanded(child: right),
            ],
          ),
  );
}

class _BreakdownPanel extends StatelessWidget {
  const _BreakdownPanel({required this.title, required this.values});

  final String title;
  final List<TradeCallBreakdown> values;

  @override
  Widget build(BuildContext context) => _Panel(
    title: title,
    child: values.isEmpty
        ? Text(context.l10n.text('No evaluated calls for this breakdown.'))
        : Column(
            children: [
              for (final value in values.take(8))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text(value.label)),
                  subtitle: Text(
                    context.l10n.text('{wins}W · {losses}L · {be}BE', {
                      'wins': value.wins,
                      'losses': value.losses,
                      'be': value.breakEvens,
                    }),
                  ),
                  trailing: Text(
                    '${value.winRate.toStringAsFixed(1)}%\n${value.totalPips.toStringAsFixed(1)} pips',
                    textAlign: TextAlign.end,
                  ),
                ),
            ],
          ),
  );
}

class _ProviderComparison extends StatelessWidget {
  const _ProviderComparison({required this.values});

  final List<TradeCallBreakdown> values;

  @override
  Widget build(BuildContext context) {
    final ranked = [...values]
      ..sort((left, right) {
        final rate = right.winRate.compareTo(left.winRate);
        return rate == 0 ? right.evaluated.compareTo(left.evaluated) : rate;
      });
    return _Panel(
      title: 'Signal-provider comparison',
      child: values.isEmpty
          ? Text(
              context.l10n.text('No provider-attributed calls are available.'),
            )
          : SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                columns: [
                  DataColumn(label: Text(context.l10n.text('Rank'))),
                  DataColumn(label: Text(context.l10n.text('Provider'))),
                  DataColumn(label: Text(context.l10n.text('Sample'))),
                  DataColumn(label: Text(context.l10n.text('Win rate'))),
                  DataColumn(label: Text(context.l10n.text('Total pips'))),
                ],
                rows: [
                  for (final indexed in ranked.indexed)
                    DataRow(
                      cells: [
                        DataCell(Text('#${indexed.$1 + 1}')),
                        DataCell(Text(indexed.$2.label)),
                        DataCell(
                          Text(
                            indexed.$2.evaluated < 10
                                ? '${indexed.$2.evaluated} (${context.l10n.text('provisional')})'
                                : '${indexed.$2.evaluated}',
                          ),
                        ),
                        DataCell(
                          Text('${indexed.$2.winRate.toStringAsFixed(1)}%'),
                        ),
                        DataCell(Text(indexed.$2.totalPips.toStringAsFixed(1))),
                      ],
                    ),
                ],
              ),
            ),
    );
  }
}

class _RecentCompleted extends StatelessWidget {
  const _RecentCompleted({required this.repository, required this.calls});

  final TradingRepository repository;
  final List<TradingSignalItem> calls;

  @override
  Widget build(BuildContext context) => _Panel(
    title: 'Recent completed calls',
    child: calls.isEmpty
        ? Text(context.l10n.text('No completed calls in this period.'))
        : Column(
            children: [
              for (final call in calls)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    child: Text(call.direction == 'Buy' ? 'B' : 'S'),
                  ),
                  title: Text(call.tradingPair),
                  subtitle: Text(
                    '${context.l10n.text(call.progressLabel)} · ${call.pipsResult ?? 0} pips',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => TradeCallDetailScreen(
                        signal: call,
                        repository: repository,
                      ),
                    ),
                  ),
                ),
            ],
          ),
  );
}

class _QualityNotice extends StatelessWidget {
  const _QualityNotice({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) => Material(
    color: Theme.of(context).colorScheme.errorContainer,
    borderRadius: BorderRadius.circular(16),
    child: ListTile(
      leading: const Icon(Icons.warning_amber_rounded),
      title: Text(context.l10n.text('Data-quality notice')),
      subtitle: Text(
        context.l10n.text(
          '{count} legacy calls have no provider identifier and are excluded from provider comparison.',
          {'count': count},
        ),
      ),
    ),
  );
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text(title),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    ),
  );
}

class _StatisticsError extends StatelessWidget {
  const _StatisticsError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 42),
        const SizedBox(height: 10),
        Text(context.l10n.text('Unable to load trade-call statistics.')),
        const SizedBox(height: 10),
        FilledButton.icon(
          onPressed: onRetry,
          icon: const Icon(Icons.refresh),
          label: Text(context.l10n.text('Try again')),
        ),
      ],
    ),
  );
}

String _duration(Duration? value) {
  if (value == null) return 'N/A';
  if (value.inDays > 0) return '${value.inDays}d ${value.inHours % 24}h';
  if (value.inHours > 0) return '${value.inHours}h ${value.inMinutes % 60}m';
  return '${value.inMinutes}m';
}
