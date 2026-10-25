import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/publishing_authorization.dart';
import 'package:hc_trader/core/security/user_roles.dart';
import 'package:hc_trader/features/profile/current_roles_card.dart';
import 'package:hc_trader/models/app_user.dart';

void main() {
  group('current role resolution', () {
    test('resolves one trader role', () {
      expect(
        resolveUserRoles({
          'roles': ['trader'],
        }),
        ['trader'],
      );
    });

    test('resolves one market analyst role', () {
      expect(
        resolveUserRoles({
          'roles': ['market_analyst'],
        }),
        ['market_analyst'],
      );
    });

    test('sorts two or more roles by stable priority', () {
      expect(
        resolveUserRoles({
          'roles': ['trader', 'recruiter', 'leader', 'market_analyst'],
        }),
        ['leader', 'market_analyst', 'recruiter', 'trader'],
      );
    });

    test('normalizes capitalization, spaces, hyphens, and duplicates', () {
      expect(
        resolveUserRoles({
          'roles': ['Trader', ' trader ', 'MARKET-ANALYST', 'market analyst'],
        }),
        ['market_analyst', 'trader'],
      );
    });

    test('uses legacy role only when the roles collection is empty', () {
      expect(resolveUserRoles({'roles': [], 'role': 'trader'}), ['trader']);
      expect(
        resolveUserRoles({
          'roles': ['market_analyst'],
          'role': 'trader',
        }),
        ['market_analyst'],
      );
    });

    test('handles missing and invalid role values without throwing', () {
      expect(resolveUserRoles(const {}), isEmpty);
      expect(
        resolveUserRoles({
          'roles': [null, '', '  ', 'risk_manager'],
        }),
        ['risk_manager'],
      );
      expect(getRoleDisplayName('risk_manager', 'en'), 'Risk Manager');
    });

    test('supports existing numeric role_ids records', () {
      expect(
        resolveUserRoles({
          'role_ids': [750, '501', 750],
        }),
        ['market_analyst', 'trader'],
      );
    });

    test('shows only approved and active status-based assignments', () {
      final roles = resolveUserRoles({
        'role': 'trader',
        'roleAssignments': [
          {'role': 'trader', 'status': 'suspended', 'isActive': true},
          {'role': 'signal_provider', 'status': 'approved', 'isActive': false},
          {'role': 'market_analyst', 'status': 'approved', 'isActive': true},
          {'role': 'MARKET ANALYST', 'status': 'approved', 'isActive': true},
        ],
      });
      expect(roles, ['market_analyst']);
    });

    test(
      'status assignments with no active role do not restore legacy access',
      () {
        final user = AppUser.fromJson({
          'id': 'suspended-role-user',
          'email': 'user@example.test',
          'role_id': 750,
          'roleAssignments': [
            {'role': 'trader', 'status': 'suspended', 'isActive': true},
          ],
        });
        expect(user.effectiveRoles, isEmpty);
        expect(user.effectiveRoleIds, isEmpty);
        expect(user.roleId, 0);
      },
    );

    test('multi-role users retain the existing merged permission behavior', () {
      final user = AppUser.fromJson({
        'id': 'publisher',
        'email': 'publisher@example.test',
        'roles': ['trader', 'market_analyst'],
        'access_status': 'approved',
      });
      final authorization = PublishingAuthorization(user);

      expect(user.effectiveRoleIds, {501, 750});
      expect(user.hasRole('TRADER'), isTrue);
      expect(user.hasAnyRole(['signal_provider', 'market-analyst']), isTrue);
      expect(authorization.canPublishMarketAnalysis, isTrue);
    });
  });

  group('CurrentRolesCard', () {
    testWidgets('renders every role and updates after a role is removed', (
      tester,
    ) async {
      await _pumpCard(tester, const ['trader', 'market_analyst']);
      expect(find.text('Trader'), findsOneWidget);
      expect(find.text('Market Analyst'), findsOneWidget);

      await _pumpCard(tester, const ['trader']);
      expect(find.text('Trader'), findsOneWidget);
      expect(find.text('Market Analyst'), findsNothing);
    });

    testWidgets('renders a localized empty state', (tester) async {
      await _pumpCard(tester, const []);
      expect(find.text('No role assigned'), findsOneWidget);
      expect(find.byType(Chip), findsNothing);
    });

    testWidgets('uses Simplified Chinese role labels', (tester) async {
      await _pumpCard(tester, const [
        'trader',
        'market_analyst',
      ], locale: const Locale('zh'));
      expect(find.text('\u5f53\u524d\u89d2\u8272'), findsOneWidget);
      expect(find.text('\u4ea4\u6613\u5458'), findsOneWidget);
      expect(find.text('\u5e02\u573a\u5206\u6790\u5e08'), findsOneWidget);
    });

    testWidgets('chips wrap on a narrow text-scaled screen', (tester) async {
      await tester.binding.setSurfaceSize(const Size(210, 500));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await _pumpCard(tester, const [
        'trader',
        'market_analyst',
        'signal_provider',
      ], textScaler: const TextScaler.linear(1.35));

      final trader = tester.getTopLeft(
        find.byKey(const ValueKey('role-chip-trader')),
      );
      final analyst = tester.getTopLeft(
        find.byKey(const ValueKey('role-chip-market_analyst')),
      );
      expect(analyst.dy, isNot(trader.dy));
      expect(tester.takeException(), isNull);
    });

    testWidgets('dark mode keeps role content renderable', (tester) async {
      await _pumpCard(tester, const [
        'super_admin',
        'financial_department',
        'lead_client',
      ], brightness: Brightness.dark);
      expect(find.byType(Chip), findsNWidgets(3));
      expect(find.text('Super Admin'), findsOneWidget);
      expect(find.text('Financial Department'), findsOneWidget);
      expect(find.text('Lead Client'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}

Future<void> _pumpCard(
  WidgetTester tester,
  List<String> roles, {
  Locale locale = const Locale('en'),
  Brightness brightness = Brightness.light,
  TextScaler textScaler = TextScaler.noScaling,
}) async {
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
      theme: ThemeData(colorSchemeSeed: Colors.teal, brightness: brightness),
      home: MediaQuery(
        data: MediaQueryData(textScaler: textScaler),
        child: Scaffold(
          body: SingleChildScrollView(child: CurrentRolesCard(roles: roles)),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}
