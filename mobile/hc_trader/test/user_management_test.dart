import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/user_management_screen.dart';
import 'package:hc_trader/models/admin_user_management.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

Widget app(Widget child, {Locale locale = const Locale('en')}) => MaterialApp(
  locale: locale,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: AppLocalizations.supportedLocales,
  home: Scaffold(body: child),
);

AdminUserRecord userRecord({
  String id = 'user-a',
  String name = 'Alicia Wong',
  String accessStatus = 'approved',
  bool hasPendingApplication = false,
}) => AdminUserRecord.fromMap({
  'id': id,
  'name': name,
  'email': '$id@example.test',
  'role_id': 750,
  'role_ids': [750],
  'account_status': 'active',
  'access_status': accessStatus,
  'has_pending_application': hasPendingApplication,
  'verification_status': 'verified',
  'mt5_status': 'connected',
  'main_mt5_login': '501122',
  'created_at': DateTime(2026, 7, 1),
  'last_active_at': DateTime(2026, 7, 16),
});

class _UserRepository extends TradingRepository {
  _UserRepository() : super(firebaseEnabled: false);

  final requestedPages = <int>[];
  final requestedFilters = <AdminUserFilters>[];

  @override
  Future<AdminUserPage> adminUserPage({
    AdminUserFilters filters = const AdminUserFilters(),
    AdminUserSort sort = AdminUserSort.newest,
    int page = 1,
    int pageSize = 30,
    bool refreshMetrics = true,
  }) async {
    requestedPages.add(page);
    requestedFilters.add(filters);
    return AdminUserPage(
      users: [
        userRecord(),
        userRecord(id: 'user-b', name: 'Bo Chen'),
      ],
      roles: const [
        {'id': 750, 'name': 'Trader'},
        {'id': 201, 'name': 'Signal Provider'},
      ],
      metrics: const AdminUserMetrics(total: 61, active: 58),
      page: page,
      pageSize: pageSize,
      total: 61,
      hasNext: page == 1,
    );
  }

  @override
  Future<AdminUserDetail> adminUserDetail(String userId) async =>
      AdminUserDetail(
        user: userRecord(
          id: userId,
          name: userId == 'user-a' ? 'Alicia Wong' : 'Bo Chen',
        ),
        activities: const [
          {'action': 'login'},
        ],
        auditLogs: const [
          {'action': 'account_created'},
        ],
      );
}

const adminActor = AppUser(
  uid: 'admin-a',
  email: 'admin@example.test',
  roleId: 1,
  roleIds: {1},
  isAdmin: true,
);

void main() {
  test('legacy user records tolerate missing optional fields', () {
    final user = AdminUserRecord.fromMap({
      'id': 'legacy-user',
      'email': 'legacy@example.test',
      'role_id': '750',
      'status': 1,
    });

    expect(user.id, 'legacy-user');
    expect(user.label, 'legacy@example.test');
    expect(user.roleId, 750);
    expect(user.accountStatus, 'active');
    expect(user.mt5Status, 'none');
    expect(user.createdAt, isNull);
  });

  test('user filters track active criteria and keep search when cleared', () {
    const filters = AdminUserFilters(
      search: 'alicia',
      roleId: 750,
      accessStatus: 'approved',
      hasPendingApplication: true,
    );
    expect(filters.activeCount, 4);
    expect(filters.copyWith(clearRole: true).roleId, isNull);
    expect(filters.copyWith(clearRole: true).search, 'alicia');
    expect(filters.cacheKey, contains('approved'));
  });

  testWidgets('status badges include an icon and readable label', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        const Wrap(
          children: [
            UserStatusBadge(status: 'suspended'),
            VerificationStatusBadge(status: 'verified'),
            Mt5ConnectionBadge(status: 'connected'),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Suspended'), findsOneWidget);
    expect(find.text('Verified'), findsOneWidget);
    expect(find.text('MT5 Connected'), findsOneWidget);
    expect(find.byType(Icon), findsNWidgets(3));
    expect(tester.takeException(), isNull);
  });

  testWidgets('management header renders Simplified Chinese labels', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        const UserManagementHeader(
          metrics: AdminUserMetrics(
            total: 120,
            active: 95,
            pendingAccess: 3,
            pendingVerification: 4,
            suspended: 2,
            recentlyRegistered: 8,
          ),
          loading: false,
        ),
        locale: const Locale('zh'),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('用户管理'), findsOneWidget);
    expect(find.text('用户总数'), findsOneWidget);
    expect(find.text('活跃用户'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('access action menu follows the application lifecycle', (
    tester,
  ) async {
    Future<void> expectAction(
      AdminUserRecord user,
      String expectedLabel,
    ) async {
      await tester.pumpWidget(
        app(
          AdminActionMenu(
            actor: adminActor,
            user: user,
            busy: false,
            onSelected: (_) {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(
        find.byWidgetPredicate((widget) => widget is PopupMenuButton),
      );
      await tester.pumpAndSettle();
      expect(find.text(expectedLabel), findsOneWidget);
      await tester.tap(find.text(expectedLabel));
      await tester.pumpAndSettle();
    }

    await expectAction(
      userRecord(accessStatus: 'pendingReview', hasPendingApplication: true),
      'Request more information',
    );
    await expectAction(userRecord(), 'Revoke access');
    await expectAction(userRecord(accessStatus: 'revoked'), 'Restore access');
    expect(tester.takeException(), isNull);
  });

  testWidgets('mobile filter panel remains usable without overflow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      app(
        const UserFilterPanel(
          initial: AdminUserFilters(),
          roles: [
            {'id': 750, 'name': 'Trader'},
            {'id': 201, 'name': 'Signal Provider'},
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Filter users'), findsOneWidget);
    expect(find.text('Apply filters'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('desktop directory paginates and detail panel collapses', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1280, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _UserRepository();

    await tester.pumpWidget(
      app(AdminUserManagementScreen(actor: adminActor, repository: repository)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Alicia Wong'), findsWidgets);
    expect(find.byTooltip('Collapse details'), findsOneWidget);
    await tester.tap(find.byTooltip('Collapse details'));
    await tester.pumpAndSettle();
    expect(find.byTooltip('Show details'), findsOneWidget);
    await tester.tap(find.byTooltip('Next page'));
    await tester.pumpAndSettle();
    expect(repository.requestedPages, contains(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets('search is debounced and mobile directory has no overflow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _UserRepository();

    await tester.pumpWidget(
      app(AdminUserManagementScreen(actor: adminActor, repository: repository)),
    );
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(SearchBar), 'alicia');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();

    expect(repository.requestedFilters.last.search, 'alicia');
    expect(find.text('Alicia Wong'), findsOneWidget);
    expect(find.byTooltip('Collapse details'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('tablet width keeps the adaptive split detail layout', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      app(
        AdminUserManagementScreen(
          actor: adminActor,
          repository: _UserRepository(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byTooltip('Collapse details'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
