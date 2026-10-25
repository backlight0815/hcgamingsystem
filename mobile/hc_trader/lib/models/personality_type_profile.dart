import 'personality_assessment.dart';

class PersonalityTypeProfile {
  const PersonalityTypeProfile({
    required this.type,
    required this.titleKey,
    required this.summaryKey,
    required this.strengthKeys,
    required this.challengeKeys,
    required this.decisionKey,
    required this.planningKey,
    required this.stressKey,
    required this.learningKey,
    required this.communicationKey,
    required this.tradingStrengthKeys,
    required this.tradingRiskKeys,
    required this.suitableStyleKeys,
    required this.riskGuidanceKey,
    required this.disciplineGuidanceKey,
    required this.planSuggestionKey,
    required this.strategySuggestionKey,
  });

  final String type;
  final String titleKey;
  final String summaryKey;
  final List<String> strengthKeys;
  final List<String> challengeKeys;
  final String decisionKey;
  final String planningKey;
  final String stressKey;
  final String learningKey;
  final String communicationKey;
  final List<String> tradingStrengthKeys;
  final List<String> tradingRiskKeys;
  final List<String> suitableStyleKeys;
  final String riskGuidanceKey;
  final String disciplineGuidanceKey;
  final String planSuggestionKey;
  final String strategySuggestionKey;
}

abstract final class PersonalityTypeCatalog {
  static const _identities = <String, (String, String)>{
    'ISTJ': (
      'Systematic Inspector',
      'Quiet, practical, logical, and structured, you tend to build confidence through evidence and repeatable routines.',
    ),
    'ISFJ': (
      'Steady Supporter',
      'Observant and considerate, you usually prefer dependable routines and decisions that protect people and stability.',
    ),
    'INFJ': (
      'Purposeful Strategist',
      'Reflective and future-focused, you connect patterns with personal values and prefer a clear long-range direction.',
    ),
    'INTJ': (
      'Independent Architect',
      'Independent and systems-minded, you enjoy turning complex patterns into efficient plans that can be tested.',
    ),
    'ISTP': (
      'Adaptive Technician',
      'Calm and hands-on, you examine what is happening now and adjust logically when conditions change.',
    ),
    'ISFP': (
      'Calm Craftsperson',
      'Private, observant, and values-aware, you prefer practical freedom without unnecessary pressure or rigid attention.',
    ),
    'INFP': (
      'Values-Led Explorer',
      'Imaginative and reflective, you seek work that fits your values while leaving room to explore possibilities.',
    ),
    'INTP': (
      'Analytical Investigator',
      'Curious and independent, you test assumptions, search for underlying logic, and resist rules that lack evidence.',
    ),
    'ESTP': (
      'Tactical Operator',
      'Energetic and pragmatic, you respond quickly to visible opportunities and learn through direct market experience.',
    ),
    'ESFP': (
      'Energetic Improviser',
      'Warm and action-oriented, you notice immediate opportunities and bring flexibility and enthusiasm to decisions.',
    ),
    'ENFP': (
      'Possibility Connector',
      'Expressive and imaginative, you connect ideas quickly and gain energy from discovering new directions with others.',
    ),
    'ENTP': (
      'Inventive Challenger',
      'Quick and exploratory, you enjoy testing alternatives, questioning assumptions, and adapting promising ideas.',
    ),
    'ESTJ': (
      'Decisive Organizer',
      'Direct and practical, you turn observable facts into organized action and prefer clear ownership and standards.',
    ),
    'ESFJ': (
      'Cooperative Coordinator',
      'Sociable and dependable, you organize practical details while considering how decisions affect the group.',
    ),
    'ENFJ': (
      'Insightful Mentor',
      'Expressive and future-aware, you organize people and ideas around shared development and meaningful goals.',
    ),
    'ENTJ': (
      'Strategic Commander',
      'Decisive and future-focused, you organize complex work around measurable goals, logic, and efficient execution.',
    ),
  };

  static PersonalityTypeProfile forResult(PersonalityAssessmentResult result) {
    final type = _identities.containsKey(result.type) ? result.type : 'ISTP';
    final identity = _identities[type]!;
    final letters = type.split('').toSet();
    final strengths = <String>[
      letters.contains('E')
          ? 'Comfortable exchanging ideas and acting visibly'
          : 'Independent concentration and resistance to social noise',
      letters.contains('S')
          ? 'Attention to concrete price behavior and execution detail'
          : 'Pattern recognition and awareness of developing possibilities',
      letters.contains('T')
          ? 'Logical comparison and objective rule evaluation'
          : 'Awareness of values, motivation, and emotional context',
      letters.contains('J')
          ? 'Preparation, closure, and consistent routines'
          : 'Flexibility when market information changes',
    ];
    final challenges = <String>[
      letters.contains('E')
          ? 'External discussion may increase social influence or FOMO'
          : 'Private analysis can become isolated or slow to challenge',
      letters.contains('S')
          ? 'Immediate details may hide a larger structural shift'
          : 'Interesting theories may outrun confirmed evidence',
      letters.contains('T')
          ? 'Stress signals may be noticed later than logical errors'
          : 'A loss may feel personal and affect the next decision',
      letters.contains('J')
          ? 'A fixed plan may be defended after conditions change'
          : 'Open options may weaken routine and follow-through',
    ];

    return PersonalityTypeProfile(
      type: type,
      titleKey: identity.$1,
      summaryKey: identity.$2,
      strengthKeys: strengths,
      challengeKeys: challenges,
      decisionKey: letters.contains('T')
          ? 'You tend to compare evidence, consistency, and consequences before personal comfort.'
          : 'You tend to consider values and human impact alongside the available evidence.',
      planningKey: letters.contains('J')
          ? 'You usually prefer a defined plan, clear limits, and completed decisions before action.'
          : 'You usually prefer adaptable options and room to respond as new information appears.',
      stressKey: _stressKey(type),
      learningKey: letters.contains('S')
          ? 'Concrete examples, screenshots, and repeated practice are likely to support learning.'
          : 'Conceptual models, pattern comparisons, and scenario exploration are likely to support learning.',
      communicationKey: letters.contains('E')
          ? 'You may clarify your thinking through active discussion; protect final decisions from group momentum.'
          : 'You may communicate best after private reflection; schedule a deliberate challenge to your own view.',
      tradingStrengthKeys: [
        letters.contains('J')
            ? 'Pre-session preparation and checklist consistency'
            : 'Adaptation when a valid setup changes shape',
        letters.contains('T')
            ? 'Objective review of risk and execution statistics'
            : 'Early awareness of confidence, fear, and team pressure',
        letters.contains('S')
            ? 'Observation of entries, spreads, and invalidation levels'
            : 'Connecting higher-timeframe themes and market structure',
      ],
      tradingRiskKeys: [
        ...challenges.take(2),
        letters.contains('P')
            ? 'Overtrading when new possibilities keep appearing'
            : 'Forcing the original scenario instead of invalidating it',
      ],
      suitableStyleKeys: _styles(type),
      riskGuidanceKey: _riskGuidance(result),
      disciplineGuidanceKey: letters.contains('P')
          ? 'Use a visible checklist, a hard trade limit, and a scheduled end-of-session review.'
          : 'Add an explicit invalidation rule so discipline includes changing the plan when evidence changes.',
      planSuggestionKey: letters.contains('J')
          ? 'Draft a concise session plan with one alternative scenario and objective invalidation conditions.'
          : 'Draft flexible scenarios, then lock risk, stop loss, daily loss limit, and maximum trade count.',
      strategySuggestionKey: letters.contains('N')
          ? 'Review market structure, trend-following, and rules-based confluence before selecting a strategy.'
          : 'Review support and resistance, breakout and retest, and clearly defined range rules before selecting a strategy.',
    );
  }

  static String _stressKey(String type) {
    if (type.contains('E') && type.contains('P')) {
      return 'Under stress, activity and new opportunities may increase; a cooldown protects against impulsive frequency.';
    }
    if (type.contains('I') && type.contains('J')) {
      return 'Under stress, you may narrow your focus and hold tightly to the plan; pause to test whether assumptions changed.';
    }
    if (type.contains('F')) {
      return 'Under stress, losses or conflict may feel personally significant; name the emotion before making another risk decision.';
    }
    return 'Under stress, analysis may become overly detached or repetitive; use a time limit and an objective stop condition.';
  }

  static List<String> _styles(String type) {
    if (type.contains('S') && type.contains('P')) {
      return const ['Intraday trading', 'Breakout trading', 'Range trading'];
    }
    if (type.contains('N') && type.contains('J')) {
      return const ['Swing trading', 'Position trading', 'Trend following'];
    }
    if (type.contains('T') && type.contains('J')) {
      return const [
        'Rules-based systematic trading',
        'Trend following',
        'Breakout and retest',
      ];
    }
    return const [
      'Swing trading',
      'Rules-based confluence',
      'Discretionary trading with fixed risk',
    ];
  }

  static String _riskGuidance(PersonalityAssessmentResult result) {
    final strongest = result.dimensionScores
        .map(
          (score) => score.firstPercent > score.secondPercent
              ? score.firstPercent
              : score.secondPercent,
        )
        .reduce((left, right) => left > right ? left : right);
    return strongest >= 65
        ? 'A strong preference can magnify both strengths and blind spots. Consider a suggested maximum risk of 0.5% per trade until journal evidence supports a change.'
        : 'Balanced preferences may shift with context. Consider a suggested maximum risk of 1% per trade and evaluate consistency before changing it.';
  }
}

class PersonalityGuidanceSuggestion {
  const PersonalityGuidanceSuggestion({
    required this.id,
    required this.titleKey,
    required this.actionKey,
    required this.cautionKey,
    this.actionValues = const {},
  });
  final String id;
  final String titleKey;
  final String actionKey;
  final String cautionKey;
  final Map<String, String> actionValues;

  Map<String, Object?> toJson() => {
    'id': id,
    'title': titleKey,
    'action': actionKey,
    if (actionValues.isNotEmpty) 'action_values': actionValues,
    'caution': cautionKey,
  };
}

class PersonalityAssessmentGuidance {
  const PersonalityAssessmentGuidance({
    required this.maximumRiskPercent,
    required this.dailyLossLimitPercent,
    required this.maximumTradesPerDay,
    required this.suggestions,
  });
  final double maximumRiskPercent;
  final double dailyLossLimitPercent;
  final int maximumTradesPerDay;
  final List<PersonalityGuidanceSuggestion> suggestions;

  Map<String, Object?> draftFor(Set<String> selected, String attemptId) => {
    'source': 'personality_guidance',
    'attempt_id': attemptId,
    'created_at': DateTime.now().toIso8601String(),
    'suggested_maximum_risk_percent': maximumRiskPercent,
    'suggested_daily_loss_limit_percent': dailyLossLimitPercent,
    'suggested_maximum_trades_per_day': maximumTradesPerDay,
    'selected_guidance': suggestions
        .where((suggestion) => selected.contains(suggestion.id))
        .map((suggestion) => suggestion.toJson())
        .toList(growable: false),
  };
}
