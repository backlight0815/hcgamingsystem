import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/portfolio_repository_snapshot.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_health_decision_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_health_score_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_validation_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/trading_day_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/simplified_strategy_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('synchronization status', () {
    test('a confirmed remote save is not presented as a save failure', () {
      expect(
        simplifiedVisibleSyncState(
          PortfolioSyncState.failed,
          hasUnsynchronizedChanges: false,
        ),
        PortfolioSyncState.pending,
      );
      expect(
        simplifiedVisibleSyncState(
          PortfolioSyncState.failed,
          hasUnsynchronizedChanges: true,
        ),
        PortfolioSyncState.failed,
      );
    });

    test('an older live snapshot cannot overwrite an unsaved local draft', () {
      final localUpdatedAt = DateTime.utc(2026, 7, 22, 10, 43);
      expect(
        shouldPreserveLocalPortfolioSnapshot(
          hasUnsynchronizedChanges: true,
          localUpdatedAt: localUpdatedAt,
          remoteUpdatedAt: localUpdatedAt.subtract(const Duration(seconds: 1)),
        ),
        isTrue,
      );
      expect(
        shouldPreserveLocalPortfolioSnapshot(
          hasUnsynchronizedChanges: true,
          localUpdatedAt: localUpdatedAt,
          remoteUpdatedAt: localUpdatedAt.add(const Duration(seconds: 1)),
        ),
        isFalse,
      );
    });

    test('cooling countdown formats hours, minutes, and seconds', () {
      expect(
        formatCoolingDuration(const Duration(hours: 1, minutes: 1, seconds: 1)),
        '1h 1m 1s',
      );
      expect(formatCoolingDuration(const Duration(seconds: 125)), '2m 05s');
    });

    test('framework exception prefixes are not shown to users', () {
      expect(
        simplifiedErrorMessage(
          StateError('No account is eligible to start this session.'),
        ),
        'No account is eligible to start this session.',
      );
    });
  });

  group('simplified account calculations and validation', () {
    const limits = AccountLossLimitService();
    const validation = AccountValidationService();
    const health = AccountHealthScoreService();
    final now = DateTime.utc(2026, 7, 22, 9);

    test('fixed and percentage loss limits use one centralized calculator', () {
      final fixed = _account('fixed');
      final percentage = _account(
        'percentage',
        dailyLossType: LossLimitType.percentage,
        maximumLossType: LossLimitType.percentage,
        dailyLossLimit: 5,
        maximumLossLimit: 10,
        situation: const AccountCurrentSituation(dailyStartingBalance: 10000),
      );

      expect(limits.calculate(fixed).dailyLossAmount, 500);
      expect(limits.calculate(fixed).maximumLossAmount, 1000);
      expect(limits.calculate(percentage).dailyLossAmount, 500);
      expect(limits.calculate(percentage).maximumLossAmount, 1000);
      final differentDailyReference = percentage.copyWith(
        situation: percentage.situation.copyWith(dailyStartingBalance: 12000),
      );
      expect(limits.calculate(differentDailyReference).dailyLossAmount, 500);
    });

    test('funded and evaluation fields validate conditionally', () {
      expect(() => validation.validate(_account('funded')), returnsNormally);
      expect(
        () => validation.validate(
          _account(
            'evaluation',
            accountType: PropFirmAccountType.evaluation,
            minimumPayout: 0,
            profitTarget: 800,
          ),
        ),
        returnsNormally,
      );
      expect(
        () => validation.validate(
          _account(
            'invalid-evaluation',
            accountType: PropFirmAccountType.evaluation,
            minimumPayout: 0,
            profitTarget: 0,
          ),
        ),
        throwsFormatException,
      );
      expect(
        () =>
            validation.validate(_account('invalid-funded', minimumPayout: -1)),
        throwsFormatException,
      );
      expect(
        () => validation.validate(
          _account(
            'invalid-percent',
            dailyLossType: LossLimitType.percentage,
            dailyLossLimit: 101,
          ),
        ),
        throwsFormatException,
      );
    });

    test('TP, SL, and BE amounts normalize safely', () {
      expect(validation.normalizeResult(TradeResultType.fullTp, 150), 150);
      expect(validation.normalizeResult(TradeResultType.fullSl, 150), -150);
      expect(validation.normalizeResult(TradeResultType.breakeven, -2.5), -2.5);
      expect(
        () => validation.normalizeResult(TradeResultType.fullSl, -150),
        throwsFormatException,
      );
      expect(
        () => validation.normalizeResult(TradeResultType.fullTp, double.nan),
        throwsFormatException,
      );
    });

    test(
      'health boundaries, target completion, and payout progress are stable',
      () {
        final healthy = health.calculate(_account('healthy'), now: now);
        final moderate = health.calculate(
          _account(
            'moderate',
            situation: const AccountCurrentSituation(
              maximumLossAmount: 1000,
              dailyLossAmount: 500,
              dailyStartingBalance: 10000,
              todayRealisedProfitLoss: -300,
              lossAlreadyRecordedToday: 300,
            ),
          ),
          now: now,
        );
        final warning = health.calculate(
          _account(
            'warning',
            situation: const AccountCurrentSituation(
              maximumLossAmount: 1000,
              dailyLossAmount: 500,
              dailyStartingBalance: 10000,
              todayRealisedProfitLoss: -450,
              lossAlreadyRecordedToday: 450,
            ),
          ),
          now: now,
        );
        final critical = health.calculate(
          _account(
            'critical',
            currentBalance: 9200,
            equity: 9200,
            situation: const AccountCurrentSituation(
              maximumLossAmount: 1000,
              dailyLossAmount: 500,
              dailyStartingBalance: 10000,
              todayRealisedProfitLoss: -400,
              lossAlreadyRecordedToday: 400,
            ),
          ),
          now: now,
        );
        final passed = health.calculate(
          _account(
            'passed',
            accountType: PropFirmAccountType.evaluation,
            currentBalance: 10800,
            equity: 10800,
            minimumPayout: 0,
            profitTarget: 800,
          ),
          now: now,
        );
        final payout = health.calculate(
          _account(
            'payout',
            currentBalance: 10150,
            equity: 10150,
            minimumPayout: 100,
            nextPayoutDate: DateTime.utc(2026, 7, 21),
          ),
          now: now,
        );

        expect(healthy.band, StrategyHealthBand.healthy);
        expect(moderate.band, StrategyHealthBand.moderate);
        expect(warning.band, StrategyHealthBand.warning);
        expect(critical.band, StrategyHealthBand.critical);
        expect(passed.score, 0);
        expect(passed.isEvaluationPassed, isTrue);
        expect(
          passed.unavailableReasonKey,
          'account.unavailable.evaluationPassed',
        );
        expect(payout.payoutProgress, 1);
        expect(payout.isPayoutEligible, isTrue);
      },
    );

    test(
      'profitable recovery improves health while cooldown still blocks trading',
      () {
        final afterYesterdayLoss = _account(
          'recovery',
          currentBalance: 9547.24,
          equity: 9547.24,
          situation: const AccountCurrentSituation(
            maximumLossAmount: 1000,
            dailyLossAmount: 500,
            dailyStartingBalance: 9547.24,
          ),
        ).copyWith(consecutiveLosses: 1);
        final previousScore = health.calculate(afterYesterdayLoss, now: now);
        final recoveredToday = afterYesterdayLoss.copyWith(
          currentBalance: 9756.22,
          manuallyEnteredEquity: 9756.22,
          consecutiveLosses: 0,
          consecutiveWins: 1,
          status: AccountStatus.coolingAfterWin,
          cooldownUntil: now.add(const Duration(minutes: 30)),
          situation: afterYesterdayLoss.situation.copyWith(
            todayRealisedProfitLoss: 208.98,
          ),
        );
        final recoveryScore = health.calculate(recoveredToday, now: now);

        expect(previousScore.score, closeTo(62.4, 0.1));
        expect(recoveryScore.score, greaterThan(previousScore.score));
        expect(recoveryScore.score, greaterThan(0));
        expect(recoveryScore.band, isNot(StrategyHealthBand.unavailable));
        expect(
          recoveryScore.unavailableReasonKey,
          'account.unavailable.cooldown',
        );
        expect(recoveryScore.isEligible, isFalse);

        final afterCooldown = health.calculate(
          recoveredToday,
          now: now.add(const Duration(minutes: 31)),
        );
        expect(afterCooldown.score, greaterThan(recoveryScore.score));
        expect(afterCooldown.isEligible, isTrue);
      },
    );
  });

  group('configured trading day', () {
    const service = TradingDayService();
    const policy = PortfolioRiskPolicy(
      maximumDailyLoss: 500,
      maximumRiskPerTrade: 100,
      resetHourUtc: 17,
      resetTimeZone: 'America/New_York',
    );

    test('uses IANA daylight-saving offsets at the configured reset time', () {
      final springBefore = DateTime.utc(2026, 3, 8, 20, 59);
      final springAfter = DateTime.utc(2026, 3, 8, 21);
      expect(service.tradingDayKey(springBefore, policy), '2026-03-07');
      expect(service.tradingDayKey(springAfter, policy), '2026-03-08');
      expect(
        service.hasTradingDayChanged(
          previousInstant: springBefore,
          currentInstant: springAfter,
          policy: policy,
        ),
        isTrue,
      );

      final autumnBefore = DateTime.utc(2026, 11, 1, 21, 59);
      final autumnAfter = DateTime.utc(2026, 11, 1, 22);
      expect(service.tradingDayKey(autumnBefore, policy), '2026-10-31');
      expect(service.tradingDayKey(autumnAfter, policy), '2026-11-01');
    });
  });

  group('complete simplified workflow', () {
    late LocalAppStorage storage;
    late PropFirmPlannerController controller;
    var now = DateTime.utc(2026, 7, 22, 9);

    setUp(() async {
      now = DateTime.utc(2026, 7, 22, 9);
      SharedPreferences.setMockInitialValues({});
      storage = await LocalAppStorage.create();
      controller = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'simplified-flow-user',
        ),
        now: () => now,
      );
      await controller.initialize();
      await controller.createSimplifiedStrategy();
      await controller.saveAccount(_account('a'));
      await controller.saveAccount(_account('b'));
      await controller.saveAccount(
        _account(
          'c',
          accountType: PropFirmAccountType.evaluation,
          minimumPayout: 0,
          profitTarget: 800,
        ),
      );
    });

    tearDown(() => controller.dispose());

    test('SL rotates, TP updates, session ends, and state restores', () async {
      expect(controller.syncState, PortfolioSyncState.synchronized);
      expect(controller.hasUnsynchronizedChanges, isFalse);
      await controller.startSessionWithAccount('a');
      expect(controller.hasUnsynchronizedChanges, isFalse);
      final sessionId = controller.portfolio!.activeSessionId;
      final sl = controller.previewSimplifiedResult(
        type: TradeResultType.fullSl,
        enteredAmount: 150,
        notes: 'planned stop',
      );
      expect(sl.normalizedAmount, -150);
      expect(sl.balanceAfter, 9850);
      expect(sl.equityAfter, 9850);

      await controller.recordSimplifiedResult(sl);
      final accountA = controller.portfolio!.accounts.firstWhere(
        (account) => account.id == 'a',
      );
      final storedSl = controller.portfolio!.results.single;
      expect(accountA.currentBalance, 9850);
      expect(accountA.referenceEquity, 9850);
      expect(accountA.situation.todayRealisedProfitLoss, -150);
      expect(accountA.status, AccountStatus.dailyLocked);
      expect(storedSl.enteredAmount, 150);
      expect(storedSl.amount, -150);
      expect(storedSl.balanceBefore, 10000);
      expect(storedSl.balanceAfter, 9850);
      expect(storedSl.healthScoreAfter, 0);
      expect(storedSl.sessionId, sessionId);
      expect(controller.simplifiedRecommendedAccountId, isNot('a'));
      expect(controller.hasOpenTradingSession, isTrue);
      await expectLater(
        controller.recordSimplifiedResult(sl),
        throwsStateError,
      );

      final next = controller.simplifiedRecommendedAccountId!;
      now = now.add(const Duration(minutes: 10));
      await controller.startSessionWithAccount(next);
      final tp = controller.previewSimplifiedResult(
        type: TradeResultType.fullTp,
        enteredAmount: 200,
      );
      await controller.recordSimplifiedResult(tp);
      final nextAccount = controller.portfolio!.accounts.firstWhere(
        (account) => account.id == next,
      );
      expect(nextAccount.currentBalance, 10200);
      expect(controller.currentSessionResults, hasLength(2));

      now = now.add(const Duration(minutes: 5));
      await controller.completeTradingSession();
      expect(
        controller.portfolio!.sessionStatus,
        PortfolioSessionStatus.completed,
      );
      expect(controller.portfolio!.activeAccountId, isNull);
      expect(controller.hasUnsynchronizedChanges, isFalse);

      final restored = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'simplified-flow-user',
        ),
        now: () => now,
      );
      await restored.initialize();
      expect(restored.portfolio!.schemaVersion, 4);
      expect(restored.portfolio!.results, hasLength(2));
      expect(
        restored.portfolio!.accounts
            .firstWhere((account) => account.id == 'a')
            .currentBalance,
        9850,
      );
      expect(
        restored.portfolio!.sessionStatus,
        PortfolioSessionStatus.completed,
      );
      restored.dispose();
    });

    test(
      'an account daily maximum rotates to another eligible account',
      () async {
        await controller.saveAccount(
          controller.portfolio!.accounts.first.copyWith(maxTradesPerDay: 1),
        );
        await controller.startSessionWithAccount('a');
        final result = controller.previewSimplifiedResult(
          type: TradeResultType.breakeven,
          enteredAmount: 0,
        );
        await controller.recordSimplifiedResult(result);

        expect(controller.portfolio!.stopReason, isNull);
        expect(controller.dashboardSnapshot!.tradesToday, 1);
        expect(
          controller.accountDailyTradeLimits['a']!.hasReachedLimit,
          isTrue,
        );
        expect(controller.canStartSimplifiedSession, isTrue);
        expect(controller.simplifiedRecommendedAccountId, isNot('a'));
        expect(controller.simplifiedStartPlan!.portfolioStopped, isFalse);
      },
    );
  });

  testWidgets(
    'daily maximum disables Start and explains the stopped state in Chinese',
    (tester) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final now = DateTime.utc(2026, 7, 22, 17, 11);
      final controller = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'daily-limit-overview-user',
        ),
        now: () => now,
      );
      await controller.initialize();
      await controller.createSimplifiedStrategy();
      await controller.saveAccount(
        _account('daily-limit').copyWith(maxTradesPerDay: 1),
      );
      await controller.startSessionWithAccount('daily-limit');
      await controller.recordSimplifiedResult(
        controller.previewSimplifiedResult(
          type: TradeResultType.breakeven,
          enteredAmount: 0,
        ),
      );
      addTearDown(controller.dispose);
      addTearDown(() async => tester.pumpWidget(const SizedBox.shrink()));
      await tester.binding.setSurfaceSize(const Size(540, 1000));
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
          home: Scaffold(
            body: SimplifiedOverviewPage(
              controller: controller,
              portfolio: controller.portfolio!,
              onOpenAccount: (_) {},
            ),
          ),
        ),
      );
      await tester.pump();

      expect(
        find.text(
          '\u5df2\u8fbe\u5230\u6bcf\u65e5\u4ea4\u6613\u4e0a\u9650\uff081/1\uff09',
        ),
        findsOneWidget,
      );
      expect(
        find.text(
          '\u6240\u6709\u53ef\u7528\u8d26\u6237\u5747\u5df2\u8fbe\u5230\u6bcf\u65e5\u4ea4\u6613\u6b21\u6570\u9650\u5236\u3002',
        ),
        findsOneWidget,
      );
      expect(
        find.text('\u6682\u65e0\u4ea4\u6613\u4e2d\u7684\u8d26\u6237'),
        findsOneWidget,
      );
      expect(find.text('\u6682\u65e0\u63a8\u8350'), findsOneWidget);
      expect(find.text('\u672a\u5ba1\u6838'), findsNothing);
      final action = tester.widget<FilledButton>(
        find.byKey(const Key('primary-trading-action')),
      );
      expect(action.onPressed, isNull);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('overview shows a live cooldown and a non-zero recovery score', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final now = DateTime.utc(2026, 7, 22, 11, 14);
    final controller = PropFirmPlannerController(
      repository: PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'cooldown-overview-user',
      ),
      now: () => now,
    );
    await controller.initialize();
    await controller.createSimplifiedStrategy();
    final recovered =
        _account(
          'recovered',
          currentBalance: 9756.22,
          equity: 9756.22,
          situation: const AccountCurrentSituation(
            maximumLossAmount: 1000,
            dailyLossAmount: 500,
            dailyStartingBalance: 9547.24,
            todayRealisedProfitLoss: 208.98,
          ),
        ).copyWith(
          status: AccountStatus.coolingAfterWin,
          consecutiveWins: 1,
          cooldownUntil: now.add(const Duration(minutes: 30)),
        );
    await controller.saveAccount(recovered);
    addTearDown(controller.dispose);
    addTearDown(() async => tester.pumpWidget(const SizedBox.shrink()));
    await tester.binding.setSurfaceSize(const Size(1280, 1100));
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
        home: Scaffold(
          body: SimplifiedOverviewPage(
            controller: controller,
            portfolio: controller.portfolio!,
            onOpenAccount: (_) {},
          ),
        ),
      ),
    );
    await tester.pump();

    expect(
      find.textContaining('Cooling period remaining: 30m 00s'),
      findsOneWidget,
    );
    expect(find.text('73'), findsWidgets);
    expect(find.text('0'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('account editor shows only fields relevant to its type', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Builder(
          builder: (context) => Scaffold(
            body: FilledButton(
              onPressed: () =>
                  showSimplifiedAccountEditor(context, _account('editor')),
              child: const Text('Open editor'),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Open editor'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Minimum payout amount'),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Minimum payout amount'), findsOneWidget);
    expect(find.text('Set next payout date'), findsOneWidget);
    expect(find.text('Profit target'), findsNothing);

    await tester.scrollUntilVisible(
      find.text('Evaluation Account'),
      -350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Evaluation Account'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Profit target'),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Profit target'), findsOneWidget);
    expect(find.text('Minimum payout amount'), findsNothing);
    expect(find.text('Set next payout date'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('account editor validates the per-account daily trade limit', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Builder(
          builder: (context) => Scaffold(
            body: FilledButton(
              onPressed: () =>
                  showSimplifiedAccountEditor(context, _account('limit')),
              child: const Text('Open limit editor'),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Open limit editor'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('daily-trade-limit-field')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Daily Trading Controls'), findsOneWidget);
    expect(find.text('Enable daily trade limit'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('daily-trade-limit-field')),
      '0',
    );
    await tester.tap(find.byKey(const Key('save-simplified-account')));
    await tester.pump();
    expect(find.text('Enter a whole number from 1 to 100.'), findsWidgets);
    expect(find.text('Edit Account'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

PropFirmAccount _account(
  String id, {
  PropFirmAccountType accountType = PropFirmAccountType.funded,
  LossLimitType dailyLossType = LossLimitType.fixedAmount,
  LossLimitType maximumLossType = LossLimitType.fixedAmount,
  double dailyLossLimit = 500,
  double maximumLossLimit = 1000,
  double currentBalance = 10000,
  double? equity,
  double minimumPayout = 100,
  double profitTarget = 0,
  DateTime? nextPayoutDate,
  AccountCurrentSituation? situation,
}) => PropFirmAccount(
  id: id,
  name: 'Account ${id.toUpperCase()}',
  identifier: 'MASK-$id-001',
  propFirmName: 'Test Firm',
  currency: 'USD',
  accountType: accountType,
  accountSize: 10000,
  startingBalance: 10000,
  currentBalance: currentBalance,
  manuallyEnteredEquity: equity ?? currentBalance,
  dailyLossLimit: dailyLossLimit,
  maximumLossLimit: maximumLossLimit,
  dailyLossType: dailyLossType,
  maximumLossType: maximumLossType,
  profitTarget: profitTarget,
  minimumPayout: minimumPayout,
  nextPayoutDate: nextPayoutDate,
  maxRiskPerTrade: 100,
  maxTradesPerDay: 10,
  situation:
      situation ??
      const AccountCurrentSituation(
        maximumLossAmount: 1000,
        dailyLossAmount: 500,
        dailyStartingBalance: 10000,
      ),
  createdAt: DateTime.utc(2026, 7, 1),
);
