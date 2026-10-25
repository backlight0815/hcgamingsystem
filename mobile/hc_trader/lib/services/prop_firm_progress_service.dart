import '../models/standalone_models.dart';
import '../models/trading_journal_entry.dart';

class PropFirmProgress {
  const PropFirmProgress({
    required this.netProfit,
    required this.profitPercent,
    required this.targetAmount,
    required this.remainingTarget,
    required this.worstDailyLoss,
    required this.maxDrawdown,
    required this.profitableDays,
    required this.status,
    required this.warnings,
  });

  final double netProfit;
  final double profitPercent;
  final double targetAmount;
  final double remainingTarget;
  final double worstDailyLoss;
  final double maxDrawdown;
  final int profitableDays;
  final String status;
  final List<String> warnings;
}

class PropFirmProgressService {
  const PropFirmProgressService();

  PropFirmProgress evaluate({
    required double startingBalance,
    required PropFirmRule rule,
    required List<TradingJournalEntry> trades,
  }) {
    final sorted = trades.where((trade) => trade.status == 'closed').toList()
      ..sort((left, right) => left.closeDate.compareTo(right.closeDate));
    final daily = <DateTime, double>{};
    var net = 0.0;
    var equity = startingBalance;
    var peak = startingBalance;
    var maxDrawdown = 0.0;

    for (final trade in sorted) {
      net += trade.profitLoss;
      equity += trade.profitLoss;
      if (equity > peak) peak = equity;
      final drawdown = peak - equity;
      if (drawdown > maxDrawdown) maxDrawdown = drawdown;
      final shifted = trade.closeDate.hour < 5
          ? trade.closeDate.subtract(const Duration(days: 1))
          : trade.closeDate;
      final day = DateTime(shifted.year, shifted.month, shifted.day);
      daily[day] = (daily[day] ?? 0) + trade.profitLoss;
    }

    final target = startingBalance * rule.profitTargetPercent / 100;
    final dailyLimit = startingBalance * rule.maxDailyLossPercent / 100;
    final overallLimit = startingBalance * rule.maxOverallLossPercent / 100;
    final worstDailyLoss = daily.values
        .where((value) => value < 0)
        .fold<double>(0, (worst, value) => value < worst ? value : worst);
    final profitableDays = daily.values.where((value) => value > 0).length;
    final warnings = <String>[];
    if (worstDailyLoss.abs() >= dailyLimit) {
      warnings.add('Daily loss limit reached.');
    } else if (worstDailyLoss.abs() >= dailyLimit * .8) {
      warnings.add('Daily loss is above 80% of its limit.');
    }
    if (maxDrawdown >= overallLimit) {
      warnings.add('Maximum overall loss limit reached.');
    } else if (maxDrawdown >= overallLimit * .8) {
      warnings.add('Overall drawdown is above 80% of its limit.');
    }
    if (profitableDays < rule.minimumTradingDays) {
      warnings.add(
        '${rule.minimumTradingDays - profitableDays} profitable day(s) still required.',
      );
    }

    final failed =
        worstDailyLoss.abs() >= dailyLimit || maxDrawdown >= overallLimit;
    final passed =
        !failed && net >= target && profitableDays >= rule.minimumTradingDays;

    return PropFirmProgress(
      netProfit: _round(net),
      profitPercent: startingBalance > 0
          ? _round(net / startingBalance * 100)
          : 0,
      targetAmount: _round(target),
      remainingTarget: _round((target - net).clamp(0, double.infinity)),
      worstDailyLoss: _round(worstDailyLoss),
      maxDrawdown: _round(maxDrawdown),
      profitableDays: profitableDays,
      status: failed
          ? 'Failed'
          : passed
          ? 'Passed'
          : warnings.isEmpty
          ? 'Safe'
          : 'Warning',
      warnings: warnings,
    );
  }

  static double _round(double value) => double.parse(value.toStringAsFixed(2));
}
