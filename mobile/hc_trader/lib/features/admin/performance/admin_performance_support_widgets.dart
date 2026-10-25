part of 'admin_performance_screen.dart';

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({
    required this.title,
    required this.subtitle,
    this.values = const {},
  });
  final String title;
  final String subtitle;
  final Map<String, Object?> values;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(title, values),
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 2),
      Text(
        context.l10n.text(subtitle, values),
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      ),
    ],
  );
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric(this.label, this.value, [this.color]);
  final String label;
  final String value;
  final Color? color;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelSmall,
      ),
      const SizedBox(height: 3),
      Text(
        context.l10n.text(value),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(fontWeight: FontWeight.w800, color: color),
      ),
    ],
  );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.tone});
  final String label;
  final Color tone;
  @override
  Widget build(BuildContext context) => Tooltip(
    message: context.l10n.text('Current review status: {status}', {
      'status': context.l10n.text(_pretty(label)),
    }),
    child: DecoratedBox(
      decoration: BoxDecoration(
        color: tone.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: tone.withValues(alpha: .3)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        child: Text(
          context.l10n.text(_pretty(label)),
          style: TextStyle(
            color: tone,
            fontSize: 11,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    ),
  );
}

class _LegendDot extends StatelessWidget {
  const _LegendDot(this.label, this.color);
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 10,
        height: 10,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
      const SizedBox(width: 6),
      Text(context.l10n.text(label)),
    ],
  );
}

class _ChartEmpty extends StatelessWidget {
  const _ChartEmpty();
  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          Icons.query_stats,
          color: Theme.of(context).colorScheme.outline,
          size: 38,
        ),
        const SizedBox(height: 8),
        Text(context.l10n.text('No chart data for this period.')),
      ],
    ),
  );
}

class _PerformanceSkeleton extends StatelessWidget {
  const _PerformanceSkeleton();
  @override
  Widget build(BuildContext context) => Column(
    children: [
      for (var row = 0; row < 3; row++)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            children: [
              for (var column = 0; column < 3; column++)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: Container(
                      height: row == 2 ? 240 : 120,
                      decoration: BoxDecoration(
                        color: Theme.of(
                          context,
                        ).colorScheme.surfaceContainerHigh,
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
    ],
  );
}

class _PerformanceErrorState extends StatelessWidget {
  const _PerformanceErrorState({required this.error, required this.onRetry});
  final Object? error;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 44),
      child: Column(
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 52,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 12),
          Text(
            context.l10n.text('Failed to load performance'),
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 6),
          Text(context.l10n.text('$error'), textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

class _PerformanceEmptyState extends StatelessWidget {
  const _PerformanceEmptyState({
    required this.title,
    required this.message,
    required this.onAction,
    this.actionLabel = 'Refresh',
  });
  final String title;
  final String message;
  final VoidCallback onAction;
  final String actionLabel;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: [
          Icon(
            Icons.query_stats,
            size: 50,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(height: 12),
          Text(
            context.l10n.text(title),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text(context.l10n.text(message), textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.tonal(
            onPressed: onAction,
            child: Text(context.l10n.text(actionLabel)),
          ),
        ],
      ),
    ),
  );
}

class _PerformanceAccessDenied extends StatelessWidget {
  const _PerformanceAccessDenied();
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.lock_outline, size: 54),
          const SizedBox(height: 12),
          Text(
            context.l10n.text(
              'You do not have permission to view trader performance.',
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
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
Color _reviewTone(String value) {
  final status = value.toLowerCase();
  if (status.contains('approved') || status.contains('funded')) {
    return analyticsPositive;
  }
  if (status.contains('suspend') || status.contains('reject')) {
    return analyticsNegative;
  }
  if (status.contains('review') ||
      status.contains('correction') ||
      status.contains('pending')) {
    return analyticsWarning;
  }
  return _analyticsNeutral;
}

Color _insightColor(String kind) => switch (kind) {
  'positive' => analyticsPositive,
  'negative' => analyticsNegative,
  'warning' => analyticsWarning,
  _ => analyticsInformation,
};
IconData _insightIcon(String kind) => switch (kind) {
  'positive' => Icons.trending_up,
  'negative' => Icons.warning_amber,
  'warning' => Icons.fact_check_outlined,
  _ => Icons.info_outline,
};
String _periodLabel(AdminPerformancePeriod value) => switch (value) {
  AdminPerformancePeriod.sevenDays => 'Last 7 days',
  AdminPerformancePeriod.thirtyDays => 'Last 30 days',
  AdminPerformancePeriod.ninetyDays => 'Last 90 days',
  AdminPerformancePeriod.year => 'This year',
  AdminPerformancePeriod.all => 'All time',
};
String _sourceLabel(String value) => switch (value) {
  'mt5_sync' => 'MT5 synced data',
  'manual' => 'Manual trading journal',
  'excel_import' => 'Imported Excel data',
  _ => 'Combined data',
};
String _profitLabel(AdminPerformanceProfitFilter value) => switch (value) {
  AdminPerformanceProfitFilter.profitable => 'Profitable only',
  AdminPerformanceProfitFilter.losing => 'Losing only',
  AdminPerformanceProfitFilter.breakeven => 'Breakeven only',
  _ => 'All outcomes',
};
String _riskFilterLabel(AdminPerformanceRiskFilter value) => switch (value) {
  AdminPerformanceRiskFilter.controlled => 'Controlled risk',
  AdminPerformanceRiskFilter.attention => 'Requires attention',
  AdminPerformanceRiskFilter.high => 'High risk',
  _ => 'All risk levels',
};
String _sortLabel(AdminPerformanceSort value) => switch (value) {
  AdminPerformanceSort.profitHigh => 'Highest profit',
  AdminPerformanceSort.profitLow => 'Lowest profit',
  AdminPerformanceSort.returnHigh => 'Highest return',
  AdminPerformanceSort.winRateHigh => 'Highest win rate',
  AdminPerformanceSort.drawdownLow => 'Lowest drawdown',
  AdminPerformanceSort.drawdownHigh => 'Highest drawdown',
  AdminPerformanceSort.scoreHigh => 'Highest overall score',
  AdminPerformanceSort.mostActive => 'Most active',
  AdminPerformanceSort.leastActive => 'Least active',
  AdminPerformanceSort.recent => 'Most recent',
  _ => 'Ranking score',
};
String _kpiHelp(String label) => switch (label) {
  'Total net profit' =>
    'Sum of eligible closed-trade results. Deposits and withdrawals are excluded.',
  'Average return' =>
    'Average net trading result divided by available starting capital.',
  'Average win rate' =>
    'Average wins divided by wins and losses; breakeven trades are excluded.',
  'Average drawdown' =>
    'Average maximum peak-to-trough decline. Lower is better.',
  'Accounts requiring review' =>
    'Accounts with pending, correction, rejected, suspended, or under-review states.',
  _ => 'Calculated from the filtered trader records.',
};
