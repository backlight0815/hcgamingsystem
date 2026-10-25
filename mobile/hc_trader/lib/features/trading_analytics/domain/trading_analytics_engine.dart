import 'dart:math' as math;

import 'analytics_filter.dart';
import 'analytics_result.dart';
import 'analytics_trade.dart';

class TradingAnalyticsEngine {
  String? _lastCacheKey;
  TradingAnalyticsResult? _lastResult;

  TradingAnalyticsResult analyze({
    required List<AnalyticsTrade> trades,
    required List<AnalyticsCashFlow> cashFlows,
    required AnalyticsFilter filter,
    double? configuredStartingBalance,
    double? liveCurrentBalance,
    double? liveCurrentEquity,
    List<AccountEquitySnapshot> equitySnapshots = const [],
    int timezoneOffsetMinutes = 0,
  }) {
    final cacheKey = _cacheKey(
      trades,
      cashFlows,
      filter,
      configuredStartingBalance,
      liveCurrentBalance,
      liveCurrentEquity,
      equitySnapshots,
      timezoneOffsetMinutes,
    );
    if (_lastCacheKey == cacheKey && _lastResult != null) return _lastResult!;

    final deduplication = _deduplicate(trades);
    final deduped = deduplication.trades;
    final filtered = deduped.where(filter.matches).toList(growable: false)
      ..sort(_tradeTimeComparison);
    final closed = filtered
        .where((trade) => trade.isEligibleClosed)
        .toList(growable: false);
    final open = filtered
        .where((trade) => trade.isOpen && !trade.isCashFlow)
        .toList(growable: false);
    final scopedCashFlows = cashFlows
        .where((flow) {
          if (!filter.sources.contains(flow.source)) return false;
          if (filter.accountId != '*' && flow.accountId != filter.accountId) {
            return false;
          }
          if (filter.startUtc != null &&
              flow.timestampUtc.isBefore(filter.startUtc!)) {
            return false;
          }
          if (filter.endUtcExclusive != null &&
              !flow.timestampUtc.isBefore(filter.endUtcExclusive!)) {
            return false;
          }
          return true;
        })
        .toList(growable: false);

    final allScopedFlows = cashFlows
        .where((flow) {
          if (!filter.sources.contains(flow.source)) return false;
          return filter.accountId == '*' || flow.accountId == filter.accountId;
        })
        .toList(growable: false);
    final allScopedTrades = deduped
        .where((trade) {
          if (!filter.sources.contains(trade.source)) return false;
          return filter.accountId == '*' || trade.accountId == filter.accountId;
        })
        .toList(growable: false);
    final startBalance = _startingBalance(
      configuredStartingBalance: configuredStartingBalance,
      liveCurrentBalance: liveCurrentBalance,
      allScopedTrades: allScopedTrades,
      allScopedFlows: allScopedFlows,
      startUtc: filter.startUtc,
    );
    final netPnl = _sum(closed.map((trade) => trade.netProfit));
    final floatingPnl = _sum(open.map((trade) => trade.netProfit));
    final filteredCashFlow = _sum(
      scopedCashFlows.map((flow) => flow.signedAmount),
    );
    final periodCurrentBalance = startBalance + netPnl + filteredCashFlow;
    final rangeIncludesNow =
        filter.endUtcExclusive == null ||
        filter.endUtcExclusive!.isAfter(DateTime.now().toUtc());
    final currentBalance = rangeIncludesNow && liveCurrentBalance != null
        ? liveCurrentBalance
        : periodCurrentBalance;
    final currentEquity = rangeIncludesNow && liveCurrentEquity != null
        ? liveCurrentEquity
        : currentBalance + floatingPnl;

    final cumulative = _cumulativePnl(closed);
    final tradingBalance = _balanceCurve(
      closed: closed,
      cashFlows: const [],
      startingBalance: startBalance,
    );
    final actualBalance = _balanceCurve(
      closed: closed,
      cashFlows: scopedCashFlows,
      startingBalance: startBalance,
    );
    final snapshots =
        equitySnapshots
            .where(
              (item) =>
                  (filter.accountId == '*' ||
                      item.accountId == filter.accountId) &&
                  (filter.startUtc == null ||
                      !item.timestampUtc.isBefore(filter.startUtc!)) &&
                  (filter.endUtcExclusive == null ||
                      item.timestampUtc.isBefore(filter.endUtcExclusive!)),
            )
            .toList()
          ..sort((a, b) => a.timestampUtc.compareTo(b.timestampUtc));
    final equityCurve = [
      for (final item in snapshots)
        AnalyticsPoint(
          timestampUtc: item.timestampUtc,
          value: item.equity,
          secondaryValue: item.balance,
          label: item.currency,
        ),
    ];
    final drawdown = _drawdown(tradingBalance);
    final daily = _aggregate(closed, _TimeBucket.day, timezoneOffsetMinutes);
    final weekly = _aggregate(closed, _TimeBucket.week, timezoneOffsetMinutes);
    final monthly = _aggregate(
      closed,
      _TimeBucket.month,
      timezoneOffsetMinutes,
    );
    final summary = _summary(
      closed: closed,
      open: open,
      startingBalance: startBalance,
      currentBalance: currentBalance,
      currentEquity: currentEquity,
      netPnl: netPnl,
      floatingPnl: floatingPnl,
      drawdown: drawdown,
      daily: daily,
      weekly: weekly,
      monthly: monthly,
    );
    final breakdowns = <String, List<AnalyticsBreakdown>>{
      'symbol': _breakdown(closed, (trade) => trade.symbol),
      'direction': _breakdown(closed, (trade) => trade.direction.name),
      'strategy': _breakdown(
        closed,
        (trade) => _labelOrUnspecified(trade.strategyName),
      ),
      'setup': _breakdown(
        closed,
        (trade) => _labelOrUnspecified(trade.setupName),
      ),
      'session': _breakdown(
        closed,
        (trade) => _labelOrUnspecified(
          trade.session ??
              _derivedSession(trade.openTimeUtc, timezoneOffsetMinutes),
        ),
      ),
      'weekday': _breakdown(
        closed,
        (trade) => _weekdayLabel(
          trade.closeTimeUtc!.add(Duration(minutes: timezoneOffsetMinutes)),
        ),
      ),
      'source': _breakdown(closed, (trade) => trade.source.name),
      'account': _breakdown(
        closed,
        (trade) => _labelOrUnspecified(trade.accountId, fallback: 'Unassigned'),
      ),
    };
    final currencies = {
      for (final trade in filtered)
        if (trade.accountCurrency.trim().isNotEmpty)
          trade.accountCurrency.trim().toUpperCase(),
      for (final flow in scopedCashFlows)
        if (flow.currency.trim().isNotEmpty) flow.currency.trim().toUpperCase(),
    };
    final currencyTotals = _currencyTotals(filtered);
    final quality = _dataQuality(
      raw: trades,
      filtered: filtered,
      cashFlows: scopedCashFlows,
      duplicates: deduplication.duplicates,
      equitySnapshotsAvailable: snapshots.isNotEmpty,
    );
    final monthlyTable = [
      for (final point in monthly)
        MonthlyAnalytics(
          monthUtc: point.timestampUtc,
          metrics: _breakdownRow(
            point.label,
            closed
                .where(
                  (trade) =>
                      _bucket(
                        trade.closeTimeUtc!,
                        _TimeBucket.month,
                        timezoneOffsetMinutes,
                      ) ==
                      point.timestampUtc,
                )
                .toList(growable: false),
          ),
          returnPercent: startBalance > 0
              ? point.value / startBalance * 100
              : null,
        ),
    ];
    final result = TradingAnalyticsResult(
      filter: filter,
      trades: List.unmodifiable(filtered),
      closedTrades: List.unmodifiable(closed),
      openTrades: List.unmodifiable(open),
      summary: summary,
      cumulativePnl: cumulative,
      tradingBalanceCurve: tradingBalance,
      actualBalanceCurve: actualBalance,
      equityCurve: List.unmodifiable(equityCurve),
      drawdownTimeline: drawdown.timeline,
      maximumDrawdownPeriod: drawdown.maximum,
      dailyPnl: daily,
      weeklyPnl: weekly,
      monthlyPnl: monthly,
      pnlHistogram: _histogram(closed.map((trade) => trade.netProfit)),
      lotHistogram: _histogram(
        closed.map((trade) => trade.lots).where((value) => value > 0),
      ),
      holdingHistogram: _histogram(
        closed
            .map((trade) => trade.holdingDuration?.inMinutes.toDouble())
            .whereType<double>(),
      ),
      lotOverTime: [
        for (final trade in closed)
          AnalyticsPoint(
            timestampUtc: trade.closeTimeUtc!,
            value: trade.lots,
            secondaryValue: trade.netProfit,
            tradeId: trade.id,
            symbol: trade.symbol,
          ),
      ],
      holdingOverTime: [
        for (final trade in closed)
          if (trade.holdingDuration != null)
            AnalyticsPoint(
              timestampUtc: trade.closeTimeUtc!,
              value: trade.holdingDuration!.inMinutes.toDouble(),
              secondaryValue: trade.netProfit,
              tradeId: trade.id,
              symbol: trade.symbol,
            ),
      ],
      rollingWinRate10: _rollingWinRate(closed, 10),
      rollingWinRate20: _rollingWinRate(closed, 20),
      rollingWinRate50: _rollingWinRate(closed, 50),
      rollingProfitFactor20: _rollingProfitFactor(closed, 20),
      winningDistribution: _distribution(
        closed.where((trade) => trade.isWin).map((trade) => trade.netProfit),
      ),
      losingDistribution: _distribution(
        closed.where((trade) => trade.isLoss).map((trade) => trade.netProfit),
      ),
      breakdowns: Map.unmodifiable(breakdowns),
      monthlyTable: List.unmodifiable(monthlyTable),
      insights: _insights(summary, breakdowns, closed, quality),
      dataQuality: quality,
      currencyTotals: currencyTotals,
      monetaryMetricsAvailable: currencies.length <= 1,
      currency: currencies.length == 1 ? currencies.single : '',
    );
    _lastCacheKey = cacheKey;
    _lastResult = result;
    return result;
  }

  String _cacheKey(
    List<AnalyticsTrade> trades,
    List<AnalyticsCashFlow> cashFlows,
    AnalyticsFilter filter,
    double? start,
    double? balance,
    double? equity,
    List<AccountEquitySnapshot> snapshots,
    int offset,
  ) {
    final tradeVersion = trades.fold<int>(
      trades.length,
      (value, trade) =>
          value ^
          trade.id.hashCode ^
          (trade.updatedAtUtc?.microsecondsSinceEpoch ?? 0) ^
          trade.netProfit.hashCode,
    );
    final flowVersion = cashFlows.fold<int>(
      cashFlows.length,
      (value, flow) =>
          value ^
          flow.id.hashCode ^
          flow.timestampUtc.microsecondsSinceEpoch ^
          flow.amount.hashCode,
    );
    return [
      tradeVersion,
      flowVersion,
      filter.cacheKey,
      start,
      balance,
      equity,
      snapshots.length,
      snapshots.lastOrNull?.timestampUtc.microsecondsSinceEpoch,
      offset,
    ].join('|');
  }
}

({List<AnalyticsTrade> trades, int duplicates}) _deduplicate(
  List<AnalyticsTrade> input,
) {
  final selected = <String, AnalyticsTrade>{};
  var duplicates = 0;
  for (final trade in input) {
    final key = trade.stableIdentity;
    final existing = selected[key];
    if (existing == null) {
      selected[key] = trade;
      continue;
    }
    duplicates++;
    if (trade.informationScore > existing.informationScore) {
      selected[key] = trade;
    }
  }
  return (
    trades: selected.values.toList(growable: false),
    duplicates: duplicates,
  );
}

double _startingBalance({
  required double? configuredStartingBalance,
  required double? liveCurrentBalance,
  required List<AnalyticsTrade> allScopedTrades,
  required List<AnalyticsCashFlow> allScopedFlows,
  required DateTime? startUtc,
}) {
  if (liveCurrentBalance != null) {
    final tradingSinceStart = _sum(
      allScopedTrades
          .where((trade) {
            if (!trade.isEligibleClosed) return false;
            return startUtc == null || !trade.closeTimeUtc!.isBefore(startUtc);
          })
          .map((trade) => trade.netProfit),
    );
    final cashSinceStart = _sum(
      allScopedFlows
          .where(
            (flow) => startUtc == null || !flow.timestampUtc.isBefore(startUtc),
          )
          .map((flow) => flow.signedAmount),
    );
    return liveCurrentBalance - tradingSinceStart - cashSinceStart;
  }
  if (configuredStartingBalance != null) {
    if (startUtc == null) return configuredStartingBalance;
    final tradingBeforeStart = _sum(
      allScopedTrades
          .where(
            (trade) =>
                trade.isEligibleClosed &&
                trade.closeTimeUtc!.isBefore(startUtc),
          )
          .map((trade) => trade.netProfit),
    );
    final cashBeforeStart = _sum(
      allScopedFlows
          .where((flow) => flow.timestampUtc.isBefore(startUtc))
          .map((flow) => flow.signedAmount),
    );
    return configuredStartingBalance + tradingBeforeStart + cashBeforeStart;
  }
  final depositsBeforeStart = _sum(
    allScopedFlows
        .where(
          (flow) =>
              flow.type == AnalyticsCashFlowType.deposit &&
              (startUtc == null || flow.timestampUtc.isBefore(startUtc)),
        )
        .map((flow) => flow.amount),
  );
  final withdrawalsBeforeStart = _sum(
    allScopedFlows
        .where(
          (flow) =>
              flow.type == AnalyticsCashFlowType.withdrawal &&
              (startUtc == null || flow.timestampUtc.isBefore(startUtc)),
        )
        .map((flow) => flow.amount),
  );
  return math.max(0, depositsBeforeStart - withdrawalsBeforeStart);
}

List<AnalyticsPoint> _cumulativePnl(List<AnalyticsTrade> closed) {
  var cumulative = 0.0;
  return List.unmodifiable([
    for (final trade in closed)
      AnalyticsPoint(
        timestampUtc: trade.closeTimeUtc!,
        value: cumulative += trade.netProfit,
        secondaryValue: trade.grossProfit,
        tradeCount: 1,
        totalLots: trade.lots,
        tradeId: trade.id,
        symbol: trade.symbol,
      ),
  ]);
}

List<AnalyticsPoint> _balanceCurve({
  required List<AnalyticsTrade> closed,
  required List<AnalyticsCashFlow> cashFlows,
  required double startingBalance,
}) {
  final events = <({DateTime time, double value, AnalyticsTrade? trade})>[
    for (final trade in closed)
      (time: trade.closeTimeUtc!, value: trade.netProfit, trade: trade),
    for (final flow in cashFlows)
      (time: flow.timestampUtc, value: flow.signedAmount, trade: null),
  ]..sort((a, b) => a.time.compareTo(b.time));
  var balance = startingBalance;
  return List.unmodifiable([
    if (events.isNotEmpty)
      AnalyticsPoint(
        timestampUtc: events.first.time.subtract(
          const Duration(microseconds: 1),
        ),
        value: startingBalance,
        label: 'starting-balance',
      ),
    for (final event in events)
      AnalyticsPoint(
        timestampUtc: event.time,
        value: balance += event.value,
        secondaryValue: event.value,
        tradeCount: event.trade == null ? 0 : 1,
        tradeId: event.trade?.id,
        symbol: event.trade?.symbol,
      ),
  ]);
}

({
  List<DrawdownPoint> timeline,
  DrawdownPeriod maximum,
  double currentAmount,
  double currentPercent,
})
_drawdown(List<AnalyticsPoint> curve) {
  if (curve.isEmpty) {
    return (
      timeline: const <DrawdownPoint>[],
      maximum: const DrawdownPeriod(amount: 0, percent: 0),
      currentAmount: 0,
      currentPercent: 0,
    );
  }
  var peak = curve.first.value;
  var peakDate = curve.first.timestampUtc;
  var maxAmount = 0.0;
  var maxPercent = 0.0;
  DateTime? maximumPeak;
  DateTime? maximumTrough;
  DateTime? recovery;
  var seekingRecovery = false;
  final timeline = <DrawdownPoint>[];
  for (final point in curve) {
    if (point.value >= peak) {
      if (seekingRecovery && recovery == null) recovery = point.timestampUtc;
      peak = point.value;
      peakDate = point.timestampUtc;
      seekingRecovery = false;
    }
    final amount = math.max(0.0, peak - point.value);
    final percent = peak > 0 ? amount / peak * 100 : 0.0;
    if (amount > maxAmount) {
      maxAmount = amount;
      maxPercent = percent;
      maximumPeak = peakDate;
      maximumTrough = point.timestampUtc;
      recovery = null;
      seekingRecovery = amount > 0;
    }
    timeline.add(
      DrawdownPoint(
        timestampUtc: point.timestampUtc,
        amount: amount,
        percent: percent,
        peak: peak,
      ),
    );
  }
  if (seekingRecovery) recovery = null;
  final current = timeline.last;
  return (
    timeline: List.unmodifiable(timeline),
    maximum: DrawdownPeriod(
      amount: maxAmount,
      percent: maxPercent,
      peakDateUtc: maximumPeak,
      troughDateUtc: maximumTrough,
      recoveryDateUtc: recovery,
    ),
    currentAmount: current.amount,
    currentPercent: current.percent,
  );
}

enum _TimeBucket { day, week, month }

List<AnalyticsPoint> _aggregate(
  List<AnalyticsTrade> trades,
  _TimeBucket bucket,
  int offset,
) {
  final grouped = <DateTime, List<AnalyticsTrade>>{};
  for (final trade in trades) {
    grouped
        .putIfAbsent(_bucket(trade.closeTimeUtc!, bucket, offset), () => [])
        .add(trade);
  }
  final keys = grouped.keys.toList()..sort();
  return List.unmodifiable([
    for (final key in keys)
      () {
        final rows = grouped[key]!;
        final decided = rows.where((trade) => !trade.isBreakeven).length;
        final wins = rows.where((trade) => trade.isWin).length;
        return AnalyticsPoint(
          timestampUtc: key,
          value: _sum(rows.map((trade) => trade.netProfit)),
          tradeCount: rows.length,
          winRate: decided == 0 ? 0 : wins / decided * 100,
          totalLots: _sum(rows.map((trade) => trade.lots)),
          label: switch (bucket) {
            _TimeBucket.day =>
              '${key.year}-${key.month.toString().padLeft(2, '0')}-${key.day.toString().padLeft(2, '0')}',
            _TimeBucket.week =>
              '${key.year}-W${_weekOfYear(key).toString().padLeft(2, '0')}',
            _TimeBucket.month =>
              '${key.year}-${key.month.toString().padLeft(2, '0')}',
          },
        );
      }(),
  ]);
}

DateTime _bucket(DateTime utc, _TimeBucket bucket, int offset) {
  final local = utc.toUtc().add(Duration(minutes: offset));
  final day = DateTime.utc(local.year, local.month, local.day);
  return switch (bucket) {
    _TimeBucket.day => day,
    _TimeBucket.week => day.subtract(Duration(days: local.weekday - 1)),
    _TimeBucket.month => DateTime.utc(local.year, local.month),
  };
}

AnalyticsSummary _summary({
  required List<AnalyticsTrade> closed,
  required List<AnalyticsTrade> open,
  required double startingBalance,
  required double currentBalance,
  required double currentEquity,
  required double netPnl,
  required double floatingPnl,
  required ({
    List<DrawdownPoint> timeline,
    DrawdownPeriod maximum,
    double currentAmount,
    double currentPercent,
  })
  drawdown,
  required List<AnalyticsPoint> daily,
  required List<AnalyticsPoint> weekly,
  required List<AnalyticsPoint> monthly,
}) {
  final wins = closed.where((trade) => trade.isWin).toList(growable: false);
  final losses = closed.where((trade) => trade.isLoss).toList(growable: false);
  final breakEven = closed.where((trade) => trade.isBreakeven).length;
  final decided = wins.length + losses.length;
  final grossProfit = _sum(
    closed
        .where((trade) => trade.grossProfit > 0)
        .map((trade) => trade.grossProfit),
  );
  final grossLoss = _sum(
    closed
        .where((trade) => trade.grossProfit < 0)
        .map((trade) => trade.grossProfit.abs()),
  );
  final averageWin = _mean(wins.map((trade) => trade.netProfit));
  final averageLoss = _mean(losses.map((trade) => trade.netProfit.abs()));
  final holdings = closed
      .map((trade) => trade.holdingDuration?.inMinutes.toDouble())
      .whereType<double>()
      .toList(growable: false);
  final winHoldings = wins
      .map((trade) => trade.holdingDuration?.inMinutes.toDouble())
      .whereType<double>();
  final lossHoldings = losses
      .map((trade) => trade.holdingDuration?.inMinutes.toDouble())
      .whereType<double>();
  final lots = closed
      .map((trade) => trade.lots)
      .where((value) => value > 0)
      .toList(growable: false);
  final streaks = _streaks(closed);
  final exposure = _maximumExposure([...closed, ...open]);
  final dailyValues = daily.map((point) => point.value).toList(growable: false);
  final weeklyValues = weekly.map((point) => point.value);
  final monthlyValues = monthly.map((point) => point.value);
  final dailyReturns = <double>[];
  var balance = startingBalance;
  for (final point in daily) {
    if (balance != 0) dailyReturns.add(point.value / balance);
    balance += point.value;
  }
  final dailyReturnDeviation = _sampleStandardDeviation(dailyReturns);
  final downside = dailyReturns.where((value) => value < 0).toList();
  final downsideDeviation = downside.isEmpty
      ? 0.0
      : math.sqrt(_mean(downside.map((value) => value * value)));
  final meanDailyReturn = _mean(dailyReturns);
  final durationDays = closed.length < 2
      ? 0
      : closed.last.closeTimeUtc!
                .difference(closed.first.closeTimeUtc!)
                .inDays
                .abs() +
            1;
  final annualizedReturn = startingBalance > 0 && durationDays > 0
      ? (netPnl / startingBalance) * (365 / durationDays)
      : null;
  final rValues = closed.map((trade) => trade.rMultiple).whereType<double>();
  final lotChanges = _lotChanges(closed);
  final tradingDays = daily.length;
  final profitableDays = dailyValues.where((value) => value > 0).length;
  final losingDays = dailyValues.where((value) => value < 0).length;
  final beDays = tradingDays - profitableDays - losingDays;
  return AnalyticsSummary(
    startingBalance: startingBalance,
    currentBalance: currentBalance,
    currentEquity: currentEquity,
    netTradingPnl: netPnl,
    grossProfit: grossProfit,
    grossLoss: grossLoss,
    floatingPnl: floatingPnl,
    totalReturnPercent: startingBalance > 0
        ? netPnl / startingBalance * 100
        : null,
    totalClosedTrades: closed.length,
    totalOpenTrades: open.length,
    winningTrades: wins.length,
    losingTrades: losses.length,
    breakevenTrades: breakEven,
    winRate: decided == 0 ? 0 : wins.length / decided * 100,
    lossRate: decided == 0 ? 0 : losses.length / decided * 100,
    averageTrade: _mean(closed.map((trade) => trade.netProfit)),
    averageWin: averageWin,
    averageLoss: averageLoss,
    largestWin: wins.isEmpty
        ? 0
        : wins.map((trade) => trade.netProfit).reduce(math.max),
    largestLoss: losses.isEmpty
        ? 0
        : losses.map((trade) => trade.netProfit).reduce(math.min),
    profitFactor: grossLoss > 0 ? grossProfit / grossLoss : null,
    payoffRatio: averageLoss > 0 ? averageWin / averageLoss : null,
    expectancy: decided == 0
        ? 0
        : (wins.length / decided * averageWin) -
              (losses.length / decided * averageLoss),
    averageHoldingMinutes: _mean(holdings),
    medianHoldingMinutes: _percentile(holdings, .5),
    averageWinningHoldingMinutes: _mean(winHoldings),
    averageLosingHoldingMinutes: _mean(lossHoldings),
    longestHoldingMinutes: holdings.isEmpty ? 0 : holdings.reduce(math.max),
    shortestHoldingMinutes: holdings.isEmpty ? 0 : holdings.reduce(math.min),
    maximumDrawdown: drawdown.maximum.amount,
    maximumDrawdownPercent: drawdown.maximum.percent,
    currentDrawdown: drawdown.currentAmount,
    currentDrawdownPercent: drawdown.currentPercent,
    recoveryFactor: drawdown.maximum.amount > 0
        ? netPnl / drawdown.maximum.amount
        : null,
    returnToDrawdown: drawdown.maximum.percent > 0 && startingBalance > 0
        ? (netPnl / startingBalance * 100) / drawdown.maximum.percent
        : null,
    averageLot: _mean(lots),
    medianLot: _percentile(lots, .5),
    maximumLot: lots.isEmpty ? 0 : lots.reduce(math.max),
    minimumLot: lots.isEmpty ? 0 : lots.reduce(math.min),
    totalLots: _sum(lots),
    lotStandardDeviation: _populationStandardDeviation(lots),
    maximumSimultaneousLots: exposure.total,
    maximumBuyExposure: exposure.buy,
    maximumSellExposure: exposure.sell,
    averageTradesPerDay: tradingDays == 0 ? 0 : closed.length / tradingDays,
    bestDay: dailyValues.isEmpty ? 0 : dailyValues.reduce(math.max),
    worstDay: dailyValues.isEmpty ? 0 : dailyValues.reduce(math.min),
    bestMonth: monthly.isEmpty
        ? 0
        : monthly.map((point) => point.value).reduce(math.max),
    worstMonth: monthly.isEmpty
        ? 0
        : monthly.map((point) => point.value).reduce(math.min),
    currentWinningStreak: streaks.currentWin,
    currentLosingStreak: streaks.currentLoss,
    maximumWinningStreak: streaks.maxWin,
    maximumLosingStreak: streaks.maxLoss,
    profitableDaysPercent: tradingDays == 0
        ? 0
        : profitableDays / tradingDays * 100,
    losingDaysPercent: tradingDays == 0 ? 0 : losingDays / tradingDays * 100,
    breakevenDaysPercent: tradingDays == 0 ? 0 : beDays / tradingDays * 100,
    averageDailyPnl: _mean(dailyValues),
    medianDailyPnl: _percentile(dailyValues, .5),
    averageWeeklyPnl: _mean(weeklyValues),
    averageMonthlyPnl: _mean(monthlyValues),
    dailyReturnStandardDeviation: dailyReturnDeviation,
    downsideDeviation: downsideDeviation,
    sharpeRatio: dailyReturns.length >= 2 && dailyReturnDeviation > 0
        ? meanDailyReturn / dailyReturnDeviation * math.sqrt(252)
        : null,
    sortinoRatio: dailyReturns.length >= 2 && downsideDeviation > 0
        ? meanDailyReturn / downsideDeviation * math.sqrt(252)
        : null,
    calmarRatio: annualizedReturn != null && drawdown.maximum.percent > 0
        ? annualizedReturn / drawdown.maximum.percent
        : null,
    averageRMultiple: rValues.isEmpty ? null : _mean(rValues),
    medianRMultiple: rValues.isEmpty ? null : _percentile(rValues, .5),
    expectedRMultiple: rValues.isEmpty ? null : _mean(rValues),
    pnlConsistencyScore: _consistencyScore(dailyValues),
    lotConsistencyScore: _consistencyScore(lots),
    holdingConsistencyScore: _consistencyScore(holdings),
    frequencyConsistencyScore: _consistencyScore(
      daily.map((point) => point.tradeCount.toDouble()),
    ),
    postLossLotChangePercent: lotChanges.afterLoss,
    postWinLotChangePercent: lotChanges.afterWin,
  );
}

({int currentWin, int currentLoss, int maxWin, int maxLoss}) _streaks(
  List<AnalyticsTrade> trades,
) {
  var currentWin = 0;
  var currentLoss = 0;
  var maxWin = 0;
  var maxLoss = 0;
  for (final trade in trades) {
    if (trade.isWin) {
      currentWin++;
      currentLoss = 0;
    } else if (trade.isLoss) {
      currentLoss++;
      currentWin = 0;
    } else {
      currentWin = 0;
      currentLoss = 0;
    }
    maxWin = math.max(maxWin, currentWin);
    maxLoss = math.max(maxLoss, currentLoss);
  }
  return (
    currentWin: currentWin,
    currentLoss: currentLoss,
    maxWin: maxWin,
    maxLoss: maxLoss,
  );
}

({double total, double buy, double sell}) _maximumExposure(
  List<AnalyticsTrade> trades,
) {
  final events = <({DateTime time, double buy, double sell, bool opening})>[];
  for (final trade in trades) {
    if (trade.lots <= 0) continue;
    final buy = trade.direction == AnalyticsTradeDirection.buy
        ? trade.lots
        : 0.0;
    final sell = trade.direction == AnalyticsTradeDirection.sell
        ? trade.lots
        : 0.0;
    events.add((time: trade.openTimeUtc, buy: buy, sell: sell, opening: true));
    final close = trade.closeTimeUtc;
    if (close != null && close.isAfter(trade.openTimeUtc)) {
      events.add((time: close, buy: -buy, sell: -sell, opening: false));
    }
  }
  events.sort((a, b) {
    final time = a.time.compareTo(b.time);
    if (time != 0) return time;
    return a.opening == b.opening ? 0 : (a.opening ? 1 : -1);
  });
  var buy = 0.0;
  var sell = 0.0;
  var maxBuy = 0.0;
  var maxSell = 0.0;
  var maxTotal = 0.0;
  for (final event in events) {
    buy = math.max(0, buy + event.buy);
    sell = math.max(0, sell + event.sell);
    maxBuy = math.max(maxBuy, buy);
    maxSell = math.max(maxSell, sell);
    maxTotal = math.max(maxTotal, buy + sell);
  }
  return (total: maxTotal, buy: maxBuy, sell: maxSell);
}

({double afterLoss, double afterWin}) _lotChanges(List<AnalyticsTrade> trades) {
  final afterLoss = <double>[];
  final afterWin = <double>[];
  for (var index = 1; index < trades.length; index++) {
    final before = trades[index - 1];
    final after = trades[index];
    if (before.lots <= 0) continue;
    final change = (after.lots - before.lots) / before.lots * 100;
    if (before.isLoss) afterLoss.add(change);
    if (before.isWin) afterWin.add(change);
  }
  return (afterLoss: _mean(afterLoss), afterWin: _mean(afterWin));
}

List<AnalyticsHistogramBin> _histogram(Iterable<double> raw) {
  final values = raw.where((value) => value.isFinite).toList()..sort();
  if (values.isEmpty) return const [];
  if (values.length == 1 || values.first == values.last) {
    return [
      AnalyticsHistogramBin(
        lower: values.first,
        upper: values.last,
        count: values.length,
      ),
    ];
  }
  final lower = values.length >= 20 ? _percentile(values, .01) : values.first;
  final upper = values.length >= 20 ? _percentile(values, .99) : values.last;
  final clippedLower = math.min(lower, upper);
  final clippedUpper = math.max(lower, upper);
  final iqr = _percentile(values, .75) - _percentile(values, .25);
  final fdWidth = iqr > 0 ? 2 * iqr / math.pow(values.length, 1 / 3) : 0.0;
  final suggested = fdWidth > 0
      ? ((clippedUpper - clippedLower) / fdWidth).ceil()
      : math.sqrt(values.length).ceil();
  final binCount = suggested.clamp(5, 24).toInt();
  final width = (clippedUpper - clippedLower) / binCount;
  if (width <= 0) {
    return [
      AnalyticsHistogramBin(
        lower: values.first,
        upper: values.last,
        count: values.length,
      ),
    ];
  }
  final counts = List<int>.filled(binCount, 0);
  var lowerOutliers = false;
  var upperOutliers = false;
  for (final value in values) {
    if (value < clippedLower) lowerOutliers = true;
    if (value > clippedUpper) upperOutliers = true;
    final clipped = value.clamp(clippedLower, clippedUpper);
    final index = math.min(
      binCount - 1,
      ((clipped - clippedLower) / width).floor(),
    );
    counts[index]++;
  }
  return List.unmodifiable([
    for (var index = 0; index < binCount; index++)
      AnalyticsHistogramBin(
        lower: clippedLower + index * width,
        upper: index == binCount - 1
            ? clippedUpper
            : clippedLower + (index + 1) * width,
        count: counts[index],
        includesLowerOutliers: index == 0 && lowerOutliers,
        includesUpperOutliers: index == binCount - 1 && upperOutliers,
      ),
  ]);
}

DistributionSummary _distribution(Iterable<double> raw) {
  final values = raw.where((value) => value.isFinite).toList()..sort();
  if (values.isEmpty) return const DistributionSummary.empty();
  return DistributionSummary(
    count: values.length,
    minimum: values.first,
    p25: _percentile(values, .25),
    median: _percentile(values, .5),
    p75: _percentile(values, .75),
    p90: _percentile(values, .9),
    maximum: values.last,
    mean: _mean(values),
    standardDeviation: _populationStandardDeviation(values),
  );
}

List<AnalyticsPoint> _rollingWinRate(List<AnalyticsTrade> trades, int window) {
  return List.unmodifiable([
    for (var index = 0; index < trades.length; index++)
      () {
        final start = math.max(0, index - window + 1);
        final sample = trades.sublist(start, index + 1);
        final decided = sample.where((trade) => !trade.isBreakeven).length;
        final wins = sample.where((trade) => trade.isWin).length;
        return AnalyticsPoint(
          timestampUtc: trades[index].closeTimeUtc!,
          value: decided == 0 ? 0 : wins / decided * 100,
          tradeCount: sample.length,
          label: sample.length < window ? 'small-sample' : '',
        );
      }(),
  ]);
}

List<AnalyticsPoint> _rollingProfitFactor(
  List<AnalyticsTrade> trades,
  int window,
) {
  return List.unmodifiable([
    for (var index = 0; index < trades.length; index++)
      () {
        final start = math.max(0, index - window + 1);
        final sample = trades.sublist(start, index + 1);
        final profit = _sum(
          sample
              .where((trade) => trade.netProfit > 0)
              .map((trade) => trade.netProfit),
        );
        final loss = _sum(
          sample
              .where((trade) => trade.netProfit < 0)
              .map((trade) => trade.netProfit.abs()),
        );
        return AnalyticsPoint(
          timestampUtc: trades[index].closeTimeUtc!,
          value: loss > 0
              ? profit / loss
              : profit > 0
              ? 10
              : 0,
          tradeCount: sample.length,
          label: loss == 0 && profit > 0 ? 'no-losing-trades' : '',
        );
      }(),
  ]);
}

List<AnalyticsBreakdown> _breakdown(
  List<AnalyticsTrade> trades,
  String Function(AnalyticsTrade) keyFor,
) {
  final grouped = <String, List<AnalyticsTrade>>{};
  for (final trade in trades) {
    final key = keyFor(trade);
    grouped.putIfAbsent(key, () => []).add(trade);
  }
  final result = [
    for (final entry in grouped.entries) _breakdownRow(entry.key, entry.value),
  ]..sort((a, b) => b.netProfit.compareTo(a.netProfit));
  return List.unmodifiable(result);
}

AnalyticsBreakdown _breakdownRow(String label, List<AnalyticsTrade> trades) {
  final wins = trades.where((trade) => trade.isWin).toList(growable: false);
  final losses = trades.where((trade) => trade.isLoss).toList(growable: false);
  final grossProfit = _sum(
    trades
        .where((trade) => trade.netProfit > 0)
        .map((trade) => trade.netProfit),
  );
  final grossLoss = _sum(
    trades
        .where((trade) => trade.netProfit < 0)
        .map((trade) => trade.netProfit.abs()),
  );
  final curve = _balanceCurve(
    closed: trades,
    cashFlows: const [],
    startingBalance: 0,
  );
  final drawdown = _drawdown(curve);
  final holdings = trades
      .map((trade) => trade.holdingDuration?.inMinutes.toDouble())
      .whereType<double>();
  return AnalyticsBreakdown(
    key: label.toLowerCase(),
    label: label,
    trades: trades.length,
    wins: wins.length,
    losses: losses.length,
    breakeven: trades.length - wins.length - losses.length,
    netProfit: _sum(trades.map((trade) => trade.netProfit)),
    grossProfit: grossProfit,
    grossLoss: grossLoss,
    winRate: wins.length + losses.length == 0
        ? 0
        : wins.length / (wins.length + losses.length) * 100,
    profitFactor: grossLoss > 0 ? grossProfit / grossLoss : null,
    averageTrade: _mean(trades.map((trade) => trade.netProfit)),
    averageWin: _mean(wins.map((trade) => trade.netProfit)),
    averageLoss: _mean(losses.map((trade) => trade.netProfit.abs())),
    maxDrawdown: drawdown.maximum.amount,
    totalLots: _sum(trades.map((trade) => trade.lots)),
    averageLots: _mean(trades.map((trade) => trade.lots)),
    averageHoldingMinutes: _mean(holdings),
    bestTrade: trades.isEmpty
        ? 0
        : trades.map((trade) => trade.netProfit).reduce(math.max),
    worstTrade: trades.isEmpty
        ? 0
        : trades.map((trade) => trade.netProfit).reduce(math.min),
    buyProfit: _sum(
      trades
          .where((trade) => trade.direction == AnalyticsTradeDirection.buy)
          .map((trade) => trade.netProfit),
    ),
    sellProfit: _sum(
      trades
          .where((trade) => trade.direction == AnalyticsTradeDirection.sell)
          .map((trade) => trade.netProfit),
    ),
  );
}

AnalyticsDataQuality _dataQuality({
  required List<AnalyticsTrade> raw,
  required List<AnalyticsTrade> filtered,
  required List<AnalyticsCashFlow> cashFlows,
  required int duplicates,
  required bool equitySnapshotsAvailable,
}) {
  final times =
      raw.map((trade) => trade.closeTimeUtc ?? trade.openTimeUtc).toList()
        ..sort();
  final excelTimes =
      raw
          .where((trade) => trade.source == AnalyticsTradeDataSource.excel)
          .map(
            (trade) =>
                trade.updatedAtUtc ?? trade.closeTimeUtc ?? trade.openTimeUtc,
          )
          .toList()
        ..sort();
  final mt5Times =
      raw
          .where((trade) => trade.source == AnalyticsTradeDataSource.mt5)
          .map(
            (trade) =>
                trade.updatedAtUtc ?? trade.closeTimeUtc ?? trade.openTimeUtc,
          )
          .toList()
        ..sort();
  return AnalyticsDataQuality(
    totalRawRecords: raw.length,
    filteredRecords: filtered.length,
    eligibleClosedTrades: filtered
        .where((trade) => trade.isEligibleClosed)
        .length,
    openTrades: filtered.where((trade) => trade.isOpen).length,
    deposits: cashFlows
        .where((flow) => flow.type == AnalyticsCashFlowType.deposit)
        .length,
    withdrawals: cashFlows
        .where((flow) => flow.type == AnalyticsCashFlowType.withdrawal)
        .length,
    cancelledOrders: raw
        .where((trade) => trade.status == AnalyticsTradeStatus.cancelled)
        .length,
    pendingOrders: raw
        .where((trade) => trade.status == AnalyticsTradeStatus.pending)
        .length,
    rejectedOrExpiredOrders: raw
        .where(
          (trade) =>
              trade.status == AnalyticsTradeStatus.rejected ||
              trade.status == AnalyticsTradeStatus.expired,
        )
        .length,
    duplicateRecordsIgnored: duplicates,
    tradesMissingCloseTime: raw
        .where((trade) => trade.isClosed && trade.closeTimeUtc == null)
        .length,
    tradesMissingLotSize: filtered.where((trade) => trade.lots <= 0).length,
    tradesMissingStrategy: filtered
        .where((trade) => trade.strategyName?.trim().isNotEmpty != true)
        .length,
    tradesMissingRiskData: filtered
        .where(
          (trade) =>
              trade.initialRiskAmount == null || trade.initialRiskAmount! <= 0,
        )
        .length,
    earliestTradeUtc: times.firstOrNull,
    latestTradeUtc: times.lastOrNull,
    lastExcelImportUtc: excelTimes.lastOrNull,
    lastMt5SynchronizationUtc: mt5Times.lastOrNull,
    equitySnapshotsAvailable: equitySnapshotsAvailable,
  );
}

List<CurrencyAnalyticsTotal> _currencyTotals(List<AnalyticsTrade> trades) {
  final grouped = <String, List<AnalyticsTrade>>{};
  for (final trade in trades) {
    final currency = trade.accountCurrency.trim().toUpperCase();
    grouped
        .putIfAbsent(currency.isEmpty ? 'Unknown' : currency, () => [])
        .add(trade);
  }
  final result = [
    for (final entry in grouped.entries)
      CurrencyAnalyticsTotal(
        currency: entry.key,
        netTradingPnl: _sum(
          entry.value
              .where((trade) => trade.isEligibleClosed)
              .map((trade) => trade.netProfit),
        ),
        floatingPnl: _sum(
          entry.value
              .where((trade) => trade.isOpen)
              .map((trade) => trade.netProfit),
        ),
        closedTrades: entry.value
            .where((trade) => trade.isEligibleClosed)
            .length,
        openTrades: entry.value.where((trade) => trade.isOpen).length,
      ),
  ]..sort((a, b) => a.currency.compareTo(b.currency));
  return List.unmodifiable(result);
}

List<AnalyticsInsight> _insights(
  AnalyticsSummary summary,
  Map<String, List<AnalyticsBreakdown>> breakdowns,
  List<AnalyticsTrade> trades,
  AnalyticsDataQuality quality,
) {
  if (trades.isEmpty) return const [];
  final insights = <AnalyticsInsight>[];
  final symbols = breakdowns['symbol'] ?? const [];
  if (trades.length >= 3 && symbols.isNotEmpty) {
    final best = symbols.first;
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.strength,
        title: 'Leading symbol',
        message: 'Most of the selected period profit came from {symbol}.',
        supportingValue: '${best.label}: ${best.netProfit.toStringAsFixed(2)}',
        sampleSize: best.trades,
      ),
    );
  }
  final sessions = breakdowns['session'] ?? const [];
  final losingSessions = [...sessions]
    ..sort((a, b) => a.netProfit.compareTo(b.netProfit));
  if (losingSessions.isNotEmpty &&
      losingSessions.first.netProfit < 0 &&
      losingSessions.first.trades >= 3) {
    final worst = losingSessions.first;
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.pattern,
        title: 'Weakest session',
        message: 'The largest net losses occurred during {session}.',
        supportingValue:
            '${worst.label}: ${worst.netProfit.toStringAsFixed(2)}',
        sampleSize: worst.trades,
      ),
    );
  }
  if (trades.length >= 5 && summary.postLossLotChangePercent > 15) {
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.risk,
        title: 'Possible post-loss risk escalation',
        message: 'Average lot size increased after losing trades.',
        supportingValue:
            '+${summary.postLossLotChangePercent.toStringAsFixed(1)}%',
        sampleSize: trades.length,
      ),
    );
  }
  if (trades.length >= 5 &&
      summary.averageWinningHoldingMinutes !=
          summary.averageLosingHoldingMinutes) {
    final winningLonger =
        summary.averageWinningHoldingMinutes >
        summary.averageLosingHoldingMinutes;
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.pattern,
        title: 'Holding-time pattern',
        message: winningLonger
            ? 'Winning trades were held longer than losing trades.'
            : 'Losing trades were held longer than winning trades.',
        supportingValue:
            '${summary.averageWinningHoldingMinutes.toStringAsFixed(0)}m vs '
            '${summary.averageLosingHoldingMinutes.toStringAsFixed(0)}m',
        sampleSize: trades.length,
      ),
    );
  }
  if (summary.grossProfit > 0 && summary.largestWin > 0) {
    final concentration = summary.largestWin / summary.grossProfit * 100;
    if (concentration > 40 && trades.length >= 5) {
      insights.add(
        AnalyticsInsight(
          kind: AnalyticsInsightKind.risk,
          title: 'Profit concentration',
          message:
              'A small number of trades generated a large share of total profit.',
          supportingValue:
              '${concentration.toStringAsFixed(1)}% from the largest win',
          sampleSize: trades.length,
        ),
      );
    }
  }
  if (summary.currentDrawdownPercent > 0) {
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.pattern,
        title: 'Current drawdown context',
        message: summary.currentDrawdownPercent < summary.maximumDrawdownPercent
            ? 'Current drawdown is below the historical maximum.'
            : 'Current drawdown is at the historical maximum.',
        supportingValue:
            '${summary.currentDrawdownPercent.toStringAsFixed(1)}% / '
            '${summary.maximumDrawdownPercent.toStringAsFixed(1)}%',
        sampleSize: trades.length,
      ),
    );
  }
  if (quality.tradesMissingRiskData > 0) {
    insights.add(
      AnalyticsInsight(
        kind: AnalyticsInsightKind.dataQuality,
        title: 'Risk data incomplete',
        message:
            'R-multiple and risk-based metrics use only trades with recorded initial risk.',
        supportingValue: '${quality.tradesMissingRiskData} missing',
        sampleSize: quality.filteredRecords,
      ),
    );
  }
  return List.unmodifiable(insights.take(8));
}

double _consistencyScore(Iterable<double> raw) {
  final values = raw.where((value) => value.isFinite).toList(growable: false);
  if (values.length < 2) return values.isEmpty ? 0 : 100;
  final meanAbsolute = _mean(values.map((value) => value.abs()));
  if (meanAbsolute == 0) return 100;
  final coefficient = _populationStandardDeviation(values) / meanAbsolute;
  return (100 - coefficient * 50).clamp(0, 100).toDouble();
}

double _mean(Iterable<double> values) {
  var count = 0;
  var total = 0.0;
  for (final value in values) {
    if (!value.isFinite) continue;
    count++;
    total += value;
  }
  return count == 0 ? 0 : total / count;
}

double _sum(Iterable<double> values) =>
    values.fold<double>(0, (sum, value) => value.isFinite ? sum + value : sum);

double _populationStandardDeviation(Iterable<double> raw) {
  final values = raw.where((value) => value.isFinite).toList(growable: false);
  if (values.isEmpty) return 0;
  final mean = _mean(values);
  return math.sqrt(
    _sum(values.map((value) => math.pow(value - mean, 2).toDouble())) /
        values.length,
  );
}

double _sampleStandardDeviation(Iterable<double> raw) {
  final values = raw.where((value) => value.isFinite).toList(growable: false);
  if (values.length < 2) return 0;
  final mean = _mean(values);
  return math.sqrt(
    _sum(values.map((value) => math.pow(value - mean, 2).toDouble())) /
        (values.length - 1),
  );
}

double _percentile(Iterable<double> raw, double percentile) {
  final values = raw.where((value) => value.isFinite).toList()..sort();
  if (values.isEmpty) return 0;
  if (values.length == 1) return values.single;
  final position = (values.length - 1) * percentile.clamp(0, 1);
  final lower = position.floor();
  final upper = position.ceil();
  if (lower == upper) return values[lower];
  return values[lower] + (values[upper] - values[lower]) * (position - lower);
}

int _tradeTimeComparison(AnalyticsTrade a, AnalyticsTrade b) =>
    (a.closeTimeUtc ?? a.openTimeUtc).compareTo(
      b.closeTimeUtc ?? b.openTimeUtc,
    );

String _labelOrUnspecified(String? value, {String fallback = 'Unspecified'}) {
  final label = value?.trim() ?? '';
  return label.isEmpty ? fallback : label;
}

String _derivedSession(DateTime utc, int offset) {
  final hour = utc.add(Duration(minutes: offset)).hour;
  if (hour < 8) return 'Asia';
  if (hour < 13) return 'London';
  if (hour < 22) return 'New York';
  return 'Late Session';
}

String _weekdayLabel(DateTime value) => const [
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
  'Sunday',
][value.weekday - 1];

int _weekOfYear(DateTime date) {
  final first = DateTime.utc(date.year, 1, 1);
  return ((date.difference(first).inDays + first.weekday - 1) / 7).floor() + 1;
}
