import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/account_health_translations.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/localization/multi_account_prop_firm_translations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_rotation_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_calculation_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/scenario_simulation_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('prop firm calculations', () {
    const service = PropFirmCalculationService();

    test('planned risk and directional reward-to-risk are deterministic', () {
      expect(service.plannedRiskAmount(10000, 1), 100);
      expect(
        service.rewardRiskRatio(
          direction: TradeDirection.buy,
          entryPrice: 100,
          stopLossPrice: 95,
          takeProfitPrice: 110,
        ),
        2,
      );
      expect(
        service.rewardRiskRatio(
          direction: TradeDirection.sell,
          entryPrice: 100,
          stopLossPrice: 105,
          takeProfitPrice: 90,
        ),
        2,
      );
      expect(
        () => service.rewardRiskRatio(
          direction: TradeDirection.buy,
          entryPrice: 100,
          stopLossPrice: 101,
          takeProfitPrice: 110,
        ),
        throwsFormatException,
      );
    });

    test('portfolio risk, payout, recovery, and reset time are safe', () {
      expect(
        service.portfolioRiskRemaining(
          portfolioDailyLossLimit: 200,
          totalRealisedLossToday: 75,
          totalActivePlannedRisk: 50,
        ),
        75,
      );
      final account = _account(
        id: 'a',
        currentBalance: 9700,
        minimumPayout: 150,
        desiredPayout: 500,
      );
      final recovery = service.recoverySummary(
        account,
        mode: RecoveryMode.conservative,
      );
      expect(recovery.recoveryRequired, 300);
      expect(recovery.riskPerTrade, 50);
      expect(recovery.requiredNetR, 6);
      expect(recovery.fullWinsAtTwoR, 3);

      final profitable = account.copyWith(currentBalance: 10600);
      final payout = service.payoutSummary(
        profitable,
        now: DateTime(2026, 7, 21),
      );
      expect(payout.requiredGrossProfit, 500);
      expect(payout.progress, 1.2);
      expect(payout.estimatedNetPayout, 400);

      const policy = PortfolioRiskPolicy(
        maximumDailyLoss: 200,
        maximumRiskPerTrade: 100,
        resetHourUtc: 5,
        resetUtcOffsetMinutes: 480,
      );
      expect(
        service.tradingDayKey(DateTime.utc(2026, 7, 20, 21), policy),
        '2026-07-21',
      );
      expect(
        service.tradingDayKey(DateTime.utc(2026, 7, 20, 20), policy),
        '2026-07-20',
      );
    });
  });

  group('state machine and recommendation engine', () {
    const rotation = AccountRotationService();
    final now = DateTime(2026, 7, 21, 9);

    test('TP cools account and rotates to another eligible account', () {
      final portfolio = _portfolio(now);
      final updated = rotation.transitionAfterResult(
        portfolio.accounts.first,
        RecordedTradeResult(
          id: 'r1',
          accountId: 'a',
          type: TradeResultType.fullTp,
          amount: 200,
          rMultiple: 2,
          resultTime: now,
        ),
        portfolio.strategy,
        portfolio.riskPolicy,
      );
      expect(updated.status, AccountStatus.coolingAfterWin);
      expect(updated.currentBalance, 10200);
      final recommendation = rotation.recommend(
        portfolio.copyWith(
          accounts: [updated, portfolio.accounts[1], portfolio.accounts[2]],
        ),
        now: now,
      );
      expect(recommendation.accountId, isNot('a'));
      expect(recommendation.exclusions['a'], isNotEmpty);
      expect(recommendation.reasons, isNotEmpty);
    });

    test('SL locks account and hard exclusions remain explainable', () {
      final portfolio = _portfolio(now);
      final updated = rotation.transitionAfterResult(
        portfolio.accounts.first,
        RecordedTradeResult(
          id: 'r1',
          accountId: 'a',
          type: TradeResultType.fullSl,
          amount: -100,
          rMultiple: -1,
          resultTime: now,
        ),
        portfolio.strategy,
        portfolio.riskPolicy,
      );
      expect(updated.status, AccountStatus.dailyLocked);
      expect(updated.consecutiveLosses, 1);
      final result = rotation.recommend(
        portfolio.copyWith(
          accounts: [
            updated,
            portfolio.accounts[1].copyWith(status: AccountStatus.paused),
            portfolio.accounts[2].copyWith(
              status: AccountStatus.payoutProtection,
            ),
          ],
        ),
        now: now,
      );
      expect(result.shouldStop, isTrue);
      expect(result.exclusions.length, 3);
    });

    test('duplicate symbol, direction, and combined risk produce warnings', () {
      final existing = _plan('p1', 'a', now, risk: 150);
      final portfolio = _portfolio(now).copyWith(plannedTrades: [existing]);
      final candidate = _plan('p2', 'b', now, risk: 100);
      final warnings = rotation.duplicateExposureWarnings(portfolio, candidate);
      expect(warnings.length, 3);
      expect(
        warnings.map((item) => item.messageKey),
        contains(
          'Combined planned risk exceeds the portfolio daily loss limit.',
        ),
      );
    });
  });

  group('controller acceptance flows', () {
    late LocalAppStorage storage;
    late PropFirmPlannerController controller;
    var now = DateTime(2026, 7, 21, 9);

    setUp(() async {
      now = DateTime(2026, 7, 21, 9);
      SharedPreferences.setMockInitialValues({});
      storage = await LocalAppStorage.create();
      controller = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'guest',
        ),
        now: () => now,
      );
      await controller.initialize();
      await controller.createStarterPortfolio(virtualRiskBudget: 3000);
    });

    test('healthy rotation and global two-loss stop persist locally', () async {
      expect(controller.portfolio!.accounts, hasLength(3));
      expect(controller.recommendation.accountId, isNotNull);
      final first = controller.recommendation.accountId!;
      await controller.startSession();
      await controller.recordResult(
        RecordedTradeResult(
          id: 'win',
          accountId: first,
          type: TradeResultType.fullTp,
          amount: 200,
          rMultiple: 2,
          resultTime: now,
        ),
      );
      expect(
        controller.portfolio!.accounts
            .firstWhere((item) => item.id == first)
            .status,
        AccountStatus.coolingAfterWin,
      );
      final second = controller.recommendation.accountId!;
      expect(second, isNot(first));
      now = now.add(const Duration(minutes: 5));
      await controller.recordResult(
        RecordedTradeResult(
          id: 'loss-1',
          accountId: second,
          type: TradeResultType.fullSl,
          amount: -100,
          rMultiple: -1,
          resultTime: now,
        ),
      );
      expect(controller.recommendation.shouldStop, isFalse);
      final third = controller.recommendation.accountId!;
      now = now.add(const Duration(minutes: 5));
      await controller.recordResult(
        RecordedTradeResult(
          id: 'loss-2',
          accountId: third,
          type: TradeResultType.fullSl,
          amount: -100,
          rMultiple: -1,
          resultTime: now,
        ),
      );
      expect(controller.portfolio!.portfolioLocked, isTrue);
      expect(controller.recommendation.shouldStop, isTrue);
      expect(
        controller.portfolio!.accounts.where(
          (item) => item.status == AccountStatus.portfolioLocked,
        ),
        isNotEmpty,
      );

      final reloaded = PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'guest',
      ).load();
      expect(reloaded!.results, hasLength(3));
      expect(reloaded.portfolioLocked, isTrue);
    });

    test('manual override is recorded with its reason', () async {
      await controller.emergencyLock();
      await controller.recordOverride('Reviewed the stop and reduced scope.');
      expect(controller.portfolio!.portfolioLocked, isFalse);
      expect(
        controller.portfolio!.disciplineRecords.last.overrideReason,
        'Reviewed the stop and reduced scope.',
      );
    });

    test(
      'editable payout presets survive export and validated import',
      () async {
        final source = controller.portfolio!.firmPresets.first;
        await controller.updateFirmPreset(
          source.copyWith(minimumPayout: 175, updatedAt: now),
        );
        final json = controller.exportJson();
        expect(json, contains('175.0'));
        await controller.importJson(json);
        expect(controller.portfolio!.firmPresets.first.minimumPayout, 175);
        expect(
          () => controller.repository.importJson('[]'),
          throwsFormatException,
        );
        expect(controller.exportCsv(), contains('result_id'));
      },
    );

    test(
      'guest portfolio migrates once into signed-in local storage',
      () async {
        final signedIn = PropFirmPlannerController(
          repository: PropFirmPortfolioRepository(
            storage: storage,
            ownerId: 'user-123',
          ),
          now: () => now,
        );
        await signedIn.initialize();
        expect(signedIn.portfolio, isNotNull);
        expect(signedIn.portfolio!.accounts, hasLength(3));

        final reloaded = PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'user-123',
        ).load();
        expect(reloaded!.accounts.map((item) => item.id).toSet(), hasLength(3));
        signedIn.dispose();
      },
    );

    test(
      'situation assessment, override, reset, and audit are persisted',
      () async {
        final payoutDate = DateTime(2026, 7, 28);
        final account = controller.portfolio!.accounts.first;
        final updated = account.copyWith(
          currentBalance: 9850,
          situation: account.situation.copyWith(
            dailyStartingBalance: 10000,
            resetHour: 17,
            resetTimeZone: 'UTC',
            resetUtcOffsetMinutes: 480,
            todayRealisedProfitLoss: -150,
            lossAlreadyRecordedToday: 150,
            manuallyEnteredOpenRisk: 50,
            nextPayoutRequestDate: payoutDate,
            payoutRequestStatus: PayoutRequestStatus.waitingForDate,
            payoutHistory: [
              PayoutHistoryEntry(
                id: 'payout-1',
                status: PayoutRequestStatus.waitingForDate,
                createdAt: now,
              ),
            ],
            updatedAt: now,
          ),
        );
        await controller.saveAccount(updated);
        await controller.completeCurrentSituationAssessment({
          for (final item in controller.portfolio!.activeAccounts)
            item.id: item,
        });
        expect(controller.portfolio!.currentSituationAssessed, isTrue);

        await expectLater(
          controller.overrideAccountHealthStatus(
            account.id,
            ManualAccountHealthStatus.paused,
            'no',
          ),
          throwsA(isA<FormatException>()),
        );
        await controller.overrideAccountHealthStatus(
          account.id,
          ManualAccountHealthStatus.paused,
          'Reviewed current drawdown manually.',
        );
        expect(
          controller.portfolio!.disciplineRecords.last.type,
          'account_health_override',
        );
        expect(
          controller.portfolio!.disciplineRecords.last.overrideReason,
          'Reviewed current drawdown manually.',
        );

        await controller.resetAccountToday(account.id);
        final reset = controller.portfolio!.accounts.firstWhere(
          (item) => item.id == account.id,
        );
        expect(reset.situation.todayRealisedProfitLoss, 0);
        expect(reset.situation.lossAlreadyRecordedToday, 0);
        expect(reset.situation.manuallyEnteredOpenRisk, 0);
        expect(reset.situation.nextPayoutRequestDate, payoutDate);
        expect(reset.situation.payoutHistory, hasLength(1));
        expect(
          controller.portfolio!.disciplineRecords.last.type,
          'account_daily_reset',
        );
      },
    );

    test('account reset follows its configured reset hour', () async {
      final account = controller.portfolio!.accounts.first;
      await controller.saveAccount(
        account.copyWith(
          situation: account.situation.copyWith(
            dailyStartingBalance: 10000,
            resetHour: 17,
            resetTimeZone: 'UTC',
            resetUtcOffsetMinutes: 480,
            todayRealisedProfitLoss: -100,
            lossAlreadyRecordedToday: 100,
            updatedAt: now,
          ),
        ),
      );
      now = DateTime(2026, 7, 21, 18);
      await controller.refreshHealthAssessment();
      final reset = controller.portfolio!.accounts.firstWhere(
        (item) => item.id == account.id,
      );
      expect(reset.situation.todayRealisedProfitLoss, 0);
      expect(reset.situation.lossAlreadyRecordedToday, 0);
      expect(
        controller.portfolio!.disciplineRecords.where(
          (item) => item.type == 'account_daily_reset',
        ),
        isNotEmpty,
      );
    });
  });

  group('hypothetical simulation', () {
    test(
      'is deterministic, rounded by UI, and reduced risk lowers drawdown',
      () {
        const service = ScenarioSimulationService();
        const configuration = SimulationConfiguration(
          trials: 100,
          tradeCount: 50,
          seed: 9,
        );
        final first = service.run(configuration);
        final second = service.run(configuration);
        expect(first.payoutFrequency, second.payoutFrequency);
        expect(first.breachFrequency, inInclusiveRange(0, 1));
        expect(first.payoutFrequency, inInclusiveRange(0, 1));
        expect(first.samplePath, isNotEmpty);
        expect(
          first.reducedRiskMedianDrawdown,
          lessThanOrEqualTo(first.medianDrawdown),
        );
      },
    );
  });

  group('localization and responsive widget', () {
    test('all planner catalog entries have Chinese translations', () {
      const l10n = AppLocalizations(Locale('zh'));
      expect(multiAccountPropFirmZh.length, greaterThan(200));
      for (final entry in multiAccountPropFirmZh.entries) {
        expect(
          l10n.text(entry.key),
          isNot(entry.key),
          reason: 'Missing planner translation: ${entry.key}',
        );
      }
      for (final entry in accountHealthZh.entries) {
        expect(
          l10n.text(entry.key),
          isNot(entry.key),
          reason: 'Missing account health translation: ${entry.key}',
        );
      }
      for (final key in const [
        'Funded Account Rotation & Payout Planner',
        'Use funded account planner as guest',
        'Portfolio dashboard',
        'Daily control room',
        'Pre-trade decision assistant',
        'Conservative recovery planner',
        'Hypothetical scenario simulator',
        'Education centre',
        'The same instrument and direction is already planned on another account.',
      ]) {
        expect(l10n.text(key), isNot(key), reason: 'Missing translation: $key');
      }
    });

    testWidgets('guest can create portfolio and desktop uses navigation rail', (
      tester,
    ) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      await tester.binding.setSurfaceSize(const Size(1280, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('zh'),
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
      expect(find.text('一次配置，每次只交易一个账户。'), findsOneWidget);
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pump();
      await tester.tap(find.text('创建策略'));
      await tester.pumpAndSettle();
      expect(find.byType(NavigationRail), findsOneWidget);
      expect(find.text('尚未配置账户'), findsOneWidget);
      await tester.tap(find.text('教程与常见问题'));
      await tester.pumpAndSettle();
      expect(find.text('教程、指南与常见问题'), findsOneWidget);
      expect(find.text('什么是多账户策略？'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets(
      'health dashboard selects healthy account and restricts loss and payout accounts',
      (tester) async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final now = DateTime(2026, 7, 21, 9);
        final portfolio = _portfolio(now).copyWith(
          currentSituationAssessed: true,
          accounts: [
            _account(
              id: 'a',
              situation: const AccountCurrentSituation(
                dailyLossAmount: 500,
                maximumLossAmount: 1000,
                dailyStartingBalance: 10000,
                todayRealisedProfitLoss: -440,
                resetHour: 0,
                resetTimeZone: 'UTC',
              ),
            ),
            _account(
              id: 'b',
              situation: const AccountCurrentSituation(
                dailyLossAmount: 500,
                maximumLossAmount: 1000,
                dailyStartingBalance: 10000,
                resetHour: 0,
                resetTimeZone: 'UTC',
              ),
            ),
            _account(
              id: 'c',
              currentBalance: 10500,
              minimumTradingDays: 3,
              completedTradingDays: 3,
              situation: AccountCurrentSituation(
                dailyLossAmount: 500,
                maximumLossAmount: 1000,
                dailyStartingBalance: 10500,
                resetHour: 0,
                resetTimeZone: 'UTC',
                currentPayoutEligibleProfit: 500,
                nextPayoutRequestDate: now.add(const Duration(days: 7)),
                protectUntilPayout: true,
              ),
            ),
          ],
        );
        await PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'guest',
        ).save(portfolio);
        await tester.binding.setSurfaceSize(const Size(1280, 1000));
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
              now: () => now,
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Your accounts'), findsOneWidget);
        expect(find.text('Recommended next'), findsOneWidget);
        expect(find.text('Account B'), findsWidgets);
        await tester.drag(find.byType(ListView).at(0), const Offset(0, -1100));
        await tester.pumpAndSettle();
        expect(find.text('Warning'), findsWidgets);
        expect(find.text('account.unavailable.payoutProtected'), findsNothing);
        expect(find.text('Payout protected'), findsWidgets);
      },
    );

    testWidgets('mobile health workspace is swipeable and opens its updater', (
      tester,
    ) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final now = DateTime(2026, 7, 21, 9);
      final portfolio = _portfolio(now).copyWith(
        currentSituationAssessed: true,
        accounts: [
          for (final account in _portfolio(now).accounts)
            account.copyWith(
              situation: const AccountCurrentSituation(
                dailyLossAmount: 500,
                maximumLossAmount: 1000,
                dailyStartingBalance: 10000,
                resetHour: 0,
                resetTimeZone: 'UTC',
              ),
            ),
        ],
      );
      await PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'mobile-health',
      ).save(portfolio);
      await tester.binding.setSurfaceSize(const Size(390, 844));
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
          home: MediaQuery(
            data: const MediaQueryData(size: Size(390, 844)),
            child: PropFirmPlannerScreen(
              storage: storage,
              ownerId: 'mobile-health',
              showBackButton: false,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        find.byType(NavigationBar),
        findsOneWidget,
        reason: tester
            .widgetList<Text>(find.byType(Text))
            .map((item) => item.data)
            .whereType<String>()
            .take(20)
            .join(' | '),
      );
      final navigation = tester.widget<NavigationBar>(
        find.byType(NavigationBar),
      );
      navigation.onDestinationSelected?.call(2);
      await tester.pumpAndSettle();
      expect(find.text('Tutorial, Guidelines & FAQ'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}

PropFirmPortfolio _portfolio(DateTime now) => PropFirmPortfolio(
  id: 'portfolio',
  name: 'Portfolio',
  virtualRiskBudget: 3000,
  acknowledged: true,
  accounts: [
    _account(id: 'a'),
    _account(id: 'b'),
    _account(id: 'c'),
  ],
  riskPolicy: const PortfolioRiskPolicy(
    maximumDailyLoss: 200,
    maximumRiskPerTrade: 100,
  ),
  strategy: RotationStrategy.forTemplate(RotationTemplate.balanced),
  plannedTrades: const [],
  results: const [],
  disciplineRecords: const [],
  transitions: const [],
  firmPresets: [
    PropFirmRulePreset(
      id: 'the5ers',
      firmName: 'The5ers',
      minimumPayout: 150,
      updatedAt: now,
    ),
  ],
  sessionDateKey: '2026-07-21',
  createdAt: now,
  updatedAt: now,
);

PropFirmAccount _account({
  required String id,
  double currentBalance = 10000,
  double minimumPayout = 150,
  double desiredPayout = 500,
  int minimumTradingDays = 0,
  int completedTradingDays = 0,
  AccountCurrentSituation situation = const AccountCurrentSituation(),
}) => PropFirmAccount(
  id: id,
  name: 'Account ${id.toUpperCase()}',
  identifier: '1000$id',
  propFirmName: 'Test Firm',
  accountSize: 10000,
  startingBalance: 10000,
  currentBalance: currentBalance,
  dailyLossLimit: 500,
  maximumLossLimit: 1000,
  minimumPayout: minimumPayout,
  desiredPayout: desiredPayout,
  minimumTradingDays: minimumTradingDays,
  completedTradingDays: completedTradingDays,
  maxRiskPerTrade: 100,
  virtualRiskAllocation: 1000,
  situation: situation,
  createdAt: DateTime(2026, 7, 1),
);

PlannedTrade _plan(
  String id,
  String accountId,
  DateTime now, {
  required double risk,
}) => PlannedTrade(
  id: id,
  accountId: accountId,
  instrument: 'XAUUSD',
  direction: TradeDirection.buy,
  entryPrice: 3300,
  stopLossPrice: 3290,
  takeProfitPrice: 3320,
  riskAmount: risk,
  riskPercentage: 1,
  expectedReward: risk * 2,
  rewardRiskRatio: 2,
  strategy: 'Breakout',
  setupQuality: 'A',
  tradeReason: 'Valid breakout',
  invalidationReason: 'Below support',
  session: 'London',
  psychology: const PsychologyCheck(),
  createdAt: now,
);
