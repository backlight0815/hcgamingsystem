enum PersonalityDimension { energy, information, decisions, structure }

extension PersonalityDimensionDefinition on PersonalityDimension {
  String get key => switch (this) {
    PersonalityDimension.energy => 'EI',
    PersonalityDimension.information => 'SN',
    PersonalityDimension.decisions => 'TF',
    PersonalityDimension.structure => 'JP',
  };

  String get firstLetter => key[0];
  String get secondLetter => key[1];

  String get titleKey => switch (this) {
    PersonalityDimension.energy => 'Extraversion / Introversion',
    PersonalityDimension.information => 'Sensing / Intuition',
    PersonalityDimension.decisions => 'Thinking / Feeling',
    PersonalityDimension.structure => 'Judging / Perceiving',
  };
}

class PersonalityQuestion {
  const PersonalityQuestion({
    required this.id,
    required this.textKey,
    required this.dimension,
    required this.displayOrder,
    this.reverseScored = false,
    this.weight = 1,
    this.tieBreaker = false,
    this.active = true,
    this.testVersion = PersonalityQuestionBank.testVersion,
  });

  final String id;
  final String textKey;
  final PersonalityDimension dimension;
  final bool reverseScored;
  final double weight;
  final bool tieBreaker;
  final bool active;
  final String testVersion;
  final int displayOrder;
}

abstract final class PersonalityQuestionBank {
  static const schemaVersion = 2;
  static const testVersion = 'personality-16-v1';
  static const scoringVersion = 'personality-score-v1';
  static const guidanceVersion = 'personality-guidance-v1';

  static const questions = <PersonalityQuestion>[
    PersonalityQuestion(
      id: 'ei_01',
      textKey:
          'During market review, I think more clearly when discussing ideas with other traders.',
      dimension: PersonalityDimension.energy,
      displayOrder: 1,
    ),
    PersonalityQuestion(
      id: 'ei_02',
      textKey:
          'I prefer to form my trading view privately before hearing other opinions.',
      dimension: PersonalityDimension.energy,
      reverseScored: true,
      displayOrder: 2,
    ),
    PersonalityQuestion(
      id: 'ei_03',
      textKey: 'A lively trading room usually increases my energy and focus.',
      dimension: PersonalityDimension.energy,
      displayOrder: 3,
    ),
    PersonalityQuestion(
      id: 'ei_04',
      textKey:
          'After a demanding session, quiet time helps me recover better than conversation.',
      dimension: PersonalityDimension.energy,
      reverseScored: true,
      displayOrder: 4,
    ),
    PersonalityQuestion(
      id: 'ei_05',
      textKey:
          'I readily explain a developing trade idea while I am still working through it.',
      dimension: PersonalityDimension.energy,
      displayOrder: 5,
    ),
    PersonalityQuestion(
      id: 'ei_06',
      textKey: 'I am least influenced when I analyze markets on my own.',
      dimension: PersonalityDimension.energy,
      reverseScored: true,
      displayOrder: 6,
    ),
    PersonalityQuestion(
      id: 'ei_07',
      textKey:
          'Meeting new traders and comparing methods feels stimulating to me.',
      dimension: PersonalityDimension.energy,
      displayOrder: 7,
    ),
    PersonalityQuestion(
      id: 'ei_08',
      textKey:
          'Before making an important trading decision, I need uninterrupted space to reflect.',
      dimension: PersonalityDimension.energy,
      reverseScored: true,
      weight: 1.25,
      tieBreaker: true,
      displayOrder: 8,
    ),
    PersonalityQuestion(
      id: 'sn_01',
      textKey:
          'I trust clearly observed price behavior more than an untested market story.',
      dimension: PersonalityDimension.information,
      displayOrder: 9,
    ),
    PersonalityQuestion(
      id: 'sn_02',
      textKey:
          'I naturally look for emerging patterns even when the available details are incomplete.',
      dimension: PersonalityDimension.information,
      reverseScored: true,
      displayOrder: 10,
    ),
    PersonalityQuestion(
      id: 'sn_03',
      textKey:
          'Specific entry conditions and concrete examples help me learn fastest.',
      dimension: PersonalityDimension.information,
      displayOrder: 11,
    ),
    PersonalityQuestion(
      id: 'sn_04',
      textKey:
          'I enjoy exploring several possible future market scenarios at once.',
      dimension: PersonalityDimension.information,
      reverseScored: true,
      displayOrder: 12,
    ),
    PersonalityQuestion(
      id: 'sn_05',
      textKey:
          'I notice small changes in spread, volatility, and candle behavior quickly.',
      dimension: PersonalityDimension.information,
      displayOrder: 13,
    ),
    PersonalityQuestion(
      id: 'sn_06',
      textKey:
          'The overall market theme often matters more to me than individual data points.',
      dimension: PersonalityDimension.information,
      reverseScored: true,
      displayOrder: 14,
    ),
    PersonalityQuestion(
      id: 'sn_07',
      textKey:
          'I prefer a method with practical evidence over one that is mainly theoretical.',
      dimension: PersonalityDimension.information,
      displayOrder: 15,
    ),
    PersonalityQuestion(
      id: 'sn_08',
      textKey:
          'Unusual connections between markets often attract my attention first.',
      dimension: PersonalityDimension.information,
      reverseScored: true,
      weight: 1.25,
      tieBreaker: true,
      displayOrder: 16,
    ),
    PersonalityQuestion(
      id: 'tf_01',
      textKey:
          'When rules conflict with my feelings, I usually follow the rules.',
      dimension: PersonalityDimension.decisions,
      displayOrder: 17,
    ),
    PersonalityQuestion(
      id: 'tf_02',
      textKey:
          'I consider how a decision will affect people before deciding whether it is right.',
      dimension: PersonalityDimension.decisions,
      reverseScored: true,
      displayOrder: 18,
    ),
    PersonalityQuestion(
      id: 'tf_03',
      textKey:
          'I can close a losing trade without treating the loss as a personal judgment.',
      dimension: PersonalityDimension.decisions,
      displayOrder: 19,
    ),
    PersonalityQuestion(
      id: 'tf_04',
      textKey:
          'Harmony in a team discussion matters to me even when the fastest answer is less personal.',
      dimension: PersonalityDimension.decisions,
      reverseScored: true,
      displayOrder: 20,
    ),
    PersonalityQuestion(
      id: 'tf_05',
      textKey:
          'I prefer feedback that is direct, specific, and supported by evidence.',
      dimension: PersonalityDimension.decisions,
      displayOrder: 21,
    ),
    PersonalityQuestion(
      id: 'tf_06',
      textKey:
          'My personal values strongly shape which trading opportunities I am willing to take.',
      dimension: PersonalityDimension.decisions,
      reverseScored: true,
      displayOrder: 22,
    ),
    PersonalityQuestion(
      id: 'tf_07',
      textKey:
          'I compare alternatives logically before considering which one feels most comfortable.',
      dimension: PersonalityDimension.decisions,
      displayOrder: 23,
    ),
    PersonalityQuestion(
      id: 'tf_08',
      textKey:
          'When someone is under stress, understanding their experience comes before correcting their logic.',
      dimension: PersonalityDimension.decisions,
      reverseScored: true,
      weight: 1.25,
      tieBreaker: true,
      displayOrder: 24,
    ),
    PersonalityQuestion(
      id: 'jp_01',
      textKey:
          'I trade best when my session plan and limits are decided in advance.',
      dimension: PersonalityDimension.structure,
      displayOrder: 25,
    ),
    PersonalityQuestion(
      id: 'jp_02',
      textKey:
          'I prefer to keep several trade possibilities open until price forces a decision.',
      dimension: PersonalityDimension.structure,
      reverseScored: true,
      displayOrder: 26,
    ),
    PersonalityQuestion(
      id: 'jp_03',
      textKey:
          'Completing a checklist gives me confidence that I am ready to act.',
      dimension: PersonalityDimension.structure,
      displayOrder: 27,
    ),
    PersonalityQuestion(
      id: 'jp_04',
      textKey:
          'Unexpected market changes are more interesting than disruptive to me.',
      dimension: PersonalityDimension.structure,
      reverseScored: true,
      displayOrder: 28,
    ),
    PersonalityQuestion(
      id: 'jp_05',
      textKey: 'I feel uneasy when risk limits or exit rules remain undecided.',
      dimension: PersonalityDimension.structure,
      displayOrder: 29,
    ),
    PersonalityQuestion(
      id: 'jp_06',
      textKey:
          'I often adapt my approach during a session instead of following the original sequence.',
      dimension: PersonalityDimension.structure,
      reverseScored: true,
      displayOrder: 30,
    ),
    PersonalityQuestion(
      id: 'jp_07',
      textKey:
          'I would rather miss a trade than enter before every required condition is confirmed.',
      dimension: PersonalityDimension.structure,
      displayOrder: 31,
    ),
    PersonalityQuestion(
      id: 'jp_08',
      textKey:
          'A flexible watchlist suits me better than a tightly scheduled trading routine.',
      dimension: PersonalityDimension.structure,
      reverseScored: true,
      weight: 1.25,
      tieBreaker: true,
      displayOrder: 32,
    ),
  ];
}

class PersonalityAnswer {
  const PersonalityAnswer({required this.questionId, required this.value});

  final String questionId;
  final int value;

  Map<String, Object?> toJson() => {'question_id': questionId, 'value': value};
}

enum PreferenceStrength { balanced, slight, moderate, strong }

class PersonalityDimensionScore {
  const PersonalityDimensionScore({
    required this.dimension,
    required this.firstRaw,
    required this.secondRaw,
    required this.firstPercent,
    required this.secondPercent,
    required this.winningLetter,
    required this.strength,
    required this.balanced,
    required this.tieRule,
  });

  final PersonalityDimension dimension;
  final double firstRaw;
  final double secondRaw;
  final double firstPercent;
  final double secondPercent;
  final String winningLetter;
  final PreferenceStrength strength;
  final bool balanced;
  final String tieRule;

  Map<String, Object?> toJson() => {
    'dimension': dimension.key,
    'first_raw': firstRaw,
    'second_raw': secondRaw,
    'first_percent': firstPercent,
    'second_percent': secondPercent,
    'winning_letter': winningLetter,
    'strength': strength.name,
    'balanced': balanced,
    'tie_rule': tieRule,
  };

  factory PersonalityDimensionScore.fromJson(Map<String, dynamic> json) {
    final key = '${json['dimension'] ?? ''}';
    final dimension = PersonalityDimension.values.firstWhere(
      (value) => value.key == key,
      orElse: () => PersonalityDimension.energy,
    );
    return PersonalityDimensionScore(
      dimension: dimension,
      firstRaw: _double(json['first_raw']),
      secondRaw: _double(json['second_raw']),
      firstPercent: _double(json['first_percent']),
      secondPercent: _double(json['second_percent']),
      winningLetter: '${json['winning_letter'] ?? ''}',
      strength: PreferenceStrength.values.firstWhere(
        (value) => value.name == '${json['strength'] ?? ''}',
        orElse: () => PreferenceStrength.balanced,
      ),
      balanced: json['balanced'] == true,
      tieRule: '${json['tie_rule'] ?? ''}',
    );
  }
}

class PersonalityAssessmentResult {
  const PersonalityAssessmentResult({
    required this.attemptId,
    required this.type,
    required this.testVersion,
    required this.scoringVersion,
    required this.guidanceVersion,
    required this.dimensionScores,
    required this.submittedAt,
  });

  final String attemptId;
  final String type;
  final String testVersion;
  final String scoringVersion;
  final String guidanceVersion;
  final List<PersonalityDimensionScore> dimensionScores;
  final DateTime submittedAt;

  PersonalityDimensionScore scoreFor(PersonalityDimension dimension) =>
      dimensionScores.firstWhere((score) => score.dimension == dimension);

  Map<String, Object?> toJson() => {
    'attempt_id': attemptId,
    'type': type,
    'test_version': testVersion,
    'scoring_version': scoringVersion,
    'guidance_version': guidanceVersion,
    'dimension_scores': dimensionScores.map((score) => score.toJson()).toList(),
    'submitted_at': submittedAt.toIso8601String(),
  };

  factory PersonalityAssessmentResult.fromJson(Map<String, dynamic> json) =>
      PersonalityAssessmentResult(
        attemptId: '${json['attempt_id'] ?? ''}',
        type: '${json['type'] ?? ''}',
        testVersion: '${json['test_version'] ?? ''}',
        scoringVersion: '${json['scoring_version'] ?? ''}',
        guidanceVersion: '${json['guidance_version'] ?? ''}',
        dimensionScores: (json['dimension_scores'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (value) => PersonalityDimensionScore.fromJson(
                Map<String, dynamic>.from(value),
              ),
            )
            .toList(growable: false),
        submittedAt: _date(json['submitted_at']),
      );
}

enum PersonalityAttemptStatus { inProgress, completed }

class PersonalityAssessmentAttempt {
  const PersonalityAssessmentAttempt({
    required this.id,
    required this.userId,
    required this.testVersion,
    required this.startedAt,
    required this.updatedAt,
    this.status = PersonalityAttemptStatus.inProgress,
    this.answers = const {},
    this.result,
    this.retakeOf,
  });

  final String id;
  final String userId;
  final String testVersion;
  final DateTime startedAt;
  final DateTime updatedAt;
  final PersonalityAttemptStatus status;
  final Map<String, int> answers;
  final PersonalityAssessmentResult? result;
  final String? retakeOf;

  bool get isCompleted => status == PersonalityAttemptStatus.completed;

  PersonalityAssessmentAttempt copyWith({
    DateTime? updatedAt,
    PersonalityAttemptStatus? status,
    Map<String, int>? answers,
    PersonalityAssessmentResult? result,
  }) => PersonalityAssessmentAttempt(
    id: id,
    userId: userId,
    testVersion: testVersion,
    startedAt: startedAt,
    updatedAt: updatedAt ?? this.updatedAt,
    status: status ?? this.status,
    answers: answers ?? this.answers,
    result: result ?? this.result,
    retakeOf: retakeOf,
  );

  Map<String, Object?> toJson() => {
    'schema_version': PersonalityQuestionBank.schemaVersion,
    'answer_scale_version': 'likert-7-v1',
    'attempt_id': id,
    'user_id': userId,
    'test_version': testVersion,
    'question_version': testVersion,
    'status': status.name,
    'answers': answers,
    'started_at': startedAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    if (result != null) ...{
      'result': result!.toJson(),
      'submitted_at': result!.submittedAt.toIso8601String(),
      'final_type': result!.type,
      'dimension_scores': result!.dimensionScores
          .map((score) => score.toJson())
          .toList(),
    },
    if (retakeOf != null) 'retake_of': retakeOf,
  };

  factory PersonalityAssessmentAttempt.fromJson(Map<String, dynamic> json) {
    final rawAnswers = json['answers'];
    return PersonalityAssessmentAttempt(
      id: '${json['attempt_id'] ?? json['id'] ?? ''}',
      userId: '${json['user_id'] ?? ''}',
      testVersion: '${json['test_version'] ?? ''}',
      startedAt: _date(json['started_at']),
      updatedAt: _date(json['updated_at']),
      status: '${json['status']}' == 'completed'
          ? PersonalityAttemptStatus.completed
          : PersonalityAttemptStatus.inProgress,
      answers: rawAnswers is Map
          ? rawAnswers.map((key, value) => MapEntry('$key', _int(value)))
          : const {},
      result: json['result'] is Map
          ? PersonalityAssessmentResult.fromJson(
              Map<String, dynamic>.from(json['result'] as Map),
            )
          : null,
      retakeOf: json['retake_of']?.toString(),
    );
  }
}

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
int _int(Object? value) => value is int ? value : int.tryParse('$value') ?? 0;
DateTime _date(Object? value) {
  if (value is DateTime) return value;
  try {
    final dynamic timestamp = value;
    final converted = timestamp?.toDate();
    if (converted is DateTime) return converted;
  } on Object {
    // Continue with the portable ISO parser.
  }
  return DateTime.tryParse('$value') ?? DateTime.fromMillisecondsSinceEpoch(0);
}
