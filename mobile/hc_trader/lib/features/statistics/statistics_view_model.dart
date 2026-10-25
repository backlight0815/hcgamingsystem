import 'dart:math' as math;

import '../../models/trading_journal_entry.dart';
import '../../models/trading_report.dart';

enum PerformanceInsightKind { fact, attention }

class PerformanceInsight {
  const PerformanceInsight({
    required this.title,
    required this.message,
    required this.calculation,
    this.kind = PerformanceInsightKind.fact,
    this.values = const {},
  });

  final String title;
  final String message;
  final String calculation;
  final PerformanceInsightKind kind;
  final Map<String, Object?> values;
}

class PerformanceMetricComparison {
  const PerformanceMetricComparison({
    required this.current,
    required this.previous,
    required this.higherIsBetter,
  });

  final double current;
  final double previous;
  final bool higherIsBetter;

  double get difference => current - previous;
  bool get improved => higherIsBetter ? difference > 0 : difference < 0;
  bool get declined => higherIsBetter ? difference < 0 : difference > 0;
}

class PerformanceDrawdownPoint {
  const PerformanceDrawdownPoint({
    required this.date,
    required this.amount,
    required this.percent,
  });

  final DateTime date;
  final double amount;
  final double percent;
}

class StatisticsViewModel {
  const StatisticsViewModel({
    required this.report,
    required this.planCompliance,
    required this.riskCompliance,
    required this.stopLossCompliance,
    required this.takeProfitCompliance,
    required this.sessionCompliance,
    required this.psychologyCompletion,
    required this.averagePsychologyScore,
    required this.strategyStats,
    required this.accountStats,
    required this.planViolations,
    required this.riskViolations,
    required this.stopLossViolations,
    required this.takeProfitViolations,
    required this.sessionViolations,
    required this.consistencyScore,
    required this.strongestConsistencyArea,
    required this.weakestConsistencyArea,
    required this.stableTradingDays,
    required this.profitConcentration,
    required this.averageDailyResult,
    required this.dailyResultDeviation,
    required this.riskStatus,
    required this.currentDrawdownAmount,
    required this.currentDrawdownPercent,
    required this.drawdownTimeline,
    required this.tradesByDay,
    required this.revengeTradingIncidents,
    required this.fomoIncidents,
    required this.overtradingIncidents,
    required this.unplannedTrades,
    required this.insights,
    required this.comparisons,
    this.previousReport,
  });

  final TradingReport report;
  final TradingReport? previousReport;
  final double planCompliance;
  final double riskCompliance;
  final double stopLossCompliance;
  final double takeProfitCompliance;
  final double sessionCompliance;
  final double psychologyCompletion;
  final double averagePsychologyScore;
  final List<TradingBreakdownStat> strategyStats;
  final List<TradingBreakdownStat> accountStats;
  final List<TradingJournalEntry> planViolations;
  final List<TradingJournalEntry> riskViolations;
  final List<TradingJournalEntry> stopLossViolations;
  final List<TradingJournalEntry> takeProfitViolations;
  final List<TradingJournalEntry> sessionViolations;
  final double consistencyScore;
  final String strongestConsistencyArea;
  final String weakestConsistencyArea;
  final int stableTradingDays;
  final double profitConcentration;
  final double averageDailyResult;
  final double dailyResultDeviation;
  final String riskStatus;
  final double currentDrawdownAmount;
  final double currentDrawdownPercent;
  final List<PerformanceDrawdownPoint> drawdownTimeline;
  final Map<DateTime, List<TradingJournalEntry>> tradesByDay;
  final int revengeTradingIncidents;
  final int fomoIncidents;
  final int overtradingIncidents;
  final int unplannedTrades;
  final List<PerformanceInsight> insights;
  final Map<String, PerformanceMetricComparison> comparisons;

  PerformanceMetricComparison? comparison(String key) => comparisons[key];

  factory StatisticsViewModel.fromReport(
    TradingReport report, {
    TradingReport? previousReport,
  }) {
    final trades = report.trades;
    final psychologyTrades = trades
        .where(
          (trade) =>
              trade.emotionBeforeEntry.trim().isNotEmpty ||
              trade.emotionDuringTrade.trim().isNotEmpty ||
              trade.emotionAfterTrade.trim().isNotEmpty ||
              trade.psychologyNotes.trim().isNotEmpty ||
              trade.confidenceLevel != null ||
              trade.disciplineRating != null,
        )
        .toList(growable: false);
    final psychologyScores = psychologyTrades
        .map((trade) {
          final confidence = _normalizedScore(trade.confidenceLevel);
          final discipline = _normalizedScore(trade.disciplineRating);
          if (confidence == null) return discipline ?? 0.0;
          if (discipline == null) return confidence;
          return (confidence + discipline) / 2;
        })
        .where((value) => value > 0)
        .toList(growable: false);

    final planViolations = trades
        .where((trade) => !trade.followedTradingPlan)
        .toList(growable: false);
    final riskViolations = trades
        .where((trade) => !trade.followedRiskRules)
        .toList(growable: false);
    final stopLossViolations = trades
        .where((trade) => !trade.followedSlRule)
        .toList(growable: false);
    final takeProfitViolations = trades
        .where((trade) => !trade.followedTpRule)
        .toList(growable: false);
    final sessionViolations = trades
        .where((trade) => !trade.followedSessionRules)
        .toList(growable: false);
    final planCompliance = _compliance(
      trades,
      (trade) => trade.followedTradingPlan,
    );
    final riskCompliance = _compliance(
      trades,
      (trade) => trade.followedRiskRules,
    );
    final stopLossCompliance = _compliance(
      trades,
      (trade) => trade.followedSlRule,
    );
    final takeProfitCompliance = _compliance(
      trades,
      (trade) => trade.followedTpRule,
    );
    final sessionCompliance = _compliance(
      trades,
      (trade) => trade.followedSessionRules,
    );
    final consistency = _consistency(report, riskCompliance);
    final drawdown = _drawdown(report);
    final tradesByDay = _groupByDay(trades);
    final tags = trades.expand((trade) => trade.mistakeTags).map(_normalizeTag);
    final fomoIncidents = tags.where((tag) => tag.contains('fomo')).length;
    final comparisons = _comparisons(report, previousReport);
    final riskStatus = _riskStatus(
      report: report,
      riskCompliance: riskCompliance,
    );

    final model = StatisticsViewModel(
      report: report,
      previousReport: previousReport,
      planCompliance: planCompliance,
      riskCompliance: riskCompliance,
      stopLossCompliance: stopLossCompliance,
      takeProfitCompliance: takeProfitCompliance,
      sessionCompliance: sessionCompliance,
      psychologyCompletion: trades.isEmpty
          ? 0
          : psychologyTrades.length / trades.length * 100,
      averagePsychologyScore: psychologyScores.isEmpty
          ? 0
          : psychologyScores.reduce((left, right) => left + right) /
                psychologyScores.length,
      strategyStats: _breakdown(
        trades,
        (trade) => trade.strategyName?.trim().isNotEmpty == true
            ? trade.strategyName!.trim()
            : 'Unspecified strategy',
      ),
      accountStats: _breakdown(
        trades,
        (trade) => trade.tradingAccountId?.trim().isNotEmpty == true
            ? trade.tradingAccountId!.trim()
            : 'Manual account',
      ),
      planViolations: planViolations,
      riskViolations: riskViolations,
      stopLossViolations: stopLossViolations,
      takeProfitViolations: takeProfitViolations,
      sessionViolations: sessionViolations,
      consistencyScore: consistency.score,
      strongestConsistencyArea: consistency.strongest,
      weakestConsistencyArea: consistency.weakest,
      stableTradingDays: consistency.stableDays,
      profitConcentration: consistency.profitConcentration,
      averageDailyResult: consistency.averageDaily,
      dailyResultDeviation: consistency.deviation,
      riskStatus: riskStatus,
      currentDrawdownAmount: drawdown.currentAmount,
      currentDrawdownPercent: drawdown.currentPercent,
      drawdownTimeline: drawdown.timeline,
      tradesByDay: tradesByDay,
      revengeTradingIncidents: report.behaviourProfile.revenge.eventCount,
      fomoIncidents: fomoIncidents,
      overtradingIncidents: report.behaviourProfile.overtradingDays,
      unplannedTrades: planViolations.length,
      insights: const [],
      comparisons: comparisons,
    );
    return model._withInsights(_insights(model));
  }

  StatisticsViewModel _withInsights(List<PerformanceInsight> value) =>
      StatisticsViewModel(
        report: report,
        previousReport: previousReport,
        planCompliance: planCompliance,
        riskCompliance: riskCompliance,
        stopLossCompliance: stopLossCompliance,
        takeProfitCompliance: takeProfitCompliance,
        sessionCompliance: sessionCompliance,
        psychologyCompletion: psychologyCompletion,
        averagePsychologyScore: averagePsychologyScore,
        strategyStats: strategyStats,
        accountStats: accountStats,
        planViolations: planViolations,
        riskViolations: riskViolations,
        stopLossViolations: stopLossViolations,
        takeProfitViolations: takeProfitViolations,
        sessionViolations: sessionViolations,
        consistencyScore: consistencyScore,
        strongestConsistencyArea: strongestConsistencyArea,
        weakestConsistencyArea: weakestConsistencyArea,
        stableTradingDays: stableTradingDays,
        profitConcentration: profitConcentration,
        averageDailyResult: averageDailyResult,
        dailyResultDeviation: dailyResultDeviation,
        riskStatus: riskStatus,
        currentDrawdownAmount: currentDrawdownAmount,
        currentDrawdownPercent: currentDrawdownPercent,
        drawdownTimeline: drawdownTimeline,
        tradesByDay: tradesByDay,
        revengeTradingIncidents: revengeTradingIncidents,
        fomoIncidents: fomoIncidents,
        overtradingIncidents: overtradingIncidents,
        unplannedTrades: unplannedTrades,
        insights: value,
        comparisons: comparisons,
      );

  static double _compliance(
    List<TradingJournalEntry> trades,
    bool Function(TradingJournalEntry trade) test,
  ) {
    if (trades.isEmpty) return 0;
    return trades.where(test).length / trades.length * 100;
  }

  static double? _normalizedScore(int? value) {
    if (value == null) return null;
    return value <= 10 ? value * 10.0 : value.clamp(0, 100).toDouble();
  }

  static List<TradingBreakdownStat> _breakdown(
    List<TradingJournalEntry> trades,
    String Function(TradingJournalEntry trade) labelFor,
  ) {
    final grouped = <String, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      grouped.putIfAbsent(labelFor(trade), () => []).add(trade);
    }
    final result = <TradingBreakdownStat>[
      for (final entry in grouped.entries)
        _breakdownRow(entry.key, entry.value),
    ]..sort((left, right) => right.profitLoss.compareTo(left.profitLoss));
    return List.unmodifiable(result);
  }

  static TradingBreakdownStat _breakdownRow(
    String label,
    List<TradingJournalEntry> trades,
  ) {
    final wins = trades.where((trade) => trade.profitLoss > 0).length;
    final losses = trades.where((trade) => trade.profitLoss < 0).length;
    final decided = wins + losses;
    final profit = trades.fold<double>(
      0,
      (sum, trade) => sum + trade.profitLoss,
    );
    return TradingBreakdownStat(
      label: label,
      trades: trades.length,
      wins: wins,
      losses: losses,
      profitLoss: profit,
      pips: trades.fold<double>(0, (sum, trade) => sum + trade.pips),
      winRate: decided == 0 ? 0 : wins / decided * 100,
      averageProfitLoss: trades.isEmpty ? 0 : profit / trades.length,
    );
  }

  static ({
    double score,
    String strongest,
    String weakest,
    int stableDays,
    double profitConcentration,
    double averageDaily,
    double deviation,
  })
  _consistency(TradingReport report, double riskCompliance) {
    final daily = report.dailyStats.map((item) => item.profitLoss).toList();
    final average = daily.isEmpty
        ? 0.0
        : daily.reduce((left, right) => left + right) / daily.length;
    final variance = daily.isEmpty
        ? 0.0
        : daily.fold<double>(
                0,
                (sum, value) => sum + math.pow(value - average, 2),
              ) /
              daily.length;
    final deviation = math.sqrt(variance);
    final stableDays = daily.where((value) {
      if (deviation == 0) return true;
      return (value - average).abs() <= deviation;
    }).length;
    final dailyScore = daily.isEmpty
        ? 0.0
        : (100 -
                  (deviation /
                      math.max(
                        1.0,
                        daily.map((value) => value.abs()).reduce(math.max),
                      ) *
                      100))
              .clamp(0, 100)
              .toDouble();
    final concentration = report.summary.totalProfit <= 0
        ? 0.0
        : (report.summary.largestWin / report.summary.totalProfit * 100)
              .clamp(0, 100)
              .toDouble();
    final concentrationScore = (100 - math.max(0, concentration - 20) * 1.25)
        .clamp(0, 100)
        .toDouble();
    final areas = <String, double>{
      'Daily results': dailyScore,
      'Position sizing': report.positionProfile.score,
      'Risk per trade': riskCompliance,
      'Profit distribution': concentrationScore,
    };
    final ranked = areas.entries.toList()
      ..sort((left, right) => right.value.compareTo(left.value));
    final score = report.summary.hasTrades
        ? areas.values.reduce((left, right) => left + right) / areas.length
        : 0.0;
    return (
      score: score,
      strongest: ranked.first.key,
      weakest: ranked.last.key,
      stableDays: stableDays,
      profitConcentration: concentration,
      averageDaily: average,
      deviation: deviation,
    );
  }

  static ({
    double currentAmount,
    double currentPercent,
    List<PerformanceDrawdownPoint> timeline,
  })
  _drawdown(TradingReport report) {
    // MT5 reports use the live balance to infer the selected period's opening
    // balance. Manual reports retain their deposited-capital baseline so cash
    // withdrawals never masquerade as trading drawdown.
    var equity = report.filter.source == TradingReportSource.mt5
        ? math.max(
            0.0,
            report.summary.currentBalance - report.summary.netProfitLoss,
          )
        : report.capital.totalDeposits;
    var peak = equity;
    final timeline = <PerformanceDrawdownPoint>[];
    for (final trade in report.trades) {
      equity += trade.profitLoss;
      peak = math.max(peak, equity);
      final amount = math.max(0.0, peak - equity);
      timeline.add(
        PerformanceDrawdownPoint(
          date: trade.closeDate,
          amount: amount,
          percent: peak > 0 ? amount / peak * 100 : 0,
        ),
      );
    }
    final current = timeline.isEmpty
        ? PerformanceDrawdownPoint(
            date: DateTime.fromMillisecondsSinceEpoch(0),
            amount: 0,
            percent: 0,
          )
        : timeline.last;
    return (
      currentAmount: current.amount,
      currentPercent: current.percent,
      timeline: List.unmodifiable(timeline),
    );
  }

  static Map<DateTime, List<TradingJournalEntry>> _groupByDay(
    List<TradingJournalEntry> trades,
  ) {
    final grouped = <DateTime, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      final date = DateTime(
        trade.closeDate.year,
        trade.closeDate.month,
        trade.closeDate.day,
      );
      grouped.putIfAbsent(date, () => []).add(trade);
    }
    return Map.unmodifiable(grouped);
  }

  static String _normalizeTag(String value) =>
      value.trim().toLowerCase().replaceAll(RegExp(r'[-_\s]+'), ' ');

  static String _riskStatus({
    required TradingReport report,
    required double riskCompliance,
  }) {
    if (!report.summary.hasTrades) return 'Data unavailable';
    final drawdown = report.summary.maxDrawdownPercent;
    if (drawdown >= 10 || report.ruleMonitor.any((item) => item.breached)) {
      return 'Critical';
    }
    if (drawdown >= 8 || riskCompliance < 50) return 'High Risk';
    if (drawdown >= 5 || riskCompliance < 75) return 'Caution';
    return 'Safe';
  }

  static Map<String, PerformanceMetricComparison> _comparisons(
    TradingReport current,
    TradingReport? previous,
  ) {
    if (previous == null) return const {};
    final now = current.summary;
    final before = previous.summary;
    return Map.unmodifiable({
      'netProfit': PerformanceMetricComparison(
        current: now.netProfitLoss,
        previous: before.netProfitLoss,
        higherIsBetter: true,
      ),
      'winRate': PerformanceMetricComparison(
        current: now.winRate,
        previous: before.winRate,
        higherIsBetter: true,
      ),
      'profitFactor': PerformanceMetricComparison(
        current: now.profitFactor ?? 0,
        previous: before.profitFactor ?? 0,
        higherIsBetter: true,
      ),
      'drawdown': PerformanceMetricComparison(
        current: now.maxDrawdownPercent,
        previous: before.maxDrawdownPercent,
        higherIsBetter: false,
      ),
      'expectancy': PerformanceMetricComparison(
        current: now.expectancy,
        previous: before.expectancy,
        higherIsBetter: true,
      ),
      'score': PerformanceMetricComparison(
        current: current.scoreProfile.score,
        previous: previous.scoreProfile.score,
        higherIsBetter: true,
      ),
    });
  }

  static List<PerformanceInsight> _insights(StatisticsViewModel model) {
    if (!model.report.summary.hasTrades) return const [];
    final result = <PerformanceInsight>[];
    final winRate = model.comparison('winRate');
    if (winRate != null && winRate.difference.abs() >= .1) {
      result.add(
        PerformanceInsight(
          title: winRate.improved ? 'Win rate improved' : 'Win rate decreased',
          message:
              'Win rate changed by {value} percentage points versus the previous equivalent period.',
          values: {'value': winRate.difference.abs().toStringAsFixed(1)},
          calculation: 'Current win rate minus previous-period win rate.',
          kind: winRate.improved
              ? PerformanceInsightKind.fact
              : PerformanceInsightKind.attention,
        ),
      );
    }
    final drawdown = model.comparison('drawdown');
    if (drawdown != null && drawdown.difference.abs() >= .1) {
      result.add(
        PerformanceInsight(
          title: drawdown.improved
              ? 'Drawdown decreased'
              : 'Drawdown increased',
          message:
              'Maximum drawdown changed by {value} percentage points versus the previous equivalent period.',
          values: {'value': drawdown.difference.abs().toStringAsFixed(1)},
          calculation:
              'Current maximum balance drawdown minus previous drawdown.',
          kind: drawdown.improved
              ? PerformanceInsightKind.fact
              : PerformanceInsightKind.attention,
        ),
      );
    }
    if (model.report.pairStats.isNotEmpty) {
      final best = model.report.pairStats.first;
      result.add(
        PerformanceInsight(
          title: 'Strongest symbol',
          message:
              '{symbol} contributed the highest net result in the selected period.',
          values: {'symbol': best.label},
          calculation: 'Symbols ranked by summed realized profit and loss.',
        ),
      );
    }
    if (model.profitConcentration > 40) {
      result.add(
        PerformanceInsight(
          title: 'Profit is concentrated',
          message:
              '{value}% of gross profit came from the largest winning trade.',
          values: {'value': model.profitConcentration.toStringAsFixed(1)},
          calculation: 'Largest winning trade divided by gross profit.',
          kind: PerformanceInsightKind.attention,
        ),
      );
    }
    if (model.report.duplicateTradesExcluded > 0) {
      result.add(
        PerformanceInsight(
          title: 'Duplicate records excluded',
          message:
              '{count} duplicate trade record(s) were excluded from every metric.',
          values: {'count': model.report.duplicateTradesExcluded},
          calculation:
              'Duplicate source, account and stable trade identifiers.',
        ),
      );
    }
    return List.unmodifiable(result.take(6));
  }
}
