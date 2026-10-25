import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';

enum CourseworkQuestionType {
  singleChoice('singleChoice'),
  multipleChoice('multipleChoice'),
  trueFalse('trueFalse'),
  shortAnswer('shortAnswer'),
  chartZoneSelection('chartZoneSelection'),
  marketAnalysis('marketAnalysis'),
  practicalDemonstration('practicalDemonstration'),
  orderManagementDemonstration('orderManagementDemonstration');

  const CourseworkQuestionType(this.storageValue);
  final String storageValue;

  bool get isTheory => const {
    CourseworkQuestionType.singleChoice,
    CourseworkQuestionType.multipleChoice,
    CourseworkQuestionType.trueFalse,
    CourseworkQuestionType.shortAnswer,
  }.contains(this);

  bool get requiresManualReview => const {
    CourseworkQuestionType.shortAnswer,
    CourseworkQuestionType.marketAnalysis,
    CourseworkQuestionType.practicalDemonstration,
    CourseworkQuestionType.orderManagementDemonstration,
  }.contains(this);

  bool get requiresEvidence => const {
    CourseworkQuestionType.marketAnalysis,
    CourseworkQuestionType.practicalDemonstration,
    CourseworkQuestionType.orderManagementDemonstration,
  }.contains(this);

  static CourseworkQuestionType fromStorage(Object? value) {
    final normalized = '${value ?? ''}'.replaceAll('_', '').toLowerCase();
    const aliases = <String, CourseworkQuestionType>{
      'theorysinglechoice': CourseworkQuestionType.singleChoice,
      'theorymultiplechoice': CourseworkQuestionType.multipleChoice,
      'theoryshortanswer': CourseworkQuestionType.shortAnswer,
      'scenariobasedtheory': CourseworkQuestionType.singleChoice,
      'chartinterpretation': CourseworkQuestionType.chartZoneSelection,
      'selfanalysiskbat': CourseworkQuestionType.marketAnalysis,
      'practicaldemonstration': CourseworkQuestionType.practicalDemonstration,
    };
    if (aliases[normalized] case final alias?) return alias;
    return CourseworkQuestionType.values.firstWhere(
      (type) => type.storageValue.toLowerCase() == normalized,
      orElse: () => CourseworkQuestionType.singleChoice,
    );
  }
}

enum CourseworkStatus {
  assigned,
  notStarted,
  inProgress,
  draft,
  submitted,
  underReview,
  revisionRequested,
  resubmitted,
  graded,
  completed,
  late,
  expired,
  cancelled;

  static CourseworkStatus fromStorage(Object? value) => values.firstWhere(
    (item) => item.name.toLowerCase() == '${value ?? ''}'.toLowerCase(),
    orElse: () => CourseworkStatus.notStarted,
  );

  bool get canEdit => const {
    CourseworkStatus.notStarted,
    CourseworkStatus.inProgress,
    CourseworkStatus.draft,
    CourseworkStatus.revisionRequested,
  }.contains(this);

  bool get isSubmitted => const {
    CourseworkStatus.submitted,
    CourseworkStatus.underReview,
    CourseworkStatus.resubmitted,
    CourseworkStatus.graded,
    CourseworkStatus.completed,
    CourseworkStatus.late,
  }.contains(this);
}

enum CourseworkDifficulty {
  beginner,
  intermediate,
  advanced;

  String get storageValue => name;

  static CourseworkDifficulty fromFirestore(Object? value) {
    final normalized = _canonicalToken(value);
    if (const {
      'beginner',
      'basic',
      'foundation',
      '初级',
      '基础',
    }.contains(normalized)) {
      return CourseworkDifficulty.beginner;
    }
    if (const {'advanced', 'expert', '高级', '进阶'}.contains(normalized)) {
      return CourseworkDifficulty.advanced;
    }
    return CourseworkDifficulty.intermediate;
  }
}

enum CourseworkPublicationStatus {
  draft,
  published,
  archived;

  String get storageValue => name;

  static CourseworkPublicationStatus fromFirestore(Object? value) {
    if (value is bool) {
      return value
          ? CourseworkPublicationStatus.published
          : CourseworkPublicationStatus.draft;
    }
    final normalized = _canonicalToken(value);
    if (const {'published', 'publish', 'live', '已发布'}.contains(normalized)) {
      return CourseworkPublicationStatus.published;
    }
    if (const {'archived', 'archive', 'inactive', '已归档'}.contains(normalized)) {
      return CourseworkPublicationStatus.archived;
    }
    return CourseworkPublicationStatus.draft;
  }
}

class CourseworkCategoryDefinition {
  const CourseworkCategoryDefinition({
    required this.id,
    required this.en,
    required this.zh,
  });

  final String id;
  final String en;
  final String zh;
}

abstract final class CourseworkTaxonomy {
  static const categories = <CourseworkCategoryDefinition>[
    CourseworkCategoryDefinition(
      id: 'market_foundations',
      en: 'Trading Market Foundations',
      zh: '交易市场基础',
    ),
    CourseworkCategoryDefinition(
      id: 'price_action',
      en: 'Candlestick and Price Action',
      zh: '蜡烛图与价格行为',
    ),
    CourseworkCategoryDefinition(
      id: 'key_levels',
      en: 'Support, Resistance and Key Levels',
      zh: '支撑、阻力与关键水平',
    ),
    CourseworkCategoryDefinition(
      id: 'market_structure',
      en: 'Market Structure',
      zh: '市场结构',
    ),
    CourseworkCategoryDefinition(
      id: 'trend_indicators',
      en: 'Trend, Momentum and Indicators',
      zh: '趋势、动量与指标',
    ),
    CourseworkCategoryDefinition(
      id: 'multi_timeframe',
      en: 'Multi-Timeframe Analysis',
      zh: '多时间周期分析',
    ),
    CourseworkCategoryDefinition(
      id: 'risk_management',
      en: 'Risk and Money Management',
      zh: '风险与资金管理',
    ),
    CourseworkCategoryDefinition(
      id: 'trade_execution',
      en: 'Trade Planning and Execution',
      zh: '交易计划与执行',
    ),
    CourseworkCategoryDefinition(
      id: 'trading_psychology',
      en: 'Trading Psychology and Discipline',
      zh: '交易心理与纪律',
    ),
    CourseworkCategoryDefinition(
      id: 'journal_review',
      en: 'Trading Journal and Performance Review',
      zh: '交易日志与绩效复盘',
    ),
  ];

  static String categoryId(Object? value, {Object? label}) {
    final raw = _canonicalToken(value);
    final labelToken = _canonicalToken(label);
    for (final category in categories) {
      final aliases = {
        _canonicalToken(category.id),
        _canonicalToken(category.en),
        _canonicalToken(category.zh),
      };
      if (aliases.contains(raw) || aliases.contains(labelToken)) {
        return category.id;
      }
    }
    return raw.replaceAll(RegExp(r'[\s-]+'), '_');
  }

  static CourseworkCategoryDefinition? find(String id) =>
      categories.where((item) => item.id == id).firstOrNull;
}

class CourseworkAnswerOption {
  const CourseworkAnswerOption({
    required this.id,
    required this.text,
    this.textI18n = const {},
    this.isCorrect = false,
  });

  final String id;
  final String text;
  final Map<String, String> textI18n;
  final bool isCorrect;

  String localized(String languageCode) =>
      localizedValue(textI18n, languageCode, fallback: text);

  factory CourseworkAnswerOption.fromMap(Map<String, dynamic> data) =>
      CourseworkAnswerOption(
        id: '${data['id'] ?? ''}',
        text: localizedValue(data['text'] ?? data['option_text'], 'en'),
        textI18n: _localizedMap(
          data['text_i18n'] ?? data['option_text_i18n'] ?? data['text'],
        ),
        isCorrect: firestoreBool(data['is_correct'] ?? data['isCorrect']),
      );

  Map<String, Object?> toMap({bool includeCorrectAnswer = true}) => {
    'id': id,
    'text': text,
    'text_i18n': textI18n,
    if (includeCorrectAnswer) 'is_correct': isCorrect,
  };
}

class CourseworkAnswerZone {
  const CourseworkAnswerZone({
    required this.id,
    required this.label,
    required this.minimumPrice,
    required this.maximumPrice,
    this.startTime,
    this.endTime,
    this.isCorrect = false,
    this.partialMarkRatio = 0,
  });

  final String id;
  final String label;
  final double minimumPrice;
  final double maximumPrice;
  final DateTime? startTime;
  final DateTime? endTime;
  final bool isCorrect;
  final double partialMarkRatio;

  factory CourseworkAnswerZone.fromMap(
    Map<String, dynamic> data,
  ) => CourseworkAnswerZone(
    id: '${data['id'] ?? ''}',
    label: '${data['label'] ?? ''}',
    minimumPrice: _finiteDouble(data['minimum_price'] ?? data['minimumPrice']),
    maximumPrice: _finiteDouble(data['maximum_price'] ?? data['maximumPrice']),
    startTime: firestoreDate(data['start_time'] ?? data['startTime']),
    endTime: firestoreDate(data['end_time'] ?? data['endTime']),
    isCorrect: firestoreBool(data['is_correct'] ?? data['isCorrect']),
    partialMarkRatio: _finiteDouble(
      data['partial_mark_ratio'] ?? data['partialMarkRatio'],
    ).clamp(0, 1),
  );

  Map<String, Object?> toMap({bool includeAnswer = true}) => {
    'id': id,
    'label': label,
    'minimum_price': minimumPrice,
    'maximum_price': maximumPrice,
    if (startTime != null) 'start_time': Timestamp.fromDate(startTime!),
    if (endTime != null) 'end_time': Timestamp.fromDate(endTime!),
    if (includeAnswer) 'is_correct': isCorrect,
    if (includeAnswer) 'partial_mark_ratio': partialMarkRatio,
  };
}

class CourseworkRubricCriterion {
  const CourseworkRubricCriterion({
    required this.id,
    required this.title,
    required this.maximumMark,
    this.titleI18n = const {},
    this.guidance = '',
    this.guidanceI18n = const {},
  });

  final String id;
  final String title;
  final Map<String, String> titleI18n;
  final double maximumMark;
  final String guidance;
  final Map<String, String> guidanceI18n;

  String localizedTitle(String languageCode) => localizedValue(
    {'en': title, ...titleI18n},
    languageCode,
    fallback: title,
  );

  factory CourseworkRubricCriterion.fromMap(Map<String, dynamic> data) =>
      CourseworkRubricCriterion(
        id: '${data['id'] ?? ''}',
        title: localizedValue(data['title'], 'en'),
        titleI18n: _localizedMap(data['title_i18n']),
        maximumMark: _finiteDouble(data['maximum_mark'] ?? data['maximumMark']),
        guidance: localizedValue(data['guidance'], 'en'),
        guidanceI18n: _localizedMap(data['guidance_i18n']),
      );

  Map<String, Object?> toMap() => {
    'id': id,
    'title': title,
    'title_i18n': titleI18n,
    'maximum_mark': maximumMark,
    'guidance': guidance,
    'guidance_i18n': guidanceI18n,
  };
}

class CourseworkChartCandle {
  const CourseworkChartCandle({
    required this.time,
    required this.open,
    required this.high,
    required this.low,
    required this.close,
  });

  final DateTime time;
  final double open;
  final double high;
  final double low;
  final double close;

  factory CourseworkChartCandle.fromMap(Map<String, dynamic> data) =>
      CourseworkChartCandle(
        time:
            firestoreDate(data['time']) ??
            DateTime.utc(
              2026,
              1,
              1,
            ).add(Duration(hours: firestoreInt(data['timeIndex']))),
        open: _finiteDouble(data['open']),
        high: _finiteDouble(data['high']),
        low: _finiteDouble(data['low']),
        close: _finiteDouble(data['close']),
      );

  Map<String, Object?> toMap() => {
    'time': Timestamp.fromDate(time.toUtc()),
    'open': open,
    'high': high,
    'low': low,
    'close': close,
  };
}

class CourseworkQuestion {
  const CourseworkQuestion({
    required this.id,
    required this.themeId,
    required this.number,
    required this.type,
    required this.title,
    required this.questionText,
    required this.marks,
    this.titleI18n = const {},
    this.questionTextI18n = const {},
    this.instructions = '',
    this.instructionsI18n = const {},
    this.difficulty = 'intermediate',
    this.learningObjective = '',
    this.answerOptions = const [],
    this.acceptedAnswers = const [],
    this.requiresWrittenExplanation = false,
    this.requiresImage = false,
    this.minimumCharacters = 0,
    this.maximumCharacters = 0,
    this.minimumImages = 0,
    this.maximumImages = 0,
    this.allowedFileTypes = const [],
    this.chartAsset = '',
    this.symbol = '',
    this.timeframe = '',
    this.marketDate,
    this.chartCandles = const [],
    this.answerZones = const [],
    this.gradingMode = 'manual',
    this.rubric = const [],
    this.sampleAnswer = '',
    this.reviewerGuidance = '',
    this.version = 1,
    this.status = 'published',
  });

  final String id;
  final String themeId;
  final int number;
  final CourseworkQuestionType type;
  final String title;
  final Map<String, String> titleI18n;
  final String questionText;
  final Map<String, String> questionTextI18n;
  final String instructions;
  final Map<String, String> instructionsI18n;
  final double marks;
  final String difficulty;
  final String learningObjective;
  final List<CourseworkAnswerOption> answerOptions;
  final List<String> acceptedAnswers;
  final bool requiresWrittenExplanation;
  final bool requiresImage;
  final int minimumCharacters;
  final int maximumCharacters;
  final int minimumImages;
  final int maximumImages;
  final List<String> allowedFileTypes;
  final String chartAsset;
  final String symbol;
  final String timeframe;
  final DateTime? marketDate;
  final List<CourseworkChartCandle> chartCandles;
  final List<CourseworkAnswerZone> answerZones;
  final String gradingMode;
  final List<CourseworkRubricCriterion> rubric;
  final String sampleAnswer;
  final String reviewerGuidance;
  final int version;
  final String status;

  String localizedTitle(String languageCode) => localizedValue(
    {'en': title, ...titleI18n},
    languageCode,
    fallback: title,
  );
  String localizedQuestion(String languageCode) => localizedValue(
    {'en': questionText, ...questionTextI18n},
    languageCode,
    fallback: questionText,
  );
  String localizedInstructions(String languageCode) => localizedValue(
    {'en': instructions, ...instructionsI18n},
    languageCode,
    fallback: instructions,
  );

  CourseworkQuestion copyWith({
    String? id,
    String? themeId,
    int? number,
    CourseworkQuestionType? type,
    String? title,
    Map<String, String>? titleI18n,
    String? questionText,
    Map<String, String>? questionTextI18n,
    String? instructions,
    Map<String, String>? instructionsI18n,
    double? marks,
    String? difficulty,
    String? learningObjective,
    List<CourseworkAnswerOption>? answerOptions,
    List<String>? acceptedAnswers,
    bool? requiresWrittenExplanation,
    bool? requiresImage,
    int? minimumCharacters,
    int? maximumCharacters,
    int? minimumImages,
    int? maximumImages,
    List<String>? allowedFileTypes,
    String? chartAsset,
    String? symbol,
    String? timeframe,
    DateTime? marketDate,
    List<CourseworkChartCandle>? chartCandles,
    List<CourseworkAnswerZone>? answerZones,
    String? gradingMode,
    List<CourseworkRubricCriterion>? rubric,
    String? sampleAnswer,
    String? reviewerGuidance,
    int? version,
    String? status,
  }) => CourseworkQuestion(
    id: id ?? this.id,
    themeId: themeId ?? this.themeId,
    number: number ?? this.number,
    type: type ?? this.type,
    title: title ?? this.title,
    titleI18n: titleI18n ?? this.titleI18n,
    questionText: questionText ?? this.questionText,
    questionTextI18n: questionTextI18n ?? this.questionTextI18n,
    instructions: instructions ?? this.instructions,
    instructionsI18n: instructionsI18n ?? this.instructionsI18n,
    marks: marks ?? this.marks,
    difficulty: difficulty ?? this.difficulty,
    learningObjective: learningObjective ?? this.learningObjective,
    answerOptions: answerOptions ?? this.answerOptions,
    acceptedAnswers: acceptedAnswers ?? this.acceptedAnswers,
    requiresWrittenExplanation:
        requiresWrittenExplanation ?? this.requiresWrittenExplanation,
    requiresImage: requiresImage ?? this.requiresImage,
    minimumCharacters: minimumCharacters ?? this.minimumCharacters,
    maximumCharacters: maximumCharacters ?? this.maximumCharacters,
    minimumImages: minimumImages ?? this.minimumImages,
    maximumImages: maximumImages ?? this.maximumImages,
    allowedFileTypes: allowedFileTypes ?? this.allowedFileTypes,
    chartAsset: chartAsset ?? this.chartAsset,
    symbol: symbol ?? this.symbol,
    timeframe: timeframe ?? this.timeframe,
    marketDate: marketDate ?? this.marketDate,
    chartCandles: chartCandles ?? this.chartCandles,
    answerZones: answerZones ?? this.answerZones,
    gradingMode: gradingMode ?? this.gradingMode,
    rubric: rubric ?? this.rubric,
    sampleAnswer: sampleAnswer ?? this.sampleAnswer,
    reviewerGuidance: reviewerGuidance ?? this.reviewerGuidance,
    version: version ?? this.version,
    status: status ?? this.status,
  );

  factory CourseworkQuestion.fromMap(Map<String, dynamic> data) {
    return CourseworkQuestion(
      id: '${data['question_id'] ?? data['questionId'] ?? data['id'] ?? ''}',
      themeId: '${data['theme_id'] ?? data['themeId'] ?? ''}',
      number: firestoreInt(
        data['question_number'] ?? data['questionNumber'] ?? data['order'],
      ),
      type: CourseworkQuestionType.fromStorage(
        data['question_type'] ?? data['questionType'] ?? data['type'],
      ),
      title: localizedValue(data['title'], 'en'),
      titleI18n: _localizedMap(
        data['title_i18n'] ?? data['titleI18n'] ?? data['title'],
      ),
      questionText: localizedValue(
        data['question_text'] ?? data['questionText'] ?? data['prompt'],
        'en',
      ),
      questionTextI18n: _localizedMap(
        data['question_text_i18n'] ??
            data['questionTextI18n'] ??
            data['prompt'],
      ),
      instructions: localizedValue(data['instructions'], 'en'),
      instructionsI18n: _localizedMap(
        data['instructions_i18n'] ?? data['instructions'],
      ),
      marks: _finiteDouble(data['marks']),
      difficulty: '${data['difficulty'] ?? 'intermediate'}',
      learningObjective:
          '${data['learning_objective'] ?? data['learningObjective'] ?? ''}',
      answerOptions: _mapList(
        data['answer_options'] ?? data['answerOptions'] ?? data['options'],
      ).map(CourseworkAnswerOption.fromMap).toList(growable: false),
      acceptedAnswers: _stringList(
        data['accepted_answers'] ?? data['acceptedAnswers'],
      ),
      requiresWrittenExplanation: firestoreBool(
        data['requires_written_explanation'] ??
            data['requiresWrittenExplanation'] ??
            data['requiresText'],
      ),
      requiresImage: firestoreBool(
        data['requires_image'] ?? data['requiresImage'],
      ),
      minimumCharacters: firestoreInt(
        data['minimum_characters'] ?? data['minimumCharacters'],
      ),
      maximumCharacters: firestoreInt(
        data['maximum_characters'] ?? data['maximumCharacters'],
      ),
      minimumImages: firestoreInt(
        data['minimum_images'] ?? data['minimumImages'],
      ),
      maximumImages: firestoreInt(
        data['maximum_images'] ?? data['maximumImages'],
      ),
      allowedFileTypes: _stringList(
        data['allowed_file_types'] ?? data['allowedFileTypes'],
      ),
      chartAsset: '${data['chart_asset'] ?? data['chartAsset'] ?? ''}',
      symbol: '${data['symbol'] ?? _map(data['chartData'])['symbol'] ?? ''}',
      timeframe:
          '${data['timeframe'] ?? _map(data['chartData'])['timeframe'] ?? ''}',
      marketDate: firestoreDate(data['market_date'] ?? data['marketDate']),
      chartCandles: _mapList(
        data['chart_candles'] ??
            data['chartCandles'] ??
            _map(data['chartData'])['candles'],
      ).map(CourseworkChartCandle.fromMap).toList(growable: false),
      answerZones: _mapList(
        data['answer_zones'] ??
            data['answerZones'] ??
            _map(data['chartData'])['zones'],
      ).map(CourseworkAnswerZone.fromMap).toList(growable: false),
      gradingMode:
          '${data['grading_mode'] ?? data['gradingMode'] ?? (CourseworkQuestionType.fromStorage(data['type']).requiresManualReview ? 'manual' : 'automatic')}',
      rubric: _mapList(
        data['grading_rubric'] ?? data['gradingRubric'],
      ).map(CourseworkRubricCriterion.fromMap).toList(growable: false),
      sampleAnswer: '${data['sample_answer'] ?? data['sampleAnswer'] ?? ''}',
      reviewerGuidance:
          '${data['reviewer_guidance'] ?? data['reviewerGuidance'] ?? ''}',
      version: firestoreInt(data['version'], fallback: 1),
      status: '${data['status'] ?? 'published'}',
    );
  }

  factory CourseworkQuestion.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> snapshot,
  ) => CourseworkQuestion.fromMap({
    ...?snapshot.data(),
    'question_id': snapshot.id,
  });

  Map<String, Object?> toMap({bool includeAnswers = true}) => {
    'question_id': id,
    'theme_id': themeId,
    'question_number': number,
    'question_type': type.storageValue,
    'title': title,
    'title_i18n': titleI18n,
    'question_text': questionText,
    'question_text_i18n': questionTextI18n,
    'instructions': instructions,
    'instructions_i18n': instructionsI18n,
    'marks': marks,
    'difficulty': difficulty,
    'learning_objective': learningObjective,
    'answer_options': answerOptions
        .map((item) => item.toMap(includeCorrectAnswer: includeAnswers))
        .toList(),
    if (includeAnswers) 'accepted_answers': acceptedAnswers,
    'requires_written_explanation': requiresWrittenExplanation,
    'requires_image': requiresImage,
    'minimum_characters': minimumCharacters,
    'maximum_characters': maximumCharacters,
    'minimum_images': minimumImages,
    'maximum_images': maximumImages,
    'allowed_file_types': allowedFileTypes,
    'chart_asset': chartAsset,
    'symbol': symbol,
    'timeframe': timeframe,
    if (marketDate != null) 'market_date': Timestamp.fromDate(marketDate!),
    'chart_candles': chartCandles.map((item) => item.toMap()).toList(),
    'answer_zones': answerZones
        .map((item) => item.toMap(includeAnswer: includeAnswers))
        .toList(),
    'grading_mode': gradingMode,
    if (includeAnswers)
      'grading_rubric': rubric.map((item) => item.toMap()).toList(),
    if (includeAnswers) 'sample_answer': sampleAnswer,
    if (includeAnswers) 'reviewer_guidance': reviewerGuidance,
    'version': version,
    'status': status,
  };
}

class CourseworkTheme {
  const CourseworkTheme({
    required this.id,
    required this.code,
    required this.title,
    required this.category,
    required this.questions,
    this.titleI18n = const {},
    this.shortDescription = '',
    this.shortDescriptionI18n = const {},
    this.categoryId = '',
    this.categoryI18n = const {},
    this.difficulty = 'intermediate',
    this.learningObjectives = const [],
    this.learningObjectivesZh = const [],
    this.keywords = const [],
    this.searchTokens = const [],
    this.instructions = '',
    this.instructionsI18n = const {},
    this.estimatedCompletionMinutes = 90,
    this.totalMarks = 100,
    this.status = 'draft',
    this.version = 1,
    this.isActive = true,
    this.isAssignable = true,
    this.sortOrder = 0,
    this.languageAvailability = const ['en', 'zh'],
    this.publishedAt,
    this.createdAt,
    this.updatedAt,
    this.createdBy = '',
    this.updatedBy = '',
    this.schemaVersion = 1,
  });

  final String id;
  final String code;
  final String title;
  final Map<String, String> titleI18n;
  final String shortDescription;
  final Map<String, String> shortDescriptionI18n;
  final String categoryId;
  final String category;
  final Map<String, String> categoryI18n;
  final String difficulty;
  final List<String> learningObjectives;
  final List<String> learningObjectivesZh;
  final List<String> keywords;
  final List<String> searchTokens;
  final String instructions;
  final Map<String, String> instructionsI18n;
  final int estimatedCompletionMinutes;
  final double totalMarks;
  final String status;
  final int version;
  final bool isActive;
  final bool isAssignable;
  final int sortOrder;
  final List<String> languageAvailability;
  final List<CourseworkQuestion> questions;
  final DateTime? publishedAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final String createdBy;
  final String updatedBy;
  final int schemaVersion;

  CourseworkDifficulty get difficultyValue =>
      CourseworkDifficulty.fromFirestore(difficulty);

  CourseworkPublicationStatus get publicationStatus =>
      CourseworkPublicationStatus.fromFirestore(status);

  bool get isEligibleForAssignment =>
      publicationStatus == CourseworkPublicationStatus.published &&
      isActive &&
      isAssignable;

  String localizedTitle(String languageCode) => localizedValue(
    {'en': title, ...titleI18n},
    languageCode,
    fallback: title,
  );

  bool matchesSearch(String query) {
    final needles = _searchForms(query);
    if (needles.isEmpty) return true;
    final haystack = _searchForms(
      [
        code,
        title,
        ...titleI18n.values,
        shortDescription,
        ...shortDescriptionI18n.values,
        categoryId,
        category,
        ...categoryI18n.values,
        ...learningObjectives,
        ...learningObjectivesZh,
        ...keywords,
        ...searchTokens,
      ].join(' '),
    );
    return needles.every(
      (needle) => haystack.any((value) => value.contains(needle)),
    );
  }

  CourseworkTheme copyWith({
    String? id,
    String? code,
    String? title,
    Map<String, String>? titleI18n,
    String? shortDescription,
    Map<String, String>? shortDescriptionI18n,
    String? categoryId,
    String? category,
    Map<String, String>? categoryI18n,
    String? difficulty,
    List<String>? learningObjectives,
    List<String>? learningObjectivesZh,
    List<String>? keywords,
    List<String>? searchTokens,
    String? instructions,
    Map<String, String>? instructionsI18n,
    int? estimatedCompletionMinutes,
    double? totalMarks,
    String? status,
    int? version,
    bool? isActive,
    bool? isAssignable,
    int? sortOrder,
    List<String>? languageAvailability,
    List<CourseworkQuestion>? questions,
    DateTime? publishedAt,
    DateTime? createdAt,
    DateTime? updatedAt,
    String? createdBy,
    String? updatedBy,
    int? schemaVersion,
  }) => CourseworkTheme(
    id: id ?? this.id,
    code: code ?? this.code,
    title: title ?? this.title,
    titleI18n: titleI18n ?? this.titleI18n,
    shortDescription: shortDescription ?? this.shortDescription,
    shortDescriptionI18n: shortDescriptionI18n ?? this.shortDescriptionI18n,
    categoryId: categoryId ?? this.categoryId,
    category: category ?? this.category,
    categoryI18n: categoryI18n ?? this.categoryI18n,
    difficulty: difficulty ?? this.difficulty,
    learningObjectives: learningObjectives ?? this.learningObjectives,
    learningObjectivesZh: learningObjectivesZh ?? this.learningObjectivesZh,
    keywords: keywords ?? this.keywords,
    searchTokens: searchTokens ?? this.searchTokens,
    instructions: instructions ?? this.instructions,
    instructionsI18n: instructionsI18n ?? this.instructionsI18n,
    estimatedCompletionMinutes:
        estimatedCompletionMinutes ?? this.estimatedCompletionMinutes,
    totalMarks: totalMarks ?? this.totalMarks,
    status: status ?? this.status,
    version: version ?? this.version,
    isActive: isActive ?? this.isActive,
    isAssignable: isAssignable ?? this.isAssignable,
    sortOrder: sortOrder ?? this.sortOrder,
    languageAvailability: languageAvailability ?? this.languageAvailability,
    questions: questions ?? this.questions,
    publishedAt: publishedAt ?? this.publishedAt,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
    createdBy: createdBy ?? this.createdBy,
    updatedBy: updatedBy ?? this.updatedBy,
    schemaVersion: schemaVersion ?? this.schemaVersion,
  );

  factory CourseworkTheme.fromMap(Map<String, dynamic> data) {
    final categorySource =
        data['categoryId'] ?? data['category_id'] ?? data['category'];
    final categoryLabel =
        data['categoryName'] ?? data['category_name'] ?? data['category'];
    final normalizedCategory = CourseworkTaxonomy.categoryId(
      categorySource,
      label: categoryLabel,
    );
    final categoryDefinition = CourseworkTaxonomy.find(normalizedCategory);
    final normalizedDifficulty = CourseworkDifficulty.fromFirestore(
      data['difficulty'] ?? data['difficultyLevel'] ?? data['level'],
    );
    final statusSource =
        data['status'] ??
        data['publicationStatus'] ??
        data['publication_status'] ??
        data['isPublished'];
    final normalizedStatus = CourseworkPublicationStatus.fromFirestore(
      statusSource,
    );
    return CourseworkTheme(
      id: _firestoreDocumentId(
        data['theme_id'] ?? data['themeId'] ?? data['id'],
      ),
      code: '${data['theme_code'] ?? data['themeCode'] ?? data['code'] ?? ''}',
      title: localizedValue(data['title'] ?? data['name'], 'en'),
      titleI18n: _localizedMap(
        data['title_i18n'] ??
            data['titleI18n'] ??
            data['localizedTitles'] ??
            data['title'] ??
            data['name'],
      ),
      shortDescription: localizedValue(
        data['short_description'] ??
            data['shortDescription'] ??
            data['description'],
        'en',
      ),
      shortDescriptionI18n: _localizedMap(
        data['short_description_i18n'] ??
            data['shortDescriptionI18n'] ??
            data['description'],
      ),
      categoryId: normalizedCategory,
      category: localizedValue(
        categoryLabel,
        'en',
        fallback: categoryDefinition?.en,
      ),
      categoryI18n: _localizedMap(
        data['categoryName'] ??
            data['category_name'] ??
            data['category_i18n'] ??
            (categoryDefinition == null
                ? null
                : {'en': categoryDefinition.en, 'zh': categoryDefinition.zh}),
      ),
      difficulty: normalizedDifficulty.storageValue,
      learningObjectives: _stringList(
        _localizedList(
          data['learning_objectives'] ?? data['learningObjectives'],
          'en',
        ),
      ),
      learningObjectivesZh: _stringList(
        _localizedList(
          data['learning_objectives_i18n'] ?? data['learningObjectives'],
          'zh',
        ),
      ),
      keywords: _stringList(data['keywords']),
      searchTokens: _stringList(data['searchTokens'] ?? data['search_tokens']),
      instructions: localizedValue(data['instructions'], 'en'),
      instructionsI18n: _localizedMap(data['instructions_i18n']),
      estimatedCompletionMinutes: firestoreInt(
        data['estimated_completion_minutes'] ??
            data['estimatedCompletionMinutes'] ??
            data['estimatedMinutes'],
        fallback: 90,
      ),
      totalMarks: _finiteDouble(data['total_marks'] ?? data['totalMarks']),
      status: normalizedStatus.storageValue,
      version: firestoreInt(data['version'], fallback: 1),
      isActive:
          data['is_active'] == null &&
              data['isActive'] == null &&
              data['active'] == null
          ? true
          : firestoreBool(
              data['is_active'] ?? data['isActive'] ?? data['active'],
            ),
      isAssignable:
          data['isAssignable'] == null && data['is_assignable'] == null
          ? true
          : firestoreBool(data['isAssignable'] ?? data['is_assignable']),
      sortOrder: firestoreInt(data['sortOrder'] ?? data['sort_order']),
      languageAvailability:
          _stringList(
            data['language_availability'] ?? data['languageAvailability'],
          ).isEmpty
          ? const ['en', 'zh']
          : _stringList(
              data['language_availability'] ?? data['languageAvailability'],
            ),
      questions: _mapList(
        data['question_snapshot'] ??
            data['questionSnapshot'] ??
            data['questions'],
      ).map(CourseworkQuestion.fromMap).toList(growable: false),
      publishedAt: firestoreDate(data['published_at'] ?? data['publishedAt']),
      createdAt: firestoreDate(data['created_at'] ?? data['createdAt']),
      updatedAt: firestoreDate(data['updated_at'] ?? data['updatedAt']),
      createdBy: '${data['created_by'] ?? data['createdBy'] ?? ''}',
      updatedBy: '${data['updated_by'] ?? data['updatedBy'] ?? ''}',
      schemaVersion: firestoreInt(
        data['schema_version'] ?? data['schemaVersion'],
        fallback: 1,
      ),
    );
  }

  Map<String, Object?> toMap({bool includeQuestions = true}) => {
    'id': id,
    'code': code,
    'title': {'en': title, ...titleI18n},
    'description': {'en': shortDescription, ...shortDescriptionI18n},
    'categoryId': categoryId,
    'categoryName': {'en': category, ...categoryI18n},
    'difficulty': difficultyValue.storageValue,
    'learningObjectives': {
      'en': learningObjectives,
      'zh': learningObjectivesZh,
    },
    'keywords': keywords,
    'searchTokens': searchTokens,
    'estimatedMinutes': estimatedCompletionMinutes,
    'questionCount': questions.length,
    'totalMarks': totalMarks,
    'courseworkWeight': .7,
    'status': publicationStatus.storageValue,
    'version': version,
    'isActive': isActive,
    'isAssignable': isAssignable,
    'sortOrder': sortOrder,
    'languageAvailability': languageAvailability,
    if (includeQuestions)
      'questionSnapshot': questions.map((item) => item.toMap()).toList(),
    if (publishedAt != null) 'publishedAt': Timestamp.fromDate(publishedAt!),
    if (createdAt != null) 'createdAt': Timestamp.fromDate(createdAt!),
    if (updatedAt != null) 'updatedAt': Timestamp.fromDate(updatedAt!),
    if (createdBy.isNotEmpty) 'createdBy': createdBy,
    if (updatedBy.isNotEmpty) 'updatedBy': updatedBy,
    'schemaVersion': schemaVersion,
  };
}

class CourseworkAssignment {
  const CourseworkAssignment({
    required this.id,
    required this.themeId,
    required this.themeVersion,
    required this.userId,
    required this.status,
    required this.themeSnapshot,
    this.assignedAt,
    this.availableFrom,
    this.dueAt,
    this.expiresAt,
    this.customInstructions = '',
    this.allowRevision = true,
    this.maximumRevisions = 1,
    this.themeCode = '',
    this.themeTitle = '',
  });

  final String id;
  final String themeId;
  final int themeVersion;
  final String userId;
  final CourseworkStatus status;
  final CourseworkTheme themeSnapshot;
  final DateTime? assignedAt;
  final DateTime? availableFrom;
  final DateTime? dueAt;
  final DateTime? expiresAt;
  final String customInstructions;
  final bool allowRevision;
  final int maximumRevisions;
  final String themeCode;
  final String themeTitle;

  /// Resolves old assignment relationships without ever using an ambiguous
  /// title. The canonical Firestore document ID always wins when it exists.
  String? resolveThemeId(Iterable<CourseworkTheme> themes) {
    final available = themes.toList(growable: false);
    if (themeId.isNotEmpty && available.any((item) => item.id == themeId)) {
      return themeId;
    }
    if (themeCode.isNotEmpty) {
      final matches = available
          .where((item) => item.code.toLowerCase() == themeCode.toLowerCase())
          .toList(growable: false);
      if (matches.length == 1) return matches.single.id;
    }
    if (themeTitle.isNotEmpty) {
      final normalized = themeTitle.trim().toLowerCase();
      final matches = available
          .where(
            (item) => <String>{
              item.title,
              ...item.titleI18n.values,
            }.any((title) => title.trim().toLowerCase() == normalized),
          )
          .toList(growable: false);
      if (matches.length == 1) return matches.single.id;
    }
    return null;
  }

  factory CourseworkAssignment.fromMap(Map<String, dynamic> data) {
    final rawTheme =
        data['selected_question_snapshot'] ??
        data['selectedQuestionSnapshot'] ??
        data['theme_snapshot'] ??
        data['themeSnapshot'] ??
        const <String, dynamic>{};
    final themeMap = rawTheme is Map
        ? Map<String, dynamic>.from(rawTheme)
        : const <String, dynamic>{};
    final canonicalThemeId = _firestoreDocumentId(
      data['theme_id'] ??
          data['themeId'] ??
          data['courseworkThemeId'] ??
          data['coursework_theme_id'] ??
          data['assignmentThemeId'] ??
          data['assignment_theme_id'] ??
          data['theme'],
    );
    return CourseworkAssignment(
      id: '${data['assignment_id'] ?? data['assignmentId'] ?? data['id'] ?? ''}',
      themeId: canonicalThemeId,
      themeVersion: firestoreInt(
        data['theme_version'] ?? data['themeVersion'],
        fallback: 1,
      ),
      userId:
          '${data['assigned_to_user_id'] ?? data['assignedToUserId'] ?? ''}',
      status: CourseworkStatus.fromStorage(data['status']),
      themeSnapshot: CourseworkTheme.fromMap(themeMap),
      assignedAt: firestoreDate(data['assigned_at'] ?? data['assignedAt']),
      availableFrom: firestoreDate(
        data['available_from'] ?? data['availableFrom'],
      ),
      dueAt: firestoreDate(data['due_at'] ?? data['dueAt']),
      expiresAt: firestoreDate(data['expires_at'] ?? data['expiresAt']),
      customInstructions:
          '${data['custom_instructions'] ?? data['customInstructions'] ?? ''}',
      allowRevision:
          data['allow_revision'] == null && data['allowRevision'] == null
          ? true
          : firestoreBool(data['allow_revision'] ?? data['allowRevision']),
      maximumRevisions: firestoreInt(
        data['maximum_revisions'] ?? data['maximumRevisions'],
        fallback: 1,
      ),
      themeCode:
          '${data['theme_code'] ?? data['themeCode'] ?? themeMap['code'] ?? themeMap['theme_code'] ?? ''}',
      themeTitle: localizedValue(
        data['theme_title'] ??
            data['themeTitle'] ??
            data['themeName'] ??
            data['theme_name'] ??
            themeMap['title'] ??
            (data['theme'] is String ? data['theme'] : null),
        'en',
      ),
    );
  }

  factory CourseworkAssignment.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> snapshot,
  ) => CourseworkAssignment.fromMap({...?snapshot.data(), 'id': snapshot.id});
}

abstract final class CourseworkThemeFilter {
  static List<CourseworkTheme> apply(
    Iterable<CourseworkTheme> themes, {
    String search = '',
    String? categoryId,
    Object? difficulty,
    Object? publicationStatus,
    bool includeInactive = true,
    bool assignableOnly = false,
  }) {
    final category = _optionalFilter(categoryId);
    final difficultyFilter = _optionalFilter(difficulty);
    final statusFilter = _optionalFilter(publicationStatus);
    final normalizedCategory = category == null
        ? null
        : CourseworkTaxonomy.categoryId(category);
    final normalizedDifficulty = difficultyFilter == null
        ? null
        : CourseworkDifficulty.fromFirestore(difficultyFilter).storageValue;
    final normalizedStatus = statusFilter == null
        ? null
        : CourseworkPublicationStatus.fromFirestore(statusFilter).storageValue;
    return themes
        .where(
          (theme) =>
              theme.matchesSearch(search) &&
              (normalizedCategory == null ||
                  theme.categoryId == normalizedCategory) &&
              (normalizedDifficulty == null ||
                  theme.difficultyValue.storageValue == normalizedDifficulty) &&
              (normalizedStatus == null ||
                  theme.publicationStatus.storageValue == normalizedStatus) &&
              (includeInactive || theme.isActive) &&
              (!assignableOnly || theme.isEligibleForAssignment),
        )
        .toList(growable: false);
  }

  static String? _optionalFilter(Object? value) {
    final normalized = value?.toString().trim() ?? '';
    if (normalized.isEmpty ||
        normalized.toLowerCase() == 'all' ||
        normalized == '全部') {
      return null;
    }
    return normalized;
  }
}

class CourseworkAttachment {
  const CourseworkAttachment({
    required this.id,
    required this.ownerUserId,
    required this.attemptId,
    required this.questionId,
    required this.storagePath,
    required this.fileName,
    required this.mimeType,
    required this.sizeBytes,
    required this.checksum,
    this.uploadedAt,
    this.uploadStatus = 'complete',
  });

  final String id;
  final String ownerUserId;
  final String attemptId;
  final String questionId;
  final String storagePath;
  final String fileName;
  final String mimeType;
  final int sizeBytes;
  final String checksum;
  final DateTime? uploadedAt;
  final String uploadStatus;

  factory CourseworkAttachment.fromMap(Map<String, dynamic> data) =>
      CourseworkAttachment(
        id: '${data['attachment_id'] ?? data['id'] ?? ''}',
        ownerUserId: '${data['owner_user_id'] ?? ''}',
        attemptId: '${data['attempt_id'] ?? ''}',
        questionId: '${data['question_id'] ?? ''}',
        storagePath: '${data['storage_path'] ?? ''}',
        fileName: '${data['file_name'] ?? ''}',
        mimeType: '${data['mime_type'] ?? ''}',
        sizeBytes: firestoreInt(data['size_bytes']),
        checksum: '${data['checksum'] ?? ''}',
        uploadedAt: firestoreDate(data['uploaded_at']),
        uploadStatus: '${data['upload_status'] ?? 'complete'}',
      );

  Map<String, Object?> toMap() => {
    'attachment_id': id,
    'owner_user_id': ownerUserId,
    'attempt_id': attemptId,
    'question_id': questionId,
    'storage_path': storagePath,
    'file_name': fileName,
    'mime_type': mimeType,
    'size_bytes': sizeBytes,
    'checksum': checksum,
    if (uploadedAt != null) 'uploaded_at': Timestamp.fromDate(uploadedAt!),
    'upload_status': uploadStatus,
  };
}

class CourseworkAnswer {
  const CourseworkAnswer({
    required this.questionId,
    required this.questionNumber,
    required this.questionType,
    this.textAnswer = '',
    this.selectedOptionIds = const [],
    this.selectedChartZone = '',
    this.selectedMinimumPrice,
    this.selectedMaximumPrice,
    this.marketAnalysisData = const {},
    this.orderManagementData = const {},
    this.attachments = const [],
    this.isCompleted = false,
    this.isFlagged = false,
    this.automaticMark,
    this.manualMark,
    this.maximumMark = 0,
    this.reviewerFeedback = '',
    this.clientVersion = 0,
    this.savedAt,
  });

  final String questionId;
  final int questionNumber;
  final CourseworkQuestionType questionType;
  final String textAnswer;
  final List<String> selectedOptionIds;
  final String selectedChartZone;
  final double? selectedMinimumPrice;
  final double? selectedMaximumPrice;
  final Map<String, Object?> marketAnalysisData;
  final Map<String, Object?> orderManagementData;
  final List<CourseworkAttachment> attachments;
  final bool isCompleted;
  final bool isFlagged;
  final double? automaticMark;
  final double? manualMark;
  final double maximumMark;
  final String reviewerFeedback;
  final int clientVersion;
  final DateTime? savedAt;

  CourseworkAnswer copyWith({
    String? textAnswer,
    List<String>? selectedOptionIds,
    String? selectedChartZone,
    double? selectedMinimumPrice,
    double? selectedMaximumPrice,
    Map<String, Object?>? marketAnalysisData,
    Map<String, Object?>? orderManagementData,
    List<CourseworkAttachment>? attachments,
    bool? isCompleted,
    bool? isFlagged,
    int? clientVersion,
    DateTime? savedAt,
  }) => CourseworkAnswer(
    questionId: questionId,
    questionNumber: questionNumber,
    questionType: questionType,
    textAnswer: textAnswer ?? this.textAnswer,
    selectedOptionIds: selectedOptionIds ?? this.selectedOptionIds,
    selectedChartZone: selectedChartZone ?? this.selectedChartZone,
    selectedMinimumPrice: selectedMinimumPrice ?? this.selectedMinimumPrice,
    selectedMaximumPrice: selectedMaximumPrice ?? this.selectedMaximumPrice,
    marketAnalysisData: marketAnalysisData ?? this.marketAnalysisData,
    orderManagementData: orderManagementData ?? this.orderManagementData,
    attachments: attachments ?? this.attachments,
    isCompleted: isCompleted ?? this.isCompleted,
    isFlagged: isFlagged ?? this.isFlagged,
    automaticMark: automaticMark,
    manualMark: manualMark,
    maximumMark: maximumMark,
    reviewerFeedback: reviewerFeedback,
    clientVersion: clientVersion ?? this.clientVersion,
    savedAt: savedAt ?? this.savedAt,
  );

  factory CourseworkAnswer.fromMap(Map<String, dynamic> data) =>
      CourseworkAnswer(
        questionId: '${data['question_id'] ?? data['questionId'] ?? ''}',
        questionNumber: firestoreInt(
          data['question_number'] ?? data['questionNumber'],
        ),
        questionType: CourseworkQuestionType.fromStorage(
          data['question_type'] ?? data['questionType'],
        ),
        textAnswer: '${data['text_answer'] ?? data['textAnswer'] ?? ''}',
        selectedOptionIds: _stringList(
          data['selected_option_ids'] ?? data['selectedOptionIds'],
        ),
        selectedChartZone:
            '${data['selected_chart_zone'] ?? data['selectedChartZone'] ?? ''}',
        selectedMinimumPrice: _nullableFiniteDouble(
          data['selected_minimum_price'] ?? data['selectedMinimumPrice'],
        ),
        selectedMaximumPrice: _nullableFiniteDouble(
          data['selected_maximum_price'] ?? data['selectedMaximumPrice'],
        ),
        marketAnalysisData: _objectMap(
          data['market_analysis_data'] ?? data['marketAnalysisData'],
        ),
        orderManagementData: _objectMap(
          data['order_management_data'] ?? data['orderManagementData'],
        ),
        attachments: _mapList(
          data['attachments'],
        ).map(CourseworkAttachment.fromMap).toList(growable: false),
        isCompleted: firestoreBool(data['is_completed'] ?? data['isCompleted']),
        isFlagged: firestoreBool(data['is_flagged'] ?? data['isFlagged']),
        automaticMark: _nullableFiniteDouble(
          data['automatic_mark'] ?? data['automaticMark'],
        ),
        manualMark: _nullableFiniteDouble(
          data['manual_mark'] ?? data['manualMark'],
        ),
        maximumMark: _finiteDouble(data['maximum_mark'] ?? data['maximumMark']),
        reviewerFeedback:
            '${data['reviewer_feedback'] ?? data['reviewerFeedback'] ?? ''}',
        clientVersion: firestoreInt(
          data['client_version'] ?? data['clientVersion'],
        ),
        savedAt: firestoreDate(data['saved_at'] ?? data['savedAt']),
      );

  Map<String, Object?> toMap({bool includeMarks = false}) => {
    'question_id': questionId,
    'question_number': questionNumber,
    'question_type': questionType.storageValue,
    'text_answer': textAnswer,
    'selected_option_ids': selectedOptionIds,
    'selected_chart_zone': selectedChartZone,
    if (selectedMinimumPrice != null)
      'selected_minimum_price': selectedMinimumPrice,
    if (selectedMaximumPrice != null)
      'selected_maximum_price': selectedMaximumPrice,
    'market_analysis_data': marketAnalysisData,
    'order_management_data': orderManagementData,
    'attachments': attachments.map((item) => item.toMap()).toList(),
    'is_completed': isCompleted,
    'is_flagged': isFlagged,
    'maximum_mark': maximumMark,
    'client_version': clientVersion,
    if (includeMarks && automaticMark != null) 'automatic_mark': automaticMark,
    if (includeMarks && manualMark != null) 'manual_mark': manualMark,
    if (includeMarks) 'reviewer_feedback': reviewerFeedback,
    if (savedAt != null) 'saved_at': savedAt!.toUtc().toIso8601String(),
  };
}

class CourseworkAttempt {
  const CourseworkAttempt({
    required this.id,
    required this.assignmentId,
    required this.themeId,
    required this.themeVersion,
    required this.userId,
    required this.status,
    required this.themeSnapshot,
    this.answers = const {},
    this.startedAt,
    this.lastSavedAt,
    this.submittedAt,
    this.resubmittedAt,
    this.gradedAt,
    this.rawScore,
    this.weightedScore,
    this.automaticScore = 0,
    this.manualScore = 0,
    this.revisionNumber = 0,
    this.reviewerId = '',
    this.gradingStatus = 'notStarted',
    this.declarationAccepted = false,
    this.reviewerFeedback = '',
    this.calculationVersion = 1,
  });

  final String id;
  final String assignmentId;
  final String themeId;
  final int themeVersion;
  final String userId;
  final CourseworkStatus status;
  final CourseworkTheme themeSnapshot;
  final Map<String, CourseworkAnswer> answers;
  final DateTime? startedAt;
  final DateTime? lastSavedAt;
  final DateTime? submittedAt;
  final DateTime? resubmittedAt;
  final DateTime? gradedAt;
  final double? rawScore;
  final double? weightedScore;
  final double automaticScore;
  final double manualScore;
  final int revisionNumber;
  final String reviewerId;
  final String gradingStatus;
  final bool declarationAccepted;
  final String reviewerFeedback;
  final int calculationVersion;

  int get completedQuestionCount =>
      answers.values.where((answer) => answer.isCompleted).length;
  double get completion => themeSnapshot.questions.isEmpty
      ? 0
      : completedQuestionCount / themeSnapshot.questions.length;
  bool get isFinalised =>
      const {'finalised', 'completed'}.contains(gradingStatus.toLowerCase()) &&
      rawScore != null;

  factory CourseworkAttempt.fromMap(Map<String, dynamic> data) {
    final rawAnswers = data['answers'];
    final answers = <String, CourseworkAnswer>{};
    if (rawAnswers is Map) {
      for (final entry in rawAnswers.entries) {
        if (entry.value is Map) {
          answers['${entry.key}'] = CourseworkAnswer.fromMap(
            Map<String, dynamic>.from(entry.value as Map),
          );
        }
      }
    }
    return CourseworkAttempt(
      id: '${data['attempt_id'] ?? data['attemptId'] ?? data['id'] ?? ''}',
      assignmentId: '${data['assignment_id'] ?? data['assignmentId'] ?? ''}',
      themeId: '${data['theme_id'] ?? data['themeId'] ?? ''}',
      themeVersion: firestoreInt(
        data['theme_version'] ?? data['themeVersion'],
        fallback: 1,
      ),
      userId: '${data['user_id'] ?? data['userId'] ?? ''}',
      status: CourseworkStatus.fromStorage(data['status']),
      themeSnapshot: CourseworkTheme.fromMap(
        data['theme_snapshot'] is Map
            ? Map<String, dynamic>.from(data['theme_snapshot'] as Map)
            : const {},
      ),
      answers: answers,
      startedAt: firestoreDate(data['started_at'] ?? data['startedAt']),
      lastSavedAt: firestoreDate(data['last_saved_at'] ?? data['lastSavedAt']),
      submittedAt: firestoreDate(data['submitted_at'] ?? data['submittedAt']),
      resubmittedAt: firestoreDate(
        data['resubmitted_at'] ?? data['resubmittedAt'],
      ),
      gradedAt: firestoreDate(data['graded_at'] ?? data['gradedAt']),
      rawScore: _nullableFiniteDouble(data['raw_score'] ?? data['rawScore']),
      weightedScore: _nullableFiniteDouble(
        data['weighted_score'] ?? data['weightedScore'],
      ),
      automaticScore: _finiteDouble(
        data['automatic_score'] ?? data['automaticScore'],
      ),
      manualScore: _finiteDouble(data['manual_score'] ?? data['manualScore']),
      revisionNumber: firestoreInt(
        data['revision_number'] ?? data['revisionNumber'],
      ),
      reviewerId: '${data['reviewer_id'] ?? data['reviewerId'] ?? ''}',
      gradingStatus:
          '${data['grading_status'] ?? data['gradingStatus'] ?? 'notStarted'}',
      declarationAccepted: firestoreBool(
        data['declaration_accepted'] ?? data['declarationAccepted'],
      ),
      reviewerFeedback:
          '${data['reviewer_feedback'] ?? data['reviewerFeedback'] ?? ''}',
      calculationVersion: firestoreInt(
        data['calculation_version'] ?? data['calculationVersion'],
        fallback: 1,
      ),
    );
  }

  factory CourseworkAttempt.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> snapshot,
  ) => CourseworkAttempt.fromMap({...?snapshot.data(), 'id': snapshot.id});
}

class AssessmentSettings {
  const AssessmentSettings({
    this.courseworkWeight = .7,
    this.finalExamWeight = .3,
    this.passMark = 60,
    this.gradeBoundaries = const {'A': 80, 'B': 70, 'C': 60, 'D': 50, 'F': 0},
    this.calculationVersion = 1,
    this.showCorrectAnswers = true,
  });

  final double courseworkWeight;
  final double finalExamWeight;
  final double passMark;
  final Map<String, double> gradeBoundaries;
  final int calculationVersion;
  final bool showCorrectAnswers;

  double get totalWeight => courseworkWeight + finalExamWeight;

  factory AssessmentSettings.fromMap(Map<String, dynamic> data) {
    final raw = data['grade_boundaries'] ?? data['gradeBoundaries'];
    return AssessmentSettings(
      courseworkWeight: _finiteDouble(
        data['coursework_weight'] ?? data['courseworkWeight'] ?? .7,
      ),
      finalExamWeight: _finiteDouble(
        data['final_exam_weight'] ?? data['finalExamWeight'] ?? .3,
      ),
      passMark: _finiteDouble(data['pass_mark'] ?? data['passMark'] ?? 60),
      gradeBoundaries: raw is Map
          ? raw.map((key, value) => MapEntry('$key', _finiteDouble(value)))
          : const {'A': 80, 'B': 70, 'C': 60, 'D': 50, 'F': 0},
      calculationVersion: firestoreInt(
        data['calculation_version'] ?? data['calculationVersion'],
        fallback: 1,
      ),
      showCorrectAnswers: data['show_correct_answers'] == null
          ? true
          : firestoreBool(data['show_correct_answers']),
    );
  }

  String gradeFor(double score) {
    final entries = gradeBoundaries.entries.toList()
      ..sort((left, right) => right.value.compareTo(left.value));
    return entries
        .firstWhere(
          (entry) => score >= entry.value,
          orElse: () => const MapEntry('F', 0),
        )
        .key;
  }
}

class OverallAcademicResult {
  const OverallAcademicResult({
    required this.courseworkRawScore,
    required this.courseworkWeightedScore,
    required this.finalExamRawScore,
    required this.finalExamWeightedScore,
    required this.overallScore,
    required this.grade,
    required this.passed,
    required this.calculationVersion,
  });

  final double courseworkRawScore;
  final double courseworkWeightedScore;
  final double finalExamRawScore;
  final double finalExamWeightedScore;
  final double overallScore;
  final String grade;
  final bool passed;
  final int calculationVersion;
}

abstract final class AssessmentCalculator {
  static OverallAcademicResult? calculate({
    required double? courseworkRawScore,
    required bool courseworkFinalised,
    required double? finalExamRawScore,
    required AssessmentSettings settings,
  }) {
    if (!courseworkFinalised ||
        courseworkRawScore == null ||
        finalExamRawScore == null) {
      return null;
    }
    if ((settings.totalWeight - 1).abs() > .000001) {
      throw const FormatException('Assessment weights must total 100%.');
    }
    final coursework = _round2(
      courseworkRawScore.clamp(0, 100) * settings.courseworkWeight,
    );
    final finalExam = _round2(
      finalExamRawScore.clamp(0, 100) * settings.finalExamWeight,
    );
    final overall = _round2(coursework + finalExam);
    return OverallAcademicResult(
      courseworkRawScore: _round2(courseworkRawScore.clamp(0, 100)),
      courseworkWeightedScore: coursework,
      finalExamRawScore: _round2(finalExamRawScore.clamp(0, 100)),
      finalExamWeightedScore: finalExam,
      overallScore: overall,
      grade: settings.gradeFor(overall),
      passed: overall >= settings.passMark,
      calculationVersion: settings.calculationVersion,
    );
  }
}

class OrderManagementCalculation {
  const OrderManagementCalculation({
    required this.stopLossDistance,
    required this.takeProfitDistance,
    required this.riskRewardRatio,
    required this.valid,
    this.error,
  });

  final double stopLossDistance;
  final double takeProfitDistance;
  final double riskRewardRatio;
  final bool valid;
  final String? error;
}

abstract final class OrderManagementValidator {
  static OrderManagementCalculation calculate({
    required String direction,
    required double entry,
    required double stopLoss,
    required double takeProfit,
  }) {
    if (![entry, stopLoss, takeProfit].every((value) => value.isFinite)) {
      return const OrderManagementCalculation(
        stopLossDistance: 0,
        takeProfitDistance: 0,
        riskRewardRatio: 0,
        valid: false,
        error: 'Enter valid finite prices.',
      );
    }
    final buy = direction.trim().toLowerCase() == 'buy';
    final sell = direction.trim().toLowerCase() == 'sell';
    if (!buy && !sell) {
      return const OrderManagementCalculation(
        stopLossDistance: 0,
        takeProfitDistance: 0,
        riskRewardRatio: 0,
        valid: false,
        error: 'Select buy or sell.',
      );
    }
    final risk = (entry - stopLoss).abs();
    final reward = (takeProfit - entry).abs();
    if (risk <= 0) {
      return const OrderManagementCalculation(
        stopLossDistance: 0,
        takeProfitDistance: 0,
        riskRewardRatio: 0,
        valid: false,
        error: 'Stop loss must be different from entry.',
      );
    }
    final logical = buy
        ? stopLoss < entry && takeProfit > entry
        : stopLoss > entry && takeProfit < entry;
    final ratio = reward / risk;
    return OrderManagementCalculation(
      stopLossDistance: risk,
      takeProfitDistance: reward,
      riskRewardRatio: ratio.isFinite ? ratio : 0,
      valid: logical && reward > 0 && ratio.isFinite,
      error: logical
          ? null
          : buy
          ? 'For a buy, stop loss must be below entry and take profit above it.'
          : 'For a sell, stop loss must be above entry and take profit below it.',
    );
  }
}

abstract final class CourseworkThemeValidator {
  static List<String> validate(
    CourseworkTheme theme, {
    bool forPublishing = false,
  }) {
    final errors = <String>[];
    if (theme.questions.length != 5) {
      errors.add('A Coursework theme must contain exactly five questions.');
    }
    final ordered = [...theme.questions]
      ..sort((left, right) => left.number.compareTo(right.number));
    if (ordered.map((item) => item.number).toSet().length != 5 ||
        !ordered.indexed.every((item) => item.$2.number == item.$1 + 1)) {
      errors.add('Question numbers must be unique and run from 1 to 5.');
    }
    if (ordered.where((item) => item.type.isTheory).length != 2) {
      errors.add('Questions 1 and 2 must be theory questions.');
    }
    if (ordered.length == 5 &&
        (!ordered[0].type.isTheory ||
            !ordered[1].type.isTheory ||
            ordered[2].type != CourseworkQuestionType.chartZoneSelection ||
            ordered[3].type != CourseworkQuestionType.marketAnalysis ||
            !const {
              CourseworkQuestionType.practicalDemonstration,
              CourseworkQuestionType.orderManagementDemonstration,
            }.contains(ordered[4].type))) {
      errors.add(
        'Question order must be theory, theory, chart, analysis, then order management.',
      );
    }
    if (ordered
            .where(
              (item) => item.type == CourseworkQuestionType.chartZoneSelection,
            )
            .length !=
        1) {
      errors.add('A theme must contain exactly one chart question.');
    }
    if (ordered
            .where((item) => item.type == CourseworkQuestionType.marketAnalysis)
            .length !=
        1) {
      errors.add('A theme must contain exactly one self-analysis question.');
    }
    if (ordered
            .where(
              (item) => const {
                CourseworkQuestionType.practicalDemonstration,
                CourseworkQuestionType.orderManagementDemonstration,
              }.contains(item.type),
            )
            .length !=
        1) {
      errors.add('A theme must contain exactly one practical question.');
    }
    final marks = theme.questions.fold<double>(
      0,
      (total, item) => total + item.marks,
    );
    if ((marks - 100).abs() > .000001 ||
        (theme.totalMarks - 100).abs() > .000001) {
      errors.add('Coursework marks must total exactly 100.');
    }
    if (ordered.length == 5) {
      const requiredMarks = [15.0, 15.0, 20.0, 25.0, 25.0];
      for (var index = 0; index < requiredMarks.length; index++) {
        if ((ordered[index].marks - requiredMarks[index]).abs() > .000001) {
          errors.add('Question marks must be 15, 15, 20, 25, and 25.');
          break;
        }
      }
    }
    if (forPublishing) {
      if (theme.code.trim().isEmpty ||
          theme.title.trim().isEmpty ||
          theme.learningObjectives.isEmpty) {
        errors.add(
          'Published themes require a code, title, and learning objective.',
        );
      }
      if (!theme.languageAvailability.contains('en') ||
          !theme.languageAvailability.contains('zh')) {
        errors.add('Published themes require English and Chinese content.');
      }
      for (final question in theme.questions) {
        if (question.id.isEmpty ||
            question.questionText.isEmpty ||
            question.titleI18n['zh']?.trim().isEmpty != false) {
          errors.add(
            'Every published question needs an ID and both translations.',
          );
          break;
        }
        if (question.type == CourseworkQuestionType.chartZoneSelection &&
            question.chartAsset.isEmpty &&
            question.chartCandles.length < 8) {
          errors.add(
            'The chart question needs a real chart asset or OHLC snapshot.',
          );
        }
        if (question.type.isTheory && question.answerOptions.length < 2) {
          errors.add(
            'Published objective questions need at least two public options.',
          );
        }
      }
    }
    return errors;
  }
}

double _finiteDouble(Object? value) {
  final parsed = value is num ? value.toDouble() : double.tryParse('$value');
  return parsed?.isFinite == true ? parsed! : 0;
}

double? _nullableFiniteDouble(Object? value) {
  if (value == null) return null;
  final parsed = value is num ? value.toDouble() : double.tryParse('$value');
  return parsed?.isFinite == true ? parsed : null;
}

double _round2(num value) => (value * 100).round() / 100;

Map<String, String> _localizedMap(Object? value) {
  if (value is! Map) return const {};
  return value.map((key, item) => MapEntry('$key', '$item'));
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const <String, dynamic>{};

Object? _localizedList(Object? value, String languageCode) {
  if (value is Map) return value[languageCode];
  return languageCode == 'en' ? value : null;
}

Map<String, Object?> _objectMap(Object? value) {
  if (value is! Map) return const {};
  return value.map((key, item) => MapEntry('$key', item));
}

List<Map<String, dynamic>> _mapList(Object? value) {
  if (value is! Iterable) return const [];
  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList(growable: false);
}

List<String> _stringList(Object? value) => value is Iterable
    ? value.map((item) => '$item').toList(growable: false)
    : const [];

String _firestoreDocumentId(Object? value) {
  if (value is DocumentReference) return value.id;
  final raw = value?.toString().trim() ?? '';
  if (raw.isEmpty) return '';
  if (raw.contains('/')) {
    return raw.split('/').where((item) => item.isNotEmpty).last;
  }
  return raw;
}

String _canonicalToken(Object? value) => (value?.toString() ?? '')
    .trim()
    .toLowerCase()
    .replaceAll(RegExp(r'[_–—]+'), ' ')
    .replaceAll(RegExp(r'\s+'), ' ');

Set<String> _searchForms(String value) {
  final normalized = value
      .toLowerCase()
      .trim()
      .replaceAll(RegExp(r'[_–—]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ');
  if (normalized.isEmpty) return const {};
  return {
    normalized,
    normalized.replaceAll(RegExp(r'[\s-]+'), ''),
    ...normalized.split(' ').where((item) => item.isNotEmpty),
  };
}
