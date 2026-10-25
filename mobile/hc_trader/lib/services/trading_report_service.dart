import 'dart:math' as math;

import 'package:intl/intl.dart';

import '../models/capital_transaction.dart';
import '../models/trading_data_source.dart';
import '../models/trading_journal_entry.dart';
import '../models/trading_report.dart';
import 'trading_report_intelligence_service.dart';
import 'trading_time_service.dart';

class WithdrawalEligibility {
  const WithdrawalEligibility({
    required this.eligible,
    required this.message,
    required this.bestDayRule,
    required this.grossProfitRule,
  });

  final bool eligible;
  final String message;
  final TradingBestDayRule bestDayRule;
  final TradingGrossProfitRule grossProfitRule;
}

class TradingReportService {
  static const bestDayLimitPercent = 40.0;
  static const minGrossProfitPercent = 2.0;
  static const maxDailyLossPercent = 5.0;
  static const maxTotalLossPercent = 10.0;
  static const minProfitableDays = 3;

  final _timeService = TradingTimeService();
  final _intelligence = TradingReportIntelligenceService();

  TradingReport build({
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
    TradingReportFilter filter = const TradingReportFilter(),
    String timeView = TradingTimeService.malaysia,
    int timeViewOffsetMinutes = TradingTimeService.malaysiaOffsetMinutes,
    String traderLabel = 'Trader',
    TradingReportAccount? account,
    double? currentBalanceOverride,
  }) {
    final scopedTrades = journals
        .where(
          (trade) =>
              trade.type.trim().toLowerCase() == 'trade' &&
              trade.status.trim().toLowerCase() == 'closed' &&
              _matchesReportScope(trade, filter) &&
              _matchesDate(trade.closeDate, filter),
        )
        .toList(growable: false);
    final trades = _deduplicateTrades(scopedTrades)
      ..sort((left, right) => left.closeDate.compareTo(right.closeDate));
    final capitalRecords =
        capitals
            .where((capital) => _matchesDate(capital.depositDate, filter))
            .toList()
          ..sort(
            (left, right) => right.depositDate.compareTo(left.depositDate),
          );
    final capital = _capitalSummary(capitalRecords);
    final summary = _summary(
      trades: trades,
      capital: capital,
      currentBalanceOverride: currentBalanceOverride,
    );
    final dailyStats = _dailyStats(trades, startingBalance: 0);
    final monthlyStats = _monthlyStats(trades);
    final pairStats = _pairStats(trades);
    final directionStats = _directionStats(trades);
    final weekdayStats = _weekdayStats(trades);
    final sessionStats = _sessionStats(
      trades,
      timeView: timeView,
      timeViewOffsetMinutes: timeViewOffsetMinutes,
    );
    final ruleMonitor = _ruleMonitor(summary);
    final intelligence = _intelligence.build(
      trades: trades,
      summary: summary,
      pairStats: pairStats,
      sessionStats: sessionStats,
      ruleMonitor: ruleMonitor,
      startingCapital: capital.totalDeposits,
    );

    return TradingReport(
      filter: filter,
      generatedAt: DateTime.now(),
      trades: trades,
      capital: capital,
      summary: summary,
      dailyStats: dailyStats,
      monthlyStats: monthlyStats,
      pairStats: pairStats,
      directionStats: directionStats,
      weekdayStats: weekdayStats,
      sessionStats: sessionStats,
      ruleMonitor: ruleMonitor,
      traderLabel: traderLabel,
      account: account,
      scoreProfile: intelligence.score,
      positionProfile: intelligence.position,
      behaviourProfile: intelligence.behaviour,
      levelProfile: intelligence.level,
      hedgingProfile: intelligence.hedging,
      performanceStatus: intelligence.performanceStatus,
      traderStyle: intelligence.traderStyle,
      executiveInsight: intelligence.executiveInsight,
      resumeSummary: intelligence.resumeSummary,
      technicalSkill: intelligence.technicalSkill,
      riskProfile: intelligence.riskProfile,
      administrationAction: intelligence.administrationAction,
      strengths: intelligence.strengths,
      risks: intelligence.risks,
      coachingFocus: intelligence.coachingFocus,
      duplicateTradesExcluded: scopedTrades.length - trades.length,
    );
  }

  WithdrawalEligibility withdrawalEligibility({
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
    required double requestedAmount,
  }) {
    final report = build(journals: journals, capitals: capitals);
    final currentBalance = report.summary.currentBalance;
    if (requestedAmount > currentBalance) {
      return WithdrawalEligibility(
        eligible: false,
        message: 'Withdrawal failed: insufficient balance.',
        bestDayRule: report.summary.bestDayRule,
        grossProfitRule: report.summary.grossProfitRule,
      );
    }

    final bestDayRule = report.summary.bestDayRule;
    final grossProfitRule = report.summary.grossProfitRule;
    if (bestDayRule.passed && grossProfitRule.passed) {
      return WithdrawalEligibility(
        eligible: true,
        message: '',
        bestDayRule: bestDayRule,
        grossProfitRule: grossProfitRule,
      );
    }

    final messages = <String>[];
    if (!bestDayRule.passed) {
      messages.add(
        '40% Best Day Rule pending (best day is '
        '${bestDayRule.scorePercent.toStringAsFixed(2)}%; need '
        '${bestDayRule.additionalProfitNeeded.toStringAsFixed(2)}u more generated profit).',
      );
    }

    if (!grossProfitRule.passed) {
      messages.add(
        '2% gross profit rule pending ('
        '${grossProfitRule.grossProfit.toStringAsFixed(2)}u / '
        '${grossProfitRule.requiredAmount.toStringAsFixed(2)}u).',
      );
    }

    return WithdrawalEligibility(
      eligible: false,
      message: 'Withdrawal failed: ${messages.join(' ')}',
      bestDayRule: bestDayRule,
      grossProfitRule: grossProfitRule,
    );
  }

  TradingCapitalReport _capitalSummary(List<CapitalTransaction> records) {
    final deposits = _round(
      records
          .where((capital) => capital.type == 1)
          .fold<double>(0, (sum, capital) => sum + capital.amount.abs()),
      2,
    );
    final withdrawals = _round(
      records
          .where((capital) => capital.type == 2)
          .fold<double>(0, (sum, capital) => sum + capital.amount.abs()),
      2,
    );

    return TradingCapitalReport(
      totalDeposits: deposits,
      totalWithdrawals: withdrawals,
      netCapital: _round(
        records.fold<double>(0, (sum, c) => sum + c.amount),
        2,
      ),
      transactions: records.length,
      records: List.unmodifiable(records),
    );
  }

  TradingReportSummary _summary({
    required List<TradingJournalEntry> trades,
    required TradingCapitalReport capital,
    double? currentBalanceOverride,
  }) {
    final winningTrades = trades
        .where((trade) => trade.profitLoss > 0)
        .toList();
    final losingTrades = trades.where((trade) => trade.profitLoss < 0).toList();
    final breakevenTrades = trades
        .where((trade) => trade.profitLoss == 0)
        .toList();
    final closedTradeCount = winningTrades.length + losingTrades.length;
    final totalTrades = trades.length;
    final totalProfit = _round(
      winningTrades.fold<double>(0, (sum, trade) => sum + trade.profitLoss),
      2,
    );
    final totalLoss = _round(
      losingTrades.fold<double>(
        0,
        (sum, trade) => sum + trade.profitLoss.abs(),
      ),
      2,
    );
    final netProfitLoss = _round(
      trades.fold<double>(0, (sum, trade) => sum + trade.profitLoss),
      2,
    );
    final reportStartingBalance = currentBalanceOverride == null
        ? capital.totalDeposits
        : math.max(0.0, currentBalanceOverride - netProfitLoss);
    final averageWin = winningTrades.isEmpty
        ? 0.0
        : _round(totalProfit / winningTrades.length, 2);
    final averageLoss = losingTrades.isEmpty
        ? 0.0
        : _round(totalLoss / losingTrades.length, 2);
    final winRate = closedTradeCount > 0
        ? _round((winningTrades.length / closedTradeCount) * 100, 2)
        : 0.0;
    final lossRate = closedTradeCount > 0
        ? _round((losingTrades.length / closedTradeCount) * 100, 2)
        : 0.0;
    final profitFactor = totalLoss > 0
        ? _round(totalProfit / totalLoss, 2)
        : null;
    final profitFactorLabel = totalLoss > 0
        ? profitFactor!.toStringAsFixed(2)
        : totalProfit > 0
        ? 'Perfect'
        : 'N/A';
    final winRateDecimal = totalTrades > 0
        ? winningTrades.length / totalTrades
        : 0.0;
    final lossRateDecimal = totalTrades > 0
        ? losingTrades.length / totalTrades
        : 0.0;
    final expectancy = _round(
      (winRateDecimal * averageWin) - (lossRateDecimal * averageLoss),
      2,
    );
    final dailyProfitLoss = _dailyProfitLoss(trades);
    final bestDay = dailyProfitLoss.values.isEmpty
        ? 0.0
        : _round(dailyProfitLoss.values.reduce(math.max), 2);
    final worstDay = dailyProfitLoss.values.isEmpty
        ? 0.0
        : _round(dailyProfitLoss.values.reduce(math.min), 2);
    final profitableDays = dailyProfitLoss.values
        .where((profitLoss) => profitLoss > 0)
        .length;
    final losingDays = dailyProfitLoss.values
        .where((profitLoss) => profitLoss < 0)
        .length;
    final tradingDays = dailyProfitLoss.length;
    final dailyWinRate = tradingDays > 0
        ? _round((profitableDays / tradingDays) * 100, 2)
        : 0.0;
    final bestDayRule = _bestDayRule(dailyProfitLoss);
    final grossProfitRule = _grossProfitRule(trades, reportStartingBalance);
    final drawdown = _drawdown(trades, reportStartingBalance);
    final maxDailyLossAmount = _round(worstDay < 0 ? worstDay.abs() : 0, 2);
    final maxDailyLossPercent = reportStartingBalance > 0
        ? _round((maxDailyLossAmount / reportStartingBalance) * 100, 2)
        : 0.0;
    final overallLossPercent = reportStartingBalance > 0
        ? _round(
            (math.min(0.0, netProfitLoss).abs() / reportStartingBalance) * 100,
            2,
          )
        : 0.0;
    final recoveryFactor = drawdown.amount > 0
        ? _round(netProfitLoss / drawdown.amount, 2)
        : null;
    final recoveryFactorLabel = drawdown.amount > 0
        ? recoveryFactor!.toStringAsFixed(2)
        : netProfitLoss > 0
        ? 'No Drawdown'
        : 'N/A';
    final payoffRatio = averageLoss > 0
        ? _round(averageWin / averageLoss, 2)
        : null;
    final payoffRatioLabel = averageLoss > 0
        ? payoffRatio!.toStringAsFixed(2)
        : averageWin > 0
        ? 'Perfect'
        : 'N/A';
    final streaks = _streaks(trades);
    final firstTradeDate = trades.isEmpty ? null : trades.first.closeDate;
    final lastTradeDate = trades.isEmpty ? null : trades.last.closeDate;
    final durationDays = firstTradeDate != null && lastTradeDate != null
        ? DateTime(lastTradeDate.year, lastTradeDate.month, lastTradeDate.day)
                  .difference(
                    DateTime(
                      firstTradeDate.year,
                      firstTradeDate.month,
                      firstTradeDate.day,
                    ),
                  )
                  .inDays +
              1
        : 0;
    final durations = _durations(trades);
    final weekdayStats = _weekdayStats(trades);
    final bestWeekday = weekdayStats.where((stat) => stat.trades > 0).toList()
      ..sort((left, right) => right.profitLoss.compareTo(left.profitLoss));

    return TradingReportSummary(
      totalTrades: totalTrades,
      winningTrades: winningTrades.length,
      losingTrades: losingTrades.length,
      breakevenTrades: breakevenTrades.length,
      winRate: winRate,
      lossRate: lossRate,
      totalProfit: totalProfit,
      totalLoss: totalLoss,
      netProfitLoss: netProfitLoss,
      averageWin: averageWin,
      averageLoss: averageLoss,
      profitFactor: profitFactor,
      profitFactorLabel: profitFactorLabel,
      expectancy: expectancy,
      totalPips: _round(trades.fold<double>(0, (sum, t) => sum + t.pips), 2),
      averagePips: totalTrades > 0
          ? _round(
              trades.fold<double>(0, (sum, t) => sum + t.pips) / totalTrades,
              2,
            )
          : 0.0,
      totalLots: _round(
        trades.fold<double>(0, (sum, trade) => sum + trade.lotSize),
        2,
      ),
      averageLot: totalTrades > 0
          ? _round(
              trades.fold<double>(0, (sum, trade) => sum + trade.lotSize) /
                  totalTrades,
              2,
            )
          : 0.0,
      largestWin: winningTrades.isEmpty
          ? 0.0
          : _round(
              winningTrades.map((trade) => trade.profitLoss).reduce(math.max),
              2,
            ),
      largestLoss: losingTrades.isEmpty
          ? 0.0
          : _round(
              losingTrades.map((trade) => trade.profitLoss).reduce(math.min),
              2,
            ),
      currentBalance: currentBalanceOverride == null
          ? _round(
              capital.totalDeposits + netProfitLoss - capital.totalWithdrawals,
              2,
            )
          : _round(currentBalanceOverride, 2),
      growthPercent: reportStartingBalance > 0
          ? _round((netProfitLoss / reportStartingBalance) * 100, 2)
          : 0.0,
      maxDrawdownAmount: drawdown.amount,
      maxDrawdownPercent: drawdown.percent,
      maxDailyLossAmount: maxDailyLossAmount,
      maxDailyLossPercent: maxDailyLossPercent,
      overallLossPercent: overallLossPercent,
      bestDay: bestDay,
      worstDay: worstDay,
      profitableDays: profitableDays,
      losingDays: losingDays,
      tradingDays: tradingDays,
      dailyWinRate: dailyWinRate,
      bestDayRule: bestDayRule,
      grossProfitRule: grossProfitRule,
      biggestWinningDay: bestDayRule.bestWinningDay,
      longestWinStreak: streaks.win,
      longestLossStreak: streaks.loss,
      recoveryFactor: recoveryFactor,
      recoveryFactorLabel: recoveryFactorLabel,
      payoffRatio: payoffRatio,
      payoffRatioLabel: payoffRatioLabel,
      durationDays: durationDays,
      firstTradeDate: firstTradeDate,
      lastTradeDate: lastTradeDate,
      averageHoldingMinutes: durations.average,
      averageHoldingLabel: _formatMinutes(durations.average),
      medianHoldingMinutes: durations.median,
      medianHoldingLabel: _formatMinutes(durations.median),
      maxHoldingMinutes: durations.maximum,
      maxHoldingLabel: _formatMinutes(durations.maximum),
      bestWeekday: bestWeekday.isEmpty
          ? 'N/A'
          : bestWeekday.first.shortLabel ?? bestWeekday.first.label,
    );
  }

  List<TradingDailyStat> _dailyStats(
    List<TradingJournalEntry> trades, {
    required double startingBalance,
  }) {
    var runningTotal = startingBalance;
    return _groupTradesByDate(trades).entries
        .map((entry) {
          final profitLoss = _round(
            entry.value.fold<double>(0, (sum, trade) => sum + trade.profitLoss),
            2,
          );
          runningTotal = _round(runningTotal + profitLoss, 2);

          return TradingDailyStat(
            date: entry.key,
            trades: entry.value.length,
            profitLoss: profitLoss,
            cumulativeProfitLoss: runningTotal,
          );
        })
        .toList(growable: false);
  }

  List<TradingBreakdownStat> _monthlyStats(List<TradingJournalEntry> trades) {
    final grouped = <String, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      final key = DateFormat('yyyy-MM').format(trade.closeDate);
      grouped.putIfAbsent(key, () => []).add(trade);
    }

    final rows = grouped.entries
        .map((entry) => _breakdownRow(label: entry.key, trades: entry.value))
        .toList();
    rows.sort((left, right) => left.label.compareTo(right.label));
    return rows;
  }

  List<TradingBreakdownStat> _pairStats(List<TradingJournalEntry> trades) {
    final grouped = <String, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      grouped.putIfAbsent(trade.pair.toUpperCase(), () => []).add(trade);
    }

    final rows = grouped.entries
        .map((entry) => _breakdownRow(label: entry.key, trades: entry.value))
        .toList();
    rows.sort((left, right) => right.profitLoss.compareTo(left.profitLoss));
    return rows;
  }

  List<TradingBreakdownStat> _directionStats(List<TradingJournalEntry> trades) {
    final grouped = <int, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      grouped.putIfAbsent(trade.direction, () => []).add(trade);
    }

    final rows = grouped.entries.map((entry) {
      final label = switch (entry.key) {
        1 => 'Buy',
        2 => 'Sell',
        _ => 'N/A',
      };
      return _breakdownRow(label: label, trades: entry.value);
    }).toList();
    rows.sort((left, right) => left.label.compareTo(right.label));
    return rows;
  }

  List<TradingBreakdownStat> _weekdayStats(List<TradingJournalEntry> trades) {
    const days = [
      ('Sunday', 'Sun'),
      ('Monday', 'Mon'),
      ('Tuesday', 'Tue'),
      ('Wednesday', 'Wed'),
      ('Thursday', 'Thu'),
      ('Friday', 'Fri'),
      ('Saturday', 'Sat'),
    ];
    final grouped = <int, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      grouped.putIfAbsent(trade.closeDate.weekday % 7, () => []).add(trade);
    }

    return [
      for (final indexed in days.indexed)
        _breakdownRow(
          label: indexed.$2.$1,
          shortLabel: indexed.$2.$2,
          trades: grouped[indexed.$1] ?? const [],
        ),
    ];
  }

  List<TradingBreakdownStat> _sessionStats(
    List<TradingJournalEntry> trades, {
    required String timeView,
    required int timeViewOffsetMinutes,
  }) {
    const sessions = [
      (name: 'Asia', start: 0, end: 8),
      (name: 'London', start: 8, end: 13),
      (name: 'New York', start: 13, end: 22),
      (name: 'Late Session', start: 22, end: 24),
    ];

    return [
      for (final session in sessions)
        _breakdownRow(
          label: session.name,
          trades: trades.where((trade) {
            final closedAt = _timeService.fromMalaysia(
              value: trade.closeDate,
              targetMode: timeView,
              targetOffsetMinutes: timeViewOffsetMinutes,
            );
            final hour = closedAt.hour;
            return hour >= session.start && hour < session.end;
          }).toList(),
        ),
    ];
  }

  List<TradingRuleMonitorItem> _ruleMonitor(TradingReportSummary summary) {
    final grossProfitRule = summary.grossProfitRule;
    final bestDayRule = summary.bestDayRule;
    final recoveryProgress = summary.recoveryFactor != null
        ? _clampProgress((summary.recoveryFactor! / 3) * 100)
        : summary.recoveryFactorLabel == 'No Drawdown'
        ? 100.0
        : 0.0;
    final recoveryPassed =
        summary.recoveryFactorLabel == 'No Drawdown' ||
        (summary.recoveryFactor != null && summary.recoveryFactor! >= 1.5);
    final dailyLossUsage = maxDailyLossPercent > 0
        ? _round((summary.maxDailyLossPercent / maxDailyLossPercent) * 100, 2)
        : 0.0;
    final totalLossUsage = maxTotalLossPercent > 0
        ? _round((summary.maxDrawdownPercent / maxTotalLossPercent) * 100, 2)
        : 0.0;
    final profitableDaysProgress = _round(
      (summary.profitableDays / minProfitableDays) * 100,
      2,
    );

    return [
      TradingRuleMonitorItem(
        key: 'recovery_factor',
        title: 'Recovery Quality',
        value: summary.recoveryFactorLabel,
        progress: recoveryProgress,
        status: recoveryPassed ? 'Strong' : 'Monitoring',
        tone: recoveryPassed ? 'success' : 'primary',
        caption: 'Net P/L per 1u max drawdown',
      ),
      TradingRuleMonitorItem(
        key: 'gross_profit_floor',
        title: '2% Gross Profit',
        value:
            '${grossProfitRule.grossProfit.toStringAsFixed(2)}u / ${grossProfitRule.requiredAmount.toStringAsFixed(2)}u',
        progress: grossProfitRule.minimumPercent > 0
            ? _clampProgress(
                (grossProfitRule.achievedPercent /
                        grossProfitRule.minimumPercent) *
                    100,
              )
            : 0,
        status: grossProfitRule.passed ? 'Passed' : 'Monitoring',
        tone: grossProfitRule.passed ? 'success' : 'primary',
        caption:
            'Minimum ${grossProfitRule.minimumPercent.toStringAsFixed(0)}% gross profit on account balance',
      ),
      TradingRuleMonitorItem(
        key: 'daily_loss',
        title: 'Max Daily Loss',
        value: '${summary.maxDailyLossPercent.toStringAsFixed(2)}% used',
        progress: _clampProgress(dailyLossUsage),
        status: summary.maxDailyLossPercent > maxDailyLossPercent
            ? 'Breached'
            : 'Within Limit',
        tone: summary.maxDailyLossPercent > maxDailyLossPercent
            ? 'danger'
            : 'success',
        caption: '${maxDailyLossPercent.toStringAsFixed(0)}% limit',
      ),
      TradingRuleMonitorItem(
        key: 'total_loss',
        title: 'Max Total Drawdown',
        value: '${summary.maxDrawdownPercent.toStringAsFixed(2)}% used',
        progress: _clampProgress(totalLossUsage),
        status: summary.maxDrawdownPercent > maxTotalLossPercent
            ? 'Breached'
            : 'Within Limit',
        tone: summary.maxDrawdownPercent > maxTotalLossPercent
            ? 'danger'
            : 'success',
        caption: '${maxTotalLossPercent.toStringAsFixed(0)}% limit',
      ),
      TradingRuleMonitorItem(
        key: 'consistency',
        title: '40% Best Day Rule',
        value: '${bestDayRule.scorePercent.toStringAsFixed(2)}%',
        progress: summary.consistencyPercent > 0
            ? _clampProgress(
                (summary.consistencyPercent / bestDayLimitPercent) * 100,
              )
            : 0,
        status: bestDayRule.passed ? 'Passed' : 'Monitoring',
        tone: bestDayRule.passed ? 'success' : 'warning',
        caption:
            'Best day max ${bestDayLimitPercent.toStringAsFixed(0)}%; need ${bestDayRule.additionalProfitNeeded.toStringAsFixed(2)}u more if pending',
      ),
      TradingRuleMonitorItem(
        key: 'profitable_days',
        title: 'Profitable Days',
        value: '${summary.profitableDays} / $minProfitableDays',
        progress: _clampProgress(profitableDaysProgress),
        status: summary.profitableDays >= minProfitableDays
            ? 'Passed'
            : 'Monitoring',
        tone: summary.profitableDays >= minProfitableDays
            ? 'success'
            : 'primary',
        caption: 'Minimum target',
      ),
    ];
  }

  TradingBestDayRule _bestDayRule(Map<DateTime, double> dailyProfitLoss) {
    final winningDays = dailyProfitLoss.entries
        .where((entry) => entry.value > 0)
        .toList();
    winningDays.sort((left, right) => right.value.compareTo(left.value));
    final bestDay = winningDays.isEmpty ? null : winningDays.first;
    final bestWinningDay = _round(bestDay?.value ?? 0, 2);
    final totalGeneratedProfit = _round(
      math.max(0, dailyProfitLoss.values.fold<double>(0, (sum, v) => sum + v)),
      2,
    );
    final scorePercent = totalGeneratedProfit > 0
        ? _round((bestWinningDay / totalGeneratedProfit) * 100, 2)
        : 0.0;
    final requiredTotalProfit = bestWinningDay > 0
        ? _round(bestWinningDay / (bestDayLimitPercent / 100), 2)
        : 0.0;
    final additionalProfitNeeded = _round(
      math.max(0, requiredTotalProfit - totalGeneratedProfit),
      2,
    );
    final passed =
        totalGeneratedProfit > 0 && scorePercent <= bestDayLimitPercent;

    return TradingBestDayRule(
      limitPercent: bestDayLimitPercent,
      bestWinningDay: bestWinningDay,
      bestWinningDayDate: bestDay?.key,
      totalGeneratedProfit: totalGeneratedProfit,
      scorePercent: scorePercent,
      requiredTotalProfit: requiredTotalProfit,
      additionalProfitNeeded: additionalProfitNeeded,
      passed: passed,
      status: passed
          ? 'Passed'
          : bestWinningDay > 0
          ? 'Needs More Profit'
          : 'No Profit Yet',
      grade: _bestDayGrade(scorePercent, totalGeneratedProfit),
    );
  }

  TradingGrossProfitRule _grossProfitRule(
    List<TradingJournalEntry> trades,
    double accountBalance,
  ) {
    final roundedBalance = _round(math.max(0, accountBalance), 2);
    final grossProfit = _round(
      trades
          .where((trade) => trade.profitLoss > 0)
          .fold<double>(0, (sum, trade) => sum + trade.profitLoss),
      2,
    );
    final requiredAmount = _round(
      roundedBalance * (minGrossProfitPercent / 100),
      2,
    );
    final achievedPercent = roundedBalance > 0
        ? _round((grossProfit / roundedBalance) * 100, 2)
        : 0.0;
    final passed =
        roundedBalance > 0 && grossProfit >= requiredAmount && grossProfit > 0;

    return TradingGrossProfitRule(
      minimumPercent: minGrossProfitPercent,
      accountBalance: roundedBalance,
      grossProfit: grossProfit,
      requiredAmount: requiredAmount,
      remainingAmount: _round(math.max(0, requiredAmount - grossProfit), 2),
      achievedPercent: achievedPercent,
      passed: passed,
      status: passed ? 'Passed' : 'Needs More Gross Profit',
    );
  }

  Map<DateTime, double> _dailyProfitLoss(List<TradingJournalEntry> trades) {
    final result = <DateTime, double>{};
    for (final trade in trades) {
      final key = _tradingDay(trade.closeDate);
      result[key] = _round((result[key] ?? 0) + trade.profitLoss, 2);
    }

    return Map.fromEntries(
      result.entries.toList()
        ..sort((left, right) => left.key.compareTo(right.key)),
    );
  }

  Map<DateTime, List<TradingJournalEntry>> _groupTradesByDate(
    List<TradingJournalEntry> trades,
  ) {
    final result = <DateTime, List<TradingJournalEntry>>{};
    for (final trade in trades) {
      result.putIfAbsent(_tradingDay(trade.closeDate), () => []).add(trade);
    }

    return Map.fromEntries(
      result.entries.toList()
        ..sort((left, right) => left.key.compareTo(right.key)),
    );
  }

  TradingBreakdownStat _breakdownRow({
    required String label,
    required List<TradingJournalEntry> trades,
    String? shortLabel,
  }) {
    final wins = trades.where((trade) => trade.profitLoss > 0).length;
    final losses = trades.where((trade) => trade.profitLoss < 0).length;
    final closedTrades = wins + losses;
    final profitLoss = _round(
      trades.fold<double>(0, (sum, trade) => sum + trade.profitLoss),
      2,
    );

    return TradingBreakdownStat(
      label: label,
      shortLabel: shortLabel,
      trades: trades.length,
      wins: wins,
      losses: losses,
      profitLoss: profitLoss,
      pips: _round(trades.fold<double>(0, (sum, trade) => sum + trade.pips), 2),
      winRate: closedTrades > 0 ? _round((wins / closedTrades) * 100, 2) : 0,
      averageProfitLoss: trades.isEmpty
          ? 0
          : _round(profitLoss / trades.length, 2),
    );
  }

  ({double amount, double percent}) _drawdown(
    List<TradingJournalEntry> trades,
    double startingBalance,
  ) {
    var equity = startingBalance;
    var peak = startingBalance;
    var maxDrawdown = 0.0;
    var maxDrawdownPercent = 0.0;

    for (final trade in trades) {
      equity += trade.profitLoss;
      peak = math.max(peak, equity);
      final drawdown = math.max(0.0, peak - equity);
      final drawdownPercent = peak > 0 ? (drawdown / peak) * 100 : 0.0;

      if (drawdown > maxDrawdown) {
        maxDrawdown = drawdown;
        maxDrawdownPercent = drawdownPercent;
      }
    }

    return (
      amount: _round(maxDrawdown, 2),
      percent: _round(maxDrawdownPercent, 2),
    );
  }

  ({int win, int loss}) _streaks(List<TradingJournalEntry> trades) {
    var longestWinStreak = 0;
    var longestLossStreak = 0;
    var currentWinStreak = 0;
    var currentLossStreak = 0;

    for (final trade in trades) {
      if (trade.profitLoss > 0) {
        currentWinStreak++;
        currentLossStreak = 0;
      } else if (trade.profitLoss < 0) {
        currentLossStreak++;
        currentWinStreak = 0;
      } else {
        currentWinStreak = 0;
        currentLossStreak = 0;
      }

      longestWinStreak = math.max(longestWinStreak, currentWinStreak);
      longestLossStreak = math.max(longestLossStreak, currentLossStreak);
    }

    return (win: longestWinStreak, loss: longestLossStreak);
  }

  ({int average, int median, int maximum}) _durations(
    List<TradingJournalEntry> trades,
  ) {
    final durations =
        trades
            .where((trade) => trade.closeDate.isAfter(trade.openDate))
            .map(
              (trade) => trade.closeDate.difference(trade.openDate).inMinutes,
            )
            .toList()
          ..sort();
    if (durations.isEmpty) {
      return (average: 0, median: 0, maximum: 0);
    }

    final average =
        (durations.reduce((sum, value) => sum + value) / durations.length)
            .round();
    final median = durations.length.isOdd
        ? durations[durations.length ~/ 2]
        : ((durations[(durations.length ~/ 2) - 1] +
                      durations[durations.length ~/ 2]) /
                  2)
              .round();

    return (average: average, median: median, maximum: durations.last);
  }

  String _formatMinutes(int minutes) {
    if (minutes < 60) {
      return '${minutes}m';
    }

    final hours = minutes ~/ 60;
    final remainingMinutes = minutes % 60;
    if (hours < 24) {
      return remainingMinutes > 0
          ? '${hours}h ${remainingMinutes}m'
          : '${hours}h';
    }

    final days = hours ~/ 24;
    final remainingHours = hours % 24;
    return remainingHours > 0 ? '${days}d ${remainingHours}h' : '${days}d';
  }

  String _bestDayGrade(double scorePercent, double totalGeneratedProfit) {
    if (totalGeneratedProfit <= 0) {
      return 'N/A';
    }

    return switch (scorePercent) {
      <= 20 => 'A+',
      <= 30 => 'A',
      <= bestDayLimitPercent => 'B',
      <= 50 => 'C',
      <= 65 => 'D',
      _ => 'F',
    };
  }

  bool _matchesDate(DateTime value, TradingReportFilter filter) {
    if (filter.dateFilter.isActive) return filter.dateFilter.matches(value);
    return (filter.month == null || value.month == filter.month) &&
        (filter.year == null || value.year == filter.year);
  }

  bool _matchesReportScope(
    TradingJournalEntry trade,
    TradingReportFilter filter,
  ) {
    final ownerUid = filter.ownerUid.trim();
    if (ownerUid.isNotEmpty && trade.userId != ownerUid) return false;

    final isManual = trade.source.isManualDisplaySource;
    final selectedAccount = filter.mainMt5AccountId.trim();
    final isSelectedMt5 =
        (trade.source == TradingRecordSource.mt5Sync ||
            (trade.source == TradingRecordSource.excelImport &&
                trade.tradingAccountId?.trim().isNotEmpty == true)) &&
        (selectedAccount.isEmpty ||
            trade.tradingAccountId?.trim() == selectedAccount);

    return switch (filter.source) {
      TradingReportSource.manual => isManual,
      TradingReportSource.mt5 => isSelectedMt5,
      TradingReportSource.all => isManual || isSelectedMt5,
    };
  }

  List<TradingJournalEntry> _deduplicateTrades(
    List<TradingJournalEntry> trades,
  ) {
    final seen = <String>{};
    return [
      for (final trade in trades)
        if (seen.add(_tradeIdentity(trade))) trade,
    ];
  }

  String _tradeIdentity(TradingJournalEntry trade) {
    final id = trade.id.trim();
    if (id.isNotEmpty) {
      return '${trade.source.storageValue}|${trade.tradingAccountId ?? ''}|$id';
    }
    // Legacy rows occasionally have no document/ticket id. Only those rows use
    // a conservative exact fingerprint so distinct split fills are preserved.
    return <Object?>[
      trade.source.storageValue,
      trade.tradingAccountId ?? '',
      trade.openDate.toUtc().microsecondsSinceEpoch,
      trade.closeDate.toUtc().microsecondsSinceEpoch,
      trade.pair.trim().toUpperCase(),
      trade.direction,
      trade.lotSize,
      trade.entryPrice,
      trade.exitPrice,
      trade.profitLoss,
    ].join('|');
  }

  DateTime _dateOnly(DateTime value) {
    return DateTime(value.year, value.month, value.day);
  }

  DateTime _tradingDay(DateTime value) {
    final shifted = value.hour < 5
        ? value.subtract(const Duration(days: 1))
        : value;
    return _dateOnly(shifted);
  }

  double _round(double value, int decimals) {
    if (value.isNaN || value.isInfinite) {
      return 0;
    }

    return double.parse(value.toStringAsFixed(decimals.clamp(0, 8)));
  }

  double _clampProgress(double value) {
    return math.max(0, math.min(100, _round(value, 2)));
  }
}
