import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';

class TradingResourceItem {
  const TradingResourceItem({
    required this.id,
    required this.type,
    required this.title,
    this.description = '',
    this.url = '',
    this.status = true,
    this.createdAt,
    this.titleI18n = const {},
    this.descriptionI18n = const {},
  });

  final String id;
  final String type;
  final String title;
  final String description;
  final String url;
  final bool status;
  final DateTime? createdAt;
  final Map<String, String> titleI18n;
  final Map<String, String> descriptionI18n;

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);
  String localizedDescription(String languageCode) =>
      localizedValue(descriptionI18n, languageCode, fallback: description);

  factory TradingResourceItem.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc, {
    String fallbackType = 'resource',
  }) {
    final data = doc.data() ?? const <String, dynamic>{};

    return TradingResourceItem(
      id: doc.id,
      type: (data['type'] ?? fallbackType).toString(),
      title: localizedValue(data['title'], 'en'),
      description: localizedValue(data['description'], 'en'),
      url:
          (data['video_url'] ??
                  data['download_url'] ??
                  data['file_path'] ??
                  data['url'] ??
                  '')
              .toString(),
      status: firestoreBool(data['status'], fallback: true),
      createdAt: firestoreDate(data['created_at']),
      titleI18n: _localizedField(data, 'title'),
      descriptionI18n: _localizedField(data, 'description'),
    );
  }

  factory TradingResourceItem.fromJson(
    Map<String, dynamic> data, {
    String fallbackType = 'resource',
  }) {
    return TradingResourceItem(
      id: (data['id'] ?? '').toString(),
      type: (data['type'] ?? fallbackType).toString(),
      title: localizedValue(data['title'], 'en'),
      description: localizedValue(data['description'], 'en'),
      url: (data['url'] ?? '').toString(),
      status: firestoreBool(data['status'], fallback: true),
      createdAt: firestoreDate(data['created_at']),
      titleI18n: _localizedField(data, 'title'),
      descriptionI18n: _localizedField(data, 'description'),
    );
  }
}

Map<String, String> _localizedField(Map<String, dynamic> data, String key) {
  final map = {
    ...localizedTextMap(data['${key}_i18n']),
    ...localizedTextMap(data[key]),
  };
  final english = data['${key}_en']?.toString().trim() ?? '';
  final chinese = data['${key}_zh']?.toString().trim() ?? '';
  if (english.isNotEmpty) map['en'] = english;
  if (chinese.isNotEmpty) map['zh'] = chinese;
  return map;
}
