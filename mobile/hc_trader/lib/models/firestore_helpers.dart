import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/localization/localized_value.dart';

DateTime? firestoreDate(Object? value) {
  if (value is Timestamp) {
    return value.toDate();
  }

  if (value is DateTime) {
    return value;
  }

  return DateTime.tryParse(value?.toString() ?? '');
}

double firestoreDouble(Object? value, {double fallback = 0}) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

int firestoreInt(Object? value, {int fallback = 0}) {
  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

bool firestoreBool(Object? value, {bool fallback = false}) {
  if (value is bool) {
    return value;
  }

  if (value is num) {
    return value != 0;
  }

  final text = value?.toString().toLowerCase().trim();
  if (text == 'true' || text == '1' || text == 'yes') {
    return true;
  }

  if (text == 'false' || text == '0' || text == 'no') {
    return false;
  }

  return fallback;
}

List<String> firestoreStringList(Object? value) {
  if (value is Iterable) {
    return value
        .map((item) => localizedValue(item, 'en'))
        .where((item) => item.trim().isNotEmpty)
        .toList(growable: false);
  }

  final text = value?.toString().trim();
  if (text == null || text.isEmpty) {
    return const [];
  }

  return text
      .split(',')
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}

List<int> firestoreIntList(Object? value) {
  if (value is! Iterable) return const [];

  return value
      .map((item) => item is num ? item.toInt() : int.tryParse('$item'))
      .whereType<int>()
      .toList(growable: false);
}

Map<String, String> firestoreStringMap(Object? value) {
  if (value is! Map) return const {};

  return Map<String, String>.unmodifiable(
    value.map((key, item) => MapEntry(key.toString(), item?.toString() ?? ''))
      ..removeWhere((key, item) => key.trim().isEmpty || item.trim().isEmpty),
  );
}
