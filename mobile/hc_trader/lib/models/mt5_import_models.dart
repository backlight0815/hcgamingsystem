import 'dart:math' as math;

/// Fixed-precision decimal used by the MT5 import ledger. Values are persisted
/// as canonical strings; conversion to [double] only happens at the boundary
/// of legacy chart and journal models.
final class Mt5Decimal implements Comparable<Mt5Decimal> {
  Mt5Decimal._(this._coefficient, this.scale);

  factory Mt5Decimal.fromInt(int value) => Mt5Decimal._(BigInt.from(value), 0);

  factory Mt5Decimal.parse(Object? source) {
    if (source is Mt5Decimal) return source;
    if (source is int) return Mt5Decimal.fromInt(source);
    final original = '${source ?? ''}'.trim();
    if (original.isEmpty) throw const FormatException('Empty decimal value');
    var value = original
        .replaceAll('\u00a0', '')
        .replaceAll('\u202f', '')
        .replaceAll(' ', '')
        .replaceAll('\u2212', '-')
        .replaceAll('\u2013', '-')
        .replaceAll('\u2014', '-');
    var negative = value.startsWith('(') && value.endsWith(')');
    if (negative) value = value.substring(1, value.length - 1);
    value = value.replaceAll(RegExp(r'[^0-9,\.\-+]'), '');
    if (value.startsWith('-')) {
      negative = !negative;
      value = value.substring(1);
    } else if (value.startsWith('+')) {
      value = value.substring(1);
    }
    if (value.isEmpty) throw FormatException('Invalid decimal: $original');

    final comma = value.lastIndexOf(',');
    final dot = value.lastIndexOf('.');
    String normalized;
    if (comma >= 0 && dot >= 0) {
      final decimalSeparator = comma > dot ? ',' : '.';
      final thousandsSeparator = decimalSeparator == ',' ? '.' : ',';
      normalized = value.replaceAll(thousandsSeparator, '');
      if (decimalSeparator == ',') normalized = normalized.replaceAll(',', '.');
    } else if (comma >= 0) {
      final pieces = value.split(',');
      final looksThousands =
          pieces.length > 2 ||
          (pieces.length == 2 &&
              pieces.last.length == 3 &&
              pieces.first != '0' &&
              pieces.first.isNotEmpty);
      normalized = looksThousands ? pieces.join() : value.replaceAll(',', '.');
    } else {
      final pieces = value.split('.');
      final looksThousands =
          pieces.length > 2 && pieces.skip(1).every((part) => part.length == 3);
      normalized = looksThousands ? pieces.join() : value;
    }
    final parts = normalized.split('.');
    if (parts.length > 2 ||
        parts.any((part) => !RegExp(r'^\d*$').hasMatch(part))) {
      throw FormatException('Invalid decimal: $original');
    }
    final fraction = parts.length == 2 ? parts[1] : '';
    final digits = '${parts.first.isEmpty ? '0' : parts.first}$fraction';
    if (digits.isEmpty) throw FormatException('Invalid decimal: $original');
    var coefficient = BigInt.parse(digits);
    if (negative) coefficient = -coefficient;
    return Mt5Decimal._normalized(coefficient, fraction.length);
  }

  factory Mt5Decimal.tryParse(Object? source, {Mt5Decimal? fallback}) {
    try {
      return Mt5Decimal.parse(source);
    } on FormatException {
      return fallback ?? zero;
    }
  }

  factory Mt5Decimal._normalized(BigInt coefficient, int scale) {
    var nextCoefficient = coefficient;
    var nextScale = math.max(0, scale);
    while (nextScale > 0 && nextCoefficient % BigInt.from(10) == BigInt.zero) {
      nextCoefficient ~/= BigInt.from(10);
      nextScale--;
    }
    return Mt5Decimal._(nextCoefficient, nextScale);
  }

  static final zero = Mt5Decimal.fromInt(0);
  static final one = Mt5Decimal.fromInt(1);

  final BigInt _coefficient;
  final int scale;

  bool get isZero => _coefficient == BigInt.zero;
  bool get isPositive => _coefficient > BigInt.zero;
  bool get isNegative => _coefficient < BigInt.zero;
  Mt5Decimal get abs => Mt5Decimal._normalized(_coefficient.abs(), scale);

  Mt5Decimal operator -() => Mt5Decimal._normalized(-_coefficient, scale);

  Mt5Decimal operator +(Mt5Decimal other) {
    final targetScale = math.max(scale, other.scale);
    return Mt5Decimal._normalized(
      _scaled(targetScale) + other._scaled(targetScale),
      targetScale,
    );
  }

  Mt5Decimal operator -(Mt5Decimal other) => this + (-other);

  Mt5Decimal operator *(Mt5Decimal other) => Mt5Decimal._normalized(
    _coefficient * other._coefficient,
    scale + other.scale,
  );

  Mt5Decimal divide(Mt5Decimal other, {int resultScale = 10}) {
    if (other.isZero) {
      throw UnsupportedError('Cannot divide an MT5 decimal by zero.');
    }
    final numerator = _coefficient * _pow10(resultScale + other.scale);
    final denominator = other._coefficient * _pow10(scale);
    final quotient = numerator ~/ denominator;
    return Mt5Decimal._normalized(quotient, resultScale);
  }

  Mt5Decimal rounded(int digits) {
    if (scale <= digits) return this;
    final divisor = _pow10(scale - digits);
    final quotient = _coefficient ~/ divisor;
    final remainder = _coefficient.abs() % divisor;
    final rounded = remainder * BigInt.two >= divisor
        ? quotient + (_coefficient.isNegative ? -BigInt.one : BigInt.one)
        : quotient;
    return Mt5Decimal._normalized(rounded, digits);
  }

  BigInt _scaled(int targetScale) => _coefficient * _pow10(targetScale - scale);

  static BigInt _pow10(int exponent) => BigInt.from(10).pow(exponent);

  @override
  int compareTo(Mt5Decimal other) {
    final targetScale = math.max(scale, other.scale);
    return _scaled(targetScale).compareTo(other._scaled(targetScale));
  }

  double toDouble() => double.parse(toString());

  @override
  String toString() {
    final negative = _coefficient.isNegative;
    var digits = _coefficient.abs().toString();
    if (scale == 0) return '${negative ? '-' : ''}$digits';
    if (digits.length <= scale) digits = digits.padLeft(scale + 1, '0');
    final split = digits.length - scale;
    return '${negative ? '-' : ''}${digits.substring(0, split)}.${digits.substring(split)}';
  }

  @override
  bool operator ==(Object other) =>
      other is Mt5Decimal && compareTo(other) == 0;

  @override
  int get hashCode => toString().hashCode;
}

enum Mt5ImportStage {
  selected,
  reading,
  detectingFormat,
  parsing,
  normalizing,
  validating,
  reconciling,
  readyForPreview,
  committing,
  completed,
  completedWithWarnings,
  failed,
  rolledBack,
}

enum Mt5ImportSection {
  metadata,
  positions,
  orders,
  deals,
  totals,
  results,
  unknown,
}

enum Mt5ValidationSeverity { information, warning, blocking }

enum Mt5DealClassification {
  tradeEntry,
  tradeExit,
  tradeInOut,
  tradeOutBy,
  deposit,
  withdrawal,
  credit,
  creditRemoval,
  bonus,
  charge,
  correction,
  commissionAdjustment,
  fee,
  swapAdjustment,
  dividend,
  tax,
  interest,
  otherBalanceAdjustment,
  unknown,
}

enum Mt5IdentityConfidence { sourceTicket, explicitLink, deterministicFallback }

class Mt5SourceReference {
  const Mt5SourceReference({
    required this.sheet,
    required this.row,
    required this.section,
  });

  final String sheet;
  final int row;
  final Mt5ImportSection section;

  Map<String, Object?> toJson() => {
    'sheet': sheet,
    'row': row,
    'section': section.name,
  };
}

class Mt5ValidationIssue {
  const Mt5ValidationIssue({
    required this.code,
    required this.messageKey,
    required this.severity,
    this.reference,
    this.details = const {},
    this.suggestedCorrection = '',
  });

  final String code;
  final String messageKey;
  final Mt5ValidationSeverity severity;
  final Mt5SourceReference? reference;
  final Map<String, Object?> details;
  final String suggestedCorrection;

  bool get blocksCommit => severity == Mt5ValidationSeverity.blocking;

  Map<String, Object?> toJson() => {
    'code': code,
    'message_key': messageKey,
    'severity': severity.name,
    if (reference != null) 'source': reference!.toJson(),
    'details': details,
    'suggested_correction': suggestedCorrection,
  };
}

class Mt5RejectedRow {
  const Mt5RejectedRow({
    required this.reference,
    required this.rawValues,
    required this.reasonCode,
    required this.messageKey,
    required this.suggestedCorrection,
    this.normalizedValues = const {},
  });

  final Mt5SourceReference reference;
  final Map<String, String> rawValues;
  final Map<String, Object?> normalizedValues;
  final String reasonCode;
  final String messageKey;
  final String suggestedCorrection;

  Map<String, Object?> toJson() => {
    ...reference.toJson(),
    'raw_values': rawValues,
    'normalized_values': normalizedValues,
    'reason_code': reasonCode,
    'message_key': messageKey,
    'suggested_correction': suggestedCorrection,
  };
}

class Mt5ReportMetadata {
  const Mt5ReportMetadata({
    this.reportName = '',
    this.ownerName = '',
    this.accountNumber = '',
    this.currency = '',
    this.brokerServer = '',
    this.company = '',
    this.environment = '',
    this.accountMode = '',
    this.reportGeneratedAt,
    this.reportGeneratedAtSource = '',
    this.sourceTimezone = '',
    this.sourceTimezoneOffsetMinutes,
  });

  final String reportName;
  final String ownerName;
  final String accountNumber;
  final String currency;
  final String brokerServer;
  final String company;
  final String environment;
  final String accountMode;
  final DateTime? reportGeneratedAt;
  final String reportGeneratedAtSource;
  final String sourceTimezone;
  final int? sourceTimezoneOffsetMinutes;

  Mt5ReportMetadata copyWith({
    String? sourceTimezone,
    int? sourceTimezoneOffsetMinutes,
  }) => Mt5ReportMetadata(
    reportName: reportName,
    ownerName: ownerName,
    accountNumber: accountNumber,
    currency: currency,
    brokerServer: brokerServer,
    company: company,
    environment: environment,
    accountMode: accountMode,
    reportGeneratedAt: reportGeneratedAt,
    reportGeneratedAtSource: reportGeneratedAtSource,
    sourceTimezone: sourceTimezone ?? this.sourceTimezone,
    sourceTimezoneOffsetMinutes:
        sourceTimezoneOffsetMinutes ?? this.sourceTimezoneOffsetMinutes,
  );

  Map<String, Object?> toJson() => {
    'report_name': reportName,
    'owner_name': ownerName,
    'account_number': accountNumber,
    'currency': currency,
    'broker_server': brokerServer,
    'company': company,
    'environment': environment,
    'account_mode': accountMode,
    'report_generated_at': reportGeneratedAt?.toIso8601String(),
    'report_generated_at_source': reportGeneratedAtSource,
    'source_timezone': sourceTimezone,
    'source_timezone_offset_minutes': sourceTimezoneOffsetMinutes,
  };
}

class Mt5ImportedPosition {
  Mt5ImportedPosition({
    required this.ticket,
    required this.symbol,
    required this.side,
    required this.volume,
    required this.openPrice,
    required this.openTime,
    required this.openTimeSource,
    required this.reference,
    this.stopLoss,
    this.takeProfit,
    this.closeTime,
    this.closeTimeSource = '',
    this.closePrice,
    Mt5Decimal? grossPriceProfit,
    Mt5Decimal? commission,
    Mt5Decimal? fee,
    Mt5Decimal? swap,
    this.relatedOrderTickets = const [],
    this.relatedDealTickets = const [],
    this.identityConfidence = Mt5IdentityConfidence.sourceTicket,
  }) : grossPriceProfit = grossPriceProfit ?? Mt5Decimal.zero,
       commission = commission ?? Mt5Decimal.zero,
       fee = fee ?? Mt5Decimal.zero,
       swap = swap ?? Mt5Decimal.zero;

  final String ticket;
  final String symbol;
  final String side;
  final Mt5Decimal volume;
  final Mt5Decimal openPrice;
  final Mt5Decimal? stopLoss;
  final Mt5Decimal? takeProfit;
  final DateTime openTime;
  final String openTimeSource;
  final DateTime? closeTime;
  final String closeTimeSource;
  final Mt5Decimal? closePrice;
  final Mt5Decimal grossPriceProfit;
  final Mt5Decimal commission;
  final Mt5Decimal fee;
  final Mt5Decimal swap;
  final List<String> relatedOrderTickets;
  final List<String> relatedDealTickets;
  final Mt5SourceReference reference;
  final Mt5IdentityConfidence identityConfidence;

  bool get isClosed => closeTime != null && closePrice != null;
  Mt5Decimal get netTradingPnl => grossPriceProfit + commission + fee + swap;

  Mt5ImportedPosition copyWith({
    List<String>? relatedOrderTickets,
    List<String>? relatedDealTickets,
    Mt5Decimal? commission,
    Mt5Decimal? fee,
    Mt5Decimal? swap,
  }) => Mt5ImportedPosition(
    ticket: ticket,
    symbol: symbol,
    side: side,
    volume: volume,
    openPrice: openPrice,
    openTime: openTime,
    openTimeSource: openTimeSource,
    reference: reference,
    stopLoss: stopLoss,
    takeProfit: takeProfit,
    closeTime: closeTime,
    closeTimeSource: closeTimeSource,
    closePrice: closePrice,
    grossPriceProfit: grossPriceProfit,
    commission: commission ?? this.commission,
    fee: fee ?? this.fee,
    swap: swap ?? this.swap,
    relatedOrderTickets: relatedOrderTickets ?? this.relatedOrderTickets,
    relatedDealTickets: relatedDealTickets ?? this.relatedDealTickets,
    identityConfidence: identityConfidence,
  );

  Map<String, Object?> toJson() => {
    'source_position_ticket': ticket,
    'symbol': symbol,
    'side': side,
    'volume': volume.toString(),
    'open_price': openPrice.toString(),
    'stop_loss': stopLoss?.toString(),
    'take_profit': takeProfit?.toString(),
    'open_time': openTime.toIso8601String(),
    'open_time_source': openTimeSource,
    'close_time': closeTime?.toIso8601String(),
    'close_time_source': closeTimeSource,
    'close_price': closePrice?.toString(),
    'gross_price_profit': grossPriceProfit.toString(),
    'commission': commission.toString(),
    'fee': fee.toString(),
    'swap': swap.toString(),
    'net_trading_pnl': netTradingPnl.toString(),
    'status': isClosed ? 'closed' : 'open',
    'related_order_tickets': relatedOrderTickets,
    'related_deal_tickets': relatedDealTickets,
    'identity_confidence': identityConfidence.name,
    'source': reference.toJson(),
  };
}

class Mt5ImportedOrder {
  const Mt5ImportedOrder({
    required this.ticket,
    required this.symbol,
    required this.orderType,
    required this.requestedVolume,
    required this.executedVolume,
    required this.createdAt,
    required this.createdAtSource,
    required this.state,
    required this.reference,
    this.requestedPrice,
    this.marketPrice = false,
    this.stopLoss,
    this.takeProfit,
    this.updatedAt,
    this.updatedAtSource = '',
    this.comment = '',
    this.relatedPositionTicket,
  });

  final String ticket;
  final String symbol;
  final String orderType;
  final Mt5Decimal requestedVolume;
  final Mt5Decimal executedVolume;
  final Mt5Decimal? requestedPrice;
  final bool marketPrice;
  final Mt5Decimal? stopLoss;
  final Mt5Decimal? takeProfit;
  final DateTime createdAt;
  final String createdAtSource;
  final DateTime? updatedAt;
  final String updatedAtSource;
  final String state;
  final String comment;
  final String? relatedPositionTicket;
  final Mt5SourceReference reference;

  bool get isFilled => const {'filled', 'partially_filled'}.contains(state);
  bool get isPending => const {'placed', 'pending'}.contains(state);
  bool get isInactive =>
      const {'cancelled', 'expired', 'rejected'}.contains(state);

  Map<String, Object?> toJson() => {
    'source_order_ticket': ticket,
    'symbol': symbol,
    'order_type': orderType,
    'requested_volume': requestedVolume.toString(),
    'executed_volume': executedVolume.toString(),
    'requested_price': requestedPrice?.toString(),
    'market_price': marketPrice,
    'stop_loss': stopLoss?.toString(),
    'take_profit': takeProfit?.toString(),
    'created_at_source': createdAtSource,
    'open_time': createdAt.toIso8601String(),
    'updated_at_source': updatedAtSource,
    'close_time': updatedAt?.toIso8601String(),
    'state': state,
    'comment': comment,
    'related_position_ticket': relatedPositionTicket,
    'source': reference.toJson(),
  };
}

class Mt5ImportedDeal {
  Mt5ImportedDeal({
    required this.ticket,
    required this.dealType,
    required this.direction,
    required this.time,
    required this.timeSource,
    required this.classification,
    required this.profit,
    required this.reference,
    this.symbol = '',
    Mt5Decimal? volume,
    this.price,
    this.orderTicket,
    this.positionTicket,
    Mt5Decimal? commission,
    Mt5Decimal? fee,
    Mt5Decimal? swap,
    this.resultingBalance,
    this.comment = '',
    this.identityConfidence = Mt5IdentityConfidence.sourceTicket,
  }) : volume = volume ?? Mt5Decimal.zero,
       commission = commission ?? Mt5Decimal.zero,
       fee = fee ?? Mt5Decimal.zero,
       swap = swap ?? Mt5Decimal.zero;

  final String ticket;
  final String symbol;
  final String dealType;
  final String direction;
  final Mt5Decimal volume;
  final Mt5Decimal? price;
  final DateTime time;
  final String timeSource;
  final String? orderTicket;
  final String? positionTicket;
  final Mt5Decimal commission;
  final Mt5Decimal fee;
  final Mt5Decimal swap;
  final Mt5Decimal profit;
  final Mt5Decimal? resultingBalance;
  final String comment;
  final Mt5DealClassification classification;
  final Mt5SourceReference reference;
  final Mt5IdentityConfidence identityConfidence;

  bool get isTradingDeal => const {
    Mt5DealClassification.tradeEntry,
    Mt5DealClassification.tradeExit,
    Mt5DealClassification.tradeInOut,
    Mt5DealClassification.tradeOutBy,
  }.contains(classification);
  bool get isTradeEntry => classification == Mt5DealClassification.tradeEntry;
  bool get isTradeExit => const {
    Mt5DealClassification.tradeExit,
    Mt5DealClassification.tradeInOut,
    Mt5DealClassification.tradeOutBy,
  }.contains(classification);
  bool get isCashFlow => const {
    Mt5DealClassification.deposit,
    Mt5DealClassification.withdrawal,
    Mt5DealClassification.credit,
    Mt5DealClassification.creditRemoval,
    Mt5DealClassification.bonus,
    Mt5DealClassification.charge,
    Mt5DealClassification.correction,
    Mt5DealClassification.otherBalanceAdjustment,
  }.contains(classification);
  bool get isTradingPnlEligible => isTradeExit;
  bool get isTradeCountEligible => isTradeExit;
  bool get isPerformanceScoreEligible => isTradeExit;

  Mt5ImportedDeal copyWith({String? positionTicket}) => Mt5ImportedDeal(
    ticket: ticket,
    dealType: dealType,
    direction: direction,
    time: time,
    timeSource: timeSource,
    classification: classification,
    profit: profit,
    reference: reference,
    symbol: symbol,
    volume: volume,
    price: price,
    orderTicket: orderTicket,
    positionTicket: positionTicket ?? this.positionTicket,
    commission: commission,
    fee: fee,
    swap: swap,
    resultingBalance: resultingBalance,
    comment: comment,
    identityConfidence: positionTicket == null
        ? identityConfidence
        : Mt5IdentityConfidence.deterministicFallback,
  );

  Map<String, Object?> toJson() => {
    'source_deal_ticket': ticket,
    'symbol': symbol,
    'deal_type': dealType,
    'direction': direction,
    'volume': volume.toString(),
    'price': price?.toString(),
    'time': time.toIso8601String(),
    'time_source': timeSource,
    'related_order_ticket': orderTicket,
    'related_position_ticket': positionTicket,
    'commission': commission.toString(),
    'fee': fee.toString(),
    'swap': swap.toString(),
    'profit': profit.toString(),
    'resulting_balance': resultingBalance?.toString(),
    'comment': comment,
    'classification': classification.name,
    'is_trading_deal': isTradingDeal,
    'is_trade_entry': isTradeEntry,
    'is_trade_exit': isTradeExit,
    'is_cash_flow': isCashFlow,
    'is_deposit': classification == Mt5DealClassification.deposit,
    'is_withdrawal': classification == Mt5DealClassification.withdrawal,
    'is_trading_pnl_eligible': isTradingPnlEligible,
    'is_trade_count_eligible': isTradeCountEligible,
    'is_performance_score_eligible': isPerformanceScoreEligible,
    'identity_confidence': identityConfidence.name,
    'source': reference.toJson(),
  };
}

class Mt5BalanceTransaction {
  const Mt5BalanceTransaction({
    required this.dealTicket,
    required this.time,
    required this.amount,
    required this.classification,
    required this.comment,
    required this.reference,
    this.resultingBalance,
  });

  final String dealTicket;
  final DateTime time;
  final Mt5Decimal amount;
  final Mt5Decimal? resultingBalance;
  final Mt5DealClassification classification;
  final String comment;
  final Mt5SourceReference reference;

  Map<String, Object?> toJson() => {
    'source_deal_ticket': dealTicket,
    'time': time.toIso8601String(),
    'amount': amount.toString(),
    'resulting_balance': resultingBalance?.toString(),
    'classification': classification.name,
    'comment': comment,
    'source': reference.toJson(),
  };
}

class Mt5ResultComparison {
  const Mt5ResultComparison({
    required this.key,
    required this.sourceValue,
    required this.calculatedValue,
    required this.difference,
    required this.matches,
  });

  final String key;
  final Mt5Decimal sourceValue;
  final Mt5Decimal calculatedValue;
  final Mt5Decimal difference;
  final bool matches;

  Map<String, Object?> toJson() => {
    'key': key,
    'source_value': sourceValue.toString(),
    'calculated_value': calculatedValue.toString(),
    'difference': difference.toString(),
    'matches': matches,
  };
}

class Mt5FinancialSummary {
  const Mt5FinancialSummary({
    required this.grossProfit,
    required this.grossLoss,
    required this.commission,
    required this.fee,
    required this.swap,
    required this.netTradingPnl,
    required this.deposits,
    required this.withdrawals,
    required this.credits,
    required this.otherAdjustments,
    required this.netCapitalFlow,
    required this.openingBalance,
    required this.calculatedClosingBalance,
    required this.sourceClosingBalance,
    required this.reconciliationDifference,
    required this.reconciled,
  });

  final Mt5Decimal grossProfit;
  final Mt5Decimal grossLoss;
  final Mt5Decimal commission;
  final Mt5Decimal fee;
  final Mt5Decimal swap;
  final Mt5Decimal netTradingPnl;
  final Mt5Decimal deposits;
  final Mt5Decimal withdrawals;
  final Mt5Decimal credits;
  final Mt5Decimal otherAdjustments;
  final Mt5Decimal netCapitalFlow;
  final Mt5Decimal openingBalance;
  final Mt5Decimal calculatedClosingBalance;
  final Mt5Decimal sourceClosingBalance;
  final Mt5Decimal reconciliationDifference;
  final bool reconciled;

  Map<String, Object?> toJson() => {
    'gross_profit': grossProfit.toString(),
    'gross_loss': grossLoss.toString(),
    'commission': commission.toString(),
    'fee': fee.toString(),
    'swap': swap.toString(),
    'net_trading_pnl': netTradingPnl.toString(),
    'deposits': deposits.toString(),
    'withdrawals': withdrawals.toString(),
    'credits': credits.toString(),
    'other_adjustments': otherAdjustments.toString(),
    'net_capital_flow': netCapitalFlow.toString(),
    'opening_balance': openingBalance.toString(),
    'calculated_closing_balance': calculatedClosingBalance.toString(),
    'source_closing_balance': sourceClosingBalance.toString(),
    'reconciliation_difference': reconciliationDifference.toString(),
    'reconciled': reconciled,
  };
}

class Mt5DuplicateSummary {
  const Mt5DuplicateSummary({
    this.newRecords = 0,
    this.existingIdentical = 0,
    this.updatedRecords = 0,
    this.conflicts = 0,
    this.previousBatchId = '',
    this.sameFileAlreadyImported = false,
  });

  final int newRecords;
  final int existingIdentical;
  final int updatedRecords;
  final int conflicts;
  final String previousBatchId;
  final bool sameFileAlreadyImported;

  factory Mt5DuplicateSummary.fromJson(Map<String, dynamic> json) =>
      Mt5DuplicateSummary(
        newRecords: _asInt(json['new_records']),
        existingIdentical: _asInt(json['existing_identical']),
        updatedRecords: _asInt(json['updated_records']),
        conflicts: _asInt(json['conflicts']),
        previousBatchId: '${json['previous_batch_id'] ?? ''}',
        sameFileAlreadyImported: json['same_file_already_imported'] == true,
      );

  Map<String, Object?> toJson() => {
    'new_records': newRecords,
    'existing_identical': existingIdentical,
    'updated_records': updatedRecords,
    'conflicts': conflicts,
    'previous_batch_id': previousBatchId,
    'same_file_already_imported': sameFileAlreadyImported,
  };
}

class Mt5ImportPreview {
  const Mt5ImportPreview({
    required this.filename,
    required this.fileSize,
    required this.fileHash,
    required this.parserProfile,
    required this.parserVersion,
    required this.sheetNames,
    required this.detectedSections,
    required this.columnMappings,
    required this.formatConfidence,
    required this.metadata,
    required this.positions,
    required this.orders,
    required this.deals,
    required this.balanceTransactions,
    required this.financialSummary,
    required this.resultComparisons,
    required this.issues,
    required this.rejectedRows,
    required this.totalSourceRows,
    required this.parsedRows,
    required this.ignoredRows,
    this.duplicateSummary = const Mt5DuplicateSummary(),
  });

  final String filename;
  final int fileSize;
  final String fileHash;
  final String parserProfile;
  final String parserVersion;
  final List<String> sheetNames;
  final Set<Mt5ImportSection> detectedSections;
  final Map<String, Map<String, int>> columnMappings;
  final double formatConfidence;
  final Mt5ReportMetadata metadata;
  final List<Mt5ImportedPosition> positions;
  final List<Mt5ImportedOrder> orders;
  final List<Mt5ImportedDeal> deals;
  final List<Mt5BalanceTransaction> balanceTransactions;
  final Mt5FinancialSummary financialSummary;
  final List<Mt5ResultComparison> resultComparisons;
  final List<Mt5ValidationIssue> issues;
  final List<Mt5RejectedRow> rejectedRows;
  final int totalSourceRows;
  final int parsedRows;
  final int ignoredRows;
  final Mt5DuplicateSummary duplicateSummary;

  bool get hasBlockingErrors => issues.any((issue) => issue.blocksCommit);
  int get completedPositions => positions.where((item) => item.isClosed).length;
  int get openPositions => positions.length - completedPositions;
  int get filledOrders => orders.where((item) => item.isFilled).length;
  int get pendingOrders => orders.where((item) => item.isPending).length;
  int get inactiveOrders => orders.where((item) => item.isInactive).length;
  int get deposits => balanceTransactions
      .where((item) => item.classification == Mt5DealClassification.deposit)
      .length;
  int get withdrawals => balanceTransactions
      .where((item) => item.classification == Mt5DealClassification.withdrawal)
      .length;

  Mt5ImportPreview copyWith({
    Mt5DuplicateSummary? duplicateSummary,
    List<Mt5ValidationIssue>? issues,
    Mt5ReportMetadata? metadata,
  }) => Mt5ImportPreview(
    filename: filename,
    fileSize: fileSize,
    fileHash: fileHash,
    parserProfile: parserProfile,
    parserVersion: parserVersion,
    sheetNames: sheetNames,
    detectedSections: detectedSections,
    columnMappings: columnMappings,
    formatConfidence: formatConfidence,
    metadata: metadata ?? this.metadata,
    positions: positions,
    orders: orders,
    deals: deals,
    balanceTransactions: balanceTransactions,
    financialSummary: financialSummary,
    resultComparisons: resultComparisons,
    issues: issues ?? this.issues,
    rejectedRows: rejectedRows,
    totalSourceRows: totalSourceRows,
    parsedRows: parsedRows,
    ignoredRows: ignoredRows,
    duplicateSummary: duplicateSummary ?? this.duplicateSummary,
  );

  Map<String, Object?> manifestJson({required String selectedAccountId}) => {
    'filename': filename,
    'file_size': fileSize,
    'file_hash': fileHash,
    'parser_profile': parserProfile,
    'parser_version': parserVersion,
    'selected_mt5_connection_id': selectedAccountId,
    'metadata': metadata.toJson(),
    'financial_summary': financialSummary.toJson(),
    'counts': {
      'source_rows': totalSourceRows,
      'parsed_rows': parsedRows,
      'ignored_rows': ignoredRows,
      'rejected_rows': rejectedRows.length,
      'positions': positions.length,
      'orders': orders.length,
      'deals': deals.length,
      'balance_transactions': balanceTransactions.length,
    },
    'record_identities': [
      ...positions.map(
        (item) => {
          'kind': 'position',
          'ticket': item.ticket,
          'financial_hash_input': _financialIdentity(item.toJson()),
          'financial_values': item.toJson(),
          'status': item.isClosed ? 'closed' : 'open',
        },
      ),
      ...orders.map(
        (item) => {
          'kind': 'order',
          'ticket': item.ticket,
          'financial_hash_input': _financialIdentity(item.toJson()),
          'financial_values': item.toJson(),
          'status': item.state,
        },
      ),
      ...deals.map(
        (item) => {
          'kind': 'deal',
          'ticket': item.ticket,
          'financial_hash_input': _financialIdentity(item.toJson()),
          'financial_values': item.toJson(),
          'status': item.classification.name,
        },
      ),
      ...balanceTransactions.map(
        (item) => {
          'kind': 'balance_transaction',
          'ticket': item.dealTicket,
          'financial_hash_input': _financialIdentity(item.toJson()),
          'financial_values': item.toJson(),
          'status': item.classification.name,
        },
      ),
    ],
  };

  List<Map<String, Object?>> recordPayloads() => [
    ...positions.map((item) => {'kind': 'position', 'data': item.toJson()}),
    ...orders.map((item) => {'kind': 'order', 'data': item.toJson()}),
    ...deals.map((item) => {'kind': 'deal', 'data': item.toJson()}),
    ...balanceTransactions.map(
      (item) => {'kind': 'balance_transaction', 'data': item.toJson()},
    ),
  ];

  Map<String, Object?> auditJson({required String selectedAccountId}) => {
    ...manifestJson(selectedAccountId: selectedAccountId),
    'sheets': sheetNames,
    'sections': detectedSections.map((item) => item.name).toList(),
    'column_mappings': columnMappings,
    'format_confidence': formatConfidence,
    'result_comparisons': resultComparisons
        .map((item) => item.toJson())
        .toList(),
    'issues': issues.map((item) => item.toJson()).toList(),
    'rejected_rows': rejectedRows.map((item) => item.toJson()).toList(),
  };
}

String _financialIdentity(Map<String, Object?> data) {
  const ignored = {'source', 'related_order_tickets', 'related_deal_tickets'};
  final entries =
      data.entries
          .where((entry) => !ignored.contains(entry.key))
          .toList(growable: false)
        ..sort((left, right) => left.key.compareTo(right.key));
  return entries.map((entry) => '${entry.key}=${entry.value}').join('|');
}

int _asInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
