import '../../models/mt5_import_models.dart';

enum TradingSignalPublicationStatus {
  draft('draft', 0),
  active('active', 1),
  closed('closed', 13),
  cancelled('cancelled', 12);

  const TradingSignalPublicationStatus(this.value, this.legacyCode);
  final String value;
  final int legacyCode;

  static TradingSignalPublicationStatus fromValue(Object? value) =>
      values
          .where(
            (status) => status.value == value?.toString().trim().toLowerCase(),
          )
          .firstOrNull ??
      draft;
}

enum MarketAnalysisPublicationStatus {
  draft('draft'),
  scheduled('scheduled'),
  published('published'),
  archived('archived'),
  cancelled('cancelled');

  const MarketAnalysisPublicationStatus(this.value);
  final String value;
}

enum TradingSignalValidationIssue {
  entryRequired,
  entryInvalid,
  stopLossRequired,
  stopLossInvalid,
  targetRequired,
  targetInvalid,
  buyStopLoss,
  sellStopLoss,
  buyTargets,
  sellTargets,
  targetOrder,
}

Mt5Decimal? parseTradingPrice(Object? value) {
  final normalized = value?.toString().trim().replaceAll(',', '') ?? '';
  try {
    final parsed = Mt5Decimal.parse(normalized);
    return parsed.isNegative || parsed.isZero ? null : parsed;
  } on FormatException {
    return null;
  }
}

TradingSignalValidationIssue? validateTradingSignalPrices({
  required String action,
  required Object? entry,
  required Object? stopLoss,
  required Iterable<Object?> targets,
}) {
  final entryText = entry?.toString().trim() ?? '';
  if (entryText.isEmpty) return TradingSignalValidationIssue.entryRequired;
  final entryPrice = parseTradingPrice(entryText);
  if (entryPrice == null) return TradingSignalValidationIssue.entryInvalid;

  final stopText = stopLoss?.toString().trim() ?? '';
  if (stopText.isEmpty) return TradingSignalValidationIssue.stopLossRequired;
  final stopPrice = parseTradingPrice(stopText);
  if (stopPrice == null) return TradingSignalValidationIssue.stopLossInvalid;

  final rawTargets = targets
      .map((value) => value?.toString().trim() ?? '')
      .where((value) => value.isNotEmpty)
      .toList(growable: false);
  if (rawTargets.isEmpty) return TradingSignalValidationIssue.targetRequired;
  final prices = rawTargets.map(parseTradingPrice).toList(growable: false);
  if (prices.any((value) => value == null)) {
    return TradingSignalValidationIssue.targetInvalid;
  }
  final targetPrices = prices.cast<Mt5Decimal>();
  final isSell = action.trim().toUpperCase() == 'SELL';
  if (isSell && stopPrice.compareTo(entryPrice) <= 0) {
    return TradingSignalValidationIssue.sellStopLoss;
  }
  if (!isSell && stopPrice.compareTo(entryPrice) >= 0) {
    return TradingSignalValidationIssue.buyStopLoss;
  }
  if (isSell && targetPrices.any((value) => value.compareTo(entryPrice) >= 0)) {
    return TradingSignalValidationIssue.sellTargets;
  }
  if (!isSell &&
      targetPrices.any((value) => value.compareTo(entryPrice) <= 0)) {
    return TradingSignalValidationIssue.buyTargets;
  }
  for (var index = 1; index < targetPrices.length; index++) {
    if (isSell && targetPrices[index].compareTo(targetPrices[index - 1]) >= 0 ||
        !isSell &&
            targetPrices[index].compareTo(targetPrices[index - 1]) <= 0) {
      return TradingSignalValidationIssue.targetOrder;
    }
  }
  return null;
}
