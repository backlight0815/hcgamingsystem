import 'dart:math' as math;

import 'prop_firm_models.dart';

class ScenarioSimulationService {
  const ScenarioSimulationService();

  SimulationResult run(SimulationConfiguration configuration) {
    _validate(configuration);
    final primary = _runTrials(configuration, configuration.riskPerTrade);
    final reduced = _runTrials(configuration, configuration.riskPerTrade * .5);
    return SimulationResult(
      payoutFrequency: primary.payouts / configuration.trials,
      breachFrequency: primary.breaches / configuration.trials,
      medianDrawdown: _median(primary.drawdowns),
      worstDrawdown: primary.drawdowns.reduce(math.max),
      averageTradesToPayout: primary.tradesToPayout.isEmpty
          ? null
          : primary.tradesToPayout.reduce((a, b) => a + b) /
                primary.tradesToPayout.length,
      samplePath: primary.samplePath,
      reducedRiskMedianDrawdown: _median(reduced.drawdowns),
    );
  }

  _TrialSet _runTrials(
    SimulationConfiguration configuration,
    double riskPerTrade,
  ) {
    final random = math.Random(configuration.seed + riskPerTrade.round());
    var payouts = 0;
    var breaches = 0;
    final drawdowns = <double>[];
    final tradesToPayout = <int>[];
    var samplePath = <double>[];
    for (var trial = 0; trial < configuration.trials; trial++) {
      final balances = List<double>.filled(
        configuration.accountCount,
        configuration.startingBalance,
      );
      final peaks = [...balances];
      var maxDrawdown = 0.0;
      var accountIndex = 0;
      var dailyLosses = 0;
      final tradesToday = List<int>.filled(configuration.accountCount, 0);
      var reachedPayout = false;
      var breached = false;
      final path = <double>[balances.reduce((a, b) => a + b)];
      for (var trade = 1; trade <= configuration.tradeCount; trade++) {
        if (tradesToday.every(
              (count) => count >= configuration.maximumTradesPerDay,
            ) ||
            dailyLosses >= configuration.maximumPortfolioLossesPerDay) {
          tradesToday.fillRange(0, tradesToday.length, 0);
          dailyLosses = 0;
        }
        var checkedAccounts = 0;
        while (tradesToday[accountIndex] >= configuration.maximumTradesPerDay &&
            checkedAccounts < configuration.accountCount) {
          accountIndex = (accountIndex + 1) % configuration.accountCount;
          checkedAccounts++;
        }
        final win = random.nextDouble() < configuration.winRate;
        final r = win
            ? configuration.averageWinningR
            : -configuration.averageLosingR;
        final change = r * riskPerTrade - configuration.transactionCost;
        balances[accountIndex] += change;
        peaks[accountIndex] = math.max(
          peaks[accountIndex],
          balances[accountIndex],
        );
        maxDrawdown = math.max(
          maxDrawdown,
          peaks[accountIndex] - balances[accountIndex],
        );
        tradesToday[accountIndex]++;
        if (!win) dailyLosses++;
        if (balances[accountIndex] <= configuration.startingBalance * .9) {
          breached = true;
          break;
        }
        if (!reachedPayout &&
            balances.any(
              (balance) =>
                  balance - configuration.startingBalance >=
                  configuration.payoutThreshold,
            )) {
          reachedPayout = true;
          tradesToPayout.add(trade);
        }
        accountIndex = (accountIndex + 1) % configuration.accountCount;
        path.add(balances.reduce((a, b) => a + b));
      }
      if (reachedPayout) payouts++;
      if (breached) breaches++;
      drawdowns.add(maxDrawdown);
      if (trial == 0) samplePath = path;
    }
    return _TrialSet(
      payouts: payouts,
      breaches: breaches,
      drawdowns: drawdowns,
      tradesToPayout: tradesToPayout,
      samplePath: samplePath,
    );
  }

  void _validate(SimulationConfiguration configuration) {
    if (configuration.accountCount < 1 ||
        configuration.startingBalance <= 0 ||
        configuration.riskPerTrade <= 0 ||
        configuration.winRate < 0 ||
        configuration.winRate > 1 ||
        configuration.averageWinningR <= 0 ||
        configuration.averageLosingR <= 0 ||
        configuration.maximumTradesPerDay < 1 ||
        configuration.maximumPortfolioLossesPerDay < 1 ||
        configuration.tradeCount < 1 ||
        configuration.trials < 1) {
      throw const FormatException('Simulation settings are invalid.');
    }
  }

  double _median(List<double> values) {
    final sorted = [...values]..sort();
    final middle = sorted.length ~/ 2;
    return sorted.length.isOdd
        ? sorted[middle]
        : (sorted[middle - 1] + sorted[middle]) / 2;
  }
}

class _TrialSet {
  const _TrialSet({
    required this.payouts,
    required this.breaches,
    required this.drawdowns,
    required this.tradesToPayout,
    required this.samplePath,
  });

  final int payouts;
  final int breaches;
  final List<double> drawdowns;
  final List<int> tradesToPayout;
  final List<double> samplePath;
}
