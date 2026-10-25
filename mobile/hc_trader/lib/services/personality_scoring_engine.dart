import '../models/personality_assessment.dart';

class PersonalityScoringException implements Exception {
  const PersonalityScoringException(this.message);
  final String message;
  @override
  String toString() => message;
}

class PersonalityScoringEngine {
  const PersonalityScoringEngine();

  PersonalityAssessmentResult score({
    required String attemptId,
    required String questionVersion,
    required Iterable<PersonalityQuestion> questions,
    required Iterable<PersonalityAnswer> answers,
    DateTime? submittedAt,
    String? previousStableType,
  }) {
    if (questionVersion != PersonalityQuestionBank.testVersion) {
      throw const PersonalityScoringException('Invalid question version.');
    }
    final activeQuestions = questions
        .where((question) => question.active)
        .toList(growable: false);
    if (activeQuestions.any(
      (question) => question.testVersion != questionVersion,
    )) {
      throw const PersonalityScoringException('Invalid question version.');
    }
    final answerMap = <String, int>{};
    for (final answer in answers) {
      if (answerMap.containsKey(answer.questionId)) {
        throw const PersonalityScoringException('Duplicate answer IDs.');
      }
      if (answer.value < 1 || answer.value > 7) {
        throw const PersonalityScoringException(
          'An answer is outside the 1 to 7 scale.',
        );
      }
      answerMap[answer.questionId] = answer.value;
    }
    final questionIds = activeQuestions.map((question) => question.id).toSet();
    if (answerMap.keys.any((id) => !questionIds.contains(id)) ||
        activeQuestions.any(
          (question) => !answerMap.containsKey(question.id),
        )) {
      throw const PersonalityScoringException(
        'Answer every required question before submitting.',
      );
    }

    final scores = <PersonalityDimensionScore>[];
    for (final dimension in PersonalityDimension.values) {
      final dimensionQuestions = activeQuestions
          .where((question) => question.dimension == dimension)
          .toList(growable: false);
      if (dimensionQuestions.length < 2) {
        throw const PersonalityScoringException(
          'A personality dimension has insufficient questions.',
        );
      }
      var firstRaw = 0.0;
      var secondRaw = 0.0;
      var tieBreaker = 0.0;
      for (final question in dimensionQuestions) {
        final original = answerMap[question.id]!;
        final adjusted = question.reverseScored ? 8 - original : original;
        firstRaw += adjusted * question.weight;
        secondRaw += (8 - adjusted) * question.weight;
        if (question.tieBreaker) {
          tieBreaker += (adjusted - 4) * question.weight;
        }
      }
      final total = firstRaw + secondRaw;
      final firstPercent = total == 0 ? 50.0 : firstRaw / total * 100;
      final secondPercent = 100.0 - firstPercent;
      final tied = (firstRaw - secondRaw).abs() < .0000001;
      var tieRule = '';
      late String winner;
      if (!tied) {
        winner = firstRaw > secondRaw
            ? dimension.firstLetter
            : dimension.secondLetter;
      } else if (tieBreaker.abs() >= .0000001) {
        winner = tieBreaker > 0
            ? dimension.firstLetter
            : dimension.secondLetter;
        tieRule = 'secondary_question';
      } else if (_previousLetter(previousStableType, dimension)
          case final previous?) {
        winner = previous;
        tieRule = 'previous_stable_result';
      } else {
        // Published neutral fallback: I/S/T/P. The UI still labels the
        // preference balanced and discloses that a neutral rule was used.
        winner = const {
          PersonalityDimension.energy: 'I',
          PersonalityDimension.information: 'S',
          PersonalityDimension.decisions: 'T',
          PersonalityDimension.structure: 'P',
        }[dimension]!;
        tieRule = 'published_neutral_rule';
      }
      final winningPercent = firstPercent > secondPercent
          ? firstPercent
          : secondPercent;
      scores.add(
        PersonalityDimensionScore(
          dimension: dimension,
          firstRaw: firstRaw,
          secondRaw: secondRaw,
          firstPercent: firstPercent,
          secondPercent: secondPercent,
          winningLetter: winner,
          strength: _strength(winningPercent, tied),
          balanced: tied || winningPercent < 53,
          tieRule: tieRule,
        ),
      );
    }

    return PersonalityAssessmentResult(
      attemptId: attemptId,
      type: scores.map((score) => score.winningLetter).join(),
      testVersion: questionVersion,
      scoringVersion: PersonalityQuestionBank.scoringVersion,
      guidanceVersion: PersonalityQuestionBank.guidanceVersion,
      dimensionScores: scores,
      submittedAt: submittedAt ?? DateTime.now(),
    );
  }

  PreferenceStrength _strength(double percent, bool tied) {
    if (tied || percent < 53) return PreferenceStrength.balanced;
    if (percent < 55) return PreferenceStrength.slight;
    if (percent < 65) return PreferenceStrength.moderate;
    return PreferenceStrength.strong;
  }

  String? _previousLetter(String? type, PersonalityDimension dimension) {
    if (type == null || type.length != 4) return null;
    final letter = type[PersonalityDimension.values.indexOf(dimension)];
    return {dimension.firstLetter, dimension.secondLetter}.contains(letter)
        ? letter
        : null;
  }
}
