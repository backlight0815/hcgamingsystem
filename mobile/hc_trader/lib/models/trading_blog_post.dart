import 'package:cloud_firestore/cloud_firestore.dart';

import 'firestore_helpers.dart';

class TradingBlogPost {
  const TradingBlogPost({
    required this.id,
    required this.title,
    this.titleZh = '',
    this.slug = '',
    this.category = 'trading_sharing',
    this.categoryLabelValue = '',
    this.excerpt = '',
    this.excerptZh = '',
    this.content = '',
    this.contentZh = '',
    this.coverImage = '',
    this.tags = const [],
    this.status = 'published',
    this.isFeatured = false,
    this.publishedAt,
    this.views = 0,
    this.readingMinutesValue,
    this.authorId = '',
    this.authorName = '',
    this.authorUsername = '',
    this.source = 'WEBSITE',
    this.version = 0,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String title;
  final String titleZh;
  final String slug;
  final String category;
  final String categoryLabelValue;
  final String excerpt;
  final String excerptZh;
  final String content;
  final String contentZh;
  final String coverImage;
  final List<String> tags;
  final String status;
  final bool isFeatured;
  final DateTime? publishedAt;
  final int views;
  final int? readingMinutesValue;
  final String authorId;
  final String authorName;
  final String authorUsername;
  final String source;
  final int version;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  // Kept as source-compatible helpers for older callers. Trading Blog content
  // is deliberately English-only and never varies with the device locale.
  String localizedTitle(String _) => title;
  String localizedExcerpt(String _) => excerpt;
  String localizedContent(String _) => content;
  List<String> localizedTags(String _) => tags;

  String get categoryLabel {
    if (categoryLabelValue.trim().isNotEmpty) {
      return categoryLabelValue.trim();
    }
    return switch (category) {
      'knowledge_sharing' => 'Knowledge Sharing',
      'psychology_sharing' => 'Trading Psychology',
      'future_prop_firm_sharing' => 'Future Prop Firm',
      'risk_management' => 'Risk Management',
      'market_outlook' => 'Market Outlook',
      'funded_trader_journey' => 'Funded Trader Journey',
      _ => 'Trading Sharing',
    };
  }

  int get readingMinutes {
    final serverValue = readingMinutesValue;
    if (serverValue != null && serverValue > 0) return serverValue;
    final words = content
        .replaceAll(RegExp(r'<[^>]+>'), ' ')
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty);
    return (words.length / 220).ceil().clamp(1, 999);
  }

  String get authorLabel {
    if (authorUsername.trim().isNotEmpty) return authorUsername.trim();
    if (authorName.trim().isNotEmpty) return authorName.trim();
    return 'HC Gaming';
  }

  /// Compatibility reader for legacy Firestore data. It selects only the
  /// stored English value and is not used as a locale-dependent fallback.
  factory TradingBlogPost.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    return TradingBlogPost(
      id: doc.id,
      title: _englishText(data['title']),
      slug: _text(data['slug'], fallback: doc.id),
      category: _text(data['category'], fallback: 'trading_sharing'),
      categoryLabelValue: _text(data['category_label']),
      excerpt: _englishText(data['excerpt']),
      content: _englishText(data['content']),
      coverImage: _text(data['cover_image']),
      tags: _englishStringList(data['tags']),
      status: _status(data['status']),
      isFeatured: firestoreBool(data['is_featured']),
      publishedAt: firestoreDate(data['published_at']),
      views: firestoreInt(data['views']),
      readingMinutesValue: _optionalPositiveInt(data['reading_minutes']),
      authorId: _authorValue(data, 'id'),
      authorName: _authorValue(data, 'name'),
      authorUsername: _authorValue(data, 'username'),
      source: _text(data['source'], fallback: 'FIRESTORE'),
      version: firestoreInt(data['version']),
      createdAt: firestoreDate(data['created_at']),
      updatedAt: firestoreDate(data['updated_at']),
    );
  }

  factory TradingBlogPost.fromJson(Map<String, dynamic> data) {
    final id = _text(data['id']);
    final title = _text(data['title']);
    if (id.isEmpty) {
      throw const FormatException('Trading blog id is missing.');
    }
    if (title.isEmpty) {
      throw FormatException('Trading blog $id has no English title.');
    }

    return TradingBlogPost(
      id: id,
      title: title,
      titleZh: _text(data['title_zh']),
      slug: _text(data['slug']),
      category: _text(data['category'], fallback: 'trading_sharing'),
      categoryLabelValue: _text(data['category_label']),
      excerpt: _text(data['excerpt']),
      excerptZh: _text(data['excerpt_zh']),
      content: _text(data['content']),
      contentZh: _text(data['content_zh']),
      coverImage: _text(data['cover_image']),
      tags: _englishStringList(data['tags']),
      status: _status(data['status']),
      isFeatured: firestoreBool(data['is_featured']),
      publishedAt: firestoreDate(data['published_at']),
      views: firestoreInt(data['views']),
      readingMinutesValue: _optionalPositiveInt(data['reading_minutes']),
      authorId: _authorValue(data, 'id'),
      authorName: _authorValue(data, 'name'),
      authorUsername: _authorValue(data, 'username'),
      source: _text(data['source'], fallback: 'WEBSITE'),
      version: firestoreInt(data['version']),
      createdAt: firestoreDate(data['created_at']),
      updatedAt: firestoreDate(data['updated_at']),
    );
  }

  TradingBlogPost mergeJson(Map<String, dynamic> data) {
    final merged = <String, dynamic>{
      'id': id,
      'title': title,
      'title_zh': titleZh,
      'slug': slug,
      'category': category,
      'category_label': categoryLabelValue,
      'excerpt': excerpt,
      'excerpt_zh': excerptZh,
      'content': content,
      'content_zh': contentZh,
      'cover_image': coverImage,
      'tags': tags,
      'status': status,
      'is_featured': isFeatured,
      'published_at': publishedAt?.toIso8601String(),
      'views': views,
      'reading_minutes': readingMinutesValue,
      'author': {
        'id': authorId,
        'name': authorName,
        'username': authorUsername,
      },
      'source': source,
      'version': version,
      'created_at': createdAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
      ...data,
    };
    return TradingBlogPost.fromJson(merged);
  }

  Map<String, Object?> toFirestore() {
    return {
      'title': title,
      'slug': slug.isEmpty ? id : slug,
      'category': category,
      if (categoryLabelValue.isNotEmpty) 'category_label': categoryLabelValue,
      'excerpt': excerpt,
      'content': content,
      'cover_image': coverImage,
      'tags': tags,
      'status': status,
      'is_featured': isFeatured,
      'published_at': Timestamp.fromDate(publishedAt ?? DateTime.now()),
      'views': views,
      'source': source,
      'version': version,
    };
  }
}

String _text(Object? value, {String fallback = ''}) {
  if (value == null) return fallback;
  final text = value is String || value is num ? value.toString().trim() : '';
  return text.isEmpty ? fallback : text;
}

String _englishText(Object? value) {
  if (value is Map) return _text(value['en']);
  return _text(value);
}

List<String> _englishStringList(Object? value) {
  if (value is Iterable) {
    return value
        .map(_englishText)
        .where((item) => item.isNotEmpty)
        .toList(growable: false);
  }
  final text = _englishText(value);
  if (text.isEmpty) return const [];
  return text
      .split(',')
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}

String _status(Object? value) {
  final status = _text(value).toLowerCase();
  return status.isEmpty ? 'published' : status;
}

int? _optionalPositiveInt(Object? value) {
  final parsed = value is num ? value.toInt() : int.tryParse('$value');
  return parsed != null && parsed > 0 ? parsed : null;
}

String _authorValue(Map<String, dynamic> data, String key) {
  final author = data['author'];
  if (author is Map) return _text(author[key]);
  return '';
}
