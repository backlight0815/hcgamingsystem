import '../models/trading_pair.dart';

class CalculatedTrade {
  const CalculatedTrade({required this.pips, required this.profitLoss});

  final double pips;
  final double profitLoss;
}

class TradingCalculator {
  double? suggestedExitPrice({
    required int result,
    required double? entryPrice,
    required double? currentExitPrice,
    double? stopLoss,
    double? takeProfit,
  }) {
    return switch (result) {
      1 => takeProfit ?? currentExitPrice,
      2 => stopLoss ?? currentExitPrice,
      3 => entryPrice ?? currentExitPrice,
      _ => currentExitPrice,
    };
  }

  CalculatedTrade calculate({
    required TradingPair pair,
    required double entryPrice,
    required double exitPrice,
    required double lotSize,
    required int result,
  }) {
    final factor = pair.pipFactor <= 0 ? 0.00000001 : pair.pipFactor;
    final rawPips = (exitPrice - entryPrice).abs() / factor;
    final pips = _round(rawPips, pair.pipDecimal);
    final absoluteProfitLoss = _round(pips * lotSize * 10, 2);

    return CalculatedTrade(
      pips: pips,
      profitLoss: switch (result) {
        1 => absoluteProfitLoss.abs(),
        2 => -absoluteProfitLoss.abs(),
        3 => 0,
        _ => 0,
      },
    );
  }

  double _round(double value, int decimals) {
    return double.parse(value.toStringAsFixed(decimals.clamp(0, 8)));
  }
}
