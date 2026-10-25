import 'package:flutter/foundation.dart';

import '../domain/analytics_filter.dart';
import '../domain/analytics_result.dart';
import '../domain/analytics_trade.dart';
import '../domain/trading_analytics_engine.dart';

class TradingAnalyticsComputation {
  const TradingAnalyticsComputation({required this.current, this.previous});

  final TradingAnalyticsResult current;
  final TradingAnalyticsResult? previous;
}

class TradingAnalyticsController {
  String? _cacheKey;
  Future<TradingAnalyticsComputation>? _cachedComputation;

  Future<TradingAnalyticsComputation> resolve({
    required List<AnalyticsTrade> trades,
    required List<AnalyticsCashFlow> cashFlows,
    required AnalyticsFilter filter,
    AnalyticsFilter? previousFilter,
    double? configuredStartingBalance,
    double? liveCurrentBalance,
    double? liveCurrentEquity,
    List<AccountEquitySnapshot> equitySnapshots = const [],
    int timezoneOffsetMinutes = 0,
  }) {
    final request = _AnalyticsComputationRequest(
      trades: trades,
      cashFlows: cashFlows,
      filter: filter,
      previousFilter: previousFilter,
      configuredStartingBalance: configuredStartingBalance,
      liveCurrentBalance: liveCurrentBalance,
      liveCurrentEquity: liveCurrentEquity,
      equitySnapshots: equitySnapshots,
      timezoneOffsetMinutes: timezoneOffsetMinutes,
    );
    final key = request.cacheKey;
    if (_cacheKey == key && _cachedComputation != null) {
      return _cachedComputation!;
    }
    _cacheKey = key;
    return _cachedComputation = compute(
      _calculateAnalytics,
      request,
      debugLabel: 'trading-account-analytics',
    );
  }

  void clear() {
    _cacheKey = null;
    _cachedComputation = null;
  }
}

class _AnalyticsComputationRequest {
  const _AnalyticsComputationRequest({
    required this.trades,
    required this.cashFlows,
    required this.filter,
    required this.previousFilter,
    required this.configuredStartingBalance,
    required this.liveCurrentBalance,
    required this.liveCurrentEquity,
    required this.equitySnapshots,
    required this.timezoneOffsetMinutes,
  });

  final List<AnalyticsTrade> trades;
  final List<AnalyticsCashFlow> cashFlows;
  final AnalyticsFilter filter;
  final AnalyticsFilter? previousFilter;
  final double? configuredStartingBalance;
  final double? liveCurrentBalance;
  final double? liveCurrentEquity;
  final List<AccountEquitySnapshot> equitySnapshots;
  final int timezoneOffsetMinutes;

  String get cacheKey {
    final tradeVersion = trades.fold<int>(
      trades.length,
      (value, trade) =>
          value ^
          trade.stableIdentity.hashCode ^
          trade.status.hashCode ^
          trade.netProfit.hashCode ^
          trade.lots.hashCode ^
          (trade.updatedAtUtc?.microsecondsSinceEpoch ?? 0),
    );
    final cashFlowVersion = cashFlows.fold<int>(
      cashFlows.length,
      (value, flow) =>
          value ^
          flow.id.hashCode ^
          flow.timestampUtc.microsecondsSinceEpoch ^
          flow.amount.hashCode ^
          flow.type.hashCode,
    );
    final snapshotVersion = equitySnapshots.fold<int>(
      equitySnapshots.length,
      (value, snapshot) =>
          value ^
          snapshot.accountId.hashCode ^
          snapshot.timestampUtc.microsecondsSinceEpoch ^
          snapshot.balance.hashCode ^
          snapshot.equity.hashCode,
    );
    return [
      tradeVersion,
      cashFlowVersion,
      snapshotVersion,
      filter.cacheKey,
      previousFilter?.cacheKey,
      configuredStartingBalance,
      liveCurrentBalance,
      liveCurrentEquity,
      timezoneOffsetMinutes,
    ].join('|');
  }
}

TradingAnalyticsComputation _calculateAnalytics(
  _AnalyticsComputationRequest request,
) {
  final current = TradingAnalyticsEngine().analyze(
    trades: request.trades,
    cashFlows: request.cashFlows,
    filter: request.filter,
    configuredStartingBalance: request.configuredStartingBalance,
    liveCurrentBalance: request.liveCurrentBalance,
    liveCurrentEquity: request.liveCurrentEquity,
    equitySnapshots: request.equitySnapshots,
    timezoneOffsetMinutes: request.timezoneOffsetMinutes,
  );
  final previousFilter = request.previousFilter;
  final previous = previousFilter == null
      ? null
      : TradingAnalyticsEngine().analyze(
          trades: request.trades,
          cashFlows: request.cashFlows,
          filter: previousFilter,
          configuredStartingBalance: request.configuredStartingBalance,
          equitySnapshots: request.equitySnapshots,
          timezoneOffsetMinutes: request.timezoneOffsetMinutes,
        );
  return TradingAnalyticsComputation(current: current, previous: previous);
}
