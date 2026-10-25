import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';

enum AnnouncementStatus { draft, scheduled, published, expired, archived }

enum AnnouncementType {
  general,
  important,
  maintenance,
  systemUpdate,
  tradingEvent,
  newFeature,
  education,
  promotion,
  emergency,
}

class Announcement {
  const Announcement({
    required this.id,
    required this.title,
    required this.message,
    required this.shortMessage,
    required this.audience,
    required this.status,
    required this.createdBy,
    required this.createdAt,
    required this.updatedAt,
    this.publishedAt,
    this.category = 'general',
    this.priority = 'normal',
    this.authorName = '',
    this.isPinned = false,
    this.isRead = false,
    this.attachmentUrls = const [],
    this.targetRoleIds = const [],
    this.targetUserIds = const [],
    this.targetPropFirmGroupIds = const [],
    this.targetAccessLevels = const [],
    this.titleI18n = const {},
    this.shortMessageI18n = const {},
    this.messageI18n = const {},
    this.startAt,
    this.expiresAt,
    this.displayLocations = const ['inbox'],
    this.imageUrl = '',
    this.actionLabelI18n = const {},
    this.deepLink = '',
    this.pushNotification = false,
    this.requireAcknowledgement = false,
    this.allowDismissal = true,
    this.isAcknowledged = false,
    this.isDismissed = false,
    this.deliveryRequestedCount = 0,
    this.deliverySuccessCount = 0,
    this.deliveryFailureCount = 0,
    this.readCount = 0,
    this.acknowledgementCount = 0,
    this.dismissalCount = 0,
  });

  final String id;
  final String title;
  final String message;
  final String shortMessage;
  final String audience;
  final String status;
  final String createdBy;
  final DateTime createdAt;
  final DateTime updatedAt;
  final DateTime? publishedAt;
  final String category;
  final String priority;
  final String authorName;
  final bool isPinned;
  final bool isRead;
  final List<String> attachmentUrls;
  final List<int> targetRoleIds;
  final List<String> targetUserIds;
  final List<String> targetPropFirmGroupIds;
  final List<String> targetAccessLevels;
  final Map<String, String> titleI18n;
  final Map<String, String> shortMessageI18n;
  final Map<String, String> messageI18n;
  final DateTime? startAt;
  final DateTime? expiresAt;
  final List<String> displayLocations;
  final String imageUrl;
  final Map<String, String> actionLabelI18n;
  final String deepLink;
  final bool pushNotification;
  final bool requireAcknowledgement;
  final bool allowDismissal;
  final bool isAcknowledged;
  final bool isDismissed;
  final int deliveryRequestedCount;
  final int deliverySuccessCount;
  final int deliveryFailureCount;
  final int readCount;
  final int acknowledgementCount;
  final int dismissalCount;

  bool get isPublished => status == 'published';
  bool get isActive {
    final now = DateTime.now();
    return isPublished &&
        (startAt == null || !startAt!.isAfter(now)) &&
        (expiresAt == null || expiresAt!.isAfter(now));
  }

  String localizedTitle(String languageCode) =>
      getLocalizedText(titleI18n, languageCode, fallback: title);
  String localizedShortMessage(String languageCode) =>
      getLocalizedText(shortMessageI18n, languageCode, fallback: shortMessage);
  String localizedMessage(String languageCode) =>
      getLocalizedText(messageI18n, languageCode, fallback: message);
  String localizedActionLabel(String languageCode) =>
      getLocalizedText(actionLabelI18n, languageCode);

  factory Announcement.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> document,
  ) {
    return Announcement.fromJson({'id': document.id, ...?document.data()});
  }

  factory Announcement.fromJson(Map<String, dynamic> json) {
    final now = DateTime.now();
    return Announcement(
      id: (json['id'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      message: (json['message'] ?? '').toString(),
      shortMessage: (json['short_message'] ?? json['shortMessage'] ?? '')
          .toString(),
      audience: (json['audience'] ?? 'all').toString(),
      status: (json['status'] ?? 'draft').toString(),
      createdBy: (json['created_by'] ?? json['createdBy'] ?? '').toString(),
      createdAt: _date(json['created_at'] ?? json['createdAt'], now),
      updatedAt: _date(json['updated_at'] ?? json['updatedAt'], now),
      publishedAt: _nullableDate(json['published_at'] ?? json['publishedAt']),
      category: (json['category'] ?? 'general').toString(),
      priority: (json['priority'] ?? 'normal').toString(),
      authorName: (json['author_name'] ?? json['authorName'] ?? '').toString(),
      isPinned: json['is_pinned'] == true || json['isPinned'] == true,
      isRead: json['is_read'] == true || json['isRead'] == true,
      attachmentUrls: _strings(json['attachment_urls']),
      targetRoleIds: _ints(json['target_role_ids']),
      targetUserIds: _strings(json['target_user_ids']),
      targetPropFirmGroupIds: _strings(json['target_prop_firm_group_ids']),
      targetAccessLevels: _strings(json['target_access_levels']),
      titleI18n: _localized(json, 'title'),
      shortMessageI18n: _localized(json, 'short_message'),
      messageI18n: _localized(json, 'message'),
      startAt: _nullableDate(json['start_at'] ?? json['startAt']),
      expiresAt: _nullableDate(
        json['expires_at'] ?? json['expiry_at'] ?? json['expiresAt'],
      ),
      displayLocations: _strings(
        json['display_locations'] ?? json['displayLocations'] ?? ['inbox'],
      ),
      imageUrl: '${json['image_url'] ?? json['imageUrl'] ?? ''}',
      actionLabelI18n: _localized(json, 'action_label'),
      deepLink: '${json['deep_link'] ?? json['deepLink'] ?? ''}',
      pushNotification:
          json['push_notification'] == true ||
          json['notification_requested'] == true,
      requireAcknowledgement:
          json['require_acknowledgement'] == true ||
          json['requireAcknowledgement'] == true,
      allowDismissal:
          json['allow_dismissal'] != false && json['allowDismissal'] != false,
      isAcknowledged:
          json['is_acknowledged'] == true || json['isAcknowledged'] == true,
      isDismissed: json['is_dismissed'] == true || json['isDismissed'] == true,
      deliveryRequestedCount: _int(
        json['delivery_requested_count'] ??
            json['delivery_metrics']?['requested'],
      ),
      deliverySuccessCount: _int(
        json['delivery_success_count'] ??
            json['delivery_metrics']?['successful'],
      ),
      deliveryFailureCount: _int(
        json['delivery_failure_count'] ?? json['delivery_metrics']?['failed'],
      ),
      readCount: _int(json['read_count']),
      acknowledgementCount: _int(json['acknowledgement_count']),
      dismissalCount: _int(json['dismissal_count']),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'title': title,
    'message': message,
    'short_message': shortMessage,
    'audience': audience,
    'status': status,
    'created_by': createdBy,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    'published_at': publishedAt?.toIso8601String(),
    'category': category,
    'priority': priority,
    'author_name': authorName,
    'is_pinned': isPinned,
    'is_read': isRead,
    'attachment_urls': attachmentUrls,
    'target_role_ids': targetRoleIds,
    'target_user_ids': targetUserIds,
    'target_prop_firm_group_ids': targetPropFirmGroupIds,
    'target_access_levels': targetAccessLevels,
    'title_i18n': titleI18n,
    'short_message_i18n': shortMessageI18n,
    'message_i18n': messageI18n,
    'start_at': startAt?.toIso8601String(),
    'expires_at': expiresAt?.toIso8601String(),
    'display_locations': displayLocations,
    'image_url': imageUrl,
    'action_label_i18n': actionLabelI18n,
    'deep_link': deepLink,
    'notification_requested': pushNotification,
    'require_acknowledgement': requireAcknowledgement,
    'allow_dismissal': allowDismissal,
    'delivery_requested_count': deliveryRequestedCount,
    'delivery_success_count': deliverySuccessCount,
    'delivery_failure_count': deliveryFailureCount,
    'read_count': readCount,
    'acknowledgement_count': acknowledgementCount,
    'dismissal_count': dismissalCount,
  };

  Map<String, Object?> toFirestore() => {
    'title': title,
    'message': message,
    'short_message': shortMessage,
    'shortMessage': shortMessage,
    'audience': audience,
    'status': status,
    'created_by': createdBy,
    'createdBy': createdBy,
    'created_at': Timestamp.fromDate(createdAt),
    'createdAt': Timestamp.fromDate(createdAt),
    'updated_at': Timestamp.fromDate(updatedAt),
    'updatedAt': Timestamp.fromDate(updatedAt),
    if (publishedAt != null) 'published_at': Timestamp.fromDate(publishedAt!),
    if (publishedAt != null) 'publishedAt': Timestamp.fromDate(publishedAt!),
    'category': category,
    'priority': priority,
    'author_name': authorName,
    'is_pinned': isPinned,
    'attachment_urls': attachmentUrls,
    'target_role_ids': targetRoleIds,
    'target_user_ids': targetUserIds,
    'target_prop_firm_group_ids': targetPropFirmGroupIds,
    'target_access_levels': targetAccessLevels,
    'title_i18n': titleI18n,
    'short_message_i18n': shortMessageI18n,
    'message_i18n': messageI18n,
    if (startAt != null) 'start_at': Timestamp.fromDate(startAt!),
    if (expiresAt != null) 'expires_at': Timestamp.fromDate(expiresAt!),
    'display_locations': displayLocations,
    'image_url': imageUrl,
    'action_label_i18n': actionLabelI18n,
    'deep_link': deepLink,
    'notification_requested': pushNotification,
    'require_acknowledgement': requireAcknowledgement,
    'allow_dismissal': allowDismissal,
  };

  Announcement copyWith({
    bool? isRead,
    bool? isAcknowledged,
    bool? isDismissed,
  }) => Announcement(
    id: id,
    title: title,
    message: message,
    shortMessage: shortMessage,
    audience: audience,
    status: status,
    createdBy: createdBy,
    createdAt: createdAt,
    updatedAt: updatedAt,
    publishedAt: publishedAt,
    category: category,
    priority: priority,
    authorName: authorName,
    isPinned: isPinned,
    isRead: isRead ?? this.isRead,
    attachmentUrls: attachmentUrls,
    targetRoleIds: targetRoleIds,
    targetUserIds: targetUserIds,
    targetPropFirmGroupIds: targetPropFirmGroupIds,
    targetAccessLevels: targetAccessLevels,
    titleI18n: titleI18n,
    shortMessageI18n: shortMessageI18n,
    messageI18n: messageI18n,
    startAt: startAt,
    expiresAt: expiresAt,
    displayLocations: displayLocations,
    imageUrl: imageUrl,
    actionLabelI18n: actionLabelI18n,
    deepLink: deepLink,
    pushNotification: pushNotification,
    requireAcknowledgement: requireAcknowledgement,
    allowDismissal: allowDismissal,
    isAcknowledged: isAcknowledged ?? this.isAcknowledged,
    isDismissed: isDismissed ?? this.isDismissed,
    deliveryRequestedCount: deliveryRequestedCount,
    deliverySuccessCount: deliverySuccessCount,
    deliveryFailureCount: deliveryFailureCount,
    readCount: readCount,
    acknowledgementCount: acknowledgementCount,
    dismissalCount: dismissalCount,
  );
}

Map<String, String> _localized(Map<String, dynamic> json, String key) {
  final direct = json['${key}_i18n'];
  if (direct is Map) {
    return {
      for (final code in const ['en', 'zh'])
        if ('${direct[code] ?? ''}'.trim().isNotEmpty)
          code: '${direct[code]}'.trim(),
    };
  }
  final english = '${json['${key}_en'] ?? json[key] ?? ''}'.trim();
  final chinese = '${json['${key}_zh'] ?? ''}'.trim();
  return {
    if (english.isNotEmpty) 'en': english,
    if (chinese.isNotEmpty) 'zh': chinese,
  };
}

List<String> _strings(Object? value) => value is List
    ? value.map((item) => '$item').where((item) => item.isNotEmpty).toList()
    : const [];

List<int> _ints(Object? value) => value is List
    ? value
          .map((item) => int.tryParse('$item') ?? 0)
          .where((item) => item > 0)
          .toList()
    : const [];

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

DateTime _date(Object? value, DateTime fallback) {
  return _nullableDate(value) ?? fallback;
}

DateTime? _nullableDate(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  if (value == null) return null;
  return DateTime.tryParse('$value');
}
