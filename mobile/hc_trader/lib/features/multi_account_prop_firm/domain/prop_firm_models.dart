import 'dart:math' as math;

enum AccountStatus {
  available,
  recommended,
  active,
  coolingAfterWin,
  coolingAfterLoss,
  dailyLocked,
  portfolioLocked,
  recovery,
  payoutProtection,
  payoutReady,
  payoutRequested,
  paused,
  breached,
  archived,
}

enum AccountPriority { low, standard, high }

enum PropFirmAccountType { funded, evaluation }

enum LossLimitType { fixedAmount, percentage }

enum DrawdownType {
  static,
  balanceBased,
  equityBased,
  trailing,
  endOfDayTrailing,
  custom,
}

enum DailyLossCalculationMethod {
  realisedOnly,
  realisedAndUnrealised,
  balanceBased,
  equityBased,
  custom,
}

enum DailyResetMethod { automaticAtFirmTime, manualOnly }

enum ManualAccountHealthStatus {
  healthy,
  moderateRisk,
  nearDailyLossLimit,
  nearMaximumLossLimit,
  dailyLocked,
  recovery,
  payoutProgress,
  payoutEligible,
  waitingForPayoutDate,
  payoutProtection,
  paused,
  breached,
  archived,
}

enum AccountHealthStatus {
  healthy,
  caution,
  highRisk,
  critical,
  nearDailyLossLimit,
  nearMaximumLossLimit,
  dailyLocked,
  recovery,
  payoutProgress,
  payoutEligible,
  waitingForPayoutDate,
  payoutProtection,
  paused,
  breached,
  archived,
}

enum PayoutRequestStatus {
  profitIncomplete,
  tradingDaysIncomplete,
  waitingForDate,
  eligibleToday,
  protected,
  requestSubmitted,
  paid,
  rejectedOrDelayed,
  userCancelled,
}

enum PortfolioRecommendationType {
  continueNormally,
  continueWithReducedRisk,
  switchAccount,
  protectForPayout,
  stopSpecificAccount,
  stopPortfolio,
  pauseAndReview,
  completeRemainingRequirementsCarefully,
  noEligibleAccount,
}

enum RecommendationConfidence {
  completeData,
  partiallyCompleteData,
  insufficientData,
}

enum RotationTemplate {
  balanced,
  payoutProtection,
  recoveryIsolation,
  oneAccountPerSession,
  custom,
}

enum TradeDirection { buy, sell }

enum TradeResultType {
  fullTp,
  fullSl,
  breakeven,
  partialTp,
  partialSl,
  closedManually,
  cancelled,
  notTriggered,
  missedTrade,
  manualAdjustment,
}

enum RecoveryMode { conservative, standard, pauseAndReview }

enum PortfolioSessionStatus {
  draft,
  ready,
  active,
  warning,
  stopped,
  completed,
  cancelled,
}

enum SessionStopReasonCode {
  combinedLossLimit,
  maximumRLoss,
  oneLoss,
  oneWin,
  consecutiveLosses,
  maximumTrades,
  dailyTarget,
  manualStop,
  emergencyStop,
  emotionalRisk,
  accountSafety,
  payoutProtection,
  exposureProtection,
  legacySafetyLock,
}

enum SessionStoppedBy {
  automaticRule,
  manualUser,
  accountSafety,
  payoutProtection,
  exposureProtection,
}

class SessionStopReason {
  const SessionStopReason({
    required this.code,
    required this.titleKey,
    required this.descriptionKey,
    required this.stoppedAt,
    required this.stoppedBy,
  });

  final SessionStopReasonCode code;
  final String titleKey;
  final String descriptionKey;
  final DateTime stoppedAt;
  final SessionStoppedBy stoppedBy;

  factory SessionStopReason.fromJson(Map<String, dynamic> json) =>
      SessionStopReason(
        code: _enumValue(
          SessionStopReasonCode.values,
          json['code'],
          SessionStopReasonCode.legacySafetyLock,
        ),
        titleKey: '${json['title_key'] ?? 'session.stop.legacy.title'}',
        descriptionKey:
            '${json['description_key'] ?? 'session.stop.legacy.description'}',
        stoppedAt: _date(json['stopped_at']) ?? DateTime(2026),
        stoppedBy: _enumValue(
          SessionStoppedBy.values,
          json['stopped_by'],
          SessionStoppedBy.accountSafety,
        ),
      );

  Map<String, Object?> toJson() => {
    'code': code.name,
    'title_key': titleKey,
    'description_key': descriptionKey,
    'stopped_at': stoppedAt.toIso8601String(),
    'stopped_by': stoppedBy.name,
  };
}

T _enumValue<T extends Enum>(Iterable<T> values, Object? raw, T fallback) {
  final name = '$raw';
  return values.where((value) => value.name == name).firstOrNull ?? fallback;
}

double _double(Object? value, [double fallback = 0]) {
  if (value is num) return value.toDouble();
  return double.tryParse('$value') ?? fallback;
}

double? _nullableDouble(Object? value) {
  if (value == null) return null;
  final parsed = value is num ? value.toDouble() : double.tryParse('$value');
  return parsed?.isFinite == true ? parsed : null;
}

int _int(Object? value, [int fallback = 0]) {
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? fallback;
}

bool _bool(Object? value, [bool fallback = false]) => switch (value) {
  bool result => result,
  'true' || 1 => true,
  'false' || 0 => false,
  _ => fallback,
};

DateTime? _date(Object? value) => DateTime.tryParse('$value');

class PropFirmRulePreset {
  const PropFirmRulePreset({
    required this.id,
    required this.firmName,
    required this.minimumPayout,
    this.currency = 'USD',
    required this.updatedAt,
  });

  final String id;
  final String firmName;
  final double minimumPayout;
  final String currency;
  final DateTime updatedAt;

  PropFirmRulePreset copyWith({
    String? firmName,
    double? minimumPayout,
    String? currency,
    DateTime? updatedAt,
  }) => PropFirmRulePreset(
    id: id,
    firmName: firmName ?? this.firmName,
    minimumPayout: minimumPayout ?? this.minimumPayout,
    currency: currency ?? this.currency,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  factory PropFirmRulePreset.fromJson(Map<String, dynamic> json) =>
      PropFirmRulePreset(
        id: '${json['id'] ?? ''}',
        firmName: '${json['firm_name'] ?? ''}',
        minimumPayout: _double(json['minimum_payout']),
        currency: '${json['currency'] ?? 'USD'}',
        updatedAt: _date(json['updated_at']) ?? DateTime(2026),
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'firm_name': firmName,
    'minimum_payout': minimumPayout,
    'currency': currency,
    'updated_at': updatedAt.toIso8601String(),
  };
}

class PayoutHistoryEntry {
  const PayoutHistoryEntry({
    required this.id,
    required this.status,
    required this.createdAt,
    this.amount = 0,
    this.notes = '',
  });

  final String id;
  final PayoutRequestStatus status;
  final DateTime createdAt;
  final double amount;
  final String notes;

  factory PayoutHistoryEntry.fromJson(Map<String, dynamic> json) =>
      PayoutHistoryEntry(
        id: '${json['id'] ?? ''}',
        status: _enumValue(
          PayoutRequestStatus.values,
          json['status'],
          PayoutRequestStatus.profitIncomplete,
        ),
        createdAt: _date(json['created_at']) ?? DateTime(2026),
        amount: _double(json['amount']),
        notes: '${json['notes'] ?? ''}',
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'status': status.name,
    'created_at': createdAt.toIso8601String(),
    'amount': amount,
    'notes': notes,
  };
}

class AccountCurrentSituation {
  const AccountCurrentSituation({
    this.programType = '',
    this.accountCreationDate,
    this.activationDate,
    this.resetHour,
    this.resetMinute = 0,
    this.resetTimeZone = '',
    this.resetUtcOffsetMinutes,
    this.dailyResetMethod = DailyResetMethod.automaticAtFirmTime,
    this.maximumLossPercentage = 10,
    this.maximumLossAmount = 0,
    this.dailyLossPercentage = 5,
    this.dailyLossAmount = 0,
    this.dailyLossCalculationMethod =
        DailyLossCalculationMethod.realisedAndUnrealised,
    this.dailyStartingBalance,
    this.dailyStartingEquity,
    this.trailingReferenceBalance,
    this.lowestBalanceOrEquityToday,
    this.lossAlreadyRecordedToday = 0,
    this.manuallyEnteredOpenRisk = 0,
    this.todayRealisedProfitLoss = 0,
    this.todayUnrealisedProfitLoss,
    this.latestTradingDayCompleted,
    this.lastTradeFollowedPlan,
    this.emotionallyAffected = false,
    this.manualStatus = ManualAccountHealthStatus.healthy,
    this.currentPayoutEligibleProfit,
    this.earliestPayoutDate,
    this.nextPayoutRequestDate,
    this.expectedPayoutProcessingDate,
    this.plannedPayoutDate,
    this.payoutTimeZone = '',
    this.eligibilityAutomaticallyCalculated = true,
    this.payoutCanCurrentlyBeRequested,
    this.consistencyRuleSatisfied,
    this.protectUntilPayout = false,
    this.payoutSafetyBuffer = 0,
    this.payoutNotes = '',
    this.payoutRequestStatus = PayoutRequestStatus.profitIncomplete,
    this.payoutHistory = const [],
    this.healthyUpperPercent = 40,
    this.cautionUpperPercent = 70,
    this.highRiskUpperPercent = 85,
    this.dailySafetyThresholdPercent = 100,
    this.quickSetup = false,
    this.updatedAt,
  });

  final String programType;
  final DateTime? accountCreationDate;
  final DateTime? activationDate;
  final int? resetHour;
  final int resetMinute;
  final String resetTimeZone;
  final int? resetUtcOffsetMinutes;
  final DailyResetMethod dailyResetMethod;
  final double maximumLossPercentage;
  final double maximumLossAmount;
  final double dailyLossPercentage;
  final double dailyLossAmount;
  final DailyLossCalculationMethod dailyLossCalculationMethod;
  final double? dailyStartingBalance;
  final double? dailyStartingEquity;
  final double? trailingReferenceBalance;
  final double? lowestBalanceOrEquityToday;
  final double lossAlreadyRecordedToday;
  final double manuallyEnteredOpenRisk;
  final double todayRealisedProfitLoss;
  final double? todayUnrealisedProfitLoss;
  final DateTime? latestTradingDayCompleted;
  final bool? lastTradeFollowedPlan;
  final bool emotionallyAffected;
  final ManualAccountHealthStatus manualStatus;
  final double? currentPayoutEligibleProfit;
  final DateTime? earliestPayoutDate;
  final DateTime? nextPayoutRequestDate;
  final DateTime? expectedPayoutProcessingDate;
  final DateTime? plannedPayoutDate;
  final String payoutTimeZone;
  final bool eligibilityAutomaticallyCalculated;
  final bool? payoutCanCurrentlyBeRequested;
  final bool? consistencyRuleSatisfied;
  final bool protectUntilPayout;
  final double payoutSafetyBuffer;
  final String payoutNotes;
  final PayoutRequestStatus payoutRequestStatus;
  final List<PayoutHistoryEntry> payoutHistory;
  final double healthyUpperPercent;
  final double cautionUpperPercent;
  final double highRiskUpperPercent;
  final double dailySafetyThresholdPercent;
  final bool quickSetup;
  final DateTime? updatedAt;

  AccountCurrentSituation copyWith({
    String? programType,
    DateTime? accountCreationDate,
    bool clearAccountCreationDate = false,
    DateTime? activationDate,
    bool clearActivationDate = false,
    int? resetHour,
    bool clearResetHour = false,
    int? resetMinute,
    String? resetTimeZone,
    int? resetUtcOffsetMinutes,
    bool clearResetUtcOffset = false,
    DailyResetMethod? dailyResetMethod,
    double? maximumLossPercentage,
    double? maximumLossAmount,
    double? dailyLossPercentage,
    double? dailyLossAmount,
    DailyLossCalculationMethod? dailyLossCalculationMethod,
    double? dailyStartingBalance,
    bool clearDailyStartingBalance = false,
    double? dailyStartingEquity,
    bool clearDailyStartingEquity = false,
    double? trailingReferenceBalance,
    bool clearTrailingReferenceBalance = false,
    double? lowestBalanceOrEquityToday,
    bool clearLowestToday = false,
    double? lossAlreadyRecordedToday,
    double? manuallyEnteredOpenRisk,
    double? todayRealisedProfitLoss,
    double? todayUnrealisedProfitLoss,
    bool clearUnrealisedProfitLoss = false,
    DateTime? latestTradingDayCompleted,
    bool clearLatestTradingDay = false,
    bool? lastTradeFollowedPlan,
    bool clearLastTradeFollowedPlan = false,
    bool? emotionallyAffected,
    ManualAccountHealthStatus? manualStatus,
    double? currentPayoutEligibleProfit,
    bool clearPayoutEligibleProfit = false,
    DateTime? earliestPayoutDate,
    bool clearEarliestPayoutDate = false,
    DateTime? nextPayoutRequestDate,
    bool clearNextPayoutRequestDate = false,
    DateTime? expectedPayoutProcessingDate,
    bool clearExpectedProcessingDate = false,
    DateTime? plannedPayoutDate,
    bool clearPlannedPayoutDate = false,
    String? payoutTimeZone,
    bool? eligibilityAutomaticallyCalculated,
    bool? payoutCanCurrentlyBeRequested,
    bool clearPayoutRequestConfirmation = false,
    bool? consistencyRuleSatisfied,
    bool clearConsistencyRule = false,
    bool? protectUntilPayout,
    double? payoutSafetyBuffer,
    String? payoutNotes,
    PayoutRequestStatus? payoutRequestStatus,
    List<PayoutHistoryEntry>? payoutHistory,
    double? healthyUpperPercent,
    double? cautionUpperPercent,
    double? highRiskUpperPercent,
    double? dailySafetyThresholdPercent,
    bool? quickSetup,
    DateTime? updatedAt,
  }) => AccountCurrentSituation(
    programType: programType ?? this.programType,
    accountCreationDate: clearAccountCreationDate
        ? null
        : accountCreationDate ?? this.accountCreationDate,
    activationDate: clearActivationDate
        ? null
        : activationDate ?? this.activationDate,
    resetHour: clearResetHour ? null : resetHour ?? this.resetHour,
    resetMinute: resetMinute ?? this.resetMinute,
    resetTimeZone: resetTimeZone ?? this.resetTimeZone,
    resetUtcOffsetMinutes: clearResetUtcOffset
        ? null
        : resetUtcOffsetMinutes ?? this.resetUtcOffsetMinutes,
    dailyResetMethod: dailyResetMethod ?? this.dailyResetMethod,
    maximumLossPercentage: maximumLossPercentage ?? this.maximumLossPercentage,
    maximumLossAmount: maximumLossAmount ?? this.maximumLossAmount,
    dailyLossPercentage: dailyLossPercentage ?? this.dailyLossPercentage,
    dailyLossAmount: dailyLossAmount ?? this.dailyLossAmount,
    dailyLossCalculationMethod:
        dailyLossCalculationMethod ?? this.dailyLossCalculationMethod,
    dailyStartingBalance: clearDailyStartingBalance
        ? null
        : dailyStartingBalance ?? this.dailyStartingBalance,
    dailyStartingEquity: clearDailyStartingEquity
        ? null
        : dailyStartingEquity ?? this.dailyStartingEquity,
    trailingReferenceBalance: clearTrailingReferenceBalance
        ? null
        : trailingReferenceBalance ?? this.trailingReferenceBalance,
    lowestBalanceOrEquityToday: clearLowestToday
        ? null
        : lowestBalanceOrEquityToday ?? this.lowestBalanceOrEquityToday,
    lossAlreadyRecordedToday:
        lossAlreadyRecordedToday ?? this.lossAlreadyRecordedToday,
    manuallyEnteredOpenRisk:
        manuallyEnteredOpenRisk ?? this.manuallyEnteredOpenRisk,
    todayRealisedProfitLoss:
        todayRealisedProfitLoss ?? this.todayRealisedProfitLoss,
    todayUnrealisedProfitLoss: clearUnrealisedProfitLoss
        ? null
        : todayUnrealisedProfitLoss ?? this.todayUnrealisedProfitLoss,
    latestTradingDayCompleted: clearLatestTradingDay
        ? null
        : latestTradingDayCompleted ?? this.latestTradingDayCompleted,
    lastTradeFollowedPlan: clearLastTradeFollowedPlan
        ? null
        : lastTradeFollowedPlan ?? this.lastTradeFollowedPlan,
    emotionallyAffected: emotionallyAffected ?? this.emotionallyAffected,
    manualStatus: manualStatus ?? this.manualStatus,
    currentPayoutEligibleProfit: clearPayoutEligibleProfit
        ? null
        : currentPayoutEligibleProfit ?? this.currentPayoutEligibleProfit,
    earliestPayoutDate: clearEarliestPayoutDate
        ? null
        : earliestPayoutDate ?? this.earliestPayoutDate,
    nextPayoutRequestDate: clearNextPayoutRequestDate
        ? null
        : nextPayoutRequestDate ?? this.nextPayoutRequestDate,
    expectedPayoutProcessingDate: clearExpectedProcessingDate
        ? null
        : expectedPayoutProcessingDate ?? this.expectedPayoutProcessingDate,
    plannedPayoutDate: clearPlannedPayoutDate
        ? null
        : plannedPayoutDate ?? this.plannedPayoutDate,
    payoutTimeZone: payoutTimeZone ?? this.payoutTimeZone,
    eligibilityAutomaticallyCalculated:
        eligibilityAutomaticallyCalculated ??
        this.eligibilityAutomaticallyCalculated,
    payoutCanCurrentlyBeRequested: clearPayoutRequestConfirmation
        ? null
        : payoutCanCurrentlyBeRequested ?? this.payoutCanCurrentlyBeRequested,
    consistencyRuleSatisfied: clearConsistencyRule
        ? null
        : consistencyRuleSatisfied ?? this.consistencyRuleSatisfied,
    protectUntilPayout: protectUntilPayout ?? this.protectUntilPayout,
    payoutSafetyBuffer: payoutSafetyBuffer ?? this.payoutSafetyBuffer,
    payoutNotes: payoutNotes ?? this.payoutNotes,
    payoutRequestStatus: payoutRequestStatus ?? this.payoutRequestStatus,
    payoutHistory: List.unmodifiable(payoutHistory ?? this.payoutHistory),
    healthyUpperPercent: healthyUpperPercent ?? this.healthyUpperPercent,
    cautionUpperPercent: cautionUpperPercent ?? this.cautionUpperPercent,
    highRiskUpperPercent: highRiskUpperPercent ?? this.highRiskUpperPercent,
    dailySafetyThresholdPercent:
        dailySafetyThresholdPercent ?? this.dailySafetyThresholdPercent,
    quickSetup: quickSetup ?? this.quickSetup,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  factory AccountCurrentSituation.fromJson(
    Map<String, dynamic> json,
  ) => AccountCurrentSituation(
    programType: '${json['program_type'] ?? ''}',
    accountCreationDate: _date(json['account_creation_date']),
    activationDate: _date(json['activation_date']),
    resetHour: json['reset_hour'] == null ? null : _int(json['reset_hour']),
    resetMinute: _int(json['reset_minute']),
    resetTimeZone: '${json['reset_time_zone'] ?? ''}',
    resetUtcOffsetMinutes: json['reset_utc_offset_minutes'] == null
        ? null
        : _int(json['reset_utc_offset_minutes']),
    dailyResetMethod: _enumValue(
      DailyResetMethod.values,
      json['daily_reset_method'],
      DailyResetMethod.automaticAtFirmTime,
    ),
    maximumLossPercentage: _double(json['maximum_loss_percentage'], 10),
    maximumLossAmount: _double(json['maximum_loss_amount']),
    dailyLossPercentage: _double(json['daily_loss_percentage'], 5),
    dailyLossAmount: _double(json['daily_loss_amount']),
    dailyLossCalculationMethod: _enumValue(
      DailyLossCalculationMethod.values,
      json['daily_loss_calculation_method'],
      DailyLossCalculationMethod.realisedAndUnrealised,
    ),
    dailyStartingBalance: json['daily_starting_balance'] == null
        ? null
        : _double(json['daily_starting_balance']),
    dailyStartingEquity: json['daily_starting_equity'] == null
        ? null
        : _double(json['daily_starting_equity']),
    trailingReferenceBalance: json['trailing_reference_balance'] == null
        ? null
        : _double(json['trailing_reference_balance']),
    lowestBalanceOrEquityToday: json['lowest_balance_or_equity_today'] == null
        ? null
        : _double(json['lowest_balance_or_equity_today']),
    lossAlreadyRecordedToday: _double(json['loss_already_recorded_today']),
    manuallyEnteredOpenRisk: _double(json['manually_entered_open_risk']),
    todayRealisedProfitLoss: _double(json['today_realised_profit_loss']),
    todayUnrealisedProfitLoss: json['today_unrealised_profit_loss'] == null
        ? null
        : _double(json['today_unrealised_profit_loss']),
    latestTradingDayCompleted: _date(json['latest_trading_day_completed']),
    lastTradeFollowedPlan: json['last_trade_followed_plan'] as bool?,
    emotionallyAffected: _bool(json['emotionally_affected']),
    manualStatus: _enumValue(
      ManualAccountHealthStatus.values,
      json['manual_status'],
      ManualAccountHealthStatus.healthy,
    ),
    currentPayoutEligibleProfit: json['current_payout_eligible_profit'] == null
        ? null
        : _double(json['current_payout_eligible_profit']),
    earliestPayoutDate: _date(json['earliest_payout_date']),
    nextPayoutRequestDate: _date(json['next_payout_request_date']),
    expectedPayoutProcessingDate: _date(
      json['expected_payout_processing_date'],
    ),
    plannedPayoutDate: _date(json['planned_payout_date']),
    payoutTimeZone: '${json['payout_time_zone'] ?? ''}',
    eligibilityAutomaticallyCalculated: _bool(
      json['eligibility_automatically_calculated'],
      true,
    ),
    payoutCanCurrentlyBeRequested:
        json['payout_can_currently_be_requested'] as bool?,
    consistencyRuleSatisfied: json['consistency_rule_satisfied'] as bool?,
    protectUntilPayout: _bool(json['protect_until_payout']),
    payoutSafetyBuffer: _double(json['payout_safety_buffer']),
    payoutNotes: '${json['payout_notes'] ?? ''}',
    payoutRequestStatus: _enumValue(
      PayoutRequestStatus.values,
      json['payout_request_status'],
      PayoutRequestStatus.profitIncomplete,
    ),
    payoutHistory: ((json['payout_history'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => PayoutHistoryEntry.fromJson(Map.from(item)))
        .toList(growable: false),
    healthyUpperPercent: _double(json['healthy_upper_percent'], 40),
    cautionUpperPercent: _double(json['caution_upper_percent'], 70),
    highRiskUpperPercent: _double(json['high_risk_upper_percent'], 85),
    dailySafetyThresholdPercent: _double(
      json['daily_safety_threshold_percent'],
      100,
    ),
    quickSetup: _bool(json['quick_setup']),
    updatedAt: _date(json['updated_at']),
  );

  Map<String, Object?> toJson() => {
    'program_type': programType,
    'account_creation_date': accountCreationDate?.toIso8601String(),
    'activation_date': activationDate?.toIso8601String(),
    'reset_hour': resetHour,
    'reset_minute': resetMinute,
    'reset_time_zone': resetTimeZone,
    'reset_utc_offset_minutes': resetUtcOffsetMinutes,
    'daily_reset_method': dailyResetMethod.name,
    'maximum_loss_percentage': maximumLossPercentage,
    'maximum_loss_amount': maximumLossAmount,
    'daily_loss_percentage': dailyLossPercentage,
    'daily_loss_amount': dailyLossAmount,
    'daily_loss_calculation_method': dailyLossCalculationMethod.name,
    'daily_starting_balance': dailyStartingBalance,
    'daily_starting_equity': dailyStartingEquity,
    'trailing_reference_balance': trailingReferenceBalance,
    'lowest_balance_or_equity_today': lowestBalanceOrEquityToday,
    'loss_already_recorded_today': lossAlreadyRecordedToday,
    'manually_entered_open_risk': manuallyEnteredOpenRisk,
    'today_realised_profit_loss': todayRealisedProfitLoss,
    'today_unrealised_profit_loss': todayUnrealisedProfitLoss,
    'latest_trading_day_completed': latestTradingDayCompleted
        ?.toIso8601String(),
    'last_trade_followed_plan': lastTradeFollowedPlan,
    'emotionally_affected': emotionallyAffected,
    'manual_status': manualStatus.name,
    'current_payout_eligible_profit': currentPayoutEligibleProfit,
    'earliest_payout_date': earliestPayoutDate?.toIso8601String(),
    'next_payout_request_date': nextPayoutRequestDate?.toIso8601String(),
    'expected_payout_processing_date': expectedPayoutProcessingDate
        ?.toIso8601String(),
    'planned_payout_date': plannedPayoutDate?.toIso8601String(),
    'payout_time_zone': payoutTimeZone,
    'eligibility_automatically_calculated': eligibilityAutomaticallyCalculated,
    'payout_can_currently_be_requested': payoutCanCurrentlyBeRequested,
    'consistency_rule_satisfied': consistencyRuleSatisfied,
    'protect_until_payout': protectUntilPayout,
    'payout_safety_buffer': payoutSafetyBuffer,
    'payout_notes': payoutNotes,
    'payout_request_status': payoutRequestStatus.name,
    'payout_history': payoutHistory.map((item) => item.toJson()).toList(),
    'healthy_upper_percent': healthyUpperPercent,
    'caution_upper_percent': cautionUpperPercent,
    'high_risk_upper_percent': highRiskUpperPercent,
    'daily_safety_threshold_percent': dailySafetyThresholdPercent,
    'quick_setup': quickSetup,
    'updated_at': updatedAt?.toIso8601String(),
  };
}

class PropFirmAccount {
  const PropFirmAccount({
    required this.id,
    this.ownerUid = '',
    required this.name,
    required this.propFirmName,
    this.identifier = '',
    this.currency = 'USD',
    this.accountType = PropFirmAccountType.funded,
    required this.accountSize,
    required this.startingBalance,
    required this.currentBalance,
    this.manuallyEnteredEquity,
    this.fundedStatus = 'Funded',
    this.phase = 'Funded',
    required this.dailyLossLimit,
    required this.maximumLossLimit,
    this.dailyLossType = LossLimitType.fixedAmount,
    this.maximumLossType = LossLimitType.fixedAmount,
    this.drawdownType = DrawdownType.static,
    this.profitTarget = 0,
    this.minimumPayout = 0,
    this.desiredPayout = 0,
    this.profitSplit = .8,
    this.minimumTradingDays = 0,
    this.completedTradingDays = 0,
    this.payoutFrequency = 'On demand',
    this.nextPayoutDate,
    this.consistencyRuleLimit = 0,
    required this.maxRiskPerTrade,
    this.dailyTradeLimitEnabled = true,
    this.maxTradesPerDay = 3,
    this.tradesToday = 0,
    this.consecutiveWins = 0,
    this.consecutiveLosses = 0,
    this.lastTradeResult = '',
    this.notes = '',
    this.isAvailableToday = true,
    this.isManuallyExcluded = false,
    this.isManuallyProtected = false,
    this.personalDailyStopLossAmount = 0,
    this.personalMaximumRiskAmount = 0,
    this.safetyBufferPercentage = 0,
    this.overallRealisedProfitLoss = 0,
    this.overallUnrealisedProfitLoss = 0,
    this.openPositionsCount = 0,
    this.currentRiskExposureAmount = 0,
    this.currentRiskExposurePercentage = 0,
    this.dailyTargetReached = false,
    this.sessionCompleted = false,
    this.status = AccountStatus.available,
    this.priority = AccountPriority.standard,
    this.colorValue = 0xFF00796B,
    this.virtualRiskAllocation = 0,
    this.cooldownUntil,
    this.lastTradeAt,
    this.situation = const AccountCurrentSituation(),
    required this.createdAt,
    DateTime? updatedAt,
  }) : updatedAt = updatedAt ?? createdAt;

  final String id;
  final String ownerUid;
  final String name;
  final String propFirmName;
  final String identifier;
  final String currency;
  final PropFirmAccountType accountType;
  final double accountSize;
  final double startingBalance;
  final double currentBalance;
  final double? manuallyEnteredEquity;
  final String fundedStatus;
  final String phase;
  final double dailyLossLimit;
  final double maximumLossLimit;
  final LossLimitType dailyLossType;
  final LossLimitType maximumLossType;
  final DrawdownType drawdownType;
  final double profitTarget;
  final double minimumPayout;
  final double desiredPayout;
  final double profitSplit;
  final int minimumTradingDays;
  final int completedTradingDays;
  final String payoutFrequency;
  final DateTime? nextPayoutDate;
  final double consistencyRuleLimit;
  final double maxRiskPerTrade;
  final bool dailyTradeLimitEnabled;
  final int maxTradesPerDay;
  final int tradesToday;
  final int consecutiveWins;
  final int consecutiveLosses;
  final String lastTradeResult;
  final String notes;
  final bool isAvailableToday;
  final bool isManuallyExcluded;
  final bool isManuallyProtected;
  final double personalDailyStopLossAmount;
  final double personalMaximumRiskAmount;
  final double safetyBufferPercentage;
  final double overallRealisedProfitLoss;
  final double overallUnrealisedProfitLoss;
  final int openPositionsCount;
  final double currentRiskExposureAmount;
  final double currentRiskExposurePercentage;
  final bool dailyTargetReached;
  final bool sessionCompleted;
  final AccountStatus status;
  final AccountPriority priority;
  final int colorValue;
  final double virtualRiskAllocation;
  final DateTime? cooldownUntil;
  final DateTime? lastTradeAt;
  final AccountCurrentSituation situation;
  final DateTime createdAt;
  final DateTime updatedAt;

  double get currentProfit => currentBalance - startingBalance;
  double get referenceEquity => manuallyEnteredEquity ?? currentBalance;
  double get drawdown => math.max(0, startingBalance - referenceEquity);
  double get remainingMaximumDrawdown =>
      math.max(0, maximumLossLimit - drawdown);
  bool get isArchived => status == AccountStatus.archived;
  bool get isFundedAccount => accountType == PropFirmAccountType.funded;
  bool get isEvaluationAccount => accountType == PropFirmAccountType.evaluation;
  bool get isPayoutNear =>
      math.max(minimumPayout, desiredPayout) > 0 &&
      currentProfit >= math.max(minimumPayout, desiredPayout) * .8;
  int get dailyTradeLimit => maxTradesPerDay;

  PropFirmAccount copyWith({
    String? ownerUid,
    String? name,
    String? propFirmName,
    String? identifier,
    String? currency,
    PropFirmAccountType? accountType,
    double? accountSize,
    double? startingBalance,
    double? currentBalance,
    double? manuallyEnteredEquity,
    bool clearEquity = false,
    String? fundedStatus,
    String? phase,
    double? dailyLossLimit,
    double? maximumLossLimit,
    LossLimitType? dailyLossType,
    LossLimitType? maximumLossType,
    DrawdownType? drawdownType,
    double? profitTarget,
    double? minimumPayout,
    double? desiredPayout,
    double? profitSplit,
    int? minimumTradingDays,
    int? completedTradingDays,
    String? payoutFrequency,
    DateTime? nextPayoutDate,
    bool clearNextPayoutDate = false,
    double? consistencyRuleLimit,
    double? maxRiskPerTrade,
    bool? dailyTradeLimitEnabled,
    int? maxTradesPerDay,
    int? tradesToday,
    int? consecutiveWins,
    int? consecutiveLosses,
    String? lastTradeResult,
    String? notes,
    bool? isAvailableToday,
    bool? isManuallyExcluded,
    bool? isManuallyProtected,
    double? personalDailyStopLossAmount,
    double? personalMaximumRiskAmount,
    double? safetyBufferPercentage,
    double? overallRealisedProfitLoss,
    double? overallUnrealisedProfitLoss,
    int? openPositionsCount,
    double? currentRiskExposureAmount,
    double? currentRiskExposurePercentage,
    bool? dailyTargetReached,
    bool? sessionCompleted,
    AccountStatus? status,
    AccountPriority? priority,
    int? colorValue,
    double? virtualRiskAllocation,
    DateTime? cooldownUntil,
    bool clearCooldown = false,
    DateTime? lastTradeAt,
    AccountCurrentSituation? situation,
    DateTime? updatedAt,
  }) => PropFirmAccount(
    id: id,
    ownerUid: ownerUid ?? this.ownerUid,
    name: name ?? this.name,
    propFirmName: propFirmName ?? this.propFirmName,
    identifier: identifier ?? this.identifier,
    currency: currency ?? this.currency,
    accountType: accountType ?? this.accountType,
    accountSize: accountSize ?? this.accountSize,
    startingBalance: startingBalance ?? this.startingBalance,
    currentBalance: currentBalance ?? this.currentBalance,
    manuallyEnteredEquity: clearEquity
        ? null
        : manuallyEnteredEquity ?? this.manuallyEnteredEquity,
    fundedStatus: fundedStatus ?? this.fundedStatus,
    phase: phase ?? this.phase,
    dailyLossLimit: dailyLossLimit ?? this.dailyLossLimit,
    maximumLossLimit: maximumLossLimit ?? this.maximumLossLimit,
    dailyLossType: dailyLossType ?? this.dailyLossType,
    maximumLossType: maximumLossType ?? this.maximumLossType,
    drawdownType: drawdownType ?? this.drawdownType,
    profitTarget: profitTarget ?? this.profitTarget,
    minimumPayout: minimumPayout ?? this.minimumPayout,
    desiredPayout: desiredPayout ?? this.desiredPayout,
    profitSplit: profitSplit ?? this.profitSplit,
    minimumTradingDays: minimumTradingDays ?? this.minimumTradingDays,
    completedTradingDays: completedTradingDays ?? this.completedTradingDays,
    payoutFrequency: payoutFrequency ?? this.payoutFrequency,
    nextPayoutDate: clearNextPayoutDate
        ? null
        : nextPayoutDate ?? this.nextPayoutDate,
    consistencyRuleLimit: consistencyRuleLimit ?? this.consistencyRuleLimit,
    maxRiskPerTrade: maxRiskPerTrade ?? this.maxRiskPerTrade,
    dailyTradeLimitEnabled:
        dailyTradeLimitEnabled ?? this.dailyTradeLimitEnabled,
    maxTradesPerDay: maxTradesPerDay ?? this.maxTradesPerDay,
    tradesToday: tradesToday ?? this.tradesToday,
    consecutiveWins: consecutiveWins ?? this.consecutiveWins,
    consecutiveLosses: consecutiveLosses ?? this.consecutiveLosses,
    lastTradeResult: lastTradeResult ?? this.lastTradeResult,
    notes: notes ?? this.notes,
    isAvailableToday: isAvailableToday ?? this.isAvailableToday,
    isManuallyExcluded: isManuallyExcluded ?? this.isManuallyExcluded,
    isManuallyProtected: isManuallyProtected ?? this.isManuallyProtected,
    personalDailyStopLossAmount:
        personalDailyStopLossAmount ?? this.personalDailyStopLossAmount,
    personalMaximumRiskAmount:
        personalMaximumRiskAmount ?? this.personalMaximumRiskAmount,
    safetyBufferPercentage:
        safetyBufferPercentage ?? this.safetyBufferPercentage,
    overallRealisedProfitLoss:
        overallRealisedProfitLoss ?? this.overallRealisedProfitLoss,
    overallUnrealisedProfitLoss:
        overallUnrealisedProfitLoss ?? this.overallUnrealisedProfitLoss,
    openPositionsCount: openPositionsCount ?? this.openPositionsCount,
    currentRiskExposureAmount:
        currentRiskExposureAmount ?? this.currentRiskExposureAmount,
    currentRiskExposurePercentage:
        currentRiskExposurePercentage ?? this.currentRiskExposurePercentage,
    dailyTargetReached: dailyTargetReached ?? this.dailyTargetReached,
    sessionCompleted: sessionCompleted ?? this.sessionCompleted,
    status: status ?? this.status,
    priority: priority ?? this.priority,
    colorValue: colorValue ?? this.colorValue,
    virtualRiskAllocation: virtualRiskAllocation ?? this.virtualRiskAllocation,
    cooldownUntil: clearCooldown ? null : cooldownUntil ?? this.cooldownUntil,
    lastTradeAt: lastTradeAt ?? this.lastTradeAt,
    situation: situation ?? this.situation,
    createdAt: createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  factory PropFirmAccount.fromJson(
    Map<String, dynamic> json, {
    int fallbackDailyTradeLimit = 3,
    bool fallbackDailyTradeLimitEnabled = true,
  }) => PropFirmAccount(
    id: '${json['id'] ?? ''}',
    ownerUid: '${json['owner_uid'] ?? json['ownerUid'] ?? ''}',
    name: '${json['name'] ?? json['account_name'] ?? ''}',
    propFirmName: '${json['prop_firm_name'] ?? ''}',
    identifier:
        '${json['identifier'] ?? json['account_number'] ?? json['masked_account_number'] ?? ''}',
    currency: '${json['currency'] ?? 'USD'}',
    accountType: _enumValue(
      PropFirmAccountType.values,
      json['account_type'],
      '${json['phase'] ?? json['funded_status'] ?? ''}'.toLowerCase().contains(
            'eval',
          )
          ? PropFirmAccountType.evaluation
          : PropFirmAccountType.funded,
    ),
    accountSize: _double(json['account_size'] ?? json['account_capital']),
    startingBalance: _double(
      json['starting_balance'] ?? json['account_capital'],
    ),
    currentBalance: _double(json['current_balance'] ?? json['balance']),
    manuallyEnteredEquity: (json['manual_equity'] ?? json['equity']) == null
        ? null
        : _double(json['manual_equity'] ?? json['equity']),
    fundedStatus: '${json['funded_status'] ?? 'Funded'}',
    phase: '${json['phase'] ?? 'Funded'}',
    dailyLossLimit: _double(
      json['daily_loss_limit'] ?? json['daily_loss_value'],
    ),
    maximumLossLimit: _double(
      json['maximum_loss_limit'] ?? json['maximum_loss_value'],
    ),
    dailyLossType: _enumValue(
      LossLimitType.values,
      json['daily_loss_type'],
      LossLimitType.fixedAmount,
    ),
    maximumLossType: _enumValue(
      LossLimitType.values,
      json['maximum_loss_type'],
      LossLimitType.fixedAmount,
    ),
    drawdownType: _enumValue(
      DrawdownType.values,
      json['drawdown_type'],
      DrawdownType.static,
    ),
    profitTarget: _double(json['profit_target']),
    minimumPayout: _double(json['minimum_payout']),
    desiredPayout: _double(json['desired_payout']),
    profitSplit: _double(json['profit_split'], .8).clamp(0, 1),
    minimumTradingDays: _int(json['minimum_trading_days']),
    completedTradingDays: _int(json['completed_trading_days']),
    payoutFrequency: '${json['payout_frequency'] ?? 'On demand'}',
    nextPayoutDate: _date(json['next_payout_date']),
    consistencyRuleLimit: _double(json['consistency_rule_limit']),
    maxRiskPerTrade: _double(json['max_risk_per_trade']),
    dailyTradeLimitEnabled: _bool(
      json['daily_trade_limit_enabled'],
      fallbackDailyTradeLimitEnabled,
    ),
    maxTradesPerDay: _int(
      json['daily_trade_limit'] ?? json['max_trades_per_day'],
      fallbackDailyTradeLimit,
    ),
    tradesToday: _int(json['daily_trade_count'] ?? json['trades_today']),
    consecutiveWins: _int(json['consecutive_wins']),
    consecutiveLosses: _int(json['consecutive_losses']),
    lastTradeResult: '${json['last_trade_result'] ?? ''}',
    notes: '${json['notes'] ?? ''}',
    isAvailableToday: _bool(json['is_available_today'], true),
    isManuallyExcluded: _bool(json['is_manually_excluded']),
    isManuallyProtected: _bool(json['is_manually_protected']),
    personalDailyStopLossAmount: _double(
      json['personal_daily_stop_loss_amount'],
    ),
    personalMaximumRiskAmount: _double(json['personal_maximum_risk_amount']),
    safetyBufferPercentage: _double(json['safety_buffer_percentage']),
    overallRealisedProfitLoss: _double(json['overall_realised_profit_loss']),
    overallUnrealisedProfitLoss: _double(
      json['overall_unrealised_profit_loss'],
    ),
    openPositionsCount: _int(json['open_positions_count']),
    currentRiskExposureAmount: _double(json['current_risk_exposure_amount']),
    currentRiskExposurePercentage: _double(
      json['current_risk_exposure_percentage'],
    ),
    dailyTargetReached: _bool(json['daily_target_reached']),
    sessionCompleted: _bool(json['session_completed']),
    status: _enumValue(
      AccountStatus.values,
      json['status'],
      AccountStatus.available,
    ),
    priority: _enumValue(
      AccountPriority.values,
      json['priority'],
      AccountPriority.standard,
    ),
    colorValue: _int(json['color_value'], 0xFF00796B),
    virtualRiskAllocation: _double(json['virtual_risk_allocation']),
    cooldownUntil: _date(json['cooldown_until']),
    lastTradeAt: _date(json['last_trade_at']),
    situation: AccountCurrentSituation.fromJson(
      Map<String, dynamic>.from(json['situation'] as Map? ?? const {}),
    ),
    createdAt: _date(json['created_at']) ?? DateTime(2026),
    updatedAt:
        _date(json['updated_at']) ??
        _date(json['created_at']) ??
        DateTime(2026),
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'owner_uid': ownerUid,
    'name': name,
    'prop_firm_name': propFirmName,
    'identifier': identifier,
    'currency': currency,
    'account_type': accountType.name,
    'account_size': accountSize,
    'starting_balance': startingBalance,
    'current_balance': currentBalance,
    'manual_equity': manuallyEnteredEquity,
    'funded_status': fundedStatus,
    'phase': phase,
    'daily_loss_limit': dailyLossLimit,
    'maximum_loss_limit': maximumLossLimit,
    'daily_loss_type': dailyLossType.name,
    'maximum_loss_type': maximumLossType.name,
    'drawdown_type': drawdownType.name,
    'profit_target': profitTarget,
    'minimum_payout': minimumPayout,
    'desired_payout': desiredPayout,
    'profit_split': profitSplit,
    'minimum_trading_days': minimumTradingDays,
    'completed_trading_days': completedTradingDays,
    'payout_frequency': payoutFrequency,
    'next_payout_date': nextPayoutDate?.toIso8601String(),
    'consistency_rule_limit': consistencyRuleLimit,
    'max_risk_per_trade': maxRiskPerTrade,
    'daily_trade_limit_enabled': dailyTradeLimitEnabled,
    'daily_trade_limit': maxTradesPerDay,
    'max_trades_per_day': maxTradesPerDay,
    'trades_today': tradesToday,
    'consecutive_wins': consecutiveWins,
    'consecutive_losses': consecutiveLosses,
    'last_trade_result': lastTradeResult,
    'notes': notes,
    'is_available_today': isAvailableToday,
    'is_manually_excluded': isManuallyExcluded,
    'is_manually_protected': isManuallyProtected,
    'personal_daily_stop_loss_amount': personalDailyStopLossAmount,
    'personal_maximum_risk_amount': personalMaximumRiskAmount,
    'safety_buffer_percentage': safetyBufferPercentage,
    'overall_realised_profit_loss': overallRealisedProfitLoss,
    'overall_unrealised_profit_loss': overallUnrealisedProfitLoss,
    'open_positions_count': openPositionsCount,
    'current_risk_exposure_amount': currentRiskExposureAmount,
    'current_risk_exposure_percentage': currentRiskExposurePercentage,
    'daily_target_reached': dailyTargetReached,
    'session_completed': sessionCompleted,
    'status': status.name,
    'priority': priority.name,
    'color_value': colorValue,
    'virtual_risk_allocation': virtualRiskAllocation,
    'cooldown_until': cooldownUntil?.toIso8601String(),
    'last_trade_at': lastTradeAt?.toIso8601String(),
    'situation': situation.toJson(),
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
  };
}

class PortfolioRiskPolicy {
  const PortfolioRiskPolicy({
    required this.maximumDailyLoss,
    required this.maximumRiskPerTrade,
    this.maximumTradesPerDay = 3,
    this.maximumConsecutiveLosses = 2,
    this.maximumAccountsTradedPerDay = 3,
    this.maximumOpenPositions = 1,
    this.simultaneousTradingAllowed = false,
    this.cooldownAfterWinMinutes = 30,
    this.cooldownAfterLossMinutes = 60,
    this.stopAfterEmotionalEvent = true,
    this.stopAfterOneWinningTrade = false,
    this.stopAfterOneLosingTrade = false,
    this.stopAfterConsecutiveLosses = true,
    this.stopAfterMaximumTrades = true,
    this.stopAfterDailyTarget = false,
    this.maximumSessionLossR = 0,
    this.combinedSessionProfitTarget = 0,
    this.maximumAccountsSameInstrument = 1,
    this.maximumCombinedRisk = 0,
    this.resetHourUtc = 0,
    this.resetMinute = 0,
    this.resetTimeZone = 'UTC',
    this.resetUtcOffsetMinutes = 0,
  });

  final double maximumDailyLoss;
  final double maximumRiskPerTrade;
  final int maximumTradesPerDay;
  final int maximumConsecutiveLosses;
  final int maximumAccountsTradedPerDay;
  final int maximumOpenPositions;
  final bool simultaneousTradingAllowed;
  final int cooldownAfterWinMinutes;
  final int cooldownAfterLossMinutes;
  final bool stopAfterEmotionalEvent;
  final bool stopAfterOneWinningTrade;
  final bool stopAfterOneLosingTrade;
  final bool stopAfterConsecutiveLosses;
  final bool stopAfterMaximumTrades;
  final bool stopAfterDailyTarget;
  final double maximumSessionLossR;
  final double combinedSessionProfitTarget;
  final int maximumAccountsSameInstrument;
  final double maximumCombinedRisk;
  final int resetHourUtc;
  final int resetMinute;
  final String resetTimeZone;
  final int resetUtcOffsetMinutes;

  PortfolioRiskPolicy copyWith({
    double? maximumDailyLoss,
    double? maximumRiskPerTrade,
    int? maximumTradesPerDay,
    int? maximumConsecutiveLosses,
    int? maximumAccountsTradedPerDay,
    int? maximumOpenPositions,
    bool? simultaneousTradingAllowed,
    int? cooldownAfterWinMinutes,
    int? cooldownAfterLossMinutes,
    bool? stopAfterEmotionalEvent,
    bool? stopAfterOneWinningTrade,
    bool? stopAfterOneLosingTrade,
    bool? stopAfterConsecutiveLosses,
    bool? stopAfterMaximumTrades,
    bool? stopAfterDailyTarget,
    double? maximumSessionLossR,
    double? combinedSessionProfitTarget,
    int? maximumAccountsSameInstrument,
    double? maximumCombinedRisk,
    int? resetHourUtc,
    int? resetMinute,
    String? resetTimeZone,
    int? resetUtcOffsetMinutes,
  }) => PortfolioRiskPolicy(
    maximumDailyLoss: maximumDailyLoss ?? this.maximumDailyLoss,
    maximumRiskPerTrade: maximumRiskPerTrade ?? this.maximumRiskPerTrade,
    maximumTradesPerDay: maximumTradesPerDay ?? this.maximumTradesPerDay,
    maximumConsecutiveLosses:
        maximumConsecutiveLosses ?? this.maximumConsecutiveLosses,
    maximumAccountsTradedPerDay:
        maximumAccountsTradedPerDay ?? this.maximumAccountsTradedPerDay,
    maximumOpenPositions: maximumOpenPositions ?? this.maximumOpenPositions,
    simultaneousTradingAllowed:
        simultaneousTradingAllowed ?? this.simultaneousTradingAllowed,
    cooldownAfterWinMinutes:
        cooldownAfterWinMinutes ?? this.cooldownAfterWinMinutes,
    cooldownAfterLossMinutes:
        cooldownAfterLossMinutes ?? this.cooldownAfterLossMinutes,
    stopAfterEmotionalEvent:
        stopAfterEmotionalEvent ?? this.stopAfterEmotionalEvent,
    stopAfterOneWinningTrade:
        stopAfterOneWinningTrade ?? this.stopAfterOneWinningTrade,
    stopAfterOneLosingTrade:
        stopAfterOneLosingTrade ?? this.stopAfterOneLosingTrade,
    stopAfterConsecutiveLosses:
        stopAfterConsecutiveLosses ?? this.stopAfterConsecutiveLosses,
    stopAfterMaximumTrades:
        stopAfterMaximumTrades ?? this.stopAfterMaximumTrades,
    stopAfterDailyTarget: stopAfterDailyTarget ?? this.stopAfterDailyTarget,
    maximumSessionLossR: maximumSessionLossR ?? this.maximumSessionLossR,
    combinedSessionProfitTarget:
        combinedSessionProfitTarget ?? this.combinedSessionProfitTarget,
    maximumAccountsSameInstrument:
        maximumAccountsSameInstrument ?? this.maximumAccountsSameInstrument,
    maximumCombinedRisk: maximumCombinedRisk ?? this.maximumCombinedRisk,
    resetHourUtc: resetHourUtc ?? this.resetHourUtc,
    resetMinute: resetMinute ?? this.resetMinute,
    resetTimeZone: resetTimeZone ?? this.resetTimeZone,
    resetUtcOffsetMinutes: resetUtcOffsetMinutes ?? this.resetUtcOffsetMinutes,
  );

  factory PortfolioRiskPolicy.fromJson(Map<String, dynamic> json) =>
      PortfolioRiskPolicy(
        maximumDailyLoss: _double(json['maximum_daily_loss'], 200),
        maximumRiskPerTrade: _double(json['maximum_risk_per_trade'], 100),
        maximumTradesPerDay: _int(json['maximum_trades_per_day'], 3),
        maximumConsecutiveLosses: _int(json['maximum_consecutive_losses'], 2),
        maximumAccountsTradedPerDay: _int(
          json['maximum_accounts_traded_per_day'],
          3,
        ),
        maximumOpenPositions: _int(json['maximum_open_positions'], 1),
        simultaneousTradingAllowed: _bool(json['simultaneous_trading_allowed']),
        cooldownAfterWinMinutes: _int(json['cooldown_after_win_minutes'], 30),
        cooldownAfterLossMinutes: _int(json['cooldown_after_loss_minutes'], 60),
        stopAfterEmotionalEvent: _bool(
          json['stop_after_emotional_event'],
          true,
        ),
        stopAfterOneWinningTrade: _bool(json['stop_after_one_winning_trade']),
        stopAfterOneLosingTrade: _bool(json['stop_after_one_losing_trade']),
        stopAfterConsecutiveLosses: _bool(
          json['stop_after_consecutive_losses'],
          true,
        ),
        stopAfterMaximumTrades: _bool(json['stop_after_maximum_trades'], true),
        stopAfterDailyTarget: _bool(json['stop_after_daily_target']),
        maximumSessionLossR: _double(json['maximum_session_loss_r']),
        combinedSessionProfitTarget: _double(
          json['combined_session_profit_target'],
        ),
        maximumAccountsSameInstrument: _int(
          json['maximum_accounts_same_instrument'],
          1,
        ),
        maximumCombinedRisk: _double(json['maximum_combined_risk']),
        resetHourUtc: _int(json['reset_hour_utc']).clamp(0, 23),
        resetMinute: _int(json['reset_minute']).clamp(0, 59),
        resetTimeZone: '${json['reset_time_zone'] ?? 'UTC'}',
        resetUtcOffsetMinutes: _int(
          json['reset_utc_offset_minutes'],
        ).clamp(-720, 840),
      );

  Map<String, Object?> toJson() => {
    'maximum_daily_loss': maximumDailyLoss,
    'maximum_risk_per_trade': maximumRiskPerTrade,
    'maximum_trades_per_day': maximumTradesPerDay,
    'maximum_consecutive_losses': maximumConsecutiveLosses,
    'maximum_accounts_traded_per_day': maximumAccountsTradedPerDay,
    'maximum_open_positions': maximumOpenPositions,
    'simultaneous_trading_allowed': simultaneousTradingAllowed,
    'cooldown_after_win_minutes': cooldownAfterWinMinutes,
    'cooldown_after_loss_minutes': cooldownAfterLossMinutes,
    'stop_after_emotional_event': stopAfterEmotionalEvent,
    'stop_after_one_winning_trade': stopAfterOneWinningTrade,
    'stop_after_one_losing_trade': stopAfterOneLosingTrade,
    'stop_after_consecutive_losses': stopAfterConsecutiveLosses,
    'stop_after_maximum_trades': stopAfterMaximumTrades,
    'stop_after_daily_target': stopAfterDailyTarget,
    'maximum_session_loss_r': maximumSessionLossR,
    'combined_session_profit_target': combinedSessionProfitTarget,
    'maximum_accounts_same_instrument': maximumAccountsSameInstrument,
    'maximum_combined_risk': maximumCombinedRisk,
    'reset_hour_utc': resetHourUtc,
    'reset_minute': resetMinute,
    'reset_time_zone': resetTimeZone,
    'reset_utc_offset_minutes': resetUtcOffsetMinutes,
  };
}

class RotationStrategy {
  const RotationStrategy({
    this.template = RotationTemplate.balanced,
    this.rotateAfterWin = true,
    this.rotateAfterLoss = true,
    this.lockAfterLoss = true,
    this.protectPayoutReadyAccounts = true,
    this.oneAccountPerSession = false,
    this.recoveryRiskMultiplier = .5,
  });

  final RotationTemplate template;
  final bool rotateAfterWin;
  final bool rotateAfterLoss;
  final bool lockAfterLoss;
  final bool protectPayoutReadyAccounts;
  final bool oneAccountPerSession;
  final double recoveryRiskMultiplier;

  factory RotationStrategy.forTemplate(RotationTemplate template) =>
      switch (template) {
        RotationTemplate.balanced => const RotationStrategy(),
        RotationTemplate.payoutProtection => const RotationStrategy(
          template: RotationTemplate.payoutProtection,
          protectPayoutReadyAccounts: true,
          recoveryRiskMultiplier: .5,
        ),
        RotationTemplate.recoveryIsolation => const RotationStrategy(
          template: RotationTemplate.recoveryIsolation,
          lockAfterLoss: false,
          recoveryRiskMultiplier: .5,
        ),
        RotationTemplate.oneAccountPerSession => const RotationStrategy(
          template: RotationTemplate.oneAccountPerSession,
          rotateAfterWin: false,
          rotateAfterLoss: false,
          lockAfterLoss: false,
          oneAccountPerSession: true,
        ),
        RotationTemplate.custom => const RotationStrategy(
          template: RotationTemplate.custom,
          lockAfterLoss: false,
        ),
      };

  factory RotationStrategy.fromJson(Map<String, dynamic> json) =>
      RotationStrategy(
        template: _enumValue(
          RotationTemplate.values,
          json['template'],
          RotationTemplate.balanced,
        ),
        rotateAfterWin: _bool(json['rotate_after_win'], true),
        rotateAfterLoss: _bool(json['rotate_after_loss'], true),
        lockAfterLoss: _bool(json['lock_after_loss'], true),
        protectPayoutReadyAccounts: _bool(
          json['protect_payout_ready_accounts'],
          true,
        ),
        oneAccountPerSession: _bool(json['one_account_per_session']),
        recoveryRiskMultiplier: _double(
          json['recovery_risk_multiplier'],
          .5,
        ).clamp(0, 1),
      );

  Map<String, Object?> toJson() => {
    'template': template.name,
    'rotate_after_win': rotateAfterWin,
    'rotate_after_loss': rotateAfterLoss,
    'lock_after_loss': lockAfterLoss,
    'protect_payout_ready_accounts': protectPayoutReadyAccounts,
    'one_account_per_session': oneAccountPerSession,
    'recovery_risk_multiplier': recoveryRiskMultiplier,
  };
}

class PsychologyCheck {
  const PsychologyCheck({
    this.isCalm = true,
    this.recoveringRecentLoss = false,
    this.increasingRiskFromFrustration = false,
    this.fomo = false,
    this.duplicateIdea = false,
    this.followsPlan = true,
    this.acceptsLoss = true,
    this.stopConditionReached = false,
  });

  final bool isCalm;
  final bool recoveringRecentLoss;
  final bool increasingRiskFromFrustration;
  final bool fomo;
  final bool duplicateIdea;
  final bool followsPlan;
  final bool acceptsLoss;
  final bool stopConditionReached;

  bool get highRisk =>
      !isCalm ||
      recoveringRecentLoss ||
      increasingRiskFromFrustration ||
      fomo ||
      !acceptsLoss ||
      stopConditionReached;

  factory PsychologyCheck.fromJson(Map<String, dynamic> json) =>
      PsychologyCheck(
        isCalm: _bool(json['is_calm'], true),
        recoveringRecentLoss: _bool(json['recovering_recent_loss']),
        increasingRiskFromFrustration: _bool(
          json['increasing_risk_from_frustration'],
        ),
        fomo: _bool(json['fomo']),
        duplicateIdea: _bool(json['duplicate_idea']),
        followsPlan: _bool(json['follows_plan'], true),
        acceptsLoss: _bool(json['accepts_loss'], true),
        stopConditionReached: _bool(json['stop_condition_reached']),
      );

  Map<String, Object?> toJson() => {
    'is_calm': isCalm,
    'recovering_recent_loss': recoveringRecentLoss,
    'increasing_risk_from_frustration': increasingRiskFromFrustration,
    'fomo': fomo,
    'duplicate_idea': duplicateIdea,
    'follows_plan': followsPlan,
    'accepts_loss': acceptsLoss,
    'stop_condition_reached': stopConditionReached,
  };
}

class PlannedTrade {
  const PlannedTrade({
    required this.id,
    required this.accountId,
    required this.instrument,
    required this.direction,
    required this.entryPrice,
    required this.stopLossPrice,
    required this.takeProfitPrice,
    required this.riskAmount,
    required this.riskPercentage,
    required this.expectedReward,
    required this.rewardRiskRatio,
    required this.strategy,
    required this.setupQuality,
    required this.tradeReason,
    required this.invalidationReason,
    required this.session,
    required this.psychology,
    this.screenshotPath = '',
    this.overrideReason = '',
    required this.createdAt,
    this.isOpen = true,
  });

  final String id;
  final String accountId;
  final String instrument;
  final TradeDirection direction;
  final double entryPrice;
  final double stopLossPrice;
  final double takeProfitPrice;
  final double riskAmount;
  final double riskPercentage;
  final double expectedReward;
  final double rewardRiskRatio;
  final String strategy;
  final String setupQuality;
  final String tradeReason;
  final String invalidationReason;
  final String session;
  final PsychologyCheck psychology;
  final String screenshotPath;
  final String overrideReason;
  final DateTime createdAt;
  final bool isOpen;

  PlannedTrade copyWith({bool? isOpen, String? overrideReason}) => PlannedTrade(
    id: id,
    accountId: accountId,
    instrument: instrument,
    direction: direction,
    entryPrice: entryPrice,
    stopLossPrice: stopLossPrice,
    takeProfitPrice: takeProfitPrice,
    riskAmount: riskAmount,
    riskPercentage: riskPercentage,
    expectedReward: expectedReward,
    rewardRiskRatio: rewardRiskRatio,
    strategy: strategy,
    setupQuality: setupQuality,
    tradeReason: tradeReason,
    invalidationReason: invalidationReason,
    session: session,
    psychology: psychology,
    screenshotPath: screenshotPath,
    overrideReason: overrideReason ?? this.overrideReason,
    createdAt: createdAt,
    isOpen: isOpen ?? this.isOpen,
  );

  factory PlannedTrade.fromJson(Map<String, dynamic> json) => PlannedTrade(
    id: '${json['id'] ?? ''}',
    accountId: '${json['account_id'] ?? ''}',
    instrument: '${json['instrument'] ?? ''}',
    direction: _enumValue(
      TradeDirection.values,
      json['direction'],
      TradeDirection.buy,
    ),
    entryPrice: _double(json['entry_price']),
    stopLossPrice: _double(json['stop_loss_price']),
    takeProfitPrice: _double(json['take_profit_price']),
    riskAmount: _double(json['risk_amount']),
    riskPercentage: _double(json['risk_percentage']),
    expectedReward: _double(json['expected_reward']),
    rewardRiskRatio: _double(json['reward_risk_ratio']),
    strategy: '${json['strategy'] ?? ''}',
    setupQuality: '${json['setup_quality'] ?? ''}',
    tradeReason: '${json['trade_reason'] ?? ''}',
    invalidationReason: '${json['invalidation_reason'] ?? ''}',
    session: '${json['session'] ?? ''}',
    psychology: PsychologyCheck.fromJson(
      Map<String, dynamic>.from(json['psychology'] as Map? ?? const {}),
    ),
    screenshotPath: '${json['screenshot_path'] ?? ''}',
    overrideReason: '${json['override_reason'] ?? ''}',
    createdAt: _date(json['created_at']) ?? DateTime(2026),
    isOpen: _bool(json['is_open'], true),
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'account_id': accountId,
    'instrument': instrument,
    'direction': direction.name,
    'entry_price': entryPrice,
    'stop_loss_price': stopLossPrice,
    'take_profit_price': takeProfitPrice,
    'risk_amount': riskAmount,
    'risk_percentage': riskPercentage,
    'expected_reward': expectedReward,
    'reward_risk_ratio': rewardRiskRatio,
    'strategy': strategy,
    'setup_quality': setupQuality,
    'trade_reason': tradeReason,
    'invalidation_reason': invalidationReason,
    'session': session,
    'psychology': psychology.toJson(),
    'screenshot_path': screenshotPath,
    'override_reason': overrideReason,
    'created_at': createdAt.toIso8601String(),
    'is_open': isOpen,
  };
}

class RecordedTradeResult {
  const RecordedTradeResult({
    required this.id,
    required this.accountId,
    this.plannedTradeId,
    required this.type,
    required this.amount,
    this.enteredAmount,
    required this.rMultiple,
    required this.resultTime,
    this.mistakeCategory = '',
    this.emotionalState = 'Calm',
    this.followedPlan = true,
    this.followedRotation = true,
    this.notes = '',
    this.screenshotPath = '',
    this.tradeReference = '',
    this.sessionId,
    this.balanceBefore,
    this.balanceAfter,
    this.equityBefore,
    this.equityAfter,
    this.healthScoreBefore,
    this.healthScoreAfter,
    this.dailyLossRemainingAfter,
    this.maximumLossRemainingAfter,
  });

  final String id;
  final String accountId;
  final String? plannedTradeId;
  final TradeResultType type;
  final double amount;
  final double? enteredAmount;
  final double rMultiple;
  final DateTime resultTime;
  final String mistakeCategory;
  final String emotionalState;
  final bool followedPlan;
  final bool followedRotation;
  final String notes;
  final String screenshotPath;
  final String tradeReference;
  final String? sessionId;
  final double? balanceBefore;
  final double? balanceAfter;
  final double? equityBefore;
  final double? equityAfter;
  final double? healthScoreBefore;
  final double? healthScoreAfter;
  final double? dailyLossRemainingAfter;
  final double? maximumLossRemainingAfter;

  bool get isLoss => amount < 0 || rMultiple < 0;
  bool get isWin => amount > 0 || rMultiple > 0;

  RecordedTradeResult copyWith({
    String? sessionId,
    double? balanceBefore,
    double? balanceAfter,
    double? equityBefore,
    double? equityAfter,
    double? healthScoreBefore,
    double? healthScoreAfter,
    double? dailyLossRemainingAfter,
    double? maximumLossRemainingAfter,
  }) => RecordedTradeResult(
    id: id,
    accountId: accountId,
    plannedTradeId: plannedTradeId,
    type: type,
    amount: amount,
    enteredAmount: enteredAmount,
    rMultiple: rMultiple,
    resultTime: resultTime,
    mistakeCategory: mistakeCategory,
    emotionalState: emotionalState,
    followedPlan: followedPlan,
    followedRotation: followedRotation,
    notes: notes,
    screenshotPath: screenshotPath,
    tradeReference: tradeReference,
    sessionId: sessionId ?? this.sessionId,
    balanceBefore: balanceBefore ?? this.balanceBefore,
    balanceAfter: balanceAfter ?? this.balanceAfter,
    equityBefore: equityBefore ?? this.equityBefore,
    equityAfter: equityAfter ?? this.equityAfter,
    healthScoreBefore: healthScoreBefore ?? this.healthScoreBefore,
    healthScoreAfter: healthScoreAfter ?? this.healthScoreAfter,
    dailyLossRemainingAfter:
        dailyLossRemainingAfter ?? this.dailyLossRemainingAfter,
    maximumLossRemainingAfter:
        maximumLossRemainingAfter ?? this.maximumLossRemainingAfter,
  );

  factory RecordedTradeResult.fromJson(Map<String, dynamic> json) =>
      RecordedTradeResult(
        id: '${json['id'] ?? ''}',
        accountId: '${json['account_id'] ?? ''}',
        plannedTradeId: json['planned_trade_id']?.toString(),
        type: _enumValue(
          TradeResultType.values,
          json['type'],
          TradeResultType.closedManually,
        ),
        amount: _double(json['amount']),
        enteredAmount: _nullableDouble(json['entered_amount']),
        rMultiple: _double(json['r_multiple']),
        resultTime: _date(json['result_time']) ?? DateTime(2026),
        mistakeCategory: '${json['mistake_category'] ?? ''}',
        emotionalState: '${json['emotional_state'] ?? 'Calm'}',
        followedPlan: _bool(json['followed_plan'], true),
        followedRotation: _bool(json['followed_rotation'], true),
        notes: '${json['notes'] ?? ''}',
        screenshotPath: '${json['screenshot_path'] ?? ''}',
        tradeReference: '${json['trade_reference'] ?? ''}',
        sessionId: json['session_id']?.toString(),
        balanceBefore: _nullableDouble(json['balance_before']),
        balanceAfter: _nullableDouble(json['balance_after']),
        equityBefore: _nullableDouble(json['equity_before']),
        equityAfter: _nullableDouble(json['equity_after']),
        healthScoreBefore: _nullableDouble(json['health_score_before']),
        healthScoreAfter: _nullableDouble(json['health_score_after']),
        dailyLossRemainingAfter: _nullableDouble(
          json['daily_loss_remaining_after'],
        ),
        maximumLossRemainingAfter: _nullableDouble(
          json['maximum_loss_remaining_after'],
        ),
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'account_id': accountId,
    'planned_trade_id': plannedTradeId,
    'type': type.name,
    'amount': amount,
    'entered_amount': enteredAmount ?? amount,
    'normalized_amount': amount,
    'result_type': type.name,
    'r_multiple': rMultiple,
    'result_time': resultTime.toUtc().toIso8601String(),
    'created_at': resultTime.toUtc().toIso8601String(),
    'mistake_category': mistakeCategory,
    'emotional_state': emotionalState,
    'followed_plan': followedPlan,
    'followed_rotation': followedRotation,
    'notes': notes,
    'screenshot_path': screenshotPath,
    'trade_reference': tradeReference,
    'session_id': sessionId,
    'balance_before': balanceBefore,
    'balance_after': balanceAfter,
    'equity_before': equityBefore,
    'equity_after': equityAfter,
    'health_score_before': healthScoreBefore,
    'health_score_after': healthScoreAfter,
    'daily_loss_remaining_after': dailyLossRemainingAfter,
    'maximum_loss_remaining_after': maximumLossRemainingAfter,
  };
}

class DisciplineRecord {
  const DisciplineRecord({
    required this.id,
    required this.type,
    required this.detail,
    required this.createdAt,
    this.overrideReason = '',
  });

  final String id;
  final String type;
  final String detail;
  final DateTime createdAt;
  final String overrideReason;

  factory DisciplineRecord.fromJson(Map<String, dynamic> json) =>
      DisciplineRecord(
        id: '${json['id'] ?? ''}',
        type: '${json['type'] ?? ''}',
        detail: '${json['detail'] ?? ''}',
        createdAt: _date(json['created_at']) ?? DateTime(2026),
        overrideReason: '${json['override_reason'] ?? ''}',
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'type': type,
    'detail': detail,
    'created_at': createdAt.toIso8601String(),
    'override_reason': overrideReason,
  };
}

class AccountStateTransition {
  const AccountStateTransition({
    required this.accountId,
    required this.from,
    required this.to,
    required this.reason,
    required this.createdAt,
  });

  final String accountId;
  final AccountStatus from;
  final AccountStatus to;
  final String reason;
  final DateTime createdAt;

  factory AccountStateTransition.fromJson(Map<String, dynamic> json) =>
      AccountStateTransition(
        accountId: '${json['account_id'] ?? ''}',
        from: _enumValue(
          AccountStatus.values,
          json['from'],
          AccountStatus.available,
        ),
        to: _enumValue(
          AccountStatus.values,
          json['to'],
          AccountStatus.available,
        ),
        reason: '${json['reason'] ?? ''}',
        createdAt: _date(json['created_at']) ?? DateTime(2026),
      );

  Map<String, Object?> toJson() => {
    'account_id': accountId,
    'from': from.name,
    'to': to.name,
    'reason': reason,
    'created_at': createdAt.toIso8601String(),
  };
}

class PropFirmPortfolio {
  const PropFirmPortfolio({
    this.schemaVersion = 4,
    required this.id,
    required this.name,
    this.currency = 'USD',
    required this.virtualRiskBudget,
    required this.acknowledged,
    required this.accounts,
    required this.riskPolicy,
    required this.strategy,
    required this.plannedTrades,
    required this.results,
    required this.disciplineRecords,
    required this.transitions,
    required this.firmPresets,
    this.sessionActive = false,
    this.sessionStatus = PortfolioSessionStatus.draft,
    this.activeSessionId,
    this.stopReason,
    this.portfolioLocked = false,
    this.emotionalStop = false,
    this.currentSituationAssessed = false,
    this.sessionDateKey = '',
    this.activeAccountId,
    required this.createdAt,
    required this.updatedAt,
  });

  final int schemaVersion;
  final String id;
  final String name;
  final String currency;
  final double virtualRiskBudget;
  final bool acknowledged;
  final List<PropFirmAccount> accounts;
  final PortfolioRiskPolicy riskPolicy;
  final RotationStrategy strategy;
  final List<PlannedTrade> plannedTrades;
  final List<RecordedTradeResult> results;
  final List<DisciplineRecord> disciplineRecords;
  final List<AccountStateTransition> transitions;
  final List<PropFirmRulePreset> firmPresets;
  final bool sessionActive;
  final PortfolioSessionStatus sessionStatus;
  final String? activeSessionId;
  final SessionStopReason? stopReason;
  final bool portfolioLocked;
  final bool emotionalStop;
  final bool currentSituationAssessed;
  final String sessionDateKey;
  final String? activeAccountId;
  final DateTime createdAt;
  final DateTime updatedAt;

  List<PropFirmAccount> get activeAccounts =>
      accounts.where((account) => !account.isArchived).toList(growable: false);
  List<PlannedTrade> get openPlans =>
      plannedTrades.where((trade) => trade.isOpen).toList(growable: false);

  PropFirmPortfolio copyWith({
    String? name,
    String? currency,
    double? virtualRiskBudget,
    bool? acknowledged,
    List<PropFirmAccount>? accounts,
    PortfolioRiskPolicy? riskPolicy,
    RotationStrategy? strategy,
    List<PlannedTrade>? plannedTrades,
    List<RecordedTradeResult>? results,
    List<DisciplineRecord>? disciplineRecords,
    List<AccountStateTransition>? transitions,
    List<PropFirmRulePreset>? firmPresets,
    bool? sessionActive,
    PortfolioSessionStatus? sessionStatus,
    String? activeSessionId,
    bool clearActiveSessionId = false,
    SessionStopReason? stopReason,
    bool clearStopReason = false,
    bool? portfolioLocked,
    bool? emotionalStop,
    bool? currentSituationAssessed,
    String? sessionDateKey,
    String? activeAccountId,
    bool clearActiveAccount = false,
    DateTime? updatedAt,
  }) => PropFirmPortfolio(
    schemaVersion: schemaVersion,
    id: id,
    name: name ?? this.name,
    currency: currency ?? this.currency,
    virtualRiskBudget: virtualRiskBudget ?? this.virtualRiskBudget,
    acknowledged: acknowledged ?? this.acknowledged,
    accounts: List.unmodifiable(accounts ?? this.accounts),
    riskPolicy: riskPolicy ?? this.riskPolicy,
    strategy: strategy ?? this.strategy,
    plannedTrades: List.unmodifiable(plannedTrades ?? this.plannedTrades),
    results: List.unmodifiable(results ?? this.results),
    disciplineRecords: List.unmodifiable(
      disciplineRecords ?? this.disciplineRecords,
    ),
    transitions: List.unmodifiable(transitions ?? this.transitions),
    firmPresets: List.unmodifiable(firmPresets ?? this.firmPresets),
    sessionActive: sessionActive ?? this.sessionActive,
    sessionStatus: sessionStatus ?? this.sessionStatus,
    activeSessionId: clearActiveSessionId
        ? null
        : activeSessionId ?? this.activeSessionId,
    stopReason: clearStopReason ? null : stopReason ?? this.stopReason,
    portfolioLocked: portfolioLocked ?? this.portfolioLocked,
    emotionalStop: emotionalStop ?? this.emotionalStop,
    currentSituationAssessed:
        currentSituationAssessed ?? this.currentSituationAssessed,
    sessionDateKey: sessionDateKey ?? this.sessionDateKey,
    activeAccountId: clearActiveAccount
        ? null
        : activeAccountId ?? this.activeAccountId,
    createdAt: createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  factory PropFirmPortfolio.fromJson(Map<String, dynamic> json) {
    final schema = _int(json['schema_version'], 1);
    if (schema < 1 || schema > 4) {
      throw const FormatException('Unsupported portfolio schema version.');
    }
    final updatedAt = _date(json['updated_at']) ?? DateTime(2026);
    final portfolioLocked = _bool(json['portfolio_locked']);
    final emotionalStop = _bool(json['emotional_stop']);
    final rawStopReason = json['stop_reason'];
    final explicitStatus = _enumValue(
      PortfolioSessionStatus.values,
      json['session_status'],
      _bool(json['session_active'])
          ? PortfolioSessionStatus.active
          : portfolioLocked || emotionalStop
          ? PortfolioSessionStatus.stopped
          : PortfolioSessionStatus.draft,
    );
    final stopReason = rawStopReason is Map
        ? SessionStopReason.fromJson(Map<String, dynamic>.from(rawStopReason))
        : explicitStatus == PortfolioSessionStatus.stopped ||
              portfolioLocked ||
              emotionalStop
        ? SessionStopReason(
            code: emotionalStop
                ? SessionStopReasonCode.emotionalRisk
                : SessionStopReasonCode.legacySafetyLock,
            titleKey: emotionalStop
                ? 'session.stop.emotionalRisk.title'
                : 'session.stop.legacy.title',
            descriptionKey: emotionalStop
                ? 'session.stop.emotionalRisk.description'
                : 'session.stop.legacy.description',
            stoppedAt: updatedAt,
            stoppedBy: SessionStoppedBy.accountSafety,
          )
        : null;
    final riskPolicy = PortfolioRiskPolicy.fromJson(
      Map<String, dynamic>.from(json['risk_policy'] as Map? ?? const {}),
    );
    final rawAccounts = ((json['accounts'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
    final accounts = <PropFirmAccount>[
      for (final entry in rawAccounts.indexed)
        PropFirmAccount.fromJson(
          {
            ...entry.$2,
            'id': '${entry.$2['id'] ?? ''}'.trim().isEmpty
                ? _legacyAccountId(entry.$2, entry.$1)
                : entry.$2['id'],
          },
          fallbackDailyTradeLimit: riskPolicy.maximumTradesPerDay,
          fallbackDailyTradeLimitEnabled: riskPolicy.stopAfterMaximumTrades,
        ),
    ];
    return PropFirmPortfolio(
      schemaVersion: 4,
      id: '${json['id'] ?? ''}',
      name: '${json['name'] ?? 'Funded Account Portfolio'}',
      currency: '${json['currency'] ?? 'USD'}',
      virtualRiskBudget: _double(json['virtual_risk_budget']),
      acknowledged: _bool(json['acknowledged']),
      accounts: accounts,
      riskPolicy: riskPolicy,
      strategy: RotationStrategy.fromJson(
        Map<String, dynamic>.from(json['strategy'] as Map? ?? const {}),
      ),
      plannedTrades: ((json['planned_trades'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => PlannedTrade.fromJson(Map.from(item)))
          .toList(growable: false),
      results: ((json['results'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => RecordedTradeResult.fromJson(Map.from(item)))
          .toList(growable: false),
      disciplineRecords: ((json['discipline_records'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => DisciplineRecord.fromJson(Map.from(item)))
          .toList(growable: false),
      transitions: ((json['transitions'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => AccountStateTransition.fromJson(Map.from(item)))
          .toList(growable: false),
      firmPresets: ((json['firm_presets'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => PropFirmRulePreset.fromJson(Map.from(item)))
          .toList(growable: false),
      sessionActive: _bool(json['session_active']),
      sessionStatus:
          stopReason == null && explicitStatus == PortfolioSessionStatus.stopped
          ? PortfolioSessionStatus.warning
          : explicitStatus,
      activeSessionId: json['active_session_id']?.toString(),
      stopReason: stopReason,
      portfolioLocked: portfolioLocked,
      emotionalStop: emotionalStop,
      currentSituationAssessed: _bool(json['current_situation_assessed']),
      sessionDateKey: '${json['session_date_key'] ?? ''}',
      activeAccountId: json['active_account_id']?.toString(),
      createdAt: _date(json['created_at']) ?? DateTime(2026),
      updatedAt: updatedAt,
    );
  }

  Map<String, Object?> toJson() => {
    'schema_version': schemaVersion,
    'id': id,
    'name': name,
    'currency': currency,
    'virtual_risk_budget': virtualRiskBudget,
    'acknowledged': acknowledged,
    'accounts': accounts.map((item) => item.toJson()).toList(),
    'risk_policy': riskPolicy.toJson(),
    'strategy': strategy.toJson(),
    'planned_trades': plannedTrades.map((item) => item.toJson()).toList(),
    'results': results.map((item) => item.toJson()).toList(),
    'discipline_records': disciplineRecords
        .map((item) => item.toJson())
        .toList(),
    'transitions': transitions.map((item) => item.toJson()).toList(),
    'firm_presets': firmPresets.map((item) => item.toJson()).toList(),
    'session_active': sessionActive,
    'session_status': sessionStatus.name,
    'active_session_id': activeSessionId,
    'stop_reason': stopReason?.toJson(),
    'portfolio_locked': portfolioLocked,
    'emotional_stop': emotionalStop,
    'current_situation_assessed': currentSituationAssessed,
    'session_date_key': sessionDateKey,
    'active_account_id': activeAccountId,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
  };
}

String _legacyAccountId(Map<String, dynamic> account, int index) {
  final identity = [
    account['identifier'],
    account['account_number'],
    account['masked_account_number'],
    account['name'],
    account['prop_firm_name'],
    account['created_at'],
    index,
  ].map((value) => '${value ?? ''}').join('|');
  var hash = 0x811c9dc5;
  for (final value in identity.codeUnits) {
    hash ^= value;
    hash = (hash * 0x01000193) & 0xffffffff;
  }
  return 'legacy-account-${hash.toRadixString(16).padLeft(8, '0')}';
}

class RecommendationReason {
  const RecommendationReason(this.messageKey, [this.values = const {}]);

  final String messageKey;
  final Map<String, Object?> values;
}

class RecommendationResult {
  const RecommendationResult({
    this.accountId,
    required this.reasons,
    required this.exclusions,
    required this.shouldStop,
  });

  final String? accountId;
  final List<RecommendationReason> reasons;
  final Map<String, List<RecommendationReason>> exclusions;
  final bool shouldStop;
}

/// A transparent loss-limit calculation for one account. Values include
/// manually entered open risk, so the remaining amounts are suitable for
/// pre-trade decisions rather than merely reporting historical P/L.
class LossLimitSnapshot {
  const LossLimitSnapshot({
    required this.maximumLossAmount,
    required this.maximumLossFloor,
    required this.dailyLossAmount,
    required this.effectiveTodayProfitLoss,
    required this.effectiveOverallProfitLoss,
    required this.dailyLossUsed,
    required this.dailyLossRemaining,
    required this.maximumLossUsed,
    required this.maximumLossRemaining,
    required this.dailyUsagePercent,
    required this.maximumUsagePercent,
    required this.openRiskIncluded,
    required this.personalDailyRiskUsed,
    required this.personalDailyRiskRemaining,
    required this.isEstimated,
  });

  final double maximumLossAmount;
  final double maximumLossFloor;
  final double dailyLossAmount;
  final double effectiveTodayProfitLoss;
  final double effectiveOverallProfitLoss;
  final double dailyLossUsed;
  final double dailyLossRemaining;
  final double maximumLossUsed;
  final double maximumLossRemaining;
  final double dailyUsagePercent;
  final double maximumUsagePercent;
  final double openRiskIncluded;
  final double personalDailyRiskUsed;
  final double personalDailyRiskRemaining;
  final bool isEstimated;

  bool get dailyLimitReached => dailyLossRemaining <= 0;
  bool get maximumLimitReached => maximumLossRemaining <= 0;
  bool get dailyLimitBreached => dailyUsagePercent > 100;
  bool get maximumLimitBreached => maximumUsagePercent > 100;
}

class PayoutEligibilityAssessment {
  const PayoutEligibilityAssessment({
    required this.eligibleProfit,
    required this.requiredProfit,
    required this.profitReached,
    required this.tradingDaysComplete,
    required this.consistencySatisfied,
    required this.requestDateReached,
    required this.manuallyConfirmed,
    required this.eligible,
    required this.waitingForDate,
    required this.tradingDaysRemaining,
    required this.daysUntilRequest,
    required this.protectionThreshold,
    required this.profitAboveThreshold,
    required this.requirements,
  });

  final double eligibleProfit;
  final double requiredProfit;
  final bool profitReached;
  final bool tradingDaysComplete;
  final bool consistencySatisfied;
  final bool requestDateReached;
  final bool manuallyConfirmed;
  final bool eligible;
  final bool waitingForDate;
  final int tradingDaysRemaining;
  final int daysUntilRequest;
  final double protectionThreshold;
  final double profitAboveThreshold;
  final List<RecommendationReason> requirements;
}

class AccountHealthAssessment {
  const AccountHealthAssessment({
    required this.accountId,
    required this.status,
    required this.manualStatus,
    required this.manualStatusDiffers,
    required this.lossLimits,
    required this.payout,
    required this.reasons,
    required this.recommendedAction,
    required this.maximumSuggestedRisk,
    required this.missingData,
    required this.confidence,
    required this.assessedAt,
  });

  final String accountId;
  final AccountHealthStatus status;
  final ManualAccountHealthStatus manualStatus;
  final bool manualStatusDiffers;
  final LossLimitSnapshot lossLimits;
  final PayoutEligibilityAssessment payout;
  final List<RecommendationReason> reasons;
  final RecommendationReason recommendedAction;
  final double maximumSuggestedRisk;
  final List<RecommendationReason> missingData;
  final RecommendationConfidence confidence;
  final DateTime assessedAt;

  bool get isRestricted =>
      {
        AccountHealthStatus.dailyLocked,
        AccountHealthStatus.critical,
        AccountHealthStatus.payoutProtection,
        AccountHealthStatus.paused,
        AccountHealthStatus.breached,
        AccountHealthStatus.archived,
      }.contains(status) ||
      status == AccountHealthStatus.waitingForPayoutDate &&
          payout.tradingDaysRemaining == 0;
}

class PortfolioRecommendation {
  const PortfolioRecommendation({
    required this.type,
    this.recommendedAccountId,
    required this.restrictedAccountIds,
    required this.reasons,
    required this.stopConditions,
    this.maximumSuggestedRisk,
    required this.confidence,
    required this.accountAssessments,
    required this.generatedAt,
  });

  final PortfolioRecommendationType type;
  final String? recommendedAccountId;
  final List<String> restrictedAccountIds;
  final List<RecommendationReason> reasons;
  final List<RecommendationReason> stopConditions;
  final double? maximumSuggestedRisk;
  final RecommendationConfidence confidence;
  final Map<String, AccountHealthAssessment> accountAssessments;
  final DateTime generatedAt;

  bool get shouldStop => {
    PortfolioRecommendationType.stopPortfolio,
    PortfolioRecommendationType.stopSpecificAccount,
    PortfolioRecommendationType.pauseAndReview,
    PortfolioRecommendationType.noEligibleAccount,
  }.contains(type);
}

class PayoutPlanSummary {
  const PayoutPlanSummary({
    required this.requiredGrossProfit,
    required this.eligibleProfit,
    required this.progress,
    required this.remainingProfit,
    required this.estimatedNetPayout,
    required this.tradingDaysRemaining,
    required this.isDateEligible,
    required this.suggestedProtectionBuffer,
  });

  final double requiredGrossProfit;
  final double eligibleProfit;
  final double progress;
  final double remainingProfit;
  final double estimatedNetPayout;
  final int tradingDaysRemaining;
  final bool isDateEligible;
  final double suggestedProtectionBuffer;
}

class RecoveryPlanSummary {
  const RecoveryPlanSummary({
    required this.mode,
    required this.recoveryRequired,
    required this.riskPerTrade,
    required this.requiredNetR,
    required this.fullWinsAtTwoR,
    required this.remainingBuffer,
  });

  final RecoveryMode mode;
  final double recoveryRequired;
  final double riskPerTrade;
  final double requiredNetR;
  final int fullWinsAtTwoR;
  final double remainingBuffer;
}

class SimulationConfiguration {
  const SimulationConfiguration({
    this.accountCount = 3,
    this.startingBalance = 10000,
    this.riskPerTrade = 100,
    this.winRate = .45,
    this.averageWinningR = 2,
    this.averageLosingR = 1,
    this.maximumTradesPerDay = 3,
    this.maximumPortfolioLossesPerDay = 2,
    this.payoutThreshold = 500,
    this.tradeCount = 100,
    this.transactionCost = 0,
    this.trials = 500,
    this.seed = 41,
  });

  final int accountCount;
  final double startingBalance;
  final double riskPerTrade;
  final double winRate;
  final double averageWinningR;
  final double averageLosingR;
  final int maximumTradesPerDay;
  final int maximumPortfolioLossesPerDay;
  final double payoutThreshold;
  final int tradeCount;
  final double transactionCost;
  final int trials;
  final int seed;
}

class SimulationResult {
  const SimulationResult({
    required this.payoutFrequency,
    required this.breachFrequency,
    required this.medianDrawdown,
    required this.worstDrawdown,
    required this.averageTradesToPayout,
    required this.samplePath,
    required this.reducedRiskMedianDrawdown,
  });

  final double payoutFrequency;
  final double breachFrequency;
  final double medianDrawdown;
  final double worstDrawdown;
  final double? averageTradesToPayout;
  final List<double> samplePath;
  final double reducedRiskMedianDrawdown;
}
