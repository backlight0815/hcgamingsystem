import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';

enum ContentPublicationStatus {
  draft,
  scheduled,
  published,
  unpublished,
  archived;

  static ContentPublicationStatus fromValue(Object? value) {
    final normalized = '$value'.trim().toLowerCase().replaceAll('_', '');
    return ContentPublicationStatus.values.firstWhere(
      (status) => status.name.toLowerCase() == normalized,
      orElse: () => ContentPublicationStatus.draft,
    );
  }
}

enum ManagedContentType {
  tradingArticle,
  financialNews,
  marketAnalysis,
  knowledgeCentre,
  tradingEducation,
  video,
  recording,
  blogPost,
  financialDailyReport,
  disclaimer,
  banner;

  String get storageValue => switch (this) {
    ManagedContentType.tradingArticle => 'trading_article',
    ManagedContentType.financialNews => 'financial_news',
    ManagedContentType.marketAnalysis => 'market_analysis',
    ManagedContentType.knowledgeCentre => 'knowledge_centre',
    ManagedContentType.tradingEducation => 'trading_education',
    ManagedContentType.video => 'video',
    ManagedContentType.recording => 'recording',
    ManagedContentType.blogPost => 'blog_post',
    ManagedContentType.financialDailyReport => 'financial_daily_report',
    ManagedContentType.disclaimer => 'disclaimer',
    ManagedContentType.banner => 'banner',
  };

  static ManagedContentType fromValue(Object? value) {
    final normalized = '$value'.trim().toLowerCase();
    return ManagedContentType.values.firstWhere(
      (type) => type.storageValue == normalized || type.name == normalized,
      orElse: () => ManagedContentType.tradingArticle,
    );
  }
}

class LocalizedPublishingFields {
  const LocalizedPublishingFields({
    this.titleEn = '',
    this.titleZh = '',
    this.summaryEn = '',
    this.summaryZh = '',
    this.contentEn = '',
    this.contentZh = '',
  });

  final String titleEn;
  final String titleZh;
  final String summaryEn;
  final String summaryZh;
  final String contentEn;
  final String contentZh;

  bool get hasEnglish =>
      titleEn.trim().isNotEmpty ||
      summaryEn.trim().isNotEmpty ||
      contentEn.trim().isNotEmpty;
  bool get hasChinese =>
      titleZh.trim().isNotEmpty ||
      summaryZh.trim().isNotEmpty ||
      contentZh.trim().isNotEmpty;
  bool get hasPublishableTitle =>
      titleEn.trim().isNotEmpty || titleZh.trim().isNotEmpty;
  bool get hasPublishableContent =>
      contentEn.trim().isNotEmpty || contentZh.trim().isNotEmpty;

  List<String> validateForPublish({bool requireContent = true}) {
    return <String>[
      if (!hasPublishableTitle) 'A title is required in English or Chinese.',
      if (requireContent && !hasPublishableContent)
        'Content is required in English or Chinese.',
    ];
  }

  String localizedTitle(String languageCode) =>
      getLocalizedText({'en': titleEn, 'zh': titleZh}, languageCode);

  String localizedSummary(String languageCode) =>
      getLocalizedText({'en': summaryEn, 'zh': summaryZh}, languageCode);

  String localizedContent(String languageCode) =>
      getLocalizedText({'en': contentEn, 'zh': contentZh}, languageCode);

  Map<String, Object?> toFirestore() => {
    'title_i18n': {
      if (titleEn.trim().isNotEmpty) 'en': titleEn.trim(),
      if (titleZh.trim().isNotEmpty) 'zh': titleZh.trim(),
    },
    'summary_i18n': {
      if (summaryEn.trim().isNotEmpty) 'en': summaryEn.trim(),
      if (summaryZh.trim().isNotEmpty) 'zh': summaryZh.trim(),
    },
    'content_i18n': {
      if (contentEn.trim().isNotEmpty) 'en': contentEn.trim(),
      if (contentZh.trim().isNotEmpty) 'zh': contentZh.trim(),
    },
    'available_languages': [if (hasEnglish) 'en', if (hasChinese) 'zh'],
  };

  factory LocalizedPublishingFields.fromMap(Map<String, dynamic> data) {
    String localized(String key, String language) {
      final value = data['${key}_i18n'] ?? data[key];
      if (value is Map) return '${value[language] ?? ''}'.trim();
      if (language == 'en') return '${value ?? ''}'.trim();
      return '${data['${key}_zh'] ?? ''}'.trim();
    }

    return LocalizedPublishingFields(
      titleEn: localized('title', 'en'),
      titleZh: localized('title', 'zh'),
      summaryEn: localized('summary', 'en'),
      summaryZh: localized('summary', 'zh'),
      contentEn: localized('content', 'en'),
      contentZh: localized('content', 'zh'),
    );
  }
}

class ManagedContentRecord {
  const ManagedContentRecord({
    required this.id,
    required this.type,
    required this.status,
    required this.localized,
    required this.createdAt,
    required this.updatedAt,
    this.category = '',
    this.authorName = '',
    this.thumbnailUrl = '',
    this.tags = const [],
    this.symbols = const [],
    this.publishedAt,
    this.scheduledAt,
    this.viewCount = 0,
    this.bookmarkCount = 0,
    this.featured = false,
  });

  final String id;
  final ManagedContentType type;
  final ContentPublicationStatus status;
  final LocalizedPublishingFields localized;
  final String category;
  final String authorName;
  final String thumbnailUrl;
  final List<String> tags;
  final List<String> symbols;
  final DateTime createdAt;
  final DateTime updatedAt;
  final DateTime? publishedAt;
  final DateTime? scheduledAt;
  final int viewCount;
  final int bookmarkCount;
  final bool featured;

  factory ManagedContentRecord.fromMap(String id, Map<String, dynamic> data) {
    return ManagedContentRecord(
      id: id,
      type: ManagedContentType.fromValue(data['content_type'] ?? data['type']),
      status: ContentPublicationStatus.fromValue(
        data['publication_status'] ?? data['status'],
      ),
      localized: LocalizedPublishingFields.fromMap(data),
      category: '${data['category'] ?? ''}',
      authorName: '${data['author_name'] ?? data['authorName'] ?? ''}',
      thumbnailUrl: '${data['thumbnail_url'] ?? data['image_url'] ?? ''}',
      tags: _strings(data['tags']),
      symbols: _strings(data['symbols'] ?? data['related_symbols']),
      createdAt:
          _date(data['created_at'] ?? data['createdAt']) ?? DateTime(1970),
      updatedAt:
          _date(data['updated_at'] ?? data['updatedAt']) ?? DateTime(1970),
      publishedAt: _date(data['published_at'] ?? data['publishedAt']),
      scheduledAt: _date(data['scheduled_at'] ?? data['scheduledAt']),
      viewCount: _integer(data['view_count'] ?? data['viewCount']),
      bookmarkCount: _integer(data['bookmark_count'] ?? data['bookmarkCount']),
      featured: data['featured'] == true || data['is_featured'] == true,
    );
  }
}

List<String> _strings(Object? value) => value is Iterable
    ? value
          .map((item) => '$item'.trim())
          .where((item) => item.isNotEmpty)
          .toList(growable: false)
    : const [];

int _integer(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

DateTime? _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return value == null ? null : DateTime.tryParse('$value');
}
