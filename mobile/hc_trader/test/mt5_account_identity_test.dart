import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/mt5_sync_models.dart';

void main() {
  group('MT5 account identity', () {
    test('uses the normalized owner and canonical account document id', () {
      final account = Mt5Account.fromJson({
        'id': 'Broker-A_10001',
        'ownerUid': 'user-a',
        'user_id': 'legacy-owner',
        'account_key': 'Broker-A_10001',
        'mt5_login': 10001,
        'broker_server': 'Broker-A',
      });

      expect(account.userId, 'user-a');
      expect(account.accountKey, 'Broker-A_10001');
      expect(account.login, 10001);
    });

    test('normalizes a legacy string login without changing account id', () {
      final account = Mt5Account.fromJson({
        'id': 'Broker-B_700138229',
        'user_id': 'user-b',
        'account_key': 'Broker-B_700138229',
        'mt5_login': '700138229',
        'broker_server': 'Broker-B',
      });

      expect(account.login, 700138229);
      expect(account.accountKey, 'Broker-B_700138229');
    });

    test('Firestore payload includes ownership and canonical id aliases', () {
      const account = Mt5Account(
        id: 'Broker-C_42',
        userId: 'user-c',
        accountKey: 'Broker-C_42',
        login: 42,
        brokerServer: 'Broker-C',
      );

      final payload = account.toJson();
      expect(payload['ownerUid'], 'user-c');
      expect(payload['user_id'], 'user-c');
      expect(payload['mt5AccountId'], 'Broker-C_42');
      expect(payload['account_key'], 'Broker-C_42');
    });
  });
}
