import 'package:flutter/widgets.dart';

import 'app_localizations.dart';

class LocalizedText {
  const LocalizedText(this.values);

  factory LocalizedText.fromFirestore(Object? value, {Object? fallback}) {
    final values = {...localizedTextMap(fallback), ...localizedTextMap(value)};
    return LocalizedText(values);
  }

  final Map<String, String> values;

  bool get isEmpty => values.values.every((value) => value.trim().isEmpty);

  String getText(BuildContext context) =>
      textForLanguage(AppLocalizations.of(context).locale.languageCode);

  String textForLanguage(String languageCode, {Object? fallback}) =>
      localizedValue(values, languageCode, fallback: fallback);
}

class LocalizedExamText {
  const LocalizedExamText({
    required this.question,
    required this.options,
    required this.explanation,
    this.missingTranslations = const [],
  });

  final String question;
  final List<String> options;
  final String explanation;
  final List<String> missingTranslations;

  bool get hasMissingTranslations => missingTranslations.isNotEmpty;
}

class LocalizedKnowledgeImageSection {
  const LocalizedKnowledgeImageSection({
    required this.imageId,
    required this.type,
    required this.assetPath,
    required this.storagePath,
    required this.sortOrder,
    required this.concept,
    required this.qualityStatus,
    required this.title,
    required this.caption,
    required this.description,
    required this.altText,
    this.missingTranslations = const [],
  });

  final String imageId;
  final String type;
  final String assetPath;
  final String storagePath;
  final int sortOrder;
  final String concept;
  final String qualityStatus;
  final String title;
  final String caption;
  final String description;
  final String altText;
  final List<String> missingTranslations;
}

class LocalizedKnowledgeCentre {
  const LocalizedKnowledgeCentre({
    required this.title,
    required this.shortDescription,
    required this.learningObjectives,
    required this.beginnerExplanation,
    required this.practicalExplanation,
    required this.fullContent,
    required this.stepByStepGuide,
    required this.keyPoints,
    required this.examples,
    required this.commonMistakes,
    required this.checklist,
    required this.relatedConcepts,
    required this.imageSections,
    this.missingTranslations = const [],
  });

  final String title;
  final String shortDescription;
  final List<String> learningObjectives;
  final String beginnerExplanation;
  final String practicalExplanation;
  final String fullContent;
  final List<String> stepByStepGuide;
  final List<String> keyPoints;
  final List<String> examples;
  final List<String> commonMistakes;
  final List<String> checklist;
  final List<String> relatedConcepts;
  final List<LocalizedKnowledgeImageSection> imageSections;
  final List<String> missingTranslations;

  bool get hasMissingTranslations => missingTranslations.isNotEmpty;
}

LocalizedKnowledgeCentre getLocalizedKnowledgeCentre(
  Object? knowledgeDoc,
  Object? currentLocale,
) {
  final data = knowledgeDoc is Map
      ? Map<Object?, Object?>.from(knowledgeDoc)
      : {};
  final locales = data['locales'] is Map
      ? Map<Object?, Object?>.from(data['locales'] as Map)
      : const <Object?, Object?>{};
  final preferred = isChineseLocale(currentLocale) ? 'zh' : 'en';
  final fallback = preferred == 'zh' ? 'en' : 'zh';
  final preferredLocale = _mapValue(locales[preferred]);
  final fallbackLocale = _mapValue(locales[fallback]);
  final missing = <String>[];

  String field(String key, {Object? legacy}) {
    final value = preferredLocale[key]?.toString().trim() ?? '';
    if (value.isNotEmpty) return value;
    final fallbackValue =
        fallbackLocale[key]?.toString().trim() ??
        localizedValue(legacy, fallback);
    if (fallbackValue.isNotEmpty && preferred == 'zh') {
      missing.add('locales.zh.$key');
    }
    return fallbackValue;
  }

  List<String> listField(String key, {Object? legacy}) {
    final preferredList = _stringList(preferredLocale[key]);
    if (preferredList.isNotEmpty) return preferredList;
    final fallbackList = _stringList(fallbackLocale[key]);
    if (fallbackList.isNotEmpty) {
      if (preferred == 'zh') missing.add('locales.zh.$key');
      return fallbackList;
    }
    final legacyList = localizedStringList(legacy, fallback);
    if (legacyList.isNotEmpty && preferred == 'zh') {
      missing.add('locales.zh.$key');
    }
    return legacyList;
  }

  final imageSections = _localizedKnowledgeImages(
    data['imageSections'],
    preferred,
    fallback,
    missing,
  );

  if (missing.isNotEmpty) {
    debugPrint(
      'Missing Knowledge Centre translation fallback: ${missing.join(', ')}',
    );
  }

  return LocalizedKnowledgeCentre(
    title: field('title', legacy: data['title']),
    shortDescription: field(
      'shortDescription',
      legacy: data['description'] ?? data['shortDescription'],
    ),
    learningObjectives: listField('learningObjectives'),
    beginnerExplanation: field('beginnerExplanation'),
    practicalExplanation: field('practicalExplanation'),
    fullContent: field('fullContent'),
    stepByStepGuide: listField('stepByStepGuide'),
    keyPoints: listField('keyPoints'),
    examples: listField('examples'),
    commonMistakes: listField('commonMistakes'),
    checklist: listField('checklist'),
    relatedConcepts: listField('relatedConcepts'),
    imageSections: imageSections,
    missingTranslations: missing,
  );
}

LocalizedExamText getLocalizedExamText(
  Object? questionDoc,
  Object? currentLocale,
) {
  final data = questionDoc is Map
      ? Map<Object?, Object?>.from(questionDoc)
      : {};
  final locales = data['locales'] is Map
      ? Map<Object?, Object?>.from(data['locales'] as Map)
      : const <Object?, Object?>{};
  final preferred = isChineseLocale(currentLocale) ? 'zh' : 'en';
  final fallback = preferred == 'zh' ? 'en' : 'zh';
  final preferredLocale = _mapValue(locales[preferred]);
  final fallbackLocale = _mapValue(locales[fallback]);
  final missing = <String>[];

  String field(String key) {
    final value = preferredLocale[key]?.toString().trim() ?? '';
    if (value.isNotEmpty) return value;
    final fallbackValue = fallbackLocale[key]?.toString().trim() ?? '';
    if (fallbackValue.isNotEmpty) {
      if (preferred == 'zh') missing.add('locales.zh.$key');
      return fallbackValue;
    }
    return '';
  }

  final question = field('question');
  final explanation = field('explanation');
  final options = _localizedExamOptions(
    preferredLocale['options'],
    fallbackLocale['options'],
    missing,
    preferred,
  );

  if (missing.isNotEmpty) {
    debugPrint(
      'Missing final exam translation fallback: ${missing.join(', ')}',
    );
  }

  return LocalizedExamText(
    question: question,
    options: options,
    explanation: explanation,
    missingTranslations: missing,
  );
}

bool isChineseLocale(Object? locale) {
  final normalized = (locale?.toString() ?? '')
      .trim()
      .replaceAll('-', '_')
      .toLowerCase();
  return normalized == 'zh' ||
      normalized.startsWith('zh_') ||
      normalized.contains('chinese');
}

String localizedValue(Object? value, String languageCode, {Object? fallback}) {
  final preferred = isChineseLocale(languageCode) ? 'zh' : 'en';
  final fallbackCode = preferred == 'zh' ? 'en' : 'zh';
  final direct = _localizedFrom(value, preferred, fallbackCode);
  if (direct.trim().isNotEmpty) return direct;

  final fallbackValue = _localizedFrom(fallback, preferred, fallbackCode);
  if (fallbackValue.trim().isNotEmpty) return fallbackValue;

  return '';
}

String getLocalizedText(
  Object? field,
  Object? currentLanguage, {
  Object? fallback,
}) {
  final languageCode = currentLanguage?.toString() ?? '';
  return localizedValue(field, languageCode, fallback: fallback);
}

Map<Object?, Object?> _mapValue(Object? value) =>
    value is Map ? Map<Object?, Object?>.from(value) : const {};

List<String> _localizedExamOptions(
  Object? preferred,
  Object? fallback,
  List<String> missing,
  String preferredCode,
) {
  final preferredOptions = _stringOptions(preferred);
  final fallbackOptions = _stringOptions(fallback);
  final length = preferredOptions.isNotEmpty
      ? preferredOptions.length
      : fallbackOptions.length;
  final options = <String>[];
  for (var index = 0; index < length; index++) {
    final value = index < preferredOptions.length
        ? preferredOptions[index].trim()
        : '';
    if (value.isNotEmpty) {
      options.add(value);
      continue;
    }
    final fallbackValue = index < fallbackOptions.length
        ? fallbackOptions[index].trim()
        : '';
    if (fallbackValue.isNotEmpty && preferredCode == 'zh') {
      missing.add('locales.zh.options[$index]');
    }
    options.add(fallbackValue);
  }
  return options;
}

List<LocalizedKnowledgeImageSection> _localizedKnowledgeImages(
  Object? rawImages,
  String preferred,
  String fallback,
  List<String> missing,
) {
  if (rawImages is! Iterable) return const [];
  final images = <LocalizedKnowledgeImageSection>[];
  for (final raw in rawImages) {
    if (raw is! Map) continue;
    final data = Map<Object?, Object?>.from(raw);
    final locales = data['locales'] is Map
        ? Map<Object?, Object?>.from(data['locales'] as Map)
        : const <Object?, Object?>{};
    final preferredLocale = _mapValue(locales[preferred]);
    final fallbackLocale = _mapValue(locales[fallback]);
    final imageId = data['imageId']?.toString() ?? '';

    String imageField(String key) {
      final value = preferredLocale[key]?.toString().trim() ?? '';
      if (value.isNotEmpty) return value;
      final fallbackValue = fallbackLocale[key]?.toString().trim() ?? '';
      if (fallbackValue.isNotEmpty && preferred == 'zh') {
        missing.add('imageSections.$imageId.locales.zh.$key');
      }
      return fallbackValue;
    }

    images.add(
      LocalizedKnowledgeImageSection(
        imageId: imageId,
        type: data['type']?.toString() ?? '',
        assetPath: data['assetPath']?.toString() ?? '',
        storagePath: data['storagePath']?.toString() ?? '',
        sortOrder: _intValue(data['sortOrder']),
        concept: data['concept']?.toString() ?? '',
        qualityStatus: data['qualityStatus']?.toString() ?? '',
        title: imageField('title'),
        caption: imageField('caption'),
        description: imageField('description'),
        altText: imageField('altText'),
      ),
    );
  }
  images.sort((left, right) => left.sortOrder.compareTo(right.sortOrder));
  return images;
}

List<String> _stringOptions(Object? value) {
  if (value is Iterable) {
    return value.map((item) => item?.toString().trim() ?? '').toList();
  }
  if (value is Map) {
    final entries = value.entries.toList()
      ..sort(
        (left, right) => left.key.toString().compareTo(right.key.toString()),
      );
    return entries.map((entry) => localizedValue(entry.value, 'en')).toList();
  }
  return const [];
}

List<String> _stringList(Object? value) {
  if (value is Iterable) {
    return value
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList(growable: false);
  }
  final text = value?.toString().trim() ?? '';
  if (text.isEmpty) return const [];
  return [text];
}

int _intValue(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

List<String> localizedStringList(Object? value, String languageCode) {
  if (value is Iterable) {
    return value
        .map((item) => localizedValue(item, languageCode))
        .where((item) => item.trim().isNotEmpty)
        .toList(growable: false);
  }

  final text = localizedValue(value, languageCode).trim();
  if (text.isEmpty) return const [];
  return text
      .split(',')
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}

Map<String, String> localizedTextMap(Object? value) {
  if (value is Map) {
    final map = <String, String>{};
    for (final entry in value.entries) {
      final text = entry.value?.toString().trim() ?? '';
      if (text.isNotEmpty) {
        map[entry.key.toString()] = text;
      }
    }
    return map;
  }

  final text = value?.toString().trim() ?? '';
  if (text.isEmpty) return const {};
  return {'en': text};
}

String _localizedFrom(Object? value, String preferred, String fallbackCode) {
  if (value == null) return '';
  if (value is Map) {
    final map = Map<Object?, Object?>.from(value);
    for (final key in [preferred, fallbackCode, 'text', 'value', 'label']) {
      final text = map[key]?.toString().trim() ?? '';
      if (text.isNotEmpty) return text;
    }
    for (final entry in map.entries) {
      final text = entry.value?.toString().trim() ?? '';
      if (text.isNotEmpty) return text;
    }
    return '';
  }
  return value.toString();
}
