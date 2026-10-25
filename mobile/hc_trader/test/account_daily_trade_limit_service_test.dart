import 'package:flutter_test/flutter_test.dart';

import 'package:hc_trader/features/multi_account_prop_firm/domain/account_daily_trade_limit_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/trading_day_service.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';

void main() {
  final now = DateTime.utc(2026, 7, 22, 12);
  const service = AccountDailyTradeLimitService();

  test(
    'each account uses its own limit and only reached account is excluded',
    () {
      final portfolio = _portfolio(
        now,
        accounts: [
          _account('a', now, limit: 3),
          _account('b', now, limit: 5),
          _account('c', now, limit: 2),
        ],
        results: [
          ..._results('a', now, 3),
          ..._results('b', now, 2),
          ..._results('c', now, 1),
        ],
      );

      expect(
        service.evaluate(portfolio, portfolio.accounts[0], now: now).status,
        DailyTradeLimitStatus.reached,
      );
      expect(
        service
            .evaluate(portfolio, portfolio.accounts[1], now: now)
            .remainingTrades,
        3,
      );
      expect(
        service
            .evaluate(portfolio, portfolio.accounts[2], now: now)
            .remainingTrades,
        1,
      );

      final plan = const AccountSequenceService().generateSequence(
        portfolio: portfolio,
        settings: const AccountSequenceSettings(),
        now: now,
      );
      expect(plan.portfolioStopped, isFalse);
      expect(plan.steps.map((step) => step.accountId), containsAll(['b', 'c']));
      expect(plan.steps.map((step) => step.accountId), isNot(contains('a')));
      expect(
        plan.evaluations['a']!.exclusionReasons
            .singleWhere(
              (reason) =>
                  reason.messageKey == 'sequence.exclusion.tradeLimitReached',
            )
            .values,
        {'current': 3, 'limit': 3},
      );
    },
  );

  test('duplicates and non-executed records do not consume capacity', () {
    final executed = _result('same-id', 'a', now, TradeResultType.fullTp);
    final portfolio = _portfolio(
      now,
      accounts: [_account('a', now, limit: 5)],
      results: [
        executed,
        executed,
        _result('cancelled', 'a', now, TradeResultType.cancelled),
        _result('missed', 'a', now, TradeResultType.missedTrade),
        _result('adjustment', 'a', now, TradeResultType.manualAdjustment),
        _result('other-account', 'b', now, TradeResultType.fullSl),
      ],
    );
    final result = service.evaluate(
      portfolio,
      portfolio.accounts.single,
      now: now,
    );
    expect(result.currentTrades, 1);
    expect(result.remainingTrades, 4);
  });

  test('disabled limit reports unlimited account-level availability', () {
    final account = _account('a', now, limit: 1, enabled: false);
    final portfolio = _portfolio(
      now,
      accounts: [account],
      results: _results('a', now, 4),
    );
    final result = service.evaluate(portfolio, account, now: now);
    expect(result.status, DailyTradeLimitStatus.disabled);
    expect(result.remainingTrades, isNull);
    expect(result.canStartTrading, isTrue);
  });

  test('legacy portfolio limit migrates as the missing account fallback', () {
    final source = _portfolio(
      now,
      accounts: [_account('a', now, limit: 3)],
      policy: const PortfolioRiskPolicy(
        maximumDailyLoss: 500,
        maximumRiskPerTrade: 100,
        maximumTradesPerDay: 7,
        stopAfterMaximumTrades: true,
      ),
    ).toJson();
    final account =
        Map<String, Object?>.from((source['accounts']! as List).single as Map)
          ..remove('daily_trade_limit')
          ..remove('daily_trade_limit_enabled')
          ..remove('max_trades_per_day');
    source['accounts'] = [account];

    final migrated = PropFirmPortfolio.fromJson(
      Map<String, dynamic>.from(source),
    );
    expect(migrated.accounts.single.maxTradesPerDay, 7);
    expect(migrated.accounts.single.dailyTradeLimitEnabled, isTrue);
  });

  test('different account limits round-trip through the Firestore model', () {
    final source = _portfolio(
      now,
      accounts: [
        _account('a', now, limit: 2),
        _account('b', now, limit: 5, enabled: false),
      ],
    );
    final restored = PropFirmPortfolio.fromJson(
      Map<String, dynamic>.from(source.toJson()),
    );
    expect(restored.accounts[0].dailyTradeLimit, 2);
    expect(restored.accounts[1].dailyTradeLimit, 5);
    expect(restored.accounts[1].dailyTradeLimitEnabled, isFalse);
  });

  test('trading day and next reset use configured timezone boundary', () {
    const policy = PortfolioRiskPolicy(
      maximumDailyLoss: 500,
      maximumRiskPerTrade: 100,
      resetTimeZone: 'Asia/Singapore',
      resetHourUtc: 6,
      resetMinute: 30,
    );
    const tradingDays = TradingDayService();
    final before = DateTime.utc(2026, 7, 21, 22, 29); // 06:29 Singapore
    final after = before.add(const Duration(minutes: 2));
    expect(tradingDays.tradingDayKey(before, policy), '2026-07-21');
    expect(tradingDays.tradingDayKey(after, policy), '2026-07-22');
    expect(
      tradingDays.nextResetAt(before, policy),
      DateTime.utc(2026, 7, 21, 22, 30),
    );
  });

  test('legacy portfolio maximum-trade stop is safely unlocked', () {
    final stopped = _portfolio(now, accounts: [_account('a', now)]).copyWith(
      accounts: [
        _account('a', now).copyWith(status: AccountStatus.portfolioLocked),
      ],
      sessionStatus: PortfolioSessionStatus.stopped,
      portfolioLocked: true,
      stopReason: SessionStopReason(
        code: SessionStopReasonCode.maximumTrades,
        titleKey: 'Maximum-trades rule triggered',
        descriptionKey: 'Session trades reached the configured maximum.',
        stoppedAt: now,
        stoppedBy: SessionStoppedBy.automaticRule,
      ),
    );
    final migrated = migrateLegacyMaximumTradeStop(stopped, now: now);
    expect(migrated.portfolioLocked, isFalse);
    expect(migrated.stopReason, isNull);
    expect(migrated.sessionStatus, PortfolioSessionStatus.ready);
    expect(migrated.accounts.single.status, AccountStatus.available);
  });
}

PropFirmAccount _account(
  String id,
  DateTime now, {
  int limit = 3,
  bool enabled = true,
}) => PropFirmAccount(
  id: id,
  name: 'Account $id',
  propFirmName: 'Firm',
  accountSize: 10000,
  startingBalance: 10000,
  currentBalance: 10000,
  dailyLossLimit: 500,
  maximumLossLimit: 1000,
  maxRiskPerTrade: 100,
  maxTradesPerDay: limit,
  dailyTradeLimitEnabled: enabled,
  situation: const AccountCurrentSituation(
    maximumLossAmount: 1000,
    dailyLossAmount: 500,
    dailyStartingBalance: 10000,
    dailyStartingEquity: 10000,
    resetTimeZone: 'UTC',
  ),
  createdAt: now,
  updatedAt: now,
);

PropFirmPortfolio _portfolio(
  DateTime now, {
  required List<PropFirmAccount> accounts,
  List<RecordedTradeResult> results = const [],
  PortfolioRiskPolicy policy = const PortfolioRiskPolicy(
    maximumDailyLoss: 500,
    maximumRiskPerTrade: 100,
  ),
}) => PropFirmPortfolio(
  id: 'portfolio',
  name: 'Portfolio',
  virtualRiskBudget: 3000,
  acknowledged: true,
  accounts: accounts,
  riskPolicy: policy,
  strategy: const RotationStrategy(),
  plannedTrades: const [],
  results: results,
  disciplineRecords: const [],
  transitions: const [],
  firmPresets: const [],
  sessionDateKey: '2026-07-22',
  createdAt: now,
  updatedAt: now,
);

List<RecordedTradeResult> _results(String accountId, DateTime now, int count) =>
    List.generate(
      count,
      (index) => _result(
        '$accountId-$index',
        accountId,
        now.add(Duration(minutes: index)),
        TradeResultType.breakeven,
      ),
    );

RecordedTradeResult _result(
  String id,
  String accountId,
  DateTime time,
  TradeResultType type,
) => RecordedTradeResult(
  id: id,
  accountId: accountId,
  type: type,
  amount: 0,
  rMultiple: 0,
  resultTime: time,
);
