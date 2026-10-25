import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';
import 'trading_signal_lifecycle.dart';

class TradingSignalItem {
  const TradingSignalItem({
    required this.id,
    required this.tradingPair,
    this.signalCode = '',
    this.title = '',
    this.immediateAction = '',
    this.entryPrice = '',
    this.stopLoss = '',
    this.targets = const [],
    this.disclaimer = '',
    this.riskLevel = '',
    this.status = 0,
    this.isDone = false,
    this.isBreakEven = false,
    this.isSetBreakEven = false,
    this.pipsResult,
    this.triggerTime,
    this.createdAt,
    this.updatedAt,
    this.currentPrice = '',
    this.analyst = '',
    this.providerId = '',
    this.operatorIds = const <String>{},
    this.signalSource = '',
    this.lifecycleStatus = '',
    this.timeframe = '',
    this.orderType = '',
    this.expiryTime,
    this.marketCondition = '',
    this.tradeSession = '',
    this.suggestedRiskPercent = '',
    this.activatedAt,
    this.closedAt,
    this.actualExitPrice,
    this.realizedRiskReward,
    this.isTest = false,
    this.isDeleted = false,
    this.analysisSections = const {},
    this.link = '',
    this.chartImage = '',
    this.primaryLanguage = 'en',
    this.availableLanguages = const [],
    this.titleI18n = const {},
    this.immediateActionI18n = const {},
    this.disclaimerI18n = const {},
    this.riskLevelI18n = const {},
    this.originalEntryPrice = '',
    this.originalStopLoss = '',
    this.currentStopLoss = '',
    this.breakEvenPrice = '',
    this.targetsHit = const {},
    this.currentTargetIndex = 0,
    this.percentageRemaining = 100,
    this.publishedAt,
    this.completedAt,
    this.cancelledAt,
    this.isArchived = false,
    this.version = 0,
    this.lastUpdatedBy = '',
    this.lastUpdateType = '',
  });

  final String id;
  final String tradingPair;
  final String signalCode;
  final String title;
  final String immediateAction;
  final String entryPrice;
  final String stopLoss;
  final List<String> targets;
  final String disclaimer;
  final String riskLevel;
  final int status;
  final bool isDone;
  final bool isBreakEven;
  final bool isSetBreakEven;
  final int? pipsResult;
  final DateTime? triggerTime;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final String currentPrice;
  final String analyst;
  final String providerId;
  final Set<String> operatorIds;
  final String signalSource;
  final String lifecycleStatus;
  final String timeframe;
  final String orderType;
  final DateTime? expiryTime;
  final String marketCondition;
  final String tradeSession;
  final String suggestedRiskPercent;
  final DateTime? activatedAt;
  final DateTime? closedAt;
  final double? actualExitPrice;
  final double? realizedRiskReward;
  final bool isTest;
  final bool isDeleted;
  final Map<String, String> analysisSections;
  final String link;
  final String chartImage;
  final String primaryLanguage;
  final List<String> availableLanguages;
  final Map<String, String> titleI18n;
  final Map<String, String> immediateActionI18n;
  final Map<String, String> disclaimerI18n;
  final Map<String, String> riskLevelI18n;
  final String originalEntryPrice;
  final String originalStopLoss;
  final String currentStopLoss;
  final String breakEvenPrice;
  final Set<int> targetsHit;
  final int currentTargetIndex;
  final double percentageRemaining;
  final DateTime? publishedAt;
  final DateTime? completedAt;
  final DateTime? cancelledAt;
  final bool isArchived;
  final int version;
  final String lastUpdatedBy;
  final String lastUpdateType;

  SignalLifecycleStatus get lifecycle => SignalLifecycleStatus.parse(
    lifecycleStatus,
    legacyStatus: status,
    done: isDone,
    breakEvenHit: isBreakEven,
    breakEvenSet: isSetBreakEven,
    archived: isArchived,
  );

  SignalLifecycleSnapshot get lifecycleSnapshot =>
      SignalLifecycleSnapshot.fromMap(Map<String, dynamic>.from(toFirestore()));

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);
  String localizedImmediateAction(String languageCode) => localizedValue(
    immediateActionI18n,
    languageCode,
    fallback: immediateAction,
  );
  String localizedDisclaimer(String languageCode) =>
      localizedValue(disclaimerI18n, languageCode, fallback: disclaimer);
  String localizedRiskLevel(String languageCode) =>
      localizedValue(riskLevelI18n, languageCode, fallback: riskLevel);

  String get progressLabel {
    return lifecycle.label;
  }

  bool get isClosed =>
      lifecycle.isTerminal || lifecycle == SignalLifecycleStatus.archived;

  bool isOperableBy(String userId) {
    final normalized = userId.trim();
    return normalized.isNotEmpty &&
        (operatorIds.contains(normalized) || providerId == normalized);
  }

  String get direction {
    final action = immediateAction.trim().toLowerCase();
    if (action.contains('buy') || action.contains('long')) return 'Buy';
    if (action.contains('sell') || action.contains('short')) return 'Sell';
    if (status == 12) return 'Cancelled';
    if (isClosed) return 'Completed';
    return 'Neutral';
  }

  double? get riskRewardRatio {
    final entry = _firstNumber(entryPrice);
    final stop = _firstNumber(stopLoss);
    final target = targets.isEmpty ? null : _firstNumber(targets.first);
    if (entry == null || stop == null || target == null) return null;
    final risk = (entry - stop).abs();
    if (risk == 0) return null;
    return (target - entry).abs() / risk;
  }

  factory TradingSignalItem.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    final targets = <String>[];
    for (var index = 1; index <= 20; index++) {
      final value = data['target_$index'];
      if (value != null && value.toString().trim().isNotEmpty) {
        targets.add(value.toString());
      }
    }

    return TradingSignalItem(
      id: doc.id,
      tradingPair: (data['trading_pair'] ?? data['symbol'] ?? '').toString(),
      signalCode: (data['signal_code'] ?? '').toString(),
      title: localizedValue(data['signal_title'] ?? data['title'], 'en'),
      immediateAction: localizedValue(
        data['immediate_action'] ?? data['signal_type'],
        'en',
      ),
      entryPrice: (data['entry_price'] ?? '').toString(),
      stopLoss: (data['stop_loss'] ?? '').toString(),
      targets: targets.isEmpty ? firestoreStringList(data['targets']) : targets,
      disclaimer: localizedValue(data['disclaimer'], 'en'),
      riskLevel: localizedValue(data['risk_level'], 'en'),
      status: firestoreInt(data['status']),
      isDone: firestoreBool(data['is_done']) || firestoreBool(data['IsDone']),
      isBreakEven: firestoreBool(data['IsBE']),
      isSetBreakEven: firestoreBool(data['IsSetBE']),
      pipsResult: data['pips_result'] == null
          ? null
          : firestoreInt(data['pips_result']),
      triggerTime: firestoreDate(data['trigger_time']),
      createdAt: firestoreDate(data['created_at']),
      updatedAt: firestoreDate(data['updated_at']),
      currentPrice: (data['current_price'] ?? data['market_price'] ?? '')
          .toString(),
      analyst:
          (data['analyst_name'] ??
                  data['source_name'] ??
                  data['created_by_name'] ??
                  '')
              .toString(),
      providerId:
          (data['provider_id'] ??
                  data['assigned_provider_id'] ??
                  data['assignedProviderId'] ??
                  data['created_by'] ??
                  data['created_by_id'] ??
                  data['user_id'] ??
                  '')
              .toString(),
      operatorIds: _signalOperatorIds(data),
      signalSource:
          (data['signal_source'] ?? data['source'] ?? data['source_name'] ?? '')
              .toString(),
      lifecycleStatus: (data['lifecycle_status'] ?? data['status_name'] ?? '')
          .toString(),
      timeframe: (data['timeframe'] ?? '').toString(),
      orderType: (data['order_type'] ?? '').toString(),
      expiryTime: firestoreDate(data['expiry_time'] ?? data['expires_at']),
      marketCondition: (data['market_condition'] ?? '').toString(),
      tradeSession: (data['trade_session'] ?? data['session'] ?? '').toString(),
      suggestedRiskPercent:
          (data['suggested_risk_percent'] ?? data['risk_percent'] ?? '')
              .toString(),
      activatedAt: firestoreDate(
        data['activated_at'] ?? data['actual_entry_at'] ?? data['trigger_time'],
      ),
      closedAt: firestoreDate(
        data['closed_at'] ?? data['result_at'] ?? data['completed_at'],
      ),
      actualExitPrice: _nullableNumber(
        data['actual_exit_price'] ?? data['close_price'] ?? data['exit_price'],
      ),
      realizedRiskReward: _nullableNumber(
        data['realized_risk_reward'] ?? data['realized_rr'],
      ),
      isTest:
          firestoreBool(data['is_test']) || firestoreBool(data['test_record']),
      isDeleted:
          firestoreBool(data['is_deleted']) ||
          data['deleted_at'] != null ||
          firestoreBool(data['duplicate']) ||
          firestoreBool(data['is_duplicate']),
      analysisSections: _analysisSections(data),
      link: (data['link'] ?? '').toString(),
      chartImage:
          (data['chart_image_url'] ??
                  data['chart_image'] ??
                  data['image_url'] ??
                  '')
              .toString(),
      primaryLanguage: _primaryLanguage(data['primary_language']),
      availableLanguages: _availableLanguages(data),
      titleI18n: _localizedField(data, 'signal_title', aliasKey: 'title'),
      immediateActionI18n: _localizedField(data, 'immediate_action'),
      disclaimerI18n: _localizedField(data, 'disclaimer'),
      riskLevelI18n: _localizedField(data, 'risk_level'),
      originalEntryPrice:
          (data['original_entry_price'] ?? data['entry_price'] ?? '')
              .toString(),
      originalStopLoss: (data['original_stop_loss'] ?? data['stop_loss'] ?? '')
          .toString(),
      currentStopLoss: (data['current_stop_loss'] ?? data['stop_loss'] ?? '')
          .toString(),
      breakEvenPrice: (data['break_even_price'] ?? '').toString(),
      targetsHit: firestoreIntList(
        data['targets_hit'] ?? data['targetsHit'],
      ).toSet(),
      currentTargetIndex: firestoreInt(data['current_target_index']),
      percentageRemaining:
          double.tryParse('${data['percentage_remaining'] ?? 100}') ?? 100,
      publishedAt: firestoreDate(data['published_at']),
      completedAt: firestoreDate(data['completed_at']),
      cancelledAt: firestoreDate(data['cancelled_at']),
      isArchived: firestoreBool(data['archived']),
      version: firestoreInt(data['version']),
      lastUpdatedBy: (data['last_updated_by'] ?? data['updated_by'] ?? '')
          .toString(),
      lastUpdateType: (data['last_update_type'] ?? '').toString(),
    );
  }

  factory TradingSignalItem.fromJson(Map<String, dynamic> data) {
    final targets = <String>[];
    for (var index = 1; index <= 20; index++) {
      final value = data['target_$index'];
      if (value != null && value.toString().trim().isNotEmpty) {
        targets.add(value.toString());
      }
    }
    return TradingSignalItem(
      id: (data['id'] ?? '').toString(),
      tradingPair: (data['trading_pair'] ?? data['symbol'] ?? '').toString(),
      signalCode: (data['signal_code'] ?? '').toString(),
      title: localizedValue(data['signal_title'] ?? data['title'], 'en'),
      immediateAction: localizedValue(
        data['immediate_action'] ?? data['signal_type'],
        'en',
      ),
      entryPrice: (data['entry_price'] ?? '').toString(),
      stopLoss: (data['stop_loss'] ?? '').toString(),
      targets: targets.isEmpty ? firestoreStringList(data['targets']) : targets,
      disclaimer: localizedValue(data['disclaimer'], 'en'),
      riskLevel: localizedValue(data['risk_level'], 'en'),
      status: firestoreInt(data['status']),
      isDone: firestoreBool(data['is_done']) || firestoreBool(data['IsDone']),
      isBreakEven: firestoreBool(data['IsBE']),
      isSetBreakEven: firestoreBool(data['IsSetBE']),
      pipsResult: data['pips_result'] == null
          ? null
          : firestoreInt(data['pips_result']),
      triggerTime: firestoreDate(data['trigger_time']),
      createdAt: firestoreDate(data['created_at']),
      updatedAt: firestoreDate(data['updated_at']),
      currentPrice: (data['current_price'] ?? data['market_price'] ?? '')
          .toString(),
      analyst:
          (data['analyst_name'] ??
                  data['source_name'] ??
                  data['created_by_name'] ??
                  '')
              .toString(),
      providerId:
          (data['provider_id'] ??
                  data['assigned_provider_id'] ??
                  data['assignedProviderId'] ??
                  data['created_by'] ??
                  data['created_by_id'] ??
                  data['user_id'] ??
                  '')
              .toString(),
      operatorIds: _signalOperatorIds(data),
      signalSource:
          (data['signal_source'] ?? data['source'] ?? data['source_name'] ?? '')
              .toString(),
      lifecycleStatus: (data['lifecycle_status'] ?? data['status_name'] ?? '')
          .toString(),
      timeframe: (data['timeframe'] ?? '').toString(),
      orderType: (data['order_type'] ?? '').toString(),
      expiryTime: firestoreDate(data['expiry_time'] ?? data['expires_at']),
      marketCondition: (data['market_condition'] ?? '').toString(),
      tradeSession: (data['trade_session'] ?? data['session'] ?? '').toString(),
      suggestedRiskPercent:
          (data['suggested_risk_percent'] ?? data['risk_percent'] ?? '')
              .toString(),
      activatedAt: firestoreDate(
        data['activated_at'] ?? data['actual_entry_at'] ?? data['trigger_time'],
      ),
      closedAt: firestoreDate(
        data['closed_at'] ?? data['result_at'] ?? data['completed_at'],
      ),
      actualExitPrice: _nullableNumber(
        data['actual_exit_price'] ?? data['close_price'] ?? data['exit_price'],
      ),
      realizedRiskReward: _nullableNumber(
        data['realized_risk_reward'] ?? data['realized_rr'],
      ),
      isTest:
          firestoreBool(data['is_test']) || firestoreBool(data['test_record']),
      isDeleted:
          firestoreBool(data['is_deleted']) ||
          data['deleted_at'] != null ||
          firestoreBool(data['duplicate']) ||
          firestoreBool(data['is_duplicate']),
      analysisSections: _analysisSections(data),
      link: (data['link'] ?? '').toString(),
      chartImage:
          (data['chart_image_url'] ??
                  data['chart_image'] ??
                  data['image_url'] ??
                  '')
              .toString(),
      primaryLanguage: _primaryLanguage(data['primary_language']),
      availableLanguages: _availableLanguages(data),
      titleI18n: _localizedField(data, 'signal_title', aliasKey: 'title'),
      immediateActionI18n: _localizedField(data, 'immediate_action'),
      disclaimerI18n: _localizedField(data, 'disclaimer'),
      riskLevelI18n: _localizedField(data, 'risk_level'),
      originalEntryPrice:
          (data['original_entry_price'] ?? data['entry_price'] ?? '')
              .toString(),
      originalStopLoss: (data['original_stop_loss'] ?? data['stop_loss'] ?? '')
          .toString(),
      currentStopLoss: (data['current_stop_loss'] ?? data['stop_loss'] ?? '')
          .toString(),
      breakEvenPrice: (data['break_even_price'] ?? '').toString(),
      targetsHit: firestoreIntList(
        data['targets_hit'] ?? data['targetsHit'],
      ).toSet(),
      currentTargetIndex: firestoreInt(data['current_target_index']),
      percentageRemaining:
          double.tryParse('${data['percentage_remaining'] ?? 100}') ?? 100,
      publishedAt: firestoreDate(data['published_at']),
      completedAt: firestoreDate(data['completed_at']),
      cancelledAt: firestoreDate(data['cancelled_at']),
      isArchived: firestoreBool(data['archived']),
      version: firestoreInt(data['version']),
      lastUpdatedBy: (data['last_updated_by'] ?? data['updated_by'] ?? '')
          .toString(),
      lastUpdateType: (data['last_update_type'] ?? '').toString(),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'trading_pair': tradingPair,
      'signal_code': signalCode,
      'signal_title': title,
      'immediate_action': immediateAction,
      if (titleI18n.isNotEmpty) 'signal_title_i18n': titleI18n,
      if (titleI18n.isNotEmpty) 'title_i18n': titleI18n,
      if (immediateActionI18n.isNotEmpty)
        'immediate_action_i18n': immediateActionI18n,
      'entry_price': entryPrice,
      'stop_loss': stopLoss,
      for (var index = 0; index < targets.length && index < 20; index++)
        'target_${index + 1}': targets[index],
      'disclaimer': disclaimer,
      'risk_level': riskLevel,
      if (disclaimerI18n.isNotEmpty) 'disclaimer_i18n': disclaimerI18n,
      if (riskLevelI18n.isNotEmpty) 'risk_level_i18n': riskLevelI18n,
      if (originalEntryPrice.isNotEmpty)
        'original_entry_price': originalEntryPrice,
      if (originalStopLoss.isNotEmpty) 'original_stop_loss': originalStopLoss,
      if (currentStopLoss.isNotEmpty) 'current_stop_loss': currentStopLoss,
      if (breakEvenPrice.isNotEmpty) 'break_even_price': breakEvenPrice,
      if (targetsHit.isNotEmpty) 'targets_hit': targetsHit.toList()..sort(),
      'current_target_index': currentTargetIndex,
      'percentage_remaining': percentageRemaining,
      if (publishedAt != null) 'published_at': Timestamp.fromDate(publishedAt!),
      if (completedAt != null) 'completed_at': Timestamp.fromDate(completedAt!),
      if (cancelledAt != null) 'cancelled_at': Timestamp.fromDate(cancelledAt!),
      if (isArchived) 'archived': true,
      'version': version,
      if (lastUpdatedBy.isNotEmpty) 'last_updated_by': lastUpdatedBy,
      if (lastUpdateType.isNotEmpty) 'last_update_type': lastUpdateType,
      'status': status,
      'is_done': isDone ? 1 : 0,
      'IsBE': isBreakEven ? 1 : 0,
      'IsSetBE': isSetBreakEven ? 1 : 0,
      'pips_result': pipsResult,
      'trigger_time': triggerTime == null
          ? null
          : Timestamp.fromDate(triggerTime!),
      'link': link,
      if (chartImage.isNotEmpty) 'chart_image_url': chartImage,
      'primary_language': _primaryLanguage(primaryLanguage),
      if (availableLanguages.isNotEmpty)
        'available_languages': availableLanguages,
      if (currentPrice.isNotEmpty) 'current_price': currentPrice,
      if (analyst.isNotEmpty) 'analyst_name': analyst,
      if (providerId.isNotEmpty) 'provider_id': providerId,
      if (signalSource.isNotEmpty) 'signal_source': signalSource,
      if (lifecycleStatus.isNotEmpty) 'lifecycle_status': lifecycleStatus,
      if (timeframe.isNotEmpty) 'timeframe': timeframe,
      if (orderType.isNotEmpty) 'order_type': orderType,
      if (expiryTime != null) 'expiry_time': Timestamp.fromDate(expiryTime!),
      if (marketCondition.isNotEmpty) 'market_condition': marketCondition,
      if (tradeSession.isNotEmpty) 'trade_session': tradeSession,
      if (suggestedRiskPercent.isNotEmpty)
        'suggested_risk_percent': suggestedRiskPercent,
      if (activatedAt != null) 'activated_at': Timestamp.fromDate(activatedAt!),
      if (closedAt != null) 'closed_at': Timestamp.fromDate(closedAt!),
      if (actualExitPrice != null) 'actual_exit_price': actualExitPrice,
      if (realizedRiskReward != null)
        'realized_risk_reward': realizedRiskReward,
      if (isTest) 'is_test': true,
      if (isDeleted) 'is_deleted': true,
      ...analysisSections,
    };
  }
}

Set<String> _signalOperatorIds(Map<String, dynamic> data) => <String>{
  for (final value in [
    data['created_by'],
    data['created_by_id'],
    data['provider_id'],
    data['assigned_provider_id'],
    data['assignedProviderId'],
    data['user_id'],
  ])
    if (value != null && value.toString().trim().isNotEmpty)
      value.toString().trim(),
  ...firestoreStringList(data['assigned_provider_ids']),
  ...firestoreStringList(data['assignedProviderIds']),
};

String _primaryLanguage(Object? value) =>
    value?.toString().trim().toLowerCase().startsWith('zh') == true
    ? 'zh'
    : 'en';

List<String> _availableLanguages(Map<String, dynamic> data) {
  final stored = firestoreStringList(data['available_languages']);
  if (stored.isNotEmpty) {
    return stored.map(_primaryLanguage).toSet().toList(growable: false);
  }
  final maps = [
    _localizedField(data, 'signal_title', aliasKey: 'title'),
    _localizedField(data, 'disclaimer'),
  ];
  return [
    for (final code in const ['en', 'zh'])
      if (maps.any((map) => (map[code] ?? '').trim().isNotEmpty)) code,
  ];
}

Map<String, String> _analysisSections(Map<String, dynamic> data) {
  const keys = [
    'market_bias',
    'major_structure',
    'internal_structure',
    'current_structure',
    'key_level',
    'entry_confirmation',
    'confluences',
    'invalidation',
    'risk_warning',
    'analyst_notes',
  ];
  return {
    for (final key in keys)
      if ((data[key] ?? '').toString().trim().isNotEmpty)
        key: (data[key] ?? '').toString().trim(),
    if ((data['analysis'] ?? '').toString().trim().isNotEmpty &&
        (data['analyst_notes'] ?? '').toString().trim().isEmpty)
      'analyst_notes': (data['analysis'] ?? '').toString().trim(),
  };
}

double? _firstNumber(String value) {
  final match = RegExp(
    r'-?\d+(?:\.\d+)?',
  ).firstMatch(value.replaceAll(',', ''));
  return match == null ? null : double.tryParse(match.group(0)!);
}

double? _nullableNumber(Object? value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString().replaceAll(',', '') ?? '');
}

Map<String, String> _localizedField(
  Map<String, dynamic> data,
  String key, {
  String? aliasKey,
}) {
  final map = {
    ...localizedTextMap(data['${key}_i18n']),
    ...localizedTextMap(data[key]),
  };
  if (aliasKey != null) {
    map.addAll(localizedTextMap(data['${aliasKey}_i18n']));
    map.addAll(localizedTextMap(data[aliasKey]));
  }
  for (final candidate in [key, ?aliasKey]) {
    final english = data['${candidate}_en']?.toString().trim() ?? '';
    final chinese = data['${candidate}_zh']?.toString().trim() ?? '';
    if (english.isNotEmpty) map['en'] = english;
    if (chinese.isNotEmpty) map['zh'] = chinese;
  }
  return map;
}
