import 'prop_firm_models.dart';
import 'trading_day_service.dart';

enum DailyTradeLimitStatus { available, approachingLimit, reached, disabled }

class AccountDailyTradeLimitResult {
  const AccountDailyTradeLimitResult({
    required this.accountId,
    required this.currentTrades,
    required this.limit,
    required this.enabled,
    required this.status,
    required this.tradingDayKey,
    required this.nextResetAt,
  });

  final String accountId;
  final int currentTrades;
  final int limit;
  final bool enabled;
  final DailyTradeLimitStatus status;
  final String tradingDayKey;
  final DateTime nextResetAt;

  int? get remainingTrades =>
      enabled ? (limit - currentTrades).clamp(0, limit) : null;
  bool get hasReachedLimit => status == DailyTradeLimitStatus.reached;
  bool get canStartTrading => !enabled || currentTrades < limit;
  double? get usagePercentage => enabled
      ? limit <= 0
            ? 1
            : (currentTrades / limit).clamp(0, 1).toDouble()
      : null;
}

/// Central source of truth for per-account daily trade-count safeguards.
/// Recorded result IDs are deduplicated and only executed result types count.
class AccountDailyTradeLimitService {
  const AccountDailyTradeLimitService({
    this.tradingDays = const TradingDayService(),
  });

  static const defaultLimit = 3;
  static const maximumSafeLimit = 100;

  final TradingDayService tradingDays;

  AccountDailyTradeLimitResult evaluate(
    PropFirmPortfolio portfolio,
    PropFirmAccount account, {
    required DateTime now,
  }) {
    final key = tradingDays.tradingDayKey(now, portfolio.riskPolicy);
    final currentTrades = resultsForAccountTradingDay(
      portfolio,
      account.id,
      now: now,
    ).length;
    final enabled = account.dailyTradeLimitEnabled;
    final limit = account.maxTradesPerDay.clamp(1, maximumSafeLimit);
    final remaining = (limit - currentTrades).clamp(0, limit);
    final status = !enabled
        ? DailyTradeLimitStatus.disabled
        : currentTrades >= limit
        ? DailyTradeLimitStatus.reached
        : remaining == 1
        ? DailyTradeLimitStatus.approachingLimit
        : DailyTradeLimitStatus.available;
    return AccountDailyTradeLimitResult(
      accountId: account.id,
      currentTrades: currentTrades,
      limit: limit,
      enabled: enabled,
      status: status,
      tradingDayKey: key,
      nextResetAt: tradingDays.nextResetAt(now, portfolio.riskPolicy),
    );
  }

  List<RecordedTradeResult> resultsForAccountTradingDay(
    PropFirmPortfolio portfolio,
    String accountId, {
    required DateTime now,
  }) {
    final key = tradingDays.tradingDayKey(now, portfolio.riskPolicy);
    final seenIds = <String>{};
    return portfolio.results
        .where(
          (result) =>
              result.accountId == accountId &&
              countsTowardDailyLimit(result) &&
              seenIds.add(result.id) &&
              tradingDays.tradingDayKey(
                    result.resultTime,
                    portfolio.riskPolicy,
                  ) ==
                  key,
        )
        .toList(growable: false);
  }

  bool countsTowardDailyLimit(RecordedTradeResult result) =>
      switch (result.type) {
        TradeResultType.fullTp ||
        TradeResultType.fullSl ||
        TradeResultType.breakeven ||
        TradeResultType.partialTp ||
        TradeResultType.partialSl ||
        TradeResultType.closedManually => true,
        TradeResultType.cancelled ||
        TradeResultType.notTriggered ||
        TradeResultType.missedTrade ||
        TradeResultType.manualAdjustment => false,
      };
}
