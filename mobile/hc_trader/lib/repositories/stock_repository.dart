import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:cloud_functions/cloud_functions.dart';
import 'package:firebase_auth/firebase_auth.dart';

import '../models/stock_market.dart';

const _stockClientVersion = String.fromEnvironment(
  'APP_VERSION',
  defaultValue: '1.7.3',
);

class StockDataException implements Exception {
  const StockDataException(this.message, {this.code = 'stock-data-error'});

  final String message;
  final String code;

  @override
  String toString() => message;
}

abstract class StockMarketDataProvider {
  Future<List<StockSearchResult>> searchStocks(String query);
  Future<StockQuote> getQuote(String symbol);
  Future<CompanyProfile> getCompanyProfile(String symbol);
  Future<List<MarketNews>> getCompanyNews(String symbol);
  Future<FinancialCalendar?> getFinancialCalendar(String symbol);
}

class FirebaseStockMarketDataProvider implements StockMarketDataProvider {
  FirebaseStockMarketDataProvider({FirebaseFunctions? functions})
    : _functions =
          functions ?? FirebaseFunctions.instanceFor(region: 'us-central1');

  final FirebaseFunctions _functions;
  final Map<String, _CachedSnapshot> _cache = {};
  final Map<String, Future<StockMarketSnapshot>> _inFlight = {};
  static const _cacheDuration = Duration(minutes: 2);

  @override
  Future<List<StockSearchResult>> searchStocks(String query) async {
    try {
      final result = await _functions.httpsCallable('searchStocks').call({
        'query': query.trim(),
        'limit': 30,
        'app_version': _stockClientVersion,
      });
      final data = _map(result.data);
      final rows = data['items'];
      if (rows is! Iterable) return const [];
      return rows
          .whereType<Map>()
          .map(
            (row) => StockSearchResult.fromMap(Map<String, dynamic>.from(row)),
          )
          .where((item) => item.symbol.isNotEmpty)
          .toList(growable: false);
    } on FirebaseFunctionsException catch (error) {
      throw StockDataException(
        _friendlyFunctionMessage(error),
        code: error.code,
      );
    }
  }

  Future<StockMarketSnapshot> getSnapshot(
    String rawSymbol, {
    bool forceRefresh = false,
  }) async {
    final symbol = _symbol(rawSymbol);
    final cached = _cache[symbol];
    if (!forceRefresh &&
        cached != null &&
        DateTime.now().difference(cached.cachedAt) < _cacheDuration) {
      return cached.snapshot;
    }
    final existing = _inFlight[symbol];
    if (existing != null) return existing;
    final future = _loadSnapshot(symbol, forceRefresh: forceRefresh);
    _inFlight[symbol] = future;
    try {
      final snapshot = await future;
      _cache[symbol] = _CachedSnapshot(snapshot, DateTime.now());
      return snapshot;
    } finally {
      _inFlight.remove(symbol);
    }
  }

  Future<StockMarketSnapshot> _loadSnapshot(
    String symbol, {
    required bool forceRefresh,
  }) async {
    try {
      final result = await _functions.httpsCallable('getStockSnapshot').call({
        'symbol': symbol,
        'force_refresh': forceRefresh,
        'app_version': _stockClientVersion,
      });
      return StockMarketSnapshot.fromMap(_map(result.data));
    } on FirebaseFunctionsException catch (error) {
      throw StockDataException(
        _friendlyFunctionMessage(error),
        code: error.code,
      );
    }
  }

  @override
  Future<StockQuote> getQuote(String symbol) async =>
      (await getSnapshot(symbol)).quote;

  @override
  Future<CompanyProfile> getCompanyProfile(String symbol) async =>
      (await getSnapshot(symbol)).profile;

  @override
  Future<List<MarketNews>> getCompanyNews(String symbol) async =>
      (await getSnapshot(symbol)).news;

  @override
  Future<FinancialCalendar?> getFinancialCalendar(String symbol) async =>
      (await getSnapshot(symbol)).calendar;
}

class StockRepository {
  StockRepository({
    required this.firebaseEnabled,
    StockMarketDataProvider? provider,
    FirebaseFirestore? firestore,
    FirebaseAuth? auth,
  }) : provider = provider ?? FirebaseStockMarketDataProvider(),
       _firestore = firestore ?? FirebaseFirestore.instance,
       _auth = auth ?? FirebaseAuth.instance;

  final bool firebaseEnabled;
  final StockMarketDataProvider provider;
  final FirebaseFirestore _firestore;
  final FirebaseAuth _auth;

  Future<List<StockSearchResult>> search(String query) {
    _requireFirebase();
    return provider.searchStocks(query);
  }

  Future<StockMarketSnapshot> loadSnapshot(
    String symbol, {
    bool forceRefresh = false,
  }) async {
    _requireFirebase();
    final currentProvider = provider;
    if (currentProvider is FirebaseStockMarketDataProvider) {
      return currentProvider.getSnapshot(symbol, forceRefresh: forceRefresh);
    }
    final values = await Future.wait<Object?>([
      currentProvider.getQuote(symbol),
      currentProvider.getCompanyProfile(symbol),
      currentProvider.getCompanyNews(symbol),
      currentProvider.getFinancialCalendar(symbol),
    ]);
    return StockMarketSnapshot(
      quote: values[0] as StockQuote,
      profile: values[1] as CompanyProfile,
      news: values[2] as List<MarketNews>,
      calendar: values[3] as FinancialCalendar?,
    );
  }

  Stream<List<StockWatchlistItem>> watchlist(String userId) {
    if (!firebaseEnabled) return Stream.value(const []);
    return _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .orderBy('position')
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => StockWatchlistItem.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<bool> isOnWatchlist(String userId, String rawSymbol) {
    if (!firebaseEnabled) return Stream.value(false);
    return _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .doc(_symbol(rawSymbol))
        .snapshots()
        .map((document) => document.exists);
  }

  Future<void> addToWatchlist({
    required String userId,
    required String symbol,
    required String companyName,
  }) async {
    _requireOwner(userId);
    final existing = await _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .get();
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .doc(_symbol(symbol))
        .set({
          'symbol': _symbol(symbol),
          'company_name': companyName.trim(),
          'position': existing.size,
          'daily_reports_enabled': true,
          'company_announcements_enabled': true,
          'earnings_enabled': true,
          'created_at': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
  }

  Future<void> removeFromWatchlist({
    required String userId,
    required String symbol,
  }) async {
    _requireOwner(userId);
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .doc(_symbol(symbol))
        .delete();
  }

  Future<void> updateWatchlistPreferences({
    required String userId,
    required String symbol,
    required bool dailyReports,
    required bool companyAnnouncements,
    required bool earnings,
  }) async {
    _requireOwner(userId);
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('watchlist')
        .doc(_symbol(symbol))
        .update({
          'daily_reports_enabled': dailyReports,
          'company_announcements_enabled': companyAnnouncements,
          'earnings_enabled': earnings,
          'updated_at': FieldValue.serverTimestamp(),
        });
  }

  Future<void> reorderWatchlist({
    required String userId,
    required List<String> symbols,
  }) async {
    _requireOwner(userId);
    final batch = _firestore.batch();
    for (var index = 0; index < symbols.length; index++) {
      final reference = _firestore
          .collection('users')
          .doc(userId)
          .collection('watchlist')
          .doc(_symbol(symbols[index]));
      batch.update(reference, {
        'position': index,
        'updated_at': FieldValue.serverTimestamp(),
      });
    }
    await batch.commit();
  }

  Stream<List<FinancialDailyReport>> publishedReports(
    String rawSymbol, {
    int limit = 30,
  }) {
    if (!firebaseEnabled) return Stream.value(const []);
    final symbol = _symbol(rawSymbol);
    return _firestore
        .collection('stocks')
        .doc(symbol)
        .collection('reports')
        .where('report_status', isEqualTo: 'published')
        .orderBy('report_date', descending: true)
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => FinancialDailyReport.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<List<FinancialDailyReport>> latestPublishedReports({int limit = 30}) {
    if (!firebaseEnabled) return Stream.value(const []);
    return _firestore
        .collectionGroup('reports')
        .where('report_status', isEqualTo: 'published')
        .orderBy('published_at', descending: true)
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => FinancialDailyReport.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<List<FinancialDailyReport>> adminReports({
    FinancialReportStatus? status,
    int limit = 100,
  }) {
    if (!firebaseEnabled) return Stream.value(const []);
    Query<Map<String, dynamic>> query = _firestore.collectionGroup('reports');
    if (status != null) {
      query = query.where('report_status', isEqualTo: status.storageValue);
    }
    return query
        .orderBy('updated_at', descending: true)
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => FinancialDailyReport.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Future<void> saveReportDraft({
    required String symbol,
    required String companyName,
    required DateTime reportDate,
    required String titleEn,
    required String titleZh,
    required String summaryEn,
    required String summaryZh,
    required String contentEn,
    required String contentZh,
    required List<String> sourceReferences,
    String? reportId,
    int? expectedVersion,
  }) async {
    _requireFirebase();
    if (titleEn.trim().isEmpty && titleZh.trim().isEmpty) {
      throw const StockDataException(
        'Add a report title in English or Chinese.',
        code: 'invalid-argument',
      );
    }
    if (contentEn.trim().isEmpty && contentZh.trim().isEmpty) {
      throw const StockDataException(
        'Add report content in English or Chinese.',
        code: 'invalid-argument',
      );
    }
    try {
      await FirebaseFunctions.instanceFor(
        region: 'us-central1',
      ).httpsCallable('saveFinancialDailyReportDraft').call({
        'symbol': _symbol(symbol),
        'company_name': companyName.trim(),
        'report_date': reportDate.toUtc().toIso8601String(),
        'title_en': titleEn.trim(),
        'title_zh': titleZh.trim(),
        'summary_en': summaryEn.trim(),
        'summary_zh': summaryZh.trim(),
        'content_en': contentEn.trim(),
        'content_zh': contentZh.trim(),
        'source_references': sourceReferences
            .map((source) => source.trim())
            .where((source) => source.isNotEmpty)
            .toList(),
        'report_id': ?reportId,
        'expected_version': ?expectedVersion,
        'app_version': _stockClientVersion,
      });
    } on FirebaseFunctionsException catch (error) {
      throw StockDataException(
        _friendlyFunctionMessage(error),
        code: error.code,
      );
    }
  }

  Future<void> reviewReport({
    required String symbol,
    required String reportId,
    required FinancialReportStatus status,
    int? expectedVersion,
    String note = '',
  }) async {
    _requireFirebase();
    try {
      await FirebaseFunctions.instanceFor(
        region: 'us-central1',
      ).httpsCallable('reviewFinancialDailyReport').call({
        'symbol': _symbol(symbol),
        'report_id': reportId,
        'status': status.storageValue,
        'expected_version': ?expectedVersion,
        'review_note': note.trim(),
        'app_version': _stockClientVersion,
      });
    } on FirebaseFunctionsException catch (error) {
      throw StockDataException(
        _friendlyFunctionMessage(error),
        code: error.code,
      );
    }
  }

  void _requireFirebase() {
    if (!firebaseEnabled) {
      throw const StockDataException(
        'Stock information is temporarily unavailable.',
        code: 'unavailable',
      );
    }
  }

  void _requireOwner(String userId) {
    _requireFirebase();
    if (_auth.currentUser?.uid != userId) {
      throw const StockDataException(
        'You can change only your own watchlist.',
        code: 'permission-denied',
      );
    }
  }
}

class _CachedSnapshot {
  const _CachedSnapshot(this.snapshot, this.cachedAt);

  final StockMarketSnapshot snapshot;
  final DateTime cachedAt;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const <String, dynamic>{};

String _symbol(String value) {
  final normalized = value.trim().toUpperCase();
  if (!RegExp(r'^[A-Z0-9.\-]{1,15}$').hasMatch(normalized)) {
    throw const StockDataException(
      'Enter a valid stock symbol.',
      code: 'invalid-symbol',
    );
  }
  return normalized;
}

String _friendlyFunctionMessage(
  FirebaseFunctionsException error,
) => switch (error.code) {
  'permission-denied' => 'You do not have permission for this stock action.',
  'resource-exhausted' =>
    'Market data is busy. Please wait a moment and try again.',
  'not-found' => 'No supported market data was found for this symbol.',
  'failed-precondition' =>
    error.message ?? 'This stock action is not available yet.',
  'unavailable' =>
    'Market data is temporarily unavailable. Cached information may be shown.',
  _ => error.message ?? 'Unable to load stock information.',
};
