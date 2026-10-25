import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';

enum TradingQuizQuestionType {
  multipleChoice('multiple_choice'),
  chartMultipleChoice('chart_multiple_choice'),
  chartScreenshotAnalysis('chart_screenshot_analysis');

  const TradingQuizQuestionType(this.storageValue);
  final String storageValue;

  bool get requiresChoice => this != chartScreenshotAnalysis;

  static TradingQuizQuestionType fromStorage(Object? value) {
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    return TradingQuizQuestionType.values.firstWhere(
      (type) => type.storageValue == normalized,
      orElse: () => TradingQuizQuestionType.multipleChoice,
    );
  }
}

class TradingQuizCandle {
  const TradingQuizCandle({
    required this.open,
    required this.high,
    required this.low,
    required this.close,
    this.label = '',
  });

  final double open;
  final double high;
  final double low;
  final double close;
  final String label;

  factory TradingQuizCandle.fromMap(Map<String, dynamic> data) =>
      TradingQuizCandle(
        open: _double(data['open']),
        high: _double(data['high']),
        low: _double(data['low']),
        close: _double(data['close']),
        label: (data['label'] ?? '').toString(),
      );

  Map<String, Object?> toMap() => {
    'open': open,
    'high': high,
    'low': low,
    'close': close,
    if (label.isNotEmpty) 'label': label,
  };
}

class TradingExamOptionMobile {
  const TradingExamOptionMobile({
    required this.id,
    required this.optionText,
    this.isCorrect = false,
    this.position = 1,
    this.optionTextI18n = const {},
  });

  final String id;
  final String optionText;
  final bool isCorrect;
  final int position;
  final Map<String, String> optionTextI18n;

  String localizedText(String languageCode) =>
      localizedValue(optionTextI18n, languageCode, fallback: optionText);

  factory TradingExamOptionMobile.fromMap(
    Map<String, dynamic> data,
    int index,
  ) {
    return TradingExamOptionMobile(
      id: (data['id'] ?? index).toString(),
      optionText: localizedValue(data['option_text'] ?? data['text'], 'en'),
      isCorrect: firestoreBool(data['is_correct']),
      position: firestoreInt(data['position'], fallback: index + 1),
      optionTextI18n: _localizedField(data, 'option_text', aliasKey: 'text'),
    );
  }

  Map<String, Object?> toMap() {
    return {
      'id': id,
      'option_text': optionText,
      if (optionTextI18n.isNotEmpty) 'option_text_i18n': optionTextI18n,
      'is_correct': isCorrect,
      'position': position,
    };
  }
}

class TradingExamQuestionMobile {
  const TradingExamQuestionMobile({
    required this.id,
    required this.questionText,
    required this.options,
    this.category = '',
    this.difficulty = 'foundation',
    this.explanation = '',
    this.status = 'approved',
    this.questionTextI18n = const {},
    this.categoryI18n = const {},
    this.explanationI18n = const {},
    this.questionType = TradingQuizQuestionType.multipleChoice,
    this.chartImageUrl = '',
    this.chartThumbnailUrl = '',
    this.chartDescription = '',
    this.chartDescriptionI18n = const {},
    this.chartCandles = const [],
    this.requiresScreenshot = false,
    this.requiresWrittenExplanation = false,
    this.minimumExplanationCharacters = 40,
    this.maximumExplanationCharacters = 2000,
    this.version = 1,
    this.maximumAttempts = 1,
    this.publishedAt,
    this.deadline,
  });

  final String id;
  final String questionText;
  final List<TradingExamOptionMobile> options;
  final String category;
  final String difficulty;
  final String explanation;
  final String status;
  final Map<String, String> questionTextI18n;
  final Map<String, String> categoryI18n;
  final Map<String, String> explanationI18n;
  final TradingQuizQuestionType questionType;
  final String chartImageUrl;
  final String chartThumbnailUrl;
  final String chartDescription;
  final Map<String, String> chartDescriptionI18n;
  final List<TradingQuizCandle> chartCandles;
  final bool requiresScreenshot;
  final bool requiresWrittenExplanation;
  final int minimumExplanationCharacters;
  final int maximumExplanationCharacters;
  final int version;
  final int maximumAttempts;
  final DateTime? publishedAt;
  final DateTime? deadline;

  String localizedQuestionText(String languageCode) =>
      localizedValue(questionTextI18n, languageCode, fallback: questionText);
  String localizedCategory(String languageCode) =>
      localizedValue(categoryI18n, languageCode, fallback: category);
  String localizedExplanation(String languageCode) =>
      localizedValue(explanationI18n, languageCode, fallback: explanation);
  String localizedChartDescription(String languageCode) => localizedValue(
    chartDescriptionI18n,
    languageCode,
    fallback: chartDescription,
  );

  bool get isScreenshotAnalysis =>
      questionType == TradingQuizQuestionType.chartScreenshotAnalysis;
  bool get needsScreenshot => requiresScreenshot || isScreenshotAnalysis;
  bool get needsWrittenExplanation =>
      requiresWrittenExplanation || isScreenshotAnalysis;
  bool get hasPublishedChart =>
      chartImageUrl.trim().isNotEmpty || chartCandles.length >= 4;

  int get correctOptionIndex {
    final index = options.indexWhere((option) => option.isCorrect);
    return index < 0 ? 0 : index;
  }

  String get difficultyLabel {
    return switch (difficulty) {
      'risk' => 'Risk Management',
      'technical' => 'Technical Analysis',
      'psychology' => 'Trading Psychology',
      'platform' => 'Platform / Process',
      _ => 'Foundation',
    };
  }

  factory TradingExamQuestionMobile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    final options = _examOptions(data['options']);

    return TradingExamQuestionMobile(
      id: doc.id,
      questionText: localizedValue(data['question_text'], 'en'),
      options: options,
      category: localizedValue(data['category'], 'en'),
      difficulty: (data['difficulty'] ?? 'foundation').toString(),
      explanation: localizedValue(data['explanation'], 'en'),
      status: (data['status'] ?? 'approved').toString(),
      questionTextI18n: _localizedField(data, 'question_text'),
      categoryI18n: _localizedField(data, 'category'),
      explanationI18n: _localizedField(data, 'explanation'),
      questionType: TradingQuizQuestionType.fromStorage(
        data['question_type'] ?? data['type'],
      ),
      chartImageUrl: (data['chart_image_url'] ?? data['chart_url'] ?? '')
          .toString(),
      chartThumbnailUrl: (data['chart_thumbnail_url'] ?? '').toString(),
      chartDescription: localizedValue(data['chart_description'], 'en'),
      chartDescriptionI18n: _localizedField(data, 'chart_description'),
      chartCandles: _candles(data['chart_candles'] ?? data['ohlc']),
      requiresScreenshot: firestoreBool(data['requires_screenshot']),
      requiresWrittenExplanation: firestoreBool(
        data['requires_written_explanation'],
      ),
      minimumExplanationCharacters: firestoreInt(
        data['minimum_explanation_characters'],
        fallback: 40,
      ),
      maximumExplanationCharacters: firestoreInt(
        data['maximum_explanation_characters'],
        fallback: 2000,
      ),
      version: firestoreInt(data['version'], fallback: 1),
      maximumAttempts: firestoreInt(data['maximum_attempts'], fallback: 1),
      publishedAt: firestoreDate(data['published_at']),
      deadline: firestoreDate(data['deadline']),
    );
  }

  factory TradingExamQuestionMobile.fromJson(Map<String, dynamic> data) {
    final options = _examOptions(data['options']);
    return TradingExamQuestionMobile(
      id: (data['id'] ?? '').toString(),
      questionText: localizedValue(data['question_text'], 'en'),
      options: options,
      category: localizedValue(data['category'], 'en'),
      difficulty: (data['difficulty'] ?? 'foundation').toString(),
      explanation: localizedValue(data['explanation'], 'en'),
      status: (data['status'] ?? 'approved').toString(),
      questionTextI18n: _localizedField(data, 'question_text'),
      categoryI18n: _localizedField(data, 'category'),
      explanationI18n: _localizedField(data, 'explanation'),
      questionType: TradingQuizQuestionType.fromStorage(
        data['question_type'] ?? data['type'],
      ),
      chartImageUrl: (data['chart_image_url'] ?? data['chart_url'] ?? '')
          .toString(),
      chartThumbnailUrl: (data['chart_thumbnail_url'] ?? '').toString(),
      chartDescription: localizedValue(data['chart_description'], 'en'),
      chartDescriptionI18n: _localizedField(data, 'chart_description'),
      chartCandles: _candles(data['chart_candles'] ?? data['ohlc']),
      requiresScreenshot: firestoreBool(data['requires_screenshot']),
      requiresWrittenExplanation: firestoreBool(
        data['requires_written_explanation'],
      ),
      minimumExplanationCharacters: firestoreInt(
        data['minimum_explanation_characters'],
        fallback: 40,
      ),
      maximumExplanationCharacters: firestoreInt(
        data['maximum_explanation_characters'],
        fallback: 2000,
      ),
      version: firestoreInt(data['version'], fallback: 1),
      maximumAttempts: firestoreInt(data['maximum_attempts'], fallback: 1),
      publishedAt: firestoreDate(data['published_at']),
      deadline: firestoreDate(data['deadline']),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'category': category,
      if (categoryI18n.isNotEmpty) 'category_i18n': categoryI18n,
      'difficulty': difficulty,
      'question_text': questionText,
      if (questionTextI18n.isNotEmpty) 'question_text_i18n': questionTextI18n,
      'explanation': explanation,
      if (explanationI18n.isNotEmpty) 'explanation_i18n': explanationI18n,
      'status': status,
      'question_type': questionType.storageValue,
      'chart_image_url': chartImageUrl,
      'chart_thumbnail_url': chartThumbnailUrl,
      'chart_description': chartDescription,
      if (chartDescriptionI18n.isNotEmpty)
        'chart_description_i18n': chartDescriptionI18n,
      if (chartCandles.isNotEmpty)
        'chart_candles': chartCandles.map((item) => item.toMap()).toList(),
      'requires_screenshot': needsScreenshot,
      'requires_written_explanation': needsWrittenExplanation,
      'minimum_explanation_characters': minimumExplanationCharacters,
      'maximum_explanation_characters': maximumExplanationCharacters,
      'version': version,
      'maximum_attempts': maximumAttempts,
      if (publishedAt != null) 'published_at': Timestamp.fromDate(publishedAt!),
      if (deadline != null) 'deadline': Timestamp.fromDate(deadline!),
      'options': options.map((option) => option.toMap()).toList(),
      'updated_at': FieldValue.serverTimestamp(),
    };
  }
}

class TradingExamAttemptMobile {
  const TradingExamAttemptMobile({
    required this.id,
    required this.userId,
    required this.examDate,
    this.status = 'completed',
    this.totalQuestions = 0,
    this.score = 0,
    this.answers = const {},
    this.questionResults = const [],
    this.passingPercentage,
    this.completedAt,
    this.submittedAt,
    this.reviewedAt,
    this.reviewedBy = '',
    this.reviewNote = '',
    this.reviewScore,
    this.quizVersion = 1,
  });

  final String id;
  final String userId;
  final DateTime examDate;
  final String status;
  final int totalQuestions;
  final int score;
  final Map<String, int> answers;
  final List<TradingExamQuestionResultMobile> questionResults;
  final int? passingPercentage;
  final DateTime? completedAt;
  final DateTime? submittedAt;
  final DateTime? reviewedAt;
  final String reviewedBy;
  final String reviewNote;
  final double? reviewScore;
  final int quizVersion;

  int get percentage {
    if (totalQuestions <= 0) {
      return 0;
    }

    return ((score / totalQuestions) * 100).round();
  }

  int get wrongAnswers => totalQuestions - score;
  bool? get passed =>
      passingPercentage == null ? null : percentage >= passingPercentage!;

  factory TradingExamAttemptMobile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    final rawAnswers = data['answers'];

    return TradingExamAttemptMobile(
      id: doc.id,
      userId: (data['user_id'] ?? '').toString(),
      examDate: firestoreDate(data['exam_date']) ?? DateTime.now(),
      status: (data['status'] ?? 'completed').toString(),
      totalQuestions: firestoreInt(data['total_questions']),
      score: firestoreInt(data['score']),
      answers: rawAnswers is Map
          ? rawAnswers.map(
              (key, value) => MapEntry(key.toString(), firestoreInt(value)),
            )
          : const {},
      questionResults: _questionResults(data['question_results']),
      passingPercentage: data['passing_percentage'] == null
          ? null
          : firestoreInt(data['passing_percentage']),
      completedAt: firestoreDate(data['completed_at']),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
      reviewedBy: (data['reviewed_by'] ?? '').toString(),
      reviewNote: (data['review_note'] ?? '').toString(),
      reviewScore: data['review_score'] == null
          ? null
          : _double(data['review_score']),
      quizVersion: firestoreInt(data['quiz_version'], fallback: 1),
    );
  }

  factory TradingExamAttemptMobile.fromJson(Map<String, dynamic> data) {
    return TradingExamAttemptMobile(
      id: (data['id'] ?? '').toString(),
      userId: (data['user_id'] ?? '').toString(),
      examDate: firestoreDate(data['exam_date']) ?? DateTime.now(),
      status: (data['status'] ?? 'completed').toString(),
      totalQuestions: firestoreInt(data['total_questions']),
      score: firestoreInt(data['score']),
      answers: data['answers'] is Map
          ? (data['answers'] as Map).map(
              (key, value) => MapEntry(key.toString(), firestoreInt(value)),
            )
          : const {},
      questionResults: _questionResults(data['question_results']),
      passingPercentage: data['passing_percentage'] == null
          ? null
          : firestoreInt(data['passing_percentage']),
      completedAt: firestoreDate(data['completed_at']),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
      reviewedBy: (data['reviewed_by'] ?? '').toString(),
      reviewNote: (data['review_note'] ?? '').toString(),
      reviewScore: data['review_score'] == null
          ? null
          : _double(data['review_score']),
      quizVersion: firestoreInt(data['quiz_version'], fallback: 1),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'user_id': userId,
      'exam_date': Timestamp.fromDate(examDate),
      'status': status,
      'total_questions': totalQuestions,
      'score': score,
      'answers': answers,
      'question_results': questionResults.map((item) => item.toMap()).toList(),
      if (passingPercentage != null) 'passing_percentage': passingPercentage,
      if (passed != null) 'passed': passed,
      'submitted_at': Timestamp.fromDate(submittedAt ?? DateTime.now()),
      if (reviewedAt != null) 'reviewed_at': Timestamp.fromDate(reviewedAt!),
      if (reviewedBy.isNotEmpty) 'reviewed_by': reviewedBy,
      if (reviewNote.isNotEmpty) 'review_note': reviewNote,
      if (reviewScore != null) 'review_score': reviewScore,
      'quiz_version': quizVersion,
      'completed_at': Timestamp.fromDate(completedAt ?? DateTime.now()),
      'updated_at': FieldValue.serverTimestamp(),
    };
  }

  static List<TradingExamQuestionResultMobile> _questionResults(Object? raw) {
    if (raw is! Iterable) return const [];
    return raw
        .whereType<Map>()
        .map(
          (item) => TradingExamQuestionResultMobile.fromMap(
            Map<String, dynamic>.from(item),
          ),
        )
        .toList(growable: false);
  }
}

class TradingExamQuestionResultMobile {
  const TradingExamQuestionResultMobile({
    required this.questionId,
    required this.questionText,
    required this.options,
    required this.selectedIndex,
    required this.correctIndex,
    this.explanation = '',
    this.difficulty = 'foundation',
    this.questionTextI18n = const {},
    this.optionsI18n = const [],
    this.explanationI18n = const {},
    this.questionType = TradingQuizQuestionType.multipleChoice,
    this.questionVersion = 1,
    this.writtenExplanation = '',
    this.screenshotUrl = '',
    this.screenshotStoragePath = '',
    this.reviewStatus = 'not_required',
    this.reviewScore,
    this.reviewFeedback = '',
  });

  final String questionId;
  final String questionText;
  final List<String> options;
  final int selectedIndex;
  final int correctIndex;
  final String explanation;
  final String difficulty;
  final Map<String, String> questionTextI18n;
  final List<Map<String, String>> optionsI18n;
  final Map<String, String> explanationI18n;
  final TradingQuizQuestionType questionType;
  final int questionVersion;
  final String writtenExplanation;
  final String screenshotUrl;
  final String screenshotStoragePath;
  final String reviewStatus;
  final double? reviewScore;
  final String reviewFeedback;

  bool get isCorrect => selectedIndex == correctIndex;
  String? get selectedAnswer =>
      selectedIndex >= 0 && selectedIndex < options.length
      ? options[selectedIndex]
      : null;
  String? get correctAnswer =>
      correctIndex >= 0 && correctIndex < options.length
      ? options[correctIndex]
      : null;
  String localizedQuestionText(String languageCode) =>
      localizedValue(questionTextI18n, languageCode, fallback: questionText);
  List<String> localizedOptions(String languageCode) {
    return [
      for (var index = 0; index < options.length; index++)
        localizedValue(
          index < optionsI18n.length ? optionsI18n[index] : null,
          languageCode,
          fallback: options[index],
        ),
    ];
  }

  String localizedExplanation(String languageCode) =>
      localizedValue(explanationI18n, languageCode, fallback: explanation);

  factory TradingExamQuestionResultMobile.fromQuestion(
    TradingExamQuestionMobile question,
    int selectedIndex,
  ) {
    return TradingExamQuestionResultMobile(
      questionId: question.id,
      questionText: question.questionText,
      options: question.options.map((option) => option.optionText).toList(),
      selectedIndex: selectedIndex,
      correctIndex: question.correctOptionIndex,
      explanation: question.explanation,
      difficulty: question.difficulty,
      questionTextI18n: question.questionTextI18n,
      optionsI18n: question.options
          .map((option) => option.optionTextI18n)
          .toList(growable: false),
      explanationI18n: question.explanationI18n,
      questionType: question.questionType,
      questionVersion: question.version,
    );
  }

  factory TradingExamQuestionResultMobile.fromMap(Map<String, dynamic> data) {
    final rawOptions = data['options'];
    return TradingExamQuestionResultMobile(
      questionId: (data['question_id'] ?? '').toString(),
      questionText: localizedValue(data['question_text'], 'en'),
      options: rawOptions is Iterable
          ? rawOptions
                .map((item) => localizedValue(item, 'en'))
                .toList(growable: false)
          : const [],
      selectedIndex: firestoreInt(data['selected_index'], fallback: -1),
      correctIndex: firestoreInt(data['correct_index'], fallback: -1),
      explanation: localizedValue(data['explanation'], 'en'),
      difficulty: (data['difficulty'] ?? 'foundation').toString(),
      questionTextI18n: _localizedField(data, 'question_text'),
      optionsI18n: _localizedOptions(data['options_i18n'] ?? data['options']),
      explanationI18n: _localizedField(data, 'explanation'),
      questionType: TradingQuizQuestionType.fromStorage(data['question_type']),
      questionVersion: firestoreInt(data['question_version'], fallback: 1),
      writtenExplanation: (data['written_explanation'] ?? '').toString(),
      screenshotUrl: (data['screenshot_url'] ?? '').toString(),
      screenshotStoragePath: (data['screenshot_storage_path'] ?? '').toString(),
      reviewStatus: (data['review_status'] ?? 'not_required').toString(),
      reviewScore: data['review_score'] == null
          ? null
          : _double(data['review_score']),
      reviewFeedback: (data['review_feedback'] ?? '').toString(),
    );
  }

  Map<String, Object?> toMap() => {
    'question_id': questionId,
    'question_text': questionText,
    if (questionTextI18n.isNotEmpty) 'question_text_i18n': questionTextI18n,
    'options': options,
    if (optionsI18n.isNotEmpty) 'options_i18n': optionsI18n,
    'selected_index': selectedIndex,
    'correct_index': correctIndex,
    'is_correct': isCorrect,
    'explanation': explanation,
    if (explanationI18n.isNotEmpty) 'explanation_i18n': explanationI18n,
    'difficulty': difficulty,
    'question_type': questionType.storageValue,
    'question_version': questionVersion,
    if (writtenExplanation.isNotEmpty)
      'written_explanation': writtenExplanation,
    if (screenshotUrl.isNotEmpty) 'screenshot_url': screenshotUrl,
    if (screenshotStoragePath.isNotEmpty)
      'screenshot_storage_path': screenshotStoragePath,
    'review_status': reviewStatus,
    if (reviewScore != null) 'review_score': reviewScore,
    if (reviewFeedback.isNotEmpty) 'review_feedback': reviewFeedback,
  };
}

class TradingQuizResponseDraft {
  const TradingQuizResponseDraft({
    required this.questionId,
    required this.questionVersion,
    this.selectedIndex,
    this.writtenExplanation = '',
    this.screenshotUrl = '',
    this.screenshotStoragePath = '',
  });

  final String questionId;
  final int questionVersion;
  final int? selectedIndex;
  final String writtenExplanation;
  final String screenshotUrl;
  final String screenshotStoragePath;

  Map<String, Object?> toJson() => {
    'questionId': questionId,
    'questionVersion': questionVersion,
    if (selectedIndex != null) 'selectedIndex': selectedIndex,
    if (writtenExplanation.isNotEmpty) 'writtenExplanation': writtenExplanation,
    if (screenshotUrl.isNotEmpty) 'screenshotUrl': screenshotUrl,
    if (screenshotStoragePath.isNotEmpty)
      'screenshotStoragePath': screenshotStoragePath,
  };
}

Map<String, String> _localizedField(
  Map<String, dynamic> data,
  String key, {
  String? aliasKey,
}) {
  final map = {
    ...localizedTextMap(data['${key}_i18n']),
    ...localizedTextMap(data[key]),
  };
  if (aliasKey != null) {
    map.addAll(localizedTextMap(data['${aliasKey}_i18n']));
    map.addAll(localizedTextMap(data[aliasKey]));
  }
  for (final candidate in [key, ?aliasKey]) {
    final english = data['${candidate}_en']?.toString().trim() ?? '';
    final chinese = data['${candidate}_zh']?.toString().trim() ?? '';
    if (english.isNotEmpty) map['en'] = english;
    if (chinese.isNotEmpty) map['zh'] = chinese;
  }
  return map;
}

List<Map<String, String>> _localizedOptions(Object? raw) {
  if (raw is! Iterable) return const [];
  return raw.map((item) => localizedTextMap(item)).toList(growable: false);
}

List<TradingExamOptionMobile> _examOptions(Object? rawOptions) {
  if (rawOptions is Map) {
    final entries = rawOptions.entries.toList()
      ..sort(
        (left, right) => left.key.toString().compareTo(right.key.toString()),
      );
    return [
      for (final entry in entries.indexed)
        _examOptionFromRaw(
          entry.$2.value,
          entry.$1,
          fallbackId: entry.$2.key.toString(),
        ),
    ];
  }

  if (rawOptions is Iterable) {
    return [
      for (final entry in rawOptions.indexed)
        _examOptionFromRaw(entry.$2, entry.$1),
    ];
  }

  return const [];
}

TradingExamOptionMobile _examOptionFromRaw(
  Object? raw,
  int index, {
  String? fallbackId,
}) {
  if (raw is Map) {
    final data = Map<String, dynamic>.from(raw);
    data['id'] ??= fallbackId ?? index;
    data['position'] ??= index + 1;
    return TradingExamOptionMobile.fromMap(data, index);
  }

  return TradingExamOptionMobile(
    id: fallbackId ?? index.toString(),
    optionText: localizedValue(raw, 'en'),
    optionTextI18n: localizedTextMap(raw),
    position: index + 1,
  );
}

List<TradingQuizCandle> _candles(Object? raw) {
  if (raw is! Iterable) return const [];
  return raw
      .whereType<Map>()
      .map((item) => TradingQuizCandle.fromMap(Map<String, dynamic>.from(item)))
      .where((item) => item.high >= item.low)
      .toList(growable: false);
}

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
