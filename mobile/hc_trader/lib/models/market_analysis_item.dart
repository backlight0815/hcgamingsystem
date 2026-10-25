import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';

class MarketAnalysisItem {
  const MarketAnalysisItem({
    required this.id,
    required this.title,
    this.market = 'XAUUSD',
    this.trendStrength = '',
    this.analysisDate,
    this.marketOverview = '',
    this.trendStructure = '',
    this.keyZones = '',
    this.analystView = '',
    this.strategy = '',
    this.tradingPlan = '',
    this.chartSignals = '',
    this.rsiLevel = '',
    this.orderBlock = '',
    this.outlookImage = '',
    this.outlookCode = '',
    this.titleI18n = const {},
    this.marketOverviewI18n = const {},
    this.trendStructureI18n = const {},
    this.keyZonesI18n = const {},
    this.analystViewI18n = const {},
    this.strategyI18n = const {},
    this.tradingPlanI18n = const {},
    this.chartSignalsI18n = const {},
    this.orderBlockI18n = const {},
    this.createdBy = '',
    this.authorName = '',
    this.status = 'published',
    this.publishedAt,
    this.updatedAt,
    this.timeframe = '',
    this.riskLevel = '',
    this.tags = const [],
    this.chartImages = const [],
    this.primaryLanguage = 'en',
    this.availableLanguages = const [],
    this.outcomeStatus = '',
    this.outcomeScore,
  });

  final String id;
  final String title;
  final String market;
  final String trendStrength;
  final DateTime? analysisDate;
  final String marketOverview;
  final String trendStructure;
  final String keyZones;
  final String analystView;
  final String strategy;
  final String tradingPlan;
  final String chartSignals;
  final String rsiLevel;
  final String orderBlock;
  final String outlookImage;
  final String outlookCode;
  final Map<String, String> titleI18n;
  final Map<String, String> marketOverviewI18n;
  final Map<String, String> trendStructureI18n;
  final Map<String, String> keyZonesI18n;
  final Map<String, String> analystViewI18n;
  final Map<String, String> strategyI18n;
  final Map<String, String> tradingPlanI18n;
  final Map<String, String> chartSignalsI18n;
  final Map<String, String> orderBlockI18n;
  final String createdBy;
  final String authorName;
  final String status;
  final DateTime? publishedAt;
  final DateTime? updatedAt;
  final String timeframe;
  final String riskLevel;
  final List<String> tags;
  final List<String> chartImages;
  final String primaryLanguage;
  final List<String> availableLanguages;
  final String outcomeStatus;
  final double? outcomeScore;

  bool get isEvaluated => const {
    'correct',
    'incorrect',
    'partial',
    'successful',
    'failed',
  }.contains(outcomeStatus.trim().toLowerCase());

  bool get isAccurateOutcome => const {
    'correct',
    'successful',
  }.contains(outcomeStatus.trim().toLowerCase());

  List<String> get allChartImages => <String>{
    if (outlookImage.trim().isNotEmpty) outlookImage.trim(),
    ...chartImages.where((item) => item.trim().isNotEmpty),
  }.toList(growable: false);

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);
  String localizedMarketOverview(String languageCode) => localizedValue(
    marketOverviewI18n,
    languageCode,
    fallback: marketOverview,
  );
  String localizedTrendStructure(String languageCode) => localizedValue(
    trendStructureI18n,
    languageCode,
    fallback: trendStructure,
  );
  String localizedKeyZones(String languageCode) =>
      localizedValue(keyZonesI18n, languageCode, fallback: keyZones);
  String localizedAnalystView(String languageCode) =>
      localizedValue(analystViewI18n, languageCode, fallback: analystView);
  String localizedStrategy(String languageCode) =>
      localizedValue(strategyI18n, languageCode, fallback: strategy);
  String localizedTradingPlan(String languageCode) =>
      localizedValue(tradingPlanI18n, languageCode, fallback: tradingPlan);
  String localizedChartSignals(String languageCode) =>
      localizedValue(chartSignalsI18n, languageCode, fallback: chartSignals);
  String localizedOrderBlock(String languageCode) =>
      localizedValue(orderBlockI18n, languageCode, fallback: orderBlock);

  factory MarketAnalysisItem.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};

    return MarketAnalysisItem(
      id: doc.id,
      title: localizedValue(data['title'], 'en'),
      market: (data['market'] ?? 'XAUUSD').toString(),
      trendStrength: (data['trend_strength'] ?? '').toString(),
      analysisDate: firestoreDate(data['analysis_date']),
      marketOverview: localizedValue(data['market_overview'], 'en'),
      trendStructure: localizedValue(data['trend_structure'], 'en'),
      keyZones: localizedValue(data['key_zones'], 'en'),
      analystView: localizedValue(data['analyst_view'], 'en'),
      strategy: localizedValue(data['strategy'], 'en'),
      tradingPlan: localizedValue(data['trading_plan'], 'en'),
      chartSignals: localizedValue(data['chart_signals'], 'en'),
      rsiLevel: (data['rsi_level'] ?? '').toString(),
      orderBlock: localizedValue(data['order_block'], 'en'),
      outlookImage: (data['outlook_image'] ?? '').toString(),
      outlookCode: (data['Outlook_Code'] ?? data['outlook_code'] ?? '')
          .toString(),
      titleI18n: _localizedField(data, 'title'),
      marketOverviewI18n: _localizedField(data, 'market_overview'),
      trendStructureI18n: _localizedField(data, 'trend_structure'),
      keyZonesI18n: _localizedField(data, 'key_zones'),
      analystViewI18n: _localizedField(data, 'analyst_view'),
      strategyI18n: _localizedField(data, 'strategy'),
      tradingPlanI18n: _localizedField(data, 'trading_plan'),
      chartSignalsI18n: _localizedField(data, 'chart_signals'),
      orderBlockI18n: _localizedField(data, 'order_block'),
      createdBy: (data['created_by'] ?? data['published_by'] ?? '').toString(),
      authorName: (data['author_name'] ?? data['analyst_name'] ?? '')
          .toString(),
      status: (data['status'] ?? 'published').toString(),
      publishedAt: firestoreDate(data['published_at']),
      updatedAt: firestoreDate(data['updated_at']),
      timeframe: (data['timeframe'] ?? '').toString(),
      riskLevel: (data['risk_level'] ?? '').toString(),
      tags: _strings(data['tags']),
      chartImages: _strings(data['chart_images']),
      primaryLanguage: _primaryLanguage(data['primary_language']),
      availableLanguages: _availableLanguages(data),
      outcomeStatus: (data['outcome_status'] ?? data['outcome'] ?? '')
          .toString(),
      outcomeScore: _nullableDouble(data['outcome_score']),
    );
  }

  factory MarketAnalysisItem.fromJson(Map<String, dynamic> data) {
    return MarketAnalysisItem(
      id: (data['id'] ?? '').toString(),
      title: localizedValue(data['title'], 'en'),
      market: (data['market'] ?? 'XAUUSD').toString(),
      trendStrength: (data['trend_strength'] ?? '').toString(),
      analysisDate: firestoreDate(data['analysis_date']),
      marketOverview: localizedValue(data['market_overview'], 'en'),
      trendStructure: localizedValue(data['trend_structure'], 'en'),
      keyZones: localizedValue(data['key_zones'], 'en'),
      analystView: localizedValue(data['analyst_view'], 'en'),
      strategy: localizedValue(data['strategy'], 'en'),
      tradingPlan: localizedValue(data['trading_plan'], 'en'),
      chartSignals: localizedValue(data['chart_signals'], 'en'),
      rsiLevel: (data['rsi_level'] ?? '').toString(),
      orderBlock: localizedValue(data['order_block'], 'en'),
      outlookImage: (data['outlook_image'] ?? '').toString(),
      outlookCode: (data['Outlook_Code'] ?? data['outlook_code'] ?? '')
          .toString(),
      titleI18n: _localizedField(data, 'title'),
      marketOverviewI18n: _localizedField(data, 'market_overview'),
      trendStructureI18n: _localizedField(data, 'trend_structure'),
      keyZonesI18n: _localizedField(data, 'key_zones'),
      analystViewI18n: _localizedField(data, 'analyst_view'),
      strategyI18n: _localizedField(data, 'strategy'),
      tradingPlanI18n: _localizedField(data, 'trading_plan'),
      chartSignalsI18n: _localizedField(data, 'chart_signals'),
      orderBlockI18n: _localizedField(data, 'order_block'),
      createdBy: (data['created_by'] ?? data['published_by'] ?? '').toString(),
      authorName: (data['author_name'] ?? data['analyst_name'] ?? '')
          .toString(),
      status: (data['status'] ?? 'published').toString(),
      publishedAt: firestoreDate(data['published_at']),
      updatedAt: firestoreDate(data['updated_at']),
      timeframe: (data['timeframe'] ?? '').toString(),
      riskLevel: (data['risk_level'] ?? '').toString(),
      tags: _strings(data['tags']),
      chartImages: _strings(data['chart_images']),
      primaryLanguage: _primaryLanguage(data['primary_language']),
      availableLanguages: _availableLanguages(data),
      outcomeStatus: (data['outcome_status'] ?? data['outcome'] ?? '')
          .toString(),
      outcomeScore: _nullableDouble(data['outcome_score']),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'title': title,
      if (titleI18n.isNotEmpty) 'title_i18n': titleI18n,
      'market': market,
      'trend_strength': trendStrength,
      'analysis_date': Timestamp.fromDate(analysisDate ?? DateTime.now()),
      'market_overview': marketOverview,
      if (marketOverviewI18n.isNotEmpty)
        'market_overview_i18n': marketOverviewI18n,
      'trend_structure': trendStructure,
      if (trendStructureI18n.isNotEmpty)
        'trend_structure_i18n': trendStructureI18n,
      'key_zones': keyZones,
      if (keyZonesI18n.isNotEmpty) 'key_zones_i18n': keyZonesI18n,
      'analyst_view': analystView,
      if (analystViewI18n.isNotEmpty) 'analyst_view_i18n': analystViewI18n,
      'strategy': strategy,
      if (strategyI18n.isNotEmpty) 'strategy_i18n': strategyI18n,
      'trading_plan': tradingPlan,
      if (tradingPlanI18n.isNotEmpty) 'trading_plan_i18n': tradingPlanI18n,
      'chart_signals': chartSignals,
      if (chartSignalsI18n.isNotEmpty) 'chart_signals_i18n': chartSignalsI18n,
      'rsi_level': rsiLevel,
      'order_block': orderBlock,
      if (orderBlockI18n.isNotEmpty) 'order_block_i18n': orderBlockI18n,
      'outlook_image': outlookImage,
      'outlook_code': outlookCode,
      if (timeframe.isNotEmpty) 'timeframe': timeframe,
      if (riskLevel.isNotEmpty) 'risk_level': riskLevel,
      if (tags.isNotEmpty) 'tags': tags,
      if (chartImages.isNotEmpty) 'chart_images': chartImages,
      'primary_language': _primaryLanguage(primaryLanguage),
      if (availableLanguages.isNotEmpty)
        'available_languages': availableLanguages,
      if (outcomeStatus.isNotEmpty) 'outcome_status': outcomeStatus,
      if (outcomeScore != null) 'outcome_score': outcomeScore,
    };
  }
}

String _primaryLanguage(Object? value) =>
    value?.toString().trim().toLowerCase().startsWith('zh') == true
    ? 'zh'
    : 'en';

List<String> _availableLanguages(Map<String, dynamic> data) {
  final stored = _strings(data['available_languages']);
  if (stored.isNotEmpty) {
    return stored.map(_primaryLanguage).toSet().toList(growable: false);
  }
  final maps = [
    _localizedField(data, 'title'),
    _localizedField(data, 'market_overview'),
    _localizedField(data, 'trading_plan'),
  ];
  return [
    for (final code in const ['en', 'zh'])
      if (maps.any((map) => (map[code] ?? '').trim().isNotEmpty)) code,
  ];
}

List<String> _strings(Object? value) {
  if (value is! Iterable) return const [];
  return value
      .map((item) => item.toString().trim())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}

double? _nullableDouble(Object? value) => value == null
    ? null
    : value is num
    ? value.toDouble()
    : double.tryParse(value.toString());

Map<String, String> _localizedField(Map<String, dynamic> data, String key) {
  final map = {
    ...localizedTextMap(data['${key}_i18n']),
    ...localizedTextMap(data[key]),
  };
  final english = data['${key}_en']?.toString().trim() ?? '';
  final chinese = data['${key}_zh']?.toString().trim() ?? '';
  if (english.isNotEmpty) map['en'] = english;
  if (chinese.isNotEmpty) map['zh'] = chinese;
  return map;
}
