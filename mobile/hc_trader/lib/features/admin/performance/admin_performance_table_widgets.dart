part of 'admin_performance_screen.dart';

class _MobilePerformanceList extends StatelessWidget {
  const _MobilePerformanceList({
    required this.controller,
    required this.onOpen,
    required this.onReview,
    required this.canReview,
  });
  final AdminPerformanceController controller;
  final ValueChanged<AdminTraderPerformance> onOpen;
  final ValueChanged<AdminTraderPerformance> onReview;
  final bool canReview;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      for (final record in controller.pageRecords)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: _MobileTraderCard(
            record: record,
            selected: controller.selectedIds.contains(record.id),
            onSelected: (value) => controller.toggleSelection(record.id, value),
            onOpen: () => onOpen(record),
            onReview: () => onReview(record),
            canReview: canReview,
          ),
        ),
      _PaginationFooter(controller: controller),
    ],
  );
}

class _MobileTraderCard extends StatefulWidget {
  const _MobileTraderCard({
    required this.record,
    required this.selected,
    required this.onSelected,
    required this.onOpen,
    required this.onReview,
    required this.canReview,
  });
  final AdminTraderPerformance record;
  final bool selected;
  final ValueChanged<bool> onSelected;
  final VoidCallback onOpen;
  final VoidCallback onReview;
  final bool canReview;
  @override
  State<_MobileTraderCard> createState() => _MobileTraderCardState();
}

class _MobileTraderCardState extends State<_MobileTraderCard> {
  bool expanded = false;
  @override
  Widget build(BuildContext context) {
    final record = widget.record;
    return AnalyticsSurface(
      child: Column(
        children: [
          Row(
            children: [
              Checkbox(
                value: widget.selected,
                onChanged: (value) => widget.onSelected(value ?? false),
              ),
              CircleAvatar(child: Text(_initials(record.name))),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      record.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      record.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              _StatusChip(
                label: record.reviewStatus,
                tone: _reviewTone(record.reviewStatus),
              ),
            ],
          ),
          const Divider(height: 22),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  'Net profit',
                  formatSignedMoney(record.netProfit),
                  record.netProfit >= 0 ? analyticsPositive : analyticsNegative,
                ),
              ),
              Expanded(
                child: _MiniMetric(
                  'Return',
                  formatPercent(record.returnPercent),
                ),
              ),
              Expanded(
                child: _MiniMetric(
                  'Score',
                  record.overallScore.toStringAsFixed(0),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _MiniMetric('Win rate', formatPercent(record.winRate)),
              ),
              Expanded(
                child: _MiniMetric(
                  'Drawdown',
                  formatPercent(record.maxDrawdownPercent),
                  record.riskLevel == 'high' ? analyticsNegative : null,
                ),
              ),
              Expanded(child: _MiniMetric('Trades', '${record.totalTrades}')),
            ],
          ),
          if (expanded) ...[
            const Divider(height: 24),
            Row(
              children: [
                Expanded(
                  child: _MiniMetric('Profit factor', record.profitFactorLabel),
                ),
                Expanded(
                  child: _MiniMetric(
                    'Expectancy',
                    formatSignedMoney(record.expectancy),
                  ),
                ),
                Expanded(
                  child: _MiniMetric(
                    'Last activity',
                    record.lastTradeAt == null
                        ? 'N/A'
                        : DateFormat('d MMM').format(record.lastTradeAt!),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            alignment: WrapAlignment.end,
            spacing: 4,
            runSpacing: 4,
            children: [
              TextButton.icon(
                onPressed: () => setState(() => expanded = !expanded),
                icon: Icon(expanded ? Icons.expand_less : Icons.expand_more),
                label: Text(context.l10n.text(expanded ? 'Less' : 'More')),
              ),
              if (widget.canReview)
                IconButton(
                  tooltip: context.l10n.text('Review'),
                  onPressed: widget.onReview,
                  icon: const Icon(Icons.fact_check_outlined),
                ),
              FilledButton.tonal(
                onPressed: widget.onOpen,
                child: Text(context.l10n.text('View details')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DesktopPerformanceTable extends StatelessWidget {
  const _DesktopPerformanceTable({
    required this.controller,
    required this.onOpen,
    required this.onReview,
    required this.onCompare,
    required this.canReview,
  });
  final AdminPerformanceController controller;
  final ValueChanged<AdminTraderPerformance> onOpen;
  final ValueChanged<AdminTraderPerformance> onReview;
  final VoidCallback onCompare;
  final bool canReview;
  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    padding: EdgeInsets.zero,
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 10, 8),
          child: Row(
            children: [
              Expanded(
                child: _SectionTitle(
                  title: 'Trader performance',
                  subtitle: '{count} matching trader(s)',
                  values: {'count': controller.filteredRecords.length},
                ),
              ),
              if (controller.selectedIds.isNotEmpty)
                Text(
                  '${controller.selectedIds.length} ${context.l10n.text('selected')}',
                ),
              const SizedBox(width: 8),
              FilledButton.tonalIcon(
                onPressed:
                    controller.selectedIds.length >= 2 &&
                        controller.selectedIds.length <= 5
                    ? onCompare
                    : null,
                icon: const Icon(Icons.compare_arrows),
                label: Text(context.l10n.text('Compare')),
              ),
              PopupMenuButton<AdminPerformanceDensity>(
                tooltip: context.l10n.text('Table density'),
                onSelected: controller.setDensity,
                itemBuilder: (context) => [
                  PopupMenuItem(
                    value: AdminPerformanceDensity.comfortable,
                    child: Text(context.l10n.text('Comfortable')),
                  ),
                  PopupMenuItem(
                    value: AdminPerformanceDensity.compact,
                    child: Text(context.l10n.text('Compact')),
                  ),
                ],
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Scrollbar(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              showCheckboxColumn: true,
              dataRowMinHeight:
                  controller.density == AdminPerformanceDensity.compact
                  ? 46
                  : 58,
              dataRowMaxHeight:
                  controller.density == AdminPerformanceDensity.compact
                  ? 50
                  : 64,
              columns: [
                DataColumn(
                  label: Text(context.l10n.text('Rank')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.ranking),
                ),
                DataColumn(label: Text(context.l10n.text('Trader'))),
                DataColumn(label: Text(context.l10n.text('Status'))),
                DataColumn(
                  label: Text(context.l10n.text('Net profit')),
                  onSort: (_, ascending) => controller.setSort(
                    ascending
                        ? AdminPerformanceSort.profitLow
                        : AdminPerformanceSort.profitHigh,
                  ),
                ),
                DataColumn(
                  label: Text(context.l10n.text('Return')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.returnHigh),
                ),
                DataColumn(
                  label: Text(context.l10n.text('Win rate')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.winRateHigh),
                ),
                DataColumn(
                  label: Text(context.l10n.text('Trades')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.mostActive),
                ),
                DataColumn(label: Text(context.l10n.text('Profit factor'))),
                DataColumn(
                  label: Text(context.l10n.text('Maximum drawdown')),
                  onSort: (_, ascending) => controller.setSort(
                    ascending
                        ? AdminPerformanceSort.drawdownLow
                        : AdminPerformanceSort.drawdownHigh,
                  ),
                ),
                DataColumn(
                  label: Text(context.l10n.text('Score')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.scoreHigh),
                ),
                DataColumn(
                  label: Text(context.l10n.text('Last trade')),
                  onSort: (_, _) =>
                      controller.setSort(AdminPerformanceSort.recent),
                ),
                DataColumn(label: Text(context.l10n.text('Actions'))),
              ],
              rows: [
                for (final record in controller.pageRecords)
                  DataRow(
                    selected: controller.selectedIds.contains(record.id),
                    onSelectChanged: (value) =>
                        controller.toggleSelection(record.id, value ?? false),
                    cells: [
                      DataCell(Text('#${record.rank}')),
                      DataCell(
                        SizedBox(
                          width: 190,
                          child: ListTile(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              radius: 18,
                              child: Text(_initials(record.name)),
                            ),
                            title: Text(
                              record.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            subtitle: Text(
                              record.email,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                        onTap: () => onOpen(record),
                      ),
                      DataCell(
                        _StatusChip(
                          label: record.reviewStatus,
                          tone: _reviewTone(record.reviewStatus),
                        ),
                      ),
                      DataCell(
                        Text(
                          formatSignedMoney(record.netProfit),
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: record.netProfit >= 0
                                ? analyticsPositive
                                : analyticsNegative,
                          ),
                        ),
                      ),
                      DataCell(Text(formatPercent(record.returnPercent))),
                      DataCell(Text(formatPercent(record.winRate))),
                      DataCell(Text('${record.totalTrades}')),
                      DataCell(Text(record.profitFactorLabel)),
                      DataCell(
                        Text(
                          formatPercent(record.maxDrawdownPercent),
                          style: TextStyle(
                            color: record.riskLevel == 'high'
                                ? analyticsNegative
                                : null,
                          ),
                        ),
                      ),
                      DataCell(Text(record.overallScore.toStringAsFixed(0))),
                      DataCell(
                        Text(
                          record.lastTradeAt == null
                              ? context.l10n.text('N/A')
                              : DateFormat(
                                  'd MMM yyyy',
                                ).format(record.lastTradeAt!),
                        ),
                      ),
                      DataCell(
                        PopupMenuButton<String>(
                          tooltip: context.l10n.text('Actions'),
                          onSelected: (value) {
                            if (value == 'view') {
                              onOpen(record);
                            } else if (canReview) {
                              onReview(record);
                            }
                          },
                          itemBuilder: (context) => [
                            PopupMenuItem(
                              value: 'view',
                              child: Text(context.l10n.text('View details')),
                            ),
                            if (canReview)
                              PopupMenuItem(
                                value: 'review',
                                child: Text(context.l10n.text('Review')),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
        _PaginationFooter(controller: controller),
      ],
    ),
  );
}

class _PaginationFooter extends StatelessWidget {
  const _PaginationFooter({required this.controller});
  final AdminPerformanceController controller;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(10),
    child: Wrap(
      alignment: WrapAlignment.end,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 8,
      runSpacing: 4,
      children: [
        Text(context.l10n.text('Rows per page')),
        DropdownButton<int>(
          value: controller.rowsPerPage,
          items: [
            for (final value in const [10, 20, 50])
              DropdownMenuItem(value: value, child: Text('$value')),
          ],
          onChanged: (value) {
            if (value != null) controller.setRowsPerPage(value);
          },
        ),
        Text(
          context.l10n.text('Page {page} of {lastPage}', {
            'page': controller.page + 1,
            'lastPage': controller.pageCount,
          }),
        ),
        IconButton(
          onPressed: controller.page > 0
              ? () => controller.setPage(controller.page - 1)
              : null,
          icon: const Icon(Icons.chevron_left),
        ),
        IconButton(
          onPressed: controller.page + 1 < controller.pageCount
              ? () => controller.setPage(controller.page + 1)
              : null,
          icon: const Icon(Icons.chevron_right),
        ),
      ],
    ),
  );
}
