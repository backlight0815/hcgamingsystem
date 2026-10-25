import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';
import 'knowledge_centre_category.dart';

class KnowledgeCentreImageSectionMobile {
  const KnowledgeCentreImageSectionMobile({
    required this.imageId,
    required this.type,
    required this.assetPath,
    required this.storagePath,
    required this.imageUrl,
    required this.sortOrder,
    required this.concept,
    required this.qualityStatus,
    required this.title,
    required this.caption,
    required this.description,
    required this.altText,
    this.titleI18n = const {},
    this.captionI18n = const {},
    this.descriptionI18n = const {},
    this.altTextI18n = const {},
  });

  final String imageId;
  final String type;
  final String assetPath;
  final String storagePath;
  final String imageUrl;
  final int sortOrder;
  final String concept;
  final String qualityStatus;
  final String title;
  final String caption;
  final String description;
  final String altText;
  final Map<String, String> titleI18n;
  final Map<String, String> captionI18n;
  final Map<String, String> descriptionI18n;
  final Map<String, String> altTextI18n;

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);
  String localizedCaption(String languageCode) =>
      localizedValue(captionI18n, languageCode, fallback: caption);
  String localizedDescription(String languageCode) =>
      localizedValue(descriptionI18n, languageCode, fallback: description);
  String localizedAltText(String languageCode) =>
      localizedValue(altTextI18n, languageCode, fallback: altText);

  factory KnowledgeCentreImageSectionMobile.fromJson(
    Map<String, dynamic> data,
  ) {
    final localeFields = _imageLocaleFields(data);
    return KnowledgeCentreImageSectionMobile(
      imageId: (data['imageId'] ?? '').toString(),
      type: (data['type'] ?? 'diagram').toString(),
      assetPath: (data['assetPath'] ?? '').toString(),
      storagePath: (data['storagePath'] ?? '').toString(),
      imageUrl: (data['imageUrl'] ?? data['image_url'] ?? '').toString(),
      sortOrder: _int(data['sortOrder']),
      concept: (data['concept'] ?? '').toString(),
      qualityStatus: (data['qualityStatus'] ?? '').toString(),
      title: localizedValue(localeFields['title'], 'en'),
      caption: localizedValue(localeFields['caption'], 'en'),
      description: localizedValue(localeFields['description'], 'en'),
      altText: localizedValue(localeFields['altText'], 'en'),
      titleI18n: localeFields['title'] ?? const {},
      captionI18n: localeFields['caption'] ?? const {},
      descriptionI18n: localeFields['description'] ?? const {},
      altTextI18n: localeFields['altText'] ?? const {},
    );
  }
}

class KnowledgeCentreItemMobile {
  const KnowledgeCentreItemMobile({
    required this.id,
    required this.title,
    this.description = '',
    this.filePath = '',
    this.imageUrl = '',
    this.status = true,
    this.approvalStatus = 'approved',
    this.approvedAt,
    this.createdAt,
    this.knowledgeCenterId = '',
    this.category = '',
    this.order = 0,
    this.contentVersion = 0,
    this.qualityStatus = '',
    this.shortDescription = '',
    this.learningObjectives = const [],
    this.beginnerExplanation = '',
    this.practicalExplanation = '',
    this.fullContent = '',
    this.stepByStepGuide = const [],
    this.keyPoints = const [],
    this.examples = const [],
    this.commonMistakes = const [],
    this.checklist = const [],
    this.relatedConcepts = const [],
    this.imageSections = const [],
    this.thumbnailAssetPath = '',
    this.relatedQuestionTarget = 0,
    this.futureFinalExamMappingReady = false,
    this.mappedQuestionIds = const [],
    this.titleI18n = const {},
    this.descriptionI18n = const {},
    this.shortDescriptionI18n = const {},
    this.learningObjectivesI18n = const {},
    this.beginnerExplanationI18n = const {},
    this.practicalExplanationI18n = const {},
    this.fullContentI18n = const {},
    this.stepByStepGuideI18n = const {},
    this.keyPointsI18n = const {},
    this.examplesI18n = const {},
    this.commonMistakesI18n = const {},
    this.checklistI18n = const {},
    this.relatedConceptsI18n = const {},
  });

  final String id;
  final String title;
  final String description;
  final String filePath;
  final String imageUrl;
  final bool status;
  final String approvalStatus;
  final DateTime? approvedAt;
  final DateTime? createdAt;
  final String knowledgeCenterId;
  final String category;
  final int order;
  final int contentVersion;
  final String qualityStatus;
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
  final List<KnowledgeCentreImageSectionMobile> imageSections;
  final String thumbnailAssetPath;
  final int relatedQuestionTarget;
  final bool futureFinalExamMappingReady;
  final List<String> mappedQuestionIds;
  final Map<String, String> titleI18n;
  final Map<String, String> descriptionI18n;
  final Map<String, String> shortDescriptionI18n;
  final Map<String, List<String>> learningObjectivesI18n;
  final Map<String, String> beginnerExplanationI18n;
  final Map<String, String> practicalExplanationI18n;
  final Map<String, String> fullContentI18n;
  final Map<String, List<String>> stepByStepGuideI18n;
  final Map<String, List<String>> keyPointsI18n;
  final Map<String, List<String>> examplesI18n;
  final Map<String, List<String>> commonMistakesI18n;
  final Map<String, List<String>> checklistI18n;
  final Map<String, List<String>> relatedConceptsI18n;

  bool get isVisible => status && approvalStatus == 'approved';

  String get primaryAssetPath => thumbnailAssetPath.trim().isNotEmpty
      ? thumbnailAssetPath
      : imageSections
            .map((image) => image.assetPath)
            .firstWhere((value) => value.trim().isNotEmpty, orElse: () => '');

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);

  String localizedDescription(String languageCode) =>
      localizedShortDescription(languageCode);

  String localizedShortDescription(String languageCode) => localizedValue(
    shortDescriptionI18n,
    languageCode,
    fallback: shortDescription.isNotEmpty ? shortDescription : description,
  );

  List<String> localizedLearningObjectives(String languageCode) =>
      _localizedList(learningObjectivesI18n, languageCode, learningObjectives);

  String localizedBeginnerExplanation(String languageCode) => localizedValue(
    beginnerExplanationI18n,
    languageCode,
    fallback: beginnerExplanation,
  );

  String localizedPracticalExplanation(String languageCode) => localizedValue(
    practicalExplanationI18n,
    languageCode,
    fallback: practicalExplanation,
  );

  String localizedFullContent(String languageCode) =>
      localizedValue(fullContentI18n, languageCode, fallback: fullContent);

  List<String> localizedStepByStepGuide(String languageCode) =>
      _localizedList(stepByStepGuideI18n, languageCode, stepByStepGuide);

  List<String> localizedKeyPoints(String languageCode) =>
      _localizedList(keyPointsI18n, languageCode, keyPoints);

  List<String> localizedExamples(String languageCode) =>
      _localizedList(examplesI18n, languageCode, examples);

  List<String> localizedCommonMistakes(String languageCode) =>
      _localizedList(commonMistakesI18n, languageCode, commonMistakes);

  List<String> localizedChecklist(String languageCode) =>
      _localizedList(checklistI18n, languageCode, checklist);

  List<String> localizedRelatedConcepts(String languageCode) =>
      _localizedList(relatedConceptsI18n, languageCode, relatedConcepts);

  String localizedCategoryLabel(String languageCode) {
    final chinese = isChineseLocale(languageCode);
    final normalizedCategory = KnowledgeCentreCategory.normalizeSlug(category);
    return switch (normalizedCategory) {
      'theory' => chinese ? '\u7406\u8bba' : 'Theory',
      'technical' => chinese ? '\u6280\u672f\u5206\u6790' : 'Technical',
      'psychology' => chinese ? '\u4ea4\u6613\u5fc3\u7406' : 'Psychology',
      'stocks' => chinese ? '\u80a1\u7968' : 'Stocks',
      'futures-trading' =>
        chinese ? '\u671f\u8d27\u4ea4\u6613' : 'Futures Trading',
      'investment' => chinese ? '\u6295\u8d44' : 'Investment',
      'cfd-trading' =>
        chinese ? '\u5dee\u4ef7\u5408\u7ea6\u4ea4\u6613' : 'CFD Trading',
      _ => chinese ? '\u672a\u77e5' : 'Unknown',
    };
  }

  factory KnowledgeCentreItemMobile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = {'id': doc.id, ...?doc.data()};
    return KnowledgeCentreItemMobile.fromJson(data);
  }

  factory KnowledgeCentreItemMobile.fromJson(Map<String, dynamic> data) {
    final localizedEn = getLocalizedKnowledgeCentre(data, 'en');
    final titleI18n = {
      ..._localizedField(data, 'title'),
      ..._localeField(data, 'title'),
    };
    final shortDescriptionI18n = {
      ..._localizedField(
        data,
        'shortDescription',
        aliasKeys: const ['description'],
      ),
      ..._localeField(data, 'shortDescription'),
    };
    final fullContentI18n = {
      ..._localizedField(data, 'fullContent'),
      ..._localeField(data, 'fullContent'),
    };
    final beginnerExplanationI18n = {
      ..._localizedField(data, 'beginnerExplanation'),
      ..._localeField(data, 'beginnerExplanation'),
    };
    final practicalExplanationI18n = {
      ..._localizedField(data, 'practicalExplanation'),
      ..._localeField(data, 'practicalExplanation'),
    };
    final description = localizedValue(
      shortDescriptionI18n,
      'en',
      fallback: localizedEn.shortDescription,
    );
    final images = _imageSections(data['imageSections']);
    final statusValue = data.containsKey('isActive')
        ? data['isActive']
        : data['status'];
    return KnowledgeCentreItemMobile(
      id: (data['id'] ?? data['knowledgeCenterId'] ?? '').toString(),
      knowledgeCenterId: (data['knowledgeCenterId'] ?? data['id'] ?? '')
          .toString(),
      title: localizedValue(titleI18n, 'en', fallback: localizedEn.title),
      description: description,
      shortDescription: description,
      fullContent: localizedValue(
        fullContentI18n,
        'en',
        fallback: localizedEn.fullContent,
      ),
      filePath: (data['file_path'] ?? data['filePath'] ?? '').toString(),
      imageUrl: (data['image_url'] ?? data['imageUrl'] ?? '').toString(),
      status: firestoreBool(statusValue, fallback: true),
      approvalStatus: (data['approval_status'] ?? 'approved').toString(),
      approvedAt: firestoreDate(data['approved_at']),
      createdAt: firestoreDate(data['created_at'] ?? data['createdAt']),
      category: KnowledgeCentreCategory.normalizeSlug(data['category']),
      order: _int(data['order']),
      contentVersion: _int(data['contentVersion']),
      qualityStatus: (data['qualityStatus'] ?? '').toString(),
      learningObjectives: localizedEn.learningObjectives,
      beginnerExplanation: localizedValue(
        beginnerExplanationI18n,
        'en',
        fallback: localizedEn.beginnerExplanation,
      ),
      practicalExplanation: localizedValue(
        practicalExplanationI18n,
        'en',
        fallback: localizedEn.practicalExplanation,
      ),
      keyPoints: localizedEn.keyPoints,
      stepByStepGuide: localizedEn.stepByStepGuide,
      examples: localizedEn.examples,
      commonMistakes: localizedEn.commonMistakes,
      checklist: localizedEn.checklist,
      relatedConcepts: localizedEn.relatedConcepts,
      imageSections: images,
      thumbnailAssetPath:
          (data['thumbnailAssetPath'] ??
                  images
                      .map((image) => image.assetPath)
                      .firstWhere(
                        (value) => value.trim().isNotEmpty,
                        orElse: () => '',
                      ))
              .toString(),
      relatedQuestionTarget: _int(data['relatedQuestionTarget']),
      futureFinalExamMappingReady: data['futureFinalExamMappingReady'] == true,
      mappedQuestionIds: _stringList(data['mappedQuestionIds']),
      titleI18n: titleI18n,
      descriptionI18n: shortDescriptionI18n,
      shortDescriptionI18n: shortDescriptionI18n,
      learningObjectivesI18n: _localizedListField(data, 'learningObjectives'),
      beginnerExplanationI18n: beginnerExplanationI18n,
      practicalExplanationI18n: practicalExplanationI18n,
      fullContentI18n: fullContentI18n,
      stepByStepGuideI18n: _localizedListField(data, 'stepByStepGuide'),
      keyPointsI18n: _localizedListField(data, 'keyPoints'),
      examplesI18n: _localizedListField(data, 'examples'),
      commonMistakesI18n: _localizedListField(data, 'commonMistakes'),
      checklistI18n: _localizedListField(data, 'checklist'),
      relatedConceptsI18n: _localizedListField(data, 'relatedConcepts'),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'knowledgeCenterId': knowledgeCenterId.isEmpty ? id : knowledgeCenterId,
      'category': category,
      'order': order,
      'contentVersion': contentVersion,
      'qualityStatus': qualityStatus,
      'isActive': status,
      'status': status,
      'approval_status': approvalStatus,
      'title': titleI18n.isEmpty ? title : titleI18n,
      'description': shortDescriptionI18n.isEmpty
          ? description
          : shortDescriptionI18n,
      'file_path': filePath,
      'image_url': imageUrl,
      'thumbnailAssetPath': thumbnailAssetPath,
      'relatedQuestionTarget': relatedQuestionTarget,
      'futureFinalExamMappingReady': futureFinalExamMappingReady,
      'mappedQuestionIds': mappedQuestionIds,
      'approved_at': approvedAt == null
          ? null
          : Timestamp.fromDate(approvedAt!),
      'created_at': createdAt == null ? null : Timestamp.fromDate(createdAt!),
      'updated_at': FieldValue.serverTimestamp(),
    };
  }
}

Map<String, String> _localizedField(
  Map<String, dynamic> data,
  String key, {
  List<String> aliasKeys = const [],
}) {
  final map = <String, String>{
    ...localizedTextMap(data['${key}_i18n']),
    ...localizedTextMap(data[key]),
  };
  for (final alias in aliasKeys) {
    map.addAll(localizedTextMap(data['${alias}_i18n']));
    map.addAll(localizedTextMap(data[alias]));
  }
  for (final candidate in [key, ...aliasKeys]) {
    final english = data['${candidate}_en']?.toString().trim() ?? '';
    final chinese = data['${candidate}_zh']?.toString().trim() ?? '';
    if (english.isNotEmpty) map['en'] = english;
    if (chinese.isNotEmpty) map['zh'] = chinese;
  }
  return map;
}

Map<String, String> _localeField(Map<String, dynamic> data, String key) {
  final locales = data['locales'];
  if (locales is! Map) return const {};
  final map = <String, String>{};
  for (final language in const ['en', 'zh']) {
    final locale = locales[language];
    if (locale is Map) {
      final value = locale[key]?.toString().trim() ?? '';
      if (value.isNotEmpty) map[language] = value;
    }
  }
  return map;
}

Map<String, List<String>> _localizedListField(
  Map<String, dynamic> data,
  String key,
) {
  final locales = data['locales'];
  if (locales is! Map) return const {};
  final map = <String, List<String>>{};
  for (final language in const ['en', 'zh']) {
    final locale = locales[language];
    if (locale is Map) {
      final values = _stringList(locale[key]);
      if (values.isNotEmpty) map[language] = values;
    }
  }
  return map;
}

Map<String, Map<String, String>> _imageLocaleFields(Map<String, dynamic> data) {
  final locales = data['locales'];
  final fields = <String, Map<String, String>>{
    'title': {},
    'caption': {},
    'description': {},
    'altText': {},
  };
  if (locales is Map) {
    for (final language in const ['en', 'zh']) {
      final locale = locales[language];
      if (locale is Map) {
        for (final field in fields.keys) {
          final value = locale[field]?.toString().trim() ?? '';
          if (value.isNotEmpty) fields[field]![language] = value;
        }
      }
    }
  }
  return fields;
}

List<KnowledgeCentreImageSectionMobile> _imageSections(Object? raw) {
  if (raw is! Iterable) return const [];
  final images = raw
      .whereType<Map>()
      .map((item) {
        return KnowledgeCentreImageSectionMobile.fromJson(
          Map<String, dynamic>.from(item),
        );
      })
      .toList(growable: false);
  return images
    ..sort((left, right) => left.sortOrder.compareTo(right.sortOrder));
}

List<String> _localizedList(
  Map<String, List<String>> values,
  String languageCode,
  List<String> fallback,
) {
  final preferred = isChineseLocale(languageCode) ? 'zh' : 'en';
  final direct = values[preferred] ?? const [];
  if (direct.isNotEmpty) return direct;
  final fallbackCode = preferred == 'zh' ? 'en' : 'zh';
  final translatedFallback = values[fallbackCode] ?? const [];
  if (translatedFallback.isNotEmpty) return translatedFallback;
  return fallback;
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

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
