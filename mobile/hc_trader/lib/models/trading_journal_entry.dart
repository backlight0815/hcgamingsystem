import 'package:cloud_firestore/cloud_firestore.dart';

import 'journal_attachment.dart';
import 'trading_data_source.dart';

class TradingJournalEntry {
  const TradingJournalEntry({
    required this.id,
    required this.userId,
    required this.openDate,
    required this.closeDate,
    required this.pair,
    required this.direction,
    required this.entryPrice,
    required this.exitPrice,
    required this.lotSize,
    required this.pips,
    required this.profitLoss,
    required this.result,
    this.grossProfit,
    this.commission = 0,
    this.swap = 0,
    this.fees = 0,
    this.stopLoss,
    this.takeProfit,
    this.initialRiskAmount,
    this.initialRiskPercent,
    this.setupName,
    this.session,
    this.sourceTicket,
    this.sourceOrderId,
    this.sourcePositionId,
    this.currency = '',
    this.type = 'trade',
    this.source = TradingRecordSource.manual,
    this.tradingAccountId,
    this.status = 'closed',
    this.closeHandling = 'manual_close',
    this.timeInputTimezone = 'malaysia',
    this.timeInputOffsetMinutes = 480,
    this.notes = '',
    this.emotionBeforeEntry = '',
    this.emotionDuringTrade = '',
    this.emotionAfterTrade = '',
    this.confidenceLevel,
    this.disciplineRating,
    this.mistakeTags = const [],
    this.psychologyNotes = '',
    this.followedTradingPlan = false,
    this.tradingPlanId,
    this.tradingPlanName,
    this.tradingPlanCheckedAt,
    this.strategyId,
    this.strategyName,
    this.planComplianceChecklist = const {},
    this.strategyComplianceChecklist = const {},
    this.followedRiskRules = false,
    this.avoidedFomo = false,
    this.followedSessionRules = false,
    this.avoidedRestrictedNews = false,
    this.stoppedAfterMaxLoss = false,
    this.followedStrategyRules = false,
    this.followedSlRule = false,
    this.followedTpRule = false,
    this.followedInvalidationRule = false,
    this.attachments = const [],
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final String type;
  final TradingRecordSource source;
  final String? tradingAccountId;
  final String status;
  final String closeHandling;
  final String timeInputTimezone;
  final int timeInputOffsetMinutes;
  final DateTime openDate;
  final DateTime closeDate;
  final String pair;
  final int direction;
  final double entryPrice;
  final double exitPrice;
  final double lotSize;
  final double pips;
  final double profitLoss;
  final int result;
  final double? grossProfit;
  final double commission;
  final double swap;
  final double fees;
  final double? stopLoss;
  final double? takeProfit;
  final double? initialRiskAmount;
  final double? initialRiskPercent;
  final String? setupName;
  final String? session;
  final String? sourceTicket;
  final String? sourceOrderId;
  final String? sourcePositionId;
  final String currency;
  final String notes;
  final String emotionBeforeEntry;
  final String emotionDuringTrade;
  final String emotionAfterTrade;
  final int? confidenceLevel;
  final int? disciplineRating;
  final List<String> mistakeTags;
  final String psychologyNotes;
  final bool followedTradingPlan;
  final String? tradingPlanId;
  final String? tradingPlanName;
  final DateTime? tradingPlanCheckedAt;
  final String? strategyId;
  final String? strategyName;
  final Map<String, bool> planComplianceChecklist;
  final Map<String, bool> strategyComplianceChecklist;
  final bool followedRiskRules;
  final bool avoidedFomo;
  final bool followedSessionRules;
  final bool avoidedRestrictedNews;
  final bool stoppedAfterMaxLoss;
  final bool followedStrategyRules;
  final bool followedSlRule;
  final bool followedTpRule;
  final bool followedInvalidationRule;
  final List<JournalAttachment> attachments;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  bool get isWin => profitLoss > 0;
  bool get isLoss => profitLoss < 0;
  bool get isBreakEven => profitLoss == 0;

  String get directionLabel {
    return direction == 1 ? 'Buy' : 'Sell';
  }

  String get resultLabel {
    return switch (result) {
      1 => 'Win',
      2 => 'Loss',
      3 => 'Break Even',
      _ => 'Open',
    };
  }

  TradingJournalEntry copyWith({
    String? id,
    String? userId,
    String? type,
    TradingRecordSource? source,
    String? tradingAccountId,
    String? status,
    String? closeHandling,
    String? timeInputTimezone,
    int? timeInputOffsetMinutes,
    DateTime? openDate,
    DateTime? closeDate,
    String? pair,
    int? direction,
    double? entryPrice,
    double? exitPrice,
    double? lotSize,
    double? pips,
    double? profitLoss,
    int? result,
    double? grossProfit,
    bool clearGrossProfit = false,
    double? commission,
    double? swap,
    double? fees,
    double? stopLoss,
    bool clearStopLoss = false,
    double? takeProfit,
    bool clearTakeProfit = false,
    double? initialRiskAmount,
    bool clearInitialRiskAmount = false,
    double? initialRiskPercent,
    bool clearInitialRiskPercent = false,
    String? setupName,
    String? session,
    String? sourceTicket,
    String? sourceOrderId,
    String? sourcePositionId,
    String? currency,
    String? notes,
    String? emotionBeforeEntry,
    String? emotionDuringTrade,
    String? emotionAfterTrade,
    int? confidenceLevel,
    int? disciplineRating,
    List<String>? mistakeTags,
    String? psychologyNotes,
    bool? followedTradingPlan,
    String? tradingPlanId,
    String? tradingPlanName,
    DateTime? tradingPlanCheckedAt,
    String? strategyId,
    String? strategyName,
    Map<String, bool>? planComplianceChecklist,
    Map<String, bool>? strategyComplianceChecklist,
    bool? followedRiskRules,
    bool? avoidedFomo,
    bool? followedSessionRules,
    bool? avoidedRestrictedNews,
    bool? stoppedAfterMaxLoss,
    bool? followedStrategyRules,
    bool? followedSlRule,
    bool? followedTpRule,
    bool? followedInvalidationRule,
    List<JournalAttachment>? attachments,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return TradingJournalEntry(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      type: type ?? this.type,
      source: source ?? this.source,
      tradingAccountId: tradingAccountId ?? this.tradingAccountId,
      status: status ?? this.status,
      closeHandling: closeHandling ?? this.closeHandling,
      timeInputTimezone: timeInputTimezone ?? this.timeInputTimezone,
      timeInputOffsetMinutes:
          timeInputOffsetMinutes ?? this.timeInputOffsetMinutes,
      openDate: openDate ?? this.openDate,
      closeDate: closeDate ?? this.closeDate,
      pair: pair ?? this.pair,
      direction: direction ?? this.direction,
      entryPrice: entryPrice ?? this.entryPrice,
      exitPrice: exitPrice ?? this.exitPrice,
      lotSize: lotSize ?? this.lotSize,
      pips: pips ?? this.pips,
      profitLoss: profitLoss ?? this.profitLoss,
      result: result ?? this.result,
      grossProfit: clearGrossProfit ? null : grossProfit ?? this.grossProfit,
      commission: commission ?? this.commission,
      swap: swap ?? this.swap,
      fees: fees ?? this.fees,
      stopLoss: clearStopLoss ? null : stopLoss ?? this.stopLoss,
      takeProfit: clearTakeProfit ? null : takeProfit ?? this.takeProfit,
      initialRiskAmount: clearInitialRiskAmount
          ? null
          : initialRiskAmount ?? this.initialRiskAmount,
      initialRiskPercent: clearInitialRiskPercent
          ? null
          : initialRiskPercent ?? this.initialRiskPercent,
      setupName: setupName ?? this.setupName,
      session: session ?? this.session,
      sourceTicket: sourceTicket ?? this.sourceTicket,
      sourceOrderId: sourceOrderId ?? this.sourceOrderId,
      sourcePositionId: sourcePositionId ?? this.sourcePositionId,
      currency: currency ?? this.currency,
      notes: notes ?? this.notes,
      emotionBeforeEntry: emotionBeforeEntry ?? this.emotionBeforeEntry,
      emotionDuringTrade: emotionDuringTrade ?? this.emotionDuringTrade,
      emotionAfterTrade: emotionAfterTrade ?? this.emotionAfterTrade,
      confidenceLevel: confidenceLevel ?? this.confidenceLevel,
      disciplineRating: disciplineRating ?? this.disciplineRating,
      mistakeTags: mistakeTags ?? this.mistakeTags,
      psychologyNotes: psychologyNotes ?? this.psychologyNotes,
      followedTradingPlan: followedTradingPlan ?? this.followedTradingPlan,
      tradingPlanId: tradingPlanId ?? this.tradingPlanId,
      tradingPlanName: tradingPlanName ?? this.tradingPlanName,
      tradingPlanCheckedAt: tradingPlanCheckedAt ?? this.tradingPlanCheckedAt,
      strategyId: strategyId ?? this.strategyId,
      strategyName: strategyName ?? this.strategyName,
      planComplianceChecklist:
          planComplianceChecklist ?? this.planComplianceChecklist,
      strategyComplianceChecklist:
          strategyComplianceChecklist ?? this.strategyComplianceChecklist,
      followedRiskRules: followedRiskRules ?? this.followedRiskRules,
      avoidedFomo: avoidedFomo ?? this.avoidedFomo,
      followedSessionRules: followedSessionRules ?? this.followedSessionRules,
      avoidedRestrictedNews:
          avoidedRestrictedNews ?? this.avoidedRestrictedNews,
      stoppedAfterMaxLoss: stoppedAfterMaxLoss ?? this.stoppedAfterMaxLoss,
      followedStrategyRules:
          followedStrategyRules ?? this.followedStrategyRules,
      followedSlRule: followedSlRule ?? this.followedSlRule,
      followedTpRule: followedTpRule ?? this.followedTpRule,
      followedInvalidationRule:
          followedInvalidationRule ?? this.followedInvalidationRule,
      attachments: attachments ?? this.attachments,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  factory TradingJournalEntry.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};

    return TradingJournalEntry(
      id: doc.id,
      userId: (data['user_id'] ?? '').toString(),
      type: (data['type'] ?? 'trade').toString(),
      source: TradingRecordSource.fromStorage(
        data['source'] ?? data['record_source'] ?? data['recordSource'],
      ),
      tradingAccountId: data['trading_account_id']?.toString(),
      status: (data['status'] ?? 'closed').toString(),
      closeHandling: (data['close_handling'] ?? 'manual_close').toString(),
      timeInputTimezone: (data['time_input_timezone'] ?? 'malaysia').toString(),
      timeInputOffsetMinutes: _asInt(
        data['time_input_offset_minutes'],
        fallback: 480,
      ),
      openDate: _asDate(data['open_date']) ?? DateTime.now(),
      closeDate: _asDate(data['close_date']) ?? DateTime.now(),
      pair: (data['pair'] ?? '').toString().toUpperCase(),
      direction: _asInt(data['direction'], fallback: 1),
      entryPrice: _asDouble(data['entry_price']),
      exitPrice: _asDouble(data['exit_price']),
      lotSize: _asDouble(data['lot_size']),
      pips: _asDouble(data['pips']),
      profitLoss: _asDouble(data['profit_loss']),
      result: _asInt(data['result'], fallback: 3),
      grossProfit: _asNullableDouble(
        data['gross_profit'] ?? data['grossProfit'],
      ),
      commission: _asDouble(data['commission']),
      swap: _asDouble(data['swap']),
      fees: _asDouble(data['fees'] ?? data['fee']),
      stopLoss: _asNullableDouble(data['stop_loss'] ?? data['stopLoss']),
      takeProfit: _asNullableDouble(data['take_profit'] ?? data['takeProfit']),
      initialRiskAmount: _asNullableDouble(
        data['initial_risk_amount'] ?? data['initialRiskAmount'],
      ),
      initialRiskPercent: _asNullableDouble(
        data['initial_risk_percent'] ?? data['initialRiskPercent'],
      ),
      setupName: (data['setup_name'] ?? data['setup'])?.toString(),
      session: data['session']?.toString(),
      sourceTicket:
          (data['source_ticket'] ??
                  data['ticket'] ??
                  data['deal_ticket'] ??
                  data['dealTicket'])
              ?.toString(),
      sourceOrderId:
          (data['source_order_id'] ??
                  data['order_id'] ??
                  data['order_ticket'] ??
                  data['orderTicket'])
              ?.toString(),
      sourcePositionId:
          (data['source_position_id'] ??
                  data['position_id'] ??
                  data['positionId'])
              ?.toString(),
      currency: (data['currency'] ?? '').toString().toUpperCase(),
      notes: (data['notes'] ?? '').toString(),
      emotionBeforeEntry: (data['emotion_before_entry'] ?? '').toString(),
      emotionDuringTrade: (data['emotion_during_trade'] ?? '').toString(),
      emotionAfterTrade: (data['emotion_after_trade'] ?? '').toString(),
      confidenceLevel: _asNullableInt(data['confidence_level']),
      disciplineRating: _asNullableInt(data['discipline_rating']),
      mistakeTags: _asStrings(data['mistake_tags']),
      psychologyNotes: (data['psychology_notes'] ?? '').toString(),
      followedTradingPlan: _asBool(
        data['followed_trading_plan'] ?? data['followedTradingPlan'],
      ),
      tradingPlanId: (data['trading_plan_id'] ?? data['tradingPlanId'])
          ?.toString(),
      tradingPlanName: (data['trading_plan_name'] ?? data['tradingPlanName'])
          ?.toString(),
      tradingPlanCheckedAt: _asDate(
        data['trading_plan_checked_at'] ?? data['tradingPlanCheckedAt'],
      ),
      strategyId: (data['strategy_id'] ?? data['strategyId'])?.toString(),
      strategyName: (data['strategy_name'] ?? data['strategyName'])?.toString(),
      planComplianceChecklist: _asBoolMap(
        data['plan_compliance_checklist'] ?? data['planComplianceChecklist'],
      ),
      strategyComplianceChecklist: _asBoolMap(
        data['strategy_compliance_checklist'] ??
            data['strategyComplianceChecklist'],
      ),
      followedRiskRules: _asBool(
        data['followed_risk_rules'] ?? data['followedRiskRules'],
      ),
      avoidedFomo: _asBool(data['avoided_fomo'] ?? data['avoidedFomo']),
      followedSessionRules: _asBool(
        data['followed_session_rules'] ?? data['followedSessionRules'],
      ),
      avoidedRestrictedNews: _asBool(
        data['avoided_restricted_news'] ?? data['avoidedRestrictedNews'],
      ),
      stoppedAfterMaxLoss: _asBool(
        data['stopped_after_max_loss'] ?? data['stoppedAfterMaxLoss'],
      ),
      followedStrategyRules: _asBool(
        data['followed_strategy_rules'] ?? data['followedStrategyRules'],
      ),
      followedSlRule: _asBool(
        data['followed_sl_rule'] ?? data['followedSlRule'],
      ),
      followedTpRule: _asBool(
        data['followed_tp_rule'] ?? data['followedTpRule'],
      ),
      followedInvalidationRule: _asBool(
        data['followed_invalidation_rule'] ?? data['followedInvalidationRule'],
      ),
      attachments: (data['attachments'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                JournalAttachment.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      createdAt: _asDate(data['created_at']),
      updatedAt: _asDate(data['updated_at']),
    );
  }

  factory TradingJournalEntry.fromJson(Map<String, dynamic> data) {
    final openDate = _asDate(data['open_date']) ?? DateTime.now();
    return TradingJournalEntry(
      id: (data['id'] ?? '').toString(),
      userId: (data['user_id'] ?? '').toString(),
      type: (data['type'] ?? 'trade').toString(),
      source: TradingRecordSource.fromStorage(
        data['source'] ?? data['record_source'] ?? data['recordSource'],
      ),
      tradingAccountId: data['trading_account_id']?.toString(),
      status: (data['status'] ?? 'closed').toString(),
      closeHandling: (data['close_handling'] ?? 'manual_close').toString(),
      timeInputTimezone: (data['time_input_timezone'] ?? 'malaysia').toString(),
      timeInputOffsetMinutes: _asInt(
        data['time_input_offset_minutes'],
        fallback: 480,
      ),
      openDate: openDate,
      closeDate: _asDate(data['close_date']) ?? openDate,
      pair: (data['pair'] ?? '').toString().toUpperCase(),
      direction: _asInt(data['direction'], fallback: 1),
      entryPrice: _asDouble(data['entry_price']),
      exitPrice: _asDouble(data['exit_price']),
      lotSize: _asDouble(data['lot_size']),
      pips: _asDouble(data['pips']),
      profitLoss: _asDouble(data['profit_loss']),
      result: _asInt(data['result'], fallback: 3),
      grossProfit: _asNullableDouble(
        data['gross_profit'] ?? data['grossProfit'],
      ),
      commission: _asDouble(data['commission']),
      swap: _asDouble(data['swap']),
      fees: _asDouble(data['fees'] ?? data['fee']),
      stopLoss: _asNullableDouble(data['stop_loss'] ?? data['stopLoss']),
      takeProfit: _asNullableDouble(data['take_profit'] ?? data['takeProfit']),
      initialRiskAmount: _asNullableDouble(
        data['initial_risk_amount'] ?? data['initialRiskAmount'],
      ),
      initialRiskPercent: _asNullableDouble(
        data['initial_risk_percent'] ?? data['initialRiskPercent'],
      ),
      setupName: (data['setup_name'] ?? data['setup'])?.toString(),
      session: data['session']?.toString(),
      sourceTicket:
          (data['source_ticket'] ??
                  data['ticket'] ??
                  data['deal_ticket'] ??
                  data['dealTicket'])
              ?.toString(),
      sourceOrderId:
          (data['source_order_id'] ??
                  data['order_id'] ??
                  data['order_ticket'] ??
                  data['orderTicket'])
              ?.toString(),
      sourcePositionId:
          (data['source_position_id'] ??
                  data['position_id'] ??
                  data['positionId'])
              ?.toString(),
      currency: (data['currency'] ?? '').toString().toUpperCase(),
      notes: (data['notes'] ?? '').toString(),
      emotionBeforeEntry: (data['emotion_before_entry'] ?? '').toString(),
      emotionDuringTrade: (data['emotion_during_trade'] ?? '').toString(),
      emotionAfterTrade: (data['emotion_after_trade'] ?? '').toString(),
      confidenceLevel: _asNullableInt(data['confidence_level']),
      disciplineRating: _asNullableInt(data['discipline_rating']),
      mistakeTags: _asStrings(data['mistake_tags']),
      psychologyNotes: (data['psychology_notes'] ?? '').toString(),
      followedTradingPlan: _asBool(
        data['followed_trading_plan'] ?? data['followedTradingPlan'],
      ),
      tradingPlanId: (data['trading_plan_id'] ?? data['tradingPlanId'])
          ?.toString(),
      tradingPlanName: (data['trading_plan_name'] ?? data['tradingPlanName'])
          ?.toString(),
      tradingPlanCheckedAt: _asDate(
        data['trading_plan_checked_at'] ?? data['tradingPlanCheckedAt'],
      ),
      strategyId: (data['strategy_id'] ?? data['strategyId'])?.toString(),
      strategyName: (data['strategy_name'] ?? data['strategyName'])?.toString(),
      planComplianceChecklist: _asBoolMap(
        data['plan_compliance_checklist'] ?? data['planComplianceChecklist'],
      ),
      strategyComplianceChecklist: _asBoolMap(
        data['strategy_compliance_checklist'] ??
            data['strategyComplianceChecklist'],
      ),
      followedRiskRules: _asBool(
        data['followed_risk_rules'] ?? data['followedRiskRules'],
      ),
      avoidedFomo: _asBool(data['avoided_fomo'] ?? data['avoidedFomo']),
      followedSessionRules: _asBool(
        data['followed_session_rules'] ?? data['followedSessionRules'],
      ),
      avoidedRestrictedNews: _asBool(
        data['avoided_restricted_news'] ?? data['avoidedRestrictedNews'],
      ),
      stoppedAfterMaxLoss: _asBool(
        data['stopped_after_max_loss'] ?? data['stoppedAfterMaxLoss'],
      ),
      followedStrategyRules: _asBool(
        data['followed_strategy_rules'] ?? data['followedStrategyRules'],
      ),
      followedSlRule: _asBool(
        data['followed_sl_rule'] ?? data['followedSlRule'],
      ),
      followedTpRule: _asBool(
        data['followed_tp_rule'] ?? data['followedTpRule'],
      ),
      followedInvalidationRule: _asBool(
        data['followed_invalidation_rule'] ?? data['followedInvalidationRule'],
      ),
      attachments: (data['attachments'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                JournalAttachment.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      createdAt: _asDate(data['created_at']),
      updatedAt: _asDate(data['updated_at']),
    );
  }

  Map<String, Object?> toApiJson() {
    return {
      'status': status,
      'source': source.storageValue,
      'trading_account_id': tradingAccountId,
      'close_handling': closeHandling,
      'time_input_timezone': timeInputTimezone,
      'time_input_offset_minutes': timeInputOffsetMinutes,
      'open_date': openDate.toIso8601String(),
      'close_date': status == 'closed' ? closeDate.toIso8601String() : null,
      'pair': pair.toUpperCase(),
      'direction': direction,
      'entry_price': entryPrice,
      'exit_price': status == 'closed' ? exitPrice : null,
      'lot_size': lotSize,
      'result': status == 'closed' ? result : null,
      'pips': status == 'closed' ? pips : null,
      'profit_loss': status == 'closed' ? profitLoss : null,
      if (grossProfit != null) 'gross_profit': grossProfit,
      'commission': commission,
      'swap': swap,
      'fees': fees,
      'stop_loss': stopLoss,
      'take_profit': takeProfit,
      'initial_risk_amount': initialRiskAmount,
      'initial_risk_percent': initialRiskPercent,
      'setup_name': setupName,
      'session': session,
      'source_ticket': sourceTicket,
      'source_order_id': sourceOrderId,
      'source_position_id': sourcePositionId,
      'currency': currency,
      'notes': notes,
      'emotion_before_entry': emotionBeforeEntry,
      'emotion_during_trade': emotionDuringTrade,
      'emotion_after_trade': emotionAfterTrade,
      'confidence_level': confidenceLevel,
      'discipline_rating': disciplineRating,
      'mistake_tags': mistakeTags,
      'psychology_notes': psychologyNotes,
      'followed_trading_plan': followedTradingPlan,
      'trading_plan_id': tradingPlanId,
      'trading_plan_name': tradingPlanName,
      'trading_plan_checked_at': tradingPlanCheckedAt?.toIso8601String(),
      'strategy_id': strategyId,
      'strategy_name': strategyName,
      'plan_compliance_checklist': planComplianceChecklist,
      'strategy_compliance_checklist': strategyComplianceChecklist,
      'followed_risk_rules': followedRiskRules,
      'avoided_fomo': avoidedFomo,
      'followed_session_rules': followedSessionRules,
      'avoided_restricted_news': avoidedRestrictedNews,
      'stopped_after_max_loss': stoppedAfterMaxLoss,
      'followed_strategy_rules': followedStrategyRules,
      'followed_sl_rule': followedSlRule,
      'followed_tp_rule': followedTpRule,
      'followed_invalidation_rule': followedInvalidationRule,
    };
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'type': type,
    'source': source.storageValue,
    'trading_account_id': tradingAccountId,
    'status': status,
    'close_handling': closeHandling,
    'time_input_timezone': timeInputTimezone,
    'time_input_offset_minutes': timeInputOffsetMinutes,
    'open_date': openDate.toIso8601String(),
    'close_date': closeDate.toIso8601String(),
    'pair': pair,
    'direction': direction,
    'entry_price': entryPrice,
    'exit_price': exitPrice,
    'lot_size': lotSize,
    'pips': pips,
    'profit_loss': profitLoss,
    'result': result,
    'gross_profit': grossProfit,
    'commission': commission,
    'swap': swap,
    'fees': fees,
    'stop_loss': stopLoss,
    'take_profit': takeProfit,
    'initial_risk_amount': initialRiskAmount,
    'initial_risk_percent': initialRiskPercent,
    'setup_name': setupName,
    'session': session,
    'source_ticket': sourceTicket,
    'source_order_id': sourceOrderId,
    'source_position_id': sourcePositionId,
    'currency': currency,
    'notes': notes,
    'emotion_before_entry': emotionBeforeEntry,
    'emotion_during_trade': emotionDuringTrade,
    'emotion_after_trade': emotionAfterTrade,
    'confidence_level': confidenceLevel,
    'discipline_rating': disciplineRating,
    'mistake_tags': mistakeTags,
    'psychology_notes': psychologyNotes,
    'followed_trading_plan': followedTradingPlan,
    'trading_plan_id': tradingPlanId,
    'trading_plan_name': tradingPlanName,
    'trading_plan_checked_at': tradingPlanCheckedAt?.toIso8601String(),
    'strategy_id': strategyId,
    'strategy_name': strategyName,
    'plan_compliance_checklist': planComplianceChecklist,
    'strategy_compliance_checklist': strategyComplianceChecklist,
    'followed_risk_rules': followedRiskRules,
    'avoided_fomo': avoidedFomo,
    'followed_session_rules': followedSessionRules,
    'avoided_restricted_news': avoidedRestrictedNews,
    'stopped_after_max_loss': stoppedAfterMaxLoss,
    'followed_strategy_rules': followedStrategyRules,
    'followed_sl_rule': followedSlRule,
    'followed_tp_rule': followedTpRule,
    'followed_invalidation_rule': followedInvalidationRule,
    'attachments': attachments.map((item) => item.toMap()).toList(),
    'created_at': createdAt?.toIso8601String(),
    'updated_at': updatedAt?.toIso8601String(),
  };

  Map<String, Object?> toFirestore({bool includeTimestamps = true}) {
    return {
      'user_id': userId,
      'type': type,
      'source': source.storageValue,
      'trading_account_id': tradingAccountId,
      'status': status,
      'close_handling': closeHandling,
      'time_input_timezone': timeInputTimezone,
      'time_input_offset_minutes': timeInputOffsetMinutes,
      'open_date': Timestamp.fromDate(openDate),
      'close_date': Timestamp.fromDate(closeDate),
      'pair': pair.toUpperCase(),
      'direction': direction,
      'entry_price': entryPrice,
      'exit_price': exitPrice,
      'lot_size': lotSize,
      'pips': pips,
      'profit_loss': profitLoss,
      'result': result,
      'gross_profit': grossProfit,
      'commission': commission,
      'swap': swap,
      'fees': fees,
      'stop_loss': stopLoss,
      'take_profit': takeProfit,
      'initial_risk_amount': initialRiskAmount,
      'initial_risk_percent': initialRiskPercent,
      'setup_name': setupName,
      'session': session,
      'source_ticket': sourceTicket,
      'source_order_id': sourceOrderId,
      'source_position_id': sourcePositionId,
      'currency': currency,
      'notes': notes,
      'emotion_before_entry': emotionBeforeEntry,
      'emotion_during_trade': emotionDuringTrade,
      'emotion_after_trade': emotionAfterTrade,
      'confidence_level': confidenceLevel,
      'discipline_rating': disciplineRating,
      'mistake_tags': mistakeTags,
      'psychology_notes': psychologyNotes,
      'followed_trading_plan': followedTradingPlan,
      'trading_plan_id': tradingPlanId,
      'trading_plan_name': tradingPlanName,
      'trading_plan_checked_at': tradingPlanCheckedAt == null
          ? null
          : Timestamp.fromDate(tradingPlanCheckedAt!),
      'strategy_id': strategyId,
      'strategy_name': strategyName,
      'plan_compliance_checklist': planComplianceChecklist,
      'strategy_compliance_checklist': strategyComplianceChecklist,
      'followed_risk_rules': followedRiskRules,
      'avoided_fomo': avoidedFomo,
      'followed_session_rules': followedSessionRules,
      'avoided_restricted_news': avoidedRestrictedNews,
      'stopped_after_max_loss': stoppedAfterMaxLoss,
      'followed_strategy_rules': followedStrategyRules,
      'followed_sl_rule': followedSlRule,
      'followed_tp_rule': followedTpRule,
      'followed_invalidation_rule': followedInvalidationRule,
      'attachments': attachments.map((item) => item.toMap()).toList(),
      if (includeTimestamps) 'updated_at': FieldValue.serverTimestamp(),
    };
  }
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '') ?? 0;
}

double? _asNullableDouble(Object? value) {
  if (value == null || value.toString().trim().isEmpty) return null;
  return value is num ? value.toDouble() : double.tryParse(value.toString());
}

int _asInt(Object? value, {required int fallback}) {
  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

int? _asNullableInt(Object? value) {
  if (value == null || value.toString().isEmpty) return null;
  return value is num ? value.toInt() : int.tryParse(value.toString());
}

bool _asBool(Object? value) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  final text = value?.toString().trim().toLowerCase();
  return text == 'true' || text == '1' || text == 'yes';
}

Map<String, bool> _asBoolMap(Object? value) {
  if (value is! Map) return const {};
  return value.map((key, item) => MapEntry(key.toString(), _asBool(item)));
}

List<String> _asStrings(Object? value) {
  if (value is List) return value.map((item) => item.toString()).toList();
  if (value is String && value.trim().isNotEmpty) {
    return value
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }
  return const [];
}

DateTime? _asDate(Object? value) {
  if (value is Timestamp) {
    return value.toDate();
  }

  if (value is DateTime) {
    return value;
  }

  return DateTime.tryParse(value?.toString() ?? '');
}
