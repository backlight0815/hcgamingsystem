import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:excel/excel.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_functions/cloud_functions.dart';
import 'package:firebase_storage/firebase_storage.dart';
import 'package:flutter/foundation.dart';

import '../core/config/mt5_sync_config.dart';
import '../core/network/api_client.dart';
import '../core/localization/localized_value.dart';
import '../core/security/app_role_permissions.dart';
import '../core/security/app_roles.dart';
import '../core/security/publishing_authorization.dart';
import '../core/storage/local_app_storage.dart';
import '../models/access_status.dart';
import '../models/admin_user_management.dart';
import '../models/admin_dashboard_models.dart';
import '../models/announcement.dart';
import '../models/app_user.dart';
import '../models/capital_transaction.dart';
import '../models/final_exam_mobile.dart';
import '../models/firestore_helpers.dart';
import '../models/knowledge_centre_item.dart';
import '../models/knowledge_centre_category.dart';
import '../models/journal_attachment.dart';
import '../models/market_analysis_item.dart';
import '../models/market_analyst_models.dart';
import '../models/mt5_sync_models.dart';
import '../models/prop_firm_challenge.dart';
import '../models/performance_rating.dart';
import '../models/trader_account_profile.dart';
import '../models/trading_blog_post.dart';
import '../models/trading_account.dart';
import '../models/trading_data_source.dart';
import '../models/trading_exam_models.dart';
import '../models/trading_journal_entry.dart';
import '../models/trading_pair.dart';
import '../models/trading_position_application_mobile.dart';
import '../models/trading_recording_class.dart';
import '../models/trading_resource_item.dart';
import '../models/trading_signal_item.dart';
import '../models/trading_signal_lifecycle.dart';
import '../models/standalone_models.dart';
import '../services/trading_report_service.dart';
import '../services/trading_data_source_service.dart';
import '../services/trading_blog_api_service.dart';
import '../services/mt5_trade_eligibility_service.dart';
import '../services/firebase_callable_service.dart';
import '../services/daily_quiz_upload_service.dart';
import '../services/trading_signal_api_service.dart';
import '../services/trading_journal_sync_service.dart';

class Mt5MainAccountException implements Exception {
  const Mt5MainAccountException(this.code, this.message, {this.cause});

  final String code;
  final String message;
  final Object? cause;

  @override
  String toString() => message;
}

class _ReplayLatestStream<T> extends Stream<T> {
  const _ReplayLatestStream({
    required this.source,
    required this.hasLatest,
    required this.latest,
  });

  final Stream<T> source;
  final bool Function() hasLatest;
  final T Function() latest;

  @override
  StreamSubscription<T> listen(
    void Function(T event)? onData, {
    Function? onError,
    void Function()? onDone,
    bool? cancelOnError,
  }) {
    late final StreamSubscription<T> sourceSubscription;
    late final StreamController<T> controller;
    controller = StreamController<T>(
      sync: true,
      onListen: () {
        if (hasLatest()) controller.add(latest());
        sourceSubscription = source.listen(
          controller.add,
          onError: controller.addError,
          onDone: controller.close,
          cancelOnError: cancelOnError,
        );
      },
      onPause: () => sourceSubscription.pause(),
      onResume: () => sourceSubscription.resume(),
      onCancel: () => sourceSubscription.cancel(),
    );
    return controller.stream.listen(
      onData,
      onError: onError,
      onDone: onDone,
      cancelOnError: cancelOnError,
    );
  }
}

class _ReadAttempt<T> {
  const _ReadAttempt({this.value, this.error});

  final T? value;
  final Object? error;
}

Future<_ReadAttempt<T>> _attemptRead<T>(Future<T> Function() read) async {
  try {
    return _ReadAttempt<T>(value: await read());
  } catch (error) {
    return _ReadAttempt<T>(error: error);
  }
}

class TradingRepository {
  TradingRepository({
    required this.firebaseEnabled,
    this.apiClient,
    this.storage,
    FirebaseFirestore? firestore,
    FirebaseCallableService? callableService,
  }) : _journalFirestore = firestore,
       _callableService = callableService ?? FirebaseCallableService() {
    _signalApi = apiClient == null ? null : TradingSignalApiService(apiClient!);
    _blogApi = apiClient == null ? null : TradingBlogApiService(apiClient!);
    if (_signalApi != null) {
      _restoreSignalApiCache();
    }
    if (_blogApi != null) {
      _restoreBlogApiCache();
    }
    if (_usingApi) {
      _restoreReferenceCache();
      scheduleMicrotask(_refreshTradingData);
    } else if (!firebaseEnabled) {
      _localPairs = TradingPair.defaults;
      _localBlogs = _demoBlogs;
      _localAnalyses = _demoAnalyses;
      _localSignals = _demoSignals;
      _localResources = _demoResources;
      _localExamQuestions = _demoExamQuestions;
      _localRecordings = _demoRecordings;
      _localKnowledge = _demoKnowledge;
      scheduleMicrotask(_emitPairs);
    }
  }

  final bool firebaseEnabled;
  static const _mt5Eligibility = Mt5TradeEligibilityService();
  final ApiClient? apiClient;
  final LocalAppStorage? storage;
  final FirebaseFirestore? _journalFirestore;
  final FirebaseCallableService _callableService;
  late final TradingSignalApiService? _signalApi;
  late final TradingBlogApiService? _blogApi;
  TradingJournalSyncService? _journalSync;
  TradingJournalSyncService? get _journalSyncService {
    if (!firebaseEnabled || apiClient == null || storage == null) return null;
    return _journalSync ??= TradingJournalSyncService(
      client: apiClient!,
      firestore: _firestore,
      storage: storage!,
    );
  }

  final Map<String, DateTime> _lastJournalSyncAttempt = {};
  Future<void>? _signalApiRefresh;
  Future<void>? _blogApiRefresh;
  bool _blogApiHasLoaded = false;
  bool _blogUsingCachedFallback = false;
  String? _blogSyncErrorMessage;
  DateTime? _blogLastSyncedAt;
  var _blogCategoryLabels = <String, String>{};
  var _blogCategoryCounts = <String, int>{};
  static const _blogCacheFreshFor = Duration(minutes: 15);
  StreamSubscription<Map<String, dynamic>>? _signalApiSubscription;
  StreamSubscription<Map<String, dynamic>>? _blogApiSubscription;
  final Map<String, Future<Mt5SyncJob?>> _mt5RefreshInFlight = {};
  final Map<String, Future<void>> _mainMt5SelectionInFlight = {};
  final Map<String, String> _mainMt5SelectionTarget = {};
  final Map<String, Stream<Mt5Account?>> _mainMt5AccountStreams = {};
  final Map<String, DocumentSnapshot<Map<String, dynamic>>>
  _adminUserPageCursors = {};
  static const _functionsRegion = 'us-central1';
  static const _runtimeAppVersion = String.fromEnvironment(
    'APP_VERSION',
    defaultValue: '1.7.3',
  );
  static const _adminResourceCollections = <String>{
    'contents',
    'trading_resources',
    'trading_recordings',
    'knowledge_centres',
    'trading_blogs',
    'trading_news',
    'trading_signals',
    'market_analyses',
    'trading_exam_questions',
    'final_exam_questions',
    'prop_firm_models',
  };
  static const _adminBilingualFields = <String, Set<String>>{
    'contents': {'title', 'summary', 'content'},
    'trading_resources': {'title', 'description'},
    'trading_recordings': {'title', 'description'},
    'knowledge_centres': {'title', 'description'},
    'trading_blogs': {'title', 'content'},
    'trading_news': {'title', 'content', 'category'},
    'trading_signals': {'title', 'analysis'},
    'market_analyses': {
      'title',
      'market_overview',
      'trend_structure',
      'key_zones',
      'analyst_view',
      'trading_plan',
    },
    'trading_exam_questions': {
      'question_text',
      'category',
      'section',
      'chart_description',
      'option_a',
      'option_b',
      'option_c',
      'option_d',
      'explanation',
    },
    'final_exam_questions': {
      'question_text',
      'category',
      'scenario_text',
      'option_a',
      'option_b',
      'option_c',
      'option_d',
      'explanation',
    },
    'prop_firm_models': {
      'prop_firm_name',
      'name',
      'lot_size_rules',
      'consistency_rule',
    },
  };
  // Core repository CRUD stays in one data mode. Dedicated cross-platform
  // signal and blog transports intentionally use Laravel even in Firebase mode.
  bool get _usingApi => !firebaseEnabled && apiClient != null;
  Future<void>? _tradingRefresh;
  DateTime? _lastTradingRefresh;
  Future<void>? _contentRefresh;
  Future<void>? _examRefresh;
  String? _currentExamAttemptId;
  final _pairsController = StreamController<List<TradingPair>>.broadcast();
  final _journalControllers =
      <String, StreamController<List<TradingJournalEntry>>>{};
  final _capitalControllers =
      <String, StreamController<List<CapitalTransaction>>>{};
  final _accountControllers =
      <String, StreamController<TraderAccountProfile>>{};
  final _tradingAccountControllers =
      <String, StreamController<List<TradingAccount>>>{};
  final _displayModeControllers =
      <String, StreamController<TradingDataDisplayMode>>{};
  final _positionControllers =
      <String, StreamController<List<TradingPositionApplicationMobile>>>{};
  final _blogsController = StreamController<List<TradingBlogPost>>.broadcast();
  final _analysesController =
      StreamController<List<MarketAnalysisItem>>.broadcast();
  final _signalsController =
      StreamController<List<TradingSignalItem>>.broadcast();
  final _resourcesController =
      StreamController<List<TradingResourceItem>>.broadcast();
  final _examQuestionsController =
      StreamController<List<TradingExamQuestionMobile>>.broadcast();
  final _adminMutationController = StreamController<void>.broadcast();
  final _recordingsController =
      StreamController<List<TradingRecordingClassMobile>>.broadcast();
  final _knowledgeController =
      StreamController<List<KnowledgeCentreItemMobile>>.broadcast();
  final _examAttemptControllers =
      <String, StreamController<List<TradingExamAttemptMobile>>>{};
  final _localJournals = <String, List<TradingJournalEntry>>{};
  final _localCapitals = <String, List<CapitalTransaction>>{};
  final _localAccounts = <String, TraderAccountProfile>{};
  final _localTradingAccounts = <String, List<TradingAccount>>{};
  final _localPositions = <String, List<TradingPositionApplicationMobile>>{};
  var _localBlogs = <TradingBlogPost>[];
  var _localAnalyses = <MarketAnalysisItem>[];
  var _localSignals = <TradingSignalItem>[];
  var _localResources = <TradingResourceItem>[];
  var _localExamQuestions = <TradingExamQuestionMobile>[];
  var _localRecordings = <TradingRecordingClassMobile>[];
  var _localKnowledge = <KnowledgeCentreItemMobile>[];
  final _localExamAttempts = <String, List<TradingExamAttemptMobile>>{};
  var _localPairs = <TradingPair>[];
  final _restoredTradingUsers = <String>{};
  final _migratedTradingStrategyUsers = <String>{};
  final _migratedTradingPlanUsers = <String>{};

  FirebaseFirestore get _firestore =>
      _journalFirestore ?? FirebaseFirestore.instance;

  Future<void> refreshTradingData() {
    if (firebaseEnabled) return _firestore.waitForPendingWrites();
    return _refreshTradingData(force: true);
  }

  Future<void> synchronizeJournal(String uid, {bool force = false}) {
    final journalSync = _journalSyncService;
    if (journalSync == null) return Future<void>.value();
    final last = _lastJournalSyncAttempt[uid];
    if (!force &&
        last != null &&
        DateTime.now().difference(last) < const Duration(seconds: 30)) {
      return Future<void>.value();
    }
    _lastJournalSyncAttempt[uid] = DateTime.now();
    return journalSync.sync(uid);
  }

  Future<void> refreshTradingSignals() {
    if (_signalApi != null) return _refreshSignalsFromApi(force: true);
    if (firebaseEnabled) return _firestore.waitForPendingWrites();
    _emitSignals();
    return Future<void>.value();
  }

  Stream<TradingDataDisplayMode> tradingDataDisplayModeFor(String uid) {
    if (firebaseEnabled) {
      return _firestore.collection('users').doc(uid).snapshots().map((
        snapshot,
      ) {
        final data = snapshot.data() ?? const <String, dynamic>{};
        final mode = TradingDataDisplayMode.fromStorage(
          data['tradingDataDisplayMode'] ?? data['trading_data_display_mode'],
        );
        storage?.writeMap(uid, 'trading_data_display_mode', {
          'mode': mode.storageValue,
        });
        return mode;
      });
    }

    final controller = _displayModeControllers.putIfAbsent(
      uid,
      () => StreamController<TradingDataDisplayMode>.broadcast(),
    );
    scheduleMicrotask(() => _emitTradingDataDisplayMode(uid));
    return controller.stream;
  }

  Future<void> setTradingDataDisplayMode({
    required String uid,
    required TradingDataDisplayMode mode,
  }) async {
    await storage?.writeMap(uid, 'trading_data_display_mode', {
      'mode': mode.storageValue,
    });
    _emitTradingDataDisplayMode(uid);
    if (firebaseEnabled) {
      await _firestore.collection('users').doc(uid).set({
        'tradingDataDisplayMode': mode.storageValue,
        'trading_data_display_mode': mode.storageValue,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    }
  }

  Stream<List<TradingPair>> tradingPairsStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshTradingData);
      return _pairsController.stream;
    }
    if (firebaseEnabled) {
      return _firestore
          .collection('trading_pairs')
          .orderBy('symbol')
          .snapshots()
          .map((snapshot) {
            final pairs = snapshot.docs
                .map(TradingPair.fromFirestore)
                .toList(growable: false);
            return pairs.isEmpty ? TradingPair.defaults : pairs;
          });
    }

    scheduleMicrotask(_emitPairs);
    return _pairsController.stream;
  }

  Stream<List<TradingJournalEntry>> journalsFor(String uid) {
    if (_usingApi) {
      final controller = _journalControllers.putIfAbsent(
        uid,
        () => StreamController<List<TradingJournalEntry>>.broadcast(),
      );
      _restoreTradingCache(uid);
      scheduleMicrotask(_refreshTradingData);
      return controller.stream;
    }
    if (firebaseEnabled) {
      scheduleMicrotask(() => synchronizeJournal(uid).catchError((_) {}));
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_journals')
          .orderBy('close_date', descending: true)
          .snapshots(includeMetadataChanges: true)
          .map((snapshot) {
            if (!snapshot.metadata.isFromCache &&
                !snapshot.metadata.hasPendingWrites) {
              scheduleMicrotask(
                () => synchronizeJournal(uid).catchError((_) {}),
              );
            }
            return snapshot.docs
                .map(TradingJournalEntry.fromFirestore)
                .where((journal) => journal.type == 'trade')
                .toList(growable: false);
          });
    }

    final controller = _journalControllers.putIfAbsent(
      uid,
      () => StreamController<List<TradingJournalEntry>>.broadcast(),
    );
    scheduleMicrotask(() => _emitJournals(uid));
    return controller.stream;
  }

  Stream<List<Mt5Account>> mt5AccountsFor(String uid) {
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .orderBy('updated_at', descending: true)
          .snapshots()
          .map(
            (snapshot) => _sortMt5Accounts(
              snapshot.docs
                  .map(Mt5Account.fromFirestore)
                  .where((account) => account.isActive),
            ),
          );
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_accounts')
            .map(Mt5Account.fromJson)
            .where((account) => account.isActive)
            .toList(growable: false) ??
        const <Mt5Account>[];
    return Stream.value(
      cached.isEmpty && Mt5SyncConfig.mockDataEnabled
          ? _mockMt5Accounts(uid)
          : _sortMt5Accounts(cached),
    );
  }

  Stream<Mt5Account?> mainMt5AccountFor(String uid) {
    if (firebaseEnabled) {
      return _mainMt5AccountStreams.putIfAbsent(
        uid,
        () => _createMainMt5AccountStream(uid),
      );
    }

    return mt5AccountsFor(uid).map(
      (accounts) =>
          _resolveMainMt5Account(accounts, _localMainMt5Key(accounts)),
    );
  }

  Stream<Mt5Account?> _createMainMt5AccountStream(String uid) {
    StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>?
    userSubscription;
    StreamSubscription<List<Mt5Account>>? accountSubscription;
    late final StreamController<Mt5Account?> controller;
    late final Stream<Mt5Account?> stream;
    String mainKey = '';
    var accounts = const <Mt5Account>[];
    var userLoaded = false;
    var accountsLoaded = false;
    var listening = false;
    var hasLatest = false;
    Mt5Account? latest;

    void emit() {
      if (userLoaded && accountsLoaded && !controller.isClosed) {
        latest = _resolveMainMt5Account(accounts, mainKey);
        hasLatest = true;
        controller.add(latest);
      }
    }

    void start() {
      if (listening) return;
      listening = true;
      userSubscription = _firestore
          .collection('users')
          .doc(uid)
          .snapshots()
          .listen((snapshot) {
            final data = snapshot.data() ?? const <String, dynamic>{};
            mainKey = _mainMt5AccountIdFrom(data);
            userLoaded = true;
            emit();
          }, onError: controller.addError);
      accountSubscription = mt5AccountsFor(uid).listen((items) {
        accounts = items;
        accountsLoaded = true;
        emit();
      }, onError: controller.addError);
    }

    Future<void> stop() async {
      listening = false;
      final oldUserSubscription = userSubscription;
      final oldAccountSubscription = accountSubscription;
      userSubscription = null;
      accountSubscription = null;
      await oldUserSubscription?.cancel();
      await oldAccountSubscription?.cancel();
      if (!listening && identical(_mainMt5AccountStreams[uid], stream)) {
        _mainMt5AccountStreams.remove(uid);
      }
    }

    controller = StreamController<Mt5Account?>.broadcast(
      onListen: start,
      onCancel: stop,
    );
    stream = _ReplayLatestStream<Mt5Account?>(
      source: controller.stream,
      hasLatest: () => hasLatest,
      latest: () => latest,
    );
    return stream;
  }

  Stream<Mt5SyncState?> mt5SyncStateForAccount({
    required String uid,
    required String accountKey,
  }) {
    if (accountKey.trim().isEmpty) return Stream.value(null);

    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey)
          .collection('sync_state')
          .doc('default')
          .snapshots()
          .map(
            (snapshot) =>
                snapshot.exists ? Mt5SyncState.fromFirestore(snapshot) : null,
          );
    }

    final cached = storage?.readMap(uid, 'mt5_sync_state_$accountKey');
    if (cached != null) return Stream.value(Mt5SyncState.fromJson(cached));
    return Stream.value(
      Mt5SyncConfig.mockDataEnabled ? _mockMt5SyncState(uid, accountKey) : null,
    );
  }

  Future<List<Mt5Account>> loadMt5Accounts(
    String uid, {
    bool forceServer = false,
  }) async {
    if (firebaseEnabled) {
      final snapshot = await _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .orderBy('updated_at', descending: true)
          .get(_serverGetOptions(forceServer));
      return _sortMt5Accounts(
        snapshot.docs
            .map(Mt5Account.fromFirestore)
            .where((account) => account.isActive),
      );
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_accounts')
            .map(Mt5Account.fromJson)
            .where((account) => account.isActive)
            .toList(growable: false) ??
        const <Mt5Account>[];
    return cached.isEmpty && Mt5SyncConfig.mockDataEnabled
        ? _mockMt5Accounts(uid)
        : _sortMt5Accounts(cached);
  }

  Future<Mt5Account?> loadMainMt5Account(
    String uid, {
    bool forceServer = false,
  }) async {
    final accounts = await loadMt5Accounts(uid, forceServer: forceServer);
    if (!firebaseEnabled) {
      return _resolveMainMt5Account(accounts, _localMainMt5Key(accounts));
    }
    final user = await _firestore
        .collection('users')
        .doc(uid)
        .get(_serverGetOptions(forceServer));
    final data = user.data() ?? const <String, dynamic>{};
    final mainKey = _mainMt5AccountIdFrom(data);
    final resolved = _resolveMainMt5Account(accounts, mainKey);
    if (resolved == null && mainKey.isNotEmpty && _firebaseUid() == uid) {
      await _clearInvalidMainMt5AccountReference(
        uid: uid,
        expectedAccountId: mainKey,
      );
    }
    return resolved;
  }

  /// Resolves the authoritative `mainMt5AccountId` and validates it at the
  /// account document before report generation. The login is metadata only;
  /// it is never used as the report scope.
  Future<Mt5Account> validateMainMt5AccountForReport(String uid) async {
    final normalizedUid = uid.trim();
    if (normalizedUid.isEmpty) {
      throw const Mt5MainAccountException(
        'unauthenticated',
        'Your session has expired. Please sign in again.',
      );
    }
    if (!firebaseEnabled) {
      final account = await loadMainMt5Account(normalizedUid);
      if (account == null) {
        throw const Mt5MainAccountException(
          'main-account-required',
          'Select a main MT5 account before generating an MT5 report.',
        );
      }
      if (account.userId != normalizedUid || !account.isActive) {
        throw const Mt5MainAccountException(
          'ownership-mismatch',
          'You do not have permission to access this MT5 account.',
        );
      }
      return account;
    }

    if (_firebaseUid() != normalizedUid) {
      throw const Mt5MainAccountException(
        'unauthenticated',
        'Your session has expired. Please sign in again.',
      );
    }
    final userRef = _firestore.collection('users').doc(normalizedUid);
    final userSnapshot = await userRef.get(
      const GetOptions(source: Source.server),
    );
    final mainAccountId = _mainMt5AccountIdFrom(
      userSnapshot.data() ?? const <String, dynamic>{},
    );
    if (mainAccountId.isEmpty) {
      throw const Mt5MainAccountException(
        'main-account-required',
        'Select a main MT5 account before generating an MT5 report.',
      );
    }
    final accountRef = userRef.collection('mt5_accounts').doc(mainAccountId);
    final accountSnapshot = await accountRef.get(
      const GetOptions(source: Source.server),
    );
    if (!accountSnapshot.exists) {
      throw const Mt5MainAccountException(
        'not-found',
        'The selected main MT5 account is no longer available.',
      );
    }
    final data = accountSnapshot.data()!;
    final ownerUid = _mt5OwnerUidFrom(data);
    if (ownerUid != normalizedUid) {
      throw const Mt5MainAccountException(
        'ownership-mismatch',
        'You do not have permission to access this MT5 account.',
      );
    }
    final storedAccountId = _mt5AccountIdFrom(data);
    if ((storedAccountId.isNotEmpty && storedAccountId != mainAccountId) ||
        !_isSelectableMt5Account(data)) {
      throw const Mt5MainAccountException(
        'failed-precondition',
        'The selected main MT5 account is no longer available.',
      );
    }
    final account = Mt5Account.fromFirestore(accountSnapshot);
    if (kDebugMode) {
      debugPrint(
        '[Trading Report] validated uid=$normalizedUid '
        'mainMt5AccountId=$mainAccountId login=${account.login} '
        'ownerUid=$ownerUid active=${account.isActive}',
      );
    }
    return account;
  }

  Future<void> _clearInvalidMainMt5AccountReference({
    required String uid,
    required String expectedAccountId,
  }) async {
    final userRef = _firestore.collection('users').doc(uid);
    await _firestore.runTransaction((transaction) async {
      final snapshot = await transaction.get(userRef);
      if (!snapshot.exists ||
          _mainMt5AccountIdFrom(snapshot.data()!) != expectedAccountId) {
        return;
      }
      transaction.set(userRef, {
        'mainMt5AccountId': FieldValue.delete(),
        'main_mt5_account_id': FieldValue.delete(),
        'mainMt5AccountKey': FieldValue.delete(),
        'main_mt5_account_key': FieldValue.delete(),
        'mainMt5AccountLogin': FieldValue.delete(),
        'main_mt5_account_login': FieldValue.delete(),
        'mainMt5AccountUpdatedAt': FieldValue.serverTimestamp(),
        'main_mt5_account_updated_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    });
    if (kDebugMode) {
      debugPrint(
        '[MT5 Main Account] cleared invalid reference uid=$uid '
        'previousMainAccountId=$expectedAccountId',
      );
    }
  }

  Future<void> setMainMt5Account({
    required String uid,
    required String accountKey,
  }) {
    final key = accountKey.trim();
    if (key.isEmpty) {
      throw const Mt5MainAccountException(
        'not-found',
        'The selected MT5 account no longer exists.',
      );
    }
    if (!firebaseEnabled) {
      return _setLocalMainMt5Account(uid: uid, accountKey: key);
    }

    final authenticatedUid = _firebaseUid();
    if (authenticatedUid != uid) {
      throw const Mt5MainAccountException(
        'ownership-mismatch',
        'This MT5 account does not belong to the current user.',
      );
    }
    final existing = _mainMt5SelectionInFlight[authenticatedUid];
    if (existing != null) {
      if (_mainMt5SelectionTarget[authenticatedUid] == key) return existing;
      throw const Mt5MainAccountException(
        'aborted',
        'A main-account update is already in progress. Please retry.',
      );
    }

    late final Future<void> operation;
    operation = _setMainMt5AccountOnce(uid: authenticatedUid, accountKey: key)
        .whenComplete(() {
          if (identical(
            _mainMt5SelectionInFlight[authenticatedUid],
            operation,
          )) {
            _mainMt5SelectionInFlight.remove(authenticatedUid);
            _mainMt5SelectionTarget.remove(authenticatedUid);
          }
        });
    _mainMt5SelectionTarget[authenticatedUid] = key;
    _mainMt5SelectionInFlight[authenticatedUid] = operation;
    return operation;
  }

  Future<void> _setMainMt5AccountOnce({
    required String uid,
    required String accountKey,
  }) async {
    final userRef = _firestore.collection('users').doc(uid);
    final accountCollection = userRef.collection('mt5_accounts');
    final selectedRef = accountCollection.doc(accountKey);
    String previousMainAccountId = '';
    String selectedOwnerUid = '';
    if (kDebugMode) {
      debugPrint(
        '[MT5 Main Account] request uid=$uid selectedAccountId=$accountKey '
        'readPath=${selectedRef.path} writePath=${userRef.path}',
      );
    }
    try {
      final changed = await _firestore.runTransaction<bool>((
        transaction,
      ) async {
        final userSnapshot = await transaction.get(userRef);
        if (!userSnapshot.exists) {
          throw const Mt5MainAccountException(
            'unauthenticated',
            'Your session has expired. Please sign in again.',
          );
        }
        final selectedSnapshot = await transaction.get(selectedRef);
        if (!selectedSnapshot.exists) {
          throw const Mt5MainAccountException(
            'not-found',
            'The selected MT5 account no longer exists.',
          );
        }

        final accountData = selectedSnapshot.data()!;
        selectedOwnerUid = _mt5OwnerUidFrom(accountData);
        if (selectedOwnerUid != uid) {
          throw const Mt5MainAccountException(
            'ownership-mismatch',
            'This MT5 account does not belong to the current user.',
          );
        }
        final storedAccountId = _mt5AccountIdFrom(accountData);
        if (storedAccountId.isNotEmpty && storedAccountId != accountKey) {
          throw const Mt5MainAccountException(
            'failed-precondition',
            'The selected MT5 account is no longer available.',
          );
        }
        if (!_isSelectableMt5Account(accountData)) {
          throw const Mt5MainAccountException(
            'failed-precondition',
            'The selected MT5 account is no longer available.',
          );
        }

        previousMainAccountId = _mainMt5AccountIdFrom(
          userSnapshot.data() ?? const <String, dynamic>{},
        );
        if (previousMainAccountId == accountKey) return false;

        if (previousMainAccountId.isNotEmpty) {
          final previousRef = accountCollection.doc(previousMainAccountId);
          final previousSnapshot = await transaction.get(previousRef);
          if (previousSnapshot.exists &&
              _mt5OwnerUidFrom(previousSnapshot.data()!) == uid) {
            transaction.set(previousRef, {
              'is_primary': false,
              'isPrimary': false,
              'updated_at': FieldValue.serverTimestamp(),
            }, SetOptions(merge: true));
          }
        }

        final login = _intValue(
          accountData['mt5_login'] ??
              accountData['login'] ??
              accountData['account_number'],
        );
        transaction.set(selectedRef, {
          'is_primary': true,
          'isPrimary': true,
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
        transaction.set(userRef, {
          'mainMt5AccountId': accountKey,
          'mainMt5AccountLogin': login,
          'mainMt5AccountUpdatedAt': FieldValue.serverTimestamp(),
          'main_mt5_account_id': accountKey,
          'main_mt5_account_login': login,
          'main_mt5_account_updated_at': FieldValue.serverTimestamp(),
          'main_mt5_account_key': accountKey,
          'mainMt5AccountKey': accountKey,
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
        return true;
      });

      if (kDebugMode) {
        debugPrint(
          '[MT5 Main Account] committed uid=$uid '
          'selectedAccountId=$accountKey selectedOwnerUid=$selectedOwnerUid '
          'previousMainAccountId=$previousMainAccountId changed=$changed',
        );
      }
      if (!changed) return;
      try {
        await _writeAudit(
          action: 'update',
          module: 'mt5_accounts',
          targetType: 'main_mt5_account',
          targetId: accountKey,
          oldValues: {'mainMt5AccountId': previousMainAccountId},
          newValues: {'mainMt5AccountId': accountKey},
        );
      } catch (error, stackTrace) {
        // Auditing is secondary to the already-confirmed authoritative write.
        if (kDebugMode) {
          debugPrint('[MT5 Main Account] audit write failed: $error');
          debugPrintStack(stackTrace: stackTrace);
        }
      }
    } on Mt5MainAccountException {
      rethrow;
    } on FirebaseException catch (error, stackTrace) {
      if (kDebugMode) {
        debugPrint(
          '[MT5 Main Account] failed uid=$uid selectedAccountId=$accountKey '
          'selectedOwnerUid=$selectedOwnerUid '
          'previousMainAccountId=$previousMainAccountId '
          'code=${error.code} message=${error.message ?? ''}',
        );
        debugPrintStack(stackTrace: stackTrace);
      }
      throw _mainMt5AccountFirebaseException(error);
    }
  }

  Future<void> _setLocalMainMt5Account({
    required String uid,
    required String accountKey,
  }) async {
    final accounts = storage?.readList(uid, 'mt5_accounts') ?? const [];
    final selected = accounts.where((item) {
      final itemKey = (item['account_key'] ?? item['accountKey'] ?? '')
          .toString();
      final owner =
          (item['ownerUid'] ?? item['user_id'] ?? item['userId'] ?? '')
              .toString();
      return itemKey == accountKey &&
          item['is_active'] != false &&
          item['isActive'] != false &&
          owner == uid;
    });
    if (selected.isEmpty) {
      throw const Mt5MainAccountException(
        'not-found',
        'The selected MT5 account no longer exists.',
      );
    }
    await storage?.writeList(uid, 'mt5_accounts', [
      for (final item in accounts)
        {
          ...item,
          'is_primary':
              (item['account_key'] ?? item['accountKey'] ?? '') == accountKey,
          'isPrimary':
              (item['account_key'] ?? item['accountKey'] ?? '') == accountKey,
        },
    ]);
  }

  Future<Mt5SyncState?> loadMt5SyncState({
    required String uid,
    required String accountKey,
    bool forceServer = false,
  }) async {
    if (accountKey.trim().isEmpty) return null;

    if (firebaseEnabled) {
      final snapshot = await _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey)
          .collection('sync_state')
          .doc('default')
          .get(_serverGetOptions(forceServer));
      return snapshot.exists ? Mt5SyncState.fromFirestore(snapshot) : null;
    }

    final cached = storage?.readMap(uid, 'mt5_sync_state_$accountKey');
    if (cached != null) return Mt5SyncState.fromJson(cached);
    return Mt5SyncConfig.mockDataEnabled
        ? _mockMt5SyncState(uid, accountKey)
        : null;
  }

  GetOptions? _serverGetOptions(bool forceServer) {
    return forceServer ? const GetOptions(source: Source.server) : null;
  }

  Future<List<TradingStrategyRecord>> loadTradingStrategyRecords(
    String uid, {
    bool forceServer = false,
  }) async {
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_strategies');
      final snapshot = await collection
          .orderBy('updated_at', descending: true)
          .get(_serverGetOptions(forceServer));
      final remote = snapshot.docs
          .map(TradingStrategyRecord.fromFirestore)
          .where((record) => record.strategy.name.trim().isNotEmpty)
          .toList(growable: false);
      final local = _localTradingStrategyRecords(uid);
      final remoteIds = remote.map((record) => record.id).toSet();
      final missingLocal = local
          .where((record) => !remoteIds.contains(record.id))
          .toList(growable: false);
      if (missingLocal.isNotEmpty && _migratedTradingStrategyUsers.add(uid)) {
        final batch = _firestore.batch();
        for (final record in missingLocal) {
          final normalized = _strategyRecordForUser(uid, record);
          batch.set(
            collection.doc(normalized.id),
            normalized.toFirestore(),
            SetOptions(merge: true),
          );
        }
        await batch.commit();
      }
      final combined =
          <String, TradingStrategyRecord>{
              for (final record in missingLocal)
                record.id: _strategyRecordForUser(uid, record),
              for (final record in remote)
                record.id: _strategyRecordForUser(uid, record),
            }.values.toList()
            ..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
      await _writeLocalTradingStrategies(uid, combined);
      return combined;
    }

    return _localTradingStrategyRecords(uid);
  }

  Future<void> upsertTradingStrategyRecord({
    required String uid,
    required TradingStrategyRecord record,
  }) async {
    final normalized = _strategyRecordForUser(uid, record);
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_strategies')
          .doc(normalized.id)
          .set(normalized.toFirestore(), SetOptions(merge: true));
    }

    final records = _localTradingStrategyRecords(uid);
    final next = [
      normalized,
      for (final item in records)
        if (item.id != normalized.id) item,
    ]..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    await _writeLocalTradingStrategies(uid, next);
  }

  Future<void> deleteTradingStrategyRecord({
    required String uid,
    required String strategyId,
  }) async {
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_strategies')
          .doc(strategyId)
          .delete();
    }
    final next = _localTradingStrategyRecords(
      uid,
    ).where((record) => record.id != strategyId).toList(growable: false);
    await _writeLocalTradingStrategies(uid, next);
  }

  Future<List<TradingPlanRecord>> loadTradingPlanRecords(
    String uid, {
    bool forceServer = false,
  }) async {
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_plans');
      final snapshot = await collection
          .orderBy('updated_at', descending: true)
          .get(_serverGetOptions(forceServer));
      final remote = snapshot.docs
          .map(TradingPlanRecord.fromFirestore)
          .where(_hasTradingPlanContent)
          .toList(growable: false);
      final local = _localTradingPlanRecords(uid);
      final remoteIds = remote.map((record) => record.id).toSet();
      final missingLocal = local
          .where((record) => !remoteIds.contains(record.id))
          .toList(growable: false);
      if (missingLocal.isNotEmpty && _migratedTradingPlanUsers.add(uid)) {
        final batch = _firestore.batch();
        for (final record in missingLocal) {
          final normalized = _planRecordForUser(uid, record);
          batch.set(
            collection.doc(normalized.id),
            normalized.toFirestore(),
            SetOptions(merge: true),
          );
        }
        await batch.commit();
      }
      final combined =
          <String, TradingPlanRecord>{
              for (final record in missingLocal)
                record.id: _planRecordForUser(uid, record),
              for (final record in remote)
                record.id: _planRecordForUser(uid, record),
            }.values.toList()
            ..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
      await _writeLocalTradingPlans(uid, combined);
      return combined;
    }

    return _localTradingPlanRecords(uid);
  }

  Future<void> upsertTradingPlanRecord({
    required String uid,
    required TradingPlanRecord record,
  }) async {
    final normalized = _planRecordForUser(uid, record);
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_plans');
      final batch = _firestore.batch();
      if (normalized.plan.isDefault) {
        final defaults = await collection
            .where('is_default', isEqualTo: true)
            .get();
        for (final doc in defaults.docs) {
          if (doc.id == normalized.id) continue;
          batch.update(doc.reference, {
            'is_default': false,
            'plan.is_default': false,
            'updated_at': FieldValue.serverTimestamp(),
          });
        }
      }
      batch.set(
        collection.doc(normalized.id),
        normalized.toFirestore(),
        SetOptions(merge: true),
      );
      await batch.commit();
    }

    final records = _localTradingPlanRecords(uid);
    final next = [
      normalized,
      for (final item in records)
        if (item.id != normalized.id)
          normalized.plan.isDefault ? item.withDefault(false) : item,
    ]..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    await _writeLocalTradingPlans(uid, next);
  }

  Future<void> deleteTradingPlanRecord({
    required String uid,
    required String planId,
  }) async {
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_plans')
          .doc(planId)
          .delete();
    }
    final next = _localTradingPlanRecords(
      uid,
    ).where((record) => record.id != planId).toList(growable: false);
    await _writeLocalTradingPlans(uid, next);
  }

  Future<List<PsychologyCheckIn>> loadPsychologyCheckIns(
    String uid, {
    bool forceServer = false,
  }) async {
    final local =
        storage
            ?.readList(uid, 'psychology_checkins')
            .map(PsychologyCheckIn.fromJson)
            .toList() ??
        <PsychologyCheckIn>[];
    if (!firebaseEnabled) {
      local.sort((left, right) => right.createdAt.compareTo(left.createdAt));
      return local;
    }

    final collection = _firestore
        .collection('users')
        .doc(uid)
        .collection('psychology_checks');
    final snapshot = await collection
        .orderBy('completed_at', descending: true)
        .get(_serverGetOptions(forceServer));
    final remote = snapshot.docs
        .map(PsychologyCheckIn.fromFirestore)
        .toList(growable: false);
    final remoteIds = remote.map((item) => item.id).toSet();
    final missingLocal = local.where((item) => !remoteIds.contains(item.id));
    if (missingLocal.isNotEmpty) {
      final batch = _firestore.batch();
      for (final item in missingLocal) {
        batch.set(
          collection.doc(item.id),
          PsychologyCheckIn(
            id: item.id,
            userId: uid,
            createdAt: item.createdAt,
            emotion: item.emotion,
            confidence: item.confidence,
            discipline: item.discipline,
            fomo: item.fomo,
            revengeUrge: item.revengeUrge,
            overconfidence: item.overconfidence,
            notes: item.notes,
          ).toFirestore(),
          SetOptions(merge: true),
        );
      }
      await batch.commit();
    }
    final combined =
        <String, PsychologyCheckIn>{
            for (final item in local) item.id: item,
            for (final item in remote) item.id: item,
          }.values.toList()
          ..sort((left, right) => right.createdAt.compareTo(left.createdAt));
    await storage?.writeList(
      uid,
      'psychology_checkins',
      combined.map((item) => item.toJson()).toList(),
    );
    return combined;
  }

  Future<void> upsertPsychologyCheckIn({
    required String uid,
    required PsychologyCheckIn checkIn,
  }) async {
    final normalized = PsychologyCheckIn(
      id: checkIn.id,
      userId: uid,
      createdAt: checkIn.createdAt,
      emotion: checkIn.emotion,
      confidence: checkIn.confidence,
      discipline: checkIn.discipline,
      fomo: checkIn.fomo,
      revengeUrge: checkIn.revengeUrge,
      overconfidence: checkIn.overconfidence,
      notes: checkIn.notes,
    );
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('psychology_checks')
          .doc(normalized.id)
          .set(normalized.toFirestore(), SetOptions(merge: true));
    }
    final history = await loadPsychologyCheckIns(uid);
    final next = [
      normalized,
      for (final item in history)
        if (item.id != normalized.id) item,
    ]..sort((left, right) => right.createdAt.compareTo(left.createdAt));
    await storage?.writeList(
      uid,
      'psychology_checkins',
      next.map((item) => item.toJson()).toList(),
    );
  }

  Stream<List<PerformanceRating>> performanceRatingsFor(String uid) {
    if (!firebaseEnabled) {
      return Stream.value(const <PerformanceRating>[]);
    }
    return _firestore
        .collection('users')
        .doc(uid)
        .collection('performance_ratings')
        .orderBy('period_start', descending: true)
        .limit(120)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map(PerformanceRating.fromFirestore)
              .toList(growable: false),
        );
  }

  Future<void> recalculatePerformanceRatings({
    required String uid,
    DateTime? date,
  }) async {
    if (!firebaseEnabled) return;
    await _callableService.call('recalculatePerformanceRatings', {
      'userId': uid,
      if (date != null) 'date': date.toIso8601String(),
    });
  }

  Future<Mt5Account> linkMt5Account({
    required String uid,
    required int login,
    required String brokerServer,
    String displayName = '',
    String syncMachineType = 'local',
  }) async {
    final normalizedServer = brokerServer.trim();
    if (login <= 0) {
      throw const ApiException('Enter a valid MT5 account number.');
    }
    if (normalizedServer.isEmpty) {
      throw const ApiException('Broker server is required.');
    }

    final accountKey = mt5AccountKey(normalizedServer, login);

    if (firebaseEnabled) {
      final userRef = _firestore.collection('users').doc(uid);
      final collection = userRef.collection('mt5_accounts');
      final reference = collection.doc(accountKey);
      final activeAccounts = await collection
          .where('is_active', isEqualTo: true)
          .limit(1)
          .get();
      final exists = (await reference.get()).exists;
      final makePrimary = activeAccounts.docs.isEmpty;
      final account = Mt5Account(
        id: accountKey,
        userId: uid,
        accountKey: accountKey,
        login: login,
        brokerServer: normalizedServer,
        displayName: displayName.trim(),
        syncMachineType: syncMachineType.trim().isEmpty
            ? 'local'
            : syncMachineType.trim(),
        isActive: true,
        isPrimary: makePrimary,
      );
      final batch = _firestore.batch();
      batch.set(
        reference,
        exists
            ? <String, Object?>{
                'display_name': account.displayName,
                'sync_machine_type': account.syncMachineType,
                'is_active': true,
                'is_primary': makePrimary,
                'isPrimary': makePrimary,
                'updated_at': FieldValue.serverTimestamp(),
              }
            : <String, Object?>{
                ...account.toFirestore(includeCreatedAt: true),
                'isPrimary': makePrimary,
              },
        SetOptions(merge: true),
      );
      if (makePrimary) {
        batch.set(userRef, {
          'main_mt5_account_key': accountKey,
          'mainMt5AccountKey': accountKey,
          'main_mt5_account_id': accountKey,
          'mainMt5AccountId': accountKey,
          'main_mt5_account_login': login,
          'mainMt5AccountLogin': login,
          'main_mt5_account_updated_at': FieldValue.serverTimestamp(),
          'mainMt5AccountUpdatedAt': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
      }
      await batch.commit();
      return account;
    }

    final accounts = storage?.readList(uid, 'mt5_accounts') ?? const [];
    final makePrimary = accounts
        .where(
          (item) => item['is_active'] != false && item['isActive'] != false,
        )
        .isEmpty;
    final account = Mt5Account(
      id: accountKey,
      userId: uid,
      accountKey: accountKey,
      login: login,
      brokerServer: normalizedServer,
      displayName: displayName.trim(),
      syncMachineType: syncMachineType.trim().isEmpty
          ? 'local'
          : syncMachineType.trim(),
      isActive: true,
      isPrimary: makePrimary,
    );
    final updated = [
      for (final item in accounts)
        if ((item['account_key'] ?? item['accountKey']) != accountKey) item,
      account.toJson(),
    ];
    await storage?.writeList(uid, 'mt5_accounts', updated);
    return account;
  }

  Future<Mt5Account> updateMt5Account({
    required String uid,
    required Mt5Account account,
    required String displayName,
    required String syncMachineType,
  }) async {
    final updated = Mt5Account(
      id: account.id,
      userId: account.userId,
      accountKey: account.accountKey,
      login: account.login,
      brokerServer: account.brokerServer,
      displayName: displayName.trim(),
      syncMachineType: syncMachineType.trim().isEmpty
          ? account.syncMachineType
          : syncMachineType.trim(),
      isActive: account.isActive,
      isPrimary: account.isPrimary,
      syncStatus: account.syncStatus,
      displayStatus: account.displayStatus,
      lastSyncTime: account.lastSyncTime,
      lastSuccessfulSyncAt: account.lastSuccessfulSyncAt,
      lastConnectionCheckAt: account.lastConnectionCheckAt,
      lastHeartbeatAt: account.lastHeartbeatAt,
      lastActivityTime: account.lastActivityTime,
      lastError: account.lastError,
      lastErrorCode: account.lastErrorCode,
      balance: account.balance,
      equity: account.equity,
      leverage: account.leverage,
      margin: account.margin,
      freeMargin: account.freeMargin,
      currency: account.currency,
      updatedAt: account.updatedAt,
    );

    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(account.accountKey)
          .set({
            'display_name': updated.displayName,
            'sync_machine_type': updated.syncMachineType,
            'updated_at': FieldValue.serverTimestamp(),
          }, SetOptions(merge: true));
      return updated;
    }

    final accounts = storage?.readList(uid, 'mt5_accounts') ?? const [];
    final next = [
      for (final item in accounts)
        if ((item['account_key'] ?? item['accountKey']) != account.accountKey)
          item,
      updated.toJson(),
    ];
    await storage?.writeList(uid, 'mt5_accounts', next);
    return updated;
  }

  Future<void> unlinkMt5Account({
    required String uid,
    required String accountKey,
  }) async {
    if (accountKey.trim().isEmpty) return;
    if (firebaseEnabled) {
      final userRef = _firestore.collection('users').doc(uid);
      final collection = userRef.collection('mt5_accounts');
      final userSnapshot = await userRef.get(
        const GetOptions(source: Source.server),
      );
      final wasPrimary =
          _mainMt5AccountIdFrom(
            userSnapshot.data() ?? const <String, dynamic>{},
          ) ==
          accountKey;
      final batch = _firestore.batch();
      batch.set(collection.doc(accountKey), {
        'is_active': false,
        'is_primary': false,
        'isPrimary': false,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
      if (wasPrimary) {
        batch.set(userRef, {
          'main_mt5_account_key': FieldValue.delete(),
          'mainMt5AccountKey': FieldValue.delete(),
          'main_mt5_account_id': FieldValue.delete(),
          'mainMt5AccountId': FieldValue.delete(),
          'main_mt5_account_login': FieldValue.delete(),
          'mainMt5AccountLogin': FieldValue.delete(),
          'main_mt5_account_updated_at': FieldValue.serverTimestamp(),
          'mainMt5AccountUpdatedAt': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
      }
      await batch.commit();
      return;
    }

    final accounts = storage?.readList(uid, 'mt5_accounts') ?? const [];
    final wasPrimary = accounts.any(
      (item) =>
          (item['account_key'] ?? item['accountKey']) == accountKey &&
          (item['is_primary'] == true || item['isPrimary'] == true),
    );
    final next = [
      for (final item in accounts)
        if ((item['account_key'] ?? item['accountKey']) == accountKey)
          {
            ...item,
            'is_active': false,
            'isActive': false,
            'is_primary': false,
            'isPrimary': false,
            'sync_status': 'not_connected',
          }
        else
          {
            ...item,
            if (wasPrimary) 'is_primary': false,
            if (wasPrimary) 'isPrimary': false,
          },
    ];
    await storage?.writeList(uid, 'mt5_accounts', next);
  }

  Stream<List<Mt5TradeRecord>> mt5RecordsForAccount({
    required String uid,
    required String accountKey,
    int limit = 200,
  }) {
    if (accountKey.trim().isEmpty) return Stream.value(const []);

    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);
      final trades = account
          .collection('trades')
          .orderBy('open_time', descending: true)
          .limit(limit)
          .snapshots()
          .map(
            (snapshot) => snapshot.docs
                .map(Mt5TradeRecord.fromFirestore)
                .toList(growable: false),
          );
      final orders = account
          .collection('orders')
          .orderBy('open_time', descending: true)
          .limit(limit)
          .snapshots()
          .map(
            (snapshot) => snapshot.docs
                .map(Mt5TradeRecord.fromFirestore)
                .toList(growable: false),
          );
      return _filterCompletedMt5ImportRecords(
        _combineMt5RecordStreams([trades, orders]),
        _completedMt5ImportBatchIds(uid: uid, accountKey: accountKey),
      );
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_records_$accountKey')
            .map(Mt5TradeRecord.fromJson)
            .toList(growable: false) ??
        const <Mt5TradeRecord>[];
    return Stream.value(
      cached.isEmpty && Mt5SyncConfig.mockDataEnabled
          ? _mockMt5Records(uid, accountKey)
          : cached,
    );
  }

  Stream<List<TradingJournalEntry>> mt5JournalEntriesFor(
    String uid, {
    required String accountKey,
    int limitPerAccount = 2000,
  }) {
    final scopedAccountKey = accountKey.trim();
    if (scopedAccountKey.isEmpty) return Stream.value(const []);
    if (kDebugMode) {
      debugPrint(
        '[Trading Report] query uid=$uid mainMt5AccountId=$scopedAccountKey '
        'collections=trades,orders limitPerCollection=$limitPerAccount',
      );
    }
    final records = mt5RecordsForAccount(
      uid: uid,
      accountKey: scopedAccountKey,
      limit: limitPerAccount,
    );
    final annotatedRecords = _combineMt5RecordsWithAnnotations(
      records,
      mt5JournalAnnotationsForAccount(uid: uid, accountId: scopedAccountKey),
    );
    return annotatedRecords.map((records) {
      final dataset = _mt5Eligibility.build(
        records,
        userId: uid,
        accountKey: scopedAccountKey,
      );
      final journals = dataset.executedTrades.map(journalFromMt5Record).toList()
        ..sort((left, right) {
          final leftDate = left.status == 'closed'
              ? left.closeDate
              : left.openDate;
          final rightDate = right.status == 'closed'
              ? right.closeDate
              : right.openDate;
          return rightDate.compareTo(leftDate);
        });
      if (kDebugMode) {
        debugPrint(
          '[Trading Report] queriedRecords=${records.length} '
          'scopedRecords=${journals.length} uid=$uid '
          'mainMt5AccountId=$scopedAccountKey '
          'eligibility=${dataset.diagnostics.toJson()}',
        );
        for (final item in dataset.excludedNonTradeRecords.take(5)) {
          final record = item.record;
          debugPrint(
            '[MT5 Eligibility] event=trade_record_excluded '
            'reason=${item.reason.name} '
            'account=${_maskedMt5AccountRef(scopedAccountKey)} '
            'order_type=${record.normalizedOrderType} '
            'order_state=${record.normalizedOrderState} '
            'executed_volume=${record.executedVolume} '
            'has_entry_deal=${record.hasEntryDeal == true} '
            'has_position=${record.hasValidPosition}',
          );
        }
      }
      return journals;
    });
  }

  Stream<List<TradingJournalEntry>> mt5JournalEntriesForAccounts(
    String uid, {
    required Iterable<String> accountKeys,
    int limitPerAccount = 2000,
  }) {
    final keys = accountKeys
        .map((key) => key.trim())
        .where((key) => key.isNotEmpty)
        .toSet()
        .toList(growable: false);
    return _combineListStreams<TradingJournalEntry>(
      [
        for (final key in keys)
          mt5JournalEntriesFor(
            uid,
            accountKey: key,
            limitPerAccount: limitPerAccount,
          ),
      ],
      identity: (trade) =>
          '${trade.source.storageValue}|${trade.tradingAccountId ?? ''}|${trade.id}',
      compare: (left, right) => right.closeDate.compareTo(left.closeDate),
    );
  }

  Stream<List<CapitalTransaction>> mt5CapitalTransactionsFor(
    String uid, {
    required String accountKey,
    int limit = 5000,
  }) {
    final scopedAccountKey = accountKey.trim();
    if (scopedAccountKey.isEmpty || !firebaseEnabled) {
      return Stream.value(const <CapitalTransaction>[]);
    }
    final account = _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(scopedAccountKey);
    final records = account
        .collection('balance_transactions')
        .orderBy('transaction_time', descending: true)
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) {
                final data = doc.data();
                final classification =
                    '${data['classification'] ?? 'otherBalanceAdjustment'}';
                final type = switch (classification) {
                  'deposit' => 1,
                  'withdrawal' => 2,
                  'credit' || 'creditRemoval' || 'bonus' => 3,
                  _ => 4,
                };
                final rawAmount = data['amount'] ?? data['amount_decimal'];
                final amount = rawAmount is num
                    ? rawAmount.toDouble()
                    : double.tryParse('$rawAmount') ?? 0;
                final time = _nullableFirestoreDate(
                  data['transaction_time'] ?? data['time'],
                );
                return _Mt5ImportedCapitalRecord(
                  batchId: '${data['import_batch_id'] ?? ''}',
                  capital: CapitalTransaction(
                    id: 'mt5:$scopedAccountKey:${doc.id}',
                    userId: uid,
                    depositDate: time ?? DateTime.fromMillisecondsSinceEpoch(0),
                    amount: type == 1
                        ? amount.abs()
                        : type == 2
                        ? -amount.abs()
                        : amount,
                    type: type,
                    tradingAccountId: scopedAccountKey,
                    notes: [
                      classification,
                      if ('${data['comment'] ?? ''}'.trim().isNotEmpty)
                        '${data['comment']}'.trim(),
                    ].join(': '),
                    createdAt: _nullableFirestoreDate(data['synced_at']),
                    updatedAt: _nullableFirestoreDate(data['last_seen_at']),
                  ),
                );
              })
              .toList(growable: false),
        );
    return _filterCompletedMt5ImportCapitals(
      records,
      _completedMt5ImportBatchIds(uid: uid, accountKey: scopedAccountKey),
    );
  }

  Stream<List<CapitalTransaction>> mt5CapitalTransactionsForAccounts(
    String uid, {
    required Iterable<String> accountKeys,
    int limitPerAccount = 5000,
  }) {
    final keys = accountKeys
        .map((key) => key.trim())
        .where((key) => key.isNotEmpty)
        .toSet()
        .toList(growable: false);
    return _combineListStreams<CapitalTransaction>(
      [
        for (final key in keys)
          mt5CapitalTransactionsFor(
            uid,
            accountKey: key,
            limit: limitPerAccount,
          ),
      ],
      identity: (capital) => capital.id,
      compare: (left, right) => right.depositDate.compareTo(left.depositDate),
    );
  }

  Stream<Set<String>> _completedMt5ImportBatchIds({
    required String uid,
    required String accountKey,
  }) {
    if (!firebaseEnabled) return Stream.value(const <String>{});
    return _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(accountKey)
        .collection('import_batches')
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .where(
                (doc) => const {
                  'completed',
                  'completed_with_warnings',
                }.contains('${doc.data()['status'] ?? ''}'),
              )
              .map((doc) => doc.id)
              .toSet(),
        );
  }

  Stream<List<Mt5JournalAnnotation>> mt5JournalAnnotationsForAccount({
    required String uid,
    required String accountId,
  }) {
    if (uid.trim().isEmpty || accountId.trim().isEmpty) {
      return Stream.value(const []);
    }
    if (!firebaseEnabled) return Stream.value(const []);
    return _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(accountId)
        .collection('journal_annotations')
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map(Mt5JournalAnnotation.fromFirestore)
              .toList(growable: false),
        );
  }

  Future<Mt5JournalAnnotation?> loadMt5JournalAnnotation({
    required String uid,
    required String accountId,
    required String lifecycleKey,
  }) async {
    if (!firebaseEnabled) return null;
    final id = Mt5JournalAnnotation.idForLifecycle(lifecycleKey);
    final snapshot = await _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(accountId)
        .collection('journal_annotations')
        .doc(id)
        .get();
    return snapshot.exists
        ? Mt5JournalAnnotation.fromFirestore(snapshot)
        : null;
  }

  Future<void> saveMt5JournalAnnotation(Mt5JournalAnnotation annotation) async {
    final uid = _firebaseUid();
    if (annotation.userId != uid || annotation.accountId.trim().isEmpty) {
      throw const ApiException('Invalid MT5 journal annotation scope.');
    }
    final id = Mt5JournalAnnotation.idForLifecycle(annotation.lifecycleKey);
    final reference = _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(annotation.accountId)
        .collection('journal_annotations')
        .doc(id);
    final existing = await reference.get();
    await reference.set({
      ...annotation.toJson(),
      'annotation_version': 1,
      'updated_at': FieldValue.serverTimestamp(),
      if (!existing.exists) 'created_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
  }

  Future<String> uploadMt5JournalAttachment({
    required Mt5JournalAnnotation annotation,
    required ApiUploadFile file,
  }) async {
    final uid = _firebaseUid();
    if (annotation.userId != uid) {
      throw const ApiException('Invalid MT5 journal attachment scope.');
    }
    final bytes = await _uploadBytes(file);
    final annotationId = Mt5JournalAnnotation.idForLifecycle(
      annotation.lifecycleKey,
    );
    final storagePath =
        'users/$uid/mt5_journal/${annotation.accountId}/$annotationId/'
        '${DateTime.now().microsecondsSinceEpoch}_${_safeName(file.filename)}';
    final reference = FirebaseStorage.instance.ref(storagePath);
    await reference.putData(
      bytes,
      SettableMetadata(contentType: _contentType(file.filename)),
    );
    final url = await reference.getDownloadURL();
    final annotationReference = _firestore
        .collection('users')
        .doc(uid)
        .collection('mt5_accounts')
        .doc(annotation.accountId)
        .collection('journal_annotations')
        .doc(annotationId);
    final existing = await annotationReference.get();
    await annotationReference.set({
      ...annotation.toJson(),
      'attachment_urls': FieldValue.arrayUnion([url]),
      'annotation_version': 1,
      'updated_at': FieldValue.serverTimestamp(),
      if (!existing.exists) 'created_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    return url;
  }

  Stream<List<Mt5TradeRecord>> mt5RealtimeRecordsForAccount({
    required String uid,
    required String accountKey,
    int liveLimit = 50,
    int recentClosedLimit = 50,
  }) {
    if (!Mt5SyncConfig.mt5RealtimeListenerEnabled) {
      return mt5RecordsForAccount(
        uid: uid,
        accountKey: accountKey,
        limit: recentClosedLimit,
      );
    }
    if (accountKey.trim().isEmpty) return Stream.value(const []);

    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);
      Stream<List<Mt5TradeRecord>> records(
        Query<Map<String, dynamic>> query, {
        bool Function(Mt5TradeRecord record)? where,
        int? take,
      }) {
        return query.snapshots().map((snapshot) {
          var items = snapshot.docs.map(Mt5TradeRecord.fromFirestore);
          if (where != null) items = items.where(where);
          if (take != null) items = items.take(take);
          return items.toList(growable: false);
        });
      }

      final openTrades = records(
        account
            .collection('trades')
            .orderBy('open_time', descending: true)
            .limit(liveLimit * 2),
        where: (record) => record.isOpen,
        take: liveLimit,
      );
      final pendingOrders = records(
        account
            .collection('orders')
            .orderBy('open_time', descending: true)
            .limit(liveLimit * 2),
        where: (record) => record.isPending,
        take: liveLimit,
      );
      final recentClosedTrades = records(
        account
            .collection('trades')
            .orderBy('close_time', descending: true)
            .limit(recentClosedLimit * 2),
        where: (record) => record.isClosed,
        take: recentClosedLimit,
      );
      return _filterCompletedMt5ImportRecords(
        _combineMt5RecordStreams([
          openTrades,
          pendingOrders,
          recentClosedTrades,
        ]),
        _completedMt5ImportBatchIds(uid: uid, accountKey: accountKey),
      );
    }

    return mt5RecordsForAccount(
      uid: uid,
      accountKey: accountKey,
      limit: recentClosedLimit,
    );
  }

  Future<List<Mt5TradeRecord>> loadMt5ControlledRecordsForAccount({
    required String uid,
    required String accountKey,
    int liveLimit = 50,
    int recentClosedLimit = 50,
    bool forceServer = false,
  }) async {
    if (accountKey.trim().isEmpty) return const [];

    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);

      final snapshots = await Future.wait([
        account
            .collection('trades')
            .orderBy('open_time', descending: true)
            .limit(liveLimit * 4)
            .get(_serverGetOptions(forceServer)),
        account
            .collection('orders')
            .orderBy('open_time', descending: true)
            .limit(liveLimit * 4)
            .get(_serverGetOptions(forceServer)),
        account
            .collection('trades')
            .orderBy('close_time', descending: true)
            .limit(recentClosedLimit * 2)
            .get(_serverGetOptions(forceServer)),
        account
            .collection('orders')
            .orderBy('close_time', descending: true)
            .limit(recentClosedLimit * 2)
            .get(_serverGetOptions(forceServer)),
      ]);

      final completedBatches =
          (await account
                  .collection('import_batches')
                  .get(_serverGetOptions(forceServer)))
              .docs
              .where(
                (doc) => const {
                  'completed',
                  'completed_with_warnings',
                }.contains('${doc.data()['status'] ?? ''}'),
              )
              .map((doc) => doc.id)
              .toSet();
      final records = snapshots
          .expand((snapshot) => snapshot.docs)
          .map(Mt5TradeRecord.fromFirestore)
          .where(
            (record) =>
                record.importBatchId.isEmpty ||
                completedBatches.contains(record.importBatchId),
          )
          .toList(growable: false);
      final dataset = _mt5Eligibility.build(
        records,
        userId: uid,
        accountKey: accountKey,
      );
      return _dedupeAndSortMt5Records([
        ...dataset.executedOpenTrades.take(liveLimit),
        ...dataset.activePendingOrders.take(liveLimit),
        ...dataset.executedClosedTrades.reversed.take(recentClosedLimit),
      ]);
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_records_$accountKey')
            .map(Mt5TradeRecord.fromJson)
            .toList(growable: false) ??
        const <Mt5TradeRecord>[];
    final records = cached.isEmpty && Mt5SyncConfig.mockDataEnabled
        ? _mockMt5Records(uid, accountKey)
        : cached;
    final dataset = _mt5Eligibility.build(
      records,
      userId: uid,
      accountKey: accountKey,
    );
    return _dedupeAndSortMt5Records([
      ...dataset.executedOpenTrades.take(liveLimit),
      ...dataset.activePendingOrders.take(liveLimit),
      ...dataset.executedClosedTrades.reversed.take(recentClosedLimit),
    ]);
  }

  Future<Mt5SyncJob?> refreshMt5Account({
    required String uid,
    required String accountKey,
    String refreshType = 'manual_refresh',
    DateTime? requestedDateFrom,
    DateTime? requestedDateTo,
    bool waitForCompletion = true,
    Duration timeout = const Duration(seconds: 90),
    bool forceServer = true,
  }) {
    if (accountKey.trim().isEmpty) return Future.value(null);
    final requestKey = _mt5RefreshRequestKey(
      uid: uid,
      accountKey: accountKey,
      refreshType: refreshType,
      requestedDateFrom: requestedDateFrom,
      requestedDateTo: requestedDateTo,
    );
    final inFlight = _mt5RefreshInFlight[requestKey];
    if (inFlight != null) {
      debugPrint(
        '[MT5 Sync] Reusing in-flight refresh request for '
        '$accountKey ($refreshType).',
      );
      return inFlight;
    }

    late final Future<Mt5SyncJob?> future;
    future =
        _refreshMt5AccountOnce(
          uid: uid,
          accountKey: accountKey,
          refreshType: refreshType,
          requestedDateFrom: requestedDateFrom,
          requestedDateTo: requestedDateTo,
          waitForCompletion: waitForCompletion,
          timeout: timeout,
          forceServer: forceServer,
        ).whenComplete(() {
          if (identical(_mt5RefreshInFlight[requestKey], future)) {
            _mt5RefreshInFlight.remove(requestKey);
          }
        });
    _mt5RefreshInFlight[requestKey] = future;
    return future;
  }

  Future<Mt5SyncJob?> _refreshMt5AccountOnce({
    required String uid,
    required String accountKey,
    required String refreshType,
    required DateTime? requestedDateFrom,
    required DateTime? requestedDateTo,
    required bool waitForCompletion,
    required Duration timeout,
    required bool forceServer,
  }) async {
    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);
      final snapshot = await account.get(_serverGetOptions(forceServer));
      if (!snapshot.exists) {
        throw const ApiException('MT5 account not found.');
      }

      final linkedAccount = Mt5Account.fromFirestore(snapshot);
      final job = await _createMt5SyncJob(
        uid: uid,
        account: linkedAccount,
        refreshType: refreshType,
        requestedDateFrom: requestedDateFrom,
        requestedDateTo: requestedDateTo,
      );
      if (!waitForCompletion) return job;

      final completed = await _waitForMt5SyncJob(
        jobId: job.id,
        timeout: timeout,
      );
      await _reloadMt5AccountDocuments(
        account: account,
        forceServer: forceServer,
      );
      return completed;
    }
    await refreshTradingData();
    return Mt5SyncJob(
      id: 'local-${DateTime.now().microsecondsSinceEpoch}',
      userId: uid,
      accountKey: accountKey,
      login: 0,
      brokerServer: '',
      refreshType: refreshType,
      status: 'completed',
      requestedDateFrom: requestedDateFrom,
      requestedDateTo: requestedDateTo,
      createdAt: DateTime.now(),
      completedAt: DateTime.now(),
    );
  }

  Future<Mt5SyncJob?> refreshMt5HistoryRange({
    required String uid,
    required String accountKey,
    required DateTime requestedDateFrom,
    required DateTime requestedDateTo,
    bool forceServer = true,
  }) {
    return refreshMt5Account(
      uid: uid,
      accountKey: accountKey,
      refreshType: 'selected_history_range',
      requestedDateFrom: requestedDateFrom,
      requestedDateTo: requestedDateTo,
      forceServer: forceServer,
    );
  }

  String _mt5RefreshRequestKey({
    required String uid,
    required String accountKey,
    required String refreshType,
    DateTime? requestedDateFrom,
    DateTime? requestedDateTo,
  }) {
    String dateKey(DateTime? date) => date == null
        ? ''
        : DateTime(date.year, date.month, date.day).toIso8601String();
    return [
      uid,
      accountKey.trim(),
      refreshType,
      dateKey(requestedDateFrom),
      dateKey(requestedDateTo),
    ].join('|');
  }

  Future<Mt5SyncJob> _createMt5SyncJob({
    required String uid,
    required Mt5Account account,
    required String refreshType,
    DateTime? requestedDateFrom,
    DateTime? requestedDateTo,
  }) async {
    final normalizedFrom = requestedDateFrom == null
        ? null
        : DateTime(
            requestedDateFrom.year,
            requestedDateFrom.month,
            requestedDateFrom.day,
          );
    final normalizedTo = requestedDateTo == null
        ? null
        : DateTime(
            requestedDateTo.year,
            requestedDateTo.month,
            requestedDateTo.day,
            23,
            59,
            59,
          );
    final jobRef = _firestore.collection('mt5_sync_jobs').doc();
    final clientRequestId = _mt5RefreshRequestKey(
      uid: uid,
      accountKey: account.accountKey,
      refreshType: refreshType,
      requestedDateFrom: normalizedFrom,
      requestedDateTo: normalizedTo,
    );
    final payload = <String, Object?>{
      'job_id': jobRef.id,
      'jobId': jobRef.id,
      'client_request_id': clientRequestId,
      'clientRequestId': clientRequestId,
      'dedupe_key': clientRequestId,
      'dedupeKey': clientRequestId,
      'user_id': uid,
      'userId': uid,
      'mt5_account_id': account.accountKey,
      'mt5AccountId': account.accountKey,
      'account_key': account.accountKey,
      'accountKey': account.accountKey,
      'account_number': account.login,
      'accountNumber': account.login,
      'mt5_login': account.login,
      'broker_server': account.brokerServer,
      'brokerServer': account.brokerServer,
      'refresh_type': refreshType,
      'refreshType': refreshType,
      'status': 'pending',
      'priority': refreshType == 'manual_refresh' ? 10 : 20,
      'requested_by': uid,
      'requestedBy': uid,
      'requested_from': 'flutter_mobile',
      'requestedFrom': 'flutter_mobile',
      'retry_count': 0,
      'retryCount': 0,
      'created_at': FieldValue.serverTimestamp(),
      'createdAt': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
      'updatedAt': FieldValue.serverTimestamp(),
      if (normalizedFrom != null) ...{
        'requested_date_from': Timestamp.fromDate(normalizedFrom),
        'requestedDateFrom': Timestamp.fromDate(normalizedFrom),
      },
      if (normalizedTo != null) ...{
        'requested_date_to': Timestamp.fromDate(normalizedTo),
        'requestedDateTo': Timestamp.fromDate(normalizedTo),
      },
    };
    try {
      await jobRef.set(payload);
    } on FirebaseException catch (error, stackTrace) {
      _logMt5FirestoreException(
        operation: 'create MT5 sync job',
        path: 'mt5_sync_jobs/${jobRef.id}',
        error: error,
        stackTrace: stackTrace,
      );
      rethrow;
    }
    debugPrint(
      '[MT5 Sync] Refresh job ${jobRef.id} created for '
      '${account.login} ${account.brokerServer} ($refreshType).',
    );
    return Mt5SyncJob.fromJson({
      'id': jobRef.id,
      ...payload,
      'created_at': DateTime.now(),
      'updated_at': DateTime.now(),
    });
  }

  Future<Mt5SyncJob> _waitForMt5SyncJob({
    required String jobId,
    required Duration timeout,
  }) {
    final jobRef = _firestore.collection('mt5_sync_jobs').doc(jobId);
    final completer = Completer<Mt5SyncJob>();
    late final StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>
    subscription;
    late final Timer timer;

    void finish(Mt5SyncJob job) {
      if (!completer.isCompleted) completer.complete(job);
    }

    void fail(Object error, [StackTrace? stackTrace]) {
      if (!completer.isCompleted) completer.completeError(error, stackTrace);
    }

    subscription = jobRef.snapshots().listen(
      (snapshot) {
        if (!snapshot.exists) return;
        final job = Mt5SyncJob.fromFirestore(snapshot);
        debugPrint('[MT5 Sync] Job ${job.id} status changed: ${job.status}.');
        if (!job.isTerminal) return;
        if (job.isCompleted) {
          finish(job);
        } else {
          fail(ApiException(_mt5JobFailureMessage(job)));
        }
      },
      onError: (Object error, StackTrace stackTrace) {
        if (error is FirebaseException) {
          _logMt5FirestoreException(
            operation: 'listen for MT5 sync job',
            path: 'mt5_sync_jobs/$jobId',
            error: error,
            stackTrace: stackTrace,
          );
        }
        fail(error, stackTrace);
      },
    );

    timer = Timer(timeout, () async {
      try {
        final snapshot = await jobRef.get(
          const GetOptions(source: Source.server),
        );
        if (snapshot.exists) {
          final job = Mt5SyncJob.fromFirestore(snapshot);
          if (job.isCompleted) {
            finish(job);
            return;
          }
          if (job.isFailed) {
            fail(ApiException(_mt5JobFailureMessage(job)));
            return;
          }
        }
      } on FirebaseException catch (error, stackTrace) {
        _logMt5FirestoreException(
          operation: 'read MT5 sync job timeout state',
          path: 'mt5_sync_jobs/$jobId',
          error: error,
          stackTrace: stackTrace,
        );
        // Preserve the clearer timeout message below.
      } catch (_) {
        // Preserve the clearer timeout message below.
      }
      fail(
        const ApiException(
          'MT5 refresh is still processing. Please try again shortly.',
        ),
      );
    });

    return completer.future.whenComplete(() async {
      timer.cancel();
      await subscription.cancel();
    });
  }

  String _mt5JobFailureMessage(Mt5SyncJob job) {
    final message = job.errorMessage.trim();
    if (message.isNotEmpty) return message;
    final code = job.errorCode.trim();
    if (code == 'MT5_ACTIVE_ACCOUNT_MISMATCH') {
      return 'Wrong MT5 account detected.';
    }
    return job.status == 'cancelled'
        ? 'MT5 refresh was cancelled.'
        : 'MT5 refresh failed.';
  }

  void _logMt5FirestoreException({
    required String operation,
    required String path,
    required FirebaseException error,
    StackTrace? stackTrace,
  }) {
    debugPrint(
      '[MT5 Sync][$operation] FirebaseException '
      'code=${error.code} path=$path message=${error.message ?? ''}',
    );
    if (kDebugMode && stackTrace != null) {
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  Future<void> _reloadMt5AccountDocuments({
    required DocumentReference<Map<String, dynamic>> account,
    required bool forceServer,
  }) async {
    await Future.wait([
      account.get(_serverGetOptions(forceServer)),
      account
          .collection('sync_state')
          .doc('default')
          .get(_serverGetOptions(forceServer)),
      account
          .collection('trades')
          .orderBy('open_time', descending: true)
          .limit(50)
          .get(_serverGetOptions(forceServer)),
      account
          .collection('trades')
          .orderBy('close_time', descending: true)
          .limit(50)
          .get(_serverGetOptions(forceServer)),
      account
          .collection('orders')
          .orderBy('open_time', descending: true)
          .limit(50)
          .get(_serverGetOptions(forceServer)),
      account
          .collection('orders')
          .orderBy('close_time', descending: true)
          .limit(50)
          .get(_serverGetOptions(forceServer)),
      _firestore.waitForPendingWrites(),
    ]);
  }

  Future<List<Mt5TradeRecord>> mt5ClosedHistoryPage({
    required String uid,
    required String accountKey,
    DateTime? beforeCloseTime,
    int limit = 50,
    bool forceServer = false,
  }) async {
    if (accountKey.trim().isEmpty) return const [];
    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);
      var tradesQuery = account
          .collection('trades')
          .orderBy('close_time', descending: true)
          .limit(limit * 3);
      var ordersQuery = account
          .collection('orders')
          .orderBy('close_time', descending: true)
          .limit(limit * 3);
      if (beforeCloseTime != null) {
        final cursor = Timestamp.fromDate(beforeCloseTime.toUtc());
        tradesQuery = tradesQuery.where('close_time', isLessThan: cursor);
        ordersQuery = ordersQuery.where('close_time', isLessThan: cursor);
      }
      final snapshots = await Future.wait([
        tradesQuery.get(_serverGetOptions(forceServer)),
        ordersQuery.get(_serverGetOptions(forceServer)),
      ]);
      final dataset = _mt5Eligibility.build(
        [
          ...snapshots[0].docs.map(Mt5TradeRecord.fromFirestore),
          ...snapshots[1].docs.map(Mt5TradeRecord.fromFirestore),
        ],
        userId: uid,
        accountKey: accountKey,
      );
      return dataset.executedClosedTrades.reversed
          .take(limit)
          .toList(growable: false);
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_records_$accountKey')
            .map(Mt5TradeRecord.fromJson)
            .where((record) => record.isClosed)
            .toList() ??
        (Mt5SyncConfig.mockDataEnabled
            ? _mockMt5Records(
                uid,
                accountKey,
              ).where((record) => record.isClosed).toList()
            : const <Mt5TradeRecord>[]);
    final dataset = _mt5Eligibility.build(
      cached,
      userId: uid,
      accountKey: accountKey,
    );
    final filtered = beforeCloseTime == null
        ? dataset.executedClosedTrades
        : dataset.executedClosedTrades
              .where((record) {
                final time = record.closeTime ?? record.openTime;
                return time != null && time.isBefore(beforeCloseTime);
              })
              .toList(growable: false);
    return filtered.reversed.take(limit).toList(growable: false);
  }

  Future<List<Mt5TradeRecord>> mt5ClosedHistoryRange({
    required String uid,
    required String accountKey,
    required DateTime from,
    required DateTime to,
    int limit = 500,
    bool forceServer = false,
  }) async {
    if (accountKey.trim().isEmpty) return const [];
    final start = from.isAfter(to) ? to : from;
    final end = from.isAfter(to) ? from : to;

    if (firebaseEnabled) {
      final account = _firestore
          .collection('users')
          .doc(uid)
          .collection('mt5_accounts')
          .doc(accountKey);
      Query<Map<String, dynamic>> query(
        CollectionReference<Map<String, dynamic>> collection,
      ) {
        return collection
            .where(
              'close_time',
              isGreaterThanOrEqualTo: Timestamp.fromDate(start),
            )
            .where('close_time', isLessThanOrEqualTo: Timestamp.fromDate(end))
            .orderBy('close_time', descending: true)
            .limit(limit);
      }

      final snapshots = await Future.wait([
        query(account.collection('trades')).get(_serverGetOptions(forceServer)),
        query(account.collection('orders')).get(_serverGetOptions(forceServer)),
      ]);
      final dataset = _mt5Eligibility.build(
        [
          ...snapshots[0].docs.map(Mt5TradeRecord.fromFirestore),
          ...snapshots[1].docs.map(Mt5TradeRecord.fromFirestore),
        ],
        userId: uid,
        accountKey: accountKey,
      );
      return dataset.executedClosedTrades.reversed.toList(growable: false);
    }

    final cached =
        storage
            ?.readList(uid, 'mt5_records_$accountKey')
            .map(Mt5TradeRecord.fromJson)
            .where((record) => record.isClosed)
            .toList() ??
        (Mt5SyncConfig.mockDataEnabled
            ? _mockMt5Records(
                uid,
                accountKey,
              ).where((record) => record.isClosed).toList()
            : const <Mt5TradeRecord>[]);
    final dataset = _mt5Eligibility.build(
      cached.where((record) {
        final time = record.closeTime ?? record.openTime;
        return time != null && !time.isBefore(start) && !time.isAfter(end);
      }),
      userId: uid,
      accountKey: accountKey,
    );
    return dataset.executedClosedTrades.reversed
        .take(limit)
        .toList(growable: false);
  }

  Stream<List<PropFirmChallenge>> propFirmChallengesFor(String uid) {
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('prop_firm_challenges')
          .orderBy('updated_at', descending: true)
          .snapshots()
          .map(
            (snapshot) => snapshot.docs
                .map(PropFirmChallenge.fromFirestore)
                .toList(growable: false),
          );
    }

    final cached =
        storage
            ?.readList(uid, 'prop_firm_challenges')
            .map(PropFirmChallenge.fromJson)
            .toList(growable: false) ??
        const <PropFirmChallenge>[];
    return Stream.value(cached);
  }

  Stream<PropFirmMetrics?> propFirmMetricsForChallenge({
    required String uid,
    required String challengeId,
  }) {
    if (challengeId.trim().isEmpty) return Stream.value(null);
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('prop_firm_challenges')
          .doc(challengeId)
          .collection('metrics')
          .doc('current')
          .snapshots()
          .map(
            (snapshot) => snapshot.exists
                ? PropFirmMetrics.fromFirestore(snapshot)
                : null,
          );
    }
    final cached = storage?.readMap(uid, 'prop_firm_metrics_$challengeId');
    return Stream.value(
      cached == null ? null : PropFirmMetrics.fromJson(cached),
    );
  }

  Future<PropFirmChallenge> savePropFirmChallenge(
    PropFirmChallenge challenge,
  ) async {
    final id = challenge.id.trim().isEmpty
        ? 'challenge_${DateTime.now().millisecondsSinceEpoch}'
        : challenge.id;
    final updated = challenge.copyWith(id: id);

    if (firebaseEnabled) {
      final reference = _firestore
          .collection('users')
          .doc(updated.userId)
          .collection('prop_firm_challenges')
          .doc(id);
      final exists = (await reference.get()).exists;
      await reference.set(
        updated.toFirestore(includeCreatedAt: !exists),
        SetOptions(merge: true),
      );
      return updated;
    }

    final items =
        storage?.readList(updated.userId, 'prop_firm_challenges') ?? const [];
    final next = [
      for (final item in items)
        if ((item['id'] ?? item['challenge_id']) != id) item,
      {
        ...updated.toJson(),
        'id': id,
        'created_at': DateTime.now().toIso8601String(),
        'updated_at': DateTime.now().toIso8601String(),
      },
    ];
    await storage?.writeList(updated.userId, 'prop_firm_challenges', next);
    return updated;
  }

  Stream<List<CapitalTransaction>> capitalsFor(String uid) {
    if (_usingApi) {
      final controller = _capitalControllers.putIfAbsent(
        uid,
        () => StreamController<List<CapitalTransaction>>.broadcast(),
      );
      _restoreTradingCache(uid);
      scheduleMicrotask(_refreshTradingData);
      return controller.stream;
    }
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('capitals')
          .orderBy('deposit_date', descending: true)
          .snapshots()
          .map(
            (snapshot) => snapshot.docs
                .map(CapitalTransaction.fromFirestore)
                .toList(growable: false),
          );
    }

    final controller = _capitalControllers.putIfAbsent(
      uid,
      () => StreamController<List<CapitalTransaction>>.broadcast(),
    );
    scheduleMicrotask(() => _emitCapitals(uid));
    return controller.stream;
  }

  Stream<TraderAccountProfile> accountProfileFor(String uid) {
    if (_usingApi) {
      final controller = _accountControllers.putIfAbsent(
        uid,
        () => StreamController<TraderAccountProfile>.broadcast(),
      );
      scheduleMicrotask(() => _refreshAccount(uid));
      return controller.stream;
    }
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('trader_onboarding')
          .doc('profile')
          .snapshots()
          .map(
            (snapshot) => snapshot.exists
                ? TraderAccountProfile.fromFirestore(snapshot)
                : TraderAccountProfile.empty(uid),
          );
    }

    final controller = _accountControllers.putIfAbsent(
      uid,
      () => StreamController<TraderAccountProfile>.broadcast(),
    );
    scheduleMicrotask(() => _emitAccount(uid));
    return controller.stream;
  }

  Stream<List<TradingAccount>> tradingAccountsFor(String uid) {
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_accounts')
          .snapshots()
          .map((snapshot) {
            final accounts = snapshot.docs
                .map(TradingAccount.fromFirestore)
                .where((account) => account.status != 'archived')
                .toList();
            accounts.sort((left, right) {
              if (left.isPrimary != right.isPrimary) {
                return left.isPrimary ? -1 : 1;
              }
              return left.displayName.compareTo(right.displayName);
            });
            return accounts;
          });
    }
    final controller = _tradingAccountControllers.putIfAbsent(
      uid,
      () => StreamController<List<TradingAccount>>.broadcast(),
    );
    final cached =
        storage?.readList(uid, 'cached_trading_accounts') ?? const [];
    if ((_localTradingAccounts[uid] ?? const []).isEmpty && cached.isNotEmpty) {
      _localTradingAccounts[uid] = cached
          .map(TradingAccount.fromJson)
          .toList(growable: false);
      scheduleMicrotask(() => _emitTradingAccounts(uid));
    }
    if (_usingApi) {
      scheduleMicrotask(() => _refreshTradingAccounts(uid));
    }
    return controller.stream;
  }

  Stream<List<TradingPositionApplicationMobile>> positionApplicationsFor(
    String uid, {
    required String mt5AccountId,
  }) {
    if (_usingApi) {
      final controller = _positionControllers.putIfAbsent(
        uid,
        () =>
            StreamController<
              List<TradingPositionApplicationMobile>
            >.broadcast(),
      );
      scheduleMicrotask(() => _refreshPositions(uid));
      return controller.stream.map(
        (items) => filterPositionApplicationsForMt5(items, mt5AccountId),
      );
    }
    if (firebaseEnabled) {
      if (mt5AccountId.trim().isEmpty) return Stream.value(const []);
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_position_applications')
          .where('mt5_account_id', isEqualTo: mt5AccountId.trim())
          .snapshots()
          .map((snapshot) {
            final applications = snapshot.docs
                .map(TradingPositionApplicationMobile.fromFirestore)
                .toList();
            applications.sort(
              (left, right) => (right.submittedAt ?? DateTime(1900)).compareTo(
                left.submittedAt ?? DateTime(1900),
              ),
            );
            return applications;
          });
    }

    final controller = _positionControllers.putIfAbsent(
      uid,
      () =>
          StreamController<List<TradingPositionApplicationMobile>>.broadcast(),
    );
    scheduleMicrotask(() => _emitPositions(uid));
    return controller.stream.map(
      (items) => filterPositionApplicationsForMt5(items, mt5AccountId),
    );
  }

  Stream<List<TradingBlogPost>> tradingBlogsStream() {
    if (_blogApi != null) {
      _startBlogApiSync();
      return _ReplayLatestStream<List<TradingBlogPost>>(
        source: _blogsController.stream,
        hasLatest: () => _blogApiHasLoaded || _localBlogs.isNotEmpty,
        latest: () => List<TradingBlogPost>.unmodifiable(_localBlogs),
      );
    }
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _blogsController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('trading_blogs').snapshots().map((snapshot) {
        final blogs = snapshot.docs
            .map(TradingBlogPost.fromFirestore)
            .where((blog) => blog.status == 'published')
            .toList();
        blogs.sort(
          (left, right) => (right.publishedAt ?? DateTime(1900)).compareTo(
            left.publishedAt ?? DateTime(1900),
          ),
        );
        return blogs;
      });
    }

    scheduleMicrotask(_emitBlogs);
    return _blogsController.stream;
  }

  Future<void> refreshTradingBlogs() => _refreshBlogsFromApi(force: true);

  bool get tradingBlogsUsingCachedFallback => _blogUsingCachedFallback;

  String? get tradingBlogSyncErrorMessage => _blogSyncErrorMessage;

  DateTime? get tradingBlogLastSyncedAt => _blogLastSyncedAt;

  bool get tradingBlogCacheExpired {
    final syncedAt = _blogLastSyncedAt;
    return syncedAt == null ||
        DateTime.now().toUtc().difference(syncedAt.toUtc()) >
            _blogCacheFreshFor;
  }

  Map<String, String> get tradingBlogCategoryLabels =>
      Map<String, String>.unmodifiable(_blogCategoryLabels);

  Map<String, int> get tradingBlogCategoryCounts =>
      Map<String, int>.unmodifiable(_blogCategoryCounts);

  Future<TradingBlogPost> loadTradingBlogDetail(
    TradingBlogPost fallback,
  ) async {
    if (_blogApi == null || fallback.id.trim().isEmpty) return fallback;
    final row = await _blogApi.fetchBlog(fallback.id);
    final refreshed = fallback.mergeJson(row);
    final index = _localBlogs.indexWhere((blog) => blog.id == refreshed.id);
    if (index < 0) {
      _localBlogs.insert(0, refreshed);
    } else {
      _localBlogs[index] = refreshed;
    }
    _emitBlogs();
    return refreshed;
  }

  Stream<List<MarketAnalysisItem>> marketAnalysesStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _analysesController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('market_analyses').snapshots().map((
        snapshot,
      ) {
        final analyses = snapshot.docs
            .where(
              (document) =>
                  document.data()['deleted_at'] == null &&
                  document.data()['status'] != 'archived',
            )
            .map(MarketAnalysisItem.fromFirestore)
            .toList();
        analyses.sort(
          (left, right) => (right.analysisDate ?? DateTime(1900)).compareTo(
            left.analysisDate ?? DateTime(1900),
          ),
        );
        return analyses;
      });
    }

    scheduleMicrotask(_emitAnalyses);
    return _analysesController.stream;
  }

  Future<MarketAnalystProfile> marketAnalystProfile({
    required String analystId,
    String fallbackName = '',
  }) async {
    if (!firebaseEnabled || analystId.trim().isEmpty) {
      return MarketAnalystProfile(
        userId: analystId,
        displayName: fallbackName.trim().isEmpty
            ? 'Market Analyst'
            : fallbackName.trim(),
      );
    }
    Map<String, dynamic> profile = const {};
    Map<String, dynamic> user = const {};
    try {
      final snapshot = await _firestore
          .collection('market_analyst_profiles')
          .doc(analystId)
          .get();
      profile = snapshot.data() ?? const {};
    } on FirebaseException catch (error) {
      debugPrint('Analyst profile unavailable (${error.code}).');
    }
    try {
      final snapshot = await _firestore
          .collection('users')
          .doc(analystId)
          .get();
      user = snapshot.data() ?? const {};
    } on FirebaseException catch (error) {
      debugPrint('Analyst account summary unavailable (${error.code}).');
    }
    if (fallbackName.trim().isNotEmpty &&
        '${profile['display_name'] ?? user['name'] ?? ''}'.trim().isEmpty) {
      profile = {...profile, 'display_name': fallbackName.trim()};
    }
    return MarketAnalystProfile.fromMaps(
      userId: analystId,
      profile: profile,
      user: user,
    );
  }

  Stream<bool> isFollowingMarketAnalyst({
    required String analystId,
    required String userId,
  }) {
    if (!firebaseEnabled || analystId.isEmpty || userId.isEmpty) {
      return Stream.value(false);
    }
    return _firestore
        .collection('market_analysts')
        .doc(analystId)
        .collection('followers')
        .doc(userId)
        .snapshots()
        .map((snapshot) => snapshot.exists);
  }

  Future<bool> toggleMarketAnalystFollow({
    required String analystId,
    required bool follow,
  }) async {
    if (!firebaseEnabled || analystId.trim().isEmpty) {
      throw const ApiException('Analyst follow is unavailable.');
    }
    try {
      final result = await _callableService.call('toggleMarketAnalystFollow', {
        'analystId': analystId.trim(),
        'follow': follow,
      });
      return result['following'] == true;
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(error.message);
    }
  }

  Stream<List<TradingSignalItem>> tradingSignalsStream() {
    if (_signalApi != null) {
      _startSignalApiSync();
      return _ReplayLatestStream<List<TradingSignalItem>>(
        source: _signalsController.stream,
        hasLatest: () => _localSignals.isNotEmpty,
        latest: () => List<TradingSignalItem>.unmodifiable(_localSignals),
      );
    }
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _signalsController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('trading_signals').snapshots().map((
        snapshot,
      ) {
        final signals = snapshot.docs
            .where((document) => document.data()['deleted_at'] == null)
            .map(TradingSignalItem.fromFirestore)
            .toList();
        signals.sort(
          (left, right) => (right.triggerTime ?? DateTime(1900)).compareTo(
            left.triggerTime ?? DateTime(1900),
          ),
        );
        return signals;
      });
    }

    scheduleMicrotask(_emitSignals);
    return _signalsController.stream;
  }

  Stream<TradingSignalItem?> tradingSignalStream(String signalId) {
    final id = signalId.trim();
    if (id.isEmpty) return Stream.value(null);
    if (_signalApi != null || !firebaseEnabled) {
      return tradingSignalsStream().map(
        (signals) => signals.where((signal) => signal.id == id).firstOrNull,
      );
    }
    return _firestore
        .collection('trading_signals')
        .doc(id)
        .snapshots()
        .map(
          (document) => document.exists
              ? TradingSignalItem.fromFirestore(document)
              : null,
        );
  }

  Stream<SignalOperationsMetrics> signalOperationsMetricsStream(AppUser actor) {
    final authorization = PublishingAuthorization(actor);
    return tradingSignalsStream().map((signals) {
      final visible = authorization.canManageAllTradingSignals
          ? signals
          : signals
                .where((signal) => signal.providerId == actor.uid)
                .toList(growable: false);
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final recentThreshold = now.subtract(const Duration(hours: 24));
      bool recent(DateTime? value) =>
          value != null && !value.isBefore(recentThreshold);
      final open = visible.where((signal) => signal.lifecycle.isOpen).toList();
      return SignalOperationsMetrics(
        total: visible.length,
        active: visible.where((signal) {
          return const {
            SignalLifecycleStatus.active,
            SignalLifecycleStatus.partiallyClosed,
            SignalLifecycleStatus.breakEven,
            SignalLifecycleStatus.trailing,
          }.contains(signal.lifecycle);
        }).length,
        pendingEntry: visible
            .where(
              (signal) =>
                  signal.lifecycle == SignalLifecycleStatus.pendingEntry ||
                  signal.lifecycle == SignalLifecycleStatus.published,
            )
            .length,
        recentTargetHits: visible
            .where(
              (signal) =>
                  signal.lastUpdateType == SignalUpdateType.targetHit.value &&
                  recent(signal.updatedAt),
            )
            .length,
        nearExpiry: open
            .where(
              (signal) =>
                  signal.expiryTime != null &&
                  signal.expiryTime!.isAfter(now) &&
                  signal.expiryTime!.isBefore(
                    now.add(const Duration(hours: 24)),
                  ),
            )
            .length,
        stale: open
            .where(
              (signal) =>
                  (signal.updatedAt ?? signal.createdAt)?.isBefore(
                    recentThreshold,
                  ) ==
                  true,
            )
            .length,
        completedToday: visible
            .where(
              (signal) =>
                  signal.completedAt != null &&
                  !signal.completedAt!.isBefore(today),
            )
            .length,
        cancelled: visible
            .where(
              (signal) => signal.lifecycle == SignalLifecycleStatus.cancelled,
            )
            .length,
      );
    });
  }

  Stream<List<TradingSignalUpdate>> tradingSignalUpdatesStream(
    String signalId,
  ) {
    final id = signalId.trim();
    if (id.isEmpty || _signalApi != null || !firebaseEnabled) {
      return Stream.value(const []);
    }
    return _firestore
        .collection('trading_signals')
        .doc(id)
        .collection('updates')
        .orderBy('created_at', descending: true)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map(TradingSignalUpdate.fromFirestore)
              .toList(growable: false),
        );
  }

  /// Applies a lifecycle event and its immutable history records in one
  /// transaction. The update document ID doubles as the idempotency key.
  Future<void> updateTradingSignal({
    required String signalId,
    required String publisherId,
    required SignalUpdateDraft update,
  }) async {
    if (_signalApi != null) {
      await _signalApi.updateLifecycle(
        signalId: signalId,
        payload: <String, Object?>{
          'action': update.updateType.value,
          'idempotency_key': update.idempotencyKey,
          'expected_version': update.expectedVersion,
          if (update.titleI18n.isNotEmpty) 'title_i18n': update.titleI18n,
          if (update.messageI18n.isNotEmpty) 'message_i18n': update.messageI18n,
          if (update.primaryLanguage?.trim().isNotEmpty == true)
            'primary_language': update.primaryLanguage!.trim(),
          if (update.reason.trim().isNotEmpty) 'reason': update.reason.trim(),
          if (update.entryPrice?.trim().isNotEmpty == true)
            'entry_price': update.entryPrice!.trim(),
          if (update.entryMin?.trim().isNotEmpty == true)
            'entry_price_min': update.entryMin!.trim(),
          if (update.entryMax?.trim().isNotEmpty == true)
            'entry_price_max': update.entryMax!.trim(),
          if (update.newStopLoss?.trim().isNotEmpty == true)
            'stop_loss': update.newStopLoss!.trim(),
          if (update.breakEvenPrice?.trim().isNotEmpty == true)
            'break_even_price': update.breakEvenPrice!.trim(),
          if (update.targetNumber != null) 'target_number': update.targetNumber,
          if (update.targetPrice?.trim().isNotEmpty == true)
            'target_price': update.targetPrice!.trim(),
          if (update.percentageClosed != null)
            'percentage_closed': update.percentageClosed!.value,
          if (update.percentageRemaining != null)
            'percentage_remaining': update.percentageRemaining!.value,
          if (update.realisedPips?.trim().isNotEmpty == true)
            'realised_pips': update.realisedPips!.trim(),
          if (update.realisedPercentage?.trim().isNotEmpty == true)
            'realised_percentage': update.realisedPercentage!.trim(),
          if (update.profitLockOffset?.trim().isNotEmpty == true)
            'profit_lock_offset': update.profitLockOffset!.trim(),
          if (update.entryWasTriggered != null)
            'entry_was_triggered': update.entryWasTriggered,
          if (update.attachmentUrls.isNotEmpty)
            'attachment_urls': update.attachmentUrls,
          if (update.effectiveAt != null)
            'effective_at': update.effectiveAt!.toUtc().toIso8601String(),
          'notification_requested': update.notificationRequested,
          'is_correction': update.isCorrection,
          if (update.correctedUpdateId.trim().isNotEmpty)
            'corrected_update_id': update.correctedUpdateId.trim(),
          if (update.lifecycleOverride != null)
            'lifecycle_override': update.lifecycleOverride!.value,
          if (update.timeframe?.trim().isNotEmpty == true)
            'timeframe': update.timeframe!.trim(),
          if (update.riskLevel?.trim().isNotEmpty == true)
            'risk_level': update.riskLevel!.trim(),
          if (update.expiryAt != null)
            'expires_at': update.expiryAt!.toUtc().toIso8601String(),
        },
      );
      await _refreshSignalsFromApi(force: true);
      return;
    }
    if (!firebaseEnabled) {
      throw const ApiException(
        'Firebase is required to update trading signals.',
      );
    }
    final actor = await _verifiedPublisher(
      publisherId: publisherId,
      operation: 'TradingSignalUpdate',
      permission: (authorization) => authorization.canManageTradingSignals,
    );
    final authorization = PublishingAuthorization(actor);
    final signalReference = _firestore
        .collection('trading_signals')
        .doc(signalId.trim());
    final updateId = _signalUpdateDocumentId(update.idempotencyKey);
    final updateReference = signalReference.collection('updates').doc(updateId);
    final auditReference = signalReference
        .collection('audit_logs')
        .doc(updateId);

    try {
      await _firestore.runTransaction<void>((transaction) async {
        final signalDocument = await transaction.get(signalReference);
        if (!signalDocument.exists) {
          throw const SignalLifecycleException('Trading signal not found.');
        }
        final existingUpdate = await transaction.get(updateReference);
        if (existingUpdate.exists) return;

        final data = signalDocument.data() ?? const <String, dynamic>{};
        final ownerIds = <String>{
          for (final value in [
            data['created_by'],
            data['created_by_id'],
            data['provider_id'],
            data['assigned_provider_id'],
            data['assignedProviderId'],
            data['user_id'],
          ])
            if (value != null && '$value'.trim().isNotEmpty) '$value'.trim(),
          ...firestoreStringList(data['assigned_provider_ids']),
          ...firestoreStringList(data['assignedProviderIds']),
        };
        final ownsSignal = ownerIds.contains(actor.uid);
        if (!authorization.canManageAllTradingSignals &&
            !(authorization.canUpdateOwnTradingSignals && ownsSignal)) {
          throw const SignalLifecycleException(
            'You can update only signals created by or assigned to your account.',
          );
        }

        final snapshot = SignalLifecycleSnapshot.fromMap(data);
        final projection = SignalLifecyclePolicy.project(
          snapshot,
          update,
          superAdmin: actor.isSuperAdmin,
        );
        final contentLanguages = update.contentLanguages.toList()..sort();
        final primaryLanguage =
            update.primaryLanguage?.toLowerCase().startsWith('zh') == true
            ? 'zh'
            : update.primaryLanguage?.trim().isNotEmpty == true
            ? 'en'
            : contentLanguages.firstOrNull ??
                  _publishingLanguage(data['primary_language']);
        final roleIds = actor.effectiveRoleIds.toList()..sort();
        final previousValues = <String, Object?>{
          ...projection.previousValues,
          if (update.updateType == SignalUpdateType.detailsUpdated) ...{
            'signal_title': data['signal_title'] ?? data['title'],
            'signal_title_i18n':
                data['signal_title_i18n'] ?? data['title_i18n'],
            'timeframe': data['timeframe'],
            'risk_level': data['risk_level'],
            'expiry_time': data['expiry_time'],
          },
        };
        final newValues = <String, Object?>{
          ...projection.newValues,
          if (update.updateType == SignalUpdateType.detailsUpdated)
            for (final key in const {
              'signal_title',
              'signal_title_i18n',
              'timeframe',
              'risk_level',
              'expiry_time',
            })
              if (projection.signalChanges.containsKey(key))
                key: projection.signalChanges[key],
        };
        final effectiveAt = update.effectiveAt == null
            ? FieldValue.serverTimestamp()
            : Timestamp.fromDate(update.effectiveAt!);
        final updateData = <String, Object?>{
          'update_id': updateId,
          'signal_id': signalReference.id,
          'update_type': update.updateType.value,
          'lifecycle_from': projection.from.value,
          'lifecycle_to': projection.to.value,
          'version_from': snapshot.version,
          'version_to': snapshot.version + 1,
          'primary_language': primaryLanguage,
          'available_languages': contentLanguages,
          if (update.titleI18n.isNotEmpty) 'title_i18n': update.titleI18n,
          if (update.messageI18n.isNotEmpty) 'message_i18n': update.messageI18n,
          if (update.reason.trim().isNotEmpty) 'reason': update.reason.trim(),
          if (update.targetNumber != null) 'target_number': update.targetNumber,
          if (update.targetPrice?.trim().isNotEmpty == true)
            'target_price': update.targetPrice!.trim(),
          if (update.percentageClosed != null)
            'percentage_closed': update.percentageClosed!.value,
          if (update.percentageRemaining != null)
            'percentage_remaining': update.percentageRemaining!.value,
          if (update.realisedPips?.trim().isNotEmpty == true)
            'realised_pips': update.realisedPips!.trim(),
          if (update.realisedPercentage?.trim().isNotEmpty == true)
            'realised_percentage': update.realisedPercentage!.trim(),
          if (update.profitLockOffset?.trim().isNotEmpty == true)
            'profit_lock_offset': update.profitLockOffset!.trim(),
          if (update.entryWasTriggered != null)
            'entry_was_triggered': update.entryWasTriggered,
          if (update.attachmentUrls.isNotEmpty)
            'attachment_urls': update.attachmentUrls,
          'previous_values': previousValues,
          'new_values': newValues,
          'effective_at': effectiveAt,
          'created_at': FieldValue.serverTimestamp(),
          'created_by': actor.uid,
          'created_by_name': actor.label,
          'created_by_role': actor.roleName,
          'created_by_role_ids': roleIds,
          'notification_requested': update.notificationRequested,
          'notification_sent': false,
          'is_correction': update.isCorrection,
          if (update.correctedUpdateId.trim().isNotEmpty)
            'corrected_update_id': update.correctedUpdateId.trim(),
        };

        transaction.update(signalReference, {
          ...projection.signalChanges,
          'last_update_id': updateId,
          'last_audit_id': updateId,
          'last_update_type': update.updateType.value,
          'last_updated_by': actor.uid,
          'last_updated_by_name': actor.label,
          'last_updated_by_role_ids': roleIds,
          'updated_by': actor.uid,
          'updated_at': FieldValue.serverTimestamp(),
        });
        transaction.set(updateReference, updateData);
        transaction.set(auditReference, {
          'audit_id': updateId,
          'signal_id': signalReference.id,
          'update_id': updateId,
          'action': update.updateType.value,
          'actor_id': actor.uid,
          'actor_name': actor.label,
          'actor_role_ids': roleIds,
          'lifecycle_from': projection.from.value,
          'lifecycle_to': projection.to.value,
          'version_from': snapshot.version,
          'version_to': snapshot.version + 1,
          'old_values': previousValues,
          'new_values': newValues,
          'is_correction': update.isCorrection,
          'platform': 'flutter',
          'created_at': FieldValue.serverTimestamp(),
        });
      });
    } on SignalLifecycleException {
      rethrow;
    } on FirebaseException catch (error, stackTrace) {
      _debugPublisherFailure('TradingSignalUpdate', error, stackTrace);
      throw ApiException(_publishingFirebaseMessage(error, 'trading signal'));
    }
  }

  /// Loads the role-scoped projection used by the trade-call statistics page.
  /// Provider ownership is enforced again by the callable; the public signal
  /// stream is intentionally not used for private provider comparisons.
  Future<List<TradingSignalItem>> loadTradeCallStatisticsSignals() async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Trade-call statistics require a Firebase connection.',
      );
    }
    try {
      final calls = <TradingSignalItem>[];
      String? pageToken;
      do {
        final result = await _callableService.call(
          'loadTradeCallStatistics',
          <String, dynamic>{'pageSize': 500, 'pageToken': ?pageToken},
        );
        final rawCalls = result['calls'];
        if (rawCalls is List) {
          calls.addAll(
            rawCalls.whereType<Map>().map(
              (value) =>
                  TradingSignalItem.fromJson(Map<String, dynamic>.from(value)),
            ),
          );
        }
        final rawNextPageToken = result['nextPageToken']?.toString().trim();
        pageToken = rawNextPageToken == null || rawNextPageToken.isEmpty
            ? null
            : rawNextPageToken;
      } while (pageToken != null);
      calls.sort(
        (left, right) =>
            (right.closedAt ??
                    right.updatedAt ??
                    right.triggerTime ??
                    DateTime(1900))
                .compareTo(
                  left.closedAt ??
                      left.updatedAt ??
                      left.triggerTime ??
                      DateTime(1900),
                ),
      );
      return List<TradingSignalItem>.unmodifiable(calls);
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(error.message);
    }
  }

  Future<void> publishTradingSignal({
    required TradingSignalItem signal,
    required String publisherId,
    String? idempotencyKey,
  }) async {
    if (_signalApi != null) {
      final orderType = SignalOrderType.parse(signal.orderType);
      SignalLifecyclePolicy.validatePublication(
        direction: signal.immediateAction,
        orderType: orderType,
        entryPrice: signal.entryPrice,
        stopLoss: signal.stopLoss,
        targets: signal.targets,
      );
      final requestId = idempotencyKey?.trim().isNotEmpty == true
          ? idempotencyKey!.trim()
          : _newSignalIdempotencyKey(publisherId);
      await _signalApi.submitSignal(
        idempotencyKey: requestId,
        payload: <String, Object?>{
          'symbol': signal.tradingPair.trim().toUpperCase(),
          'market': _marketForSignal(signal),
          'signal_type': signal.immediateAction.trim().toUpperCase(),
          'order_type': orderType.value,
          'entry_price': signal.entryPrice.trim(),
          'stop_loss': signal.stopLoss.trim(),
          for (
            var index = 0;
            index < signal.targets.length && index < 10;
            index++
          )
            'take_profit_${index + 1}': signal.targets[index].trim(),
          if (signal.riskLevel.trim().isNotEmpty)
            'risk_level': signal.riskLevel.trim().toUpperCase(),
          if (signal.timeframe.trim().isNotEmpty)
            'timeframe': signal.timeframe.trim().toUpperCase(),
          if (signal.title.trim().isNotEmpty) 'strategy': signal.title.trim(),
          if (signal.disclaimer.trim().isNotEmpty)
            'description': signal.disclaimer.trim(),
          if (signal.expiryTime != null)
            'expires_at': signal.expiryTime!.toUtc().toIso8601String(),
          if (signal.link.trim().isNotEmpty) 'link': signal.link.trim(),
          if (signal.chartImage.trim().isNotEmpty)
            'chart_image_url': signal.chartImage.trim(),
        },
      );
      await _refreshSignalsFromApi(force: true);
      return;
    }
    if (!firebaseEnabled) {
      throw const ApiException(
        'Firebase is required to publish trading signals.',
      );
    }
    final publisher = await _verifiedPublisher(
      publisherId: publisherId,
      operation: 'TradingSignalPublish',
      permission: (authorization) => authorization.canPublishTradingSignal,
    );
    final document = _firestore.collection('trading_signals').doc();
    final orderType = SignalOrderType.parse(signal.orderType);
    SignalLifecyclePolicy.validatePublication(
      direction: signal.immediateAction,
      orderType: orderType,
      entryPrice: signal.entryPrice,
      stopLoss: signal.stopLoss,
      targets: signal.targets,
    );
    final lifecycle = orderType.isPending
        ? SignalLifecycleStatus.pendingEntry
        : SignalLifecycleStatus.active;
    const publicationUpdateId = 'publication-v0';
    final roleIds = publisher.effectiveRoleIds.toList()..sort();
    final publicationLanguages =
        <String>{
            ...signal.availableLanguages.map(_publishingLanguage),
            _publishingLanguage(signal.primaryLanguage),
            ...signal.titleI18n.keys.map(_publishingLanguage),
            ...signal.disclaimerI18n.keys.map(_publishingLanguage),
          }.where((language) => language == 'en' || language == 'zh').toList()
          ..sort();
    final originalEntry = signal.originalEntryPrice.trim().isNotEmpty
        ? signal.originalEntryPrice.trim()
        : signal.entryPrice.trim();
    final originalStop = signal.originalStopLoss.trim().isNotEmpty
        ? signal.originalStopLoss.trim()
        : signal.stopLoss.trim();
    try {
      await _firestore.runTransaction<void>((transaction) async {
        final signalData = <String, Object?>{
          ...signal.toFirestore(),
          'created_by': publisher.uid,
          'provider_id': publisher.uid,
          'published_by': publisher.uid,
          'published_by_name': publisher.label,
          'published_by_role_ids': roleIds,
          'order_type': orderType.value,
          'lifecycle_status': lifecycle.value,
          'status': lifecycle == SignalLifecycleStatus.active ? 1 : 0,
          'original_entry_price': originalEntry,
          'current_entry_price': signal.entryPrice.trim(),
          'original_stop_loss': originalStop,
          'current_stop_loss': signal.stopLoss.trim(),
          'targets_hit': <int>[],
          'current_target_index': 0,
          'percentage_remaining': 100,
          'version': 0,
          'last_update_id': publicationUpdateId,
          'last_audit_id': publicationUpdateId,
          'last_update_type': 'published',
          'published_at': FieldValue.serverTimestamp(),
          'created_at': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        };
        final initialValues = <String, Object?>{
          'lifecycle_status': lifecycle.value,
          'entry_price': signal.entryPrice,
          'stop_loss': signal.stopLoss,
          'percentage_remaining': 100,
          'version': 0,
        };
        transaction.set(document, signalData);
        transaction
            .set(document.collection('updates').doc(publicationUpdateId), {
              'update_id': publicationUpdateId,
              'signal_id': document.id,
              'update_type': 'published',
              'lifecycle_from': SignalLifecycleStatus.draft.value,
              'lifecycle_to': lifecycle.value,
              'version_from': -1,
              'version_to': 0,
              'primary_language': _publishingLanguage(signal.primaryLanguage),
              'available_languages': publicationLanguages,
              'new_values': initialValues,
              'effective_at': FieldValue.serverTimestamp(),
              'created_at': FieldValue.serverTimestamp(),
              'created_by': publisher.uid,
              'created_by_name': publisher.label,
              'created_by_role': publisher.roleName,
              'created_by_role_ids': roleIds,
              'notification_requested': false,
              'notification_sent': false,
              'is_correction': false,
            });
        transaction
            .set(document.collection('audit_logs').doc(publicationUpdateId), {
              'audit_id': publicationUpdateId,
              'signal_id': document.id,
              'update_id': publicationUpdateId,
              'action': 'published',
              'actor_id': publisher.uid,
              'actor_name': publisher.label,
              'actor_role_ids': roleIds,
              'lifecycle_from': SignalLifecycleStatus.draft.value,
              'lifecycle_to': lifecycle.value,
              'version_from': -1,
              'version_to': 0,
              'new_values': initialValues,
              'is_correction': false,
              'platform': 'flutter',
              'created_at': FieldValue.serverTimestamp(),
            });
      });
      _debugPublisherOperation(
        'TradingSignalPublish',
        stage: 'firestoreWriteResult',
        value: 'success',
      );
    } on FirebaseException catch (error, stackTrace) {
      _debugPublisherFailure('TradingSignalPublish', error, stackTrace);
      throw ApiException(_publishingFirebaseMessage(error, 'trading signal'));
    }
    await _writeAudit(
      action: 'create',
      module: 'trading_signals',
      targetType: 'trading_signal',
      targetId: document.id,
      newValues: {'title': signal.title, 'publisher_id': publisher.uid},
    );
  }

  Future<void> publishMarketAnalysis({
    required MarketAnalysisItem analysis,
    required String publisherId,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Firebase is required to publish market analysis.',
      );
    }
    final publisher = await _verifiedPublisher(
      publisherId: publisherId,
      operation: 'MarketAnalysisPublish',
      permission: (authorization) => authorization.canPublishMarketAnalysis,
    );
    final document = _firestore.collection('market_analyses').doc();
    try {
      await document.set({
        ...analysis.toFirestore(),
        'created_by': publisher.uid,
        'author_name': publisher.label,
        'status': 'published',
        'is_published': true,
        'published_by': publisher.uid,
        'published_at': FieldValue.serverTimestamp(),
        'created_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      });
      _debugPublisherOperation(
        'MarketAnalysisPublish',
        stage: 'firestoreWriteResult',
        value: 'success',
      );
    } on FirebaseException catch (error, stackTrace) {
      _debugPublisherFailure('MarketAnalysisPublish', error, stackTrace);
      throw ApiException(_publishingFirebaseMessage(error, 'market analysis'));
    }
    await _writeAudit(
      action: 'create',
      module: 'market_analyses',
      targetType: 'market_analysis',
      targetId: document.id,
      newValues: {'title': analysis.title, 'publisher_id': publisher.uid},
    );
  }

  Future<void> publishTradingBlog({
    required TradingBlogPost blog,
    required String publisherId,
    String? idempotencyKey,
  }) async {
    if (_blogApi != null) {
      await _blogApi.submitBlog(
        idempotencyKey: idempotencyKey?.trim().isNotEmpty == true
            ? idempotencyKey!.trim()
            : _newBlogIdempotencyKey(publisherId),
        payload: <String, Object?>{
          'title': blog.title.trim(),
          'category': blog.category,
          if (blog.excerpt.trim().isNotEmpty) 'excerpt': blog.excerpt.trim(),
          'content': blog.content.trim(),
          if (blog.coverImage.trim().isNotEmpty)
            'cover_image_url': blog.coverImage.trim(),
          if (blog.tags.isNotEmpty) 'tags': blog.tags,
          'status': blog.status,
          'is_featured': blog.isFeatured,
          if (blog.publishedAt != null)
            'published_at': blog.publishedAt!.toUtc().toIso8601String(),
        },
      );
      await _refreshBlogsFromApi(force: true);
      return;
    }
    if (!firebaseEnabled) {
      throw const ApiException(
        'Firebase is required to publish trading blogs.',
      );
    }
    final document = _firestore.collection('trading_blogs').doc();
    await document.set({
      ...blog.toFirestore(),
      'created_by': publisherId,
      'published_by': publisherId,
      'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    });
    await _writeAudit(
      action: 'create',
      module: 'trading_blogs',
      targetType: 'trading_blog',
      targetId: document.id,
      newValues: {'title': blog.title, 'publisher_id': publisherId},
    );
  }

  Future<String> uploadContentImage({
    required String area,
    required ApiUploadFile file,
  }) async {
    if (area == 'trading_blogs' && _blogApi != null) {
      return _blogApi.uploadImage(file);
    }
    final upload = await uploadManagedFile(area: area, file: file);
    return '${upload['url']}';
  }

  Future<void> deleteContentImage(String downloadUrl) async {
    final url = downloadUrl.trim();
    if (!firebaseEnabled || url.isEmpty) return;
    try {
      await FirebaseStorage.instance.refFromURL(url).delete();
    } on FirebaseException catch (error, stackTrace) {
      if (kDebugMode) {
        debugPrint(
          '[PublisherImageCleanup] code=${error.code} '
          'message=${error.message ?? 'unknown'}\n$stackTrace',
        );
      }
    }
  }

  Future<Map<String, Object?>> uploadManagedFile({
    required String area,
    required ApiUploadFile file,
    String? ownerId,
  }) async {
    const imageOnlyAreas = {
      'market_analyses',
      'trading_signals',
      'profile_images',
    };
    const documentAreas = {
      'knowledge_centre',
      'community_documents',
      'recording_materials',
      'support_tickets',
    };
    const managedContentAreas = {'admin_assets'};
    if (!firebaseEnabled ||
        !imageOnlyAreas.contains(area) &&
            !documentAreas.contains(area) &&
            !managedContentAreas.contains(area)) {
      throw const ApiException('This upload destination is not available.');
    }
    final contentType = _contentType(file.filename);
    final isImage = contentType.startsWith('image/');
    final isPdf = contentType == 'application/pdf';
    final isDocument = const {
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    }.contains(contentType);
    final isVideo = contentType == 'video/mp4';
    if (imageOnlyAreas.contains(area) && !isImage) {
      throw const ApiException('Choose a JPG, PNG, WEBP, or GIF image.');
    }
    if (documentAreas.contains(area) && !isImage && !isPdf) {
      throw const ApiException('Choose a PDF, JPG, PNG, WEBP, or GIF file.');
    }
    if (managedContentAreas.contains(area) &&
        !isImage &&
        !isPdf &&
        !isDocument &&
        !isVideo) {
      throw const ApiException('Choose an image, PDF, DOC, DOCX, or MP4 file.');
    }
    final bytes = await _uploadBytes(file);
    if (bytes.isEmpty) throw const ApiException('The selected file is empty.');
    final maximumBytes = isVideo
        ? 100 * 1024 * 1024
        : isImage
        ? 8 * 1024 * 1024
        : 10 * 1024 * 1024;
    if (bytes.length > maximumBytes) {
      throw ApiException(
        isImage
            ? 'Images must be smaller than 8 MB.'
            : 'PDF files must be smaller than 10 MB.',
      );
    }
    final uid = _firebaseUid();
    final resolvedOwner = ownerId?.trim().isNotEmpty == true ? ownerId! : uid;
    final storagePath =
        '$area/$resolvedOwner/${DateTime.now().microsecondsSinceEpoch}_${_safeName(file.filename)}';
    final reference = FirebaseStorage.instance.ref(storagePath);
    try {
      await reference.putData(
        bytes,
        SettableMetadata(
          contentType: contentType,
          customMetadata: {
            'uploaded_by': uid,
            'owner_uid': resolvedOwner,
            'content_area': area,
            'original_name': file.filename,
          },
        ),
      );
      return {
        'url': await reference.getDownloadURL(),
        'storage_path': storagePath,
        'name': file.filename,
        'mime_type': contentType,
        'size': bytes.length,
      };
    } on FirebaseException catch (error) {
      if (const {
        'bucket-not-found',
        'object-not-found',
        'project-not-found',
      }.contains(error.code)) {
        throw const ApiException(
          'Image storage is not active for this Firebase project yet.',
        );
      }
      throw ApiException(error.message ?? 'Unable to upload this image.');
    }
  }

  Stream<List<TradingResourceItem>> tradingResourcesStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _resourcesController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('trading_resources').snapshots().map((
        snapshot,
      ) {
        final resources = snapshot.docs
            .map(TradingResourceItem.fromFirestore)
            .where((resource) => resource.status)
            .toList();
        resources.sort(
          (left, right) => (right.createdAt ?? DateTime(1900)).compareTo(
            left.createdAt ?? DateTime(1900),
          ),
        );
        return resources;
      });
    }

    scheduleMicrotask(_emitResources);
    return _resourcesController.stream;
  }

  Stream<List<TradingExamQuestionMobile>> tradingExamQuestionsStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshExam);
      return _examQuestionsController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('trading_exam_questions').snapshots().map((
        snapshot,
      ) {
        final questions = snapshot.docs
            .map(TradingExamQuestionMobile.fromFirestore)
            .where(
              (question) =>
                  const {'approved', 'published'}.contains(question.status),
            )
            .where(
              (question) =>
                  !question.questionType.requiresChoice ||
                  question.options.length >= 2,
            )
            .toList();
        questions.sort(
          (left, right) => left.difficulty.compareTo(right.difficulty),
        );
        return questions;
      });
    }

    scheduleMicrotask(_emitExamQuestions);
    return _examQuestionsController.stream;
  }

  Stream<void> get adminMutations => _adminMutationController.stream;

  Stream<List<TradingExamAttemptMobile>> tradingExamAttemptsFor(String uid) {
    if (_usingApi) {
      final controller = _examAttemptControllers.putIfAbsent(
        uid,
        () => StreamController<List<TradingExamAttemptMobile>>.broadcast(),
      );
      scheduleMicrotask(() => _refreshExam(uid: uid));
      return controller.stream;
    }
    if (firebaseEnabled) {
      return _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_exam_attempts')
          .snapshots()
          .map((snapshot) {
            final attempts = snapshot.docs
                .map(TradingExamAttemptMobile.fromFirestore)
                .toList();
            attempts.sort(
              (left, right) => right.examDate.compareTo(left.examDate),
            );
            return attempts;
          });
    }

    final controller = _examAttemptControllers.putIfAbsent(
      uid,
      () => StreamController<List<TradingExamAttemptMobile>>.broadcast(),
    );
    scheduleMicrotask(() => _emitExamAttempts(uid));
    return controller.stream;
  }

  String dailyQuizAttemptId([DateTime? date]) {
    final day = _dateOnly(date ?? DateTime.now());
    return 'exam-${day.toIso8601String().substring(0, 10)}';
  }

  DailyQuizUploadTask startDailyQuizScreenshotUpload({
    required String uid,
    required String questionId,
    required String attemptId,
    required String filename,
    required Uint8List bytes,
  }) {
    if (!firebaseEnabled || _firebaseUid() != uid) {
      throw const ApiException(
        'Sign in again before uploading your quiz response.',
      );
    }
    return const DailyQuizUploadService().start(
      userId: uid,
      quizId: questionId,
      attemptId: attemptId,
      filename: filename,
      bytes: bytes,
    );
  }

  Stream<List<TradingRecordingClassMobile>> tradingRecordingsStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _recordingsController.stream;
    }
    if (firebaseEnabled) {
      return _firestore.collection('trading_recordings').snapshots().map((
        snapshot,
      ) {
        final recordings = snapshot.docs
            .map(TradingRecordingClassMobile.fromFirestore)
            .where(
              (recording) =>
                  recording.status && recording.approvalStatus == 'approved',
            )
            .toList();
        recordings.sort(
          (left, right) => (right.createdAt ?? DateTime(1900)).compareTo(
            left.createdAt ?? DateTime(1900),
          ),
        );
        return recordings;
      });
    }

    scheduleMicrotask(_emitRecordings);
    return _recordingsController.stream;
  }

  Stream<List<KnowledgeCentreItemMobile>> knowledgeCentreStream() {
    if (_usingApi) {
      scheduleMicrotask(_refreshContent);
      return _knowledgeController.stream;
    }
    if (firebaseEnabled) {
      return Stream<List<KnowledgeCentreItemMobile>>.multi((controller) {
        var primaryItems = <KnowledgeCentreItemMobile>[];
        var camelCaseItems = <KnowledgeCentreItemMobile>[];
        StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? primary;
        StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? camelCase;

        void emit() {
          final items = _preferredKnowledgeItems(primaryItems, camelCaseItems);
          controller.add(items);
        }

        primary = _firestore.collection('knowledge_centres').snapshots().listen(
          (snapshot) {
            primaryItems = _knowledgeItemsFromSnapshot(snapshot);
            emit();
          },
          onError: controller.addError,
        );

        // Some admin specs use camelCase. Treat it as a compatibility source,
        // but keep the existing snake_case collection as the canonical one.
        camelCase = _firestore
            .collection('knowledgeCentres')
            .snapshots()
            .listen((snapshot) {
              camelCaseItems = _knowledgeItemsFromSnapshot(snapshot);
              emit();
            }, onError: (_) {});

        controller.onCancel = () async {
          await primary?.cancel();
          await camelCase?.cancel();
        };
      });
    }

    scheduleMicrotask(_emitKnowledge);
    return _knowledgeController.stream;
  }

  Future<void> upsertJournal(TradingJournalEntry entry) async {
    if (_usingApi) {
      final payload = entry.toApiJson();
      final operationId = entry.id.isEmpty
          ? 'journal_${DateTime.now().microsecondsSinceEpoch}'
          : null;
      if (operationId != null) {
        await _enqueueJournal(entry.userId, operationId, payload);
      }
      try {
        if (entry.id.isEmpty) {
          await apiClient!.post('journals', body: payload);
        } else {
          await apiClient!.put('journals/${entry.id}', body: payload);
        }
        if (operationId != null) {
          await _removeQueuedJournal(entry.userId, operationId);
        }
      } on ApiException catch (error) {
        if (operationId != null && error.statusCode == null) {
          final pending = entry.copyWith(id: 'pending:$operationId');
          final entries = _localJournals.putIfAbsent(entry.userId, () => []);
          entries.removeWhere((item) => item.id == pending.id);
          entries.add(pending);
          await _persistTradingCache(entry.userId);
          _emitJournals(entry.userId);
          return;
        }
        if (operationId != null) {
          await _removeQueuedJournal(entry.userId, operationId);
        }
        rethrow;
      }
      await _refreshTradingData(force: true);
      return;
    }
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(entry.userId)
          .collection('trading_journals');

      if (entry.id.isEmpty) {
        await collection.add({
          ...entry.toFirestore(),
          'created_at': FieldValue.serverTimestamp(),
        });
      } else {
        await collection
            .doc(entry.id)
            .set(entry.toFirestore(), SetOptions(merge: true));
      }
      unawaited(
        synchronizeJournal(entry.userId, force: true).catchError((_) {}),
      );
      return;
    }

    final entries = _localJournals.putIfAbsent(entry.userId, () => []);
    final localEntry = entry.id.isEmpty
        ? entry.copyWith(id: _newLocalId('journal'))
        : entry;
    final existingIndex = entries.indexWhere(
      (journal) => journal.id == localEntry.id,
    );
    if (existingIndex >= 0) {
      entries[existingIndex] = localEntry;
    } else {
      entries.add(localEntry);
    }
    _emitJournals(entry.userId);
  }

  Future<void> deleteJournal({
    required String uid,
    required String journalId,
  }) async {
    if (_usingApi) {
      await apiClient!.delete('journals/$journalId');
      await _refreshTradingData(force: true);
      return;
    }
    if (firebaseEnabled) {
      final journalSync = _journalSyncService;
      if (journalSync != null) {
        await journalSync.queueDeletion(uid: uid, docId: journalId);
        unawaited(synchronizeJournal(uid, force: true).catchError((_) {}));
      } else {
        await _firestore
            .collection('users')
            .doc(uid)
            .collection('trading_journals')
            .doc(journalId)
            .delete();
      }
      return;
    }

    _localJournals[uid]?.removeWhere((journal) => journal.id == journalId);
    _emitJournals(uid);
  }

  Future<void> addCapitalTransaction(CapitalTransaction transaction) {
    return upsertCapitalTransaction(transaction);
  }

  Future<void> upsertCapitalTransaction(CapitalTransaction transaction) async {
    if (_usingApi) {
      if (transaction.id.isEmpty) {
        await apiClient!.post('capitals', body: transaction.toApiJson());
      } else {
        await apiClient!.put(
          'capitals/${transaction.id}',
          body: transaction.toApiJson(update: true),
        );
      }
      await _refreshTradingData(force: true);
      return;
    }
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(transaction.userId)
          .collection('capitals');
      final data = <String, Object?>{...transaction.toFirestore()};
      if (transaction.tradingAccountId == null) {
        try {
          final primary = await _firestore
              .collection('users')
              .doc(transaction.userId)
              .collection('trading_accounts')
              .where('is_primary', isEqualTo: true)
              .limit(1)
              .get();
          if (primary.docs.isNotEmpty) {
            data['trading_account_id'] = primary.docs.first.id;
          }
        } on FirebaseException catch (error) {
          // Trading accounts require approved client access, while manual
          // capital records are available to any active owner. An optional
          // primary-account lookup must not block a pre-approval deposit.
          if (error.code != 'permission-denied') {
            rethrow;
          }
        }
      }

      if (transaction.id.isEmpty) {
        await collection.add({
          ...data,
          'created_at': FieldValue.serverTimestamp(),
        });
      } else {
        await collection.doc(transaction.id).set(data, SetOptions(merge: true));
      }
      return;
    }

    final entries = _localCapitals.putIfAbsent(transaction.userId, () => []);
    final localTransaction = transaction.id.isEmpty
        ? transaction.copyWith(id: _newLocalId('capital'))
        : transaction;
    final existingIndex = entries.indexWhere(
      (capital) => capital.id == localTransaction.id,
    );
    if (existingIndex >= 0) {
      entries[existingIndex] = localTransaction;
    } else {
      entries.add(localTransaction);
    }
    _emitCapitals(transaction.userId);
  }

  Future<void> deleteCapitalTransaction({
    required String uid,
    required String capitalId,
  }) async {
    if (_usingApi) {
      await apiClient!.delete('capitals/$capitalId');
      await _refreshTradingData(force: true);
      return;
    }
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('capitals')
          .doc(capitalId)
          .delete();
      return;
    }

    _localCapitals[uid]?.removeWhere((capital) => capital.id == capitalId);
    _emitCapitals(uid);
  }

  Future<List<Map<String, dynamic>>> readinessChecklist(String uid) async {
    if (!firebaseEnabled) {
      throw const ApiException('The readiness checklist requires Firebase.');
    }
    final progress = await _firestore
        .collection('users')
        .doc(uid)
        .collection('readiness_checklist')
        .get();
    final progressById = {for (final doc in progress.docs) doc.id: doc.data()};
    return [
      for (final item in _readinessItems)
        {...item, ...?progressById['${item['id']}']},
    ];
  }

  Future<void> saveReadinessProgress({
    required String uid,
    required String itemId,
    required bool completed,
    int? selfRating,
    bool demoPracticed = false,
    String reflectionNote = '',
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('The readiness checklist requires Firebase.');
    }
    final reference = _firestore
        .collection('users')
        .doc(uid)
        .collection('readiness_checklist')
        .doc(itemId);
    if (!completed) {
      await reference.delete();
      return;
    }
    await reference.set({
      'completed': true,
      'completed_at': FieldValue.serverTimestamp(),
      'self_rating': selfRating,
      'demo_practiced': demoPracticed,
      'reflection_note': reflectionNote.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    });
  }

  Future<void> resetReadinessChecklist(String uid) async {
    if (!firebaseEnabled) {
      throw const ApiException('The readiness checklist requires Firebase.');
    }
    final snapshot = await _firestore
        .collection('users')
        .doc(uid)
        .collection('readiness_checklist')
        .get();
    final batch = _firestore.batch();
    for (final doc in snapshot.docs) {
      batch.delete(doc.reference);
    }
    await batch.commit();
  }

  Future<Map<String, dynamic>> recruitmentWorkspace({
    required String uid,
    required int roleId,
    Iterable<int> roleIds = const <int>[],
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trader recruitment requires Firebase.');
    }

    final actorRoleIds = <int>{roleId, ...roleIds};
    final allowedRoles = <int>{
      for (final actorRoleId in actorRoleIds)
        ...RolePermissionMatrix.assignableRoleIds(actorRoleId),
    }.where((item) => item != RolePermissionMatrix.superAdmin).toList()..sort();
    if (allowedRoles.isEmpty) {
      return {
        'referral_code': '',
        'allowed_roles': const <int>[],
        'downlines': const <Map<String, dynamic>>[],
      };
    }

    final referralCode = await _safeReferralCode(uid);
    final weeklyReferrals = <String, Map<String, dynamic>>{};
    for (final allowedRole in allowedRoles) {
      weeklyReferrals['$allowedRole'] =
          await ensureWeeklyRecruitmentReferralCode(
            uid,
            recruitmentRoleId: allowedRole,
          );
    }
    final weeklyReferral =
        weeklyReferrals['${RolePermissionMatrix.trader}'] ??
        weeklyReferrals.values.first;
    final downlines = <String, Map<String, dynamic>>{};
    var downlineLoadLimited = false;
    Future<void> addUserQuery(String field) async {
      try {
        final snapshot = await _firestore
            .collection('users')
            .where(field, isEqualTo: uid)
            .get();
        for (final doc in snapshot.docs) {
          downlines[doc.id] = _downlineUserMap(doc);
        }
      } on FirebaseException catch (error) {
        if (!_isPermissionDenied(error)) rethrow;
        downlineLoadLimited = true;
      }
    }

    await addUserQuery('upline_id');
    await addUserQuery('referrer_id');

    try {
      final relationshipSnapshot = await _firestore
          .collection('users')
          .doc(uid)
          .collection('downlines')
          .get();
      for (final doc in relationshipSnapshot.docs) {
        downlines.putIfAbsent(doc.id, () => {'id': doc.id, ...doc.data()});
      }
    } on FirebaseException catch (error) {
      if (!_isPermissionDenied(error)) rethrow;
      downlineLoadLimited = true;
    }

    final list = downlines.values.toList()
      ..sort(
        (left, right) => _firestoreDate(
          right['created_at'] ?? right['joined_at'],
        ).compareTo(_firestoreDate(left['created_at'] ?? left['joined_at'])),
      );
    return {
      'referral_code': referralCode,
      'weekly_referral_code': weeklyReferral['code'] ?? '',
      'weekly_referral_week': weeklyReferral['week'] ?? '',
      'weekly_referral_expires_at': weeklyReferral['expiresAt'] ?? '',
      'weekly_referral_codes': weeklyReferrals,
      'allowed_roles': allowedRoles,
      'downlines': list,
      'downline_load_limited': downlineLoadLimited,
    };
  }

  Future<void> recruitDownlineByReferralCode({
    required String uid,
    required String referralCode,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trader recruitment requires Firebase.');
    }
    final normalized = _normalizeReferralCode(referralCode);
    if (normalized.isEmpty) {
      throw const ApiException('Referral code is required');
    }

    try {
      await _callableService
          .call('recruitDownlineByReferralCode', {
            'referralCode': normalized,
            'requesterId': uid,
          })
          .timeout(const Duration(seconds: 30));
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(_recruitmentFunctionMessage(error));
    } on TimeoutException {
      throw const ApiException('Unable to recruit downline. Please try again.');
    }
  }

  Future<Map<String, dynamic>> ensureWeeklyRecruitmentReferralCode(
    String uid, {
    int recruitmentRoleId = RolePermissionMatrix.trader,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trader recruitment requires Firebase.');
    }
    try {
      return await _callableService
          .call('ensureWeeklyRecruitmentReferralCode', {
            'requesterId': uid,
            'recruitmentRoleId': recruitmentRoleId,
          })
          .timeout(const Duration(seconds: 30));
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(_recruitmentFunctionMessage(error));
    } on TimeoutException {
      throw const ApiException(
        'Unable to load weekly referral code. Please try again.',
      );
    }
  }

  Future<Map<String, dynamic>> appointmentWorkspace({
    required String uid,
    required bool isAdmin,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    final now = Timestamp.now();
    final slotsFuture = _firestore
        .collection('trading_appointment_slots')
        .where('start_at', isGreaterThanOrEqualTo: now)
        .get();
    final appointmentsFuture = isAdmin
        ? _firestore.collection('trading_appointments').get()
        : _firestore
              .collection('trading_appointments')
              .where('participant_ids', arrayContains: uid)
              .get();
    final hostsFuture = isAdmin
        ? _firestore.collection('users').get()
        : _firestore.collection('appointment_hosts').get();
    final results = await Future.wait([
      slotsFuture,
      appointmentsFuture,
      hostsFuture,
    ]);
    return _appointmentWorkspaceData(
      slotsSnapshot: results[0],
      appointmentsSnapshot: results[1],
      hostsSnapshot: results[2],
      isAdmin: isAdmin,
    );
  }

  Stream<Map<String, dynamic>> appointmentWorkspaceStream({
    required String uid,
    required bool isAdmin,
  }) {
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Trading appointments require Firebase.'),
      );
    }
    final now = Timestamp.now();
    final slotsQuery = _firestore
        .collection('trading_appointment_slots')
        .where('start_at', isGreaterThanOrEqualTo: now);
    final appointmentsQuery = isAdmin
        ? _firestore.collection('trading_appointments')
        : _firestore
              .collection('trading_appointments')
              .where('participant_ids', arrayContains: uid);
    final hostsQuery = isAdmin
        ? _firestore.collection('users')
        : _firestore.collection('appointment_hosts');

    QuerySnapshot<Map<String, dynamic>>? slots;
    QuerySnapshot<Map<String, dynamic>>? appointments;
    QuerySnapshot<Map<String, dynamic>>? hosts;
    final subscriptions = <StreamSubscription<dynamic>>[];
    late final StreamController<Map<String, dynamic>> controller;

    void emit() {
      if (slots == null || appointments == null || hosts == null) return;
      controller.add(
        _appointmentWorkspaceData(
          slotsSnapshot: slots!,
          appointmentsSnapshot: appointments!,
          hostsSnapshot: hosts!,
          isAdmin: isAdmin,
        ),
      );
    }

    controller = StreamController<Map<String, dynamic>>(
      onListen: () {
        subscriptions.addAll([
          slotsQuery.snapshots().listen((value) {
            slots = value;
            emit();
          }, onError: controller.addError),
          appointmentsQuery.snapshots().listen((value) {
            appointments = value;
            emit();
          }, onError: controller.addError),
          hostsQuery.snapshots().listen((value) {
            hosts = value;
            emit();
          }, onError: controller.addError),
        ]);
      },
      onCancel: () async {
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      },
    );
    return controller.stream;
  }

  Map<String, dynamic> _appointmentWorkspaceData({
    required QuerySnapshot<Map<String, dynamic>> slotsSnapshot,
    required QuerySnapshot<Map<String, dynamic>> appointmentsSnapshot,
    required QuerySnapshot<Map<String, dynamic>> hostsSnapshot,
    required bool isAdmin,
  }) {
    final slots =
        slotsSnapshot.docs
            .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
            .where((item) => isAdmin || item['status'] == 'active')
            .toList()
          ..sort(
            (left, right) => _firestoreDate(
              left['start_at'],
            ).compareTo(_firestoreDate(right['start_at'])),
          );
    final appointments =
        appointmentsSnapshot.docs
            .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
            .toList()
          ..sort(
            (left, right) => _firestoreDate(
              right['scheduled_start_at'],
            ).compareTo(_firestoreDate(left['scheduled_start_at'])),
          );
    final hosts = isAdmin
        ? hostsSnapshot.docs
              .where(
                (doc) => const {
                  1,
                  2,
                  760,
                }.contains(_intValue(doc.data()['role_id'])),
              )
              .map(_adminUserMap)
              .toList()
        : hostsSnapshot.docs
              .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
              .toList();
    return {'slots': slots, 'appointments': appointments, 'hosts': hosts};
  }

  Future<void> saveAppointmentSlot({
    String? id,
    required String hostId,
    required String hostName,
    required DateTime startAt,
    required int durationMinutes,
    required String location,
    required String notes,
    required bool active,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    if (!startAt.isAfter(DateTime.now())) {
      throw const ApiException('Appointment slots must be in the future.');
    }
    final endAt = startAt.add(Duration(minutes: durationMinutes));
    if (await _appointmentConflict(
      hostId: hostId,
      startAt: startAt,
      endAt: endAt,
      ignoreSlotId: id,
    )) {
      throw const ApiException(
        'This host already has a slot or appointment during that time.',
      );
    }
    final collection = _firestore.collection('trading_appointment_slots');
    final reference = id?.isNotEmpty == true
        ? collection.doc(id)
        : collection.doc();
    final batch = _firestore.batch();
    batch.set(reference, {
      'host_user_id': hostId,
      'host_name': hostName,
      'created_by': _firebaseUid(),
      'start_at': Timestamp.fromDate(startAt),
      'end_at': Timestamp.fromDate(endAt),
      'duration_minutes': durationMinutes,
      'location': location.trim(),
      'notes': notes.trim(),
      'status': active ? 'active' : 'inactive',
      if (id?.isNotEmpty != true) 'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    batch.set(
      _firestore.collection('appointment_hosts').doc(hostId),
      {
        'name': hostName,
        'active': true,
        'updated_at': FieldValue.serverTimestamp(),
      },
      SetOptions(merge: true),
    );
    await batch.commit();
    await _writeAudit(
      action: id?.isNotEmpty == true ? 'update' : 'create',
      module: 'appointments',
      targetType: 'trading_appointment_slot',
      targetId: reference.id,
      newValues: {
        'host_user_id': hostId,
        'start_at': startAt,
        'duration_minutes': durationMinutes,
        'status': active ? 'active' : 'inactive',
      },
    );
  }

  Future<void> deleteAppointmentSlot(String slotId) async {
    final reference = _firestore
        .collection('trading_appointment_slots')
        .doc(slotId);
    final snapshot = await reference.get();
    final data = snapshot.data();
    if (data == null) throw const ApiException('Appointment slot not found.');
    if (data['status'] == 'booked') {
      throw const ApiException('Cancel the booking before deleting this slot.');
    }
    await reference.delete();
    await _writeAudit(
      action: 'delete',
      module: 'appointments',
      targetType: 'trading_appointment_slot',
      targetId: slotId,
      oldValues: data,
    );
  }

  Future<void> bookAppointmentSlot({
    required String slotId,
    required String uid,
    required String requesterName,
    required String subject,
    required String note,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    if (_firebaseUid() != uid) {
      throw const ApiException(
        'Your session changed. Sign in again before booking.',
      );
    }
    if (subject.trim().isEmpty) {
      throw const ApiException('Enter an appointment subject.');
    }
    final slotReference = _firestore
        .collection('trading_appointment_slots')
        .doc(slotId);
    final appointmentReference = _firestore
        .collection('trading_appointments')
        .doc();
    await _firestore.runTransaction((transaction) async {
      final slot = await transaction.get(slotReference);
      final data = slot.data();
      if (data == null || data['status'] != 'active') {
        throw const ApiException(
          'This appointment slot is no longer available.',
        );
      }
      final startAt = _firestoreDate(data['start_at']);
      final endAt = _firestoreDate(data['end_at']);
      if (!startAt.isAfter(DateTime.now()) || !endAt.isAfter(startAt)) {
        throw const ApiException(
          'This appointment slot has expired or is invalid.',
        );
      }
      final hostId = '${data['host_user_id']}';
      if (hostId.isEmpty || hostId == 'null') {
        throw const ApiException('This appointment slot has no valid host.');
      }
      if (hostId == uid) {
        throw const ApiException('You cannot book your own appointment slot.');
      }
      transaction.set(appointmentReference, {
        'appointment_slot_id': slotId,
        'requester_id': uid,
        'requester_name': requesterName,
        'host_user_id': hostId,
        'host_name': data['host_name'],
        'participant_ids': [uid, hostId],
        'request_type': 'slot',
        'subject': subject.trim(),
        'request_note': note.trim(),
        'scheduled_start_at': data['start_at'],
        'scheduled_end_at': data['end_at'],
        'duration_minutes': data['duration_minutes'],
        'location': data['location'],
        'status': 'approved',
        'reviewed_at': FieldValue.serverTimestamp(),
        'booked_at': FieldValue.serverTimestamp(),
        'created_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      });
      transaction.update(slotReference, {
        'status': 'booked',
        'booked_by': uid,
        'appointment_id': appointmentReference.id,
        'updated_at': FieldValue.serverTimestamp(),
      });
    });
  }

  Future<void> requestPreferredAppointment({
    required String uid,
    required String requesterName,
    required String hostId,
    required String hostName,
    required String subject,
    required String note,
    required DateTime startAt,
    required int durationMinutes,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    if (!startAt.isAfter(DateTime.now())) {
      throw const ApiException('Choose a future appointment time.');
    }
    await _firestore.collection('trading_appointments').add({
      'requester_id': uid,
      'requester_name': requesterName,
      'host_user_id': hostId,
      'host_name': hostName,
      'participant_ids': [uid, hostId],
      'request_type': 'preferred',
      'subject': subject.trim(),
      'request_note': note.trim(),
      'scheduled_start_at': Timestamp.fromDate(startAt),
      'scheduled_end_at': Timestamp.fromDate(
        startAt.add(Duration(minutes: durationMinutes)),
      ),
      'duration_minutes': durationMinutes,
      'status': 'pending',
      'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    });
  }

  Future<void> reviewAppointment({
    required String appointmentId,
    required bool approve,
    required String note,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    final reference = _firestore
        .collection('trading_appointments')
        .doc(appointmentId);
    final snapshot = await reference.get();
    final data = snapshot.data();
    if (data == null) throw const ApiException('Appointment not found.');
    if (data['status'] != 'pending') {
      throw const ApiException(
        'Only pending appointments can be approved or rejected.',
      );
    }
    if (approve) {
      if (await _appointmentConflict(
        hostId: '${data['host_user_id']}',
        startAt: _firestoreDate(data['scheduled_start_at']),
        endAt: _firestoreDate(data['scheduled_end_at']),
        ignoreAppointmentId: appointmentId,
      )) {
        throw const ApiException(
          'The host already has another slot or appointment during that time.',
        );
      }
    }
    await reference.set({
      'status': approve ? 'approved' : 'rejected',
      'review_note': note.trim(),
      'reviewed_by': _firebaseUid(),
      'reviewed_at': FieldValue.serverTimestamp(),
      if (!approve) 'rejected_by': _firebaseUid(),
      if (!approve) 'rejected_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: approve ? 'approve' : 'reject',
      module: 'trading_appointments',
      targetType: 'trading_appointment',
      targetId: appointmentId,
      newValues: {
        'status': approve ? 'approved' : 'rejected',
        'note': note.trim(),
      },
    );
  }

  Future<bool> _appointmentConflict({
    required String hostId,
    required DateTime startAt,
    required DateTime endAt,
    String? ignoreSlotId,
    String? ignoreAppointmentId,
  }) async {
    final results = await Future.wait([
      _firestore
          .collection('trading_appointment_slots')
          .where('host_user_id', isEqualTo: hostId)
          .get(),
      _firestore
          .collection('trading_appointments')
          .where('host_user_id', isEqualTo: hostId)
          .get(),
    ]);
    for (final doc in results[0].docs) {
      if (doc.id == ignoreSlotId ||
          !const {'active', 'booked'}.contains(doc.data()['status'])) {
        continue;
      }
      final otherStart = _firestoreDate(doc.data()['start_at']);
      final otherEnd = _firestoreDate(doc.data()['end_at']);
      if (startAt.isBefore(otherEnd) && endAt.isAfter(otherStart)) return true;
    }
    for (final doc in results[1].docs) {
      if (doc.id == ignoreAppointmentId ||
          !const {'pending', 'approved'}.contains(doc.data()['status'])) {
        continue;
      }
      final otherStart = _firestoreDate(doc.data()['scheduled_start_at']);
      final otherEnd = _firestoreDate(doc.data()['scheduled_end_at']);
      if (startAt.isBefore(otherEnd) && endAt.isAfter(otherStart)) return true;
    }
    return false;
  }

  Future<void> cancelAppointment({
    required String appointmentId,
    required String reason,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Trading appointments require Firebase.');
    }
    final reference = _firestore
        .collection('trading_appointments')
        .doc(appointmentId);
    final appointment = await reference.get();
    final data = appointment.data();
    if (data == null) throw const ApiException('Appointment not found.');
    final status = '${data['status'] ?? ''}'.trim().toLowerCase();
    if (!const {
      'pending',
      'requested',
      'awaiting_confirmation',
      'awaiting confirmation',
      'awaiting-confirmation',
      'approved',
      'confirmed',
    }.contains(status)) {
      throw const ApiException(
        'Only pending, requested, awaiting-confirmation, approved, or confirmed appointments can be cancelled.',
      );
    }
    final currentUid = _firebaseUid();
    final participants = data['participant_ids'] is Iterable
        ? (data['participant_ids'] as Iterable)
              .map((item) => item.toString())
              .toSet()
        : <String>{};
    if (participants.isNotEmpty && !participants.contains(currentUid)) {
      throw const ApiException('You can only cancel your own appointment.');
    }
    if (reason.trim().length < 5) {
      throw const ApiException('Please enter a cancellation reason.');
    }
    final startAt = _firestoreDate(data['scheduled_start_at']);
    if (!startAt.isAfter(DateTime.now())) {
      throw const ApiException('Past appointments cannot be cancelled.');
    }
    final batch = _firestore.batch();
    batch.set(reference, {
      'status': 'cancelled',
      'cancellation_status': 'cancelledByClient',
      'cancelled_by': currentUid,
      'cancelled_by_client': currentUid == '${data['requester_id']}',
      'cancelled_at': FieldValue.serverTimestamp(),
      'cancellation_reason': reason.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    final slotId = data['appointment_slot_id']?.toString();
    if (slotId?.isNotEmpty == true) {
      batch.set(
        _firestore.collection('trading_appointment_slots').doc(slotId),
        {
          'status': 'active',
          'booked_by': null,
          'appointment_id': null,
          'updated_at': FieldValue.serverTimestamp(),
        },
        SetOptions(merge: true),
      );
    }
    await batch.commit();
    await _writeAudit(
      action: 'cancel',
      module: 'trading_appointments',
      targetType: 'trading_appointment',
      targetId: appointmentId,
      oldValues: data,
      newValues: {'status': 'cancelled', 'reason': reason.trim()},
    );
  }

  Future<List<Map<String, dynamic>>> supportTickets({
    required String uid,
    required bool isAdmin,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Support tickets require Firebase.');
    }
    final snapshot = isAdmin
        ? await _firestore.collection('support_tickets').get()
        : await _firestore
              .collection('support_tickets')
              .where('user_id', isEqualTo: uid)
              .get();
    return _supportTicketRows(snapshot);
  }

  Stream<List<Map<String, dynamic>>> supportTicketsStream({
    required String uid,
    required bool isAdmin,
  }) {
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Support tickets require Firebase.'),
      );
    }
    final query = isAdmin
        ? _firestore.collection('support_tickets')
        : _firestore
              .collection('support_tickets')
              .where('user_id', isEqualTo: uid);
    return query.snapshots().map(_supportTicketRows);
  }

  Stream<Map<String, dynamic>?> supportTicketStream(String ticketId) {
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Support tickets require Firebase.'),
      );
    }
    return _firestore
        .collection('support_tickets')
        .doc(ticketId)
        .snapshots()
        .map(
          (document) => document.data() == null
              ? null
              : <String, dynamic>{'id': document.id, ...document.data()!},
        );
  }

  List<Map<String, dynamic>> _supportTicketRows(
    QuerySnapshot<Map<String, dynamic>> snapshot,
  ) {
    return snapshot.docs
        .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
        .toList()
      ..sort(
        (left, right) => _firestoreDate(
          right['updated_at'],
        ).compareTo(_firestoreDate(left['updated_at'])),
      );
  }

  Future<String> createSupportTicket({
    required String uid,
    required String requesterName,
    required String subject,
    required String category,
    required String priority,
    required String message,
    required bool isAdmin,
    List<ApiUploadFile> attachments = const [],
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Support tickets require Firebase.');
    }
    final ticket = _firestore.collection('support_tickets').doc();
    final messageReference = ticket.collection('messages').doc();
    final uploadedAttachments = await _uploadSupportAttachments(
      ownerId: uid,
      files: attachments,
    );
    final messagePreview = message.trim().isEmpty && attachments.isNotEmpty
        ? 'Attachment'
        : message.trim();
    final batch = _firestore.batch();
    final ticketNumber =
        'TKT-${DateTime.now().millisecondsSinceEpoch.toRadixString(36).toUpperCase()}';
    batch.set(ticket, {
      'user_id': uid,
      'requester_name': requesterName,
      'ticket_number': ticketNumber,
      'subject': subject.trim(),
      'category': category,
      'priority': priority,
      'status': isAdmin ? 'answered' : 'open',
      'last_reply_is_admin': isAdmin,
      'last_message': messagePreview,
      'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    });
    batch.set(messageReference, {
      'user_id': uid,
      'sender_name': requesterName,
      'message': message.trim(),
      'attachments': uploadedAttachments,
      'is_admin_reply': isAdmin,
      'created_at': FieldValue.serverTimestamp(),
    });
    await batch.commit();
    await _writeAudit(
      action: 'create',
      module: 'support_tickets',
      targetType: 'support_ticket',
      targetId: ticket.id,
      newValues: {'subject': subject.trim(), 'priority': priority},
    );
    return ticket.id;
  }

  Stream<List<Map<String, dynamic>>> supportTicketMessages(String ticketId) {
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Support tickets require Firebase.'),
      );
    }
    return _firestore
        .collection('support_tickets')
        .doc(ticketId)
        .collection('messages')
        .orderBy('created_at')
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
              .toList(),
        );
  }

  Future<void> replySupportTicket({
    required String ticketId,
    required String uid,
    required String senderName,
    required String message,
    required bool isAdmin,
    List<ApiUploadFile> attachments = const [],
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Support tickets require Firebase.');
    }
    final ticket = _firestore.collection('support_tickets').doc(ticketId);
    final snapshot = await ticket.get();
    if (snapshot.data()?['status'] == 'closed') {
      throw const ApiException('Closed tickets cannot receive replies.');
    }
    final ownerId = '${snapshot.data()?['user_id'] ?? uid}';
    final uploadedAttachments = await _uploadSupportAttachments(
      ownerId: ownerId,
      files: attachments,
    );
    final messagePreview = message.trim().isEmpty && attachments.isNotEmpty
        ? 'Attachment'
        : message.trim();
    final messageReference = ticket.collection('messages').doc();
    final batch = _firestore.batch();
    batch.set(messageReference, {
      'user_id': uid,
      'sender_name': senderName,
      'message': message.trim(),
      'attachments': uploadedAttachments,
      'is_admin_reply': isAdmin,
      'created_at': FieldValue.serverTimestamp(),
    });
    batch.set(ticket, {
      'status': isAdmin ? 'answered' : 'open',
      'last_reply_is_admin': isAdmin,
      'assigned_admin_id': isAdmin
          ? uid
          : snapshot.data()?['assigned_admin_id'],
      'last_message': messagePreview,
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await batch.commit();
    await _writeAudit(
      action: 'reply',
      module: 'support_tickets',
      targetType: 'support_ticket',
      targetId: ticketId,
      newValues: {'is_staff_reply': isAdmin},
    );
  }

  Future<List<Map<String, Object?>>> _uploadSupportAttachments({
    required String ownerId,
    required List<ApiUploadFile> files,
  }) async {
    if (files.length > 3) {
      throw const ApiException('Attach no more than 3 files per message.');
    }
    final uploaded = <Map<String, Object?>>[];
    for (final file in files) {
      uploaded.add(
        await uploadManagedFile(
          area: 'support_tickets',
          ownerId: ownerId,
          file: file,
        ),
      );
    }
    return uploaded;
  }

  Future<void> closeSupportTicket(String ticketId, {String? note}) async {
    if (!firebaseEnabled) {
      throw const ApiException('Support tickets require Firebase.');
    }
    final reference = _firestore.collection('support_tickets').doc(ticketId);
    final previous = await reference.get();
    await reference.set({
      'status': 'closed',
      'closed_by': _firebaseUid(),
      'closed_at': FieldValue.serverTimestamp(),
      if (note?.trim().isNotEmpty == true) 'closing_note': note!.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'close',
      module: 'support_tickets',
      targetType: 'support_ticket',
      targetId: ticketId,
      oldValues: previous.data(),
      newValues: {'status': 'closed', 'closing_note': note},
    );
  }

  Future<void> reopenSupportTicket(String ticketId, {String? reason}) async {
    final reference = _firestore.collection('support_tickets').doc(ticketId);
    final previous = await reference.get();
    if (!previous.exists) throw const ApiException('Support ticket not found.');
    await reference.set({
      'status': 'reopened',
      'reopened_by': _firebaseUid(),
      'reopened_at': FieldValue.serverTimestamp(),
      if (reason?.trim().isNotEmpty == true) 'reopen_reason': reason!.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'reopen',
      module: 'support_tickets',
      targetType: 'support_ticket',
      targetId: ticketId,
      oldValues: previous.data(),
      newValues: {'status': 'reopened', 'reason': reason},
    );
  }

  Future<void> assignSupportTicket({
    required String ticketId,
    required String adminId,
    String? internalNote,
  }) async {
    final reference = _firestore.collection('support_tickets').doc(ticketId);
    final previous = await reference.get();
    if (!previous.exists) throw const ApiException('Support ticket not found.');
    await reference.set({
      'assigned_admin_id': adminId,
      if (internalNote?.trim().isNotEmpty == true)
        'internal_note': internalNote!.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'assign',
      module: 'support_tickets',
      targetType: 'support_ticket',
      targetId: ticketId,
      oldValues: previous.data(),
      newValues: {'assigned_admin_id': adminId},
    );
  }

  Future<void> saveAccountProfile(
    TraderAccountProfile profile, {
    ApiUploadFile? document,
  }) async {
    if (_usingApi) {
      final data = profile.toApiJson();
      await apiClient!.postMultipart(
        'profile/onboarding',
        fields: data.map(
          (key, value) => MapEntry(
            key,
            value is bool ? (value ? '1' : '0') : (value ?? '').toString(),
          ),
        ),
        files: document == null ? const [] : [document],
      );
      await _refreshAccount(profile.userId);
      return;
    }
    if (firebaseEnabled) {
      String? documentFirestorePath;
      String? documentBackend;
      String? documentUrl;
      String? documentStoragePath;
      int? documentSize;
      DocumentReference<Map<String, dynamic>>? documentReference;
      Uint8List? documentBytes;
      if (document != null) {
        final bytes = await _uploadBytes(document);
        documentSize = bytes.length;
        final contentType = _contentType(document.filename);
        if (!contentType.startsWith('image/') &&
            contentType != 'application/pdf') {
          throw const ApiException(
            'Choose a PDF, JPG, PNG, WEBP, or GIF verification document.',
          );
        }
        if (bytes.length > 10 * 1024 * 1024) {
          throw const ApiException(
            'The verification document must be smaller than 10 MB.',
          );
        }
        documentReference = _firestore
            .collection('users')
            .doc(profile.userId)
            .collection('onboarding_documents')
            .doc('current');
        documentFirestorePath = documentReference.path;
        final storagePath =
            'users/${profile.userId}/onboarding/${DateTime.now().microsecondsSinceEpoch}_${_safeName(document.filename)}';
        try {
          final storageReference = FirebaseStorage.instance.ref(storagePath);
          await storageReference.putData(
            bytes,
            SettableMetadata(contentType: contentType),
          );
          documentUrl = await storageReference.getDownloadURL();
          documentStoragePath = storagePath;
          documentBackend = 'storage';
        } on FirebaseException catch (error) {
          if (bytes.length <= 800 * 1024 &&
              const {
                'bucket-not-found',
                'object-not-found',
                'project-not-found',
              }.contains(error.code)) {
            documentBytes = bytes;
            documentBackend = 'firestore';
          } else {
            throw ApiException(
              error.message ?? 'Unable to upload the verification document.',
            );
          }
        }
      }

      final profileReference = _firestore
          .collection('users')
          .doc(profile.userId)
          .collection('trader_onboarding')
          .doc('profile');
      final batch = _firestore.batch();
      if (documentReference != null && documentBytes != null) {
        batch.set(documentReference, {
          'name': document!.filename,
          'content_type': _contentType(document.filename),
          'size': documentBytes.length,
          'bytes': Blob(documentBytes),
          'uploaded_at': FieldValue.serverTimestamp(),
        });
      } else if (documentReference != null) {
        batch.set(documentReference, {
          'name': document!.filename,
          'content_type': _contentType(document.filename),
          'size': documentSize,
          'storage_path': documentStoragePath,
          'url': documentUrl,
          'uploaded_at': FieldValue.serverTimestamp(),
        });
      }
      batch.set(profileReference, {
        ...profile.toFirestore(),
        if (profile.status == 'pending') ...{
          'rejection_reason': null,
          'rejection_note': null,
          'reviewed_by': null,
          'reviewed_at': null,
          'allow_resubmission': true,
        },
        if (document != null) ...{
          'document_url': documentUrl,
          'document_storage_path': documentStoragePath,
          'document_firestore_path': documentFirestorePath,
          'document_backend': documentBackend,
          'document_name': document.filename,
          'document_size': documentSize,
        },
      }, SetOptions(merge: true));
      await batch.commit();
      return;
    }

    _localAccounts[profile.userId] = profile.copyWith(
      id: 'profile',
      status: profile.status == 'not_submitted' ? 'pending' : profile.status,
      submittedAt: profile.submittedAt ?? DateTime.now(),
    );
    _emitAccount(profile.userId);
  }

  Future<void> upsertTradingAccount(TradingAccount account) async {
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(account.userId)
          .collection('trading_accounts');
      final existing = await collection.get();
      final reference = account.id.isEmpty
          ? collection.doc()
          : collection.doc(account.id);
      final makePrimary = account.isPrimary || existing.docs.isEmpty;
      final batch = _firestore.batch();
      if (makePrimary) {
        for (final doc in existing.docs) {
          if (doc.id != reference.id && doc.data()['is_primary'] == true) {
            batch.update(doc.reference, {
              'is_primary': false,
              'updated_at': FieldValue.serverTimestamp(),
            });
          }
        }
      }
      batch.set(reference, {
        ...account.toFirestore(),
        'is_primary': makePrimary,
        if (account.id.isEmpty) 'created_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
      await batch.commit();
      return;
    }
    if (!_usingApi) {
      throw const ApiException('Connect to the server to save an account.');
    }
    if (account.id.isEmpty) {
      await apiClient!.post('trading-accounts', body: account.toApiJson());
    } else {
      await apiClient!.put(
        'trading-accounts/${account.id}',
        body: account.toApiJson(),
      );
    }
    await _refreshTradingAccounts(account.userId);
  }

  Future<void> deleteTradingAccount({
    required String uid,
    required String accountId,
  }) async {
    if (firebaseEnabled) {
      final collection = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_accounts');
      final reference = collection.doc(accountId);
      final snapshot = await reference.get();
      if (!snapshot.exists) return;
      final wasPrimary = snapshot.data()?['is_primary'] == true;
      await reference.update({
        'status': 'archived',
        'is_primary': false,
        'updated_at': FieldValue.serverTimestamp(),
      });
      if (wasPrimary) {
        final remaining = await collection
            .where('status', isNotEqualTo: 'archived')
            .limit(1)
            .get();
        if (remaining.docs.isNotEmpty) {
          await remaining.docs.first.reference.update({
            'is_primary': true,
            'updated_at': FieldValue.serverTimestamp(),
          });
        }
      }
      return;
    }
    if (!_usingApi) {
      throw const ApiException('Connect to the server to archive an account.');
    }
    await apiClient!.delete('trading-accounts/$accountId');
    await _refreshTradingAccounts(uid);
    await _refreshTradingData(force: true);
  }

  Future<JournalAttachment> uploadJournalAttachment({
    required String journalId,
    required ApiUploadFile file,
  }) async {
    if (firebaseEnabled) {
      final uid = FirebaseAuth.instance.currentUser?.uid;
      if (uid == null) {
        throw const ApiException('Sign in before uploading an attachment.');
      }
      final bytes = await _uploadBytes(file);
      final attachmentId = '${DateTime.now().microsecondsSinceEpoch}';
      final storagePath =
          'users/$uid/journals/$journalId/${attachmentId}_${_safeName(file.filename)}';
      final reference = FirebaseStorage.instance.ref(storagePath);
      final mimeType = _contentType(file.filename);
      await reference.putData(bytes, SettableMetadata(contentType: mimeType));
      final url = await reference.getDownloadURL();
      final attachment = JournalAttachment(
        id: attachmentId,
        journalId: journalId,
        originalName: file.filename,
        url: url,
        mimeType: mimeType,
        sizeBytes: bytes.length,
        storagePath: storagePath,
      );
      await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_journals')
          .doc(journalId)
          .update({
            'attachments': FieldValue.arrayUnion([attachment.toMap()]),
            'updated_at': FieldValue.serverTimestamp(),
          });
      return attachment;
    }
    if (!_usingApi) {
      throw const ApiException('Connect to upload journal attachments.');
    }
    final response = await apiClient!.postMultipart(
      'journals/$journalId/attachments',
      files: [
        ApiUploadFile(
          field: 'attachment',
          filename: file.filename,
          path: file.path,
          bytes: file.bytes,
        ),
      ],
    );
    await _refreshTradingData(force: true);
    return JournalAttachment.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<void> deleteJournalAttachment({
    required String journalId,
    required String attachmentId,
  }) async {
    if (firebaseEnabled) {
      final uid = FirebaseAuth.instance.currentUser?.uid;
      if (uid == null) {
        throw const ApiException('Sign in before deleting an attachment.');
      }
      final journal = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_journals')
          .doc(journalId);
      final snapshot = await journal.get();
      final raw = (snapshot.data()?['attachments'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
      final attachment = raw
          .where((item) => '${item['id']}' == attachmentId)
          .firstOrNull;
      if (attachment == null) return;
      final storagePath = attachment['storage_path']?.toString();
      if (storagePath?.isNotEmpty == true) {
        await FirebaseStorage.instance
            .ref(storagePath)
            .delete()
            .catchError((_) {});
      }
      await journal.update({
        'attachments': FieldValue.arrayRemove([attachment]),
        'updated_at': FieldValue.serverTimestamp(),
      });
      return;
    }
    if (!_usingApi) {
      throw const ApiException('Connect to delete journal attachments.');
    }
    await apiClient!.delete('journals/$journalId/attachments/$attachmentId');
    await _refreshTradingData(force: true);
  }

  Future<Map<String, dynamic>> importJournals({
    required ApiUploadFile file,
    String? tradingAccountId,
    String timeInputTimezone = 'mt5',
    int timeInputOffsetMinutes = 180,
  }) async {
    if (firebaseEnabled) {
      final uid = FirebaseAuth.instance.currentUser?.uid;
      if (uid == null) {
        throw const ApiException('Sign in before importing journals.');
      }
      final rows = await _spreadsheetRows(file);
      if (rows.isEmpty) throw const ApiException('The selected file is empty.');
      final headerIndex = rows.indexWhere(
        (row) =>
            row.map(_heading).contains('open_date') &&
            row.map(_heading).contains('pair'),
      );
      if (headerIndex < 0) {
        throw const ApiException(
          'No supported HC journal heading row was found.',
        );
      }
      final headings = <String, int>{};
      for (var index = 0; index < rows[headerIndex].length; index++) {
        headings[_heading(rows[headerIndex][index])] = index;
      }
      String value(List<String> row, String key) {
        final index = headings[key];
        return index == null || index >= row.length ? '' : row[index].trim();
      }

      final collection = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_journals');
      var imported = 0;
      var skipped = 0;
      var batch = _firestore.batch();
      var pendingWrites = 0;
      for (final row in rows.skip(headerIndex + 1)) {
        if (row.every((cell) => cell.trim().isEmpty)) continue;
        final openDate = _parseImportDate(value(row, 'open_date'));
        final closeDate = _parseImportDate(value(row, 'close_date'));
        final pair = value(row, 'pair').toUpperCase();
        final entry = double.tryParse(
          value(row, 'entry_price').replaceAll(',', ''),
        );
        final exit = double.tryParse(
          value(row, 'exit_price').replaceAll(',', ''),
        );
        final lot = double.tryParse(value(row, 'lot_size').replaceAll(',', ''));
        if (openDate == null ||
            closeDate == null ||
            pair.isEmpty ||
            entry == null ||
            exit == null ||
            lot == null) {
          skipped++;
          continue;
        }
        final profitLoss =
            double.tryParse(value(row, 'profit_loss').replaceAll(',', '')) ?? 0;
        final pips =
            double.tryParse(value(row, 'pips').replaceAll(',', '')) ?? 0;
        final directionText = value(row, 'direction').toLowerCase();
        final direction = directionText == 'sell' || directionText == '2'
            ? 2
            : 1;
        final resultText = value(row, 'result').toLowerCase();
        final result = resultText == 'loss' || resultText == '2'
            ? 2
            : resultText == 'break even' ||
                  resultText == 'breakeven' ||
                  resultText == '3'
            ? 3
            : profitLoss < 0
            ? 2
            : profitLoss == 0
            ? 3
            : 1;
        final document = collection.doc();
        batch.set(document, {
          'user_id': uid,
          'type': 'trade',
          'source': TradingRecordSource.excelImport.storageValue,
          'trading_account_id': tradingAccountId,
          'status': value(row, 'status').isEmpty
              ? 'closed'
              : value(row, 'status'),
          'close_handling': value(row, 'close_handling').isEmpty
              ? 'manual_close'
              : value(row, 'close_handling'),
          'time_input_timezone': value(row, 'time_input_timezone').isEmpty
              ? timeInputTimezone
              : value(row, 'time_input_timezone'),
          'time_input_offset_minutes':
              int.tryParse(value(row, 'time_input_offset_minutes')) ??
              timeInputOffsetMinutes,
          'open_date': Timestamp.fromDate(openDate),
          'close_date': Timestamp.fromDate(closeDate),
          'pair': pair,
          'direction': direction,
          'entry_price': entry,
          'exit_price': exit,
          'lot_size': lot,
          'pips': pips,
          'profit_loss': profitLoss,
          'result': result,
          'notes': value(row, 'notes'),
          'emotion_before_entry': value(row, 'emotion_before_entry'),
          'emotion_during_trade': value(row, 'emotion_during_trade'),
          'emotion_after_trade': value(row, 'emotion_after_trade'),
          'confidence_level': int.tryParse(value(row, 'confidence_level')),
          'discipline_rating': int.tryParse(value(row, 'discipline_rating')),
          'mistake_tags': value(row, 'mistake_tags')
              .split(',')
              .map((item) => item.trim())
              .where((item) => item.isNotEmpty)
              .toList(),
          'psychology_notes': value(row, 'psychology_notes'),
          'attachments': const [],
          'created_at': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        });
        imported++;
        pendingWrites++;
        if (pendingWrites == 400) {
          await batch.commit();
          batch = _firestore.batch();
          pendingWrites = 0;
        }
      }
      if (pendingWrites > 0) await batch.commit();
      return {
        'imported': imported,
        'updated': 0,
        'deduplicated': 0,
        'skipped': skipped,
        'skipped_rows': const [],
      };
    }
    if (!_usingApi) {
      throw const ApiException('Connect to import a journal file.');
    }
    final response = await apiClient!.postMultipart(
      'journals/import',
      fields: {
        'time_input_timezone': timeInputTimezone,
        'time_input_offset_minutes': '$timeInputOffsetMinutes',
        'source': TradingRecordSource.excelImport.storageValue,
        if (tradingAccountId?.isNotEmpty == true)
          'trading_account_id': tradingAccountId!,
      },
      files: [
        ApiUploadFile(
          field: 'file',
          filename: file.filename,
          path: file.path,
          bytes: file.bytes,
        ),
      ],
    );
    await _refreshTradingData(force: true);
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<List<TradingAccount>> loadTradingAccounts(String uid) async {
    if (firebaseEnabled) {
      final snapshot = await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_accounts')
          .get();
      return snapshot.docs
          .map(TradingAccount.fromFirestore)
          .where((account) => account.status != 'archived')
          .toList();
    }
    if (_usingApi) await _refreshTradingAccounts(uid);
    return List.unmodifiable(_localTradingAccounts[uid] ?? const []);
  }

  String resolveUrl(String value) => apiClient?.resolveUrl(value) ?? value;

  Future<void> submitPositionApplication(
    TradingPositionApplicationMobile application,
  ) async {
    if (_usingApi) {
      await apiClient!.post('positions', body: application.toApiJson());
      await _refreshPositions(application.userId);
      return;
    }
    if (firebaseEnabled) {
      await _firestore
          .collection('users')
          .doc(application.userId)
          .collection('trading_position_applications')
          .add(application.toFirestore());
      return;
    }

    final applications = _localPositions.putIfAbsent(
      application.userId,
      () => [],
    );
    applications.add(
      TradingPositionApplicationMobile(
        id: application.id.isEmpty ? _newLocalId('position') : application.id,
        userId: application.userId,
        requestedPosition: application.requestedPosition,
        mt5AccountId: application.mt5AccountId,
        mt5Login: application.mt5Login,
        requestedRoleId: application.requestedRoleId,
        status: application.status,
        tradeCountSnapshot: application.tradeCountSnapshot,
        strategySummary: application.strategySummary,
        tradeHistorySummary: application.tradeHistorySummary,
        personalitySummary: application.personalitySummary,
        marketingPlan: application.marketingPlan,
        clientSupportPlan: application.clientSupportPlan,
        submittedAt: application.submittedAt ?? DateTime.now(),
      ),
    );
    _emitPositions(application.userId);
  }

  Future<TradingExamAttemptMobile> submitTradingExamAttempt({
    required String uid,
    required List<TradingExamQuestionMobile> questions,
    required Map<String, int> answers,
    Map<String, TradingQuizResponseDraft> responses = const {},
  }) async {
    if (questions.isEmpty) {
      throw const ApiException('Answer every question before submitting.');
    }
    for (final question in questions) {
      if (question.questionType.requiresChoice) {
        final selected = answers[question.id];
        if (selected == null ||
            selected < 0 ||
            selected >= question.options.length) {
          throw const ApiException('Answer every question before submitting.');
        }
      }
      final response = responses[question.id];
      if (question.needsWrittenExplanation &&
          (response?.writtenExplanation.trim().length ?? 0) <
              question.minimumExplanationCharacters) {
        throw ApiException(
          'Write at least ${question.minimumExplanationCharacters} characters for every chart analysis.',
        );
      }
      if (question.needsScreenshot &&
          (response?.screenshotStoragePath.trim().isEmpty ?? true)) {
        throw const ApiException(
          'Upload the required chart screenshot before submitting.',
        );
      }
    }
    if (_usingApi) {
      final attemptId = _currentExamAttemptId;
      if (attemptId == null) {
        throw const ApiException('No active daily examination is available.');
      }
      final selectedOptionIds = <String, int>{};
      for (final question in questions) {
        final selectedIndex = answers[question.id];
        if (selectedIndex == null ||
            selectedIndex < 0 ||
            selectedIndex >= question.options.length) {
          throw const ApiException('Answer every question before submitting.');
        }
        selectedOptionIds[question.id] = int.parse(
          question.options[selectedIndex].id,
        );
      }
      await apiClient!.post(
        'exam/$attemptId/submit',
        body: {'answers': selectedOptionIds},
      );
      await _refreshExam(uid: uid, force: true);
      final attempts = _localExamAttempts[uid] ?? const [];
      if (attempts.isNotEmpty) return attempts.first;
      throw const ApiException('The examination result could not be loaded.');
    }
    if (firebaseEnabled) {
      if (_firebaseUid() != uid) {
        throw const ApiException(
          'Your session changed. Sign in again before submitting.',
        );
      }
      final attemptId = dailyQuizAttemptId();
      try {
        await _callableService.call('submitDailyQuizAttempt', {
          'attemptId': attemptId,
          'questions': [
            for (final question in questions)
              {
                'questionId': question.id,
                'questionVersion': question.version,
                if (answers[question.id] != null)
                  'selectedIndex': answers[question.id],
                if (responses[question.id] != null)
                  ...responses[question.id]!.toJson(),
              },
          ],
          'timezoneOffsetMinutes': DateTime.now().timeZoneOffset.inMinutes,
        });
      } on FirebaseCallableFailure catch (error) {
        throw ApiException(error.message);
      }
      final snapshot = await _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_exam_attempts')
          .doc(attemptId)
          .get();
      if (!snapshot.exists) {
        throw const ApiException(
          'The submitted quiz result could not be loaded.',
        );
      }
      return TradingExamAttemptMobile.fromFirestore(snapshot);
    }
    final score = questions.fold<int>(0, (total, question) {
      return total +
          (question.questionType.requiresChoice &&
                  (answers[question.id] ?? -1) == question.correctOptionIndex
              ? 1
              : 0);
    });
    final today = _dateOnly(DateTime.now());
    final attempt = TradingExamAttemptMobile(
      id: 'exam-${today.toIso8601String().substring(0, 10)}',
      userId: uid,
      examDate: today,
      status: questions.any((question) => question.isScreenshotAnalysis)
          ? 'pending_review'
          : 'completed',
      totalQuestions: questions.length,
      score: score,
      answers: answers,
      questionResults: questions
          .map((question) {
            final response = responses[question.id];
            final base = TradingExamQuestionResultMobile.fromQuestion(
              question,
              answers[question.id] ?? -1,
            );
            return TradingExamQuestionResultMobile(
              questionId: base.questionId,
              questionText: base.questionText,
              options: base.options,
              selectedIndex: base.selectedIndex,
              correctIndex: base.correctIndex,
              explanation: base.explanation,
              difficulty: base.difficulty,
              questionTextI18n: base.questionTextI18n,
              optionsI18n: base.optionsI18n,
              explanationI18n: base.explanationI18n,
              questionType: question.questionType,
              questionVersion: question.version,
              writtenExplanation: response?.writtenExplanation ?? '',
              screenshotUrl: response?.screenshotUrl ?? '',
              screenshotStoragePath: response?.screenshotStoragePath ?? '',
              reviewStatus: question.isScreenshotAnalysis
                  ? 'pending_review'
                  : 'not_required',
            );
          })
          .toList(growable: false),
      completedAt: DateTime.now(),
    );

    final attempts = _localExamAttempts.putIfAbsent(uid, () => []);
    attempts.removeWhere((item) => item.id == attempt.id);
    attempts.add(attempt);
    _emitExamAttempts(uid);
    return attempt;
  }

  Future<FinalExamStatusMobile> finalExamStatus() async {
    if (firebaseEnabled) {
      final uid = _firebaseUid();
      final userFuture = _firestore.collection('users').doc(uid).get();
      final attemptsFuture = _firestore
          .collection('users')
          .doc(uid)
          .collection('final_exam_attempts')
          .get();
      final sourceQuestionsFuture = _approvedFinalQuestionSources();
      final modelSnapshotFuture = _firestore
          .collection('prop_firm_models')
          .get();
      final user = await userFuture;
      final attemptsSnapshot = await attemptsFuture;
      final sourceQuestions = await sourceQuestionsFuture;
      final sourceCache = _sourceMapFromList(sourceQuestions);
      final modelSnapshot = await modelSnapshotFuture;
      final attempts = <FinalExamAttemptMobile>[];
      for (final doc in attemptsSnapshot.docs) {
        final data = {'id': doc.id, ...doc.data()};
        final enriched = data['status'] == 'in_progress'
            ? await _enrichFinalExamAttemptData(
                data,
                reference: doc.reference,
                sourceCache: sourceCache,
              )
            : data;
        attempts.add(FinalExamAttemptMobile.fromJson(enriched));
      }
      attempts.sort((left, right) => right.id.compareTo(left.id));
      final current = attempts
          .where((item) => item.status == 'in_progress')
          .firstOrNull;
      final questionCount = sourceQuestions.length;
      final models = modelSnapshot.docs
          .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
          .where((item) => item['active'] != false)
          .toList();
      final hasPassedAttempt = attempts.any((item) => item.status == 'passed');
      final passed =
          user.data()?['final_exam_passed'] == true || hasPassedAttempt;
      return FinalExamStatusMobile(
        required: user.data()?['requires_final_exam'] == true && !passed,
        passed: passed,
        unlocked: passed,
        allowed: questionCount >= 50,
        passingPercentage: 60,
        message: questionCount >= 50
            ? 'Your examination is stored and scored in Firebase.'
            : 'The administrator must publish at least 50 final-exam questions in Firestore.',
        currentAttempt: current,
        recentAttempts: attempts
            .where((item) => item.status != 'in_progress')
            .take(10)
            .toList(),
        propFirmModels: models,
      );
    }
    if (!_usingApi) {
      return const FinalExamStatusMobile(
        required: true,
        passed: false,
        unlocked: false,
        allowed: false,
        passingPercentage: 60,
        message: 'Connect to the server to take the official examination.',
        currentAttempt: null,
        recentAttempts: [],
        propFirmModels: [],
      );
    }
    final response = await apiClient!.get('final-exam');
    return FinalExamStatusMobile.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<FinalExamAttemptMobile> startFinalExam() async {
    if (firebaseEnabled) {
      final uid = _firebaseUid();
      final attempts = _firestore
          .collection('users')
          .doc(uid)
          .collection('final_exam_attempts');
      final current = await attempts
          .where('status', isEqualTo: 'in_progress')
          .limit(1)
          .get();
      if (current.docs.isNotEmpty) {
        final doc = current.docs.first;
        final data = {'id': doc.id, ...doc.data()};
        final questions = _jsonList(data['questions']);
        final sourceCache = await _finalQuestionSourceMapForAttempt(questions);
        final enriched = await _enrichFinalExamAttemptData(
          {...data},
          reference: doc.reference,
          sourceCache: sourceCache,
        );
        return FinalExamAttemptMobile.fromJson(enriched);
      }
      final sourceQuestions = await _approvedFinalQuestionSources();
      if (sourceQuestions.length < 50) {
        throw const ApiException(
          'At least 50 approved Firestore questions are required.',
        );
      }
      sourceQuestions.shuffle(Random.secure());
      final selected = sourceQuestions.take(50).toList();
      final questionPayloads = <Map<String, dynamic>>[];
      for (var index = 0; index < selected.length; index++) {
        questionPayloads.add(_finalQuestionPayload(selected[index], index + 1));
      }
      final reference = attempts.doc();
      final payload = <String, dynamic>{
        'user_id': uid,
        'status': 'in_progress',
        'total_questions': selected.length,
        'correct_answers': 0,
        'wrong_answers': 0,
        'score_percentage': 0,
        'questions': questionPayloads,
        'started_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      };
      await reference.set(payload);
      return FinalExamAttemptMobile.fromJson({'id': reference.id, ...payload});
    }
    if (!_usingApi) {
      throw const ApiException(
        'Connect to the server to start the examination.',
      );
    }
    final response = await apiClient!.post('final-exam/start');
    return FinalExamAttemptMobile.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<Map<String, dynamic>> submitFinalExam({
    required String attemptId,
    required Map<String, String> answers,
  }) async {
    if (firebaseEnabled) {
      final uid = _firebaseUid();
      final reference = _firestore
          .collection('users')
          .doc(uid)
          .collection('final_exam_attempts')
          .doc(attemptId);
      final snapshot = await reference.get();
      final data = snapshot.data();
      if (data == null) {
        throw const ApiException('Examination attempt not found.');
      }
      if (data['status'] != 'in_progress') {
        return {
          'attempt': {'id': snapshot.id, ...data},
        };
      }
      final questions = _jsonList(data['questions']);
      if (answers.length != questions.length) {
        throw const ApiException('Answer every question before submitting.');
      }
      var correct = 0;
      for (final question in questions) {
        final attemptQuestionId = '${question['attempt_question_id']}';
        var expected = '${question['answer_key'] ?? ''}'.trim();
        if (expected.isEmpty) {
          final sourceId = '${question['question_id'] ?? attemptQuestionId}';
          final source = await _firestore
              .collection('final_exam_questions')
              .doc(sourceId)
              .get();
          expected = _correctFinalAnswer(source.data() ?? const {});
        }
        if (expected.isNotEmpty &&
            answers[attemptQuestionId]?.toUpperCase() ==
                expected.toUpperCase()) {
          correct++;
        }
      }
      final total = questions.length;
      final percentage = total == 0 ? 0.0 : (correct / total) * 100;
      final passed = percentage >= 60;
      final result = <String, dynamic>{
        ...data,
        'status': passed ? 'passed' : 'failed',
        'correct_answers': correct,
        'wrong_answers': total - correct,
        'score_percentage': percentage,
        'answers': answers,
        'completed_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      };
      await reference.update(result);
      if (passed) {
        unawaited(_markFinalExamPassed(uid: uid, attemptId: attemptId));
      }
      return {
        'attempt': {'id': attemptId, ...result},
      };
    }
    if (!_usingApi) {
      throw const ApiException(
        'Connect to the server to submit the examination.',
      );
    }
    final response = await apiClient!.post(
      'final-exam/$attemptId/submit',
      body: {'answers': answers},
    );
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> _markFinalExamPassed({
    required String uid,
    required String attemptId,
  }) async {
    try {
      await _firestore.collection('users').doc(uid).set({
        'final_exam_passed': true,
        'requires_final_exam': false,
        'final_exam_last_attempt_id': attemptId,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    } on FirebaseException catch (error, stackTrace) {
      if (kDebugMode) {
        debugPrint(
          '[HC Trader FinalExam] Profile pass flag update skipped: '
          'plugin=${error.plugin} code=${error.code} message=${error.message}',
        );
        debugPrint('$stackTrace');
      }
    }
  }

  Future<void> selectPropFirmModel(String modelId) async {
    if (firebaseEnabled) {
      final model = await _firestore
          .collection('prop_firm_models')
          .doc(modelId)
          .get();
      if (!model.exists) {
        throw const ApiException(
          'The selected prop-firm model no longer exists.',
        );
      }
      await _firestore.collection('users').doc(_firebaseUid()).set({
        'prop_firm_model_id': modelId,
        'prop_firm_model': model.data(),
        'prop_firm_unlocked': true,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
      return;
    }
    if (!_usingApi) {
      throw const ApiException('Connect to the server to save this selection.');
    }
    await apiClient!.post(
      'final-exam/prop-firm',
      body: {'prop_firm_model_id': int.parse(modelId)},
    );
  }

  Future<Map<String, bool>> featureConfiguration() async {
    const defaults = <String, bool>{
      'trading': true,
      'marketing_resources': true,
      'support_tickets': true,
      'ai_assistant': true,
      'appointments': true,
      'trading_exams': true,
      'notifications': true,
      'community': true,
      'trading_news': true,
      'signal_provider_desk': true,
      'trading_certificates': true,
      'knowledge_centre': true,
      'market_analyst': true,
      'trader_centre': true,
      'trading_signals': true,
      'push_notifications': true,
      'stock_information': true,
      'stock_report_publishing': true,
      'content_publishing': true,
      'leaderboard': true,
      'recording_classes': true,
      'trading_blog': true,
      'trading_pair': true,
      'traders_performance': true,
      'funded_traders': true,
      'prop_firm_config': true,
      'dealership_ecommerce': false,
    };
    if (!firebaseEnabled) return defaults;
    final uid = FirebaseAuth.instance.currentUser?.uid ?? '';
    final modulesFuture = _attemptRead(
      () => _firestore.collection('app_configuration').doc('modules').get(),
    );
    final configurationsFuture = _attemptRead(
      () => _firestore.collection('featureConfigurations').get(),
    );
    final userFuture = uid.isEmpty
        ? null
        : _attemptRead(() => _firestore.collection('users').doc(uid).get());
    final modulesRead = await modulesFuture;
    final configurationsRead = await configurationsFuture;
    final userRead = await userFuture;
    if (modulesRead.value == null && configurationsRead.value == null) {
      throw const ApiException(
        'Feature configuration is temporarily unavailable.',
      );
    }
    if (kDebugMode) {
      if (modulesRead.error != null) {
        debugPrint(
          '[FeatureConfiguration] legacy source unavailable: '
          '${_safeErrorCode(modulesRead.error)}',
        );
      }
      if (configurationsRead.error != null) {
        debugPrint(
          '[FeatureConfiguration] versioned source unavailable: '
          '${_safeErrorCode(configurationsRead.error)}',
        );
      }
      if (userRead?.error != null) {
        debugPrint(
          '[FeatureConfiguration] user targeting unavailable: '
          '${_safeErrorCode(userRead?.error)}',
        );
      }
    }
    final data = modulesRead.value?.data() ?? const <String, dynamic>{};
    final resolved = <String, bool>{
      for (final entry in defaults.entries)
        entry.key: data[entry.key] is bool
            ? data[entry.key] as bool
            : entry.value,
    };
    final user = userRead?.value?.data() ?? const <String, dynamic>{};
    final roles = <String>{
      '${user['role_id'] ?? ''}',
      if (user['role_ids'] is Iterable)
        for (final role in user['role_ids'] as Iterable) '$role',
    };
    final accessLevel =
        '${user['subscription_level'] ?? user['access_level'] ?? ''}';
    final region = '${user['country_code'] ?? user['region'] ?? ''}'
        .toUpperCase();
    final platform = kIsWeb ? 'web' : defaultTargetPlatform.name;
    final now = DateTime.now();
    for (final document
        in configurationsRead.value?.docs ??
            const <QueryDocumentSnapshot<Map<String, dynamic>>>[]) {
      final configuration = document.data();
      final mode = '${configuration['mode'] ?? 'enabled'}';
      final platforms = _stringSet(configuration['platforms']);
      final enabledRoles = _stringSet(configuration['role_ids']);
      final accessLevels = _stringSet(configuration['access_levels']);
      final selectedUsers = _stringSet(configuration['selected_user_ids']);
      final regions = _stringSet(
        configuration['regions'],
      ).map((value) => value.toUpperCase()).toSet();
      final startsAt = _nullableFirestoreDate(configuration['starts_at']);
      final endsAt = _nullableFirestoreDate(configuration['ends_at']);
      final minimumVersion = '${configuration['minimum_app_version'] ?? ''}';
      final rollout =
          int.tryParse(
            '${configuration['rollout_percentage'] ?? 100}',
          )?.clamp(0, 100) ??
          100;
      final bucket = uid.isEmpty ? 0 : _stableRolloutBucket(uid, document.id);
      resolved[document.id] =
          !const {'disabled', 'hidden', 'maintenance'}.contains(mode) &&
          (platforms.isEmpty || platforms.contains(platform)) &&
          (enabledRoles.isEmpty || roles.any(enabledRoles.contains)) &&
          (accessLevels.isEmpty || accessLevels.contains(accessLevel)) &&
          (selectedUsers.isEmpty || selectedUsers.contains(uid)) &&
          (regions.isEmpty ||
              (region.isNotEmpty && regions.contains(region))) &&
          (startsAt == null || !startsAt.isAfter(now)) &&
          (endsAt == null || endsAt.isAfter(now)) &&
          (minimumVersion.isEmpty ||
              _compareVersions(_runtimeAppVersion, minimumVersion) >= 0) &&
          bucket < rollout;
    }
    return resolved;
  }

  static Set<String> _stringSet(Object? value) => value is Iterable
      ? value
            .map((item) => '$item'.trim())
            .where((item) => item.isNotEmpty)
            .toSet()
      : const {};

  static String _safeErrorCode(Object? error) =>
      error is FirebaseException ? error.code : error.runtimeType.toString();

  static int _stableRolloutBucket(String uid, String featureKey) {
    var hash = 0x811c9dc5;
    for (final unit in '$featureKey|$uid'.codeUnits) {
      hash ^= unit;
      hash = (hash * 0x01000193) & 0x7fffffff;
    }
    return hash % 100;
  }

  static int _compareVersions(String left, String right) {
    List<int> parts(String value) => value
        .split('+')
        .first
        .split('.')
        .map((part) => int.tryParse(part) ?? 0)
        .toList();
    final a = parts(left);
    final b = parts(right);
    for (var index = 0; index < max(a.length, b.length); index++) {
      final av = index < a.length ? a[index] : 0;
      final bv = index < b.length ? b[index] : 0;
      if (av != bv) return av.compareTo(bv);
    }
    return 0;
  }

  Future<void> adminSaveFeatureConfiguration(
    Map<String, bool> configuration,
  ) async {
    if (!firebaseEnabled) {
      throw const ApiException('Feature configuration requires Firebase.');
    }
    final oldValues = await featureConfiguration();
    await _firestore.collection('app_configuration').doc('modules').set({
      ...configuration,
      'updated_by': _firebaseUid(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'update',
      module: 'feature_configuration',
      targetType: 'app_configuration',
      targetId: 'modules',
      oldValues: oldValues,
      newValues: configuration,
    );
  }

  Future<List<Map<String, dynamic>>> adminAuditLogs({
    String search = '',
    String? module,
    int limit = 150,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Audit history requires Firebase.');
    }
    final snapshot = await _firestore
        .collection('audit_logs')
        .orderBy('created_at', descending: true)
        .limit(limit)
        .get();
    final query = search.trim().toLowerCase();
    return snapshot.docs
        .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
        .where((row) {
          if (module?.isNotEmpty == true && row['module'] != module) {
            return false;
          }
          if (query.isEmpty) return true;
          return [
            row['action'],
            row['module'],
            row['target_type'],
            row['target_id'],
            row['actor_name'],
          ].any((value) => '$value'.toLowerCase().contains(query));
        })
        .toList();
  }

  Stream<List<Announcement>> publishedAnnouncementsStream({
    required AppUser user,
  }) {
    if (!firebaseEnabled) {
      return Stream.value(const <Announcement>[]);
    }
    Stream<List<Announcement>> query(Query<Map<String, dynamic>> query) =>
        query.snapshots().map(
          (snapshot) => snapshot.docs
              .map(Announcement.fromFirestore)
              .where((item) => item.title.trim().isNotEmpty)
              .toList(growable: false),
        );
    final collection = _firestore.collection('announcements');
    final streams = <Stream<List<Announcement>>>[
      query(
        collection
            .where('status', isEqualTo: 'published')
            .where('audience', isEqualTo: 'all'),
      ),
      query(
        collection
            .where('status', isEqualTo: 'published')
            .where(
              'audience',
              isEqualTo: user.hasClientAccess
                  ? 'approved_clients'
                  : 'pending_users',
            ),
      ),
      for (final roleId in user.effectiveRoleIds)
        query(
          collection
              .where('status', isEqualTo: 'published')
              .where('audience', isEqualTo: 'roles')
              .where('target_role_ids', arrayContains: roleId),
        ),
      query(
        collection
            .where('status', isEqualTo: 'published')
            .where('audience', isEqualTo: 'users')
            .where('target_user_ids', arrayContains: user.uid),
      ),
      for (final groupId in user.propFirmGroupIds)
        query(
          collection
              .where('status', isEqualTo: 'published')
              .where('audience', isEqualTo: 'prop_firm_groups')
              .where('target_prop_firm_group_ids', arrayContains: groupId),
        ),
      if (user.subscriptionLevel.isNotEmpty)
        query(
          collection
              .where('status', isEqualTo: 'published')
              .where('audience', isEqualTo: 'subscription_levels')
              .where(
                'target_access_levels',
                arrayContains: user.subscriptionLevel,
              ),
        ),
    ];
    return _combineAnnouncementStreams(streams).asyncMap((announcements) async {
      final reads = await Future.wait([
        for (final announcement in announcements)
          _firestore
              .collection('announcements')
              .doc(announcement.id)
              .collection('reads')
              .doc(user.uid)
              .get(),
      ]);
      announcements = [
        for (var index = 0; index < announcements.length; index++)
          announcements[index].copyWith(
            isRead: reads[index].exists,
            isAcknowledged: reads[index].data()?['acknowledged_at'] != null,
            isDismissed: reads[index].data()?['dismissed_at'] != null,
          ),
      ];
      announcements = announcements
          .where(
            (announcement) =>
                announcement.isActive &&
                !(announcement.allowDismissal && announcement.isDismissed),
          )
          .toList();
      announcements.sort((left, right) {
        if (left.isPinned != right.isPinned) return left.isPinned ? -1 : 1;
        return (right.publishedAt ?? right.updatedAt).compareTo(
          left.publishedAt ?? left.updatedAt,
        );
      });
      return announcements;
    });
  }

  Future<void> markAnnouncementRead({
    required String announcementId,
    required String uid,
  }) => _firestore
      .collection('announcements')
      .doc(announcementId)
      .collection('reads')
      .doc(uid)
      .set({
        'user_id': uid,
        'read_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));

  Future<void> acknowledgeAnnouncement({
    required String announcementId,
    required String uid,
  }) => _firestore
      .collection('announcements')
      .doc(announcementId)
      .collection('reads')
      .doc(uid)
      .set({
        'user_id': uid,
        'read_at': FieldValue.serverTimestamp(),
        'acknowledged_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));

  Future<void> dismissAnnouncement({
    required String announcementId,
    required String uid,
  }) => _firestore
      .collection('announcements')
      .doc(announcementId)
      .collection('reads')
      .doc(uid)
      .set({
        'user_id': uid,
        'read_at': FieldValue.serverTimestamp(),
        'dismissed_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));

  Stream<List<Announcement>> adminAnnouncementsStream() {
    if (!firebaseEnabled) {
      return Stream.value(const <Announcement>[]);
    }
    return _firestore.collection('announcements').snapshots().map((snapshot) {
      final announcements = snapshot.docs
          .map(Announcement.fromFirestore)
          .where((item) => item.title.trim().isNotEmpty)
          .toList();
      announcements.sort(
        (left, right) => (right.publishedAt ?? right.updatedAt).compareTo(
          left.publishedAt ?? left.updatedAt,
        ),
      );
      return announcements;
    });
  }

  Future<void> adminSaveAnnouncement({
    String? id,
    required String title,
    String titleZh = '',
    required String message,
    String messageZh = '',
    required String shortMessage,
    String shortMessageZh = '',
    String audience = 'all',
    String category = 'general',
    String priority = 'normal',
    bool isPinned = false,
    List<int> targetRoleIds = const [],
    List<String> targetUserIds = const [],
    List<String> targetPropFirmGroupIds = const [],
    List<String> targetAccessLevels = const [],
    required String status,
    DateTime? startAt,
    DateTime? expiresAt,
    List<String> displayLocations = const ['inbox'],
    String imageUrl = '',
    String actionLabel = '',
    String actionLabelZh = '',
    String deepLink = '',
    bool pushNotification = false,
    bool requireAcknowledgement = false,
    bool allowDismissal = true,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Announcement administration requires Firebase.',
      );
    }
    final trimmedTitle = title.trim();
    final trimmedTitleZh = titleZh.trim();
    final trimmedMessage = message.trim();
    final trimmedMessageZh = messageZh.trim();
    if (trimmedTitle.isEmpty && trimmedTitleZh.isEmpty) {
      throw const ApiException(
        'Announcement title is required in English or Chinese.',
      );
    }
    if (trimmedMessage.isEmpty && trimmedMessageZh.isEmpty) {
      throw const ApiException(
        'Announcement message is required in English or Chinese.',
      );
    }
    final reference = id?.trim().isNotEmpty == true
        ? _firestore.collection('announcements').doc(id!.trim())
        : _firestore.collection('announcements').doc();
    final previous = await reference.get();
    final previousData = previous.data();
    final actor = await _firestore
        .collection('users')
        .doc(_firebaseUid())
        .get();
    final now = DateTime.now();
    final effectiveStatus =
        status == 'published' && startAt?.isAfter(now) == true
        ? 'scheduled'
        : status;
    final publishing = effectiveStatus == 'published';
    final previouslyPublishedAt = _nullableFirestoreDate(
      previousData?['published_at'] ?? previousData?['publishedAt'],
    );
    final announcement = Announcement(
      id: reference.id,
      title: trimmedTitle.isNotEmpty ? trimmedTitle : trimmedTitleZh,
      message: trimmedMessage.isNotEmpty ? trimmedMessage : trimmedMessageZh,
      shortMessage: shortMessage.trim().isEmpty
          ? _preview(
              trimmedMessage.isNotEmpty ? trimmedMessage : trimmedMessageZh,
            )
          : shortMessage.trim(),
      audience: audience.trim().isEmpty ? 'all' : audience.trim(),
      status: effectiveStatus,
      createdBy: previousData?['created_by']?.toString() ?? _firebaseUid(),
      createdAt: previousData == null
          ? now
          : _firestoreDate(
              previousData['created_at'] ?? previousData['createdAt'],
            ),
      updatedAt: now,
      publishedAt: publishing ? (previouslyPublishedAt ?? now) : null,
      category: category,
      priority: priority,
      authorName:
          previousData?['author_name']?.toString() ??
          actor.data()?['name']?.toString() ??
          '',
      isPinned: isPinned,
      targetRoleIds: targetRoleIds,
      targetUserIds: targetUserIds,
      targetPropFirmGroupIds: targetPropFirmGroupIds,
      targetAccessLevels: targetAccessLevels,
      titleI18n: {
        if (trimmedTitle.isNotEmpty) 'en': trimmedTitle,
        if (trimmedTitleZh.isNotEmpty) 'zh': trimmedTitleZh,
      },
      shortMessageI18n: {
        if (shortMessage.trim().isNotEmpty) 'en': shortMessage.trim(),
        if (shortMessageZh.trim().isNotEmpty) 'zh': shortMessageZh.trim(),
      },
      messageI18n: {
        if (trimmedMessage.isNotEmpty) 'en': trimmedMessage,
        if (trimmedMessageZh.isNotEmpty) 'zh': trimmedMessageZh,
      },
      startAt: startAt,
      expiresAt: expiresAt,
      displayLocations: displayLocations,
      imageUrl: imageUrl.trim(),
      actionLabelI18n: {
        if (actionLabel.trim().isNotEmpty) 'en': actionLabel.trim(),
        if (actionLabelZh.trim().isNotEmpty) 'zh': actionLabelZh.trim(),
      },
      deepLink: deepLink.trim(),
      pushNotification: pushNotification,
      requireAcknowledgement: requireAcknowledgement,
      allowDismissal: allowDismissal,
    );
    await reference.set(announcement.toFirestore(), SetOptions(merge: true));
    await _writeAudit(
      action: previous.exists ? 'update' : 'create',
      module: 'announcements',
      targetType: 'announcement',
      targetId: reference.id,
      oldValues: previousData,
      newValues: announcement.toJson(),
    );
  }

  Future<void> adminArchiveAnnouncement(String id) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Announcement administration requires Firebase.',
      );
    }
    final reference = _firestore.collection('announcements').doc(id);
    final previous = await reference.get();
    await reference.set({
      'status': 'archived',
      'updated_at': FieldValue.serverTimestamp(),
      'updatedAt': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'archive',
      module: 'announcements',
      targetType: 'announcement',
      targetId: id,
      oldValues: previous.data(),
      newValues: {'status': 'archived'},
    );
  }

  Future<List<Map<String, dynamic>>> adminResourceRecords(
    String collection, {
    int limit = 200,
  }) async {
    _validateAdminCollection(collection);
    if (!firebaseEnabled) {
      throw const ApiException('Resource administration requires Firebase.');
    }
    final snapshot = await _firestore.collection(collection).limit(limit).get();
    return _adminResourceRows(snapshot);
  }

  Stream<List<Map<String, dynamic>>> adminResourceRecordsStream(
    String collection,
  ) {
    _validateAdminCollection(collection);
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Resource administration requires Firebase.'),
      );
    }
    return _firestore
        .collection(collection)
        .snapshots()
        .map(_adminResourceRows);
  }

  List<Map<String, dynamic>> _adminResourceRows(
    QuerySnapshot<Map<String, dynamic>> snapshot,
  ) {
    final rows = snapshot.docs
        .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
        .toList();
    rows.sort(
      (left, right) => _firestoreDate(
        right['updated_at'] ?? right['created_at'],
      ).compareTo(_firestoreDate(left['updated_at'] ?? left['created_at'])),
    );
    return rows;
  }

  Future<void> adminSaveResource({
    required String collection,
    String? id,
    required Map<String, Object?> values,
  }) async {
    _validateAdminCollection(collection);
    if (collection == 'trading_signals') {
      throw const ApiException(
        'Use the controlled signal publisher or lifecycle update workflow.',
      );
    }
    if (!firebaseEnabled) {
      throw const ApiException('Resource administration requires Firebase.');
    }
    final reference = id?.trim().isNotEmpty == true
        ? _firestore.collection(collection).doc(id)
        : _firestore.collection(collection).doc();
    final previous = await reference.get();
    final cleaned = _manualBilingualAdminValues(collection, <String, Object?>{
      for (final entry in values.entries)
        if (entry.key != 'id' && entry.key != 'created_by')
          entry.key: entry.value,
    });
    final publishesMarketAnalysis =
        collection == 'market_analyses' && cleaned['status'] == 'published';
    final publishesTradingSignal =
        collection == 'trading_signals' &&
        cleaned['lifecycle_status'] == 'active';
    await reference.set({
      ...cleaned,
      if (!previous.exists) 'created_by': _firebaseUid(),
      if (!previous.exists) 'created_at': FieldValue.serverTimestamp(),
      'updated_by': _firebaseUid(),
      if (collection == 'market_analyses')
        'is_published': publishesMarketAnalysis,
      if (publishesMarketAnalysis || publishesTradingSignal)
        'published_by': _firebaseUid(),
      if (cleaned['status'] == 'published' ||
          cleaned['publication_status'] == 'published' ||
          cleaned['lifecycle_status'] == 'active')
        'published_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: previous.exists ? 'update' : 'create',
      module: collection,
      targetType: collection,
      targetId: reference.id,
      oldValues: previous.data(),
      newValues: cleaned,
    );
  }

  Map<String, Object?> _manualBilingualAdminValues(
    String collection,
    Map<String, Object?> rawValues,
  ) {
    final values = <String, Object?>{
      for (final entry in rawValues.entries)
        if (!entry.key.endsWith('_zh')) entry.key: entry.value,
    };
    final bilingualFields = _adminBilingualFields[collection] ?? const {};
    final flexiblePublishingContent = const {
      'trading_signals',
      'market_analyses',
    }.contains(collection);
    final primaryLanguage = _publishingLanguage(rawValues['primary_language']);
    final localizedMaps = <Map<String, String>>[];

    for (final field in bilingualFields) {
      final english = _textValue(rawValues[field]);
      final chinese = _textValue(rawValues['${field}_zh']);
      if (english.isEmpty && chinese.isEmpty) continue;
      if (flexiblePublishingContent) {
        final localized = {
          if (english.isNotEmpty) 'en': english,
          if (chinese.isNotEmpty) 'zh': chinese,
        };
        localizedMaps.add(localized);
        values[field] = primaryLanguage == 'zh'
            ? chinese.isNotEmpty
                  ? chinese
                  : english
            : english.isNotEmpty
            ? english
            : chinese;
        values['${field}_i18n'] = localized;
      } else {
        values[field] = english.isNotEmpty ? english : chinese;
        values['${field}_i18n'] = _bilingualMap(
          label: _fieldLabel(field),
          english: english,
          chinese: chinese,
          required: true,
        );
      }
    }

    if (flexiblePublishingContent) {
      final publishing = collection == 'trading_signals'
          ? values['lifecycle_status'] == 'active'
          : values['status'] == 'published';
      final requiredFields = collection == 'trading_signals'
          ? const ['title']
          : const ['title', 'market_overview', 'trading_plan'];
      if (publishing) {
        for (final field in requiredFields) {
          final localized = values['${field}_i18n'];
          final primaryValue = localized is Map
              ? _textValue(localized[primaryLanguage])
              : '';
          if (primaryValue.isEmpty) {
            throw ApiException(
              primaryLanguage == 'zh'
                  ? '${_fieldLabel(field)} requires Simplified Chinese content.'
                  : '${_fieldLabel(field)} requires English content.',
            );
          }
        }
      }
      values['primary_language'] = primaryLanguage;
      values['available_languages'] = [
        for (final code in const ['en', 'zh'])
          if (localizedMaps.any(
            (localized) => (localized[code] ?? '').trim().isNotEmpty,
          ))
            code,
      ];
    }

    if (collection == 'knowledge_centres') {
      final category = KnowledgeCentreCategory.normalizeSlug(
        rawValues['category'],
      );
      if (category.isNotEmpty) values['category'] = category;
      final title = _bilingualFromValues(values, 'title');
      final description = _bilingualFromValues(values, 'description');
      if (title.isNotEmpty && description.isNotEmpty) {
        values['locales'] = {
          'en': {
            'title': title['en'] ?? title['zh'],
            'shortDescription': description['en'] ?? description['zh'],
            'fullContent': description['en'] ?? description['zh'],
            'keyPoints': <String>[],
            'examples': <String>[],
            'commonMistakes': <String>[],
            'checklist': <String>[],
          },
          'zh': {
            'title': title['zh'] ?? title['en'],
            'shortDescription': description['zh'] ?? description['en'],
            'fullContent': description['zh'] ?? description['en'],
            'keyPoints': <String>[],
            'examples': <String>[],
            'commonMistakes': <String>[],
            'checklist': <String>[],
          },
        };
      }
    }

    if (collection == 'trading_exam_questions' ||
        collection == 'final_exam_questions') {
      final questionKey = values.containsKey('question_text')
          ? 'question_text'
          : 'question';
      final question = _bilingualFromValues(values, questionKey);
      final category = _bilingualFromValues(values, 'category');
      final section = _bilingualFromValues(values, 'section');
      final scenario = _bilingualFromValues(values, 'scenario_text');
      final explanation = _bilingualFromValues(values, 'explanation');
      final optionI18n = <String, Map<String, String>>{};
      final optionTexts = <String, Map<String, Object?>>{};

      for (final key in const ['A', 'B', 'C', 'D']) {
        final field = 'option_${key.toLowerCase()}';
        final english = _textValue(rawValues[field]);
        final chinese = _textValue(rawValues['${field}_zh']);
        if (english.isEmpty && chinese.isEmpty) continue;
        final map = _bilingualMap(
          label: 'Option $key',
          english: english,
          chinese: chinese,
          required: true,
        );
        optionI18n[key] = map;
        optionTexts[key] = {
          'id': key,
          'option_text': map['en'],
          'text': map['en'],
          'option_text_i18n': map,
          'text_i18n': map,
          'is_correct': '${values['correct_answer'] ?? 'A'}' == key,
        };
        values.remove(field);
      }
      if (optionTexts.isNotEmpty) {
        values['options'] = optionTexts;
        values['options_i18n'] = optionI18n;
      }

      final enOptions = [
        for (final key in const ['A', 'B', 'C', 'D'])
          if (optionI18n[key]?['en']?.isNotEmpty == true)
            optionI18n[key]!['en'],
      ];
      final zhOptions = [
        for (final key in const ['A', 'B', 'C', 'D'])
          if (optionI18n[key]?['en']?.isNotEmpty == true)
            optionI18n[key]!['zh'] ?? optionI18n[key]!['en'],
      ];
      if (question.isNotEmpty && enOptions.isNotEmpty) {
        values['locales'] = {
          'en': {
            if (category.isNotEmpty) 'category': category['en'],
            if (section.isNotEmpty) 'section': section['en'],
            'question': question['en'],
            if (scenario.isNotEmpty) 'scenario_text': scenario['en'],
            'options': enOptions,
            if (explanation.isNotEmpty) 'explanation': explanation['en'],
          },
          'zh': {
            if (category.isNotEmpty)
              'category': category['zh'] ?? category['en'],
            if (section.isNotEmpty) 'section': section['zh'] ?? section['en'],
            'question': question['zh'] ?? question['en'],
            if (scenario.isNotEmpty)
              'scenario_text': scenario['zh'] ?? scenario['en'],
            'options': zhOptions,
            if (explanation.isNotEmpty)
              'explanation': explanation['zh'] ?? explanation['en'],
          },
        };
      }
    }

    return values;
  }

  Map<String, String> _requiredBilingualMap({
    required String label,
    required String english,
    required String chinese,
  }) => _bilingualMap(
    label: label,
    english: english,
    chinese: chinese,
    required: true,
  );

  Map<String, String> _optionalBilingualMap({
    required String label,
    required String english,
    required String chinese,
  }) => _bilingualMap(
    label: label,
    english: english,
    chinese: chinese,
    required: false,
  );

  Map<String, String> _bilingualMap({
    required String label,
    required Object? english,
    required Object? chinese,
    required bool required,
  }) {
    final en = _textValue(english);
    final zh = _textValue(chinese);
    if (required && en.isEmpty && zh.isEmpty) {
      throw ApiException(
        '$label requires English or Simplified Chinese content.',
      );
    }
    return {if (en.isNotEmpty) 'en': en, if (zh.isNotEmpty) 'zh': zh};
  }

  Map<String, String> _bilingualFromValues(
    Map<String, Object?> values,
    String key,
  ) {
    final i18n = values['${key}_i18n'];
    if (i18n is Map) {
      final en = _textValue(i18n['en']);
      final zh = _textValue(i18n['zh']);
      if (en.isNotEmpty || zh.isNotEmpty) {
        return {if (en.isNotEmpty) 'en': en, if (zh.isNotEmpty) 'zh': zh};
      }
    }
    final value = values[key];
    if (value is Map) {
      final en = _textValue(value['en']);
      final zh = _textValue(value['zh']);
      if (en.isNotEmpty || zh.isNotEmpty) {
        return {if (en.isNotEmpty) 'en': en, if (zh.isNotEmpty) 'zh': zh};
      }
    }
    return const {};
  }

  String _textValue(Object? value) => value?.toString().trim() ?? '';

  String _publishingLanguage(Object? value) =>
      _textValue(value).toLowerCase().startsWith('zh') ? 'zh' : 'en';

  String _signalUpdateDocumentId(String value) {
    final normalized = value.trim().replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '-');
    if (normalized.isEmpty || normalized.length > 160) {
      throw const SignalLifecycleException(
        'The signal update idempotency key is invalid.',
      );
    }
    return normalized;
  }

  String _fieldLabel(String key) => key
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  Future<void> adminArchiveResource({
    required String collection,
    required String id,
    String? reason,
  }) async {
    _validateAdminCollection(collection);
    if (collection == 'trading_signals') {
      throw const ApiException(
        'Archive signals through their lifecycle timeline.',
      );
    }
    final reference = _firestore.collection(collection).doc(id);
    final previous = await reference.get();
    if (!previous.exists) throw const ApiException('Resource not found.');
    final boolStatus = const {
      'trading_recordings',
      'knowledge_centres',
    }.contains(collection);
    await reference.set({
      'status': boolStatus ? false : 'archived',
      'status_before_archive': previous.data()?['status'],
      'publication_status': 'archived',
      'publication_status_before_archive': previous
          .data()?['publication_status'],
      'archived': true,
      'archived_at': FieldValue.serverTimestamp(),
      'archived_by': _firebaseUid(),
      'deleted_at': FieldValue.serverTimestamp(),
      'deleted_by': _firebaseUid(),
      'delete_reason': reason?.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'soft_delete',
      module: collection,
      targetType: collection,
      targetId: id,
      oldValues: previous.data(),
      newValues: {
        'status': boolStatus ? false : 'archived',
        'archived': true,
        'reason': reason,
      },
    );
  }

  Future<void> adminRestoreResource({
    required String collection,
    required String id,
  }) async {
    _validateAdminCollection(collection);
    if (collection == 'trading_signals') {
      throw const ApiException(
        'Restore signals through the Super Admin correction workflow.',
      );
    }
    final reference = _firestore.collection(collection).doc(id);
    final previous = await reference.get();
    final data = previous.data();
    if (data == null) throw const ApiException('Resource not found.');
    if (data['deleted_at'] == null && data['archived'] != true) {
      throw const ApiException('This resource is not archived.');
    }
    final fallbackStatus =
        const {'trading_recordings', 'knowledge_centres'}.contains(collection)
        ? true
        : 'draft';
    final restoredStatus = data['status_before_archive'] ?? fallbackStatus;
    final restoredPublicationStatus =
        data['publication_status_before_archive'] ??
        (restoredStatus == true || restoredStatus == 'published'
            ? 'published'
            : 'draft');
    await reference.set({
      'status': restoredStatus,
      'publication_status': restoredPublicationStatus,
      'archived': false,
      'archived_at': FieldValue.delete(),
      'archived_by': FieldValue.delete(),
      'deleted_at': FieldValue.delete(),
      'deleted_by': FieldValue.delete(),
      'delete_reason': FieldValue.delete(),
      'status_before_archive': FieldValue.delete(),
      'publication_status_before_archive': FieldValue.delete(),
      'restored_at': FieldValue.serverTimestamp(),
      'restored_by': _firebaseUid(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'restore',
      module: collection,
      targetType: collection,
      targetId: id,
      oldValues: data,
      newValues: {'status': restoredStatus, 'archived': false},
    );
  }

  Future<void> submitDeleteRequest({
    required String targetType,
    required String targetId,
    required String reason,
  }) async {
    if (!const {'market_analyses', 'trading_resources'}.contains(targetType)) {
      throw const ApiException('Delete requests are not supported here.');
    }
    if (reason.trim().isEmpty) {
      throw const ApiException('A deletion reason is required.');
    }
    final existing = await _firestore
        .collection('delete_requests')
        .where('target_type', isEqualTo: targetType)
        .get();
    if (existing.docs.any(
      (doc) =>
          doc.data()['target_id'] == targetId &&
          doc.data()['status'] == 'pending',
    )) {
      throw const ApiException('A delete request is already pending.');
    }
    final request = _firestore.collection('delete_requests').doc();
    await request.set({
      'requester_id': _firebaseUid(),
      'target_type': targetType,
      'target_id': targetId,
      'reason': reason.trim(),
      'status': 'pending',
      'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    });
    await _writeAudit(
      action: 'request_delete',
      module: targetType,
      targetType: targetType,
      targetId: targetId,
      newValues: {'reason': reason.trim(), 'request_id': request.id},
    );
  }

  Future<List<Map<String, dynamic>>> adminDeleteRequests() async {
    final snapshot = await _firestore
        .collection('delete_requests')
        .orderBy('created_at', descending: true)
        .get();
    return snapshot.docs
        .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
        .toList();
  }

  Future<void> adminReviewDeleteRequest({
    required String requestId,
    required bool approve,
    String? note,
  }) async {
    final requestReference = _firestore
        .collection('delete_requests')
        .doc(requestId);
    final request = await requestReference.get();
    final data = request.data();
    if (data == null || data['status'] != 'pending') {
      throw const ApiException('This delete request is no longer pending.');
    }
    final targetType = '${data['target_type']}';
    if (!const {'market_analyses', 'trading_resources'}.contains(targetType)) {
      throw const ApiException('Unsupported delete-request target.');
    }
    final batch = _firestore.batch();
    if (approve) {
      batch.set(
        _firestore.collection(targetType).doc('${data['target_id']}'),
        {
          'status': 'archived',
          'deleted_at': FieldValue.serverTimestamp(),
          'deleted_by': _firebaseUid(),
          'updated_at': FieldValue.serverTimestamp(),
        },
        SetOptions(merge: true),
      );
    }
    batch.set(requestReference, {
      'status': approve ? 'approved' : 'rejected',
      'reviewed_by': _firebaseUid(),
      'reviewed_at': FieldValue.serverTimestamp(),
      'review_note': note?.trim(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await batch.commit();
    await _writeAudit(
      action: approve ? 'approve' : 'reject',
      module: 'delete_requests',
      targetType: targetType,
      targetId: '${data['target_id']}',
      oldValues: data,
      newValues: {'review_note': note, 'request_id': requestId},
    );
  }

  Future<List<Map<String, dynamic>>> adminTradingJournals() async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Trading journal administration requires Firebase.',
      );
    }
    final snapshot = await _adminGet(
      'users/*/trading_journals',
      _firestore.collectionGroup('trading_journals'),
    );
    return _adminTradingJournalRows(snapshot);
  }

  Stream<List<Map<String, dynamic>>> adminTradingJournalsStream() {
    if (!firebaseEnabled) {
      return Stream.error(
        const ApiException('Trading journal administration requires Firebase.'),
      );
    }
    return _firestore
        .collectionGroup('trading_journals')
        .snapshots()
        .asyncMap(_adminTradingJournalRows);
  }

  Future<List<Map<String, dynamic>>> _adminTradingJournalRows(
    QuerySnapshot<Map<String, dynamic>> snapshot,
  ) async {
    final users = <String, Map<String, dynamic>>{};
    final rows = <Map<String, dynamic>>[];
    for (final document in snapshot.docs) {
      final data = document.data();
      final uid =
          '${data['user_id'] ?? document.reference.parent.parent?.id ?? ''}';
      if (uid.isNotEmpty && !users.containsKey(uid)) {
        final user = await _firestore.collection('users').doc(uid).get();
        users[uid] = user.exists ? _adminUserMap(user) : {'id': uid};
      }
      rows.add({
        'id': document.id,
        'document_path': document.reference.path,
        ...data,
        'user': users[uid] ?? {'id': uid},
      });
    }
    rows.sort(
      (left, right) => _firestoreDate(
        right['close_date'] ?? right['created_at'],
      ).compareTo(_firestoreDate(left['close_date'] ?? left['created_at'])),
    );
    return rows;
  }

  Future<void> adminSaveJournalNote({
    required String documentPath,
    required String note,
  }) async {
    if (!RegExp(
      r'^users/[^/]+/trading_journals/[^/]+$',
    ).hasMatch(documentPath)) {
      throw const ApiException('Invalid trading-journal reference.');
    }
    final reference = _firestore.doc(documentPath);
    final previous = await reference.get();
    if (!previous.exists) {
      throw const ApiException('Trading journal not found.');
    }
    await reference.set({
      'admin_note': note.trim(),
      'admin_noted_by': _firebaseUid(),
      'admin_noted_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: 'review',
      module: 'trading_journals',
      targetType: 'trading_journal',
      targetId: documentPath,
      oldValues: previous.data(),
      newValues: {'admin_note': note.trim()},
    );
  }

  Future<AdminDashboardSnapshot> loadAdminDashboard({
    AdminDashboardRange range = AdminDashboardRange.last30Days,
  }) async {
    final now = DateTime.now();
    if (!firebaseEnabled) {
      final legacy = await adminOverview();
      final counts = Map<String, dynamic>.from(
        legacy['counts'] as Map? ?? const {},
      );
      return AdminDashboardSnapshot(
        metrics: [
          AdminDashboardMetric(
            id: 'users',
            label: 'Total users',
            value: _intValue(counts['users']),
          ),
          AdminDashboardMetric(
            id: 'traders',
            label: 'Active traders',
            value: _intValue(counts['traders']),
          ),
          AdminDashboardMetric(
            id: 'signals',
            label: 'Active signals',
            value: _intValue(counts['active_signals']),
          ),
          AdminDashboardMetric(
            id: 'analyses',
            label: 'Market outlooks',
            value: _intValue(counts['market_analyses']),
          ),
          AdminDashboardMetric(
            id: 'pending',
            label: 'Pending actions',
            value:
                _intValue(counts['pending_onboarding']) +
                _intValue(counts['pending_positions']) +
                _intValue(counts['pending_delete_requests']),
          ),
        ],
        trend: const [],
        pendingActions: const [],
        activities: const [],
        fetchedAt: now,
      );
    }

    final currentStart = range.start(now);
    final previousStart = currentStart.subtract(Duration(days: range.days));
    final userCollection = _firestore.collection('users');
    final signals = _firestore.collection('trading_signals');
    final analyses = _firestore.collection('market_analyses');
    final accessRequests = _firestore.collection('accessRequests');
    final positions = _firestore.collectionGroup(
      'trading_position_applications',
    );
    final deleteRequests = _firestore.collection('delete_requests');

    final counts = await Future.wait<int>([
      _dashboardCount(userCollection),
      _dashboardCount(
        userCollection.where('access_status', isEqualTo: 'approved'),
      ),
      _dashboardCount(
        signals.where('status', whereIn: const ['active', 'published', 1]),
      ),
      _dashboardCount(analyses.where('status', isEqualTo: 'published')),
      _dashboardCount(
        accessRequests.where(
          'status',
          whereIn: const ['pending', 'pendingReview', 'submitted'],
        ),
      ),
      _dashboardCount(positions.where('status', isEqualTo: 'pending')),
      _dashboardCount(deleteRequests.where('status', isEqualTo: 'pending')),
      _dashboardCount(
        userCollection
            .where(
              'created_at',
              isGreaterThanOrEqualTo: Timestamp.fromDate(currentStart),
            )
            .where('created_at', isLessThan: Timestamp.fromDate(now)),
      ),
      _dashboardCount(
        userCollection
            .where(
              'created_at',
              isGreaterThanOrEqualTo: Timestamp.fromDate(previousStart),
            )
            .where('created_at', isLessThan: Timestamp.fromDate(currentStart)),
      ),
    ]);

    final trendFuture = _loadDashboardTrend(range: range, now: now);
    final pendingFuture = _loadDashboardPendingActions();
    final activitiesFuture = _loadDashboardActivities();
    final results = await Future.wait<Object>([
      trendFuture,
      pendingFuture,
      activitiesFuture,
    ]);

    return AdminDashboardSnapshot(
      metrics: [
        AdminDashboardMetric(
          id: 'users',
          label: 'Total users',
          value: counts[0],
        ),
        AdminDashboardMetric(
          id: 'approved',
          label: 'Approved clients',
          value: counts[1],
        ),
        AdminDashboardMetric(
          id: 'signals',
          label: 'Active signals',
          value: counts[2],
        ),
        AdminDashboardMetric(
          id: 'analyses',
          label: 'Market outlooks',
          value: counts[3],
        ),
        AdminDashboardMetric(
          id: 'pending',
          label: 'Pending actions',
          value: counts[4] + counts[5] + counts[6],
        ),
        AdminDashboardMetric(
          id: 'registrations',
          label: 'New registrations',
          value: counts[7],
          previousValue: counts[8],
        ),
      ],
      trend: results[0] as List<AdminDashboardTrendPoint>,
      pendingActions: results[1] as List<AdminPendingAction>,
      activities: results[2] as List<AdminActivityItem>,
      fetchedAt: DateTime.now(),
    );
  }

  Future<int> _dashboardCount(Query<Map<String, dynamic>> query) async {
    try {
      final snapshot = await query.count().get();
      return snapshot.count ?? 0;
    } catch (error, stackTrace) {
      debugPrint('Dashboard aggregate skipped: $error');
      debugPrintStack(stackTrace: stackTrace);
      return 0;
    }
  }

  Future<List<AdminDashboardTrendPoint>> _loadDashboardTrend({
    required AdminDashboardRange range,
    required DateTime now,
  }) async {
    final start = range.start(now);
    const buckets = 7;
    final bucketDays = max(1, (range.days / buckets).ceil());
    final points = <AdminDashboardTrendPoint>[];
    for (var index = 0; index < buckets; index++) {
      final bucketStart = start.add(Duration(days: index * bucketDays));
      if (bucketStart.isAfter(now)) break;
      final bucketEnd = index == buckets - 1
          ? now.add(const Duration(seconds: 1))
          : minDate(
              start.add(Duration(days: (index + 1) * bucketDays)),
              now.add(const Duration(seconds: 1)),
            );
      final values = await Future.wait<int>([
        _dashboardCount(
          _firestore
              .collection('users')
              .where(
                'created_at',
                isGreaterThanOrEqualTo: Timestamp.fromDate(bucketStart),
              )
              .where('created_at', isLessThan: Timestamp.fromDate(bucketEnd)),
        ),
        _dashboardCount(
          _firestore
              .collection('trading_signals')
              .where(
                'created_at',
                isGreaterThanOrEqualTo: Timestamp.fromDate(bucketStart),
              )
              .where('created_at', isLessThan: Timestamp.fromDate(bucketEnd)),
        ),
        _dashboardCount(
          _firestore
              .collection('market_analyses')
              .where(
                'created_at',
                isGreaterThanOrEqualTo: Timestamp.fromDate(bucketStart),
              )
              .where('created_at', isLessThan: Timestamp.fromDate(bucketEnd)),
        ),
      ]);
      points.add(
        AdminDashboardTrendPoint(
          date: bucketStart,
          users: values[0],
          signals: values[1],
          analyses: values[2],
        ),
      );
    }
    return points;
  }

  Future<List<AdminPendingAction>> _loadDashboardPendingActions() async {
    final items = <AdminPendingAction>[];

    Future<void> addFromQuery({
      required Query<Map<String, dynamic>> query,
      required String kind,
      required String title,
      required int tab,
    }) async {
      try {
        final snapshot = await query.limit(7).get();
        for (final doc in snapshot.docs) {
          final data = doc.data();
          items.add(
            AdminPendingAction(
              id: doc.id,
              kind: kind,
              title: title,
              subtitle:
                  _textValue(
                    data['name'] ??
                        data['email'] ??
                        data['user_id'] ??
                        data['userId'] ??
                        data['requester_id'] ??
                        data['target_id'],
                  ).trim().isEmpty
                  ? doc.id
                  : _textValue(
                      data['name'] ??
                          data['email'] ??
                          data['user_id'] ??
                          data['userId'] ??
                          data['requester_id'] ??
                          data['target_id'],
                    ),
              targetTab: tab,
              createdAt: _nullableFirestoreDate(
                data['created_at'] ??
                    data['submitted_at'] ??
                    data['requested_at'],
              ),
            ),
          );
        }
      } catch (error, stackTrace) {
        debugPrint('Pending dashboard query skipped: $error');
        debugPrintStack(stackTrace: stackTrace);
      }
    }

    await Future.wait([
      addFromQuery(
        query: _firestore
            .collection('accessRequests')
            .where(
              'status',
              whereIn: const ['pending', 'pendingReview', 'submitted'],
            ),
        kind: 'access',
        title: 'Client access review',
        tab: 5,
      ),
      addFromQuery(
        query: _firestore
            .collectionGroup('trading_position_applications')
            .where('status', isEqualTo: 'pending'),
        kind: 'position',
        title: 'Position application',
        tab: 6,
      ),
      addFromQuery(
        query: _firestore
            .collection('delete_requests')
            .where('status', isEqualTo: 'pending'),
        kind: 'delete',
        title: 'Deletion request',
        tab: 11,
      ),
    ]);
    items.sort(
      (left, right) => (right.createdAt ?? DateTime(1900)).compareTo(
        left.createdAt ?? DateTime(1900),
      ),
    );
    return items.take(12).toList(growable: false);
  }

  Future<List<AdminActivityItem>> _loadDashboardActivities() async {
    try {
      final snapshot = await _firestore
          .collection('audit_logs')
          .orderBy('created_at', descending: true)
          .limit(12)
          .get();
      return snapshot.docs
          .map((doc) {
            final data = doc.data();
            return AdminActivityItem(
              id: doc.id,
              action: _textValue(data['action']).isEmpty
                  ? 'updated'
                  : _textValue(data['action']),
              module: _textValue(data['module']),
              actor:
                  _textValue(
                    data['actor_name'] ??
                        data['actor_email'] ??
                        data['actor_id'],
                  ).isEmpty
                  ? 'Administrator'
                  : _textValue(
                      data['actor_name'] ??
                          data['actor_email'] ??
                          data['actor_id'],
                    ),
              target: _textValue(
                data['target_name'] ?? data['target_id'] ?? data['target_type'],
              ),
              createdAt: _nullableFirestoreDate(
                data['created_at'] ?? data['timestamp'],
              ),
            );
          })
          .toList(growable: false);
    } catch (error, stackTrace) {
      debugPrint('Dashboard activity query skipped: $error');
      debugPrintStack(stackTrace: stackTrace);
      return const [];
    }
  }

  DateTime minDate(DateTime left, DateTime right) =>
      left.isBefore(right) ? left : right;

  Future<List<Map<String, dynamic>>> adminExamAttempts() async {
    if (!firebaseEnabled) {
      throw const ApiException('Exam result review requires Firebase.');
    }
    final results = await Future.wait([
      _firestore.collectionGroup('trading_exam_attempts').get(),
      _firestore.collectionGroup('final_exam_attempts').get(),
    ]);
    final rows = <Map<String, dynamic>>[];
    for (var groupIndex = 0; groupIndex < results.length; groupIndex++) {
      for (final document in results[groupIndex].docs) {
        final data = document.data();
        final uid =
            '${data['user_id'] ?? document.reference.parent.parent?.id ?? ''}';
        final user = uid.isEmpty
            ? null
            : await _firestore.collection('users').doc(uid).get();
        rows.add({
          'id': document.id,
          'exam_type': groupIndex == 0
              ? 'Legacy Daily Exam'
              : 'Final Examination',
          ...data,
          'user': user?.exists == true ? _adminUserMap(user!) : {'id': uid},
        });
      }
    }
    rows.sort(
      (left, right) => _firestoreDate(
        right['completed_at'] ?? right['created_at'],
      ).compareTo(_firestoreDate(left['completed_at'] ?? left['created_at'])),
    );
    return rows;
  }

  Future<void> adminReviewDailyQuiz({
    required String userId,
    required String attemptId,
    required Map<String, double> scores,
    required Map<String, String> feedback,
    String reviewNote = '',
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Daily quiz review requires Firebase.');
    }
    try {
      await _callableService.call('reviewDailyQuizSubmission', {
        'userId': userId,
        'attemptId': attemptId,
        'reviewNote': reviewNote.trim(),
        'decisions': {
          for (final entry in scores.entries)
            entry.key: {
              'score': entry.value,
              'feedback': feedback[entry.key]?.trim() ?? '',
            },
        },
      });
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(error.message);
    }
  }

  Future<Map<String, dynamic>> adminOverview() async {
    if (firebaseEnabled) {
      final results = await Future.wait([
        _adminGet('users', _firestore.collection('users')),
        _adminGet(
          'users/*/trading_journals',
          _firestore.collectionGroup('trading_journals'),
        ),
        _adminGet(
          'users/*/trader_onboarding',
          _firestore.collectionGroup('trader_onboarding'),
        ),
        _adminGet(
          'users/*/trading_position_applications',
          _firestore.collectionGroup('trading_position_applications'),
        ),
        _adminGet(
          'trading_recordings',
          _firestore.collection('trading_recordings'),
        ),
        _adminGet(
          'knowledge_centres',
          _firestore.collection('knowledge_centres'),
        ),
        _adminGet(
          'trading_competitions',
          _firestore.collection('trading_competitions'),
        ),
        _adminGet(
          'trading_appointments',
          _firestore.collection('trading_appointments'),
        ),
        _adminGet('support_tickets', _firestore.collection('support_tickets')),
        _adminGet(
          'community_documents',
          _firestore.collection('community_documents'),
        ),
        _adminGet('market_analyses', _firestore.collection('market_analyses')),
        _adminGet('trading_signals', _firestore.collection('trading_signals')),
        _adminGet(
          'trading_resources',
          _firestore.collection('trading_resources'),
        ),
        _adminGet('trading_news', _firestore.collection('trading_news')),
        _adminGet(
          'trading_exam_questions',
          _firestore.collection('trading_exam_questions'),
        ),
        _adminGet(
          'users/*/final_exam_attempts',
          _firestore.collectionGroup('final_exam_attempts'),
        ),
        _adminGet('delete_requests', _firestore.collection('delete_requests')),
        _adminGet(
          'prop_firm_models',
          _firestore.collection('prop_firm_models'),
        ),
        _adminGet(
          'trading_appointment_slots',
          _firestore.collection('trading_appointment_slots'),
        ),
      ]);
      final users = results[0];
      final journals = results[1];
      final onboarding = results[2];
      final positions = results[3];
      final recordings = results[4];
      final knowledge = results[5];
      final competitions = results[6];
      final appointments = results[7];
      final tickets = results[8];
      final communityDocuments = results[9];
      final analyses = results[10];
      final signals = results[11];
      final resources = results[12];
      final news = results[13];
      final exams = results[14];
      final finalAttempts = results[15];
      final deleteRequests = results[16];
      final propFirmModels = results[17];
      final recentUsers = [...users.docs]
        ..sort(
          (left, right) => _firestoreDate(
            right.data()['created_at'],
          ).compareTo(_firestoreDate(left.data()['created_at'])),
        );
      return {
        'counts': {
          'users': users.docs.length,
          'traders': users.docs
              .where((doc) => _intValue(doc.data()['role_id']) == 750)
              .length,
          'market_analysts': users.docs
              .where((doc) => _intValue(doc.data()['role_id']) == 501)
              .length,
          'signal_providers': users.docs
              .where(
                (doc) => const {
                  201,
                  202,
                  502,
                }.contains(_intValue(doc.data()['role_id'])),
              )
              .length,
          'trading_journals': journals.docs.length,
          'pending_onboarding': onboarding.docs
              .where((doc) => doc.data()['status'] == 'pending')
              .length,
          'pending_positions': positions.docs
              .where((doc) => doc.data()['status'] == 'pending')
              .length,
          'recording_classes': recordings.docs.length,
          'knowledge_items': knowledge.docs.length,
          'competitions': competitions.docs.length,
          'pending_appointments': appointments.docs
              .where((doc) => doc.data()['status'] == 'pending')
              .length,
          'open_support_tickets': tickets.docs
              .where((doc) => doc.data()['status'] != 'closed')
              .length,
          'closed_support_tickets': tickets.docs
              .where((doc) => doc.data()['status'] == 'closed')
              .length,
          'pending_ticket_replies': tickets.docs
              .where(
                (doc) =>
                    const {'open', 'reopened'}.contains(doc.data()['status']),
              )
              .length,
          'appointment_slots': results[18].docs.length,
          'upcoming_bookings': appointments.docs
              .where(
                (doc) =>
                    const {
                      'pending',
                      'approved',
                    }.contains(doc.data()['status']) &&
                    _firestoreDate(
                      doc.data()['scheduled_start_at'],
                    ).isAfter(DateTime.now()),
              )
              .length,
          'trading_exams': exams.docs.length,
          'final_exam_attempts': finalAttempts.docs.length,
          'pending_prop_reviews': users.docs
              .where(
                (doc) => const {
                  'pending',
                  'requested',
                }.contains(doc.data()['prop_firm_review_status']),
              )
              .length,
          'published_news': news.docs
              .where((doc) => doc.data()['status'] == 'published')
              .length,
          'active_signals': signals.docs
              .where(
                (doc) => const {
                  'active',
                  'published',
                  1,
                }.contains(doc.data()['status']),
              )
              .length,
          'active_marketing_resources': resources.docs
              .where(
                (doc) =>
                    !const {false, 'archived'}.contains(doc.data()['status']),
              )
              .length,
          'pending_delete_requests': deleteRequests.docs
              .where((doc) => doc.data()['status'] == 'pending')
              .length,
          'market_analyses': analyses.docs.length,
          'prop_firm_models': propFirmModels.docs.length,
          'community_documents': communityDocuments.docs.length,
        },
        'recent_users': recentUsers.take(25).map(_adminUserMap).toList(),
      };
    }
    if (!_usingApi) {
      throw const ApiException('Connect to the server to load admin data.');
    }
    final response = await apiClient!.get('admin/overview');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Stream<Map<String, dynamic>> adminOverviewStream() {
    if (!firebaseEnabled) return Stream.fromFuture(adminOverview());
    late StreamController<Map<String, dynamic>> controller;
    final subscriptions =
        <StreamSubscription<QuerySnapshot<Map<String, dynamic>>>>[];
    Timer? debounce;
    var loading = false;
    var queued = false;

    Future<void> refresh() async {
      if (loading) {
        queued = true;
        return;
      }
      loading = true;
      try {
        controller.add(await adminOverview());
      } catch (error, stackTrace) {
        controller.addError(error, stackTrace);
      } finally {
        loading = false;
        if (queued) {
          queued = false;
          unawaited(refresh());
        }
      }
    }

    void scheduleRefresh() {
      debounce?.cancel();
      debounce = Timer(const Duration(milliseconds: 180), refresh);
    }

    controller = StreamController<Map<String, dynamic>>(
      onListen: () {
        final queries = <Query<Map<String, dynamic>>>[
          _firestore.collection('users'),
          _firestore.collectionGroup('trading_journals'),
          _firestore.collectionGroup('trader_onboarding'),
          _firestore.collectionGroup('trading_position_applications'),
          _firestore.collection('trading_recordings'),
          _firestore.collection('knowledge_centres'),
          _firestore.collection('trading_competitions'),
          _firestore.collection('trading_appointments'),
          _firestore.collection('support_tickets'),
          _firestore.collection('community_documents'),
          _firestore.collection('market_analyses'),
          _firestore.collection('trading_signals'),
          _firestore.collection('trading_resources'),
          _firestore.collection('trading_news'),
          _firestore.collection('trading_exam_questions'),
          _firestore.collectionGroup('final_exam_attempts'),
          _firestore.collection('delete_requests'),
          _firestore.collection('prop_firm_models'),
          _firestore.collection('trading_appointment_slots'),
        ];
        for (final query in queries) {
          subscriptions.add(
            query.snapshots().listen(
              (_) => scheduleRefresh(),
              onError: (Object error, StackTrace stackTrace) =>
                  controller.addError(error, stackTrace),
            ),
          );
        }
      },
      onCancel: () async {
        debounce?.cancel();
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      },
    );
    return controller.stream;
  }

  Future<Map<String, dynamic>> adminUsers({
    String search = '',
    String? roleId,
    int? status,
    int page = 1,
  }) async {
    if (firebaseEnabled) {
      final snapshot = await _firestore.collection('users').get();
      var users = snapshot.docs.map(_adminUserMap).toList();
      final query = search.trim().toLowerCase();
      if (query.isNotEmpty) {
        users = users
            .where(
              (user) => [
                user['name'],
                user['username'],
                user['email'],
              ].any((value) => '$value'.toLowerCase().contains(query)),
            )
            .toList();
      }
      if (roleId?.isNotEmpty == true) {
        users = users.where((user) => '${user['role_id']}' == roleId).toList();
      }
      if (status != null) {
        users = users
            .where((user) => _intValue(user['status']) == status)
            .toList();
      }
      users.sort(
        (left, right) => '${left['name']}'.compareTo('${right['name']}'),
      );
      final roleDocs = await _firestore.collection('registration_roles').get();
      final roles = <Map<String, dynamic>>[
        {'id': 1, 'name': 'Super Admin', 'description': 'Full administration'},
        {'id': 2, 'name': 'Admin', 'description': 'Administration'},
        {
          'id': 201,
          'name': 'Signal Provider',
          'description': 'Publish trading signals',
        },
        {
          'id': 202,
          'name': 'Senior Signal Provider',
          'description': 'Publish trading signals',
        },
        {
          'id': 501,
          'name': 'Market Analyst',
          'description': 'Publish market analysis',
        },
        {
          'id': 502,
          'name': 'Signal Provider Management',
          'description': 'Manage signal providers and signals',
        },
        {'id': 750, 'name': 'Trader', 'description': 'Trading member'},
        {
          'id': 760,
          'name': 'Trading Leadership',
          'description': 'Trading leader',
        },
        {
          'id': 770,
          'name': 'Trading Recruiter',
          'description': 'Trading recruiter',
        },
        for (final doc in roleDocs.docs)
          {'id': doc.data()['role_id'] ?? doc.id, ...doc.data()},
      ];
      final uniqueRoles = <String, Map<String, dynamic>>{
        for (final role in roles) '${role['id']}': role,
      }.values.toList();
      const pageSize = 30;
      final start = (page - 1) * pageSize;
      final paged = start >= users.length
          ? <Map<String, dynamic>>[]
          : users.skip(start).take(pageSize).toList();
      return {
        'users': paged,
        'roles': uniqueRoles,
        'pagination': {
          'current_page': page,
          'last_page': max(1, (users.length / pageSize).ceil()),
          'total': users.length,
        },
      };
    }
    if (!_usingApi) throw const ApiException('Connect to load users.');
    final query = <String, String>{
      if (search.trim().isNotEmpty) 'search': search.trim(),
      if (roleId?.isNotEmpty == true) 'role_id': roleId!,
      if (status != null) 'status': '$status',
      'page': '$page',
    };
    final response = await apiClient!.get(
      Uri(path: 'admin/users', queryParameters: query).toString(),
    );
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  /// Loads one server-side page for the responsive user administration desk.
  /// Search uses the normalized `admin_search_terms` field populated by the
  /// registration functions and the deployment migration. No full-directory
  /// client scan is performed.
  Future<AdminUserPage> adminUserPage({
    AdminUserFilters filters = const AdminUserFilters(),
    AdminUserSort sort = AdminUserSort.newest,
    int page = 1,
    int pageSize = 30,
    bool refreshMetrics = true,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('User administration requires Firebase.');
    }
    final safePage = page.clamp(1, 1000000);
    final safePageSize = pageSize.clamp(10, 100);
    var query = _adminUserQuery(filters, sort);
    final cursorKey = '${filters.cacheKey}|${sort.value}|$safePageSize';
    if (safePage == 1) {
      _adminUserPageCursors.removeWhere(
        (key, _) => key.startsWith('$cursorKey|'),
      );
    } else {
      final cursor = _adminUserPageCursors['$cursorKey|${safePage - 1}'];
      if (cursor == null) {
        throw const ApiException(
          'This user page is stale. Refresh the directory and try again.',
        );
      }
      query = query.startAfterDocument(cursor);
    }

    final countFuture = _adminCount(
      _adminUserQuery(filters, sort, ordered: false),
    );
    final rolesFuture = _adminAssignableRoleRecords();
    final metricsFuture = refreshMetrics
        ? _adminUserMetrics()
        : Future.value(const AdminUserMetrics());
    final pageSnapshot = await query.limit(safePageSize + 1).get();
    final hasNext = pageSnapshot.docs.length > safePageSize;
    final visibleDocuments = pageSnapshot.docs.take(safePageSize).toList();
    if (visibleDocuments.isNotEmpty) {
      _adminUserPageCursors['$cursorKey|$safePage'] = visibleDocuments.last;
    }
    final results = await Future.wait<Object>([
      countFuture,
      rolesFuture,
      metricsFuture,
    ]);
    return AdminUserPage(
      users: visibleDocuments
          .map(
            (document) => AdminUserRecord.fromMap({
              'id': document.id,
              ...document.data(),
            }),
          )
          .toList(growable: false),
      roles: results[1] as List<Map<String, dynamic>>,
      metrics: results[2] as AdminUserMetrics,
      page: safePage,
      pageSize: safePageSize,
      total: results[0] as int,
      hasNext: hasNext,
    );
  }

  Query<Map<String, dynamic>> _adminUserQuery(
    AdminUserFilters filters,
    AdminUserSort sort, {
    bool ordered = true,
  }) {
    Query<Map<String, dynamic>> query = _firestore.collection('users');
    final search = filters.normalizedSearch;
    if (search.isNotEmpty) {
      query = query.where('admin_search_terms', arrayContains: search);
    }
    if (filters.roleId != null) {
      query = query.where('role_id', isEqualTo: filters.roleId);
    }
    if (filters.accountStatus?.isNotEmpty == true) {
      query = query.where('account_status', isEqualTo: filters.accountStatus);
    }
    if (filters.accessStatus?.isNotEmpty == true) {
      query = query.where('access_status', isEqualTo: filters.accessStatus);
    }
    if (filters.verificationStatus?.isNotEmpty == true) {
      query = query.where(
        'verification_status',
        isEqualTo: filters.verificationStatus,
      );
    }
    if (filters.mt5Status?.isNotEmpty == true) {
      query = query.where('mt5_status', isEqualTo: filters.mt5Status);
    }
    if (filters.hasPendingApplication != null) {
      query = query.where(
        'has_pending_application',
        isEqualTo: filters.hasPendingApplication,
      );
    }
    if (filters.hasUnresolvedTicket != null) {
      query = query.where(
        'has_unresolved_ticket',
        isEqualTo: filters.hasUnresolvedTicket,
      );
    }
    if (filters.assignedProviderId?.isNotEmpty == true) {
      query = query.where(
        'assigned_provider_id',
        isEqualTo: filters.assignedProviderId,
      );
    }
    if (filters.registeredAfter != null) {
      query = query.where(
        'created_at',
        isGreaterThanOrEqualTo: Timestamp.fromDate(filters.registeredAfter!),
      );
    }
    if (filters.registeredBefore != null) {
      query = query.where(
        'created_at',
        isLessThan: Timestamp.fromDate(
          filters.registeredBefore!.add(const Duration(days: 1)),
        ),
      );
    }
    if (!ordered) return query;
    return switch (sort) {
      AdminUserSort.oldest => query.orderBy('created_at'),
      AdminUserSort.recentlyActive => query.orderBy(
        'last_active_at',
        descending: true,
      ),
      AdminUserSort.name => query.orderBy('name_lower'),
      AdminUserSort.role => query.orderBy('role_id'),
      AdminUserSort.verification => query.orderBy('verification_status'),
      AdminUserSort.access => query.orderBy('access_status'),
      AdminUserSort.newest => query.orderBy('created_at', descending: true),
    };
  }

  Future<int> _adminCount(Query<Map<String, dynamic>> query) async {
    final snapshot = await query.count().get();
    return snapshot.count ?? 0;
  }

  Future<AdminUserMetrics> _adminUserMetrics() async {
    final users = _firestore.collection('users');
    final recent = Timestamp.fromDate(
      DateTime.now().subtract(const Duration(days: 30)),
    );
    final counts = await Future.wait<int>([
      _adminCount(users),
      _adminCount(users.where('account_status', isEqualTo: 'active')),
      _adminCount(
        users.where(
          'access_status',
          whereIn: const ['pending', 'pendingReview', 'submitted'],
        ),
      ),
      _adminCount(
        users.where(
          'verification_status',
          whereIn: const ['pending', 'pendingReview', 'submitted'],
        ),
      ),
      _adminCount(users.where('is_suspended', isEqualTo: true)),
      _adminCount(users.where('created_at', isGreaterThanOrEqualTo: recent)),
    ]);
    return AdminUserMetrics(
      total: counts[0],
      active: counts[1],
      pendingAccess: counts[2],
      pendingVerification: counts[3],
      suspended: counts[4],
      recentlyRegistered: counts[5],
    );
  }

  Future<List<Map<String, dynamic>>> _adminAssignableRoleRecords() async {
    final roleDocs = await _firestore.collection('registration_roles').get();
    final roles = <Map<String, dynamic>>[
      for (final entry in AppRoles.names.entries)
        {'id': entry.key, 'name': entry.value},
      for (final document in roleDocs.docs)
        {'id': document.data()['role_id'] ?? document.id, ...document.data()},
    ];
    return <String, Map<String, dynamic>>{
      for (final role in roles) '${role['id']}': role,
    }.values.toList(growable: false)..sort(
      (left, right) => _intValue(left['id']).compareTo(_intValue(right['id'])),
    );
  }

  Future<AdminUserDetail> adminUserDetail(String userId) async {
    if (!firebaseEnabled || userId.trim().isEmpty) {
      throw const ApiException('User administration requires Firebase.');
    }
    final uid = userId.trim();
    final userReference = _firestore.collection('users').doc(uid);
    final userFuture = userReference.get();
    final detailQueries = await Future.wait<List<Map<String, dynamic>>>([
      _adminDetailRows(
        _firestore
            .collection('accessRequests')
            .where('user_id', isEqualTo: uid)
            .limit(20),
      ),
      _adminDetailRows(userReference.collection('mt5_accounts').limit(20)),
      _adminDetailRows(
        userReference
            .collection('activity')
            .orderBy('created_at', descending: true)
            .limit(30),
      ),
      _adminDetailRows(
        _firestore
            .collection('support_tickets')
            .where('user_id', isEqualTo: uid)
            .limit(20),
      ),
      _adminDetailRows(
        _firestore
            .collection('trading_appointments')
            .where('participant_ids', arrayContains: uid)
            .limit(20),
      ),
      _adminDetailRows(
        _firestore
            .collection('admin_audit_logs')
            .where('targetUserId', isEqualTo: uid)
            .orderBy('created_at', descending: true)
            .limit(30),
      ),
      _adminDetailRows(
        userReference
            .collection('admin_notes')
            .orderBy('created_at', descending: true)
            .limit(30),
      ),
      _adminDetailRows(
        _firestore
            .collection('referral_relationships')
            .where('referrer_id', isEqualTo: uid)
            .limit(30),
      ),
    ]);
    final userDocument = await userFuture;
    if (!userDocument.exists) {
      throw const ApiException('The user account no longer exists.');
    }
    return AdminUserDetail(
      user: AdminUserRecord.fromMap({
        'id': userDocument.id,
        ...?userDocument.data(),
      }),
      accessApplications: detailQueries[0],
      mt5Accounts: detailQueries[1],
      activities: detailQueries[2],
      supportTickets: detailQueries[3],
      appointments: detailQueries[4],
      auditLogs: detailQueries[5],
      notes: detailQueries[6],
      referrals: detailQueries[7],
    );
  }

  Future<List<Map<String, dynamic>>> _adminDetailRows(
    Query<Map<String, dynamic>> query,
  ) async {
    try {
      final snapshot = await query.get();
      return snapshot.docs
          .map(
            (document) => <String, dynamic>{
              'id': document.id,
              ...document.data(),
            },
          )
          .toList(growable: false);
    } on FirebaseException catch (error, stackTrace) {
      if (kDebugMode) {
        debugPrint(
          '[Admin User Detail] optional query skipped: '
          'code=${error.code} message=${error.message}',
        );
        debugPrint('$stackTrace');
      }
      return const <Map<String, dynamic>>[];
    }
  }

  Future<void> adminAddUserNote({
    required String userId,
    required String note,
  }) async {
    final value = note.trim();
    if (value.isEmpty) throw const ApiException('Enter an internal note.');
    try {
      await _callableService.call('adminAddUserNote', {
        'targetUserId': userId.trim(),
        'note': value,
      });
      _adminMutationController.add(null);
    } on FirebaseCallableFailure catch (error) {
      throw ApiException(_adminCallableMessage(error));
    }
  }

  Future<void> adminUpdateUser(
    String userId, {
    required String name,
    required String roleId,
    Set<int>? roleIds,
    required int status,
    Map<String, bool> permissionOverrides = const {},
    String reason = 'operational_admin_update',
  }) async {
    if (firebaseEnabled) {
      final requestedRole = int.parse(roleId);
      await _callableService.call('adminUpdateUserAccessControl', {
        'targetUserId': userId,
        'roleIds': (roleIds?.isNotEmpty == true ? roleIds! : {requestedRole})
            .toList(),
        'permissionOverrides': permissionOverrides,
        'name': name.trim(),
        'status': status,
        'reason': reason.trim().isEmpty ? 'operational_admin_update' : reason,
      });
      _adminMutationController.add(null);
      return;
    }
    await apiClient!.patch(
      'admin/users/$userId',
      body: {'name': name, 'role_id': int.parse(roleId), 'status': status},
    );
  }

  Future<void> adminCreateUser({
    required String name,
    required String email,
    required String username,
    required String password,
    required int roleId,
    required int status,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('User administration requires Firebase.');
    }
    try {
      final callable = FirebaseFunctions.instanceFor(
        region: _functionsRegion,
      ).httpsCallable('adminCreateUser');
      await callable
          .call<Map<String, dynamic>>({
            'name': name.trim(),
            'email': email.trim().toLowerCase(),
            'username': username.trim().toLowerCase(),
            'password': password,
            'roleId': roleId,
            'status': status,
            'accessStatus': AccessStatus.notSubmitted.storageValue,
          })
          .timeout(const Duration(seconds: 60));
    } on FirebaseFunctionsException catch (error) {
      throw ApiException(_adminFunctionMessage(error));
    } on TimeoutException {
      throw const ApiException('Unable to create user. Please try again.');
    }
  }

  Stream<List<Map<String, dynamic>>> deletedUsersStream({int limit = 100}) {
    return Stream.fromFuture(adminDeletedUsers(limit: limit));
  }

  Future<List<Map<String, dynamic>>> adminDeletedUsers({
    int limit = 100,
  }) async {
    if (!firebaseEnabled) return const <Map<String, dynamic>>[];
    try {
      final result = await _callableService
          .call('adminListDeletedUsers', {'limit': limit.clamp(1, 200)})
          .timeout(const Duration(seconds: 30));
      return _jsonList(result['users']);
    } on FirebaseCallableFailure catch (error) {
      if (const {'not-found', 'unavailable'}.contains(error.code)) {
        return _adminDeletedUsersDirect(limit: limit);
      }
      throw ApiException(_adminCallableMessage(error));
    } on TimeoutException {
      throw const ApiException(
        'Unable to load deleted users. Please try again.',
      );
    }
  }

  Future<List<Map<String, dynamic>>> _adminDeletedUsersDirect({
    required int limit,
  }) async {
    try {
      final snapshot = await _firestore
          .collection('deletedUsers')
          .orderBy('deleted_at', descending: true)
          .limit(limit)
          .get();
      return snapshot.docs
          .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
          .toList(growable: false);
    } on FirebaseException catch (error) {
      if (_isPermissionDenied(error)) {
        throw const ApiException(
          'Deleted user audit access is not deployed yet. Please deploy the updated Cloud Functions and Firestore rules.',
        );
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> adminOnboarding() async {
    if (firebaseEnabled) {
      final snapshot = await _firestore
          .collectionGroup('trader_onboarding')
          .get();
      final applications = <Map<String, dynamic>>[];
      for (final doc in snapshot.docs) {
        final data = doc.data();
        final uid = '${data['user_id']}';
        final trader = await _firestore.collection('users').doc(uid).get();
        applications.add({
          'id': uid,
          ...data,
          'trader': trader.exists ? _adminUserMap(trader) : {'id': uid},
        });
      }
      return {
        'pending': applications
            .where((item) => item['status'] == 'pending')
            .toList(),
        'recent': applications
            .where((item) => item['status'] != 'pending')
            .toList(),
        'rejection_reasons': const {
          'missing_document': 'Document is missing, unclear, or incomplete',
          'deposit_not_confirmed': 'Deposit cannot be confirmed',
          'deposit_amount_mismatch': 'Deposit amount does not match the record',
          'broker_details_mismatch': 'Broker details do not match',
          'discord_not_found': 'Discord username cannot be verified',
          'information_not_aligned': 'Submitted information is not aligned',
          'final_not_proceeding': 'Application will not proceed',
        },
      };
    }
    final response = await apiClient!.get('admin/onboarding');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>?> adminOnboardingDocument(String userId) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Document preview is currently available for Firebase administration.',
      );
    }
    final snapshot = await _firestore
        .collection('users')
        .doc(userId)
        .collection('onboarding_documents')
        .doc('current')
        .get();
    final data = snapshot.data();
    if (data == null) return null;
    final blob = data['bytes'];
    if (blob is Blob) return {...data, 'bytes': blob.bytes};
    final storagePath = data['storage_path']?.toString();
    if (storagePath?.isNotEmpty == true) {
      final bytes = await FirebaseStorage.instance
          .ref(storagePath)
          .getData(10 * 1024 * 1024);
      if (bytes != null) return {...data, 'bytes': bytes};
    }
    return data;
  }

  Future<void> adminReviewOnboarding(
    String applicationId, {
    required String decision,
    String? rejectionReason,
    String? note,
  }) async {
    if (firebaseEnabled) {
      final status = switch (decision) {
        'approve' => 'approved',
        'resubmit_documents' => 'rejected_resubmittable',
        'close_new_application' => 'rejected_new_application',
        _ => 'rejected_final',
      };
      final profile = _firestore
          .collection('users')
          .doc(applicationId)
          .collection('trader_onboarding')
          .doc('profile');
      final userRef = _firestore.collection('users').doc(applicationId);
      final reviewerId = _firebaseUid();
      await _firestore.runTransaction((transaction) async {
        final snapshot = await transaction.get(profile);
        final current = snapshot.data();
        if (current == null) {
          throw const ApiException('Broker verification was not found.');
        }
        if (BrokerVerificationStatus.fromStorage(current['status']) !=
            BrokerVerificationStatus.pending) {
          throw const ApiException(
            'This broker verification has already been processed.',
          );
        }
        transaction.set(profile, {
          'status': status,
          'rejection_reason': status == 'approved' ? null : rejectionReason,
          'rejection_note': status == 'approved' ? null : note?.trim(),
          'allow_resubmission': status != 'rejected_final',
          'reviewed_by': reviewerId,
          'reviewed_at': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
        transaction.set(userRef, {
          'requires_onboarding': status != 'approved',
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
      });
      await _writeAudit(
        action: status == 'approved' ? 'approve' : 'reject',
        module: 'trader_verification',
        targetType: 'trader_onboarding',
        targetId: applicationId,
        newValues: {'status': status, 'note': note},
      );
      return;
    }
    final body = <String, Object?>{'decision': decision};
    if (rejectionReason case final reason?) {
      body['rejection_reason'] = reason;
    }
    if (note?.trim().isNotEmpty == true) {
      body['rejection_note'] = note!.trim();
    }
    await apiClient!.post('admin/onboarding/$applicationId/review', body: body);
  }

  Future<Map<String, dynamic>> adminAccessRequests() async {
    if (!firebaseEnabled) {
      throw const ApiException('Client access review requires Firebase.');
    }

    final snapshot = await _adminGet(
      'client access requests',
      _firestore.collection('accessRequests'),
    );
    final requests = <Map<String, dynamic>>[];
    for (final doc in snapshot.docs) {
      final data = doc.data();
      final uid = '${data['user_id'] ?? data['userId'] ?? doc.id}';
      final client = await _firestore.collection('users').doc(uid).get();
      final status = AccessStatus.fromStorage(
        data['status'] ?? data['access_status'] ?? data['accessStatus'],
      );
      requests.add({
        'id': doc.id,
        'user_id': uid,
        ...data,
        'status': status.storageValue,
        'client': client.exists ? _adminUserMap(client) : {'id': uid},
      });
    }
    requests.sort((left, right) {
      final leftDate = _firestoreDate(
        left['updated_at'] ?? left['updatedAt'] ?? left['created_at'],
      );
      final rightDate = _firestoreDate(
        right['updated_at'] ?? right['updatedAt'] ?? right['created_at'],
      );
      return rightDate.compareTo(leftDate);
    });
    return {
      'pending': requests
          .where(
            (item) => item['status'] == AccessStatus.pendingReview.storageValue,
          )
          .toList(),
      'recent': requests
          .where(
            (item) => item['status'] != AccessStatus.pendingReview.storageValue,
          )
          .toList(),
    };
  }

  Future<void> adminReviewAccessRequest(
    String requestId, {
    required String decision,
    String? note,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Client access review requires Firebase.');
    }
    try {
      if (kDebugMode) {
        debugPrint(
          '[Admin Access][review] requestId=$requestId decision=$decision',
        );
      }
      await _callableService
          .call('adminReviewAccessApplication', {
            'requestId': requestId,
            'decision': decision,
            'note': note?.trim() ?? '',
          })
          .timeout(const Duration(seconds: 30));
      if (kDebugMode) {
        debugPrint(
          '[Admin Access][review] requestId=$requestId result=success',
        );
      }
    } on FirebaseCallableFailure catch (error) {
      if (kDebugMode) {
        debugPrint(
          '[Admin Access][review] requestId=$requestId '
          'result=failure code=${error.code}',
        );
      }
      throw ApiException(_adminCallableMessage(error));
    } on TimeoutException {
      throw const ApiException('Unable to review access. Please try again.');
    }
  }

  Future<List<Map<String, dynamic>>> adminPositions() async {
    if (firebaseEnabled) {
      final snapshot = await _firestore
          .collectionGroup('trading_position_applications')
          .get();
      final applications = <Map<String, dynamic>>[];
      for (final doc in snapshot.docs) {
        final data = doc.data();
        final uid = '${data['user_id']}';
        final applicant = await _firestore.collection('users').doc(uid).get();
        applications.add({
          'id': '$uid|${doc.id}',
          ...data,
          'applicant': applicant.exists
              ? _adminUserMap(applicant)
              : {'id': uid},
        });
      }
      applications.sort(
        (left, right) => _firestoreDate(
          right['submitted_at'],
        ).compareTo(_firestoreDate(left['submitted_at'])),
      );
      return applications;
    }
    final response = await apiClient!.get('admin/positions');
    return _jsonList(response['data']);
  }

  Future<void> adminReviewPosition(
    String applicationId, {
    required String decision,
    String? note,
  }) async {
    if (firebaseEnabled) {
      final separator = applicationId.indexOf('|');
      if (separator < 1) {
        throw const ApiException('Invalid position application identifier.');
      }
      final uid = applicationId.substring(0, separator);
      final documentId = applicationId.substring(separator + 1);
      final reference = _firestore
          .collection('users')
          .doc(uid)
          .collection('trading_position_applications')
          .doc(documentId);
      final actorId = _firebaseUid();
      if (actorId == uid) {
        throw const ApiException(
          'Administrators cannot review their own position application.',
        );
      }
      final approved = decision == 'approve';
      Map<String, dynamic>? previous;
      await _firestore.runTransaction((transaction) async {
        final snapshot = await transaction.get(reference);
        final data = snapshot.data();
        if (data == null) {
          throw const ApiException('Position application not found.');
        }
        if ('${data['status'] ?? 'pending'}' != 'pending') {
          throw const ApiException(
            'This position application has already been processed.',
          );
        }
        previous = data;
        transaction.update(reference, {
          'status': approved ? 'approved' : 'rejected',
          'review_note': note?.trim(),
          'reviewed_by': actorId,
          'reviewed_at': FieldValue.serverTimestamp(),
        });
        if (approved) {
          transaction.set(_firestore.collection('users').doc(uid), {
            'role_id': _intValue(data['requested_role_id']),
            'role_name': _roleLabel(_intValue(data['requested_role_id'])),
            'updated_at': FieldValue.serverTimestamp(),
          }, SetOptions(merge: true));
        }
      });
      await _writeAudit(
        action: approved ? 'approve' : 'reject',
        module: 'position_verification',
        targetType: 'trading_position_application',
        targetId: applicationId,
        oldValues: previous,
        newValues: {'decision': decision, 'note': note},
      );
      return;
    }
    await apiClient!.post(
      'admin/positions/$applicationId/review',
      body: {
        'decision': decision,
        if (note?.trim().isNotEmpty == true) 'review_note': note!.trim(),
      },
    );
  }

  Future<Map<String, dynamic>> adminContent() async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Content administration is not available from this connection.',
      );
    }
    final results = await Future.wait([
      _firestore.collection('trading_recordings').get(),
      _firestore.collection('knowledge_centres').get(),
      _firestore.collection('community_documents').get(),
    ]);
    final recordings = results[0].docs
        .map((doc) => {'id': doc.id, ...doc.data()})
        .toList();
    final knowledge = results[1].docs
        .map((doc) => {'id': doc.id, ...doc.data()})
        .toList();
    final communityDocuments = results[2].docs
        .map((doc) => {'id': doc.id, ...doc.data()})
        .toList();
    recordings.sort(
      (left, right) => '${left['title']}'.compareTo('${right['title']}'),
    );
    knowledge.sort(
      (left, right) => '${left['title']}'.compareTo('${right['title']}'),
    );
    communityDocuments.sort(
      (left, right) => '${left['title']}'.compareTo('${right['title']}'),
    );
    return {
      'recordings': recordings,
      'knowledge': knowledge,
      'community_documents': communityDocuments,
    };
  }

  Future<void> adminSaveRecording({
    String? id,
    required String title,
    required String titleZh,
    required String description,
    required String descriptionZh,
    required String videoUrl,
    required String downloadUrl,
    required String sourceName,
    required bool active,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Connect to Firebase to publish recordings.');
    }
    final collection = _firestore.collection('trading_recordings');
    final reference = id?.isNotEmpty == true
        ? collection.doc(id)
        : collection.doc();
    final titleI18n = _requiredBilingualMap(
      label: 'Recording title',
      english: title,
      chinese: titleZh,
    );
    final descriptionI18n = _optionalBilingualMap(
      label: 'Recording description',
      english: description,
      chinese: descriptionZh,
    );
    await reference.set({
      'title': title.trim(),
      'description': description.trim(),
      'title_i18n': titleI18n,
      if (descriptionI18n.isNotEmpty) 'description_i18n': descriptionI18n,
      'video_url': videoUrl.trim(),
      'download_url': downloadUrl.trim(),
      'source_name': sourceName.trim(),
      'status': active,
      'approval_status': 'approved',
      'approved_at': FieldValue.serverTimestamp(),
      if (id?.isNotEmpty != true) 'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: id?.isNotEmpty == true ? 'update' : 'create',
      module: 'recording_classes',
      targetType: 'trading_recording',
      targetId: reference.id,
      newValues: {'title': title.trim(), 'status': active},
    );
  }

  Future<void> adminSaveKnowledge({
    String? id,
    required String category,
    required String title,
    required String titleZh,
    required String description,
    required String descriptionZh,
    required String fileUrl,
    required String imageUrl,
    required bool active,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Connect to Firebase to publish knowledge-centre content.',
      );
    }
    final collection = _firestore.collection('knowledge_centres');
    final reference = id?.isNotEmpty == true
        ? collection.doc(id)
        : collection.doc();
    final trimmedTitle = title.trim();
    final trimmedDescription = description.trim();
    final normalizedCategory = KnowledgeCentreCategory.normalizeSlug(category);
    if (KnowledgeCentreCategory.bySlug(normalizedCategory) == null) {
      throw const ApiException('Select a valid Knowledge Centre category.');
    }
    final titleI18n = _requiredBilingualMap(
      label: 'Knowledge title',
      english: title,
      chinese: titleZh,
    );
    final descriptionI18n = _requiredBilingualMap(
      label: 'Knowledge content',
      english: description,
      chinese: descriptionZh,
    );
    final trimmedImageUrl = imageUrl.trim();
    final englishLocale = {
      'title': trimmedTitle,
      'category': normalizedCategory,
      'shortDescription': trimmedDescription,
      'fullContent': trimmedDescription,
      'keyPoints': <String>[],
      'examples': <String>[],
      'commonMistakes': <String>[],
      'checklist': <String>[],
    };
    final chineseLocale = {
      'title': titleI18n['zh'] ?? trimmedTitle,
      'shortDescription': descriptionI18n['zh'] ?? trimmedDescription,
      'fullContent': descriptionI18n['zh'] ?? trimmedDescription,
      'keyPoints': <String>[],
      'examples': <String>[],
      'commonMistakes': <String>[],
      'checklist': <String>[],
    };
    await reference.set({
      'title': trimmedTitle,
      'description': trimmedDescription,
      'title_i18n': titleI18n,
      'description_i18n': descriptionI18n,
      'locales': {'en': englishLocale, 'zh': chineseLocale},
      if (trimmedImageUrl.isNotEmpty)
        'imageSections': [
          {
            'imageId': '${reference.id}_IMG_001',
            'type': 'diagram',
            'assetPath': '',
            'storagePath': trimmedImageUrl,
            'sortOrder': 1,
            'locales': {
              'en': {
                'title': '$trimmedTitle image',
                'caption': trimmedTitle,
                'description': trimmedDescription,
                'altText': 'Knowledge Centre image for $trimmedTitle',
              },
              'zh': {
                'title': '${titleI18n['zh'] ?? trimmedTitle}\u56fe\u7247',
                'caption': titleI18n['zh'] ?? trimmedTitle,
                'description': descriptionI18n['zh'] ?? trimmedDescription,
                'altText': titleI18n['zh'] ?? trimmedTitle,
              },
            },
          },
        ],
      'file_path': fileUrl.trim(),
      'image_url': trimmedImageUrl,
      'status': active,
      'isActive': active,
      'approval_status': 'approved',
      'approved_at': FieldValue.serverTimestamp(),
      if (id?.isNotEmpty != true) 'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: id?.isNotEmpty == true ? 'update' : 'create',
      module: 'knowledge_centre',
      targetType: 'knowledge_item',
      targetId: reference.id,
      newValues: {
        'title': trimmedTitle,
        'category': normalizedCategory,
        'status': active,
      },
    );
  }

  Future<void> adminSaveCommunityDocument({
    String? id,
    required String community,
    required String title,
    required String description,
    required String fileUrl,
    required bool active,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Connect to Firebase to publish community documents.',
      );
    }
    final collection = _firestore.collection('community_documents');
    final reference = id?.isNotEmpty == true
        ? collection.doc(id)
        : collection.doc();
    await reference.set({
      'community': community.trim(),
      'title': title.trim(),
      'description': description.trim(),
      'file_url': fileUrl.trim(),
      'status': active,
      'uploaded_by': _firebaseUid(),
      if (id?.isNotEmpty != true) 'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    await _writeAudit(
      action: id?.isNotEmpty == true ? 'update' : 'create',
      module: 'community',
      targetType: 'community_document',
      targetId: reference.id,
      newValues: {'title': title.trim(), 'community': community.trim()},
    );
  }

  Future<void> adminDeleteContent({
    required String collection,
    required String id,
  }) async {
    if (!firebaseEnabled ||
        !const {
          'trading_recordings',
          'knowledge_centres',
          'community_documents',
        }.contains(collection)) {
      throw const ApiException('This content item cannot be removed.');
    }
    final reference = _firestore.collection(collection).doc(id);
    final previous = await reference.get();
    await reference.delete();
    await _writeAudit(
      action: 'delete',
      module: collection,
      targetType: collection,
      targetId: id,
      oldValues: previous.data(),
    );
  }

  Future<List<Map<String, dynamic>>> adminTraderPerformance({
    DateTime? startAt,
    DateTime? endAt,
    String dataSource = 'all',
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException(
        'Trader performance administration requires Firebase.',
      );
    }
    try {
      return await _loadAdminTraderPerformance(
        startAt: startAt,
        endAt: endAt,
        dataSource: dataSource,
      ).timeout(const Duration(seconds: 30));
    } on TimeoutException {
      throw const ApiException(
        'Performance data took too long to load. Check your connection and try again.',
      );
    } on FirebaseException catch (error, stackTrace) {
      debugPrint(
        '[Admin Firestore] trader performance failed: '
        '${error.code} ${error.message}\n$stackTrace',
      );
      throw ApiException(
        'Unable to load trader performance. Please try again.',
        cause: error,
      );
    }
  }

  Future<List<Map<String, dynamic>>> _loadAdminTraderPerformance({
    DateTime? startAt,
    DateTime? endAt,
    required String dataSource,
  }) async {
    final usersSnapshot = await _adminGet(
      'traders',
      _firestore
          .collection('users')
          .where(
            'role_id',
            whereIn: const [750, 760, 770, '750', '760', '770'],
          ),
    );
    final traderDocs = usersSnapshot.docs
        .where(
          (doc) =>
              const {
                750,
                760,
                770,
              }.contains(_intValue(doc.data()['role_id'])) &&
              _intValue(doc.data()['status']) != 0,
        )
        .toList();
    final traderIds = traderDocs.map((document) => document.id).toSet();
    final journalQueryStart = _adminPerformanceQueryStart(
      startAt: startAt,
      endAt: endAt,
    );
    final performanceDocuments = await Future.wait([
      _adminPerformanceDocuments(
        'trading_journals',
        traderDocs,
        startAt: journalQueryStart,
        endAt: endAt,
        dataSource: dataSource,
      ),
      _adminPerformanceDocuments('capitals', traderDocs),
    ]);
    final journalsByUser =
        <String, List<QueryDocumentSnapshot<Map<String, dynamic>>>>{};
    for (final document in performanceDocuments[0]) {
      final userId = document.reference.parent.parent?.id;
      if (userId == null || !traderIds.contains(userId)) continue;
      journalsByUser.putIfAbsent(userId, () => []).add(document);
    }
    final capitalsByUser =
        <String, List<QueryDocumentSnapshot<Map<String, dynamic>>>>{};
    for (final document in performanceDocuments[1]) {
      final userId = document.reference.parent.parent?.id;
      if (userId == null || !traderIds.contains(userId)) continue;
      capitalsByUser.putIfAbsent(userId, () => []).add(document);
    }
    final rows = traderDocs.map((user) {
      final allJournals = (journalsByUser[user.id] ?? const [])
          .map(TradingJournalEntry.fromFirestore)
          .where((entry) => entry.type.trim().toLowerCase() == 'trade')
          .where(
            (entry) =>
                dataSource == 'all' || entry.source.storageValue == dataSource,
          )
          .toList(growable: false);
      final journals = allJournals
          .where(
            (entry) =>
                (startAt == null || !entry.closeDate.isBefore(startAt)) &&
                (endAt == null || !entry.closeDate.isAfter(endAt)),
          )
          .toList(growable: false);
      final capitals = (capitalsByUser[user.id] ?? const [])
          .map(CapitalTransaction.fromFirestore)
          .toList();
      final report = TradingReportService().build(
        journals: journals,
        capitals: capitals,
        traderLabel: _adminUserName(user.data()),
      );
      final summary = report.summary;
      final previousJournals = _previousPerformancePeriod(
        allJournals,
        startAt: startAt,
        endAt: endAt,
      );
      final previousReport = previousJournals == null
          ? null
          : TradingReportService().build(
              journals: previousJournals,
              capitals: capitals,
              traderLabel: _adminUserName(user.data()),
            );
      final data = user.data();
      final score =
          (summary.growthPercent * 4) +
          (summary.winRate * .4) +
          (summary.consistencyPassed ? 10 : 0) -
          (report.capital.totalDeposits > 0
              ? (summary.maxDrawdownAmount / report.capital.totalDeposits) * 100
              : 0);
      final sources =
          report.trades
              .map((trade) => trade.source.storageValue)
              .toSet()
              .toList(growable: false)
            ..sort();
      final recentTrades = [...report.trades]
        ..sort((left, right) => right.closeDate.compareTo(left.closeDate));
      return <String, dynamic>{
        'user': _adminUserMap(user),
        'total_trades': summary.totalTrades,
        'wins': summary.winningTrades,
        'losses': summary.losingTrades,
        'break_evens': summary.breakevenTrades,
        'win_rate': summary.winRate,
        'loss_rate': summary.lossRate,
        'net_profit_loss': summary.netProfitLoss,
        'gross_profit': summary.totalProfit,
        'gross_loss': summary.totalLoss,
        'average_win': summary.averageWin,
        'average_loss': summary.averageLoss,
        'profit_factor': summary.profitFactor,
        'profit_factor_label': summary.profitFactorLabel,
        'payoff_ratio': summary.payoffRatio,
        'payoff_ratio_label': summary.payoffRatioLabel,
        'deposits': report.capital.totalDeposits,
        'withdrawals': report.capital.totalWithdrawals,
        'balance': summary.currentBalance,
        'growth_percent': summary.growthPercent,
        'max_drawdown': summary.maxDrawdownAmount,
        'max_drawdown_percent': summary.maxDrawdownPercent,
        'expectancy': summary.expectancy,
        'largest_win': summary.largestWin,
        'largest_loss': summary.largestLoss,
        'best_day': summary.bestDay,
        'worst_day': summary.worstDay,
        'trading_days': summary.tradingDays,
        'longest_win_streak': summary.longestWinStreak,
        'longest_loss_streak': summary.longestLossStreak,
        'total_lots': summary.totalLots,
        'consistency_passed': summary.consistencyPassed,
        'consistency_percent': summary.consistencyPercent,
        'score': double.parse(score.toStringAsFixed(2)),
        'report_score': report.scoreProfile.score,
        'report_grade': report.scoreProfile.grade,
        'score_provisional': report.scoreProfile.isProvisional,
        'performance_status': report.performanceStatus,
        'data_sources': sources,
        'duplicate_trades_excluded': report.duplicateTradesExcluded,
        'first_trade_at': summary.firstTradeDate?.toIso8601String(),
        'last_trade_at': journals.isEmpty
            ? null
            : journals
                  .map((entry) => entry.closeDate)
                  .reduce((left, right) => left.isAfter(right) ? left : right)
                  .toIso8601String(),
        'daily_stats': [
          for (final stat in report.dailyStats)
            {
              'date': stat.date.toIso8601String(),
              'trades': stat.trades,
              'profit_loss': stat.profitLoss,
              'cumulative_profit_loss': stat.cumulativeProfitLoss,
            },
        ],
        'monthly_stats': _adminPerformanceBreakdowns(report.monthlyStats),
        'pair_stats': _adminPerformanceBreakdowns(report.pairStats),
        'session_stats': _adminPerformanceBreakdowns(report.sessionStats),
        'recent_trades': [
          for (final trade in recentTrades.take(50))
            {
              'id': trade.id,
              'pair': trade.pair,
              'direction': trade.direction,
              'open_date': trade.openDate.toIso8601String(),
              'close_date': trade.closeDate.toIso8601String(),
              'lot_size': trade.lotSize,
              'profit_loss': trade.profitLoss,
              'pips': trade.pips,
              'source': trade.source.storageValue,
              'strategy': trade.strategyName,
              'attachment_count': trade.attachments.length,
            },
        ],
        if (previousReport != null) ...{
          'previous_total_trades': previousReport.summary.totalTrades,
          'previous_net_profit_loss': previousReport.summary.netProfitLoss,
          'previous_growth_percent': previousReport.summary.growthPercent,
          'previous_win_rate': previousReport.summary.winRate,
          'previous_max_drawdown': previousReport.summary.maxDrawdownAmount,
          'previous_max_drawdown_percent':
              previousReport.summary.maxDrawdownPercent,
          'previous_report_score': previousReport.scoreProfile.score,
        },
        'prop_firm_phase': _intValue(data['prop_firm_phase']),
        'prop_firm_review_status': (data['prop_firm_review_status'] ?? 'none')
            .toString(),
        'prop_firm_trade_locked': data['prop_firm_trade_locked'] == true,
        'prop_firm_review_note': (data['prop_firm_review_note'] ?? '')
            .toString(),
        'funded_status': _intValue(data['funded_status']),
      };
    }).toList();
    rows.sort(
      (left, right) =>
          (right['score'] as double).compareTo(left['score'] as double),
    );
    for (var index = 0; index < rows.length; index++) {
      rows[index]['rank'] = index + 1;
    }
    return rows;
  }

  List<TradingJournalEntry>? _previousPerformancePeriod(
    List<TradingJournalEntry> journals, {
    required DateTime? startAt,
    required DateTime? endAt,
  }) {
    if (startAt == null || endAt == null || endAt.isBefore(startAt)) {
      return null;
    }
    final duration = endAt.difference(startAt);
    final previousEnd = startAt.subtract(const Duration(microseconds: 1));
    final previousStart = previousEnd.subtract(duration);
    return journals
        .where(
          (entry) =>
              !entry.closeDate.isBefore(previousStart) &&
              !entry.closeDate.isAfter(previousEnd),
        )
        .toList(growable: false);
  }

  DateTime? _adminPerformanceQueryStart({
    required DateTime? startAt,
    required DateTime? endAt,
  }) {
    if (startAt == null || endAt == null || endAt.isBefore(startAt)) {
      return startAt;
    }
    return startAt
        .subtract(endAt.difference(startAt))
        .subtract(const Duration(microseconds: 1));
  }

  List<Map<String, dynamic>> _adminPerformanceBreakdowns(
    Iterable<dynamic> stats,
  ) => [
    for (final dynamic stat in stats)
      {
        'label': stat.label,
        'trades': stat.trades,
        'wins': stat.wins,
        'losses': stat.losses,
        'profit_loss': stat.profitLoss,
        'win_rate': stat.winRate,
      },
  ];

  String _adminUserName(Map<String, dynamic> data) =>
      (data['name'] ?? data['username'] ?? 'Trader').toString();

  Future<List<QueryDocumentSnapshot<Map<String, dynamic>>>>
  _adminPerformanceDocuments(
    String collection,
    List<QueryDocumentSnapshot<Map<String, dynamic>>> traders, {
    DateTime? startAt,
    DateTime? endAt,
    String dataSource = 'all',
  }) async {
    try {
      if (collection != 'trading_journals' ||
          (startAt == null && endAt == null && dataSource == 'all')) {
        final snapshot = await _firestore.collectionGroup(collection).get();
        return snapshot.docs;
      }
      Future<List<QueryDocumentSnapshot<Map<String, dynamic>>>> query({
        required bool filterSource,
      }) async {
        Query<Map<String, dynamic>> base = _firestore.collectionGroup(
          collection,
        );
        if (filterSource && dataSource != 'all') {
          base = base.where('source', isEqualTo: dataSource);
        }
        final queries = <Query<Map<String, dynamic>>>[];
        Query<Map<String, dynamic>> timestamps = base;
        if (startAt != null) {
          timestamps = timestamps.where(
            'close_date',
            isGreaterThanOrEqualTo: Timestamp.fromDate(startAt),
          );
        }
        if (endAt != null) {
          timestamps = timestamps.where(
            'close_date',
            isLessThanOrEqualTo: Timestamp.fromDate(endAt),
          );
        }
        queries.add(timestamps);

        // Earlier mobile releases stored ISO-8601 strings. Firestore orders
        // each value type independently, so a second bounded query preserves
        // those partially migrated records without scanning all history.
        Query<Map<String, dynamic>> legacyStrings = base;
        if (startAt != null) {
          legacyStrings = legacyStrings.where(
            'close_date',
            isGreaterThanOrEqualTo: startAt.toIso8601String(),
          );
        }
        if (endAt != null) {
          legacyStrings = legacyStrings.where(
            'close_date',
            isLessThanOrEqualTo: endAt.toIso8601String(),
          );
        }
        queries.add(legacyStrings);
        final snapshots = await Future.wait(queries.map((item) => item.get()));
        final documents =
            <String, QueryDocumentSnapshot<Map<String, dynamic>>>{};
        for (final snapshot in snapshots) {
          for (final document in snapshot.docs) {
            documents[document.reference.path] = document;
          }
        }
        return documents.values.toList(growable: false);
      }

      try {
        return await query(filterSource: true);
      } on FirebaseException catch (error) {
        // If the optional source/date composite index is not ready, retry the
        // collection-group read with only its date bound. A missing date-only
        // collection-group index is handled by the per-trader fallback below.
        if (dataSource != 'all' && error.code == 'failed-precondition') {
          return await query(filterSource: false);
        }
        rethrow;
      }
    } on FirebaseException catch (error) {
      // A filtered collection-group query needs collection-group indexes in
      // addition to Firestore's automatic collection-scoped indexes. Keep the
      // dashboard operational while those indexes (or recursive read rules)
      // are being deployed by falling back to the already-authorized nested
      // trader collections.
      if (!_isPermissionDenied(error) && error.code != 'failed-precondition') {
        rethrow;
      }
      final snapshots = await Future.wait(
        traders.map(
          (trader) => _adminPerformanceDocumentsForTrader(
            trader,
            collection,
            startAt: startAt,
            endAt: endAt,
            dataSource: dataSource,
          ),
        ),
      );
      return [for (final documents in snapshots) ...documents];
    }
  }

  Future<List<QueryDocumentSnapshot<Map<String, dynamic>>>>
  _adminPerformanceDocumentsForTrader(
    QueryDocumentSnapshot<Map<String, dynamic>> trader,
    String collection, {
    DateTime? startAt,
    DateTime? endAt,
    required String dataSource,
  }) async {
    final reference = trader.reference.collection(collection);
    if (collection != 'trading_journals' ||
        (startAt == null && endAt == null && dataSource == 'all')) {
      return (await reference.get()).docs;
    }

    Future<List<QueryDocumentSnapshot<Map<String, dynamic>>>> query({
      required bool filterSource,
    }) async {
      Query<Map<String, dynamic>> base = reference;
      if (filterSource && dataSource != 'all') {
        base = base.where('source', isEqualTo: dataSource);
      }
      final queries = <Query<Map<String, dynamic>>>[];
      Query<Map<String, dynamic>> timestamps = base;
      if (startAt != null) {
        timestamps = timestamps.where(
          'close_date',
          isGreaterThanOrEqualTo: Timestamp.fromDate(startAt),
        );
      }
      if (endAt != null) {
        timestamps = timestamps.where(
          'close_date',
          isLessThanOrEqualTo: Timestamp.fromDate(endAt),
        );
      }
      queries.add(timestamps);

      Query<Map<String, dynamic>> legacyStrings = base;
      if (startAt != null) {
        legacyStrings = legacyStrings.where(
          'close_date',
          isGreaterThanOrEqualTo: startAt.toIso8601String(),
        );
      }
      if (endAt != null) {
        legacyStrings = legacyStrings.where(
          'close_date',
          isLessThanOrEqualTo: endAt.toIso8601String(),
        );
      }
      queries.add(legacyStrings);

      final snapshots = await Future.wait(queries.map((item) => item.get()));
      final documents = <String, QueryDocumentSnapshot<Map<String, dynamic>>>{};
      for (final snapshot in snapshots) {
        for (final document in snapshot.docs) {
          documents[document.reference.path] = document;
        }
      }
      return documents.values.toList(growable: false);
    }

    try {
      return await query(filterSource: true);
    } on FirebaseException catch (error) {
      // A source/date composite index is optional on this compatibility path;
      // date-only reads remain bounded and source filtering is applied later.
      if (dataSource != 'all' && error.code == 'failed-precondition') {
        return query(filterSource: false);
      }
      rethrow;
    }
  }

  Future<void> adminReviewPropFirm(
    String userId, {
    required String decision,
    String? note,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Prop-firm reviews require Firebase.');
    }
    final reference = _firestore.collection('users').doc(userId);
    final snapshot = await reference.get();
    final current = snapshot.data();
    if (current == null) throw const ApiException('Trader not found.');
    if (const {'reject', 'correction_required'}.contains(decision) &&
        (note == null || note.trim().isEmpty)) {
      throw const ApiException(
        'A review note is required for rejection or correction.',
      );
    }
    final currentStatus = '${current['prop_firm_review_status'] ?? 'none'}';
    final update = <String, Object?>{
      'prop_firm_review_note': note?.trim(),
      'prop_firm_reviewed_by': _firebaseUid(),
      'updated_at': FieldValue.serverTimestamp(),
    };
    switch (decision) {
      case 'approve_phase2':
        update.addAll({
          'prop_firm_review_status': 'approved_phase2',
          'prop_firm_phase': 2,
          'prop_firm_trade_locked': false,
          'prop_firm_review_approved_at': FieldValue.serverTimestamp(),
        });
      case 'approve_funded':
        update.addAll({
          'prop_firm_review_status': 'funded_approved',
          'prop_firm_phase': 3,
          'funded_status': 1,
          'prop_firm_unlocked': true,
          'prop_firm_trade_locked': false,
          'prop_firm_review_approved_at': FieldValue.serverTimestamp(),
        });
      case 'allow_risk':
        update.addAll({
          'prop_firm_review_status': currentStatus.startsWith('total_loss')
              ? 'total_loss_allowed'
              : 'daily_loss_allowed',
          'prop_firm_trade_locked': false,
          'prop_firm_review_approved_at': FieldValue.serverTimestamp(),
        });
      case 'suspend':
        update.addAll({
          'prop_firm_review_status': 'suspended',
          'prop_firm_trade_locked': true,
        });
      case 'reject':
        update.addAll({
          'prop_firm_review_status': 'rejected',
          'prop_firm_trade_locked': true,
        });
      case 'correction_required':
        update.addAll({
          'prop_firm_review_status': 'correction_required',
          'prop_firm_trade_locked': true,
          'prop_firm_correction_requested_at': FieldValue.serverTimestamp(),
        });
      default:
        throw const ApiException('Choose a valid prop-firm decision.');
    }
    await reference.set(update, SetOptions(merge: true));
    await _writeAudit(
      action: decision == 'correction_required'
          ? 'review'
          : decision.startsWith('approve') || decision == 'allow_risk'
          ? 'approve'
          : 'reject',
      module: 'prop_firm_results',
      targetType: 'user_prop_firm_review',
      targetId: userId,
      oldValues: current,
      newValues: {'decision': decision, 'note': note},
    );
  }

  Future<List<Map<String, dynamic>>> adminCompetitions() async {
    if (!firebaseEnabled) {
      throw const ApiException('Competition administration requires Firebase.');
    }
    final snapshot = await _firestore.collection('trading_competitions').get();
    final competitions = snapshot.docs
        .map((doc) => <String, dynamic>{'id': doc.id, ...doc.data()})
        .toList();
    competitions.sort(
      (left, right) => _firestoreDate(
        right['start_at'],
      ).compareTo(_firestoreDate(left['start_at'])),
    );
    return competitions;
  }

  Future<void> adminSaveCompetition({
    String? id,
    required String title,
    required String description,
    required DateTime startAt,
    required DateTime endAt,
    required String prize,
    required int minimumTrades,
    required String status,
  }) async {
    if (!firebaseEnabled) {
      throw const ApiException('Competition administration requires Firebase.');
    }
    if (!endAt.isAfter(startAt)) {
      throw const ApiException('Competition end date must be after its start.');
    }
    final collection = _firestore.collection('trading_competitions');
    final reference = id?.isNotEmpty == true
        ? collection.doc(id)
        : collection.doc();
    await reference.set({
      'title': title.trim(),
      'description': description.trim(),
      'start_at': Timestamp.fromDate(startAt),
      'end_at': Timestamp.fromDate(endAt),
      'prize': prize.trim(),
      'minimum_trades': minimumTrades,
      'status': status,
      'created_by': _firebaseUid(),
      if (id?.isNotEmpty != true) 'created_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
  }

  Future<void> adminDeleteCompetition(String id) async {
    if (!firebaseEnabled) {
      throw const ApiException('Competition administration requires Firebase.');
    }
    await _firestore.collection('trading_competitions').doc(id).delete();
  }

  Future<void> seedTradingPairs() async {
    if (firebaseEnabled) {
      final batch = _firestore.batch();
      final collection = _firestore.collection('trading_pairs');
      for (final pair in TradingPair.defaults) {
        batch.set(
          collection.doc(pair.id),
          pair.toFirestore(),
          SetOptions(merge: true),
        );
      }
      await batch.commit();
      return;
    }

    _localPairs = TradingPair.defaults;
    _emitPairs();
  }

  Future<void> seedTradingContent() async {
    if (firebaseEnabled) {
      final batch = _firestore.batch();

      for (final blog in _demoBlogs) {
        batch.set(
          _firestore.collection('trading_blogs').doc(blog.id),
          blog.toFirestore(),
          SetOptions(merge: true),
        );
      }

      for (final analysis in _demoAnalyses) {
        batch.set(
          _firestore.collection('market_analyses').doc(analysis.id),
          analysis.toFirestore(),
          SetOptions(merge: true),
        );
      }

      for (final signal in _demoSignals) {
        batch.set(
          _firestore.collection('trading_signals').doc(signal.id),
          signal.toFirestore(),
          SetOptions(merge: true),
        );
      }

      for (final resource in _demoResources) {
        batch.set(
          _firestore.collection('trading_resources').doc(resource.id),
          {
            'type': resource.type,
            'title': resource.title,
            'description': resource.description,
            'url': resource.url,
            'status': resource.status,
            'created_at': FieldValue.serverTimestamp(),
          },
          SetOptions(merge: true),
        );
      }

      for (final question in _demoExamQuestions) {
        batch.set(
          _firestore.collection('trading_exam_questions').doc(question.id),
          question.toFirestore(),
          SetOptions(merge: true),
        );
      }

      for (final recording in _demoRecordings) {
        batch.set(
          _firestore.collection('trading_recordings').doc(recording.id),
          recording.toFirestore(),
          SetOptions(merge: true),
        );
      }

      for (final knowledge in _demoKnowledge) {
        batch.set(
          _firestore.collection('knowledge_centres').doc(knowledge.id),
          knowledge.toFirestore(),
          SetOptions(merge: true),
        );
      }

      await batch.commit();
      return;
    }

    _localBlogs = _demoBlogs;
    _localAnalyses = _demoAnalyses;
    _localSignals = _demoSignals;
    _localResources = _demoResources;
    _localExamQuestions = _demoExamQuestions;
    _localRecordings = _demoRecordings;
    _localKnowledge = _demoKnowledge;
    _emitBlogs();
    _emitAnalyses();
    _emitSignals();
    _emitResources();
    _emitExamQuestions();
    _emitRecordings();
    _emitKnowledge();
  }

  void _restoreSignalApiCache() {
    final raw = storage?.readString('trading_signals_api_cache');
    if (raw == null || raw.trim().isEmpty) return;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Iterable) return;
      _localSignals = decoded
          .whereType<Map>()
          .where(_cacheableSignalRow)
          .map(
            (item) =>
                TradingSignalItem.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList();
    } on Object {
      _localSignals = <TradingSignalItem>[];
    }
  }

  void _restoreBlogApiCache() {
    final raw = storage?.readString('trading_blogs_api_cache');
    if (raw == null || raw.trim().isEmpty) return;
    try {
      final decoded = jsonDecode(raw);
      final cachedRows = decoded is Map ? decoded['blogs'] : decoded;
      if (cachedRows is! Iterable) return;
      if (decoded is Map) {
        _blogCategoryLabels = _stringMap(decoded['categories']);
        _blogCategoryCounts = _nonNegativeIntMap(decoded['category_counts']);
        _blogLastSyncedAt = DateTime.tryParse(
          (decoded['synced_at'] ?? '').toString(),
        );
      }
      _localBlogs = cachedRows
          .whereType<Map>()
          .map(
            (item) => TradingBlogPost.fromJson(
              _normalizeBlogApiRow(Map<String, dynamic>.from(item)),
            ),
          )
          .where((blog) => blog.status == 'published')
          .toList();
      _blogApiHasLoaded = true;
      _blogUsingCachedFallback = true;
      _ensureBlogCategoryMetadata();
    } on Object {
      _localBlogs = <TradingBlogPost>[];
    }
  }

  void _startBlogApiSync() {
    if (_blogApi == null) return;
    if (_blogApiSubscription != null) {
      if (!_blogApiHasLoaded) {
        scheduleMicrotask(_refreshBlogsFromApi);
      }
      return;
    }
    scheduleMicrotask(_refreshBlogsFromApi);
    _blogApiSubscription = _blogApi.watchChanges().listen(
      (event) {
        if (event['event'] != 'ready') {
          unawaited(_refreshBlogsFromApi(force: true));
        }
      },
      onError: (Object error, StackTrace stackTrace) {
        if (!_blogsController.isClosed) {
          _blogsController.addError(error, stackTrace);
        }
      },
    );
  }

  Future<void> _refreshBlogsFromApi({bool force = false}) {
    if (_blogApi == null) return Future<void>.value();
    if (_blogApiRefresh != null) {
      return force
          ? _blogApiRefresh!.then((_) => _refreshBlogsFromApi())
          : _blogApiRefresh!;
    }
    final completer = Completer<void>();
    _blogApiRefresh = completer.future;
    () async {
      try {
        final snapshot = await _blogApi.fetchSnapshot();
        _blogCategoryLabels = Map<String, String>.from(snapshot.categories);
        _blogCategoryCounts = Map<String, int>.from(snapshot.categoryCounts);
        final rows = snapshot.blogs
            .map(_normalizeBlogApiRow)
            .toList(growable: false);
        _localBlogs = _mobileBlogsFromApiRows(rows);
        _blogApiHasLoaded = true;
        _blogUsingCachedFallback = false;
        _blogSyncErrorMessage = null;
        _blogLastSyncedAt = (snapshot.syncedAt ?? DateTime.now()).toUtc();
        _ensureBlogCategoryMetadata();
        await storage?.writeString(
          'trading_blogs_api_cache',
          jsonEncode(<String, Object?>{
            'schema_version': 3,
            'synced_at': _blogLastSyncedAt!.toIso8601String(),
            'categories': _blogCategoryLabels,
            'category_counts': _blogCategoryCounts,
            'blogs': rows
                .where(
                  (row) =>
                      row['status']?.toString().toLowerCase() == 'published',
                )
                .toList(growable: false),
          }),
        );
        _emitBlogs();
        completer.complete();
      } catch (error, stackTrace) {
        _blogApiHasLoaded = true;
        _blogUsingCachedFallback = _localBlogs.isNotEmpty;
        _blogSyncErrorMessage = _blogApiErrorMessage(error);
        _ensureBlogCategoryMetadata();
        _emitBlogs();
        if (kDebugMode) {
          debugPrint('Trading blog API refresh failed; using fallback: $error');
          debugPrintStack(stackTrace: stackTrace);
        }
        completer.complete();
      } finally {
        _blogApiRefresh = null;
      }
    }();
    return completer.future;
  }

  List<TradingBlogPost> _mobileBlogsFromApiRows(
    List<Map<String, dynamic>> rows,
  ) {
    final blogs = <TradingBlogPost>[];
    for (final row in rows) {
      final blog = TradingBlogPost.fromJson(row);
      if (blog.status == 'published') blogs.add(blog);
    }
    return blogs;
  }

  String _blogApiErrorMessage(Object error) {
    if (error is FormatException) {
      return 'Trading blog data could not be read. Please try again.';
    }
    if (error is ApiException) {
      return switch (error.statusCode) {
        401 =>
          'The trading blog feed still requires login (HTTP 401). Please contact support.',
        403 =>
          'This account cannot access trading blogs (HTTP 403). Please contact support.',
        404 =>
          'The trading blog API is unavailable (HTTP 404). Please contact support.',
        429 =>
          'Too many trading blog requests (HTTP 429). Please retry shortly.',
        final status? when status >= 500 =>
          'The trading blog server failed (HTTP $status). Please try again later.',
        _ => error.message,
      };
    }
    return 'Unable to load trading blogs. Please try again.';
  }

  Map<String, dynamic> _normalizeBlogApiRow(Map<String, dynamic> source) {
    final row = Map<String, dynamic>.from(source);
    for (final key in const [
      'cover_image',
      'cover_image_url',
      'featured_image',
      'image',
    ]) {
      final value = row[key]?.toString().trim() ?? '';
      if (value.isNotEmpty) {
        row[key] = resolveUrl(value);
        break;
      }
    }
    final category = (row['category'] ?? row['category_slug'])
        ?.toString()
        .trim();
    if ((row['category_label']?.toString().trim().isEmpty ?? true) &&
        category?.isNotEmpty == true) {
      row['category_label'] = _blogCategoryLabels[category];
    }
    return row;
  }

  void _ensureBlogCategoryMetadata() {
    for (final blog in _localBlogs) {
      if (blog.category.trim().isEmpty) continue;
      _blogCategoryLabels.putIfAbsent(blog.category, () => blog.categoryLabel);
      _blogCategoryCounts.putIfAbsent(blog.category, () => 0);
    }
    final derivedCounts = <String, int>{};
    for (final blog in _localBlogs) {
      derivedCounts.update(
        blog.category,
        (value) => value + 1,
        ifAbsent: () => 1,
      );
    }
    if (_blogCategoryCounts.isEmpty) {
      _blogCategoryCounts = {
        for (final category in _blogCategoryLabels.keys) category: 0,
        ...derivedCounts,
      };
    } else {
      for (final category in _blogCategoryLabels.keys) {
        _blogCategoryCounts.putIfAbsent(category, () => 0);
      }
    }
  }

  Map<String, String> _stringMap(Object? value) {
    if (value is! Map) return <String, String>{};
    return value.map((key, item) => MapEntry(key.toString(), item.toString()));
  }

  Map<String, int> _nonNegativeIntMap(Object? value) {
    if (value is! Map) return <String, int>{};
    final result = <String, int>{};
    for (final entry in value.entries) {
      final parsed = entry.value is num
          ? (entry.value as num).toInt()
          : int.tryParse(entry.value.toString());
      result[entry.key.toString()] = parsed != null && parsed >= 0 ? parsed : 0;
    }
    return result;
  }

  String _newBlogIdempotencyKey(String publisherId) {
    final random = Random.secure();
    final entropy = List<int>.generate(12, (_) => random.nextInt(256));
    final suffix = base64UrlEncode(entropy).replaceAll('=', '');
    return 'blog-${publisherId.trim()}-'
        '${DateTime.now().microsecondsSinceEpoch}-$suffix';
  }

  void _startSignalApiSync() {
    if (_signalApiSubscription != null || _signalApi == null) return;
    scheduleMicrotask(_refreshSignalsFromApi);
    _signalApiSubscription = _signalApi.watchChanges().listen(
      (event) {
        if (event['event'] != 'ready') {
          unawaited(_refreshSignalsFromApi(force: true));
        }
      },
      onError: (Object error, StackTrace stackTrace) {
        if (!_signalsController.isClosed) {
          _signalsController.addError(error, stackTrace);
        }
      },
    );
  }

  Future<void> _refreshSignalsFromApi({bool force = false}) {
    if (_signalApi == null) return Future<void>.value();
    if (_signalApiRefresh != null) return _signalApiRefresh!;
    final completer = Completer<void>();
    _signalApiRefresh = completer.future;
    () async {
      try {
        final rows = await _signalApi.fetchSignals();
        _localSignals = rows.map(TradingSignalItem.fromJson).toList();
        await storage?.writeString(
          'trading_signals_api_cache',
          jsonEncode(rows.where(_cacheableSignalRow).toList(growable: false)),
        );
        _emitSignals();
        completer.complete();
      } catch (error, stackTrace) {
        if (!_signalsController.isClosed) {
          _signalsController.addError(error, stackTrace);
        }
        completer.completeError(error, stackTrace);
      } finally {
        _signalApiRefresh = null;
      }
    }();
    return completer.future;
  }

  String _newSignalIdempotencyKey(String publisherId) {
    final random = Random.secure();
    final entropy = List<int>.generate(12, (_) => random.nextInt(256));
    final suffix = base64UrlEncode(entropy).replaceAll('=', '');
    return '${publisherId.trim()}-${DateTime.now().microsecondsSinceEpoch}-$suffix';
  }

  bool _cacheableSignalRow(Map<dynamic, dynamic> row) {
    return const {
      'PUBLISHED',
      'CLOSED',
      'CANCELLED',
      'EXPIRED',
    }.contains(row['workflow_status']?.toString().toUpperCase());
  }

  String _marketForSignal(TradingSignalItem signal) {
    final pair = signal.tradingPair.toUpperCase();
    if (pair.contains('BTC') || pair.contains('ETH') || pair.contains('USDT')) {
      return 'CRYPTO';
    }
    if (pair.contains('XAU') || pair.contains('XAG')) return 'METALS';
    if (pair.contains('US30') || pair.contains('NAS') || pair.contains('SPX')) {
      return 'INDEX';
    }
    return 'FOREX';
  }

  Future<void> _refreshContent() async {
    if (!_usingApi || _contentRefresh != null) return _contentRefresh;
    final completer = Completer<void>();
    _contentRefresh = completer.future;
    try {
      final response = await apiClient!.get('content');
      final data = Map<String, dynamic>.from(response['data'] as Map);
      _localBlogs = _jsonList(
        data['blogs'],
      ).map(TradingBlogPost.fromJson).toList();
      final featuredBlog = data['featured_blog'];
      if (featuredBlog is Map) {
        final item = TradingBlogPost.fromJson(
          Map<String, dynamic>.from(featuredBlog),
        );
        if (_localBlogs.every((blog) => blog.id != item.id)) {
          _localBlogs.insert(0, item);
        }
      }
      _localAnalyses = _jsonList(
        data['market_analyses'],
      ).map(MarketAnalysisItem.fromJson).toList();
      final featuredAnalysis = data['featured_market_analysis'];
      if (featuredAnalysis is Map) {
        final item = MarketAnalysisItem.fromJson(
          Map<String, dynamic>.from(featuredAnalysis),
        );
        if (_localAnalyses.every((analysis) => analysis.id != item.id)) {
          _localAnalyses.insert(0, item);
        }
      }
      _localSignals = _jsonList(
        data['signals'],
      ).map(TradingSignalItem.fromJson).toList();
      _localResources = _jsonList(data['marketing_resources'])
          .map(
            (item) => TradingResourceItem.fromJson({
              ...item,
              'url': 'protected://marketing/${item['id']}',
            }, fallbackType: 'marketing'),
          )
          .toList();
      _localRecordings = _jsonList(
        data['recordings'],
      ).map(TradingRecordingClassMobile.fromJson).toList();
      _localKnowledge = _jsonList(
        data['knowledge'],
      ).map(KnowledgeCentreItemMobile.fromJson).toList();
      _emitBlogs();
      _emitAnalyses();
      _emitSignals();
      _emitResources();
      _emitRecordings();
      _emitKnowledge();
      completer.complete();
    } catch (error, stackTrace) {
      for (final controller in [
        _blogsController,
        _analysesController,
        _signalsController,
        _resourcesController,
        _recordingsController,
        _knowledgeController,
      ]) {
        controller.addError(error, stackTrace);
      }
      completer.completeError(error, stackTrace);
    } finally {
      _contentRefresh = null;
    }
  }

  Future<void> _refreshExam({String? uid, bool force = false}) async {
    if (!_usingApi || (_examRefresh != null && !force)) return _examRefresh;
    final completer = Completer<void>();
    _examRefresh = completer.future;
    try {
      final response = await apiClient!.get('exam');
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final attempt = data['attempt'];
      _currentExamAttemptId = attempt is Map ? attempt['id']?.toString() : null;
      _localExamQuestions = _jsonList(
        data['questions'],
      ).map(TradingExamQuestionMobile.fromJson).toList();
      final attempts = _jsonList(
        data['recent_attempts'],
      ).map(TradingExamAttemptMobile.fromJson).toList();
      for (final userId in {..._examAttemptControllers.keys, ?uid}) {
        _localExamAttempts[userId] = attempts;
        _emitExamAttempts(userId);
      }
      _emitExamQuestions();
      completer.complete();
    } catch (error, stackTrace) {
      _examQuestionsController.addError(error, stackTrace);
      for (final controller in _examAttemptControllers.values) {
        controller.addError(error, stackTrace);
      }
      completer.completeError(error, stackTrace);
    } finally {
      _examRefresh = null;
    }
  }

  Future<void> _refreshTradingData({bool force = false}) async {
    if (!_usingApi) return;
    final fresh =
        _lastTradingRefresh != null &&
        DateTime.now().difference(_lastTradingRefresh!) <
            const Duration(seconds: 3);
    if (!force && fresh) {
      _emitPairs();
      for (final uid in _journalControllers.keys) {
        _emitJournals(uid);
        _emitCapitals(uid);
        _emitTradingAccounts(uid);
      }
      return;
    }
    if (_tradingRefresh != null) return _tradingRefresh!;

    final completer = Completer<void>();
    _tradingRefresh = completer.future;
    try {
      for (final uid in {
        ..._journalControllers.keys,
        ..._capitalControllers.keys,
      }) {
        await _flushPendingJournals(uid);
      }
      final response = await apiClient!.get('trading-data');
      final data = Map<String, dynamic>.from(response['data'] as Map);
      _localPairs = _jsonList(
        data['trading_pairs'],
      ).map(TradingPair.fromJson).toList(growable: false);
      final journals = _jsonList(data['journals'])
          .map(TradingJournalEntry.fromJson)
          .where((entry) => entry.type == 'trade')
          .toList(growable: false);
      final capitals = _jsonList(
        data['capitals'],
      ).map(CapitalTransaction.fromJson).toList(growable: false);
      final tradingAccounts = _jsonList(
        data['trading_accounts'],
      ).map(TradingAccount.fromJson).toList(growable: false);
      for (final uid in {
        ..._journalControllers.keys,
        ..._capitalControllers.keys,
        ..._tradingAccountControllers.keys,
      }) {
        _localJournals[uid] = journals;
        _localCapitals[uid] = capitals;
        _localTradingAccounts[uid] = tradingAccounts;
        await _persistTradingCache(uid);
      }
      await storage?.writeList(
        '_app',
        'reference_pairs',
        _localPairs.map((pair) => pair.toJson()).toList(),
      );
      _lastTradingRefresh = DateTime.now();
      _emitPairs();
      for (final uid in {
        ..._journalControllers.keys,
        ..._capitalControllers.keys,
        ..._tradingAccountControllers.keys,
      }) {
        _emitJournals(uid);
        _emitCapitals(uid);
        _emitTradingAccounts(uid);
      }
      completer.complete();
    } catch (error, stackTrace) {
      final hasCachedData =
          _localPairs.isNotEmpty ||
          _localJournals.values.any((items) => items.isNotEmpty) ||
          _localCapitals.values.any((items) => items.isNotEmpty) ||
          _localTradingAccounts.values.any((items) => items.isNotEmpty);
      if (hasCachedData) {
        _emitPairs();
        for (final uid in {
          ..._journalControllers.keys,
          ..._capitalControllers.keys,
          ..._tradingAccountControllers.keys,
        }) {
          _emitJournals(uid);
          _emitCapitals(uid);
          _emitTradingAccounts(uid);
        }
        completer.complete();
      } else {
        _pairsController.addError(error, stackTrace);
        for (final controller in _journalControllers.values) {
          controller.addError(error, stackTrace);
        }
        for (final controller in _capitalControllers.values) {
          controller.addError(error, stackTrace);
        }
        for (final controller in _tradingAccountControllers.values) {
          controller.addError(error, stackTrace);
        }
        completer.completeError(error, stackTrace);
      }
    } finally {
      _tradingRefresh = null;
    }
  }

  void _restoreReferenceCache() {
    final cached = storage?.readList('_app', 'reference_pairs') ?? const [];
    if (cached.isNotEmpty) {
      _localPairs = cached.map(TradingPair.fromJson).toList(growable: false);
      scheduleMicrotask(_emitPairs);
    }
  }

  void _restoreTradingCache(String uid) {
    if (!_restoredTradingUsers.add(uid)) return;
    final journalCache = storage?.readList(uid, 'cached_journals') ?? const [];
    final capitalCache = storage?.readList(uid, 'cached_capitals') ?? const [];
    final accountCache =
        storage?.readList(uid, 'cached_trading_accounts') ?? const [];
    if (journalCache.isNotEmpty) {
      _localJournals[uid] = journalCache
          .map(TradingJournalEntry.fromJson)
          .toList(growable: false);
    }
    if (capitalCache.isNotEmpty) {
      _localCapitals[uid] = capitalCache
          .map(CapitalTransaction.fromJson)
          .toList(growable: false);
    }
    if (accountCache.isNotEmpty) {
      _localTradingAccounts[uid] = accountCache
          .map(TradingAccount.fromJson)
          .toList(growable: false);
    }
    scheduleMicrotask(() {
      _emitJournals(uid);
      _emitCapitals(uid);
      _emitTradingAccounts(uid);
    });
  }

  Future<void> _persistTradingCache(String uid) async {
    final localStorage = storage;
    if (localStorage == null) return;
    await localStorage.writeList(
      uid,
      'cached_journals',
      (_localJournals[uid] ?? const [])
          .map((journal) => journal.toJson())
          .toList(),
    );
    await localStorage.writeList(
      uid,
      'cached_trading_accounts',
      (_localTradingAccounts[uid] ?? const [])
          .map((account) => account.toJson())
          .toList(),
    );
    await localStorage.writeList(
      uid,
      'cached_capitals',
      (_localCapitals[uid] ?? const [])
          .map((capital) => capital.toJson())
          .toList(),
    );
  }

  List<TradingStrategyRecord> _localTradingStrategyRecords(String uid) {
    final records =
        storage
            ?.readList(uid, 'trading_strategies')
            .map(TradingStrategyRecord.fromJson)
            .where((record) => record.strategy.name.trim().isNotEmpty)
            .map((record) => _strategyRecordForUser(uid, record))
            .toList() ??
        <TradingStrategyRecord>[];
    records.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    return records;
  }

  Future<void> _writeLocalTradingStrategies(
    String uid,
    List<TradingStrategyRecord> records,
  ) async {
    await storage?.writeList(uid, 'trading_strategies', [
      for (final record in records)
        _strategyRecordForUser(uid, record).toJson(),
    ]);
  }

  List<TradingPlanRecord> _localTradingPlanRecords(String uid) {
    final records =
        storage
            ?.readList(uid, 'trading_plans')
            .map(TradingPlanRecord.fromJson)
            .where(_hasTradingPlanContent)
            .map((record) => _planRecordForUser(uid, record))
            .toList() ??
        <TradingPlanRecord>[];
    if (records.isEmpty) {
      final legacy = storage?.readMap(uid, 'trading_plan');
      if (legacy != null) {
        final now = DateTime.now();
        final legacyRecord = TradingPlanRecord(
          id: 'legacy-trading-plan',
          userId: uid,
          plan: TradingPlan.fromJson(legacy),
          createdAt: now,
          updatedAt: now,
        );
        if (_hasTradingPlanContent(legacyRecord)) {
          records.add(legacyRecord);
        }
      }
    }
    records.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    return records;
  }

  Future<void> _writeLocalTradingPlans(
    String uid,
    List<TradingPlanRecord> records,
  ) async {
    await storage?.writeList(uid, 'trading_plans', [
      for (final record in records) _planRecordForUser(uid, record).toJson(),
    ]);
  }

  TradingStrategyRecord _strategyRecordForUser(
    String uid,
    TradingStrategyRecord record,
  ) {
    return TradingStrategyRecord(
      id: record.id,
      userId: uid,
      strategy: record.strategy,
      createdAt: record.createdAt,
      updatedAt: record.updatedAt,
    );
  }

  TradingPlanRecord _planRecordForUser(String uid, TradingPlanRecord record) {
    return TradingPlanRecord(
      id: record.id,
      userId: uid,
      plan: record.plan,
      createdAt: record.createdAt,
      updatedAt: record.updatedAt,
      versionId: record.versionId.isEmpty
          ? '${record.id}-${record.updatedAt.microsecondsSinceEpoch}'
          : record.versionId,
      effectiveFrom: record.effectiveFrom ?? record.createdAt,
      effectiveTo: record.effectiveTo,
    );
  }

  bool _hasTradingPlanContent(TradingPlanRecord record) =>
      record.plan.planName.trim().isNotEmpty ||
      record.plan.description.trim().isNotEmpty ||
      record.plan.market.trim().isNotEmpty ||
      record.plan.entryCriteria.trim().isNotEmpty ||
      record.plan.exitRules.trim().isNotEmpty ||
      record.plan.generatedProfileSummary.trim().isNotEmpty ||
      record.plan.technicalRules.trim().isNotEmpty ||
      record.plan.noTradeConditions.trim().isNotEmpty ||
      record.plan.allowedPairs.isNotEmpty ||
      record.plan.allowedStrategyIds.isNotEmpty;

  Future<void> _enqueueJournal(
    String uid,
    String operationId,
    Map<String, Object?> payload,
  ) async {
    final localStorage = storage;
    if (localStorage == null) return;
    final queue = localStorage.readList(uid, 'pending_journal_sync');
    queue.add({
      'id': operationId,
      'created_at': DateTime.now().toIso8601String(),
      'payload': payload,
    });
    await localStorage.writeList(uid, 'pending_journal_sync', queue);
  }

  Future<void> _removeQueuedJournal(String uid, String operationId) async {
    final localStorage = storage;
    if (localStorage == null) return;
    final queue = localStorage.readList(uid, 'pending_journal_sync')
      ..removeWhere((item) => item['id'] == operationId);
    await localStorage.writeList(uid, 'pending_journal_sync', queue);
  }

  Future<void> _flushPendingJournals(String uid) async {
    final localStorage = storage;
    if (localStorage == null) return;
    final queue = localStorage.readList(uid, 'pending_journal_sync');
    if (queue.isEmpty) return;
    final remaining = <Map<String, Object?>>[];
    for (var index = 0; index < queue.length; index++) {
      final item = queue[index];
      final rawPayload = item['payload'];
      if (rawPayload is! Map) continue;
      try {
        await apiClient!.post(
          'journals',
          body: Map<String, Object?>.from(rawPayload),
        );
      } on ApiException catch (error) {
        if (error.statusCode == null) {
          remaining.addAll(queue.skip(index));
          break;
        }
        // Server-side validation/authorization errors are not retried forever.
      }
    }
    await localStorage.writeList(uid, 'pending_journal_sync', remaining);
  }

  Future<void> _refreshAccount(String uid) async {
    try {
      final response = await apiClient!.get('profile');
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final onboarding = Map<String, dynamic>.from(data['onboarding'] as Map);
      final latest = onboarding['latest'];
      _localAccounts[uid] = latest is Map
          ? TraderAccountProfile.fromJson(Map<String, dynamic>.from(latest))
          : TraderAccountProfile.empty(uid);
      _emitAccount(uid);
    } catch (error, stackTrace) {
      _accountControllers[uid]?.addError(error, stackTrace);
    }
  }

  Future<void> _refreshTradingAccounts(String uid) async {
    try {
      final response = await apiClient!.get('trading-accounts');
      _localTradingAccounts[uid] = _jsonList(
        response['data'],
      ).map(TradingAccount.fromJson).toList(growable: false);
      await _persistTradingCache(uid);
      _emitTradingAccounts(uid);
    } catch (error, stackTrace) {
      if ((_localTradingAccounts[uid] ?? const []).isNotEmpty) {
        _emitTradingAccounts(uid);
      } else {
        _tradingAccountControllers[uid]?.addError(error, stackTrace);
      }
    }
  }

  Future<void> _refreshPositions(String uid) async {
    try {
      final response = await apiClient!.get('positions');
      final data = Map<String, dynamic>.from(response['data'] as Map);
      _localPositions[uid] = _jsonList(
        data['applications'],
      ).map(TradingPositionApplicationMobile.fromJson).toList(growable: false);
      _emitPositions(uid);
    } catch (error, stackTrace) {
      _positionControllers[uid]?.addError(error, stackTrace);
    }
  }

  Future<Uint8List> _uploadBytes(ApiUploadFile file) async {
    if (file.bytes case final bytes?) return Uint8List.fromList(bytes);
    if (file.path case final path?) return File(path).readAsBytes();
    throw const ApiException('The selected file could not be read.');
  }

  Future<List<List<String>>> _spreadsheetRows(ApiUploadFile file) async {
    final bytes = await _uploadBytes(file);
    final extension = file.filename.split('.').last.toLowerCase();
    if (extension == 'csv') {
      return const LineSplitter()
          .convert(utf8.decode(bytes, allowMalformed: true))
          .map(_parseCsvLine)
          .toList();
    }
    if (extension != 'xlsx') {
      throw const ApiException('Firebase import supports XLSX and CSV files.');
    }
    final workbook = Excel.decodeBytes(bytes);
    final rows = <List<String>>[];
    for (final sheet in workbook.tables.values) {
      for (final row in sheet.rows) {
        rows.add(row.map((cell) => cell?.value?.toString() ?? '').toList());
      }
    }
    return rows;
  }

  List<String> _parseCsvLine(String line) {
    final values = <String>[];
    final buffer = StringBuffer();
    var quoted = false;
    for (var index = 0; index < line.length; index++) {
      final character = line[index];
      if (character == '"') {
        if (quoted && index + 1 < line.length && line[index + 1] == '"') {
          buffer.write('"');
          index++;
        } else {
          quoted = !quoted;
        }
      } else if (character == ',' && !quoted) {
        values.add(buffer.toString());
        buffer.clear();
      } else {
        buffer.write(character);
      }
    }
    values.add(buffer.toString());
    return values;
  }

  String _heading(String value) => value
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '_')
      .replaceAll(RegExp(r'^_+|_+$'), '');

  DateTime? _parseImportDate(String value) {
    final normalized = value.trim();
    if (normalized.isEmpty) return null;
    final direct = DateTime.tryParse(normalized);
    if (direct != null) return direct;
    final match = RegExp(
      r'^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$',
    ).firstMatch(normalized);
    if (match == null) return null;
    return DateTime(
      int.parse(match.group(3)!),
      int.parse(match.group(2)!),
      int.parse(match.group(1)!),
      int.tryParse(match.group(4) ?? '') ?? 0,
      int.tryParse(match.group(5) ?? '') ?? 0,
      int.tryParse(match.group(6) ?? '') ?? 0,
    );
  }

  String _safeName(String filename) =>
      filename.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');

  String _contentType(String filename) {
    final extension = filename.split('.').last.toLowerCase();
    return switch (extension) {
      'jpg' || 'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
      'gif' => 'image/gif',
      'pdf' => 'application/pdf',
      'doc' => 'application/msword',
      'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'mp4' => 'video/mp4',
      'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'csv' => 'text/csv',
      _ => 'application/octet-stream',
    };
  }

  String _firebaseUid() {
    final uid = FirebaseAuth.instance.currentUser?.uid;
    if (uid == null) throw const ApiException('Please sign in again.');
    return uid;
  }

  Future<QuerySnapshot<Map<String, dynamic>>> _adminGet(
    String label,
    Query<Map<String, dynamic>> query,
  ) async {
    try {
      return await query.get();
    } on FirebaseException catch (error, stackTrace) {
      debugPrint(
        '[Admin Firestore] $label failed: ${error.code} ${error.message}\n$stackTrace',
      );
      throw ApiException(
        "Unable to load '$label': ${error.message ?? error.code}",
      );
    }
  }

  void _validateAdminCollection(String collection) {
    if (!_adminResourceCollections.contains(collection)) {
      throw const ApiException('This administration module is not allowed.');
    }
  }

  Future<void> _writeAudit({
    required String action,
    required String module,
    required String targetType,
    required String targetId,
    Map<String, dynamic>? oldValues,
    Map<String, Object?>? newValues,
  }) async {
    if (!firebaseEnabled) return;
    final uid = _firebaseUid();
    String actorName = '';
    int actorRole = 0;
    try {
      final actor = await _firestore.collection('users').doc(uid).get();
      actorName = '${actor.data()?['name'] ?? actor.data()?['email'] ?? ''}';
      actorRole = _intValue(actor.data()?['role_id']);
    } catch (_) {
      // The action itself must not fail because actor display data is missing.
    }
    try {
      await _firestore.collection('audit_logs').add({
        'actor_id': uid,
        'actor_name': actorName,
        'actor_role_id': actorRole,
        'action': action,
        'module': module,
        'target_type': targetType,
        'target_id': targetId,
        if (oldValues != null) 'old_values': _safeAuditMap(oldValues),
        if (newValues != null) 'new_values': _safeAuditMap(newValues),
        'platform': 'flutter',
        'ip_address': 'not_available_from_mobile_client',
        'user_agent': 'Flutter/${Platform.operatingSystem}',
        'created_at': FieldValue.serverTimestamp(),
      });
    } on FirebaseException catch (error, stackTrace) {
      if (error.code != 'permission-denied') rethrow;
      // audit_logs is intentionally server-owned. Content mutations must not be
      // reported as failed after their Firestore write already committed.
      if (kDebugMode) {
        debugPrint(
          '[PublisherAudit] clientAuditResult=server-owned '
          'module=$module action=$action errorCode=${error.code}',
        );
        debugPrint('$stackTrace');
      }
    }
    if (!_adminMutationController.isClosed) {
      _adminMutationController.add(null);
    }
  }

  Future<AppUser> _verifiedPublisher({
    required String publisherId,
    required String operation,
    required bool Function(PublishingAuthorization authorization) permission,
  }) async {
    final currentUid = _firebaseUid();
    if (publisherId.trim().isEmpty || publisherId.trim() != currentUid) {
      _debugPublisherOperation(
        operation,
        stage: 'authenticationState',
        value: 'uid-mismatch',
      );
      throw const ApiException(
        'Your sign-in session changed. Sign in again before publishing.',
      );
    }

    try {
      final snapshot = await _firestore
          .collection('users')
          .doc(currentUid)
          .get(const GetOptions(source: Source.server));
      final data = snapshot.data();
      if (data == null) {
        _debugPublisherOperation(
          operation,
          stage: 'resolvedRole',
          value: 'missing-profile',
        );
        throw const ApiException(
          'Unable to verify your publishing role because the user profile is missing.',
        );
      }
      final user = AppUser.fromJson({'id': snapshot.id, ...data});
      final authorization = PublishingAuthorization(user);
      final allowed = permission(authorization);
      _debugPublisherOperation(
        operation,
        stage: 'permissionResult',
        value:
            'allowed=$allowed roles=${authorization.resolvedRoleIds} '
            'user=${_safeUidSuffix(currentUid)}',
      );
      if (!allowed) {
        throw const ApiException(
          'Unable to publish because your account permission could not be verified.',
        );
      }
      return user;
    } on ApiException {
      rethrow;
    } on FirebaseException catch (error, stackTrace) {
      _debugPublisherFailure(operation, error, stackTrace);
      throw ApiException(_publishingFirebaseMessage(error, 'content'));
    }
  }

  Map<String, Object?> _safeAuditMap(Map<String, dynamic> values) {
    return {
      for (final entry in values.entries)
        if (!const {
          'password',
          'password_hash',
          'token',
          'notification_tokens',
        }.contains(entry.key.toLowerCase()))
          entry.key: _safeAuditValue(entry.value),
    };
  }

  Object? _safeAuditValue(Object? value) {
    if (value == null || value is bool || value is num || value is Timestamp) {
      return value;
    }
    if (value is DateTime) return Timestamp.fromDate(value);
    if (value is String) {
      return value.length <= 2000 ? value : '${value.substring(0, 2000)}â€¦';
    }
    if (value is Map) {
      return _safeAuditMap(Map<String, dynamic>.from(value));
    }
    if (value is Iterable) {
      return value.take(50).map(_safeAuditValue).toList();
    }
    return '$value';
  }

  Future<List<Map<String, dynamic>>> _approvedFinalQuestionSources() async {
    final direct = await _firestore.collection('final_exam_questions').get();
    final questions = direct.docs
        .map(
          (document) => <String, dynamic>{
            '_id': document.id,
            ...document.data(),
          },
        )
        .where(_activeFinalQuestionSource)
        .toList();

    final banks = await _firestore
        .collection('final_exam_question_banks')
        .get();
    for (final bank in banks.docs) {
      final rawQuestions = bank.data()['questions'];
      if (rawQuestions is! List) continue;
      for (var index = 0; index < rawQuestions.length; index++) {
        final raw = rawQuestions[index];
        if (raw is! Map) continue;
        final data = Map<String, dynamic>.from(raw);
        if (!_activeFinalQuestionSource(data)) continue;
        questions.add({
          '_id': '${bank.id}::${data['id'] ?? index + 1}',
          ...data,
        });
      }
    }
    final seededQuestions = questions
        .where(_finalExamSeedBankQuestion)
        .toList();
    final selectedQuestions = seededQuestions.length >= 500
        ? seededQuestions
        : questions;
    selectedQuestions.sort(
      (left, right) =>
          _intValue(left['order']).compareTo(_intValue(right['order'])),
    );
    return selectedQuestions;
  }

  Future<Map<String, dynamic>> _enrichFinalExamAttemptData(
    Map<String, dynamic> data, {
    DocumentReference<Map<String, dynamic>>? reference,
    Map<String, Map<String, dynamic>>? sourceCache,
  }) async {
    final questions = _jsonList(data['questions']);
    if (questions.isEmpty) return data;
    final sources =
        sourceCache ?? await _finalQuestionSourceMapForAttempt(questions);

    var changed = false;
    final enrichedQuestions = <Map<String, dynamic>>[];
    for (final question in questions) {
      final enriched = _enrichFinalExamQuestionSnapshot(question, sources);
      changed = changed || enriched.$2;
      enrichedQuestions.add(enriched.$1);
    }

    if (!changed) return data;
    final enrichedData = {...data, 'questions': enrichedQuestions};
    if (reference != null) {
      await reference.set({
        'questions': enrichedQuestions,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    }
    return enrichedData;
  }

  (Map<String, dynamic>, bool) _enrichFinalExamQuestionSnapshot(
    Map<String, dynamic> question,
    Map<String, Map<String, dynamic>> sourceCache,
  ) {
    final sourceId = _sourceIdFromQuestion(question);
    if (sourceId.trim().isEmpty) return (question, false);
    final source = sourceCache[sourceId];
    if (source == null) return (question, false);

    final payload = _finalQuestionPayload(
      source,
      _intValue(question['position']),
    );
    final enriched = <String, dynamic>{...question};
    var changed = false;
    for (final key in const [
      'category_i18n',
      'subcategory_i18n',
      'question_i18n',
      'scenario_text_i18n',
      'explanation_i18n',
    ]) {
      final incoming = payload[key];
      if (!_hasLocalizedChinese(incoming)) continue;
      if (_hasLocalizedChinese(enriched[key])) continue;
      enriched[key] = incoming;
      changed = true;
    }
    final optionsI18n = _mergeFinalOptionsI18n(
      enriched['options_i18n'],
      payload['options_i18n'],
    );
    if (optionsI18n.$2) {
      enriched['options_i18n'] = optionsI18n.$1;
      changed = true;
    }
    return (enriched, changed);
  }

  Future<Map<String, Map<String, dynamic>>> _finalQuestionSourceMapForAttempt(
    List<Map<String, dynamic>> questions,
  ) async {
    final ids = questions
        .map(_sourceIdFromQuestion)
        .where((id) => id.isNotEmpty);
    final directIds = <String>{};
    final bankIds = <String>{};
    for (final id in ids) {
      if (id.contains('::')) {
        bankIds.add(id.split('::').first);
      } else {
        directIds.add(id);
      }
    }
    final directFuture = _directFinalQuestionSourcesById(directIds);
    final bankFuture = _bankFinalQuestionSourcesById(bankIds);
    return {...(await directFuture), ...(await bankFuture)};
  }

  Future<Map<String, Map<String, dynamic>>> _directFinalQuestionSourcesById(
    Set<String> ids,
  ) async {
    if (ids.isEmpty) return {};
    final sources = <String, Map<String, dynamic>>{};
    final idList = ids.toList();
    for (var index = 0; index < idList.length; index += 30) {
      final batch = idList.skip(index).take(30).toList();
      final snapshot = await _firestore
          .collection('final_exam_questions')
          .where(FieldPath.documentId, whereIn: batch)
          .get();
      for (final document in snapshot.docs) {
        sources[document.id] = {'_id': document.id, ...document.data()};
      }
    }
    return sources;
  }

  Future<Map<String, Map<String, dynamic>>> _bankFinalQuestionSourcesById(
    Set<String> bankIds,
  ) async {
    if (bankIds.isEmpty) return {};
    final sources = <String, Map<String, dynamic>>{};
    for (final bankId in bankIds) {
      final bank = await _firestore
          .collection('final_exam_question_banks')
          .doc(bankId)
          .get();
      final rawQuestions = bank.data()?['questions'];
      if (rawQuestions is! List) continue;
      for (var index = 0; index < rawQuestions.length; index++) {
        final raw = rawQuestions[index];
        if (raw is! Map) continue;
        final data = Map<String, dynamic>.from(raw);
        final sourceId = '$bankId::${data['id'] ?? index + 1}';
        sources[sourceId] = {'_id': sourceId, ...data};
      }
    }
    return sources;
  }

  Map<String, Map<String, dynamic>> _sourceMapFromList(
    List<Map<String, dynamic>> sources,
  ) {
    return {
      for (final source in sources)
        if ('${source['_id'] ?? ''}'.trim().isNotEmpty)
          '${source['_id']}': source,
    };
  }

  bool _activeFinalQuestionSource(Map<String, dynamic> data) {
    final examType = '${data['examType'] ?? data['exam_type'] ?? 'final'}'
        .trim()
        .toLowerCase();
    if (examType.isNotEmpty && examType != 'final') return false;
    if (data['isActive'] == false || data['is_active'] == false) return false;
    if (data['isActive'] == true || data['is_active'] == true) return true;
    final status = '${data['status'] ?? data['approval_status'] ?? ''}'
        .trim()
        .toLowerCase();
    return status == 'approved' || status == 'active';
  }

  bool _finalExamSeedBankQuestion(Map<String, dynamic> data) {
    final seedMeta = data['seedMeta'];
    if (seedMeta is Map && seedMeta['contentVersion'] == 'final_exam_500_v1') {
      return true;
    }
    final id = '${data['questionId'] ?? data['_id'] ?? ''}'.trim();
    return RegExp(
      r'^final_exam_(theory|technical|psychology)_\d{3}$',
    ).hasMatch(id);
  }

  String _sourceIdFromQuestion(Map<String, dynamic> question) =>
      '${question['question_id'] ?? question['attempt_question_id'] ?? ''}'
          .trim();

  bool _hasLocalizedChinese(Object? value) {
    if (value is Map) {
      final zh = value['zh']?.toString().trim() ?? '';
      if (zh.isNotEmpty && _containsCjk(zh)) return true;
      return value.values.any(_hasLocalizedChinese);
    }
    if (value is Iterable) return value.any(_hasLocalizedChinese);
    return false;
  }

  (Map<String, Map<String, String>>, bool) _mergeFinalOptionsI18n(
    Object? existingRaw,
    Object? incomingRaw,
  ) {
    final existing = _localizedOptionMaps(existingRaw);
    final incoming = _localizedOptionMaps(incomingRaw);
    var changed = false;
    for (final entry in incoming.entries) {
      if (!_hasLocalizedChinese(entry.value)) continue;
      if (_hasLocalizedChinese(existing[entry.key])) continue;
      existing[entry.key] = {...?existing[entry.key], ...entry.value};
      changed = true;
    }
    return (existing, changed);
  }

  Map<String, Map<String, String>> _localizedOptionMaps(Object? raw) {
    if (raw is! Map) return {};
    final result = <String, Map<String, String>>{};
    for (final entry in raw.entries) {
      final value = _localizedMap(entry.value);
      if (value.isNotEmpty) result['${entry.key}'] = value;
    }
    return result;
  }

  Map<String, dynamic> _finalQuestionPayload(
    Map<String, dynamic> data,
    int position,
  ) {
    final id = '${data['_id'] ?? ''}';
    final questionI18n = {
      ..._localizedMapFrom(data, 'question', aliasKey: 'question_text'),
      ..._finalLocaleFieldMap(data, 'question'),
    };
    final explanationI18n = {
      ..._localizedMapFrom(data, 'explanation'),
      ..._finalLocaleFieldMap(data, 'explanation'),
    };
    return {
      'attempt_question_id': id,
      'question_id': id,
      'position': position,
      'category': _finalCategoryText(data),
      'category_i18n': _finalCategoryI18n(data),
      'subcategory': _finalSubcategoryText(data),
      'subcategory_i18n': _finalSubcategoryI18n(data),
      'question_type':
          data['question_type'] ?? data['questionType'] ?? 'multiple_choice',
      'question':
          data['question'] ?? data['question_text'] ?? questionI18n['en'] ?? '',
      'question_i18n': questionI18n,
      'scenario_text': data['scenario_text'] ?? '',
      'scenario_text_i18n': _localizedMapFrom(data, 'scenario_text'),
      'explanation': data['explanation'] ?? explanationI18n['en'] ?? '',
      'explanation_i18n': explanationI18n,
      'chart_image_url': data['chart_image_url'],
      'chart_data': data['chart_data_json'] ?? data['chart_data'],
      'chart_timeframe': data['chart_timeframe'] ?? '',
      'difficulty': data['difficulty'] ?? '',
      'options': _finalOptions(data['options'], locales: data['locales']),
      'options_i18n': _finalOptionsI18n(
        data['options'],
        i18n: data['options_i18n'],
        locales: data['locales'],
      ),
      'answer_key': _correctFinalAnswer(data),
    };
  }

  Map<String, String> _finalOptions(Object? raw, {Object? locales}) {
    final localeOptions = _localeOptions(locales, 'en');
    if (raw == null && localeOptions.isNotEmpty) {
      return {
        for (var index = 0; index < localeOptions.length; index++)
          String.fromCharCode(65 + index): localeOptions[index],
      };
    }
    if (raw is Map) {
      return raw.map(
        (key, value) => MapEntry(
          '$key',
          value is Map
              ? localizedValue(
                  value['text'] ?? value['option_text'] ?? value,
                  'en',
                )
              : '$value',
        ),
      );
    }
    if (raw is List) {
      final options = <String, String>{};
      for (var index = 0; index < raw.length; index++) {
        final value = raw[index];
        final key = String.fromCharCode(65 + index);
        options[key] = value is Map
            ? localizedValue(
                value['text'] ?? value['option_text'] ?? value,
                'en',
              )
            : '$value';
      }
      return options;
    }
    return const {};
  }

  Map<String, Map<String, String>> _finalOptionsI18n(
    Object? raw, {
    Object? i18n,
    Object? locales,
  }) {
    final translations = <String, Map<String, String>>{};
    if (raw is Map) {
      for (final entry in raw.entries) {
        final key = '${entry.key}';
        translations[key] = {
          ..._localizedMap(entry.value),
          ..._localizedMap(_optionI18nValue(i18n, key, null)),
        };
      }
    } else if (raw is List) {
      for (var index = 0; index < raw.length; index++) {
        final key = String.fromCharCode(65 + index);
        translations[key] = {
          ..._localizedMap(raw[index]),
          ..._localizedMap(_optionI18nValue(i18n, key, index)),
        };
      }
    } else if (raw == null) {
      final enOptions = _localeOptions(locales, 'en');
      final zhOptions = _localeOptions(locales, 'zh');
      final length = enOptions.length > zhOptions.length
          ? enOptions.length
          : zhOptions.length;
      for (var index = 0; index < length; index++) {
        translations[String.fromCharCode(65 + index)] = {
          if (index < enOptions.length && enOptions[index].isNotEmpty)
            'en': enOptions[index],
          if (index < zhOptions.length && zhOptions[index].isNotEmpty)
            'zh': zhOptions[index],
        };
      }
    }
    translations.removeWhere((_, value) => value.isEmpty);
    return translations;
  }

  Object? _optionI18nValue(Object? i18n, String key, int? index) {
    if (i18n is Map) return i18n[key];
    if (i18n is List && index != null && index >= 0 && index < i18n.length) {
      return i18n[index];
    }
    return null;
  }

  String _finalCategoryText(Map<String, dynamic> data) => localizedValue(
    _finalCategoryI18n(data),
    'en',
    fallback: data['category'],
  );

  Map<String, String> _finalCategoryI18n(Map<String, dynamic> data) {
    final code = '${data['category'] ?? ''}'.trim().toLowerCase();
    final seededLabel = _finalCategoryLabels[code];
    return {
      ..._localizedMapFrom(data, 'category'),
      ...localizedTextMap(data['category_label']),
      ...localizedTextMap(data['categoryLabel']),
      if (seededLabel != null) ...seededLabel,
    };
  }

  String _finalSubcategoryText(Map<String, dynamic> data) => localizedValue(
    _finalSubcategoryI18n(data),
    'en',
    fallback: data['subcategory'],
  );

  Map<String, String> _finalSubcategoryI18n(Map<String, dynamic> data) {
    return {
      ..._localizedMapFrom(data, 'subcategory'),
      ...localizedTextMap(data['subcategory_label']),
      ...localizedTextMap(data['subcategoryLabel']),
    };
  }

  Map<String, String> _finalLocaleFieldMap(
    Map<String, dynamic> data,
    String field,
  ) {
    final locales = data['locales'];
    if (locales is! Map) return const {};
    final map = <String, String>{};
    for (final language in const ['en', 'zh']) {
      final locale = locales[language];
      if (locale is Map) {
        final text = locale[field]?.toString().trim() ?? '';
        if (text.isNotEmpty) map[language] = text;
      }
    }
    return map;
  }

  List<String> _localeOptions(Object? locales, String language) {
    if (locales is! Map) return const [];
    final locale = locales[language];
    if (locale is! Map) return const [];
    final raw = locale['options'];
    if (raw is Iterable) {
      return raw.map((item) => item?.toString().trim() ?? '').toList();
    }
    return const [];
  }

  static const _finalCategoryLabels = <String, Map<String, String>>{
    'theory': {'en': 'Theory / Basic Trading Knowledge', 'zh': '理论 / 基础交易知识'},
    'technical': {
      'en': 'Technical Analysis / Market Analysis',
      'zh': '技术分析 / 市场分析',
    },
    'psychology': {
      'en': 'Trading Psychology / Risk Discipline',
      'zh': '交易心理 / 风险纪律',
    },
    'foundation': {
      'en': 'Theory / Basic Trading Knowledge',
      'zh': '理论 / 基础交易知识',
    },
    'technique': {
      'en': 'Technical Analysis / Market Analysis',
      'zh': '技术分析 / 市场分析',
    },
  };

  Map<String, String> _localizedMapFrom(
    Map<String, dynamic> data,
    String key, {
    String? aliasKey,
  }) {
    final map = <String, String>{
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

  Map<String, String> _localizedMap(Object? value) {
    if (value is Map) {
      final map = <String, String>{
        ...localizedTextMap(value['text_i18n']),
        ...localizedTextMap(value['option_text_i18n']),
        ...localizedTextMap(value['text']),
        ...localizedTextMap(value['option_text']),
        ...localizedTextMap(value),
      };
      final directEnglish = _trimmed(value['en']);
      final directChinese = _trimmed(value['zh']);
      final english = directEnglish.isNotEmpty
          ? directEnglish
          : _firstNonEmpty([value['text_en'], value['option_text_en']]);
      final chinese = directChinese.isNotEmpty
          ? directChinese
          : _firstNonEmpty([value['text_zh'], value['option_text_zh']]);
      if (english.isNotEmpty) map['en'] = english;
      if (chinese.isNotEmpty) map['zh'] = chinese;
      return map;
    }
    return localizedTextMap(value);
  }

  String _trimmed(Object? value) => value?.toString().trim() ?? '';

  String _firstNonEmpty(Iterable<Object?> values) {
    for (final value in values) {
      final text = _trimmed(value);
      if (text.isNotEmpty) return text;
    }
    return '';
  }

  bool _containsCjk(String value) => RegExp(r'[\u3400-\u9fff]').hasMatch(value);

  String _correctFinalAnswer(Map<String, dynamic> data) {
    final direct =
        data['answer_key'] ??
        data['correct_answer'] ??
        data['correct_option'] ??
        data['correct_option_key'];
    if (direct != null && '$direct'.trim().isNotEmpty) return '$direct'.trim();
    final rawIndex = data['correctOptionIndex'] ?? data['correct_option_index'];
    final optionIndex = rawIndex is num
        ? rawIndex.toInt()
        : int.tryParse(rawIndex?.toString() ?? '');
    if (optionIndex != null && optionIndex >= 0 && optionIndex <= 3) {
      return String.fromCharCode(65 + optionIndex);
    }
    final options = data['options'];
    if (options is Map) {
      for (final entry in options.entries) {
        if (entry.value is Map && entry.value['is_correct'] == true) {
          return '${entry.key}';
        }
      }
    } else if (options is List) {
      for (var index = 0; index < options.length; index++) {
        final option = options[index];
        if (option is Map && option['is_correct'] == true) {
          return String.fromCharCode(65 + index);
        }
      }
    }
    return '';
  }

  Map<String, dynamic> _adminUserMap(
    DocumentSnapshot<Map<String, dynamic>> document,
  ) {
    final data = document.data() ?? const <String, dynamic>{};
    final roleId = _intValue(data['role_id']);
    return {
      'id': document.id,
      ...data,
      'role': {'id': roleId, 'name': data['role_name'] ?? _roleLabel(roleId)},
    };
  }

  Map<String, dynamic> _downlineUserMap(
    DocumentSnapshot<Map<String, dynamic>> document,
  ) {
    final data = document.data() ?? const <String, dynamic>{};
    final roleId = _intValue(data['role_id']);
    return {
      'id': document.id,
      'user_id': document.id,
      ...data,
      'role': {'id': roleId, 'name': data['role_name'] ?? _roleLabel(roleId)},
    };
  }

  Future<String> _safeReferralCode(String uid) async {
    try {
      return await _ensureReferralCode(uid);
    } on FirebaseException catch (error) {
      if (!_isPermissionDenied(error)) rethrow;
      return _existingReferralCode(uid);
    }
  }

  Future<String> _existingReferralCode(String uid) async {
    final profile = await _firestore.collection('users').doc(uid).get();
    final existingProfileCode = '${profile.data()?['referral_code'] ?? ''}'
        .trim();
    if (existingProfileCode.isNotEmpty) return existingProfileCode;

    try {
      final existing = await _firestore
          .collection('referral_codes')
          .where('owner_user_id', isEqualTo: uid)
          .where('code_type', isEqualTo: 'normal')
          .limit(1)
          .get();
      if (existing.docs.isNotEmpty) {
        return '${existing.docs.first.data()['code'] ?? existing.docs.first.id}'
            .trim();
      }
    } on FirebaseException catch (error) {
      if (!_isPermissionDenied(error)) rethrow;
    }
    return '';
  }

  Future<String> _ensureReferralCode(String uid) async {
    final profile = await _firestore.collection('users').doc(uid).get();
    final existingProfileCode = '${profile.data()?['referral_code'] ?? ''}'
        .trim();
    if (existingProfileCode.isNotEmpty) return existingProfileCode;

    final existing = await _firestore
        .collection('referral_codes')
        .where('owner_user_id', isEqualTo: uid)
        .where('code_type', isEqualTo: 'normal')
        .limit(1)
        .get();
    if (existing.docs.isNotEmpty) {
      return '${existing.docs.first.data()['code'] ?? existing.docs.first.id}'
          .trim();
    }

    for (var attempt = 0; attempt < 5; attempt++) {
      final code = _randomReferralCode();
      final reference = _firestore
          .collection('referral_codes')
          .doc(code.toLowerCase());
      final collision = await reference.get();
      if (collision.exists) continue;
      try {
        await reference.set({
          'code': code,
          'user_id': uid,
          'owner_user_id': uid,
          'code_type': 'normal',
          'allowed_purpose': 'upline_downline',
          'registration_role_id': RolePermissionMatrix.trader,
          'is_active': true,
          'created_at': FieldValue.serverTimestamp(),
        });
        return code;
      } on FirebaseException catch (error) {
        if (error.code != 'already-exists' && error.code != 'aborted') {
          rethrow;
        }
      }
    }
    throw const ApiException('Unable to create referral code. Try again.');
  }

  bool _isPermissionDenied(FirebaseException error) =>
      error.code == 'permission-denied';

  String _randomReferralCode() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    final random = Random.secure();
    return List.generate(
      8,
      (_) => alphabet[random.nextInt(alphabet.length)],
    ).join();
  }

  DateTime _firestoreDate(Object? value) {
    if (value is Timestamp) return value.toDate();
    if (value is DateTime) return value;
    return DateTime.tryParse('$value') ?? DateTime(1970);
  }

  DateTime? _nullableFirestoreDate(Object? value) {
    if (value == null) return null;
    if (value is Timestamp) return value.toDate();
    if (value is DateTime) return value;
    return DateTime.tryParse('$value');
  }

  String _preview(String value) {
    final compact = value.trim().replaceAll(RegExp(r'\s+'), ' ');
    return compact.length <= 120 ? compact : '${compact.substring(0, 117)}...';
  }

  String _maskedMt5AccountRef(String value) {
    final compact = value.trim();
    if (compact.length <= 4) return '****';
    return '***${compact.substring(compact.length - 4)}';
  }

  int _intValue(Object? value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _roleLabel(int roleId) => AppRoles.name(roleId);

  List<Map<String, dynamic>> _jsonList(Object? raw) {
    if (raw is! List) return const [];
    return raw.whereType<Map>().map(Map<String, dynamic>.from).toList();
  }

  Stream<List<Mt5TradeRecord>> _combineMt5RecordStreams(
    List<Stream<List<Mt5TradeRecord>>> streams,
  ) {
    if (streams.isEmpty) return Stream.value(const <Mt5TradeRecord>[]);
    final subscriptions = <StreamSubscription<List<Mt5TradeRecord>>>[];
    final latest = List<List<Mt5TradeRecord>>.filled(
      streams.length,
      const <Mt5TradeRecord>[],
    );

    late final StreamController<List<Mt5TradeRecord>> controller;
    controller = StreamController<List<Mt5TradeRecord>>(
      onListen: () {
        void emit() {
          final combined = _dedupeAndSortMt5Records(
            latest.expand((items) => items),
          );
          if (!controller.isClosed) controller.add(combined);
        }

        for (var index = 0; index < streams.length; index += 1) {
          subscriptions.add(
            streams[index].listen((items) {
              latest[index] = items;
              emit();
            }, onError: controller.addError),
          );
        }
      },
      onCancel: () async {
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      },
    );
    return controller.stream;
  }

  Stream<List<T>> _combineListStreams<T>(
    List<Stream<List<T>>> streams, {
    required String Function(T item) identity,
    required int Function(T left, T right) compare,
  }) {
    if (streams.isEmpty) return Stream.value(<T>[]);
    final subscriptions = <StreamSubscription<List<T>>>[];
    final latest = List<List<T>>.generate(
      streams.length,
      (_) => <T>[],
      growable: false,
    );
    final ready = List<bool>.filled(streams.length, false);

    late final StreamController<List<T>> controller;
    controller = StreamController<List<T>>(
      onListen: () {
        void emit() {
          if (ready.any((value) => !value) || controller.isClosed) return;
          final unique = <String, T>{};
          for (final item in latest.expand((items) => items)) {
            unique.putIfAbsent(identity(item), () => item);
          }
          final combined = unique.values.toList(growable: false)..sort(compare);
          controller.add(List<T>.unmodifiable(combined));
        }

        for (var index = 0; index < streams.length; index += 1) {
          subscriptions.add(
            streams[index].listen((items) {
              latest[index] = items;
              ready[index] = true;
              emit();
            }, onError: controller.addError),
          );
        }
      },
      onCancel: () async {
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      },
    );
    return controller.stream;
  }

  Stream<List<Mt5TradeRecord>> _filterCompletedMt5ImportRecords(
    Stream<List<Mt5TradeRecord>> records,
    Stream<Set<String>> completedBatchIds,
  ) {
    StreamSubscription<List<Mt5TradeRecord>>? recordSubscription;
    StreamSubscription<Set<String>>? batchSubscription;
    var latestRecords = const <Mt5TradeRecord>[];
    var latestCompleted = const <String>{};
    late final StreamController<List<Mt5TradeRecord>> controller;
    controller = StreamController<List<Mt5TradeRecord>>(
      onListen: () {
        void emit() {
          if (controller.isClosed) return;
          controller.add(
            latestRecords
                .where(
                  (record) =>
                      record.importBatchId.isEmpty ||
                      latestCompleted.contains(record.importBatchId),
                )
                .toList(growable: false),
          );
        }

        recordSubscription = records.listen((value) {
          latestRecords = value;
          emit();
        }, onError: controller.addError);
        batchSubscription = completedBatchIds.listen((value) {
          latestCompleted = value;
          emit();
        }, onError: controller.addError);
      },
      onCancel: () async {
        await recordSubscription?.cancel();
        await batchSubscription?.cancel();
      },
    );
    return controller.stream;
  }

  Stream<List<CapitalTransaction>> _filterCompletedMt5ImportCapitals(
    Stream<List<_Mt5ImportedCapitalRecord>> records,
    Stream<Set<String>> completedBatchIds,
  ) {
    StreamSubscription<List<_Mt5ImportedCapitalRecord>>? recordSubscription;
    StreamSubscription<Set<String>>? batchSubscription;
    var latestRecords = const <_Mt5ImportedCapitalRecord>[];
    var latestCompleted = const <String>{};
    late final StreamController<List<CapitalTransaction>> controller;
    controller = StreamController<List<CapitalTransaction>>(
      onListen: () {
        void emit() {
          if (controller.isClosed) return;
          controller.add(
            latestRecords
                .where((record) => latestCompleted.contains(record.batchId))
                .map((record) => record.capital)
                .toList(growable: false),
          );
        }

        recordSubscription = records.listen((value) {
          latestRecords = value;
          emit();
        }, onError: controller.addError);
        batchSubscription = completedBatchIds.listen((value) {
          latestCompleted = value;
          emit();
        }, onError: controller.addError);
      },
      onCancel: () async {
        await recordSubscription?.cancel();
        await batchSubscription?.cancel();
      },
    );
    return controller.stream;
  }

  Stream<List<Announcement>> _combineAnnouncementStreams(
    List<Stream<List<Announcement>>> streams,
  ) {
    final subscriptions = <StreamSubscription<List<Announcement>>>[];
    final latest = List<List<Announcement>>.filled(streams.length, const []);
    late final StreamController<List<Announcement>> controller;
    controller = StreamController<List<Announcement>>(
      onListen: () {
        void emit() {
          final unique = <String, Announcement>{};
          for (final announcement in latest.expand((items) => items)) {
            unique[announcement.id] = announcement;
          }
          if (!controller.isClosed) controller.add(unique.values.toList());
        }

        for (var index = 0; index < streams.length; index++) {
          subscriptions.add(
            streams[index].listen((items) {
              latest[index] = items;
              emit();
            }, onError: controller.addError),
          );
        }
      },
      onCancel: () async {
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      },
    );
    return controller.stream;
  }

  Stream<List<Mt5TradeRecord>> _combineMt5RecordsWithAnnotations(
    Stream<List<Mt5TradeRecord>> records,
    Stream<List<Mt5JournalAnnotation>> annotations,
  ) {
    StreamSubscription<List<Mt5TradeRecord>>? recordSubscription;
    StreamSubscription<List<Mt5JournalAnnotation>>? annotationSubscription;
    var latestRecords = const <Mt5TradeRecord>[];
    var latestAnnotations = const <Mt5JournalAnnotation>[];
    late final StreamController<List<Mt5TradeRecord>> controller;
    controller = StreamController<List<Mt5TradeRecord>>(
      onListen: () {
        void emit() {
          final byLifecycle = {
            for (final item in latestAnnotations) item.lifecycleKey: item,
          };
          final merged = latestRecords
              .map(
                (record) => record.withAnnotation(
                  byLifecycle[record.canonicalLifecycleKey],
                ),
              )
              .toList(growable: false);
          if (!controller.isClosed) controller.add(merged);
        }

        recordSubscription = records.listen((value) {
          latestRecords = value;
          emit();
        }, onError: controller.addError);
        annotationSubscription = annotations.listen((value) {
          latestAnnotations = value;
          emit();
        }, onError: controller.addError);
      },
      onCancel: () async {
        await recordSubscription?.cancel();
        await annotationSubscription?.cancel();
      },
    );
    return controller.stream;
  }

  List<Mt5TradeRecord> _dedupeAndSortMt5Records(
    Iterable<Mt5TradeRecord> records,
  ) => dedupeMt5LifecycleRecords(records);

  List<Mt5Account> _sortMt5Accounts(Iterable<Mt5Account> accounts) {
    final sorted = accounts.toList(growable: false);
    sorted.sort((left, right) {
      if (left.isPrimary != right.isPrimary) return left.isPrimary ? -1 : 1;
      final leftDate = left.updatedAt ?? DateTime.fromMillisecondsSinceEpoch(0);
      final rightDate =
          right.updatedAt ?? DateTime.fromMillisecondsSinceEpoch(0);
      return rightDate.compareTo(leftDate);
    });
    return sorted;
  }

  Mt5Account? _resolveMainMt5Account(
    List<Mt5Account> accounts,
    String mainKey,
  ) {
    if (accounts.isEmpty) return null;
    final normalizedKey = mainKey.trim();
    if (normalizedKey.isNotEmpty) {
      for (final account in accounts) {
        if (account.accountKey == normalizedKey ||
            account.id == normalizedKey) {
          return account;
        }
      }
      return null;
    }
    return null;
  }

  String _localMainMt5Key(List<Mt5Account> accounts) {
    final selected = accounts
        .where((account) => account.isPrimary)
        .toList(growable: false);
    return selected.length == 1 ? selected.single.accountKey : '';
  }

  String _mainMt5AccountIdFrom(Map<String, dynamic> data) {
    return (data['mainMt5AccountId'] ??
            data['main_mt5_account_id'] ??
            data['mainMt5AccountKey'] ??
            data['main_mt5_account_key'] ??
            '')
        .toString()
        .trim();
  }

  String _mt5OwnerUidFrom(Map<String, dynamic> data) {
    return (data['ownerUid'] ?? data['user_id'] ?? data['userId'] ?? '')
        .toString()
        .trim();
  }

  String _mt5AccountIdFrom(Map<String, dynamic> data) {
    return (data['mt5AccountId'] ??
            data['mt5_account_id'] ??
            data['account_key'] ??
            data['accountKey'] ??
            '')
        .toString()
        .trim();
  }

  bool _isSelectableMt5Account(Map<String, dynamic> data) {
    final status = (data['status'] ?? data['sync_status'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    return data['is_active'] != false &&
        data['isActive'] != false &&
        data['is_deleted'] != true &&
        data['isDeleted'] != true &&
        data['is_archived'] != true &&
        data['isArchived'] != true &&
        data['deleted_at'] == null &&
        data['archived_at'] == null &&
        status != 'deleted' &&
        status != 'archived' &&
        status != 'disabled';
  }

  Mt5MainAccountException _mainMt5AccountFirebaseException(
    FirebaseException error,
  ) {
    return switch (error.code) {
      'permission-denied' => Mt5MainAccountException(
        error.code,
        'You do not have permission to select this MT5 account.',
        cause: error,
      ),
      'unauthenticated' => Mt5MainAccountException(
        error.code,
        'Your session has expired. Please sign in again.',
        cause: error,
      ),
      'not-found' => Mt5MainAccountException(
        error.code,
        'The selected MT5 account no longer exists.',
        cause: error,
      ),
      'unavailable' || 'deadline-exceeded' => Mt5MainAccountException(
        error.code,
        'The service is temporarily unavailable. Please retry.',
        cause: error,
      ),
      'aborted' => Mt5MainAccountException(
        error.code,
        'The main-account update was interrupted. Please retry.',
        cause: error,
      ),
      'failed-precondition' => Mt5MainAccountException(
        error.code,
        'The selected MT5 account is no longer available.',
        cause: error,
      ),
      _ => Mt5MainAccountException(
        error.code,
        'Unable to update the main account.',
        cause: error,
      ),
    };
  }

  List<Mt5Account> _mockMt5Accounts(String uid) {
    final now = DateTime.now();
    const login = 900000001;
    const server = 'Demo-Server';
    final accountKey = mt5AccountKey(server, login);
    return [
      Mt5Account(
        id: accountKey,
        userId: uid,
        accountKey: accountKey,
        login: login,
        brokerServer: server,
        displayName: 'PU Prime Demo',
        isPrimary: true,
        syncStatus: 'connected',
        lastSyncTime: now,
        lastSuccessfulSyncAt: now,
        lastConnectionCheckAt: now,
        lastHeartbeatAt: now,
        balance: 9987.36,
        equity: 9991.89,
        currency: 'USD',
        updatedAt: now,
      ),
    ];
  }

  List<Mt5TradeRecord> _mockMt5Records(String uid, String accountKey) {
    final now = DateTime.now();
    const login = 900000001;
    const server = 'Demo-Server';
    return [
      Mt5TradeRecord(
        id: 'position_${login}_${server}_281558192',
        userId: uid,
        accountKey: accountKey,
        mt5Login: login,
        brokerServer: server,
        symbol: 'BTCUSD',
        positionId: 281558192,
        uniqueMt5Id: '281558192',
        tradeType: 'sell',
        recordType: 'position',
        status: 'open',
        lotSize: 0.02,
        entryPrice: 62658.06,
        currentPrice: 62431.29,
        sl: 63843.58,
        tp: 58437.49,
        openTime: now.subtract(const Duration(hours: 2)),
        profit: 4.53,
        source: 'mobile_mt5',
        syncedAt: now,
        lastSeenAt: now,
      ),
      Mt5TradeRecord(
        id: 'order_${login}_${server}_281521112',
        userId: uid,
        accountKey: accountKey,
        mt5Login: login,
        brokerServer: server,
        symbol: 'XAUUSD.s',
        orderTicket: 281521112,
        uniqueMt5Id: '281521112',
        tradeType: 'buy',
        recordType: 'order',
        status: 'pending',
        entryPrice: 4146,
        currentPrice: 4175.18,
        sl: 4130,
        tp: 4300,
        openTime: now.subtract(const Duration(hours: 5)),
        source: 'mobile_mt5',
        syncedAt: now,
        lastSeenAt: now,
      ),
    ];
  }

  Mt5SyncState _mockMt5SyncState(String uid, String accountKey) {
    final now = DateTime.now();
    const login = 900000001;
    const server = 'Demo-Server';
    return Mt5SyncState(
      id: 'default',
      userId: uid,
      accountKey: accountKey,
      login: login,
      brokerServer: server,
      syncStatus: 'connected',
      realtimeMode: true,
      syncIntervalSeconds: 1,
      lastSyncStartedAt: now.subtract(const Duration(seconds: 1)),
      lastSyncedTime: now,
      lastSuccessfulSyncAt: now,
      lastConnectionCheckAt: now,
      lastHeartbeatAt: now,
      serviceVersion: 'mock',
      updatedAt: now,
      lastTradeActivityAt: now,
      lastTradeActivityId: 'mock_activity',
      lastTradeActivityType: 'mock',
      lastTradeChangeVersion: 1,
      changedRecordCount: 2,
      writtenRecordCount: 2,
    );
  }

  void _emitPairs() {
    if (!_pairsController.isClosed) {
      _pairsController.add([..._localPairs]..sort(_sortPairs));
    }
  }

  void _emitJournals(String uid) {
    final controller = _journalControllers[uid];
    if (controller == null || controller.isClosed) {
      return;
    }

    controller.add(
      [..._localJournals.putIfAbsent(uid, () => [])]
        ..sort((left, right) => right.closeDate.compareTo(left.closeDate)),
    );
  }

  void _emitCapitals(String uid) {
    final controller = _capitalControllers[uid];
    if (controller == null || controller.isClosed) {
      return;
    }

    controller.add(
      [..._localCapitals.putIfAbsent(uid, () => [])]
        ..sort((left, right) => right.depositDate.compareTo(left.depositDate)),
    );
  }

  void _emitTradingDataDisplayMode(String uid) {
    final controller = _displayModeControllers[uid];
    if (controller == null || controller.isClosed) return;
    final saved = storage?.readMap(uid, 'trading_data_display_mode');
    controller.add(
      TradingDataDisplayMode.fromStorage(
        saved?['mode'] ?? saved?['tradingDataDisplayMode'],
      ),
    );
  }

  void _emitAccount(String uid) {
    final controller = _accountControllers[uid];
    if (controller == null || controller.isClosed) {
      return;
    }

    controller.add(_localAccounts[uid] ?? TraderAccountProfile.empty(uid));
  }

  void _emitTradingAccounts(String uid) {
    final controller = _tradingAccountControllers[uid];
    if (controller == null || controller.isClosed) return;
    controller.add(
      [..._localTradingAccounts.putIfAbsent(uid, () => [])]
        ..sort((left, right) {
          if (left.isPrimary != right.isPrimary) return left.isPrimary ? -1 : 1;
          return left.displayName.compareTo(right.displayName);
        }),
    );
  }

  void _emitPositions(String uid) {
    final controller = _positionControllers[uid];
    if (controller == null || controller.isClosed) {
      return;
    }

    controller.add(
      [..._localPositions.putIfAbsent(uid, () => [])]..sort(
        (left, right) => (right.submittedAt ?? DateTime(1900)).compareTo(
          left.submittedAt ?? DateTime(1900),
        ),
      ),
    );
  }

  void _emitBlogs() {
    if (!_blogsController.isClosed) {
      _blogsController.add(
        [..._localBlogs]..sort(
          (left, right) => (right.publishedAt ?? DateTime(1900)).compareTo(
            left.publishedAt ?? DateTime(1900),
          ),
        ),
      );
    }
  }

  void _emitAnalyses() {
    if (!_analysesController.isClosed) {
      _analysesController.add(
        [..._localAnalyses]..sort(
          (left, right) => (right.analysisDate ?? DateTime(1900)).compareTo(
            left.analysisDate ?? DateTime(1900),
          ),
        ),
      );
    }
  }

  void _emitSignals() {
    if (!_signalsController.isClosed) {
      _signalsController.add(
        [..._localSignals]..sort(
          (left, right) =>
              (right.publishedAt ??
                      right.triggerTime ??
                      right.createdAt ??
                      DateTime(1900))
                  .compareTo(
                    left.publishedAt ??
                        left.triggerTime ??
                        left.createdAt ??
                        DateTime(1900),
                  ),
        ),
      );
    }
  }

  void _emitResources() {
    if (!_resourcesController.isClosed) {
      _resourcesController.add(
        [..._localResources]..sort(
          (left, right) => (right.createdAt ?? DateTime(1900)).compareTo(
            left.createdAt ?? DateTime(1900),
          ),
        ),
      );
    }
  }

  void _emitExamQuestions() {
    if (!_examQuestionsController.isClosed) {
      _examQuestionsController.add(
        [
            ..._localExamQuestions,
          ].where((question) => question.status == 'approved').toList()
          ..sort((left, right) => left.difficulty.compareTo(right.difficulty)),
      );
    }
  }

  void _emitExamAttempts(String uid) {
    final controller = _examAttemptControllers[uid];
    if (controller == null || controller.isClosed) {
      return;
    }

    controller.add(
      [..._localExamAttempts.putIfAbsent(uid, () => [])]
        ..sort((left, right) => right.examDate.compareTo(left.examDate)),
    );
  }

  void _emitRecordings() {
    if (!_recordingsController.isClosed) {
      _recordingsController.add(
        [..._localRecordings]
            .where(
              (recording) =>
                  recording.status && recording.approvalStatus == 'approved',
            )
            .toList()
          ..sort(
            (left, right) => (right.createdAt ?? DateTime(1900)).compareTo(
              left.createdAt ?? DateTime(1900),
            ),
          ),
      );
    }
  }

  void _emitKnowledge() {
    if (!_knowledgeController.isClosed) {
      final seeded = _localKnowledge
          .where((item) => item.isVisible)
          .where(_isSeededKnowledgeCentre)
          .toList();
      final items = seeded.length >= 30
          ? seeded
          : [..._localKnowledge].where((item) => item.isVisible).toList();
      _knowledgeController.add(items..sort(_sortKnowledgeCentreItems));
    }
  }

  List<KnowledgeCentreItemMobile> _knowledgeItemsFromSnapshot(
    QuerySnapshot<Map<String, dynamic>> snapshot,
  ) {
    return snapshot.docs
        .map(KnowledgeCentreItemMobile.fromFirestore)
        .where((item) => item.isVisible)
        .toList();
  }

  List<KnowledgeCentreItemMobile> _preferredKnowledgeItems(
    List<KnowledgeCentreItemMobile> primary,
    List<KnowledgeCentreItemMobile> camelCase,
  ) {
    final primarySeeded = primary.where(_isSeededKnowledgeCentre).toList();
    final camelSeeded = camelCase.where(_isSeededKnowledgeCentre).toList();
    final items = primarySeeded.length >= 30
        ? primarySeeded
        : camelSeeded.length >= 30
        ? camelSeeded
        : primary.isNotEmpty
        ? primary
        : camelCase;
    return [...items]..sort(_sortKnowledgeCentreItems);
  }

  bool _isSeededKnowledgeCentre(KnowledgeCentreItemMobile item) {
    final id = item.knowledgeCenterId.isNotEmpty
        ? item.knowledgeCenterId
        : item.id;
    return RegExp(r'^KC_(THEORY|TECHNICAL|PSYCHOLOGY)_\d{3}$').hasMatch(id);
  }

  int _sortKnowledgeCentreItems(
    KnowledgeCentreItemMobile left,
    KnowledgeCentreItemMobile right,
  ) {
    if (left.order != right.order) return left.order.compareTo(right.order);
    return left.title.compareTo(right.title);
  }

  int _sortPairs(TradingPair left, TradingPair right) {
    return left.symbol.compareTo(right.symbol);
  }

  String _newLocalId(String prefix) {
    return '$prefix-${DateTime.now().microsecondsSinceEpoch}';
  }

  DateTime _dateOnly(DateTime value) {
    return DateTime(value.year, value.month, value.day);
  }

  static final _demoBlogs = [
    TradingBlogPost(
      id: 'risk-playbook',
      title: 'Risk Playbook for Funded Traders',
      slug: 'risk-playbook-for-funded-traders',
      category: 'risk_management',
      excerpt:
          'A practical checklist for sizing, drawdown control, and review.',
      content:
          'Use fixed risk, respect maximum daily loss, and review every trade before scaling. A funded account rewards repeatability more than one strong entry.',
      tags: const ['risk', 'funded', 'discipline'],
      isFeatured: true,
      publishedAt: DateTime.now().subtract(const Duration(days: 2)),
      views: 128,
    ),
    TradingBlogPost(
      id: 'psychology-after-loss',
      title: 'What To Do After a Losing Trade',
      slug: 'what-to-do-after-a-losing-trade',
      category: 'psychology_sharing',
      excerpt: 'A simple reset routine before the next setup.',
      content:
          'After a loss, document the reason, wait for the next qualified setup, and avoid increasing lot size just to recover faster.',
      tags: const ['psychology', 'journal'],
      publishedAt: DateTime.now().subtract(const Duration(days: 5)),
      views: 84,
    ),
  ];

  static final _demoAnalyses = [
    MarketAnalysisItem(
      id: 'xauusd-weekly-plan',
      title: 'XAUUSD Weekly Market Outlook',
      market: 'XAUUSD',
      trendStrength: 'Moderate bullish pressure',
      analysisDate: DateTime.now(),
      marketOverview:
          'Gold is holding above the recent demand zone while buyers defend higher lows.',
      trendStructure:
          'Short-term structure remains bullish while price trades above the last swing low.',
      keyZones: 'Support: 2320-2328. Resistance: 2368-2375.',
      analystView:
          'Wait for confirmation around support before looking for continuation.',
      strategy: 'Buy dips only after rejection candles or liquidity sweep.',
      tradingPlan:
          'Plan A: Buy confirmation at support. Plan B: Stand aside below 2320.',
      chartSignals: 'RSI neutral, trend line still respected.',
      rsiLevel: '52',
      orderBlock: 'Demand at 2320-2328',
      outlookCode: 'OUTLOOK-DEMO-1',
    ),
  ];

  static final _demoSignals = [
    TradingSignalItem(
      id: 'sig-xauusd-buy',
      tradingPair: 'XAUUSD',
      signalCode: 'HC-XAU-001',
      title: 'Gold Support Rejection',
      immediateAction: 'Buy on confirmation',
      entryPrice: '2332.50',
      stopLoss: '2324.00',
      targets: const ['2342.00', '2355.00', '2368.00'],
      riskLevel: 'Medium',
      status: 1,
      triggerTime: DateTime.now().subtract(const Duration(hours: 2)),
    ),
    TradingSignalItem(
      id: 'sig-eurusd-pending',
      tradingPair: 'EURUSD',
      signalCode: 'HC-EU-002',
      title: 'EURUSD Range Break',
      immediateAction: 'Wait for breakout',
      entryPrice: '1.0870',
      stopLoss: '1.0835',
      targets: const ['1.0910', '1.0950'],
      riskLevel: 'Low',
      status: 0,
      triggerTime: DateTime.now().subtract(const Duration(days: 1)),
    ),
  ];

  static final _demoResources = [
    TradingResourceItem(
      id: 'recording-risk-class',
      type: 'recording',
      title: 'Risk Management Class',
      description: 'Recorded mentor session for account protection.',
      url: 'https://example.com/recording',
      createdAt: DateTime.now().subtract(const Duration(days: 3)),
    ),
    TradingResourceItem(
      id: 'marketing-pack',
      type: 'marketing',
      title: 'Recruiter Marketing Pack',
      description: 'Starter content for trading community promotion.',
      url: 'https://example.com/resource',
      createdAt: DateTime.now().subtract(const Duration(days: 6)),
    ),
  ];

  static final _demoExamQuestions = [
    TradingExamQuestionMobile(
      id: 'exam-risk-1',
      category: 'Risk',
      difficulty: 'risk',
      questionText:
          'A trader has 1,000u capital and risks 3% on one trade. What is the risk amount?',
      explanation: '3% of 1,000u is 30u.',
      options: const [
        TradingExamOptionMobile(id: 'a', optionText: '10u', position: 1),
        TradingExamOptionMobile(
          id: 'b',
          optionText: '30u',
          isCorrect: true,
          position: 2,
        ),
        TradingExamOptionMobile(id: 'c', optionText: '100u', position: 3),
      ],
    ),
    TradingExamQuestionMobile(
      id: 'exam-psychology-1',
      category: 'Psychology',
      difficulty: 'psychology',
      questionText: 'What should you avoid immediately after a losing trade?',
      explanation:
          'Increasing size emotionally after a loss is revenge trading.',
      options: const [
        TradingExamOptionMobile(id: 'a', optionText: 'Reviewing the setup'),
        TradingExamOptionMobile(id: 'b', optionText: 'Taking a short break'),
        TradingExamOptionMobile(
          id: 'c',
          optionText: 'Doubling lot size to recover',
          isCorrect: true,
        ),
      ],
    ),
    TradingExamQuestionMobile(
      id: 'exam-technical-1',
      category: 'Technical',
      difficulty: 'technical',
      questionText:
          'A support zone is most useful when it is paired with what?',
      explanation: 'Confirmation helps avoid blind entries into weak levels.',
      options: const [
        TradingExamOptionMobile(
          id: 'a',
          optionText: 'Entry confirmation',
          isCorrect: true,
        ),
        TradingExamOptionMobile(id: 'b', optionText: 'Random lot increase'),
        TradingExamOptionMobile(id: 'c', optionText: 'Ignoring stop loss'),
      ],
    ),
    TradingExamQuestionMobile(
      id: 'exam-platform-1',
      category: 'Platform',
      difficulty: 'platform',
      questionText: 'Why should MT5 server time be converted consistently?',
      explanation:
          'Consistent time keeps journal analytics and daily rules accurate.',
      options: const [
        TradingExamOptionMobile(id: 'a', optionText: 'To change profit amount'),
        TradingExamOptionMobile(
          id: 'b',
          optionText: 'To align journal records by the correct day',
          isCorrect: true,
        ),
        TradingExamOptionMobile(id: 'c', optionText: 'To hide losing trades'),
      ],
    ),
    TradingExamQuestionMobile(
      id: 'exam-foundation-1',
      category: 'Foundation',
      questionText: 'What is the first priority before entering a trade?',
      explanation: 'Plan, risk, and invalidation should be clear before entry.',
      options: const [
        TradingExamOptionMobile(
          id: 'a',
          optionText: 'Know the risk and plan',
          isCorrect: true,
        ),
        TradingExamOptionMobile(id: 'b', optionText: 'Enter immediately'),
        TradingExamOptionMobile(
          id: 'c',
          optionText: 'Ignore the market structure',
        ),
      ],
    ),
  ];

  static final _demoRecordings = [
    TradingRecordingClassMobile(
      id: 'class-risk-core',
      title: 'Core Risk Management Class',
      description:
          'Recorded lesson covering capital protection, daily loss limits, and trade review.',
      videoUrl: 'https://example.com/classes/risk-core',
      sourceName: 'HC Mentor Desk',
      createdAt: DateTime.now().subtract(const Duration(days: 4)),
      materials: const [
        TradingRecordingMaterialMobile(
          title: 'Risk Checklist PDF',
          filePath: 'https://example.com/files/risk-checklist.pdf',
          originalFilename: 'risk-checklist.pdf',
          mimeType: 'application/pdf',
          fileSize: 384000,
        ),
      ],
    ),
    TradingRecordingClassMobile(
      id: 'class-market-structure',
      title: 'Market Structure Replay',
      description:
          'Replay class on higher highs, lower lows, liquidity sweeps, and entry timing.',
      videoUrl: 'https://example.com/classes/market-structure',
      sourceName: 'Market Analyst Team',
      createdAt: DateTime.now().subtract(const Duration(days: 8)),
    ),
  ];

  static final _demoKnowledge = [
    KnowledgeCentreItemMobile(
      id: 'knowledge-prop-rules',
      title: 'Prop Firm Rule Sheet',
      description:
          'Summary of profit target, daily loss, total loss, and best-day consistency rules.',
      filePath: 'https://example.com/knowledge/prop-rules.pdf',
      approvedAt: DateTime.now().subtract(const Duration(days: 3)),
      createdAt: DateTime.now().subtract(const Duration(days: 3)),
    ),
    KnowledgeCentreItemMobile(
      id: 'knowledge-journal-guide',
      title: 'Trading Journal Guide',
      description:
          'How to record trades, notes, MT5 time, and post-trade review.',
      filePath: 'https://example.com/knowledge/journal-guide.pdf',
      approvedAt: DateTime.now().subtract(const Duration(days: 6)),
      createdAt: DateTime.now().subtract(const Duration(days: 6)),
    ),
  ];

  static const _readinessItems = <Map<String, Object>>[
    {
      'id': 'verification_profile_broker',
      'category': 'Account Verification',
      'category_i18n': {'zh': '账户验证'},
      'title': 'Profile and broker details are correct',
      'title_i18n': {'zh': '个人资料和经纪商资料正确'},
      'description':
          'Confirm your broker UID, email, Discord username, and verification documents all match.',
      'description_i18n': {'zh': '确认经纪商 UID、邮箱、Discord 用户名和验证文件全部一致。'},
      'why_it_matters':
          'Accurate identity details prevent delays with support, deposits, and withdrawals.',
      'why_it_matters_i18n': {'zh': '准确身份资料可避免客服、入金和出金延误。'},
      'suggested_action':
          'Review your information in Accounts before continuing.',
      'suggested_action_i18n': {'zh': '继续前请在账户页面检查你的资料。'},
      'is_core': true,
      'sort_order': 10,
    },
    {
      'id': 'risk_disclaimer_platform_rules',
      'category': 'Account Verification',
      'category_i18n': {'zh': '账户验证'},
      'title': 'Risk disclaimer and trading rules are understood',
      'title_i18n': {'zh': '已理解风险声明和交易规则'},
      'description':
          'Signals, classes, and analysis are educational support; every position remains your responsibility.',
      'description_i18n': {'zh': '信号、课程和分析只作教育辅助；每一笔仓位仍由你自己负责。'},
      'why_it_matters':
          'Deposit approval is not the same as trading readiness.',
      'why_it_matters_i18n': {'zh': '入金获批不代表已经准备好交易。'},
      'suggested_action':
          'Write down your maximum risk per trade and daily loss.',
      'suggested_action_i18n': {'zh': '写下每笔最大风险和每日最大亏损。'},
      'is_core': true,
      'sort_order': 20,
    },
    {
      'id': 'demo_first_plan',
      'category': 'Demo Practice',
      'category_i18n': {'zh': '模拟练习'},
      'title': 'Complete a demo practice plan first',
      'title_i18n': {'zh': '先完成模拟练习计划'},
      'description':
          'Record at least 20 planned demo trades with entry, exit, screenshots, and review notes.',
      'description_i18n': {'zh': '记录至少 20 笔有计划的模拟交易，包括进场、出场、截图和复盘备注。'},
      'why_it_matters':
          'Practice exposes execution mistakes without real-money pressure.',
      'why_it_matters_i18n': {'zh': '练习能在没有真钱压力下暴露执行错误。'},
      'suggested_action': 'Use the Journal tab for every practice trade.',
      'suggested_action_i18n': {'zh': '每笔练习交易都使用交易日记记录。'},
      'is_core': true,
      'sort_order': 30,
    },
    {
      'id': 'learning_materials_reviewed',
      'category': 'Learning Foundation',
      'category_i18n': {'zh': '学习基础'},
      'title': 'Core learning materials are reviewed',
      'title_i18n': {'zh': '已复习核心学习资料'},
      'description':
          'Review beginner knowledge resources and recording classes before taking live entries.',
      'description_i18n': {'zh': '实盘进场前先复习新手知识资源和录播课程。'},
      'why_it_matters': 'You need a shared trading vocabulary and process.',
      'why_it_matters_i18n': {'zh': '你需要建立一致的交易术语和流程。'},
      'suggested_action': 'Write three rules you learned from Trading Hub.',
      'suggested_action_i18n': {'zh': '写下从交易中心学到的三条规则。'},
      'is_core': true,
      'sort_order': 40,
    },
    {
      'id': 'risk_reward_ratio',
      'category': 'Risk Management',
      'category_i18n': {'zh': '风险管理'},
      'title': 'Risk/reward and break-even win rate are understood',
      'title_i18n': {'zh': '已理解风险回报和保本胜率'},
      'description':
          'Know how 1:1, 1:2, and 1:3 setups affect the win rate you need.',
      'description_i18n': {'zh': '了解 1:1、1:2 和 1:3 设置如何影响所需胜率。'},
      'why_it_matters': 'Win rate alone does not determine profitability.',
      'why_it_matters_i18n': {'zh': '单靠胜率不能决定盈利能力。'},
      'suggested_action':
          'Calculate break-even win rates before completing this item.',
      'suggested_action_i18n': {'zh': '完成此项前先计算保本胜率。'},
      'is_core': true,
      'sort_order': 50,
    },
    {
      'id': 'position_sizing_lot_size',
      'category': 'Risk Management',
      'category_i18n': {'zh': '风险管理'},
      'title': 'Position size and stop-loss risk are calculated',
      'title_i18n': {'zh': '已计算仓位大小和止损风险'},
      'description':
          'Use account balance, stop distance, pip value, and lot size to define money at risk.',
      'description_i18n': {'zh': '用账户余额、止损距离、点值和手数定义实际风险金额。'},
      'why_it_matters':
          'Oversized trades cause many avoidable beginner losses.',
      'why_it_matters_i18n': {'zh': '过大仓位会造成许多可避免的新手亏损。'},
      'suggested_action': 'Practice with a fixed 0.5%–1% maximum risk rule.',
      'suggested_action_i18n': {'zh': '用固定 0.5%–1% 最大风险规则练习。'},
      'is_core': true,
      'sort_order': 60,
    },
    {
      'id': 'stop_loss_take_profit_rules',
      'category': 'Risk Management',
      'category_i18n': {'zh': '风险管理'},
      'title': 'Stop loss, take profit, and no-chase rules are written',
      'title_i18n': {'zh': '已写下止损、止盈和不追价规则'},
      'description':
          'Define invalidation, profit-taking, and when a late entry must be skipped.',
      'description_i18n': {'zh': '定义失效条件、止盈方式，以及何时必须跳过迟到进场。'},
      'why_it_matters':
          'Written rules reduce emotional decisions after price moves.',
      'why_it_matters_i18n': {'zh': '书面规则可减少价格波动后的情绪化决定。'},
      'suggested_action':
          'Keep a one-page trade rule sheet beside your platform.',
      'suggested_action_i18n': {'zh': '在交易平台旁放一页交易规则表。'},
      'is_core': true,
      'sort_order': 70,
    },
    {
      'id': 'support_resistance_levels',
      'category': 'Technical Analysis',
      'category_i18n': {'zh': '技术分析'},
      'title': 'Key support and resistance areas can be identified',
      'title_i18n': {'zh': '能识别关键支撑和阻力区域'},
      'description':
          'Mark higher-timeframe levels, swing points, and reaction zones.',
      'description_i18n': {'zh': '标记高周期价位、摆动点和反应区。'},
      'why_it_matters':
          'Levels help avoid buying into resistance or selling into support.',
      'why_it_matters_i18n': {'zh': '关键价位能避免在阻力买入或在支撑卖出。'},
      'suggested_action': 'Mark and explain three clean levels on a chart.',
      'suggested_action_i18n': {'zh': '在图表上标记并解释三个清晰价位。'},
      'is_core': true,
      'sort_order': 80,
    },
    {
      'id': 'market_structure',
      'category': 'Technical Analysis',
      'category_i18n': {'zh': '技术分析'},
      'title': 'Trend, range, breakout, and reversal are separated',
      'title_i18n': {'zh': '能区分趋势、震荡、突破和反转'},
      'description':
          'Identify the current market type before choosing an entry strategy.',
      'description_i18n': {'zh': '选择进场策略前先识别当前市场类型。'},
      'why_it_matters': 'A trend strategy can fail badly inside a range.',
      'why_it_matters_i18n': {'zh': '趋势策略在震荡行情中可能严重失效。'},
      'suggested_action':
          'Classify five recent charts and explain the suitable strategy.',
      'suggested_action_i18n': {'zh': '分类五张近期图表，并说明适合的策略。'},
      'is_core': true,
      'sort_order': 90,
    },
    {
      'id': 'news_calendar_checked',
      'category': 'Trade Preparation',
      'category_i18n': {'zh': '交易准备'},
      'title': 'Economic news is checked before trading',
      'title_i18n': {'zh': '交易前已检查经济新闻'},
      'description':
          'Know when high-impact events could affect your pair and spread.',
      'description_i18n': {'zh': '了解高影响事件何时会影响交易品种和点差。'},
      'why_it_matters':
          'Volatility can invalidate otherwise reasonable setups.',
      'why_it_matters_i18n': {'zh': '剧烈波动可能让原本合理的设置失效。'},
      'suggested_action': 'Add a news check to your pre-trade routine.',
      'suggested_action_i18n': {'zh': '把新闻检查加入进场前流程。'},
      'is_core': true,
      'sort_order': 100,
    },
    {
      'id': 'execution_order_types',
      'category': 'Trade Preparation',
      'category_i18n': {'zh': '交易准备'},
      'title': 'Order types and platform execution are practised',
      'title_i18n': {'zh': '已练习订单类型和平台执行'},
      'description':
          'Practise market, limit, stop, modification, partial close, and full close.',
      'description_i18n': {'zh': '练习市价、限价、止损挂单、修改、部分平仓和全部平仓。'},
      'why_it_matters':
          'Platform hesitation becomes expensive during fast markets.',
      'why_it_matters_i18n': {'zh': '快速行情中，平台操作犹豫会变得很昂贵。'},
      'suggested_action': 'Complete each action safely on demo.',
      'suggested_action_i18n': {'zh': '在模拟账户安全完成每一种操作。'},
      'is_core': true,
      'sort_order': 110,
    },
    {
      'id': 'journal_review_routine',
      'category': 'Review Discipline',
      'category_i18n': {'zh': '复盘纪律'},
      'title': 'A weekly journal review routine is scheduled',
      'title_i18n': {'zh': '已安排每周交易日记复盘'},
      'description':
          'Review wins, losses, mistakes, emotions, and rule compliance weekly.',
      'description_i18n': {'zh': '每周复盘盈利、亏损、错误、情绪和规则执行。'},
      'why_it_matters': 'A journal only helps when its patterns are reviewed.',
      'why_it_matters_i18n': {'zh': '只有复盘模式，交易日记才真正有帮助。'},
      'suggested_action': 'Set a recurring weekly review time.',
      'suggested_action_i18n': {'zh': '设置固定的每周复盘时间。'},
      'is_core': false,
      'sort_order': 120,
    },
  ];
}

void _debugPublisherOperation(
  String operation, {
  required String stage,
  required String value,
}) {
  if (!kDebugMode) return;
  debugPrint('[$operation] $stage=$value');
}

void _debugPublisherFailure(
  String operation,
  FirebaseException error,
  StackTrace stackTrace,
) {
  if (!kDebugMode) return;
  debugPrint(
    '[$operation] firestoreWriteResult=failure '
    'plugin=${error.plugin} errorCode=${error.code}',
  );
  debugPrint('$stackTrace');
}

String _publishingFirebaseMessage(FirebaseException error, String contentType) {
  return switch (error.code.toLowerCase()) {
    'permission-denied' =>
      'Unable to publish the $contentType because your account permission could not be verified.',
    'unauthenticated' =>
      'Your sign-in session expired. Sign in again before publishing.',
    'unavailable' || 'network-request-failed' =>
      'Unable to reach Firebase. Check your connection and try again.',
    'not-found' => 'The required Firebase record could not be found.',
    _ => 'Unable to publish the $contentType. Please try again.',
  };
}

String _safeUidSuffix(String uid) {
  final trimmed = uid.trim();
  if (trimmed.length <= 4) return '***';
  return '***${trimmed.substring(trimmed.length - 4)}';
}

String _normalizeReferralCode(Object? value) {
  return _normalizeCompatibilityAscii(
    value?.toString() ?? '',
  ).replaceAll(RegExp(r'\s+'), '').toLowerCase();
}

String _normalizeCompatibilityAscii(String value) {
  return String.fromCharCodes(
    value.trim().runes.map((rune) {
      if (rune == 0x3000) return 0x20;
      if (rune >= 0xFF01 && rune <= 0xFF5E) return rune - 0xFEE0;
      return rune;
    }),
  );
}

String _recruitmentFunctionMessage(FirebaseCallableFailure error) {
  final message = error.message.trim();
  if (message.isNotEmpty) {
    return message;
  }
  return switch (error.code.toLowerCase()) {
    'invalid-argument' || 'not-found' => 'Invalid referral code',
    'already-exists' ||
    'failed-precondition' => 'This trader already has an upline',
    'permission-denied' =>
      'Your account does not have permission to complete this request.',
    'unauthenticated' => 'Sign in before completing this request.',
    _ => 'Unable to recruit downline. Please try again.',
  };
}

String _adminFunctionMessage(FirebaseFunctionsException error) {
  final message = error.message?.trim();
  if (message != null && message.isNotEmpty) return message;
  return switch (error.code.toLowerCase()) {
    'already-exists' => 'This account or username is already registered.',
    'invalid-argument' => 'Check the user details and try again.',
    'permission-denied' =>
      'Your account does not have permission to complete this request.',
    'unauthenticated' => 'Sign in before completing this request.',
    _ => 'Unable to complete the admin request. Please try again.',
  };
}

String _adminCallableMessage(FirebaseCallableFailure error) {
  final message = error.message.trim();
  if (message.isNotEmpty) return message;
  return switch (error.code.toLowerCase()) {
    'already-exists' => 'This account or username is already registered.',
    'invalid-argument' => 'Check the user details and try again.',
    'permission-denied' =>
      'Your account does not have permission to complete this request.',
    'unauthenticated' => 'Sign in before completing this request.',
    _ => 'Unable to complete the admin request. Please try again.',
  };
}

class _Mt5ImportedCapitalRecord {
  const _Mt5ImportedCapitalRecord({
    required this.batchId,
    required this.capital,
  });

  final String batchId;
  final CapitalTransaction capital;
}
