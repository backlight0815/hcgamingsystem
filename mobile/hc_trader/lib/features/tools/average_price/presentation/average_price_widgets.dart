import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../core/localization/app_localizations.dart';
import '../../../../models/mt5_import_models.dart';
import '../../../../shared/analytics/analytics_widgets.dart';
import '../application/average_price_controller.dart';
import '../application/average_price_form_models.dart';
import '../domain/average_price_models.dart';

const averagePositive = Color(0xFF087A63);
const averageNegative = Color(0xFFB23B55);
const averageWarning = Color(0xFFB87309);
const averageInformation = Color(0xFF3568A8);
const averageNeutral = Color(0xFF687681);

class AveragePriceFormat {
  const AveragePriceFormat._();

  static String price(Mt5Decimal? value, int precision) {
    if (value == null) return '—';
    final safePrecision = precision.clamp(0, 8);
    return value.toDouble().toStringAsFixed(safePrecision);
  }

  static String flexible(Mt5Decimal? value, {int precision = 4}) {
    if (value == null) return '—';
    final text = value.toDouble().toStringAsFixed(precision.clamp(0, 8));
    return text.contains('.')
        ? text.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '')
        : text;
  }

  static String money(
    Mt5Decimal? value,
    String currency, {
    bool signed = false,
  }) {
    if (value == null) return '—';
    final amount = value.toDouble();
    final prefix = signed && amount > 0 ? '+' : '';
    return '${currency.trim().isEmpty ? '' : '${currency.trim().toUpperCase()} '}$prefix${amount.toStringAsFixed(2)}';
  }

  static String ratio(Mt5Decimal? value) =>
      value == null ? '—' : '1:${flexible(value, precision: 2)}';
}

class DirectionSelector extends StatelessWidget {
  const DirectionSelector({
    super.key,
    required this.value,
    required this.onChanged,
  });

  final PositionDirection value;
  final ValueChanged<PositionDirection> onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      container: true,
      label: context.l10n.text('Position direction'),
      child: Container(
        padding: const EdgeInsets.all(5),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Expanded(
              child: _DirectionButton(
                key: const ValueKey('direction-long'),
                selected: value == PositionDirection.long,
                label: context.l10n.text('Buy / Long'),
                icon: Icons.trending_up,
                color: averagePositive,
                onTap: () => onChanged(PositionDirection.long),
              ),
            ),
            const SizedBox(width: 6),
            Expanded(
              child: _DirectionButton(
                key: const ValueKey('direction-short'),
                selected: value == PositionDirection.short,
                label: context.l10n.text('Sell / Short'),
                icon: Icons.trending_down,
                color: averageNegative,
                onTap: () => onChanged(PositionDirection.short),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DirectionButton extends StatelessWidget {
  const _DirectionButton({
    super.key,
    required this.selected,
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final bool selected;
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? color.withValues(
              alpha: Theme.of(context).brightness == Brightness.dark
                  ? .24
                  : .13,
            )
          : Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 20, color: selected ? color : null),
                const SizedBox(width: 7),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: selected ? color : null,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class AveragePriceHeroCard extends StatelessWidget {
  const AveragePriceHeroCard({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final result = controller.result;
    final precision = controller.pricePrecision;
    final tone = controller.direction == PositionDirection.long
        ? averagePositive
        : averageInformation;
    return Semantics(
      container: true,
      label: context.l10n.text('Weighted Average Entry: {value}', {
        'value': AveragePriceFormat.price(
          result.weightedAverageEntry,
          precision,
        ),
      }),
      child: Container(
        key: const ValueKey('average-price-hero'),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Color.alphaBlend(
            tone.withValues(
              alpha: Theme.of(context).brightness == Brightness.dark
                  ? .15
                  : .08,
            ),
            Theme.of(context).colorScheme.surfaceContainerLow,
          ),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: tone.withValues(alpha: .26)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Weighted Average Entry'),
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                CalculationStatusChip(status: result.status),
              ],
            ),
            const SizedBox(height: 8),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: Text(
                AveragePriceFormat.price(
                  result.weightedAverageEntry,
                  precision,
                ),
                key: ValueKey(result.weightedAverageEntry?.toString()),
                style: Theme.of(context).textTheme.displaySmall?.copyWith(
                  color: tone,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -.9,
                ),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              controller.symbol.trim().isEmpty
                  ? context.l10n.text(
                      'Enter valid position orders to calculate.',
                    )
                  : controller.symbol.trim().toUpperCase(),
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 18),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 620 ? 3 : 2;
                final width =
                    (constraints.maxWidth - (columns - 1) * 10) / columns;
                return Wrap(
                  spacing: 10,
                  runSpacing: 12,
                  children: [
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Fee-Adjusted Break-Even'),
                      value: AveragePriceFormat.price(
                        result.feeAdjustedBreakEven,
                        precision,
                      ),
                      icon: Icons.price_check_outlined,
                    ),
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Suggested Safe Break-Even'),
                      value: AveragePriceFormat.price(
                        result.safeBreakEven,
                        precision,
                      ),
                      icon: Icons.shield_outlined,
                    ),
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Total Quantity'),
                      value: AveragePriceFormat.flexible(result.totalQuantity),
                      icon: Icons.layers_outlined,
                    ),
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Total Exposure'),
                      value: result.totalExposure == null
                          ? '—'
                          : AveragePriceFormat.money(
                              result.totalExposure,
                              controller.accountCurrency,
                            ),
                      icon: Icons.account_balance_wallet_outlined,
                    ),
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Current estimated P/L'),
                      value: result.currentNetPnl == null
                          ? '—'
                          : AveragePriceFormat.money(
                              result.currentNetPnl,
                              controller.accountCurrency,
                              signed: true,
                            ),
                      icon: Icons.monitor_heart_outlined,
                      tone: _pnlTone(result.currentNetPnl),
                    ),
                    _HeroMetric(
                      width: width,
                      label: context.l10n.text('Risk-to-Reward Ratio'),
                      value: AveragePriceFormat.ratio(
                        result.netRiskReward ?? result.grossRiskReward,
                      ),
                      icon: Icons.balance_outlined,
                    ),
                  ],
                );
              },
            ),
            if (controller.isAdvanced &&
                !result.monetaryEstimatesAvailable) ...[
              const SizedBox(height: 12),
              ValidationMessage(
                message: context.l10n.text(
                  'Additional contract or tick information is required for monetary estimates.',
                ),
                tone: averageInformation,
                icon: Icons.info_outline,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Color? _pnlTone(Mt5Decimal? value) {
    if (value == null || value.isZero) return averageNeutral;
    return value.isPositive ? averagePositive : averageNegative;
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.width,
    required this.label,
    required this.value,
    required this.icon,
    this.tone,
  });

  final double width;
  final String label;
  final String value;
  final IconData icon;
  final Color? tone;

  @override
  Widget build(BuildContext context) {
    final color = tone ?? Theme.of(context).colorScheme.onSurface;
    return SizedBox(
      width: width,
      child: Semantics(
        label: '$label, $value',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 16, color: color),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    label,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: color,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class CalculationStatusChip extends StatelessWidget {
  const CalculationStatusChip({super.key, required this.status});

  final BreakEvenStatus status;

  @override
  Widget build(BuildContext context) {
    final (label, icon, tone) = switch (status) {
      BreakEvenStatus.unavailable => (
        context.l10n.text('Awaiting price'),
        Icons.hourglass_empty,
        averageNeutral,
      ),
      BreakEvenStatus.invalidSetup => (
        context.l10n.text('Invalid setup'),
        Icons.error_outline,
        averageNegative,
      ),
      BreakEvenStatus.belowBreakEven => (
        context.l10n.text('Below break-even'),
        Icons.south_east,
        averageNegative,
      ),
      BreakEvenStatus.approachingBreakEven => (
        context.l10n.text('Approaching break-even'),
        Icons.near_me_outlined,
        averageWarning,
      ),
      BreakEvenStatus.atGrossBreakEven => (
        context.l10n.text('At gross break-even'),
        Icons.horizontal_rule,
        averageInformation,
      ),
      BreakEvenStatus.aboveFeeAdjustedBreakEven => (
        context.l10n.text('Above fee-adjusted break-even'),
        Icons.price_check,
        averageInformation,
      ),
      BreakEvenStatus.aboveSafeBreakEven => (
        context.l10n.text('Above safe break-even'),
        Icons.verified_outlined,
        averagePositive,
      ),
      BreakEvenStatus.targetReached => (
        context.l10n.text('Target reached'),
        Icons.flag_outlined,
        averagePositive,
      ),
    };
    return Tooltip(
      message: label,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: tone.withValues(alpha: .12),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 15, color: tone),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: tone,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class PositionOrderCard extends StatelessWidget {
  const PositionOrderCard({
    super.key,
    required this.order,
    required this.index,
    required this.formEpoch,
    required this.onPriceChanged,
    required this.onQuantityChanged,
    required this.onFeeChanged,
    required this.onNoteChanged,
    required this.onDuplicate,
    required this.onDelete,
    this.dragHandle,
  });

  final PositionOrderDraft order;
  final int index;
  final int formEpoch;
  final ValueChanged<String> onPriceChanged;
  final ValueChanged<String> onQuantityChanged;
  final ValueChanged<String> onFeeChanged;
  final ValueChanged<String> onNoteChanged;
  final VoidCallback onDuplicate;
  final VoidCallback onDelete;
  final Widget? dragHandle;

  @override
  Widget build(BuildContext context) {
    return Card(
      key: ValueKey('order-card-${order.id}'),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                dragHandle ??
                    Icon(
                      Icons.drag_indicator,
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    context.l10n.text('Order {number}', {'number': index + 1}),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Duplicate order'),
                  onPressed: onDuplicate,
                  icon: const Icon(Icons.content_copy_outlined),
                ),
                IconButton(
                  tooltip: context.l10n.text('Delete order'),
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
            const SizedBox(height: 10),
            ResponsiveFieldGrid(
              children: [
                AveragePriceTextField(
                  key: ValueKey('$formEpoch-${order.id}-price'),
                  fieldKey: ValueKey('order-price-${order.id}'),
                  initialValue: order.price,
                  label: context.l10n.text('Entry price'),
                  icon: Icons.show_chart,
                  onChanged: onPriceChanged,
                ),
                AveragePriceTextField(
                  key: ValueKey('$formEpoch-${order.id}-quantity'),
                  fieldKey: ValueKey('order-quantity-${order.id}'),
                  initialValue: order.quantity,
                  label: context.l10n.text('Quantity / lots'),
                  icon: Icons.layers_outlined,
                  onChanged: onQuantityChanged,
                ),
                AveragePriceTextField(
                  key: ValueKey('$formEpoch-${order.id}-fee'),
                  initialValue: order.fee,
                  label: context.l10n.text('Entry fee (optional)'),
                  icon: Icons.receipt_long_outlined,
                  onChanged: onFeeChanged,
                ),
                AveragePriceTextField(
                  key: ValueKey('$formEpoch-${order.id}-note'),
                  initialValue: order.note,
                  label: context.l10n.text('Note (optional)'),
                  icon: Icons.notes_outlined,
                  numeric: false,
                  onChanged: onNoteChanged,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class InstrumentSettingsPanel extends StatelessWidget {
  const InstrumentSettingsPanel({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      padding: EdgeInsets.zero,
      child: ExpansionTile(
        key: const ValueKey('instrument-settings-panel'),
        initiallyExpanded: false,
        leading: const Icon(Icons.tune_outlined),
        title: Text(
          context.l10n.text('Instrument Settings'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(
          controller.symbol.trim().isEmpty
              ? context.l10n.text('Optional contract and market details')
              : '${controller.symbol.toUpperCase()} · ${controller.pricePrecision} ${context.l10n.text('decimals')}',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 4, 16, 18),
        children: [
          ResponsiveFieldGrid(
            children: [
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-symbol'),
                initialValue: controller.symbol,
                label: context.l10n.text('Instrument / symbol'),
                icon: Icons.candlestick_chart_outlined,
                numeric: false,
                onChanged: controller.setSymbol,
              ),
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-market-price'),
                fieldKey: const ValueKey('current-market-price'),
                initialValue: controller.currentMarketPrice,
                label: context.l10n.text('Current market price'),
                icon: Icons.sensors_outlined,
                onChanged: controller.setCurrentMarketPrice,
              ),
              DropdownButtonFormField<AssetCategory>(
                initialValue: controller.assetCategory,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Asset category'),
                  prefixIcon: const Icon(Icons.category_outlined),
                ),
                items: [
                  for (final category in AssetCategory.values)
                    DropdownMenuItem(
                      value: category,
                      child: Text(context.l10n.text(_assetLabel(category))),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) controller.setAssetCategory(value);
                },
              ),
              DropdownButtonFormField<int>(
                initialValue: controller.pricePrecision,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Price decimal precision'),
                  prefixIcon: const Icon(Icons.numbers_outlined),
                ),
                items: [
                  for (var precision = 0; precision <= 8; precision++)
                    DropdownMenuItem(
                      value: precision,
                      child: Text('$precision'),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) controller.setPricePrecision(value);
                },
              ),
              DropdownButtonFormField<QuantityType>(
                initialValue: controller.quantityType,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Quantity type'),
                  prefixIcon: const Icon(Icons.straighten_outlined),
                ),
                items: [
                  for (final type in QuantityType.values)
                    DropdownMenuItem(
                      value: type,
                      child: Text(context.l10n.text(_quantityLabel(type))),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) controller.setQuantityType(value);
                },
              ),
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-currency'),
                initialValue: controller.accountCurrency,
                label: context.l10n.text('Account currency'),
                icon: Icons.currency_exchange,
                numeric: false,
                onChanged: controller.setAccountCurrency,
              ),
            ],
          ),
          if (controller.isAdvanced) ...[
            const SizedBox(height: 16),
            Divider(color: Theme.of(context).colorScheme.outlineVariant),
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                context.l10n.text('Contract specifications'),
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            const SizedBox(height: 10),
            ResponsiveFieldGrid(
              children: [
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-contract'),
                  initialValue: controller.contractSize,
                  label: context.l10n.text('Contract size'),
                  onChanged: controller.setContractSize,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-tick-size'),
                  initialValue: controller.tickSize,
                  label: context.l10n.text('Tick size'),
                  onChanged: controller.setTickSize,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-tick-value'),
                  initialValue: controller.tickValue,
                  label: context.l10n.text('Tick value'),
                  onChanged: controller.setTickValue,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-pip-size'),
                  initialValue: controller.pipSize,
                  label: context.l10n.text('Pip size'),
                  onChanged: controller.setPipSize,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                context.l10n.text('Estimated trading costs'),
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            const SizedBox(height: 10),
            ResponsiveFieldGrid(
              children: [
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-entry-commission'),
                  initialValue: controller.entryCommissionPercent,
                  label: context.l10n.text('Entry commission %'),
                  onChanged: controller.setEntryCommission,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-exit-commission'),
                  initialValue: controller.exitCommissionPercent,
                  label: context.l10n.text('Exit commission %'),
                  onChanged: controller.setExitCommission,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-spread'),
                  initialValue: controller.spread,
                  label: context.l10n.text('Spread'),
                  onChanged: controller.setSpread,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-slippage'),
                  initialValue: controller.slippage,
                  label: context.l10n.text('Expected slippage'),
                  onChanged: controller.setSlippage,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-swap'),
                  initialValue: controller.swapCost,
                  label: context.l10n.text('Swap / holding cost'),
                  onChanged: controller.setSwapCost,
                ),
                AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-other-costs'),
                  initialValue: controller.otherCosts,
                  label: context.l10n.text('Other estimated costs'),
                  onChanged: controller.setOtherCosts,
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  static String _assetLabel(AssetCategory category) => switch (category) {
    AssetCategory.forex => 'Forex',
    AssetCategory.commodities => 'Gold and commodities',
    AssetCategory.indices => 'Indices',
    AssetCategory.stocks => 'Stocks',
    AssetCategory.cryptocurrency => 'Cryptocurrency',
    AssetCategory.futures => 'Futures',
    AssetCategory.cfd => 'CFD',
    AssetCategory.custom => 'Custom',
  };

  static String _quantityLabel(QuantityType type) => switch (type) {
    QuantityType.units => 'Units',
    QuantityType.lots => 'Lots',
    QuantityType.contracts => 'Contracts',
    QuantityType.shares => 'Shares',
    QuantityType.coins => 'Coins',
  };
}

class RiskRewardSummaryCard extends StatelessWidget {
  const RiskRewardSummaryCard({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final result = controller.result;
    final precision = controller.pricePrecision;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Stop-Loss & Risk/Reward'),
            subtitle: context.l10n.text(
              'Plan levels without treating estimates as guaranteed outcomes.',
            ),
            icon: Icons.balance_outlined,
          ),
          const SizedBox(height: 14),
          AveragePriceTextField(
            key: ValueKey('${controller.formEpoch}-stop-loss'),
            fieldKey: const ValueKey('stop-loss-field'),
            initialValue: controller.stopLoss,
            label: context.l10n.text('Stop-Loss'),
            icon: Icons.gpp_bad_outlined,
            onChanged: controller.setStopLoss,
          ),
          const SizedBox(height: 12),
          Text(
            context.l10n.text('Target risk-to-reward'),
            style: Theme.of(
              context,
            ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              for (final value in const ['1', '1.5', '2', '3'])
                ChoiceChip(
                  label: Text('1:$value'),
                  selected: controller.targetRiskReward == value,
                  onSelected: (_) => controller.setTargetRiskReward(value),
                ),
              SizedBox(
                width: 118,
                child: AveragePriceTextField(
                  key: ValueKey('${controller.formEpoch}-target-rr'),
                  initialValue: controller.targetRiskReward,
                  label: context.l10n.text('Custom R:R'),
                  onChanged: controller.setTargetRiskReward,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _MetricRows(
            rows: [
              (
                context.l10n.text('Risk distance'),
                AveragePriceFormat.price(result.riskDistance, precision),
              ),
              (
                context.l10n.text('Reward distance'),
                AveragePriceFormat.price(result.rewardDistance, precision),
              ),
              (
                context.l10n.text('Gross R:R'),
                AveragePriceFormat.ratio(result.grossRiskReward),
              ),
              if (result.netRiskReward != null &&
                  result.netRiskReward != result.grossRiskReward)
                (
                  context.l10n.text('Net R:R'),
                  AveragePriceFormat.ratio(result.netRiskReward),
                ),
              (
                context.l10n.text('Required TP for Target R:R'),
                AveragePriceFormat.price(result.requiredTakeProfit, precision),
              ),
              if (result.estimatedProfitAtTarget != null)
                (
                  context.l10n.text('Estimated profit at take-profit'),
                  AveragePriceFormat.money(
                    result.estimatedProfitAtTarget,
                    controller.accountCurrency,
                    signed: true,
                  ),
                ),
              if (result.estimatedLossAtStop != null)
                (
                  context.l10n.text('Estimated loss at stop-loss'),
                  AveragePriceFormat.money(
                    result.estimatedLossAtStop,
                    controller.accountCurrency,
                    signed: true,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class TakeProfitAllocationCard extends StatelessWidget {
  const TakeProfitAllocationCard({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final result = controller.result;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Take-Profit Targets'),
            subtitle: context.l10n.text(
              'Allocate partial exits; totals above 100% are flagged.',
            ),
            icon: Icons.flag_outlined,
            action: IconButton(
              key: const ValueKey('add-take-profit'),
              tooltip: context.l10n.text('Add take-profit'),
              onPressed: controller.addTakeProfit,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ),
          const SizedBox(height: 12),
          if (controller.takeProfits.isEmpty)
            Text(context.l10n.text('No take-profit targets added.'))
          else
            for (final indexed in controller.takeProfits.indexed) ...[
              _TakeProfitRow(
                key: ValueKey('tp-row-${indexed.$2.id}'),
                target: indexed.$2,
                index: indexed.$1,
                formEpoch: controller.formEpoch,
                calculation: result.takeProfitResults
                    .where((item) => item.target.id == indexed.$2.id)
                    .firstOrNull,
                precision: controller.pricePrecision,
                currency: controller.accountCurrency,
                onPriceChanged: (value) =>
                    controller.updateTakeProfit(indexed.$2.id, price: value),
                onPercentageChanged: (value) => controller.updateTakeProfit(
                  indexed.$2.id,
                  percentage: value,
                ),
                onDelete: () => controller.deleteTakeProfit(indexed.$2.id),
              ),
              if (indexed.$1 != controller.takeProfits.length - 1)
                const SizedBox(height: 10),
            ],
          const SizedBox(height: 12),
          _MetricRows(
            rows: [
              (
                context.l10n.text('Allocated'),
                '${AveragePriceFormat.flexible(result.allocatedPercentage)}%',
              ),
              (
                context.l10n.text('Remaining open position'),
                '${AveragePriceFormat.flexible(result.remainingPercentage)}%',
              ),
              if (result.blendedNetProfit != null)
                (
                  context.l10n.text('Blended estimated net profit'),
                  AveragePriceFormat.money(
                    result.blendedNetProfit,
                    controller.accountCurrency,
                    signed: true,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TakeProfitRow extends StatelessWidget {
  const _TakeProfitRow({
    super.key,
    required this.target,
    required this.index,
    required this.formEpoch,
    required this.calculation,
    required this.precision,
    required this.currency,
    required this.onPriceChanged,
    required this.onPercentageChanged,
    required this.onDelete,
  });

  final TakeProfitDraft target;
  final int index;
  final int formEpoch;
  final TakeProfitCalculationResult? calculation;
  final int precision;
  final String currency;
  final ValueChanged<String> onPriceChanged;
  final ValueChanged<String> onPercentageChanged;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'TP${index + 1}',
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
              IconButton(
                tooltip: context.l10n.text('Delete take-profit'),
                onPressed: onDelete,
                icon: const Icon(Icons.delete_outline),
              ),
            ],
          ),
          ResponsiveFieldGrid(
            children: [
              AveragePriceTextField(
                key: ValueKey('$formEpoch-${target.id}-price'),
                fieldKey: ValueKey('tp-price-${target.id}'),
                initialValue: target.price,
                label: context.l10n.text('Target price'),
                onChanged: onPriceChanged,
              ),
              AveragePriceTextField(
                key: ValueKey('$formEpoch-${target.id}-allocation'),
                fieldKey: ValueKey('tp-allocation-${target.id}'),
                initialValue: target.percentage,
                label: context.l10n.text('Close allocation %'),
                onChanged: onPercentageChanged,
              ),
            ],
          ),
          if (calculation != null) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 12,
              runSpacing: 5,
              children: [
                Text(
                  '${context.l10n.text('Distance')}: ${AveragePriceFormat.price(calculation!.distance, precision)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Text(
                  '${context.l10n.text('Distance %')}: ${AveragePriceFormat.flexible(calculation!.percentageDistance)}%',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Text(
                  '${context.l10n.text('R:R')}: ${AveragePriceFormat.ratio(calculation!.grossRiskReward)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                if (calculation!.netProfit != null)
                  Text(
                    '${context.l10n.text('Net P/L')}: ${AveragePriceFormat.money(calculation!.netProfit, currency, signed: true)}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: calculation!.netProfit!.isNegative
                          ? averageNegative
                          : averagePositive,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class BreakEvenGuidanceCard extends StatelessWidget {
  const BreakEvenGuidanceCard({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final result = controller.result;
    final precision = controller.pricePrecision;
    final bufferLabel =
        '${controller.bufferValue} ${context.l10n.text(_bufferLabel(controller.bufferType))}';
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Break-Even Guidance'),
            subtitle: context.l10n.text(
              'Neutral guidance based on your inputs.',
            ),
            icon: Icons.lightbulb_outline,
          ),
          const SizedBox(height: 12),
          ResponsiveFieldGrid(
            children: [
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-buffer'),
                initialValue: controller.bufferValue,
                label: context.l10n.text('Safety buffer'),
                onChanged: controller.setBufferValue,
              ),
              DropdownButtonFormField<SafetyBufferType>(
                initialValue: controller.bufferType,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Buffer format'),
                ),
                items: [
                  for (final type in SafetyBufferType.values)
                    DropdownMenuItem(
                      value: type,
                      child: Text(context.l10n.text(_bufferLabel(type))),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) controller.setBufferType(value);
                },
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (!result.canCalculate)
            ValidationMessage(
              message: context.l10n.text(
                'Enter at least one positive price and quantity to generate guidance.',
              ),
              tone: averageInformation,
              icon: Icons.info_outline,
            )
          else ...[
            Text(
              context.l10n.text('Your weighted average entry is {average}.', {
                'average': AveragePriceFormat.price(
                  result.weightedAverageEntry,
                  precision,
                ),
              }),
            ),
            const SizedBox(height: 7),
            Text(
              context.l10n.text(
                'The estimated fee-adjusted break-even price is {price}.',
                {
                  'price': AveragePriceFormat.price(
                    result.feeAdjustedBreakEven,
                    precision,
                  ),
                },
              ),
            ),
            const SizedBox(height: 7),
            Text(
              context.l10n.text(
                'With a {buffer} safety buffer, the suggested safe break-even level is {price}.',
                {
                  'buffer': bufferLabel,
                  'price': AveragePriceFormat.price(
                    result.safeBreakEven,
                    precision,
                  ),
                },
              ),
            ),
            const SizedBox(height: 7),
            Text(
              context.l10n.text(
                'Moving a stop to this level before price trades sufficiently beyond it may increase the chance of an early exit.',
              ),
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }

  static String _bufferLabel(SafetyBufferType type) => switch (type) {
    SafetyBufferType.points => 'Price points',
    SafetyBufferType.percentage => 'Percentage',
    SafetyBufferType.pips => 'Pips',
    SafetyBufferType.ticks => 'Ticks',
  };
}

class WhatIfOrderSimulator extends StatelessWidget {
  const WhatIfOrderSimulator({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final simulation = controller.simulationResult;
    final precision = controller.pricePrecision;
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('What If I Add Another Order?'),
            subtitle: context.l10n.text(
              'Preview the effect before applying it to the active position.',
            ),
            icon: Icons.science_outlined,
          ),
          const SizedBox(height: 14),
          ResponsiveFieldGrid(
            children: [
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-simulation-price'),
                fieldKey: const ValueKey('simulation-price'),
                initialValue: controller.simulationDraft.price,
                label: context.l10n.text('Proposed entry price'),
                onChanged: (value) => controller.updateSimulation(price: value),
              ),
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-simulation-quantity'),
                fieldKey: const ValueKey('simulation-quantity'),
                initialValue: controller.simulationDraft.quantity,
                label: context.l10n.text('Proposed quantity'),
                onChanged: (value) =>
                    controller.updateSimulation(quantity: value),
              ),
              AveragePriceTextField(
                key: ValueKey('${controller.formEpoch}-simulation-fee'),
                fieldKey: const ValueKey('simulation-fee'),
                initialValue: controller.simulationDraft.fee,
                label: context.l10n.text('Proposed fee'),
                onChanged: (value) => controller.updateSimulation(fee: value),
              ),
            ],
          ),
          if (simulation != null) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Theme.of(
                  context,
                ).colorScheme.primaryContainer.withValues(alpha: .42),
                borderRadius: BorderRadius.circular(16),
              ),
              child: _MetricRows(
                rows: [
                  (
                    context.l10n.text('Current Average'),
                    AveragePriceFormat.price(
                      simulation.current.weightedAverageEntry,
                      precision,
                    ),
                  ),
                  (
                    context.l10n.text('Projected Average'),
                    AveragePriceFormat.price(
                      simulation.projected.weightedAverageEntry,
                      precision,
                    ),
                  ),
                  (
                    context.l10n.text('Average price movement'),
                    AveragePriceFormat.price(
                      simulation.averageMovement,
                      precision,
                    ),
                  ),
                  (
                    context.l10n.text('New total quantity'),
                    AveragePriceFormat.flexible(
                      simulation.projected.totalQuantity,
                    ),
                  ),
                  (
                    context.l10n.text('New break-even price'),
                    AveragePriceFormat.price(
                      simulation.projected.feeAdjustedBreakEven,
                      precision,
                    ),
                  ),
                  (
                    context.l10n.text('Updated risk at stop-loss'),
                    AveragePriceFormat.price(
                      simulation.projected.riskDistance,
                      precision,
                    ),
                  ),
                  (
                    context.l10n.text('Updated R:R'),
                    AveragePriceFormat.ratio(
                      simulation.projected.netRiskReward ??
                          simulation.projected.grossRiskReward,
                    ),
                  ),
                  (
                    context.l10n.text('Additional capital required'),
                    AveragePriceFormat.money(
                      simulation.additionalCapital,
                      controller.accountCurrency,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            alignment: WrapAlignment.end,
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                key: const ValueKey('reset-simulation'),
                onPressed: controller.resetSimulation,
                icon: const Icon(Icons.refresh),
                label: Text(context.l10n.text('Reset Simulation')),
              ),
              FilledButton.icon(
                key: const ValueKey('apply-simulation'),
                onPressed: simulation == null
                    ? null
                    : controller.applySimulation,
                icon: const Icon(Icons.add_task),
                label: Text(context.l10n.text('Apply to Position')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class PriceLevelMap extends StatelessWidget {
  const PriceLevelMap({super.key, required this.controller});

  final AveragePriceController controller;

  @override
  Widget build(BuildContext context) {
    final levels = _levels(context);
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AnalyticsSectionHeader(
            title: context.l10n.text('Price-Level Map'),
            subtitle: context.l10n.text(
              'Scaled from the lowest to highest entered level.',
            ),
            icon: Icons.stacked_line_chart,
          ),
          const SizedBox(height: 12),
          if (levels.isEmpty)
            SizedBox(
              height: 210,
              child: Center(
                child: Text(
                  context.l10n.text('Add valid price levels to build the map.'),
                  textAlign: TextAlign.center,
                ),
              ),
            )
          else
            _PriceMapCanvas(
              levels: levels,
              precision: controller.pricePrecision,
            ),
        ],
      ),
    );
  }

  List<_PriceLevel> _levels(BuildContext context) {
    final result = controller.result;
    final levels = <_PriceLevel>[];
    void add(String label, Mt5Decimal? value, Color color, IconData icon) {
      if (value == null || !value.isPositive) return;
      levels.add(_PriceLevel(label, value, color, icon));
    }

    add(
      context.l10n.text('Stop-Loss'),
      _decimal(controller.stopLoss),
      averageNegative,
      Icons.gpp_bad_outlined,
    );
    for (final indexed in controller.orders.indexed) {
      add(
        context.l10n.text('Order {number}', {'number': indexed.$1 + 1}),
        _decimal(indexed.$2.price),
        averageInformation,
        Icons.login,
      );
    }
    add(
      context.l10n.text('Weighted Average'),
      result.weightedAverageEntry,
      Theme.of(context).colorScheme.primary,
      Icons.functions,
    );
    add(
      context.l10n.text('Gross Break-Even'),
      result.grossBreakEven,
      averageNeutral,
      Icons.horizontal_rule,
    );
    add(
      context.l10n.text('Fee-Adjusted Break-Even'),
      result.feeAdjustedBreakEven,
      averageWarning,
      Icons.price_check_outlined,
    );
    add(
      context.l10n.text('Suggested Safe Break-Even'),
      result.safeBreakEven,
      averagePositive,
      Icons.shield_outlined,
    );
    add(
      context.l10n.text('Current market price'),
      _decimal(controller.currentMarketPrice),
      Theme.of(context).colorScheme.tertiary,
      Icons.sensors,
    );
    for (final indexed in controller.takeProfits.indexed) {
      add(
        'TP${indexed.$1 + 1}',
        _decimal(indexed.$2.price),
        averagePositive,
        Icons.flag_outlined,
      );
    }
    return levels;
  }

  Mt5Decimal? _decimal(String value) {
    if (value.trim().isEmpty) return null;
    final parsed = Mt5Decimal.tryParse(value);
    return parsed.isPositive ? parsed : null;
  }
}

class _PriceLevel {
  const _PriceLevel(this.label, this.price, this.color, this.icon);

  final String label;
  final Mt5Decimal price;
  final Color color;
  final IconData icon;
}

class _PriceMapCanvas extends StatelessWidget {
  const _PriceMapCanvas({required this.levels, required this.precision});

  final List<_PriceLevel> levels;
  final int precision;

  @override
  Widget build(BuildContext context) {
    const height = 330.0;
    final values = levels.map((level) => level.price.toDouble()).toList();
    var minPrice = values.reduce(math.min);
    var maxPrice = values.reduce(math.max);
    if ((maxPrice - minPrice).abs() < 1e-12) {
      final padding = math.max(1.0, maxPrice.abs() * .01);
      minPrice -= padding;
      maxPrice += padding;
    }
    final groups = <String, List<_PriceLevel>>{};
    for (final level in levels) {
      final key = level.price.rounded(precision.clamp(0, 8)).toString();
      groups.putIfAbsent(key, () => []).add(level);
    }
    final placed = [
      for (final entry in groups.entries)
        _PlacedPriceGroup(
          price: entry.value.first.price,
          levels: entry.value,
          desiredY:
              12 +
              ((maxPrice - entry.value.first.price.toDouble()) /
                      (maxPrice - minPrice)) *
                  (height - 42),
        ),
    ]..sort((left, right) => left.desiredY.compareTo(right.desiredY));
    var nextY = 4.0;
    for (final group in placed) {
      group.displayY = math.max(group.desiredY, nextY);
      nextY = group.displayY + 32;
    }
    if (placed.isNotEmpty && placed.last.displayY > height - 30) {
      var cursor = height - 30;
      for (final group in placed.reversed) {
        group.displayY = math.min(group.displayY, cursor);
        cursor = group.displayY - 32;
      }
    }

    return Semantics(
      label: context.l10n.text(
        'Price map with {count} level(s), from {minimum} to {maximum}.',
        {
          'count': levels.length,
          'minimum': minPrice.toStringAsFixed(precision),
          'maximum': maxPrice.toStringAsFixed(precision),
        },
      ),
      child: Container(
        height: height,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainer,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned(
              top: 12,
              bottom: 12,
              left: 26,
              child: Container(
                width: 2,
                color: Theme.of(context).colorScheme.outlineVariant,
              ),
            ),
            for (final group in placed)
              Positioned(
                top: group.displayY,
                left: 13,
                right: 8,
                child: _PriceLevelMarker(group: group, precision: precision),
              ),
          ],
        ),
      ),
    );
  }
}

class _PlacedPriceGroup {
  _PlacedPriceGroup({
    required this.price,
    required this.levels,
    required this.desiredY,
  }) : displayY = desiredY;

  final Mt5Decimal price;
  final List<_PriceLevel> levels;
  final double desiredY;
  double displayY;
}

class _PriceLevelMarker extends StatelessWidget {
  const _PriceLevelMarker({required this.group, required this.precision});

  final _PlacedPriceGroup group;
  final int precision;

  @override
  Widget build(BuildContext context) {
    final primary = group.levels.first;
    final label = group.levels.map((level) => level.label).join(' · ');
    final price = AveragePriceFormat.price(group.price, precision);
    return Row(
      children: [
        Container(
          width: 28,
          height: 3,
          decoration: BoxDecoration(
            color: primary.color,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Tooltip(
            message: '$label · $price',
            child: Material(
              color: primary.color.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(10),
              child: InkWell(
                borderRadius: BorderRadius.circular(10),
                onTap: () => showModalBottomSheet<void>(
                  context: context,
                  showDragHandle: true,
                  builder: (context) => SafeArea(
                    child: ListTile(
                      leading: Icon(primary.icon, color: primary.color),
                      title: Text(label),
                      subtitle: Text(price),
                    ),
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 5,
                  ),
                  child: Row(
                    children: [
                      Icon(primary.icon, size: 15, color: primary.color),
                      const SizedBox(width: 5),
                      Expanded(
                        child: Text(
                          label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        price,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: primary.color,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class ValidationMessage extends StatelessWidget {
  const ValidationMessage({
    super.key,
    required this.message,
    this.tone = averageWarning,
    this.icon = Icons.warning_amber_rounded,
  });

  final String message;
  final Color tone;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      label: message,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: tone.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: tone.withValues(alpha: .25)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: tone),
            const SizedBox(width: 9),
            Expanded(child: Text(message)),
          ],
        ),
      ),
    );
  }
}

class AveragePriceTextField extends StatelessWidget {
  const AveragePriceTextField({
    super.key,
    this.fieldKey,
    required this.initialValue,
    required this.label,
    required this.onChanged,
    this.icon,
    this.numeric = true,
    this.maxLines = 1,
  });

  final Key? fieldKey;
  final String initialValue;
  final String label;
  final ValueChanged<String> onChanged;
  final IconData? icon;
  final bool numeric;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      textField: true,
      label: label,
      child: TextFormField(
        key: fieldKey,
        initialValue: initialValue,
        keyboardType: numeric
            ? const TextInputType.numberWithOptions(decimal: true, signed: true)
            : maxLines > 1
            ? TextInputType.multiline
            : TextInputType.text,
        textInputAction: maxLines == 1
            ? TextInputAction.next
            : TextInputAction.newline,
        maxLines: maxLines,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: icon == null ? null : Icon(icon),
        ),
        onChanged: onChanged,
      ),
    );
  }
}

class ResponsiveFieldGrid extends StatelessWidget {
  const ResponsiveFieldGrid({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final twoColumns = constraints.maxWidth >= 430;
        final width = twoColumns
            ? (constraints.maxWidth - 10) / 2
            : constraints.maxWidth;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final child in children) SizedBox(width: width, child: child),
          ],
        );
      },
    );
  }
}

class _MetricRows extends StatelessWidget {
  const _MetricRows({required this.rows});

  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final indexed in rows.indexed) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  indexed.$2.$1,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Flexible(
                child: Text(
                  indexed.$2.$2,
                  textAlign: TextAlign.end,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          if (indexed.$1 != rows.length - 1) ...[
            const SizedBox(height: 7),
            Divider(
              height: 1,
              color: Theme.of(context).colorScheme.outlineVariant,
            ),
            const SizedBox(height: 7),
          ],
        ],
      ],
    );
  }
}

String averagePriceWarningText(
  BuildContext context,
  AveragePriceWarningCode code,
) => context.l10n.text(switch (code) {
  AveragePriceWarningCode.noValidOrders =>
    'No valid orders are available for calculation.',
  AveragePriceWarningCode.invalidPrice =>
    'Prices must be greater than zero; invalid rows are excluded.',
  AveragePriceWarningCode.invalidQuantity =>
    'Quantities and allocations must be positive; zero or negative orders are excluded.',
  AveragePriceWarningCode.stopLossDirection =>
    'The stop-loss placement appears inconsistent with the selected direction.',
  AveragePriceWarningCode.stopLossAtAverage =>
    'Stop-loss equals the average entry, so risk-to-reward cannot be calculated.',
  AveragePriceWarningCode.takeProfitDirection =>
    'A take-profit placement appears inconsistent with the selected direction.',
  AveragePriceWarningCode.takeProfitAtAverage =>
    'A take-profit equals the average entry and has no reward distance.',
  AveragePriceWarningCode.allocationAboveHundred =>
    'Take-profit close allocation exceeds 100%.',
  AveragePriceWarningCode.allocationBelowHundred =>
    'Take-profit allocation is below 100%; a portion will remain open.',
  AveragePriceWarningCode.invalidCommission =>
    'Commission percentages must be at least 0% and below 100%.',
  AveragePriceWarningCode.invalidContractSpecification =>
    'Additional contract, tick, pip, or value information is required for this estimate.',
});
