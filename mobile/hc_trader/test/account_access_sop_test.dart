import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/app_role_permissions.dart';
import 'package:hc_trader/features/account/account_screen.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  testWidgets('locked Accounts screen offers the customer access SOP', (
    tester,
  ) async {
    var openedAccessApplication = false;
    final user = AppUser(
      uid: 'analyst-1',
      email: 'analyst@example.com',
      roleId: RolePermissionMatrix.marketAnalyst,
      roleIds: const {RolePermissionMatrix.marketAnalyst},
      accessStatus: AccessStatus.unapproved,
    );

    await tester.pumpWidget(
      MaterialApp(
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Scaffold(
          body: AccountScreen(
            user: user,
            repository: TradingRepository(firebaseEnabled: false),
            onViewAccessStatus: () => openedAccessApplication = true,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Client access required'), findsOneWidget);
    expect(find.text('Apply for Access'), findsOneWidget);

    await tester.tap(find.text('Apply for Access'));
    expect(openedAccessApplication, isTrue);
  });
}
