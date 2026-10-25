import 'capital_transaction.dart';
import 'trading_date_filter.dart';
import 'trading_journal_entry.dart';

enum TradingReportSource {
  manual('manual'),
  mt5('mt5'),
  all('all');

  const TradingReportSource(this.apiValue);

  final String apiValue;

  bool get requiresMainMt5Account => this != TradingReportSource.manual;
}

class TradingReportFilter {
  const TradingReportFilter({
    this.month,
    this.year,
    this.dateFilter = const TradingDateFilter.allTime(),
    this.stage = 'overall',
    this.source = TradingReportSource.manual,
    this.ownerUid = '',
    this.mainMt5AccountId = '',
    this.timezone = 'Asia/Kuala_Lumpur',
    this.languageCode = 'en',
  });

  final int? month;
  final int? year;
  final TradingDateFilter dateFilter;
  final String stage;
  final TradingReportSource source;
  final String ownerUid;
  final String mainMt5AccountId;
  final String timezone;
  final String languageCode;

  bool get hasDateFilter =>
      dateFilter.isActive || month != null || year != null;
  bool get requiresMainMt5Account => source.requiresMainMt5Account;

  TradingDateFilter get effectiveDateFilter {
    if (dateFilter.isActive) return dateFilter;
    if (month != null && year != null) {
      return TradingDateFilter.month(
        year!,
        month!,
        timezone: timezone,
        dataSource: source.apiValue,
        mt5AccountId: mainMt5AccountId,
      );
    }
    if (year != null) {
      return TradingDateFilter.year(
        year!,
        timezone: timezone,
        dataSource: source.apiValue,
        mt5AccountId: mainMt5AccountId,
      );
    }
    return dateFilter.withScope(
      dataSource: source.apiValue,
      mt5AccountId: mainMt5AccountId,
    );
  }

  TradingReportFilter copyWith({
    int? month,
    int? year,
    TradingDateFilter? dateFilter,
    bool clearMonth = false,
    bool clearYear = false,
    String? stage,
    TradingReportSource? source,
    String? ownerUid,
    String? mainMt5AccountId,
    String? timezone,
    String? languageCode,
  }) {
    return TradingReportFilter(
      month: clearMonth ? null : month ?? this.month,
      year: clearYear ? null : year ?? this.year,
      dateFilter: dateFilter ?? this.dateFilter,
      stage: stage ?? this.stage,
      source: source ?? this.source,
      ownerUid: ownerUid ?? this.ownerUid,
      mainMt5AccountId: mainMt5AccountId ?? this.mainMt5AccountId,
      timezone: timezone ?? this.timezone,
      languageCode: languageCode ?? this.languageCode,
    );
  }
}

class TradingReportAccount {
  const TradingReportAccount({
    required this.id,
    required this.name,
    required this.login,
    required this.broker,
    required this.server,
    required this.currency,
    this.balance,
    this.equity,
    this.isMain = true,
  });

  final String id;
  final String name;
  final String login;
  final String broker;
  final String server;
  final String currency;
  final double? balance;
  final double? equity;
  final bool isMain;

  String get displayLabel => name.trim().isEmpty ? '$server / $login' : name;
}

class TradingCapitalReport {
  const TradingCapitalReport({
    required this.totalDeposits,
    required this.totalWithdrawals,
    required this.netCapital,
    required this.transactions,
    required this.records,
  });

  final double totalDeposits;
  final double totalWithdrawals;
  final double netCapital;
  final int transactions;
  final List<CapitalTransaction> records;
}

class TradingBestDayRule {
  const TradingBestDayRule({
    required this.limitPercent,
    required this.bestWinningDay,
    required this.totalGeneratedProfit,
    required this.scorePercent,
    required this.requiredTotalProfit,
    required this.additionalProfitNeeded,
    required this.passed,
    required this.status,
    required this.grade,
    this.bestWinningDayDate,
  });

  final double limitPercent;
  final double bestWinningDay;
  final DateTime? bestWinningDayDate;
  final double totalGeneratedProfit;
  final double scorePercent;
  final double requiredTotalProfit;
  final double additionalProfitNeeded;
  final bool passed;
  final String status;
  final String grade;
}

class TradingGrossProfitRule {
  const TradingGrossProfitRule({
    required this.minimumPercent,
    required this.accountBalance,
    required this.grossProfit,
    required this.requiredAmount,
    required this.remainingAmount,
    required this.achievedPercent,
    required this.passed,
    required this.status,
  });

  final double minimumPercent;
  final double accountBalance;
  final double grossProfit;
  final double requiredAmount;
  final double remainingAmount;
  final double achievedPercent;
  final bool passed;
  final String status;
}

class TradingReportSummary {
  const TradingReportSummary({
    required this.totalTrades,
    required this.winningTrades,
    required this.losingTrades,
    required this.breakevenTrades,
    required this.winRate,
    required this.lossRate,
    required this.totalProfit,
    required this.totalLoss,
    required this.netProfitLoss,
    required this.averageWin,
    required this.averageLoss,
    required this.profitFactorLabel,
    required this.expectancy,
    required this.totalPips,
    required this.averagePips,
    required this.totalLots,
    required this.averageLot,
    required this.largestWin,
    required this.largestLoss,
    required this.currentBalance,
    required this.growthPercent,
    required this.maxDrawdownAmount,
    required this.maxDrawdownPercent,
    required this.maxDailyLossAmount,
    required this.maxDailyLossPercent,
    required this.overallLossPercent,
    required this.bestDay,
    required this.worstDay,
    required this.profitableDays,
    required this.losingDays,
    required this.tradingDays,
    required this.dailyWinRate,
    required this.bestDayRule,
    required this.grossProfitRule,
    required this.biggestWinningDay,
    required this.longestWinStreak,
    required this.longestLossStreak,
    required this.recoveryFactorLabel,
    required this.payoffRatioLabel,
    required this.durationDays,
    required this.averageHoldingMinutes,
    required this.averageHoldingLabel,
    required this.medianHoldingMinutes,
    required this.medianHoldingLabel,
    required this.maxHoldingMinutes,
    required this.maxHoldingLabel,
    required this.bestWeekday,
    this.profitFactor,
    this.recoveryFactor,
    this.payoffRatio,
    this.firstTradeDate,
    this.lastTradeDate,
  });

  final int totalTrades;
  final int winningTrades;
  final int losingTrades;
  final int breakevenTrades;
  final double winRate;
  final double lossRate;
  final double totalProfit;
  final double totalLoss;
  final double netProfitLoss;
  final double averageWin;
  final double averageLoss;
  final double? profitFactor;
  final String profitFactorLabel;
  final double expectancy;
  final double totalPips;
  final double averagePips;
  final double totalLots;
  final double averageLot;
  final double largestWin;
  final double largestLoss;
  final double currentBalance;
  final double growthPercent;
  final double maxDrawdownAmount;
  final double maxDrawdownPercent;
  final double maxDailyLossAmount;
  final double maxDailyLossPercent;
  final double overallLossPercent;
  final double bestDay;
  final double worstDay;
  final int profitableDays;
  final int losingDays;
  final int tradingDays;
  final double dailyWinRate;
  final TradingBestDayRule bestDayRule;
  final TradingGrossProfitRule grossProfitRule;
  final double biggestWinningDay;
  final int longestWinStreak;
  final int longestLossStreak;
  final double? recoveryFactor;
  final String recoveryFactorLabel;
  final double? payoffRatio;
  final String payoffRatioLabel;
  final int durationDays;
  final DateTime? firstTradeDate;
  final DateTime? lastTradeDate;
  final int averageHoldingMinutes;
  final String averageHoldingLabel;
  final int medianHoldingMinutes;
  final String medianHoldingLabel;
  final int maxHoldingMinutes;
  final String maxHoldingLabel;
  final String bestWeekday;

  bool get hasTrades => totalTrades > 0;
  double get consistencyPercent => bestDayRule.scorePercent;
  bool get consistencyPassed => bestDayRule.passed;
}

class TradingDailyStat {
  const TradingDailyStat({
    required this.date,
    required this.trades,
    required this.profitLoss,
    required this.cumulativeProfitLoss,
  });

  final DateTime date;
  final int trades;
  final double profitLoss;
  final double cumulativeProfitLoss;
}

class TradingBreakdownStat {
  const TradingBreakdownStat({
    required this.label,
    required this.trades,
    required this.wins,
    required this.losses,
    required this.profitLoss,
    required this.pips,
    required this.winRate,
    required this.averageProfitLoss,
    this.shortLabel,
  });

  final String label;
  final String? shortLabel;
  final int trades;
  final int wins;
  final int losses;
  final double profitLoss;
  final double pips;
  final double winRate;
  final double averageProfitLoss;
}

class TradingRuleMonitorItem {
  const TradingRuleMonitorItem({
    required this.key,
    required this.title,
    required this.value,
    required this.progress,
    required this.status,
    required this.tone,
    required this.caption,
  });

  final String key;
  final String title;
  final String value;
  final double progress;
  final String status;
  final String tone;
  final String caption;

  bool get breached => status == 'Breached';
}

class TradingScoreComponent {
  const TradingScoreComponent({
    required this.metric,
    required this.value,
    required this.grade,
    required this.points,
    required this.maximumPoints,
    required this.status,
    required this.formula,
    required this.calculation,
    this.isPenalty = false,
  });

  final String metric;
  final String value;
  final String grade;
  final double points;
  final double maximumPoints;
  final String status;
  final String formula;
  final String calculation;
  final bool isPenalty;
}

class TradingScoreProfile {
  const TradingScoreProfile({
    required this.score,
    required this.rawScore,
    required this.baseScore,
    required this.grade,
    required this.meaning,
    required this.behaviourPenaltyPercent,
    required this.behaviourPenaltyPoints,
    required this.isProvisional,
    required this.confidencePercent,
    required this.components,
  });

  final double score;
  final double rawScore;
  final double baseScore;
  final String grade;
  final String meaning;
  final double behaviourPenaltyPercent;
  final double behaviourPenaltyPoints;
  final bool isProvisional;
  final double confidencePercent;
  final List<TradingScoreComponent> components;
}

class TradingPositionProfile {
  const TradingPositionProfile({
    required this.status,
    required this.grade,
    required this.description,
    required this.score,
    required this.mainLot,
    required this.mainLotShare,
    required this.nearMainLotShare,
    required this.averageLot,
    required this.medianLot,
    required this.minimumLot,
    required this.maximumLot,
    required this.coefficientOfVariation,
    required this.rangeRatio,
  });

  final String status;
  final String grade;
  final String description;
  final double score;
  final double mainLot;
  final double mainLotShare;
  final double nearMainLotShare;
  final double averageLot;
  final double medianLot;
  final double minimumLot;
  final double maximumLot;
  final double coefficientOfVariation;
  final double rangeRatio;
}

class TradingBehaviourEvidence {
  const TradingBehaviourEvidence({
    required this.tier,
    required this.primaryTrade,
    required this.secondaryTrade,
    required this.pair,
    required this.delayLabel,
    required this.lotChange,
    required this.signals,
    this.side = '',
    this.lot,
    this.profitLoss,
  });

  final String tier;
  final String primaryTrade;
  final String secondaryTrade;
  final String pair;
  final String delayLabel;
  final String lotChange;
  final String signals;
  final String side;
  final double? lot;
  final double? profitLoss;
}

class TradingBehaviourCheck {
  const TradingBehaviourCheck({
    required this.name,
    required this.value,
    required this.status,
    required this.points,
    required this.description,
  });

  final String name;
  final String value;
  final String status;
  final double? points;
  final String description;
}

class TradingBehaviourComponent {
  const TradingBehaviourComponent({
    required this.status,
    required this.score,
    required this.tier,
    required this.method,
    required this.eventCount,
    required this.quickCount,
    required this.lotIncreaseCount,
    required this.averageDelayLabel,
    required this.examples,
  });

  final String status;
  final double score;
  final String tier;
  final String method;
  final int eventCount;
  final int quickCount;
  final int lotIncreaseCount;
  final String averageDelayLabel;
  final List<TradingBehaviourEvidence> examples;
}

class TradingBehaviourProfile {
  const TradingBehaviourProfile({
    required this.revenge,
    required this.gambling,
    required this.layering,
    required this.rawScore,
    required this.finalScore,
    required this.recoveryPercent,
    required this.recoveryBlocks,
    required this.layeredTradeCount,
    required this.layeredTradePercent,
    required this.maximumLayers,
    required this.overlapCount,
    required this.adverseLayerCount,
    required this.overtradingDays,
    required this.maximumTradesPerDay,
    required this.oversizedLossCount,
    required this.checks,
  });

  final TradingBehaviourComponent revenge;
  final TradingBehaviourComponent gambling;
  final TradingBehaviourComponent layering;
  final double rawScore;
  final double finalScore;
  final double recoveryPercent;
  final int recoveryBlocks;
  final int layeredTradeCount;
  final double layeredTradePercent;
  final int maximumLayers;
  final int overlapCount;
  final int adverseLayerCount;
  final int overtradingDays;
  final int maximumTradesPerDay;
  final int oversizedLossCount;
  final List<TradingBehaviourCheck> checks;
}

class TradingTraderLevelProfile {
  const TradingTraderLevelProfile({
    required this.levelNumber,
    required this.label,
    required this.sampleMode,
    required this.classificationScore,
    required this.baseLevel,
    required this.capLevel,
    required this.capActive,
    required this.capReason,
    required this.nextLevel,
    required this.rewardEstimate,
    required this.highestRewardEstimate,
    required this.profileNote,
  });

  final int levelNumber;
  final String label;
  final String sampleMode;
  final double classificationScore;
  final int baseLevel;
  final int capLevel;
  final bool capActive;
  final String capReason;
  final String nextLevel;
  final double rewardEstimate;
  final double highestRewardEstimate;
  final String profileNote;
}

class TradingHedgingProfile {
  const TradingHedgingProfile({
    required this.detected,
    required this.eventCount,
    required this.affectedTrades,
    required this.description,
  });

  final bool detected;
  final int eventCount;
  final int affectedTrades;
  final String description;
}

class TradingReport {
  const TradingReport({
    required this.filter,
    required this.generatedAt,
    required this.trades,
    required this.capital,
    required this.summary,
    required this.dailyStats,
    required this.monthlyStats,
    required this.pairStats,
    required this.directionStats,
    required this.weekdayStats,
    required this.sessionStats,
    required this.ruleMonitor,
    required this.traderLabel,
    required this.scoreProfile,
    required this.positionProfile,
    required this.behaviourProfile,
    required this.levelProfile,
    required this.hedgingProfile,
    required this.performanceStatus,
    required this.traderStyle,
    required this.executiveInsight,
    required this.resumeSummary,
    required this.technicalSkill,
    required this.riskProfile,
    required this.administrationAction,
    required this.strengths,
    required this.risks,
    required this.coachingFocus,
    this.duplicateTradesExcluded = 0,
    this.account,
  });

  final TradingReportFilter filter;
  final DateTime generatedAt;
  final List<TradingJournalEntry> trades;
  final TradingCapitalReport capital;
  final TradingReportSummary summary;
  final List<TradingDailyStat> dailyStats;
  final List<TradingBreakdownStat> monthlyStats;
  final List<TradingBreakdownStat> pairStats;
  final List<TradingBreakdownStat> directionStats;
  final List<TradingBreakdownStat> weekdayStats;
  final List<TradingBreakdownStat> sessionStats;
  final List<TradingRuleMonitorItem> ruleMonitor;
  final String traderLabel;
  final TradingReportAccount? account;
  final TradingScoreProfile scoreProfile;
  final TradingPositionProfile positionProfile;
  final TradingBehaviourProfile behaviourProfile;
  final TradingTraderLevelProfile levelProfile;
  final TradingHedgingProfile hedgingProfile;
  final String performanceStatus;
  final String traderStyle;
  final String executiveInsight;
  final String resumeSummary;
  final String technicalSkill;
  final String riskProfile;
  final String administrationAction;
  final List<String> strengths;
  final List<String> risks;
  final List<String> coachingFocus;
  final int duplicateTradesExcluded;
}
