import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/final_exam_mobile.dart';

void main() {
  test(
    'final exam strict Chinese validation covers 50 questions and 200 options',
    () {
      final rawQuestions = _strictFinalExamQuestions();
      final attempt = FinalExamAttemptMobile.fromJson({
        'id': 'strict-localisation-audit',
        'status': 'in_progress',
        'total_questions': rawQuestions.length,
        'questions': rawQuestions,
      });

      expect(attempt.questions, hasLength(50));
      expect(
        attempt.questions.fold<int>(0, (sum, q) => sum + q.options.length),
        200,
      );

      for (var index = 0; index < attempt.questions.length; index++) {
        final raw = rawQuestions[index];
        final question = attempt.questions[index];

        expect(question.options.keys.toList(), ['A', 'B', 'C', 'D']);
        expect(question.answerKey, raw['answer_key']);
        expect(question.options.containsKey(question.answerKey), isTrue);
        expect(question.localizedQuestion('en'), raw['question']);
        expect(
          _hasCjk(question.localizedCategory('zh')),
          isTrue,
          reason: 'category ${index + 1}',
        );
        expect(
          _hasCjk(question.localizedQuestion('zh')),
          isTrue,
          reason: 'question ${index + 1}',
        );
        expect(
          _hasLongEnglishSentence(question.localizedQuestion('zh')),
          isFalse,
          reason: 'question ${index + 1}',
        );

        for (final option in question.options.entries) {
          expect(
            _hasCjk(question.localizedOption(option.key, 'zh')),
            isTrue,
            reason: 'question ${index + 1} option ${option.key}',
          );
          expect(
            _hasLongEnglishSentence(question.localizedOption(option.key, 'zh')),
            isFalse,
            reason: 'question ${index + 1} option ${option.key}',
          );
        }

        if ('${raw['explanation']}'.trim().isNotEmpty) {
          expect(
            _hasCjk(question.localizedExplanation('zh')),
            isTrue,
            reason: 'explanation ${index + 1}',
          );
        }
      }
    },
  );
}

List<Map<String, Object?>> _strictFinalExamQuestions() {
  final questions = <Map<String, Object?>>[];
  var position = 1;

  const foundation = <(String, String)>[
    (
      'Risk Management',
      'Rules controlling how much capital is exposed to loss.',
    ),
    ('Drawdown', 'A decline from account peak to a lower value.'),
    ('Win Rate', 'The percentage of trades that close profitably.'),
    ('Take Profit', 'A planned exit at the profit target.'),
    (
      'Stop Loss',
      'A planned exit that limits loss when the idea is invalidated.',
    ),
    ('Market Order', 'An order intended to execute immediately.'),
    ('Limit Order', 'An order to buy lower or sell higher than current price.'),
    (
      'Trading Journal',
      'A structured record of setup, execution, emotion, and lesson.',
    ),
    (
      'High Impact News',
      'Scheduled events that can increase volatility and slippage.',
    ),
    ('No-Trade Condition', 'A market state where the plan says not to enter.'),
    (
      'Forex Trading',
      'Buying one currency while selling another to profit from exchange-rate movement.',
    ),
    ('Lot Size', 'The volume of a trade position.'),
    (
      'Leverage',
      'Borrowed exposure that lets smaller margin control a larger position.',
    ),
    (
      'Margin',
      'Capital held by the broker to support an open leveraged position.',
    ),
    ('Spread', 'The difference between bid and ask price.'),
  ];

  for (final item in foundation) {
    questions.add(
      _question(
        position: position++,
        category: 'Foundation and Knowledge',
        subcategory: 'Market Basics',
        question:
            'Foundation definition: what does ${item.$1} mean in trading?',
        answer: item.$2,
        explanation:
            'Foundation knowledge becomes useful only when it supports a consistent process.',
      ),
    );
  }

  const technique = <(String, String)>[
    ('Support', 'is an area where buyers reacted'),
    ('Resistance', 'is an area where sellers reacted'),
    ('Higher High', 'is a swing high above the previous meaningful high'),
    ('Higher Low', 'is a pullback low above the previous meaningful low'),
    ('Lower High', 'is a rally high below the previous meaningful high'),
    ('Lower Low', 'is a swing low below the previous meaningful low'),
    ('BOS', 'is a break of structure in the trend direction'),
    ('CHOCH', 'is a change of character that warns momentum may be shifting'),
    ('Breakout', 'price moves beyond a level'),
    ('Retest', 'price returns to a broken level'),
    ('Fakeout', 'price fails a breakout and returns inside range'),
    ('Confirmation Candle', 'wait for confirmation'),
    ('Rejection Wick', 'use with market structure context'),
    ('SL Placement', 'place it where the idea is proven wrong'),
    ('TP Placement', 'avoid unrealistic targets beyond strong opposing zones'),
    ('Breakeven Movement', 'move BE only when the setup has earned protection'),
    ('Layering', 'calculate combined risk before adding'),
    ('Scaling out', 'take partials only by plan'),
    ('FVG', 'use it only when aligned with structure'),
    ('Order Block', 'define why the area matters before entry'),
  ];

  for (final item in technique) {
    questions.add(
      _question(
        position: position++,
        category: 'Technique and Analysis Skills',
        subcategory: 'Market Structure',
        question: 'What best describes ${item.$1}?',
        answer: item.$2,
        explanation:
            'Professional technical analysis connects the concept to entry, invalidation, and risk.',
      ),
    );
  }

  const psychologyScenarios = <String>[
    'The trader was stopped out, then price later reached the original TP area. The trader feels angry and wants to re-enter with double lot.',
    'Price broke out and ran without retesting. The trader missed the planned entry and fears missing profit.',
    'The trade is in profit but has not reached the planned management area. The trader wants to close because of fear.',
    'Price pulls back against the trade but has not broken invalidation. The trader wants to panic close.',
    'Price is close to SL. The trader wants to remove the stop to avoid being wrong.',
    'Price is near TP. The trader feels the move is strong and wants to move TP much further.',
    'The trader has reached daily loss limit. A clean-looking setup appears later.',
    'After three wins, the trader sees a weak setup and feels unusually confident.',
    'After two losses, a fast candle appears and the trader wants to recover quickly.',
    'After a losing trade, the trader wants to risk heavily on the next setup.',
    'After a strong winner, the trader wants to double lot on the next signal.',
    'A confirmation candle is still forming. The trader wants to enter before it closes.',
    'Price breaks out strongly without a retest. The trader wants to chase immediately.',
    'The trade is still valid, but the trader wants to manually close from discomfort.',
    'Price approaches SL and the trader wants to move it wider.',
  ];

  for (final scenario in psychologyScenarios.indexed) {
    questions.add(
      _question(
        position: position++,
        category: 'Psychology',
        subcategory: 'Trade Discipline',
        scenarioText: scenario.$2,
        question:
            'Psychology chart case ${scenario.$1 + 1}: after seeing this chart, what is the best trader behavior?',
        answer: 'Wait for a fresh valid setup.',
        explanation: 'Wait for a fresh valid setup.',
      ),
    );
  }

  return questions;
}

Map<String, Object?> _question({
  required int position,
  required String category,
  required String subcategory,
  required String question,
  required String answer,
  required String explanation,
  String scenarioText = '',
}) {
  const wrongB = 'increase risk without a plan';
  const wrongC = 'ignore setup invalidation';
  const wrongD = 'decide from emotion only';
  final options = {'A': answer, 'B': wrongB, 'C': wrongC, 'D': wrongD};
  return {
    'attempt_question_id': 'q$position',
    'question_id': 'source$position',
    'position': position,
    'category': category,
    'subcategory': subcategory,
    'question_type': scenarioText.isEmpty
        ? 'multiple_choice'
        : 'chart_scenario_mcq',
    'question': question,
    'question_i18n': {'en': question, 'zh': question},
    'scenario_text': scenarioText,
    if (scenarioText.isNotEmpty)
      'scenario_text_i18n': {'en': scenarioText, 'zh': scenarioText},
    'options': options,
    'options_i18n': {
      for (final entry in options.entries)
        entry.key: {'en': entry.value, 'zh': entry.value},
    },
    'answer_key': 'A',
    'explanation': explanation,
    'explanation_i18n': {'en': explanation, 'zh': explanation},
  };
}

bool _hasCjk(String value) => RegExp(r'[\u3400-\u9fff]').hasMatch(value);

bool _hasLongEnglishSentence(String value) =>
    RegExp(r'[A-Za-z]{3,}(?:\s+[A-Za-z]{3,}){2,}').hasMatch(value);
