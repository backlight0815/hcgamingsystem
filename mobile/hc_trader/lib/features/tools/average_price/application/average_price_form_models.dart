import '../../../../models/mt5_import_models.dart';
import '../domain/average_price_models.dart';

class PositionOrderDraft {
  const PositionOrderDraft({
    required this.id,
    this.price = '',
    this.quantity = '',
    this.fee = '',
    this.note = '',
  });

  final String id;
  final String price;
  final String quantity;
  final String fee;
  final String note;

  PositionOrderDraft copyWith({
    String? id,
    String? price,
    String? quantity,
    String? fee,
    String? note,
  }) => PositionOrderDraft(
    id: id ?? this.id,
    price: price ?? this.price,
    quantity: quantity ?? this.quantity,
    fee: fee ?? this.fee,
    note: note ?? this.note,
  );

  PositionEntry toDomain() => PositionEntry(
    id: id,
    entryPrice: Mt5Decimal.tryParse(price),
    quantity: Mt5Decimal.tryParse(quantity),
    entryFee: _nullableDecimal(fee),
    note: note.trim().isEmpty ? null : note.trim(),
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'price': price,
    'quantity': quantity,
    'fee': fee,
    'note': note,
  };

  factory PositionOrderDraft.fromJson(Map<String, dynamic> json) =>
      PositionOrderDraft(
        id: json['id']?.toString() ?? '',
        price: json['price']?.toString() ?? '',
        quantity: json['quantity']?.toString() ?? '',
        fee: json['fee']?.toString() ?? '',
        note: json['note']?.toString() ?? '',
      );
}

class TakeProfitDraft {
  const TakeProfitDraft({
    required this.id,
    this.price = '',
    this.percentage = '',
  });

  final String id;
  final String price;
  final String percentage;

  TakeProfitDraft copyWith({String? id, String? price, String? percentage}) =>
      TakeProfitDraft(
        id: id ?? this.id,
        price: price ?? this.price,
        percentage: percentage ?? this.percentage,
      );

  TakeProfitTarget toDomain() => TakeProfitTarget(
    id: id,
    targetPrice: Mt5Decimal.tryParse(price),
    closePercentage: Mt5Decimal.tryParse(percentage),
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'price': price,
    'percentage': percentage,
  };

  factory TakeProfitDraft.fromJson(Map<String, dynamic> json) =>
      TakeProfitDraft(
        id: json['id']?.toString() ?? '',
        price: json['price']?.toString() ?? '',
        percentage: json['percentage']?.toString() ?? '',
      );
}

class AveragePriceSetupSnapshot {
  const AveragePriceSetupSnapshot({
    required this.direction,
    required this.mode,
    required this.orders,
    required this.takeProfits,
    required this.symbol,
    required this.assetCategory,
    required this.pricePrecision,
    required this.quantityType,
    required this.contractSize,
    required this.tickSize,
    required this.tickValue,
    required this.pipSize,
    required this.accountCurrency,
    required this.currentMarketPrice,
    required this.entryCommissionPercent,
    required this.exitCommissionPercent,
    required this.spread,
    required this.slippage,
    required this.swapCost,
    required this.otherCosts,
    required this.bufferValue,
    required this.bufferType,
    required this.stopLoss,
    required this.targetRiskReward,
    required this.notes,
  });

  final PositionDirection direction;
  final AveragePriceMode mode;
  final List<PositionOrderDraft> orders;
  final List<TakeProfitDraft> takeProfits;
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
  final String entryCommissionPercent;
  final String exitCommissionPercent;
  final String spread;
  final String slippage;
  final String swapCost;
  final String otherCosts;
  final String bufferValue;
  final SafetyBufferType bufferType;
  final String stopLoss;
  final String targetRiskReward;
  final String notes;

  AveragePriceCalculationRequest toRequest() => AveragePriceCalculationRequest(
    direction: direction,
    entries: orders.map((order) => order.toDomain()).toList(),
    takeProfits: takeProfits.map((target) => target.toDomain()).toList(),
    instrument: InstrumentSettings(
      symbol: symbol.trim(),
      assetCategory: assetCategory,
      pricePrecision: pricePrecision,
      quantityType: quantityType,
      contractSize: contractSize,
      tickSize: tickSize,
      tickValue: tickValue,
      pipSize: pipSize,
      accountCurrency: accountCurrency.trim().toUpperCase(),
      currentMarketPrice: currentMarketPrice,
    ),
    costs: TradingCostSettings(
      entryCommissionPercent: entryCommissionPercent,
      exitCommissionPercent: exitCommissionPercent,
      spread: spread,
      slippage: slippage,
      swapCost: swapCost,
      otherCosts: otherCosts,
    ),
    safetyBuffer: SafetyBufferSettings(value: bufferValue, type: bufferType),
    stopLoss: _nullableDecimal(stopLoss),
    primaryTakeProfit: takeProfits.isEmpty
        ? null
        : _nullableDecimal(takeProfits.first.price),
    targetRiskReward: _nullableDecimal(targetRiskReward),
    advancedMode: mode == AveragePriceMode.advanced,
  );

  Map<String, Object?> toJson() => {
    'direction': direction.name,
    'mode': mode.name,
    'orders': orders.map((order) => order.toJson()).toList(),
    'take_profits': takeProfits.map((target) => target.toJson()).toList(),
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
    'entry_commission_percent': entryCommissionPercent,
    'exit_commission_percent': exitCommissionPercent,
    'spread': spread,
    'slippage': slippage,
    'swap_cost': swapCost,
    'other_costs': otherCosts,
    'buffer_value': bufferValue,
    'buffer_type': bufferType.name,
    'stop_loss': stopLoss,
    'target_risk_reward': targetRiskReward,
    'notes': notes,
  };

  factory AveragePriceSetupSnapshot.fromJson(
    Map<String, dynamic> json,
  ) => AveragePriceSetupSnapshot(
    direction: _enumByName(
      PositionDirection.values,
      json['direction'],
      PositionDirection.long,
    ),
    mode: _enumByName(
      AveragePriceMode.values,
      json['mode'],
      AveragePriceMode.simple,
    ),
    orders: _maps(json['orders']).map(PositionOrderDraft.fromJson).toList(),
    takeProfits: _maps(
      json['take_profits'],
    ).map(TakeProfitDraft.fromJson).toList(),
    symbol: json['symbol']?.toString() ?? '',
    assetCategory: _enumByName(
      AssetCategory.values,
      json['asset_category'],
      AssetCategory.custom,
    ),
    pricePrecision: ((json['price_precision'] as num?)?.toInt() ?? 2).clamp(
      0,
      8,
    ),
    quantityType: _enumByName(
      QuantityType.values,
      json['quantity_type'],
      QuantityType.units,
    ),
    contractSize: json['contract_size']?.toString() ?? '',
    tickSize: json['tick_size']?.toString() ?? '',
    tickValue: json['tick_value']?.toString() ?? '',
    pipSize: json['pip_size']?.toString() ?? '',
    accountCurrency: json['account_currency']?.toString() ?? 'USD',
    currentMarketPrice: json['current_market_price']?.toString() ?? '',
    entryCommissionPercent: json['entry_commission_percent']?.toString() ?? '0',
    exitCommissionPercent: json['exit_commission_percent']?.toString() ?? '0',
    spread: json['spread']?.toString() ?? '0',
    slippage: json['slippage']?.toString() ?? '0',
    swapCost: json['swap_cost']?.toString() ?? '0',
    otherCosts: json['other_costs']?.toString() ?? '0',
    bufferValue: json['buffer_value']?.toString() ?? '0',
    bufferType: _enumByName(
      SafetyBufferType.values,
      json['buffer_type'],
      SafetyBufferType.points,
    ),
    stopLoss: json['stop_loss']?.toString() ?? '',
    targetRiskReward: json['target_risk_reward']?.toString() ?? '2',
    notes: json['notes']?.toString() ?? '',
  );
}

class WhatIfOrderDraft {
  const WhatIfOrderDraft({this.price = '', this.quantity = '', this.fee = ''});

  final String price;
  final String quantity;
  final String fee;

  WhatIfOrderDraft copyWith({String? price, String? quantity, String? fee}) =>
      WhatIfOrderDraft(
        price: price ?? this.price,
        quantity: quantity ?? this.quantity,
        fee: fee ?? this.fee,
      );

  bool get isValid =>
      Mt5Decimal.tryParse(price).isPositive &&
      Mt5Decimal.tryParse(quantity).isPositive;
}

class WhatIfCalculationResult {
  const WhatIfCalculationResult({
    required this.current,
    required this.projected,
    required this.averageMovement,
    required this.additionalCapital,
  });

  final AveragePriceCalculationResult current;
  final AveragePriceCalculationResult projected;
  final Mt5Decimal averageMovement;
  final Mt5Decimal? additionalCapital;
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

T _enumByName<T extends Enum>(Iterable<T> values, Object? source, T fallback) {
  final name = source?.toString();
  return values.where((value) => value.name == name).firstOrNull ?? fallback;
}

Iterable<Map<String, dynamic>> _maps(Object? source) sync* {
  if (source is! List) return;
  for (final value in source) {
    if (value is Map) yield Map<String, dynamic>.from(value);
  }
}
