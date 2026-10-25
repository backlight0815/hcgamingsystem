import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/feature_configuration.dart';
import '../../repositories/feature_configuration_repository.dart';

class FeatureConfigurationControlCenter extends StatefulWidget {
  const FeatureConfigurationControlCenter({
    super.key,
    required this.repository,
  });

  final FeatureConfigurationRepository repository;

  @override
  State<FeatureConfigurationControlCenter> createState() =>
      _FeatureConfigurationControlCenterState();
}

class _FeatureConfigurationControlCenterState
    extends State<FeatureConfigurationControlCenter> {
  String _search = '';

  @override
  Widget build(
    BuildContext context,
  ) => StreamBuilder<List<FeatureConfiguration>>(
    stream: widget.repository.configurations(),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _FeatureState(
          icon: Icons.cloud_off_outlined,
          title: context.l10n.text('Feature controls unavailable'),
          message: context.l10n.text(
            'No configuration was changed. Check your connection and permissions.',
          ),
        );
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final all = snapshot.data!;
      final query = _search.trim().toLowerCase();
      final visible = all.where((feature) {
        return query.isEmpty ||
            feature.name.toLowerCase().contains(query) ||
            feature.description.toLowerCase().contains(query) ||
            feature.group.toLowerCase().contains(query);
      }).toList();
      final byKey = {for (final feature in all) feature.key: feature};
      final groups = <String, List<FeatureConfiguration>>{};
      for (final feature in visible) {
        groups.putIfAbsent(feature.group, () => []).add(feature);
      }
      return ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          Text(
            context.l10n.text('Feature Configuration'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            context.l10n.text(
              'Control availability, maintenance states and staged rollout with audited, version-safe changes.',
            ),
          ),
          const SizedBox(height: 14),
          _EmergencySummary(features: all),
          const SizedBox(height: 14),
          SearchBar(
            leading: const Icon(Icons.search),
            hintText: context.l10n.text('Search features'),
            onChanged: (value) => setState(() => _search = value),
          ),
          const SizedBox(height: 18),
          if (visible.isEmpty)
            _FeatureState(
              icon: Icons.search_off,
              title: context.l10n.text('No matching features'),
              message: context.l10n.text('Try a different search term.'),
            )
          else
            for (final entry in groups.entries) ...[
              _GroupHeading(label: entry.key, count: entry.value.length),
              const SizedBox(height: 8),
              LayoutBuilder(
                builder: (context, constraints) {
                  if (constraints.maxWidth < 900) {
                    return Column(
                      children: [
                        for (final feature in entry.value)
                          _FeatureCard(
                            feature: feature,
                            all: byKey,
                            repository: widget.repository,
                          ),
                      ],
                    );
                  }
                  return GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: entry.value.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          mainAxisExtent: 310,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                        ),
                    itemBuilder: (context, index) => _FeatureCard(
                      feature: entry.value[index],
                      all: byKey,
                      repository: widget.repository,
                    ),
                  );
                },
              ),
              const SizedBox(height: 18),
            ],
        ],
      );
    },
  );
}

class _EmergencySummary extends StatelessWidget {
  const _EmergencySummary({required this.features});

  final List<FeatureConfiguration> features;

  @override
  Widget build(BuildContext context) {
    final critical = features.where((feature) => feature.critical).toList();
    final paused = critical
        .where(
          (feature) =>
              feature.mode == FeatureOperatingMode.disabled ||
              feature.mode == FeatureOperatingMode.maintenance,
        )
        .length;
    return Card(
      color: paused > 0
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(
              paused > 0 ? Icons.emergency : Icons.health_and_safety_outlined,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Emergency controls'),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  Text(
                    context.l10n.text(
                      paused > 0
                          ? '{count} critical feature(s) are paused or under maintenance.'
                          : 'All critical publishing, notification and synchronization controls are operational.',
                      {'count': paused},
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GroupHeading extends StatelessWidget {
  const _GroupHeading({required this.label, required this.count});

  final String label;
  final int count;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          context.l10n.text(_pretty(label)),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      Chip(label: Text('$count')),
    ],
  );
}

class _FeatureCard extends StatefulWidget {
  const _FeatureCard({
    required this.feature,
    required this.all,
    required this.repository,
  });

  final FeatureConfiguration feature;
  final Map<String, FeatureConfiguration> all;
  final FeatureConfigurationRepository repository;

  @override
  State<_FeatureCard> createState() => _FeatureCardState();
}

class _FeatureCardState extends State<_FeatureCard> {
  late FeatureOperatingMode _mode = widget.feature.mode;
  late double _rollout = widget.feature.rolloutPercentage.toDouble();
  FeatureConfiguration? _advanced;
  bool _saving = false;

  @override
  void didUpdateWidget(covariant _FeatureCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.feature.version != widget.feature.version) {
      _mode = widget.feature.mode;
      _rollout = widget.feature.rolloutPercentage.toDouble();
      _advanced = null;
    }
  }

  bool get _changed =>
      _mode != widget.feature.mode ||
      _rollout.round() != widget.feature.rolloutPercentage ||
      _advanced != null;

  @override
  Widget build(BuildContext context) {
    final warnings = widget.feature.dependencyWarnings(widget.all);
    final effective = _advanced ?? widget.feature;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  child: Icon(
                    widget.feature.critical
                        ? Icons.emergency_outlined
                        : Icons.toggle_on_outlined,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.feature.name,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        'v${widget.feature.version} · ${widget.feature.key}',
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Advanced targeting and schedule'),
                  onPressed: _saving ? null : _openAdvanced,
                  icon: const Icon(Icons.tune),
                ),
                IconButton(
                  tooltip: context.l10n.text('Rollback previous version'),
                  onPressed: _saving || widget.feature.version < 2
                      ? null
                      : _rollback,
                  icon: const Icon(Icons.settings_backup_restore),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(widget.feature.description, maxLines: 2),
            const SizedBox(height: 8),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                Chip(
                  label: Text(
                    effective.platforms.isEmpty
                        ? context.l10n.text('All platforms')
                        : effective.platforms
                              .map((platform) => _pretty(platform.name))
                              .join(', '),
                  ),
                ),
                Chip(
                  label: Text(
                    context.l10n.text('{count} role(s)', {
                      'count': effective.roleIds.length,
                    }),
                  ),
                ),
                Chip(label: Text(effective.environments.join(', '))),
                if (effective.minimumAppVersion.isNotEmpty)
                  Chip(
                    label: Text(
                      context.l10n.text('Minimum v{version}', {
                        'version': effective.minimumAppVersion,
                      }),
                    ),
                  ),
              ],
            ),
            if (warnings.isNotEmpty) ...[
              const SizedBox(height: 8),
              for (final warning in warnings)
                Text(
                  '⚠ ${context.l10n.text(warning)}',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
            const SizedBox(height: 10),
            DropdownButtonFormField<FeatureOperatingMode>(
              initialValue: _mode,
              decoration: InputDecoration(
                labelText: context.l10n.text('Operating mode'),
              ),
              items: [
                for (final mode in FeatureOperatingMode.values)
                  DropdownMenuItem(
                    value: mode,
                    child: Text(context.l10n.text(_pretty(mode.name))),
                  ),
              ],
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _mode = value ?? _mode),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Percentage rollout: {value}%', {
                      'value': _rollout.round(),
                    }),
                  ),
                ),
                if (_changed)
                  TextButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: const Icon(Icons.save_outlined),
                    label: Text(context.l10n.text('Apply')),
                  ),
              ],
            ),
            Slider(
              value: _rollout,
              divisions: 20,
              label: '${_rollout.round()}%',
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _rollout = value),
            ),
          ],
        ),
      ),
    );
  }

  FeatureConfiguration _updated() {
    final base = _advanced ?? widget.feature;
    return FeatureConfiguration(
      key: base.key,
      name: base.name,
      description: base.description,
      group: base.group,
      mode: _mode,
      version: widget.feature.version,
      updatedAt: widget.feature.updatedAt,
      platforms: base.platforms,
      roleIds: base.roleIds,
      accessLevels: base.accessLevels,
      environments: base.environments,
      minimumAppVersion: base.minimumAppVersion,
      rolloutPercentage: _rollout.round(),
      selectedUserIds: base.selectedUserIds,
      regions: base.regions,
      dependencies: base.dependencies,
      startsAt: base.startsAt,
      endsAt: base.endsAt,
      updatedBy: widget.feature.updatedBy,
      critical: base.critical,
    );
  }

  Future<void> _openAdvanced() async {
    final result = await showDialog<FeatureConfiguration>(
      context: context,
      builder: (_) => _FeatureAdvancedDialog(feature: _updated()),
    );
    if (result != null && mounted) setState(() => _advanced = result);
  }

  Future<void> _save() async {
    if (widget.feature.critical) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(context.l10n.text('Apply critical feature change?')),
          content: Text(
            context.l10n.text(
              'This change affects production access. Dependencies and the current version will be validated by the backend.',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(context.l10n.text('Cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(context.l10n.text('Apply')),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
    }
    setState(() => _saving = true);
    try {
      await widget.repository.save(_updated());
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.l10n.text(
                'The feature changed elsewhere or could not be saved. Refresh and try again.',
              ),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _rollback() async {
    setState(() => _saving = true);
    try {
      await widget.repository.rollback(
        featureKey: widget.feature.key,
        currentVersion: widget.feature.version,
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _FeatureAdvancedDialog extends StatefulWidget {
  const _FeatureAdvancedDialog({required this.feature});

  final FeatureConfiguration feature;

  @override
  State<_FeatureAdvancedDialog> createState() => _FeatureAdvancedDialogState();
}

class _FeatureAdvancedDialogState extends State<_FeatureAdvancedDialog> {
  final Set<FeaturePlatform> _platforms = {};
  late final TextEditingController _roles = TextEditingController(
    text: widget.feature.roleIds.join(', '),
  );
  late final TextEditingController _access = TextEditingController(
    text: widget.feature.accessLevels.join(', '),
  );
  late final TextEditingController _environments = TextEditingController(
    text: widget.feature.environments.join(', '),
  );
  late final TextEditingController _minimumVersion = TextEditingController(
    text: widget.feature.minimumAppVersion,
  );
  late final TextEditingController _users = TextEditingController(
    text: widget.feature.selectedUserIds.join(', '),
  );
  late final TextEditingController _regions = TextEditingController(
    text: widget.feature.regions.join(', '),
  );
  late final TextEditingController _dependencies = TextEditingController(
    text: widget.feature.dependencies.join(', '),
  );
  late final TextEditingController _starts = TextEditingController(
    text: widget.feature.startsAt?.toIso8601String() ?? '',
  );
  late final TextEditingController _ends = TextEditingController(
    text: widget.feature.endsAt?.toIso8601String() ?? '',
  );

  @override
  void initState() {
    super.initState();
    _platforms.addAll(widget.feature.platforms);
  }

  @override
  void dispose() {
    for (final controller in [
      _roles,
      _access,
      _environments,
      _minimumVersion,
      _users,
      _regions,
      _dependencies,
      _starts,
      _ends,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Set<String> _csv(TextEditingController controller) => controller.text
      .split(',')
      .map((value) => value.trim())
      .where((value) => value.isNotEmpty)
      .toSet();

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.l10n.text('Advanced targeting and schedule')),
    content: SizedBox(
      width: 680,
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Supported platforms'),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final platform in FeaturePlatform.values)
                  FilterChip(
                    label: Text(_pretty(platform.name)),
                    selected: _platforms.contains(platform),
                    onSelected: (selected) => setState(() {
                      if (selected) {
                        _platforms.add(platform);
                      } else {
                        _platforms.remove(platform);
                      }
                    }),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            _field(context, _roles, 'Enabled role IDs'),
            _field(context, _access, 'Enabled access levels'),
            _field(context, _environments, 'Enabled environments'),
            _field(context, _minimumVersion, 'Minimum app version', csv: false),
            _field(context, _users, 'Selected user IDs'),
            _field(context, _regions, 'Countries or regions'),
            _field(context, _dependencies, 'Dependencies'),
            _field(context, _starts, 'Starts at (ISO 8601)', csv: false),
            _field(context, _ends, 'Ends at (ISO 8601)', csv: false),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: _apply,
        child: Text(context.l10n.text('Apply preview')),
      ),
    ],
  );

  Widget _field(
    BuildContext context,
    TextEditingController controller,
    String label, {
    bool csv = true,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(
      controller: controller,
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        helperText: csv ? context.l10n.text('Comma separated') : null,
      ),
    ),
  );

  void _apply() {
    final starts = _starts.text.trim().isEmpty
        ? null
        : DateTime.tryParse(_starts.text.trim());
    final ends = _ends.text.trim().isEmpty
        ? null
        : DateTime.tryParse(_ends.text.trim());
    if ((_starts.text.trim().isNotEmpty && starts == null) ||
        (_ends.text.trim().isNotEmpty && ends == null) ||
        (starts != null && ends != null && !ends.isAfter(starts))) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.text('Enter a valid schedule.'))),
      );
      return;
    }
    Navigator.pop(
      context,
      FeatureConfiguration(
        key: widget.feature.key,
        name: widget.feature.name,
        description: widget.feature.description,
        group: widget.feature.group,
        mode: widget.feature.mode,
        version: widget.feature.version,
        updatedAt: widget.feature.updatedAt,
        platforms: _platforms,
        roleIds: _csv(_roles).map(int.tryParse).whereType<int>().toSet(),
        accessLevels: _csv(_access),
        environments: _csv(_environments),
        minimumAppVersion: _minimumVersion.text.trim(),
        rolloutPercentage: widget.feature.rolloutPercentage,
        selectedUserIds: _csv(_users),
        regions: _csv(_regions),
        dependencies: _csv(_dependencies),
        startsAt: starts,
        endsAt: ends,
        updatedBy: widget.feature.updatedBy,
        critical: widget.feature.critical,
      ),
    );
  }
}

class _FeatureState extends StatelessWidget {
  const _FeatureState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 52),
          const SizedBox(height: 12),
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

String _pretty(String value) => value
    .replaceAllMapped(
      RegExp(r'([a-z])([A-Z])'),
      (match) => '${match[1]} ${match[2]}',
    )
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');
