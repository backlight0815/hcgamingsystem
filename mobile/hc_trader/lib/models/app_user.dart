import '../core/security/app_roles.dart';
import '../core/security/app_role_permissions.dart';
import '../core/security/user_roles.dart';
import 'access_status.dart';

class AppUser {
  const AppUser({
    required this.uid,
    required this.email,
    this.displayName,
    this.isDemo = false,
    this.username,
    this.phoneNumber = '',
    this.avatarUrl = '',
    this.roleId = 0,
    this.roleIds = const {},
    this.roles = const [],
    this.isAdmin = false,
    this.permissionOverrides = const {},
    this.accountStatus = 'active',
    this.isSuspended = false,
    this.teamIds = const {},
    this.assignedClientIds = const {},
    this.assignedEvaluationIds = const {},
    this.organisationId = '',
    this.timezone = 'Asia/Singapore',
    this.timezoneOffsetMinutes = 480,
    this.accessStatus = AccessStatus.notSubmitted,
    this.requiresOnboarding = false,
    this.finalExamPassed = false,
    this.requiresFinalExam = false,
    this.mainMt5AccountKey = '',
    this.propFirmGroupIds = const {},
    this.subscriptionLevel = '',
  });

  final String uid;
  final String email;
  final String? displayName;
  final bool isDemo;
  final String? username;
  final String phoneNumber;
  final String avatarUrl;
  final int roleId;
  final Set<int> roleIds;
  final List<String> roles;
  final bool isAdmin;
  final Map<String, bool> permissionOverrides;
  final String accountStatus;
  final bool isSuspended;
  final Set<String> teamIds;
  final Set<String> assignedClientIds;
  final Set<String> assignedEvaluationIds;
  final String organisationId;
  final String timezone;
  final int timezoneOffsetMinutes;
  final AccessStatus accessStatus;
  final bool requiresOnboarding;
  final bool finalExamPassed;
  final bool requiresFinalExam;
  final String mainMt5AccountKey;
  final Set<String> propFirmGroupIds;
  final String subscriptionLevel;

  bool get canPublishTradingBlog =>
      isAdmin || effectiveRoleIds.any(RolePermissionMatrix.isAdminRole);

  bool get isSuperAdmin => effectiveRoleIds.contains(1);

  bool get hasClientAccess => isAdmin || accessStatus == AccessStatus.approved;

  String get roleName => AppRoles.name(roleId);

  Set<int> get effectiveRoleIds {
    final resolved = <int>{...roleIds};
    for (final role in roles) {
      final roleId = AppRoles.idFromName(role);
      if (roleId > 0) resolved.add(roleId);
    }
    if (resolved.isNotEmpty) return resolved;
    return roleId > 0 ? {roleId} : const <int>{};
  }

  List<String> get effectiveRoles {
    if (roles.isNotEmpty) return sortUserRoles(roles);
    return sortUserRoles(effectiveRoleIds.map(roleCodeForId));
  }

  bool hasRole(String role) {
    final normalized = normalizeUserRole(role);
    return normalized.isNotEmpty && effectiveRoles.contains(normalized);
  }

  bool hasAnyRole(Iterable<String> requiredRoles) => requiredRoles.any(hasRole);

  factory AppUser.fromJson(Map<String, dynamic> json) {
    final resolvedRoles = resolveUserRoles(json);
    final rawRoleAssignments =
        json['roleAssignments'] ?? json['role_assignments'];
    final hasRoleAssignments =
        rawRoleAssignments is Iterable && rawRoleAssignments.isNotEmpty;
    final legacyRoleId =
        int.tryParse(json['role_id']?.toString() ?? '') ??
        AppRoles.idFromName(json['role_name'] ?? json['role']);
    final parsedRoleIds = resolvedRoles
        .map(AppRoles.idFromName)
        .where((roleId) => roleId > 0)
        .toSet();
    final parsedRoleId = parsedRoleIds.isNotEmpty
        ? (parsedRoleIds.contains(legacyRoleId)
              ? legacyRoleId
              : parsedRoleIds.first)
        : (hasRoleAssignments ? 0 : legacyRoleId);
    final administrator = parsedRoleIds.any(RolePermissionMatrix.isAdminRole);
    return AppUser(
      uid: (json['id'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      displayName: json['name']?.toString(),
      username: json['username']?.toString(),
      phoneNumber: (json['phone_number'] ?? json['phoneNumber'] ?? '')
          .toString(),
      avatarUrl: (json['photo_url'] ?? json['avatar_url'] ?? '').toString(),
      roleId: parsedRoleId,
      roleIds: parsedRoleIds,
      roles: resolvedRoles,
      isAdmin: json['is_admin'] == true || administrator,
      permissionOverrides: _permissionOverrides(
        json['permission_overrides'] ?? json['permissionOverrides'],
      ),
      accountStatus:
          (json['account_status'] ?? json['accountStatus'] ?? 'active')
              .toString(),
      isSuspended: json['is_suspended'] == true || json['isSuspended'] == true,
      teamIds: _strings(json['team_ids'] ?? json['teamIds']),
      assignedClientIds: _strings(
        json['assigned_client_ids'] ?? json['assignedClientIds'],
      ),
      assignedEvaluationIds: _strings(
        json['assigned_evaluation_ids'] ?? json['assignedEvaluationIds'],
      ),
      organisationId: (json['organisation_id'] ?? json['organisationId'] ?? '')
          .toString(),
      timezone: (json['timezone'] ?? 'Asia/Singapore').toString(),
      timezoneOffsetMinutes:
          int.tryParse(
            (json['timezone_offset_minutes'] ??
                    json['timezoneOffsetMinutes'] ??
                    480)
                .toString(),
          ) ??
          480,
      accessStatus: administrator
          ? AccessStatus.approved
          : AccessStatus.fromStorage(
              json['access_status'] ?? json['accessStatus'],
            ),
      requiresOnboarding: json['requires_onboarding'] == true,
      finalExamPassed: json['final_exam_passed'] == true,
      requiresFinalExam: json['requires_final_exam'] == true,
      mainMt5AccountKey:
          (json['mainMt5AccountId'] ??
                  json['main_mt5_account_id'] ??
                  json['mainMt5AccountKey'] ??
                  json['main_mt5_account_key'] ??
                  '')
              .toString(),
      propFirmGroupIds: _strings(
        json['prop_firm_group_ids'] ?? json['propFirmGroupIds'],
      ),
      subscriptionLevel:
          (json['subscription_level'] ?? json['access_level'] ?? '').toString(),
    );
  }

  String get label {
    final name = displayName?.trim();
    if (name != null && name.isNotEmpty) {
      return name;
    }

    return email;
  }
}

Set<String> _strings(Object? value) {
  if (value is! Iterable) return const <String>{};
  return value
      .map((item) => item.toString().trim())
      .where((item) => item.isNotEmpty)
      .toSet();
}

Map<String, bool> _permissionOverrides(Object? value) {
  if (value is! Map) return const <String, bool>{};
  return value.map((key, item) {
    final allowed = item is bool
        ? item
        : const {
            'allow',
            'allowed',
            'true',
            '1',
            'yes',
          }.contains(item.toString().trim().toLowerCase());
    return MapEntry(key.toString(), allowed);
  });
}
