class TradingPlanAnswerOption {
  const TradingPlanAnswerOption({
    required this.key,
    required this.label,
    this.weight = const {},
  });

  final String key;
  final String label;
  final Map<String, int> weight;
}

class TradingPlanQuestion {
  const TradingPlanQuestion({
    required this.id,
    required this.label,
    required this.options,
    this.required = true,
    this.allowsMultiple = false,
  });

  final String id;
  final String label;
  final List<TradingPlanAnswerOption> options;
  final bool required;
  final bool allowsMultiple;

  TradingPlanAnswerOption? optionFor(String? key) {
    if (key == null || key.trim().isEmpty) return null;
    for (final option in options) {
      if (option.key == key) return option;
    }
    return null;
  }

  List<TradingPlanAnswerOption> optionsFor(String? encodedKeys) {
    final selected = answerKeysFor(encodedKeys).toSet();
    return [
      for (final option in options)
        if (selected.contains(option.key)) option,
    ];
  }
}

class TradingPlanQuestionSection {
  const TradingPlanQuestionSection({
    required this.id,
    required this.label,
    required this.questions,
  });

  final String id;
  final String label;
  final List<TradingPlanQuestion> questions;
}

class TradingPlanQuestionnaireResponse {
  const TradingPlanQuestionnaireResponse({this.answers = const {}});

  final Map<String, String> answers;

  String? answerFor(String questionId) => answers[questionId];

  List<String> answersFor(String questionId) =>
      answerKeysFor(answers[questionId]);

  TradingPlanQuestionnaireResponse putAnswer({
    required String questionId,
    required String answerKey,
  }) {
    return TradingPlanQuestionnaireResponse(
      answers: {...answers, questionId: answerKey},
    );
  }

  TradingPlanQuestionnaireResponse toggleAnswer({
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
    return TradingPlanQuestionnaireResponse(answers: next);
  }

  bool isComplete(List<TradingPlanQuestionSection> sections) {
    return missingQuestions(sections).isEmpty;
  }

  List<TradingPlanQuestion> missingQuestions(
    List<TradingPlanQuestionSection> sections,
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
    List<TradingPlanQuestionSection> sections,
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

  factory TradingPlanQuestionnaireResponse.fromJson(
    Map<String, dynamic>? json,
  ) {
    final raw = json?['answers'];
    return TradingPlanQuestionnaireResponse(
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

class TradingPlanRecommendationResult {
  const TradingPlanRecommendationResult({
    required this.planName,
    required this.traderProfileSummary,
    required this.selectedTradingStrategy,
    required this.accountType,
    required this.tradingObjective,
    required this.riskManagementRules,
    required this.riskPercent,
    required this.maxDailyLossPercent,
    required this.maxWeeklyLossPercent,
    required this.maxTradesPerDay,
    required this.minimumRiskRewardRatio,
    required this.tradingSessionRule,
    required this.tradingRoutine,
    required this.entryPermissionRules,
    required this.psychologyRules,
    required this.personalityWarnings,
    required this.lossRecoveryRules,
    required this.noTradeConditions,
    required this.preTradeChecklist,
    required this.postTradeReviewRules,
    required this.disciplineRules,
    required this.weeklyReviewRules,
    required this.improvementNotes,
    required this.accountRules,
    required this.newsFilterRule,
    required this.propFirmRules,
    required this.fomoPreventionRules,
    required this.revengeTradingPreventionRules,
    required this.afterWinRules,
    required this.afterLossRules,
    required this.afterBreakingRules,
    required this.dailyRoutine,
    required this.weeklyReviewRoutine,
    required this.allowedSessions,
    required this.allowedPairs,
    this.selectedTradingStrategies = const [],
    this.psychologyIssues = const [],
    this.questionnaireVersion = '2026.07',
    this.templateKey = 'recommended_questionnaire',
    this.maxLossesPerDay = 2,
    this.maxConsecutiveLosses = 2,
    this.maxOpenTrades = 1,
    this.cooldownAfterLossMinutes = 30,
  });

  final String questionnaireVersion;
  final String templateKey;
  final String planName;
  final String traderProfileSummary;
  final String selectedTradingStrategy;
  final String accountType;
  final String tradingObjective;
  final String riskManagementRules;
  final double riskPercent;
  final double maxDailyLossPercent;
  final double maxWeeklyLossPercent;
  final int maxTradesPerDay;
  final int maxLossesPerDay;
  final int maxConsecutiveLosses;
  final int maxOpenTrades;
  final double minimumRiskRewardRatio;
  final int cooldownAfterLossMinutes;
  final String tradingSessionRule;
  final String tradingRoutine;
  final String entryPermissionRules;
  final String psychologyRules;
  final String personalityWarnings;
  final String lossRecoveryRules;
  final String noTradeConditions;
  final String preTradeChecklist;
  final String postTradeReviewRules;
  final String disciplineRules;
  final String weeklyReviewRules;
  final String improvementNotes;
  final String accountRules;
  final String newsFilterRule;
  final String propFirmRules;
  final String fomoPreventionRules;
  final String revengeTradingPreventionRules;
  final String afterWinRules;
  final String afterLossRules;
  final String afterBreakingRules;
  final String dailyRoutine;
  final String weeklyReviewRoutine;
  final List<String> allowedSessions;
  final List<String> allowedPairs;
  final List<String> selectedTradingStrategies;
  final List<String> psychologyIssues;

  String get recommendedTradingStyle => selectedTradingStrategy;
  String get recommendedMarket => accountType;
  String get recommendedTimeframes => tradingSessionRule;
  String get technicalStrategyRules => entryPermissionRules;
  String get entryRules => entryPermissionRules;
  String get exitRules => lossRecoveryRules;
  String get stopLossRules => riskManagementRules;
  String get takeProfitRules => tradingObjective;
  String get recommendedImprovementNotes => improvementNotes;
  List<String> get allowedStrategyNames => selectedTradingStrategies;
}
