import '../../../../models/mt5_import_models.dart';
import 'average_price_models.dart';

/// Pure, fixed-precision calculation engine for the Average Price tool.
///
/// The configured cost model is linear in exit price, so break-even and the
/// fee-adjusted target can be solved directly without an iterative solver.
class AveragePricePositionEngine {
  const AveragePricePositionEngine(this.request);

  final AveragePriceCalculationRequest request;

  AveragePriceCalculationResult calculate() {
    final warnings = <AveragePriceWarningCode>{};
    final validEntries = <PositionEntry>[];
    for (final entry in request.entries) {
      if (!entry.entryPrice.isPositive) {
        warnings.add(AveragePriceWarningCode.invalidPrice);
      }
      if (!entry.quantity.isPositive) {
        warnings.add(AveragePriceWarningCode.invalidQuantity);
      }
      if (entry.entryPrice.isPositive && entry.quantity.isPositive) {
        validEntries.add(entry);
      }
    }
    if (validEntries.isEmpty) {
      warnings.add(AveragePriceWarningCode.noValidOrders);
      return AveragePriceCalculationResult.empty(warnings: warnings);
    }

    if (_invalidCommission(request.costs.entryCommissionPercent) ||
        _invalidCommission(request.costs.exitCommissionPercent)) {
      warnings.add(AveragePriceWarningCode.invalidCommission);
    }
    if (request.advancedMode && !request.instrument.hasMonetarySpecification) {
      warnings.add(AveragePriceWarningCode.invalidContractSpecification);
    }

    final totalQuantity = _sum(validEntries.map((entry) => entry.quantity));
    final totalEntryValue = _sum(
      validEntries.map((entry) => entry.entryPrice * entry.quantity),
    );
    if (!totalQuantity.isPositive) {
      warnings.add(AveragePriceWarningCode.noValidOrders);
      return AveragePriceCalculationResult.empty(warnings: warnings);
    }
    final average = totalEntryValue.divide(totalQuantity, resultScale: 14);
    final feeBreakEven = _breakEvenDecimal(
      validEntries,
      totalQuantity,
      totalEntryValue,
      average,
    );
    final safeBreakEven = _safeBreakEven(feeBreakEven, warnings);
    final monetaryAvailable =
        request.advancedMode && request.instrument.hasMonetarySpecification;
    final contractSize = request.instrument.contractSizeValue;
    final exposure = contractSize.isPositive
        ? totalEntryValue * contractSize
        : null;

    final stopLoss = request.stopLoss;
    final riskDistance = stopLoss == null
        ? null
        : _riskDistance(average, stopLoss, warnings);
    final primaryTarget =
        request.primaryTakeProfit ??
        request.takeProfits
            .where((target) => target.targetPrice.isPositive)
            .firstOrNull
            ?.targetPrice;
    final rewardDistance = primaryTarget == null
        ? null
        : _rewardDistance(average, primaryTarget, warnings);
    final grossRiskReward =
        riskDistance != null &&
            riskDistance.isPositive &&
            rewardDistance != null &&
            rewardDistance.isPositive
        ? rewardDistance.divide(riskDistance, resultScale: 10)
        : null;

    final allocated = _sum(
      request.takeProfits.map((target) => target.closePercentage),
    );
    if (allocated.compareTo(_hundred) > 0) {
      warnings.add(AveragePriceWarningCode.allocationAboveHundred);
    } else if (request.takeProfits.isNotEmpty &&
        allocated.compareTo(_hundred) < 0) {
      warnings.add(AveragePriceWarningCode.allocationBelowHundred);
    }
    final remaining = (_hundred - allocated).compareTo(Mt5Decimal.zero) < 0
        ? Mt5Decimal.zero
        : _hundred - allocated;

    final targetResults = <TakeProfitCalculationResult>[];
    var blendedGross = Mt5Decimal.zero;
    var blendedNet = Mt5Decimal.zero;
    for (final target in request.takeProfits) {
      if (target.closePercentage.isNegative) {
        warnings.add(AveragePriceWarningCode.invalidQuantity);
      }
      final distance = _directionalDistance(average, target.targetPrice);
      if (target.targetPrice.isPositive && !distance.isPositive) {
        warnings.add(
          distance.isZero
              ? AveragePriceWarningCode.takeProfitAtAverage
              : AveragePriceWarningCode.takeProfitDirection,
        );
      }
      final percentageDistance = average.isZero
          ? Mt5Decimal.zero
          : distance.divide(average.abs, resultScale: 12).multiplyByHundred();
      final allocation = target.closePercentage.abs.divide(
        _hundred,
        resultScale: 12,
      );
      final gross = monetaryAvailable
          ? _grossPnl(target.targetPrice, average, totalQuantity, allocation)
          : null;
      final net = monetaryAvailable
          ? _netPnl(
              target.targetPrice,
              validEntries,
              totalQuantity,
              totalEntryValue,
              average,
              allocation,
            )
          : null;
      if (gross != null) blendedGross += gross;
      if (net != null) blendedNet += net;
      targetResults.add(
        TakeProfitCalculationResult(
          target: target,
          distance: distance,
          percentageDistance: percentageDistance,
          grossRiskReward:
              riskDistance != null &&
                  riskDistance.isPositive &&
                  distance.isPositive
              ? distance.divide(riskDistance, resultScale: 10)
              : null,
          grossProfit: gross,
          netProfit: net,
        ),
      );
    }

    final profitAtTarget = monetaryAvailable && primaryTarget != null
        ? _netPnl(
            primaryTarget,
            validEntries,
            totalQuantity,
            totalEntryValue,
            average,
            Mt5Decimal.one,
          )
        : null;
    final lossAtStop = monetaryAvailable && stopLoss != null
        ? _netPnl(
            stopLoss,
            validEntries,
            totalQuantity,
            totalEntryValue,
            average,
            Mt5Decimal.one,
          )
        : null;
    final netRiskReward =
        profitAtTarget != null &&
            profitAtTarget.isPositive &&
            lossAtStop != null &&
            lossAtStop.isNegative
        ? profitAtTarget.divide(lossAtStop.abs, resultScale: 10)
        : null;
    final requiredTarget = _requiredTakeProfit(
      validEntries,
      totalQuantity,
      totalEntryValue,
      average,
      riskDistance,
      lossAtStop,
      monetaryAvailable,
    );

    final currentPrice = request.instrument.currentMarketPriceValue;
    final currentNetPnl = monetaryAvailable && currentPrice != null
        ? _netPnl(
            currentPrice,
            validEntries,
            totalQuantity,
            totalEntryValue,
            average,
            Mt5Decimal.one,
          )
        : null;
    final distanceToBreakEven = currentPrice == null
        ? null
        : _directionalDistance(feeBreakEven, currentPrice);

    return AveragePriceCalculationResult(
      totalQuantity: totalQuantity,
      totalEntryValue: totalEntryValue,
      weightedAverageEntry: average,
      grossBreakEven: average,
      feeAdjustedBreakEven: feeBreakEven,
      safeBreakEven: safeBreakEven,
      totalExposure: exposure,
      currentNetPnl: currentNetPnl,
      estimatedProfitAtTarget: profitAtTarget,
      estimatedLossAtStop: lossAtStop,
      riskDistance: riskDistance,
      rewardDistance: rewardDistance,
      grossRiskReward: grossRiskReward,
      netRiskReward: netRiskReward,
      requiredTakeProfit: requiredTarget,
      distanceToBreakEven: distanceToBreakEven,
      takeProfitResults: targetResults,
      blendedGrossProfit: monetaryAvailable ? blendedGross : null,
      blendedNetProfit: monetaryAvailable ? blendedNet : null,
      allocatedPercentage: allocated,
      remainingPercentage: remaining,
      status: _status(
        average,
        feeBreakEven,
        safeBreakEven,
        currentPrice,
        primaryTarget,
        warnings,
      ),
      warnings: warnings,
      monetaryEstimatesAvailable: monetaryAvailable,
      validOrderCount: validEntries.length,
    );
  }

  /// Public boundary used by integrations that need a numeric P/L quote.
  /// Invalid or incomplete setups return zero, never NaN or Infinity.
  double calculateNetPnlAtPrice(double exitPrice) {
    if (!exitPrice.isFinite) return 0;
    final data = _positionData();
    if (data == null) return 0;
    return _netPnl(
      Mt5Decimal.parse(exitPrice.toString()),
      data.entries,
      data.quantity,
      data.entryValue,
      data.average,
      Mt5Decimal.one,
    ).toDouble();
  }

  /// Returns zero for an invalid setup, never NaN or Infinity.
  double calculateBreakEvenPrice() {
    final data = _positionData();
    if (data == null) return 0;
    return _breakEvenDecimal(
      data.entries,
      data.quantity,
      data.entryValue,
      data.average,
    ).toDouble();
  }

  _PositionData? _positionData() {
    final entries = request.entries
        .where(
          (entry) => entry.entryPrice.isPositive && entry.quantity.isPositive,
        )
        .toList(growable: false);
    if (entries.isEmpty) return null;
    final quantity = _sum(entries.map((entry) => entry.quantity));
    if (!quantity.isPositive) return null;
    final entryValue = _sum(
      entries.map((entry) => entry.entryPrice * entry.quantity),
    );
    return _PositionData(
      entries: entries,
      quantity: quantity,
      entryValue: entryValue,
      average: entryValue.divide(quantity, resultScale: 14),
    );
  }

  Mt5Decimal _breakEvenDecimal(
    List<PositionEntry> entries,
    Mt5Decimal quantity,
    Mt5Decimal entryValue,
    Mt5Decimal average,
  ) {
    final sign = request.direction == PositionDirection.long
        ? Mt5Decimal.one
        : -Mt5Decimal.one;
    final priceFactor = _priceValueFactor();
    final notionalFactor = _notionalFactor();
    final grossCoefficient = sign * priceFactor * quantity;
    final exitCoefficient =
        request.costs.exitCommissionRate * notionalFactor * quantity;
    final denominator = grossCoefficient - exitCoefficient;
    if (denominator.isZero) return average;
    final numerator =
        grossCoefficient * average +
        _constantCosts(entries, quantity, entryValue);
    final result = numerator.divide(denominator, resultScale: 14);
    return result.isPositive ? result : average;
  }

  Mt5Decimal _netPnl(
    Mt5Decimal exitPrice,
    List<PositionEntry> entries,
    Mt5Decimal totalQuantity,
    Mt5Decimal totalEntryValue,
    Mt5Decimal average,
    Mt5Decimal allocation,
  ) {
    final gross = _grossPnl(exitPrice, average, totalQuantity, allocation);
    final exitCommission =
        exitPrice *
        totalQuantity *
        _notionalFactor() *
        request.costs.exitCommissionRate *
        allocation;
    final costs =
        _constantCosts(entries, totalQuantity, totalEntryValue) * allocation;
    return gross - exitCommission - costs;
  }

  Mt5Decimal _grossPnl(
    Mt5Decimal exitPrice,
    Mt5Decimal average,
    Mt5Decimal totalQuantity,
    Mt5Decimal allocation,
  ) {
    final movement = request.direction == PositionDirection.long
        ? exitPrice - average
        : average - exitPrice;
    return movement * _priceValueFactor() * totalQuantity * allocation;
  }

  Mt5Decimal _constantCosts(
    List<PositionEntry> entries,
    Mt5Decimal quantity,
    Mt5Decimal entryValue,
  ) {
    final perOrderFees = _sum(
      entries.map((entry) => entry.entryFee?.abs ?? Mt5Decimal.zero),
    );
    final entryCommission =
        entryValue * _notionalFactor() * request.costs.entryCommissionRate;
    final executionCosts =
        (request.costs.spreadValue + request.costs.slippageValue) *
        _priceValueFactor() *
        quantity;
    return perOrderFees +
        entryCommission +
        executionCosts +
        request.costs.swapCostValue +
        request.costs.otherCostsValue;
  }

  Mt5Decimal _priceValueFactor() {
    final tickSize = request.instrument.tickSizeValue;
    final tickValue = request.instrument.tickValueValue;
    if (tickSize != null &&
        tickSize.isPositive &&
        tickValue != null &&
        tickValue.isPositive) {
      return tickValue.divide(tickSize, resultScale: 14);
    }
    final contractSize = request.instrument.contractSizeValue;
    return contractSize.isPositive ? contractSize : Mt5Decimal.one;
  }

  Mt5Decimal _notionalFactor() {
    final contractSize = request.instrument.contractSizeValue;
    return contractSize.isPositive ? contractSize : Mt5Decimal.one;
  }

  Mt5Decimal _safeBreakEven(
    Mt5Decimal feeBreakEven,
    Set<AveragePriceWarningCode> warnings,
  ) {
    final value = Mt5Decimal.tryParse(request.safetyBuffer.value).abs;
    final buffer = switch (request.safetyBuffer.type) {
      SafetyBufferType.points => value,
      SafetyBufferType.percentage =>
        feeBreakEven.abs * value.divide(_hundred, resultScale: 14),
      SafetyBufferType.pips => _sizedBuffer(
        value,
        request.instrument.pipSizeValue,
        warnings,
      ),
      SafetyBufferType.ticks => _sizedBuffer(
        value,
        request.instrument.tickSizeValue,
        warnings,
      ),
    };
    return request.direction == PositionDirection.long
        ? feeBreakEven + buffer
        : feeBreakEven - buffer;
  }

  Mt5Decimal _sizedBuffer(
    Mt5Decimal value,
    Mt5Decimal? size,
    Set<AveragePriceWarningCode> warnings,
  ) {
    if (size == null || !size.isPositive) {
      warnings.add(AveragePriceWarningCode.invalidContractSpecification);
      return Mt5Decimal.zero;
    }
    return value * size;
  }

  Mt5Decimal? _riskDistance(
    Mt5Decimal average,
    Mt5Decimal stopLoss,
    Set<AveragePriceWarningCode> warnings,
  ) {
    final distance = request.direction == PositionDirection.long
        ? average - stopLoss
        : stopLoss - average;
    if (!distance.isPositive) {
      warnings.add(
        distance.isZero
            ? AveragePriceWarningCode.stopLossAtAverage
            : AveragePriceWarningCode.stopLossDirection,
      );
    }
    return distance;
  }

  Mt5Decimal? _rewardDistance(
    Mt5Decimal average,
    Mt5Decimal takeProfit,
    Set<AveragePriceWarningCode> warnings,
  ) {
    final distance = _directionalDistance(average, takeProfit);
    if (!distance.isPositive) {
      warnings.add(
        distance.isZero
            ? AveragePriceWarningCode.takeProfitAtAverage
            : AveragePriceWarningCode.takeProfitDirection,
      );
    }
    return distance;
  }

  Mt5Decimal _directionalDistance(Mt5Decimal from, Mt5Decimal to) =>
      request.direction == PositionDirection.long ? to - from : from - to;

  Mt5Decimal? _requiredTakeProfit(
    List<PositionEntry> entries,
    Mt5Decimal quantity,
    Mt5Decimal entryValue,
    Mt5Decimal average,
    Mt5Decimal? riskDistance,
    Mt5Decimal? lossAtStop,
    bool monetaryAvailable,
  ) {
    final targetRatio = request.targetRiskReward;
    if (targetRatio == null ||
        !targetRatio.isPositive ||
        riskDistance == null ||
        !riskDistance.isPositive) {
      return null;
    }
    if (!monetaryAvailable || lossAtStop == null) {
      final movement = targetRatio * riskDistance;
      return request.direction == PositionDirection.long
          ? average + movement
          : average - movement;
    }
    final desiredNetProfit = lossAtStop.abs * targetRatio;
    final sign = request.direction == PositionDirection.long
        ? Mt5Decimal.one
        : -Mt5Decimal.one;
    final grossCoefficient = sign * _priceValueFactor() * quantity;
    final exitCoefficient =
        request.costs.exitCommissionRate * _notionalFactor() * quantity;
    final denominator = grossCoefficient - exitCoefficient;
    if (denominator.isZero) return null;
    final numerator =
        desiredNetProfit +
        grossCoefficient * average +
        _constantCosts(entries, quantity, entryValue);
    final result = numerator.divide(denominator, resultScale: 14);
    return result.isPositive ? result : null;
  }

  BreakEvenStatus _status(
    Mt5Decimal grossBreakEven,
    Mt5Decimal feeBreakEven,
    Mt5Decimal safeBreakEven,
    Mt5Decimal? currentPrice,
    Mt5Decimal? primaryTarget,
    Set<AveragePriceWarningCode> warnings,
  ) {
    if (warnings.contains(AveragePriceWarningCode.noValidOrders)) {
      return BreakEvenStatus.invalidSetup;
    }
    if (currentPrice == null || !currentPrice.isPositive) {
      return BreakEvenStatus.unavailable;
    }
    if (primaryTarget != null &&
        _directionalDistance(
              primaryTarget,
              currentPrice,
            ).compareTo(Mt5Decimal.zero) >=
            0) {
      return BreakEvenStatus.targetReached;
    }
    if (_directionalDistance(
          safeBreakEven,
          currentPrice,
        ).compareTo(Mt5Decimal.zero) >=
        0) {
      return BreakEvenStatus.aboveSafeBreakEven;
    }
    if (_directionalDistance(
          feeBreakEven,
          currentPrice,
        ).compareTo(Mt5Decimal.zero) >=
        0) {
      return BreakEvenStatus.aboveFeeAdjustedBreakEven;
    }
    final grossDistance = _directionalDistance(grossBreakEven, currentPrice);
    final precision = request.instrument.pricePrecision.clamp(0, 8);
    final tolerance = Mt5Decimal.parse(
      precision == 0 ? '1' : '0.${'0' * (precision - 1)}1',
    );
    if (grossDistance.abs.compareTo(tolerance) <= 0) {
      return BreakEvenStatus.atGrossBreakEven;
    }
    final approachThreshold = grossBreakEven.abs * Mt5Decimal.parse('0.001');
    if (grossDistance.isNegative &&
        grossDistance.abs.compareTo(approachThreshold) <= 0) {
      return BreakEvenStatus.approachingBreakEven;
    }
    return BreakEvenStatus.belowBreakEven;
  }

  bool _invalidCommission(String source) {
    final value = Mt5Decimal.tryParse(source);
    return value.isNegative || value.compareTo(_hundred) >= 0;
  }

  static Mt5Decimal _sum(Iterable<Mt5Decimal> values) =>
      values.fold(Mt5Decimal.zero, (total, value) => total + value);

  static final _hundred = Mt5Decimal.parse('100');
}

class _PositionData {
  const _PositionData({
    required this.entries,
    required this.quantity,
    required this.entryValue,
    required this.average,
  });

  final List<PositionEntry> entries;
  final Mt5Decimal quantity;
  final Mt5Decimal entryValue;
  final Mt5Decimal average;
}

extension on Mt5Decimal {
  Mt5Decimal multiplyByHundred() => this * Mt5Decimal.parse('100');
}
