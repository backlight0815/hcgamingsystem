import 'dart:math' as math;

enum AdminPerformancePeriod { sevenDays, thirtyDays, ninetyDays, year, all }

enum AdminPerformanceProfitFilter { all, profitable, losing, breakeven }

enum AdminPerformanceRiskFilter { all, controlled, attention, high }

enum AdminPerformanceSort {
  ranking,
  profitHigh,
  profitLow,
  returnHigh,
  winRateHigh,
  drawdownLow,
  drawdownHigh,
  scoreHigh,
  mostActive,
  leastActive,
  recent,
}

enum AdminPerformanceDensity { comfortable, compact }

class AdminPerformanceDateRange {
  const AdminPerformanceDateRange({this.start, this.end});

  final DateTime? start;
  final DateTime? end;

  bool get isAllTime => start == null || end == null;

  factory AdminPerformanceDateRange.forPeriod(
    AdminPerformancePeriod period,
    DateTime now,
  ) {
    final end = DateTime(now.year, now.month, now.day, 23, 59, 59, 999);
    return switch (period) {
      AdminPerformancePeriod.sevenDays => AdminPerformanceDateRange(
        start: end.subtract(const Duration(days: 6)),
        end: end,
      ),
      AdminPerformancePeriod.thirtyDays => AdminPerformanceDateRange(
        start: end.subtract(const Duration(days: 29)),
        end: end,
      ),
      AdminPerformancePeriod.ninetyDays => AdminPerformanceDateRange(
        start: end.subtract(const Duration(days: 89)),
        end: end,
      ),
      AdminPerformancePeriod.year => AdminPerformanceDateRange(
        start: DateTime(now.year),
        end: end,
      ),
      AdminPerformancePeriod.all => const AdminPerformanceDateRange(),
    };
  }
}

class AdminPerformanceFilter {
  const AdminPerformanceFilter({
    this.search = '',
    this.period = AdminPerformancePeriod.thirtyDays,
    this.dataSource = 'all',
    this.profit = AdminPerformanceProfitFilter.all,
    this.risk = AdminPerformanceRiskFilter.all,
    this.reviewStatus = 'all',
    this.minimumTrades = 0,
    this.minimumProfit,
    this.maximumDrawdown,
    this.sort = AdminPerformanceSort.ranking,
  });

  final String search;
  final AdminPerformancePeriod period;
  final String dataSource;
  final AdminPerformanceProfitFilter profit;
  final AdminPerformanceRiskFilter risk;
  final String reviewStatus;
  final int minimumTrades;
  final double? minimumProfit;
  final double? maximumDrawdown;
  final AdminPerformanceSort sort;

  int get activeCount => [
    search.trim().isNotEmpty,
    period != AdminPerformancePeriod.thirtyDays,
    dataSource != 'all',
    profit != AdminPerformanceProfitFilter.all,
    risk != AdminPerformanceRiskFilter.all,
    reviewStatus != 'all',
    minimumTrades > 0,
    minimumProfit != null,
    maximumDrawdown != null,
    sort != AdminPerformanceSort.ranking,
  ].where((active) => active).length;

  AdminPerformanceFilter copyWith({
    String? search,
    AdminPerformancePeriod? period,
    String? dataSource,
    AdminPerformanceProfitFilter? profit,
    AdminPerformanceRiskFilter? risk,
    String? reviewStatus,
    int? minimumTrades,
    double? minimumProfit,
    double? maximumDrawdown,
    AdminPerformanceSort? sort,
    bool clearMinimumProfit = false,
    bool clearMaximumDrawdown = false,
  }) => AdminPerformanceFilter(
    search: search ?? this.search,
    period: period ?? this.period,
    dataSource: dataSource ?? this.dataSource,
    profit: profit ?? this.profit,
    risk: risk ?? this.risk,
    reviewStatus: reviewStatus ?? this.reviewStatus,
    minimumTrades: minimumTrades ?? this.minimumTrades,
    minimumProfit: clearMinimumProfit
        ? null
        : minimumProfit ?? this.minimumProfit,
    maximumDrawdown: clearMaximumDrawdown
        ? null
        : maximumDrawdown ?? this.maximumDrawdown,
    sort: sort ?? this.sort,
  );
}

class AdminPerformanceDailyPoint {
  const AdminPerformanceDailyPoint({
    required this.date,
    required this.profitLoss,
    required this.trades,
  });

  final DateTime date;
  final double profitLoss;
  final int trades;
}

class AdminPerformanceBreakdown {
  const AdminPerformanceBreakdown({
    required this.label,
    required this.trades,
    required this.profitLoss,
    required this.winRate,
  });

  final String label;
  final int trades;
  final double profitLoss;
  final double winRate;

  factory AdminPerformanceBreakdown.fromMap(Map<String, dynamic> value) =>
      AdminPerformanceBreakdown(
        label: '${value['label'] ?? ''}',
        trades: _int(value['trades']),
        profitLoss: _double(value['profit_loss']),
        winRate: _double(value['win_rate']),
      );
}

class AdminPerformanceTrade {
  const AdminPerformanceTrade({
    required this.id,
    required this.pair,
    required this.direction,
    required this.closeDate,
    required this.lotSize,
    required this.profitLoss,
    required this.pips,
    required this.source,
    required this.strategy,
    required this.attachmentCount,
  });

  final String id;
  final String pair;
  final int direction;
  final DateTime? closeDate;
  final double lotSize;
  final double profitLoss;
  final double pips;
  final String source;
  final String strategy;
  final int attachmentCount;

  factory AdminPerformanceTrade.fromMap(Map<String, dynamic> value) =>
      AdminPerformanceTrade(
        id: '${value['id'] ?? ''}',
        pair: '${value['pair'] ?? ''}',
        direction: _int(value['direction']),
        closeDate: DateTime.tryParse('${value['close_date'] ?? ''}'),
        lotSize: _double(value['lot_size']),
        profitLoss: _double(value['profit_loss']),
        pips: _double(value['pips']),
        source: '${value['source'] ?? 'manual'}',
        strategy: '${value['strategy'] ?? ''}',
        attachmentCount: _int(value['attachment_count']),
      );
}

class AdminTraderPerformance {
  const AdminTraderPerformance({
    required this.raw,
    required this.id,
    required this.name,
    required this.email,
    required this.accountStatus,
    required this.rank,
    required this.totalTrades,
    required this.wins,
    required this.losses,
    required this.breakEvens,
    required this.netProfit,
    required this.returnPercent,
    required this.winRate,
    required this.profitFactor,
    required this.profitFactorLabel,
    required this.payoffRatio,
    required this.expectancy,
    required this.maxDrawdown,
    required this.maxDrawdownPercent,
    required this.balance,
    required this.deposits,
    required this.withdrawals,
    required this.overallScore,
    required this.rankingScore,
    required this.reportGrade,
    required this.reviewStatus,
    required this.funded,
    required this.propFirmPhase,
    required this.dataSources,
    required this.lastTradeAt,
    required this.previousNetProfit,
    required this.previousReturnPercent,
    required this.previousWinRate,
    required this.previousDrawdownPercent,
    required this.daily,
    required this.monthly,
    required this.pairs,
    required this.sessions,
    required this.recentTrades,
  });

  final Map<String, dynamic> raw;
  final String id;
  final String name;
  final String email;
  final String accountStatus;
  final int rank;
  final int totalTrades;
  final int wins;
  final int losses;
  final int breakEvens;
  final double netProfit;
  final double returnPercent;
  final double winRate;
  final double? profitFactor;
  final String profitFactorLabel;
  final double? payoffRatio;
  final double expectancy;
  final double maxDrawdown;
  final double maxDrawdownPercent;
  final double balance;
  final double deposits;
  final double withdrawals;
  final double overallScore;
  final double rankingScore;
  final String reportGrade;
  final String reviewStatus;
  final bool funded;
  final int propFirmPhase;
  final List<String> dataSources;
  final DateTime? lastTradeAt;
  final double? previousNetProfit;
  final double? previousReturnPercent;
  final double? previousWinRate;
  final double? previousDrawdownPercent;
  final List<AdminPerformanceDailyPoint> daily;
  final List<AdminPerformanceBreakdown> monthly;
  final List<AdminPerformanceBreakdown> pairs;
  final List<AdminPerformanceBreakdown> sessions;
  final List<AdminPerformanceTrade> recentTrades;

  bool get profitable => netProfit > 0;
  bool get losing => netProfit < 0;
  bool get active => accountStatus != 'inactive' && accountStatus != '0';
  String get riskLevel => maxDrawdownPercent >= 10
      ? 'high'
      : maxDrawdownPercent >= 5
      ? 'attention'
      : 'controlled';
  bool get requiresReview => const {
    'pending',
    'under_review',
    'correction_required',
    'suspended',
    'rejected',
  }.contains(reviewStatus.toLowerCase());

  factory AdminTraderPerformance.fromMap(Map<String, dynamic> value) {
    final user = _map(value['user']);
    return AdminTraderPerformance(
      raw: value,
      id: '${user['id'] ?? ''}',
      name: '${user['name'] ?? user['username'] ?? 'Trader'}',
      email: '${user['email'] ?? ''}',
      accountStatus: '${user['account_status'] ?? user['status'] ?? 'active'}'
          .toLowerCase(),
      rank: _int(value['rank']),
      totalTrades: _int(value['total_trades']),
      wins: _int(value['wins']),
      losses: _int(value['losses']),
      breakEvens: _int(value['break_evens']),
      netProfit: _double(value['net_profit_loss']),
      returnPercent: _double(value['growth_percent']),
      winRate: _double(value['win_rate']),
      profitFactor: _nullableDouble(value['profit_factor']),
      profitFactorLabel: '${value['profit_factor_label'] ?? 'N/A'}',
      payoffRatio: _nullableDouble(value['payoff_ratio']),
      expectancy: _double(value['expectancy']),
      maxDrawdown: _double(value['max_drawdown']),
      maxDrawdownPercent: _double(value['max_drawdown_percent']),
      balance: _double(value['balance']),
      deposits: _double(value['deposits']),
      withdrawals: _double(value['withdrawals']),
      overallScore: _double(value['report_score']),
      rankingScore: _double(value['score']),
      reportGrade: '${value['report_grade'] ?? 'N/A'}',
      reviewStatus: '${value['prop_firm_review_status'] ?? 'none'}',
      funded: _int(value['funded_status']) == 1,
      propFirmPhase: _int(value['prop_firm_phase']),
      dataSources: _list(value['data_sources']).map((item) => '$item').toList(),
      lastTradeAt: DateTime.tryParse('${value['last_trade_at'] ?? ''}'),
      previousNetProfit: _nullableDouble(value['previous_net_profit_loss']),
      previousReturnPercent: _nullableDouble(value['previous_growth_percent']),
      previousWinRate: _nullableDouble(value['previous_win_rate']),
      previousDrawdownPercent: _nullableDouble(
        value['previous_max_drawdown_percent'],
      ),
      daily: _maps(value['daily_stats'])
          .map(
            (item) => AdminPerformanceDailyPoint(
              date:
                  DateTime.tryParse('${item['date']}') ??
                  DateTime.fromMillisecondsSinceEpoch(0),
              profitLoss: _double(item['profit_loss']),
              trades: _int(item['trades']),
            ),
          )
          .toList(),
      monthly: _maps(
        value['monthly_stats'],
      ).map(AdminPerformanceBreakdown.fromMap).toList(),
      pairs: _maps(
        value['pair_stats'],
      ).map(AdminPerformanceBreakdown.fromMap).toList(),
      sessions: _maps(
        value['session_stats'],
      ).map(AdminPerformanceBreakdown.fromMap).toList(),
      recentTrades: _maps(
        value['recent_trades'],
      ).map(AdminPerformanceTrade.fromMap).toList(),
    );
  }
}

class AdminPerformanceInsight {
  const AdminPerformanceInsight({
    required this.title,
    required this.message,
    required this.kind,
    required this.count,
  });

  final String title;
  final String message;
  final String kind;
  final int count;
}

class AdminPerformanceSummary {
  const AdminPerformanceSummary({
    required this.totalTraders,
    required this.activeTraders,
    required this.profitableTraders,
    required this.losingTraders,
    required this.netProfit,
    required this.averageReturn,
    required this.averageWinRate,
    required this.averageDrawdown,
    required this.reviewRequired,
    required this.fundedAccounts,
    required this.previousNetProfit,
    required this.previousAverageReturn,
    required this.previousAverageWinRate,
    required this.previousAverageDrawdown,
  });

  final int totalTraders;
  final int activeTraders;
  final int profitableTraders;
  final int losingTraders;
  final double netProfit;
  final double averageReturn;
  final double averageWinRate;
  final double averageDrawdown;
  final int reviewRequired;
  final int fundedAccounts;
  final double? previousNetProfit;
  final double? previousAverageReturn;
  final double? previousAverageWinRate;
  final double? previousAverageDrawdown;

  factory AdminPerformanceSummary.fromRecords(
    List<AdminTraderPerformance> records,
  ) {
    double average(Iterable<double> values) {
      final list = values.toList();
      return list.isEmpty
          ? 0
          : list.reduce((left, right) => left + right) / list.length;
    }

    double? previous(Iterable<double?> values, {bool sum = false}) {
      final list = values.whereType<double>().toList();
      if (list.isEmpty) return null;
      return sum ? list.reduce((left, right) => left + right) : average(list);
    }

    return AdminPerformanceSummary(
      totalTraders: records.length,
      activeTraders: records.where((record) => record.active).length,
      profitableTraders: records.where((record) => record.profitable).length,
      losingTraders: records.where((record) => record.losing).length,
      netProfit: records.fold(0, (sum, record) => sum + record.netProfit),
      averageReturn: average(records.map((record) => record.returnPercent)),
      averageWinRate: average(records.map((record) => record.winRate)),
      averageDrawdown: average(
        records.map((record) => record.maxDrawdownPercent),
      ),
      reviewRequired: records.where((record) => record.requiresReview).length,
      fundedAccounts: records.where((record) => record.funded).length,
      previousNetProfit: previous(
        records.map((record) => record.previousNetProfit),
        sum: true,
      ),
      previousAverageReturn: previous(
        records.map((record) => record.previousReturnPercent),
      ),
      previousAverageWinRate: previous(
        records.map((record) => record.previousWinRate),
      ),
      previousAverageDrawdown: previous(
        records.map((record) => record.previousDrawdownPercent),
      ),
    );
  }
}

List<AdminPerformanceInsight> buildAdminPerformanceInsights(
  List<AdminTraderPerformance> records,
) {
  final improved = records
      .where(
        (record) =>
            record.previousReturnPercent != null &&
            record.returnPercent > record.previousReturnPercent!,
      )
      .length;
  final declined = records
      .where(
        (record) =>
            record.previousReturnPercent != null &&
            record.returnPercent < record.previousReturnPercent!,
      )
      .length;
  final highRisk = records.where((record) => record.riskLevel == 'high').length;
  final review = records.where((record) => record.requiresReview).length;
  final inactive = records
      .where(
        (record) =>
            record.lastTradeAt == null ||
            DateTime.now().difference(record.lastTradeAt!).inDays >= 30,
      )
      .length;
  return [
    if (improved > 0)
      AdminPerformanceInsight(
        title: 'Performance improved',
        message:
            '{count} trader(s) improved return versus the previous period.',
        kind: 'positive',
        count: improved,
      ),
    if (declined > 0)
      AdminPerformanceInsight(
        title: 'Performance declined',
        message: '{count} trader(s) declined versus the previous period.',
        kind: 'warning',
        count: declined,
      ),
    if (highRisk > 0)
      AdminPerformanceInsight(
        title: 'High drawdown exposure',
        message: '{count} trader(s) have maximum drawdown of at least 10%.',
        kind: 'negative',
        count: highRisk,
      ),
    if (review > 0)
      AdminPerformanceInsight(
        title: 'Manual review required',
        message: '{count} account(s) are in a review or restricted status.',
        kind: 'warning',
        count: review,
      ),
    if (inactive > 0)
      AdminPerformanceInsight(
        title: 'No recent activity',
        message: '{count} trader(s) have no trade in the last 30 days.',
        kind: 'neutral',
        count: inactive,
      ),
  ];
}

Map<DateTime, double> aggregateAdminDailyProfit(
  Iterable<AdminTraderPerformance> records,
) {
  final result = <DateTime, double>{};
  for (final point in records.expand((record) => record.daily)) {
    final day = DateTime(point.date.year, point.date.month, point.date.day);
    result[day] = (result[day] ?? 0) + point.profitLoss;
  }
  return Map.fromEntries(
    result.entries.toList()..sort((a, b) => a.key.compareTo(b.key)),
  );
}

double safePercentChange(double current, double? previous) {
  if (previous == null || previous == 0 || !current.isFinite) return 0;
  final value = (current - previous) / previous.abs() * 100;
  return value.isFinite ? value : 0;
}

double clampFinite(
  double value, [
  double minimum = -1e12,
  double maximum = 1e12,
]) {
  if (!value.isFinite) return 0;
  return math.min(maximum, math.max(minimum, value));
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<dynamic> _list(Object? value) => value is List ? value : const [];
List<Map<String, dynamic>> _maps(Object? value) => _list(
  value,
).whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
double? _nullableDouble(Object? value) => value == null
    ? null
    : value is num
    ? value.toDouble()
    : double.tryParse('$value');
