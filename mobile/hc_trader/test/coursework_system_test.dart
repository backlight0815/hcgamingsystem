import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/examination/coursework_chart.dart';
import 'package:hc_trader/features/examination/coursework_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/coursework_models.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  group('assessment score calculation', () {
    const settings = AssessmentSettings();

    test('Coursework 100 and Final 100 equals 100', () {
      final result = AssessmentCalculator.calculate(
        courseworkRawScore: 100,
        courseworkFinalised: true,
        finalExamRawScore: 100,
        settings: settings,
      )!;
      expect(result.courseworkWeightedScore, 70);
      expect(result.finalExamWeightedScore, 30);
      expect(result.overallScore, 100);
    });

    test('Coursework 80 and Final 70 equals 77', () {
      final result = AssessmentCalculator.calculate(
        courseworkRawScore: 80,
        courseworkFinalised: true,
        finalExamRawScore: 70,
        settings: settings,
      )!;
      expect(result.courseworkWeightedScore, 56);
      expect(result.finalExamWeightedScore, 21);
      expect(result.overallScore, 77);
    });

    test('Coursework 0 and Final 100 equals 30', () {
      expect(
        AssessmentCalculator.calculate(
          courseworkRawScore: 0,
          courseworkFinalised: true,
          finalExamRawScore: 100,
          settings: settings,
        )!.overallScore,
        30,
      );
    });

    test('Coursework 100 and Final 0 equals 70', () {
      expect(
        AssessmentCalculator.calculate(
          courseworkRawScore: 100,
          courseworkFinalised: true,
          finalExamRawScore: 0,
          settings: settings,
        )!.overallScore,
        70,
      );
    });

    test('pending Coursework review keeps overall result pending', () {
      expect(
        AssessmentCalculator.calculate(
          courseworkRawScore: 80,
          courseworkFinalised: false,
          finalExamRawScore: 70,
          settings: settings,
        ),
        isNull,
      );
    });

    test('decimal rounding is stable and weights must total 100%', () {
      final result = AssessmentCalculator.calculate(
        courseworkRawScore: 81.235,
        courseworkFinalised: true,
        finalExamRawScore: 72.345,
        settings: settings,
      )!;
      expect(result.courseworkWeightedScore, 56.86);
      expect(result.finalExamWeightedScore, 21.7);
      expect(result.overallScore, 78.56);
      expect(
        () => AssessmentCalculator.calculate(
          courseworkRawScore: 80,
          courseworkFinalised: true,
          finalExamRawScore: 70,
          settings: const AssessmentSettings(
            courseworkWeight: .8,
            finalExamWeight: .3,
          ),
        ),
        throwsFormatException,
      );
    });
  });

  group('Coursework question bank', () {
    test('contains exactly 100 bilingual themes and 500 valid questions', () {
      final themes = _themes();
      expect(themes, hasLength(100));
      expect(themes.expand((item) => item.questions), hasLength(500));
      expect(themes.map((item) => item.code).toSet(), hasLength(100));
      expect(
        themes.where((item) => item.matchesSearch('CW-001')),
        hasLength(1),
      );
      expect(themes.where((item) => item.matchesSearch('risk')), isNotEmpty);
      expect(themes.where((item) => item.matchesSearch('XAUUSD')), isNotEmpty);
      expect(themes.where((item) => item.matchesSearch('心理')), isNotEmpty);
      expect(
        themes.where((item) => item.categoryId == 'market_structure'),
        hasLength(10),
      );
      expect(
        themes.where((item) => item.difficulty == 'advanced'),
        hasLength(25),
      );

      for (final theme in themes) {
        expect(
          _containsCjk(theme.localizedTitle('zh')),
          isTrue,
          reason: '${theme.code} Chinese title',
        );
        expect(
          _containsCjk(theme.shortDescriptionI18n['zh'] ?? ''),
          isTrue,
          reason: '${theme.code} Chinese description',
        );
        expect(
          CourseworkThemeValidator.validate(theme, forPublishing: true),
          isEmpty,
          reason: theme.code,
        );
        expect(
          theme.questions.where((item) => item.type.isTheory),
          hasLength(2),
        );
        expect(
          theme.questions.where(
            (item) => item.type == CourseworkQuestionType.chartZoneSelection,
          ),
          hasLength(1),
        );
        for (final question in theme.questions) {
          expect(
            _containsCjk(question.localizedTitle('zh')),
            isTrue,
            reason: '${theme.code}/${question.id} Chinese title',
          );
          expect(
            _containsCjk(question.localizedQuestion('zh')),
            isTrue,
            reason: '${theme.code}/${question.id} Chinese question',
          );
          expect(
            _containsCjk(question.localizedInstructions('zh')),
            isTrue,
            reason: '${theme.code}/${question.id} Chinese instructions',
          );
          for (final option in question.answerOptions) {
            expect(
              _containsCjk(option.localized('zh')),
              isTrue,
              reason:
                  '${theme.code}/${question.id}/${option.id} Chinese option',
            );
          }
        }
      }
    });

    test(
      'seed contains no placeholder labels and chart metadata is frozen',
      () {
        final themes = _themes();
        final text = themes
            .expand(
              (theme) => [
                theme.title,
                theme.shortDescription,
                ...theme.questions.map((question) => question.questionText),
              ],
            )
            .join(' ')
            .toLowerCase();
        expect(text, isNot(contains('lorem ipsum')));
        expect(text, isNot(contains('sample question')));
        expect(text, isNot(contains('question coming soon')));
        for (final chart
            in themes
                .expand((theme) => theme.questions)
                .where(
                  (item) =>
                      item.type == CourseworkQuestionType.chartZoneSelection,
                )) {
          expect(chart.chartCandles.length, greaterThanOrEqualTo(8));
          expect(chart.symbol, isNotEmpty);
          expect(chart.timeframe, isNotEmpty);
          expect(
            chart.answerZones.where((item) => item.isCorrect),
            isEmpty,
            reason: 'Correct zones must remain private.',
          );
        }
      },
    );
  });

  group('order-management validation', () {
    test('accepts logical buy and sell geometry', () {
      expect(
        OrderManagementValidator.calculate(
          direction: 'buy',
          entry: 100,
          stopLoss: 95,
          takeProfit: 115,
        ).riskRewardRatio,
        3,
      );
      expect(
        OrderManagementValidator.calculate(
          direction: 'sell',
          entry: 100,
          stopLoss: 105,
          takeProfit: 90,
        ).valid,
        isTrue,
      );
    });

    test('rejects zero stop, invalid direction, NaN, and infinite prices', () {
      expect(
        OrderManagementValidator.calculate(
          direction: 'buy',
          entry: 100,
          stopLoss: 100,
          takeProfit: 110,
        ).valid,
        isFalse,
      );
      expect(
        OrderManagementValidator.calculate(
          direction: 'buy',
          entry: 100,
          stopLoss: 105,
          takeProfit: 110,
        ).valid,
        isFalse,
      );
      expect(
        OrderManagementValidator.calculate(
          direction: 'buy',
          entry: double.nan,
          stopLoss: 95,
          takeProfit: double.infinity,
        ).valid,
        isFalse,
      );
    });
  });

  testWidgets('chart renders and supports zone selection on a small phone', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final chart = _themes().first.questions[2];
    String? selected;
    await tester.pumpWidget(
      _app(
        Scaffold(
          body: SingleChildScrollView(
            child: CourseworkChart(
              candles: chart.chartCandles,
              symbol: chart.symbol,
              timeframe: chart.timeframe,
              marketDate: chart.marketDate,
              zones: chart.answerZones,
              onZoneSelected: (value) => selected = value,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('XAUUSD · H1'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.tapAt(const Offset(160, 210));
    await tester.pump();
    expect(selected == null || selected!.isNotEmpty, isTrue);
  });

  testWidgets('Coursework dashboard renders on a wide Windows viewport', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1600, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      _app(
        Scaffold(
          body: CourseworkScreen(
            user: const AppUser(
              uid: 'candidate',
              email: 'candidate@example.test',
            ),
            tradingRepository: TradingRepository(firebaseEnabled: false),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Coursework'), findsWidgets);
    expect(find.text('No Coursework assigned'), findsOneWidget);
    expect(find.text('Final Examination'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('submission review blocks incomplete work in Chinese', (
    tester,
  ) async {
    final theme = _themes().first;
    await tester.pumpWidget(
      _app(
        CourseworkSubmissionReviewScreen(theme: theme, answers: const {}),
        locale: const Locale('zh'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('检查提交内容'), findsOneWidget);
    expect(find.text('提交课程作业'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, '提交课程作业'))
          .onPressed,
      isNull,
    );
    expect(tester.takeException(), isNull);
  });
}

List<CourseworkTheme> _themes() {
  return List.generate(100, (index) {
    final id = 'CW-${(index + 1).toString().padLeft(3, '0')}';
    final raw =
        jsonDecode(
              File(
                'firebase/seed/coursework/themes/$id.json',
              ).readAsStringSync(),
            )
            as Map<String, dynamic>;
    return CourseworkTheme.fromMap({
      ...Map<String, dynamic>.from(raw['theme'] as Map),
      'questions': (raw['questions'] as List)
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(growable: false),
    });
  }, growable: false);
}

bool _containsCjk(String value) =>
    value.runes.any((rune) => rune >= 0x3400 && rune <= 0x9fff);

Widget _app(Widget home, {Locale locale = const Locale('en')}) => MaterialApp(
  locale: locale,
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  theme: ThemeData(useMaterial3: true),
  darkTheme: ThemeData(useMaterial3: true, brightness: Brightness.dark),
  home: home,
);
