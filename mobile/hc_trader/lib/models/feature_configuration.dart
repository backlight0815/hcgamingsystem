import 'package:cloud_firestore/cloud_firestore.dart';

enum FeatureOperatingMode {
  enabled,
  disabled,
  hidden,
  readOnly,
  maintenance,
  beta,
}

enum FeaturePlatform { android, ios, web, windows, macos }

class FeatureConfiguration {
  const FeatureConfiguration({
    required this.key,
    required this.name,
    required this.description,
    required this.group,
    required this.mode,
    required this.version,
    required this.updatedAt,
    this.platforms = const {},
    this.roleIds = const {},
    this.accessLevels = const {},
    this.environments = const {'production'},
    this.minimumAppVersion = '',
    this.rolloutPercentage = 100,
    this.selectedUserIds = const {},
    this.regions = const {},
    this.dependencies = const {},
    this.startsAt,
    this.endsAt,
    this.updatedBy = '',
    this.critical = false,
  });

  final String key;
  final String name;
  final String description;
  final String group;
  final FeatureOperatingMode mode;
  final int version;
  final Set<FeaturePlatform> platforms;
  final Set<int> roleIds;
  final Set<String> accessLevels;
  final Set<String> environments;
  final String minimumAppVersion;
  final int rolloutPercentage;
  final Set<String> selectedUserIds;
  final Set<String> regions;
  final Set<String> dependencies;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final String updatedBy;
  final DateTime updatedAt;
  final bool critical;

  bool get isAvailable =>
      mode != FeatureOperatingMode.disabled &&
      mode != FeatureOperatingMode.hidden &&
      mode != FeatureOperatingMode.maintenance;

  List<String> dependencyWarnings(Map<String, FeatureConfiguration> all) => [
    for (final dependency in dependencies)
      if (all[dependency]?.isAvailable != true)
        '$name requires ${all[dependency]?.name ?? dependency}.',
  ];

  factory FeatureConfiguration.fromMap(
    String key,
    Map<String, dynamic> data,
  ) => FeatureConfiguration(
    key: key,
    name: '${data['name'] ?? key}',
    description: '${data['description'] ?? ''}',
    group: '${data['group'] ?? 'experimental'}',
    mode: FeatureOperatingMode.values.firstWhere(
      (mode) =>
          mode.name.toLowerCase() ==
          '${data['mode'] ?? (data['enabled'] == false ? 'disabled' : 'enabled')}'
              .replaceAll('_', '')
              .toLowerCase(),
      orElse: () => FeatureOperatingMode.enabled,
    ),
    version: _int(data['version'], fallback: 1),
    platforms: _strings(data['platforms'])
        .map(
          (value) => FeaturePlatform.values.firstWhere(
            (platform) => platform.name == value,
            orElse: () => FeaturePlatform.android,
          ),
        )
        .toSet(),
    roleIds: _strings(
      data['role_ids'],
    ).map(int.tryParse).whereType<int>().toSet(),
    accessLevels: _strings(data['access_levels']).toSet(),
    environments: _strings(data['environments']).toSet(),
    minimumAppVersion: '${data['minimum_app_version'] ?? ''}',
    rolloutPercentage: _int(
      data['rollout_percentage'],
      fallback: 100,
    ).clamp(0, 100),
    selectedUserIds: _strings(data['selected_user_ids']).toSet(),
    regions: _strings(data['regions']).toSet(),
    dependencies: _strings(data['dependencies']).toSet(),
    startsAt: _date(data['starts_at']),
    endsAt: _date(data['ends_at']),
    updatedBy: '${data['updated_by'] ?? ''}',
    updatedAt: _date(data['updated_at']) ?? DateTime(1970),
    critical: data['critical'] == true,
  );

  Map<String, Object?> toMap({required int expectedVersion}) => {
    'key': key,
    'name': name,
    'description': description,
    'group': group,
    'mode': mode.name,
    'enabled': mode != FeatureOperatingMode.disabled,
    'visible': mode != FeatureOperatingMode.hidden,
    'version': expectedVersion + 1,
    'expected_version': expectedVersion,
    'platforms': platforms.map((platform) => platform.name).toList(),
    'role_ids': roleIds.toList(),
    'access_levels': accessLevels.toList(),
    'environments': environments.toList(),
    'minimum_app_version': minimumAppVersion,
    'rollout_percentage': rolloutPercentage,
    'selected_user_ids': selectedUserIds.toList(),
    'regions': regions.toList(),
    'dependencies': dependencies.toList(),
    if (startsAt != null) 'starts_at': Timestamp.fromDate(startsAt!),
    if (endsAt != null) 'ends_at': Timestamp.fromDate(endsAt!),
    'critical': critical,
  };
}

List<String> _strings(Object? value) => value is Iterable
    ? value
          .map((item) => '$item'.trim())
          .where((item) => item.isNotEmpty)
          .toList()
    : const [];
int _int(Object? value, {required int fallback}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
DateTime? _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return value == null ? null : DateTime.tryParse('$value');
}
