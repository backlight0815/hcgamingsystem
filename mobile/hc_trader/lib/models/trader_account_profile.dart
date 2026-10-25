import 'package:cloud_firestore/cloud_firestore.dart';

import 'firestore_helpers.dart';

enum BrokerVerificationStatus {
  notSubmitted('not_submitted'),
  pending('pending'),
  approved('approved'),
  rejectedResubmittable('rejected_resubmittable'),
  rejectedNewApplication('rejected_new_application'),
  rejectedFinal('rejected_final');

  const BrokerVerificationStatus(this.storageValue);

  final String storageValue;

  static BrokerVerificationStatus fromStorage(Object? value) {
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    return switch (normalized) {
      'pending' ||
      'pending_review' ||
      'pendingreview' => BrokerVerificationStatus.pending,
      'approved' || 'verified' => BrokerVerificationStatus.approved,
      'rejected_resubmittable' ||
      'resubmission_required' => BrokerVerificationStatus.rejectedResubmittable,
      'rejected_new_application' =>
        BrokerVerificationStatus.rejectedNewApplication,
      'rejected' || 'rejected_final' => BrokerVerificationStatus.rejectedFinal,
      _ => BrokerVerificationStatus.notSubmitted,
    };
  }
}

class TraderAccountProfile {
  const TraderAccountProfile({
    required this.id,
    required this.userId,
    this.status = 'not_submitted',
    this.isClient = false,
    this.hasDeposit = false,
    this.depositAmount = 0,
    this.discordUsername = '',
    this.brokerUid = '',
    this.brokerEmail = '',
    this.traderNote = '',
    this.rejectionReason = '',
    this.rejectionNote = '',
    this.allowResubmission = true,
    this.submittedAt,
    this.reviewedAt,
  });

  final String id;
  final String userId;
  final String status;
  final bool isClient;
  final bool hasDeposit;
  final double depositAmount;
  final String discordUsername;
  final String brokerUid;
  final String brokerEmail;
  final String traderNote;
  final String rejectionReason;
  final String rejectionNote;
  final bool allowResubmission;
  final DateTime? submittedAt;
  final DateTime? reviewedAt;

  String get statusLabel {
    return switch (verificationStatus) {
      BrokerVerificationStatus.approved => 'Approved',
      BrokerVerificationStatus.rejectedResubmittable => 'Resubmission required',
      BrokerVerificationStatus.rejectedNewApplication =>
        'New application required',
      BrokerVerificationStatus.rejectedFinal => 'Closed',
      BrokerVerificationStatus.pending => 'Awaiting broker verification review',
      BrokerVerificationStatus.notSubmitted => 'Not Submitted',
    };
  }

  BrokerVerificationStatus get verificationStatus =>
      BrokerVerificationStatus.fromStorage(status);

  bool get isApproved =>
      verificationStatus == BrokerVerificationStatus.approved;
  bool get isPending => verificationStatus == BrokerVerificationStatus.pending;
  bool get canSubmit => switch (verificationStatus) {
    BrokerVerificationStatus.notSubmitted ||
    BrokerVerificationStatus.rejectedResubmittable ||
    BrokerVerificationStatus.rejectedNewApplication => allowResubmission,
    BrokerVerificationStatus.approved => true,
    _ => false,
  };

  TraderAccountProfile copyWith({
    String? id,
    String? userId,
    String? status,
    bool? isClient,
    bool? hasDeposit,
    double? depositAmount,
    String? discordUsername,
    String? brokerUid,
    String? brokerEmail,
    String? traderNote,
    String? rejectionReason,
    String? rejectionNote,
    bool? allowResubmission,
    DateTime? submittedAt,
    DateTime? reviewedAt,
  }) {
    return TraderAccountProfile(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      status: status ?? this.status,
      isClient: isClient ?? this.isClient,
      hasDeposit: hasDeposit ?? this.hasDeposit,
      depositAmount: depositAmount ?? this.depositAmount,
      discordUsername: discordUsername ?? this.discordUsername,
      brokerUid: brokerUid ?? this.brokerUid,
      brokerEmail: brokerEmail ?? this.brokerEmail,
      traderNote: traderNote ?? this.traderNote,
      rejectionReason: rejectionReason ?? this.rejectionReason,
      rejectionNote: rejectionNote ?? this.rejectionNote,
      allowResubmission: allowResubmission ?? this.allowResubmission,
      submittedAt: submittedAt ?? this.submittedAt,
      reviewedAt: reviewedAt ?? this.reviewedAt,
    );
  }

  factory TraderAccountProfile.empty(String userId) {
    return TraderAccountProfile(id: 'profile', userId: userId);
  }

  factory TraderAccountProfile.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};

    return TraderAccountProfile(
      id: doc.id,
      userId: (data['user_id'] ?? '').toString(),
      status: BrokerVerificationStatus.fromStorage(data['status']).storageValue,
      isClient: firestoreBool(data['is_client']),
      hasDeposit: firestoreBool(data['has_deposit']),
      depositAmount: firestoreDouble(data['deposit_amount']),
      discordUsername: (data['discord_username'] ?? '').toString(),
      brokerUid: (data['broker_uid'] ?? '').toString(),
      brokerEmail: (data['broker_email'] ?? '').toString(),
      traderNote: (data['trader_note'] ?? '').toString(),
      rejectionReason: (data['rejection_reason'] ?? '').toString(),
      rejectionNote: (data['rejection_note'] ?? '').toString(),
      allowResubmission: firestoreBool(
        data['allow_resubmission'],
        fallback: true,
      ),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
    );
  }

  factory TraderAccountProfile.fromJson(Map<String, dynamic> data) {
    return TraderAccountProfile(
      id: (data['id'] ?? 'profile').toString(),
      userId: (data['user_id'] ?? '').toString(),
      status: BrokerVerificationStatus.fromStorage(data['status']).storageValue,
      isClient: firestoreBool(data['is_client']),
      hasDeposit: firestoreBool(data['has_deposit']),
      depositAmount: firestoreDouble(data['deposit_amount']),
      discordUsername: (data['discord_username'] ?? '').toString(),
      brokerUid: (data['broker_uid'] ?? '').toString(),
      brokerEmail: (data['broker_email'] ?? '').toString(),
      traderNote: (data['trader_note'] ?? '').toString(),
      rejectionReason: (data['rejection_reason'] ?? '').toString(),
      rejectionNote: (data['rejection_note'] ?? '').toString(),
      allowResubmission: firestoreBool(
        data['allow_resubmission'],
        fallback: true,
      ),
      submittedAt: firestoreDate(data['submitted_at']),
      reviewedAt: firestoreDate(data['reviewed_at']),
    );
  }

  Map<String, Object?> toApiJson() {
    return {
      'is_client': isClient,
      'has_deposit': hasDeposit,
      'deposit_amount': depositAmount,
      'discord_username': discordUsername,
      'broker_uid': brokerUid,
      'broker_email': brokerEmail,
      'trader_note': traderNote,
    };
  }

  Map<String, Object?> toFirestore() {
    return {
      'user_id': userId,
      'status': status == 'not_submitted' ? 'pending' : status,
      'is_client': isClient,
      'has_deposit': hasDeposit,
      'deposit_amount': depositAmount,
      'discord_username': discordUsername,
      'broker_uid': brokerUid,
      'broker_email': brokerEmail,
      'trader_note': traderNote,
      'allow_resubmission': allowResubmission,
      'submitted_at': Timestamp.fromDate(submittedAt ?? DateTime.now()),
      'updated_at': FieldValue.serverTimestamp(),
    };
  }
}
