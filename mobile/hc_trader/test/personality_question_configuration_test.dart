import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/personality_assessment.dart';
import 'package:hc_trader/models/personality_question_configuration.dart';

void main() {
  group('personality question-set validation', () {
    List<PersonalityQuestionConfiguration> validQuestions() => [
      for (final question in PersonalityQuestionBank.questions)
        PersonalityQuestionConfiguration.fromBuiltIn(
          question,
          textZh: '中文 ${question.id}',
        ),
    ];

    test('accepts the balanced localized built-in bank', () {
      expect(
        PersonalityQuestionSetValidator.validate(
          PersonalityQuestionBank.testVersion,
          validQuestions(),
        ),
        isEmpty,
      );
    });

    test('rejects missing translations and version mismatches', () {
      final questions = validQuestions();
      questions[0] = PersonalityQuestionConfiguration(
        id: questions[0].id,
        textEn: questions[0].textEn,
        textZh: '',
        dimension: questions[0].dimension,
        direction: questions[0].direction,
        reverseScored: questions[0].reverseScored,
        weight: questions[0].weight,
        active: questions[0].active,
        testVersion: 'different-version',
        displayOrder: questions[0].displayOrder,
        randomizationGroup: questions[0].randomizationGroup,
        tieBreaker: questions[0].tieBreaker,
      );

      final errors = PersonalityQuestionSetValidator.validate(
        PersonalityQuestionBank.testVersion,
        questions,
      );
      expect(errors, contains(contains('both translations')));
    });

    test('rejects an unbalanced or undersized published set', () {
      final questions = validQuestions()
          .where(
            (question) => question.dimension == PersonalityDimension.energy,
          )
          .toList();
      final errors = PersonalityQuestionSetValidator.validate(
        PersonalityQuestionBank.testVersion,
        questions,
      );

      expect(errors, contains(contains('32 to 48')));
      expect(errors, contains(contains('Each dimension')));
    });
  });
}
