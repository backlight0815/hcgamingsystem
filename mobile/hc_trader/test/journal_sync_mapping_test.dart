import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/services/journal_sync_mapping.dart';

void main() {
  test('missing numbers remain omitted; explicit nulls remain null', () {
    final values = journalSyncFields({'pair': 'EURUSD', 'exit_price': null});
    expect(values.containsKey('entry_price'), isFalse);
    expect(values['exit_price'], isNull);
    expect(values.containsKey('fees'), isFalse);
    expect(journalRemoteFields({'pips': null})['pips'], isNull);
  });
  test('website account ID never overwrites the unrelated mobile ID', () {
    expect(journalRemoteFields({'trading_account_id': 99}), isEmpty);
    expect(
      journalRemoteFields({
        'trading_account_id': 99,
        'mobile_trading_account_id': 'firestore-account',
      })['trading_account_id'],
      'firestore-account',
    );
    expect(journalSyncFields({'trading_account_id': 'firestore-account'}), {
      'mobile_trading_account_id': 'firestore-account',
    });
  });
  test(
    'dates and decimal responses compare consistently without losing nulls',
    () {
      final source = {
        'open_date': Timestamp.fromDate(DateTime.utc(2026, 10, 1)),
        'entry_price': 1.23456789,
      };
      final downloaded = journalRemoteFields({
        'open_date': '2026-10-01T00:00:00.000000Z',
        'entry_price': '1.23456789',
      });
      expect(
        journalSyncEqual(
          journalSyncFields(source),
          journalSyncFields(downloaded),
        ),
        isTrue,
      );
      expect(journalSyncEqual(0, null), isFalse);
      expect(
        journalSyncEqual({'a': true, 'b': false}, {'b': false, 'a': true}),
        isTrue,
      );
    },
  );
}
