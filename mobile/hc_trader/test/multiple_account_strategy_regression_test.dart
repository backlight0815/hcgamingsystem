import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/firestore_prop_firm_portfolio_data_source.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/portfolio_repository_snapshot.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_health_decision_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/discipline_score_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/session_stop_condition_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart';

void main() {
  final now = DateTime(2026, 7, 22, 9);
  const losses = AccountLossLimitService();
  const health = AccountHealthService();
  const stops = SessionStopConditionService();

  group('risk calculation regression matrix', () {
    test('profitable day never creates negative loss used', () {
      final snapshot = losses.calculate(
        _account(now: now, situation: _situation(todayRealised: 250)),
      );
      expect(snapshot.dailyLossUsed, 0);
      expect(snapshot.dailyLossRemaining, 500);
    });

    test('realised and floating losses follow the configured method', () {
      final included = losses.calculate(
        _account(
          now: now,
          situation: _situation(
            todayRealised: -100,
            todayFloating: -50,
            method: DailyLossCalculationMethod.realisedAndUnrealised,
          ),
        ),
      );
      final excluded = losses.calculate(
        _account(
          now: now,
          situation: _situation(
            todayRealised: -100,
            todayFloating: -50,
            method: DailyLossCalculationMethod.realisedOnly,
          ),
        ),
      );
      expect(included.dailyLossUsed, 150);
      expect(excluded.dailyLossUsed, 100);
    });

    test('remaining values floor at zero and breach remains observable', () {
      final snapshot = losses.calculate(
        _account(
          now: now,
          currentBalance: 8800,
          situation: _situation(todayRealised: -600),
        ),
      );
      expect(snapshot.dailyLossRemaining, 0);
      expect(snapshot.maximumLossRemaining, 0);
      expect(snapshot.dailyUsagePercent, 120);
      expect(snapshot.maximumUsagePercent, 120);
      expect(snapshot.dailyLimitBreached, isTrue);
      expect(snapshot.maximumLimitBreached, isTrue);
    });

    test('zero limits are safe and finite', () {
      final snapshot = losses.calculate(
        _account(
          now: now,
          dailyLossLimit: 0,
          maximumLossLimit: 0,
          situation: const AccountCurrentSituation(
            maximumLossPercentage: 0,
            dailyLossPercentage: 0,
            todayRealisedProfitLoss: -10,
          ),
        ),
      );
      expect(snapshot.dailyUsagePercent.isFinite, isTrue);
      expect(snapshot.maximumUsagePercent.isFinite, isTrue);
    });

    test('static and trailing drawdown use their configured references', () {
      final staticSnapshot = losses.calculate(
        _account(
          now: now,
          currentBalance: 9500,
          equity: 9500,
          drawdownType: DrawdownType.static,
        ),
      );
      final trailingSnapshot = losses.calculate(
        _account(
          now: now,
          currentBalance: 11000,
          equity: 10500,
          drawdownType: DrawdownType.trailing,
          situation: _situation(trailingReference: 11000),
        ),
      );
      expect(staticSnapshot.maximumLossRemaining, 500);
      expect(trailingSnapshot.maximumLossRemaining, 500);
    });

    test('configured safety buffer reduces recommended remaining room', () {
      final snapshot = losses.calculate(
        _account(now: now, safetyBufferPercentage: 10),
      );
      expect(snapshot.dailyLossRemaining, 450);
      expect(snapshot.maximumLossRemaining, 900);
    });
  });

  group('health classification boundaries and precedence', () {
    AccountHealthStatus at(double percentage) => health
        .assess(
          _account(
            now: now,
            maxRiskPerTrade: 1,
            situation: _situation(todayRealised: -500 * percentage / 100),
          ),
          now: now,
        )
        .status;

    test('39.99 is healthy and 40 begins caution', () {
      expect(at(39.99), AccountHealthStatus.healthy);
      expect(at(40), AccountHealthStatus.caution);
    });

    test('69.99 is caution and 70 begins high risk', () {
      expect(at(69.99), AccountHealthStatus.caution);
      expect(at(70), AccountHealthStatus.highRisk);
    });

    test('84.99 is high risk and 85 begins critical', () {
      expect(at(84.99), AccountHealthStatus.highRisk);
      expect(at(85), AccountHealthStatus.critical);
    });

    test('breach overrides payout protection', () {
      final assessment = health.assess(
        _account(
          now: now,
          currentBalance: 8800,
          manuallyProtected: true,
          situation: _situation(todayRealised: -600),
        ),
        now: now,
      );
      expect(assessment.status, AccountHealthStatus.breached);
    });

    test('protection and pause cannot display healthy', () {
      final protected = health.assess(
        _account(now: now, manuallyProtected: true),
        now: now,
      );
      final paused = health.assess(
        _account(now: now, status: AccountStatus.paused),
        now: now,
      );
      expect(protected.status, AccountHealthStatus.payoutProtection);
      expect(paused.status, AccountHealthStatus.paused);
    });
  });

  group('sequence and stop-condition regressions', () {
    test('safe selected account remains first', () {
      final portfolio = _portfolio(now: now);
      final plan = const AccountSequenceService().generateSequence(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'b'),
        now: now,
      );
      expect(plan.steps.first.accountId, 'b');
    });

    test('critical selected account is excluded with a reason', () {
      final critical = _account(
        id: 'b',
        now: now,
        maxRiskPerTrade: 1,
        situation: _situation(todayRealised: -450),
      );
      final portfolio = _portfolio(
        now: now,
        accounts: [
          _account(id: 'a', now: now),
          critical,
        ],
      );
      final plan = const AccountSequenceService().generateSequence(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'b'),
        now: now,
      );
      expect(plan.steps.first.accountId, 'a');
      expect(
        plan.exclusions
            .firstWhere((item) => item.accountId == 'b')
            .reasons
            .map((item) => item.messageKey),
        contains('sequence.exclusion.critical'),
      );
    });

    test('USD 200 of USD 500 does not stop without another enabled rule', () {
      final portfolio = _portfolio(
        now: now,
        results: [_lossResult(now, amount: -200, r: -2)],
        riskPolicy: const PortfolioRiskPolicy(
          maximumDailyLoss: 500,
          maximumRiskPerTrade: 100,
        ),
      );
      final result = stops.evaluate(portfolio, now: now);
      expect(result.combinedLossUsed, 200);
      expect(result.combinedLossRemaining, 300);
      expect(result.usagePercentage, 40);
      expect(result.status, PortfolioSessionStatus.active);
      expect(result.stopReason, isNull);
    });

    test('one-loss stop applies only when enabled and contains a reason', () {
      final portfolio = _portfolio(
        now: now,
        results: [_lossResult(now, amount: -200, r: -2)],
        riskPolicy: const PortfolioRiskPolicy(
          maximumDailyLoss: 500,
          maximumRiskPerTrade: 100,
          stopAfterOneLosingTrade: true,
        ),
      );
      final result = stops.evaluate(portfolio, now: now);
      expect(result.status, PortfolioSessionStatus.stopped);
      expect(result.stopReason?.code, SessionStopReasonCode.oneLoss);
      expect(result.stopReason?.titleKey, 'One-loss stop rule triggered');
    });

    test('manual, consecutive and combined-limit stops are explicit', () {
      final manualReason = SessionStopReason(
        code: SessionStopReasonCode.manualStop,
        titleKey: 'session.stop.manualStop.title',
        descriptionKey: 'session.stop.manualStop.description',
        stoppedAt: now,
        stoppedBy: SessionStoppedBy.manualUser,
      );
      final manual = stops.evaluate(
        _portfolio(
          now: now,
          portfolioLocked: true,
          sessionStatus: PortfolioSessionStatus.stopped,
          stopReason: manualReason,
        ),
        now: now,
      );
      final consecutive = stops.evaluate(
        _portfolio(
          now: now,
          results: [
            _lossResult(now.subtract(const Duration(minutes: 1))),
            _lossResult(now),
          ],
        ),
        now: now,
      );
      final limit = stops.evaluate(
        _portfolio(now: now, results: [_lossResult(now, amount: -500)]),
        now: now,
      );
      expect(manual.stopReason?.code, SessionStopReasonCode.manualStop);
      expect(
        consecutive.stopReason?.code,
        SessionStopReasonCode.consecutiveLosses,
      );
      expect(limit.stopReason?.code, SessionStopReasonCode.combinedLossLimit);
    });

    test('open exposure stays separate and can trigger its own rule', () {
      final portfolio = _portfolio(
        now: now,
        accounts: [
          _account(id: 'a', now: now, currentRiskExposureAmount: 120),
          _account(id: 'b', now: now, currentRiskExposureAmount: 90),
        ],
        riskPolicy: const PortfolioRiskPolicy(
          maximumDailyLoss: 500,
          maximumRiskPerTrade: 100,
          maximumCombinedRisk: 200,
        ),
      );
      final result = stops.evaluate(portfolio, now: now);
      expect(result.combinedLossUsed, 0);
      expect(result.stopReason?.code, SessionStopReasonCode.exposureProtection);
      expect(result.stopReason?.stoppedBy, SessionStoppedBy.exposureProtection);
    });
  });

  group('repository synchronization and migration', () {
    test(
      'two devices cannot reserve the same final start concurrently',
      () async {
        final remote = _portfolio(
          now: now,
          accounts: [_account(id: 'a', now: now).copyWith(maxTradesPerDay: 1)],
        );
        final source = _FakePortfolioCloudDataSource(
          PortfolioRepositorySnapshot(
            portfolio: remote,
            sequenceState: const AccountSequenceWorkspaceState(),
            syncState: PortfolioSyncState.synchronized,
            lastSynchronizedAt: remote.updatedAt,
          ),
          enforceStartClaim: true,
        );
        PropFirmPlannerController controller() => PropFirmPlannerController(
          repository: PropFirmPortfolioRepository.withCloudDataSource(
            ownerId: 'owner-concurrent',
            cloudDataSource: source,
          ),
          now: () => now,
        );
        final first = controller();
        final second = controller();
        addTearDown(first.dispose);
        addTearDown(second.dispose);
        await Future.wait([first.initialize(), second.initialize()]);

        Future<bool> attempt(PropFirmPlannerController value) async {
          try {
            await value.startSessionWithAccount('a');
            return true;
          } on Object {
            return false;
          }
        }

        final results = await Future.wait([attempt(first), attempt(second)]);
        expect(results.where((succeeded) => succeeded), hasLength(1));
        expect(source.successfulStartReservations, 1);
      },
    );

    test('an empty draft cannot erase existing Firestore accounts', () {
      expect(
        wouldEraseExistingCloudAccounts(
          existingAccountCount: 3,
          incomingAccountCount: 0,
        ),
        isTrue,
      );
      expect(
        wouldEraseExistingCloudAccounts(
          existingAccountCount: 0,
          incomingAccountCount: 0,
        ),
        isFalse,
      );
      expect(
        wouldEraseExistingCloudAccounts(
          existingAccountCount: 3,
          incomingAccountCount: 3,
        ),
        isFalse,
      );
    });

    test('Firestore envelope schema does not replace sequence schema', () {
      final normalizedLegacyDocument = accountSequenceModelJson({
        'owner_uid': 'owner',
        'schema_version': 4,
        'settings': const <String, Object?>{},
        'plan': null,
        'templates': const <Object?>[],
      });
      expect(normalizedLegacyDocument['schema_version'], 1);
      expect(
        AccountSequenceWorkspaceState.fromJson(
          normalizedLegacyDocument,
        ).schemaVersion,
        1,
      );

      final currentDocument = accountSequenceModelJson({
        'owner_uid': 'owner',
        'schema_version': 1,
        'firestore_schema_version': 4,
        'settings': const <String, Object?>{},
        'plan': null,
        'templates': const <Object?>[],
      });
      expect(currentDocument['schema_version'], 1);
    });

    test(
      'cloud repository uses Firestore and never reads or writes local storage',
      () async {
        final local = _portfolio(now: now).copyWith(name: 'stale local data');
        final remote = _portfolio(now: now).copyWith(name: 'Firestore data');
        final localKey =
            'hc_mobile_owner-cloud_${PropFirmPortfolioRepository.storageKey}';
        SharedPreferences.setMockInitialValues({
          localKey: jsonEncode(local.toJson()),
        });
        final dataSource = _FakePortfolioCloudDataSource(
          PortfolioRepositorySnapshot(
            portfolio: remote,
            sequenceState: const AccountSequenceWorkspaceState(),
            syncState: PortfolioSyncState.synchronized,
            lastSynchronizedAt: remote.updatedAt,
          ),
        );
        final repository = PropFirmPortfolioRepository.withCloudDataSource(
          ownerId: 'owner-cloud',
          cloudDataSource: dataSource,
        );

        expect(repository.load(), isNull);
        expect(repository.loadSequenceState().plan, isNull);
        expect((await repository.read()).portfolio?.name, 'Firestore data');

        final updated = remote.copyWith(name: 'Firestore update');
        await repository.saveWorkspace(
          updated,
          const AccountSequenceWorkspaceState(),
        );
        expect(dataSource.savedPortfolio?.name, 'Firestore update');
        expect(
          (await SharedPreferences.getInstance()).getString(localKey),
          jsonEncode(local.toJson()),
        );
      },
    );

    test('one local repository update reaches multiple subscribers', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final first = PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'owner',
      );
      final second = PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'owner',
      );
      final firstEvent = Completer<PropFirmPortfolio>();
      final secondEvent = Completer<PropFirmPortfolio>();
      final subscriptions = [
        first.watch().listen((snapshot) {
          if (snapshot.portfolio case final portfolio?) {
            if (!firstEvent.isCompleted) firstEvent.complete(portfolio);
          }
        }),
        second.watch().listen((snapshot) {
          if (snapshot.portfolio case final portfolio?) {
            if (!secondEvent.isCompleted) secondEvent.complete(portfolio);
          }
        }),
      ];
      addTearDown(() async {
        for (final subscription in subscriptions) {
          await subscription.cancel();
        }
      });

      await first.saveWorkspace(
        _portfolio(now: now),
        const AccountSequenceWorkspaceState(),
      );
      expect((await firstEvent.future).accounts, hasLength(3));
      expect((await secondEvent.future).accounts, hasLength(3));
    });

    test(
      'two controllers stay synchronized without navigation or restart',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final first = PropFirmPlannerController(
          repository: PropFirmPortfolioRepository(
            storage: storage,
            ownerId: 'owner-controllers',
          ),
          now: () => now,
        );
        final second = PropFirmPlannerController(
          repository: PropFirmPortfolioRepository(
            storage: storage,
            ownerId: 'owner-controllers',
          ),
          now: () => now,
        );
        addTearDown(first.dispose);
        addTearDown(second.dispose);
        await first.initialize();
        await second.initialize();
        await first.createStarterPortfolio(virtualRiskBudget: 3000);
        await _until(() => second.portfolio?.accounts.length == 3);
        final updated = first.portfolio!.accounts.last.copyWith(
          situation: _situation(todayRealised: -350),
        );
        await first.saveAccount(updated);
        await _until(
          () =>
              second
                  .portfolio!
                  .accounts
                  .last
                  .situation
                  .todayRealisedProfitLoss ==
              -350,
        );
        expect(second.sequencePlan, isNotNull);
      },
    );

    test('legacy numeric strings migrate with stable IDs and defaults', () {
      final json = _portfolio(now: now).toJson()..['schema_version'] = 1;
      final accounts = (json['accounts']! as List).cast<Map<String, Object?>>();
      accounts.first['account_size'] = '10000.50';
      accounts.last.remove('id');
      accounts.first.remove('updated_at');
      accounts.first.remove('owner_uid');
      final migrated = PropFirmPortfolio.fromJson(json);
      expect(migrated.schemaVersion, 4);
      expect(migrated.accounts.first.id, 'a');
      expect(migrated.accounts.first.accountSize, 10000.5);
      expect(migrated.accounts.last.id, startsWith('legacy-account-'));
      expect(
        PropFirmPortfolio.fromJson(json).accounts.last.id,
        migrated.accounts.last.id,
      );
      expect(
        migrated.accounts.first.updatedAt,
        migrated.accounts.first.createdAt,
      );
    });

    test('serialization round-trip preserves stop reason and raw values', () {
      final reason = SessionStopReason(
        code: SessionStopReasonCode.emergencyStop,
        titleKey: 'session.stop.emergencyStop.title',
        descriptionKey: 'session.stop.emergencyStop.description',
        stoppedAt: now,
        stoppedBy: SessionStoppedBy.manualUser,
      );
      final source = _portfolio(
        now: now,
        portfolioLocked: true,
        sessionStatus: PortfolioSessionStatus.stopped,
        stopReason: reason,
      );
      final decoded = PropFirmPortfolio.fromJson(source.toJson());
      expect(decoded.stopReason?.code, SessionStopReasonCode.emergencyStop);
      expect(decoded.accounts.first.dailyLossLimit, 500);
      expect(decoded.accounts.first.situation.todayRealisedProfitLoss, 0);
    });
  });

  test('discipline score is unavailable before meaningful actions', () {
    final result = const DisciplineScoreService().calculate(
      _portfolio(now: now),
    );
    expect(result.hasEnoughData, isFalse);
    expect(result.score, isNull);
  });

  testWidgets('dashboard displays the exact persisted stop reason', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final liveNow = DateTime.now();
    final reason = SessionStopReason(
      code: SessionStopReasonCode.oneLoss,
      titleKey: 'session.stop.oneLoss.title',
      descriptionKey: 'session.stop.oneLoss.description',
      stoppedAt: liveNow,
      stoppedBy: SessionStoppedBy.automaticRule,
    );
    await PropFirmPortfolioRepository(
      storage: storage,
      ownerId: 'guest',
    ).saveWorkspace(
      _portfolio(
        now: liveNow,
        portfolioLocked: true,
        sessionStatus: PortfolioSessionStatus.stopped,
        stopReason: reason,
      ),
      const AccountSequenceWorkspaceState(),
    );
    await tester.binding.setSurfaceSize(const Size(1280, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: PropFirmPlannerScreen(
          storage: storage,
          ownerId: 'guest',
          showBackButton: false,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Stopped: One-loss stop rule triggered'),
      findsOneWidget,
      reason: tester
          .widgetList<Text>(find.byType(Text))
          .map((item) => item.data)
          .whereType<String>()
          .join(' | '),
    );
    expect(find.text('Daily Risk Limit Reached'), findsNothing);
  });

  testWidgets('tablet workspace uses adaptive navigation without overflow', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    await PropFirmPortfolioRepository(
      storage: storage,
      ownerId: 'tablet-owner',
    ).saveWorkspace(
      _portfolio(now: now),
      const AccountSequenceWorkspaceState(),
    );
    await tester.binding.setSurfaceSize(const Size(820, 1180));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: PropFirmPlannerScreen(
          storage: storage,
          ownerId: 'tablet-owner',
          showBackButton: false,
          now: () => now,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.text('Multiple Account Strategy'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _until(bool Function() condition) async {
  for (var attempt = 0; attempt < 20; attempt++) {
    if (condition()) return;
    await Future<void>.delayed(const Duration(milliseconds: 1));
  }
  expect(condition(), isTrue);
}

RecordedTradeResult _lossResult(
  DateTime now, {
  double amount = -100,
  double r = -1,
}) => RecordedTradeResult(
  id: 'result-${now.microsecondsSinceEpoch}-$amount',
  accountId: 'a',
  type: TradeResultType.fullSl,
  amount: amount,
  rMultiple: r,
  resultTime: now,
);

AccountCurrentSituation _situation({
  double todayRealised = 0,
  double? todayFloating,
  double? trailingReference,
  DailyLossCalculationMethod method =
      DailyLossCalculationMethod.realisedAndUnrealised,
}) => AccountCurrentSituation(
  maximumLossAmount: 1000,
  dailyLossAmount: 500,
  dailyStartingBalance: 10000,
  dailyStartingEquity: 10000,
  trailingReferenceBalance: trailingReference,
  todayRealisedProfitLoss: todayRealised,
  todayUnrealisedProfitLoss: todayFloating,
  dailyLossCalculationMethod: method,
  resetHour: 0,
  resetTimeZone: 'UTC',
);

PropFirmAccount _account({
  String id = 'a',
  required DateTime now,
  double currentBalance = 10000,
  double? equity,
  double dailyLossLimit = 500,
  double maximumLossLimit = 1000,
  double maxRiskPerTrade = 100,
  double safetyBufferPercentage = 0,
  double currentRiskExposureAmount = 0,
  bool manuallyProtected = false,
  AccountStatus status = AccountStatus.available,
  DrawdownType drawdownType = DrawdownType.static,
  AccountCurrentSituation? situation,
}) => PropFirmAccount(
  id: id,
  name: 'Account ${id.toUpperCase()}',
  propFirmName: 'Firm',
  accountSize: 10000,
  startingBalance: 10000,
  currentBalance: currentBalance,
  manuallyEnteredEquity: equity,
  dailyLossLimit: dailyLossLimit,
  maximumLossLimit: maximumLossLimit,
  drawdownType: drawdownType,
  minimumPayout: 500,
  desiredPayout: 500,
  maxRiskPerTrade: maxRiskPerTrade,
  safetyBufferPercentage: safetyBufferPercentage,
  currentRiskExposureAmount: currentRiskExposureAmount,
  isManuallyProtected: manuallyProtected,
  status: status,
  situation: situation ?? _situation(),
  createdAt: now,
  updatedAt: now,
);

PropFirmPortfolio _portfolio({
  required DateTime now,
  List<PropFirmAccount>? accounts,
  List<RecordedTradeResult> results = const [],
  PortfolioRiskPolicy riskPolicy = const PortfolioRiskPolicy(
    maximumDailyLoss: 500,
    maximumRiskPerTrade: 100,
  ),
  bool portfolioLocked = false,
  PortfolioSessionStatus sessionStatus = PortfolioSessionStatus.draft,
  SessionStopReason? stopReason,
}) => PropFirmPortfolio(
  id: 'portfolio',
  name: 'Portfolio',
  virtualRiskBudget: 3000,
  acknowledged: true,
  accounts:
      accounts ??
      [
        _account(id: 'a', now: now),
        _account(id: 'b', now: now),
        _account(id: 'c', now: now),
      ],
  riskPolicy: riskPolicy,
  strategy: const RotationStrategy(),
  plannedTrades: const [],
  results: results,
  disciplineRecords: const [],
  transitions: const [],
  firmPresets: const [],
  portfolioLocked: portfolioLocked,
  sessionStatus: sessionStatus,
  stopReason: stopReason,
  sessionDateKey:
      '${now.year.toString().padLeft(4, '0')}-'
      '${now.month.toString().padLeft(2, '0')}-'
      '${now.day.toString().padLeft(2, '0')}',
  createdAt: now,
  updatedAt: now,
);

class _FakePortfolioCloudDataSource
    implements PropFirmPortfolioCloudDataSource {
  _FakePortfolioCloudDataSource(
    this.snapshot, {
    this.enforceStartClaim = false,
  });

  PortfolioRepositorySnapshot snapshot;
  PropFirmPortfolio? savedPortfolio;
  final bool enforceStartClaim;
  String? _startClaim;
  int successfulStartReservations = 0;
  final _controller = StreamController<PortfolioRepositorySnapshot>.broadcast();

  @override
  Future<void> archiveWorkspace() async {}

  @override
  Future<void> reserveAccountStart({
    required String accountId,
    required String requestId,
    required DateTime now,
  }) async {
    if (!enforceStartClaim) return;
    if (_startClaim != null && _startClaim != requestId) {
      throw FirebaseException(
        plugin: 'cloud_firestore',
        code: 'session-already-active',
      );
    }
    _startClaim = requestId;
    successfulStartReservations++;
    await Future<void>.delayed(const Duration(milliseconds: 10));
  }

  @override
  Future<void> releaseAccountStart({required String requestId}) async {
    if (_startClaim == requestId) _startClaim = null;
  }

  @override
  Future<PortfolioRepositorySnapshot> read() async => snapshot;

  @override
  Future<void> save(
    PropFirmPortfolio portfolio,
    AccountSequenceWorkspaceState sequenceState,
  ) async {
    savedPortfolio = portfolio;
    snapshot = PortfolioRepositorySnapshot(
      portfolio: portfolio,
      sequenceState: sequenceState,
      syncState: PortfolioSyncState.synchronized,
      lastSynchronizedAt: portfolio.updatedAt,
    );
    _controller.add(snapshot);
  }

  @override
  Stream<PortfolioRepositorySnapshot> watch() => _controller.stream;
}
