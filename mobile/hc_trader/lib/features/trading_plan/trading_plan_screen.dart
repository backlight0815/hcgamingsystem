import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/standalone_models.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/app_back_button.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';
import 'trading_plan_recommendation_screen.dart';

class TradingPlanScreen extends StatefulWidget {
  const TradingPlanScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<TradingPlanScreen> createState() => _TradingPlanScreenState();
}

class _TradingPlanScreenState extends State<TradingPlanScreen> {
  var _plans = <TradingPlanRecord>[];
  var _strategies = <TradingStrategyRecord>[];
  var _loading = true;

  @override
  void initState() {
    super.initState();
    unawaited(_loadRecords());
  }

  Future<void> _loadRecords() async {
    late final List<TradingPlanRecord> plans;
    late final List<TradingStrategyRecord> strategies;
    try {
      plans = await widget.repository.loadTradingPlanRecords(widget.user.uid);
      strategies = await widget.repository.loadTradingStrategyRecords(
        widget.user.uid,
      );
    } catch (_) {
      plans = _plans;
      strategies = _strategies;
    }
    if (!mounted) return;
    setState(() {
      _plans = plans;
      _strategies = strategies;
      _loading = false;
    });
  }

  Future<void> _createPlan() async {
    final record = await Navigator.of(context).push<TradingPlanRecord>(
      MaterialPageRoute(
        builder: (_) => _TradingPlanEditorScreen(
          userId: widget.user.uid,
          strategies: _strategies,
          onSave: (record) => widget.repository.upsertTradingPlanRecord(
            uid: widget.user.uid,
            record: record,
          ),
        ),
      ),
    );
    if (record == null) return;
    final updated = [
      record,
      for (final plan in _plans)
        record.plan.isDefault ? plan.withDefault(false) : plan,
    ];
    if (!mounted) return;
    setState(() => _plans = updated);
    AppFeedback.success(context, 'Trading plan saved.');
    unawaited(_loadRecords());
  }

  Future<void> _generateRecommendedPlan() async {
    final record = await Navigator.of(context).push<TradingPlanRecord>(
      MaterialPageRoute(
        builder: (_) => TradingPlanRecommendationScreen(
          userId: widget.user.uid,
          storage: widget.storage,
          onSave: (record) => widget.repository.upsertTradingPlanRecord(
            uid: widget.user.uid,
            record: record,
          ),
        ),
      ),
    );
    if (record == null) return;
    final updated = [
      record,
      for (final plan in _plans)
        record.plan.isDefault ? plan.withDefault(false) : plan,
    ];
    if (!mounted) return;
    setState(() => _plans = updated);
    AppFeedback.success(context, 'Recommended trading plan saved.');
    unawaited(_loadRecords());
  }

  Future<void> _editPlan(TradingPlanRecord record) async {
    final updatedRecord = await Navigator.of(context).push<TradingPlanRecord>(
      MaterialPageRoute(
        builder: (_) => _TradingPlanEditorScreen(
          userId: widget.user.uid,
          record: record,
          strategies: _strategies,
          onSave: (record) => widget.repository.upsertTradingPlanRecord(
            uid: widget.user.uid,
            record: record,
          ),
        ),
      ),
    );
    if (updatedRecord == null) return;
    final updated = [
      for (final plan in _plans)
        if (plan.id == updatedRecord.id)
          updatedRecord
        else if (updatedRecord.plan.isDefault)
          plan.withDefault(false)
        else
          plan,
    ]..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    if (!mounted) return;
    setState(() => _plans = updated);
    AppFeedback.success(context, 'Trading plan updated.');
    unawaited(_loadRecords());
  }

  Future<void> _deletePlan(TradingPlanRecord record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Delete trading plan?')),
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
      await widget.repository.deleteTradingPlanRecord(
        uid: widget.user.uid,
        planId: record.id,
      );
      final updated = _plans.where((plan) => plan.id != record.id).toList();
      if (!mounted) return;
      setState(() => _plans = updated);
      AppFeedback.success(context, 'Trading plan deleted.');
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'delete trading plan');
      }
    }
  }

  void _openDetail(TradingPlanRecord record) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _TradingPlanDetailScreen(
          record: record,
          onEdit: () => _editPlan(record),
          onDelete: () => _deletePlan(record),
        ),
      ),
    );
  }

  void _openGuideline() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => const _TradingPlanGuidelineScreen(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Plan')),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createPlan,
        icon: const Icon(Icons.add),
        label: Text(context.l10n.text('New plan')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
        children: [
          _TradingPlanGuidelineCard(onTap: _openGuideline),
          const SizedBox(height: 12),
          _TradingPlanActionCard(
            icon: Icons.edit_note_outlined,
            title: context.l10n.text('Create Own Trading Plan'),
            subtitle: context.l10n.text(
              'Build your own personal rulebook manually using risk, psychology, session, and discipline rules.',
            ),
            onTap: _createPlan,
          ),
          const SizedBox(height: 10),
          _TradingPlanActionCard(
            icon: Icons.auto_awesome_outlined,
            title: context.l10n.text('Generate Recommended Trading Plan'),
            subtitle: context.l10n.text(
              'Builds your full trading rulebook including risk, psychology, discipline, and routine.',
            ),
            onTap: _generateRecommendedPlan,
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_plans.isEmpty)
            EmptyState(
              icon: Icons.rule_folder_outlined,
              title: context.l10n.text('No trading plans yet'),
              action: FilledButton.icon(
                onPressed: _createPlan,
                icon: const Icon(Icons.add),
                label: Text(context.l10n.text('Create trading plan')),
              ),
            )
          else
            for (var index = 0; index < _plans.length; index++) ...[
              _TradingPlanCard(
                record: _plans[index],
                onTap: () => _openDetail(_plans[index]),
                onEdit: () => _editPlan(_plans[index]),
                onDelete: () => _deletePlan(_plans[index]),
              ),
              if (index != _plans.length - 1) const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }
}

class _TradingPlanGuidelineCard extends StatelessWidget {
  const _TradingPlanGuidelineCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const CircleAvatar(child: Icon(Icons.lightbulb_outline)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.text('How to Define Your Trading Plan'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.l10n.text(
                        'Start simple, follow your rules, and improve the plan through your journal.',
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _TradingPlanActionCard extends StatelessWidget {
  const _TradingPlanActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

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
        onTap: onTap,
      ),
    );
  }
}

class _TradingPlanGuidelineScreen extends StatelessWidget {
  const _TradingPlanGuidelineScreen();

  @override
  Widget build(BuildContext context) {
    final bulletItems = [
      'Market/session you trade',
      'Strategy name',
      'Entry conditions',
      'Stop loss rules',
      'Take profit rules',
      'Risk per trade',
      'Maximum daily loss',
      'Confirmation rules',
      'No-trade conditions',
      'Post-trade review notes',
    ];
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('How to Define Your Trading Plan')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          _GuidelineSection(
            title: 'What is a trading plan?',
            body:
                'A trading plan is a written set of rules that guides when you enter, manage, and exit a trade. It helps you avoid random decisions and keeps your trading consistent.',
          ),
          _GuidelineSection(
            title: 'Why do you need a trading plan?',
            body:
                'Without a trading plan, traders often rely on emotion, fear, greed, or FOMO. A trading plan helps you trade based on rules instead of mood.',
          ),
          _GuidelineSection(
            title: 'What should your trading plan include?',
            bullets: bulletItems,
          ),
          _GuidelineSection(
            title: 'Example trading plan',
            body:
                'Example: I only trade XAUUSD during London or New York session. I only enter when price reaches my key level, confirms market structure, and gives a valid rejection or breakout retest. I risk maximum 1% per trade and stop trading after 2 losses in one day.',
          ),
          _GuidelineSection(
            title: 'Reminder',
            body:
                'Your trading plan does not need to be perfect at the beginning. Start simple, follow it consistently, and improve it through your trading journal.',
          ),
        ],
      ),
    );
  }
}

class _GuidelineSection extends StatelessWidget {
  const _GuidelineSection({required this.title, this.body, this.bullets});

  final String title;
  final String? body;
  final List<String>? bullets;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            if (body != null) ...[
              const SizedBox(height: 8),
              Text(context.l10n.text(body!)),
            ],
            if (bullets != null) ...[
              const SizedBox(height: 8),
              for (final item in bullets!)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('- '),
                      Expanded(child: Text(context.l10n.text(item))),
                    ],
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TradingPlanCard extends StatelessWidget {
  const _TradingPlanCard({
    required this.record,
    required this.onTap,
    required this.onEdit,
    required this.onDelete,
  });

  final TradingPlanRecord record;
  final VoidCallback onTap;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final plan = record.plan;
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
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      plan.planName.trim().isEmpty
                          ? context.l10n.text('Untitled plan')
                          : _localizedPlanContent(
                              context,
                              plan.planName,
                              plan.isRecommended,
                            ),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  if (plan.isDefault)
                    Chip(
                      label: Text(context.l10n.text('Default')),
                      visualDensity: VisualDensity.compact,
                    ),
                  if (plan.isActive)
                    Chip(
                      label: Text(context.l10n.text('Active')),
                      visualDensity: VisualDensity.compact,
                    ),
                  if (plan.isRecommended)
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
                  _PlanChip(
                    icon: Icons.show_chart,
                    label: _marketLabel(context, plan),
                  ),
                  if (plan.timeframe.trim().isNotEmpty)
                    _PlanChip(
                      icon: Icons.schedule,
                      label: _localizedPlanContent(
                        context,
                        plan.timeframe,
                        plan.isRecommended,
                      ),
                    ),
                  _PlanChip(
                    icon: Icons.shield_outlined,
                    label: context.l10n.text('Risk {value}%', {
                      'value': plan.riskPercent.toStringAsFixed(2),
                    }),
                  ),
                  if (plan.allowedStrategyIds.isNotEmpty)
                    _PlanChip(
                      icon: Icons.auto_graph_outlined,
                      label: context.l10n.text('{count} strategies', {
                        'count': plan.allowedStrategyIds.length,
                      }),
                    ),
                  _PlanChip(
                    icon: Icons.update,
                    label: context.l10n.text('Updated {date}', {
                      'date': formatDate(record.updatedAt),
                    }),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _PreviewLine(
                label: context.l10n.text('Entry rules'),
                value: plan.entryCriteria,
                localize: plan.isRecommended,
              ),
              _PreviewLine(
                label: context.l10n.text('Exit rules'),
                value: plan.exitRules,
                localize: plan.isRecommended,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TradingPlanDetailScreen extends StatelessWidget {
  const _TradingPlanDetailScreen({
    required this.record,
    required this.onEdit,
    required this.onDelete,
  });

  final TradingPlanRecord record;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final plan = record.plan;
    final localizeGenerated = plan.isRecommended;
    final planName = plan.planName.trim().isEmpty
        ? context.l10n.text('Untitled plan')
        : _localizedPlanContent(context, plan.planName, localizeGenerated);
    String detail(String value) =>
        _detailFallback(context, value, localize: localizeGenerated);
    String content(String value) =>
        _localizedPlanContent(context, value, localizeGenerated);
    final market = _marketText(plan).trim();
    final isGeneratedRulebook = plan.source == 'plan_questionnaire';
    final noTradeRules = _joinBlocks([
      plan.noTradeConditions,
      plan.fomoPreventionRules,
      plan.revengeTradingPreventionRules,
    ]);
    final preTradeChecklist = _joinBlocks([
      plan.preTradeChecklist,
      plan.checklistBeforeEntry,
    ]);
    final postTradeReview = _joinBlocks([
      plan.postTradeReviewRules,
      plan.postTradeReviewRoutine,
      plan.checklistAfterTrade,
    ]);
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Plan Details')),
        actions: [
          IconButton(
            tooltip: context.l10n.text('Edit'),
            onPressed: () {
              Navigator.pop(context);
              onEdit();
            },
            icon: const Icon(Icons.edit_outlined),
          ),
          IconButton(
            tooltip: context.l10n.text('Delete'),
            onPressed: () {
              Navigator.pop(context);
              onDelete();
            },
            icon: const Icon(Icons.delete_outline),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          _PlanDetailHeroCard(
            title: planName,
            description: detail(plan.description),
            plan: plan,
            onEdit: () {
              Navigator.pop(context);
              onEdit();
            },
            onDelete: () {
              Navigator.pop(context);
              onDelete();
            },
          ),
          const SizedBox(height: 12),
          _PlanMetricGrid(
            stats: [
              _PlanDetailStat(
                icon: Icons.percent,
                label: context.l10n.text('Risk per trade'),
                value: '${plan.riskPercent.toStringAsFixed(2)}%',
              ),
              _PlanDetailStat(
                icon: Icons.shield_outlined,
                label: context.l10n.text('Daily max loss'),
                value: '${plan.dailyMaxLossPercent.toStringAsFixed(2)}%',
              ),
              _PlanDetailStat(
                icon: Icons.rule_outlined,
                label: context.l10n.text('Minimum RR'),
                value: '1:${plan.minimumRiskReward.toStringAsFixed(2)}',
              ),
              _PlanDetailStat(
                icon: Icons.format_list_numbered,
                label: context.l10n.text('Max trades/day'),
                value: '${plan.maxTradesPerDay}',
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.account_circle_outlined,
            title: context.l10n.text('Trader Profile Summary'),
            children: [
              _DetailRow(
                context.l10n.text('Generated profile'),
                detail(plan.generatedProfileSummary),
              ),
              _DetailRow(
                context.l10n.text('Trading style'),
                detail(plan.tradingStyle),
              ),
              if (!isGeneratedRulebook)
                _DetailRow(
                  context.l10n.text('Market / symbol'),
                  market.isEmpty
                      ? context.l10n.text('Not specified')
                      : content(market),
                ),
              _DetailRow(
                context.l10n.text('Capital basis'),
                context.l10n.text(_capitalBasisLabel(plan.capitalBasis)),
              ),
              _DetailRow(
                context.l10n.text('Risk profile'),
                context.l10n.text(_riskProfileLabel(plan.riskPercent)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.candlestick_chart_outlined,
            title: context.l10n.text(
              isGeneratedRulebook ? 'Strategy Selection' : 'Strategy Overview',
            ),
            children: [
              _DetailRow(
                context.l10n.text(
                  isGeneratedRulebook ? 'Selected Strategy' : 'Strategy type',
                ),
                detail(plan.strategyType),
              ),
              _DetailRow(
                context.l10n.text('Allowed strategies'),
                plan.allowedStrategyNames.isEmpty
                    ? context.l10n.text('No linked strategy')
                    : plan.allowedStrategyNames.map(content).join(', '),
              ),
              _DetailRow(
                context.l10n.text(
                  isGeneratedRulebook ? 'Session Rule' : 'Timeframe',
                ),
                detail(plan.timeframe),
              ),
              _DetailRow(
                context.l10n.text('Template'),
                context.l10n.text(_templateLabel(plan.templateKey)),
              ),
              _RuleList(
                title: context.l10n.text(
                  isGeneratedRulebook
                      ? 'Entry Permission Rules'
                      : 'Technical Rules',
                ),
                body: detail(plan.technicalRules),
                localize: localizeGenerated,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.security_outlined,
            title: context.l10n.text('Risk Management'),
            children: [
              _DetailRow(
                context.l10n.text('Weekly max loss'),
                plan.weeklyMaxLossPercent == null
                    ? context.l10n.text('Not specified')
                    : '${plan.weeklyMaxLossPercent!.toStringAsFixed(2)}%',
              ),
              _DetailRow(
                context.l10n.text('Monthly max loss'),
                plan.monthlyMaxLossPercent == null
                    ? context.l10n.text('Not specified')
                    : '${plan.monthlyMaxLossPercent!.toStringAsFixed(2)}%',
              ),
              _DetailRow(
                context.l10n.text('Max losses/day'),
                '${plan.maxLossesPerDay}',
              ),
              _DetailRow(
                context.l10n.text('Max consecutive losses'),
                '${plan.maxConsecutiveLosses}',
              ),
              _DetailRow(
                context.l10n.text('Max open trades'),
                '${plan.maxOpenTrades}',
              ),
              _DetailRow(
                context.l10n.text('Cooldown after loss'),
                context.l10n.text('{minutes} minutes', {
                  'minutes': plan.cooldownAfterLossMinutes,
                }),
              ),
              if (plan.accountCapital != null)
                _DetailRow(
                  context.l10n.text('Account capital'),
                  formatMoney(plan.accountCapital!),
                ),
              if (plan.maxLossPercent != null)
                _DetailRow(
                  context.l10n.text('Maximum loss limit'),
                  '${plan.maxLossPercent!.toStringAsFixed(2)}%',
                ),
              if (plan.effectiveRiskCapital != null)
                _DetailRow(
                  context.l10n.text('Effective risk capital'),
                  formatMoney(plan.effectiveRiskCapital!),
                ),
              _RuleList(
                title: context.l10n.text('Risk Management Rules'),
                body: detail(plan.riskManagementRules),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Account rules'),
                body: detail(plan.accountRules),
                localize: localizeGenerated,
              ),
              if (plan.propFirmRules.trim().isNotEmpty)
                _RuleList(
                  title: context.l10n.text('Prop firm rules'),
                  body: plan.propFirmRules,
                  localize: localizeGenerated,
                ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.login_outlined,
            title: context.l10n.text('Entry Rules'),
            children: [
              _RuleList(
                title: context.l10n.text('Entry rules'),
                body: detail(plan.entryCriteria),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Pre-Trade Checklist'),
                body: detail(preTradeChecklist),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('News filter rule'),
                body: detail(plan.newsFilterRule),
                localize: localizeGenerated,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.logout_outlined,
            title: context.l10n.text(
              isGeneratedRulebook ? 'Loss Recovery Rules' : 'Exit Rules',
            ),
            children: [
              _RuleList(
                title: context.l10n.text(
                  isGeneratedRulebook ? 'Loss Recovery Rules' : 'Exit rules',
                ),
                body: detail(plan.exitRules),
                localize: localizeGenerated,
              ),
              if (!isGeneratedRulebook)
                _RuleList(
                  title: context.l10n.text('Stop Loss Rules'),
                  body: detail(plan.stopLossRules),
                  localize: localizeGenerated,
                ),
              if (!isGeneratedRulebook)
                _RuleList(
                  title: context.l10n.text('Take Profit Rules'),
                  body: detail(plan.takeProfitRules),
                  localize: localizeGenerated,
                ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.psychology_alt_outlined,
            title: context.l10n.text('Psychology and Discipline'),
            children: [
              _RuleList(
                title: context.l10n.text('Psychology Rules'),
                body: detail(plan.psychologyRules),
                localize: localizeGenerated,
              ),
              if (plan.personalityWarnings.trim().isNotEmpty)
                _WarningRuleList(
                  title: context.l10n.text('Personality-Based Warnings'),
                  body: plan.personalityWarnings,
                  localize: localizeGenerated,
                ),
              _RuleList(
                title: context.l10n.text('FOMO prevention rules'),
                body: detail(plan.fomoPreventionRules),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Revenge trading prevention rules'),
                body: detail(plan.revengeTradingPreventionRules),
                localize: localizeGenerated,
              ),
              if (plan.disciplineRules.trim().isNotEmpty)
                _RuleList(
                  title: context.l10n.text('Discipline Rules'),
                  body: plan.disciplineRules,
                  localize: localizeGenerated,
                ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.block_outlined,
            title: context.l10n.text('No-Trade Conditions'),
            children: [
              _WarningRuleList(
                title: context.l10n.text('When to skip trading'),
                body: noTradeRules.trim().isEmpty
                    ? context.l10n.text(
                        'No trade when rules are unclear, risk cannot be calculated, or the daily limit is reached.',
                      )
                    : noTradeRules,
                localize: localizeGenerated,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.event_available_outlined,
            title: context.l10n.text('Routine and Review'),
            children: [
              _RuleList(
                title: context.l10n.text('Daily routine before trading'),
                body: detail(plan.dailyRoutine),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Post-trade review routine'),
                body: detail(postTradeReview),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Weekly review routine'),
                body: detail(plan.weeklyReviewRoutine),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('What to do after win'),
                body: detail(plan.afterWinRules),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('What to do after loss'),
                body: detail(plan.afterLossRules),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('What to do after breaking rules'),
                body: detail(plan.afterBreakingRules),
                localize: localizeGenerated,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PlanDetailCard(
            icon: Icons.notes_outlined,
            title: context.l10n.text('Notes'),
            children: [
              _RuleList(
                title: context.l10n.text('Recommended Improvement Notes'),
                body: detail(plan.improvementNotes),
                localize: localizeGenerated,
              ),
              _RuleList(
                title: context.l10n.text('Review notes'),
                body: detail(plan.reviewNotes),
                localize: localizeGenerated,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _DetailChip(
                icon: Icons.calendar_today_outlined,
                label: context.l10n.text('Created {date}', {
                  'date': formatDate(record.createdAt),
                }),
              ),
              _DetailChip(
                icon: Icons.update,
                label: context.l10n.text('Updated {date}', {
                  'date': formatDate(record.updatedAt),
                }),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TradingPlanEditorScreen extends StatefulWidget {
  const _TradingPlanEditorScreen({
    required this.userId,
    required this.strategies,
    required this.onSave,
    this.record,
  });

  final String userId;
  final TradingPlanRecord? record;
  final List<TradingStrategyRecord> strategies;
  final Future<void> Function(TradingPlanRecord record) onSave;

  @override
  State<_TradingPlanEditorScreen> createState() =>
      _TradingPlanEditorScreenState();
}

class _TradingPlanEditorScreenState extends State<_TradingPlanEditorScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _accountCapital;
  late final TextEditingController _maxLossPercent;
  late final TextEditingController _effectiveCapital;
  late final TextEditingController _tradingStyle;
  late final TextEditingController _strategyType;
  late final TextEditingController _technicalRules;
  late final TextEditingController _market;
  late final TextEditingController _timeframe;
  late final TextEditingController _entryRules;
  late final TextEditingController _exitRules;
  late final TextEditingController _stopLossRules;
  late final TextEditingController _takeProfitRules;
  late final TextEditingController _entryChecklist;
  late final TextEditingController _afterTradeChecklist;
  late final TextEditingController _risk;
  late final TextEditingController _dailyLoss;
  late final TextEditingController _weeklyLoss;
  late final TextEditingController _monthlyLoss;
  late final TextEditingController _maxTrades;
  late final TextEditingController _maxLosses;
  late final TextEditingController _maxConsecutiveLosses;
  late final TextEditingController _maxOpenTrades;
  late final TextEditingController _rr;
  late final TextEditingController _cooldown;
  late final TextEditingController _newsFilter;
  late final TextEditingController _accountRules;
  late final TextEditingController _riskManagementRules;
  late final TextEditingController _propFirmRules;
  late final TextEditingController _psychologyRules;
  late final TextEditingController _personalityWarnings;
  late final TextEditingController _noTradeConditions;
  late final TextEditingController _fomoRules;
  late final TextEditingController _revengeRules;
  late final TextEditingController _afterWin;
  late final TextEditingController _afterLoss;
  late final TextEditingController _afterBreaking;
  late final TextEditingController _dailyRoutine;
  late final TextEditingController _postReview;
  late final TextEditingController _weeklyReview;
  late final TextEditingController _disciplineRules;
  late final TextEditingController _improvementNotes;
  late final TextEditingController _notes;
  final _sessions = <String>{};
  final _allowedStrategyIds = <String>{};
  var _templateKey = 'custom';
  var _capitalBasis = 'custom_capital';
  var _isDefault = false;
  var _isActive = true;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final plan = widget.record?.plan ?? const TradingPlan();
    _templateKey = _templateExists(plan.templateKey)
        ? plan.templateKey
        : 'custom';
    _capitalBasis = _capitalBasisExists(plan.capitalBasis)
        ? plan.capitalBasis
        : 'custom_capital';
    _name = TextEditingController(text: plan.planName);
    _description = TextEditingController(text: plan.description);
    _accountCapital = TextEditingController(
      text: _optionalNumberText(plan.accountCapital),
    );
    _maxLossPercent = TextEditingController(
      text: _optionalNumberText(plan.maxLossPercent),
    );
    _effectiveCapital = TextEditingController(
      text: _optionalNumberText(plan.effectiveRiskCapital),
    );
    _tradingStyle = TextEditingController(text: plan.tradingStyle);
    _strategyType = TextEditingController(text: plan.strategyType);
    _technicalRules = TextEditingController(text: plan.technicalRules);
    _market = TextEditingController(text: _marketText(plan));
    _timeframe = TextEditingController(text: plan.timeframe);
    _entryRules = TextEditingController(text: plan.entryCriteria);
    _exitRules = TextEditingController(text: plan.exitRules);
    _stopLossRules = TextEditingController(text: plan.stopLossRules);
    _takeProfitRules = TextEditingController(text: plan.takeProfitRules);
    _entryChecklist = TextEditingController(text: plan.checklistBeforeEntry);
    _afterTradeChecklist = TextEditingController(
      text: plan.checklistAfterTrade,
    );
    _risk = TextEditingController(text: '${plan.riskPercent}');
    _dailyLoss = TextEditingController(text: '${plan.dailyMaxLossPercent}');
    _weeklyLoss = TextEditingController(
      text: plan.weeklyMaxLossPercent == null
          ? ''
          : '${plan.weeklyMaxLossPercent}',
    );
    _monthlyLoss = TextEditingController(
      text: plan.monthlyMaxLossPercent == null
          ? ''
          : '${plan.monthlyMaxLossPercent}',
    );
    _maxTrades = TextEditingController(text: '${plan.maxTradesPerDay}');
    _maxLosses = TextEditingController(text: '${plan.maxLossesPerDay}');
    _maxConsecutiveLosses = TextEditingController(
      text: '${plan.maxConsecutiveLosses}',
    );
    _maxOpenTrades = TextEditingController(text: '${plan.maxOpenTrades}');
    _rr = TextEditingController(text: '${plan.minimumRiskReward}');
    _cooldown = TextEditingController(text: '${plan.cooldownAfterLossMinutes}');
    _newsFilter = TextEditingController(text: plan.newsFilterRule);
    _accountRules = TextEditingController(text: plan.accountRules);
    _riskManagementRules = TextEditingController(
      text: plan.riskManagementRules,
    );
    _propFirmRules = TextEditingController(text: plan.propFirmRules);
    _psychologyRules = TextEditingController(text: plan.psychologyRules);
    _personalityWarnings = TextEditingController(
      text: plan.personalityWarnings,
    );
    _noTradeConditions = TextEditingController(text: plan.noTradeConditions);
    _fomoRules = TextEditingController(text: plan.fomoPreventionRules);
    _revengeRules = TextEditingController(
      text: plan.revengeTradingPreventionRules,
    );
    _afterWin = TextEditingController(text: plan.afterWinRules);
    _afterLoss = TextEditingController(text: plan.afterLossRules);
    _afterBreaking = TextEditingController(text: plan.afterBreakingRules);
    _dailyRoutine = TextEditingController(text: plan.dailyRoutine);
    _postReview = TextEditingController(text: plan.postTradeReviewRoutine);
    _weeklyReview = TextEditingController(text: plan.weeklyReviewRoutine);
    _disciplineRules = TextEditingController(text: plan.disciplineRules);
    _improvementNotes = TextEditingController(text: plan.improvementNotes);
    _notes = TextEditingController(text: plan.reviewNotes);
    _sessions.addAll(plan.allowedSessions);
    _allowedStrategyIds.addAll(plan.allowedStrategyIds);
    _isDefault = plan.isDefault;
    _isActive = plan.isActive;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _description,
      _accountCapital,
      _maxLossPercent,
      _effectiveCapital,
      _tradingStyle,
      _strategyType,
      _technicalRules,
      _market,
      _timeframe,
      _entryRules,
      _exitRules,
      _stopLossRules,
      _takeProfitRules,
      _entryChecklist,
      _afterTradeChecklist,
      _risk,
      _dailyLoss,
      _weeklyLoss,
      _monthlyLoss,
      _maxTrades,
      _maxLosses,
      _maxConsecutiveLosses,
      _maxOpenTrades,
      _rr,
      _cooldown,
      _newsFilter,
      _accountRules,
      _riskManagementRules,
      _propFirmRules,
      _psychologyRules,
      _personalityWarnings,
      _noTradeConditions,
      _fomoRules,
      _revengeRules,
      _afterWin,
      _afterLoss,
      _afterBreaking,
      _dailyRoutine,
      _postReview,
      _weeklyReview,
      _disciplineRules,
      _improvementNotes,
      _notes,
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
            widget.record == null ? 'Create trading plan' : 'Edit trading plan',
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
                labelText: context.l10n.text('Plan name'),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            _multiline(_description, 'Description'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _templateKey,
              decoration: InputDecoration(
                labelText: context.l10n.text('Trading plan template'),
                helperText: context.l10n.text(
                  'Templates only prefill suggestions. You can edit every field.',
                ),
              ),
              items: [
                for (final template in _tradingPlanTemplates)
                  DropdownMenuItem(
                    value: template.key,
                    child: Text(context.l10n.text(template.label)),
                  ),
              ],
              onChanged: _saving
                  ? null
                  : (value) {
                      if (value == null) return;
                      _applyTemplate(value);
                    },
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(_templateDescription(_templateKey)),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _capitalBasis,
              decoration: InputDecoration(
                labelText: context.l10n.text('Capital basis'),
                helperText: context.l10n.text(
                  'Choose what the risk percentages should be based on.',
                ),
              ),
              items: [
                for (final item in _capitalBasisOptions)
                  DropdownMenuItem(
                    value: item.key,
                    child: Text(context.l10n.text(item.label)),
                  ),
              ],
              onChanged: _saving
                  ? null
                  : (value) {
                      if (value == null) return;
                      setState(() {
                        _capitalBasis = value;
                        _recalculateEffectiveCapital();
                      });
                    },
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _number(
                    _accountCapital,
                    'Account capital',
                    optional: true,
                    helperText:
                        'Full broker balance, prop firm account size, or your chosen custom amount.',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _number(
                    _maxLossPercent,
                    'Max loss %',
                    optional: true,
                    helperText:
                        'For prop firm plans, this converts account size into effective risk capital.',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _number(
              _effectiveCapital,
              'Effective risk capital',
              optional: true,
              helperText:
                  'Use this amount when sizing risk if it differs from account balance.',
            ),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: _saving
                    ? null
                    : () => setState(_recalculateEffectiveCapital),
                icon: const Icon(Icons.refresh),
                label: Text(context.l10n.text('Recalculate effective capital')),
              ),
            ),
            SwitchListTile(
              value: _isDefault,
              contentPadding: EdgeInsets.zero,
              title: Text(context.l10n.text('Default plan')),
              onChanged: (value) => setState(() => _isDefault = value),
            ),
            SwitchListTile(
              value: _isActive,
              contentPadding: EdgeInsets.zero,
              title: Text(context.l10n.text('Active plan')),
              onChanged: (value) => setState(() => _isActive = value),
            ),
            const SizedBox(height: 12),
            Text(
              context.l10n.text('Allowed strategies'),
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            if (widget.strategies.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  context.l10n.text(
                    'No strategies yet. You can link them after creating strategies.',
                  ),
                ),
              )
            else
              Wrap(
                spacing: 8,
                children: [
                  for (final strategy in widget.strategies)
                    FilterChip(
                      label: Text(strategy.strategy.name),
                      selected: _allowedStrategyIds.contains(strategy.id),
                      onSelected: (selected) => setState(
                        () => selected
                            ? _allowedStrategyIds.add(strategy.id)
                            : _allowedStrategyIds.remove(strategy.id),
                      ),
                    ),
                ],
              ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _market,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: context.l10n.text('Allowed symbols / markets'),
                hintText: context.l10n.text(
                  'Example: XAUUSD, Forex, Indices, Crypto, US Stocks',
                ),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _timeframe,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: context.l10n.text('Timeframe'),
                hintText: 'H1, H4, Daily',
              ),
            ),
            const SizedBox(height: 12),
            _multiline(_entryRules, 'Entry rules', required: true),
            const SizedBox(height: 12),
            _multiline(_exitRules, 'Exit rules', required: true),
            const SizedBox(height: 12),
            _multiline(_stopLossRules, 'Stop Loss Rules'),
            const SizedBox(height: 12),
            _multiline(_takeProfitRules, 'Take Profit Rules'),
            const SizedBox(height: 12),
            _multiline(
              _entryChecklist,
              'Checklist before entry',
              helperText:
                  'Example: trend checked, risk calculated, news checked, setup matches plan.',
            ),
            const SizedBox(height: 12),
            _multiline(
              _afterTradeChecklist,
              'Checklist after trade',
              helperText:
                  'Example: screenshot saved, result logged, mistake and lesson reviewed.',
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _number(_risk, 'Risk per trade %')),
                const SizedBox(width: 10),
                Expanded(child: _number(_rr, 'Minimum RR')),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _number(_dailyLoss, 'Daily max loss %')),
                const SizedBox(width: 10),
                Expanded(
                  child: _number(
                    _weeklyLoss,
                    'Weekly max loss %',
                    optional: true,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _number(_monthlyLoss, 'Monthly max loss %', optional: true),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _number(_maxTrades, 'Max trades/day')),
                const SizedBox(width: 10),
                Expanded(child: _number(_maxLosses, 'Max losses/day')),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _number(
                    _maxConsecutiveLosses,
                    'Max consecutive losses',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(child: _number(_maxOpenTrades, 'Max open trades')),
              ],
            ),
            const SizedBox(height: 12),
            _number(_cooldown, 'Cooldown after loss (minutes)'),
            const SizedBox(height: 12),
            TextFormField(
              controller: _tradingStyle,
              decoration: InputDecoration(
                labelText: context.l10n.text('Trading style'),
                helperText: context.l10n.text(
                  'Examples: Scalping, Intraday, Swing, Position Trading.',
                ),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _strategyType,
              decoration: InputDecoration(
                labelText: context.l10n.text('Strategy type'),
                helperText: context.l10n.text(
                  'Examples: price action, trend continuation, breakout retest, SMC.',
                ),
              ),
            ),
            const SizedBox(height: 12),
            _multiline(_technicalRules, 'Technical Strategy Rules'),
            const SizedBox(height: 16),
            Text(
              context.l10n.text('Allowed trading sessions'),
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final session in const [
                  'Asian',
                  'London',
                  'New York',
                  'Custom',
                ])
                  FilterChip(
                    label: Text(context.l10n.text(session)),
                    selected: _sessions.contains(session),
                    onSelected: (selected) => setState(
                      () => selected
                          ? _sessions.add(session)
                          : _sessions.remove(session),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            _multiline(_newsFilter, 'News filter rule'),
            const SizedBox(height: 12),
            _multiline(_accountRules, 'Account rules'),
            const SizedBox(height: 12),
            _multiline(_riskManagementRules, 'Risk Management Rules'),
            const SizedBox(height: 12),
            _multiline(_propFirmRules, 'Prop firm rules'),
            const SizedBox(height: 12),
            _multiline(_psychologyRules, 'Psychology rules'),
            const SizedBox(height: 12),
            _multiline(_personalityWarnings, 'Personality-Based Warnings'),
            const SizedBox(height: 12),
            _multiline(_noTradeConditions, 'No-Trade Conditions'),
            const SizedBox(height: 12),
            _multiline(_fomoRules, 'FOMO prevention rules'),
            const SizedBox(height: 12),
            _multiline(_revengeRules, 'Revenge trading prevention rules'),
            const SizedBox(height: 12),
            _multiline(_afterWin, 'What to do after win'),
            const SizedBox(height: 12),
            _multiline(_afterLoss, 'What to do after loss'),
            const SizedBox(height: 12),
            _multiline(_afterBreaking, 'What to do after breaking rules'),
            const SizedBox(height: 12),
            _multiline(_dailyRoutine, 'Daily routine before trading'),
            const SizedBox(height: 12),
            _multiline(_postReview, 'Post-trade review routine'),
            const SizedBox(height: 12),
            _multiline(_weeklyReview, 'Weekly review routine'),
            const SizedBox(height: 12),
            _multiline(_disciplineRules, 'Discipline Rules'),
            const SizedBox(height: 12),
            _multiline(_improvementNotes, 'Recommended Improvement Notes'),
            const SizedBox(height: 12),
            _multiline(_notes, 'Review notes'),
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

  TextFormField _number(
    TextEditingController controller,
    String label, {
    bool optional = false,
    String? helperText,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        helperText: helperText == null ? null : context.l10n.text(helperText),
      ),
      validator: (value) {
        if (optional && (value ?? '').trim().isEmpty) return null;
        return (double.tryParse((value ?? '').trim()) ?? 0) > 0
            ? null
            : context.l10n.text('Required');
      },
    );
  }

  TextFormField _multiline(
    TextEditingController controller,
    String label, {
    bool required = false,
    String? helperText,
  }) {
    return TextFormField(
      controller: controller,
      minLines: 2,
      maxLines: 6,
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        helperText: helperText == null ? null : context.l10n.text(helperText),
        alignLabelWithHint: true,
      ),
      validator: required ? _required : null,
    );
  }

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  void _applyTemplate(String key) {
    final template = _templateByKey(key);
    setState(() {
      _templateKey = template.key;
      if (template.key == 'custom') return;
      _name.text = context.l10n.text(template.planName);
      _description.text = context.l10n.text(template.description);
      _capitalBasis = template.capitalBasis;
      _accountCapital.text = _optionalNumberText(template.accountCapital);
      _maxLossPercent.text = _optionalNumberText(template.maxLossPercent);
      _market.text = template.market;
      _timeframe.text = template.timeframe;
      _entryRules.text = context.l10n.text(template.entryRules);
      _exitRules.text = context.l10n.text(template.exitRules);
      _entryChecklist.text = context.l10n.text(template.entryChecklist);
      _afterTradeChecklist.text = context.l10n.text(
        template.afterTradeChecklist,
      );
      _risk.text = _optionalNumberText(template.riskPercent);
      _dailyLoss.text = _optionalNumberText(template.dailyLossPercent);
      _weeklyLoss.text = _optionalNumberText(template.weeklyLossPercent);
      _monthlyLoss.text = _optionalNumberText(template.monthlyLossPercent);
      _maxTrades.text = '${template.maxTradesPerDay}';
      _maxLosses.text = '${template.maxLossesPerDay}';
      _maxConsecutiveLosses.text = '${template.maxConsecutiveLosses}';
      _maxOpenTrades.text = '${template.maxOpenTrades}';
      _rr.text = _optionalNumberText(template.minimumRiskReward);
      _cooldown.text = '${template.cooldownMinutes}';
      _sessions
        ..clear()
        ..addAll(template.sessions);
      _newsFilter.text = context.l10n.text(template.newsFilterRule);
      _accountRules.text = context.l10n.text(template.accountRules);
      _propFirmRules.text = context.l10n.text(template.propFirmRules);
      _psychologyRules.text = context.l10n.text(template.psychologyRules);
      _fomoRules.text = context.l10n.text(template.fomoRules);
      _revengeRules.text = context.l10n.text(template.revengeRules);
      _afterWin.text = context.l10n.text(template.afterWinRules);
      _afterLoss.text = context.l10n.text(template.afterLossRules);
      _afterBreaking.text = context.l10n.text(template.afterBreakingRules);
      _dailyRoutine.text = context.l10n.text(template.dailyRoutine);
      _postReview.text = context.l10n.text(template.postTradeReviewRoutine);
      _weeklyReview.text = context.l10n.text(template.weeklyReviewRoutine);
      _tradingStyle.text = context.l10n.text(template.tradingStyle);
      _strategyType.text = context.l10n.text(template.strategyType);
      _technicalRules.text = context.l10n.text(template.entryRules);
      _notes.text = context.l10n.text(template.suggestionReason);
      _recalculateEffectiveCapital();
    });
  }

  void _recalculateEffectiveCapital() {
    final capital = _optionalDouble(_accountCapital.text);
    final maxLoss = _optionalDouble(_maxLossPercent.text);
    if (capital == null || capital <= 0) return;
    final effective =
        _capitalBasis == 'prop_firm_max_loss' && maxLoss != null && maxLoss > 0
        ? capital * maxLoss / 100
        : capital;
    _effectiveCapital.text = _optionalNumberText(effective);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    final now = DateTime.now();
    final markets = _market.text
        .split(',')
        .map((item) => item.trim().toUpperCase())
        .where((item) => item.isNotEmpty)
        .toList();
    final allowedStrategies = widget.strategies
        .where((item) => _allowedStrategyIds.contains(item.id))
        .toList();
    final plan = TradingPlan(
      strategy: _name.text.trim(),
      description: _description.text.trim(),
      isDefault: _isDefault,
      isActive: _isActive,
      planType: widget.record?.plan.planType ?? 'manual',
      source: widget.record?.plan.source ?? '',
      questionnaireVersion: widget.record?.plan.questionnaireVersion ?? '',
      questionnaireAnswers:
          widget.record?.plan.questionnaireAnswers ?? const <String, String>{},
      generatedProfileSummary:
          widget.record?.plan.generatedProfileSummary ??
          _description.text.trim(),
      templateKey: _templateKey,
      capitalBasis: _capitalBasis,
      accountCapital: _optionalDouble(_accountCapital.text),
      maxLossPercent: _optionalDouble(_maxLossPercent.text),
      effectiveRiskCapital: _optionalDouble(_effectiveCapital.text),
      tradingStyle: _tradingStyle.text.trim(),
      strategyType: _strategyType.text.trim(),
      technicalRules: _technicalRules.text.trim(),
      allowedStrategyIds: _allowedStrategyIds.toList()..sort(),
      allowedStrategyNames: [
        for (final strategy in allowedStrategies) strategy.strategy.name,
      ],
      market: _market.text.trim().toUpperCase(),
      timeframe: _timeframe.text.trim().toUpperCase(),
      entryCriteria: _entryRules.text.trim(),
      exitRules: _exitRules.text.trim(),
      stopLossRules: _stopLossRules.text.trim(),
      takeProfitRules: _takeProfitRules.text.trim(),
      checklistBeforeEntry: _entryChecklist.text.trim(),
      checklistAfterTrade: _afterTradeChecklist.text.trim(),
      riskManagementRules: _riskManagementRules.text.trim(),
      riskPercent: double.parse(_risk.text.trim()),
      dailyMaxLossPercent: double.parse(_dailyLoss.text.trim()),
      weeklyMaxLossPercent: _optionalDouble(_weeklyLoss.text),
      monthlyMaxLossPercent: _optionalDouble(_monthlyLoss.text),
      maxTradesPerDay: double.parse(_maxTrades.text.trim()).round(),
      maxLossesPerDay: double.parse(_maxLosses.text.trim()).round(),
      maxConsecutiveLosses: double.parse(
        _maxConsecutiveLosses.text.trim(),
      ).round(),
      maxOpenTrades: double.parse(_maxOpenTrades.text.trim()).round(),
      minimumRiskReward: double.parse(_rr.text.trim()),
      cooldownAfterLossMinutes: double.parse(_cooldown.text.trim()).round(),
      allowedSessions: _sessions.toList()..sort(),
      allowedPairs: markets,
      newsFilterRule: _newsFilter.text.trim(),
      accountRules: _accountRules.text.trim(),
      propFirmRules: _propFirmRules.text.trim(),
      psychologyRules: _psychologyRules.text.trim(),
      personalityWarnings: _personalityWarnings.text.trim(),
      noTradeConditions: _noTradeConditions.text.trim(),
      preTradeChecklist: _entryChecklist.text.trim(),
      postTradeReviewRules: _postReview.text.trim(),
      disciplineRules: _disciplineRules.text.trim(),
      improvementNotes: _improvementNotes.text.trim(),
      fomoPreventionRules: _fomoRules.text.trim(),
      revengeTradingPreventionRules: _revengeRules.text.trim(),
      afterWinRules: _afterWin.text.trim(),
      afterLossRules: _afterLoss.text.trim(),
      afterBreakingRules: _afterBreaking.text.trim(),
      dailyRoutine: _dailyRoutine.text.trim(),
      postTradeReviewRoutine: _postReview.text.trim(),
      weeklyReviewRoutine: _weeklyReview.text.trim(),
      reviewNotes: _notes.text.trim(),
    );
    final record = TradingPlanRecord(
      id: widget.record?.id ?? 'plan-${now.microsecondsSinceEpoch}',
      userId: widget.record?.userId ?? widget.userId,
      plan: plan,
      createdAt: widget.record?.createdAt ?? now,
      updatedAt: now,
    );
    try {
      await widget.onSave(record);
      if (mounted) {
        Navigator.pop(context, record);
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'save trading plan');
        setState(() => _saving = false);
      }
    }
  }
}

class _PlanTemplate {
  const _PlanTemplate({
    required this.key,
    required this.label,
    required this.description,
    required this.planName,
    required this.suggestionReason,
    this.capitalBasis = 'custom_capital',
    this.accountCapital = 10000,
    this.maxLossPercent,
    this.market = 'XAUUSD, Forex, Indices',
    this.timeframe = 'H1, H4',
    this.riskPercent = 0.5,
    this.dailyLossPercent = 3,
    this.weeklyLossPercent = 6,
    this.monthlyLossPercent = 10,
    this.maxTradesPerDay = 3,
    this.maxLossesPerDay = 2,
    this.maxConsecutiveLosses = 3,
    this.maxOpenTrades = 2,
    this.minimumRiskReward = 2,
    this.cooldownMinutes = 30,
    this.sessions = const ['London', 'New York'],
    this.tradingStyle = 'Intraday',
    this.strategyType = 'Price action confirmation',
    this.entryRules =
        'Only enter when trend, level, confirmation, and risk are aligned.',
    this.exitRules =
        'Place stop loss at invalidation and take profit at planned levels.',
    this.entryChecklist =
        'Trend checked, key level marked, news checked, risk calculated, setup matches plan.',
    this.afterTradeChecklist =
        'Screenshot saved, result logged, mistake reviewed, next action written.',
    this.newsFilterRule =
        'Avoid new entries shortly before high-impact news unless the plan specifically allows it.',
    this.accountRules =
        'Stop trading when daily risk limit or max losing trades is reached.',
    this.propFirmRules =
        'Monitor daily loss, maximum loss, consistency, and minimum trading days.',
    this.psychologyRules =
        'Trade only when calm enough to follow the written checklist.',
    this.fomoRules =
        'Do not chase missed moves. Wait for the next planned setup.',
    this.revengeRules =
        'After a loss, pause and take the next trade only if it fully qualifies.',
    this.afterWinRules =
        'After a win, keep the same risk and wait for the next valid setup.',
    this.afterLossRules =
        'After a loss, review whether rules were followed before trading again.',
    this.afterBreakingRules =
        'If a rule is broken, stop trading for the session and record the lesson.',
    this.dailyRoutine =
        'Check calendar, mark levels, confirm risk limits, and review the checklist.',
    this.postTradeReviewRoutine =
        'Record entry, exit, screenshot, emotion, rule compliance, and lesson.',
    this.weeklyReviewRoutine =
        'Review statistics, repeated mistakes, best setups, and one improvement focus.',
  });

  final String key;
  final String label;
  final String description;
  final String planName;
  final String suggestionReason;
  final String capitalBasis;
  final double accountCapital;
  final double? maxLossPercent;
  final String market;
  final String timeframe;
  final double riskPercent;
  final double dailyLossPercent;
  final double weeklyLossPercent;
  final double monthlyLossPercent;
  final int maxTradesPerDay;
  final int maxLossesPerDay;
  final int maxConsecutiveLosses;
  final int maxOpenTrades;
  final double minimumRiskReward;
  final int cooldownMinutes;
  final List<String> sessions;
  final String tradingStyle;
  final String strategyType;
  final String entryRules;
  final String exitRules;
  final String entryChecklist;
  final String afterTradeChecklist;
  final String newsFilterRule;
  final String accountRules;
  final String propFirmRules;
  final String psychologyRules;
  final String fomoRules;
  final String revengeRules;
  final String afterWinRules;
  final String afterLossRules;
  final String afterBreakingRules;
  final String dailyRoutine;
  final String postTradeReviewRoutine;
  final String weeklyReviewRoutine;
}

class _CapitalBasisOption {
  const _CapitalBasisOption(this.key, this.label);

  final String key;
  final String label;
}

const _capitalBasisOptions = [
  _CapitalBasisOption('broker_balance', 'Broker capital balance'),
  _CapitalBasisOption(
    'prop_firm_max_loss',
    'Prop firm maximum loss limit as capital',
  ),
  _CapitalBasisOption('custom_capital', 'Custom capital amount'),
];

const _tradingPlanTemplates = [
  _PlanTemplate(
    key: 'personal_live',
    label: 'Personal Live Trading Account',
    description: 'Use broker balance as capital with moderate day-to-day risk.',
    planName: 'Personal live account plan',
    suggestionReason:
        'Suggested for live accounts: protect capital first, then scale after consistent execution.',
    capitalBasis: 'broker_balance',
    accountCapital: 5000,
    maxLossPercent: 10,
    riskPercent: 0.75,
  ),
  _PlanTemplate(
    key: 'broker_balance',
    label: 'Broker Capital Balance based plan',
    description: 'Base every risk limit on the current broker balance.',
    planName: 'Broker balance risk plan',
    suggestionReason:
        'Suggested when broker balance is the amount you are willing to manage directly.',
    capitalBasis: 'broker_balance',
    accountCapital: 10000,
    riskPercent: 1,
    dailyLossPercent: 3,
    weeklyLossPercent: 7,
    monthlyLossPercent: 12,
    maxTradesPerDay: 4,
  ),
  _PlanTemplate(
    key: 'prop_challenge',
    label: 'Prop Firm Challenge Plan',
    description:
        'Use the prop firm maximum loss limit as the effective capital base.',
    planName: 'Prop firm challenge plan',
    suggestionReason:
        'Suggested for challenges: a 10,000 account with 10% max loss uses 1,000 as effective risk capital.',
    capitalBasis: 'prop_firm_max_loss',
    accountCapital: 10000,
    maxLossPercent: 10,
    riskPercent: 1,
    dailyLossPercent: 20,
    weeklyLossPercent: 40,
    monthlyLossPercent: 100,
    maxTradesPerDay: 3,
    maxLossesPerDay: 2,
    maxOpenTrades: 1,
    minimumRiskReward: 2,
    propFirmRules:
        'Use maximum loss as effective capital, track daily loss, avoid news violations, and stop before breach risk.',
  ),
  _PlanTemplate(
    key: 'prop_funded',
    label: 'Prop Firm Funded Account Plan',
    description:
        'Protect funded status by using smaller risk on the effective drawdown allowance.',
    planName: 'Prop firm funded account plan',
    suggestionReason:
        'Suggested for funded accounts: keep risk smaller because account survival matters more than fast growth.',
    capitalBasis: 'prop_firm_max_loss',
    accountCapital: 100000,
    maxLossPercent: 10,
    riskPercent: 0.5,
    dailyLossPercent: 10,
    weeklyLossPercent: 25,
    monthlyLossPercent: 50,
    maxTradesPerDay: 3,
    maxLossesPerDay: 1,
    maxOpenTrades: 1,
  ),
  _PlanTemplate(
    key: 'conservative',
    label: 'Conservative Risk Plan',
    description: 'Slow, defensive plan for beginners or drawdown recovery.',
    planName: 'Conservative risk plan',
    suggestionReason:
        'Suggested when consistency and confidence are more important than growth speed.',
    riskPercent: 0.25,
    dailyLossPercent: 1,
    weeklyLossPercent: 3,
    monthlyLossPercent: 5,
    maxTradesPerDay: 2,
    maxLossesPerDay: 1,
    minimumRiskReward: 2.5,
    cooldownMinutes: 60,
    tradingStyle: 'Intraday or swing',
  ),
  _PlanTemplate(
    key: 'aggressive',
    label: 'Aggressive Growth Plan',
    description:
        'Higher activity plan for experienced traders who can control drawdown.',
    planName: 'Aggressive growth plan',
    suggestionReason:
        'Suggested only for experienced traders with proven discipline and clear stop rules.',
    riskPercent: 1.5,
    dailyLossPercent: 5,
    weeklyLossPercent: 10,
    monthlyLossPercent: 20,
    maxTradesPerDay: 5,
    maxLossesPerDay: 3,
    maxOpenTrades: 3,
    minimumRiskReward: 1.5,
    cooldownMinutes: 20,
    tradingStyle: 'Intraday',
  ),
  _PlanTemplate(
    key: 'custom',
    label: 'Custom Plan',
    description: 'Start blank and build your own rules.',
    planName: 'Custom trading plan',
    suggestionReason:
        'Custom plan selected. Fill the fields based on your own account rules.',
    market: 'XAUUSD, Forex, Indices',
    timeframe: 'H1, H4',
    maxConsecutiveLosses: 3,
    sessions: ['London', 'New York'],
    strategyType: 'Price action confirmation',
    entryRules:
        'Only enter when trend, level, confirmation, and risk are aligned.',
    exitRules:
        'Place stop loss at invalidation and take profit at planned levels.',
    entryChecklist:
        'Trend checked, key level marked, news checked, risk calculated, setup matches plan.',
    afterTradeChecklist:
        'Screenshot saved, result logged, mistake reviewed, next action written.',
    newsFilterRule:
        'Avoid new entries shortly before high-impact news unless the plan specifically allows it.',
    accountRules:
        'Stop trading when daily risk limit or max losing trades is reached.',
    psychologyRules:
        'Trade only when calm enough to follow the written checklist.',
    fomoRules: 'Do not chase missed moves. Wait for the next planned setup.',
    revengeRules:
        'After a loss, pause and take the next trade only if it fully qualifies.',
    afterWinRules:
        'After a win, keep the same risk and wait for the next valid setup.',
    afterLossRules:
        'After a loss, review whether rules were followed before trading again.',
    afterBreakingRules:
        'If a rule is broken, stop trading for the session and record the lesson.',
    dailyRoutine:
        'Check calendar, mark levels, confirm risk limits, and review the checklist.',
    postTradeReviewRoutine:
        'Record entry, exit, screenshot, emotion, rule compliance, and lesson.',
    weeklyReviewRoutine:
        'Review statistics, repeated mistakes, best setups, and one improvement focus.',
  ),
];

_PlanTemplate _templateByKey(String key) => _tradingPlanTemplates.firstWhere(
  (template) => template.key == key,
  orElse: () => _tradingPlanTemplates.last,
);

String _templateLabel(String key) => _templateByKey(key).label;

String _templateDescription(String key) => _templateByKey(key).description;

bool _templateExists(String key) =>
    _tradingPlanTemplates.any((template) => template.key == key);

String _capitalBasisLabel(String key) => _capitalBasisOptions
    .firstWhere(
      (option) => option.key == key,
      orElse: () => _capitalBasisOptions.last,
    )
    .label;

bool _capitalBasisExists(String key) =>
    _capitalBasisOptions.any((option) => option.key == key);

String _optionalNumberText(double? value) {
  if (value == null) return '';
  if (value == value.roundToDouble()) return value.toStringAsFixed(0);
  return value.toStringAsFixed(2);
}

String _detailFallback(
  BuildContext context,
  String value, {
  required bool localize,
}) {
  final text = value.trim();
  return text.isEmpty
      ? context.l10n.text('Not specified')
      : _localizedPlanContent(context, text, localize);
}

String _joinBlocks(Iterable<String> blocks) {
  return blocks
      .map((block) => block.trim())
      .where((block) => block.isNotEmpty)
      .join('\n');
}

List<String> _ruleLines(String body) {
  final lines = body
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .toList();
  return lines.isEmpty ? [body.trim()] : lines;
}

String _riskProfileLabel(double riskPercent) {
  if (riskPercent <= .5) return 'Conservative risk profile';
  if (riskPercent <= 1) return 'Balanced risk profile';
  return 'Aggressive risk profile';
}

class _PlanChip extends StatelessWidget {
  const _PlanChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(icon, size: 16),
    label: Text(label),
    visualDensity: VisualDensity.compact,
  );
}

class _PreviewLine extends StatelessWidget {
  const _PreviewLine({
    required this.label,
    required this.value,
    required this.localize,
  });

  final String label;
  final String value;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    if (value.trim().isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Text(
        '$label: ${_localizedBlock(context, value, localize: localize)}',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
    );
  }
}

class _PlanDetailHeroCard extends StatelessWidget {
  const _PlanDetailHeroCard({
    required this.title,
    required this.description,
    required this.plan,
    required this.onEdit,
    required this.onDelete,
  });

  final String title;
  final String description;
  final TradingPlan plan;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const CircleAvatar(child: Icon(Icons.rule_folder_outlined)),
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
                      Text(description),
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
                if (plan.isRecommended)
                  _DetailChip(
                    icon: Icons.auto_awesome_outlined,
                    label: context.l10n.text('Recommended'),
                  ),
                if (plan.isDefault)
                  _DetailChip(
                    icon: Icons.star_border,
                    label: context.l10n.text('Default'),
                  ),
                _DetailChip(
                  icon: plan.isActive
                      ? Icons.check_circle_outline
                      : Icons.pause_circle_outline,
                  label: context.l10n.text(
                    plan.isActive ? 'Active' : 'Inactive',
                  ),
                ),
                if (plan.source.trim().isNotEmpty)
                  _DetailChip(
                    icon: Icons.source_outlined,
                    label: context.l10n.text(plan.source),
                  ),
              ],
            ),
            const SizedBox(height: 14),
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

class _PlanMetricGrid extends StatelessWidget {
  const _PlanMetricGrid({required this.stats});

  final List<_PlanDetailStat> stats;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 640 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: stats.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: columns == 4 ? 1.6 : 1.45,
          ),
          itemBuilder: (context, index) => _PlanMetricTile(stat: stats[index]),
        );
      },
    );
  }
}

class _PlanDetailStat {
  const _PlanDetailStat({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;
}

class _PlanMetricTile extends StatelessWidget {
  const _PlanMetricTile({required this.stat});

  final _PlanDetailStat stat;

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.primary;
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Icon(stat.icon, color: color),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  stat.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 2),
                Text(
                  stat.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PlanDetailCard extends StatelessWidget {
  const _PlanDetailCard({
    required this.icon,
    required this.title,
    required this.children,
  });

  final IconData icon;
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 20),
                const SizedBox(width: 8),
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
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _RuleList extends StatelessWidget {
  const _RuleList({
    required this.title,
    required this.body,
    required this.localize,
  });

  final String title;
  final String body;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    final lines = _ruleLines(body);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          for (final line in lines)
            Padding(
              padding: const EdgeInsets.only(bottom: 5),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 2),
                    child: Icon(Icons.check_circle_outline, size: 16),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(_localizedPlanContent(context, line, localize)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _WarningRuleList extends StatelessWidget {
  const _WarningRuleList({
    required this.title,
    required this.body,
    required this.localize,
  });

  final String title;
  final String body;
  final bool localize;

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.error;
    final lines = _ruleLines(body);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          for (final line in lines)
            Padding(
              padding: const EdgeInsets.only(bottom: 5),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Icon(
                      Icons.warning_amber_outlined,
                      size: 16,
                      color: color,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(_localizedPlanContent(context, line, localize)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _DetailChip extends StatelessWidget {
  const _DetailChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(icon, size: 16),
    label: Text(label),
    visualDensity: VisualDensity.compact,
  );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final labelWidget = Text(
        label,
        style: const TextStyle(fontWeight: FontWeight.w800),
      );
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: constraints.maxWidth < 420
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [labelWidget, const SizedBox(height: 3), Text(value)],
              )
            : Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(width: 152, child: labelWidget),
                  const SizedBox(width: 12),
                  Expanded(child: Text(value)),
                ],
              ),
      );
    },
  );
}

String _marketLabel(BuildContext context, TradingPlan plan) {
  final text = _marketText(plan);
  return text.isEmpty
      ? context.l10n.text('N/A')
      : _localizedPlanContent(context, text, plan.isRecommended);
}

String _localizedBlock(
  BuildContext context,
  String value, {
  required bool localize,
}) {
  return value
      .split('\n')
      .map((line) => _localizedPlanContent(context, line.trim(), localize))
      .where((line) => line.trim().isNotEmpty)
      .join('\n');
}

String _localizedPlanContent(
  BuildContext context,
  String value,
  bool localize,
) {
  final text = value.trim();
  if (text.isEmpty) return text;
  return localize ? context.l10n.text(text) : text;
}

String _marketText(TradingPlan plan) {
  if (plan.market.trim().isNotEmpty) return plan.market.trim();
  return plan.allowedPairs.join(', ');
}

double? _optionalDouble(String value) {
  final text = value.trim();
  if (text.isEmpty) return null;
  return double.tryParse(text);
}
