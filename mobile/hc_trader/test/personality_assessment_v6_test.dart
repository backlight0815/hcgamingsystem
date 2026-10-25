import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/psychology/personality_guidance_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/personality_assessment.dart';
import 'package:hc_trader/models/personality_type_profile.dart';
import 'package:hc_trader/repositories/personality_assessment_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/personality_assessment_guidance_service.dart';
import 'package:hc_trader/services/personality_scoring_engine.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const engine = PersonalityScoringEngine();

  group('personality assessment scoring', () {
    test('scores all dimensions, reverse items, weights and percentages', () {
      final result = engine.score(
        attemptId: 'attempt-estj',
        questionVersion: PersonalityQuestionBank.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: _answersFavoringFirstPreference(),
        submittedAt: DateTime.utc(2026, 7, 14),
      );

      expect(result.type, 'ESTJ');
      expect(result.dimensionScores, hasLength(4));
      for (final score in result.dimensionScores) {
        expect(score.firstPercent + score.secondPercent, closeTo(100, .0001));
        expect(score.firstRaw, greaterThan(score.secondRaw));
        expect(score.strength, PreferenceStrength.strong);
      }
      expect(result.scoringVersion, PersonalityQuestionBank.scoringVersion);
      expect(result.guidanceVersion, PersonalityQuestionBank.guidanceVersion);
    });

    test('inverse responses select the second side of every dimension', () {
      final result = engine.score(
        attemptId: 'attempt-infp',
        questionVersion: PersonalityQuestionBank.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: _answersFavoringSecondPreference(),
      );

      expect(result.type, 'INFP');
      expect(
        result.dimensionScores.every(
          (score) => score.secondRaw > score.firstRaw,
        ),
        isTrue,
      );
    });

    test('uses the documented neutral tie rule and marks ties balanced', () {
      final result = engine.score(
        attemptId: 'neutral',
        questionVersion: PersonalityQuestionBank.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: PersonalityQuestionBank.questions.map(
          (question) => PersonalityAnswer(questionId: question.id, value: 4),
        ),
      );

      expect(result.type, 'ISTP');
      expect(
        result.dimensionScores.every(
          (score) =>
              score.balanced &&
              score.strength == PreferenceStrength.balanced &&
              score.tieRule == 'published_neutral_rule',
        ),
        isTrue,
      );
    });

    test('a prior stable result resolves an otherwise neutral tie', () {
      final result = engine.score(
        attemptId: 'neutral-repeat',
        questionVersion: PersonalityQuestionBank.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: PersonalityQuestionBank.questions.map(
          (question) => PersonalityAnswer(questionId: question.id, value: 4),
        ),
        previousStableType: 'ENTJ',
      );

      expect(result.type, 'ENTJ');
      expect(
        result.dimensionScores.every(
          (score) => score.tieRule == 'previous_stable_result',
        ),
        isTrue,
      );
    });

    test('rejects missing, duplicate, out-of-range and wrong-version data', () {
      final answers = _answersFavoringFirstPreference().toList();
      expect(
        () => engine.score(
          attemptId: 'missing',
          questionVersion: PersonalityQuestionBank.testVersion,
          questions: PersonalityQuestionBank.questions,
          answers: answers.sublist(1),
        ),
        throwsA(isA<PersonalityScoringException>()),
      );
      expect(
        () => engine.score(
          attemptId: 'duplicate',
          questionVersion: PersonalityQuestionBank.testVersion,
          questions: PersonalityQuestionBank.questions,
          answers: [...answers, answers.first],
        ),
        throwsA(isA<PersonalityScoringException>()),
      );
      expect(
        () => engine.score(
          attemptId: 'range',
          questionVersion: PersonalityQuestionBank.testVersion,
          questions: PersonalityQuestionBank.questions,
          answers: [
            PersonalityAnswer(questionId: answers.first.questionId, value: 8),
            ...answers.skip(1),
          ],
        ),
        throwsA(isA<PersonalityScoringException>()),
      );
      expect(
        () => engine.score(
          attemptId: 'version',
          questionVersion: 'unknown-version',
          questions: PersonalityQuestionBank.questions,
          answers: answers,
        ),
        throwsA(isA<PersonalityScoringException>()),
      );
    });
  });

  group('personality persistence and guidance', () {
    test('progress resumes and completed attempts remain immutable', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final repository = PersonalityAssessmentRepository(
        firebaseEnabled: false,
        storage: storage,
        now: () => DateTime.utc(2026, 7, 14, 2),
      );
      final attempt = repository.createAttempt(userId: 'trader-1');
      final progress = attempt.copyWith(answers: {'ei_01': 7});
      await repository.saveProgress(progress);

      final resumed = await repository.loadAttempts('trader-1');
      expect(resumed.single.answers, {'ei_01': 7});
      final result = engine.score(
        attemptId: attempt.id,
        questionVersion: attempt.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: _answersFavoringFirstPreference(),
      );
      final completed = await repository.complete(progress, result);
      expect(completed.isCompleted, isTrue);
      expect(
        (await repository.loadAttempts('trader-1')).single.result?.type,
        'ESTJ',
      );
      await expectLater(
        repository.saveProgress(completed.copyWith(answers: {'ei_01': 1})),
        throwsStateError,
      );

      final same = await repository.complete(progress, result);
      expect(same.result?.type, 'ESTJ');
      final conflicting = _resultForType('ISTP', attempt.id);
      await expectLater(
        repository.complete(progress, conflicting),
        throwsStateError,
      );

      final retake = repository.createAttempt(
        userId: 'trader-1',
        retakeOf: attempt.id,
      );
      await repository.saveProgress(retake);
      final history = await repository.loadAttempts('trader-1');
      expect(history, hasLength(2));
      expect(
        history.singleWhere((value) => value.id == retake.id).retakeOf,
        attempt.id,
      );
      expect(
        history.singleWhere((value) => value.id == attempt.id).isCompleted,
        isTrue,
      );
    });

    test(
      'cloud failures fall back to local history and preserve offline completion',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final localRepository = PersonalityAssessmentRepository(
          firebaseEnabled: false,
          storage: storage,
          now: () => DateTime.utc(2026, 7, 14, 3),
        );
        final attempt = localRepository.createAttempt(userId: 'trader-1');
        final answered = attempt.copyWith(
          answers: {
            for (final question in PersonalityQuestionBank.questions)
              question.id: question.reverseScored ? 1 : 7,
          },
        );
        await localRepository.saveProgress(answered);

        final unavailableRepository = PersonalityAssessmentRepository(
          firebaseEnabled: true,
          storage: storage,
          remoteLoader: (_) async => throw Exception('firestore unavailable'),
          remoteProgressWriter: (_) async =>
              throw Exception('firestore unavailable'),
          remoteCompletionWriter: (_) async =>
              throw Exception('firestore unavailable'),
        );
        final loaded = await unavailableRepository.loadAttempts('trader-1');
        expect(loaded.single.answers, hasLength(32));
        expect(unavailableRepository.takeLastSyncError(), isNotNull);

        final result = engine.score(
          attemptId: attempt.id,
          questionVersion: attempt.testVersion,
          questions: PersonalityQuestionBank.questions,
          answers: _answersFavoringFirstPreference(),
        );
        final completed = await unavailableRepository.complete(
          answered,
          result,
        );
        expect(completed.isCompleted, isTrue);
        expect(unavailableRepository.takeLastSyncError(), isNotNull);

        final localHistory = await localRepository.loadAttempts('trader-1');
        expect(localHistory.single.isCompleted, isTrue);
        expect(localHistory.single.result?.type, 'ESTJ');
      },
    );

    test('malformed local cache does not block the assessment', () async {
      SharedPreferences.setMockInitialValues({
        'hc_mobile_trader-1_personality_assessment_attempts': '{"bad":true}',
      });
      final storage = await LocalAppStorage.create();
      final repository = PersonalityAssessmentRepository(
        firebaseEnabled: false,
        storage: storage,
      );

      expect(await repository.loadAttempts('trader-1'), isEmpty);
    });

    test('guidance is structured, parameterized and only creates a draft', () {
      final result = engine.score(
        attemptId: 'guidance',
        questionVersion: PersonalityQuestionBank.testVersion,
        questions: PersonalityQuestionBank.questions,
        answers: _answersFavoringFirstPreference(),
      );
      final guidance = const PersonalityAssessmentGuidanceService().generate(
        result,
      );

      expect(guidance.maximumRiskPercent, .5);
      expect(guidance.dailyLossLimitPercent, 1);
      expect(guidance.maximumTradesPerDay, 2);
      expect(guidance.suggestions, hasLength(8));
      expect(guidance.suggestions.first.actionKey, contains('{risk}'));
      expect(guidance.suggestions.first.actionValues, {'risk': '0.5'});
      final draft = guidance.draftFor({
        'fixed-risk',
        'cooldown',
      }, result.attemptId);
      expect(draft['attempt_id'], result.attemptId);
      expect(draft['selected_guidance'], hasLength(2));
      expect(draft, isNot(contains('trading_plan')));
      expect(draft, isNot(contains('trading_strategy')));
    });

    test('all generated assessment content is localized in Chinese', () {
      const l10n = AppLocalizations(Locale('zh'));
      final keys = <String>{
        for (final question in PersonalityQuestionBank.questions)
          question.textKey,
        for (final type in const [
          'ISTJ',
          'ISFJ',
          'INFJ',
          'INTJ',
          'ISTP',
          'ISFP',
          'INFP',
          'INTP',
          'ESTP',
          'ESFP',
          'ENFP',
          'ENTP',
          'ESTJ',
          'ESFJ',
          'ENFJ',
          'ENTJ',
        ])
          ..._profileKeys(
            PersonalityTypeCatalog.forResult(_resultForType(type, type)),
          ),
      };
      final guidance = const PersonalityAssessmentGuidanceService().generate(
        _resultForType('ESTP', 'localized-guidance'),
      );
      for (final suggestion in guidance.suggestions) {
        keys
          ..add(suggestion.titleKey)
          ..add(suggestion.actionKey)
          ..add(suggestion.cautionKey);
      }

      for (final key in keys) {
        expect(
          l10n.text(key),
          isNot(key),
          reason: 'Missing Simplified Chinese translation for "$key"',
        );
      }
    });
  });

  group('personality assessment widget flow', () {
    testWidgets(
      'opens the built-in assessment when cloud sync is unavailable',
      (tester) async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final assessments = PersonalityAssessmentRepository(
          firebaseEnabled: true,
          storage: storage,
          remoteLoader: (_) async => throw Exception('permission-denied'),
          remoteProgressWriter: (_) async =>
              throw Exception('permission-denied'),
          remoteCompletionWriter: (_) async =>
              throw Exception('permission-denied'),
        );
        await tester.binding.setSurfaceSize(const Size(900, 1100));
        addTearDown(() => tester.binding.setSurfaceSize(null));

        await _pumpAssessment(tester, storage, assessments);

        expect(find.text('32 questions'), findsOneWidget);
        expect(
          find.text('The personality assessment could not be loaded.'),
          findsNothing,
        );
        expect(
          find.textContaining('Cloud sync is temporarily unavailable'),
          findsOneWidget,
        );
        await tester.tap(
          find.byKey(const ValueKey('personality-start-resume')),
        );
        await tester.pumpAndSettle();
        expect(find.text('Question 1 of 32'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets(
      'requires answers, resumes progress and completes a retake-safe result',
      (tester) async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final assessments = PersonalityAssessmentRepository(
          firebaseEnabled: false,
          storage: storage,
        );
        await tester.binding.setSurfaceSize(const Size(900, 1100));
        addTearDown(() => tester.binding.setSurfaceSize(null));

        await _pumpAssessment(tester, storage, assessments);
        expect(find.text('32 questions'), findsOneWidget);
        expect(
          find.textContaining('not a medical or psychological diagnosis'),
          findsOneWidget,
        );
        await tester.tap(
          find.byKey(const ValueKey('personality-start-resume')),
        );
        await tester.pumpAndSettle();
        await tester.ensureVisible(
          find.byKey(const ValueKey('personality-next')),
        );
        await tester.tap(find.byKey(const ValueKey('personality-next')));
        await tester.pump();
        expect(
          find.text('Select an answer before continuing.'),
          findsOneWidget,
        );

        await _answerCurrentQuestion(tester, 7);
        expect(find.text('Question 2 of 32'), findsOneWidget);
        await tester.tap(find.byTooltip('Exit assessment'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Save and exit'));
        await tester.pumpAndSettle();
        expect(find.text('1 of 32 answered'), findsOneWidget);

        await tester.pumpWidget(const SizedBox.shrink());
        await _pumpAssessment(tester, storage, assessments);
        expect(find.text('Resume assessment'), findsOneWidget);
        await tester.tap(
          find.byKey(const ValueKey('personality-start-resume')),
        );
        await tester.pumpAndSettle();
        expect(find.text('Question 2 of 32'), findsOneWidget);

        for (
          var index = 1;
          index < PersonalityQuestionBank.questions.length;
          index++
        ) {
          await _answerCurrentQuestion(tester, 7);
        }
        expect(find.text('Submit assessment?'), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey('personality-submit')));
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey('personality-result')),
          findsOneWidget,
        );
        expect(find.text('INFP'), findsOneWidget);

        await _scrollUntilBuilt(
          tester,
          find.byKey(const ValueKey('personality-apply-guidance')),
        );
        await tester.tap(
          find.byKey(const ValueKey('personality-apply-guidance')),
        );
        await tester.pumpAndSettle();
        expect(find.textContaining('will not be changed'), findsOneWidget);
        await tester.tap(
          find.byKey(const ValueKey('personality-confirm-draft')),
        );
        await tester.pumpAndSettle();
        expect(
          storage.readList('trader-1', 'personality_guidance_drafts'),
          hasLength(1),
        );
        expect(storage.readList('trader-1', 'trading_plans'), isEmpty);
        expect(storage.readList('trader-1', 'trading_strategies'), isEmpty);

        await tester.tap(find.byTooltip('Retake test'));
        await tester.pumpAndSettle();
        expect(find.text('Question 1 of 32'), findsOneWidget);
        expect((await assessments.loadAttempts('trader-1')), hasLength(2));
        expect(tester.takeException(), isNull);
      },
    );
  });
}

Iterable<PersonalityAnswer> _answersFavoringFirstPreference() =>
    PersonalityQuestionBank.questions.map(
      (question) => PersonalityAnswer(
        questionId: question.id,
        value: question.reverseScored ? 1 : 7,
      ),
    );

Iterable<PersonalityAnswer> _answersFavoringSecondPreference() =>
    PersonalityQuestionBank.questions.map(
      (question) => PersonalityAnswer(
        questionId: question.id,
        value: question.reverseScored ? 7 : 1,
      ),
    );

PersonalityAssessmentResult _resultForType(String type, String attemptId) {
  final scores = <PersonalityDimensionScore>[];
  for (var index = 0; index < PersonalityDimension.values.length; index++) {
    final dimension = PersonalityDimension.values[index];
    final firstWins = type[index] == dimension.firstLetter;
    scores.add(
      PersonalityDimensionScore(
        dimension: dimension,
        firstRaw: firstWins ? 70 : 30,
        secondRaw: firstWins ? 30 : 70,
        firstPercent: firstWins ? 70 : 30,
        secondPercent: firstWins ? 30 : 70,
        winningLetter: type[index],
        strength: PreferenceStrength.strong,
        balanced: false,
        tieRule: '',
      ),
    );
  }
  return PersonalityAssessmentResult(
    attemptId: attemptId,
    type: type,
    testVersion: PersonalityQuestionBank.testVersion,
    scoringVersion: PersonalityQuestionBank.scoringVersion,
    guidanceVersion: PersonalityQuestionBank.guidanceVersion,
    dimensionScores: scores,
    submittedAt: DateTime.utc(2026, 7, 14),
  );
}

Set<String> _profileKeys(PersonalityTypeProfile profile) => {
  profile.titleKey,
  profile.summaryKey,
  ...profile.strengthKeys,
  ...profile.challengeKeys,
  profile.decisionKey,
  profile.planningKey,
  profile.stressKey,
  profile.learningKey,
  profile.communicationKey,
  ...profile.tradingStrengthKeys,
  ...profile.tradingRiskKeys,
  ...profile.suitableStyleKeys,
  profile.riskGuidanceKey,
  profile.disciplineGuidanceKey,
  profile.planSuggestionKey,
  profile.strategySuggestionKey,
};

Future<void> _pumpAssessment(
  WidgetTester tester,
  LocalAppStorage storage,
  PersonalityAssessmentRepository assessments,
) async {
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('en'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: PersonalityGuidanceScreen(
        user: const AppUser(uid: 'trader-1', email: 'trader@example.com'),
        repository: TradingRepository(firebaseEnabled: false, storage: storage),
        storage: storage,
        assessmentRepository: assessments,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _answerCurrentQuestion(WidgetTester tester, int value) async {
  final answer = find.byKey(ValueKey('personality-answer-$value'));
  await tester.ensureVisible(answer);
  await tester.tap(answer);
  await tester.pump();
  final next = find.byKey(const ValueKey('personality-next'));
  await tester.ensureVisible(next);
  await tester.tap(next);
  await tester.pumpAndSettle();
}

Future<void> _scrollUntilBuilt(WidgetTester tester, Finder target) async {
  for (var attempt = 0; attempt < 12 && target.evaluate().isEmpty; attempt++) {
    await tester.drag(
      find.byKey(const ValueKey('personality-result')),
      const Offset(0, -650),
    );
    await tester.pumpAndSettle();
  }
  expect(target, findsOneWidget);
  await tester.ensureVisible(target);
}
