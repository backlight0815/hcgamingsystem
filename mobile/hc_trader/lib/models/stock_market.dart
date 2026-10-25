import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';

enum StockDataFreshness { realTime, delayed, endOfDay, unavailable }

enum FinancialReportStatus {
  draft,
  underReview,
  scheduled,
  published,
  rejected,
  archived;

  String get storageValue => switch (this) {
    FinancialReportStatus.underReview => 'under_review',
    _ => name,
  };

  static FinancialReportStatus fromValue(Object? value) {
    final normalized = '$value'.trim().toLowerCase().replaceAll('_', '');
    return FinancialReportStatus.values.firstWhere(
      (status) => status.name.toLowerCase() == normalized,
      orElse: () => FinancialReportStatus.draft,
    );
  }
}

class StockQuote {
  const StockQuote({
    required this.symbol,
    required this.price,
    required this.change,
    required this.changePercent,
    required this.currency,
    required this.exchange,
    required this.asOf,
    required this.freshness,
    this.delayMinutes = 0,
    this.open,
    this.high,
    this.low,
    this.previousClose,
    this.volume,
    this.marketCapitalization,
    this.yearHigh,
    this.yearLow,
  });

  final String symbol;
  final double price;
  final double change;
  final double changePercent;
  final String currency;
  final String exchange;
  final DateTime asOf;
  final StockDataFreshness freshness;
  final int delayMinutes;
  final double? open;
  final double? high;
  final double? low;
  final double? previousClose;
  final int? volume;
  final double? marketCapitalization;
  final double? yearHigh;
  final double? yearLow;

  bool get isPositive => change >= 0;

  factory StockQuote.fromMap(Map<String, dynamic> data) => StockQuote(
    symbol: '${data['symbol'] ?? ''}'.trim().toUpperCase(),
    price: _double(data['price'] ?? data['latest_price']),
    change: _double(data['change']),
    changePercent: _double(data['change_percent'] ?? data['changePercent']),
    currency: '${data['currency'] ?? 'USD'}',
    exchange: '${data['exchange'] ?? ''}',
    asOf:
        _date(data['as_of'] ?? data['data_timestamp'] ?? data['updated_at']) ??
        DateTime.fromMillisecondsSinceEpoch(0),
    freshness: _freshness(data['freshness'] ?? data['data_type']),
    delayMinutes: _int(data['delay_minutes']),
    open: _nullableDouble(data['open']),
    high: _nullableDouble(data['high']),
    low: _nullableDouble(data['low']),
    previousClose: _nullableDouble(
      data['previous_close'] ?? data['previousClose'],
    ),
    volume: _nullableInt(data['volume']),
    marketCapitalization: _nullableDouble(
      data['market_capitalization'] ?? data['market_cap'],
    ),
    yearHigh: _nullableDouble(data['year_high'] ?? data['52_week_high']),
    yearLow: _nullableDouble(data['year_low'] ?? data['52_week_low']),
  );

  Map<String, Object?> toMap() => {
    'symbol': symbol,
    'price': price,
    'change': change,
    'change_percent': changePercent,
    'currency': currency,
    'exchange': exchange,
    'as_of': Timestamp.fromDate(asOf),
    'freshness': freshness.name,
    'delay_minutes': delayMinutes,
    if (open != null) 'open': open,
    if (high != null) 'high': high,
    if (low != null) 'low': low,
    if (previousClose != null) 'previous_close': previousClose,
    if (volume != null) 'volume': volume,
    if (marketCapitalization != null)
      'market_capitalization': marketCapitalization,
    if (yearHigh != null) 'year_high': yearHigh,
    if (yearLow != null) 'year_low': yearLow,
  };
}

class CompanyProfile {
  const CompanyProfile({
    required this.symbol,
    required this.companyName,
    this.description = '',
    this.sector = '',
    this.industry = '',
    this.exchange = '',
    this.currency = 'USD',
    this.website = '',
    this.logoUrl = '',
  });

  final String symbol;
  final String companyName;
  final String description;
  final String sector;
  final String industry;
  final String exchange;
  final String currency;
  final String website;
  final String logoUrl;

  factory CompanyProfile.fromMap(Map<String, dynamic> data) => CompanyProfile(
    symbol: '${data['symbol'] ?? ''}'.trim().toUpperCase(),
    companyName: '${data['company_name'] ?? data['name'] ?? ''}'.trim(),
    description: '${data['description'] ?? ''}'.trim(),
    sector: '${data['sector'] ?? ''}'.trim(),
    industry: '${data['industry'] ?? ''}'.trim(),
    exchange: '${data['exchange'] ?? ''}'.trim(),
    currency: '${data['currency'] ?? 'USD'}'.trim(),
    website: '${data['website'] ?? ''}'.trim(),
    logoUrl: '${data['logo_url'] ?? ''}'.trim(),
  );
}

class MarketNews {
  const MarketNews({
    required this.id,
    required this.title,
    required this.source,
    required this.publishedAt,
    this.summary = '',
    this.url = '',
    this.verified = false,
  });

  final String id;
  final String title;
  final String summary;
  final String source;
  final String url;
  final DateTime publishedAt;
  final bool verified;

  factory MarketNews.fromMap(Map<String, dynamic> data) => MarketNews(
    id: '${data['id'] ?? data['news_id'] ?? ''}',
    title: '${data['title'] ?? ''}'.trim(),
    summary: '${data['summary'] ?? ''}'.trim(),
    source: '${data['source'] ?? ''}'.trim(),
    url: '${data['url'] ?? ''}'.trim(),
    publishedAt:
        _date(data['published_at'] ?? data['publishedAt']) ?? DateTime(1970),
    verified: data['verified'] == true || data['is_verified'] == true,
  );
}

class FinancialCalendar {
  const FinancialCalendar({
    this.nextEarningsAt,
    this.exDividendAt,
    this.dividendPerShare,
    this.earningsEstimate,
  });

  final DateTime? nextEarningsAt;
  final DateTime? exDividendAt;
  final double? dividendPerShare;
  final double? earningsEstimate;

  factory FinancialCalendar.fromMap(Map<String, dynamic> data) =>
      FinancialCalendar(
        nextEarningsAt: _date(data['next_earnings_at']),
        exDividendAt: _date(data['ex_dividend_at']),
        dividendPerShare: _nullableDouble(data['dividend_per_share']),
        earningsEstimate: _nullableDouble(data['earnings_estimate']),
      );
}

class StockPricePoint {
  const StockPricePoint(this.at, this.close);

  final DateTime at;
  final double close;

  factory StockPricePoint.fromMap(Map<String, dynamic> data) => StockPricePoint(
    _date(data['at'] ?? data['timestamp']) ?? DateTime(1970),
    _double(data['close'] ?? data['price']),
  );
}

class StockMarketSnapshot {
  const StockMarketSnapshot({
    required this.quote,
    required this.profile,
    this.news = const [],
    this.calendar,
    this.history = const [],
  });

  final StockQuote quote;
  final CompanyProfile profile;
  final List<MarketNews> news;
  final FinancialCalendar? calendar;
  final List<StockPricePoint> history;

  factory StockMarketSnapshot.fromMap(Map<String, dynamic> data) {
    final quote = Map<String, dynamic>.from(
      data['quote'] is Map ? data['quote'] as Map : data,
    );
    final profile = Map<String, dynamic>.from(
      data['profile'] is Map ? data['profile'] as Map : data,
    );
    return StockMarketSnapshot(
      quote: StockQuote.fromMap(quote),
      profile: CompanyProfile.fromMap(profile),
      news: _maps(data['news']).map(MarketNews.fromMap).toList(growable: false),
      calendar: data['calendar'] is Map
          ? FinancialCalendar.fromMap(
              Map<String, dynamic>.from(data['calendar'] as Map),
            )
          : null,
      history: _maps(
        data['history'],
      ).map(StockPricePoint.fromMap).toList(growable: false),
    );
  }
}

class FinancialDailyReport {
  const FinancialDailyReport({
    required this.id,
    required this.symbol,
    required this.companyName,
    required this.reportDate,
    required this.status,
    required this.title,
    required this.summary,
    required this.content,
    required this.dataTimestamp,
    this.sourceReferences = const [],
    this.marketDataSnapshot = const {},
    this.publishedAt,
    this.generatedBy = '',
    this.reviewedBy = '',
    this.publishedBy = '',
    this.corrected = false,
    this.version = 1,
  });

  final String id;
  final String symbol;
  final String companyName;
  final DateTime reportDate;
  final FinancialReportStatus status;
  final Map<String, String> title;
  final Map<String, String> summary;
  final Map<String, String> content;
  final List<String> sourceReferences;
  final Map<String, dynamic> marketDataSnapshot;
  final DateTime dataTimestamp;
  final DateTime? publishedAt;
  final String generatedBy;
  final String reviewedBy;
  final String publishedBy;
  final bool corrected;
  final int version;

  String localizedTitle(String languageCode) =>
      getLocalizedText(title, languageCode, fallback: symbol);
  String localizedSummary(String languageCode) =>
      getLocalizedText(summary, languageCode);
  String localizedContent(String languageCode) =>
      getLocalizedText(content, languageCode);

  List<String> validateForPublish() => <String>[
    if (symbol.trim().isEmpty) 'A stock symbol is required.',
    if (title.values.every((value) => value.trim().isEmpty))
      'A report title is required in English or Chinese.',
    if (content.values.every((value) => value.trim().isEmpty))
      'Report content is required in English or Chinese.',
    if (sourceReferences.isEmpty) 'At least one source reference is required.',
  ];

  factory FinancialDailyReport.fromMap(String id, Map<String, dynamic> data) =>
      FinancialDailyReport(
        id: id,
        symbol: '${data['symbol'] ?? ''}'.trim().toUpperCase(),
        companyName: '${data['company_name'] ?? data['companyName'] ?? ''}'
            .trim(),
        reportDate:
            _date(data['report_date'] ?? data['reportDate']) ?? DateTime(1970),
        status: FinancialReportStatus.fromValue(
          data['report_status'] ?? data['reportStatus'] ?? data['status'],
        ),
        title: _localized(data, 'title'),
        summary: _localized(data, 'summary'),
        content: _localized(data, 'content'),
        sourceReferences: _strings(
          data['source_references'] ?? data['sourceReferences'],
        ),
        marketDataSnapshot: data['market_data_snapshot'] is Map
            ? Map<String, dynamic>.from(data['market_data_snapshot'] as Map)
            : const {},
        dataTimestamp:
            _date(data['data_timestamp'] ?? data['dataTimestamp']) ??
            DateTime(1970),
        publishedAt: _date(data['published_at'] ?? data['publishedAt']),
        generatedBy: '${data['generated_by'] ?? ''}',
        reviewedBy: '${data['reviewed_by'] ?? ''}',
        publishedBy: '${data['published_by'] ?? ''}',
        corrected: data['corrected'] == true,
        version: _int(data['version'] ?? 1),
      );
}

class StockSearchResult {
  const StockSearchResult({
    required this.symbol,
    required this.companyName,
    required this.exchange,
    this.currency = 'USD',
    this.latestPrice,
    this.changePercent,
    this.freshness = StockDataFreshness.unavailable,
  });

  final String symbol;
  final String companyName;
  final String exchange;
  final String currency;
  final double? latestPrice;
  final double? changePercent;
  final StockDataFreshness freshness;

  factory StockSearchResult.fromMap(Map<String, dynamic> data) =>
      StockSearchResult(
        symbol: '${data['symbol'] ?? ''}'.trim().toUpperCase(),
        companyName: '${data['company_name'] ?? data['name'] ?? ''}'.trim(),
        exchange: '${data['exchange'] ?? ''}'.trim(),
        currency: '${data['currency'] ?? 'USD'}'.trim(),
        latestPrice: _nullableDouble(data['latest_price'] ?? data['price']),
        changePercent: _nullableDouble(
          data['change_percent'] ?? data['changePercent'],
        ),
        freshness: _freshness(data['freshness'] ?? data['data_type']),
      );
}

class StockWatchlistItem {
  const StockWatchlistItem({
    required this.symbol,
    required this.companyName,
    required this.position,
    this.dailyReportsEnabled = true,
    this.companyAnnouncementsEnabled = true,
    this.earningsEnabled = true,
  });

  final String symbol;
  final String companyName;
  final int position;
  final bool dailyReportsEnabled;
  final bool companyAnnouncementsEnabled;
  final bool earningsEnabled;

  factory StockWatchlistItem.fromMap(
    String symbol,
    Map<String, dynamic> data,
  ) => StockWatchlistItem(
    symbol: symbol.toUpperCase(),
    companyName: '${data['company_name'] ?? ''}'.trim(),
    position: _int(data['position'] ?? data['sort_order']),
    dailyReportsEnabled: data['daily_reports_enabled'] != false,
    companyAnnouncementsEnabled: data['company_announcements_enabled'] != false,
    earningsEnabled: data['earnings_enabled'] != false,
  );
}

StockDataFreshness _freshness(Object? value) {
  final normalized = '$value'.trim().toLowerCase().replaceAll(
    RegExp(r'[_ -]'),
    '',
  );
  return switch (normalized) {
    'realtime' => StockDataFreshness.realTime,
    'delayed' => StockDataFreshness.delayed,
    'endofday' || 'eod' => StockDataFreshness.endOfDay,
    _ => StockDataFreshness.unavailable,
  };
}

Map<String, String> _localized(Map<String, dynamic> data, String key) {
  final raw = data['${key}_i18n'];
  if (raw is Map) {
    return {
      for (final code in const ['en', 'zh'])
        if ('${raw[code] ?? ''}'.trim().isNotEmpty) code: '${raw[code]}'.trim(),
    };
  }
  return {
    if ('${data['${key}_en'] ?? data[key] ?? ''}'.trim().isNotEmpty)
      'en': '${data['${key}_en'] ?? data[key]}'.trim(),
    if ('${data['${key}_zh'] ?? ''}'.trim().isNotEmpty)
      'zh': '${data['${key}_zh']}'.trim(),
  };
}

List<Map<String, dynamic>> _maps(Object? value) => value is Iterable
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false)
    : const [];

List<String> _strings(Object? value) => value is Iterable
    ? value
          .map((item) => '$item'.trim())
          .where((item) => item.isNotEmpty)
          .toList(growable: false)
    : const [];

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
double? _nullableDouble(Object? value) => value == null
    ? null
    : value is num
    ? value.toDouble()
    : double.tryParse('$value');
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
int? _nullableInt(Object? value) => value == null
    ? null
    : value is num
    ? value.toInt()
    : int.tryParse('$value');
DateTime? _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  if (value is num) return DateTime.fromMillisecondsSinceEpoch(value.toInt());
  return value == null ? null : DateTime.tryParse('$value');
}
