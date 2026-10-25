import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/features/tools/average_price/domain/average_price_calculator.dart';
import 'package:hc_trader/features/tools/average_price/domain/average_price_models.dart';
import 'package:hc_trader/models/mt5_import_models.dart';

void main() {
  group('AveragePricePositionEngine weighted entry', () {
    test('equal-sized long orders average to 4005', () {
      final result = _engine(
        entries: [_entry('4000', '1'), _entry('4010', '1')],
        takeProfit: '4030',
      ).calculate();

      expect(result.weightedAverageEntry, Mt5Decimal.parse('4005'));
      expect(result.grossBreakEven, Mt5Decimal.parse('4005'));
      expect(result.rewardDistance, Mt5Decimal.parse('25'));
      expect(result.totalQuantity, Mt5Decimal.parse('2'));
      expect(result.totalEntryValue, Mt5Decimal.parse('8010'));
    });

    test('unequal quantities use weighted rather than arithmetic average', () {
      final result = _engine(
        entries: [_entry('4000', '1'), _entry('4010', '2')],
      ).calculate();

      expect(
        result.weightedAverageEntry!.toDouble(),
        closeTo(4006.6666666667, 0.000000001),
      );
      expect(result.totalQuantity, Mt5Decimal.parse('3'));
    });

    test('single valid order remains its own average', () {
      final result = _engine(entries: [_entry('4000', '1')]).calculate();
      expect(result.weightedAverageEntry, Mt5Decimal.parse('4000'));
    });

    test('zero and negative quantities are excluded with a warning', () {
      final result = _engine(
        entries: [
          _entry('4000', '1'),
          _entry('9000', '0'),
          _entry('12000', '-2'),
        ],
      ).calculate();

      expect(result.weightedAverageEntry, Mt5Decimal.parse('4000'));
      expect(result.validOrderCount, 1);
      expect(
        result.warnings,
        contains(AveragePriceWarningCode.invalidQuantity),
      );
    });
  });

  group('direction, break-even and risk/reward', () {
    test('long risk/reward is 2.5 for 4005 / 3995 / 4030', () {
      final result = _engine(
        entries: [_entry('4000', '1'), _entry('4010', '1')],
        stopLoss: '3995',
        takeProfit: '4030',
        targetRiskReward: '2',
      ).calculate();

      expect(result.riskDistance, Mt5Decimal.parse('10'));
      expect(result.rewardDistance, Mt5Decimal.parse('25'));
      expect(result.grossRiskReward, Mt5Decimal.parse('2.5'));
      expect(result.requiredTakeProfit, Mt5Decimal.parse('4025'));
    });

    test('short reverses profit, validation and safe buffer direction', () {
      final engine = _engine(
        direction: PositionDirection.short,
        entries: [_entry('4000', '1'), _entry('4010', '1')],
        stopLoss: '4015',
        takeProfit: '3980',
        buffer: '2',
        advanced: true,
        contractSize: '1',
        costs: const TradingCostSettings(spread: '1'),
      );
      final result = engine.calculate();

      expect(result.weightedAverageEntry, Mt5Decimal.parse('4005'));
      expect(result.feeAdjustedBreakEven, Mt5Decimal.parse('4004'));
      expect(result.safeBreakEven, Mt5Decimal.parse('4002'));
      expect(engine.calculateNetPnlAtPrice(3980), greaterThan(0));
      expect(engine.calculateNetPnlAtPrice(4015), lessThan(0));
      expect(
        result.warnings,
        isNot(contains(AveragePriceWarningCode.stopLossDirection)),
      );
      expect(
        result.warnings,
        isNot(contains(AveragePriceWarningCode.takeProfitDirection)),
      );
    });

    test('directionally inconsistent SL and TP produce neutral warnings', () {
      final result = _engine(
        direction: PositionDirection.short,
        entries: [_entry('100', '1')],
        stopLoss: '90',
        takeProfit: '110',
      ).calculate();

      expect(
        result.warnings,
        contains(AveragePriceWarningCode.stopLossDirection),
      );
      expect(
        result.warnings,
        contains(AveragePriceWarningCode.takeProfitDirection),
      );
    });

    test('positive costs move break-even up for long and down for short', () {
      final longResult = _engine(
        entries: [_entry('100', '2')],
        advanced: true,
        contractSize: '1',
        costs: const TradingCostSettings(
          entryCommissionPercent: '0.1',
          exitCommissionPercent: '0.1',
          spread: '1',
          slippage: '.5',
          otherCosts: '1',
        ),
      ).calculate();
      final shortResult = _engine(
        direction: PositionDirection.short,
        entries: [_entry('100', '2')],
        advanced: true,
        contractSize: '1',
        costs: const TradingCostSettings(
          entryCommissionPercent: '0.1',
          exitCommissionPercent: '0.1',
          spread: '1',
          slippage: '.5',
          otherCosts: '1',
        ),
      ).calculate();

      expect(
        longResult.feeAdjustedBreakEven!.compareTo(longResult.grossBreakEven!),
        greaterThan(0),
      );
      expect(
        shortResult.feeAdjustedBreakEven!.compareTo(
          shortResult.grossBreakEven!,
        ),
        lessThan(0),
      );
    });
  });

  group('advanced targets and decimal safety', () {
    test('partial target allocation and blended profit are correct', () {
      final result = _engine(
        entries: [_entry('100', '10')],
        stopLoss: '95',
        advanced: true,
        contractSize: '1',
        targets: [_target('tp1', '110', '50'), _target('tp2', '120', '50')],
      ).calculate();

      expect(result.allocatedPercentage, Mt5Decimal.parse('100'));
      expect(result.remainingPercentage, Mt5Decimal.zero);
      expect(result.blendedGrossProfit, Mt5Decimal.parse('150'));
      expect(result.blendedNetProfit, Mt5Decimal.parse('150'));
      expect(
        result.takeProfitResults.first.grossProfit,
        Mt5Decimal.parse('50'),
      );
      expect(
        result.takeProfitResults.last.grossProfit,
        Mt5Decimal.parse('100'),
      );
    });

    test('allocation below and above 100% are reported without crashing', () {
      final below = _engine(
        entries: [_entry('10', '1')],
        targets: [_target('tp1', '11', '40')],
      ).calculate();
      final above = _engine(
        entries: [_entry('10', '1')],
        targets: [_target('tp1', '11', '70'), _target('tp2', '12', '50')],
      ).calculate();

      expect(
        below.warnings,
        contains(AveragePriceWarningCode.allocationBelowHundred),
      );
      expect(
        above.warnings,
        contains(AveragePriceWarningCode.allocationAboveHundred),
      );
      expect(above.remainingPercentage, Mt5Decimal.zero);
    });

    for (final testCase in const [
      ('stock', '182.17', '3', '183.02', '2'),
      ('forex', '1.08452', '0.4', '1.08477', '0.6'),
      ('gold', '2415.37', '1.25', '2416.02', '0.75'),
      ('crypto', '0.00001234', '150000', '0.00001255', '250000'),
    ]) {
      test('${testCase.$1} values preserve controlled decimal precision', () {
        final result = _engine(
          entries: [
            _entry(testCase.$2, testCase.$3),
            _entry(testCase.$4, testCase.$5),
          ],
        ).calculate();

        expect(result.weightedAverageEntry, isNotNull);
        expect(result.weightedAverageEntry!.toDouble().isFinite, isTrue);
        expect(result.weightedAverageEntry.toString(), isNot(contains('NaN')));
      });
    }

    test('locale commas and thousands separators are parsed safely', () {
      final result = _engine(
        entries: [_entry('4,000.50', '1'), _entry('4.010,50', '1')],
      ).calculate();
      expect(result.weightedAverageEntry, Mt5Decimal.parse('4005.5'));
    });

    test('empty and division-by-zero states never return NaN or Infinity', () {
      final empty = _engine(entries: const []).calculate();
      final zero = _engine(entries: [_entry('100', '0')]);

      expect(empty.weightedAverageEntry, isNull);
      expect(empty.totalQuantity, Mt5Decimal.zero);
      expect(zero.calculateBreakEvenPrice().isFinite, isTrue);
      expect(zero.calculateBreakEvenPrice(), 0);
      expect(zero.calculateNetPnlAtPrice(double.infinity), 0);
    });
  });
}

AveragePricePositionEngine _engine({
  PositionDirection direction = PositionDirection.long,
  required List<PositionEntry> entries,
  String? stopLoss,
  String? takeProfit,
  String? targetRiskReward,
  String buffer = '0',
  bool advanced = false,
  String contractSize = '',
  TradingCostSettings costs = const TradingCostSettings(),
  List<TakeProfitTarget>? targets,
}) {
  final takeProfits =
      targets ??
      (takeProfit == null
          ? const <TakeProfitTarget>[]
          : [_target('tp1', takeProfit, '100')]);
  return AveragePricePositionEngine(
    AveragePriceCalculationRequest(
      direction: direction,
      entries: entries,
      takeProfits: takeProfits,
      instrument: InstrumentSettings(
        pricePrecision: 8,
        contractSize: contractSize,
      ),
      costs: costs,
      safetyBuffer: SafetyBufferSettings(value: buffer),
      stopLoss: stopLoss == null ? null : Mt5Decimal.parse(stopLoss),
      primaryTakeProfit: takeProfit == null
          ? (takeProfits.isEmpty ? null : takeProfits.first.targetPrice)
          : Mt5Decimal.parse(takeProfit),
      targetRiskReward: targetRiskReward == null
          ? null
          : Mt5Decimal.parse(targetRiskReward),
      advancedMode: advanced,
    ),
  );
}

PositionEntry _entry(String price, String quantity) => PositionEntry(
  id: '$price-$quantity',
  entryPrice: Mt5Decimal.parse(price),
  quantity: Mt5Decimal.parse(quantity),
);

TakeProfitTarget _target(String id, String price, String percentage) =>
    TakeProfitTarget(
      id: id,
      targetPrice: Mt5Decimal.parse(price),
      closePercentage: Mt5Decimal.parse(percentage),
    );
