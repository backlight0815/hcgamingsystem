import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';

void main() {
  test(
    'website journal with optional nulls decodes and retains price precision',
    () {
      final entry = TradingJournalEntry.fromJson({
        'id': 'website_25',
        'user_id': 'firebase-owner',
        'status': 'closed',
        'open_date': '2026-10-01T08:00:00',
        'close_date': '2026-10-01T09:00:00',
        'pair': 'EURUSD',
        'direction': 1,
        'entry_price': '1.12345678',
        'exit_price': '1.12355678',
        'lot_size': '0.125',
        'pips': '1.00',
        'profit_loss': '12.34',
        'result': 1,
        'notes': null,
        'strategy_name': null,
      });

      expect(entry.entryPrice, 1.12345678);
      expect(entry.lotSize, 0.125);
      expect(entry.notes, '');
      expect(entry.strategyName, isNull);
      expect(entry.toApiJson()['profit_loss'], 12.34);
      expect(entry.toApiJson()['time_input_offset_minutes'], 480);
    },
  );

  test('open trade leaves close values absent and remains safe for UI', () {
    final entry = TradingJournalEntry.fromJson({
      'open_date': '2026-10-01T08:00:00',
      'status': 'open',
      'pair': 'XAUUSD',
      'direction': 2,
      'entry_price': '2350.50',
      'lot_size': '0.50',
      'close_date': null,
      'result': null,
    });

    expect(entry.status, 'open');
    expect(entry.toApiJson()['close_date'], isNull);
    expect(entry.toApiJson()['profit_loss'], isNull);
  });
}
