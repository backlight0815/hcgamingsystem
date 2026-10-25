import 'package:cloud_firestore/cloud_firestore.dart';

class TradingAccount {
  const TradingAccount({
    required this.id,
    required this.userId,
    required this.name,
    required this.broker,
    required this.platform,
    required this.accountType,
    required this.currency,
    required this.startingBalance,
    required this.status,
    this.accountNumber = '',
    this.currentEquity,
    this.leverage = '',
    this.isPrimary = false,
    this.propFirmModelId,
    this.notes = '',
    this.journalsCount = 0,
    this.netProfitLoss = 0,
    this.netCapital = 0,
    this.calculatedBalance = 0,
  });

  final String id;
  final String userId;
  final String name;
  final String broker;
  final String accountNumber;
  final String platform;
  final String accountType;
  final String currency;
  final double startingBalance;
  final double? currentEquity;
  final String leverage;
  final String status;
  final bool isPrimary;
  final String? propFirmModelId;
  final String notes;
  final int journalsCount;
  final double netProfitLoss;
  final double netCapital;
  final double calculatedBalance;

  String get displayName => name.trim().isEmpty ? broker : name;
  String get typeLabel => accountType.replaceAll('_', ' ').toUpperCase();

  factory TradingAccount.fromJson(Map<String, dynamic> json) {
    return TradingAccount(
      id: (json['id'] ?? '').toString(),
      userId: (json['user_id'] ?? '').toString(),
      name: (json['name'] ?? 'Trading Account').toString(),
      broker: (json['broker'] ?? '').toString(),
      accountNumber: (json['account_number'] ?? '').toString(),
      platform: (json['platform'] ?? 'MT5').toString(),
      accountType: (json['account_type'] ?? 'live').toString(),
      currency: (json['currency'] ?? 'USD').toString().toUpperCase(),
      startingBalance: _double(json['starting_balance']),
      currentEquity: json['current_equity'] == null
          ? null
          : _double(json['current_equity']),
      leverage: (json['leverage'] ?? '').toString(),
      status: (json['status'] ?? 'active').toString(),
      isPrimary: json['is_primary'] == true || json['is_primary'] == 1,
      propFirmModelId: json['prop_firm_model_id']?.toString(),
      notes: (json['notes'] ?? '').toString(),
      journalsCount: _int(json['journals_count']),
      netProfitLoss: _double(json['net_profit_loss']),
      netCapital: _double(json['net_capital']),
      calculatedBalance: json['calculated_balance'] == null
          ? _double(json['starting_balance'])
          : _double(json['calculated_balance']),
    );
  }

  factory TradingAccount.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) => TradingAccount.fromJson({'id': doc.id, ...?doc.data()});

  Map<String, Object?> toApiJson() => {
    'name': name.trim(),
    'broker': broker.trim(),
    'account_number': accountNumber.trim().isEmpty
        ? null
        : accountNumber.trim(),
    'platform': platform,
    'account_type': accountType,
    'currency': currency.toUpperCase(),
    'starting_balance': startingBalance,
    'current_equity': currentEquity,
    'leverage': leverage.trim().isEmpty ? null : leverage.trim(),
    'status': status,
    'is_primary': isPrimary,
    'prop_firm_model_id': propFirmModelId,
    'notes': notes.trim().isEmpty ? null : notes.trim(),
  };

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    ...toApiJson(),
  };

  Map<String, Object?> toFirestore() => {
    'user_id': userId,
    ...toApiJson(),
    'updated_at': FieldValue.serverTimestamp(),
  };
}

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
