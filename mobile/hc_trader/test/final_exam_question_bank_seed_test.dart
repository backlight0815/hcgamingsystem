import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/localization/localized_value.dart';
import 'package:hc_trader/features/examination/final_exam_screen.dart';
import 'package:hc_trader/models/final_exam_mobile.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  test('final exam seed contains exactly 500 localized questions', () {
    final questions = _seedQuestions();
    final categories = <String, int>{};
    final difficulties = <String, int>{};
    final ids = <String>{};
    final englishQuestions = <String>{};
    final chineseQuestions = <String>{};

    for (final question in questions) {
      final id = question['questionId'] as String;
      expect(ids.add(id), isTrue, reason: id);
      final category = question['category'] as String;
      categories[category] = (categories[category] ?? 0) + 1;
      final difficulty = question['difficulty'] as String;
      difficulties[difficulty] = (difficulties[difficulty] ?? 0) + 1;

      final locales = Map<String, dynamic>.from(question['locales'] as Map);
      final en = Map<String, dynamic>.from(locales['en'] as Map);
      final zh = Map<String, dynamic>.from(locales['zh'] as Map);
      expect(en['question'], isNotEmpty);
      expect(zh['question'], isNotEmpty);
      expect(englishQuestions.add(en['question'] as String), isTrue);
      expect(chineseQuestions.add(zh['question'] as String), isTrue);
      expect(en['options'], hasLength(4));
      expect(zh['options'], hasLength(4));
      expect(question['correctOptionIndex'], inInclusiveRange(0, 3));
      expect(en['explanation'], isNotEmpty);
      expect(zh['explanation'], isNotEmpty);
      expect(question['tags'], isNotEmpty);
    }

    expect(questions, hasLength(500));
    expect(categories, {'theory': 150, 'technical': 250, 'psychology': 100});
    expect(difficulties, {
      'beginner': 200,
      'intermediate': 200,
      'advanced': 100,
    });
  });

  test('final exam mobile model parses locales and correctOptionIndex', () {
    final raw = _seedQuestions().first;
    final question = FinalExamQuestionMobile.fromJson({
      ...raw,
      'attempt_question_id': raw['questionId'],
      'position': 1,
    });

    final expectedCorrect = String.fromCharCode(
      65 + (raw['correctOptionIndex'] as int),
    );
    expect(question.options, hasLength(4));
    expect(question.answerKey, expectedCorrect);
    expect(question.localizedQuestion('en'), raw['locales']['en']['question']);
    expect(
      question.localizedQuestion('zh_CN'),
      raw['locales']['zh']['question'],
    );
    expect(question.localizedOption('A', 'zh_Hans'), isNotEmpty);
    expect(question.localizedExplanation('zh'), isNotEmpty);
  });

  test(
    'localized final exam helper falls back and records missing zh fields',
    () {
      final text = getLocalizedExamText({
        'locales': {
          'en': {
            'question': 'What is risk management?',
            'options': ['Risk control', 'Guessing', 'Overtrading', 'No stop'],
            'explanation': 'Risk management defines planned exposure.',
          },
        },
      }, 'zh_Hans');

      expect(text.question, 'What is risk management?');
      expect(text.options, hasLength(4));
      expect(text.explanation, 'Risk management defines planned exposure.');
      expect(text.missingTranslations, contains('locales.zh.question'));
      expect(text.missingTranslations, contains('locales.zh.options[0]'));
      expect(text.missingTranslations, contains('locales.zh.explanation'));
    },
  );

  testWidgets('final exam screen renders English seed question and review', (
    tester,
  ) async {
    final raw = _seedQuestions().first;
    final repository = _FakeFinalExamRepository(raw);
    final en = _localeBlock(raw, 'en');
    final options = _localeOptions(raw, 'en');
    final correctOption =
        '${repository.correctKey}. ${options[repository.correctOptionIndex]}';

    await tester.pumpWidget(
      _examApp(repository: repository, locale: const Locale('en')),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Start examination'));
    await tester.pumpAndSettle();

    expect(find.text(en['question'] as String), findsOneWidget);
    expect(find.text(correctOption), findsOneWidget);

    await tester.tap(find.text(correctOption));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit'));
    await tester.pumpAndSettle();

    expect(find.text('Passed'), findsOneWidget);
    expect(find.text('Explanation'), findsOneWidget);
    expect(find.text(en['explanation'] as String), findsOneWidget);
  });

  testWidgets('final exam screen renders Chinese seed question and review', (
    tester,
  ) async {
    final raw = _seedQuestions().first;
    final repository = _FakeFinalExamRepository(raw);
    final zh = _localeBlock(raw, 'zh');
    final options = _localeOptions(raw, 'zh');
    final l10n = AppLocalizations(const Locale('zh'));
    final correctOption =
        '${repository.correctKey}. ${options[repository.correctOptionIndex]}';

    await tester.pumpWidget(
      _examApp(repository: repository, locale: const Locale('zh')),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text(l10n.text('Start examination')));
    await tester.pumpAndSettle();

    expect(find.text(zh['question'] as String), findsOneWidget);
    expect(find.text(correctOption), findsOneWidget);

    await tester.tap(find.text(correctOption));
    await tester.pumpAndSettle();
    await tester.tap(find.text(l10n.text('Submit')));
    await tester.pumpAndSettle();

    expect(find.text(l10n.text('Passed')), findsOneWidget);
    expect(find.text(l10n.text('Explanation')), findsOneWidget);
    expect(find.text(zh['explanation'] as String), findsOneWidget);
  });
}

List<Map<String, dynamic>> _seedQuestions() {
  final file = File('assets/seed/final_exam_questions_500.json');
  final raw = jsonDecode(file.readAsStringSync()) as List;
  return raw
      .map((item) => Map<String, dynamic>.from(item as Map))
      .toList(growable: false);
}

Map<String, dynamic> _localeBlock(Map<String, dynamic> question, String code) {
  final locales = Map<String, dynamic>.from(question['locales'] as Map);
  return Map<String, dynamic>.from(locales[code] as Map);
}

List<String> _localeOptions(Map<String, dynamic> question, String code) {
  final rawOptions = _localeBlock(question, code)['options'] as List;
  return rawOptions.map((item) => item.toString()).toList(growable: false);
}

Widget _examApp({
  required _FakeFinalExamRepository repository,
  required Locale locale,
}) {
  return MaterialApp(
    locale: locale,
    supportedLocales: AppLocalizations.supportedLocales,
    localizationsDelegates: const [
      AppLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: FinalExamScreen(repository: repository),
  );
}

class _FakeFinalExamRepository extends TradingRepository {
  _FakeFinalExamRepository(this.rawQuestion) : super(firebaseEnabled: false);

  final Map<String, dynamic> rawQuestion;

  int get correctOptionIndex => rawQuestion['correctOptionIndex'] as int;

  String get correctKey => String.fromCharCode(65 + correctOptionIndex);

  String get attemptQuestionId => '${rawQuestion['questionId']}-attempt';

  Map<String, dynamic> _attemptPayload({
    required String status,
    required Map<String, String> answers,
  }) {
    final correct = answers[attemptQuestionId]?.toUpperCase() == correctKey;
    return {
      'id': 'attempt-seed-ui',
      'status': status,
      'total_questions': 1,
      'correct_answers': status == 'in_progress' ? 0 : (correct ? 1 : 0),
      'wrong_answers': status == 'in_progress' ? 0 : (correct ? 0 : 1),
      'score_percentage': status == 'in_progress'
          ? 0.0
          : (correct ? 100.0 : 0.0),
      'answers': answers,
      'questions': [
        {
          ...rawQuestion,
          'question_id': rawQuestion['questionId'],
          'attempt_question_id': attemptQuestionId,
          'position': 1,
        },
      ],
    };
  }

  @override
  Future<FinalExamStatusMobile> finalExamStatus() async {
    return const FinalExamStatusMobile(
      required: true,
      passed: false,
      unlocked: false,
      allowed: true,
      passingPercentage: 60,
      message: '',
      currentAttempt: null,
      recentAttempts: [],
      propFirmModels: [],
    );
  }

  @override
  Future<FinalExamAttemptMobile> startFinalExam() async {
    return FinalExamAttemptMobile.fromJson(
      _attemptPayload(status: 'in_progress', answers: const {}),
    );
  }

  @override
  Future<Map<String, dynamic>> submitFinalExam({
    required String attemptId,
    required Map<String, String> answers,
  }) async {
    return {'attempt': _attemptPayload(status: 'passed', answers: answers)};
  }
}
