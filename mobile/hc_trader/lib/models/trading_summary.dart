class TradingSummary {
  const TradingSummary({
    required this.totalTrades,
    required this.wins,
    required this.losses,
    required this.breakEvens,
    required this.winRate,
    required this.totalProfit,
    required this.totalLoss,
    required this.netProfitLoss,
    required this.totalDeposits,
    required this.totalWithdrawals,
    required this.currentBalance,
    required this.growthPercent,
    required this.expectancy,
    required this.maxDrawdownAmount,
    required this.bestWinningDay,
    required this.bestWinningDayDate,
    required this.consistencyPercent,
    required this.consistencyPassed,
    required this.dailyProfitLoss,
  });

  final int totalTrades;
  final int wins;
  final int losses;
  final int breakEvens;
  final double winRate;
  final double totalProfit;
  final double totalLoss;
  final double netProfitLoss;
  final double totalDeposits;
  final double totalWithdrawals;
  final double currentBalance;
  final double growthPercent;
  final double expectancy;
  final double maxDrawdownAmount;
  final double bestWinningDay;
  final DateTime? bestWinningDayDate;
  final double consistencyPercent;
  final bool consistencyPassed;
  final Map<DateTime, double> dailyProfitLoss;

  bool get hasTrades => totalTrades > 0;
}
