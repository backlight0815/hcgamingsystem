import '../../../../models/mt5_import_models.dart';

enum PositionDirection { long, short }

enum AveragePriceMode { simple, advanced }

enum AssetCategory {
  forex,
  commodities,
  indices,
  stocks,
  cryptocurrency,
  futures,
  cfd,
  custom,
}

enum QuantityType { units, lots, contracts, shares, coins }

enum SafetyBufferType { points, percentage, pips, ticks }

enum AveragePriceWarningCode {
  noValidOrders,
  invalidPrice,
  invalidQuantity,
  stopLossDirection,
  stopLossAtAverage,
  takeProfitDirection,
  takeProfitAtAverage,
  allocationAboveHundred,
  allocationBelowHundred,
  invalidCommission,
  invalidContractSpecification,
}

enum BreakEvenStatus {
  unavailable,
  invalidSetup,
  belowBreakEven,
  approachingBreakEven,
  atGrossBreakEven,
  aboveFeeAdjustedBreakEven,
  aboveSafeBreakEven,
  targetReached,
}

class PositionEntry {
  const PositionEntry({
    required this.id,
    required this.entryPrice,
    required this.quantity,
    this.entryFee,
    this.note,
  });

  final String id;
  final Mt5Decimal entryPrice;
  final Mt5Decimal quantity;
  final Mt5Decimal? entryFee;
  final String? note;

  PositionEntry copyWith({
    String? id,
    Mt5Decimal? entryPrice,
    Mt5Decimal? quantity,
    Mt5Decimal? entryFee,
    String? note,
    bool clearEntryFee = false,
  }) => PositionEntry(
    id: id ?? this.id,
    entryPrice: entryPrice ?? this.entryPrice,
    quantity: quantity ?? this.quantity,
    entryFee: clearEntryFee ? null : entryFee ?? this.entryFee,
    note: note ?? this.note,
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'entry_price': entryPrice.toString(),
    'quantity': quantity.toString(),
    if (entryFee != null) 'entry_fee': entryFee.toString(),
    if (note?.trim().isNotEmpty == true) 'note': note!.trim(),
  };

  factory PositionEntry.fromJson(Map<String, dynamic> json) => PositionEntry(
    id: json['id']?.toString() ?? '',
    entryPrice: Mt5Decimal.tryParse(json['entry_price']),
    quantity: Mt5Decimal.tryParse(json['quantity']),
    entryFee: _nullableDecimal(json['entry_fee']),
    note: json['note']?.toString(),
  );
}

class TakeProfitTarget {
  const TakeProfitTarget({
    required this.id,
    required this.targetPrice,
    required this.closePercentage,
  });

  final String id;
  final Mt5Decimal targetPrice;
  final Mt5Decimal closePercentage;

  Map<String, Object?> toJson() => {
    'id': id,
    'target_price': targetPrice.toString(),
    'close_percentage': closePercentage.toString(),
  };

  factory TakeProfitTarget.fromJson(Map<String, dynamic> json) =>
      TakeProfitTarget(
        id: json['id']?.toString() ?? '',
        targetPrice: Mt5Decimal.tryParse(json['target_price']),
        closePercentage: Mt5Decimal.tryParse(json['close_percentage']),
      );
}

class TradingCostSettings {
  const TradingCostSettings({
    this.entryCommissionPercent = '0',
    this.exitCommissionPercent = '0',
    this.spread = '0',
    this.slippage = '0',
    this.swapCost = '0',
    this.otherCosts = '0',
  });

  final String entryCommissionPercent;
  final String exitCommissionPercent;
  final String spread;
  final String slippage;
  final String swapCost;
  final String otherCosts;

  Mt5Decimal get entryCommissionRate => _percent(entryCommissionPercent);
  Mt5Decimal get exitCommissionRate => _percent(exitCommissionPercent);
  Mt5Decimal get spreadValue => Mt5Decimal.tryParse(spread).abs;
  Mt5Decimal get slippageValue => Mt5Decimal.tryParse(slippage).abs;
  Mt5Decimal get swapCostValue => Mt5Decimal.tryParse(swapCost).abs;
  Mt5Decimal get otherCostsValue => Mt5Decimal.tryParse(otherCosts).abs;

  bool get hasCosts =>
      entryCommissionRate.isPositive ||
      exitCommissionRate.isPositive ||
      spreadValue.isPositive ||
      slippageValue.isPositive ||
      swapCostValue.isPositive ||
      otherCostsValue.isPositive;

  Map<String, Object?> toJson() => {
    'entry_commission_percent': entryCommissionPercent,
    'exit_commission_percent': exitCommissionPercent,
    'spread': spread,
    'slippage': slippage,
    'swap_cost': swapCost,
    'other_costs': otherCosts,
  };

  factory TradingCostSettings.fromJson(Map<String, dynamic>? json) {
    final value = json ?? const <String, dynamic>{};
    return TradingCostSettings(
      entryCommissionPercent:
          value['entry_commission_percent']?.toString() ?? '0',
      exitCommissionPercent:
          value['exit_commission_percent']?.toString() ?? '0',
      spread: value['spread']?.toString() ?? '0',
      slippage: value['slippage']?.toString() ?? '0',
      swapCost: value['swap_cost']?.toString() ?? '0',
      otherCosts: value['other_costs']?.toString() ?? '0',
    );
  }
}

class InstrumentSettings {
  const InstrumentSettings({
    this.symbol = '',
    this.assetCategory = AssetCategory.custom,
    this.pricePrecision = 2,
    this.quantityType = QuantityType.units,
    this.contractSize = '',
    this.tickSize = '',
    this.tickValue = '',
    this.pipSize = '',
    this.accountCurrency = 'USD',
    this.currentMarketPrice = '',
  });

  final String symbol;
  final AssetCategory assetCategory;
  final int pricePrecision;
  final QuantityType quantityType;
  final String contractSize;
  final String tickSize;
  final String tickValue;
  final String pipSize;
  final String accountCurrency;
  final String currentMarketPrice;

  Mt5Decimal get contractSizeValue => Mt5Decimal.tryParse(contractSize);
  Mt5Decimal? get tickSizeValue => _nullableDecimal(tickSize);
  Mt5Decimal? get tickValueValue => _nullableDecimal(tickValue);
  Mt5Decimal? get pipSizeValue => _nullableDecimal(pipSize);
  Mt5Decimal? get currentMarketPriceValue =>
      _nullableDecimal(currentMarketPrice);

  bool get hasMonetarySpecification {
    final tickSize = tickSizeValue;
    final tickValue = tickValueValue;
    return contractSizeValue.isPositive ||
        (tickSize != null &&
            tickSize.isPositive &&
            tickValue != null &&
            tickValue.isPositive);
  }

  Map<String, Object?> toJson() => {
    'symbol': symbol,
    'asset_category': assetCategory.name,
    'price_precision': pricePrecision,
    'quantity_type': quantityType.name,
    'contract_size': contractSize,
    'tick_size': tickSize,
    'tick_value': tickValue,
    'pip_size': pipSize,
    'account_currency': accountCurrency,
    'current_market_price': currentMarketPrice,
  };

  factory InstrumentSettings.fromJson(Map<String, dynamic>? json) {
    final value = json ?? const <String, dynamic>{};
    return InstrumentSettings(
      symbol: value['symbol']?.toString() ?? '',
      assetCategory: _enumByName(
        AssetCategory.values,
        value['asset_category'],
        AssetCategory.custom,
      ),
      pricePrecision: ((value['price_precision'] as num?)?.toInt() ?? 2).clamp(
        0,
        8,
      ),
      quantityType: _enumByName(
        QuantityType.values,
        value['quantity_type'],
        QuantityType.units,
      ),
      contractSize: value['contract_size']?.toString() ?? '',
      tickSize: value['tick_size']?.toString() ?? '',
      tickValue: value['tick_value']?.toString() ?? '',
      pipSize: value['pip_size']?.toString() ?? '',
      accountCurrency: value['account_currency']?.toString() ?? 'USD',
      currentMarketPrice: value['current_market_price']?.toString() ?? '',
    );
  }
}

class SafetyBufferSettings {
  const SafetyBufferSettings({
    this.value = '0',
    this.type = SafetyBufferType.points,
  });

  final String value;
  final SafetyBufferType type;

  Map<String, Object?> toJson() => {'value': value, 'type': type.name};

  factory SafetyBufferSettings.fromJson(Map<String, dynamic>? json) {
    final value = json ?? const <String, dynamic>{};
    return SafetyBufferSettings(
      value: value['value']?.toString() ?? '0',
      type: _enumByName(
        SafetyBufferType.values,
        value['type'],
        SafetyBufferType.points,
      ),
    );
  }
}

class AveragePriceCalculationRequest {
  const AveragePriceCalculationRequest({
    required this.direction,
    required this.entries,
    required this.takeProfits,
    required this.instrument,
    required this.costs,
    required this.safetyBuffer,
    this.stopLoss,
    this.primaryTakeProfit,
    this.targetRiskReward,
    this.advancedMode = false,
  });

  final PositionDirection direction;
  final List<PositionEntry> entries;
  final List<TakeProfitTarget> takeProfits;
  final InstrumentSettings instrument;
  final TradingCostSettings costs;
  final SafetyBufferSettings safetyBuffer;
  final Mt5Decimal? stopLoss;
  final Mt5Decimal? primaryTakeProfit;
  final Mt5Decimal? targetRiskReward;
  final bool advancedMode;
}

class TakeProfitCalculationResult {
  const TakeProfitCalculationResult({
    required this.target,
    required this.distance,
    required this.percentageDistance,
    required this.grossRiskReward,
    required this.grossProfit,
    required this.netProfit,
  });

  final TakeProfitTarget target;
  final Mt5Decimal distance;
  final Mt5Decimal percentageDistance;
  final Mt5Decimal? grossRiskReward;
  final Mt5Decimal? grossProfit;
  final Mt5Decimal? netProfit;
}

class AveragePriceCalculationResult {
  const AveragePriceCalculationResult({
    required this.totalQuantity,
    required this.totalEntryValue,
    required this.weightedAverageEntry,
    required this.grossBreakEven,
    required this.feeAdjustedBreakEven,
    required this.safeBreakEven,
    required this.totalExposure,
    required this.currentNetPnl,
    required this.estimatedProfitAtTarget,
    required this.estimatedLossAtStop,
    required this.riskDistance,
    required this.rewardDistance,
    required this.grossRiskReward,
    required this.netRiskReward,
    required this.requiredTakeProfit,
    required this.distanceToBreakEven,
    required this.takeProfitResults,
    required this.blendedGrossProfit,
    required this.blendedNetProfit,
    required this.allocatedPercentage,
    required this.remainingPercentage,
    required this.status,
    required this.warnings,
    required this.monetaryEstimatesAvailable,
    required this.validOrderCount,
  });

  final Mt5Decimal totalQuantity;
  final Mt5Decimal totalEntryValue;
  final Mt5Decimal? weightedAverageEntry;
  final Mt5Decimal? grossBreakEven;
  final Mt5Decimal? feeAdjustedBreakEven;
  final Mt5Decimal? safeBreakEven;
  final Mt5Decimal? totalExposure;
  final Mt5Decimal? currentNetPnl;
  final Mt5Decimal? estimatedProfitAtTarget;
  final Mt5Decimal? estimatedLossAtStop;
  final Mt5Decimal? riskDistance;
  final Mt5Decimal? rewardDistance;
  final Mt5Decimal? grossRiskReward;
  final Mt5Decimal? netRiskReward;
  final Mt5Decimal? requiredTakeProfit;
  final Mt5Decimal? distanceToBreakEven;
  final List<TakeProfitCalculationResult> takeProfitResults;
  final Mt5Decimal? blendedGrossProfit;
  final Mt5Decimal? blendedNetProfit;
  final Mt5Decimal allocatedPercentage;
  final Mt5Decimal remainingPercentage;
  final BreakEvenStatus status;
  final Set<AveragePriceWarningCode> warnings;
  final bool monetaryEstimatesAvailable;
  final int validOrderCount;

  bool get canCalculate => weightedAverageEntry != null;

  static AveragePriceCalculationResult empty({
    Set<AveragePriceWarningCode> warnings = const {
      AveragePriceWarningCode.noValidOrders,
    },
  }) => AveragePriceCalculationResult(
    totalQuantity: Mt5Decimal.zero,
    totalEntryValue: Mt5Decimal.zero,
    weightedAverageEntry: null,
    grossBreakEven: null,
    feeAdjustedBreakEven: null,
    safeBreakEven: null,
    totalExposure: null,
    currentNetPnl: null,
    estimatedProfitAtTarget: null,
    estimatedLossAtStop: null,
    riskDistance: null,
    rewardDistance: null,
    grossRiskReward: null,
    netRiskReward: null,
    requiredTakeProfit: null,
    distanceToBreakEven: null,
    takeProfitResults: const [],
    blendedGrossProfit: null,
    blendedNetProfit: null,
    allocatedPercentage: Mt5Decimal.zero,
    remainingPercentage: Mt5Decimal.parse('100'),
    status: BreakEvenStatus.unavailable,
    warnings: warnings,
    monetaryEstimatesAvailable: false,
    validOrderCount: 0,
  );
}

Mt5Decimal? _nullableDecimal(Object? source) {
  final text = source?.toString().trim() ?? '';
  if (text.isEmpty) return null;
  try {
    return Mt5Decimal.parse(text);
  } on FormatException {
    return null;
  }
}

Mt5Decimal _percent(Object? source) => Mt5Decimal.tryParse(
  source,
).abs.divide(Mt5Decimal.parse('100'), resultScale: 12);

T _enumByName<T extends Enum>(Iterable<T> values, Object? source, T fallback) {
  final name = source?.toString();
  for (final value in values) {
    if (value.name == name) return value;
  }
  return fallback;
}
