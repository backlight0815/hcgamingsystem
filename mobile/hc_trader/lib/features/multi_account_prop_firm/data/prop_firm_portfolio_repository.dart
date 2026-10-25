import 'dart:async';
import 'dart:convert';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/foundation.dart';

import '../../../core/storage/local_app_storage.dart';
import '../domain/account_sequence_models.dart';
import '../domain/prop_firm_models.dart';
import 'firestore_prop_firm_portfolio_data_source.dart';
import 'portfolio_repository_snapshot.dart';

class PortfolioRepositoryException implements Exception {
  const PortfolioRepositoryException(this.message, {this.code, this.cause});

  final String message;
  final String? code;
  final Object? cause;

  @override
  String toString() => message;
}

class PropFirmPortfolioRepository {
  PropFirmPortfolioRepository({
    required this.storage,
    required this.ownerId,
    FirestorePropFirmPortfolioDataSource? cloudDataSource,
  }) : _cloudDataSource = cloudDataSource;

  factory PropFirmPortfolioRepository.cloud({required String ownerId}) =>
      PropFirmPortfolioRepository._cloud(
        ownerId: ownerId,
        cloudDataSource: FirestorePropFirmPortfolioDataSource(
          ownerUid: ownerId,
        ),
      );

  @visibleForTesting
  factory PropFirmPortfolioRepository.withCloudDataSource({
    required String ownerId,
    required PropFirmPortfolioCloudDataSource cloudDataSource,
  }) => PropFirmPortfolioRepository._cloud(
    ownerId: ownerId,
    cloudDataSource: cloudDataSource,
  );

  PropFirmPortfolioRepository._cloud({
    required this.ownerId,
    required PropFirmPortfolioCloudDataSource cloudDataSource,
  }) : storage = null,
       _cloudDataSource = cloudDataSource;

  static const storageKey = 'multi_account_prop_firm_portfolio_v1';
  static const sequenceStorageKey =
      'multi_account_prop_firm_sequence_workspace_v1';

  final LocalAppStorage? storage;
  final String ownerId;
  final PropFirmPortfolioCloudDataSource? _cloudDataSource;
  PropFirmPortfolio? _latestPortfolio;
  AccountSequenceWorkspaceState _latestSequenceState =
      const AccountSequenceWorkspaceState();
  String? _localStartRequestId;

  static final Map<String, StreamController<PortfolioRepositorySnapshot>>
  _localStreams = {};

  bool get usesCloud => _cloudDataSource != null;

  PropFirmPortfolio? load() {
    if (usesCloud) return null;
    final value = storage!.readMap(ownerId, storageKey);
    if (value == null) return null;
    try {
      final portfolio = PropFirmPortfolio.fromJson(value);
      _latestPortfolio = portfolio;
      return portfolio;
    } on Object {
      return null;
    }
  }

  PropFirmPortfolio? loadGuestPortfolio() {
    if (usesCloud) return null;
    if (ownerId == 'guest') return null;
    final value = storage!.readMap('guest', storageKey);
    if (value == null) return null;
    try {
      return PropFirmPortfolio.fromJson(value);
    } on Object {
      return null;
    }
  }

  Future<void> save(PropFirmPortfolio portfolio) =>
      saveWorkspace(portfolio, _latestSequenceState);

  AccountSequenceWorkspaceState loadSequenceState() {
    if (usesCloud) return const AccountSequenceWorkspaceState();
    final value = storage!.readMap(ownerId, sequenceStorageKey);
    if (value == null) return const AccountSequenceWorkspaceState();
    try {
      final state = AccountSequenceWorkspaceState.fromJson(value);
      _latestSequenceState = state;
      return state;
    } on Object {
      return const AccountSequenceWorkspaceState();
    }
  }

  AccountSequenceWorkspaceState? loadGuestSequenceState() {
    if (usesCloud) return null;
    if (ownerId == 'guest') return null;
    final value = storage!.readMap('guest', sequenceStorageKey);
    if (value == null) return null;
    try {
      return AccountSequenceWorkspaceState.fromJson(value);
    } on Object {
      return null;
    }
  }

  Future<void> saveSequenceState(AccountSequenceWorkspaceState state) async {
    _latestSequenceState = state;
    if (usesCloud) {
      final portfolio = _latestPortfolio;
      if (portfolio == null) {
        throw StateError('Load the Firestore workspace before saving.');
      }
      await saveWorkspace(portfolio, state);
      return;
    }
    final portfolio = _latestPortfolio ?? load();
    if (portfolio != null) {
      await saveWorkspace(portfolio, state);
    } else {
      await storage!.writeMap(ownerId, sequenceStorageKey, state.toJson());
      _emitLocal(null, state);
    }
  }

  Stream<PortfolioRepositorySnapshot> watch() {
    final cloud = _cloudDataSource;
    if (cloud != null) {
      return cloud.watch().map((snapshot) {
        _latestPortfolio = snapshot.portfolio;
        _latestSequenceState = snapshot.sequenceState;
        return snapshot;
      });
    }
    final stream = _localStreams.putIfAbsent(
      ownerId,
      () => StreamController<PortfolioRepositorySnapshot>.broadcast(sync: true),
    );
    return stream.stream;
  }

  Future<PortfolioRepositorySnapshot> read() async {
    final cloud = _cloudDataSource;
    if (cloud != null) return cloud.read();
    final portfolio = load();
    return PortfolioRepositorySnapshot(
      portfolio: portfolio,
      sequenceState: loadSequenceState(),
      syncState: PortfolioSyncState.synchronized,
      lastSynchronizedAt: portfolio?.updatedAt,
    );
  }

  Future<void> saveWorkspace(
    PropFirmPortfolio portfolio,
    AccountSequenceWorkspaceState state,
  ) async {
    _latestPortfolio = portfolio;
    _latestSequenceState = state;
    try {
      final cloud = _cloudDataSource;
      if (cloud != null) {
        await cloud.save(portfolio, state);
        return;
      }
      await storage!.writeMap(ownerId, storageKey, portfolio.toJson());
      await storage!.writeMap(ownerId, sequenceStorageKey, state.toJson());
      _emitLocal(portfolio, state);
    } on FirebaseException catch (error) {
      throw PortfolioRepositoryException(
        _firebaseMessage(error),
        code: error.code,
        cause: error,
      );
    }
  }

  Future<void> reserveAccountStart({
    required String accountId,
    required String requestId,
    required DateTime now,
  }) async {
    try {
      final cloud = _cloudDataSource;
      if (cloud != null) {
        await cloud.reserveAccountStart(
          accountId: accountId,
          requestId: requestId,
          now: now,
        );
        return;
      }
      if (_localStartRequestId != null && _localStartRequestId != requestId) {
        throw StateError('Another trading session is already starting.');
      }
      _localStartRequestId = requestId;
    } on FirebaseException catch (error) {
      throw PortfolioRepositoryException(
        _firebaseMessage(error),
        code: error.code,
        cause: error,
      );
    }
  }

  Future<void> releaseAccountStart({required String requestId}) async {
    try {
      final cloud = _cloudDataSource;
      if (cloud != null) {
        await cloud.releaseAccountStart(requestId: requestId);
      } else if (_localStartRequestId == requestId) {
        _localStartRequestId = null;
      }
    } on FirebaseException catch (error) {
      throw PortfolioRepositoryException(
        _firebaseMessage(error),
        code: error.code,
        cause: error,
      );
    }
  }

  Future<void> delete() async {
    try {
      final cloud = _cloudDataSource;
      if (cloud != null) {
        await cloud.archiveWorkspace();
        _latestPortfolio = null;
        _latestSequenceState = const AccountSequenceWorkspaceState();
        return;
      }
    } on FirebaseException catch (error) {
      throw PortfolioRepositoryException(
        _firebaseMessage(error),
        code: error.code,
        cause: error,
      );
    }
    await storage!.remove(ownerId, storageKey);
    await storage!.remove(ownerId, sequenceStorageKey);
    _latestPortfolio = null;
    _latestSequenceState = const AccountSequenceWorkspaceState();
    _emitLocal(null, _latestSequenceState);
  }

  String exportJson(PropFirmPortfolio portfolio) =>
      const JsonEncoder.withIndent('  ').convert(portfolio.toJson());

  PropFirmPortfolio importJson(String source) {
    if (source.length > 2 * 1024 * 1024) {
      throw const FormatException('The import is larger than 2 MB.');
    }
    final decoded = jsonDecode(source);
    if (decoded is! Map) {
      throw const FormatException('Portfolio JSON must contain an object.');
    }
    final portfolio = PropFirmPortfolio.fromJson(
      Map<String, dynamic>.from(decoded),
    );
    _validateImportedPortfolio(portfolio);
    return portfolio;
  }

  String exportCsv(PropFirmPortfolio portfolio) {
    final rows = <List<Object?>>[
      const [
        'result_id',
        'account_id',
        'account_name',
        'result_type',
        'amount',
        'r_multiple',
        'result_time',
        'followed_plan',
        'followed_rotation',
        'emotional_state',
        'notes',
      ],
      for (final result in portfolio.results)
        [
          result.id,
          result.accountId,
          portfolio.accounts
                  .where((account) => account.id == result.accountId)
                  .firstOrNull
                  ?.name ??
              '',
          result.type.name,
          result.amount,
          result.rMultiple,
          result.resultTime.toIso8601String(),
          result.followedPlan,
          result.followedRotation,
          result.emotionalState,
          result.notes,
        ],
    ];
    return rows.map((row) => row.map(_csvCell).join(',')).join('\r\n');
  }

  void _validateImportedPortfolio(PropFirmPortfolio portfolio) {
    if (portfolio.id.trim().isEmpty || portfolio.name.trim().isEmpty) {
      throw const FormatException('Portfolio identity is missing.');
    }
    if (!portfolio.virtualRiskBudget.isFinite ||
        portfolio.virtualRiskBudget < 0) {
      throw const FormatException('Virtual risk budget is invalid.');
    }
    final accountIds = <String>{};
    for (final account in portfolio.accounts) {
      if (account.id.isEmpty || !accountIds.add(account.id)) {
        throw const FormatException('Duplicate or empty account ID.');
      }
      final numbers = [
        account.accountSize,
        account.startingBalance,
        account.currentBalance,
        account.dailyLossLimit,
        account.maximumLossLimit,
        account.maxRiskPerTrade,
      ];
      if (numbers.any((value) => !value.isFinite) ||
          account.accountSize <= 0 ||
          account.startingBalance <= 0 ||
          account.dailyLossLimit <= 0 ||
          account.maximumLossLimit < account.dailyLossLimit ||
          account.maxRiskPerTrade <= 0 ||
          account.profitSplit < 0 ||
          account.profitSplit > 1) {
        throw FormatException('Invalid account settings for ${account.name}.');
      }
    }
    final tradeIds = <String>{};
    for (final trade in portfolio.plannedTrades) {
      if (trade.id.isEmpty || !tradeIds.add(trade.id)) {
        throw const FormatException('Duplicate or empty planned trade ID.');
      }
      if (!accountIds.contains(trade.accountId) ||
          !trade.riskAmount.isFinite ||
          trade.riskAmount < 0) {
        throw const FormatException('A planned trade is invalid.');
      }
    }
    final resultIds = <String>{};
    for (final result in portfolio.results) {
      if (result.id.isEmpty || !resultIds.add(result.id)) {
        throw const FormatException('Duplicate or empty result ID.');
      }
      if (!accountIds.contains(result.accountId) ||
          !result.amount.isFinite ||
          !result.rMultiple.isFinite) {
        throw const FormatException('A recorded result is invalid.');
      }
    }
  }

  static String _csvCell(Object? value) {
    final text = '${value ?? ''}'.replaceAll('"', '""');
    return '"$text"';
  }

  void _emitLocal(
    PropFirmPortfolio? portfolio,
    AccountSequenceWorkspaceState sequence,
  ) {
    _localStreams[ownerId]?.add(
      PortfolioRepositorySnapshot(
        portfolio: portfolio,
        sequenceState: sequence,
        syncState: PortfolioSyncState.synchronized,
        lastSynchronizedAt: portfolio?.updatedAt,
      ),
    );
  }

  String _firebaseMessage(FirebaseException error) => switch (error.code) {
    'permission-denied' =>
      'You do not have permission to save this portfolio. Sign in again.',
    'unavailable' =>
      'Firestore is unavailable. Nothing was saved; reconnect and retry.',
    'unauthenticated' => 'Sign in again before saving this portfolio.',
    'deadline-exceeded' =>
      'Saving is taking longer than expected. Check your connection and retry.',
    'failed-precondition' =>
      'Firestore already contains account data. Refresh before saving.',
    'session-already-active' =>
      'Another device is already starting or trading this session.',
    'daily-trade-limit-reached' =>
      'This account has reached its daily trade limit. Choose another account.',
    'not-found' => 'The selected account is no longer available. Refresh.',
    _ => 'The portfolio could not be saved. Please retry.',
  };
}
