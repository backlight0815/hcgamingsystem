import 'package:cloud_firestore/cloud_firestore.dart';

import 'firestore_helpers.dart';

class TradingPositionApplicationMobile {
  const TradingPositionApplicationMobile({
    required this.id,
    required this.userId,
    required this.requestedPosition,
    required this.mt5AccountId,
    this.mt5Login,
    this.requestedRoleId = 770,
    this.status = 'pending',
    this.tradeCountSnapshot = 0,
    this.strategySummary = '',
    this.tradeHistorySummary = '',
    this.personalitySummary = '',
    this.marketingPlan = '',
    this.clientSupportPlan = '',
    this.reviewNote = '',
    this.submittedAt,
    this.reviewedAt,
  });

  final String id;
  final String userId;
  final String requestedPosition;
  final String mt5AccountId;
  final int? mt5Login;
  final int requestedRoleId;
  final String status;
  final int tradeCountSnapshot;
  final String strategySummary;
  final String tradeHistorySummary;
  final String personalitySummary;
  final String marketingPlan;
  final String clientSupportPlan;
  final String reviewNote;
  final DateTime? submittedAt;
  final DateTime? reviewedAt;

  String get positionLabel {
    return requestedPosition == 'leadership' ? 'Leadership' : 'Recruiter';
  }

  String get statusLabel {
    return switch (status) {
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      _ => 'Pending Review',
    };
  }

  factory TradingPositionApplicationMobile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};

    return TradingPositionApplicationMobile(
      id: doc.id,
      userId: (data['user_id'] ?? '').toString(),
      requestedPosition: (data['requested_position'] ?? 'recruiter').toString(),
      mt5AccountId: (data['mt5_account_id'] ?? '').toString(),
      mt5Login: data['mt5_login'] == null
          ? null
          : firestoreInt(data['mt5_login']),
      requestedRoleId: firestoreInt(data['requested_role_id'], fallback: 770),
      status: (data['status'] ?? 'pending').toString(),
      tradeCountSnapshot: firestoreInt(data['trade_count_snapshot']),
      strategySummary: (data['strategy_summary'] ?? '').toString(),
      tradeHistorySummary: (data['trade_history_summary'] ?? '').toString(),
      personalitySummary: (data['personality_summary'] ?? '').toString(),
      marketingPlan: (data['marketing_plan'] ?? '').toString(),
      clientSupportPlan: (data['client_support_plan'] ?? '').toString(),
      reviewNote: (data['review_note'] ?? '').toString(),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
    );
  }

  factory TradingPositionApplicationMobile.fromJson(Map<String, dynamic> data) {
    return TradingPositionApplicationMobile(
      id: (data['id'] ?? '').toString(),
      userId: (data['user_id'] ?? '').toString(),
      requestedPosition: (data['requested_position'] ?? 'recruiter').toString(),
      mt5AccountId: (data['mt5_account_id'] ?? '').toString(),
      mt5Login: data['mt5_login'] == null
          ? null
          : firestoreInt(data['mt5_login']),
      requestedRoleId: firestoreInt(data['requested_role_id'], fallback: 770),
      status: (data['status'] ?? 'pending').toString(),
      tradeCountSnapshot: firestoreInt(data['trade_count_snapshot']),
      strategySummary: (data['strategy_summary'] ?? '').toString(),
      tradeHistorySummary: (data['trade_history_summary'] ?? '').toString(),
      personalitySummary: (data['personality_summary'] ?? '').toString(),
      marketingPlan: (data['marketing_plan'] ?? '').toString(),
      clientSupportPlan: (data['client_support_plan'] ?? '').toString(),
      reviewNote: (data['review_note'] ?? '').toString(),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
    );
  }

  Map<String, Object?> toApiJson() {
    return {
      'requested_position': requestedPosition,
      'mt5_account_id': mt5AccountId,
      'strategy_summary': strategySummary,
      'trade_history_summary': tradeHistorySummary,
      'personality_summary': personalitySummary,
      'marketing_plan': marketingPlan,
      'client_support_plan': clientSupportPlan,
    };
  }

  Map<String, Object?> toFirestore() {
    return {
      'user_id': userId,
      'requested_position': requestedPosition,
      'mt5_account_id': mt5AccountId,
      if (mt5Login != null) 'mt5_login': mt5Login,
      'requested_role_id': requestedRoleId,
      'status': status,
      'trade_count_snapshot': tradeCountSnapshot,
      'strategy_summary': strategySummary,
      'trade_history_summary': tradeHistorySummary,
      'personality_summary': personalitySummary,
      'marketing_plan': marketingPlan,
      'client_support_plan': clientSupportPlan,
      'submitted_at': Timestamp.fromDate(submittedAt ?? DateTime.now()),
      'updated_at': FieldValue.serverTimestamp(),
    };
  }

  static int roleForPosition(String position) {
    return position == 'leadership' ? 760 : 770;
  }
}

List<TradingPositionApplicationMobile> filterPositionApplicationsForMt5(
  Iterable<TradingPositionApplicationMobile> applications,
  String mt5AccountId,
) {
  final accountId = mt5AccountId.trim();
  if (accountId.isEmpty) return const [];
  return applications
      .where((application) => application.mt5AccountId == accountId)
      .toList(growable: false);
}
