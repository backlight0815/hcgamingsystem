import '../../../../core/storage/local_app_storage.dart';
import '../application/average_price_form_models.dart';

class SavedAveragePriceSetup {
  const SavedAveragePriceSetup({
    required this.id,
    required this.name,
    required this.createdAt,
    required this.updatedAt,
    required this.snapshot,
  });

  final String id;
  final String name;
  final DateTime createdAt;
  final DateTime updatedAt;
  final AveragePriceSetupSnapshot snapshot;

  String get symbol => snapshot.symbol.trim();

  SavedAveragePriceSetup copyWith({
    String? id,
    String? name,
    DateTime? createdAt,
    DateTime? updatedAt,
    AveragePriceSetupSnapshot? snapshot,
  }) => SavedAveragePriceSetup(
    id: id ?? this.id,
    name: name ?? this.name,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
    snapshot: snapshot ?? this.snapshot,
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'name': name,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    'snapshot': snapshot.toJson(),
  };

  factory SavedAveragePriceSetup.fromJson(Map<String, dynamic> json) {
    final snapshot = json['snapshot'];
    return SavedAveragePriceSetup(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      createdAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0),
      updatedAt:
          DateTime.tryParse(json['updated_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0),
      snapshot: AveragePriceSetupSnapshot.fromJson(
        snapshot is Map ? Map<String, dynamic>.from(snapshot) : const {},
      ),
    );
  }
}

class AveragePriceHistoryStore {
  AveragePriceHistoryStore(this.storage, {DateTime Function()? now})
    : _now = now ?? DateTime.now;

  static const storageKey = 'average_price_history';
  static const draftKey = 'average_price_last_draft';

  final LocalAppStorage storage;
  final DateTime Function() _now;

  List<SavedAveragePriceSetup> load(String userId) {
    final items = storage
        .readList(userId, storageKey)
        .map(SavedAveragePriceSetup.fromJson)
        .where((setup) => setup.id.isNotEmpty)
        .toList();
    items.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    return items;
  }

  AveragePriceSetupSnapshot? loadDraft(String userId) {
    final data = storage.readMap(userId, draftKey);
    return data == null ? null : AveragePriceSetupSnapshot.fromJson(data);
  }

  Future<void> saveDraft(String userId, AveragePriceSetupSnapshot snapshot) =>
      storage.writeMap(userId, draftKey, snapshot.toJson());

  Future<SavedAveragePriceSetup> save({
    required String userId,
    required String name,
    required AveragePriceSetupSnapshot snapshot,
    String? existingId,
  }) async {
    final items = load(userId);
    final now = _now();
    final index = existingId == null
        ? -1
        : items.indexWhere((setup) => setup.id == existingId);
    final result = index >= 0
        ? items[index].copyWith(
            name: name.trim(),
            updatedAt: now,
            snapshot: snapshot,
          )
        : SavedAveragePriceSetup(
            id: 'average-${now.microsecondsSinceEpoch}',
            name: name.trim(),
            createdAt: now,
            updatedAt: now,
            snapshot: snapshot,
          );
    if (index >= 0) {
      items[index] = result;
    } else {
      items.insert(0, result);
    }
    await _write(userId, items);
    return result;
  }

  Future<SavedAveragePriceSetup?> duplicate(
    String userId,
    String setupId,
    String duplicateSuffix,
  ) async {
    final source = load(
      userId,
    ).where((setup) => setup.id == setupId).firstOrNull;
    if (source == null) return null;
    return save(
      userId: userId,
      name: '${source.name} $duplicateSuffix'.trim(),
      snapshot: source.snapshot,
    );
  }

  Future<void> delete(String userId, String setupId) async {
    final items = load(userId)..removeWhere((setup) => setup.id == setupId);
    await _write(userId, items);
  }

  List<SavedAveragePriceSetup> search(String userId, String query) {
    final normalized = query.trim().toLowerCase();
    if (normalized.isEmpty) return load(userId);
    return load(userId)
        .where(
          (setup) =>
              setup.name.toLowerCase().contains(normalized) ||
              setup.symbol.toLowerCase().contains(normalized),
        )
        .toList(growable: false);
  }

  Future<void> _write(String userId, List<SavedAveragePriceSetup> items) =>
      storage.writeList(
        userId,
        storageKey,
        items.map((setup) => setup.toJson()).toList(growable: false),
      );
}
