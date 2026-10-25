import '../models/capital_transaction.dart';
import '../models/trading_journal_entry.dart';
import '../models/trading_summary.dart';

class TradingAnalytics {
  static const bestDayLimitPercent = 40.0;

  TradingSummary summarize({
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
  }) {
    final trades = journals.where((trade) => trade.status == 'closed').toList()
      ..sort((left, right) => left.closeDate.compareTo(right.closeDate));
    final winningTrades = trades
        .where((trade) => trade.profitLoss > 0)
        .toList();
    final losingTrades = trades.where((trade) => trade.profitLoss < 0).toList();
    final breakEvenTrades = trades
        .where((trade) => trade.profitLoss == 0)
        .toList();

    final totalWithoutBreakEven = winningTrades.length + losingTrades.length;
    final winRate = totalWithoutBreakEven > 0
        ? _round((winningTrades.length / totalWithoutBreakEven) * 100, 2)
        : 0.0;
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
    final netProfitLoss = _round(totalProfit - totalLoss, 2);
    final totalDeposits = _round(
      capitals
          .where((capital) => capital.type == 1)
          .fold<double>(0, (sum, capital) => sum + capital.amount.abs()),
      2,
    );
    final totalWithdrawals = _round(
      capitals
          .where((capital) => capital.type == 2)
          .fold<double>(0, (sum, capital) => sum + capital.amount.abs()),
      2,
    );
    final netCapital = capitals.fold<double>(
      0,
      (sum, capital) => sum + capital.amount,
    );
    final currentBalance = _round(netCapital + netProfitLoss, 2);
    final growthPercent = totalDeposits > 0 && netProfitLoss > 0
        ? _round((netProfitLoss / totalDeposits) * 100, 2)
        : 0.0;
    final averageWin = winningTrades.isEmpty
        ? 0.0
        : winningTrades.fold<double>(
                0,
                (sum, trade) => sum + trade.profitLoss,
              ) /
              winningTrades.length;
    final averageLoss = losingTrades.isEmpty
        ? 0.0
        : losingTrades.fold<double>(
                0,
                (sum, trade) => sum + trade.profitLoss.abs(),
              ) /
              losingTrades.length;
    final winRateDecimal = trades.isEmpty
        ? 0.0
        : winningTrades.length / trades.length;
    final lossRateDecimal = trades.isEmpty
        ? 0.0
        : losingTrades.length / trades.length;
    final expectancy = _round(
      (winRateDecimal * averageWin) - (lossRateDecimal * averageLoss),
      2,
    );
    final dailyProfitLoss = _dailyProfitLoss(trades);
    final bestDay = dailyProfitLoss.entries
        .where((entry) => entry.value > 0)
        .fold<MapEntry<DateTime, double>?>(
          null,
          (best, entry) =>
              best == null || entry.value > best.value ? entry : best,
        );
    final totalGeneratedProfit = dailyProfitLoss.values.fold<double>(
      0,
      (sum, value) => sum + value,
    );
    final bestWinningDay = _round(bestDay?.value ?? 0, 2);
    final consistencyPercent = totalGeneratedProfit > 0
        ? _round((bestWinningDay / totalGeneratedProfit) * 100, 2)
        : 0.0;

    return TradingSummary(
      totalTrades: trades.length,
      wins: winningTrades.length,
      losses: losingTrades.length,
      breakEvens: breakEvenTrades.length,
      winRate: winRate,
      totalProfit: totalProfit,
      totalLoss: totalLoss,
      netProfitLoss: netProfitLoss,
      totalDeposits: totalDeposits,
      totalWithdrawals: totalWithdrawals,
      currentBalance: currentBalance,
      growthPercent: growthPercent,
      expectancy: expectancy,
      maxDrawdownAmount: _maxDrawdownAmount(trades, netCapital),
      bestWinningDay: bestWinningDay,
      bestWinningDayDate: bestDay?.key,
      consistencyPercent: consistencyPercent,
      consistencyPassed:
          totalGeneratedProfit > 0 && consistencyPercent <= bestDayLimitPercent,
      dailyProfitLoss: dailyProfitLoss,
    );
  }

  Map<DateTime, double> _dailyProfitLoss(List<TradingJournalEntry> trades) {
    final result = <DateTime, double>{};

    for (final trade in trades) {
      final date = _tradingDay(trade.closeDate);
      result[date] = _round((result[date] ?? 0) + trade.profitLoss, 2);
    }

    return Map.fromEntries(
      result.entries.toList()
        ..sort((left, right) => left.key.compareTo(right.key)),
    );
  }

  DateTime _tradingDay(DateTime value) {
    final shifted = value.hour < 5
        ? value.subtract(const Duration(days: 1))
        : value;
    return DateTime(shifted.year, shifted.month, shifted.day);
  }

  double _maxDrawdownAmount(
    List<TradingJournalEntry> trades,
    double startingBalance,
  ) {
    var equity = startingBalance;
    var peak = startingBalance;
    var maxDrawdown = 0.0;

    for (final trade in trades) {
      equity += trade.profitLoss;
      if (equity > peak) {
        peak = equity;
      }

      final drawdown = peak - equity;
      if (drawdown > maxDrawdown) {
        maxDrawdown = drawdown;
      }
    }

    return _round(maxDrawdown, 2);
  }

  double _round(double value, int decimals) {
    return double.parse(value.toStringAsFixed(decimals.clamp(0, 8)));
  }
}
