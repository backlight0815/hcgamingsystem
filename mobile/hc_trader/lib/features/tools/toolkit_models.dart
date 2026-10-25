import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/standalone_models.dart';
import '../../models/trading_data_source.dart';
import '../../services/psychology_score_service.dart';

enum ToolkitCategory { planning, performance, propFirm, psychology }

extension ToolkitCategoryText on ToolkitCategory {
  String get titleKey => switch (this) {
    ToolkitCategory.planning => 'Plan and Prepare',
    ToolkitCategory.performance => 'Performance and Risk',
    ToolkitCategory.propFirm => 'Prop Firm Workspace',
    ToolkitCategory.psychology => 'Psychology and Discipline',
  };

  String get filterKey => switch (this) {
    ToolkitCategory.planning => 'Planning',
    ToolkitCategory.performance => 'Performance',
    ToolkitCategory.propFirm => 'Prop Firm',
    ToolkitCategory.psychology => 'Psychology',
  };
}

enum ToolkitSummaryKind {
  averagePrice,
  risk,
  statistics,
  rating,
  propFirm,
  multiAccountPlanner,
  psychology,
  plan,
  strategy,
}

class ToolkitItem {
  const ToolkitItem({
    required this.id,
    required this.titleKey,
    required this.descriptionKey,
    required this.searchKeywordsKey,
    required this.actionKey,
    required this.icon,
    required this.category,
    required this.summaryKind,
    this.requiresFullAccess = false,
    this.subcategoryKey,
    this.tagsKeys = const [],
    this.searchAliasesKeys = const [],
  });

  final String id;
  final String titleKey;
  final String descriptionKey;
  final String searchKeywordsKey;
  final String actionKey;
  final IconData icon;
  final ToolkitCategory category;
  final ToolkitSummaryKind summaryKind;
  final bool requiresFullAccess;
  final String? subcategoryKey;
  final List<String> tagsKeys;
  final List<String> searchAliasesKeys;
}

const toolkitCatalog = <ToolkitItem>[
  ToolkitItem(
    id: 'average_price',
    titleKey: 'Average Price & Break-Even',
    descriptionKey:
        'Weighted entries, fee-aware break-even, partial targets and what-if orders',
    searchKeywordsKey:
        'average price break even weighted entry fees cost basis partial take profit',
    actionKey: 'Calculate Average Price',
    icon: Icons.price_check_outlined,
    category: ToolkitCategory.planning,
    summaryKind: ToolkitSummaryKind.averagePrice,
    subcategoryKey: 'Position planning',
    tagsKeys: ['Weighted average', 'Break-even', 'Risk reward'],
    searchAliasesKeys: ['average entry', 'cost basis', 'breakeven calculator'],
  ),
  ToolkitItem(
    id: 'risk_calculator',
    titleKey: 'Risk Calculator',
    descriptionKey: 'Position size, pip distance, RR and projected P/L',
    searchKeywordsKey: 'risk position size calculator lot percentage',
    actionKey: 'Calculate Risk',
    icon: Icons.calculate_outlined,
    category: ToolkitCategory.planning,
    summaryKind: ToolkitSummaryKind.risk,
    subcategoryKey: 'Position sizing',
    tagsKeys: ['Risk management', 'Pip value'],
    searchAliasesKeys: ['lot size', 'risk reward', 'RR calculator'],
  ),
  ToolkitItem(
    id: 'statistics',
    titleKey: 'Statistics',
    descriptionKey: 'Performance, drawdown, expectancy and breakdowns',
    searchKeywordsKey: 'statistics analytics performance report drawdown',
    actionKey: 'View Statistics',
    icon: Icons.query_stats_outlined,
    category: ToolkitCategory.performance,
    summaryKind: ToolkitSummaryKind.statistics,
    subcategoryKey: 'Trading analytics',
    tagsKeys: ['Expectancy', 'Drawdown', 'Performance'],
    searchAliasesKeys: ['trade report', 'win rate', 'profit and loss'],
  ),
  ToolkitItem(
    id: 'performance_ratings',
    titleKey: 'Performance Ratings',
    descriptionKey: 'Daily and weekly discipline, psychology and plan scores',
    searchKeywordsKey: 'daily weekly performance rating discipline score',
    actionKey: 'View Ratings',
    icon: Icons.insights_outlined,
    category: ToolkitCategory.performance,
    summaryKind: ToolkitSummaryKind.rating,
    requiresFullAccess: true,
    subcategoryKey: 'Performance review',
    tagsKeys: ['Daily score', 'Weekly score', 'Discipline'],
    searchAliasesKeys: ['ratings', 'review score'],
  ),
  ToolkitItem(
    id: 'prop_firm',
    titleKey: 'Prop Firm',
    descriptionKey: 'Targets, limits, profitable days and phase status',
    searchKeywordsKey: 'prop firm challenge target drawdown phase',
    actionKey: 'Open Prop Firm',
    icon: Icons.account_balance_outlined,
    category: ToolkitCategory.propFirm,
    summaryKind: ToolkitSummaryKind.propFirm,
    requiresFullAccess: true,
    subcategoryKey: 'Challenge tracking',
    tagsKeys: ['Drawdown', 'Profit target', 'Trading limits'],
    searchAliasesKeys: ['funded account', 'funded trader', 'challenge'],
  ),
  ToolkitItem(
    id: 'multi_account_prop_firm',
    titleKey: 'Funded Account Rotation & Payout Planner',
    descriptionKey:
        'Portfolio safeguards, account rotation, payout planning and conservative recovery',
    searchKeywordsKey:
        'multi account prop firm portfolio rotation payout recovery cooldown discipline',
    actionKey: 'Open Rotation Planner',
    icon: Icons.account_tree_outlined,
    category: ToolkitCategory.propFirm,
    summaryKind: ToolkitSummaryKind.multiAccountPlanner,
    subcategoryKey: 'Multi-account planning',
    tagsKeys: ['Account rotation', 'Payout protection', 'Global risk stop'],
    searchAliasesKeys: [
      'funded account planner',
      'account rotation',
      'portfolio risk',
    ],
  ),
  ToolkitItem(
    id: 'psychology',
    titleKey: 'Psychology',
    descriptionKey: 'Daily check-in, warnings and discipline score',
    searchKeywordsKey: 'psychology discipline emotion check in readiness',
    actionKey: 'Daily Psychology Check',
    icon: Icons.psychology_outlined,
    category: ToolkitCategory.psychology,
    summaryKind: ToolkitSummaryKind.psychology,
    subcategoryKey: 'Trading readiness',
    tagsKeys: ['Emotion', 'Discipline', 'FOMO'],
    searchAliasesKeys: ['mindset', 'check in', 'readiness'],
  ),
  ToolkitItem(
    id: 'personality_guidance',
    titleKey: 'Self-Personality Test',
    descriptionKey:
        'Complete a four-dimension personality assessment and review optional trading guidance',
    searchKeywordsKey:
        'personality test guidance style strategy risk psychology discipline',
    actionKey: 'Start Assessment',
    icon: Icons.psychology_alt_outlined,
    category: ToolkitCategory.psychology,
    summaryKind: ToolkitSummaryKind.psychology,
    subcategoryKey: 'Personality assessment',
    tagsKeys: ['MBTI style', 'Trading guidance', 'Self reflection'],
    searchAliasesKeys: ['personality test', '16 personalities', 'self test'],
  ),
  ToolkitItem(
    id: 'trading_plan',
    titleKey: 'Trading Plan',
    descriptionKey: 'Risk, psychology, routines and allowed strategies',
    searchKeywordsKey: 'plan preparation routine rules checklist',
    actionKey: 'Open Trading Plan',
    icon: Icons.fact_check_outlined,
    category: ToolkitCategory.planning,
    summaryKind: ToolkitSummaryKind.plan,
    subcategoryKey: 'Trading preparation',
    tagsKeys: ['Risk rules', 'Routine', 'Checklist'],
    searchAliasesKeys: ['trade plan', 'daily plan'],
  ),
  ToolkitItem(
    id: 'trading_strategies',
    titleKey: 'Trading Strategies',
    descriptionKey: 'Entry, exit, SL/TP and setup rules',
    searchKeywordsKey: 'strategy entry exit setup stop loss take profit',
    actionKey: 'Open Trading Strategy',
    icon: Icons.auto_graph_outlined,
    category: ToolkitCategory.planning,
    summaryKind: ToolkitSummaryKind.strategy,
    subcategoryKey: 'Trading methods',
    tagsKeys: ['Entry rules', 'Exit rules', 'Setup'],
    searchAliasesKeys: ['strategy builder', 'trade setup', 'method'],
  ),
];

List<ToolkitItem> filterToolkitItems({
  required Iterable<ToolkitItem> items,
  required AppLocalizations localizations,
  String query = '',
  ToolkitCategory? category,
}) {
  final normalizedQuery = query.trim().toLowerCase().replaceAll(
    RegExp(r'\s+'),
    ' ',
  );
  return items
      .where((item) {
        if (category != null && item.category != category) return false;
        if (normalizedQuery.isEmpty) return true;
        return <String>[
              item.id.replaceAll('_', ' '),
              localizations.text(item.titleKey),
              localizations.text(item.descriptionKey),
              localizations.text(item.searchKeywordsKey),
              localizations.text(item.category.titleKey),
              localizations.text(item.category.filterKey),
              if (item.subcategoryKey != null)
                localizations.text(item.subcategoryKey!),
              ...item.tagsKeys.map(localizations.text),
              ...item.searchAliasesKeys.map(localizations.text),
            ]
            .join(' ')
            .toLowerCase()
            .replaceAll(RegExp(r'\s+'), ' ')
            .contains(normalizedQuery);
      })
      .toList(growable: false);
}

class ToolkitRecentEntry {
  const ToolkitRecentEntry({required this.toolId, required this.openedAt});

  final String toolId;
  final DateTime openedAt;

  factory ToolkitRecentEntry.fromJson(Map<String, dynamic> json) {
    return ToolkitRecentEntry(
      toolId: '${json['tool_id'] ?? ''}',
      openedAt:
          DateTime.tryParse('${json['opened_at'] ?? ''}') ??
          DateTime.fromMillisecondsSinceEpoch(0),
    );
  }

  Map<String, Object?> toJson() => {
    'tool_id': toolId,
    'opened_at': openedAt.toIso8601String(),
  };
}

class ToolkitRecentStore {
  ToolkitRecentStore(this.storage, {DateTime Function()? now})
    : _now = now ?? DateTime.now;

  static const _storageKey = 'toolkit_recent_tools';
  static const maxEntries = 4;

  final LocalAppStorage storage;
  final DateTime Function() _now;

  List<ToolkitRecentEntry> load(String userId) {
    final seen = <String>{};
    final entries =
        storage
            .readList(userId, _storageKey)
            .map(ToolkitRecentEntry.fromJson)
            .where((entry) => entry.toolId.isNotEmpty)
            .toList()
          ..sort((left, right) => right.openedAt.compareTo(left.openedAt));
    return entries
        .where((entry) => seen.add(entry.toolId))
        .take(maxEntries)
        .toList(growable: false);
  }

  Future<List<ToolkitRecentEntry>> record(String userId, String toolId) async {
    final updated = <ToolkitRecentEntry>[
      ToolkitRecentEntry(toolId: toolId, openedAt: _now()),
      for (final entry in load(userId))
        if (entry.toolId != toolId) entry,
    ].take(maxEntries).toList(growable: false);
    await storage.writeList(
      userId,
      _storageKey,
      updated.map((entry) => entry.toJson()).toList(growable: false),
    );
    return updated;
  }
}

class ToolkitDashboardSnapshot {
  const ToolkitDashboardSnapshot({
    required this.dataMode,
    this.riskPercent,
    this.riskSymbol = '',
    this.psychologyScore,
    this.psychologyStatus = '',
    this.psychologyCheckedToday = false,
    this.planName = '',
    this.planRiskPercent,
    this.planMinimumRiskReward,
    this.strategyName = '',
    this.strategyStyle = '',
    this.strategyRiskReward = '',
  });

  final TradingDataDisplayMode dataMode;
  final double? riskPercent;
  final String riskSymbol;
  final double? psychologyScore;
  final String psychologyStatus;
  final bool psychologyCheckedToday;
  final String planName;
  final double? planRiskPercent;
  final double? planMinimumRiskReward;
  final String strategyName;
  final String strategyStyle;
  final String strategyRiskReward;

  bool get hasPlan => planName.trim().isNotEmpty;
  bool get hasStrategy => strategyName.trim().isNotEmpty;
}

class ToolkitDashboardService {
  const ToolkitDashboardService({this.now});

  final DateTime Function()? now;

  ToolkitDashboardSnapshot load(LocalAppStorage storage, String userId) {
    final risk = storage.readMap(userId, 'last_risk_calculation');
    final checkIns = <PsychologyCheckIn>[];
    for (final value in storage.readList(userId, 'psychology_checkins')) {
      try {
        checkIns.add(PsychologyCheckIn.fromJson(value));
      } catch (_) {
        // A malformed local record must not block the remaining summaries.
      }
    }
    checkIns.sort((left, right) => right.createdAt.compareTo(left.createdAt));
    final latestCheckIn = checkIns.firstOrNull;
    final psychology = latestCheckIn == null
        ? null
        : const PsychologyScoreService().evaluate(latestCheckIn);
    final currentTime = (now ?? DateTime.now)();

    final plans = <TradingPlanRecord>[];
    for (final value in storage.readList(userId, 'trading_plans')) {
      try {
        final record = TradingPlanRecord.fromJson(value);
        if (record.plan.planName.trim().isNotEmpty) plans.add(record);
      } catch (_) {
        // Keep the workspace available when one cached plan is invalid.
      }
    }
    plans.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    if (plans.isEmpty) {
      final legacy = storage.readMap(userId, 'trading_plan');
      if (legacy != null) {
        final plan = TradingPlan.fromJson(legacy);
        if (plan.planName.trim().isNotEmpty) {
          plans.add(
            TradingPlanRecord(
              id: 'legacy-trading-plan',
              userId: userId,
              plan: plan,
              createdAt: currentTime,
              updatedAt: currentTime,
            ),
          );
        }
      }
    }
    final activePlan = _preferredPlan(plans);

    final strategies = <TradingStrategyRecord>[];
    for (final value in storage.readList(userId, 'trading_strategies')) {
      try {
        final record = TradingStrategyRecord.fromJson(value);
        if (record.strategy.name.trim().isNotEmpty) strategies.add(record);
      } catch (_) {
        // Keep other toolkit summaries usable when this cache entry is bad.
      }
    }
    strategies.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    final activeStrategy = strategies
        .where((record) => record.strategy.isActive)
        .firstOrNull;
    final selectedStrategy = activeStrategy ?? strategies.firstOrNull;

    final displayMode = storage.readMap(userId, 'trading_data_display_mode');
    return ToolkitDashboardSnapshot(
      dataMode: TradingDataDisplayMode.fromStorage(
        displayMode?['mode'] ?? displayMode?['tradingDataDisplayMode'],
      ),
      riskPercent: _double(risk?['risk_percent']),
      riskSymbol: '${risk?['symbol'] ?? ''}'.trim(),
      psychologyScore: psychology?.score,
      psychologyStatus: psychology?.status ?? '',
      psychologyCheckedToday:
          latestCheckIn != null &&
          _sameDate(latestCheckIn.createdAt, currentTime),
      planName: activePlan?.plan.planName.trim() ?? '',
      planRiskPercent: activePlan?.plan.riskPercent,
      planMinimumRiskReward: activePlan?.plan.minimumRiskReward,
      strategyName: selectedStrategy?.strategy.name.trim() ?? '',
      strategyStyle: selectedStrategy?.strategy.tradingStyle.trim() ?? '',
      strategyRiskReward:
          selectedStrategy?.strategy.riskRewardTarget.trim() ?? '',
    );
  }

  static TradingPlanRecord? _preferredPlan(List<TradingPlanRecord> plans) {
    final defaultPlan = plans
        .where((record) => record.plan.isDefault && record.plan.isActive)
        .firstOrNull;
    if (defaultPlan != null) return defaultPlan;
    final activePlan = plans
        .where((record) => record.plan.isActive)
        .firstOrNull;
    return activePlan ?? plans.firstOrNull;
  }

  static bool _sameDate(DateTime left, DateTime right) =>
      left.year == right.year &&
      left.month == right.month &&
      left.day == right.day;

  static double? _double(Object? value) =>
      value is num ? value.toDouble() : double.tryParse('$value');
}
