import 'package:cloud_firestore/cloud_firestore.dart';

class CapitalTransaction {
  const CapitalTransaction({
    required this.id,
    required this.userId,
    required this.depositDate,
    required this.amount,
    required this.type,
    this.tradingAccountId,
    this.notes = '',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final DateTime depositDate;
  final double amount;
  final int type;
  final String? tradingAccountId;
  final String notes;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  bool get isDeposit => type == 1;
  bool get isWithdrawal => type == 2;
  double get absoluteAmount => amount.abs();

  String get typeLabel => switch (type) {
    1 => 'Deposit',
    2 => 'Withdrawal',
    3 => 'Credit',
    4 => 'Balance adjustment',
    _ => 'Capital transaction',
  };

  CapitalTransaction copyWith({
    String? id,
    String? userId,
    DateTime? depositDate,
    double? amount,
    int? type,
    String? tradingAccountId,
    String? notes,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return CapitalTransaction(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      depositDate: depositDate ?? this.depositDate,
      amount: amount ?? this.amount,
      type: type ?? this.type,
      tradingAccountId: tradingAccountId ?? this.tradingAccountId,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  factory CapitalTransaction.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};
    final type = _asInt(data['type'], fallback: 1);
    final amount = _asDouble(data['amount']);

    return CapitalTransaction(
      id: doc.id,
      userId: (data['user_id'] ?? '').toString(),
      depositDate: _asDate(data['deposit_date']) ?? DateTime.now(),
      amount: type == 1
          ? amount.abs()
          : type == 2
          ? -amount.abs()
          : amount,
      type: type,
      tradingAccountId: data['trading_account_id']?.toString(),
      notes: (data['notes'] ?? '').toString(),
      createdAt: _asDate(data['created_at']),
      updatedAt: _asDate(data['updated_at']),
    );
  }

  factory CapitalTransaction.fromJson(Map<String, dynamic> data) {
    final type = _asInt(data['type'], fallback: 1);
    final amount = _asDouble(data['amount']);
    return CapitalTransaction(
      id: (data['id'] ?? '').toString(),
      userId: (data['user_id'] ?? '').toString(),
      depositDate: _asDate(data['deposit_date']) ?? DateTime.now(),
      amount: type == 1
          ? amount.abs()
          : type == 2
          ? -amount.abs()
          : amount,
      type: type,
      tradingAccountId: data['trading_account_id']?.toString(),
      notes: (data['notes'] ?? '').toString(),
      createdAt: _asDate(data['created_at']),
      updatedAt: _asDate(data['updated_at']),
    );
  }

  Map<String, Object?> toApiJson({bool update = false}) {
    return {
      if (!update) 'type': type,
      'trading_account_id': tradingAccountId,
      'depositAmount': absoluteAmount,
      'deposit_date': depositDate.toIso8601String(),
      'notes': notes,
    };
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'deposit_date': depositDate.toIso8601String(),
    'amount': amount,
    'type': type,
    'trading_account_id': tradingAccountId,
    'notes': notes,
    'created_at': createdAt?.toIso8601String(),
    'updated_at': updatedAt?.toIso8601String(),
  };

  Map<String, Object?> toFirestore({bool includeTimestamps = true}) {
    return {
      'user_id': userId,
      'type': type,
      'trading_account_id': tradingAccountId,
      'deposit_date': Timestamp.fromDate(depositDate),
      'amount': type == 1
          ? amount.abs()
          : type == 2
          ? -amount.abs()
          : amount,
      'notes': notes,
      if (includeTimestamps) 'updated_at': FieldValue.serverTimestamp(),
    };
  }
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '') ?? 0;
}

int _asInt(Object? value, {required int fallback}) {
  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

DateTime? _asDate(Object? value) {
  if (value is Timestamp) {
    return value.toDate();
  }

  if (value is DateTime) {
    return value;
  }

  return DateTime.tryParse(value?.toString() ?? '');
}
