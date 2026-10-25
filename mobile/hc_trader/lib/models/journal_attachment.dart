class JournalAttachment {
  const JournalAttachment({
    required this.id,
    required this.journalId,
    required this.originalName,
    required this.url,
    this.mimeType = '',
    this.sizeBytes = 0,
    this.storagePath = '',
  });

  final String id;
  final String journalId;
  final String originalName;
  final String url;
  final String mimeType;
  final int sizeBytes;
  final String storagePath;

  bool get isImage => mimeType.startsWith('image/');

  factory JournalAttachment.fromJson(Map<String, dynamic> json) {
    return JournalAttachment(
      id: (json['id'] ?? '').toString(),
      journalId: (json['trading_journal_id'] ?? '').toString(),
      originalName: (json['original_name'] ?? 'Attachment').toString(),
      url: (json['url'] ?? '').toString(),
      mimeType: (json['mime_type'] ?? '').toString(),
      sizeBytes: json['size_bytes'] is num
          ? (json['size_bytes'] as num).toInt()
          : int.tryParse('${json['size_bytes']}') ?? 0,
      storagePath: (json['storage_path'] ?? json['path'] ?? '').toString(),
    );
  }

  Map<String, Object?> toMap() => {
    'id': id,
    'trading_journal_id': journalId,
    'original_name': originalName,
    'url': url,
    'mime_type': mimeType,
    'size_bytes': sizeBytes,
    'storage_path': storagePath,
  };
}
