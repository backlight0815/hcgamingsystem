import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';

void main() {
  const service = AccountSequenceService();
  final now = DateTime(2026, 7, 21, 9);

  group('dynamic account sequence generation', () {
    test('user-selected eligible starting account always remains first', () {
      final portfolio = _portfolio([
        _account('a', dailyLoss: 50),
        _account('b', dailyLoss: 100),
        _account('c', dailyLoss: 250),
      ]);

      final plan = service.generateSequence(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'b',
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.steps.first.accountId, 'b');
      expect(plan.startingAccountWasReplaced, isFalse);
      expect(plan.steps.map((step) => step.accountId), ['b', 'a', 'c']);
    });

    test('ineligible selected account is replaced by healthiest account', () {
      final portfolio = _portfolio([
        _account('a', dailyLoss: 20),
        _account('b', status: AccountStatus.dailyLocked),
        _account('c', dailyLoss: 200),
      ]);

      final plan = service.generateSequence(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'b',
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.startingAccountWasReplaced, isTrue);
      expect(plan.steps.first.accountId, 'a');
      expect(plan.exclusions.single.accountId, 'b');
      expect(
        plan.exclusions.single.reasons.map((item) => item.messageKey),
        contains('sequence.exclusion.dailyLocked'),
      );
    });

    test('healthier remaining account ranks before higher-loss account', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('a', dailyLoss: 75, currentBalance: 9880),
          _account('b', dailyLoss: 25),
          _account('c', dailyLoss: 350, currentBalance: 9300),
        ]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'b',
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), ['b', 'a', 'c']);
      expect(
        plan.evaluations['a']!.healthScore,
        greaterThan(plan.evaluations['c']!.healthScore),
      );
    });

    test('normalized percentages outrank larger raw currency loss', () {
      final small = _account(
        'small',
        accountSize: 10000,
        startingBalance: 10000,
        currentBalance: 9500,
        dailyLimit: 500,
        maximumLimit: 1000,
        dailyLoss: 250,
      );
      final large = _account(
        'large',
        accountSize: 100000,
        startingBalance: 100000,
        currentBalance: 99300,
        dailyLimit: 5000,
        maximumLimit: 10000,
        dailyLoss: 700,
      );
      final plan = service.generateSequence(
        portfolio: _portfolio([_account('start'), small, large]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'start',
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), [
        'start',
        'large',
        'small',
      ]);
      expect(plan.evaluations['large']!.currentLoss, 700);
      expect(plan.evaluations['small']!.currentLoss, 500);
    });

    test('equal health produces a deterministic alternative step', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('a', dailyLoss: 100),
          _account('b', dailyLoss: 50),
          _account('c', dailyLoss: 110),
        ]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'b',
          tieThreshold: 5,
        ),
        now: now,
      );

      expect(plan.steps, hasLength(2));
      expect(plan.steps.first.accountId, 'b');
      expect(plan.steps[1].alternativeAccountIds, hasLength(1));
      expect(
        {plan.steps[1].accountId, ...plan.steps[1].alternativeAccountIds},
        {'a', 'c'},
      );
    });

    test('payout-protected account is excluded with exact reason', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('a'),
          _account('b'),
          _account(
            'c',
            currentBalance: 10500,
            completedTradingDays: 5,
            protectPayout: true,
          ),
        ]),
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'b'),
        now: now,
      );

      expect(
        plan.steps.expand((step) => [step.accountId]),
        isNot(contains('c')),
      );
      final exclusion = plan.exclusions.firstWhere(
        (item) => item.accountId == 'c',
      );
      expect(
        exclusion.reasons.map((item) => item.messageKey),
        contains('sequence.exclusion.payoutProtected'),
      );
    });

    test(
      'recovery-last mode keeps recovery account behind healthy account',
      () {
        final plan = service.generateSequence(
          portfolio: _portfolio([
            _account('a', dailyLoss: 180),
            _account('b'),
            _account('c', manualStatus: ManualAccountHealthStatus.recovery),
          ]),
          settings: const AccountSequenceSettings(
            selectedStartingAccountId: 'b',
            mode: SequenceMode.recoveryLast,
            tieThreshold: 0,
          ),
          now: now,
        );

        expect(plan.steps.map((step) => step.accountId), ['b', 'a', 'c']);
      },
    );

    test('daily-locked and breached accounts are hard exclusions', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('a'),
          _account('b', status: AccountStatus.dailyLocked),
          _account('c', status: AccountStatus.breached),
        ]),
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'a'),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), ['a']);
      expect(
        plan.exclusions.map((item) => item.accountId),
        containsAll(['b', 'c']),
      );
    });

    test('least-recently-used resolves within the same health band', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('start'),
          _account(
            'recent',
            lastTradeAt: now.subtract(const Duration(hours: 2)),
          ),
          _account('old', lastTradeAt: now.subtract(const Duration(days: 2))),
        ]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'start',
          mode: SequenceMode.leastRecentlyUsed,
          tiePreference: SequenceTiePreference.leastRecentlyUsed,
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), [
        'start',
        'old',
        'recent',
      ]);
    });

    test('configured tie rule chooses the largest daily-loss buffer', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('start', dailyLoss: 25),
          _account('largest', dailyLoss: 0),
          _account('smaller', dailyLoss: 50),
        ]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'start',
          tieThreshold: 5,
          tiePreference: SequenceTiePreference.largestDailyLossBuffer,
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), [
        'start',
        'largest',
        'smaller',
      ]);
      expect(plan.steps[1].alternativeAccountIds, isEmpty);
    });

    test('one-account policy ends after the eligible starting account', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([_account('a'), _account('b'), _account('c')]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'b',
          oneAccountOnly: true,
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), ['b']);
      expect(plan.branches.first.nextCandidateId, isNull);
    });

    test('not-available-today role safely excludes an account', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([_account('a'), _account('b')]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'a',
          roles: {'b': SequenceAccountRole.unavailable},
        ),
        now: now,
      );

      expect(plan.steps.map((step) => step.accountId), ['a']);
      expect(
        plan.exclusions.single.reasons.map((item) => item.messageKey),
        contains('sequence.exclusion.manualUnavailable'),
      );
    });

    test(
      'incomplete non-critical data is explained but can remain eligible',
      () {
        final account = _account('a').copyWith(
          situation: _account('a').situation.copyWith(
            clearDailyStartingBalance: true,
            clearResetHour: true,
            resetTimeZone: '',
          ),
        );
        final evaluation = service.evaluateAccount(
          portfolio: _portfolio([account]),
          account: account,
          settings: const AccountSequenceSettings(),
          now: now,
        );

        expect(evaluation.isEligible, isTrue);
        expect(
          evaluation.dataCompleteness,
          DataCompleteness.partiallyCompleteAccountInformation,
        );
        expect(
          evaluation.riskFactors.map((item) => item.messageKey),
          contains('sequence.risk.incompleteData'),
        );
      },
    );

    test('invalid critical data and no eligible accounts return stop plan', () {
      final invalid = _account('a').copyWith(currentBalance: 0);
      final plan = service.generateSequence(
        portfolio: _portfolio([invalid]),
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'a'),
        now: now,
      );

      expect(plan.steps, isEmpty);
      expect(plan.portfolioStopped, isFalse);
      expect(
        plan.dataCompleteness,
        DataCompleteness.insufficientAccountInformation,
      );
    });

    test('one eligible account produces one conditional step then stop', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('a'),
          _account('b', status: AccountStatus.archived),
        ]),
        settings: const AccountSequenceSettings(selectedStartingAccountId: 'a'),
        now: now,
      );

      expect(plan.steps, hasLength(1));
      expect(plan.stopConditions, isNotEmpty);
      expect(plan.branches, hasLength(5));
    });

    test('different currencies remain comparable through percentages', () {
      final plan = service.generateSequence(
        portfolio: _portfolio([
          _account('usd', currency: 'USD'),
          _account('gbp', currency: 'GBP', dailyLoss: 100),
        ]),
        settings: const AccountSequenceSettings(
          selectedStartingAccountId: 'usd',
          tieThreshold: 0,
        ),
        now: now,
      );

      expect(plan.steps, hasLength(2));
      expect(
        plan.reasons.map((item) => item.messageKey),
        contains('sequence.reason.normalizedCurrencyComparison'),
      );
    });
  });

  group('custom sequence validation and recalculation', () {
    test('unsafe inversion warns while breached ordering is blocked', () {
      final portfolio = _portfolio([
        _account('healthy'),
        _account('risk', dailyLoss: 350),
        _account('breached', status: AccountStatus.breached),
      ]);
      final warning = service.validateCustomOrder(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(),
        accountIds: const ['risk', 'healthy'],
        now: now,
      );
      final blocked = service.validateCustomOrder(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(),
        accountIds: const ['healthy', 'breached'],
        now: now,
      );

      expect(warning.canSave, isTrue);
      expect(warning.requiresOverride, isTrue);
      expect(blocked.canSave, isFalse);
    });

    test('balance and daily loss change recalculates the remaining order', () {
      final before = _portfolio([
        _account('a', dailyLoss: 50),
        _account('b'),
        _account('c', dailyLoss: 200),
      ]);
      const settings = AccountSequenceSettings(
        selectedStartingAccountId: 'b',
        tieThreshold: 0,
      );
      final initial = service.generateSequence(
        portfolio: before,
        settings: settings,
        now: now,
      );
      final damagedA = before.accounts.first.copyWith(
        currentBalance: 9200,
        situation: before.accounts.first.situation.copyWith(
          todayRealisedProfitLoss: -400,
          lossAlreadyRecordedToday: 400,
        ),
      );
      final after = before.copyWith(
        accounts: [damagedA, before.accounts[1], before.accounts[2]],
      );
      final updated = service.generateSequence(
        portfolio: after,
        settings: settings,
        now: now.add(const Duration(minutes: 2)),
        previousPlan: initial,
      );

      expect(initial.steps.map((step) => step.accountId), ['b', 'a', 'c']);
      expect(updated.steps.map((step) => step.accountId), ['b', 'c', 'a']);
      expect(updated.updateReason?.messageKey, 'sequence.update.healthChanged');
    });

    test('payout change removes an account on recalculation', () {
      final portfolio = _portfolio([_account('a'), _account('b')]);
      const settings = AccountSequenceSettings(selectedStartingAccountId: 'a');
      final initial = service.generateSequence(
        portfolio: portfolio,
        settings: settings,
        now: now,
      );
      final protected = portfolio.accounts[1].copyWith(
        currentBalance: 10500,
        completedTradingDays: 5,
        situation: portfolio.accounts[1].situation.copyWith(
          currentPayoutEligibleProfit: 500,
          protectUntilPayout: true,
        ),
      );
      final updated = service.generateSequence(
        portfolio: portfolio.copyWith(
          accounts: [portfolio.accounts.first, protected],
        ),
        settings: settings,
        now: now,
        previousPlan: initial,
      );

      expect(initial.steps, hasLength(2));
      expect(updated.steps, hasLength(1));
      expect(updated.exclusions.single.accountId, 'b');
    });
  });
}

PropFirmAccount _account(
  String id, {
  String currency = 'USD',
  double accountSize = 10000,
  double startingBalance = 10000,
  double currentBalance = 10000,
  double dailyLimit = 500,
  double maximumLimit = 1000,
  double dailyLoss = 0,
  AccountStatus status = AccountStatus.available,
  ManualAccountHealthStatus manualStatus = ManualAccountHealthStatus.healthy,
  bool protectPayout = false,
  int completedTradingDays = 0,
  DateTime? lastTradeAt,
}) => PropFirmAccount(
  id: id,
  name: 'Account ${id.toUpperCase()}',
  propFirmName: 'Firm $id',
  currency: currency,
  accountSize: accountSize,
  startingBalance: startingBalance,
  currentBalance: currentBalance,
  dailyLossLimit: dailyLimit,
  maximumLossLimit: maximumLimit,
  profitTarget: accountSize * .08,
  minimumPayout: 100,
  desiredPayout: 500,
  minimumTradingDays: 5,
  completedTradingDays: completedTradingDays,
  maxRiskPerTrade: mathMin(100, dailyLimit),
  maxTradesPerDay: 10,
  virtualRiskAllocation: 1000,
  status: status,
  lastTradeAt: lastTradeAt,
  situation: AccountCurrentSituation(
    maximumLossAmount: maximumLimit,
    dailyLossAmount: dailyLimit,
    dailyStartingBalance: startingBalance,
    resetHour: 0,
    resetTimeZone: 'UTC',
    todayRealisedProfitLoss: -dailyLoss,
    lossAlreadyRecordedToday: dailyLoss,
    manualStatus: manualStatus,
    protectUntilPayout: protectPayout,
    currentPayoutEligibleProfit: (currentBalance - startingBalance).clamp(
      0,
      double.maxFinite,
    ),
    nextPayoutRequestDate: DateTime(2026, 7, 30),
  ),
  createdAt: DateTime(2026, 7, 1),
);

double mathMin(double left, double right) => left < right ? left : right;

PropFirmPortfolio _portfolio(List<PropFirmAccount> accounts) =>
    PropFirmPortfolio(
      id: 'portfolio',
      name: 'Portfolio',
      virtualRiskBudget: 3000,
      acknowledged: true,
      accounts: accounts,
      riskPolicy: const PortfolioRiskPolicy(
        maximumDailyLoss: 1000,
        maximumRiskPerTrade: 100,
        maximumTradesPerDay: 20,
        maximumConsecutiveLosses: 3,
      ),
      strategy: const RotationStrategy(lockAfterLoss: true),
      plannedTrades: const [],
      results: const [],
      disciplineRecords: const [],
      transitions: const [],
      firmPresets: const [],
      sessionDateKey: '2026-07-21',
      createdAt: DateTime(2026, 7, 1),
      updatedAt: DateTime(2026, 7, 21),
    );
