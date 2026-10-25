import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/access/access_status_screen.dart';
import 'package:hc_trader/features/hub/trade_call_detail_screen.dart';
import 'package:hc_trader/features/profile/profile_screen.dart';
import 'package:hc_trader/features/settings/settings_screen.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/knowledge_centre_category.dart';
import 'package:hc_trader/models/knowledge_centre_item.dart';
import 'package:hc_trader/models/mt5_sync_models.dart';
import 'package:hc_trader/models/trader_account_profile.dart';
import 'package:hc_trader/models/trading_position_application_mobile.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/models/trading_signal_item.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('workflow mappings', () {
    test(
      'profile update values trim while preserving intentional empty phone',
      () {
        final values = normalizeProfileUpdateValues(
          name: '  Trader One  ',
          email: ' TRADER@EXAMPLE.COM ',
          phoneNumber: '   ',
        );
        expect(values.name, 'Trader One');
        expect(values.email, 'trader@example.com');
        expect(values.phoneNumber, '');
      },
    );

    test('access status transitions reject stale processing', () {
      expect(
        AccessStatus.pendingReview.canTransitionTo(
          AccessStatus.approved,
          administrator: true,
        ),
        isTrue,
      );
      expect(
        AccessStatus.withdrawn.canTransitionTo(
          AccessStatus.approved,
          administrator: true,
        ),
        isFalse,
      );
      expect(
        AccessStatus.pendingReview.canTransitionTo(
          AccessStatus.withdrawn,
          administrator: false,
        ),
        isTrue,
      );
      expect(
        AccessStatus.pendingReview.canTransitionTo(
          AccessStatus.moreInfoRequired,
          administrator: true,
        ),
        isTrue,
      );
      expect(AccessStatus.fromStorage('more_info_required').canReapply, isTrue);
    });

    test('broker verification maps legacy and canonical statuses', () {
      expect(
        BrokerVerificationStatus.fromStorage('pendingReview'),
        BrokerVerificationStatus.pending,
      );
      expect(
        BrokerVerificationStatus.fromStorage('resubmission_required'),
        BrokerVerificationStatus.rejectedResubmittable,
      );
      expect(
        BrokerVerificationStatus.fromStorage('verified'),
        BrokerVerificationStatus.approved,
      );
    });

    test(
      'main MT5 profile state and position account identity are preserved',
      () {
        final user = AppUser.fromJson({
          'id': 'u1',
          'email': 'u1@example.com',
          'main_mt5_account_id': 'broker_1001',
        });
        final application = TradingPositionApplicationMobile.fromJson({
          'id': 'p1',
          'user_id': 'u1',
          'requested_position': 'recruiter',
          'mt5_account_id': 'broker_1001',
          'mt5_login': 1001,
        });
        expect(user.mainMt5AccountKey, 'broker_1001');
        expect(application.mt5AccountId, 'broker_1001');
        expect(application.toFirestore()['mt5_account_id'], 'broker_1001');
      },
    );

    test('position application filtering never mixes MT5 accounts', () {
      final applications = [
        _position('a', 'account-a'),
        _position('b', 'account-b'),
      ];
      expect(
        filterPositionApplicationsForMt5(
          applications,
          'account-b',
        ).map((item) => item.id),
        ['b'],
      );
      expect(filterPositionApplicationsForMt5(applications, ''), isEmpty);
    });

    test('knowledge category aliases map to stable category slugs', () {
      expect(KnowledgeCentreCategory.normalizeSlug('Stock'), 'stocks');
      expect(
        KnowledgeCentreCategory.normalizeSlug('Trading Future'),
        'futures-trading',
      );
      final item = KnowledgeCentreItemMobile.fromJson({
        'id': 'k1',
        'title': 'Futures',
        'category': 'Trading Future',
      });
      expect(item.category, 'futures-trading');
      expect(
        KnowledgeCentreCategory.slugs,
        containsAll(['stocks', 'futures-trading', 'investment', 'cfd-trading']),
      );
    });

    test(
      'trade-call direction and risk reward derive from real setup fields',
      () {
        const signal = TradingSignalItem(
          id: 's1',
          tradingPair: 'XAUUSD',
          immediateAction: 'BUY NOW',
          entryPrice: '2000',
          stopLoss: '1990',
          targets: ['2020'],
        );
        expect(signal.direction, 'Buy');
        expect(signal.riskRewardRatio, 2);
      },
    );
  });

  group('workflow widgets', () {
    testWidgets('profile save enables only after a valid change', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(900, 1400));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final auth = _FakeAuthRepository();
      final trading = _FakeTradingRepository();
      await tester.pumpWidget(
        _app(
          ProfileScreen(
            user: _user,
            repository: auth,
            uploadRepository: trading,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<FilledButton>(find.byKey(const ValueKey('profile-save')))
            .onPressed,
        isNull,
      );
      await tester.enterText(
        find.byType(TextFormField).first,
        'Updated Trader',
      );
      await tester.pump();
      expect(
        tester
            .widget<FilledButton>(find.byKey(const ValueKey('profile-save')))
            .onPressed,
        isNotNull,
      );
      await tester.tap(find.byKey(const ValueKey('profile-save')));
      await tester.pumpAndSettle();
      expect(auth.profileUpdates, 1);
      expect(find.text('Profile updated successfully.'), findsOneWidget);
    });

    testWidgets('open profile reflects repository user updates immediately', (
      tester,
    ) async {
      final auth = _FakeAuthRepository();
      await tester.pumpWidget(
        _app(
          ProfileScreen(
            user: _user,
            repository: auth,
            uploadRepository: _FakeTradingRepository(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      auth.emitUser(
        const AppUser(
          uid: 'u1',
          email: 'updated@example.com',
          displayName: 'Latest Server Name',
          username: 'trader-one',
          phoneNumber: '60123456789',
          roleId: 750,
          accessStatus: AccessStatus.approved,
        ),
      );
      await tester.pumpAndSettle();

      final fields = tester
          .widgetList<TextFormField>(find.byType(TextFormField))
          .toList();
      expect(fields[0].controller!.text, 'Latest Server Name');
      expect(fields[1].controller!.text, 'updated@example.com');
      expect(fields[2].controller!.text, '60123456789');
      expect(find.text('trader-one'), findsOneWidget);
    });

    testWidgets('pending verified email remains visible after save', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(900, 1400));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final auth = _FakeAuthRepository();
      await tester.pumpWidget(
        _app(
          ProfileScreen(
            user: _user,
            repository: auth,
            uploadRepository: _FakeTradingRepository(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final emailField = find.byType(TextFormField).at(1);
      await tester.enterText(emailField, 'new-email@example.com');
      await tester.tap(find.byKey(const ValueKey('profile-save')));
      await tester.pumpAndSettle();

      expect(
        tester.widget<TextFormField>(emailField).controller!.text,
        'new-email@example.com',
      );
    });

    testWidgets('delete account Cancel closes safely with a null result', (
      tester,
    ) async {
      String result = 'waiting';
      await tester.pumpWidget(
        _app(
          Builder(
            builder: (context) => FilledButton(
              onPressed: () async {
                final password = await showDialog<String>(
                  context: context,
                  builder: (_) => const DeleteAccountConfirmationDialog(),
                );
                result = password ?? 'cancelled';
              },
              child: const Text('Open'),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Open'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('delete-account-cancel')));
      await tester.pumpAndSettle();
      expect(find.byType(DeleteAccountConfirmationDialog), findsNothing);
      expect(result, 'cancelled');
    });

    testWidgets('pending access application requires withdrawal confirmation', (
      tester,
    ) async {
      final auth = _FakeAuthRepository();
      await tester.pumpWidget(
        _app(
          AccessStatusScreen(
            user: _userWith(AccessStatus.pendingReview),
            authRepository: auth,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(
        find.byKey(const ValueKey('withdraw-access-application')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keep application'));
      await tester.pumpAndSettle();
      expect(auth.withdrawals, 0);
      await tester.tap(
        find.byKey(const ValueKey('withdraw-access-application')),
      );
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(FilledButton, 'Withdraw application'),
      );
      await tester.pumpAndSettle();
      expect(auth.withdrawals, 1);
    });

    testWidgets('Settings remains usable without client access', (
      tester,
    ) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final repository = _RestrictedSettingsRepository();
      await tester.pumpWidget(
        _app(
          SettingsScreen(
            user: _userWith(AccessStatus.notSubmitted),
            authRepository: _FakeAuthRepository(),
            repository: repository,
            firebaseEnabled: false,
            storage: storage,
            notificationServiceEnabled: false,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Trading settings are locked'), findsOneWidget);
      expect(find.text('Language'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Delete account'),
        400,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Delete account'), findsOneWidget);
      expect(repository.privateStreamReads, 0);
      expect(tester.takeException(), isNull);
    });

    for (final size in const [Size(360, 760), Size(1200, 850)]) {
      testWidgets('Trade Call command centre is responsive at $size', (
        tester,
      ) async {
        await tester.binding.setSurfaceSize(size);
        addTearDown(() => tester.binding.setSurfaceSize(null));
        await tester.pumpWidget(
          _app(
            TradeCallDetailScreen(
              signal: TradingSignalItem(
                id: 's1',
                tradingPair: 'XAUUSD',
                title: 'Gold setup',
                immediateAction: 'BUY',
                entryPrice: '2300 - 2302',
                stopLoss: '2290',
                targets: const ['2320', '2340'],
                currentPrice: '2305.20',
                timeframe: 'H1',
                orderType: 'Market',
                analysisSections: const {
                  'market_bias': 'Bullish while price holds above support.',
                  'invalidation': 'Close below 2290.',
                },
                createdAt: DateTime(2026, 7, 12),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey('trade-call-market-overview')),
          findsOneWidget,
        );
        expect(
          find.byKey(const ValueKey('trade-call-price-ladder')),
          findsOneWidget,
        );
        expect(tester.takeException(), isNull);
      });
    }
  });
}

const _user = AppUser(
  uid: 'u1',
  email: 'trader@example.com',
  displayName: 'Trader One',
  roleId: 750,
  accessStatus: AccessStatus.approved,
);

AppUser _userWith(AccessStatus status) => AppUser(
  uid: _user.uid,
  email: _user.email,
  displayName: _user.displayName,
  roleId: _user.roleId,
  accessStatus: status,
);

TradingPositionApplicationMobile _position(String id, String accountId) {
  return TradingPositionApplicationMobile(
    id: id,
    userId: 'u1',
    requestedPosition: 'recruiter',
    mt5AccountId: accountId,
  );
}

Widget _app(Widget home) => MaterialApp(
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: home,
);

class _FakeAuthRepository implements AuthRepository {
  int profileUpdates = 0;
  int withdrawals = 0;
  AppUser _latestUser = _user;
  final _users = StreamController<AppUser?>.broadcast();

  @override
  AppUser? get currentUser => _latestUser;

  @override
  Stream<AppUser?> authStateChanges() => Stream<AppUser?>.multi((controller) {
    controller.add(_latestUser);
    final subscription = _users.stream.listen(controller.add);
    controller.onCancel = subscription.cancel;
  });

  void emitUser(AppUser user) {
    _latestUser = user;
    _users.add(user);
  }

  @override
  Future<ProfileUpdateResult> updateProfile({
    required String name,
    required String email,
    String? phoneNumber,
    String? photoUrl,
  }) async {
    profileUpdates += 1;
    final normalizedEmail = email.trim().toLowerCase();
    final emailChanged = normalizedEmail != _latestUser.email.toLowerCase();
    emitUser(
      AppUser(
        uid: _latestUser.uid,
        email: emailChanged ? _latestUser.email : normalizedEmail,
        displayName: name.trim(),
        username: _latestUser.username,
        phoneNumber: phoneNumber?.trim() ?? _latestUser.phoneNumber,
        avatarUrl: photoUrl ?? _latestUser.avatarUrl,
        roleId: _latestUser.roleId,
        isAdmin: _latestUser.isAdmin,
        accessStatus: _latestUser.accessStatus,
        requiresOnboarding: _latestUser.requiresOnboarding,
        finalExamPassed: _latestUser.finalExamPassed,
        requiresFinalExam: _latestUser.requiresFinalExam,
        mainMt5AccountKey: _latestUser.mainMt5AccountKey,
      ),
    );
    return ProfileUpdateResult(emailVerificationSent: emailChanged);
  }

  @override
  Future<void> withdrawAccessApplication() async {
    withdrawals += 1;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeTradingRepository extends TradingRepository {
  _FakeTradingRepository() : super(firebaseEnabled: false);

  @override
  Stream<TraderAccountProfile> accountProfileFor(String uid) =>
      Stream.value(TraderAccountProfile.empty(uid));

  @override
  Stream<Mt5Account?> mainMt5AccountFor(String uid) => Stream.value(null);
}

class _RestrictedSettingsRepository extends TradingRepository {
  _RestrictedSettingsRepository() : super(firebaseEnabled: false);

  int privateStreamReads = 0;

  @override
  Stream<List<TradingJournalEntry>> journalsFor(String uid) {
    privateStreamReads += 1;
    return super.journalsFor(uid);
  }

  @override
  Stream<List<CapitalTransaction>> capitalsFor(String uid) {
    privateStreamReads += 1;
    return super.capitalsFor(uid);
  }
}
