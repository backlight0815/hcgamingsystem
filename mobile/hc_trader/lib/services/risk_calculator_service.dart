import 'dart:math' as math;

class RiskCalculation {
  const RiskCalculation({
    required this.riskAmount,
    required this.stopDistance,
    required this.pipDistance,
    required this.lotSize,
    required this.riskReward,
    required this.projectedProfit,
    required this.projectedLoss,
    this.takeProfitDistance = 0,
    this.positionSize = 0,
    this.direction = 'buy',
    this.symbol = '',
    this.accountCurrency = 'USD',
    this.warnings = const [],
  });

  final double riskAmount;
  final double stopDistance;
  final double pipDistance;
  final double takeProfitDistance;
  final double lotSize;
  final double positionSize;
  final double riskReward;
  final double projectedProfit;
  final double projectedLoss;
  final String direction;
  final String symbol;
  final String accountCurrency;
  final List<String> warnings;
}

class RiskCalculatorService {
  const RiskCalculatorService();

  RiskCalculation calculate({
    required double accountBalance,
    required double riskPercent,
    required double entryPrice,
    required double stopLoss,
    required double takeProfit,
    required double pipSize,
    String direction = 'buy',
    double? riskAmount,
    double pipValuePerStandardLot = 10,
    double contractSize = 100000,
    double minLotSize = 0.01,
    double maxLotSize = 100,
    String symbol = '',
    String accountCurrency = 'USD',
  }) {
    if (accountBalance <= 0 ||
        riskPercent <= 0 ||
        entryPrice <= 0 ||
        stopLoss <= 0 ||
        takeProfit <= 0 ||
        pipSize <= 0 ||
        pipValuePerStandardLot <= 0 ||
        contractSize <= 0 ||
        minLotSize <= 0 ||
        maxLotSize <= 0 ||
        maxLotSize < minLotSize) {
      throw const FormatException('Enter valid positive values.');
    }

    final normalizedDirection = direction.trim().toLowerCase();
    if (normalizedDirection != 'buy' && normalizedDirection != 'sell') {
      throw const FormatException('Choose Buy or Sell direction.');
    }
    if (normalizedDirection == 'buy' && stopLoss >= entryPrice) {
      throw const FormatException(
        'For Buy trades, stop loss must be below entry.',
      );
    }
    if (normalizedDirection == 'buy' && takeProfit <= entryPrice) {
      throw const FormatException(
        'For Buy trades, take profit must be above entry.',
      );
    }
    if (normalizedDirection == 'sell' && stopLoss <= entryPrice) {
      throw const FormatException(
        'For Sell trades, stop loss must be above entry.',
      );
    }
    if (normalizedDirection == 'sell' && takeProfit >= entryPrice) {
      throw const FormatException(
        'For Sell trades, take profit must be below entry.',
      );
    }

    final resolvedRiskAmount = riskAmount != null && riskAmount > 0
        ? riskAmount
        : accountBalance * riskPercent / 100;
    final stopDistance = (entryPrice - stopLoss).abs();
    if (stopDistance == 0) {
      throw const FormatException('Stop loss must differ from entry price.');
    }
    final pipDistance = stopDistance / pipSize;
    final lotSize = resolvedRiskAmount / (pipDistance * pipValuePerStandardLot);
    final rewardDistance = math.max(0, (takeProfit - entryPrice).abs());
    final riskReward = rewardDistance / stopDistance;
    final warnings = <String>[
      if (riskPercent > 2)
        'Risk is above 2% of account balance. Consider reducing exposure.',
      if (lotSize < minLotSize)
        'Recommended lot size is below the configured minimum lot size.',
      if (lotSize > maxLotSize)
        'Recommended lot size is above the configured maximum lot size.',
    ];

    return RiskCalculation(
      riskAmount: _round(resolvedRiskAmount, 2),
      stopDistance: _round(stopDistance, 6),
      pipDistance: _round(pipDistance, 2),
      takeProfitDistance: _round(rewardDistance / pipSize, 2),
      lotSize: _round(lotSize, 4),
      positionSize: _round(lotSize * contractSize, 2),
      riskReward: _round(riskReward, 2),
      projectedProfit: _round(resolvedRiskAmount * riskReward, 2),
      projectedLoss: _round(-resolvedRiskAmount, 2),
      direction: normalizedDirection,
      symbol: symbol,
      accountCurrency: accountCurrency,
      warnings: warnings,
    );
  }

  static double _round(double value, int decimals) =>
      double.parse(value.toStringAsFixed(decimals));
}
