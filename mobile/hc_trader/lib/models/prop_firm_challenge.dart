import 'package:cloud_firestore/cloud_firestore.dart';

import 'mt5_sync_models.dart' as mt5;

class PropFirmChallenge {
  const PropFirmChallenge({
    required this.id,
    required this.userId,
    required this.mt5AccountKey,
    required this.mt5Login,
    required this.brokerServer,
    required this.propFirmName,
    required this.challengeName,
    this.modelType = 'two_step',
    this.currentPhase = 'phase_1',
    this.startingBalance = 10000,
    this.accountCurrency = 'USD',
    this.phase1ProfitTargetPercent = 8,
    this.phase2ProfitTargetPercent = 5,
    this.dailyLossLimitPercent = 5,
    this.maximumLossLimitPercent = 10,
    this.dailyLossCalculationType = 'balance_based',
    this.maximumLossCalculationType = 'static_initial_balance',
    this.minimumTradingDaysPhase1 = 3,
    this.minimumTradingDaysPhase2 = 3,
    this.maxTradingDaysPhase1,
    this.maxTradingDaysPhase2,
    this.allowWeekendHolding = true,
    this.allowNewsTrading = true,
    this.timezoneForDailyReset = 'Asia/Singapore',
    this.challengeStartDate,
    this.phase1StartedAt,
    this.phase1CompletedAt,
    this.phase2StartedAt,
    this.phase2CompletedAt,
    this.fundedStartedAt,
    this.status = 'active',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final String mt5AccountKey;
  final int mt5Login;
  final String brokerServer;
  final String propFirmName;
  final String challengeName;
  final String modelType;
  final String currentPhase;
  final double startingBalance;
  final String accountCurrency;
  final double phase1ProfitTargetPercent;
  final double phase2ProfitTargetPercent;
  final double dailyLossLimitPercent;
  final double maximumLossLimitPercent;
  final String dailyLossCalculationType;
  final String maximumLossCalculationType;
  final int minimumTradingDaysPhase1;
  final int minimumTradingDaysPhase2;
  final int? maxTradingDaysPhase1;
  final int? maxTradingDaysPhase2;
  final bool allowWeekendHolding;
  final bool allowNewsTrading;
  final String timezoneForDailyReset;
  final DateTime? challengeStartDate;
  final DateTime? phase1StartedAt;
  final DateTime? phase1CompletedAt;
  final DateTime? phase2StartedAt;
  final DateTime? phase2CompletedAt;
  final DateTime? fundedStartedAt;
  final String status;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  double get activeProfitTargetPercent => currentPhase == 'phase_2'
      ? phase2ProfitTargetPercent
      : phase1ProfitTargetPercent;

  int get activeMinimumTradingDays => currentPhase == 'phase_2'
      ? minimumTradingDaysPhase2
      : minimumTradingDaysPhase1;

  bool get isTerminal =>
      currentPhase == 'funded' ||
      currentPhase == 'failed' ||
      currentPhase == 'passed' ||
      status == 'failed' ||
      status == 'passed';

  String get displayName {
    final firm = propFirmName.trim();
    final challenge = challengeName.trim();
    if (firm.isEmpty) return challenge.isEmpty ? '2-Step Challenge' : challenge;
    if (challenge.isEmpty) return firm;
    return '$firm - $challenge';
  }

  PropFirmChallenge copyWith({
    String? id,
    String? userId,
    String? mt5AccountKey,
    int? mt5Login,
    String? brokerServer,
    String? propFirmName,
    String? challengeName,
    String? currentPhase,
    double? startingBalance,
    String? accountCurrency,
    double? phase1ProfitTargetPercent,
    double? phase2ProfitTargetPercent,
    double? dailyLossLimitPercent,
    double? maximumLossLimitPercent,
    String? dailyLossCalculationType,
    String? maximumLossCalculationType,
    int? minimumTradingDaysPhase1,
    int? minimumTradingDaysPhase2,
    int? maxTradingDaysPhase1,
    int? maxTradingDaysPhase2,
    bool? allowWeekendHolding,
    bool? allowNewsTrading,
    String? timezoneForDailyReset,
    DateTime? challengeStartDate,
    DateTime? phase1StartedAt,
    DateTime? phase1CompletedAt,
    DateTime? phase2StartedAt,
    DateTime? phase2CompletedAt,
    DateTime? fundedStartedAt,
    String? status,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return PropFirmChallenge(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      mt5AccountKey: mt5AccountKey ?? this.mt5AccountKey,
      mt5Login: mt5Login ?? this.mt5Login,
      brokerServer: brokerServer ?? this.brokerServer,
      propFirmName: propFirmName ?? this.propFirmName,
      challengeName: challengeName ?? this.challengeName,
      modelType: modelType,
      currentPhase: currentPhase ?? this.currentPhase,
      startingBalance: startingBalance ?? this.startingBalance,
      accountCurrency: accountCurrency ?? this.accountCurrency,
      phase1ProfitTargetPercent:
          phase1ProfitTargetPercent ?? this.phase1ProfitTargetPercent,
      phase2ProfitTargetPercent:
          phase2ProfitTargetPercent ?? this.phase2ProfitTargetPercent,
      dailyLossLimitPercent:
          dailyLossLimitPercent ?? this.dailyLossLimitPercent,
      maximumLossLimitPercent:
          maximumLossLimitPercent ?? this.maximumLossLimitPercent,
      dailyLossCalculationType:
          dailyLossCalculationType ?? this.dailyLossCalculationType,
      maximumLossCalculationType:
          maximumLossCalculationType ?? this.maximumLossCalculationType,
      minimumTradingDaysPhase1:
          minimumTradingDaysPhase1 ?? this.minimumTradingDaysPhase1,
      minimumTradingDaysPhase2:
          minimumTradingDaysPhase2 ?? this.minimumTradingDaysPhase2,
      maxTradingDaysPhase1: maxTradingDaysPhase1 ?? this.maxTradingDaysPhase1,
      maxTradingDaysPhase2: maxTradingDaysPhase2 ?? this.maxTradingDaysPhase2,
      allowWeekendHolding: allowWeekendHolding ?? this.allowWeekendHolding,
      allowNewsTrading: allowNewsTrading ?? this.allowNewsTrading,
      timezoneForDailyReset:
          timezoneForDailyReset ?? this.timezoneForDailyReset,
      challengeStartDate: challengeStartDate ?? this.challengeStartDate,
      phase1StartedAt: phase1StartedAt ?? this.phase1StartedAt,
      phase1CompletedAt: phase1CompletedAt ?? this.phase1CompletedAt,
      phase2StartedAt: phase2StartedAt ?? this.phase2StartedAt,
      phase2CompletedAt: phase2CompletedAt ?? this.phase2CompletedAt,
      fundedStartedAt: fundedStartedAt ?? this.fundedStartedAt,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  factory PropFirmChallenge.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    return PropFirmChallenge.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory PropFirmChallenge.fromJson(Map<String, dynamic> json) {
    final login = _asInt(json['mt5_login'] ?? json['account_number'], 0);
    final server = (json['broker_server'] ?? '').toString();
    return PropFirmChallenge(
      id: (json['id'] ?? json['challenge_id'] ?? '').toString(),
      userId: (json['user_id'] ?? '').toString(),
      mt5AccountKey:
          (json['mt5_account_key'] ?? mt5.mt5AccountKey(server, login))
              .toString(),
      mt5Login: login,
      brokerServer: server,
      propFirmName: (json['prop_firm_name'] ?? '').toString(),
      challengeName: (json['challenge_name'] ?? '').toString(),
      modelType: (json['model_type'] ?? 'two_step').toString(),
      currentPhase: (json['current_phase'] ?? 'phase_1').toString(),
      startingBalance: _asDouble(json['starting_balance'], 10000),
      accountCurrency: (json['account_currency'] ?? 'USD').toString(),
      phase1ProfitTargetPercent: _asDouble(
        json['phase_1_profit_target_percent'],
        8,
      ),
      phase2ProfitTargetPercent: _asDouble(
        json['phase_2_profit_target_percent'],
        5,
      ),
      dailyLossLimitPercent: _asDouble(json['daily_loss_limit_percent'], 5),
      maximumLossLimitPercent: _asDouble(
        json['maximum_loss_limit_percent'],
        10,
      ),
      dailyLossCalculationType:
          (json['daily_loss_calculation_type'] ?? 'balance_based').toString(),
      maximumLossCalculationType:
          (json['maximum_loss_calculation_type'] ?? 'static_initial_balance')
              .toString(),
      minimumTradingDaysPhase1: _asInt(json['minimum_trading_days_phase_1'], 3),
      minimumTradingDaysPhase2: _asInt(json['minimum_trading_days_phase_2'], 3),
      maxTradingDaysPhase1: _asNullableInt(json['max_trading_days_phase_1']),
      maxTradingDaysPhase2: _asNullableInt(json['max_trading_days_phase_2']),
      allowWeekendHolding: _asBool(json['allow_weekend_holding'], true),
      allowNewsTrading: _asBool(json['allow_news_trading'], true),
      timezoneForDailyReset:
          (json['timezone_for_daily_reset'] ?? 'Asia/Singapore').toString(),
      challengeStartDate: _asDate(json['challenge_start_date']),
      phase1StartedAt: _asDate(json['phase_1_started_at']),
      phase1CompletedAt: _asDate(json['phase_1_completed_at']),
      phase2StartedAt: _asDate(json['phase_2_started_at']),
      phase2CompletedAt: _asDate(json['phase_2_completed_at']),
      fundedStartedAt: _asDate(json['funded_started_at']),
      status: (json['status'] ?? 'active').toString(),
      createdAt: _asDate(json['created_at']),
      updatedAt: _asDate(json['updated_at']),
    );
  }

  Map<String, Object?> toFirestore({bool includeCreatedAt = false}) => {
    'challenge_id': id,
    'user_id': userId,
    'mt5_account_key': mt5AccountKey,
    'mt5_login': mt5Login,
    'account_number': mt5Login,
    'broker_server': brokerServer,
    'prop_firm_name': propFirmName,
    'challenge_name': challengeName,
    'model_type': modelType,
    'current_phase': currentPhase,
    'starting_balance': startingBalance,
    'account_currency': accountCurrency,
    'phase_1_profit_target_percent': phase1ProfitTargetPercent,
    'phase_2_profit_target_percent': phase2ProfitTargetPercent,
    'daily_loss_limit_percent': dailyLossLimitPercent,
    'maximum_loss_limit_percent': maximumLossLimitPercent,
    'daily_loss_calculation_type': dailyLossCalculationType,
    'maximum_loss_calculation_type': maximumLossCalculationType,
    'minimum_trading_days_phase_1': minimumTradingDaysPhase1,
    'minimum_trading_days_phase_2': minimumTradingDaysPhase2,
    'max_trading_days_phase_1': maxTradingDaysPhase1,
    'max_trading_days_phase_2': maxTradingDaysPhase2,
    'allow_weekend_holding': allowWeekendHolding,
    'allow_news_trading': allowNewsTrading,
    'timezone_for_daily_reset': timezoneForDailyReset,
    if (challengeStartDate != null)
      'challenge_start_date': Timestamp.fromDate(challengeStartDate!),
    if (phase1StartedAt != null)
      'phase_1_started_at': Timestamp.fromDate(phase1StartedAt!),
    if (phase1CompletedAt != null)
      'phase_1_completed_at': Timestamp.fromDate(phase1CompletedAt!),
    if (phase2StartedAt != null)
      'phase_2_started_at': Timestamp.fromDate(phase2StartedAt!),
    if (phase2CompletedAt != null)
      'phase_2_completed_at': Timestamp.fromDate(phase2CompletedAt!),
    if (fundedStartedAt != null)
      'funded_started_at': Timestamp.fromDate(fundedStartedAt!),
    'status': status,
    if (includeCreatedAt) 'created_at': FieldValue.serverTimestamp(),
    'updated_at': FieldValue.serverTimestamp(),
  };

  Map<String, Object?> toJson() => {
    'id': id,
    'challenge_id': id,
    'user_id': userId,
    'mt5_account_key': mt5AccountKey,
    'mt5_login': mt5Login,
    'account_number': mt5Login,
    'broker_server': brokerServer,
    'prop_firm_name': propFirmName,
    'challenge_name': challengeName,
    'model_type': modelType,
    'current_phase': currentPhase,
    'starting_balance': startingBalance,
    'account_currency': accountCurrency,
    'phase_1_profit_target_percent': phase1ProfitTargetPercent,
    'phase_2_profit_target_percent': phase2ProfitTargetPercent,
    'daily_loss_limit_percent': dailyLossLimitPercent,
    'maximum_loss_limit_percent': maximumLossLimitPercent,
    'daily_loss_calculation_type': dailyLossCalculationType,
    'maximum_loss_calculation_type': maximumLossCalculationType,
    'minimum_trading_days_phase_1': minimumTradingDaysPhase1,
    'minimum_trading_days_phase_2': minimumTradingDaysPhase2,
    'max_trading_days_phase_1': maxTradingDaysPhase1,
    'max_trading_days_phase_2': maxTradingDaysPhase2,
    'allow_weekend_holding': allowWeekendHolding,
    'allow_news_trading': allowNewsTrading,
    'timezone_for_daily_reset': timezoneForDailyReset,
    'challenge_start_date': challengeStartDate?.toIso8601String(),
    'phase_1_started_at': phase1StartedAt?.toIso8601String(),
    'phase_1_completed_at': phase1CompletedAt?.toIso8601String(),
    'phase_2_started_at': phase2StartedAt?.toIso8601String(),
    'phase_2_completed_at': phase2CompletedAt?.toIso8601String(),
    'funded_started_at': fundedStartedAt?.toIso8601String(),
    'status': status,
    'created_at': createdAt?.toIso8601String(),
    'updated_at': updatedAt?.toIso8601String(),
  };
}

class PropFirmMetrics {
  const PropFirmMetrics({
    required this.challengeId,
    required this.userId,
    required this.mt5AccountKey,
    this.currentPhase = 'phase_1',
    this.riskStatus = 'healthy',
    this.startingBalance = 0,
    this.currentBalance = 0,
    this.currentEquity = 0,
    this.accountProfit = 0,
    this.accountProfitPercent = 0,
    this.floatingProfitLoss = 0,
    this.closedProfitLoss = 0,
    this.totalCommission = 0,
    this.totalFee = 0,
    this.totalSwap = 0,
    this.phaseProfitTargetAmount = 0,
    this.currentProfitAmount = 0,
    this.currentProfitPercent = 0,
    this.remainingProfitRequired = 0,
    this.profitTargetProgressPercent = 0,
    this.targetReached = false,
    this.dailyStartBalance = 0,
    this.dailyStartEquity = 0,
    this.todayClosedProfitLoss = 0,
    this.todayFloatingProfitLoss = 0,
    this.todayTotalProfitLoss = 0,
    this.dailyLossLimitAmount = 0,
    this.dailyLossUsedAmount = 0,
    this.dailyLossRemainingAmount = 0,
    this.dailyLossUsedPercent = 0,
    this.dailyLossBreach = false,
    this.dailyLossWarning = false,
    this.maximumLossLimitAmount = 0,
    this.minimumAllowedBalanceEquity = 0,
    this.currentDrawdownAmount = 0,
    this.maximumLossRemainingAmount = 0,
    this.maximumLossUsedPercent = 0,
    this.maximumLossBreach = false,
    this.maximumLossWarning = false,
    this.tradingDaysCount = 0,
    this.phaseMinimumTradingDaysRequired = 0,
    this.remainingTradingDays = 0,
    this.minimumDaysSatisfied = false,
    this.phase1InProgress = false,
    this.phase1Passed = false,
    this.phase2InProgress = false,
    this.phase2Passed = false,
    this.funded = false,
    this.failed = false,
    this.warning = false,
    this.updatedAt,
  });

  final String challengeId;
  final String userId;
  final String mt5AccountKey;
  final String currentPhase;
  final String riskStatus;
  final double startingBalance;
  final double currentBalance;
  final double currentEquity;
  final double accountProfit;
  final double accountProfitPercent;
  final double floatingProfitLoss;
  final double closedProfitLoss;
  final double totalCommission;
  final double totalFee;
  final double totalSwap;
  final double phaseProfitTargetAmount;
  final double currentProfitAmount;
  final double currentProfitPercent;
  final double remainingProfitRequired;
  final double profitTargetProgressPercent;
  final bool targetReached;
  final double dailyStartBalance;
  final double dailyStartEquity;
  final double todayClosedProfitLoss;
  final double todayFloatingProfitLoss;
  final double todayTotalProfitLoss;
  final double dailyLossLimitAmount;
  final double dailyLossUsedAmount;
  final double dailyLossRemainingAmount;
  final double dailyLossUsedPercent;
  final bool dailyLossBreach;
  final bool dailyLossWarning;
  final double maximumLossLimitAmount;
  final double minimumAllowedBalanceEquity;
  final double currentDrawdownAmount;
  final double maximumLossRemainingAmount;
  final double maximumLossUsedPercent;
  final bool maximumLossBreach;
  final bool maximumLossWarning;
  final int tradingDaysCount;
  final int phaseMinimumTradingDaysRequired;
  final int remainingTradingDays;
  final bool minimumDaysSatisfied;
  final bool phase1InProgress;
  final bool phase1Passed;
  final bool phase2InProgress;
  final bool phase2Passed;
  final bool funded;
  final bool failed;
  final bool warning;
  final DateTime? updatedAt;

  factory PropFirmMetrics.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    return PropFirmMetrics.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory PropFirmMetrics.fromJson(Map<String, dynamic> json) {
    return PropFirmMetrics(
      challengeId: (json['challenge_id'] ?? '').toString(),
      userId: (json['user_id'] ?? '').toString(),
      mt5AccountKey: (json['mt5_account_key'] ?? '').toString(),
      currentPhase: (json['current_phase'] ?? 'phase_1').toString(),
      riskStatus: (json['risk_status'] ?? 'healthy').toString(),
      startingBalance: _asDouble(json['starting_balance'], 0),
      currentBalance: _asDouble(json['current_balance'], 0),
      currentEquity: _asDouble(json['current_equity'], 0),
      accountProfit: _asDouble(json['account_profit'], 0),
      accountProfitPercent: _asDouble(json['account_profit_percent'], 0),
      floatingProfitLoss: _asDouble(json['floating_profit_loss'], 0),
      closedProfitLoss: _asDouble(json['closed_profit_loss'], 0),
      totalCommission: _asDouble(json['total_commission'], 0),
      totalFee: _asDouble(json['total_fee'], 0),
      totalSwap: _asDouble(json['total_swap'], 0),
      phaseProfitTargetAmount: _asDouble(json['phase_profit_target_amount'], 0),
      currentProfitAmount: _asDouble(json['current_profit_amount'], 0),
      currentProfitPercent: _asDouble(json['current_profit_percent'], 0),
      remainingProfitRequired: _asDouble(json['remaining_profit_required'], 0),
      profitTargetProgressPercent: _asDouble(
        json['profit_target_progress_percent'],
        0,
      ),
      targetReached: _asBool(json['target_reached'], false),
      dailyStartBalance: _asDouble(json['daily_start_balance'], 0),
      dailyStartEquity: _asDouble(json['daily_start_equity'], 0),
      todayClosedProfitLoss: _asDouble(json['today_closed_profit_loss'], 0),
      todayFloatingProfitLoss: _asDouble(json['today_floating_profit_loss'], 0),
      todayTotalProfitLoss: _asDouble(json['today_total_profit_loss'], 0),
      dailyLossLimitAmount: _asDouble(json['daily_loss_limit_amount'], 0),
      dailyLossUsedAmount: _asDouble(json['daily_loss_used_amount'], 0),
      dailyLossRemainingAmount: _asDouble(
        json['daily_loss_remaining_amount'],
        0,
      ),
      dailyLossUsedPercent: _asDouble(json['daily_loss_used_percent'], 0),
      dailyLossBreach: _asBool(json['daily_loss_breach'], false),
      dailyLossWarning: _asBool(json['daily_loss_warning'], false),
      maximumLossLimitAmount: _asDouble(json['maximum_loss_limit_amount'], 0),
      minimumAllowedBalanceEquity: _asDouble(
        json['minimum_allowed_balance_equity'],
        0,
      ),
      currentDrawdownAmount: _asDouble(json['current_drawdown_amount'], 0),
      maximumLossRemainingAmount: _asDouble(
        json['maximum_loss_remaining_amount'],
        0,
      ),
      maximumLossUsedPercent: _asDouble(json['maximum_loss_used_percent'], 0),
      maximumLossBreach: _asBool(json['maximum_loss_breach'], false),
      maximumLossWarning: _asBool(json['maximum_loss_warning'], false),
      tradingDaysCount: _asInt(json['trading_days_count'], 0),
      phaseMinimumTradingDaysRequired: _asInt(
        json['phase_minimum_trading_days_required'],
        0,
      ),
      remainingTradingDays: _asInt(json['remaining_trading_days'], 0),
      minimumDaysSatisfied: _asBool(json['minimum_days_satisfied'], false),
      phase1InProgress: _asBool(json['phase_1_in_progress'], false),
      phase1Passed: _asBool(json['phase_1_passed'], false),
      phase2InProgress: _asBool(json['phase_2_in_progress'], false),
      phase2Passed: _asBool(json['phase_2_passed'], false),
      funded: _asBool(json['funded'], false),
      failed: _asBool(json['failed'], false),
      warning: _asBool(json['warning'], false),
      updatedAt: _asDate(json['updated_at']),
    );
  }

  Map<String, Object?> toJson() => {
    'challenge_id': challengeId,
    'user_id': userId,
    'mt5_account_key': mt5AccountKey,
    'current_phase': currentPhase,
    'risk_status': riskStatus,
    'starting_balance': startingBalance,
    'current_balance': currentBalance,
    'current_equity': currentEquity,
    'account_profit': accountProfit,
    'account_profit_percent': accountProfitPercent,
    'floating_profit_loss': floatingProfitLoss,
    'closed_profit_loss': closedProfitLoss,
    'total_commission': totalCommission,
    'total_fee': totalFee,
    'total_swap': totalSwap,
    'phase_profit_target_amount': phaseProfitTargetAmount,
    'current_profit_amount': currentProfitAmount,
    'current_profit_percent': currentProfitPercent,
    'remaining_profit_required': remainingProfitRequired,
    'profit_target_progress_percent': profitTargetProgressPercent,
    'target_reached': targetReached,
    'daily_start_balance': dailyStartBalance,
    'daily_start_equity': dailyStartEquity,
    'today_closed_profit_loss': todayClosedProfitLoss,
    'today_floating_profit_loss': todayFloatingProfitLoss,
    'today_total_profit_loss': todayTotalProfitLoss,
    'daily_loss_limit_amount': dailyLossLimitAmount,
    'daily_loss_used_amount': dailyLossUsedAmount,
    'daily_loss_remaining_amount': dailyLossRemainingAmount,
    'daily_loss_used_percent': dailyLossUsedPercent,
    'daily_loss_breach': dailyLossBreach,
    'daily_loss_warning': dailyLossWarning,
    'maximum_loss_limit_amount': maximumLossLimitAmount,
    'minimum_allowed_balance_equity': minimumAllowedBalanceEquity,
    'current_drawdown_amount': currentDrawdownAmount,
    'maximum_loss_remaining_amount': maximumLossRemainingAmount,
    'maximum_loss_used_percent': maximumLossUsedPercent,
    'maximum_loss_breach': maximumLossBreach,
    'maximum_loss_warning': maximumLossWarning,
    'trading_days_count': tradingDaysCount,
    'phase_minimum_trading_days_required': phaseMinimumTradingDaysRequired,
    'remaining_trading_days': remainingTradingDays,
    'minimum_days_satisfied': minimumDaysSatisfied,
    'phase_1_in_progress': phase1InProgress,
    'phase_1_passed': phase1Passed,
    'phase_2_in_progress': phase2InProgress,
    'phase_2_passed': phase2Passed,
    'funded': funded,
    'failed': failed,
    'warning': warning,
    'updated_at': updatedAt?.toIso8601String(),
  };
}

DateTime? _asDate(Object? value) {
  if (value == null) return null;
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return DateTime.tryParse(value.toString());
}

double _asDouble(Object? value, double fallback) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

int _asInt(Object? value, int fallback) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

int? _asNullableInt(Object? value) {
  if (value == null || value.toString().isEmpty) return null;
  return _asInt(value, 0);
}

bool _asBool(Object? value, bool fallback) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  final text = value?.toString().trim().toLowerCase() ?? '';
  if (text == 'true' || text == '1' || text == 'yes') return true;
  if (text == 'false' || text == '0' || text == 'no') return false;
  return fallback;
}
