import 'analytics_trade.dart';

enum AnalyticsOutcomeFilter { all, winning, losing, breakeven, open, closed }

class AnalyticsFilter {
  const AnalyticsFilter({
    this.sources = const {
      AnalyticsTradeDataSource.manual,
      AnalyticsTradeDataSource.excel,
      AnalyticsTradeDataSource.mt5,
    },
    this.accountId = '*',
    this.startUtc,
    this.endUtcExclusive,
    this.symbol,
    this.direction,
    this.strategy,
    this.setup,
    this.session,
    this.outcome = AnalyticsOutcomeFilter.all,
  });

  final Set<AnalyticsTradeDataSource> sources;
  final String accountId;
  final DateTime? startUtc;
  final DateTime? endUtcExclusive;
  final String? symbol;
  final AnalyticsTradeDirection? direction;
  final String? strategy;
  final String? setup;
  final String? session;
  final AnalyticsOutcomeFilter outcome;

  bool matches(AnalyticsTrade trade) {
    if (!sources.contains(trade.source)) return false;
    if (accountId != '*' && trade.accountId != accountId) return false;
    final timestamp = trade.closeTimeUtc ?? trade.openTimeUtc;
    if (startUtc != null && timestamp.isBefore(startUtc!)) return false;
    if (endUtcExclusive != null && !timestamp.isBefore(endUtcExclusive!)) {
      return false;
    }
    if (symbol != null &&
        symbol!.isNotEmpty &&
        trade.symbol.toUpperCase() != symbol!.toUpperCase()) {
      return false;
    }
    if (direction != null && trade.direction != direction) return false;
    if (!_matchesText(strategy, trade.strategyName)) return false;
    if (!_matchesText(setup, trade.setupName)) return false;
    if (!_matchesText(session, trade.session)) return false;
    return switch (outcome) {
      AnalyticsOutcomeFilter.all => true,
      AnalyticsOutcomeFilter.winning => trade.isWin,
      AnalyticsOutcomeFilter.losing => trade.isLoss,
      AnalyticsOutcomeFilter.breakeven => trade.isBreakeven,
      AnalyticsOutcomeFilter.open => trade.isOpen,
      AnalyticsOutcomeFilter.closed => trade.isEligibleClosed,
    };
  }

  AnalyticsFilter copyWith({
    Set<AnalyticsTradeDataSource>? sources,
    String? accountId,
    DateTime? startUtc,
    DateTime? endUtcExclusive,
    bool clearDates = false,
    String? symbol,
    bool clearSymbol = false,
    AnalyticsTradeDirection? direction,
    bool clearDirection = false,
    String? strategy,
    bool clearStrategy = false,
    String? setup,
    bool clearSetup = false,
    String? session,
    bool clearSession = false,
    AnalyticsOutcomeFilter? outcome,
  }) {
    return AnalyticsFilter(
      sources: sources ?? this.sources,
      accountId: accountId ?? this.accountId,
      startUtc: clearDates ? null : startUtc ?? this.startUtc,
      endUtcExclusive: clearDates
          ? null
          : endUtcExclusive ?? this.endUtcExclusive,
      symbol: clearSymbol ? null : symbol ?? this.symbol,
      direction: clearDirection ? null : direction ?? this.direction,
      strategy: clearStrategy ? null : strategy ?? this.strategy,
      setup: clearSetup ? null : setup ?? this.setup,
      session: clearSession ? null : session ?? this.session,
      outcome: outcome ?? this.outcome,
    );
  }

  int get activeCount {
    var count = 0;
    if (sources.length != AnalyticsTradeDataSource.values.length) count++;
    if (accountId != '*') count++;
    if (startUtc != null || endUtcExclusive != null) count++;
    if (symbol?.isNotEmpty == true) count++;
    if (direction != null) count++;
    if (strategy?.isNotEmpty == true) count++;
    if (setup?.isNotEmpty == true) count++;
    if (session?.isNotEmpty == true) count++;
    if (outcome != AnalyticsOutcomeFilter.all) count++;
    return count;
  }

  String get cacheKey => [
    sources.map((value) => value.name).toList()..sort(),
    accountId,
    startUtc?.microsecondsSinceEpoch,
    endUtcExclusive?.microsecondsSinceEpoch,
    symbol,
    direction?.name,
    strategy,
    setup,
    session,
    outcome.name,
  ].join('|');
}

bool _matchesText(String? expected, String? actual) {
  final normalized = expected?.trim().toLowerCase() ?? '';
  if (normalized.isEmpty) return true;
  return (actual?.trim().toLowerCase() ?? '') == normalized;
}
