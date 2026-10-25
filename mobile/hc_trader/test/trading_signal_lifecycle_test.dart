import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/localized_value.dart';
import 'package:hc_trader/models/trading_signal_lifecycle.dart';

SignalLifecycleSnapshot signal({
  String status = 'active',
  String direction = 'BUY',
  String stopLoss = '90',
  List<int> targetsHit = const [],
  int version = 4,
}) => SignalLifecycleSnapshot.fromMap({
  'lifecycle_status': status,
  'immediate_action': direction,
  'order_type': 'market_execution',
  'entry_price': '100',
  'current_stop_loss': stopLoss,
  'target_1': '110',
  'target_2': '120',
  'targets_hit': targetsHit,
  'percentage_remaining': 100,
  'version': version,
});

SignalUpdateDraft draft(
  SignalUpdateType type, {
  int version = 4,
  String reason = 'Risk-management update',
  String? stopLoss,
  int? targetNumber,
  SignalPercentage? remaining,
  Map<String, String> message = const {},
  bool correction = false,
}) => SignalUpdateDraft(
  idempotencyKey: 'signal-1-v$version-${type.value}',
  updateType: type,
  expectedVersion: version,
  reason: reason,
  newStopLoss: stopLoss,
  targetNumber: targetNumber,
  percentageRemaining: remaining,
  messageI18n: message,
  isCorrection: correction,
);

void main() {
  group('SignalLifecyclePolicy', () {
    test('validates pending order direction and decimal-safe publication', () {
      expect(
        () => SignalLifecyclePolicy.validatePublication(
          direction: 'BUY',
          orderType: SignalOrderType.sellLimit,
          entryPrice: '1.0000000000000001',
          stopLoss: '0.9999999999999999',
          targets: const ['1.0000000000000002'],
        ),
        throwsA(isA<SignalLifecycleException>()),
      );

      expect(
        () => SignalLifecyclePolicy.validatePublication(
          direction: 'BUY',
          orderType: SignalOrderType.buyLimit,
          entryPrice: '1.0000000000000001',
          stopLoss: '0.9999999999999999',
          targets: const ['1.0000000000000002'],
        ),
        returnsNormally,
      );
    });

    test('moves a pending order to active when entry triggers', () {
      final current = signal(status: 'pending_entry');
      final projection = SignalLifecyclePolicy.project(
        current,
        draft(SignalUpdateType.entryTriggered, reason: ''),
        superAdmin: false,
      );

      expect(projection.from, SignalLifecycleStatus.pendingEntry);
      expect(projection.to, SignalLifecycleStatus.active);
      expect(projection.signalChanges['version'], 5);
    });

    test('accepts a BUY stop loss that reduces risk', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        draft(SignalUpdateType.updateStopLoss, stopLoss: '95.00000'),
        superAdmin: false,
      );

      expect(projection.to, SignalLifecycleStatus.active);
      expect(projection.signalChanges['current_stop_loss'], '95');
    });

    test('rejects a BUY stop loss that increases risk', () {
      expect(
        () => SignalLifecyclePolicy.project(
          signal(),
          draft(SignalUpdateType.updateStopLoss, stopLoss: '85'),
          superAdmin: false,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
    });

    test('rejects a SELL stop loss that increases risk', () {
      expect(
        () => SignalLifecyclePolicy.project(
          signal(direction: 'SELL', stopLoss: '110'),
          draft(SignalUpdateType.updateStopLoss, stopLoss: '115'),
          superAdmin: false,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
    });

    test('allows an explicit Super Admin risk correction', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        draft(
          SignalUpdateType.updateStopLoss,
          stopLoss: '85',
          correction: true,
        ),
        superAdmin: true,
      );

      expect(projection.signalChanges['current_stop_loss'], '85');
    });

    test('moves stop loss to break even without decimal precision loss', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        SignalUpdateDraft(
          idempotencyKey: 'signal-1-be-v4',
          updateType: SignalUpdateType.moveStopLossToBreakEven,
          expectedVersion: 4,
          reason: 'Protect the position',
          breakEvenPrice: '100.00000001',
        ),
        superAdmin: false,
      );

      expect(projection.to, SignalLifecycleStatus.breakEven);
      expect(projection.signalChanges['current_stop_loss'], '100.00000001');
      expect(projection.signalChanges['IsSetBE'], 1);
    });

    test('calculates remaining position from a partial close', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        SignalUpdateDraft(
          idempotencyKey: 'signal-1-partial-v4',
          updateType: SignalUpdateType.partialClose,
          expectedVersion: 4,
          reason: 'Bank partial profit',
          percentageClosed: SignalPercentage.parse('25.25'),
        ),
        superAdmin: false,
      );

      expect(projection.to, SignalLifecycleStatus.partiallyClosed);
      expect(projection.signalChanges['percentage_remaining'], 74.75);
    });

    test('enforces sequential take-profit progression', () {
      expect(
        () => SignalLifecyclePolicy.project(
          signal(),
          draft(SignalUpdateType.targetHit, targetNumber: 2, reason: ''),
          superAdmin: false,
        ),
        throwsA(isA<SignalLifecycleException>()),
      );
    });

    test('records TP1 and preserves a partial position', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        draft(
          SignalUpdateType.targetHit,
          targetNumber: 1,
          remaining: SignalPercentage.parse('50'),
          reason: '',
        ),
        superAdmin: false,
      );

      expect(projection.to, SignalLifecycleStatus.partiallyClosed);
      expect(projection.signalChanges['targets_hit'], [1]);
      expect(projection.signalChanges['percentage_remaining'], 50);
    });

    test('completes after the final target', () {
      final projection = SignalLifecyclePolicy.project(
        signal(targetsHit: [1]),
        draft(
          SignalUpdateType.targetHit,
          targetNumber: 2,
          remaining: SignalPercentage.parse('0'),
          reason: '',
        ),
        superAdmin: false,
      );

      expect(projection.to, SignalLifecycleStatus.completed);
      expect(projection.signalChanges['is_done'], 1);
    });

    test('detects an optimistic concurrency conflict', () {
      expect(
        () => SignalLifecyclePolicy.project(
          signal(version: 8),
          draft(SignalUpdateType.commentary, message: const {'en': 'Wait'}),
          superAdmin: false,
        ),
        throwsA(isA<SignalConflictException>()),
      );
    });

    test('accepts one or two localized content languages', () {
      final projection = SignalLifecyclePolicy.project(
        signal(),
        draft(SignalUpdateType.commentary, message: const {'zh': '等待确认'}),
        superAdmin: false,
      );
      expect(projection.to, SignalLifecycleStatus.active);

      final bilingual = SignalLifecyclePolicy.project(
        signal(),
        draft(
          SignalUpdateType.commentary,
          message: const {'en': 'Wait', 'zh': '等待'},
        ),
        superAdmin: false,
      );
      expect(bilingual.to, SignalLifecycleStatus.active);
    });

    test('only terminal signals expose normal archive action', () {
      final actions = SignalLifecyclePolicy.allowedActions(
        signal(status: 'completed'),
        superAdmin: true,
      );

      expect(actions, contains(SignalUpdateType.archived));
      expect(actions, isNot(contains(SignalUpdateType.updateStopLoss)));
    });

    test('falls back to the available localization', () {
      expect(
        localizedValue(const {'en': 'Wait for confirmation'}, 'zh'),
        'Wait for confirmation',
      );
      expect(localizedValue(const {'zh': '等待确认'}, 'en'), '等待确认');
    });
  });
}
