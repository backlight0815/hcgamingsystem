import '../core/localization/localized_value.dart';
import 'final_exam_translation_fallback.dart';

class FinalExamQuestionMobile {
  const FinalExamQuestionMobile({
    required this.attemptQuestionId,
    required this.position,
    required this.category,
    required this.questionType,
    required this.question,
    required this.options,
    this.answerKey = '',
    this.explanation = '',
    this.subcategory = '',
    this.scenarioText = '',
    this.chartImageUrl,
    this.chartData,
    this.chartTimeframe = '',
    this.difficulty = '',
    this.categoryI18n = const {},
    this.subcategoryI18n = const {},
    this.questionI18n = const {},
    this.scenarioTextI18n = const {},
    this.explanationI18n = const {},
    this.optionsI18n = const {},
  });

  final String attemptQuestionId;
  final int position;
  final String category;
  final String subcategory;
  final String questionType;
  final String question;
  final String answerKey;
  final String explanation;
  final String scenarioText;
  final String? chartImageUrl;
  final Map<String, dynamic>? chartData;
  final String chartTimeframe;
  final String difficulty;
  final Map<String, String> options;
  final Map<String, String> categoryI18n;
  final Map<String, String> subcategoryI18n;
  final Map<String, String> questionI18n;
  final Map<String, String> scenarioTextI18n;
  final Map<String, String> explanationI18n;
  final Map<String, Map<String, String>> optionsI18n;

  String localizedCategory(String languageCode) =>
      localizedValue(categoryI18n, languageCode, fallback: category);
  String localizedSubcategory(String languageCode) =>
      localizedValue(subcategoryI18n, languageCode, fallback: subcategory);
  String localizedQuestion(String languageCode) =>
      localizedValue(questionI18n, languageCode, fallback: question);
  String localizedScenarioText(String languageCode) =>
      localizedValue(scenarioTextI18n, languageCode, fallback: scenarioText);
  String localizedExplanation(String languageCode) =>
      localizedValue(explanationI18n, languageCode, fallback: explanation);
  String localizedOption(String key, String languageCode) => localizedValue(
    _withKnownFinalExamZh(optionsI18n[key], options[key]),
    languageCode,
    fallback: options[key],
  );

  factory FinalExamQuestionMobile.fromJson(Map<String, dynamic> json) {
    final examTextEn = getLocalizedExamText(json, 'en');
    final rawOptions = json['options'] ?? examTextEn.options;
    final options = <String, String>{};
    if (rawOptions is Map) {
      for (final entry in rawOptions.entries) {
        final value = localizedValue(entry.value, 'en').trim();
        if (value.isNotEmpty) options[entry.key.toString()] = value;
      }
    } else if (rawOptions is List) {
      for (var index = 0; index < rawOptions.length; index++) {
        final value = localizedValue(rawOptions[index], 'en').trim();
        if (value.isNotEmpty) {
          options[String.fromCharCode(65 + index)] = value;
        }
      }
    }
    final optionsI18n = _optionTranslations(
      json['options_i18n'] ?? json['options'] ?? _localeOptionsI18n(json),
    );
    final category = localizedValue(
      json['categoryLabel'] ?? json['category_label'] ?? json['category'],
      'en',
    );
    final subcategory = localizedValue(
      json['subcategoryLabel'] ??
          json['subcategory_label'] ??
          json['subcategory'],
      'en',
    );
    final question = localizedValue(
      json['question'],
      'en',
      fallback: examTextEn.question,
    );
    final scenarioText = localizedValue(json['scenario_text'], 'en');
    final explanation = localizedValue(
      json['explanation'],
      'en',
      fallback: examTextEn.explanation,
    );
    return FinalExamQuestionMobile(
      attemptQuestionId: (json['attempt_question_id'] ?? '').toString(),
      position: _int(json['position']),
      category: category,
      subcategory: subcategory,
      questionType: (json['question_type'] ?? json['questionType'] ?? '')
          .toString(),
      question: question,
      answerKey: _answerKey(json),
      explanation: explanation,
      scenarioText: scenarioText,
      chartImageUrl: json['chart_image_url']?.toString(),
      chartData: json['chart_data'] is Map
          ? Map<String, dynamic>.from(json['chart_data'] as Map)
          : null,
      chartTimeframe: (json['chart_timeframe'] ?? '').toString(),
      difficulty: (json['difficulty'] ?? '').toString(),
      options: options,
      categoryI18n: _withKnownFinalExamZh(
        _localizedField(json, 'category', aliasKey: 'categoryLabel'),
        category,
      ),
      subcategoryI18n: _withKnownFinalExamZh(
        _localizedField(json, 'subcategory', aliasKey: 'subcategoryLabel'),
        subcategory,
      ),
      questionI18n: _withKnownFinalExamZh({
        ..._localizedField(json, 'question', aliasKey: 'question_text'),
        ..._localeFieldI18n(json, 'question'),
      }, question),
      scenarioTextI18n: _withKnownFinalExamZh(
        _localizedField(json, 'scenario_text'),
        scenarioText,
      ),
      explanationI18n: _withKnownFinalExamZh({
        ..._localizedField(json, 'explanation'),
        ..._localeFieldI18n(json, 'explanation'),
      }, explanation),
      optionsI18n: {
        for (final entry in options.entries)
          entry.key: _withKnownFinalExamZh({
            ...?optionsI18n[entry.key],
            ..._localeOptionI18n(json, entry.key),
          }, entry.value),
      },
    );
  }
}

class FinalExamAttemptMobile {
  const FinalExamAttemptMobile({
    required this.id,
    required this.status,
    required this.totalQuestions,
    required this.correctAnswers,
    required this.wrongAnswers,
    required this.scorePercentage,
    required this.questions,
  });

  final String id;
  final String status;
  final int totalQuestions;
  final int correctAnswers;
  final int wrongAnswers;
  final double scorePercentage;
  final List<FinalExamQuestionMobile> questions;

  factory FinalExamAttemptMobile.fromJson(Map<String, dynamic> json) =>
      FinalExamAttemptMobile(
        id: (json['id'] ?? '').toString(),
        status: (json['status'] ?? '').toString(),
        totalQuestions: _int(json['total_questions']),
        correctAnswers: _int(json['correct_answers']),
        wrongAnswers: _int(json['wrong_answers']),
        scorePercentage: _double(json['score_percentage']),
        questions: _maps(
          json['questions'],
        ).map(FinalExamQuestionMobile.fromJson).toList(),
      );
}

class FinalExamStatusMobile {
  const FinalExamStatusMobile({
    required this.required,
    required this.passed,
    required this.unlocked,
    required this.allowed,
    required this.passingPercentage,
    required this.message,
    required this.currentAttempt,
    required this.recentAttempts,
    required this.propFirmModels,
  });

  final bool required;
  final bool passed;
  final bool unlocked;
  final bool allowed;
  final int passingPercentage;
  final String message;
  final FinalExamAttemptMobile? currentAttempt;
  final List<FinalExamAttemptMobile> recentAttempts;
  final List<Map<String, dynamic>> propFirmModels;

  factory FinalExamStatusMobile.fromJson(Map<String, dynamic> json) {
    final availability = json['availability'] is Map
        ? Map<String, dynamic>.from(json['availability'] as Map)
        : const <String, dynamic>{};
    final settings = json['settings'] is Map
        ? Map<String, dynamic>.from(json['settings'] as Map)
        : const <String, dynamic>{};
    final current = json['current_attempt'];
    return FinalExamStatusMobile(
      required: json['required'] == true,
      passed: json['passed'] == true,
      unlocked: json['unlocked'] == true,
      allowed: availability['allowed'] == true,
      passingPercentage: _int(settings['passing_percentage']),
      message: (availability['message'] ?? '').toString(),
      currentAttempt: current is Map
          ? FinalExamAttemptMobile.fromJson(Map<String, dynamic>.from(current))
          : null,
      recentAttempts: _maps(
        json['recent_attempts'],
      ).map(FinalExamAttemptMobile.fromJson).toList(),
      propFirmModels: _maps(json['prop_firm_models']),
    );
  }
}

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : const [];

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

String _answerKey(Map<String, dynamic> json) {
  final value =
      json['answer_key'] ??
      json['correct_answer'] ??
      json['correct_option'] ??
      json['correct_option_key'];
  final direct = value?.toString().trim().toUpperCase() ?? '';
  if (direct.isNotEmpty) return direct;

  final rawIndex = json['correctOptionIndex'] ?? json['correct_option_index'];
  final optionIndex = rawIndex is num
      ? rawIndex.toInt()
      : int.tryParse(rawIndex?.toString() ?? '');
  if (optionIndex != null && optionIndex >= 0 && optionIndex <= 3) {
    return String.fromCharCode(65 + optionIndex);
  }
  return '';
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

Map<String, Map<String, String>> _optionTranslations(Object? raw) {
  if (raw is List) {
    return {
      for (var index = 0; index < raw.length; index++)
        String.fromCharCode(65 + index): localizedTextMap(raw[index]),
    };
  }
  if (raw is! Map) return const {};
  final map = <String, Map<String, String>>{};
  for (final entry in raw.entries) {
    final value = _localizedOption(entry.value);
    if (value.isNotEmpty) {
      map[entry.key.toString()] = value;
    }
  }
  return map;
}

Map<String, String> _localizedOption(Object? value) {
  if (value is Map) {
    final data = Map<String, dynamic>.from(value);
    return {
      ...localizedTextMap(data),
      ...localizedTextMap(data['text_i18n']),
      ...localizedTextMap(data['option_text_i18n']),
      ...localizedTextMap(data['text']),
      ...localizedTextMap(data['option_text']),
    };
  }
  return localizedTextMap(value);
}

Map<String, String> _localeFieldI18n(Map<String, dynamic> data, String field) {
  final locales = data['locales'];
  if (locales is! Map) return const {};
  final map = <String, String>{};
  for (final language in const ['en', 'zh']) {
    final locale = locales[language];
    if (locale is Map) {
      final text = locale[field]?.toString().trim() ?? '';
      if (text.isNotEmpty) map[language] = text;
    }
  }
  return map;
}

Map<String, Map<String, String>> _localeOptionsI18n(Map<String, dynamic> data) {
  final locales = data['locales'];
  if (locales is! Map) return const {};
  final enOptions = _localeOptionList(locales['en']);
  final zhOptions = _localeOptionList(locales['zh']);
  final length = enOptions.length > zhOptions.length
      ? enOptions.length
      : zhOptions.length;
  return {
    for (var index = 0; index < length; index++)
      String.fromCharCode(65 + index): {
        if (index < enOptions.length && enOptions[index].isNotEmpty)
          'en': enOptions[index],
        if (index < zhOptions.length && zhOptions[index].isNotEmpty)
          'zh': zhOptions[index],
      },
  };
}

Map<String, String> _localeOptionI18n(Map<String, dynamic> data, String key) =>
    _localeOptionsI18n(data)[key] ?? const {};

List<String> _localeOptionList(Object? locale) {
  if (locale is! Map) return const [];
  final raw = locale['options'];
  if (raw is Iterable) {
    return raw.map((item) => item?.toString().trim() ?? '').toList();
  }
  return const [];
}

Map<String, String> _withKnownFinalExamZh(
  Map<String, String>? values,
  Object? fallback,
) {
  final map = <String, String>{...?values};
  final english = (map['en'] ?? localizedValue(fallback, 'en')).trim();
  if (english.isEmpty) {
    return map;
  }
  final chinese = _knownFinalExamZh(english);
  if (chinese.isNotEmpty) {
    final existingChinese = map['zh']?.trim() ?? '';
    if (existingChinese.isNotEmpty && _containsCjk(existingChinese)) {
      return map;
    }
    map['en'] = english;
    map['zh'] = chinese;
  }
  return map;
}

String _knownFinalExamZh(String english) {
  return finalExamFallbackZh(english);
}

bool _containsCjk(String value) => RegExp(r'[\u3400-\u9fff]').hasMatch(value);
