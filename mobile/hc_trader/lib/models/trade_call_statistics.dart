import 'trading_date_filter.dart';

class TradeCallStatisticsFilter {
  const TradeCallStatisticsFilter({
    this.dateFilter = const TradingDateFilter.allTime(),
    this.providerId = '',
    this.instrument = '',
    this.signalSource = '',
    this.status = '',
    this.direction = '',
    this.orderType = '',
  });

  final TradingDateFilter dateFilter;
  final String providerId;
  final String instrument;
  final String signalSource;
  final String status;
  final String direction;
  final String orderType;

  TradeCallStatisticsFilter copyWith({
    TradingDateFilter? dateFilter,
    String? providerId,
    String? instrument,
    String? signalSource,
    String? status,
    String? direction,
    String? orderType,
  }) => TradeCallStatisticsFilter(
    dateFilter: dateFilter ?? this.dateFilter,
    providerId: providerId ?? this.providerId,
    instrument: instrument ?? this.instrument,
    signalSource: signalSource ?? this.signalSource,
    status: status ?? this.status,
    direction: direction ?? this.direction,
    orderType: orderType ?? this.orderType,
  );
}

class TradeCallBreakdown {
  const TradeCallBreakdown({
    required this.key,
    required this.label,
    required this.total,
    required this.wins,
    required this.losses,
    required this.breakEvens,
    required this.totalPips,
  });

  final String key;
  final String label;
  final int total;
  final int wins;
  final int losses;
  final int breakEvens;
  final double totalPips;

  int get evaluated => wins + losses + breakEvens;
  double get winRate => evaluated == 0 ? 0 : wins * 100 / evaluated;
}

class TradeCallStatistics {
  const TradeCallStatistics({
    required this.totalCalls,
    required this.draftCalls,
    required this.pendingCalls,
    required this.activeCalls,
    required this.closedCalls,
    required this.cancelledCalls,
    required this.expiredCalls,
    required this.takeProfitCalls,
    required this.stopLossCalls,
    required this.breakEvenCalls,
    required this.winningCalls,
    required this.losingCalls,
    required this.evaluatedCalls,
    required this.excludedFromRates,
    required this.averagePlannedRiskReward,
    required this.averageRealizedRiskReward,
    required this.totalPips,
    required this.averagePips,
    required this.totalPositivePips,
    required this.totalNegativePips,
    required this.averageHoldingDuration,
    required this.shortestHoldingDuration,
    required this.longestHoldingDuration,
    required this.monthlyPerformance,
    required this.weeklyPerformance,
    required this.byInstrument,
    required this.byDirection,
    required this.byOrderType,
    required this.bySignalSource,
    required this.byProvider,
    required this.providerNames,
    required this.recentCompletedCallIds,
    required this.missingProviderCount,
  });

  final int totalCalls;
  final int draftCalls;
  final int pendingCalls;
  final int activeCalls;
  final int closedCalls;
  final int cancelledCalls;
  final int expiredCalls;
  final int takeProfitCalls;
  final int stopLossCalls;
  final int breakEvenCalls;
  final int winningCalls;
  final int losingCalls;
  final int evaluatedCalls;
  final int excludedFromRates;
  final double averagePlannedRiskReward;
  final double averageRealizedRiskReward;
  final double totalPips;
  final double averagePips;
  final double totalPositivePips;
  final double totalNegativePips;
  final Duration? averageHoldingDuration;
  final Duration? shortestHoldingDuration;
  final Duration? longestHoldingDuration;
  final List<TradeCallBreakdown> monthlyPerformance;
  final List<TradeCallBreakdown> weeklyPerformance;
  final List<TradeCallBreakdown> byInstrument;
  final List<TradeCallBreakdown> byDirection;
  final List<TradeCallBreakdown> byOrderType;
  final List<TradeCallBreakdown> bySignalSource;
  final List<TradeCallBreakdown> byProvider;
  final Map<String, String> providerNames;
  final List<String> recentCompletedCallIds;
  final int missingProviderCount;

  double get winRate =>
      evaluatedCalls == 0 ? 0 : winningCalls * 100 / evaluatedCalls;
  double get lossRate =>
      evaluatedCalls == 0 ? 0 : losingCalls * 100 / evaluatedCalls;
  double get breakEvenRate =>
      evaluatedCalls == 0 ? 0 : breakEvenCalls * 100 / evaluatedCalls;
  bool get hasSufficientSample => evaluatedCalls >= 10;
}

class TradeCallAccessException implements Exception {
  const TradeCallAccessException(this.message);

  final String message;

  @override
  String toString() => message;
}
