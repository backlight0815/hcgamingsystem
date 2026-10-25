import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/standalone_models.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_back_button.dart';
import '../../shared/text_deduplication.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';
import 'trading_strategy_recommendation_screen.dart';

class TradingStrategyScreen extends StatefulWidget {
  const TradingStrategyScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<TradingStrategyScreen> createState() => _TradingStrategyScreenState();
}

class _TradingStrategyScreenState extends State<TradingStrategyScreen> {
  var _strategies = <TradingStrategyRecord>[];
  var _loading = true;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    unawaited(_loadStrategies());
  }

  Future<void> _loadStrategies() async {
    late final List<TradingStrategyRecord> records;
    try {
      records = await widget.repository.loadTradingStrategyRecords(
        widget.user.uid,
      );
    } catch (error) {
      records = _strategies;
      if (mounted) AppFeedback.error(context, error);
    }
    if (!mounted) return;
    setState(() {
      _strategies = records;
      _loading = false;
    });
  }

  Future<void> _createStrategy() async {
    final record = await Navigator.of(context).push<TradingStrategyRecord>(
      MaterialPageRoute(builder: (_) => const _TradingStrategyEditorScreen()),
    );
    if (record == null) return;
    if (!mounted) return;
    setState(() => _saving = true);
    try {
      await widget.repository.upsertTradingStrategyRecord(
        uid: widget.user.uid,
        record: record,
      );
    } catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.error(context, error);
      }
      return;
    }
    final updated = [record, ..._strategies]
      ..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    if (!mounted) return;
    setState(() {
      _strategies = updated;
      _saving = false;
    });
    AppFeedback.success(context, 'Trading strategy saved.');
    unawaited(_loadStrategies());
  }

  Future<void> _generateRecommendedStrategy() async {
    final record = await Navigator.of(context).push<TradingStrategyRecord>(
      MaterialPageRoute(
        builder: (_) => TradingStrategyRecommendationScreen(
          userId: widget.user.uid,
          storage: widget.storage,
          onSave: (record) => widget.repository.upsertTradingStrategyRecord(
            uid: widget.user.uid,
            record: record,
          ),
        ),
      ),
    );
    if (record == null) return;
    if (!mounted) return;
    final updated = [record, ..._strategies]
      ..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    setState(() => _strategies = updated);
    AppFeedback.success(context, 'Recommended trading strategy saved.');
    unawaited(_loadStrategies());
  }

  Future<void> _editStrategy(TradingStrategyRecord record) async {
    final updatedRecord = await Navigator.of(context)
        .push<TradingStrategyRecord>(
          MaterialPageRoute(
            builder: (_) => _TradingStrategyEditorScreen(record: record),
          ),
        );
    if (updatedRecord == null) return;
    if (!mounted) return;
    setState(() => _saving = true);
    try {
      await widget.repository.upsertTradingStrategyRecord(
        uid: widget.user.uid,
        record: updatedRecord,
      );
    } catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.error(context, error);
      }
      return;
    }
    final updated = [
      for (final strategy in _strategies)
        if (strategy.id == updatedRecord.id) updatedRecord else strategy,
    ]..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    if (!mounted) return;
    setState(() {
      _strategies = updated;
      _saving = false;
    });
    AppFeedback.success(context, 'Trading strategy saved.');
    unawaited(_loadStrategies());
  }

  Future<void> _deleteStrategy(TradingStrategyRecord record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Delete trading strategy?')),
        content: Text(context.l10n.text('This cannot be undone.')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.deleteTradingStrategyRecord(
        uid: widget.user.uid,
        strategyId: record.id,
      );
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
      return;
    }
    final updated = _strategies
        .where((strategy) => strategy.id != record.id)
        .toList();
    if (!mounted) return;
    setState(() => _strategies = updated);
    AppFeedback.success(context, 'Trading strategy deleted.');
  }

  void _openDetail(TradingStrategyRecord record) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _TradingStrategyDetailScreen(
          record: record,
          onEdit: () => _editStrategy(record),
          onDelete: () => _deleteStrategy(record),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Strategies')),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _saving ? null : _createStrategy,
        icon: const Icon(Icons.add),
        label: Text(context.l10n.text('New strategy')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
        children: [
          const _StrategyPlanComparisonCard(),
          const SizedBox(height: 12),
          _TradingStrategyActionCard(
            icon: Icons.edit_note_outlined,
            title: context.l10n.text('Create Own Trading Strategy'),
            subtitle: context.l10n.text(
              'Build your own technical method manually using setup, confirmation, entry, SL, and TP rules.',
            ),
            onTap: _saving ? null : _createStrategy,
          ),
          const SizedBox(height: 10),
          _TradingStrategyActionCard(
            icon: Icons.auto_awesome_outlined,
            title: context.l10n.text('Generate Recommended Trading Strategy'),
            subtitle: context.l10n.text(
              'Finds your technical method such as SNR, SMC, ICT, EMA, MACD, Trendline.',
            ),
            onTap: _saving ? null : _generateRecommendedStrategy,
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_saving)
            const LinearProgressIndicator()
          else if (_strategies.isEmpty)
            EmptyState(
              icon: Icons.auto_graph_outlined,
              title: context.l10n.text('No trading strategies yet'),
              action: FilledButton.icon(
                onPressed: _createStrategy,
                icon: const Icon(Icons.add),
                label: Text(context.l10n.text('Create strategy')),
              ),
            )
          else
            for (var index = 0; index < _strategies.length; index++) ...[
              _TradingStrategyCard(
                record: _strategies[index],
                onTap: () => _openDetail(_strategies[index]),
                onEdit: () => _editStrategy(_strategies[index]),
                onDelete: () => _deleteStrategy(_strategies[index]),
              ),
              if (index != _strategies.length - 1) const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }
}

class _StrategyPlanComparisonCard extends StatelessWidget {
  const _StrategyPlanComparisonCard();

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Strategy vs Plan'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Trading Strategy: Entry, exit, SL/TP, setup rules. How I enter and exit the market.',
              ),
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'Trading Plan: Overall trading rules, risk, psychology, routine, allowed strategies. How I manage myself, risk, accounts, and execution.',
              ),
            ),
            const SizedBox(height: 10),
            Text(
              context.l10n.text(
                'Strategy example: Buy at H4 support after M15 CHOCH + rejection candle, SL below swing low, TP at next resistance.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 4),
            Text(
              context.l10n.text(
                'Plan example: Risk max 1% per trade, max 2 losses per day, trade only London/NY, no trade during high-impact news, stop after breaking rules.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _TradingStrategyActionCard extends StatelessWidget {
  const _TradingStrategyActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(14),
        leading: CircleAvatar(child: Icon(icon)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(subtitle),
        ),
        trailing: const Icon(Icons.chevron_right),
        enabled: onTap != null,
        onTap: onTap,
      ),
    );
  }
}

class _TradingStrategyCard extends StatelessWidget {
  const _TradingStrategyCard({
    required this.record,
    required this.onTap,
    required this.onEdit,
    required this.onDelete,
  });

  final TradingStrategyRecord record;
  final VoidCallback onTap;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final strategy = record.strategy;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      strategy.name.trim().isEmpty
                          ? context.l10n.text('Untitled strategy')
                          : _localizedStrategyContent(
                              context,
                              strategy.name,
                              strategy.isRecommended,
                            ),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  if (strategy.isActive)
                    Chip(
                      label: Text(context.l10n.text('Active')),
                      visualDensity: VisualDensity.compact,
                    ),
                  if (strategy.isRecommended)
                    Chip(
                      label: Text(context.l10n.text('Recommended')),
                      visualDensity: VisualDensity.compact,
                    ),
                  PopupMenuButton<String>(
                    onSelected: (value) {
                      if (value == 'edit') onEdit();
                      if (value == 'delete') onDelete();
                    },
                    itemBuilder: (context) => [
                      PopupMenuItem(
                        value: 'edit',
                        child: Text(context.l10n.text('Edit')),
                      ),
                      PopupMenuItem(
                        value: 'delete',
                        child: Text(context.l10n.text('Delete')),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _InfoChip(
                    icon: Icons.candlestick_chart_outlined,
                    label: context.l10n.text(strategy.tradingStyle),
                  ),
                  _InfoChip(
                    icon: Icons.show_chart,
                    label: context.l10n.text(strategy.marketCondition),
                  ),
                  if (strategy.timeframeSummary.isNotEmpty)
                    _InfoChip(
                      icon: Icons.schedule,
                      label: strategy.timeframeSummary,
                    ),
                ],
              ),
              if (strategy.entryModelRules.trim().isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  _localizedStrategyBlock(
                    context,
                    strategy.entryModelRules,
                    localize: strategy.isRecommended,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const SizedBox(height: 8),
              Text(
                context.l10n.text('Updated {date}', {
                  'date': formatDate(record.updatedAt),
                }),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TradingStrategyDetailScreen extends StatelessWidget {
  const _TradingStrategyDetailScreen({
    required this.record,
    required this.onEdit,
    required this.onDelete,
  });

  final TradingStrategyRecord record;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final strategy = record.strategy;
    final localizeGenerated = strategy.isRecommended;
    final title = strategy.name.trim().isEmpty
        ? context.l10n.text('Untitled strategy')
        : _localizedStrategyContent(
            context,
            strategy.name.trim(),
            localizeGenerated,
          );
    final sourceLabel = _strategySourceLabel(strategy);
    final category = _firstText([
      strategy.strategyType,
      _strategyFamilyLabel(_strategyFamily(strategy)),
    ]);
    final technicalConcept = _firstText([
      strategy.technicalConcept,
      strategy.analysisMethods.join(', '),
      category,
    ]);
    final confirmationTimeframe = _confirmationTimeframe(strategy);
    final multiTimeframeRule = _multiTimeframeRule(strategy);
    final entrySteps = _lineItems(strategy.entryModelRules);
    final confirmationChecklist = strategy.entryConfirmationChecklist.isEmpty
        ? _lineItems(strategy.preTradeChecklist)
        : strategy.entryConfirmationChecklist;
    final stopLossRules = _lineItems(strategy.stopLossRule);
    final takeProfitRules = _lineItems(
      _joinText([strategy.takeProfitRule, strategy.riskRewardTarget]),
    );
    final managementRules = _lineItems(
      _joinText([
        strategy.tradeManagementRule,
        if (strategy.partialCloseRule.trim().isNotEmpty)
          '${context.l10n.text('Partial Close')}: ${strategy.partialCloseRule}',
        if (strategy.breakEvenRule.trim().isNotEmpty)
          '${context.l10n.text('Break Even')}: ${strategy.breakEvenRule}',
      ]),
    );
    final invalidSetupWarnings = _uniqueText(
      _lineItems(
        _joinText([strategy.invalidationRule, strategy.noTradeConditions]),
      ),
    );
    // noTradeConditions belong to Invalid Setup Conditions. Rendering them in
    // this card as well was the source of the repeated suitable-market warning.
    final riskRules = excludingNormalizedText([
      ..._lineItems(strategy.riskManagementRules),
      ..._lineItems(strategy.psychologyRules),
    ], invalidSetupWarnings);
    final strategyWarnings = _uniqueText([
      ..._lineItems(strategy.personalityWarnings),
      ..._strategyWeaknessFallbacks(_strategyFamily(strategy)),
    ]);
    final practiceNotes = _lineItems(
      _joinText([strategy.improvementNotes, strategy.postTradeReviewRules]),
    );

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Strategy Details')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          _StrategyHeaderCard(
            title: title,
            category: category,
            technicalConcept: technicalConcept,
            sourceLabel: sourceLabel,
            createdAt: record.createdAt,
            updatedAt: record.updatedAt,
            isActive: strategy.isActive,
            chips: _strategyBadges(strategy, sourceLabel, category),
            localizeContent: localizeGenerated,
            onEdit: () {
              Navigator.pop(context);
              onEdit();
            },
            onDelete: () {
              Navigator.pop(context);
              onDelete();
            },
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.article_outlined,
            title: context.l10n.text('Strategy Overview'),
            child: Column(
              children: [
                _StrategyInfoLine(
                  label: context.l10n.text('Strategy Description'),
                  value: _valueOrFallback(
                    context,
                    strategy.description,
                    'No description added',
                    localize: localizeGenerated,
                  ),
                ),
                _StrategyInfoLine(
                  label: context.l10n.text('Suitable Trader Type'),
                  value: _valueOrFallback(
                    context,
                    strategy.suitableTraderPersonality,
                    'Not specified',
                    localize: localizeGenerated,
                  ),
                ),
                _StrategyInfoLine(
                  label: context.l10n.text('Suitable Market Condition'),
                  value: _valueOrFallback(
                    context,
                    _firstText([
                      strategy.recommendedMarket,
                      strategy.marketCondition,
                    ]),
                    'Not specified',
                    localize: localizeGenerated,
                  ),
                ),
                _StrategyInfoLine(
                  label: context.l10n.text('Recommended Trading Style'),
                  value: _valueOrFallback(
                    context,
                    strategy.tradingStyle,
                    'Not specified',
                    localize: localizeGenerated,
                  ),
                ),
                _StrategyInfoLine(
                  label: context.l10n.text('Main Objective'),
                  value: _strategyObjective(
                    context,
                    strategy,
                    localize: localizeGenerated,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.account_tree_outlined,
            title: context.l10n.text('Technical Concept'),
            child: _StrategyChecklist(
              items: _technicalConceptItems(context, strategy),
              emptyText: context.l10n.text('No rule added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.view_timeline_outlined,
            title: context.l10n.text('Timeframe Setup'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _StrategyTimeframeGrid(
                  items: [
                    _StrategyTimeframeItem(
                      label: context.l10n.text('Bias Timeframe'),
                      value: _valueOrFallback(
                        context,
                        strategy.majorTimeframe,
                        'Not specified',
                        localize: localizeGenerated,
                      ),
                    ),
                    _StrategyTimeframeItem(
                      label: context.l10n.text('Confirmation Timeframe'),
                      value: _valueOrFallback(
                        context,
                        confirmationTimeframe,
                        'Not specified',
                        localize: localizeGenerated,
                      ),
                    ),
                    _StrategyTimeframeItem(
                      label: context.l10n.text('Entry Timeframe'),
                      value: _valueOrFallback(
                        context,
                        strategy.entryTimeframe,
                        'Not specified',
                        localize: localizeGenerated,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _StrategyInfoCallout(
                  icon: Icons.sync_alt_outlined,
                  label: context.l10n.text('Multi-Timeframe Rule'),
                  body: _valueOrFallback(
                    context,
                    multiTimeframeRule,
                    'No rule added',
                    localize: localizeGenerated,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.public_outlined,
            title: context.l10n.text('Suitable Market Condition'),
            child: Column(
              children: [
                _StrategyInfoLine(
                  label: context.l10n.text('Best Condition'),
                  value: _valueOrFallback(
                    context,
                    _firstText([
                      strategy.recommendedMarket,
                      strategy.marketCondition,
                    ]),
                    'Not specified',
                    localize: localizeGenerated,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.login_outlined,
            title: context.l10n.text('Entry Model Steps'),
            child: _StrategyStepList(
              steps: entrySteps,
              emptyText: context.l10n.text('No rule added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.fact_check_outlined,
            title: context.l10n.text('Entry Confirmation Checklist'),
            child: _StrategyChecklist(
              items: confirmationChecklist,
              emptyText: context.l10n.text('No checklist added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _ResponsiveStrategyPair(
            first: _StrategySectionCard(
              icon: Icons.shield_outlined,
              title: context.l10n.text('Stop Loss Rules'),
              child: _StrategyChecklist(
                items: stopLossRules,
                emptyText: context.l10n.text('No rule added'),
                localize: localizeGenerated,
              ),
            ),
            second: _StrategySectionCard(
              icon: Icons.flag_outlined,
              title: context.l10n.text('Take Profit Rules'),
              child: _StrategyChecklist(
                items: takeProfitRules,
                emptyText: context.l10n.text('No rule added'),
                localize: localizeGenerated,
              ),
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.tune_outlined,
            title: context.l10n.text('Trade Management'),
            child: _StrategyChecklist(
              items: managementRules,
              emptyText: context.l10n.text('No rule added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.health_and_safety_outlined,
            title: context.l10n.text('Risk & Avoid Conditions'),
            child: _StrategyChecklist(
              items: riskRules,
              emptyText: context.l10n.text('No rule added'),
              infoStyle: true,
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.warning_amber_outlined,
            title: context.l10n.text('Invalid Setup Conditions'),
            child: _StrategyWarningList(
              items: invalidSetupWarnings,
              emptyText: context.l10n.text('No warning added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.report_problem_outlined,
            title: context.l10n.text('Strategy Weakness / Risk Warning'),
            child: _StrategyWarningList(
              items: strategyWarnings,
              emptyText: context.l10n.text('No warning added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.image_search_outlined,
            title: context.l10n.text('Example Setup'),
            child: _StrategyTextBlock(
              body: strategy.exampleSetupNotes,
              emptyText: context.l10n.text('No example setup added'),
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategySectionCard(
            icon: Icons.school_outlined,
            title: context.l10n.text('Practice Notes'),
            child: _StrategyChecklist(
              items: practiceNotes,
              emptyText: context.l10n.text('No notes added'),
              infoStyle: true,
              localize: localizeGenerated,
            ),
          ),
          const SizedBox(height: 14),
          _StrategyMetadataCard(record: record, sourceLabel: sourceLabel),
        ],
      ),
    );
  }
}

class _StrategyHeaderCard extends StatelessWidget {
  const _StrategyHeaderCard({
    required this.title,
    required this.category,
    required this.technicalConcept,
    required this.sourceLabel,
    required this.createdAt,
    required this.updatedAt,
    required this.isActive,
    required this.chips,
    required this.localizeContent,
    required this.onEdit,
    required this.onDelete,
  });

  final String title;
  final String category;
  final String technicalConcept;
  final String sourceLabel;
  final DateTime createdAt;
  final DateTime updatedAt;
  final bool isActive;
  final List<String> chips;
  final bool localizeContent;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        color: colors.primaryContainer.withValues(alpha: .45),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: colors.primary,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(
                    Icons.candlestick_chart_outlined,
                    color: colors.onPrimary,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _valueOrFallback(
                          context,
                          technicalConcept,
                          'Not specified',
                          localize: localizeContent,
                        ),
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final chip in chips)
                  _StrategyBadge(label: context.l10n.text(chip)),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _StrategyHeaderMeta(
                  icon: Icons.category_outlined,
                  label: context.l10n.text('Strategy Category'),
                  value: _valueOrFallback(
                    context,
                    category,
                    'Not specified',
                    localize: localizeContent,
                  ),
                ),
                _StrategyHeaderMeta(
                  icon: Icons.source_outlined,
                  label: context.l10n.text('Source'),
                  value: context.l10n.text(sourceLabel),
                ),
                _StrategyHeaderMeta(
                  icon: isActive
                      ? Icons.check_circle_outline
                      : Icons.pause_circle_outline,
                  label: context.l10n.text('Status'),
                  value: context.l10n.text(isActive ? 'Active' : 'Inactive'),
                ),
                _StrategyHeaderMeta(
                  icon: Icons.event_available_outlined,
                  label: context.l10n.text('Created'),
                  value: formatDate(createdAt),
                ),
                _StrategyHeaderMeta(
                  icon: Icons.update_outlined,
                  label: context.l10n.text('Last Updated'),
                  value: formatDate(updatedAt),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                FilledButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(context.l10n.text('Edit')),
                ),
                OutlinedButton.icon(
                  onPressed: onDelete,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: colors.error,
                  ),
                  icon: const Icon(Icons.delete_outline),
                  label: Text(context.l10n.text('Delete')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StrategyHeaderMeta extends StatelessWidget {
  const _StrategyHeaderMeta({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: 156,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.surface.withValues(alpha: .72),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: colors.primary),
          const SizedBox(height: 8),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: colors.onSurfaceVariant,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

class _StrategyBadge extends StatelessWidget {
  const _StrategyBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: colors.primary.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: colors.primary.withValues(alpha: .28)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: colors.primary,
          fontWeight: FontWeight.w800,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _StrategySectionCard extends StatelessWidget {
  const _StrategySectionCard({
    required this.icon,
    required this.title,
    required this.child,
  });

  final IconData icon;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: colors.secondaryContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    icon,
                    size: 19,
                    color: colors.onSecondaryContainer,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }
}

class _StrategyInfoLine extends StatelessWidget {
  const _StrategyInfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final stacked = constraints.maxWidth < 420;
          if (stacked) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _label(context, colors),
                const SizedBox(height: 4),
                Text(value),
              ],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(width: 180, child: _label(context, colors)),
              const SizedBox(width: 12),
              Expanded(child: Text(value)),
            ],
          );
        },
      ),
    );
  }

  Widget _label(BuildContext context, ColorScheme colors) => Text(
    label,
    style: Theme.of(context).textTheme.labelLarge?.copyWith(
      color: colors.onSurfaceVariant,
      fontWeight: FontWeight.w800,
    ),
  );
}

class _StrategyInfoCallout extends StatelessWidget {
  const _StrategyInfoCallout({
    required this.icon,
    required this.label,
    required this.body,
  });

  final IconData icon;
  final String label;
  final String body;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.tertiaryContainer.withValues(alpha: .42),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: colors.tertiary, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StrategyTimeframeItem {
  const _StrategyTimeframeItem({required this.label, required this.value});

  final String label;
  final String value;
}

class _StrategyTimeframeGrid extends StatelessWidget {
  const _StrategyTimeframeGrid({required this.items});

  final List<_StrategyTimeframeItem> items;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth < 520
            ? constraints.maxWidth
            : (constraints.maxWidth - 20) / 3;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: _StrategyTimeframeTile(item: item),
              ),
          ],
        );
      },
    );
  }
}

class _StrategyTimeframeTile extends StatelessWidget {
  const _StrategyTimeframeTile({required this.item});

  final _StrategyTimeframeItem item;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item.label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: colors.onSurfaceVariant,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            item.value,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

class _StrategyStepList extends StatelessWidget {
  const _StrategyStepList({
    required this.steps,
    required this.emptyText,
    required this.localize,
  });

  final List<String> steps;
  final String emptyText;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    if (steps.isEmpty) return _StrategyEmptyStateText(text: emptyText);
    final colors = Theme.of(context).colorScheme;
    return Column(
      children: [
        for (var index = 0; index < steps.length; index++) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: colors.primary,
                foregroundColor: colors.onPrimary,
                child: Text(
                  '${index + 1}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: colors.surfaceContainerHighest.withValues(
                      alpha: .45,
                    ),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: colors.outlineVariant),
                  ),
                  child: Text(
                    _localizedStrategyContent(context, steps[index], localize),
                  ),
                ),
              ),
            ],
          ),
          if (index != steps.length - 1) const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _StrategyChecklist extends StatelessWidget {
  const _StrategyChecklist({
    required this.items,
    required this.emptyText,
    required this.localize,
    this.infoStyle = false,
  });

  final List<String> items;
  final String emptyText;
  final bool localize;
  final bool infoStyle;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return _StrategyEmptyStateText(text: emptyText);
    final colors = Theme.of(context).colorScheme;
    final iconColor = infoStyle ? colors.tertiary : colors.primary;
    return Column(
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  infoStyle
                      ? Icons.lightbulb_outline
                      : Icons.check_circle_outline,
                  color: iconColor,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _localizedStrategyContent(context, item, localize),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _StrategyWarningList extends StatelessWidget {
  const _StrategyWarningList({
    required this.items,
    required this.emptyText,
    required this.localize,
  });

  final List<String> items;
  final String emptyText;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return _StrategyEmptyStateText(text: emptyText);
    final colors = Theme.of(context).colorScheme;
    return Column(
      children: [
        for (final item in items)
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: colors.errorContainer.withValues(alpha: .45),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: colors.error.withValues(alpha: .32)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.warning_amber_outlined,
                  color: colors.error,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _localizedStrategyContent(context, item, localize),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _StrategyTextBlock extends StatelessWidget {
  const _StrategyTextBlock({
    required this.body,
    required this.emptyText,
    required this.localize,
  });

  final String body;
  final String emptyText;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    if (body.trim().isEmpty) return _StrategyEmptyStateText(text: emptyText);
    return Text(_localizedStrategyBlock(context, body, localize: localize));
  }
}

class _StrategyEmptyStateText extends StatelessWidget {
  const _StrategyEmptyStateText({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: .35),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Text(text, style: TextStyle(color: colors.onSurfaceVariant)),
    );
  }
}

class _ResponsiveStrategyPair extends StatelessWidget {
  const _ResponsiveStrategyPair({required this.first, required this.second});

  final Widget first;
  final Widget second;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 620) {
          return Column(children: [first, const SizedBox(height: 14), second]);
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: first),
            const SizedBox(width: 14),
            Expanded(child: second),
          ],
        );
      },
    );
  }
}

class _StrategyMetadataCard extends StatelessWidget {
  const _StrategyMetadataCard({
    required this.record,
    required this.sourceLabel,
  });

  final TradingStrategyRecord record;
  final String sourceLabel;

  @override
  Widget build(BuildContext context) {
    final strategy = record.strategy;
    return _StrategySectionCard(
      icon: Icons.info_outline,
      title: context.l10n.text('Strategy Metadata'),
      child: Column(
        children: [
          _StrategyInfoLine(
            label: context.l10n.text('Created'),
            value: formatDateTime(record.createdAt),
          ),
          _StrategyInfoLine(
            label: context.l10n.text('Last Updated'),
            value: formatDateTime(record.updatedAt),
          ),
          _StrategyInfoLine(
            label: context.l10n.text('Created By'),
            value: _valueOrFallback(
              context,
              record.userId,
              'Not specified',
              localize: false,
            ),
          ),
          _StrategyInfoLine(
            label: context.l10n.text('Source'),
            value: context.l10n.text(sourceLabel),
          ),
          _StrategyInfoLine(
            label: context.l10n.text('Version'),
            value: _valueOrFallback(
              context,
              strategy.questionnaireVersion,
              'Not specified',
              localize: false,
            ),
          ),
        ],
      ),
    );
  }
}

String _strategySourceLabel(TradingStrategy strategy) {
  if (strategy.source == 'strategy_questionnaire') {
    return 'Questionnaire Generated';
  }
  if (strategy.isRecommended) return 'Recommended Strategy';
  return 'Manual Strategy';
}

List<String> _strategyBadges(
  TradingStrategy strategy,
  String sourceLabel,
  String category,
) {
  return _uniqueText([
    _dominantStrategyBadge(strategy),
    category,
    sourceLabel,
    strategy.isActive ? 'Active' : 'Inactive',
    if (strategy.marketCondition.trim().isNotEmpty) strategy.marketCondition,
  ]);
}

String _dominantStrategyBadge(TradingStrategy strategy) {
  final family = _strategyFamily(strategy);
  if (family != _StrategyFamily.hybrid) return _strategyFamilyLabel(family);
  for (final method in strategy.analysisMethods) {
    if (method.trim().isNotEmpty) return method.trim();
  }
  return 'Hybrid';
}

String _strategyObjective(
  BuildContext context,
  TradingStrategy strategy, {
  required bool localize,
}) {
  final objective = _firstText([
    strategy.description,
    strategy.technicalConcept,
    strategy.entryModelRules,
  ]);
  if (objective.isNotEmpty) {
    return _localizedStrategyBlock(context, objective, localize: localize);
  }
  return context.l10n.text(
    'Identify valid technical setups and execute them only after confirmation.',
  );
}

List<String> _technicalConceptItems(
  BuildContext context,
  TradingStrategy strategy,
) {
  final family = _strategyFamily(strategy);
  final methods = strategy.analysisMethods
      .map((method) => context.l10n.text(method))
      .where((method) => method.trim().isNotEmpty)
      .join(', ');
  return _uniqueText([
    ..._lineItems(strategy.technicalConcept),
    if (methods.isNotEmpty)
      '${context.l10n.text('Supporting Concepts')}: $methods',
    ...switch (family) {
      _StrategyFamily.snr => [
        'Mark H1/H4 support and resistance zones.',
        'Wait for rejection or breakout retest.',
        'Avoid entering in the middle of range.',
      ],
      _StrategyFamily.smcIct => [
        'Identify market structure before selecting POI.',
        'Find liquidity sweep and displacement.',
        'Use Order Block or FVG as POI.',
        'Confirm with BOS or CHOCH.',
      ],
      _StrategyFamily.indicator => [
        'Confirm trend direction before using indicator signal.',
        'Wait for pullback, crossover, or momentum confirmation.',
        'Avoid sideways market when indicator signals are noisy.',
      ],
      _StrategyFamily.trendline => [
        'Use trendline only when touches are clear.',
        'Wait for trendline reaction or breakout retest.',
        'Do not force weak trendlines.',
      ],
      _StrategyFamily.marketStructure => [
        'Confirm higher-timeframe structure first.',
        'Use BOS, CHOCH, retest, or continuation structure.',
      ],
      _StrategyFamily.hybrid => [
        'Combine level, structure, and confirmation before entry.',
      ],
    },
  ]);
}

List<String> _strategyWeaknessFallbacks(_StrategyFamily family) {
  return switch (family) {
    _StrategyFamily.snr => [
      'Can fail during strong breakout.',
      'Zones may be subjective.',
      'False breakout can happen.',
    ],
    _StrategyFamily.smcIct => [
      'POI can be invalid if structure is unclear.',
      'Liquidity sweep can fail.',
      'Requires patience and confirmation.',
    ],
    _StrategyFamily.indicator => [
      'Indicator signals may lag.',
      'Choppy market can create false signals.',
    ],
    _StrategyFamily.trendline => [
      'Forced trendlines reduce accuracy.',
      'Weak trendline touch may not be reliable.',
    ],
    _StrategyFamily.marketStructure => [
      'Structure can be unclear during transition periods.',
      'False BOS or CHOCH can trap late entries.',
    ],
    _StrategyFamily.hybrid => [
      'Too many confirmations can delay execution.',
      'Rules must stay clear to avoid subjective entries.',
    ],
  };
}

String _confirmationTimeframe(TradingStrategy strategy) {
  final value = strategy.internalTimeframe.trim();
  return _looksLikeTimeframe(value) ? value : '';
}

String _multiTimeframeRule(TradingStrategy strategy) {
  final value = strategy.internalTimeframe.trim();
  if (value.isNotEmpty && !_looksLikeTimeframe(value)) return value;
  final bias = strategy.majorTimeframe.trim();
  final confirmation = _confirmationTimeframe(strategy);
  final entry = strategy.entryTimeframe.trim();
  if ([bias, confirmation, entry].where((item) => item.isNotEmpty).length >=
      2) {
    return 'Only take setups when bias, confirmation, and entry timeframes agree.';
  }
  return '';
}

bool _looksLikeTimeframe(String value) {
  if (value.isEmpty) return false;
  final normalized = value.toUpperCase().replaceAll(' ', '');
  return RegExp(
    r'^(M1|M5|M15|M30|H1|H4|D1|DAILY|W1|WEEKLY)(/(M1|M5|M15|M30|H1|H4|D1|DAILY|W1|WEEKLY))*$',
  ).hasMatch(normalized);
}

enum _StrategyFamily {
  snr,
  smcIct,
  indicator,
  trendline,
  marketStructure,
  hybrid,
}

_StrategyFamily _strategyFamily(TradingStrategy strategy) {
  final text = [
    strategy.name,
    strategy.strategyType,
    strategy.technicalConcept,
    strategy.recommendedMarket,
    strategy.analysisMethods.join(' '),
  ].join(' ').toLowerCase();
  if (text.contains('smc') ||
      text.contains('ict') ||
      text.contains('order block') ||
      text.contains('fvg') ||
      text.contains('liquidity')) {
    return _StrategyFamily.smcIct;
  }
  if (text.contains('ema') ||
      text.contains('macd') ||
      text.contains('rsi') ||
      text.contains('indicator')) {
    return _StrategyFamily.indicator;
  }
  if (text.contains('trendline') || text.contains('trend line')) {
    return _StrategyFamily.trendline;
  }
  if (text.contains('snr') ||
      text.contains('support') ||
      text.contains('resistance')) {
    return _StrategyFamily.snr;
  }
  if (text.contains('market structure') ||
      text.contains('bos') ||
      text.contains('choch')) {
    return _StrategyFamily.marketStructure;
  }
  return _StrategyFamily.hybrid;
}

String _strategyFamilyLabel(_StrategyFamily family) {
  return switch (family) {
    _StrategyFamily.snr => 'SNR',
    _StrategyFamily.smcIct => 'SMC / ICT',
    _StrategyFamily.indicator => 'EMA / MACD / RSI',
    _StrategyFamily.trendline => 'Trendline',
    _StrategyFamily.marketStructure => 'Market Structure',
    _StrategyFamily.hybrid => 'Hybrid',
  };
}

String _firstText(Iterable<String> values) {
  for (final value in values) {
    final text = value.trim();
    if (text.isNotEmpty) return text;
  }
  return '';
}

String _joinText(Iterable<String> values) {
  return values
      .map((value) => value.trim())
      .where((value) => value.isNotEmpty)
      .join('\n');
}

List<String> _lineItems(String value) {
  return value
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .toList();
}

List<String> _uniqueText(Iterable<String> values) {
  return uniqueNormalizedText(values);
}

String _valueOrFallback(
  BuildContext context,
  String value,
  String fallbackKey, {
  required bool localize,
}) {
  final text = value.trim();
  return text.isEmpty
      ? context.l10n.text(fallbackKey)
      : _localizedStrategyContent(context, text, localize);
}

String _localizedStrategyBlock(
  BuildContext context,
  String value, {
  required bool localize,
}) {
  return value
      .split('\n')
      .map((line) => _localizedStrategyContent(context, line.trim(), localize))
      .where((line) => line.trim().isNotEmpty)
      .join('\n');
}

String _localizedStrategyContent(
  BuildContext context,
  String value,
  bool localize,
) {
  final text = value.trim();
  if (text.isEmpty) return text;
  return localize ? context.l10n.text(text) : text;
}

class _TradingStrategyEditorScreen extends StatefulWidget {
  const _TradingStrategyEditorScreen({this.record});

  final TradingStrategyRecord? record;

  @override
  State<_TradingStrategyEditorScreen> createState() =>
      _TradingStrategyEditorScreenState();
}

class _TradingStrategyEditorScreenState
    extends State<_TradingStrategyEditorScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _majorTimeframe;
  late final TextEditingController _internalTimeframe;
  late final TextEditingController _entryTimeframe;
  late final TextEditingController _entryRules;
  late final TextEditingController _checklist;
  late final TextEditingController _stopLoss;
  late final TextEditingController _takeProfit;
  late final TextEditingController _rr;
  late final TextEditingController _invalidation;
  late final TextEditingController _management;
  late final TextEditingController _partialClose;
  late final TextEditingController _breakEven;
  late final TextEditingController _exampleNotes;
  var _tradingStyle = 'Intraday';
  var _marketCondition = 'Hybrid';
  final _analysisMethods = <String>{};
  var _isActive = true;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final strategy = widget.record?.strategy ?? const TradingStrategy();
    _name = TextEditingController(text: strategy.name);
    _description = TextEditingController(text: strategy.description);
    _majorTimeframe = TextEditingController(text: strategy.majorTimeframe);
    _internalTimeframe = TextEditingController(
      text: strategy.internalTimeframe,
    );
    _entryTimeframe = TextEditingController(text: strategy.entryTimeframe);
    _entryRules = TextEditingController(text: strategy.entryModelRules);
    _checklist = TextEditingController(
      text: strategy.entryConfirmationChecklist.join('\n'),
    );
    _stopLoss = TextEditingController(text: strategy.stopLossRule);
    _takeProfit = TextEditingController(text: strategy.takeProfitRule);
    _rr = TextEditingController(text: strategy.riskRewardTarget);
    _invalidation = TextEditingController(text: strategy.invalidationRule);
    _management = TextEditingController(text: strategy.tradeManagementRule);
    _partialClose = TextEditingController(text: strategy.partialCloseRule);
    _breakEven = TextEditingController(text: strategy.breakEvenRule);
    _exampleNotes = TextEditingController(text: strategy.exampleSetupNotes);
    _tradingStyle = strategy.tradingStyle;
    _marketCondition = strategy.marketCondition;
    _analysisMethods.addAll(strategy.analysisMethods);
    _isActive = strategy.isActive;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _description,
      _majorTimeframe,
      _internalTimeframe,
      _entryTimeframe,
      _entryRules,
      _checklist,
      _stopLoss,
      _takeProfit,
      _rr,
      _invalidation,
      _management,
      _partialClose,
      _breakEven,
      _exampleNotes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          context.l10n.text(
            widget.record == null
                ? 'Create trading strategy'
                : 'Edit trading strategy',
          ),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
          children: [
            TextFormField(
              controller: _name,
              decoration: InputDecoration(
                labelText: context.l10n.text('Strategy name'),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _description,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: context.l10n.text('Description'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _tradingStyle,
              decoration: InputDecoration(
                labelText: context.l10n.text('Trading style'),
              ),
              items: [
                for (final style in tradingStrategyStyles)
                  DropdownMenuItem(
                    value: style,
                    child: Text(context.l10n.text(style)),
                  ),
              ],
              onChanged: (value) => setState(
                () => _tradingStyle = value ?? tradingStrategyStyles.first,
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _marketCondition,
              decoration: InputDecoration(
                labelText: context.l10n.text('Market condition'),
              ),
              items: [
                for (final condition in tradingStrategyMarketConditions)
                  DropdownMenuItem(
                    value: condition,
                    child: Text(context.l10n.text(condition)),
                  ),
              ],
              onChanged: (value) => setState(
                () => _marketCondition =
                    value ?? tradingStrategyMarketConditions.first,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              context.l10n.text('Technical analysis methods'),
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final method in tradingStrategyAnalysisMethods)
                  FilterChip(
                    label: Text(context.l10n.text(method)),
                    selected: _analysisMethods.contains(method),
                    onSelected: (selected) => setState(
                      () => selected
                          ? _analysisMethods.add(method)
                          : _analysisMethods.remove(method),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _text(_majorTimeframe, 'Major timeframe', 'H4'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _text(_internalTimeframe, 'Internal timeframe', 'H1'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _text(_entryTimeframe, 'Entry timeframe', 'M15'),
            const SizedBox(height: 12),
            _multiline(_entryRules, 'Entry model / rules', required: true),
            const SizedBox(height: 12),
            _multiline(
              _checklist,
              'Entry confirmation checklist',
              hint: context.l10n.text('One checklist item per line'),
            ),
            const SizedBox(height: 12),
            _multiline(_stopLoss, 'Stop loss rule'),
            const SizedBox(height: 12),
            _multiline(_takeProfit, 'Take profit rule'),
            const SizedBox(height: 12),
            _text(_rr, 'Risk reward ratio target', 'Example: 1:2'),
            const SizedBox(height: 12),
            _multiline(_invalidation, 'Invalidation rule'),
            const SizedBox(height: 12),
            _multiline(_management, 'Trade management rule'),
            const SizedBox(height: 12),
            _multiline(_partialClose, 'Partial close rule'),
            const SizedBox(height: 12),
            _multiline(_breakEven, 'Break even rule'),
            const SizedBox(height: 12),
            _multiline(_exampleNotes, 'Example setup notes'),
            const SizedBox(height: 12),
            SwitchListTile(
              value: _isActive,
              contentPadding: EdgeInsets.zero,
              title: Text(context.l10n.text('Active strategy')),
              onChanged: (value) => setState(() => _isActive = value),
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton.icon(
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(context.l10n.text('Save')),
          ),
        ),
      ),
    );
  }

  TextFormField _text(
    TextEditingController controller,
    String label,
    String hint,
  ) {
    return TextFormField(
      controller: controller,
      textCapitalization: TextCapitalization.characters,
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        hintText: hint,
      ),
    );
  }

  TextFormField _multiline(
    TextEditingController controller,
    String label, {
    String? hint,
    bool required = false,
  }) {
    return TextFormField(
      controller: controller,
      minLines: 2,
      maxLines: 6,
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        hintText: hint,
        alignLabelWithHint: true,
      ),
      validator: required ? _required : null,
    );
  }

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    final now = DateTime.now();
    final existing = widget.record?.strategy;
    final strategy = TradingStrategy(
      name: _name.text.trim(),
      description: _description.text.trim(),
      source: existing?.source ?? '',
      strategyType: existing?.strategyType ?? '',
      questionnaireVersion: existing?.questionnaireVersion ?? '',
      questionnaireAnswers: existing?.questionnaireAnswers ?? const {},
      traderProfileSummary: existing?.traderProfileSummary ?? '',
      suitableTraderPersonality: existing?.suitableTraderPersonality ?? '',
      recommendedMarket: existing?.recommendedMarket ?? '',
      technicalConcept: existing?.technicalConcept ?? '',
      riskManagementRules: existing?.riskManagementRules ?? '',
      riskPercent: existing?.riskPercent,
      maxDailyLossPercent: existing?.maxDailyLossPercent,
      maxTradesPerDay: existing?.maxTradesPerDay,
      minimumRiskRewardRatio: existing?.minimumRiskRewardRatio,
      psychologyRules: existing?.psychologyRules ?? '',
      personalityWarnings: existing?.personalityWarnings ?? '',
      noTradeConditions: existing?.noTradeConditions ?? '',
      preTradeChecklist: existing?.preTradeChecklist ?? '',
      postTradeReviewRules: existing?.postTradeReviewRules ?? '',
      improvementNotes: existing?.improvementNotes ?? '',
      tradingStyle: _tradingStyle,
      marketCondition: _marketCondition,
      analysisMethods: _analysisMethods.toList()..sort(),
      majorTimeframe: _majorTimeframe.text.trim().toUpperCase(),
      internalTimeframe: _internalTimeframe.text.trim().toUpperCase(),
      entryTimeframe: _entryTimeframe.text.trim().toUpperCase(),
      entryModelRules: _entryRules.text.trim(),
      entryConfirmationChecklist: _lines(_checklist.text),
      stopLossRule: _stopLoss.text.trim(),
      takeProfitRule: _takeProfit.text.trim(),
      riskRewardTarget: _rr.text.trim(),
      invalidationRule: _invalidation.text.trim(),
      tradeManagementRule: _management.text.trim(),
      partialCloseRule: _partialClose.text.trim(),
      breakEvenRule: _breakEven.text.trim(),
      exampleSetupNotes: _exampleNotes.text.trim(),
      isActive: _isActive,
    );
    final record = TradingStrategyRecord(
      id: widget.record?.id ?? 'strategy-${now.microsecondsSinceEpoch}',
      strategy: strategy,
      createdAt: widget.record?.createdAt ?? now,
      updatedAt: now,
    );
    if (mounted) {
      Navigator.pop(context, record);
    }
  }
}

const tradingStrategyStyles = [
  'Scalping',
  'Intraday',
  'Swing',
  'Position Trading',
  'Ranging Market',
  'Trending Market',
  'Hybrid',
];

const tradingStrategyMarketConditions = [
  'Trending',
  'Ranging',
  'Breakout',
  'Reversal',
  'Continuation',
  'Hybrid',
];

const tradingStrategyAnalysisMethods = [
  'SMC',
  'SNR',
  'Market Structure',
  'Trendline',
  'EMA',
  'MACD',
  'RSI',
  'Fibonacci',
  'Order Block',
  'FVG',
  'Liquidity',
  'VWAP',
  'Price Action',
  'Other',
];

List<String> _lines(String value) => value
    .split('\n')
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toList();

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(icon, size: 16),
    label: Text(label),
    visualDensity: VisualDensity.compact,
  );
}
