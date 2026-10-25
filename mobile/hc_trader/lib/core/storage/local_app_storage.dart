import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Stores non-sensitive mobile drafts and cached user preferences.
/// Authentication tokens remain in FlutterSecureStorage via ApiClient.
class LocalAppStorage {
  LocalAppStorage._(this._preferences);

  final SharedPreferences _preferences;

  static Future<LocalAppStorage> create() async {
    return LocalAppStorage._(await SharedPreferences.getInstance());
  }

  String _key(String userId, String name) => 'hc_mobile_${userId}_$name';

  String? readString(String name) => _preferences.getString('hc_mobile_$name');

  Future<void> writeString(String name, String value) {
    return _preferences.setString('hc_mobile_$name', value);
  }

  Map<String, dynamic>? readMap(String userId, String name) {
    final raw = _preferences.getString(_key(userId, name));
    if (raw == null || raw.isEmpty) return null;
    try {
      return Map<String, dynamic>.from(jsonDecode(raw) as Map);
    } on Object {
      return null;
    }
  }

  List<Map<String, dynamic>> readList(String userId, String name) {
    final raw = _preferences.getString(_key(userId, name));
    if (raw == null || raw.isEmpty) return const [];
    try {
      return (jsonDecode(raw) as List)
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    } on Object {
      return const [];
    }
  }

  Future<void> writeMap(
    String userId,
    String name,
    Map<String, Object?> value,
  ) {
    return _preferences.setString(_key(userId, name), jsonEncode(value));
  }

  Future<void> writeList(
    String userId,
    String name,
    List<Map<String, Object?>> value,
  ) {
    return _preferences.setString(_key(userId, name), jsonEncode(value));
  }

  Future<void> remove(String userId, String name) {
    return _preferences.remove(_key(userId, name));
  }

  /// Removes every local draft and cache scoped to one account while keeping
  /// device-wide preferences (for example the selected language) intact.
  Future<void> clearUser(String userId) async {
    final prefix = 'hc_mobile_${userId}_';
    final keys = _preferences.getKeys().where((key) => key.startsWith(prefix));
    for (final key in keys.toList(growable: false)) {
      await _preferences.remove(key);
    }
  }
}
