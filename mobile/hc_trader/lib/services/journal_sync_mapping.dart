import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/foundation.dart';

import '../models/trading_journal_entry.dart';

/// Transport values come from stored fields, not UI defaults (which turn null
/// prices into zero). Never send a website account ID as a Firestore account ID.
Map<String, dynamic> journalSyncFields(Map<String, dynamic> data) {
  final allowed = TradingJournalEntry.fromJson({}).toApiJson().keys.toSet()
    ..remove('trading_account_id')
    ..add('gross_profit');
  return {
    for (final key in allowed)
      if (data.containsKey(key)) key: _value(key, data[key]),
    if (data.containsKey('trading_account_id'))
      'mobile_trading_account_id': data['trading_account_id'],
  };
}

Object? _value(String key, Object? value) {
  if (value is Timestamp) return value.toDate().toIso8601String();
  if (value is DateTime) return value.toIso8601String();
  if (value is String &&
      const {
        'open_date',
        'close_date',
        'trading_plan_checked_at',
      }.contains(key)) {
    return DateTime.parse(value).toLocal().toIso8601String();
  }
  return value;
}

bool journalSyncEqual(Object? left, Object? right) {
  if (left is Map && right is Map) {
    return left.length == right.length &&
        left.keys.every(
          (key) =>
              right.containsKey(key) && journalSyncEqual(left[key], right[key]),
        );
  }
  if (left is List && right is List) {
    return listEquals(left, right);
  }
  if (left is num || right is num) {
    return num.tryParse('$left') == num.tryParse('$right');
  }
  return left == right;
}

Map<String, dynamic> journalRemoteFields(Map<String, dynamic> record) {
  final fields = Map<String, dynamic>.from(record)
    ..remove('trading_account_id');
  if (record.containsKey('mobile_trading_account_id')) {
    fields['trading_account_id'] = record['mobile_trading_account_id'];
  }
  final allowed = journalSyncFields(fields).keys.toSet()
    ..remove('mobile_trading_account_id');
  return {
    for (final key in allowed)
      key:
          const {
                'open_date',
                'close_date',
                'trading_plan_checked_at',
              }.contains(key) &&
              fields[key] != null
          ? Timestamp.fromDate(DateTime.parse('${fields[key]}'))
          : fields[key],
    if (fields.containsKey('trading_account_id'))
      'trading_account_id': fields['trading_account_id'],
  };
}
