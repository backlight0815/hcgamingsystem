part of 'admin_performance_screen.dart';

class _PerformanceFilterSheet extends StatefulWidget {
  const _PerformanceFilterSheet({required this.initial});
  final AdminPerformanceFilter initial;
  @override
  State<_PerformanceFilterSheet> createState() =>
      _PerformanceFilterSheetState();
}

class _PerformanceFilterSheetState extends State<_PerformanceFilterSheet> {
  late AdminPerformanceFilter value = widget.initial;
  late final TextEditingController minTrades = TextEditingController(
    text: value.minimumTrades > 0 ? '${value.minimumTrades}' : '',
  );
  late final TextEditingController minProfit = TextEditingController(
    text: value.minimumProfit?.toString() ?? '',
  );
  late final TextEditingController maxDrawdown = TextEditingController(
    text: value.maximumDrawdown?.toString() ?? '',
  );
  @override
  void dispose() {
    minTrades.dispose();
    minProfit.dispose();
    maxDrawdown.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      0,
      20,
      MediaQuery.viewInsetsOf(context).bottom + 20,
    ),
    child: SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text('Performance filters'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<AdminPerformancePeriod>(
            initialValue: value.period,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.l10n.text('Performance period'),
            ),
            items: [
              for (final item in AdminPerformancePeriod.values)
                DropdownMenuItem(
                  value: item,
                  child: Text(
                    context.l10n.text(_periodLabel(item)),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (next) =>
                setState(() => value = value.copyWith(period: next)),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: value.dataSource,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.l10n.text('Data source'),
            ),
            items: [
              for (final item in const [
                'all',
                'mt5_sync',
                'manual',
                'excel_import',
              ])
                DropdownMenuItem(
                  value: item,
                  child: Text(
                    context.l10n.text(_sourceLabel(item)),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (next) =>
                setState(() => value = value.copyWith(dataSource: next)),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<AdminPerformanceProfitFilter>(
                  initialValue: value.profit,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Profit or loss'),
                  ),
                  items: [
                    for (final item in AdminPerformanceProfitFilter.values)
                      DropdownMenuItem(
                        value: item,
                        child: Text(
                          context.l10n.text(_profitLabel(item)),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  onChanged: (next) =>
                      setState(() => value = value.copyWith(profit: next)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<AdminPerformanceRiskFilter>(
                  initialValue: value.risk,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Risk level'),
                  ),
                  items: [
                    for (final item in AdminPerformanceRiskFilter.values)
                      DropdownMenuItem(
                        value: item,
                        child: Text(
                          context.l10n.text(_riskFilterLabel(item)),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  onChanged: (next) =>
                      setState(() => value = value.copyWith(risk: next)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<AdminPerformanceSort>(
            initialValue: value.sort,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.l10n.text('Sort by'),
            ),
            items: [
              for (final item in AdminPerformanceSort.values)
                DropdownMenuItem(
                  value: item,
                  child: Text(
                    context.l10n.text(_sortLabel(item)),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (next) =>
                setState(() => value = value.copyWith(sort: next)),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: minTrades,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Minimum trades'),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: minProfit,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Minimum profit'),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: maxDrawdown,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: context.l10n.text('Maximum drawdown %'),
            ),
          ),
          const SizedBox(height: 20),
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 12,
            runSpacing: 8,
            children: [
              TextButton(
                onPressed: () => setState(() {
                  value = const AdminPerformanceFilter();
                  minTrades.clear();
                  minProfit.clear();
                  maxDrawdown.clear();
                }),
                child: Text(context.l10n.text('Clear all')),
              ),
              FilledButton.icon(
                onPressed: () {
                  final parsedTrades = int.tryParse(minTrades.text) ?? 0;
                  final parsedProfit = double.tryParse(minProfit.text);
                  final parsedDrawdown = double.tryParse(maxDrawdown.text);
                  final updated = value.copyWith(
                    minimumTrades: parsedTrades < 0 ? 0 : parsedTrades,
                    minimumProfit: parsedProfit,
                    maximumDrawdown:
                        parsedDrawdown != null && parsedDrawdown >= 0
                        ? parsedDrawdown
                        : null,
                    clearMinimumProfit:
                        minProfit.text.trim().isEmpty || parsedProfit == null,
                    clearMaximumDrawdown:
                        maxDrawdown.text.trim().isEmpty ||
                        parsedDrawdown == null ||
                        parsedDrawdown < 0,
                  );
                  Navigator.pop(context, updated);
                },
                icon: const Icon(Icons.check),
                label: Text(context.l10n.text('Apply filters')),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}
