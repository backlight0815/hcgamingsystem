enum PersonalityGuidanceCategory {
  strengths,
  risk,
  style,
  psychology,
  discipline,
  plan,
  strategy,
  review,
}

class PersonalityGuidanceItem {
  const PersonalityGuidanceItem({
    required this.id,
    required this.category,
    required this.title,
    required this.explanation,
    required this.recommendedAction,
    required this.caution,
    required this.personalityFactor,
    required this.relevance,
  });

  final String id;
  final PersonalityGuidanceCategory category;
  final String title;
  final String explanation;
  final String recommendedAction;
  final String caution;
  final String personalityFactor;
  final double relevance;

  Map<String, Object?> toJson() => {
    'id': id,
    'category': category.name,
    'title': title,
    'explanation': explanation,
    'recommended_action': recommendedAction,
    'caution': caution,
    'personality_factor': personalityFactor,
    'relevance': relevance,
  };
}

class PersonalityGuidanceResult {
  const PersonalityGuidanceResult({
    required this.personalityKey,
    required this.resultVersion,
    required this.generatedAt,
    required this.strengths,
    required this.riskAreas,
    required this.suitableTradingStyles,
    required this.strategySuggestions,
    required this.items,
  });

  final String personalityKey;
  final String resultVersion;
  final DateTime generatedAt;
  final List<String> strengths;
  final List<String> riskAreas;
  final List<String> suitableTradingStyles;
  final List<String> strategySuggestions;
  final List<PersonalityGuidanceItem> items;

  Map<String, Object?> draftFor(Set<String> selectedIds) => {
    'source': 'personality_guidance',
    'result_version': resultVersion,
    'generated_at': generatedAt.toIso8601String(),
    'personality_key': personalityKey,
    'selected_guidance': items
        .where((item) => selectedIds.contains(item.id))
        .map((item) => item.toJson())
        .toList(growable: false),
  };
}
