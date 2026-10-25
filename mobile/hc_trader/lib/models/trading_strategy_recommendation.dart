class TradingStrategyAnswerOption {
  const TradingStrategyAnswerOption({required this.key, required this.label});

  final String key;
  final String label;
}

class TradingStrategyQuestion {
  const TradingStrategyQuestion({
    required this.id,
    required this.label,
    required this.options,
    this.required = true,
    this.allowsMultiple = false,
  });

  final String id;
  final String label;
  final List<TradingStrategyAnswerOption> options;
  final bool required;
  final bool allowsMultiple;

  TradingStrategyAnswerOption? optionFor(String? key) {
    if (key == null || key.trim().isEmpty) return null;
    for (final option in options) {
      if (option.key == key) return option;
    }
    return null;
  }

  List<TradingStrategyAnswerOption> optionsFor(String? encodedKeys) {
    final selected = answerKeysFor(encodedKeys).toSet();
    return [
      for (final option in options)
        if (selected.contains(option.key)) option,
    ];
  }
}

class TradingStrategyQuestionSection {
  const TradingStrategyQuestionSection({
    required this.id,
    required this.label,
    required this.questions,
  });

  final String id;
  final String label;
  final List<TradingStrategyQuestion> questions;
}

class TradingStrategyQuestionnaireResponse {
  const TradingStrategyQuestionnaireResponse({this.answers = const {}});

  final Map<String, String> answers;

  String? answerFor(String questionId) => answers[questionId];

  List<String> answersFor(String questionId) =>
      answerKeysFor(answers[questionId]);

  TradingStrategyQuestionnaireResponse putAnswer({
    required String questionId,
    required String answerKey,
  }) {
    return TradingStrategyQuestionnaireResponse(
      answers: {...answers, questionId: answerKey},
    );
  }

  TradingStrategyQuestionnaireResponse toggleAnswer({
    required String questionId,
    required String answerKey,
  }) {
    final selected = answersFor(questionId).toList();
    if (selected.contains(answerKey)) {
      selected.remove(answerKey);
    } else {
      selected.add(answerKey);
    }
    final next = {...answers};
    if (selected.isEmpty) {
      next.remove(questionId);
    } else {
      next[questionId] = selected.join(_multiAnswerSeparator);
    }
    return TradingStrategyQuestionnaireResponse(answers: next);
  }

  bool isComplete(List<TradingStrategyQuestionSection> sections) {
    return missingQuestions(sections).isEmpty;
  }

  List<TradingStrategyQuestion> missingQuestions(
    List<TradingStrategyQuestionSection> sections,
  ) {
    return [
      for (final section in sections)
        for (final question in section.questions)
          if (question.required &&
              (question.allowsMultiple
                  ? question.optionsFor(answers[question.id]).isEmpty
                  : question.optionFor(answers[question.id]) == null))
            question,
    ];
  }

  Map<String, String> answerLabelMap(
    List<TradingStrategyQuestionSection> sections,
  ) {
    final labels = <String, String>{};
    for (final section in sections) {
      for (final question in section.questions) {
        if (question.allowsMultiple) {
          final options = question.optionsFor(answers[question.id]);
          if (options.isNotEmpty) {
            labels[question.id] = options
                .map((option) => option.label)
                .join(', ');
          }
        } else {
          final option = question.optionFor(answers[question.id]);
          if (option != null) labels[question.id] = option.label;
        }
      }
    }
    return labels;
  }

  Map<String, Object?> toJson() => {'answers': answers};

  factory TradingStrategyQuestionnaireResponse.fromJson(
    Map<String, dynamic>? json,
  ) {
    final raw = json?['answers'];
    return TradingStrategyQuestionnaireResponse(
      answers: raw is Map
          ? raw.map((key, value) => MapEntry('$key', '$value'))
          : const {},
    );
  }
}

const _multiAnswerSeparator = '|';

List<String> answerKeysFor(String? encodedKeys) {
  if (encodedKeys == null || encodedKeys.trim().isEmpty) return const [];
  return encodedKeys
      .split(_multiAnswerSeparator)
      .map((key) => key.trim())
      .where((key) => key.isNotEmpty)
      .toList();
}

class TradingStrategyRecommendationResult {
  const TradingStrategyRecommendationResult({
    required this.strategyName,
    required this.strategyCategory,
    required this.technicalConcept,
    required this.suitableMarketCondition,
    required this.recommendedTradingStyle,
    required this.recommendedDirectionTimeframe,
    required this.recommendedEntryTimeframe,
    required this.multiTimeframeConfirmationRule,
    required this.analysisMethods,
    required this.entryModel,
    required this.entryConfirmationRules,
    required this.invalidSetupConditions,
    required this.stopLossPlacementRule,
    required this.takeProfitRule,
    required this.tradeManagementRule,
    required this.exampleSetupChecklist,
    required this.suitableTraderType,
    required this.strategyWarning,
    required this.recommendedPracticeNotes,
    this.selectedStrategyTypes = const [],
    this.selectedMarketConditions = const [],
    this.recommendedStrategySet = '',
    this.marketConditionRules = '',
    this.riskReducedStrategyRules = '',
    this.questionnaireVersion = '2026.07',
  });

  final String questionnaireVersion;
  final String strategyName;
  final String strategyCategory;
  final String technicalConcept;
  final String suitableMarketCondition;
  final String recommendedTradingStyle;
  final String recommendedDirectionTimeframe;
  final String recommendedEntryTimeframe;
  final String multiTimeframeConfirmationRule;
  final List<String> analysisMethods;
  final String entryModel;
  final String entryConfirmationRules;
  final String invalidSetupConditions;
  final String stopLossPlacementRule;
  final String takeProfitRule;
  final String tradeManagementRule;
  final String exampleSetupChecklist;
  final String suitableTraderType;
  final String strategyWarning;
  final String recommendedPracticeNotes;
  final List<String> selectedStrategyTypes;
  final List<String> selectedMarketConditions;
  final String recommendedStrategySet;
  final String marketConditionRules;
  final String riskReducedStrategyRules;

  String get strategyType => strategyCategory;
  String get recommendedMarketCondition => suitableMarketCondition;
  String get majorTimeframe => recommendedDirectionTimeframe;
  String get internalTimeframe => multiTimeframeConfirmationRule;
  String get entryTimeframe => recommendedEntryTimeframe;
  String get recommendedTimeframes =>
      '$recommendedDirectionTimeframe/$recommendedEntryTimeframe';
  String get entryRules => entryConfirmationRules;
  List<String> get confirmationChecklist => _lines(exampleSetupChecklist);
  String get stopLossRules => stopLossPlacementRule;
  String get takeProfitRules => takeProfitRule;
  String get invalidationRule => invalidSetupConditions;
  String get recommendedImprovementNotes => recommendedPracticeNotes;

  List<String> _lines(String value) {
    return value
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .toList();
  }
}
