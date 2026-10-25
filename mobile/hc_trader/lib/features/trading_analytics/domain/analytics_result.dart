import 'analytics_filter.dart';
import 'analytics_trade.dart';

class AnalyticsPoint {
  const AnalyticsPoint({
    required this.timestampUtc,
    required this.value,
    this.secondaryValue,
    this.tradeCount = 0,
    this.winRate = 0,
    this.totalLots = 0,
    this.label = '',
    this.tradeId,
    this.symbol,
  });

  final DateTime timestampUtc;
  final double value;
  final double? secondaryValue;
  final int tradeCount;
  final double winRate;
  final double totalLots;
  final String label;
  final String? tradeId;
  final String? symbol;
}

class DrawdownPoint {
  const DrawdownPoint({
    required this.timestampUtc,
    required this.amount,
    required this.percent,
    required this.peak,
  });

  final DateTime timestampUtc;
  final double amount;
  final double percent;
  final double peak;
}

class DrawdownPeriod {
  const DrawdownPeriod({
    required this.amount,
    required this.percent,
    this.peakDateUtc,
    this.troughDateUtc,
    this.recoveryDateUtc,
  });

  final double amount;
  final double percent;
  final DateTime? peakDateUtc;
  final DateTime? troughDateUtc;
  final DateTime? recoveryDateUtc;

  bool get recovered => recoveryDateUtc != null;
  Duration? get duration {
    final start = peakDateUtc;
    final end = recoveryDateUtc ?? troughDateUtc;
    if (start == null || end == null) return null;
    return end.difference(start);
  }
}

class AnalyticsHistogramBin {
  const AnalyticsHistogramBin({
    required this.lower,
    required this.upper,
    required this.count,
    this.includesLowerOutliers = false,
    this.includesUpperOutliers = false,
  });

  final double lower;
  final double upper;
  final int count;
  final bool includesLowerOutliers;
  final bool includesUpperOutliers;

  String get label =>
      '${includesLowerOutliers ? '≤' : ''}${lower.toStringAsFixed(2)} – '
      '${includesUpperOutliers ? '≥' : ''}${upper.toStringAsFixed(2)}';
}

class DistributionSummary {
  const DistributionSummary({
    required this.count,
    required this.minimum,
    required this.p25,
    required this.median,
    required this.p75,
    required this.p90,
    required this.maximum,
    required this.mean,
    required this.standardDeviation,
  });

  const DistributionSummary.empty()
    : count = 0,
      minimum = 0,
      p25 = 0,
      median = 0,
      p75 = 0,
      p90 = 0,
      maximum = 0,
      mean = 0,
      standardDeviation = 0;

  final int count;
  final double minimum;
  final double p25;
  final double median;
  final double p75;
  final double p90;
  final double maximum;
  final double mean;
  final double standardDeviation;
}

class AnalyticsBreakdown {
  const AnalyticsBreakdown({
    required this.key,
    required this.label,
    required this.trades,
    required this.wins,
    required this.losses,
    required this.breakeven,
    required this.netProfit,
    required this.grossProfit,
    required this.grossLoss,
    required this.winRate,
    required this.averageTrade,
    required this.averageWin,
    required this.averageLoss,
    required this.maxDrawdown,
    required this.totalLots,
    required this.averageLots,
    required this.averageHoldingMinutes,
    required this.bestTrade,
    required this.worstTrade,
    required this.buyProfit,
    required this.sellProfit,
    this.profitFactor,
  });

  final String key;
  final String label;
  final int trades;
  final int wins;
  final int losses;
  final int breakeven;
  final double netProfit;
  final double grossProfit;
  final double grossLoss;
  final double winRate;
  final double? profitFactor;
  final double averageTrade;
  final double averageWin;
  final double averageLoss;
  final double maxDrawdown;
  final double totalLots;
  final double averageLots;
  final double averageHoldingMinutes;
  final double bestTrade;
  final double worstTrade;
  final double buyProfit;
  final double sellProfit;
}

class MonthlyAnalytics {
  const MonthlyAnalytics({
    required this.monthUtc,
    required this.metrics,
    required this.returnPercent,
  });

  final DateTime monthUtc;
  final AnalyticsBreakdown metrics;
  final double? returnPercent;
}

class AnalyticsSummary {
  const AnalyticsSummary({
    required this.startingBalance,
    required this.currentBalance,
    required this.currentEquity,
    required this.netTradingPnl,
    required this.grossProfit,
    required this.grossLoss,
    required this.floatingPnl,
    required this.totalClosedTrades,
    required this.totalOpenTrades,
    required this.winningTrades,
    required this.losingTrades,
    required this.breakevenTrades,
    required this.winRate,
    required this.lossRate,
    required this.averageTrade,
    required this.averageWin,
    required this.averageLoss,
    required this.largestWin,
    required this.largestLoss,
    required this.expectancy,
    required this.averageHoldingMinutes,
    required this.medianHoldingMinutes,
    required this.averageWinningHoldingMinutes,
    required this.averageLosingHoldingMinutes,
    required this.longestHoldingMinutes,
    required this.shortestHoldingMinutes,
    required this.maximumDrawdown,
    required this.maximumDrawdownPercent,
    required this.currentDrawdown,
    required this.currentDrawdownPercent,
    required this.averageLot,
    required this.medianLot,
    required this.maximumLot,
    required this.minimumLot,
    required this.totalLots,
    required this.lotStandardDeviation,
    required this.maximumSimultaneousLots,
    required this.maximumBuyExposure,
    required this.maximumSellExposure,
    required this.averageTradesPerDay,
    required this.bestDay,
    required this.worstDay,
    required this.bestMonth,
    required this.worstMonth,
    required this.currentWinningStreak,
    required this.currentLosingStreak,
    required this.maximumWinningStreak,
    required this.maximumLosingStreak,
    required this.profitableDaysPercent,
    required this.losingDaysPercent,
    required this.breakevenDaysPercent,
    required this.averageDailyPnl,
    required this.medianDailyPnl,
    required this.averageWeeklyPnl,
    required this.averageMonthlyPnl,
    required this.dailyReturnStandardDeviation,
    required this.downsideDeviation,
    required this.pnlConsistencyScore,
    required this.lotConsistencyScore,
    required this.holdingConsistencyScore,
    required this.frequencyConsistencyScore,
    required this.postLossLotChangePercent,
    required this.postWinLotChangePercent,
    this.totalReturnPercent,
    this.profitFactor,
    this.payoffRatio,
    this.recoveryFactor,
    this.returnToDrawdown,
    this.sharpeRatio,
    this.sortinoRatio,
    this.calmarRatio,
    this.averageRMultiple,
    this.medianRMultiple,
    this.expectedRMultiple,
  });

  final double startingBalance;
  final double currentBalance;
  final double currentEquity;
  final double netTradingPnl;
  final double grossProfit;
  final double grossLoss;
  final double floatingPnl;
  final double? totalReturnPercent;
  final int totalClosedTrades;
  final int totalOpenTrades;
  final int winningTrades;
  final int losingTrades;
  final int breakevenTrades;
  final double winRate;
  final double lossRate;
  final double averageTrade;
  final double averageWin;
  final double averageLoss;
  final double largestWin;
  final double largestLoss;
  final double? profitFactor;
  final double? payoffRatio;
  final double expectancy;
  final double averageHoldingMinutes;
  final double medianHoldingMinutes;
  final double averageWinningHoldingMinutes;
  final double averageLosingHoldingMinutes;
  final double longestHoldingMinutes;
  final double shortestHoldingMinutes;
  final double maximumDrawdown;
  final double maximumDrawdownPercent;
  final double currentDrawdown;
  final double currentDrawdownPercent;
  final double? recoveryFactor;
  final double? returnToDrawdown;
  final double averageLot;
  final double medianLot;
  final double maximumLot;
  final double minimumLot;
  final double totalLots;
  final double lotStandardDeviation;
  final double maximumSimultaneousLots;
  final double maximumBuyExposure;
  final double maximumSellExposure;
  final double averageTradesPerDay;
  final double bestDay;
  final double worstDay;
  final double bestMonth;
  final double worstMonth;
  final int currentWinningStreak;
  final int currentLosingStreak;
  final int maximumWinningStreak;
  final int maximumLosingStreak;
  final double profitableDaysPercent;
  final double losingDaysPercent;
  final double breakevenDaysPercent;
  final double averageDailyPnl;
  final double medianDailyPnl;
  final double averageWeeklyPnl;
  final double averageMonthlyPnl;
  final double dailyReturnStandardDeviation;
  final double downsideDeviation;
  final double? sharpeRatio;
  final double? sortinoRatio;
  final double? calmarRatio;
  final double? averageRMultiple;
  final double? medianRMultiple;
  final double? expectedRMultiple;
  final double pnlConsistencyScore;
  final double lotConsistencyScore;
  final double holdingConsistencyScore;
  final double frequencyConsistencyScore;
  final double postLossLotChangePercent;
  final double postWinLotChangePercent;
}

enum AnalyticsInsightKind { strength, risk, pattern, dataQuality }

class AnalyticsInsight {
  const AnalyticsInsight({
    required this.kind,
    required this.title,
    required this.message,
    required this.supportingValue,
    required this.sampleSize,
  });

  final AnalyticsInsightKind kind;
  final String title;
  final String message;
  final String supportingValue;
  final int sampleSize;
}

class AnalyticsDataQuality {
  const AnalyticsDataQuality({
    required this.totalRawRecords,
    required this.filteredRecords,
    required this.eligibleClosedTrades,
    required this.openTrades,
    required this.deposits,
    required this.withdrawals,
    required this.cancelledOrders,
    required this.pendingOrders,
    required this.rejectedOrExpiredOrders,
    required this.duplicateRecordsIgnored,
    required this.tradesMissingCloseTime,
    required this.tradesMissingLotSize,
    required this.tradesMissingStrategy,
    required this.tradesMissingRiskData,
    required this.equitySnapshotsAvailable,
    this.earliestTradeUtc,
    this.latestTradeUtc,
    this.lastExcelImportUtc,
    this.lastMt5SynchronizationUtc,
  });

  final int totalRawRecords;
  final int filteredRecords;
  final int eligibleClosedTrades;
  final int openTrades;
  final int deposits;
  final int withdrawals;
  final int cancelledOrders;
  final int pendingOrders;
  final int rejectedOrExpiredOrders;
  final int duplicateRecordsIgnored;
  final int tradesMissingCloseTime;
  final int tradesMissingLotSize;
  final int tradesMissingStrategy;
  final int tradesMissingRiskData;
  final DateTime? earliestTradeUtc;
  final DateTime? latestTradeUtc;
  final DateTime? lastExcelImportUtc;
  final DateTime? lastMt5SynchronizationUtc;
  final bool equitySnapshotsAvailable;
}

class CurrencyAnalyticsTotal {
  const CurrencyAnalyticsTotal({
    required this.currency,
    required this.netTradingPnl,
    required this.floatingPnl,
    required this.closedTrades,
    required this.openTrades,
  });

  final String currency;
  final double netTradingPnl;
  final double floatingPnl;
  final int closedTrades;
  final int openTrades;
}

class TradingAnalyticsResult {
  const TradingAnalyticsResult({
    required this.filter,
    required this.trades,
    required this.closedTrades,
    required this.openTrades,
    required this.summary,
    required this.cumulativePnl,
    required this.tradingBalanceCurve,
    required this.actualBalanceCurve,
    required this.equityCurve,
    required this.drawdownTimeline,
    required this.maximumDrawdownPeriod,
    required this.dailyPnl,
    required this.weeklyPnl,
    required this.monthlyPnl,
    required this.pnlHistogram,
    required this.lotHistogram,
    required this.holdingHistogram,
    required this.lotOverTime,
    required this.holdingOverTime,
    required this.rollingWinRate10,
    required this.rollingWinRate20,
    required this.rollingWinRate50,
    required this.rollingProfitFactor20,
    required this.winningDistribution,
    required this.losingDistribution,
    required this.breakdowns,
    required this.monthlyTable,
    required this.insights,
    required this.dataQuality,
    required this.currencyTotals,
    required this.monetaryMetricsAvailable,
    required this.currency,
  });

  final AnalyticsFilter filter;
  final List<AnalyticsTrade> trades;
  final List<AnalyticsTrade> closedTrades;
  final List<AnalyticsTrade> openTrades;
  final AnalyticsSummary summary;
  final List<AnalyticsPoint> cumulativePnl;
  final List<AnalyticsPoint> tradingBalanceCurve;
  final List<AnalyticsPoint> actualBalanceCurve;
  final List<AnalyticsPoint> equityCurve;
  final List<DrawdownPoint> drawdownTimeline;
  final DrawdownPeriod maximumDrawdownPeriod;
  final List<AnalyticsPoint> dailyPnl;
  final List<AnalyticsPoint> weeklyPnl;
  final List<AnalyticsPoint> monthlyPnl;
  final List<AnalyticsHistogramBin> pnlHistogram;
  final List<AnalyticsHistogramBin> lotHistogram;
  final List<AnalyticsHistogramBin> holdingHistogram;
  final List<AnalyticsPoint> lotOverTime;
  final List<AnalyticsPoint> holdingOverTime;
  final List<AnalyticsPoint> rollingWinRate10;
  final List<AnalyticsPoint> rollingWinRate20;
  final List<AnalyticsPoint> rollingWinRate50;
  final List<AnalyticsPoint> rollingProfitFactor20;
  final DistributionSummary winningDistribution;
  final DistributionSummary losingDistribution;
  final Map<String, List<AnalyticsBreakdown>> breakdowns;
  final List<MonthlyAnalytics> monthlyTable;
  final List<AnalyticsInsight> insights;
  final AnalyticsDataQuality dataQuality;
  final List<CurrencyAnalyticsTotal> currencyTotals;
  final bool monetaryMetricsAvailable;
  final String currency;
}
