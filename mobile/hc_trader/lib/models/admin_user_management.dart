import 'firestore_helpers.dart';

enum AdminUserSort {
  newest('newest', 'Newest registration'),
  oldest('oldest', 'Oldest registration'),
  recentlyActive('recently_active', 'Recently active'),
  name('name', 'Name'),
  role('role', 'Role'),
  verification('verification', 'Verification status'),
  access('access', 'Access status');

  const AdminUserSort(this.value, this.label);

  final String value;
  final String label;
}

class AdminUserFilters {
  const AdminUserFilters({
    this.search = '',
    this.roleId,
    this.accountStatus,
    this.accessStatus,
    this.verificationStatus,
    this.mt5Status,
    this.registeredAfter,
    this.registeredBefore,
    this.hasPendingApplication,
    this.hasUnresolvedTicket,
    this.assignedProviderId,
  });

  final String search;
  final int? roleId;
  final String? accountStatus;
  final String? accessStatus;
  final String? verificationStatus;
  final String? mt5Status;
  final DateTime? registeredAfter;
  final DateTime? registeredBefore;
  final bool? hasPendingApplication;
  final bool? hasUnresolvedTicket;
  final String? assignedProviderId;

  bool get isEmpty => activeCount == 0;

  int get activeCount => <Object?>[
    if (search.trim().isNotEmpty) search,
    if (roleId != null) roleId,
    if (accountStatus != null) accountStatus,
    if (accessStatus != null) accessStatus,
    if (verificationStatus != null) verificationStatus,
    if (mt5Status != null) mt5Status,
    if (registeredAfter != null) registeredAfter,
    if (registeredBefore != null) registeredBefore,
    if (hasPendingApplication != null) hasPendingApplication,
    if (hasUnresolvedTicket != null) hasUnresolvedTicket,
    if (assignedProviderId != null) assignedProviderId,
  ].length;

  String get cacheKey => <Object?>[
    normalizedSearch,
    roleId,
    accountStatus,
    accessStatus,
    verificationStatus,
    mt5Status,
    registeredAfter?.millisecondsSinceEpoch,
    registeredBefore?.millisecondsSinceEpoch,
    hasPendingApplication,
    hasUnresolvedTicket,
    assignedProviderId,
  ].join('|');

  String get normalizedSearch => search.trim().toLowerCase();

  AdminUserFilters copyWith({
    String? search,
    int? roleId,
    bool clearRole = false,
    String? accountStatus,
    bool clearAccountStatus = false,
    String? accessStatus,
    bool clearAccessStatus = false,
    String? verificationStatus,
    bool clearVerificationStatus = false,
    String? mt5Status,
    bool clearMt5Status = false,
    DateTime? registeredAfter,
    bool clearRegisteredAfter = false,
    DateTime? registeredBefore,
    bool clearRegisteredBefore = false,
    bool? hasPendingApplication,
    bool clearPendingApplication = false,
    bool? hasUnresolvedTicket,
    bool clearUnresolvedTicket = false,
    String? assignedProviderId,
    bool clearAssignedProvider = false,
  }) => AdminUserFilters(
    search: search ?? this.search,
    roleId: clearRole ? null : roleId ?? this.roleId,
    accountStatus: clearAccountStatus
        ? null
        : accountStatus ?? this.accountStatus,
    accessStatus: clearAccessStatus ? null : accessStatus ?? this.accessStatus,
    verificationStatus: clearVerificationStatus
        ? null
        : verificationStatus ?? this.verificationStatus,
    mt5Status: clearMt5Status ? null : mt5Status ?? this.mt5Status,
    registeredAfter: clearRegisteredAfter
        ? null
        : registeredAfter ?? this.registeredAfter,
    registeredBefore: clearRegisteredBefore
        ? null
        : registeredBefore ?? this.registeredBefore,
    hasPendingApplication: clearPendingApplication
        ? null
        : hasPendingApplication ?? this.hasPendingApplication,
    hasUnresolvedTicket: clearUnresolvedTicket
        ? null
        : hasUnresolvedTicket ?? this.hasUnresolvedTicket,
    assignedProviderId: clearAssignedProvider
        ? null
        : assignedProviderId ?? this.assignedProviderId,
  );
}

class AdminUserRecord {
  const AdminUserRecord({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.avatarUrl,
    required this.roleId,
    required this.roleIds,
    required this.accountStatus,
    required this.accessStatus,
    required this.verificationStatus,
    required this.mt5Status,
    required this.emailVerified,
    required this.referralCode,
    required this.uplineName,
    required this.assignedProviderId,
    required this.assignedProviderName,
    required this.mainMt5Login,
    required this.createdAt,
    required this.lastActiveAt,
    required this.preferredLanguage,
    required this.hasPendingApplication,
    required this.hasUnresolvedTicket,
    required this.raw,
  });

  factory AdminUserRecord.fromMap(Map<String, dynamic> data) {
    final status = '${data['account_status'] ?? data['accountStatus'] ?? ''}'
        .trim()
        .toLowerCase();
    final numericStatus = firestoreInt(data['status'], fallback: 1);
    final suspended = firestoreBool(
      data['is_suspended'] ?? data['isSuspended'],
    );
    final accountStatus = suspended || numericStatus == 0
        ? 'suspended'
        : status.isEmpty
        ? 'active'
        : status;
    final access =
        '${data['access_status'] ?? data['accessStatus'] ?? 'notSubmitted'}';
    final verification =
        '${data['verification_status'] ?? data['broker_verification_status'] ?? data['onboarding_status'] ?? 'not_submitted'}';
    final mt5 =
        '${data['mt5_status'] ?? data['main_mt5_status'] ?? (data['mainMt5AccountId'] == null && data['main_mt5_account_id'] == null ? 'none' : 'connected')}';
    return AdminUserRecord(
      id: '${data['id'] ?? data['uid'] ?? data['user_id'] ?? ''}',
      name: '${data['name'] ?? data['display_name'] ?? data['username'] ?? ''}'
          .trim(),
      email: '${data['email'] ?? ''}'.trim(),
      phone: '${data['phone_number'] ?? data['phoneNumber'] ?? ''}'.trim(),
      avatarUrl:
          '${data['photo_url'] ?? data['avatar_url'] ?? data['avatarUrl'] ?? ''}'
              .trim(),
      roleId: firestoreInt(data['role_id'] ?? data['roleId']),
      roleIds: firestoreIntList(data['role_ids'] ?? data['roleIds']).toSet(),
      accountStatus: accountStatus,
      accessStatus: access,
      verificationStatus: verification,
      mt5Status: mt5,
      emailVerified: firestoreBool(
        data['email_verified'] ?? data['emailVerified'],
      ),
      referralCode: '${data['referral_code'] ?? data['referralCode'] ?? ''}'
          .trim(),
      uplineName:
          '${data['upline_name'] ?? data['referred_by_name'] ?? data['recruitedByName'] ?? ''}'
              .trim(),
      assignedProviderId:
          '${data['assigned_provider_id'] ?? data['signal_provider_id'] ?? ''}'
              .trim(),
      assignedProviderName:
          '${data['assigned_provider_name'] ?? data['signal_provider_name'] ?? ''}'
              .trim(),
      mainMt5Login:
          '${data['main_mt5_login'] ?? data['mt5_login'] ?? data['mainMt5Login'] ?? ''}'
              .trim(),
      createdAt: firestoreDate(
        data['created_at'] ?? data['createdAt'] ?? data['registered_at'],
      ),
      lastActiveAt: firestoreDate(
        data['last_active_at'] ?? data['lastActiveAt'] ?? data['last_login_at'],
      ),
      preferredLanguage:
          '${data['preferred_language'] ?? data['language'] ?? 'en'}',
      hasPendingApplication: firestoreBool(
        data['has_pending_application'] ?? data['hasPendingApplication'],
      ),
      hasUnresolvedTicket: firestoreBool(
        data['has_unresolved_ticket'] ?? data['hasUnresolvedTicket'],
      ),
      raw: Map.unmodifiable(data),
    );
  }

  final String id;
  final String name;
  final String email;
  final String phone;
  final String avatarUrl;
  final int roleId;
  final Set<int> roleIds;
  final String accountStatus;
  final String accessStatus;
  final String verificationStatus;
  final String mt5Status;
  final bool emailVerified;
  final String referralCode;
  final String uplineName;
  final String assignedProviderId;
  final String assignedProviderName;
  final String mainMt5Login;
  final DateTime? createdAt;
  final DateTime? lastActiveAt;
  final String preferredLanguage;
  final bool hasPendingApplication;
  final bool hasUnresolvedTicket;
  final Map<String, dynamic> raw;

  String get label => name.isNotEmpty
      ? name
      : email.isNotEmpty
      ? email
      : id;
  bool get isSuspended => const {
    'suspended',
    'disabled',
    'inactive',
    'revoked',
    'deleted',
  }.contains(accountStatus.replaceAll(RegExp(r'[-_\s]'), '').toLowerCase());
}

class AdminUserMetrics {
  const AdminUserMetrics({
    this.total = 0,
    this.active = 0,
    this.pendingAccess = 0,
    this.pendingVerification = 0,
    this.suspended = 0,
    this.recentlyRegistered = 0,
  });

  final int total;
  final int active;
  final int pendingAccess;
  final int pendingVerification;
  final int suspended;
  final int recentlyRegistered;
}

class AdminUserPage {
  const AdminUserPage({
    required this.users,
    required this.roles,
    required this.metrics,
    required this.page,
    required this.pageSize,
    required this.total,
    required this.hasNext,
  });

  final List<AdminUserRecord> users;
  final List<Map<String, dynamic>> roles;
  final AdminUserMetrics metrics;
  final int page;
  final int pageSize;
  final int total;
  final bool hasNext;
}

class AdminUserDetail {
  const AdminUserDetail({
    required this.user,
    this.accessApplications = const [],
    this.mt5Accounts = const [],
    this.activities = const [],
    this.supportTickets = const [],
    this.appointments = const [],
    this.auditLogs = const [],
    this.notes = const [],
    this.referrals = const [],
  });

  final AdminUserRecord user;
  final List<Map<String, dynamic>> accessApplications;
  final List<Map<String, dynamic>> mt5Accounts;
  final List<Map<String, dynamic>> activities;
  final List<Map<String, dynamic>> supportTickets;
  final List<Map<String, dynamic>> appointments;
  final List<Map<String, dynamic>> auditLogs;
  final List<Map<String, dynamic>> notes;
  final List<Map<String, dynamic>> referrals;
}
