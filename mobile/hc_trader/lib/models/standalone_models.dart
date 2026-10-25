import 'package:cloud_firestore/cloud_firestore.dart';

class TradingStrategy {
  const TradingStrategy({
    this.name = '',
    this.description = '',
    this.source = '',
    this.strategyType = '',
    this.questionnaireVersion = '',
    this.questionnaireAnswers = const {},
    this.traderProfileSummary = '',
    this.suitableTraderPersonality = '',
    this.recommendedMarket = '',
    this.technicalConcept = '',
    this.riskManagementRules = '',
    this.riskPercent,
    this.maxDailyLossPercent,
    this.maxTradesPerDay,
    this.minimumRiskRewardRatio,
    this.psychologyRules = '',
    this.personalityWarnings = '',
    this.noTradeConditions = '',
    this.preTradeChecklist = '',
    this.postTradeReviewRules = '',
    this.improvementNotes = '',
    this.tradingStyle = 'Intraday',
    this.marketCondition = 'Hybrid',
    this.analysisMethods = const [],
    this.selectedStrategyTypes = const [],
    this.selectedMarketConditions = const [],
    this.majorTimeframe = '',
    this.internalTimeframe = '',
    this.entryTimeframe = '',
    this.entryModelRules = '',
    this.entryConfirmationChecklist = const [],
    this.stopLossRule = '',
    this.takeProfitRule = '',
    this.riskRewardTarget = '',
    this.invalidationRule = '',
    this.tradeManagementRule = '',
    this.partialCloseRule = '',
    this.breakEvenRule = '',
    this.exampleSetupNotes = '',
    this.isActive = true,
  });

  final String name;
  final String description;
  final String source;
  final String strategyType;
  final String questionnaireVersion;
  final Map<String, String> questionnaireAnswers;
  final String traderProfileSummary;
  final String suitableTraderPersonality;
  final String recommendedMarket;
  final String technicalConcept;
  final String riskManagementRules;
  final double? riskPercent;
  final double? maxDailyLossPercent;
  final int? maxTradesPerDay;
  final double? minimumRiskRewardRatio;
  final String psychologyRules;
  final String personalityWarnings;
  final String noTradeConditions;
  final String preTradeChecklist;
  final String postTradeReviewRules;
  final String improvementNotes;
  final String tradingStyle;
  final String marketCondition;
  final List<String> analysisMethods;
  final List<String> selectedStrategyTypes;
  final List<String> selectedMarketConditions;
  final String majorTimeframe;
  final String internalTimeframe;
  final String entryTimeframe;
  final String entryModelRules;
  final List<String> entryConfirmationChecklist;
  final String stopLossRule;
  final String takeProfitRule;
  final String riskRewardTarget;
  final String invalidationRule;
  final String tradeManagementRule;
  final String partialCloseRule;
  final String breakEvenRule;
  final String exampleSetupNotes;
  final bool isActive;

  String get timeframeSummary => [
    majorTimeframe,
    internalTimeframe,
    entryTimeframe,
  ].where((item) => item.trim().isNotEmpty).join('/');

  bool get isRecommended =>
      source == 'questionnaire' ||
      source == 'strategy_questionnaire' ||
      questionnaireVersion.trim().isNotEmpty;

  factory TradingStrategy.fromJson(
    Map<String, dynamic> json,
  ) => TradingStrategy(
    name: (json['name'] ?? json['strategy_name'] ?? '').toString(),
    description: (json['description'] ?? '').toString(),
    source: (json['source'] ?? '').toString(),
    strategyType: (json['strategy_type'] ?? '').toString(),
    questionnaireVersion: (json['questionnaire_version'] ?? '').toString(),
    questionnaireAnswers: _stringMap(json['questionnaire_answers']),
    traderProfileSummary: (json['trader_profile_summary'] ?? '').toString(),
    suitableTraderPersonality: (json['suitable_trader_personality'] ?? '')
        .toString(),
    recommendedMarket: (json['recommended_market'] ?? '').toString(),
    technicalConcept: (json['technical_concept'] ?? '').toString(),
    riskManagementRules: (json['risk_management_rules'] ?? '').toString(),
    riskPercent: _nullableDouble(json['risk_percent']),
    maxDailyLossPercent: _nullableDouble(json['max_daily_loss_percent']),
    maxTradesPerDay: json['max_trades_per_day'] == null
        ? null
        : _int(json['max_trades_per_day'], 0),
    minimumRiskRewardRatio: _nullableDouble(json['minimum_risk_reward_ratio']),
    psychologyRules: (json['psychology_rules'] ?? '').toString(),
    personalityWarnings: (json['personality_warnings'] ?? '').toString(),
    noTradeConditions: (json['no_trade_conditions'] ?? '').toString(),
    preTradeChecklist: (json['pre_trade_checklist'] ?? '').toString(),
    postTradeReviewRules: (json['post_trade_review_rules'] ?? '').toString(),
    improvementNotes: (json['improvement_notes'] ?? '').toString(),
    tradingStyle: (json['trading_style'] ?? 'Intraday').toString(),
    marketCondition: (json['market_condition'] ?? 'Hybrid').toString(),
    analysisMethods: _strings(json['analysis_methods']),
    selectedStrategyTypes: _strings(json['selected_strategy_types']),
    selectedMarketConditions: _strings(json['selected_market_conditions']),
    majorTimeframe: (json['major_timeframe'] ?? '').toString(),
    internalTimeframe: (json['internal_timeframe'] ?? '').toString(),
    entryTimeframe: (json['entry_timeframe'] ?? '').toString(),
    entryModelRules: (json['entry_model_rules'] ?? '').toString(),
    entryConfirmationChecklist: _strings(json['entry_confirmation_checklist']),
    stopLossRule: (json['stop_loss_rule'] ?? '').toString(),
    takeProfitRule: (json['take_profit_rule'] ?? '').toString(),
    riskRewardTarget: (json['risk_reward_target'] ?? '').toString(),
    invalidationRule: (json['invalidation_rule'] ?? '').toString(),
    tradeManagementRule: (json['trade_management_rule'] ?? '').toString(),
    partialCloseRule: (json['partial_close_rule'] ?? '').toString(),
    breakEvenRule: (json['break_even_rule'] ?? '').toString(),
    exampleSetupNotes: (json['example_setup_notes'] ?? '').toString(),
    isActive: _bool(json['is_active'], true),
  );

  Map<String, Object?> toJson() => {
    'name': name,
    'description': description,
    'source': source,
    'strategy_type': strategyType,
    'questionnaire_version': questionnaireVersion,
    'questionnaire_answers': questionnaireAnswers,
    'trader_profile_summary': traderProfileSummary,
    'suitable_trader_personality': suitableTraderPersonality,
    'recommended_market': recommendedMarket,
    'technical_concept': technicalConcept,
    'risk_management_rules': riskManagementRules,
    'risk_percent': riskPercent,
    'max_daily_loss_percent': maxDailyLossPercent,
    'max_trades_per_day': maxTradesPerDay,
    'minimum_risk_reward_ratio': minimumRiskRewardRatio,
    'psychology_rules': psychologyRules,
    'personality_warnings': personalityWarnings,
    'no_trade_conditions': noTradeConditions,
    'pre_trade_checklist': preTradeChecklist,
    'post_trade_review_rules': postTradeReviewRules,
    'improvement_notes': improvementNotes,
    'trading_style': tradingStyle,
    'market_condition': marketCondition,
    'analysis_methods': analysisMethods,
    'selected_strategy_types': selectedStrategyTypes,
    'selected_market_conditions': selectedMarketConditions,
    'major_timeframe': majorTimeframe,
    'internal_timeframe': internalTimeframe,
    'entry_timeframe': entryTimeframe,
    'entry_model_rules': entryModelRules,
    'entry_confirmation_checklist': entryConfirmationChecklist,
    'stop_loss_rule': stopLossRule,
    'take_profit_rule': takeProfitRule,
    'risk_reward_target': riskRewardTarget,
    'invalidation_rule': invalidationRule,
    'trade_management_rule': tradeManagementRule,
    'partial_close_rule': partialCloseRule,
    'break_even_rule': breakEvenRule,
    'example_setup_notes': exampleSetupNotes,
    'is_active': isActive,
  };
}

class TradingStrategyRecord {
  const TradingStrategyRecord({
    required this.id,
    required this.strategy,
    required this.createdAt,
    required this.updatedAt,
    this.userId = '',
  });

  final String id;
  final String userId;
  final TradingStrategy strategy;
  final DateTime createdAt;
  final DateTime updatedAt;

  factory TradingStrategyRecord.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    return TradingStrategyRecord.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory TradingStrategyRecord.fromJson(Map<String, dynamic> json) {
    return TradingStrategyRecord(
      id: (json['id'] ?? '').toString(),
      userId: (json['user_id'] ?? json['userId'] ?? '').toString(),
      strategy: TradingStrategy.fromJson(
        Map<String, dynamic>.from(json['strategy'] as Map? ?? json),
      ),
      createdAt: _date(json['created_at'] ?? json['createdAt']),
      updatedAt: _date(json['updated_at'] ?? json['updatedAt']),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'strategy': strategy.toJson(),
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
  };

  Map<String, Object?> toFirestore() => {
    'user_id': userId,
    ...strategy.toJson(),
    'strategy': strategy.toJson(),
    'created_at': Timestamp.fromDate(createdAt),
    'updated_at': Timestamp.fromDate(updatedAt),
  };
}

class TradingPlan {
  const TradingPlan({
    this.strategy = '',
    this.description = '',
    this.isDefault = false,
    this.isActive = true,
    this.planType = 'manual',
    this.source = '',
    this.questionnaireVersion = '',
    this.questionnaireAnswers = const {},
    this.generatedProfileSummary = '',
    this.templateKey = 'custom',
    this.capitalBasis = 'custom_capital',
    this.accountCapital,
    this.maxLossPercent,
    this.effectiveRiskCapital,
    this.tradingStyle = '',
    this.strategyType = '',
    this.technicalRules = '',
    this.checklistBeforeEntry = '',
    this.checklistAfterTrade = '',
    this.allowedStrategyIds = const [],
    this.allowedStrategyNames = const [],
    this.market = '',
    this.timeframe = '',
    this.entryCriteria = '',
    this.exitRules = '',
    this.stopLossRules = '',
    this.takeProfitRules = '',
    this.riskManagementRules = '',
    this.riskPercent = 0.5,
    this.dailyMaxLossPercent = 5,
    this.weeklyMaxLossPercent,
    this.monthlyMaxLossPercent,
    this.maxTradesPerDay = 8,
    this.maxLossesPerDay = 2,
    this.maxConsecutiveLosses = 3,
    this.maxOpenTrades = 3,
    this.minimumRiskReward = 1,
    this.cooldownAfterLossMinutes = 30,
    this.allowedSessions = const ['London', 'New York'],
    this.allowedTradingDays = const [],
    this.allowedPairs = const [],
    this.newsFilterRule = '',
    this.accountRules = '',
    this.propFirmRules = '',
    this.psychologyRules = '',
    this.personalityWarnings = '',
    this.noTradeConditions = '',
    this.preTradeChecklist = '',
    this.postTradeReviewRules = '',
    this.disciplineRules = '',
    this.improvementNotes = '',
    this.fomoPreventionRules = '',
    this.revengeTradingPreventionRules = '',
    this.afterWinRules = '',
    this.afterLossRules = '',
    this.afterBreakingRules = '',
    this.dailyRoutine = '',
    this.postTradeReviewRoutine = '',
    this.weeklyReviewRoutine = '',
    this.reviewNotes = '',
  });

  final String strategy;
  final String description;
  final bool isDefault;
  final bool isActive;
  final String planType;
  final String source;
  final String questionnaireVersion;
  final Map<String, String> questionnaireAnswers;
  final String generatedProfileSummary;
  final String templateKey;
  final String capitalBasis;
  final double? accountCapital;
  final double? maxLossPercent;
  final double? effectiveRiskCapital;
  final String tradingStyle;
  final String strategyType;
  final String technicalRules;
  final String checklistBeforeEntry;
  final String checklistAfterTrade;
  final List<String> allowedStrategyIds;
  final List<String> allowedStrategyNames;
  final String market;
  final String timeframe;
  final String entryCriteria;
  final String exitRules;
  final String stopLossRules;
  final String takeProfitRules;
  final String riskManagementRules;
  final double riskPercent;
  final double dailyMaxLossPercent;
  final double? weeklyMaxLossPercent;
  final double? monthlyMaxLossPercent;
  final int maxTradesPerDay;
  final int maxLossesPerDay;
  final int maxConsecutiveLosses;
  final int maxOpenTrades;
  final double minimumRiskReward;
  final int cooldownAfterLossMinutes;
  final List<String> allowedSessions;
  final List<String> allowedTradingDays;
  final List<String> allowedPairs;
  final String newsFilterRule;
  final String accountRules;
  final String propFirmRules;
  final String psychologyRules;
  final String personalityWarnings;
  final String noTradeConditions;
  final String preTradeChecklist;
  final String postTradeReviewRules;
  final String disciplineRules;
  final String improvementNotes;
  final String fomoPreventionRules;
  final String revengeTradingPreventionRules;
  final String afterWinRules;
  final String afterLossRules;
  final String afterBreakingRules;
  final String dailyRoutine;
  final String postTradeReviewRoutine;
  final String weeklyReviewRoutine;
  final String reviewNotes;

  String get planName => strategy;

  bool get isRecommended =>
      planType == 'recommended' ||
      source == 'questionnaire' ||
      source == 'plan_questionnaire';

  TradingPlan copyWith({bool? isDefault}) => TradingPlan(
    strategy: strategy,
    description: description,
    isDefault: isDefault ?? this.isDefault,
    isActive: isActive,
    planType: planType,
    source: source,
    questionnaireVersion: questionnaireVersion,
    questionnaireAnswers: questionnaireAnswers,
    generatedProfileSummary: generatedProfileSummary,
    templateKey: templateKey,
    capitalBasis: capitalBasis,
    accountCapital: accountCapital,
    maxLossPercent: maxLossPercent,
    effectiveRiskCapital: effectiveRiskCapital,
    tradingStyle: tradingStyle,
    strategyType: strategyType,
    technicalRules: technicalRules,
    checklistBeforeEntry: checklistBeforeEntry,
    checklistAfterTrade: checklistAfterTrade,
    allowedStrategyIds: allowedStrategyIds,
    allowedStrategyNames: allowedStrategyNames,
    market: market,
    timeframe: timeframe,
    entryCriteria: entryCriteria,
    exitRules: exitRules,
    stopLossRules: stopLossRules,
    takeProfitRules: takeProfitRules,
    riskManagementRules: riskManagementRules,
    riskPercent: riskPercent,
    dailyMaxLossPercent: dailyMaxLossPercent,
    weeklyMaxLossPercent: weeklyMaxLossPercent,
    monthlyMaxLossPercent: monthlyMaxLossPercent,
    maxTradesPerDay: maxTradesPerDay,
    maxLossesPerDay: maxLossesPerDay,
    maxConsecutiveLosses: maxConsecutiveLosses,
    maxOpenTrades: maxOpenTrades,
    minimumRiskReward: minimumRiskReward,
    cooldownAfterLossMinutes: cooldownAfterLossMinutes,
    allowedSessions: allowedSessions,
    allowedTradingDays: allowedTradingDays,
    allowedPairs: allowedPairs,
    newsFilterRule: newsFilterRule,
    accountRules: accountRules,
    propFirmRules: propFirmRules,
    psychologyRules: psychologyRules,
    personalityWarnings: personalityWarnings,
    noTradeConditions: noTradeConditions,
    preTradeChecklist: preTradeChecklist,
    postTradeReviewRules: postTradeReviewRules,
    disciplineRules: disciplineRules,
    improvementNotes: improvementNotes,
    fomoPreventionRules: fomoPreventionRules,
    revengeTradingPreventionRules: revengeTradingPreventionRules,
    afterWinRules: afterWinRules,
    afterLossRules: afterLossRules,
    afterBreakingRules: afterBreakingRules,
    dailyRoutine: dailyRoutine,
    postTradeReviewRoutine: postTradeReviewRoutine,
    weeklyReviewRoutine: weeklyReviewRoutine,
    reviewNotes: reviewNotes,
  );

  factory TradingPlan.fromJson(Map<String, dynamic> json) => TradingPlan(
    strategy: (json['plan_name'] ?? json['strategy'] ?? '').toString(),
    description: (json['description'] ?? '').toString(),
    isDefault: _bool(json['is_default'], false),
    isActive: _bool(json['is_active'], true),
    planType: (json['plan_type'] ?? json['planType'] ?? 'manual').toString(),
    source: (json['source'] ?? '').toString(),
    questionnaireVersion:
        (json['questionnaire_version'] ?? json['questionnaireVersion'] ?? '')
            .toString(),
    questionnaireAnswers: _stringMap(
      json['questionnaire_answers'] ?? json['questionnaireAnswers'],
    ),
    generatedProfileSummary:
        (json['generated_profile_summary'] ??
                json['generatedProfileSummary'] ??
                '')
            .toString(),
    templateKey: (json['template_key'] ?? json['templateKey'] ?? 'custom')
        .toString(),
    capitalBasis:
        (json['capital_basis'] ?? json['capitalBasis'] ?? 'custom_capital')
            .toString(),
    accountCapital: _nullableDouble(
      json['account_capital'] ?? json['accountCapital'],
    ),
    maxLossPercent: _nullableDouble(
      json['max_loss_percent'] ?? json['maxLossPercent'],
    ),
    effectiveRiskCapital: _nullableDouble(
      json['effective_risk_capital'] ?? json['effectiveRiskCapital'],
    ),
    tradingStyle: (json['trading_style'] ?? json['tradingStyle'] ?? '')
        .toString(),
    strategyType: (json['strategy_type'] ?? json['strategyType'] ?? '')
        .toString(),
    technicalRules: (json['technical_rules'] ?? json['technicalRules'] ?? '')
        .toString(),
    checklistBeforeEntry:
        (json['checklist_before_entry'] ?? json['checklistBeforeEntry'] ?? '')
            .toString(),
    checklistAfterTrade:
        (json['checklist_after_trade'] ?? json['checklistAfterTrade'] ?? '')
            .toString(),
    allowedStrategyIds: _strings(json['allowed_strategy_ids']),
    allowedStrategyNames: _strings(json['allowed_strategy_names']),
    market: (json['market'] ?? json['symbol'] ?? '').toString(),
    timeframe: (json['timeframe'] ?? '').toString(),
    entryCriteria: (json['entry_criteria'] ?? '').toString(),
    exitRules: (json['exit_rules'] ?? '').toString(),
    stopLossRules: (json['stop_loss_rules'] ?? json['stopLossRules'] ?? '')
        .toString(),
    takeProfitRules:
        (json['take_profit_rules'] ?? json['takeProfitRules'] ?? '').toString(),
    riskManagementRules:
        (json['risk_management_rules'] ?? json['riskManagementRules'] ?? '')
            .toString(),
    riskPercent: _double(json['risk_percent'], .5),
    dailyMaxLossPercent: _double(json['daily_max_loss_percent'], 5),
    weeklyMaxLossPercent: _nullableDouble(json['weekly_max_loss_percent']),
    monthlyMaxLossPercent: _nullableDouble(json['monthly_max_loss_percent']),
    maxTradesPerDay: _int(json['max_trades_per_day'], 8),
    maxLossesPerDay: _int(json['max_losses_per_day'], 2),
    maxConsecutiveLosses: _int(json['max_consecutive_losses'], 3),
    maxOpenTrades: _int(json['max_open_trades'], 3),
    minimumRiskReward: _double(json['minimum_risk_reward'], 1),
    cooldownAfterLossMinutes: _int(json['cooldown_minutes'], 30),
    allowedSessions: _strings(json['allowed_sessions']),
    allowedTradingDays: _strings(
      json['allowed_trading_days'] ?? json['allowedTradingDays'],
    ),
    allowedPairs: _strings(json['allowed_pairs']),
    newsFilterRule: (json['news_filter_rule'] ?? '').toString(),
    accountRules: (json['account_rules'] ?? '').toString(),
    propFirmRules: (json['prop_firm_rules'] ?? '').toString(),
    psychologyRules: (json['psychology_rules'] ?? '').toString(),
    personalityWarnings:
        (json['personality_warnings'] ?? json['personalityWarnings'] ?? '')
            .toString(),
    noTradeConditions:
        (json['no_trade_conditions'] ?? json['noTradeConditions'] ?? '')
            .toString(),
    preTradeChecklist:
        (json['pre_trade_checklist'] ?? json['preTradeChecklist'] ?? '')
            .toString(),
    postTradeReviewRules:
        (json['post_trade_review_rules'] ?? json['postTradeReviewRules'] ?? '')
            .toString(),
    disciplineRules: (json['discipline_rules'] ?? json['disciplineRules'] ?? '')
        .toString(),
    improvementNotes:
        (json['improvement_notes'] ?? json['improvementNotes'] ?? '')
            .toString(),
    fomoPreventionRules: (json['fomo_prevention_rules'] ?? '').toString(),
    revengeTradingPreventionRules:
        (json['revenge_trading_prevention_rules'] ?? '').toString(),
    afterWinRules: (json['after_win_rules'] ?? '').toString(),
    afterLossRules: (json['after_loss_rules'] ?? '').toString(),
    afterBreakingRules: (json['after_breaking_rules'] ?? '').toString(),
    dailyRoutine: (json['daily_routine'] ?? '').toString(),
    postTradeReviewRoutine: (json['post_trade_review_routine'] ?? '')
        .toString(),
    weeklyReviewRoutine: (json['weekly_review_routine'] ?? '').toString(),
    reviewNotes: (json['review_notes'] ?? '').toString(),
  );

  Map<String, Object?> toJson() => {
    'strategy': strategy,
    'plan_name': strategy,
    'description': description,
    'is_default': isDefault,
    'is_active': isActive,
    'plan_type': planType,
    'source': source,
    'questionnaire_version': questionnaireVersion,
    'questionnaire_answers': questionnaireAnswers,
    'generated_profile_summary': generatedProfileSummary,
    'template_key': templateKey,
    'capital_basis': capitalBasis,
    'account_capital': accountCapital,
    'max_loss_percent': maxLossPercent,
    'effective_risk_capital': effectiveRiskCapital,
    'trading_style': tradingStyle,
    'strategy_type': strategyType,
    'technical_rules': technicalRules,
    'checklist_before_entry': checklistBeforeEntry,
    'checklist_after_trade': checklistAfterTrade,
    'allowed_strategy_ids': allowedStrategyIds,
    'allowed_strategy_names': allowedStrategyNames,
    'market': market,
    'timeframe': timeframe,
    'entry_criteria': entryCriteria,
    'exit_rules': exitRules,
    'stop_loss_rules': stopLossRules,
    'take_profit_rules': takeProfitRules,
    'risk_management_rules': riskManagementRules,
    'risk_percent': riskPercent,
    'daily_max_loss_percent': dailyMaxLossPercent,
    'weekly_max_loss_percent': weeklyMaxLossPercent,
    'monthly_max_loss_percent': monthlyMaxLossPercent,
    'max_trades_per_day': maxTradesPerDay,
    'max_losses_per_day': maxLossesPerDay,
    'max_consecutive_losses': maxConsecutiveLosses,
    'max_open_trades': maxOpenTrades,
    'minimum_risk_reward': minimumRiskReward,
    'cooldown_minutes': cooldownAfterLossMinutes,
    'allowed_sessions': allowedSessions,
    'allowed_trading_days': allowedTradingDays,
    'allowed_pairs': allowedPairs,
    'news_filter_rule': newsFilterRule,
    'account_rules': accountRules,
    'prop_firm_rules': propFirmRules,
    'psychology_rules': psychologyRules,
    'personality_warnings': personalityWarnings,
    'no_trade_conditions': noTradeConditions,
    'pre_trade_checklist': preTradeChecklist,
    'post_trade_review_rules': postTradeReviewRules,
    'discipline_rules': disciplineRules,
    'improvement_notes': improvementNotes,
    'fomo_prevention_rules': fomoPreventionRules,
    'revenge_trading_prevention_rules': revengeTradingPreventionRules,
    'after_win_rules': afterWinRules,
    'after_loss_rules': afterLossRules,
    'after_breaking_rules': afterBreakingRules,
    'daily_routine': dailyRoutine,
    'post_trade_review_routine': postTradeReviewRoutine,
    'weekly_review_routine': weeklyReviewRoutine,
    'review_notes': reviewNotes,
  };
}

class TradingPlanRecord {
  const TradingPlanRecord({
    required this.id,
    required this.plan,
    required this.createdAt,
    required this.updatedAt,
    this.userId = '',
    this.versionId = '',
    this.effectiveFrom,
    this.effectiveTo,
  });

  final String id;
  final String userId;
  final TradingPlan plan;
  final DateTime createdAt;
  final DateTime updatedAt;
  final String versionId;
  final DateTime? effectiveFrom;
  final DateTime? effectiveTo;

  DateTime get effectiveStart => effectiveFrom ?? createdAt;

  factory TradingPlanRecord.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    return TradingPlanRecord.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory TradingPlanRecord.fromJson(Map<String, dynamic> json) {
    return TradingPlanRecord(
      id: (json['id'] ?? '').toString(),
      userId: (json['user_id'] ?? json['userId'] ?? json['created_by'] ?? '')
          .toString(),
      plan: TradingPlan.fromJson(
        Map<String, dynamic>.from(json['plan'] as Map? ?? json),
      ),
      createdAt: _date(json['created_at'] ?? json['createdAt']),
      updatedAt: _date(json['updated_at'] ?? json['updatedAt']),
      versionId: (json['version_id'] ?? json['versionId'] ?? '').toString(),
      effectiveFrom: _nullableDate(
        json['effective_from'] ?? json['effectiveFrom'],
      ),
      effectiveTo: _nullableDate(json['effective_to'] ?? json['effectiveTo']),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'plan_id': id,
    'created_by': userId,
    'updated_by': userId,
    'plan': plan.toJson(),
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    'version_id': versionId,
    'effective_from': effectiveStart.toIso8601String(),
    'effective_to': effectiveTo?.toIso8601String(),
  };

  Map<String, Object?> toFirestore() => {
    'user_id': userId,
    'plan_id': id,
    'created_by': userId,
    'updated_by': userId,
    ...plan.toJson(),
    'plan': plan.toJson(),
    'created_at': Timestamp.fromDate(createdAt),
    'updated_at': Timestamp.fromDate(updatedAt),
    'version_id': versionId.isEmpty ? '$id-v1' : versionId,
    'effective_from': Timestamp.fromDate(effectiveStart),
    'effective_to': effectiveTo == null
        ? null
        : Timestamp.fromDate(effectiveTo!),
  };

  TradingPlanRecord withDefault(bool isDefault) => TradingPlanRecord(
    id: id,
    userId: userId,
    plan: plan.copyWith(isDefault: isDefault),
    createdAt: createdAt,
    updatedAt: updatedAt,
    versionId: versionId,
    effectiveFrom: effectiveFrom,
    effectiveTo: effectiveTo,
  );
}

class PsychologyCheckIn {
  const PsychologyCheckIn({
    required this.id,
    required this.createdAt,
    required this.emotion,
    required this.confidence,
    required this.discipline,
    required this.fomo,
    required this.revengeUrge,
    required this.overconfidence,
    this.userId = '',
    this.notes = '',
  });

  final String id;
  final DateTime createdAt;
  final String emotion;
  final int confidence;
  final int discipline;
  final bool fomo;
  final bool revengeUrge;
  final bool overconfidence;
  final String userId;
  final String notes;

  factory PsychologyCheckIn.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) => PsychologyCheckIn.fromJson({'id': doc.id, ...?doc.data()});

  factory PsychologyCheckIn.fromJson(Map<String, dynamic> json) =>
      PsychologyCheckIn(
        id: (json['id'] ?? '').toString(),
        createdAt: _date(
          json['completed_at'] ?? json['created_at'] ?? json['createdAt'],
        ),
        emotion: (json['emotion'] ?? 'Calm').toString(),
        confidence: _int(json['confidence'], 5),
        discipline: _int(json['discipline'], 5),
        fomo: json['fomo'] == true,
        revengeUrge: json['revenge_urge'] == true,
        overconfidence: json['overconfidence'] == true,
        userId: (json['user_id'] ?? json['userId'] ?? '').toString(),
        notes: (json['notes'] ?? '').toString(),
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'created_at': createdAt.toIso8601String(),
    'emotion': emotion,
    'confidence': confidence,
    'discipline': discipline,
    'fomo': fomo,
    'revenge_urge': revengeUrge,
    'overconfidence': overconfidence,
    'user_id': userId,
    'notes': notes,
  };

  Map<String, Object?> toFirestore() => {
    'user_id': userId,
    'emotion': emotion,
    'confidence': confidence,
    'discipline': discipline,
    'fomo': fomo,
    'revenge_urge': revengeUrge,
    'overconfidence': overconfidence,
    'notes': notes,
    'completed_at': Timestamp.fromDate(createdAt),
    'created_at': Timestamp.fromDate(createdAt),
    'updated_at': FieldValue.serverTimestamp(),
  };
}

class PropFirmRule {
  const PropFirmRule({
    required this.name,
    required this.phase,
    required this.profitTargetPercent,
    required this.maxDailyLossPercent,
    required this.maxOverallLossPercent,
    required this.minimumTradingDays,
  });

  final String name;
  final int phase;
  final double profitTargetPercent;
  final double maxDailyLossPercent;
  final double maxOverallLossPercent;
  final int minimumTradingDays;

  static const defaults = [
    PropFirmRule(
      name: 'FundingPips 2 Step · Phase 1',
      phase: 1,
      profitTargetPercent: 8,
      maxDailyLossPercent: 5,
      maxOverallLossPercent: 10,
      minimumTradingDays: 3,
    ),
    PropFirmRule(
      name: 'FundingPips 2 Step · Phase 2',
      phase: 2,
      profitTargetPercent: 5,
      maxDailyLossPercent: 5,
      maxOverallLossPercent: 10,
      minimumTradingDays: 3,
    ),
    PropFirmRule(
      name: 'Master Account',
      phase: 3,
      profitTargetPercent: 0,
      maxDailyLossPercent: 5,
      maxOverallLossPercent: 10,
      minimumTradingDays: 0,
    ),
  ];
}

double _double(Object? value, double fallback) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? fallback;

double? _nullableDouble(Object? value) {
  if (value == null || value == '') return null;
  return value is num ? value.toDouble() : double.tryParse('$value');
}

int _int(Object? value, int fallback) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

bool _bool(Object? value, bool fallback) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  final text = value?.toString().trim().toLowerCase();
  if (text == null || text.isEmpty) return fallback;
  return text == 'true' || text == '1' || text == 'yes';
}

DateTime _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return DateTime.tryParse('${value ?? ''}') ?? DateTime.now();
}

DateTime? _nullableDate(Object? value) {
  if (value == null || value == '') return null;
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return DateTime.tryParse(value.toString());
}

List<String> _strings(Object? value) =>
    value is List ? value.map((item) => item.toString()).toList() : const [];

Map<String, String> _stringMap(Object? value) => value is Map
    ? value.map((key, item) => MapEntry('$key', '$item'))
    : const {};
