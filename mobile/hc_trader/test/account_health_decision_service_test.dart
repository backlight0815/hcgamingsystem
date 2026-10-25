import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_health_decision_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';

void main() {
  const lossService = AccountLossLimitService();
  const healthService = AccountHealthService();
  const portfolioService = PortfolioHealthDecisionService();
  final now = DateTime(2026, 7, 21, 9);

  group('account loss-limit service', () {
    test('uses editable 10% and 5% defaults with planned risk separated', () {
      final account = _account(
        currentBalance: 96000,
        situation: const AccountCurrentSituation(
          maximumLossPercentage: 10,
          dailyLossPercentage: 5,
          dailyStartingBalance: 100000,
          todayRealisedProfitLoss: -3500,
          todayUnrealisedProfitLoss: -400,
          manuallyEnteredOpenRisk: 100,
        ),
      );

      final result = lossService.calculate(account);

      expect(result.maximumLossAmount, 10000);
      expect(result.maximumLossFloor, 90000);
      expect(result.dailyLossAmount, 5000);
      expect(result.dailyLossUsed, 4000);
      expect(result.dailyLossRemaining, 1000);
      expect(result.dailyUsagePercent, 80);
      expect(result.openRiskIncluded, 100);
      expect(result.maximumLossRemaining, 5900);
    });

    test('uses equity and trailing references and never divides by zero', () {
      final equity = _account(
        currentBalance: 99000,
        manuallyEnteredEquity: 94000,
        drawdownType: DrawdownType.equityBased,
        situation: const AccountCurrentSituation(
          maximumLossAmount: 10000,
          dailyLossAmount: 5000,
          dailyStartingEquity: 100000,
          dailyLossCalculationMethod: DailyLossCalculationMethod.equityBased,
        ),
      );
      final equityResult = lossService.calculate(equity);
      expect(equityResult.maximumUsagePercent, 60);
      expect(equityResult.dailyLossUsed, 6000);
      expect(equityResult.dailyLimitReached, isTrue);

      final trailing = _account(
        currentBalance: 101000,
        manuallyEnteredEquity: 100000,
        drawdownType: DrawdownType.trailing,
        situation: const AccountCurrentSituation(
          maximumLossAmount: 10000,
          dailyLossAmount: 5000,
          dailyStartingBalance: 101000,
          trailingReferenceBalance: 105000,
        ),
      );
      expect(lossService.calculate(trailing).maximumLossRemaining, 5000);

      final zero = _account(
        accountSize: 0,
        startingBalance: 0,
        currentBalance: 0,
        dailyLossLimit: 0,
        maximumLossLimit: 0,
        situation: const AccountCurrentSituation(
          maximumLossPercentage: -1,
          dailyLossPercentage: 0,
        ),
      );
      final zeroResult = lossService.calculate(zero);
      expect(zeroResult.dailyUsagePercent.isFinite, isTrue);
      expect(zeroResult.maximumUsagePercent.isFinite, isTrue);
      expect(zeroResult.isEstimated, isTrue);
    });

    test('honours custom amounts and balance-based daily loss', () {
      final account = _account(
        currentBalance: 97000,
        dailyLossLimit: 5000,
        maximumLossLimit: 10000,
        situation: const AccountCurrentSituation(
          maximumLossAmount: 7000,
          dailyLossAmount: 1200,
          dailyStartingBalance: 100000,
          dailyLossCalculationMethod: DailyLossCalculationMethod.balanceBased,
        ),
      );

      final result = lossService.calculate(account);

      expect(result.maximumLossAmount, 7000);
      expect(result.dailyLossAmount, 1200);
      expect(result.dailyLossUsed, 3000);
      expect(result.dailyLimitReached, isTrue);
    });
  });

  group('health and payout assessment', () {
    test('evaluates every payout condition and protects a thin buffer', () {
      final account = _account(
        currentBalance: 100500,
        minimumPayout: 500,
        minimumTradingDays: 5,
        completedTradingDays: 5,
        situation: AccountCurrentSituation(
          dailyStartingBalance: 100500,
          resetHour: 17,
          resetTimeZone: 'America/New_York',
          currentPayoutEligibleProfit: 500,
          nextPayoutRequestDate: now.add(const Duration(days: 4)),
          payoutSafetyBuffer: 100,
        ),
      );

      final result = healthService.assess(account, now: now);

      expect(result.payout.profitReached, isTrue);
      expect(result.payout.tradingDaysComplete, isTrue);
      expect(result.payout.waitingForDate, isTrue);
      expect(result.payout.eligible, isFalse);
      expect(result.payout.daysUntilRequest, 4);
      expect(result.status, AccountHealthStatus.payoutProtection);
      expect(result.maximumSuggestedRisk, 0);
    });

    test('does not call a date-only account payout eligible', () {
      final account = _account(
        currentBalance: 100200,
        minimumPayout: 500,
        minimumTradingDays: 5,
        completedTradingDays: 3,
        consistencyRuleLimit: 30,
        situation: AccountCurrentSituation(
          dailyStartingBalance: 100000,
          resetHour: 0,
          resetTimeZone: 'UTC',
          nextPayoutRequestDate: now.subtract(const Duration(days: 1)),
          consistencyRuleSatisfied: false,
        ),
      );

      final payout = healthService.assessPayout(account, now: now);

      expect(payout.requestDateReached, isTrue);
      expect(payout.profitReached, isFalse);
      expect(payout.tradingDaysComplete, isFalse);
      expect(payout.consistencySatisfied, isFalse);
      expect(payout.eligible, isFalse);
      expect(payout.requirements, hasLength(3));
    });

    test('reports incomplete data while still returning an assessment', () {
      final result = healthService.assess(
        _account(
          drawdownType: DrawdownType.equityBased,
          dailyLossLimit: 0,
          maximumLossLimit: 0,
        ),
        now: now,
      );

      expect(result.missingData.length, greaterThanOrEqualTo(3));
      expect(result.confidence, RecommendationConfidence.insufficientData);
      expect(result.reasons, isNotEmpty);
    });

    test('classifies healthy, caution, high-risk, and near-maximum states', () {
      AccountCurrentSituation situation(double result) =>
          AccountCurrentSituation(
            dailyLossAmount: 5000,
            maximumLossAmount: 10000,
            dailyStartingBalance: 100000,
            todayRealisedProfitLoss: result,
            resetHour: 0,
            resetTimeZone: 'UTC',
          );

      expect(
        healthService
            .assess(_account(situation: situation(0)), now: now)
            .status,
        AccountHealthStatus.healthy,
      );
      expect(
        healthService
            .assess(_account(situation: situation(-2000)), now: now)
            .status,
        AccountHealthStatus.caution,
      );
      expect(
        healthService
            .assess(_account(situation: situation(-3500)), now: now)
            .status,
        AccountHealthStatus.highRisk,
      );
      expect(
        healthService
            .assess(
              _account(currentBalance: 91500, situation: situation(0)),
              now: now,
            )
            .status,
        AccountHealthStatus.critical,
      );
    });

    test('distinguishes waiting-for-date from eligible today', () {
      final base = _account(
        currentBalance: 101000,
        minimumPayout: 500,
        minimumTradingDays: 2,
        completedTradingDays: 2,
        situation: AccountCurrentSituation(
          dailyLossAmount: 5000,
          maximumLossAmount: 10000,
          dailyStartingBalance: 101000,
          resetHour: 0,
          resetTimeZone: 'UTC',
          currentPayoutEligibleProfit: 1000,
          nextPayoutRequestDate: now.add(const Duration(days: 3)),
        ),
      );

      expect(
        healthService.assess(base, now: now).status,
        AccountHealthStatus.waitingForPayoutDate,
      );
      final eligible = base.copyWith(
        nextPayoutDate: now,
        situation: base.situation.copyWith(nextPayoutRequestDate: now),
      );
      final result = healthService.assess(eligible, now: now);
      expect(result.status, AccountHealthStatus.payoutEligible);
      expect(result.payout.eligible, isTrue);
      expect(result.maximumSuggestedRisk, 0);
    });
  });

  group('portfolio decision service', () {
    test('switches away from a locked account to a healthy account', () {
      final locked = _account(
        id: 'locked',
        situation: const AccountCurrentSituation(
          dailyLossAmount: 5000,
          maximumLossAmount: 10000,
          dailyStartingBalance: 100000,
          todayRealisedProfitLoss: -5000,
          resetHour: 0,
          resetTimeZone: 'UTC',
        ),
      );
      final healthy = _account(
        id: 'healthy',
        situation: const AccountCurrentSituation(
          dailyLossAmount: 5000,
          maximumLossAmount: 10000,
          dailyStartingBalance: 100000,
          resetHour: 0,
          resetTimeZone: 'UTC',
        ),
      );
      final result = portfolioService.recommend(
        _portfolio(now, [locked, healthy], activeAccountId: locked.id),
        now: now,
      );

      expect(result.type, PortfolioRecommendationType.switchAccount);
      expect(result.recommendedAccountId, healthy.id);
      expect(result.restrictedAccountIds, contains(locked.id));
      expect(result.maximumSuggestedRisk, lessThanOrEqualTo(300));
    });

    test('emotional risk stops the full portfolio instead of switching', () {
      final affected = _account(
        id: 'affected',
        situation: const AccountCurrentSituation(emotionallyAffected: true),
      );
      final result = portfolioService.recommend(
        _portfolio(now, [affected, _account(id: 'other')]),
        now: now,
      );

      expect(result.type, PortfolioRecommendationType.stopPortfolio);
      expect(result.recommendedAccountId, isNull);
      expect(result.shouldStop, isTrue);
    });

    test('the same losing idea across accounts stops the portfolio', () {
      final first = _account(id: 'first');
      final second = _account(id: 'second');
      final planOne = _plan('plan-1', first.id, now);
      final planTwo = _plan('plan-2', second.id, now);
      final portfolio = _portfolio(now, [first, second]).copyWith(
        plannedTrades: [planOne, planTwo],
        results: [
          RecordedTradeResult(
            id: 'loss-1',
            accountId: first.id,
            plannedTradeId: planOne.id,
            type: TradeResultType.fullSl,
            amount: -300,
            rMultiple: -1,
            resultTime: now,
          ),
          RecordedTradeResult(
            id: 'loss-2',
            accountId: second.id,
            plannedTradeId: planTwo.id,
            type: TradeResultType.fullSl,
            amount: -300,
            rMultiple: -1,
            resultTime: now,
          ),
        ],
      );

      final result = portfolioService.recommend(portfolio, now: now);

      expect(result.type, PortfolioRecommendationType.stopPortfolio);
      expect(
        result.reasons.single.messageKey,
        'portfolio.reason.duplicateLosingIdea',
      );
    });

    test('returns continue, reduced-risk, and account-stop decisions', () {
      AccountCurrentSituation situation(double result) =>
          AccountCurrentSituation(
            dailyLossAmount: 5000,
            maximumLossAmount: 10000,
            dailyStartingBalance: 100000,
            todayRealisedProfitLoss: result,
            resetHour: 0,
            resetTimeZone: 'UTC',
          );
      final healthy = _account(id: 'healthy', situation: situation(0));
      expect(
        portfolioService.recommend(_portfolio(now, [healthy]), now: now).type,
        PortfolioRecommendationType.continueNormally,
      );

      final caution = _account(id: 'caution', situation: situation(-2600));
      final reduced = portfolioService.recommend(
        _portfolio(now, [caution]),
        now: now,
      );
      expect(reduced.type, PortfolioRecommendationType.continueWithReducedRisk);
      expect(reduced.maximumSuggestedRisk, lessThan(300));

      final locked = _account(id: 'locked', situation: situation(-5000));
      final stopped = portfolioService.recommend(
        _portfolio(now, [locked]),
        now: now,
      );
      expect(stopped.type, PortfolioRecommendationType.stopSpecificAccount);
      expect(stopped.shouldStop, isTrue);
    });
  });

  test('schema 1 migrates to schema 4 and requests situation assessment', () {
    final portfolio = _portfolio(now, [_account()]);
    final legacy = portfolio.toJson()
      ..['schema_version'] = 1
      ..remove('current_situation_assessed');

    final migrated = PropFirmPortfolio.fromJson(legacy);

    expect(migrated.schemaVersion, 4);
    expect(migrated.currentSituationAssessed, isFalse);
    expect(migrated.accounts.single.situation.maximumLossPercentage, 10);
    expect(migrated.accounts.single.situation.dailyLossPercentage, 5);
  });
}

PropFirmAccount _account({
  String id = 'account',
  double accountSize = 100000,
  double startingBalance = 100000,
  double currentBalance = 100000,
  double? manuallyEnteredEquity,
  double dailyLossLimit = 5000,
  double maximumLossLimit = 10000,
  DrawdownType drawdownType = DrawdownType.static,
  double minimumPayout = 0,
  int minimumTradingDays = 0,
  int completedTradingDays = 0,
  double consistencyRuleLimit = 0,
  AccountCurrentSituation situation = const AccountCurrentSituation(),
}) => PropFirmAccount(
  id: id,
  name: id,
  propFirmName: 'Test Firm',
  accountSize: accountSize,
  startingBalance: startingBalance,
  currentBalance: currentBalance,
  manuallyEnteredEquity: manuallyEnteredEquity,
  dailyLossLimit: dailyLossLimit,
  maximumLossLimit: maximumLossLimit,
  drawdownType: drawdownType,
  minimumPayout: minimumPayout,
  minimumTradingDays: minimumTradingDays,
  completedTradingDays: completedTradingDays,
  consistencyRuleLimit: consistencyRuleLimit,
  maxRiskPerTrade: 300,
  createdAt: DateTime(2026, 7),
  situation: situation,
);

PropFirmPortfolio _portfolio(
  DateTime now,
  List<PropFirmAccount> accounts, {
  String? activeAccountId,
}) => PropFirmPortfolio(
  id: 'portfolio',
  name: 'Portfolio',
  virtualRiskBudget: 3000,
  acknowledged: true,
  accounts: accounts,
  riskPolicy: const PortfolioRiskPolicy(
    maximumDailyLoss: 1000,
    maximumRiskPerTrade: 300,
    maximumConsecutiveLosses: 3,
  ),
  strategy: const RotationStrategy(),
  plannedTrades: const [],
  results: const [],
  disciplineRecords: const [],
  transitions: const [],
  firmPresets: const [],
  activeAccountId: activeAccountId,
  createdAt: now,
  updatedAt: now,
);

PlannedTrade _plan(String id, String accountId, DateTime now) => PlannedTrade(
  id: id,
  accountId: accountId,
  instrument: 'XAUUSD',
  direction: TradeDirection.buy,
  entryPrice: 3300,
  stopLossPrice: 3290,
  takeProfitPrice: 3320,
  riskAmount: 300,
  riskPercentage: .3,
  expectedReward: 600,
  rewardRiskRatio: 2,
  strategy: 'Breakout',
  setupQuality: 'A',
  tradeReason: 'Same idea',
  invalidationReason: 'Below support',
  session: 'London',
  psychology: const PsychologyCheck(),
  createdAt: now,
  isOpen: false,
);
