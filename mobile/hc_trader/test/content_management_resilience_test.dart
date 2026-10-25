import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/content/content_management_screen.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('one denied collection does not blank available content', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _testApp(
        ContentManagementScreen(
          repository: TradingRepository(firebaseEnabled: false),
          loadRecords: (collection) async {
            if (collection == 'contents') {
              throw StateError('permission-denied');
            }
            if (collection == 'trading_blogs') {
              return [
                {
                  'id': 'risk-controls',
                  'title': 'Risk controls for volatile sessions',
                  'content': 'Use a predefined risk limit.',
                  'status': 'draft',
                  'publication_status': 'draft',
                  'created_at': DateTime(2026, 7, 16),
                  'updated_at': DateTime(2026, 7, 16),
                },
              ];
            }
            return const [];
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Content centre unavailable'), findsNothing);
    expect(find.text('Some content sources are unavailable'), findsOneWidget);
    expect(find.textContaining('contents'), findsOneWidget);
    expect(find.text('Risk controls for volatile sessions'), findsOneWidget);
  });

  testWidgets('all denied collections retain the full unavailable state', (
    tester,
  ) async {
    await tester.pumpWidget(
      _testApp(
        ContentManagementScreen(
          repository: TradingRepository(firebaseEnabled: false),
          loadRecords: (_) async => throw StateError('permission-denied'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Content centre unavailable'), findsOneWidget);
    expect(find.textContaining('deployed Firestore rules'), findsOneWidget);
  });
}

Widget _testApp(Widget child) => MaterialApp(
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: child),
);
