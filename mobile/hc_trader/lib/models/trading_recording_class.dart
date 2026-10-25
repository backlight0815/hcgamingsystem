import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';
import 'firestore_helpers.dart';

class TradingRecordingMaterialMobile {
  const TradingRecordingMaterialMobile({
    required this.title,
    this.filePath = '',
    this.originalFilename = '',
    this.mimeType = '',
    this.fileSize = 0,
    this.downloadCount = 0,
    this.titleI18n = const {},
  });

  final String title;
  final String filePath;
  final String originalFilename;
  final String mimeType;
  final int fileSize;
  final int downloadCount;
  final Map<String, String> titleI18n;

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);

  String get fileSizeLabel {
    if (fileSize >= 1048576) {
      return '${(fileSize / 1048576).toStringAsFixed(2)} MB';
    }

    if (fileSize >= 1024) {
      return '${(fileSize / 1024).toStringAsFixed(2)} KB';
    }

    return '$fileSize B';
  }

  factory TradingRecordingMaterialMobile.fromMap(Map<String, dynamic> data) {
    return TradingRecordingMaterialMobile(
      title: (data['title'] ?? data['original_filename'] ?? '').toString(),
      filePath: (data['file_path'] ?? '').toString(),
      originalFilename: (data['original_filename'] ?? '').toString(),
      mimeType: (data['mime_type'] ?? '').toString(),
      fileSize: firestoreInt(data['file_size']),
      downloadCount: firestoreInt(data['download_count']),
      titleI18n: _localizedField(data, 'title'),
    );
  }

  Map<String, Object?> toMap() {
    return {
      'title': title,
      'file_path': filePath,
      'original_filename': originalFilename,
      'mime_type': mimeType,
      'file_size': fileSize,
      'download_count': downloadCount,
      if (titleI18n.isNotEmpty) 'title_i18n': titleI18n,
    };
  }
}

class TradingRecordingClassMobile {
  const TradingRecordingClassMobile({
    required this.id,
    required this.title,
    this.description = '',
    this.videoUrl = '',
    this.downloadUrl = '',
    this.sourceName = '',
    this.status = true,
    this.approvalStatus = 'approved',
    this.materials = const [],
    this.createdAt,
    this.titleI18n = const {},
    this.descriptionI18n = const {},
  });

  final String id;
  final String title;
  final String description;
  final String videoUrl;
  final String downloadUrl;
  final String sourceName;
  final bool status;
  final String approvalStatus;
  final List<TradingRecordingMaterialMobile> materials;
  final DateTime? createdAt;
  final Map<String, String> titleI18n;
  final Map<String, String> descriptionI18n;

  String get effectiveDownloadUrl {
    return downloadUrl.isEmpty ? videoUrl : downloadUrl;
  }

  String localizedTitle(String languageCode) =>
      localizedValue(titleI18n, languageCode, fallback: title);
  String localizedDescription(String languageCode) =>
      localizedValue(descriptionI18n, languageCode, fallback: description);

  TradingRecordingClassMobile withAccess(Map<String, dynamic> access) {
    final rawMaterials = access['materials'];
    return TradingRecordingClassMobile(
      id: id,
      title: title,
      description: description,
      videoUrl: (access['video_url'] ?? videoUrl).toString(),
      downloadUrl: (access['download_url'] ?? downloadUrl).toString(),
      sourceName: sourceName,
      status: status,
      approvalStatus: approvalStatus,
      materials: rawMaterials is Iterable
          ? rawMaterials
                .whereType<Map>()
                .map(
                  (item) => TradingRecordingMaterialMobile.fromMap(
                    Map<String, dynamic>.from(item),
                  ),
                )
                .toList()
          : materials,
      createdAt: createdAt,
      titleI18n: titleI18n,
      descriptionI18n: descriptionI18n,
    );
  }

  factory TradingRecordingClassMobile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    final rawMaterials = data['materials'];
    final materials = rawMaterials is Iterable
        ? rawMaterials
              .map(
                (item) => TradingRecordingMaterialMobile.fromMap(
                  Map<String, dynamic>.from(item as Map),
                ),
              )
              .toList()
        : const <TradingRecordingMaterialMobile>[];

    return TradingRecordingClassMobile(
      id: doc.id,
      title: localizedValue(data['title'], 'en'),
      description: localizedValue(data['description'], 'en'),
      videoUrl: (data['video_url'] ?? '').toString(),
      downloadUrl: (data['download_url'] ?? '').toString(),
      sourceName: (data['source_name'] ?? '').toString(),
      status: firestoreBool(data['status'], fallback: true),
      approvalStatus: (data['approval_status'] ?? 'approved').toString(),
      materials: materials,
      createdAt: firestoreDate(data['created_at']),
      titleI18n: _localizedField(data, 'title'),
      descriptionI18n: _localizedField(data, 'description'),
    );
  }

  factory TradingRecordingClassMobile.fromJson(Map<String, dynamic> data) {
    final rawMaterials = data['materials'];
    final materials = rawMaterials is Iterable
        ? rawMaterials
              .whereType<Map>()
              .map(
                (item) => TradingRecordingMaterialMobile.fromMap(
                  Map<String, dynamic>.from(item),
                ),
              )
              .toList()
        : const <TradingRecordingMaterialMobile>[];
    return TradingRecordingClassMobile(
      id: (data['id'] ?? '').toString(),
      title: localizedValue(data['title'], 'en'),
      description: localizedValue(data['description'], 'en'),
      videoUrl: (data['video_url'] ?? '').toString(),
      downloadUrl: (data['download_url'] ?? '').toString(),
      sourceName: (data['source_name'] ?? '').toString(),
      status: firestoreBool(data['status'], fallback: true),
      approvalStatus: (data['approval_status'] ?? 'approved').toString(),
      materials: materials,
      createdAt: firestoreDate(data['created_at']),
      titleI18n: _localizedField(data, 'title'),
      descriptionI18n: _localizedField(data, 'description'),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'title': title,
      if (titleI18n.isNotEmpty) 'title_i18n': titleI18n,
      'description': description,
      if (descriptionI18n.isNotEmpty) 'description_i18n': descriptionI18n,
      'video_url': videoUrl,
      'download_url': downloadUrl,
      'source_name': sourceName,
      'status': status,
      'approval_status': approvalStatus,
      'materials': materials.map((material) => material.toMap()).toList(),
      'created_at': createdAt == null ? null : Timestamp.fromDate(createdAt!),
      'updated_at': FieldValue.serverTimestamp(),
    };
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
