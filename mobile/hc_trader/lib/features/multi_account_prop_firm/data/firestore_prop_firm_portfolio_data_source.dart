import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';

import '../../../core/config/firestore_collections.dart';
import '../domain/account_daily_trade_limit_service.dart';
import '../domain/account_sequence_models.dart';
import '../domain/prop_firm_models.dart';
import 'portfolio_repository_snapshot.dart';

@visibleForTesting
bool wouldEraseExistingCloudAccounts({
  required int existingAccountCount,
  required int incomingAccountCount,
}) => existingAccountCount > 0 && incomingAccountCount == 0;

@visibleForTesting
Map<String, dynamic> accountSequenceModelJson(
  Map<String, dynamic> document, {
  int firestoreSchemaVersion = 4,
}) {
  final result = Map<String, dynamic>.from(document);
  final explicitSequenceSchema = result['sequence_schema_version'];
  if (explicitSequenceSchema != null) {
    result['schema_version'] = explicitSequenceSchema;
  } else if (!result.containsKey('firestore_schema_version') &&
      result['schema_version'] == firestoreSchemaVersion) {
    // Releases through 1.7.6 accidentally replaced the sequence model's
    // schema (1) with the Firestore document schema (4).
    result['schema_version'] = 1;
  }
  return result;
}

abstract interface class PropFirmPortfolioCloudDataSource {
  Stream<PortfolioRepositorySnapshot> watch();

  Future<PortfolioRepositorySnapshot> read();

  Future<void> save(
    PropFirmPortfolio portfolio,
    AccountSequenceWorkspaceState sequenceState,
  );

  Future<void> archiveWorkspace();

  Future<void> reserveAccountStart({
    required String accountId,
    required String requestId,
    required DateTime now,
  });

  Future<void> releaseAccountStart({required String requestId});
}

class FirestorePropFirmPortfolioDataSource
    implements PropFirmPortfolioCloudDataSource {
  FirestorePropFirmPortfolioDataSource({
    required this.ownerUid,
    FirebaseFirestore? firestore,
    FirebaseAuth? auth,
  }) : _firestore = firestore ?? FirebaseFirestore.instance,
       _auth = auth ?? FirebaseAuth.instance;

  static const schemaVersion = 4;
  static const workspaceDocumentId =
      FirestoreCollections.multipleAccountWorkspace;

  final String ownerUid;
  final FirebaseFirestore _firestore;
  final FirebaseAuth _auth;
  final AccountDailyTradeLimitService _dailyTradeLimits =
      const AccountDailyTradeLimitService();
  bool _migrationScheduled = false;

  DocumentReference<Map<String, dynamic>> get _workspace => _firestore
      .collection(FirestoreCollections.users)
      .doc(ownerUid)
      .collection(FirestoreCollections.multipleAccountStrategy)
      .doc(workspaceDocumentId);

  CollectionReference<Map<String, dynamic>> get _accounts =>
      _workspace.collection(FirestoreCollections.multipleAccountAccounts);
  DocumentReference<Map<String, dynamic>> get _sequence => _workspace
      .collection(FirestoreCollections.multipleAccountConfiguration)
      .doc('sequence');
  CollectionReference<Map<String, dynamic>> get _sessions =>
      _workspace.collection(FirestoreCollections.multipleAccountSessions);
  CollectionReference<Map<String, dynamic>> get _history =>
      _workspace.collection(FirestoreCollections.multipleAccountHistory);

  @override
  Stream<PortfolioRepositorySnapshot> watch() {
    late StreamController<PortfolioRepositorySnapshot> controller;
    StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>? workspaceSub;
    StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? accountsSub;
    StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>? sequenceSub;
    DocumentSnapshot<Map<String, dynamic>>? workspace;
    QuerySnapshot<Map<String, dynamic>>? accounts;
    DocumentSnapshot<Map<String, dynamic>>? sequence;

    void emit() {
      final workspaceSnapshot = workspace;
      final accountsSnapshot = accounts;
      final sequenceSnapshot = sequence;
      if (workspaceSnapshot == null ||
          accountsSnapshot == null ||
          sequenceSnapshot == null) {
        return;
      }
      try {
        final pending =
            workspaceSnapshot.metadata.hasPendingWrites ||
            accountsSnapshot.metadata.hasPendingWrites ||
            sequenceSnapshot.metadata.hasPendingWrites;
        final cached =
            workspaceSnapshot.metadata.isFromCache ||
            accountsSnapshot.metadata.isFromCache ||
            sequenceSnapshot.metadata.isFromCache;
        // Firestore is the source of truth for this feature. Ignore local SDK
        // cache and latency-compensation snapshots; the controller only
        // receives data acknowledged by the Firestore backend.
        if (pending || cached) return;
        controller.add(
          _decode(
            workspaceSnapshot,
            accountsSnapshot,
            sequenceSnapshot,
            syncState: PortfolioSyncState.synchronized,
          ),
        );
      } on Object catch (error, stackTrace) {
        debugPrint('Multiple-account Firestore decode failed: $error');
        controller.addError(error, stackTrace);
      }
    }

    controller = StreamController<PortfolioRepositorySnapshot>(
      onListen: () {
        workspaceSub = _workspace
            .snapshots(includeMetadataChanges: true)
            .listen((value) {
              workspace = value;
              emit();
            }, onError: controller.addError);
        accountsSub = _accounts.snapshots(includeMetadataChanges: true).listen((
          value,
        ) {
          accounts = value;
          emit();
        }, onError: controller.addError);
        sequenceSub = _sequence.snapshots(includeMetadataChanges: true).listen((
          value,
        ) {
          sequence = value;
          emit();
        }, onError: controller.addError);
      },
      onCancel: () async {
        await workspaceSub?.cancel();
        await accountsSub?.cancel();
        await sequenceSub?.cancel();
      },
    );
    return controller.stream;
  }

  @override
  Future<PortfolioRepositorySnapshot> read() async {
    _validateOwner();
    final values = await Future.wait<Object>([
      _workspace.get(const GetOptions(source: Source.server)),
      _accounts.get(const GetOptions(source: Source.server)),
      _sequence.get(const GetOptions(source: Source.server)),
    ]);
    return _decode(
      values[0] as DocumentSnapshot<Map<String, dynamic>>,
      values[1] as QuerySnapshot<Map<String, dynamic>>,
      values[2] as DocumentSnapshot<Map<String, dynamic>>,
      syncState: PortfolioSyncState.synchronized,
    );
  }

  @override
  Future<void> save(
    PropFirmPortfolio portfolio,
    AccountSequenceWorkspaceState sequenceState,
  ) async {
    _validateOwner();
    final existingAccounts = await _accounts.get(
      const GetOptions(source: Source.server),
    );
    if (wouldEraseExistingCloudAccounts(
      existingAccountCount: existingAccounts.docs.length,
      incomingAccountCount: portfolio.accounts.length,
    )) {
      throw FirebaseException(
        plugin: 'cloud_firestore',
        code: 'failed-precondition',
        message:
            'Firestore already contains strategy accounts. Refresh before '
            'saving an empty workspace.',
      );
    }
    final plan = sequenceState.plan;
    final sessionReference = plan?.confirmedAt == null
        ? null
        : _sessions.doc(plan!.id);
    final existingSession = sessionReference == null
        ? null
        : await sessionReference.get(const GetOptions(source: Source.server));
    final existingSnapshotIds =
        sessionReference != null && existingSession?.exists != true
        ? (await sessionReference
                  .collection('account_snapshots')
                  .get(const GetOptions(source: Source.server)))
              .docs
              .map((document) => document.id)
              .toSet()
        : const <String>{};
    final batch = _firestore.batch();
    final portfolioJson = Map<String, Object?>.from(portfolio.toJson())
      ..remove('accounts')
      ..['owner_uid'] = ownerUid
      ..['schema_version'] = schemaVersion
      ..['updated_at'] = FieldValue.serverTimestamp()
      ..['migration'] = {
        'completed_versions': [schemaVersion],
        'source': 'local_or_firestore_aggregate',
      };
    batch.set(
      _workspace,
      _firestoreMap(portfolioJson),
      SetOptions(merge: true),
    );

    final activeIds = <String>{};
    for (final account in portfolio.accounts) {
      activeIds.add(account.id);
      final dailyLimit = _dailyTradeLimits.evaluate(
        portfolio,
        account,
        now: portfolio.updatedAt,
      );
      final data =
          Map<String, Object?>.from(
              account.copyWith(ownerUid: ownerUid).toJson(),
            )
            ..['owner_uid'] = ownerUid
            ..['schema_version'] = schemaVersion
            ..['daily_trade_count'] = dailyLimit.currentTrades
            ..['daily_trade_count_key'] = dailyLimit.tradingDayKey
            ..['updated_at'] = FieldValue.serverTimestamp();
      batch.set(
        _accounts.doc(account.id),
        _firestoreMap(data),
        SetOptions(merge: true),
      );
    }
    for (final document in existingAccounts.docs) {
      if (!activeIds.contains(document.id)) {
        batch.set(document.reference, {
          'status': AccountStatus.archived.name,
          'archived_at': FieldValue.serverTimestamp(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
      }
    }

    batch.set(
      _sequence,
      _firestoreMap({
        ...sequenceState.toJson(),
        'owner_uid': ownerUid,
        'firestore_schema_version': schemaVersion,
        'updated_at': FieldValue.serverTimestamp(),
      }),
      SetOptions(merge: true),
    );

    if (sessionReference != null && plan != null) {
      final sessionResults = portfolio.results
          .where((result) => result.sessionId == plan.id)
          .toList(growable: false);
      batch.set(
        sessionReference,
        _firestoreMap({
          'id': plan.id,
          'owner_uid': ownerUid,
          'portfolio_id': portfolio.id,
          'status': portfolio.sessionStatus.name,
          'stop_reason': portfolio.stopReason?.toJson(),
          'sequence': plan.toJson(),
          'risk_policy': portfolio.riskPolicy.toJson(),
          'active_account_id': portfolio.activeAccountId,
          'recommended_next_account_id': plan.steps
              .where((step) => step.accountId != portfolio.activeAccountId)
              .firstOrNull
              ?.accountId,
          'accounts_used': sessionResults
              .map((result) => result.accountId)
              .toSet()
              .toList(growable: false),
          'result_count': sessionResults.length,
          'tp_count': sessionResults
              .where((result) => result.type == TradeResultType.fullTp)
              .length,
          'sl_count': sessionResults
              .where((result) => result.type == TradeResultType.fullSl)
              .length,
          'be_count': sessionResults
              .where((result) => result.type == TradeResultType.breakeven)
              .length,
          'total_profit_loss': sessionResults.fold<double>(
            0,
            (total, result) => total + result.amount,
          ),
          'started_at': plan.confirmedAt,
          'ended_at':
              {
                PortfolioSessionStatus.stopped,
                PortfolioSessionStatus.completed,
                PortfolioSessionStatus.cancelled,
              }.contains(portfolio.sessionStatus)
              ? portfolio.updatedAt
              : null,
          'starting_account_id': plan.selectedStartingAccountId,
          if (existingSession?.exists != true)
            'starting_combined_health_score': _combinedHealth(plan),
          if ({
            PortfolioSessionStatus.stopped,
            PortfolioSessionStatus.completed,
            PortfolioSessionStatus.cancelled,
          }.contains(portfolio.sessionStatus))
            'ending_combined_health_score': _combinedHealth(plan),
          'created_at': plan.confirmedAt,
          'updated_at': FieldValue.serverTimestamp(),
          'schema_version': schemaVersion,
        }),
        SetOptions(merge: true),
      );
      if (existingSession?.exists != true) {
        for (final account in portfolio.accounts) {
          if (existingSnapshotIds.contains(account.id)) continue;
          final evaluation = plan.evaluations[account.id];
          batch.set(
            sessionReference.collection('account_snapshots').doc(account.id),
            _firestoreMap({
              'id': account.id,
              'owner_uid': ownerUid,
              'raw_account': account.toJson(),
              'derived_evaluation': evaluation?.toJson(),
              'sequence_position': plan.steps
                  .where((step) => step.accountId == account.id)
                  .firstOrNull
                  ?.position,
              'exclusion_reasons': plan.exclusions
                  .where((item) => item.accountId == account.id)
                  .expand((item) => item.reasons)
                  .map((item) => item.toJson())
                  .toList(),
              'rule_configuration_version': schemaVersion,
              'created_at': FieldValue.serverTimestamp(),
            }),
          );
        }
      }
      for (final record in portfolio.disciplineRecords.reversed.take(100)) {
        batch.set(
          sessionReference.collection('events').doc(record.id),
          _firestoreMap({
            ...record.toJson(),
            'owner_uid': ownerUid,
            'schema_version': schemaVersion,
          }),
          SetOptions(merge: true),
        );
      }
      for (final result in sessionResults) {
        batch.set(
          sessionReference.collection('results').doc(result.id),
          _firestoreMap({
            ...result.toJson(),
            'owner_uid': ownerUid,
            'strategy_id': portfolio.id,
            'created_by': ownerUid,
            'schema_version': schemaVersion,
          }),
          SetOptions(merge: true),
        );
      }
      if ({
        PortfolioSessionStatus.stopped,
        PortfolioSessionStatus.completed,
        PortfolioSessionStatus.cancelled,
      }.contains(portfolio.sessionStatus)) {
        batch.set(
          _history.doc(plan.id),
          _firestoreMap({
            'id': plan.id,
            'owner_uid': ownerUid,
            'status': portfolio.sessionStatus.name,
            'stop_reason': portfolio.stopReason?.toJson(),
            'sequence': plan.toJson(),
            'completed_at': FieldValue.serverTimestamp(),
            'schema_version': schemaVersion,
          }),
          SetOptions(merge: true),
        );
      }
    }
    await batch.commit();
  }

  @override
  Future<void> reserveAccountStart({
    required String accountId,
    required String requestId,
    required DateTime now,
  }) async {
    _validateOwner();
    final accountReference = _accounts.doc(accountId);
    await _firestore.runTransaction((transaction) async {
      final workspaceSnapshot = await transaction.get(_workspace);
      final accountSnapshot = await transaction.get(accountReference);
      if (!workspaceSnapshot.exists || !accountSnapshot.exists) {
        throw FirebaseException(
          plugin: 'cloud_firestore',
          code: 'not-found',
          message: 'The selected account is no longer available.',
        );
      }
      final workspaceData = _jsonMap(workspaceSnapshot.data()!);
      final accountData = _jsonMap({
        ...accountSnapshot.data()!,
        'id': accountSnapshot.id,
      });
      if (workspaceData['session_active'] == true) {
        throw FirebaseException(
          plugin: 'cloud_firestore',
          code: 'session-already-active',
          message: 'Another trading session is already active.',
        );
      }
      final rawClaim = workspaceData['pending_start_claim'];
      if (rawClaim is Map) {
        final claim = Map<String, dynamic>.from(rawClaim);
        final claimId = '${claim['request_id'] ?? ''}';
        final createdAt = DateTime.tryParse('${claim['created_at'] ?? ''}');
        final stillActive =
            createdAt != null && now.difference(createdAt).inMinutes < 5;
        if (claimId != requestId && stillActive) {
          throw FirebaseException(
            plugin: 'cloud_firestore',
            code: 'session-already-active',
            message: 'Another device is starting a trading session.',
          );
        }
      }
      workspaceData['accounts'] = [accountData];
      final portfolio = PropFirmPortfolio.fromJson(workspaceData);
      final account = portfolio.accounts.single;
      final limit = _dailyTradeLimits.evaluate(portfolio, account, now: now);
      if (limit.hasReachedLimit) {
        throw FirebaseException(
          plugin: 'cloud_firestore',
          code: 'daily-trade-limit-reached',
          message:
              'This account has reached its daily trade limit '
              '(${limit.currentTrades}/${limit.limit}).',
        );
      }
      transaction.set(_workspace, {
        'pending_start_claim': {
          'request_id': requestId,
          'account_id': accountId,
          'trading_day_key': limit.tradingDayKey,
          'created_at': Timestamp.fromDate(now),
        },
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    });
  }

  @override
  Future<void> releaseAccountStart({required String requestId}) async {
    _validateOwner();
    await _firestore.runTransaction((transaction) async {
      final snapshot = await transaction.get(_workspace);
      if (!snapshot.exists) return;
      final rawClaim = snapshot.data()?['pending_start_claim'];
      if (rawClaim is Map && '${rawClaim['request_id'] ?? ''}' == requestId) {
        transaction.update(_workspace, {
          'pending_start_claim': FieldValue.delete(),
          'updated_at': FieldValue.serverTimestamp(),
        });
      }
    });
  }

  @override
  Future<void> archiveWorkspace() async {
    _validateOwner();
    final batch = _firestore.batch();
    final accounts = await _accounts.get(
      const GetOptions(source: Source.server),
    );
    batch.set(_workspace, {
      'owner_uid': ownerUid,
      'archived': true,
      'archived_at': FieldValue.serverTimestamp(),
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    for (final account in accounts.docs) {
      batch.set(account.reference, {
        'status': AccountStatus.archived.name,
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    }
    await batch.commit();
  }

  PortfolioRepositorySnapshot _decode(
    DocumentSnapshot<Map<String, dynamic>> workspace,
    QuerySnapshot<Map<String, dynamic>> accounts,
    DocumentSnapshot<Map<String, dynamic>> sequence, {
    required PortfolioSyncState syncState,
  }) {
    if (!workspace.exists) {
      return PortfolioRepositorySnapshot(
        portfolio: null,
        sequenceState: const AccountSequenceWorkspaceState(),
        syncState: syncState,
      );
    }
    final workspaceData = _jsonMap(workspace.data() ?? const {});
    final accountMaps =
        accounts.docs
            .map(
              (document) => _jsonMap({...document.data(), 'id': document.id}),
            )
            .toList()
          ..sort(
            (left, right) => '${left['created_at'] ?? ''}'.compareTo(
              '${right['created_at'] ?? ''}',
            ),
          );
    if (accountMaps.isNotEmpty) workspaceData['accounts'] = accountMaps;
    final portfolio = PropFirmPortfolio.fromJson(workspaceData);
    final sequenceState = sequence.exists
        ? AccountSequenceWorkspaceState.fromJson(
            accountSequenceModelJson(
              _jsonMap(sequence.data() ?? const {}),
              firestoreSchemaVersion: schemaVersion,
            ),
          )
        : const AccountSequenceWorkspaceState();
    final legacySchema = _intValue(workspaceData['schema_version'], 1);
    if (!_migrationScheduled &&
        (legacySchema < schemaVersion ||
            accounts.docs.isEmpty && portfolio.accounts.isNotEmpty)) {
      _migrationScheduled = true;
      unawaited(
        save(portfolio, sequenceState).catchError((Object error) {
          debugPrint('Multiple-account migration could not complete: $error');
        }),
      );
    }
    return PortfolioRepositorySnapshot(
      portfolio: portfolio,
      sequenceState: sequenceState,
      syncState: syncState,
      lastSynchronizedAt: portfolio.updatedAt,
    );
  }

  void _validateOwner() {
    final uid = _auth.currentUser?.uid;
    if (ownerUid.isEmpty || uid == null || uid != ownerUid) {
      throw StateError('Sign in again before saving this portfolio.');
    }
  }

  int _intValue(Object? value, int fallback) {
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? fallback;
  }

  double _combinedHealth(AccountSequencePlan plan) {
    if (plan.evaluations.isEmpty) return 0;
    return plan.evaluations.values.fold<double>(
          0,
          (total, evaluation) => total + evaluation.healthScore,
        ) /
        plan.evaluations.length;
  }

  Map<String, dynamic> _jsonMap(Map<String, dynamic> value) =>
      _convertFromFirestore(value) as Map<String, dynamic>;

  Object? _convertFromFirestore(Object? value) {
    if (value is Timestamp) return value.toDate().toIso8601String();
    if (value is Map) {
      return {
        for (final entry in value.entries)
          '${entry.key}': _convertFromFirestore(entry.value),
      };
    }
    if (value is Iterable) {
      return value.map(_convertFromFirestore).toList(growable: false);
    }
    return value;
  }

  Map<String, dynamic> _firestoreMap(Map<String, Object?> value) =>
      _convertToFirestore(value) as Map<String, dynamic>;

  Object? _convertToFirestore(Object? value) {
    if (value is FieldValue || value is Timestamp) return value;
    if (value is DateTime) return Timestamp.fromDate(value);
    if (value is String && RegExp(r'^\d{4}-\d{2}-\d{2}T').hasMatch(value)) {
      final date = DateTime.tryParse(value);
      if (date != null) return Timestamp.fromDate(date);
    }
    if (value is Map) {
      return {
        for (final entry in value.entries)
          '${entry.key}': _convertToFirestore(entry.value),
      };
    }
    if (value is Iterable) {
      return value.map(_convertToFirestore).toList(growable: false);
    }
    return value;
  }
}

extension<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
