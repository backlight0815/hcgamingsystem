import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../../../../core/localization/app_localizations.dart';
import '../../../../core/storage/local_app_storage.dart';
import '../../../../models/app_user.dart';
import '../../../../shared/analytics/analytics_widgets.dart';
import '../../../../shared/app_back_button.dart';
import '../application/average_price_controller.dart';
import '../domain/average_price_models.dart';
import 'average_price_widgets.dart';

class AveragePriceScreen extends StatefulWidget {
  const AveragePriceScreen({
    super.key,
    required this.user,
    required this.storage,
  });

  static const routeName = '/trading-toolkit/average-price';

  final AppUser user;
  final LocalAppStorage storage;

  @override
  State<AveragePriceScreen> createState() => _AveragePriceScreenState();
}

class _AveragePriceScreenState extends State<AveragePriceScreen> {
  late final AveragePriceController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AveragePriceController(
      userId: widget.user.uid,
      storage: widget.storage,
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Average Price & Break-Even')),
        actions: [
          IconButton(
            key: const ValueKey('average-price-history'),
            tooltip: context.l10n.text('Saved calculations'),
            onPressed: _showHistory,
            icon: const Icon(Icons.history),
          ),
          IconButton(
            key: const ValueKey('copy-average-summary'),
            tooltip: context.l10n.text('Copy calculation summary'),
            onPressed: _copySummary,
            icon: const Icon(Icons.ios_share_outlined),
          ),
          IconButton(
            key: const ValueKey('save-average-setup'),
            tooltip: context.l10n.text('Save calculation'),
            onPressed: _saveSetup,
            icon: const Icon(Icons.save_outlined),
          ),
        ],
      ),
      body: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) => LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth;
            final padding = width < 600 ? 12.0 : 20.0;
            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(padding, 10, padding, 32),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1500),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _PageHeader(controller: _controller),
                      const SizedBox(height: 14),
                      _ControlBar(controller: _controller),
                      const SizedBox(height: 16),
                      if (width >= 1120)
                        _ThreeColumnWorkspace(controller: _controller)
                      else if (width >= 720)
                        _TwoColumnWorkspace(controller: _controller)
                      else
                        _MobileWorkspace(controller: _controller),
                      const SizedBox(height: 18),
                      _BottomActions(
                        onSave: _saveSetup,
                        onReset: _confirmReset,
                        onCopy: _copySummary,
                      ),
                      const SizedBox(height: 18),
                      _EducationalNotice(),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _saveSetup() async {
    var draftName = _controller.selectedSetupId == null
        ? _defaultSetupName()
        : _controller.history
                  .where((setup) => setup.id == _controller.selectedSetupId)
                  .firstOrNull
                  ?.name ??
              '';
    final name = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Save calculation')),
        content: TextFormField(
          key: const ValueKey('setup-name-field'),
          initialValue: draftName,
          autofocus: true,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(
            labelText: context.l10n.text('Setup name'),
            hintText: context.l10n.text('Example: XAUUSD London plan'),
          ),
          onChanged: (value) => draftName = value,
          onFieldSubmitted: (value) {
            if (value.trim().isNotEmpty) Navigator.pop(context, value.trim());
          },
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            key: const ValueKey('confirm-save-setup'),
            onPressed: () {
              if (draftName.trim().isNotEmpty) {
                Navigator.pop(context, draftName.trim());
              }
            },
            child: Text(context.l10n.text('Save')),
          ),
        ],
      ),
    );
    if (name == null || !mounted) return;
    await _controller.saveSetup(name);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(context.l10n.text('Calculation saved.'))),
    );
  }

  Future<void> _confirmReset() async {
    if (!_controller.hasMeaningfulData) {
      _controller.reset();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Reset calculation?')),
        content: Text(
          context.l10n.text(
            'This clears the current draft. Saved calculations are not deleted.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            key: const ValueKey('confirm-reset-average-price'),
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.l10n.text('Reset')),
          ),
        ],
      ),
    );
    if (confirmed == true) _controller.reset();
  }

  Future<void> _copySummary() async {
    final result = _controller.result;
    final precision = _controller.pricePrecision;
    final lines = [
      context.l10n.text('Average Price & Break-Even'),
      if (_controller.symbol.trim().isNotEmpty)
        _controller.symbol.trim().toUpperCase(),
      '${context.l10n.text('Direction')}: ${context.l10n.text(_controller.direction == PositionDirection.long ? 'Buy / Long' : 'Sell / Short')}',
      '${context.l10n.text('Weighted Average Entry')}: ${AveragePriceFormat.price(result.weightedAverageEntry, precision)}',
      '${context.l10n.text('Gross Break-Even')}: ${AveragePriceFormat.price(result.grossBreakEven, precision)}',
      '${context.l10n.text('Fee-Adjusted Break-Even')}: ${AveragePriceFormat.price(result.feeAdjustedBreakEven, precision)}',
      '${context.l10n.text('Suggested Safe Break-Even')}: ${AveragePriceFormat.price(result.safeBreakEven, precision)}',
      '${context.l10n.text('Total Quantity')}: ${AveragePriceFormat.flexible(result.totalQuantity)}',
      '${context.l10n.text('Risk-to-Reward Ratio')}: ${AveragePriceFormat.ratio(result.netRiskReward ?? result.grossRiskReward)}',
      '',
      context.l10n.text(
        'Estimates only. This tool does not provide financial advice or guarantee outcomes.',
      ),
    ];
    await Clipboard.setData(ClipboardData(text: lines.join('\n')));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(context.l10n.text('Summary copied.'))),
    );
  }

  Future<void> _showHistory() async {
    var query = '';
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final items = _controller.searchHistory(query);
          return SafeArea(
            child: FractionallySizedBox(
              heightFactor: .82,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      context.l10n.text('Saved calculations'),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      key: const ValueKey('history-search-field'),
                      decoration: InputDecoration(
                        hintText: context.l10n.text(
                          'Search by setup name or symbol',
                        ),
                        prefixIcon: const Icon(Icons.search),
                      ),
                      onChanged: (value) => setSheetState(() => query = value),
                    ),
                    const SizedBox(height: 12),
                    Expanded(
                      child: items.isEmpty
                          ? Center(
                              child: Text(
                                context.l10n.text(
                                  'No saved calculations found.',
                                ),
                              ),
                            )
                          : ListView.separated(
                              itemCount: items.length,
                              separatorBuilder: (_, _) => const Divider(),
                              itemBuilder: (context, index) {
                                final setup = items[index];
                                return ListTile(
                                  key: ValueKey('history-${setup.id}'),
                                  contentPadding: EdgeInsets.zero,
                                  leading: CircleAvatar(
                                    child: Text(
                                      setup.symbol.isEmpty
                                          ? setup.name.characters.first
                                                .toUpperCase()
                                          : setup.symbol.characters.first
                                                .toUpperCase(),
                                    ),
                                  ),
                                  title: Text(setup.name),
                                  subtitle: Text(
                                    [
                                      if (setup.symbol.isNotEmpty) setup.symbol,
                                      DateFormat.yMMMd(
                                        Localizations.localeOf(
                                          context,
                                        ).toLanguageTag(),
                                      ).format(setup.updatedAt),
                                    ].join(' · '),
                                  ),
                                  onTap: () {
                                    _controller.loadSetup(setup);
                                    Navigator.pop(sheetContext);
                                  },
                                  trailing: PopupMenuButton<String>(
                                    onSelected: (action) async {
                                      if (action == 'duplicate') {
                                        await _controller.duplicateSetup(
                                          setup.id,
                                          context.l10n.text('copy'),
                                        );
                                      } else if (action == 'delete') {
                                        await _controller.deleteSetup(setup.id);
                                      }
                                      setSheetState(() {});
                                    },
                                    itemBuilder: (context) => [
                                      PopupMenuItem(
                                        value: 'duplicate',
                                        child: Text(
                                          context.l10n.text('Duplicate'),
                                        ),
                                      ),
                                      PopupMenuItem(
                                        value: 'delete',
                                        child: Text(
                                          context.l10n.text('Delete'),
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  String _defaultSetupName() {
    final symbol = _controller.symbol.trim().toUpperCase();
    return symbol.isEmpty
        ? context.l10n.text('Average price setup')
        : '$symbol ${context.l10n.text('setup')}';
  }
}

class _PageHeader extends StatelessWidget {
  const _PageHeader({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(15),
          ),
          child: Icon(
            Icons.price_check_outlined,
            color: Theme.of(context).colorScheme.onPrimaryContainer,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text('Average Price & Break-Even'),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  letterSpacing: -.4,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.text(
                  'Calculate weighted entry, break-even, risk/reward, partial targets and what-if orders.',
                ),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ControlBar extends StatelessWidget {
  const _ControlBar({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      padding: const EdgeInsets.all(12),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final narrow = constraints.maxWidth < 650;
          final direction = DirectionSelector(
            value: controller.direction,
            onChanged: controller.setDirection,
          );
          final modes = Wrap(
            spacing: 7,
            runSpacing: 7,
            alignment: WrapAlignment.end,
            children: [
              ChoiceChip(
                key: const ValueKey('simple-mode'),
                label: Text(context.l10n.text('Simple Mode')),
                avatar: const Icon(Icons.speed_outlined, size: 18),
                selected: controller.mode == AveragePriceMode.simple,
                onSelected: (_) => controller.setMode(AveragePriceMode.simple),
              ),
              ChoiceChip(
                key: const ValueKey('advanced-mode'),
                label: Text(context.l10n.text('Advanced Mode')),
                avatar: const Icon(Icons.tune_outlined, size: 18),
                selected: controller.mode == AveragePriceMode.advanced,
                onSelected: (_) =>
                    controller.setMode(AveragePriceMode.advanced),
              ),
            ],
          );
          if (narrow) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [direction, const SizedBox(height: 10), modes],
            );
          }
          return Row(
            children: [
              SizedBox(width: 390, child: direction),
              const SizedBox(width: 12),
              Expanded(child: modes),
            ],
          );
        },
      ),
    );
  }
}

class _ThreeColumnWorkspace extends StatelessWidget {
  const _ThreeColumnWorkspace({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 31,
          child: Column(
            children: [
              InstrumentSettingsPanel(controller: controller),
              const SizedBox(height: 14),
              _OrdersSection(controller: controller),
            ],
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          flex: 36,
          child: Column(
            children: [
              AveragePriceHeroCard(controller: controller),
              const SizedBox(height: 14),
              PriceLevelMap(controller: controller),
              const SizedBox(height: 14),
              WhatIfOrderSimulator(controller: controller),
            ],
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          flex: 33,
          child: Column(
            children: [
              RiskRewardSummaryCard(controller: controller),
              const SizedBox(height: 14),
              TakeProfitAllocationCard(controller: controller),
              const SizedBox(height: 14),
              BreakEvenGuidanceCard(controller: controller),
              const SizedBox(height: 14),
              _WarningsCard(controller: controller),
            ],
          ),
        ),
      ],
    );
  }
}

class _TwoColumnWorkspace extends StatelessWidget {
  const _TwoColumnWorkspace({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            children: [
              InstrumentSettingsPanel(controller: controller),
              const SizedBox(height: 14),
              _OrdersSection(controller: controller),
              const SizedBox(height: 14),
              WhatIfOrderSimulator(controller: controller),
            ],
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            children: [
              AveragePriceHeroCard(controller: controller),
              const SizedBox(height: 14),
              PriceLevelMap(controller: controller),
              const SizedBox(height: 14),
              RiskRewardSummaryCard(controller: controller),
              const SizedBox(height: 14),
              TakeProfitAllocationCard(controller: controller),
              const SizedBox(height: 14),
              BreakEvenGuidanceCard(controller: controller),
              const SizedBox(height: 14),
              _WarningsCard(controller: controller),
            ],
          ),
        ),
      ],
    );
  }
}

class _MobileWorkspace extends StatelessWidget {
  const _MobileWorkspace({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AveragePriceHeroCard(controller: controller),
        const SizedBox(height: 14),
        InstrumentSettingsPanel(controller: controller),
        const SizedBox(height: 14),
        _OrdersSection(controller: controller),
        const SizedBox(height: 14),
        PriceLevelMap(controller: controller),
        const SizedBox(height: 14),
        RiskRewardSummaryCard(controller: controller),
        const SizedBox(height: 14),
        TakeProfitAllocationCard(controller: controller),
        const SizedBox(height: 14),
        BreakEvenGuidanceCard(controller: controller),
        const SizedBox(height: 14),
        WhatIfOrderSimulator(controller: controller),
        const SizedBox(height: 14),
        _WarningsCard(controller: controller),
      ],
    );
  }
}

class _OrdersSection extends StatelessWidget {
  const _OrdersSection({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Position Orders'),
            subtitle: context.l10n.text(
              '{count} order row(s) · zero or negative quantities are excluded',
              {'count': controller.orders.length},
            ),
            icon: Icons.layers_outlined,
            action: IconButton(
              key: const ValueKey('add-order'),
              tooltip: context.l10n.text('Add Order'),
              onPressed: controller.addOrder,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ),
          const SizedBox(height: 12),
          if (controller.orders.isEmpty)
            ValidationMessage(
              message: context.l10n.text(
                'No orders. Add an order to begin calculating.',
              ),
              tone: averageInformation,
              icon: Icons.info_outline,
            )
          else
            ReorderableListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              buildDefaultDragHandles: false,
              itemCount: controller.orders.length,
              onReorder: controller.reorderOrder,
              proxyDecorator: (child, index, animation) => Material(
                elevation: 5,
                borderRadius: BorderRadius.circular(18),
                child: child,
              ),
              itemBuilder: (context, index) {
                final order = controller.orders[index];
                return Padding(
                  key: ValueKey(order.id),
                  padding: EdgeInsets.only(
                    bottom: index == controller.orders.length - 1 ? 0 : 10,
                  ),
                  child: PositionOrderCard(
                    order: order,
                    index: index,
                    formEpoch: controller.formEpoch,
                    dragHandle: ReorderableDragStartListener(
                      index: index,
                      child: Tooltip(
                        message: context.l10n.text('Reorder order'),
                        child: const Padding(
                          padding: EdgeInsets.all(8),
                          child: Icon(Icons.drag_indicator),
                        ),
                      ),
                    ),
                    onPriceChanged: (value) =>
                        controller.updateOrder(order.id, price: value),
                    onQuantityChanged: (value) =>
                        controller.updateOrder(order.id, quantity: value),
                    onFeeChanged: (value) =>
                        controller.updateOrder(order.id, fee: value),
                    onNoteChanged: (value) =>
                        controller.updateOrder(order.id, note: value),
                    onDuplicate: () => controller.duplicateOrder(order.id),
                    onDelete: () => controller.deleteOrder(order.id),
                  ),
                );
              },
            ),
          const SizedBox(height: 12),
          FilledButton.tonalIcon(
            key: const ValueKey('add-order-button'),
            onPressed: controller.addOrder,
            icon: const Icon(Icons.add),
            label: Text(context.l10n.text('Add Order')),
          ),
          const SizedBox(height: 12),
          AveragePriceTextField(
            key: ValueKey('${controller.formEpoch}-setup-notes'),
            initialValue: controller.notes,
            label: context.l10n.text('Setup notes (optional)'),
            numeric: false,
            maxLines: 2,
            onChanged: controller.setNotes,
          ),
        ],
      ),
    );
  }
}

class _WarningsCard extends StatelessWidget {
  const _WarningsCard({required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final warnings = controller.result.warnings.toList();
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Calculation checks'),
            subtitle: warnings.isEmpty
                ? context.l10n.text('No calculation warnings detected.')
                : context.l10n.text('{count} item(s) need attention', {
                    'count': warnings.length,
                  }),
            icon: warnings.isEmpty
                ? Icons.check_circle_outline
                : Icons.rule_outlined,
          ),
          if (warnings.isNotEmpty) ...[
            const SizedBox(height: 12),
            for (final indexed in warnings.indexed) ...[
              ValidationMessage(
                key: ValueKey('warning-${indexed.$2.name}'),
                message: averagePriceWarningText(context, indexed.$2),
                tone:
                    indexed.$2 ==
                        AveragePriceWarningCode.invalidContractSpecification
                    ? averageInformation
                    : averageWarning,
                icon:
                    indexed.$2 ==
                        AveragePriceWarningCode.invalidContractSpecification
                    ? Icons.info_outline
                    : Icons.warning_amber_rounded,
              ),
              if (indexed.$1 != warnings.length - 1) const SizedBox(height: 8),
            ],
          ],
        ],
      ),
    );
  }
}

class _BottomActions extends StatelessWidget {
  const _BottomActions({
    required this.onSave,
    required this.onReset,
    required this.onCopy,
  });

  final VoidCallback onSave;
  final VoidCallback onReset;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      alignment: WrapAlignment.end,
      spacing: 8,
      runSpacing: 8,
      children: [
        OutlinedButton.icon(
          key: const ValueKey('reset-average-price'),
          onPressed: onReset,
          icon: const Icon(Icons.restart_alt),
          label: Text(context.l10n.text('Reset')),
        ),
        OutlinedButton.icon(
          onPressed: onCopy,
          icon: const Icon(Icons.content_copy_outlined),
          label: Text(context.l10n.text('Copy summary')),
        ),
        FilledButton.icon(
          onPressed: onSave,
          icon: const Icon(Icons.save_outlined),
          label: Text(context.l10n.text('Save calculation')),
        ),
      ],
    );
  }
}

class _EducationalNotice extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.text('Educational notice'),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainer,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Theme.of(context).colorScheme.outlineVariant,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              Icons.school_outlined,
              size: 21,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                context.l10n.text(
                  'Calculations are estimates based on the information entered. Actual execution prices, spreads, slippage, commissions, financing costs, and contract specifications may differ. This tool does not provide financial advice or guarantee trading outcomes.',
                ),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
