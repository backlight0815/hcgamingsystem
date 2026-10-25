import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/coursework_admin_screen.dart';
import 'package:hc_trader/features/examination/coursework_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/coursework_models.dart';
import 'package:hc_trader/repositories/coursework_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  final theme = _theme();

  testWidgets('Coursework overview screenshot', (tester) async {
    await _loadFonts();
    await _surface(tester, const Size(430, 932));
    final trading = TradingRepository(firebaseEnabled: false);
    await tester.pumpWidget(
      _app(
        Scaffold(
          appBar: AppBar(title: const Text('Learning Assessments')),
          body: CourseworkScreen(
            user: const AppUser(
              uid: 'candidate',
              email: 'candidate@example.test',
            ),
            tradingRepository: trading,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await expectLater(
      find.byType(Scaffold).first,
      matchesGoldenFile('../docs/screenshots/coursework-overview.png'),
    );
  });

  testWidgets('Coursework assignment player screenshot', (tester) async {
    await _loadFonts();
    await _surface(tester, const Size(900, 1000));
    final attempt = CourseworkAttempt(
      id: 'attempt-preview',
      assignmentId: 'assignment-preview',
      themeId: theme.id,
      themeVersion: theme.version,
      userId: 'candidate',
      status: CourseworkStatus.draft,
      themeSnapshot: theme,
      startedAt: DateTime(2026, 7, 15),
    );
    await tester.pumpWidget(
      _app(
        CourseworkAttemptScreen(
          user: const AppUser(
            uid: 'candidate',
            email: 'candidate@example.test',
          ),
          repository: _PreviewCourseworkRepository(attempt),
          attemptId: attempt.id,
        ),
      ),
    );
    await tester.pumpAndSettle();
    await expectLater(
      find.byType(Scaffold).first,
      matchesGoldenFile('../docs/screenshots/coursework-assignment-player.png'),
    );
  });

  testWidgets('Coursework theme management screenshot', (tester) async {
    await _loadFonts();
    await _surface(tester, const Size(900, 1000));
    await tester.pumpWidget(
      _app(
        CourseworkThemeEditor(
          repository: _PreviewCourseworkRepository(null),
          theme: theme,
        ),
      ),
    );
    await tester.pumpAndSettle();
    await expectLater(
      find.byType(Scaffold).first,
      matchesGoldenFile('../docs/screenshots/coursework-theme-management.png'),
    );
  });
}

class _PreviewCourseworkRepository extends CourseworkRepository {
  _PreviewCourseworkRepository(this.attempt) : super(firebaseEnabled: false);
  final CourseworkAttempt? attempt;

  @override
  Stream<CourseworkAttempt> watchAttempt(String attemptId) =>
      Stream.value(attempt!);
}

CourseworkTheme _theme() {
  final values =
      jsonDecode(
            File('assets/seed/coursework_themes_100.json').readAsStringSync(),
          )
          as List;
  return CourseworkTheme.fromMap(
    Map<String, dynamic>.from(values.first as Map),
  );
}

Future<void> _surface(WidgetTester tester, Size size) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
}

Future<void> _loadFonts() async {
  final loader = FontLoader('NotoSansSC')
    ..addFont(rootBundle.load('assets/fonts/NotoSansSC-VF.ttf'));
  await loader.load();
}

Widget _app(Widget home) => MaterialApp(
  debugShowCheckedModeBanner: false,
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  theme: ThemeData(
    useMaterial3: true,
    colorSchemeSeed: const Color(0xFF2856A6),
    fontFamily: 'NotoSansSC',
  ),
  home: home,
);
