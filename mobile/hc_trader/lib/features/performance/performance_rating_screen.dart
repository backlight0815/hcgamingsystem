import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/permission_service.dart';
import '../../models/app_user.dart';
import '../../models/performance_rating.dart';
import '../../models/trading_date_filter.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_date_filter_session.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';
import '../../shared/trading_date_filter_bar.dart';
import '../access/access_required_view.dart';

class PerformanceRatingScreen extends StatefulWidget {
  const PerformanceRatingScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<PerformanceRatingScreen> createState() =>
      _PerformanceRatingScreenState();
}

class _PerformanceRatingScreenState extends State<PerformanceRatingScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  var _recalculating = false;
  late TradingDateFilter _dateFilter;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _dateFilter = TradingDateFilterSession.read(
      widget.user.uid,
      timezone: widget.user.timezone,
      timezoneOffsetMinutes: widget.user.timezoneOffsetMinutes,
    );
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!PermissionService(
      widget.user,
    ).can(AppPermission.performanceRatingReadOwn)) {
      return Scaffold(
        appBar: AppBar(
          leading: const AppBackButton(),
          title: Text(context.l10n.text('Performance Ratings')),
        ),
        body: AccessRequiredView(
          featureName: 'Performance Ratings',
          user: widget.user,
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Performance Ratings')),
        actions: [
          IconButton(
            tooltip: context.l10n.text('Recalculate ratings'),
            onPressed: _recalculating ? null : _recalculate,
            icon: _recalculating
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
          ),
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: context.l10n.text('Daily Ratings')),
            Tab(text: context.l10n.text('Weekly Ratings')),
          ],
        ),
      ),
      body: StreamBuilder<List<PerformanceRating>>(
        stream: widget.repository.performanceRatingsFor(widget.user.uid),
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(
              child: Text(
                context.l10n.text(AppFeedback.messageFor(snapshot.error!)),
              ),
            );
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final ratings = snapshot.data!
              .where((rating) => _dateFilter.matches(rating.periodStart))
              .toList(growable: false);
          final daily = ratings
              .where(
                (rating) => rating.periodType == PerformanceRatingPeriod.daily,
              )
              .toList();
          final weekly = ratings
              .where(
                (rating) => rating.periodType == PerformanceRatingPeriod.weekly,
              )
              .toList();
          final years =
              snapshot.data!
                  .map((rating) => rating.periodStart.year)
                  .toSet()
                  .toList()
                ..sort((left, right) => right.compareTo(left));
          return Column(
            children: [
              TradingDateFilterBar(
                filter: _dateFilter,
                availableYears: years,
                onChanged: (value) {
                  TradingDateFilterSession.write(widget.user.uid, value);
                  setState(() => _dateFilter = value);
                },
              ),
              Expanded(
                child: TabBarView(
                  controller: _tabs,
                  children: [
                    _RatingList(ratings: daily),
                    _RatingList(ratings: weekly),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _recalculate() async {
    setState(() => _recalculating = true);
    try {
      await widget.repository.recalculatePerformanceRatings(
        uid: widget.user.uid,
      );
      if (mounted) {
        AppFeedback.success(context, 'Performance ratings recalculated.');
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _recalculating = false);
    }
  }
}

class _RatingList extends StatelessWidget {
  const _RatingList({required this.ratings});

  final List<PerformanceRating> ratings;

  @override
  Widget build(BuildContext context) {
    if (ratings.isEmpty) {
      return EmptyState(
        icon: Icons.insights_outlined,
        title: context.l10n.text('No performance ratings yet.'),
        description: context.l10n.text(
          'Ratings are created after journal, plan, or psychology data is available.',
        ),
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: ratings.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) => _RatingCard(
        rating: ratings[index],
        previous: index + 1 < ratings.length ? ratings[index + 1] : null,
      ),
    );
  }
}

class _RatingCard extends StatelessWidget {
  const _RatingCard({required this.rating, this.previous});

  final PerformanceRating rating;
  final PerformanceRating? previous;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final difference = previous == null
        ? null
        : rating.overallScore - previous!.overallScore;
    return Card(
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
        leading: CircleAvatar(
          child: Text(rating.overallScore.round().toString()),
        ),
        title: Text(
          context.l10n.text(_bandLabel(rating.ratingBand)),
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w900,
          ),
        ),
        subtitle: Text(
          '${formatDate(rating.periodStart)} · '
          '${context.l10n.text(rating.dataSource == 'mt5' ? 'MT5 data' : 'Manual trading data')}',
        ),
        trailing: difference == null
            ? null
            : Semantics(
                label: context.l10n.text('Previous-period change'),
                child: Chip(
                  avatar: Icon(
                    difference >= 0 ? Icons.trending_up : Icons.trending_down,
                    size: 18,
                  ),
                  label: Text(
                    '${difference >= 0 ? '+' : ''}${difference.toStringAsFixed(1)}',
                  ),
                ),
              ),
        children: [
          _ScoreRow('Trading Result Score', rating.tradingResultScore),
          _ScoreRow('Trading Discipline Score', rating.tradingDisciplineScore),
          _ScoreRow('Psychology Score', rating.psychologyScore),
          _ScoreRow(
            'Trading-Plan Compliance Score',
            rating.tradingPlanComplianceScore,
          ),
          if (rating.periodType == PerformanceRatingPeriod.weekly)
            _ScoreRow('Consistency Score', rating.consistencyScore),
          const Divider(height: 28),
          _MetadataRow(
            label: 'Evaluated trades',
            value: '${rating.evaluatedTradeIds.length}',
          ),
          _MetadataRow(
            label: 'Excluded records',
            value: '${rating.excludedRecordIds.length}',
          ),
          _MetadataRow(
            label: 'Trading-plan version',
            value: rating.tradingPlanVersionId?.trim().isNotEmpty == true
                ? rating.tradingPlanVersionId!
                : context.l10n.text('Not Available'),
          ),
          _MetadataRow(label: 'Timezone', value: rating.timezone),
          _MetadataRow(
            label: 'Algorithm version',
            value: rating.algorithmVersion,
          ),
          _EvidenceSection(
            title: 'Strengths',
            icon: Icons.check_circle_outline,
            values: rating.positiveBehaviours,
            emptyLabel: 'No confirmed strengths for this period.',
          ),
          _EvidenceSection(
            title: 'Violations',
            icon: Icons.warning_amber_outlined,
            values: rating.violations,
            emptyLabel: 'No confirmed violations.',
          ),
          _EvidenceSection(
            title: 'Missing Information',
            icon: Icons.help_outline,
            values: rating.missingData,
            emptyLabel: 'No missing information.',
          ),
          _EvidenceSection(
            title: 'Improvement Recommendations',
            icon: Icons.tips_and_updates_outlined,
            values: rating.recommendations,
            emptyLabel: 'Keep following the active trading plan.',
          ),
        ],
      ),
    );
  }
}

class _ScoreRow extends StatelessWidget {
  const _ScoreRow(this.label, this.score);

  final String label;
  final double? score;

  @override
  Widget build(BuildContext context) {
    final available = score != null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final labelWidget = Text(context.l10n.text(label));
          final valueWidget = Text(
            available ? score!.round().toString() : 'N/A',
            textAlign: TextAlign.end,
          );
          final progress = LinearProgressIndicator(
            value: available ? score! / 100 : 0,
            minHeight: 9,
          );
          return Semantics(
            label: context.l10n.text(label),
            value: available ? '${score!.round()} out of 100' : 'Not Available',
            child: constraints.maxWidth < 520
                ? Column(
                    children: [
                      Row(
                        children: [
                          Expanded(child: labelWidget),
                          const SizedBox(width: 10),
                          valueWidget,
                        ],
                      ),
                      const SizedBox(height: 6),
                      progress,
                    ],
                  )
                : Row(
                    children: [
                      SizedBox(width: 210, child: labelWidget),
                      Expanded(child: progress),
                      const SizedBox(width: 10),
                      SizedBox(width: 42, child: valueWidget),
                    ],
                  ),
          );
        },
      ),
    );
  }
}

class _MetadataRow extends StatelessWidget {
  const _MetadataRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text(context.l10n.text(label))),
        Expanded(child: Text(value, textAlign: TextAlign.end)),
      ],
    ),
  );
}

class _EvidenceSection extends StatelessWidget {
  const _EvidenceSection({
    required this.title,
    required this.icon,
    required this.values,
    required this.emptyLabel,
  });

  final String title;
  final IconData icon;
  final List<String> values;
  final String emptyLabel;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 20),
            const SizedBox(width: 8),
            Text(
              context.l10n.text(title),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 6),
        if (values.isEmpty)
          Text(context.l10n.text(emptyLabel))
        else
          for (final value in values)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text('• ${context.l10n.text(_evidenceLabel(value))}'),
            ),
      ],
    ),
  );
}

String _bandLabel(String band) => switch (band) {
  'excellent' => 'Excellent',
  'good' => 'Good',
  'developing' => 'Developing',
  'needs_attention' => 'Needs Attention',
  _ => 'High Risk',
};

String _evidenceLabel(String code) =>
    const <String, String>{
      'missing_stop_loss': 'Trading without a confirmed stop loss',
      'risk_per_trade_exceeded': 'Maximum risk per trade exceeded',
      'daily_loss_limit_exceeded': 'Daily loss limit exceeded',
      'maximum_trades_exceeded': 'Maximum number of trades exceeded',
      'possible_revenge_trading': 'Possible revenge-trading pattern',
      'psychology_check_missing': 'Psychology check not completed',
      'entry_rule_not_confirmed': 'Entry rule was not confirmed',
      'risk_reward_below_plan': 'Risk-to-reward was below the trading plan',
      'positive_realised_result': 'Positive realised trading result',
      'rules_followed_without_detected_violation':
          'No rule violation was detected',
      'psychology_check_completed_before_trading':
          'Psychology check completed before trading',
      'trading_plan_missing': 'No active trading plan was available',
      'no_trades_for_period': 'No trades for this period',
      'no_closed_trades': 'No closed trades for this period',
      'stop_loss_data_missing': 'Stop-loss information is incomplete',
      'capital_basis_missing': 'Trading-plan capital basis is missing',
      'trade_plan_link_missing': 'Trade is not linked to a trading plan',
      'strategy_link_missing': 'Trade strategy information is missing',
      'plan_checklist_incomplete': 'Trading-plan checklist is incomplete',
      'psychology_check_completed_after_trading':
          'Psychology check was completed after trading began',
      'instrument_not_allowed': 'Instrument is not allowed by the plan',
      'trading_day_not_allowed': 'Trading day is not allowed by the plan',
      'trading_plan_not_followed': 'Trading plan was not followed',
      'strategy_not_allowed': 'Strategy is not allowed by the plan',
      'create_active_trading_plan': 'Create and activate a trading plan',
      'always_define_stop_loss': 'Define a stop loss before every trade',
      'respect_maximum_trades': 'Respect the maximum trades per day',
      'stop_at_daily_loss_limit': 'Stop trading at the daily loss limit',
      'reduce_risk_per_trade': 'Reduce risk per trade to the planned limit',
      'pause_after_a_loss': 'Pause and reassess after a loss',
      'complete_psychology_check_before_trading':
          'Complete the psychology check before trading',
    }[code] ??
    code;
