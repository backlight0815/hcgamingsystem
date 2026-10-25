part of 'admin_performance_screen.dart';

class _PerformanceHeader extends StatelessWidget {
  const _PerformanceHeader({
    required this.controller,
    required this.compact,
    required this.showQuickFilters,
    required this.onExport,
    required this.onFilters,
  });

  final AdminPerformanceController controller;
  final bool compact;
  final bool showQuickFilters;
  final VoidCallback onExport;
  final VoidCallback onFilters;

  @override
  Widget build(BuildContext context) {
    final title = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text('Performances'),
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            letterSpacing: -.5,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          context.l10n.text(
            'Monitor results, risk, rankings and accounts requiring review.',
          ),
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          controller.lastUpdated == null
              ? context.l10n.text('Not updated yet')
              : context.l10n.text('Last updated {time}', {
                  'time': DateFormat(
                    'd MMM, HH:mm',
                  ).format(controller.lastUpdated!),
                }),
          style: Theme.of(context).textTheme.labelMedium,
        ),
      ],
    );
    final actions = [
      IconButton.filledTonal(
        tooltip: context.l10n.text('Refresh'),
        onPressed: controller.refreshing ? null : controller.load,
        icon: const Icon(Icons.refresh),
      ),
      IconButton.filledTonal(
        tooltip: context.l10n.text('Filters'),
        onPressed: onFilters,
        icon: Badge(
          isLabelVisible: controller.filter.activeCount > 0,
          label: Text('${controller.filter.activeCount}'),
          child: const Icon(Icons.tune_outlined),
        ),
      ),
      FilledButton.icon(
        onPressed: controller.exporting ? null : onExport,
        icon: controller.exporting
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.download_outlined),
        label: Text(context.l10n.text('Export')),
      ),
    ];
    if (showQuickFilters) {
      actions.insertAll(0, [
        _HeaderQuickFilter<AdminPerformancePeriod>(
          tooltip: context.l10n.text('Performance period'),
          value: controller.filter.period,
          values: AdminPerformancePeriod.values,
          label: (value) => context.l10n.text(_periodLabel(value)),
          onChanged: (value) => unawaited(
            controller.setRemoteFilter(
              controller.filter.copyWith(period: value),
            ),
          ),
        ),
        _HeaderQuickFilter<String>(
          tooltip: context.l10n.text('Data source'),
          value: controller.filter.dataSource,
          values: const ['all', 'mt5_sync', 'manual', 'excel_import'],
          label: (value) => context.l10n.text(_sourceLabel(value)),
          onChanged: (value) => unawaited(
            controller.setRemoteFilter(
              controller.filter.copyWith(dataSource: value),
            ),
          ),
        ),
      ]);
    }
    return compact
        ? Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              title,
              const SizedBox(height: 14),
              Wrap(spacing: 8, runSpacing: 8, children: actions),
            ],
          )
        : Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: title),
              const SizedBox(width: 16),
              Wrap(spacing: 8, children: actions),
            ],
          );
  }
}

class _HeaderQuickFilter<T> extends StatelessWidget {
  const _HeaderQuickFilter({
    required this.tooltip,
    required this.value,
    required this.values,
    required this.label,
    required this.onChanged,
  });

  final String tooltip;
  final T value;
  final List<T> values;
  final String Function(T value) label;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: tooltip,
    child: SizedBox(
      width: 168,
      child: DropdownButtonFormField<T>(
        key: ValueKey(value),
        initialValue: value,
        isExpanded: true,
        isDense: true,
        decoration: const InputDecoration(
          contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        ),
        items: [
          for (final item in values)
            DropdownMenuItem<T>(
              value: item,
              child: Text(
                label(item),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        onChanged: (next) {
          if (next != null) onChanged(next);
        },
      ),
    ),
  );
}

class _PerformanceSearchBar extends StatelessWidget {
  const _PerformanceSearchBar({
    required this.controller,
    required this.model,
    required this.compact,
    required this.onFilters,
  });

  final TextEditingController controller;
  final AdminPerformanceController model;
  final bool compact;
  final VoidCallback onFilters;

  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    padding: const EdgeInsets.all(12),
    child: Column(
      children: [
        Row(
          children: [
            Expanded(
              child: SearchBar(
                controller: controller,
                hintText: context.l10n.text('Search trader name or email'),
                leading: const Icon(Icons.search),
                trailing: [
                  if (controller.text.isNotEmpty)
                    IconButton(
                      tooltip: context.l10n.text('Clear search'),
                      onPressed: () {
                        controller.clear();
                        model.search('');
                      },
                      icon: const Icon(Icons.close),
                    ),
                ],
                onChanged: model.search,
              ),
            ),
            const SizedBox(width: 10),
            if (!compact)
              OutlinedButton.icon(
                onPressed: onFilters,
                icon: const Icon(Icons.tune_outlined),
                label: Text(
                  context.l10n.text('Filters ({count})', {
                    'count': model.filter.activeCount,
                  }),
                ),
              ),
          ],
        ),
        if (model.filter.activeCount > 0) ...[
          const SizedBox(height: 10),
          Align(
            alignment: Alignment.centerLeft,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _FilterChip(
                  label: _periodLabel(model.filter.period),
                  onDeleted:
                      model.filter.period == AdminPerformancePeriod.thirtyDays
                      ? null
                      : () => model.setRemoteFilter(
                          model.filter.copyWith(
                            period: AdminPerformancePeriod.thirtyDays,
                          ),
                        ),
                ),
                if (model.filter.dataSource != 'all')
                  _FilterChip(
                    label: _sourceLabel(model.filter.dataSource),
                    onDeleted: () => model.setRemoteFilter(
                      model.filter.copyWith(dataSource: 'all'),
                    ),
                  ),
                if (model.filter.profit != AdminPerformanceProfitFilter.all)
                  _FilterChip(
                    label: _profitLabel(model.filter.profit),
                    onDeleted: () => model.setLocalFilter(
                      model.filter.copyWith(
                        profit: AdminPerformanceProfitFilter.all,
                      ),
                    ),
                  ),
                if (model.filter.risk != AdminPerformanceRiskFilter.all)
                  _FilterChip(
                    label: _riskFilterLabel(model.filter.risk),
                    onDeleted: () => model.setLocalFilter(
                      model.filter.copyWith(
                        risk: AdminPerformanceRiskFilter.all,
                      ),
                    ),
                  ),
                TextButton(
                  onPressed: model.clearFilters,
                  child: Text(context.l10n.text('Clear all')),
                ),
              ],
            ),
          ),
        ],
      ],
    ),
  );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, this.onDeleted});
  final String label;
  final VoidCallback? onDeleted;
  @override
  Widget build(BuildContext context) =>
      InputChip(label: Text(context.l10n.text(label)), onDeleted: onDeleted);
}

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.summary});
  final AdminPerformanceSummary summary;

  @override
  Widget build(BuildContext context) {
    final cards = [
      _KpiSpec(
        'Total traders',
        '${summary.totalTraders}',
        Icons.groups_outlined,
      ),
      _KpiSpec(
        'Profitable traders',
        '${summary.profitableTraders}',
        Icons.trending_up,
        tone: analyticsPositive,
      ),
      _KpiSpec(
        'Losing traders',
        '${summary.losingTraders}',
        Icons.trending_down,
        tone: analyticsNegative,
      ),
      _KpiSpec(
        'Total net profit',
        formatSignedMoney(summary.netProfit),
        Icons.account_balance_wallet_outlined,
        tone: summary.netProfit >= 0 ? analyticsPositive : analyticsNegative,
        current: summary.netProfit,
        previous: summary.previousNetProfit,
      ),
      _KpiSpec(
        'Average return',
        formatPercent(summary.averageReturn),
        Icons.show_chart,
        tone: summary.averageReturn >= 0
            ? analyticsPositive
            : analyticsNegative,
        current: summary.averageReturn,
        previous: summary.previousAverageReturn,
      ),
      _KpiSpec(
        'Average win rate',
        formatPercent(summary.averageWinRate),
        Icons.track_changes,
        current: summary.averageWinRate,
        previous: summary.previousAverageWinRate,
      ),
      _KpiSpec(
        'Average drawdown',
        formatPercent(summary.averageDrawdown),
        Icons.waterfall_chart,
        tone: summary.averageDrawdown >= 10
            ? analyticsNegative
            : analyticsWarning,
        current: summary.averageDrawdown,
        previous: summary.previousAverageDrawdown,
        higherIsBetter: false,
      ),
      _KpiSpec(
        'Accounts requiring review',
        '${summary.reviewRequired}',
        Icons.fact_check_outlined,
        tone: summary.reviewRequired > 0 ? analyticsWarning : analyticsPositive,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1250
            ? 4
            : constraints.maxWidth >= 760
            ? 3
            : constraints.maxWidth >= 480
            ? 2
            : 1;
        final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (final card in cards)
              SizedBox(
                width: width,
                child: _KpiCard(spec: card),
              ),
          ],
        );
      },
    );
  }
}

class _KpiSpec {
  const _KpiSpec(
    this.label,
    this.value,
    this.icon, {
    this.tone,
    this.current,
    this.previous,
    this.higherIsBetter = true,
  });
  final String label;
  final String value;
  final IconData icon;
  final Color? tone;
  final double? current;
  final double? previous;
  final bool higherIsBetter;
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.spec});
  final _KpiSpec spec;
  @override
  Widget build(BuildContext context) {
    final color = spec.tone ?? Theme.of(context).colorScheme.primary;
    final change = spec.current == null || spec.previous == null
        ? null
        : spec.current! - spec.previous!;
    final improved = change == null
        ? false
        : spec.higherIsBetter
        ? change > 0
        : change < 0;
    return Semantics(
      label: '${context.l10n.text(spec.label)}: ${spec.value}',
      child: AnalyticsSurface(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(9),
                    child: Icon(spec.icon, color: color, size: 22),
                  ),
                ),
                const Spacer(),
                Tooltip(
                  message: context.l10n.text(_kpiHelp(spec.label)),
                  child: const Icon(Icons.info_outline, size: 17),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              spec.value,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 3),
            Text(
              context.l10n.text(spec.label),
              style: Theme.of(context).textTheme.labelLarge,
            ),
            const SizedBox(height: 8),
            if (change == null)
              Text(
                context.l10n.text('Previous period unavailable'),
                style: Theme.of(context).textTheme.bodySmall,
              )
            else
              Row(
                children: [
                  Icon(
                    improved
                        ? Icons.arrow_upward
                        : change == 0
                        ? Icons.remove
                        : Icons.arrow_downward,
                    size: 14,
                    color: improved
                        ? analyticsPositive
                        : change == 0
                        ? Theme.of(context).colorScheme.onSurfaceVariant
                        : analyticsNegative,
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      context.l10n.text('{value} vs previous period', {
                        'value': change.abs().toStringAsFixed(1),
                      }),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
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

class _InsightsPanel extends StatelessWidget {
  const _InsightsPanel({required this.insights});
  final List<AdminPerformanceInsight> insights;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionTitle(
          title: 'Performance insights',
          subtitle:
              'Deterministic findings calculated from the selected period.',
        ),
        const SizedBox(height: 12),
        if (insights.isEmpty)
          Text(
            context.l10n.text(
              'No material risks or changes were detected for this period.',
            ),
          )
        else
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final insight in insights)
                ConstrainedBox(
                  constraints: const BoxConstraints(
                    minWidth: 250,
                    maxWidth: 420,
                  ),
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: _insightColor(
                        insight.kind,
                      ).withValues(alpha: .12),
                      child: Icon(
                        _insightIcon(insight.kind),
                        color: _insightColor(insight.kind),
                      ),
                    ),
                    title: Text(
                      context.l10n.text(insight.title),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      context.l10n.text(insight.message, {
                        'count': insight.count,
                      }),
                    ),
                  ),
                ),
            ],
          ),
      ],
    ),
  );
}
