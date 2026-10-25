import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/announcements/announcement_screens.dart';
import 'package:hc_trader/features/appointments/trading_appointments_screen.dart';
import 'package:hc_trader/features/examination/final_exam_screen.dart';
import 'package:hc_trader/features/more/more_screen.dart';
import 'package:hc_trader/features/more/profile_workspace_models.dart';
import 'package:hc_trader/features/profile/profile_screen.dart';
import 'package:hc_trader/features/readiness/readiness_checklist_screen.dart';
import 'package:hc_trader/features/recruitment/recruitment_screen.dart';
import 'package:hc_trader/features/support/support_tickets_screen.dart';
import 'package:hc_trader/features/tools/tools_screen.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('profile workspace logic', () {
    test('profile completion uses only fields required by ProfileScreen', () {
      const incomplete = AppUser(uid: 'u1', email: 'trader@example.com');
      const complete = AppUser(
        uid: 'u2',
        email: 'trader@example.com',
        displayName: 'Trader One',
      );

      expect(ProfileCompletion.fromUser(incomplete).progress, .5);
      expect(ProfileCompletion.fromUser(incomplete).isComplete, isFalse);
      expect(ProfileCompletion.fromUser(complete).progress, 1);
      expect(ProfileCompletion.fromUser(complete).isComplete, isTrue);
    });

    test('new profile workspace text is fully localized in Chinese', () {
      const localizations = AppLocalizations(Locale('zh'));
      const keys = <String>{
        'Profile details complete',
        '{completed} of {total} profile fields',
        'Your most useful account commands.',
        'My Services',
        'Services available for your account and role.',
        'Account and Preferences',
        'Account access',
        'Account setup',
        'Profile details',
        'Personal information',
        'Password and security',
        'App Preferences',
        'Language and notifications',
        'Trading data preferences',
        'Privacy and account data',
        'Account Actions',
        'Danger Zone',
        'Manage account deletion',
        'Access pending review',
        'Access rejected',
        'Access not submitted',
        'Add your display name',
        'Add a valid email address',
        'Continue setup',
      };

      for (final key in keys) {
        expect(
          localizations.text(key),
          isNot(key),
          reason: 'Missing Chinese translation for "$key"',
        );
      }
    });
  });

  group('profile workspace widgets', () {
    testWidgets('normal, administrator, and super administrator pages load', (
      tester,
    ) async {
      for (final user in const [
        AppUser(
          uid: 'normal',
          email: 'normal@example.com',
          displayName: 'Normal Client',
          roleId: 700,
          accessStatus: AccessStatus.approved,
        ),
        AppUser(
          uid: 'admin',
          email: 'admin@example.com',
          displayName: 'Administrator',
          roleId: 2,
          isAdmin: true,
          accessStatus: AccessStatus.approved,
        ),
        AppUser(
          uid: 'super',
          email: 'super@example.com',
          displayName: 'Super Administrator',
          roleId: 1,
          isAdmin: true,
          accessStatus: AccessStatus.approved,
        ),
      ]) {
        final harness = await _pumpProfile(tester, user: user);
        expect(find.byKey(const ValueKey('profile-identity')), findsOneWidget);
        expect(find.text(user.displayName!), findsWidgets);
        expect(tester.takeException(), isNull);
        await harness.dispose();
      }
    });

    testWidgets('Chinese super administrator role badge is localized', (
      tester,
    ) async {
      final harness = await _pumpProfile(
        tester,
        locale: const Locale('zh'),
        user: const AppUser(
          uid: 'super',
          email: 'superadmin@hcgaming.com',
          displayName: 'Super Administrator',
          roleId: 1,
          isAdmin: true,
          accessStatus: AccessStatus.approved,
        ),
      );
      addTearDown(harness.dispose);

      expect(find.text('超级管理员'), findsOneWidget);
      expect(find.text('访问已批准'), findsWidgets);
    });

    testWidgets('pending user sees approval action and public toolkit', (
      tester,
    ) async {
      final harness = await _pumpProfile(
        tester,
        user: const AppUser(
          uid: 'pending',
          email: 'pending@example.com',
          roleId: 750,
          accessStatus: AccessStatus.pendingReview,
          requiresOnboarding: true,
        ),
      );
      addTearDown(harness.dispose);

      expect(
        find.byKey(const ValueKey('service-client_access')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey('service-trading_tools')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey('service-final_exam')), findsNothing);
      expect(find.byKey(const ValueKey('service-appointments')), findsNothing);
      expect(find.byKey(const ValueKey('service-recruitment')), findsNothing);
    });

    testWidgets('disabled feature modules are omitted', (tester) async {
      final harness = await _pumpProfile(
        tester,
        features: const {
          'trading_exams': false,
          'appointments': false,
          'support_tickets': false,
          'trader_centre': false,
        },
      );
      addTearDown(harness.dispose);

      expect(find.byKey(const ValueKey('service-final_exam')), findsNothing);
      expect(find.byKey(const ValueKey('service-appointments')), findsNothing);
      expect(find.byKey(const ValueKey('service-support')), findsNothing);
      expect(find.byKey(const ValueKey('service-readiness')), findsNothing);
      expect(
        find.byKey(const ValueKey('service-trading_tools')),
        findsOneWidget,
      );
    });

    testWidgets('complete and incomplete profile states are honest', (
      tester,
    ) async {
      var harness = await _pumpProfile(
        tester,
        user: const AppUser(
          uid: 'incomplete',
          email: 'user@example.com',
          accessStatus: AccessStatus.approved,
        ),
      );
      expect(find.text('1 of 2 profile fields'), findsOneWidget);
      expect(find.text('Add your display name'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('profile-continue-setup')));
      await tester.pumpAndSettle();
      expect(find.byType(ProfileScreen), findsOneWidget);
      await harness.dispose();

      harness = await _pumpProfile(
        tester,
        user: const AppUser(
          uid: 'complete',
          email: 'user@example.com',
          displayName: 'Complete User',
          accessStatus: AccessStatus.approved,
        ),
      );
      addTearDown(harness.dispose);
      expect(find.text('Profile details complete'), findsOneWidget);
    });

    testWidgets('partial feature failure keeps identity and settings usable', (
      tester,
    ) async {
      final harness = await _pumpProfile(tester, failFeatures: true);
      addTearDown(harness.dispose);

      expect(find.byKey(const ValueKey('profile-identity')), findsOneWidget);
      expect(find.byKey(const ValueKey('setting-profile')), findsOneWidget);
      expect(
        find.text(
          'Some service availability could not be loaded. Core account actions remain available.',
        ),
        findsOneWidget,
      );
    });

    testWidgets('quick actions and service modules preserve destinations', (
      tester,
    ) async {
      final harness = await _pumpProfile(tester);
      addTearDown(harness.dispose);

      final routes = <String, Type>{
        'service-final_exam': FinalExamScreen,
        'service-trading_tools': ToolsScreen,
        'service-announcements': AnnouncementListScreen,
        'service-readiness': ReadinessChecklistScreen,
        'service-appointments': TradingAppointmentsScreen,
        'service-support': SupportTicketsScreen,
        'service-recruitment': RecruitmentScreen,
      };
      for (final route in routes.entries) {
        final finder = find.byKey(ValueKey(route.key));
        await tester.ensureVisible(finder);
        await tester.tap(finder);
        await tester.pumpAndSettle();
        expect(find.byType(route.value), findsOneWidget, reason: route.key);
        await tester.pageBack();
        await tester.pumpAndSettle();
      }

      final profileAction = find.byKey(const ValueKey('quick-edit_profile'));
      await tester.ensureVisible(profileAction);
      await tester.tap(profileAction);
      await tester.pumpAndSettle();
      expect(find.byType(ProfileScreen), findsOneWidget);
    });

    testWidgets('sign-out requires confirmation', (tester) async {
      final auth = _FakeAuthRepository();
      final harness = await _pumpProfile(tester, authRepository: auth);
      addTearDown(harness.dispose);

      final signOut = find.byKey(const ValueKey('account-sign-out'));
      await tester.ensureVisible(signOut);
      await tester.tap(signOut);
      await tester.pumpAndSettle();
      expect(find.text('Sign out?'), findsOneWidget);
      await tester.tap(find.widgetWithText(TextButton, 'Cancel'));
      await tester.pumpAndSettle();
      expect(auth.signOutCalls, 0);

      await tester.tap(signOut);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();
      expect(auth.signOutCalls, 1);
    });

    testWidgets('delete-account entry is separated and opens secure Settings', (
      tester,
    ) async {
      final harness = await _pumpProfile(
        tester,
        settingsDestinationBuilder: (_) =>
            const Scaffold(body: Text('Secure account settings')),
      );
      addTearDown(harness.dispose);

      expect(find.text('Danger Zone'), findsOneWidget);
      final deleteEntry = find.byKey(const ValueKey('account-delete'));
      await tester.ensureVisible(deleteEntry);
      await tester.tap(deleteEntry);
      await tester.pumpAndSettle();
      expect(find.text('Secure account settings'), findsOneWidget);
    });

    for (final testCase in const [
      ('small Android', Size(320, 900)),
      ('standard Android', Size(412, 900)),
      ('large phone', Size(600, 1000)),
      ('tablet', Size(800, 1100)),
      ('narrow Windows', Size(560, 850)),
      ('1280 desktop', Size(1280, 900)),
      ('1600 desktop', Size(1600, 1000)),
    ]) {
      testWidgets('${testCase.$1} has no overflow with Chinese text', (
        tester,
      ) async {
        final harness = await _pumpProfile(
          tester,
          size: testCase.$2,
          locale: const Locale('zh'),
          textScaler: const TextScaler.linear(1.35),
          user: const AppUser(
            uid: 'long-user',
            email: 'very.long.email.address.for.profile@example-domain.com',
            displayName: '拥有很长显示名称的超级管理员测试账户',
            roleId: 1,
            isAdmin: true,
            accessStatus: AccessStatus.approved,
          ),
        );
        addTearDown(harness.dispose);

        expect(find.text('我的服务'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('layout remains valid while Windows window is resized', (
      tester,
    ) async {
      final harness = await _pumpProfile(tester, size: const Size(1280, 900));
      addTearDown(harness.dispose);

      await tester.binding.setSurfaceSize(const Size(520, 850));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('profile-identity')), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}

class _FakeAuthRepository implements AuthRepository {
  int signOutCalls = 0;

  @override
  Stream<AppUser?> authStateChanges() => const Stream<AppUser?>.empty();

  @override
  Future<void> signOut() async {
    signOutCalls += 1;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeTradingRepository extends TradingRepository {
  _FakeTradingRepository({
    required LocalAppStorage storage,
    required this.features,
    required this.failFeatures,
  }) : super(firebaseEnabled: false, storage: storage);

  final Map<String, bool> features;
  final bool failFeatures;

  @override
  Future<Map<String, bool>> featureConfiguration() async {
    if (failFeatures) throw StateError('feature configuration unavailable');
    return features;
  }
}

class _ProfileHarness {
  const _ProfileHarness(this.tester);

  final WidgetTester tester;

  Future<void> dispose() async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.binding.setSurfaceSize(null);
  }
}

Future<_ProfileHarness> _pumpProfile(
  WidgetTester tester, {
  AppUser user = const AppUser(
    uid: 'trader-1',
    email: 'trader@example.com',
    displayName: 'Trader One',
    roleId: 750,
    accessStatus: AccessStatus.approved,
    mainMt5AccountKey: 'main-account',
  ),
  AuthRepository? authRepository,
  Map<String, bool> features = const {},
  bool failFeatures = false,
  Size size = const Size(900, 1100),
  Locale locale = const Locale('en'),
  TextScaler textScaler = TextScaler.noScaling,
  WidgetBuilder? settingsDestinationBuilder,
}) async {
  SharedPreferences.setMockInitialValues({});
  final storage = await LocalAppStorage.create();
  final repository = _FakeTradingRepository(
    storage: storage,
    features: features,
    failFeatures: failFeatures,
  );
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(
    MaterialApp(
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(textScaler: textScaler),
        child: child!,
      ),
      home: Scaffold(
        body: MoreScreen(
          user: user,
          authRepository: authRepository ?? _FakeAuthRepository(),
          tradingRepository: repository,
          storage: storage,
          firebaseEnabled: false,
          settingsDestinationBuilder: settingsDestinationBuilder,
          notificationUnregister: (_) async {},
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return _ProfileHarness(tester);
}
