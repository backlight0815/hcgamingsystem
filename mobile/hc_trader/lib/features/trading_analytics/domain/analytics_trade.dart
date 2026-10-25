enum AnalyticsTradeDirection { buy, sell, unknown }

enum AnalyticsTradeStatus {
  open,
  closed,
  pending,
  cancelled,
  rejected,
  expired,
  unknown,
}

enum AnalyticsTradeDataSource { manual, excel, mt5 }

class AnalyticsTrade {
  const AnalyticsTrade({
    required this.id,
    required this.userId,
    required this.symbol,
    required this.direction,
    required this.status,
    required this.openTimeUtc,
    required this.entryPrice,
    required this.lots,
    required this.grossProfit,
    required this.commission,
    required this.swap,
    required this.fees,
    required this.netProfit,
    required this.source,
    this.accountId,
    this.accountCurrency = '',
    this.closeTimeUtc,
    this.exitPrice,
    this.stopLoss,
    this.takeProfit,
    this.initialRiskAmount,
    this.initialRiskPercent,
    this.strategyId,
    this.strategyName,
    this.setupName,
    this.session,
    this.notes,
    this.sourceTicket,
    this.sourceOrderId,
    this.sourcePositionId,
    this.isDeposit = false,
    this.isWithdrawal = false,
    this.updatedAtUtc,
  });

  final String id;
  final String userId;
  final String? accountId;
  final String accountCurrency;
  final String symbol;
  final AnalyticsTradeDirection direction;
  final AnalyticsTradeStatus status;
  final DateTime openTimeUtc;
  final DateTime? closeTimeUtc;
  final double entryPrice;
  final double? exitPrice;
  final double lots;
  final double grossProfit;
  final double commission;
  final double swap;
  final double fees;
  final double netProfit;
  final double? stopLoss;
  final double? takeProfit;
  final double? initialRiskAmount;
  final double? initialRiskPercent;
  final String? strategyId;
  final String? strategyName;
  final String? setupName;
  final String? session;
  final String? notes;
  final AnalyticsTradeDataSource source;
  final String? sourceTicket;
  final String? sourceOrderId;
  final String? sourcePositionId;
  final bool isDeposit;
  final bool isWithdrawal;
  final DateTime? updatedAtUtc;

  bool get isCashFlow => isDeposit || isWithdrawal;
  bool get isClosed => status == AnalyticsTradeStatus.closed;
  bool get isOpen => status == AnalyticsTradeStatus.open;
  bool get isEligibleClosed => isClosed && !isCashFlow && closeTimeUtc != null;
  bool get isWin => isEligibleClosed && netProfit > 0;
  bool get isLoss => isEligibleClosed && netProfit < 0;
  bool get isBreakeven => isEligibleClosed && netProfit == 0;

  Duration? get holdingDuration {
    final closedAt = closeTimeUtc;
    if (!isEligibleClosed ||
        closedAt == null ||
        !closedAt.isAfter(openTimeUtc)) {
      return null;
    }
    return closedAt.difference(openTimeUtc);
  }

  double? get rMultiple {
    final risk = initialRiskAmount;
    if (risk == null || risk <= 0 || !isEligibleClosed) return null;
    return netProfit / risk;
  }

  String get stableIdentity {
    final account = accountId?.trim().toLowerCase() ?? '';
    final position = sourcePositionId?.trim() ?? '';
    if (position.isNotEmpty) return '$account|position|$position';
    final ticket = sourceTicket?.trim() ?? '';
    if (ticket.isNotEmpty) return '$account|ticket|$ticket';
    final order = sourceOrderId?.trim() ?? '';
    if (order.isNotEmpty) return '$account|order|$order';
    return [
      account,
      symbol.trim().toUpperCase(),
      direction.name,
      openTimeUtc.microsecondsSinceEpoch,
      closeTimeUtc?.microsecondsSinceEpoch ?? 0,
      lots.toStringAsFixed(8),
      entryPrice.toStringAsFixed(8),
      exitPrice?.toStringAsFixed(8) ?? '',
      netProfit.toStringAsFixed(8),
    ].join('|');
  }

  int get informationScore {
    var score = 0;
    if (sourceTicket?.trim().isNotEmpty == true) score += 4;
    if (sourceOrderId?.trim().isNotEmpty == true) score += 2;
    if (sourcePositionId?.trim().isNotEmpty == true) score += 4;
    if (strategyName?.trim().isNotEmpty == true) score++;
    if (setupName?.trim().isNotEmpty == true) score++;
    if (session?.trim().isNotEmpty == true) score++;
    if (initialRiskAmount != null && initialRiskAmount! > 0) score += 2;
    if (stopLoss != null) score++;
    if (takeProfit != null) score++;
    score += switch (source) {
      AnalyticsTradeDataSource.mt5 => 3,
      AnalyticsTradeDataSource.excel => 2,
      AnalyticsTradeDataSource.manual => 1,
    };
    return score;
  }
}

enum AnalyticsCashFlowType { deposit, withdrawal }

class AnalyticsCashFlow {
  const AnalyticsCashFlow({
    required this.id,
    required this.userId,
    required this.timestampUtc,
    required this.amount,
    required this.type,
    this.accountId,
    this.currency = '',
    this.source = AnalyticsTradeDataSource.manual,
  });

  final String id;
  final String userId;
  final String? accountId;
  final String currency;
  final DateTime timestampUtc;
  final double amount;
  final AnalyticsCashFlowType type;
  final AnalyticsTradeDataSource source;

  double get signedAmount =>
      type == AnalyticsCashFlowType.deposit ? amount.abs() : -amount.abs();
}

class AccountEquitySnapshot {
  const AccountEquitySnapshot({
    required this.accountId,
    required this.timestampUtc,
    required this.balance,
    required this.equity,
    required this.floatingProfit,
    required this.margin,
    required this.freeMargin,
    required this.marginLevel,
    this.currency = '',
  });

  final String accountId;
  final String currency;
  final DateTime timestampUtc;
  final double balance;
  final double equity;
  final double floatingProfit;
  final double margin;
  final double freeMargin;
  final double marginLevel;
}
