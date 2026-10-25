import 'personality_assessment.dart';

enum PersonalityQuestionSetStatus { draft, published }

class PersonalityQuestionSetConfiguration {
  const PersonalityQuestionSetConfiguration({
    required this.version,
    required this.title,
    required this.status,
    required this.questionCount,
  });

  factory PersonalityQuestionSetConfiguration.fromJson(
    String id,
    Map<String, dynamic> json,
  ) => PersonalityQuestionSetConfiguration(
    version: (json['version'] as String?)?.trim().isNotEmpty == true
        ? (json['version'] as String).trim()
        : id,
    title: (json['title'] as String?)?.trim() ?? '',
    status: json['status'] == 'published'
        ? PersonalityQuestionSetStatus.published
        : PersonalityQuestionSetStatus.draft,
    questionCount: (json['question_count'] as num?)?.toInt() ?? 0,
  );

  final String version;
  final String title;
  final PersonalityQuestionSetStatus status;
  final int questionCount;

  bool get isPublished => status == PersonalityQuestionSetStatus.published;
}

class PersonalityQuestionConfiguration {
  const PersonalityQuestionConfiguration({
    required this.id,
    required this.textEn,
    required this.textZh,
    required this.dimension,
    required this.direction,
    required this.reverseScored,
    required this.weight,
    required this.active,
    required this.testVersion,
    required this.displayOrder,
    required this.randomizationGroup,
    required this.tieBreaker,
  });

  factory PersonalityQuestionConfiguration.fromJson(
    String id,
    Map<String, dynamic> json,
  ) {
    final dimensionKey = '${json['dimension'] ?? 'EI'}';
    return PersonalityQuestionConfiguration(
      id: id,
      textEn: '${json['text_en'] ?? ''}'.trim(),
      textZh: '${json['text_zh'] ?? ''}'.trim(),
      dimension: PersonalityDimension.values.firstWhere(
        (value) => value.key == dimensionKey,
        orElse: () => PersonalityDimension.energy,
      ),
      direction: '${json['direction'] ?? dimensionKey[0]}',
      reverseScored: json['reverse_scored'] == true,
      weight: (json['weight'] as num?)?.toDouble() ?? 1,
      active: json['active'] != false,
      testVersion: '${json['test_version'] ?? ''}',
      displayOrder: (json['display_order'] as num?)?.toInt() ?? 0,
      randomizationGroup: '${json['randomization_group'] ?? dimensionKey}',
      tieBreaker: json['tie_breaker'] == true,
    );
  }

  factory PersonalityQuestionConfiguration.fromBuiltIn(
    PersonalityQuestion question, {
    required String textZh,
  }) => PersonalityQuestionConfiguration(
    id: question.id,
    textEn: question.textKey,
    textZh: textZh,
    dimension: question.dimension,
    direction: question.dimension.firstLetter,
    reverseScored: question.reverseScored,
    weight: question.weight,
    active: question.active,
    testVersion: question.testVersion,
    displayOrder: question.displayOrder,
    randomizationGroup: question.dimension.key,
    tieBreaker: question.tieBreaker,
  );

  final String id;
  final String textEn;
  final String textZh;
  final PersonalityDimension dimension;
  final String direction;
  final bool reverseScored;
  final double weight;
  final bool active;
  final String testVersion;
  final int displayOrder;
  final String randomizationGroup;
  final bool tieBreaker;

  Map<String, Object?> toJson() => {
    'question_id': id,
    'text_en': textEn,
    'text_zh': textZh,
    'dimension': dimension.key,
    'direction': direction,
    'reverse_scored': reverseScored,
    'weight': weight,
    'active': active,
    'test_version': testVersion,
    'display_order': displayOrder,
    'randomization_group': randomizationGroup,
    'tie_breaker': tieBreaker,
  };
}

abstract final class PersonalityQuestionSetValidator {
  static List<String> validate(
    String version,
    Iterable<PersonalityQuestionConfiguration> questions,
  ) {
    final active = questions.where((question) => question.active).toList();
    final errors = <String>[];
    if (active.length < 32 || active.length > 48) {
      errors.add('A published version must contain 32 to 48 active questions.');
    }
    final orders = <int>{};
    for (final question in active) {
      if (question.id.trim().isEmpty ||
          question.textEn.trim().isEmpty ||
          question.textZh.trim().isEmpty) {
        errors.add('Every active question needs an ID and both translations.');
        break;
      }
      if (question.testVersion != version) {
        errors.add('Every active question must use the set version.');
        break;
      }
      if (question.weight <= 0) {
        errors.add('Question weights must be greater than zero.');
        break;
      }
      if (question.direction != question.dimension.firstLetter &&
          question.direction != question.dimension.secondLetter) {
        errors.add('Question direction must belong to its dimension.');
        break;
      }
      if (question.displayOrder <= 0 || !orders.add(question.displayOrder)) {
        errors.add(
          'Active question display orders must be positive and unique.',
        );
        break;
      }
    }
    final dimensionCounts = {
      for (final dimension in PersonalityDimension.values)
        dimension: active
            .where((question) => question.dimension == dimension)
            .length,
    };
    if (dimensionCounts.values.any((count) => count < 8 || count > 12)) {
      errors.add('Each dimension must contain 8 to 12 active questions.');
    }
    if (!active.any((question) => question.reverseScored) ||
        !active.any((question) => !question.reverseScored)) {
      errors.add('Use both normal and reverse-scored questions.');
    }
    return errors;
  }
}
