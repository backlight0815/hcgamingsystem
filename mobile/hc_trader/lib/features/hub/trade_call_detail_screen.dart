import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/localized_value.dart';
import '../../models/trading_signal_item.dart';
import '../../models/trading_signal_lifecycle.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/formatters.dart';

class TradeCallDetailScreen extends StatelessWidget {
  const TradeCallDetailScreen({
    super.key,
    required this.signal,
    this.repository,
  });

  final TradingSignalItem signal;
  final TradingRepository? repository;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Trade Call'))),
      body: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 900;
          final overview = Column(
            children: [
              _MarketOverview(signal: signal),
              const SizedBox(height: 14),
              _TradeSetup(signal: signal),
            ],
          );
          final research = Column(
            children: [
              _TradeThesis(signal: signal),
              const SizedBox(height: 14),
              _Lifecycle(signal: signal),
              if (repository != null) ...[
                const SizedBox(height: 14),
                _PublicSignalTimeline(
                  repository: repository!,
                  signalId: signal.id,
                ),
              ],
            ],
          );
          return SelectionArea(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(
                wide ? 24 : 16,
                16,
                wide ? 24 : 16,
                32,
              ),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1220),
                  child: wide
                      ? Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(flex: 6, child: overview),
                            const SizedBox(width: 16),
                            Expanded(flex: 5, child: research),
                          ],
                        )
                      : Column(
                          children: [
                            overview,
                            const SizedBox(height: 14),
                            research,
                          ],
                        ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _PublicSignalTimeline extends StatelessWidget {
  const _PublicSignalTimeline({
    required this.repository,
    required this.signalId,
  });

  final TradingRepository repository;
  final String signalId;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    return Card(
      key: const ValueKey('public-signal-update-timeline'),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(
              icon: Icons.history_outlined,
              title: context.l10n.text('Update timeline'),
            ),
            const SizedBox(height: 12),
            StreamBuilder<List<TradingSignalUpdate>>(
              stream: repository.tradingSignalUpdatesStream(signalId),
              builder: (context, snapshot) {
                if (!snapshot.hasData && !snapshot.hasError) {
                  return const LinearProgressIndicator();
                }
                if (snapshot.hasError) {
                  return Text(
                    context.l10n.text(
                      'Signal updates are temporarily unavailable.',
                    ),
                  );
                }
                final updates = snapshot.data ?? const [];
                if (updates.isEmpty) {
                  return Text(context.l10n.text('No updates recorded yet.'));
                }
                return Column(
                  children: [
                    for (final update in updates)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: CircleAvatar(
                          child: Icon(
                            _publicUpdateIcon(update.updateType),
                            size: 18,
                          ),
                        ),
                        title: Text(
                          localizedValue(
                            update.titleI18n,
                            language,
                            fallback: context.l10n.text(
                              update.updateType.label,
                            ),
                          ),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (localizedValue(
                              update.messageI18n,
                              language,
                            ).trim().isNotEmpty)
                              Text(
                                localizedValue(update.messageI18n, language),
                              ),
                            Text(
                              context.l10n.text('{from} to {to}', {
                                'from': context.l10n.text(
                                  update.lifecycleFrom.label,
                                ),
                                'to': context.l10n.text(
                                  update.lifecycleTo.label,
                                ),
                              }),
                            ),
                            if (update.createdAt != null)
                              Text(formatDateTime(update.createdAt!)),
                          ],
                        ),
                        trailing: update.attachmentUrls.isEmpty
                            ? null
                            : const Icon(Icons.image_outlined),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

IconData _publicUpdateIcon(SignalUpdateType type) => switch (type) {
  SignalUpdateType.entryTriggered => Icons.play_arrow_rounded,
  SignalUpdateType.updateStopLoss ||
  SignalUpdateType.moveStopLossToBreakEven ||
  SignalUpdateType.updateBreakEven ||
  SignalUpdateType.trailingBreakEven ||
  SignalUpdateType.trailingStopLoss ||
  SignalUpdateType.lockProfit ||
  SignalUpdateType.reduceRisk => Icons.shield_outlined,
  SignalUpdateType.targetHit ||
  SignalUpdateType.partialProfitTaken => Icons.flag_outlined,
  SignalUpdateType.stopLossHit ||
  SignalUpdateType.breakEvenHit ||
  SignalUpdateType.cancelled ||
  SignalUpdateType.expired ||
  SignalUpdateType.invalidated => Icons.report_outlined,
  SignalUpdateType.completed => Icons.task_alt_outlined,
  SignalUpdateType.chart => Icons.add_chart_outlined,
  SignalUpdateType.warning => Icons.warning_amber_outlined,
  _ => Icons.update_outlined,
};

class _MarketOverview extends StatelessWidget {
  const _MarketOverview({required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final direction = signal.direction;
    final tone = _directionColor(context, direction);
    final language = context.l10n.locale.languageCode;
    final title = signal.localizedTitle(language).trim().isEmpty
        ? signal.tradingPair
        : signal.localizedTitle(language);
    return Container(
      key: const ValueKey('trade-call-market-overview'),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            tone.withValues(alpha: .18),
            Theme.of(context).colorScheme.surfaceContainer,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: tone.withValues(alpha: .35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Badge(
                icon: _directionIcon(direction),
                label: context.l10n.text(direction),
                color: tone,
              ),
              _Badge(
                icon: signal.isClosed ? Icons.task_alt : Icons.sensors,
                label: context.l10n.text(signal.progressLabel),
                color: tone,
              ),
              if (signal.signalCode.isNotEmpty)
                _Badge(icon: Icons.tag, label: signal.signalCode, color: tone),
            ],
          ),
          const SizedBox(height: 20),
          Text(
            signal.tradingPair,
            style: Theme.of(context).textTheme.displaySmall?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: -.8,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (signal.currentPrice.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text(
              context.l10n.text('Current market price'),
              style: Theme.of(context).textTheme.labelLarge,
            ),
            Text(
              signal.currentPrice,
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
          ],
          const SizedBox(height: 18),
          Wrap(
            spacing: 20,
            runSpacing: 10,
            children: [
              if (signal.createdAt != null || signal.triggerTime != null)
                _Meta(
                  label: context.l10n.text('Created'),
                  value: formatDateTime(
                    signal.createdAt ?? signal.triggerTime!,
                  ),
                ),
              if (signal.updatedAt != null)
                _Meta(
                  label: context.l10n.text('Last updated'),
                  value: formatDateTime(signal.updatedAt!),
                ),
              if (signal.analyst.isNotEmpty)
                _Meta(
                  label: context.l10n.text('Analyst / source'),
                  value: signal.analyst,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TradeSetup extends StatelessWidget {
  const _TradeSetup({required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final ratio = signal.riskRewardRatio;
    return Card(
      key: const ValueKey('trade-call-price-ladder'),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(
              icon: Icons.tune,
              title: context.l10n.text('Trade setup'),
            ),
            const SizedBox(height: 16),
            _PriceLadder(signal: signal),
            const Divider(height: 28),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (ratio != null)
                  _SetupMetric(
                    label: context.l10n.text('Risk-to-reward'),
                    value: '1:${ratio.toStringAsFixed(2)}',
                  ),
                if (signal.suggestedRiskPercent.isNotEmpty)
                  _SetupMetric(
                    label: context.l10n.text('Suggested risk'),
                    value: signal.suggestedRiskPercent,
                  ),
                if (signal.timeframe.isNotEmpty)
                  _SetupMetric(
                    label: context.l10n.text('Timeframe'),
                    value: signal.timeframe,
                  ),
                if (signal.orderType.isNotEmpty)
                  _SetupMetric(
                    label: context.l10n.text('Order type'),
                    value: signal.orderType,
                  ),
                if (signal.marketCondition.isNotEmpty)
                  _SetupMetric(
                    label: context.l10n.text('Market condition'),
                    value: signal.marketCondition,
                  ),
                if (signal.tradeSession.isNotEmpty)
                  _SetupMetric(
                    label: context.l10n.text('Trade session'),
                    value: signal.tradeSession,
                  ),
                if (signal.expiryTime != null)
                  _SetupMetric(
                    label: context.l10n.text('Expiry time'),
                    value: formatDateTime(signal.expiryTime!),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PriceLadder extends StatelessWidget {
  const _PriceLadder({required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final levels = <(String, String, IconData, Color)>[
      for (final target in signal.targets.indexed)
        (
          '${context.l10n.text('Take profit')} ${target.$1 + 1}',
          target.$2,
          Icons.flag_outlined,
          colors.primary,
        ),
      (
        context.l10n.text('Entry / entry zone'),
        signal.entryPrice,
        Icons.login,
        colors.tertiary,
      ),
      (
        context.l10n.text('Stop loss'),
        signal.stopLoss,
        Icons.shield_outlined,
        colors.error,
      ),
    ];
    return Column(
      children: [
        for (final level in levels)
          IntrinsicHeight(
            child: Row(
              children: [
                SizedBox(
                  width: 28,
                  child: Column(
                    children: [
                      Expanded(
                        child: Container(
                          width: 2,
                          color: level.$4.withValues(alpha: .28),
                        ),
                      ),
                      Icon(level.$3, size: 20, color: level.$4),
                      Expanded(
                        child: Container(
                          width: 2,
                          color: level.$4.withValues(alpha: .28),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            level.$1,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Flexible(
                          child: Text(
                            level.$2,
                            textAlign: TextAlign.end,
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _TradeThesis extends StatelessWidget {
  const _TradeThesis({required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final sections = <String, String>{...signal.analysisSections};
    final language = context.l10n.locale.languageCode;
    final disclaimer = signal.localizedDisclaimer(language).trim();
    if (disclaimer.isNotEmpty && !sections.containsKey('risk_warning')) {
      sections['risk_warning'] = disclaimer;
    }
    return Card(
      key: const ValueKey('trade-call-thesis'),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 10, 18, 8),
              child: _SectionTitle(
                icon: Icons.psychology_alt_outlined,
                title: context.l10n.text('Trade thesis'),
              ),
            ),
            if (sections.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 18),
                child: Text(
                  context.l10n.text(
                    'No analyst thesis was provided for this trade call.',
                  ),
                ),
              )
            else
              for (final section in sections.entries)
                ExpansionTile(
                  initiallyExpanded: section.key == sections.keys.first,
                  leading: Icon(_thesisIcon(section.key)),
                  title: Text(
                    context.l10n.text(_thesisLabel(section.key)),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  childrenPadding: const EdgeInsets.fromLTRB(56, 0, 18, 16),
                  expandedCrossAxisAlignment: CrossAxisAlignment.start,
                  children: [Text(section.value)],
                ),
          ],
        ),
      ),
    );
  }
}

class _Lifecycle extends StatelessWidget {
  const _Lifecycle({required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final stages = _lifecycleStages(signal);
    return Card(
      key: const ValueKey('trade-call-lifecycle'),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(
              icon: Icons.route_outlined,
              title: context.l10n.text('Trade-call lifecycle'),
            ),
            const SizedBox(height: 16),
            for (final stage in stages.indexed)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Icon(
                        stage.$2.$2
                            ? Icons.check_circle
                            : Icons.radio_button_unchecked,
                        size: 21,
                      ),
                      if (stage.$1 < stages.length - 1)
                        Container(
                          width: 2,
                          height: 28,
                          color: Theme.of(context).colorScheme.outlineVariant,
                        ),
                    ],
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Text(
                        context.l10n.text(stage.$2.$1),
                        style: TextStyle(
                          fontWeight: stage.$2.$2
                              ? FontWeight.w900
                              : FontWeight.w500,
                        ),
                      ),
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

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});
  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon),
      const SizedBox(width: 9),
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
    ],
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.icon, required this.label, required this.color});
  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: color.withValues(alpha: .28)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 16, color: color),
        const SizedBox(width: 6),
        Text(
          label,
          style: TextStyle(color: color, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _Meta extends StatelessWidget {
  const _Meta({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: const BoxConstraints(maxWidth: 240),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.labelMedium),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
      ],
    ),
  );
}

class _SetupMetric extends StatelessWidget {
  const _SetupMetric({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      '$label  ·  $value',
      style: const TextStyle(fontWeight: FontWeight.w700),
    ),
  );
}

Color _directionColor(BuildContext context, String direction) =>
    switch (direction) {
      'Sell' => Theme.of(context).colorScheme.error,
      'Neutral' || 'Cancelled' => Theme.of(context).colorScheme.outline,
      _ => Theme.of(context).colorScheme.primary,
    };

IconData _directionIcon(String direction) => switch (direction) {
  'Buy' => Icons.north_east,
  'Sell' => Icons.south_east,
  'Cancelled' => Icons.block,
  'Completed' => Icons.task_alt,
  _ => Icons.horizontal_rule,
};

String _thesisLabel(String key) => switch (key) {
  'market_bias' => 'Market bias',
  'major_structure' => 'Major structure',
  'internal_structure' => 'Internal structure',
  'current_structure' => 'Current structure',
  'key_level' => 'Key level',
  'entry_confirmation' => 'Entry confirmation',
  'confluences' => 'Confluences',
  'invalidation' => 'Invalidation',
  'risk_warning' => 'Risk warning',
  'analyst_notes' => 'Analyst notes',
  _ => key,
};

IconData _thesisIcon(String key) => switch (key) {
  'market_bias' => Icons.explore_outlined,
  'key_level' => Icons.horizontal_rule,
  'entry_confirmation' => Icons.fact_check_outlined,
  'invalidation' || 'risk_warning' => Icons.warning_amber_outlined,
  'analyst_notes' => Icons.notes_outlined,
  _ => Icons.account_tree_outlined,
};

List<(String, bool)> _lifecycleStages(TradingSignalItem signal) {
  final stages = <(String, bool)>[
    ('Published', true),
    ('Entry triggered', signal.status >= 1 || signal.isClosed),
    ('Active', signal.status >= 1 || signal.isClosed),
  ];
  if (signal.status >= 2 && signal.status <= 11) {
    stages.add(('Partial target reached', true));
  }
  if (signal.status == 12) stages.add(('Cancelled', true));
  if (signal.status == 13) stages.add(('Stop loss reached', true));
  if (signal.isDone && signal.status != 13) {
    stages.add(('Take profit reached', true));
  }
  if (signal.isBreakEven) stages.add(('Break even', true));
  return stages;
}
