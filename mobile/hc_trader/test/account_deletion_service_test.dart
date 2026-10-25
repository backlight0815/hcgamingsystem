import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/services/account_deletion_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('exhaustive account deletion orchestration', () {
    test(
      'reauthenticates, deletes remotely, then clears only user data',
      () async {
        SharedPreferences.setMockInitialValues({'hc_mobile_locale': 'zh'});
        final storage = await LocalAppStorage.create();
        await storage.writeMap('user-1', 'draft', {'secret': 'remove'});
        await storage.writeMap('user-2', 'draft', {'keep': true});
        final auth = _DeletionAuthRepository();
        final stages = <AccountDeletionStage>[];
        var notificationUid = '';
        final service = AccountDeletionService(
          authRepository: auth,
          storage: storage,
          unregisterNotifications: (uid) async => notificationUid = uid,
        );

        await service.deleteCurrentAccount(
          currentPassword: 'correct-password',
          reason: 'privacy',
          onProgress: stages.add,
        );

        expect(auth.preparedPassword, 'correct-password');
        expect(auth.deletionReason, 'privacy');
        expect(notificationUid, 'user-1');
        expect(storage.readMap('user-1', 'draft'), isNull);
        expect(storage.readMap('user-2', 'draft'), {'keep': true});
        expect(storage.readString('locale'), 'zh');
        expect(stages, AccountDeletionStage.values);
        expect(service.isRunning, isFalse);
      },
    );

    test('remote failure retains local data and allows a retry', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      await storage.writeMap('user-1', 'draft', {'keepUntilRemote': true});
      final auth = _DeletionAuthRepository(failRemoteOnce: true);
      final service = AccountDeletionService(
        authRepository: auth,
        storage: storage,
      );

      await expectLater(
        service.deleteCurrentAccount(currentPassword: 'password'),
        throwsA(isA<StateError>()),
      );
      expect(storage.readMap('user-1', 'draft'), isNotNull);
      expect(service.isRunning, isFalse);

      await service.deleteCurrentAccount(currentPassword: 'password');
      expect(auth.remoteCalls, 2);
      expect(storage.readMap('user-1', 'draft'), isNull);
    });

    test('notification cleanup is best effort', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final auth = _DeletionAuthRepository();
      final service = AccountDeletionService(
        authRepository: auth,
        storage: storage,
        unregisterNotifications: (_) async => throw StateError('offline'),
      );

      await service.deleteCurrentAccount(currentPassword: 'password');
      expect(auth.remoteCalls, 1);
    });

    test('rejects duplicate taps while deletion is running', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final preparation = Completer<void>();
      final auth = _DeletionAuthRepository(preparation: preparation);
      final service = AccountDeletionService(
        authRepository: auth,
        storage: storage,
      );

      final first = service.deleteCurrentAccount(currentPassword: 'password');
      await Future<void>.delayed(Duration.zero);
      expect(service.isRunning, isTrue);
      await expectLater(
        service.deleteCurrentAccount(currentPassword: 'password'),
        throwsA(
          isA<AuthFailure>().having(
            (error) => error.message,
            'message',
            contains('already in progress'),
          ),
        ),
      );
      preparation.complete();
      await first;
      expect(auth.remoteCalls, 1);
    });

    test(
      'requires an authenticated user before any destructive work',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final service = AccountDeletionService(
          authRepository: _DeletionAuthRepository(signedIn: false),
          storage: storage,
        );

        await expectLater(
          service.deleteCurrentAccount(currentPassword: ''),
          throwsA(isA<AuthFailure>()),
        );
      },
    );
  });
}

class _DeletionAuthRepository implements AuthRepository {
  _DeletionAuthRepository({
    this.failRemoteOnce = false,
    this.preparation,
    bool signedIn = true,
  }) : _user = signedIn
           ? const AppUser(uid: 'user-1', email: 'user@example.com')
           : null;

  final AppUser? _user;
  final bool failRemoteOnce;
  final Completer<void>? preparation;
  String? preparedPassword;
  String? deletionReason;
  int remoteCalls = 0;

  @override
  AppUser? get currentUser => _user;

  @override
  Future<void> prepareCurrentAccountDeletion({
    required String currentPassword,
  }) async {
    preparedPassword = currentPassword;
    await preparation?.future;
  }

  @override
  Future<void> deletePreparedCurrentAccount({String reason = ''}) async {
    deletionReason = reason;
    remoteCalls += 1;
    if (failRemoteOnce && remoteCalls == 1) {
      throw StateError('partial backend failure');
    }
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
