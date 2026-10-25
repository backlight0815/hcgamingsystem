import 'dart:convert';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:integration_test/integration_test.dart';
import 'package:hc_trader/app/hc_trader_app.dart';
import 'package:hc_trader/core/localization/app_locale_controller.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/firebase_options.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/trading_journal_sync_service.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'real Android login, journal and two-way persisted sync',
    (tester) async {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
      final qa = await Firebase.initializeApp(
        name: 'journal-local-qa',
        options: const FirebaseOptions(
          apiKey: 'demo-journal',
          appId: '1:123456789:android:journalqa',
          messagingSenderId: '123456789',
          projectId: 'demo-hc-journal',
        ),
      );
      final auth = FirebaseAuth.instanceFor(app: qa);
      final db = FirebaseFirestore.instanceFor(app: qa);
      await auth.useAuthEmulator('10.0.2.2', 9099);
      db.useFirestoreEmulator('10.0.2.2', 8085);
      final fixture =
          jsonDecode(
                (await http.get(
                  Uri.parse('http://10.0.2.2:8766/fixture'),
                )).body,
              )
              as Map;
      await auth.signOut();
      final storage = await LocalAppStorage.create();
      await storage.clearUser('${fixture['uid']}');
      final client = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () => auth.currentUser!.getIdToken(),
      );
      final repository = TradingRepository(
        firebaseEnabled: true,
        firestore: db,
        apiClient: client,
        storage: storage,
      );
      final authRepo = AuthRepository(auth: auth, firestore: db);
      await tester.pumpWidget(
        HCTraderApp(
          authRepository: authRepo,
          tradingRepository: repository,
          firebaseEnabled: true,
          storage: storage,
          localeController: AppLocaleController.create(storage),
        ),
      );
      await tester.pump(const Duration(seconds: 2));
      expect(find.text('Sign In'), findsOneWidget);
      await tester.enterText(
        find.byType(TextFormField).at(0),
        '${fixture['email']}',
      );
      await tester.enterText(
        find.byType(TextFormField).at(1),
        '${fixture['password']}',
      );
      await tester.tap(find.text('Sign In'));
      await waitFor(tester, () => find.text('Journal').evaluate().isNotEmpty);
      expect(auth.currentUser, isNotNull);
      debugPrint('QA PASS: Firebase emulator login through real app UI');
      await tester.tap(find.text('Journal').last);
      await waitFor(
        tester,
        () => find.text('Record Trade').evaluate().isNotEmpty,
      );
      final uid = auth.currentUser!.uid;
      await repository.synchronizeJournal(uid, force: true);
      await tester.pump(const Duration(seconds: 2));
      final collection = db
          .collection('users')
          .doc(uid)
          .collection('trading_journals');
      final downloaded = await collection.get();
      expect(
        downloaded.docs.any((doc) => doc.data()['notes'] == 'Website QA seed'),
        isTrue,
      );
      expect(
        storage.readMap(
          uid,
          'journal_sync_statistics',
        )?['total_net_profit_loss'],
        '145.00',
      );
      debugPrint(
        'QA PASS: website row downloaded into real Firestore, journal opened, statistics 145.00',
      );
      final mobile = collection.doc(
        'mobile-qa-${DateTime.now().millisecondsSinceEpoch}',
      );
      await mobile.set({
        'user_id': uid,
        'type': 'trade',
        'source': 'manual',
        'status': 'closed',
        'open_date': Timestamp.fromDate(DateTime.utc(2026, 10, 2, 8)),
        'close_date': Timestamp.fromDate(DateTime.utc(2026, 10, 2, 9)),
        'pair': 'EURUSD',
        'direction': 1,
        'entry_price': 1.12345678,
        'exit_price': 1.12335678,
        'lot_size': 0.1,
        'pips': -1.0,
        'profit_loss': -10.0,
        'result': 2,
        'trading_account_id': 'mobile-only-account',
        'notes': 'Mobile QA created',
      });
      await repository.synchronizeJournal(uid, force: true);
      var data = (await mobile.get()).data()!;
      expect(data['_sync_id'], isNotNull);
      expect(data['trading_account_id'], 'mobile-only-account');
      var persisted = await evidence();
      expect(
        (persisted['trading_journals'] as List).any(
          (row) => row['notes'] == 'Mobile QA created',
        ),
        isTrue,
      );
      debugPrint(
        'QA PASS: mobile row persisted in Laravel SQLite; account preserved',
      );
      await mobile.update({'notes': 'Mobile QA updated'});
      await repository.synchronizeJournal(uid, force: true);
      persisted = await evidence();
      expect(
        (persisted['trading_journals'] as List).any(
          (row) => row['notes'] == 'Mobile QA updated',
        ),
        isTrue,
      );
      final websiteClient = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () async => '${fixture['token']}',
      );
      data = (await mobile.get()).data()!;
      await websiteClient.patch(
        'journal-sync/${data['_sync_id']}',
        body: {
          'mobile_id': mobile.id,
          'base_revision': data['_sync_revision'],
          'fields': {'notes': 'Website QA updated'},
        },
      );
      await repository.synchronizeJournal(uid, force: true);
      expect((await mobile.get()).data()?['notes'], 'Website QA updated');
      await Future.wait(
        List.generate(
          3,
          (_) => repository.synchronizeJournal(uid, force: true),
        ),
      );
      expect((await evidence())['trading_journals'], hasLength(2));
      expect(
        storage.readMap(
          uid,
          'journal_sync_statistics',
        )?['total_net_profit_loss'],
        '135.00',
      );
      debugPrint(
        'QA PASS: updates both ways, repeated sync no duplicates, P&L 135.00',
      );
      final sync = TradingJournalSyncService(
        client: client,
        firestore: db,
        storage: storage,
      );
      await storage.remove(uid, 'journal_sync_deletions');
      await sync.queueDeletion(uid: uid, docId: mobile.id);
      expect(storage.readList(uid, 'journal_sync_deletions'), hasLength(1));
      await sync.sync(uid);
      expect((await mobile.get()).exists, isFalse);
      expect((await evidence())['trading_journals'], hasLength(1));
      await sync.sync(uid);
      expect((await mobile.get()).exists, isFalse);
      debugPrint(
        'QA PASS: first deletion queue, durable acknowledgement, no resurrection',
      );
      final other = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () async => '${fixture['other_token']}',
      );
      await expectLater(
        other.get('journal-sync/${data['_sync_id']}'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 404)),
      );
      final invalid = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () async => 'expired-invalid-token',
      );
      await expectLater(
        invalid.get('journal-sync/changes'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
      );
      // Validate the real production TLS route from Android without account data.
      final production = ApiClient(
        baseUrl: 'https://www.hctradinghub.com/api/mobile/v1',
      );
      await expectLater(
        production.get('journal-sync/changes', authenticate: false),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
      );
      debugPrint(
        'QA PASS: owner isolation, invalid token and production HTTPS from Android',
      );
      await tester.pumpWidget(const SizedBox());
      await tester.pump();
      // More than one API page/batch, with real HTTP and persistent documents.
      final bulkIds = <String>[];
      final writeBatch = db.batch();
      for (var index = 0; index < 61; index++) {
        final ref = collection.doc('bulk-qa-$index');
        bulkIds.add(ref.id);
        writeBatch.set(ref, {
          'user_id': uid,
          'type': 'trade',
          'source': 'manual',
          'status': 'open',
          'open_date': Timestamp.fromDate(DateTime(2026, 10, 6, 8, index)),
          'pair': 'QA$index',
          'direction': 1,
          'entry_price': 1.0,
          'lot_size': 0.1,
          'exit_price': null,
          'profit_loss': null,
          'pips': null,
          'notes': 'Bulk QA',
        });
      }
      await writeBatch.commit();
      await sync.sync(uid);
      expect((await evidence())['trading_journals'], hasLength(62));
      expect(
        (await collection.doc(bulkIds.first).get()).data()?['exit_price'],
        isNull,
      );
      await sync.sync(uid);
      expect((await evidence())['trading_journals'], hasLength(62));
      debugPrint(
        'QA PASS: 61 records in two upload batches; nulls, pagination, no duplicates',
      );
      final raceRef = collection.doc(bulkIds.first);
      await raceRef.update({'notes': 'Sent edit'});
      final racingClient = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () => auth.currentUser!.getIdToken(),
        httpClient: AfterResponseClient(
          () => raceRef.update({'notes': 'Newer local edit'}),
        ),
      );
      await TradingJournalSyncService(
        client: racingClient,
        firestore: db,
        storage: storage,
      ).sync(uid);
      expect((await raceRef.get()).data()?['notes'], 'Newer local edit');
      await sync.sync(uid);
      expect(
        (await evidence())['trading_journals'].any(
          (row) => row['notes'] == 'Newer local edit',
        ),
        isTrue,
      );
      debugPrint(
        'QA PASS: edit made while HTTP acknowledgement was in flight survived and uploaded',
      );
      for (final id in bulkIds) {
        await sync.queueDeletion(uid: uid, docId: id);
      }
      await sync.sync(uid);
      expect((await evidence())['trading_journals'], hasLength(1));
      debugPrint(
        'QA PASS: bulk deletion acknowledgements and persisted cleanup',
      );
      await tester.pump(const Duration(seconds: 2));
    },
    timeout: const Timeout(Duration(minutes: 8)),
  );
}

class AfterResponseClient extends http.BaseClient {
  AfterResponseClient(this.afterResponse);
  final Future<void> Function() afterResponse;
  final http.Client inner = http.Client();
  var applied = false;
  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final response = await inner.send(request);
    if (!applied && request.url.path.endsWith('/batch')) {
      applied = true;
      final bytes = await response.stream.toBytes();
      await afterResponse();
      return http.StreamedResponse(
        Stream.value(bytes),
        response.statusCode,
        headers: response.headers,
      );
    }
    return response;
  }
}

Future<Map<String, dynamic>> evidence() async => Map<String, dynamic>.from(
  jsonDecode((await http.get(Uri.parse('http://10.0.2.2:8766/database'))).body)
      as Map,
);

Future<void> waitFor(WidgetTester tester, bool Function() condition) async {
  for (var i = 0; i < 90; i++) {
    await tester.pump(const Duration(milliseconds: 500));
    if (condition()) return;
  }
  throw StateError('Expected app screen did not appear within 45 seconds.');
}
