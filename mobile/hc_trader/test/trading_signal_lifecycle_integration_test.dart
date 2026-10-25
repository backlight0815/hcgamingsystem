import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/mt5_import_models.dart';
import 'package:hc_trader/models/trading_signal_lifecycle.dart';

SignalLifecycleSnapshot signalState({
  required SignalLifecycleStatus status,
  String direction = 'BUY',
  SignalOrderType orderType = SignalOrderType.buyLimit,
  String entry = '2300',
  String stop = '2290',
  List<String> targets = const ['2310', '2320'],
  Set<int> targetsHit = const {},
  SignalPercentage remaining = const SignalPercentage.full(),
  int version = 0,
}) => SignalLifecycleSnapshot(
  status: status,
  direction: direction,
  orderType: orderType,
  entryPrice: Mt5Decimal.parse(entry),
  currentStopLoss: Mt5Decimal.parse(stop),
  targets: targets.map(Mt5Decimal.parse).toList(growable: false),
  targetsHit: targetsHit,
  percentageRemaining: remaining,
  version: version,
);

SignalUpdateDraft update({
  required SignalUpdateType type,
  required int version,
  String reason = '',
  String? stop,
  String? breakEven,
  int? target,
  SignalPercentage? closed,
  SignalPercentage? remaining,
}) => SignalUpdateDraft(
  idempotencyKey: 'flow-$version-${type.value}',
  updateType: type,
  expectedVersion: version,
  reason: reason,
  newStopLoss: stop,
  breakEvenPrice: breakEven,
  targetNumber: target,
  targetPrice: target == null
      ? null
      : target == 1
      ? '2310'
      : '2320',
  percentageClosed: closed,
  percentageRemaining: remaining,
);

void main() {
  group('complete trading signal flows', () {
    test('buy limit progresses pending entry through BE and all targets', () {
      SignalLifecyclePolicy.validatePublication(
        direction: 'BUY',
        orderType: SignalOrderType.buyLimit,
        entryPrice: '2300.00000',
        stopLoss: '2290.00000',
        targets: const ['2310.00000', '2320.00000'],
      );
      var state = signalState(status: SignalLifecycleStatus.pendingEntry);

      var result = SignalLifecyclePolicy.project(
        state,
        update(type: SignalUpdateType.entryTriggered, version: 0),
        superAdmin: false,
      );
      expect(result.to, SignalLifecycleStatus.active);

      state = signalState(status: result.to, version: 1);
      result = SignalLifecyclePolicy.project(
        state,
        update(
          type: SignalUpdateType.moveStopLossToBreakEven,
          version: 1,
          reason: 'Protect the position',
          breakEven: '2300.00000',
        ),
        superAdmin: false,
      );
      expect(result.to, SignalLifecycleStatus.breakEven);
      expect(result.signalChanges['current_stop_loss'], '2300');

      state = signalState(status: result.to, stop: '2300', version: 2);
      result = SignalLifecyclePolicy.project(
        state,
        update(
          type: SignalUpdateType.targetHit,
          version: 2,
          target: 1,
          remaining: SignalPercentage.parse('50'),
        ),
        superAdmin: false,
      );
      expect(result.to, SignalLifecycleStatus.partiallyClosed);
      expect(result.signalChanges['targets_hit'], [1]);

      state = signalState(
        status: result.to,
        stop: '2300',
        targetsHit: const {1},
        remaining: SignalPercentage.parse('50'),
        version: 3,
      );
      result = SignalLifecyclePolicy.project(
        state,
        update(
          type: SignalUpdateType.targetHit,
          version: 3,
          target: 2,
          remaining: SignalPercentage.parse('0'),
        ),
        superAdmin: false,
      );
      expect(result.to, SignalLifecycleStatus.completed);
      expect(result.signalChanges['is_done'], 1);
    });

    test('sell stop-loss flow blocks later target updates', () {
      SignalLifecyclePolicy.validatePublication(
        direction: 'SELL',
        orderType: SignalOrderType.marketExecution,
        entryPrice: '2300',
        stopLoss: '2320',
        targets: const ['2280', '2260'],
      );
      var state = signalState(
        status: SignalLifecycleStatus.active,
        direction: 'SELL',
        orderType: SignalOrderType.marketExecution,
        stop: '2320',
        targets: const ['2280', '2260'],
      );
      var result = SignalLifecyclePolicy.project(
        state,
        update(
          type: SignalUpdateType.updateStopLoss,
          version: 0,
          stop: '2310',
          reason: 'Reduce open risk',
        ),
        superAdmin: true,
      );
      expect(result.signalChanges['current_stop_loss'], '2310');

      state = signalState(
        status: result.to,
        direction: 'SELL',
        orderType: SignalOrderType.marketExecution,
        stop: '2310',
        targets: const ['2280', '2260'],
        version: 1,
      );
      result = SignalLifecyclePolicy.project(
        state,
        update(type: SignalUpdateType.stopLossHit, version: 1),
        superAdmin: true,
      );
      expect(result.to, SignalLifecycleStatus.stopLossHit);

      final closed = signalState(
        status: result.to,
        direction: 'SELL',
        orderType: SignalOrderType.marketExecution,
        stop: '2310',
        targets: const ['2280', '2260'],
        version: 2,
      );
      expect(
        () => SignalLifecyclePolicy.project(
          closed,
          update(type: SignalUpdateType.targetHit, version: 2, target: 1),
          superAdmin: true,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
    });

    test('cancelled pending order cannot be activated or hit a target', () {
      final pending = signalState(status: SignalLifecycleStatus.pendingEntry);
      final cancelled = SignalLifecyclePolicy.project(
        pending,
        update(
          type: SignalUpdateType.cancelled,
          version: 0,
          reason: 'Setup invalidated before entry',
        ),
        superAdmin: false,
      );
      expect(cancelled.to, SignalLifecycleStatus.cancelled);

      final closed = signalState(status: cancelled.to, version: 1);
      for (final type in [
        SignalUpdateType.entryTriggered,
        SignalUpdateType.targetHit,
      ]) {
        expect(
          () => SignalLifecyclePolicy.project(
            closed,
            update(
              type: type,
              version: 1,
              target: type == SignalUpdateType.targetHit ? 1 : null,
            ),
            superAdmin: false,
          ),
          throwsA(isA<SignalLifecycleException>()),
        );
      }
    });

    test('stale administrator update conflicts and cannot overwrite', () {
      final current = signalState(
        status: SignalLifecycleStatus.active,
        version: 7,
      );
      expect(
        () => SignalLifecyclePolicy.project(
          current,
          update(
            type: SignalUpdateType.updateStopLoss,
            version: 6,
            stop: '2295',
            reason: 'Stale update',
          ),
          superAdmin: true,
        ),
        throwsA(
          isA<SignalConflictException>()
              .having((error) => error.expectedVersion, 'expected', 6)
              .having((error) => error.actualVersion, 'actual', 7),
        ),
      );
    });

    test('position percentage cannot increase or over-close', () {
      final state = signalState(
        status: SignalLifecycleStatus.partiallyClosed,
        remaining: SignalPercentage.parse('40'),
        version: 4,
      );
      expect(
        () => SignalLifecyclePolicy.project(
          state,
          update(
            type: SignalUpdateType.partialClose,
            version: 4,
            reason: 'Invalid size',
            closed: SignalPercentage.parse('50'),
          ),
          superAdmin: false,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
      expect(
        () => SignalLifecyclePolicy.project(
          state,
          update(
            type: SignalUpdateType.targetHit,
            version: 4,
            target: 1,
            remaining: SignalPercentage.parse('60'),
          ),
          superAdmin: false,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
    });
  });
}
