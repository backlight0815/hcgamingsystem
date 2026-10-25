import 'dart:convert';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/app/hc_trader_app.dart';
import 'package:hc_trader/core/localization/app_locale_controller.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/firebase_options.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/trading_journal_sync_service.dart';
import 'package:http/http.dart' as http;
import 'package:integration_test/integration_test.dart';

// Run with flutter drive --keep-app-running, then force-stop and run again
// with --dart-define=QA_RECOVERY_RESUME=true. flutter test uninstalls on exit.
// Both executions use the actual SDK cache, Firebase emulator and Laravel HTTP.
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'journal offline recovery and persistent relaunch',
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
      const resume = bool.fromEnvironment('QA_RECOVERY_RESUME');
      if (resume) {
        expect(auth.currentUser?.uid, fixture['uid']);
      } else {
        await auth.signInWithEmailAndPassword(
          email: fixture['email'],
          password: fixture['password'],
        );
      }
      final uid = auth.currentUser!.uid;
      final storage = await LocalAppStorage.create();
      final client = ApiClient(
        baseUrl: 'http://10.0.2.2:8765/api/mobile/v1',
        authTokenProvider: () => auth.currentUser!.getIdToken(),
        requestTimeout: const Duration(seconds: 8),
      );
      final sync = TradingJournalSyncService(
        client: client,
        firestore: db,
        storage: storage,
      );
      final collection = db
          .collection('users')
          .doc(uid)
          .collection('trading_journals');
      if (resume) {
        expect(storage.readMap(uid, 'qa_recovery')?['prepared'], true);
        await db.disableNetwork();
        final cached = await collection.get(
          const GetOptions(source: Source.cache),
        );
        expect(
          cached.docs.any((d) => d.data()['notes'] == 'Website QA seed'),
          true,
        );
        await db.enableNetwork();
        // Also verify a second owner starts with an empty journal over real HTTP.
        final other = ApiClient(
          baseUrl: client.baseUrl,
          authTokenProvider: () async => fixture['other_token'],
        );
        final empty = await other.get('journal-sync/statistics');
        expect((empty['data'] as Map)['total_trades'], 0);
        debugPrint(
          'QA PASS: process relaunch retained login, journal cache and sync state; empty owner has zero trades',
        );
      } else {
        await sync.sync(uid);
        await db.disableNetwork();
        await expectLater(sync.sync(uid), throwsA(isA<FirebaseException>()));
        expect(
          storage.readMap(uid, 'journal_sync_status')?['state'],
          'offline',
        );
        final cached = await collection.get(
          const GetOptions(source: Source.cache),
        );
        expect(
          cached.docs.any((d) => d.data()['notes'] == 'Website QA seed'),
          true,
        );
        await db.enableNetwork();
        await sync.sync(uid);
        expect(storage.readMap(uid, 'journal_sync_status')?['state'], 'synced');
        await storage.writeMap(uid, 'qa_recovery', {'prepared': true});
        debugPrint(
          'QA PASS: Firestore network disabled, offline state preserved cache; re-enabled network and sync recovered',
        );
      }
      final repository = TradingRepository(
        firebaseEnabled: true,
        firestore: db,
        apiClient: client,
        storage: storage,
      );
      await tester.pumpWidget(
        HCTraderApp(
          authRepository: AuthRepository(auth: auth, firestore: db),
          tradingRepository: repository,
          firebaseEnabled: true,
          storage: storage,
          localeController: AppLocaleController.create(storage),
        ),
      );
      await waitFor(tester, () => find.text('Journal').evaluate().isNotEmpty);
      await tester.tap(find.text('Journal').last);
      await waitFor(
        tester,
        () => find.text('Record Trade').evaluate().isNotEmpty,
      );
      expect(find.text('Performance Review'), findsWidgets);
      debugPrint(
        'QA PASS: journal UI opened with cached authenticated account and manual source',
      );
      if (resume) {
        await tester.tap(find.text('Performance Review').first);
        await waitFor(
          tester,
          () => find.text('Rating Breakdown').evaluate().isNotEmpty,
        );
        expect(find.text('Profit Factor'), findsWidgets);
        debugPrint('QA PASS: Performance Review opened after process relaunch');
      }
      await tester.pump(const Duration(seconds: 2));
    },
    timeout: const Timeout(Duration(minutes: 3)),
  );
}

Future<void> waitFor(WidgetTester tester, bool Function() condition) async {
  for (var i = 0; i < 90; i++) {
    await tester.pump(const Duration(milliseconds: 500));
    if (condition()) return;
  }
  throw StateError('Expected screen did not appear.');
}
