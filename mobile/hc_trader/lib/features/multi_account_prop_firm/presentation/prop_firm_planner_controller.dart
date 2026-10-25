import 'dart:async';
import 'dart:math' as math;

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/foundation.dart';

import '../data/prop_firm_portfolio_repository.dart';
import '../data/portfolio_repository_snapshot.dart';
import '../domain/account_daily_trade_limit_service.dart';
import '../domain/account_health_decision_service.dart';
import '../domain/account_health_score_service.dart';
import '../domain/account_rotation_service.dart';
import '../domain/account_sequence_models.dart';
import '../domain/account_sequence_service.dart';
import '../domain/account_validation_service.dart';
import '../domain/prop_firm_calculation_service.dart';
import '../domain/prop_firm_models.dart';
import '../domain/portfolio_dashboard_service.dart';
import '../domain/session_stop_condition_service.dart';

class SimplifiedTradeResultPreview {
  const SimplifiedTradeResultPreview({
    required this.resultId,
    required this.account,
    required this.type,
    required this.enteredAmount,
    required this.normalizedAmount,
    required this.balanceAfter,
    required this.equityAfter,
    required this.healthBefore,
    required this.healthAfter,
    required this.dailyLossRemainingAfter,
    required this.maximumLossRemainingAfter,
    required this.resultTime,
    this.notes = '',
    this.tradeReference = '',
  });

  final String resultId;
  final PropFirmAccount account;
  final TradeResultType type;
  final double enteredAmount;
  final double normalizedAmount;
  final double balanceAfter;
  final double equityAfter;
  final double healthBefore;
  final double healthAfter;
  final double dailyLossRemainingAfter;
  final double maximumLossRemainingAfter;
  final DateTime resultTime;
  final String notes;
  final String tradeReference;
}

@visibleForTesting
bool shouldPreserveLocalPortfolioSnapshot({
  required bool hasUnsynchronizedChanges,
  required DateTime? localUpdatedAt,
  required DateTime? remoteUpdatedAt,
}) {
  if (!hasUnsynchronizedChanges || localUpdatedAt == null) return false;
  if (remoteUpdatedAt == null) return true;
  return remoteUpdatedAt.isBefore(localUpdatedAt);
}

@visibleForTesting
PropFirmPortfolio migrateLegacyMaximumTradeStop(
  PropFirmPortfolio portfolio, {
  required DateTime now,
}) {
  if (portfolio.stopReason?.code != SessionStopReasonCode.maximumTrades) {
    return portfolio;
  }
  return portfolio.copyWith(
    accounts: [
      for (final account in portfolio.accounts)
        account.status == AccountStatus.portfolioLocked
            ? account.copyWith(status: AccountStatus.available)
            : account,
    ],
    sessionActive: false,
    sessionStatus: PortfolioSessionStatus.ready,
    portfolioLocked: false,
    clearStopReason: true,
    clearActiveAccount: true,
    updatedAt: now,
  );
}

class PropFirmPlannerController extends ChangeNotifier {
  PropFirmPlannerController({
    required this.repository,
    DateTime Function()? now,
    this.calculations = const PropFirmCalculationService(),
    this.rotation = const AccountRotationService(),
    this.healthDecisions = const PortfolioHealthDecisionService(),
    this.healthScoreService = const AccountHealthScoreService(),
    this.accountValidation = const AccountValidationService(),
    this.sequences = const AccountSequenceService(),
    this.dashboardService = const PortfolioDashboardService(),
    this.stopConditions = const SessionStopConditionService(),
    this.dailyTradeLimits = const AccountDailyTradeLimitService(),
  }) : _now = now ?? DateTime.now;

  final PropFirmPortfolioRepository repository;
  final DateTime Function() _now;
  final PropFirmCalculationService calculations;
  final AccountRotationService rotation;
  final PortfolioHealthDecisionService healthDecisions;
  final AccountHealthScoreService healthScoreService;
  final AccountValidationService accountValidation;
  final AccountSequenceService sequences;
  final PortfolioDashboardService dashboardService;
  final SessionStopConditionService stopConditions;
  final AccountDailyTradeLimitService dailyTradeLimits;

  PropFirmPortfolio? _portfolio;
  AccountSequenceWorkspaceState _sequenceState =
      const AccountSequenceWorkspaceState();
  String? _error;
  var _busy = true;
  PortfolioSyncState _syncState = PortfolioSyncState.synchronized;
  DateTime? _lastSynchronizedAt;
  PortfolioRepositorySnapshot? _lastCloudSnapshot;
  StreamSubscription<PortfolioRepositorySnapshot>? _repositorySubscription;
  Future<void> Function()? _retryAction;
  bool _recoveringRepositoryStream = false;
  String? _syncErrorCode;
  bool _legacyMaximumTradeMigrationPending = false;
  int _startRequestSerial = 0;

  PropFirmPortfolio? get portfolio => _portfolio;
  String? get error => _error;
  bool get busy => _busy;
  DateTime get currentTime => _now();
  PortfolioSyncState get syncState => _syncState;
  DateTime? get lastSynchronizedAt => _lastSynchronizedAt;
  bool get canRetry => _retryAction != null;
  String? get syncErrorCode => _syncErrorCode;
  bool get hasUnsynchronizedChanges {
    final current = _portfolio;
    final synchronizedAt = _lastSynchronizedAt;
    if (current == null) return false;
    if (synchronizedAt == null) return true;
    return current.updatedAt.isAfter(synchronizedAt);
  }

  /// Re-evaluates countdown-driven getters when a cooldown reaches zero.
  /// Elapsed time does not require a portfolio mutation or Firestore write.
  void refreshTimeSensitiveState() => notifyListeners();

  AccountSequenceWorkspaceState get sequenceState => _sequenceState;

  PortfolioDashboardSnapshot? get dashboardSnapshot => _portfolio == null
      ? null
      : dashboardService.calculate(_portfolio!, now: _now());

  RecommendationResult get recommendation => _portfolio == null
      ? const RecommendationResult(
          reasons: [],
          exclusions: {},
          shouldStop: true,
        )
      : rotation.recommend(_portfolio!, now: _now());

  PortfolioRecommendation? get healthRecommendation => _portfolio == null
      ? null
      : healthDecisions.recommend(_portfolio!, now: _now());

  Map<String, AccountHealthAssessment> get healthAssessments =>
      healthRecommendation?.accountAssessments ?? const {};

  Map<String, AccountHealthScore> get simplifiedHealthScores {
    final current = _portfolio;
    if (current == null) return const {};
    final now = _now();
    return {
      for (final account in current.accounts)
        account.id: healthScoreService.calculate(account, now: now),
    };
  }

  Map<String, AccountDailyTradeLimitResult> get accountDailyTradeLimits {
    final current = _portfolio;
    if (current == null) return const {};
    final now = _now();
    return {
      for (final account in current.accounts)
        account.id: dailyTradeLimits.evaluate(current, account, now: now),
    };
  }

  bool get hasOpenTradingSession {
    final current = _portfolio;
    return current?.activeSessionId != null &&
        !{
          PortfolioSessionStatus.stopped,
          PortfolioSessionStatus.completed,
          PortfolioSessionStatus.cancelled,
        }.contains(current?.sessionStatus);
  }

  /// A fresh plan for the simplified start flow. Persisted plans are retained
  /// for audit history, but must not make a Start button look available after
  /// today's portfolio safeguards have changed.
  AccountSequencePlan? get simplifiedStartPlan {
    final current = _portfolio;
    if (current == null) return null;
    return sequences.generateSequence(
      portfolio: current,
      settings: _sequenceState.settings,
      now: _now(),
      previousPlan: _sequenceState.plan,
    );
  }

  bool get canStartSimplifiedSession {
    final current = _portfolio;
    final plan = simplifiedStartPlan;
    return current != null &&
        !current.sessionActive &&
        current.activeAccounts.isNotEmpty &&
        plan != null &&
        !plan.portfolioStopped &&
        plan.steps.isNotEmpty;
  }

  String? get simplifiedRecommendedAccountId {
    final plan = simplifiedStartPlan;
    if (plan == null || plan.portfolioStopped) return null;
    return plan.steps
        .where((step) => step.status != SequenceStepStatus.completed)
        .map((step) => step.accountId)
        .where(
          (id) =>
              id != _portfolio?.activeAccountId &&
              plan.evaluations[id]?.isEligible == true,
        )
        .firstOrNull;
  }

  List<RecordedTradeResult> get currentSessionResults {
    final sessionId = _portfolio?.activeSessionId;
    if (sessionId == null) return const [];
    return _portfolio!.results
        .where((result) => result.sessionId == sessionId)
        .toList(growable: false);
  }

  AccountSequencePlan? get sequencePlan {
    final current = _portfolio;
    if (current == null) return null;
    return _sequenceState.plan ??
        sequences.generateSequence(
          portfolio: current,
          settings: _sequenceState.settings,
          now: _now(),
        );
  }

  Future<void> initialize() async {
    try {
      if (repository.usesCloud) {
        try {
          _applyRepositorySnapshot(await repository.read(), notify: false);
        } on Object catch (error) {
          _portfolio = null;
          _sequenceState = const AccountSequenceWorkspaceState();
          _error = '$error';
          _syncErrorCode = error is FirebaseException ? error.code : 'read';
          _syncState = PortfolioSyncState.failed;
        }
      } else {
        final cachedPortfolio = repository.load();
        final cachedSequence = repository.loadSequenceState();
        _applyRepositorySnapshot(
          PortfolioRepositorySnapshot(
            portfolio: cachedPortfolio,
            sequenceState: cachedSequence,
            syncState: PortfolioSyncState.synchronized,
            lastSynchronizedAt: cachedPortfolio?.updatedAt,
          ),
          notify: false,
        );
        if (_portfolio == null) {
          final guestPortfolio = repository.loadGuestPortfolio();
          if (guestPortfolio != null) {
            _portfolio = guestPortfolio.copyWith(updatedAt: _now());
            await repository.saveWorkspace(_portfolio!, _sequenceState);
            final guestSequence = repository.loadGuestSequenceState();
            if (guestSequence != null) {
              _sequenceState = guestSequence.copyWith(updatedAt: _now());
              await repository.saveWorkspace(_portfolio!, _sequenceState);
            }
          }
        }
      }
      if (_portfolio != null) {
        await _rollTradingDayIfNeeded();
        await _rollAccountTradingDaysIfNeeded();
        if (_legacyMaximumTradeMigrationPending) {
          _legacyMaximumTradeMigrationPending = false;
          await _persist();
        }
      }
      await _restartRepositorySubscription();
    } on Object catch (exception) {
      _error = '$exception';
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  void _applyRepositorySnapshot(
    PortfolioRepositorySnapshot snapshot, {
    bool notify = true,
  }) {
    if (repository.usesCloud &&
        snapshot.syncState == PortfolioSyncState.synchronized) {
      _lastCloudSnapshot = snapshot;
    }
    final local = _portfolio;
    if (!repository.usesCloud &&
        shouldPreserveLocalPortfolioSnapshot(
          hasUnsynchronizedChanges: hasUnsynchronizedChanges,
          localUpdatedAt: local?.updatedAt,
          remoteUpdatedAt: snapshot.portfolio?.updatedAt,
        )) {
      final synchronizedAt = snapshot.lastSynchronizedAt;
      if (synchronizedAt != null &&
          (_lastSynchronizedAt == null ||
              synchronizedAt.isAfter(_lastSynchronizedAt!))) {
        _lastSynchronizedAt = synchronizedAt;
      }
      if (notify) notifyListeners();
      return;
    }
    final incoming = snapshot.portfolio;
    final migrated = incoming == null
        ? null
        : migrateLegacyMaximumTradeStop(incoming, now: _now());
    _legacyMaximumTradeMigrationPending =
        incoming != null && !identical(incoming, migrated);
    _portfolio = migrated;
    _sequenceState = snapshot.sequenceState;
    _syncState = snapshot.syncState;
    _lastSynchronizedAt = snapshot.lastSynchronizedAt;
    _error = snapshot.errorMessage;
    if (snapshot.syncState == PortfolioSyncState.synchronized) {
      _retryAction = null;
      _syncErrorCode = null;
    }
    if (notify) notifyListeners();
  }

  Future<void> retryLastSave() async {
    final retry = _retryAction;
    if (retry == null) return;
    _syncState = PortfolioSyncState.saving;
    notifyListeners();
    try {
      await retry();
      _syncState = PortfolioSyncState.synchronized;
      _lastSynchronizedAt = _portfolio?.updatedAt;
      _error = null;
      _syncErrorCode = null;
      _retryAction = null;
      await _restartRepositorySubscription();
    } on PortfolioRepositoryException catch (error) {
      _error = error.message;
      _syncErrorCode = error.code;
      _syncState = {'unavailable', 'deadline-exceeded'}.contains(error.code)
          ? PortfolioSyncState.pending
          : PortfolioSyncState.failed;
      rethrow;
    } finally {
      notifyListeners();
    }
  }

  Future<void> _restartRepositorySubscription() async {
    await _repositorySubscription?.cancel();
    _repositorySubscription = repository.watch().listen(
      _applyRepositorySnapshot,
      onError: (Object error, StackTrace stackTrace) {
        _error = '$error';
        _syncErrorCode = error is FirebaseException ? error.code : 'stream';
        _syncState = PortfolioSyncState.failed;
        notifyListeners();
        unawaited(_recoverRepositoryStream());
      },
    );
  }

  Future<void> _recoverRepositoryStream() async {
    if (_recoveringRepositoryStream) return;
    _recoveringRepositoryStream = true;
    final preserveLocalDraft = hasUnsynchronizedChanges;
    try {
      final snapshot = await repository.read();
      if (preserveLocalDraft) {
        _lastSynchronizedAt = snapshot.lastSynchronizedAt;
      } else {
        _applyRepositorySnapshot(snapshot, notify: false);
        _error = snapshot.errorMessage;
        _syncErrorCode = null;
      }
      await _restartRepositorySubscription();
    } on Object {
      // Keep the actionable failed/pending state. A user retry or the next
      // screen initialization can recover when connectivity/auth is restored.
    } finally {
      _recoveringRepositoryStream = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    unawaited(_repositorySubscription?.cancel());
    super.dispose();
  }

  Future<void> createStarterPortfolio({
    required double virtualRiskBudget,
    int accountCount = 3,
    String accountNamePrefix = 'Account',
    String portfolioName = 'Funded Account Rotation Plan',
  }) async {
    if (!virtualRiskBudget.isFinite || virtualRiskBudget <= 0) {
      throw const FormatException('Virtual risk budget must be above zero.');
    }
    if (accountCount < 1 || accountCount > 20) {
      throw const FormatException('Choose between 1 and 20 accounts.');
    }
    final now = _now();
    final allocation = virtualRiskBudget / accountCount;
    final presets = [
      PropFirmRulePreset(
        id: 'the5ers',
        firmName: 'The5ers',
        minimumPayout: 150,
        updatedAt: now,
      ),
      PropFirmRulePreset(
        id: 'alpha-capital-group',
        firmName: 'Alpha Capital Group',
        minimumPayout: 100,
        updatedAt: now,
      ),
    ];
    final accounts = List.generate(accountCount, (index) {
      final preset = presets[index % presets.length];
      return PropFirmAccount(
        id: _id('account', now, index),
        ownerUid: repository.ownerId == 'guest' ? '' : repository.ownerId,
        name: '$accountNamePrefix ${String.fromCharCode(65 + index)}',
        propFirmName: preset.firmName,
        identifier: 'Planner ${index + 1}',
        accountSize: 10000,
        startingBalance: 10000,
        currentBalance: 10000,
        dailyLossLimit: 500,
        maximumLossLimit: 1000,
        profitTarget: 800,
        minimumPayout: preset.minimumPayout,
        desiredPayout: 500,
        minimumTradingDays: 3,
        maxRiskPerTrade: 100,
        virtualRiskAllocation: allocation,
        colorValue: _accountColors[index % _accountColors.length],
        situation: AccountCurrentSituation(
          maximumLossAmount: 1000,
          dailyLossAmount: 500,
          dailyStartingBalance: 10000,
          currentPayoutEligibleProfit: 0,
          updatedAt: now,
        ),
        createdAt: now,
        updatedAt: now,
      );
    });
    final dailyRisk = math.min(
      virtualRiskBudget,
      accounts.first.maxRiskPerTrade * 2,
    );
    _portfolio = PropFirmPortfolio(
      id: _id('portfolio', now),
      name: portfolioName,
      virtualRiskBudget: virtualRiskBudget,
      acknowledged: true,
      accounts: accounts,
      riskPolicy: PortfolioRiskPolicy(
        maximumDailyLoss: dailyRisk,
        maximumRiskPerTrade: accounts.first.maxRiskPerTrade,
      ),
      strategy: RotationStrategy.forTemplate(RotationTemplate.balanced),
      plannedTrades: const [],
      results: const [],
      disciplineRecords: const [],
      transitions: const [],
      firmPresets: presets,
      sessionDateKey: calculations.tradingDayKey(
        now,
        PortfolioRiskPolicy(
          maximumDailyLoss: dailyRisk,
          maximumRiskPerTrade: accounts.first.maxRiskPerTrade,
        ),
      ),
      createdAt: now,
      updatedAt: now,
    );
    _sequenceState = const AccountSequenceWorkspaceState();
    await _persist();
  }

  Future<void> createSimplifiedStrategy({
    String name = 'Multiple Account Strategy',
  }) async {
    if (repository.usesCloud) {
      final remote = await repository.read();
      if (remote.portfolio != null) {
        _applyRepositorySnapshot(remote);
        return;
      }
    }
    final now = _now();
    final policy = const PortfolioRiskPolicy(
      maximumDailyLoss: 500,
      maximumRiskPerTrade: 100,
      resetTimeZone: 'UTC',
    );
    _portfolio = PropFirmPortfolio(
      id: _id('strategy', now),
      name: name,
      virtualRiskBudget: 0,
      acknowledged: true,
      accounts: const [],
      riskPolicy: policy,
      strategy: const RotationStrategy(),
      plannedTrades: const [],
      results: const [],
      disciplineRecords: const [],
      transitions: const [],
      firmPresets: const [],
      sessionDateKey: calculations.tradingDayKey(now, policy),
      createdAt: now,
      updatedAt: now,
    );
    _sequenceState = const AccountSequenceWorkspaceState();
    await _persist(recalculateSequence: false);
  }

  Future<void> saveAccount(PropFirmAccount account) async {
    final now = _now();
    final expectedRevision = account.updatedAt;
    account = account.copyWith(
      ownerUid: repository.ownerId == 'guest' ? '' : repository.ownerId,
      updatedAt: now,
    );
    _validateAccount(account);
    final current = _requirePortfolio();
    final index = current.accounts.indexWhere((item) => item.id == account.id);
    final accounts = [...current.accounts];
    final records = [...current.disciplineRecords];
    if (index < 0) {
      accounts.add(account);
    } else {
      final before = accounts[index];
      if (expectedRevision.isBefore(before.updatedAt)) {
        throw StateError(
          'This account changed on another screen. Review the latest values '
          'before saving again.',
        );
      }
      final situationChanges = _situationDiff(
        before.situation,
        account.situation,
      );
      if (situationChanges != 'no_changes' ||
          before.currentBalance != account.currentBalance ||
          before.manuallyEnteredEquity != account.manuallyEnteredEquity) {
        records.add(
          DisciplineRecord(
            id: _id('snapshot', now, records.length),
            type: 'account_situation_updated',
            detail: '${account.id}:balance,equity,$situationChanges',
            createdAt: now,
          ),
        );
      }
      accounts[index] = account;
    }
    _portfolio = current.copyWith(
      accounts: accounts,
      disciplineRecords: records,
      updatedAt: _now(),
    );
    await _persist();
  }

  PropFirmAccount newAccountDraft({String? name, String? firmName}) {
    final current = _requirePortfolio();
    final now = _now();
    return PropFirmAccount(
      id: _id('account', now, current.accounts.length),
      ownerUid: repository.ownerId == 'guest' ? '' : repository.ownerId,
      name: name ?? 'Account ${current.accounts.length + 1}',
      propFirmName: firmName ?? 'Custom firm',
      accountSize: 10000,
      startingBalance: 10000,
      currentBalance: 10000,
      dailyLossLimit: 500,
      maximumLossLimit: 1000,
      profitTarget: 800,
      minimumPayout: 100,
      desiredPayout: 500,
      maxRiskPerTrade: current.riskPolicy.maximumRiskPerTrade,
      virtualRiskAllocation:
          current.virtualRiskBudget / math.max(1, current.accounts.length + 1),
      colorValue:
          _accountColors[current.accounts.length % _accountColors.length],
      situation: AccountCurrentSituation(
        maximumLossAmount: 1000,
        dailyLossAmount: 500,
        dailyStartingBalance: 10000,
        currentPayoutEligibleProfit: 0,
        updatedAt: now,
      ),
      createdAt: now,
      updatedAt: now,
    );
  }

  Future<void> saveAccountSituation(
    String accountId,
    AccountCurrentSituation situation,
  ) async {
    _validateSituation(situation);
    final current = _requirePortfolio();
    final index = current.accounts.indexWhere((item) => item.id == accountId);
    if (index < 0) throw const FormatException('Select a valid account.');
    final now = _now();
    final before = current.accounts[index].situation;
    final accounts = [...current.accounts];
    accounts[index] = accounts[index].copyWith(
      situation: situation.copyWith(updatedAt: now),
    );
    _portfolio = current.copyWith(
      accounts: accounts,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('situation', now, current.disciplineRecords.length),
          type: 'account_situation_updated',
          detail: '$accountId:${_situationDiff(before, situation)}',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> completeCurrentSituationAssessment(
    Map<String, PropFirmAccount> assessedAccounts, {
    Set<String>? reviewedAccountIds,
  }) async {
    final current = _requirePortfolio();
    if (current.activeAccounts.any(
      (account) => !assessedAccounts.containsKey(account.id),
    )) {
      throw const FormatException('Complete every active account assessment.');
    }
    final reviewedIds = reviewedAccountIds ?? assessedAccounts.keys.toSet();
    if (reviewedIds.any((id) => !assessedAccounts.containsKey(id))) {
      throw const FormatException('Select a valid account for review.');
    }
    for (final account in assessedAccounts.values.where(
      (account) => reviewedIds.contains(account.id),
    )) {
      _validateAccount(account);
    }
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (assessedAccounts[account.id] case final assessed?)
            assessed.copyWith(
              situation: assessed.situation.copyWith(updatedAt: now),
            )
          else
            account,
      ],
      currentSituationAssessed: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('assessment', now, current.disciplineRecords.length),
          type: 'current_situation_assessment_completed',
          detail: 'accounts:${assessedAccounts.length}',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> overrideAccountHealthStatus(
    String accountId,
    ManualAccountHealthStatus status,
    String reason,
  ) async {
    if (reason.trim().length < 5) {
      throw const FormatException('Provide a clear override reason.');
    }
    final current = _requirePortfolio();
    if (!current.accounts.any((account) => account.id == accountId)) {
      throw const FormatException('Select a valid account.');
    }
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (account.id == accountId)
            account.copyWith(
              situation: account.situation.copyWith(
                manualStatus: status,
                updatedAt: now,
              ),
            )
          else
            account,
      ],
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('health-override', now, current.disciplineRecords.length),
          type: 'account_health_override',
          detail: '$accountId:${status.name}',
          createdAt: now,
          overrideReason: reason.trim(),
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> refreshHealthAssessment() async {
    await _rollTradingDayIfNeeded();
    await _rollAccountTradingDaysIfNeeded();
    notifyListeners();
  }

  Future<void> recalculateSequence() async {
    final current = _requirePortfolio();
    final now = _now();
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: _sequenceState.settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(plan: plan, updatedAt: now);
    await _persist(recalculateSequence: false);
  }

  Future<void> selectSequenceStartingAccount(String? accountId) async {
    final current = _requirePortfolio();
    final selected = accountId ?? '';
    if (selected.isNotEmpty &&
        !current.activeAccounts.any((account) => account.id == selected)) {
      throw const FormatException('Select a valid starting account.');
    }
    final now = _now();
    final settings = _sequenceState.settings.copyWith(
      selectedStartingAccountId: selected,
    );
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> updateSequenceSettings(AccountSequenceSettings settings) async {
    if (!settings.tieThreshold.isFinite ||
        settings.tieThreshold < 0 ||
        settings.tieThreshold > 25 ||
        !settings.minimumPlannedRisk.isFinite ||
        settings.minimumPlannedRisk < 0) {
      throw const FormatException('Sequence settings are invalid.');
    }
    final current = _requirePortfolio();
    final now = _now();
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  SequenceValidationResult validateCustomSequence(List<String> accountIds) =>
      sequences.validateCustomOrder(
        portfolio: _requirePortfolio(),
        settings: _sequenceState.settings,
        accountIds: accountIds,
        now: _now(),
      );

  Future<void> saveCustomSequence({
    required List<String> accountIds,
    required Map<String, SequenceAccountRole> roles,
    String overrideReason = '',
  }) async {
    final validation = validateCustomSequence(accountIds);
    if (!validation.canSave) {
      throw StateError(
        validation.blockingReasons.map((item) => item.messageKey).join(', '),
      );
    }
    if (validation.requiresOverride && overrideReason.trim().length < 5) {
      throw const FormatException(
        'A higher-risk custom sequence requires a recorded reason.',
      );
    }
    final current = _requirePortfolio();
    final now = _now();
    final originalOrder =
        _sequenceState.plan?.steps.map((step) => step.accountId).toList() ??
        const <String>[];
    final overrides = {..._sequenceState.settings.overrideReasons};
    if (overrideReason.trim().length >= 5) {
      for (final accountId in accountIds) {
        final currentEvaluation = sequencePlan?.evaluations[accountId];
        if (currentEvaluation?.exclusionReasons.any(
              (item) => item.messageKey == 'sequence.exclusion.payoutProtected',
            ) ==
            true) {
          overrides[accountId] = overrideReason.trim();
        }
      }
    }
    final settings = _sequenceState.settings.copyWith(
      selectedStartingAccountId: accountIds.first,
      mode: SequenceMode.custom,
      customOrder: accountIds,
      roles: roles,
      overrideReasons: overrides,
      oneAccountOnly: false,
    );
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    if (validation.requiresOverride) {
      _portfolio = current.copyWith(
        disciplineRecords: [
          ...current.disciplineRecords,
          DisciplineRecord(
            id: _id('sequence-override', now, current.disciplineRecords.length),
            type: 'sequence_order_override',
            detail:
                'original=${originalOrder.join(',')};'
                'final=${accountIds.join(',')};'
                'moves=${accountIds.indexed.map((entry) {
                  final previous = originalOrder.indexOf(entry.$2);
                  final status = plan.evaluations[entry.$2]?.healthStatus.name ?? 'unknown';
                  return '${entry.$2}:${previous + 1}>${entry.$1 + 1}:$status';
                }).join('|')};'
                'warnings=${validation.warnings.map((item) => item.messageKey).join('|')}',
            createdAt: now,
            overrideReason: overrideReason.trim(),
          ),
        ],
        updatedAt: now,
      );
    }
    await _persist(recalculateSequence: false);
  }

  Future<void> overrideSequenceAccount(String accountId, String reason) async {
    if (reason.trim().length < 5) {
      throw const FormatException('Provide a clear override reason.');
    }
    final current = _requirePortfolio();
    final account = current.accounts.firstWhere((item) => item.id == accountId);
    final evaluation = sequencePlan?.evaluations[accountId];
    if (evaluation == null ||
        evaluation.exclusionReasons.isEmpty ||
        evaluation.exclusionReasons.any(
          (item) => item.messageKey != 'sequence.exclusion.payoutProtected',
        )) {
      throw StateError('This account restriction cannot be overridden.');
    }
    final now = _now();
    final settings = _sequenceState.settings.copyWith(
      selectedStartingAccountId: accountId,
      overrideReasons: {
        ..._sequenceState.settings.overrideReasons,
        accountId: reason.trim(),
      },
    );
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    _portfolio = current.copyWith(
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('sequence-override', now, current.disciplineRecords.length),
          type: 'sequence_account_override',
          detail:
              '$accountId:estimated_payout_at_risk='
              '${account.situation.currentPayoutEligibleProfit ?? account.currentProfit.clamp(0, double.infinity)}',
          createdAt: now,
          overrideReason: reason.trim(),
        ),
      ],
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> confirmSequence() async {
    await recalculateSequence();
    final current = _requirePortfolio();
    final plan = _sequenceState.plan;
    if (plan == null || plan.portfolioStopped || plan.steps.isEmpty) {
      throw StateError('No eligible sequence can be confirmed.');
    }
    final now = _now();
    _sequenceState = _sequenceState.copyWith(
      plan: plan.copyWith(confirmedAt: now),
      updatedAt: now,
    );
    _portfolio = current.copyWith(
      sessionStatus: PortfolioSessionStatus.ready,
      activeSessionId: plan.id,
      clearStopReason: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('sequence-confirmed', now, current.disciplineRecords.length),
          type: 'account_sequence_confirmed',
          detail: plan.steps.map((item) => item.accountId).join(','),
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> startSequenceSession() async {
    await _rollTradingDayIfNeeded();
    final current = _requirePortfolio();
    final confirmed = _sequenceState.plan;
    if (confirmed?.confirmedAt == null) {
      throw StateError('Confirm the current sequence before starting.');
    }
    final fresh = sequences.generateSequence(
      portfolio: current,
      settings: _sequenceState.settings,
      now: _now(),
      previousPlan: confirmed,
    );
    if (fresh.portfolioStopped ||
        fresh.steps.isEmpty ||
        !_sameSequenceOrder(confirmed!, fresh)) {
      _sequenceState = _sequenceState.copyWith(plan: fresh, updatedAt: _now());
      await _persist(recalculateSequence: false);
      throw StateError(
        'Account conditions changed. Review and confirm the sequence again.',
      );
    }
    final accountId = fresh.steps.first.accountId;
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (account.id == accountId)
            account.copyWith(status: AccountStatus.active)
          else if ({
            AccountStatus.active,
            AccountStatus.recommended,
          }.contains(account.status))
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      sessionActive: true,
      sessionStatus: PortfolioSessionStatus.active,
      activeSessionId: confirmed.id,
      clearStopReason: true,
      activeAccountId: accountId,
      updatedAt: now,
    );
    _sequenceState = _sequenceState.copyWith(
      plan: confirmed.copyWith(
        steps: [
          for (final step in confirmed.steps)
            step.copyWith(
              status: step.accountId == accountId
                  ? SequenceStepStatus.active
                  : SequenceStepStatus.pending,
            ),
        ],
      ),
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> skipSequenceAccount(String accountId, String reason) async {
    if (reason.trim().isEmpty) {
      throw const FormatException('Select or enter a skip reason.');
    }
    final current = _requirePortfolio();
    if (!current.activeAccounts.any((account) => account.id == accountId)) {
      throw const FormatException('Select a valid account.');
    }
    final now = _now();
    final settings = _sequenceState.settings.copyWith(
      skippedAccountReasons: {
        ..._sequenceState.settings.skippedAccountReasons,
        accountId: reason.trim(),
      },
    );
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (account.id == accountId && account.status == AccountStatus.active)
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      sessionActive: current.activeAccountId == accountId
          ? false
          : current.sessionActive,
      clearActiveAccount: current.activeAccountId == accountId,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('sequence-skip', now, current.disciplineRecords.length),
          type: 'account_sequence_skip',
          detail: '$accountId:${reason.trim()}',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    final plan = sequences.generateSequence(
      portfolio: _portfolio!,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> stopSequence() async {
    final current = _requirePortfolio();
    final now = _now();
    final plan = _sequenceState.plan;
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if ({
            AccountStatus.active,
            AccountStatus.recommended,
          }.contains(account.status))
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.stopped,
      stopReason: _stopReason(
        SessionStopReasonCode.manualStop,
        SessionStoppedBy.manualUser,
        now,
      ),
      clearActiveAccount: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('sequence-stop', now, current.disciplineRecords.length),
          type: 'account_sequence_stopped',
          detail: 'manual_sequence_stop',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    if (plan != null) {
      _sequenceState = _sequenceState.copyWith(
        plan: plan.copyWith(
          steps: [
            for (final step in plan.steps)
              step.status == SequenceStepStatus.active
                  ? step.copyWith(status: SequenceStepStatus.stopped)
                  : step,
          ],
        ),
        updatedAt: now,
      );
    }
    await _persist(recalculateSequence: false);
  }

  Future<void> saveSequenceTemplate(String name) async {
    if (name.trim().length < 2) {
      throw const FormatException('Enter a template name.');
    }
    final now = _now();
    final settings = _sequenceState.settings;
    final template = AccountSequenceTemplate(
      id: _id('sequence-template', now, _sequenceState.templates.length),
      name: name.trim(),
      mode: settings.mode,
      startingAccountId: settings.selectedStartingAccountId,
      accountOrder: settings.customOrder,
      roles: settings.roles,
      tiePreference: settings.tiePreference,
      oneAccountOnly: settings.oneAccountOnly,
      createdAt: now,
    );
    _sequenceState = _sequenceState.copyWith(
      templates: [..._sequenceState.templates, template],
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> applySequenceTemplate(String templateId) async {
    final template = _sequenceState.templates
        .where((item) => item.id == templateId)
        .firstOrNull;
    if (template == null) throw const FormatException('Template not found.');
    await updateSequenceSettings(
      _sequenceState.settings.copyWith(
        selectedStartingAccountId: template.startingAccountId,
        mode: template.mode,
        customOrder: template.accountOrder,
        roles: template.roles,
        tiePreference: template.tiePreference,
        oneAccountOnly: template.oneAccountOnly,
      ),
    );
  }

  Future<void> archiveAccount(String accountId) async {
    final current = _requirePortfolio();
    final accounts = [
      for (final account in current.accounts)
        if (account.id == accountId)
          account.copyWith(status: AccountStatus.archived)
        else
          account,
    ];
    _portfolio = current.copyWith(accounts: accounts, updatedAt: _now());
    await _persist();
  }

  Future<void> selectStrategy(RotationTemplate template) async {
    final current = _requirePortfolio();
    _portfolio = current.copyWith(
      strategy: RotationStrategy.forTemplate(template),
      updatedAt: _now(),
    );
    await _persist();
  }

  Future<void> updateRiskPolicy(PortfolioRiskPolicy policy) async {
    if (policy.maximumDailyLoss <= 0 ||
        policy.maximumRiskPerTrade <= 0 ||
        policy.maximumRiskPerTrade > policy.maximumDailyLoss ||
        policy.maximumTradesPerDay < 1 ||
        policy.maximumConsecutiveLosses < 1 ||
        policy.maximumOpenPositions < 1 ||
        policy.maximumAccountsSameInstrument < 1 ||
        !policy.maximumSessionLossR.isFinite ||
        policy.maximumSessionLossR < 0 ||
        !policy.combinedSessionProfitTarget.isFinite ||
        policy.combinedSessionProfitTarget < 0 ||
        !policy.maximumCombinedRisk.isFinite ||
        policy.maximumCombinedRisk < 0) {
      throw const FormatException('Portfolio safeguard settings are invalid.');
    }
    final current = _requirePortfolio();
    _portfolio = current.copyWith(riskPolicy: policy, updatedAt: _now());
    await _persist();
  }

  Future<void> startSession() async {
    final accountId = simplifiedRecommendedAccountId;
    if (accountId == null) {
      throw StateError('No account is eligible to start this session.');
    }
    await startSessionWithAccount(accountId);
  }

  Future<void> startSessionWithAccount(String accountId) async {
    await _rollTradingDayIfNeeded();
    var current = _requirePortfolio();
    if (current.sessionActive) {
      throw StateError('A trading account is already active.');
    }
    final now = _now();
    final settings = _sequenceState.settings.copyWith(
      selectedStartingAccountId: accountId,
      mode: SequenceMode.healthiestNext,
    );
    var plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    final evaluation = plan.evaluations[accountId];
    if (evaluation == null || !evaluation.isEligible) {
      throw StateError('No account is eligible to start this session.');
    }
    final startRequestId = _id(
      'start-request',
      now,
      Object.hash(accountId, identityHashCode(this), _startRequestSerial++),
    );
    await repository.reserveAccountStart(
      accountId: accountId,
      requestId: startRequestId,
      now: now,
    );
    try {
      final continuing = hasOpenTradingSession;
      if (continuing && current.activeSessionId != null) {
        plan = _withSessionIdentity(
          plan,
          _sequenceState.plan,
          sessionId: current.activeSessionId!,
          confirmedAt: _sequenceState.plan?.confirmedAt ?? now,
        );
      } else {
        plan = plan.copyWith(confirmedAt: now);
      }
      final accounts = [
        for (final account in current.accounts)
          if (account.id == accountId)
            account.copyWith(status: AccountStatus.active)
          else if ({
            AccountStatus.active,
            AccountStatus.recommended,
          }.contains(account.status))
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ];
      current = current.copyWith(
        accounts: accounts,
        sessionActive: true,
        sessionStatus: PortfolioSessionStatus.active,
        activeSessionId: continuing ? current.activeSessionId : plan.id,
        clearStopReason: true,
        activeAccountId: accountId,
        updatedAt: now,
      );
      _portfolio = current;
      _sequenceState = _sequenceState.copyWith(
        settings: settings,
        plan: plan.copyWith(
          steps: [
            for (final step in plan.steps)
              step.copyWith(
                status: step.accountId == accountId
                    ? SequenceStepStatus.active
                    : SequenceStepStatus.pending,
              ),
          ],
        ),
        updatedAt: now,
      );
      await _persist(recalculateSequence: false);
    } finally {
      try {
        await repository.releaseAccountStart(requestId: startRequestId);
      } on Object catch (error) {
        debugPrint('Could not release account start claim: $error');
      }
    }
  }

  Future<void> setStartingAccount(String accountId) async {
    final current = _requirePortfolio();
    if (hasOpenTradingSession || current.sessionActive) {
      throw StateError('End the current session before changing the start.');
    }
    final now = _now();
    final settings = _sequenceState.settings.copyWith(
      selectedStartingAccountId: accountId,
      mode: SequenceMode.healthiestNext,
    );
    final plan = sequences.generateSequence(
      portfolio: current,
      settings: settings,
      now: now,
      previousPlan: _sequenceState.plan,
    );
    if (plan.evaluations[accountId]?.isEligible != true) {
      throw StateError('This account is not available to start.');
    }
    _sequenceState = _sequenceState.copyWith(
      settings: settings,
      plan: plan,
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> toggleAccountPaused(String accountId) async {
    final current = _requirePortfolio();
    final now = _now();
    final account = current.accounts
        .where((item) => item.id == accountId)
        .firstOrNull;
    if (account == null) throw const FormatException('Select a valid account.');
    if (account.status == AccountStatus.breached || account.isArchived) {
      throw StateError('This account cannot be resumed.');
    }
    final pausing = account.status != AccountStatus.paused;
    _portfolio = current.copyWith(
      accounts: [
        for (final item in current.accounts)
          if (item.id == accountId)
            item.copyWith(
              status: pausing ? AccountStatus.paused : AccountStatus.available,
              isAvailableToday: !pausing,
            )
          else
            item,
      ],
      sessionActive: current.activeAccountId == accountId
          ? false
          : current.sessionActive,
      sessionStatus: current.activeAccountId == accountId
          ? PortfolioSessionStatus.ready
          : current.sessionStatus,
      clearActiveAccount: current.activeAccountId == accountId,
      updatedAt: now,
    );
    await _persist();
  }

  Future<bool> removeOrArchiveAccount(String accountId) async {
    final current = _requirePortfolio();
    if (current.activeAccountId == accountId) {
      throw StateError('End or pause the active account before removing it.');
    }
    final hasHistory = current.results.any(
      (result) => result.accountId == accountId,
    );
    if (hasHistory) {
      await archiveAccount(accountId);
      return true;
    }
    final accounts = current.accounts
        .where((account) => account.id != accountId)
        .toList(growable: false);
    if (accounts.length == current.accounts.length) {
      throw const FormatException('Select a valid account.');
    }
    _portfolio = current.copyWith(accounts: accounts, updatedAt: _now());
    await _persist();
    return false;
  }

  Future<void> completeTradingSession() async {
    final current = _requirePortfolio();
    if (current.activeSessionId == null) {
      throw StateError('No trading session is open.');
    }
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if ({
            AccountStatus.active,
            AccountStatus.recommended,
          }.contains(account.status))
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.completed,
      clearActiveAccount: true,
      clearStopReason: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('session-completed', now, current.disciplineRecords.length),
          type: 'session_completed',
          detail: current.activeSessionId!,
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist(recalculateSequence: false);
  }

  Future<void> stopSession({String reason = 'manual_session_stop'}) async {
    final current = _requirePortfolio();
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (account.status == AccountStatus.active ||
              account.status == AccountStatus.recommended)
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.stopped,
      stopReason: _stopReason(
        SessionStopReasonCode.manualStop,
        SessionStoppedBy.manualUser,
        now,
      ),
      clearActiveAccount: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('discipline', now, current.disciplineRecords.length),
          type: 'stop_respected',
          detail: reason,
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> emergencyLock() async {
    final current = _requirePortfolio();
    final now = _now();
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if ({
            AccountStatus.breached,
            AccountStatus.archived,
            AccountStatus.payoutRequested,
          }.contains(account.status))
            account
          else
            account.copyWith(status: AccountStatus.portfolioLocked),
      ],
      portfolioLocked: true,
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.stopped,
      stopReason: _stopReason(
        SessionStopReasonCode.emergencyStop,
        SessionStoppedBy.manualUser,
        now,
      ),
      clearActiveAccount: true,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('discipline', now, current.disciplineRecords.length),
          type: 'stop_respected',
          detail: 'emergency_planner_lock',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> recordOverride(String reason) async {
    if (reason.trim().length < 5) {
      throw const FormatException('Provide a clear override reason.');
    }
    final current = _requirePortfolio();
    final now = _now();
    final overridden = healthDecisions.recommend(current, now: now);
    _portfolio = current.copyWith(
      portfolioLocked: false,
      emotionalStop: false,
      sessionStatus: PortfolioSessionStatus.warning,
      clearStopReason: true,
      accounts: [
        for (final account in current.accounts)
          if (account.status == AccountStatus.portfolioLocked)
            account.copyWith(status: AccountStatus.available)
          else
            account,
      ],
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('override', now, current.disciplineRecords.length),
          type: 'override',
          detail:
              'portfolio_stop_override:${overridden.type.name}:'
              'account=${overridden.recommendedAccountId ?? 'none'}:'
              'risk=${overridden.maximumSuggestedRisk ?? 0}',
          createdAt: now,
          overrideReason: reason.trim(),
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  List<RecommendationReason> validatePlannedTrade(PlannedTrade trade) {
    final current = _requirePortfolio();
    final account = current.accounts
        .where((item) => item.id == trade.accountId)
        .firstOrNull;
    if (account == null) {
      throw const FormatException('Select a valid account.');
    }
    calculations.rewardRiskRatio(
      direction: trade.direction,
      entryPrice: trade.entryPrice,
      stopLossPrice: trade.stopLossPrice,
      takeProfitPrice: trade.takeProfitPrice,
    );
    if (trade.instrument.trim().isEmpty ||
        trade.tradeReason.trim().isEmpty ||
        trade.invalidationReason.trim().isEmpty ||
        trade.riskAmount <= 0 ||
        trade.riskAmount > account.maxRiskPerTrade ||
        trade.riskAmount > current.riskPolicy.maximumRiskPerTrade) {
      throw const FormatException('Planned trade fields or risk are invalid.');
    }
    if (trade.psychology.highRisk && trade.overrideReason.trim().length < 5) {
      throw const FormatException(
        'A high emotional-risk plan requires an explicit override reason.',
      );
    }
    return rotation.duplicateExposureWarnings(current, trade);
  }

  Future<List<RecommendationReason>> addPlannedTrade(
    PlannedTrade trade, {
    bool acceptWarnings = false,
  }) async {
    final warnings = validatePlannedTrade(trade);
    if (warnings.isNotEmpty && !acceptWarnings) return warnings;
    if (warnings.isNotEmpty &&
        acceptWarnings &&
        trade.overrideReason.trim().length < 5) {
      throw const FormatException(
        'A combined-exposure override requires an explicit reason.',
      );
    }
    final current = _requirePortfolio();
    final now = _now();
    final records = [...current.disciplineRecords];
    if (trade.psychology.highRisk || warnings.isNotEmpty) {
      records.add(
        DisciplineRecord(
          id: _id('override', now, records.length),
          type: 'override',
          detail: trade.psychology.highRisk
              ? 'emotional_risk_trade_plan'
              : 'duplicate_exposure_trade_plan',
          createdAt: now,
          overrideReason: trade.overrideReason,
        ),
      );
    }
    _portfolio = current.copyWith(
      plannedTrades: [...current.plannedTrades, trade],
      disciplineRecords: records,
      emotionalStop:
          trade.psychology.highRisk &&
          current.riskPolicy.stopAfterEmotionalEvent,
      updatedAt: now,
    );
    await _applyGlobalStopIfRequired();
    await _persist();
    return warnings;
  }

  SimplifiedTradeResultPreview previewSimplifiedResult({
    required TradeResultType type,
    required double enteredAmount,
    String notes = '',
    String tradeReference = '',
  }) {
    final current = _requirePortfolio();
    final accountId = current.activeAccountId;
    if (!current.sessionActive || accountId == null) {
      throw StateError('Start an account before recording a result.');
    }
    final account = current.accounts.firstWhere((item) => item.id == accountId);
    final normalized = accountValidation.normalizeResult(type, enteredAmount);
    final now = _now();
    final beforeScore = healthScoreService.calculate(account, now: now);
    final balanceAfter = account.currentBalance + normalized;
    final equityAfter = account.referenceEquity + normalized;
    final projected = account.copyWith(
      currentBalance: balanceAfter,
      manuallyEnteredEquity: equityAfter,
      overallRealisedProfitLoss: account.overallRealisedProfitLoss + normalized,
      consecutiveWins: normalized > 0 ? account.consecutiveWins + 1 : 0,
      consecutiveLosses: normalized < 0 ? account.consecutiveLosses + 1 : 0,
      tradesToday: account.tradesToday + 1,
      status: AccountStatus.available,
      situation: account.situation.copyWith(
        todayRealisedProfitLoss:
            account.situation.todayRealisedProfitLoss + normalized,
        lossAlreadyRecordedToday:
            account.situation.lossAlreadyRecordedToday +
            math.max(0, -normalized),
        updatedAt: now,
      ),
      updatedAt: now,
    );
    final afterScore = healthScoreService.calculate(projected, now: now);
    return SimplifiedTradeResultPreview(
      resultId: _id('result', now, current.results.length),
      account: account,
      type: type,
      enteredAmount: enteredAmount,
      normalizedAmount: normalized,
      balanceAfter: balanceAfter,
      equityAfter: equityAfter,
      healthBefore: beforeScore.score,
      healthAfter: afterScore.score,
      dailyLossRemainingAfter: afterScore.lossLimits.dailyLossRemaining,
      maximumLossRemainingAfter: afterScore.lossLimits.maximumLossRemaining,
      resultTime: now,
      notes: notes.trim(),
      tradeReference: tradeReference.trim(),
    );
  }

  Future<void> recordSimplifiedResult(
    SimplifiedTradeResultPreview preview,
  ) async {
    final current = _requirePortfolio();
    if (current.results.any((result) => result.id == preview.resultId)) {
      throw StateError('This trade result has already been recorded.');
    }
    final risk = math.max(0.0, preview.account.maxRiskPerTrade);
    final result = RecordedTradeResult(
      id: preview.resultId,
      accountId: preview.account.id,
      type: preview.type,
      amount: preview.normalizedAmount,
      enteredAmount: preview.enteredAmount,
      rMultiple: risk <= 0 ? 0 : preview.normalizedAmount / risk,
      resultTime: preview.resultTime,
      notes: preview.notes,
      tradeReference: preview.tradeReference,
      sessionId: current.activeSessionId,
      balanceBefore: preview.account.currentBalance,
      balanceAfter: preview.balanceAfter,
      equityBefore: preview.account.referenceEquity,
      equityAfter: preview.equityAfter,
      healthScoreBefore: preview.healthBefore,
      healthScoreAfter: preview.healthAfter,
      dailyLossRemainingAfter: preview.dailyLossRemainingAfter,
      maximumLossRemainingAfter: preview.maximumLossRemainingAfter,
    );
    await recordResult(result);
  }

  Future<void> recordResult(RecordedTradeResult result) async {
    var current = _requirePortfolio();
    if (current.results.any((existing) => existing.id == result.id)) {
      throw StateError('This trade result has already been recorded.');
    }
    final accountIndex = current.accounts.indexWhere(
      (account) => account.id == result.accountId,
    );
    if (accountIndex < 0) {
      throw const FormatException('Select a valid account.');
    }
    final before = current.accounts[accountIndex];
    final transitioned = rotation.transitionAfterResult(
      before,
      result,
      current.strategy,
      current.riskPolicy,
    );
    final updatedRealised =
        before.situation.todayRealisedProfitLoss + result.amount;
    var updated = transitioned.copyWith(
      manuallyEnteredEquity: before.referenceEquity + result.amount,
      overallRealisedProfitLoss:
          before.overallRealisedProfitLoss + result.amount,
      situation: before.situation.copyWith(
        todayRealisedProfitLoss: updatedRealised,
        lossAlreadyRecordedToday:
            before.situation.lossAlreadyRecordedToday +
            math.max(0, -result.amount),
        currentPayoutEligibleProfit: math.max(0, transitioned.currentProfit),
        updatedAt: _now(),
      ),
    );
    if (result.isLoss) {
      updated = switch (_sequenceState.settings.afterSlAction) {
        SequenceAfterSlAction.lockForDay => updated.copyWith(
          status: AccountStatus.dailyLocked,
        ),
        SequenceAfterSlAction.startCooldown => updated.copyWith(
          status: AccountStatus.coolingAfterLoss,
          cooldownUntil: result.resultTime.add(
            Duration(minutes: current.riskPolicy.cooldownAfterLossMinutes),
          ),
        ),
        _ => updated,
      };
    } else if (result.isWin &&
        _sequenceState.settings.afterTpAction ==
            SequenceAfterTpAction.protectForDay) {
      updated = updated.copyWith(status: AccountStatus.dailyLocked);
    }
    final actualHealth = healthScoreService.calculate(
      updated,
      now: result.resultTime,
    );
    final storedResult = result.copyWith(
      sessionId: result.sessionId ?? current.activeSessionId,
      balanceBefore: result.balanceBefore ?? before.currentBalance,
      balanceAfter: updated.currentBalance,
      equityBefore: result.equityBefore ?? before.referenceEquity,
      equityAfter: updated.referenceEquity,
      healthScoreBefore:
          result.healthScoreBefore ??
          healthScoreService.calculate(before, now: result.resultTime).score,
      healthScoreAfter: actualHealth.score,
      dailyLossRemainingAfter: actualHealth.lossLimits.dailyLossRemaining,
      maximumLossRemainingAfter: actualHealth.lossLimits.maximumLossRemaining,
    );
    final accounts = [...current.accounts]..[accountIndex] = updated;
    final plans = [
      for (final plan in current.plannedTrades)
        if ((result.plannedTradeId != null &&
                plan.id == result.plannedTradeId) ||
            (result.plannedTradeId == null &&
                plan.accountId == result.accountId &&
                plan.isOpen))
          plan.copyWith(isOpen: false)
        else
          plan,
    ];
    final transition = AccountStateTransition(
      accountId: before.id,
      from: before.status,
      to: updated.status,
      reason: result.type.name,
      createdAt: result.resultTime,
    );
    final sequenceStop =
        result.isLoss &&
        _sequenceState.settings.afterSlAction ==
            SequenceAfterSlAction.stopPortfolio;
    final resultPolicyRecords = [
      ...current.disciplineRecords,
      DisciplineRecord(
        id: _id(
          'sequence-result-policy',
          result.resultTime,
          current.disciplineRecords.length,
        ),
        type: 'account_sequence_result_policy',
        detail: result.isLoss
            ? _sequenceState.settings.afterSlAction.name
            : result.isWin
            ? _sequenceState.settings.afterTpAction.name
            : _sequenceState.settings.afterBreakevenAction.name,
        createdAt: result.resultTime,
      ),
    ];
    current = current.copyWith(
      accounts: sequenceStop
          ? [
              for (final account in accounts)
                {
                      AccountStatus.breached,
                      AccountStatus.archived,
                    }.contains(account.status)
                    ? account
                    : account.copyWith(status: AccountStatus.portfolioLocked),
            ]
          : accounts,
      plannedTrades: plans,
      results: [...current.results, storedResult],
      disciplineRecords: resultPolicyRecords,
      transitions: [...current.transitions, transition],
      sessionActive: false,
      portfolioLocked: sequenceStop ? true : current.portfolioLocked,
      sessionStatus: sequenceStop
          ? PortfolioSessionStatus.stopped
          : PortfolioSessionStatus.ready,
      stopReason: sequenceStop
          ? _stopReason(
              SessionStopReasonCode.oneLoss,
              SessionStoppedBy.automaticRule,
              result.resultTime,
            )
          : current.stopReason,
      clearActiveAccount: true,
      updatedAt: _now(),
    );
    _portfolio = current;
    await _applyGlobalStopIfRequired();
    await _persist();
  }

  Future<void> resetToday() async {
    final current = _requirePortfolio();
    final now = _now();
    final todayKey = calculations.tradingDayKey(now, current.riskPolicy);
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          account.copyWith(
            tradesToday: 0,
            situation: _resetSituation(account, now),
            status:
                {
                  AccountStatus.dailyLocked,
                  AccountStatus.portfolioLocked,
                  AccountStatus.coolingAfterWin,
                  AccountStatus.coolingAfterLoss,
                  AccountStatus.active,
                  AccountStatus.recommended,
                }.contains(account.status)
                ? AccountStatus.available
                : account.status,
            clearCooldown: true,
          ),
      ],
      plannedTrades: [
        for (final trade in current.plannedTrades)
          trade.isOpen ? trade.copyWith(isOpen: false) : trade,
      ],
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.draft,
      portfolioLocked: false,
      emotionalStop: false,
      clearStopReason: true,
      clearActiveSessionId: true,
      clearActiveAccount: true,
      sessionDateKey: todayKey,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('reset', now, current.disciplineRecords.length),
          type: 'session_reset',
          detail: todayKey,
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    _sequenceState = _sequenceState.copyWith(
      settings: _sequenceState.settings.copyWith(
        skippedAccountReasons: const {},
        overrideReasons: const {},
      ),
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> resetAccountToday(String accountId) async {
    final current = _requirePortfolio();
    final index = current.accounts.indexWhere((item) => item.id == accountId);
    if (index < 0) throw const FormatException('Select a valid account.');
    final now = _now();
    final account = current.accounts[index];
    final accounts = [...current.accounts];
    accounts[index] = account.copyWith(
      tradesToday: 0,
      status:
          {
            AccountStatus.dailyLocked,
            AccountStatus.coolingAfterWin,
            AccountStatus.coolingAfterLoss,
          }.contains(account.status)
          ? AccountStatus.available
          : account.status,
      clearCooldown: true,
      situation: _resetSituation(account, now),
    );
    _portfolio = current.copyWith(
      accounts: accounts,
      disciplineRecords: [
        ...current.disciplineRecords,
        DisciplineRecord(
          id: _id('account-reset', now, current.disciplineRecords.length),
          type: 'account_daily_reset',
          detail:
              '$accountId:${_accountTradingDayKey(now, account)}:manual:'
              'result=${account.situation.todayRealisedProfitLoss}:'
              'trades=${account.tradesToday}',
          createdAt: now,
        ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> updateFirmPreset(PropFirmRulePreset preset) async {
    if (preset.firmName.trim().isEmpty || preset.minimumPayout < 0) {
      throw const FormatException('Firm rule preset is invalid.');
    }
    final current = _requirePortfolio();
    final presets = [...current.firmPresets];
    final index = presets.indexWhere((item) => item.id == preset.id);
    if (index < 0) {
      presets.add(preset);
    } else {
      presets[index] = preset;
    }
    _portfolio = current.copyWith(firmPresets: presets, updatedAt: _now());
    await _persist();
  }

  String exportJson() => repository.exportJson(_requirePortfolio());
  String exportCsv() => repository.exportCsv(_requirePortfolio());

  Future<void> importJson(String source) async {
    final imported = repository.importJson(source);
    _portfolio = imported.copyWith(updatedAt: _now());
    if (_sequenceState.plan?.portfolioId != imported.id) {
      _sequenceState = const AccountSequenceWorkspaceState();
    }
    await _persist();
  }

  Future<void> deletePortfolio() async {
    await repository.delete();
    _portfolio = null;
    _sequenceState = const AccountSequenceWorkspaceState();
    notifyListeners();
  }

  Future<void> _rollTradingDayIfNeeded() async {
    final current = _portfolio;
    if (current == null) return;
    final key = calculations.tradingDayKey(_now(), current.riskPolicy);
    if (current.sessionDateKey == key) return;
    await resetToday();
  }

  Future<void> _rollAccountTradingDaysIfNeeded() async {
    final current = _portfolio;
    if (current == null) return;
    final now = _now();
    final resetIds = <String>{};
    for (final account in current.activeAccounts) {
      final lastUpdate = account.situation.updatedAt;
      if (lastUpdate == null ||
          account.situation.resetHour == null ||
          account.situation.dailyResetMethod == DailyResetMethod.manualOnly) {
        continue;
      }
      if (_accountTradingDayKey(lastUpdate, account) !=
          _accountTradingDayKey(now, account)) {
        resetIds.add(account.id);
      }
    }
    if (resetIds.isEmpty) return;
    final skipped = {..._sequenceState.settings.skippedAccountReasons}
      ..removeWhere((key, _) => resetIds.contains(key));
    final overrides = {..._sequenceState.settings.overrideReasons}
      ..removeWhere((key, _) => resetIds.contains(key));
    _sequenceState = _sequenceState.copyWith(
      settings: _sequenceState.settings.copyWith(
        skippedAccountReasons: skipped,
        overrideReasons: overrides,
      ),
      updatedAt: now,
    );
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if (resetIds.contains(account.id))
            account.copyWith(
              tradesToday: 0,
              status:
                  {
                    AccountStatus.dailyLocked,
                    AccountStatus.coolingAfterWin,
                    AccountStatus.coolingAfterLoss,
                  }.contains(account.status)
                  ? AccountStatus.available
                  : account.status,
              clearCooldown: true,
              situation: _resetSituation(account, now),
            )
          else
            account,
      ],
      disciplineRecords: [
        ...current.disciplineRecords,
        for (final entry in resetIds.indexed)
          DisciplineRecord(
            id: _id(
              'account-reset',
              now,
              current.disciplineRecords.length + entry.$1,
            ),
            type: 'account_daily_reset',
            detail:
                '${entry.$2}:auto:'
                'result=${current.accounts.firstWhere((item) => item.id == entry.$2).situation.todayRealisedProfitLoss}:'
                'trades=${current.accounts.firstWhere((item) => item.id == entry.$2).tradesToday}',
            createdAt: now,
          ),
      ],
      updatedAt: now,
    );
    await _persist();
  }

  Future<void> _applyGlobalStopIfRequired() async {
    final current = _requirePortfolio();
    final evaluation = stopConditions.evaluate(current, now: _now());
    if (!evaluation.isStopped) {
      if (current.sessionStatus != evaluation.status) {
        _portfolio = current.copyWith(sessionStatus: evaluation.status);
      }
      return;
    }
    _portfolio = current.copyWith(
      accounts: [
        for (final account in current.accounts)
          if ({
            AccountStatus.breached,
            AccountStatus.archived,
            AccountStatus.payoutReady,
            AccountStatus.payoutProtection,
            AccountStatus.payoutRequested,
          }.contains(account.status))
            account
          else
            account.copyWith(status: AccountStatus.portfolioLocked),
      ],
      portfolioLocked: true,
      sessionActive: false,
      sessionStatus: PortfolioSessionStatus.stopped,
      stopReason: evaluation.stopReason,
      clearActiveAccount: true,
      updatedAt: _now(),
    );
  }

  void _validateAccount(PropFirmAccount account) {
    accountValidation.validate(account);
    if (account.maxRiskPerTrade <= 0 ||
        !account.maxRiskPerTrade.isFinite ||
        account.profitSplit < 0 ||
        account.profitSplit > 1 ||
        account.desiredPayout < 0 ||
        account.maxTradesPerDay < 1) {
      throw const FormatException('Account settings are invalid.');
    }
    _validateSituation(account.situation);
  }

  void _validateSituation(AccountCurrentSituation situation) {
    if (!situation.maximumLossPercentage.isFinite ||
        situation.maximumLossPercentage <= 0 ||
        situation.maximumLossPercentage > 100 ||
        !situation.dailyLossPercentage.isFinite ||
        situation.dailyLossPercentage <= 0 ||
        situation.dailyLossPercentage > 100 ||
        situation.maximumLossAmount < 0 ||
        situation.dailyLossAmount < 0 ||
        situation.lossAlreadyRecordedToday < 0 ||
        situation.manuallyEnteredOpenRisk < 0 ||
        situation.payoutSafetyBuffer < 0 ||
        situation.resetHour != null &&
            (situation.resetHour! < 0 || situation.resetHour! > 23) ||
        situation.resetMinute < 0 ||
        situation.resetMinute > 59 ||
        situation.resetUtcOffsetMinutes != null &&
            (situation.resetUtcOffsetMinutes! < -720 ||
                situation.resetUtcOffsetMinutes! > 840) ||
        situation.healthyUpperPercent < 0 ||
        situation.healthyUpperPercent >= situation.cautionUpperPercent ||
        situation.cautionUpperPercent >= situation.highRiskUpperPercent ||
        situation.highRiskUpperPercent >
            situation.dailySafetyThresholdPercent ||
        situation.dailySafetyThresholdPercent > 100) {
      throw const FormatException('Current account situation is invalid.');
    }
  }

  AccountCurrentSituation _resetSituation(
    PropFirmAccount account,
    DateTime now,
  ) => account.situation.copyWith(
    dailyStartingBalance: account.currentBalance,
    dailyStartingEquity: account.referenceEquity,
    lossAlreadyRecordedToday: 0,
    manuallyEnteredOpenRisk: 0,
    todayRealisedProfitLoss: 0,
    clearUnrealisedProfitLoss: true,
    manualStatus:
        account.situation.manualStatus == ManualAccountHealthStatus.dailyLocked
        ? ManualAccountHealthStatus.healthy
        : account.situation.manualStatus,
    updatedAt: now,
  );

  String _accountTradingDayKey(DateTime instant, PropFirmAccount account) {
    final situation = account.situation;
    final shifted = instant.toUtc().add(
      Duration(minutes: situation.resetUtcOffsetMinutes ?? 0),
    );
    final resetMinutes =
        (situation.resetHour ?? 0) * 60 + situation.resetMinute;
    final beforeReset = shifted.hour * 60 + shifted.minute < resetMinutes;
    final day = beforeReset
        ? shifted.subtract(const Duration(days: 1))
        : shifted;
    return '${day.year.toString().padLeft(4, '0')}-'
        '${day.month.toString().padLeft(2, '0')}-'
        '${day.day.toString().padLeft(2, '0')}';
  }

  String _situationDiff(
    AccountCurrentSituation before,
    AccountCurrentSituation after,
  ) {
    final oldValues = before.toJson();
    final newValues = after.toJson();
    final changed = newValues.keys
        .where((key) => '${oldValues[key]}' != '${newValues[key]}')
        .toList(growable: false);
    return changed.isEmpty ? 'no_changes' : changed.join(',');
  }

  PropFirmPortfolio _requirePortfolio() {
    final current = _portfolio;
    if (current == null) throw StateError('Create a portfolio first.');
    return current;
  }

  SessionStopReason _stopReason(
    SessionStopReasonCode code,
    SessionStoppedBy stoppedBy,
    DateTime stoppedAt,
  ) => SessionStopReason(
    code: code,
    titleKey: sessionStopReasonTitleForCode(code),
    descriptionKey: sessionStopReasonDescriptionForCode(code),
    stoppedAt: stoppedAt,
    stoppedBy: stoppedBy,
  );

  Future<void> _persist({bool recalculateSequence = true}) async {
    _synchronizeDailyTradeCountCache();
    final current = _portfolio;
    if (current == null) return;
    try {
      if (recalculateSequence && _sequenceState.plan != null) {
        final now = _now();
        final previous = _sequenceState.plan!;
        var fresh = sequences.generateSequence(
          portfolio: current,
          settings: _sequenceState.settings,
          now: now,
          previousPlan: previous,
        );
        final sessionHasResults =
            current.activeSessionId != null &&
            current.results.any(
              (result) => result.sessionId == current.activeSessionId,
            );
        if (current.activeSessionId != null &&
            previous.confirmedAt != null &&
            (current.sessionActive || sessionHasResults)) {
          fresh = _withSessionIdentity(
            fresh,
            previous,
            sessionId: current.activeSessionId!,
            confirmedAt: previous.confirmedAt!,
          );
        }
        _sequenceState = _sequenceState.copyWith(plan: fresh, updatedAt: now);
      }
      final stateToSave = _sequenceState;
      _syncState = PortfolioSyncState.saving;
      _retryAction = () => repository.saveWorkspace(current, stateToSave);
      notifyListeners();
      await repository.saveWorkspace(current, stateToSave);
      _error = null;
      _syncErrorCode = null;
      _syncState = PortfolioSyncState.synchronized;
      _lastSynchronizedAt = current.updatedAt;
      _retryAction = null;
    } on PortfolioRepositoryException catch (exception) {
      _restoreLastCloudSnapshot();
      _error = exception.message;
      _syncErrorCode = exception.code;
      _syncState = {'unavailable', 'deadline-exceeded'}.contains(exception.code)
          ? PortfolioSyncState.pending
          : PortfolioSyncState.failed;
      rethrow;
    } on Object catch (exception) {
      _restoreLastCloudSnapshot();
      _error = '$exception';
      _syncErrorCode = 'unexpected';
      _syncState = PortfolioSyncState.failed;
      rethrow;
    } finally {
      notifyListeners();
    }
  }

  void _synchronizeDailyTradeCountCache() {
    final current = _portfolio;
    if (current == null) return;
    final now = _now();
    var changed = false;
    final accounts = [
      for (final account in current.accounts)
        (() {
          final count = dailyTradeLimits
              .evaluate(current, account, now: now)
              .currentTrades;
          if (count == account.tradesToday) return account;
          changed = true;
          return account.copyWith(tradesToday: count);
        })(),
    ];
    if (changed) {
      _portfolio = current.copyWith(accounts: accounts, updatedAt: now);
    }
  }

  void _restoreLastCloudSnapshot() {
    if (!repository.usesCloud) return;
    final snapshot = _lastCloudSnapshot;
    _portfolio = snapshot?.portfolio;
    _sequenceState =
        snapshot?.sequenceState ?? const AccountSequenceWorkspaceState();
    _lastSynchronizedAt = snapshot?.lastSynchronizedAt;
  }

  bool _sameSequenceOrder(AccountSequencePlan left, AccountSequencePlan right) {
    if (left.steps.length != right.steps.length) return false;
    for (var index = 0; index < left.steps.length; index++) {
      final a = left.steps[index];
      final b = right.steps[index];
      if (a.accountId != b.accountId ||
          a.alternativeAccountIds.length != b.alternativeAccountIds.length) {
        return false;
      }
      for (var alt = 0; alt < a.alternativeAccountIds.length; alt++) {
        if (a.alternativeAccountIds[alt] != b.alternativeAccountIds[alt]) {
          return false;
        }
      }
    }
    return true;
  }

  AccountSequencePlan _withSessionIdentity(
    AccountSequencePlan fresh,
    AccountSequencePlan? previous, {
    required String sessionId,
    required DateTime confirmedAt,
  }) => AccountSequencePlan(
    id: sessionId,
    portfolioId: fresh.portfolioId,
    requestedStartingAccountId: fresh.requestedStartingAccountId,
    selectedStartingAccountId: fresh.selectedStartingAccountId,
    mode: fresh.mode,
    steps: fresh.steps,
    exclusions: fresh.exclusions,
    reasons: fresh.reasons,
    stopConditions: fresh.stopConditions,
    evaluations: fresh.evaluations,
    branches: fresh.branches,
    dataCompleteness: fresh.dataCompleteness,
    generatedAt: previous?.generatedAt ?? fresh.generatedAt,
    confirmedAt: confirmedAt,
    isCustomised: fresh.isCustomised,
    startingAccountWasReplaced: fresh.startingAccountWasReplaced,
    portfolioStopped: fresh.portfolioStopped,
    updateReason: fresh.updateReason,
  );

  static String _id(String prefix, DateTime now, [int salt = 0]) =>
      '$prefix-${now.microsecondsSinceEpoch}-$salt';

  static const _accountColors = [
    0xFF00796B,
    0xFF315CA8,
    0xFF8A5A20,
    0xFF6B4FA1,
    0xFF2C6E49,
  ];
}
