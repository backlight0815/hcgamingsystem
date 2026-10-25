const rolePriority = <String, int>{
  'super_admin': 1,
  'sub_admin': 2,
  'founder': 3,
  'leader': 4,
  'financial_department': 5,
  'market_analyst': 6,
  'signal_provider_management': 7,
  'senior_signal_provider': 8,
  'signal_provider': 9,
  'recruiter': 10,
  'trader': 11,
  'lead_client': 12,
  'admin_assistant': 13,
  'evaluator': 14,
};

const _roleCodesById = <int, String>{
  1: 'super_admin',
  2: 'sub_admin',
  3: 'founder',
  201: 'signal_provider',
  202: 'senior_signal_provider',
  501: 'market_analyst',
  502: 'signal_provider_management',
  700: 'lead_client',
  750: 'trader',
  760: 'leader',
  770: 'recruiter',
  780: 'admin_assistant',
  790: 'financial_department',
  800: 'evaluator',
};

String roleCodeForId(int roleId) =>
    _roleCodesById[roleId] ?? (roleId > 0 ? 'role_$roleId' : '');

const _englishRoleLabels = <String, String>{
  'super_admin': 'Super Admin',
  'sub_admin': 'Sub Admin',
  'founder': 'Founder',
  'market_analyst': 'Market Analyst',
  'signal_provider': 'Signal Provider',
  'senior_signal_provider': 'Senior Signal Provider',
  'signal_provider_management': 'Signal Provider Management',
  'financial_department': 'Financial Department',
  'financial_consultant': 'Financial Consultant',
  'leader': 'Leader',
  'recruiter': 'Recruiter',
  'trader': 'Trader',
  'lead_client': 'Lead Client',
  'admin_assistant': 'Admin Assistant',
  'evaluator': 'Evaluator',
};

const _chineseRoleLabels = <String, String>{
  'super_admin': '\u8d85\u7ea7\u7ba1\u7406\u5458',
  'sub_admin': '\u5b50\u7ba1\u7406\u5458',
  'founder': '\u521b\u529e\u4eba',
  'market_analyst': '\u5e02\u573a\u5206\u6790\u5e08',
  'signal_provider': '\u4fe1\u53f7\u63d0\u4f9b\u8005',
  'senior_signal_provider': '\u9ad8\u7ea7\u4fe1\u53f7\u63d0\u4f9b\u8005',
  'signal_provider_management': '\u4fe1\u53f7\u63d0\u4f9b\u8005\u7ba1\u7406',
  'financial_department': '\u8d22\u52a1\u90e8\u95e8',
  'financial_consultant': '\u8d22\u52a1\u987e\u95ee',
  'leader': '\u56e2\u961f\u9886\u5bfc',
  'recruiter': '\u62db\u52df\u5458',
  'trader': '\u4ea4\u6613\u5458',
  'lead_client': '\u6f5c\u5728\u5ba2\u6237',
  'admin_assistant': '\u884c\u653f\u52a9\u7406',
  'evaluator': '\u8bc4\u4f30\u5458',
};

/// Converts database and legacy role spellings to one stable role code.
String normalizeUserRole(Object? role) {
  final raw = role?.toString().trim() ?? '';
  if (raw.isEmpty) return '';

  final numericId = int.tryParse(raw);
  if (numericId != null) {
    return roleCodeForId(numericId);
  }

  final normalized = raw
      .toLowerCase()
      .replaceAll(RegExp(r'[\s-]+'), '_')
      .replaceAll(RegExp(r'_+'), '_')
      .replaceAll(RegExp(r'^_|_$'), '');
  return switch (normalized) {
    'superadministrator' || 'super_administrator' => 'super_admin',
    'subadministrator' ||
    'sub_administrator' ||
    'administrator' ||
    'admin' => 'sub_admin',
    'marketanalyst' => 'market_analyst',
    'signalprovider' => 'signal_provider',
    'senior_signalprovider' ||
    'seniorsignalprovider' => 'senior_signal_provider',
    'signalprovider_management' ||
    'signalprovidermanagement' => 'signal_provider_management',
    'financialdepartment' => 'financial_department',
    'trading_leadership' || 'tradingleader' => 'leader',
    'trading_recruiter' || 'tradingrecruiter' => 'recruiter',
    'potential_client' || 'potentialclient' || 'client' => 'lead_client',
    'assistant' => 'admin_assistant',
    _ => normalized,
  };
}

/// Resolves the current roles without granting legacy roles alongside a newer
/// authoritative collection. Status-based assignments take precedence when
/// present so suspended, revoked, and inactive assignments cannot leak into UI
/// or client-side permission checks.
List<String> resolveUserRoles(Map<String, dynamic> data) {
  final rawAssignments = data['roleAssignments'] ?? data['role_assignments'];
  if (rawAssignments is Iterable && rawAssignments.isNotEmpty) {
    final assigned = <String>{};
    for (final value in rawAssignments) {
      if (value is! Map) continue;
      final assignment = Map<Object?, Object?>.from(value);
      final status = (assignment['status'] ?? '')
          .toString()
          .trim()
          .toLowerCase();
      final active = _isTrue(assignment['isActive'] ?? assignment['is_active']);
      if (status != 'approved' || !active) continue;
      _addRole(
        assigned,
        assignment['role'] ??
            assignment['role_name'] ??
            assignment['roleId'] ??
            assignment['role_id'],
      );
    }
    return sortUserRoles(assigned);
  }

  final current = _rolesFromCollection(data['roles']);
  if (current.isNotEmpty) return sortUserRoles(current);

  // Existing HC Trader profiles store numeric multi-role IDs. Treat this as
  // the same collection shape while retaining support for the requested
  // string-based `roles` field.
  final currentIds = _rolesFromCollection(data['role_ids'] ?? data['roleIds']);
  if (currentIds.isNotEmpty) return sortUserRoles(currentIds);

  final legacyRole =
      data['role'] ?? data['role_name'] ?? data['role_id'] ?? data['roleId'];
  final normalizedLegacy = normalizeUserRole(legacyRole);
  return normalizedLegacy.isEmpty ? const <String>[] : [normalizedLegacy];
}

List<String> sortUserRoles(Iterable<String> roles) {
  final result = roles
      .map(normalizeUserRole)
      .where((role) => role.isNotEmpty)
      .toSet()
      .toList();
  result.sort((left, right) {
    final priority = (rolePriority[left] ?? 999).compareTo(
      rolePriority[right] ?? 999,
    );
    return priority == 0 ? left.compareTo(right) : priority;
  });
  return result;
}

String getRoleDisplayName(String role, String languageCode) {
  final normalized = normalizeUserRole(role);
  final labels = languageCode.toLowerCase().startsWith('zh')
      ? _chineseRoleLabels
      : _englishRoleLabels;
  return labels[normalized] ?? formatUnknownRole(normalized);
}

String formatUnknownRole(String role) {
  final normalized = normalizeUserRole(role);
  if (normalized.isEmpty) return '';
  return normalized
      .split('_')
      .where((part) => part.isNotEmpty)
      .map(
        (part) => part.length == 1
            ? part.toUpperCase()
            : '${part[0].toUpperCase()}${part.substring(1)}',
      )
      .join(' ');
}

Set<String> _rolesFromCollection(Object? value) {
  if (value is! Iterable || value is String) return <String>{};
  final result = <String>{};
  for (final role in value) {
    _addRole(result, role);
  }
  return result;
}

void _addRole(Set<String> roles, Object? value) {
  final normalized = normalizeUserRole(value);
  if (normalized.isNotEmpty) roles.add(normalized);
}

bool _isTrue(Object? value) {
  if (value is bool) return value;
  return const {
    'true',
    '1',
    'yes',
    'active',
  }.contains(value?.toString().trim().toLowerCase());
}
