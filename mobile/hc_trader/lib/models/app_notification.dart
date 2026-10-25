import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';

enum AppNotificationCategory {
  tradingSignals,
  announcements,
  stockReports,
  stockWatchlist,
  appointments,
  education,
  system;

  String get storageValue => switch (this) {
    AppNotificationCategory.tradingSignals => 'signals',
    AppNotificationCategory.stockReports => 'stock_reports',
    AppNotificationCategory.stockWatchlist => 'stock_watchlist',
    _ => name,
  };

  static AppNotificationCategory fromValue(Object? value) {
    final normalized = '$value'.trim().toLowerCase();
    return AppNotificationCategory.values.firstWhere(
      (category) => category.storageValue == normalized,
      orElse: () => AppNotificationCategory.system,
    );
  }
}

class AppNotificationRecord {
  const AppNotificationRecord({
    required this.id,
    required this.eventId,
    required this.category,
    required this.title,
    required this.message,
    required this.createdAt,
    this.deepLink = '',
    this.entityId = '',
    this.symbol = '',
    this.readAt,
    this.archivedAt,
  });

  final String id;
  final String eventId;
  final AppNotificationCategory category;
  final Map<String, String> title;
  final Map<String, String> message;
  final String deepLink;
  final String entityId;
  final String symbol;
  final DateTime createdAt;
  final DateTime? readAt;
  final DateTime? archivedAt;

  bool get isRead => readAt != null;
  bool get isArchived => archivedAt != null;
  String localizedTitle(String languageCode) =>
      getLocalizedText(title, languageCode, fallback: 'HC Trader');
  String localizedMessage(String languageCode) =>
      getLocalizedText(message, languageCode);

  factory AppNotificationRecord.fromMap(String id, Map<String, dynamic> data) =>
      AppNotificationRecord(
        id: id,
        eventId: '${data['event_id'] ?? id}',
        category: AppNotificationCategory.fromValue(data['category']),
        title: _localized(data['title_i18n'] ?? data['title']),
        message: _localized(
          data['message_i18n'] ?? data['message'] ?? data['body'],
        ),
        deepLink: '${data['deep_link'] ?? ''}',
        entityId: '${data['entity_id'] ?? data['document_id'] ?? ''}',
        symbol: '${data['symbol'] ?? ''}',
        createdAt: _date(data['created_at']) ?? DateTime(1970),
        readAt: _date(data['read_at']),
        archivedAt: _date(data['archived_at']),
      );
}

Map<String, String> _localized(Object? value) {
  if (value is Map) {
    return {
      for (final code in const ['en', 'zh'])
        if ('${value[code] ?? ''}'.trim().isNotEmpty)
          code: '${value[code]}'.trim(),
    };
  }
  final text = '${value ?? ''}'.trim();
  return text.isEmpty ? const {} : {'en': text};
}

DateTime? _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return value == null ? null : DateTime.tryParse('$value');
}
