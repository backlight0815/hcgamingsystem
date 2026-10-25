import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/admin_permissions.dart';
import 'package:hc_trader/features/auth/auth_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/market_analysis_item.dart';
import 'package:hc_trader/models/market_analyst_models.dart';
import 'package:hc_trader/models/trading_exam_models.dart';
import 'package:hc_trader/repositories/auth_repository.dart';

void main() {
  group('daily chart quiz schema', () {
    test('parses screenshot-analysis validation and chart fields', () {
      final question = TradingExamQuestionMobile.fromJson({
        'id': 'chart-1',
        'question_text': 'Mark the invalidation and explain your bias.',
        'question_type': 'chart_screenshot_analysis',
        'status': 'published',
        'version': 4,
        'chart_image_url': 'https://example.test/chart.png',
        'requires_screenshot': true,
        'requires_written_explanation': true,
        'minimum_explanation_characters': 80,
      });

      expect(question.isScreenshotAnalysis, isTrue);
      expect(question.needsScreenshot, isTrue);
      expect(question.needsWrittenExplanation, isTrue);
      expect(question.hasPublishedChart, isTrue);
      expect(question.version, 4);
      expect(question.minimumExplanationCharacters, 80);
      expect(question.questionType.requiresChoice, isFalse);
    });

    test('response draft emits only supplied answer artifacts', () {
      const draft = TradingQuizResponseDraft(
        questionId: 'chart-1',
        questionVersion: 3,
        writtenExplanation: 'Structured analysis',
        screenshotStoragePath: 'quiz_submissions/chart-1/user/exam/file.png',
      );
      expect(draft.toJson()['selectedIndex'], isNull);
      expect(draft.toJson()['questionVersion'], 3);
      expect(draft.toJson()['screenshotStoragePath'], contains('chart-1'));
    });
  });

  group('market analyst performance', () {
    test('accuracy includes evaluated outcomes only', () {
      final performance = MarketAnalystPerformance.fromAnalyses(const [
        MarketAnalysisItem(id: 'a', title: 'A', outcomeStatus: 'correct'),
        MarketAnalysisItem(id: 'b', title: 'B', outcomeStatus: 'incorrect'),
        MarketAnalysisItem(id: 'c', title: 'C', outcomeStatus: 'open'),
      ]);
      expect(performance.totalPublished, 3);
      expect(performance.evaluated, 2);
      expect(performance.accurate, 1);
      expect(performance.accuracyPercentage, 50);
    });
  });

  test('multi-role administrator permission uses effective roles', () {
    const user = AppUser(
      uid: 'admin',
      email: 'admin@example.test',
      roleId: 750,
      roleIds: {750, 2},
    );
    expect(user.hasPermission(AdminPermission.dashboardView), isTrue);
    expect(user.hasPermission(AdminPermission.examResultReview), isTrue);
  });

  testWidgets('registration preserves inbound referral for signed-out user', (
    tester,
  ) async {
    final repository = _FakeAuthRepository(null);
    await tester.pumpWidget(
      _localizedApp(
        AuthScreen(
          repository: repository,
          firebaseEnabled: false,
          initialReferralCode: '99559LXY',
          campaignId: 'qr-july',
          registrationSource: 'qr',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('99559LXY'), findsOneWidget);
    expect(find.text('Campaign: qr-july'), findsOneWidget);
  });

  testWidgets('registration referral input is hidden for logged-in user', (
    tester,
  ) async {
    final repository = _FakeAuthRepository(
      const AppUser(uid: 'user-a', email: 'user@example.test', roleId: 750),
    );
    await tester.pumpWidget(
      _localizedApp(AuthScreen(repository: repository, firebaseEnabled: false)),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Register'));
    await tester.pumpAndSettle();

    expect(find.text('Referral code (optional)'), findsNothing);
  });
}

Widget _localizedApp(Widget home) => MaterialApp(
  locale: const Locale('en'),
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
  _FakeAuthRepository(this._currentUser);

  final AppUser? _currentUser;

  @override
  AppUser? get currentUser => _currentUser;

  @override
  Future<List<RegistrationRole>> registrationRoles() async => const [
    RegistrationRole(id: 750, name: 'Trader'),
  ];

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
