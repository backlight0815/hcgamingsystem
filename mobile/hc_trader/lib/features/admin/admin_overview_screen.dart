import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/admin_permissions.dart';
import '../../core/security/app_role_permissions.dart';
import '../../core/security/permission_service.dart';
import '../../models/access_status.dart';
import '../../models/app_user.dart';
import '../../repositories/trading_repository.dart';
import '../../repositories/feature_configuration_repository.dart';
import '../../repositories/stock_repository.dart';
import '../../shared/app_feedback.dart';
import '../announcements/announcement_screens.dart';
import '../content/content_management_screen.dart';
import '../stocks/financial_report_admin_screen.dart';
import 'feature_configuration_control_center.dart';
import 'admin_management_screen.dart';
import 'admin_trading_operations.dart';
import 'performance/admin_performance_screen.dart';
import 'manage_backend_dashboard.dart';
import 'user_management_screen.dart';

class AdminOverviewScreen extends StatelessWidget {
  const AdminOverviewScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    if (!user.isAdmin) {
      return const Scaffold(body: _AccessDenied());
    }
    final stockRepository = StockRepository(
      firebaseEnabled: repository.firebaseEnabled,
    );
    final featureRepository = FeatureConfigurationRepository(
      firebaseEnabled: repository.firebaseEnabled,
    );
    return DefaultTabController(
      key: ValueKey(user.uid),
      length: 15,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.l10n.text('Manage Backend')),
          bottom: TabBar(
            isScrollable: true,
            tabs: [
              Tab(
                icon: const Icon(Icons.dashboard_outlined),
                text: context.l10n.text('Manage Backend'),
              ),
              Tab(
                icon: const Icon(Icons.apps_outlined),
                text: context.l10n.text('Modules'),
              ),
              Tab(
                icon: const Icon(Icons.manage_accounts_outlined),
                text: context.l10n.text('Users'),
              ),
              Tab(
                icon: const Icon(Icons.person_remove_outlined),
                text: context.l10n.text('Deleted Users'),
              ),
              Tab(
                icon: const Icon(Icons.fact_check_outlined),
                text: context.l10n.text('Verification'),
              ),
              Tab(
                icon: const Icon(Icons.verified_user_outlined),
                text: context.l10n.text('Client access'),
              ),
              Tab(
                icon: const Icon(Icons.badge_outlined),
                text: context.l10n.text('Positions'),
              ),
              Tab(
                icon: const Icon(Icons.video_library_outlined),
                text: context.l10n.text('Content'),
              ),
              Tab(
                icon: const Icon(Icons.campaign_outlined),
                text: context.l10n.text('Announcements'),
              ),
              Tab(
                icon: const Icon(Icons.monitor_heart_outlined),
                text: context.l10n.text('Stock Reports'),
              ),
              Tab(
                icon: const Icon(Icons.query_stats_outlined),
                text: context.l10n.text('Performance'),
              ),
              Tab(
                icon: const Icon(Icons.emoji_events_outlined),
                text: context.l10n.text('Leaderboard'),
              ),
              Tab(
                icon: const Icon(Icons.delete_sweep_outlined),
                text: context.l10n.text('Requests'),
              ),
              Tab(
                icon: const Icon(Icons.tune_outlined),
                text: context.l10n.text('Features'),
              ),
              Tab(
                icon: const Icon(Icons.history_outlined),
                text: context.l10n.text('Audit'),
              ),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            Builder(
              builder: (tabContext) => ManageBackendDashboard(
                user: user,
                repository: repository,
                onOpenTab: (index) =>
                    DefaultTabController.of(tabContext).animateTo(index),
              ),
            ),
            AdminManagementScreen(user: user, repository: repository),
            PermissionService(user).can(AppPermission.usersReadAll)
                ? AdminUserManagementScreen(actor: user, repository: repository)
                : const _AccessDenied(),
            _DeletedUsersTab(repository: repository),
            _OnboardingTab(repository: repository),
            _AccessRequestsTab(repository: repository),
            _PositionsTab(repository: repository),
            ContentManagementScreen(repository: repository),
            AdminAnnouncementsPanel(repository: repository),
            AdminFinancialReportsPanel(repository: stockRepository),
            user.hasPermission(AdminPermission.performanceManage)
                ? AdminPerformanceTab(user: user, repository: repository)
                : const _AccessDenied(),
            AdminLeaderboardTab(repository: repository),
            user.hasPermission(AdminPermission.marketAnalysisReviewDelete)
                ? AdminDeleteRequestsScreen(repository: repository)
                : const _AccessDenied(),
            user.hasPermission(AdminPermission.featureManage)
                ? FeatureConfigurationControlCenter(
                    repository: featureRepository,
                  )
                : const _AccessDenied(),
            user.hasPermission(AdminPermission.auditView)
                ? AdminAuditLogScreen(repository: repository)
                : const _AccessDenied(),
          ],
        ),
      ),
    );
  }
}

class _AccessDenied extends StatelessWidget {
  const _AccessDenied();

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.lock_outline, size: 52),
          const SizedBox(height: 12),
          Text(
            context.l10n.text('Administration permission is required.'),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

class _UsersTab extends StatefulWidget {
  const _UsersTab({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_UsersTab> createState() => _UsersTabState();
}

class _UsersTabState extends State<_UsersTab> {
  final _search = TextEditingController();
  String? _roleId;
  int? _status;
  int _page = 1;
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _load() {
    _future = widget.repository.adminUsers(
      search: _search.text,
      roleId: _roleId,
      status: _status,
      page: _page,
    );
  }

  void _reload({bool resetPage = false}) {
    setState(() {
      if (resetPage) _page = 1;
      _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        final roles = snapshot.hasData
            ? _assignableRoles(
                widget.user.effectiveRoleIds,
                _maps(snapshot.data!['roles']),
              )
            : const <Map<String, dynamic>>[];
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
              child: Column(
                children: [
                  SearchBar(
                    controller: _search,
                    hintText: context.l10n.text(
                      'Search name, username or email',
                    ),
                    leading: const Icon(Icons.search),
                    trailing: [
                      IconButton(
                        tooltip: context.l10n.text('Search'),
                        onPressed: () => _reload(resetPage: true),
                        icon: const Icon(Icons.arrow_forward),
                      ),
                    ],
                    onSubmitted: (_) => _reload(resetPage: true),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String?>(
                          initialValue: _roleId,
                          decoration: InputDecoration(
                            labelText: context.l10n.text('Role'),
                            isDense: true,
                          ),
                          items: [
                            DropdownMenuItem(
                              value: null,
                              child: Text(context.l10n.text('All roles')),
                            ),
                            for (final role in roles)
                              DropdownMenuItem(
                                value: '${role['id']}',
                                child: Text(
                                  context.l10n.roleName(_int(role['id'])),
                                ),
                              ),
                          ],
                          onChanged: (value) {
                            _roleId = value;
                            _reload(resetPage: true);
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: DropdownButtonFormField<int?>(
                          initialValue: _status,
                          decoration: InputDecoration(
                            labelText: context.l10n.text('Status'),
                            isDense: true,
                          ),
                          items: [
                            DropdownMenuItem(
                              value: null,
                              child: Text(context.l10n.text('All statuses')),
                            ),
                            DropdownMenuItem(
                              value: 1,
                              child: Text(context.l10n.text('Active')),
                            ),
                            DropdownMenuItem(
                              value: 0,
                              child: Text(context.l10n.text('Inactive')),
                            ),
                          ],
                          onChanged: (value) {
                            _status = value;
                            _reload(resetPage: true);
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: FilledButton.icon(
                      onPressed: roles.isEmpty
                          ? null
                          : () => _createUser(roles),
                      icon: const Icon(Icons.person_add_alt_1_outlined),
                      label: Text(context.l10n.text('Create user')),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: switch (snapshot.connectionState) {
                ConnectionState.waiting => const Center(
                  child: CircularProgressIndicator(),
                ),
                _ when snapshot.hasError => _ErrorState(
                  error: snapshot.error,
                  onRetry: _reload,
                ),
                _ => _usersList(snapshot.data!, roles),
              },
            ),
          ],
        );
      },
    );
  }

  Widget _usersList(
    Map<String, dynamic> data,
    List<Map<String, dynamic>> roles,
  ) {
    final users = _maps(data['users']);
    final pagination = _map(data['pagination']) ?? const {};
    final lastPage = _int(pagination['last_page']);
    if (users.isEmpty) {
      return const _EmptyState(
        icon: Icons.person_search_outlined,
        title: 'No matching users',
        subtitle: 'Adjust the search or filters and try again.',
      );
    }
    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              itemCount: users.length,
              itemBuilder: (context, index) {
                final user = users[index];
                final role = _map(user['role']);
                final active = _int(user['status']) == 1;
                return Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Text(_initials(_userName(user))),
                    ),
                    title: Text(_userName(user)),
                    subtitle: Text(
                      '${user['email'] ?? ''}\n${role == null ? context.l10n.text('Role {id}', {'id': user['role_id']}) : context.l10n.roleName(_int(role['id']))}',
                    ),
                    isThreeLine: true,
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _StatusChip(
                          label: active ? 'Active' : 'Inactive',
                          positive: active,
                        ),
                        const Icon(Icons.chevron_right, size: 18),
                      ],
                    ),
                    onTap: () => _editUser(user, roles),
                  ),
                );
              },
            ),
          ),
        ),
        if (lastPage > 1)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                IconButton(
                  onPressed: _page > 1
                      ? () {
                          _page--;
                          _reload();
                        }
                      : null,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  context.l10n.text('Page {page} of {lastPage}', {
                    'page': _page,
                    'lastPage': lastPage,
                  }),
                ),
                IconButton(
                  onPressed: _page < lastPage
                      ? () {
                          _page++;
                          _reload();
                        }
                      : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Future<void> _editUser(
    Map<String, dynamic> user,
    List<Map<String, dynamic>> roles,
  ) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (context) => _EditUserDialog(
        actor: widget.user,
        repository: widget.repository,
        user: user,
        roles: roles,
      ),
    );
    if (changed == true && mounted) {
      _reload();
      AppFeedback.success(context, 'User account updated successfully.');
    }
  }

  Future<void> _createUser(List<Map<String, dynamic>> roles) async {
    final created = await showDialog<bool>(
      context: context,
      builder: (context) =>
          _CreateUserDialog(repository: widget.repository, roles: roles),
    );
    if (created == true && mounted) {
      _reload(resetPage: true);
      AppFeedback.success(context, 'User account created successfully.');
    }
  }
}

List<Map<String, dynamic>> _assignableRoles(
  Iterable<int> actorRoleIds,
  List<Map<String, dynamic>> roles,
) {
  final allowed = <int>{
    for (final actorRoleId in actorRoleIds)
      ...RolePermissionMatrix.assignableRoleIds(actorRoleId),
  };
  return roles.where((role) => allowed.contains(_int(role['id']))).toList()
    ..sort((left, right) => '${left['name']}'.compareTo('${right['name']}'));
}

class _EditUserDialog extends StatefulWidget {
  const _EditUserDialog({
    required this.actor,
    required this.repository,
    required this.user,
    required this.roles,
  });

  final AppUser actor;
  final TradingRepository repository;
  final Map<String, dynamic> user;
  final List<Map<String, dynamic>> roles;

  @override
  State<_EditUserDialog> createState() => _EditUserDialogState();
}

class _EditUserDialogState extends State<_EditUserDialog> {
  late final TextEditingController _name;
  late String _roleId;
  late Set<int> _roleIds;
  late int _status;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: _userName(widget.user));
    _roleId = '${widget.user['role_id']}';
    final storedRoles = widget.user['role_ids'];
    _roleIds = storedRoles is Iterable
        ? storedRoles.map(_int).where((roleId) => roleId > 0).toSet()
        : {_int(widget.user['role_id'])};
    _status = _int(widget.user['status']);
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final targetRoleId = _int(widget.user['role_id']);
    final actorPermissions = PermissionService(widget.actor);
    final canManageTarget =
        actorPermissions.can(AppPermission.usersUpdateBasic) &&
        (!PermissionService.privilegedRoleIds.contains(targetRoleId) ||
            widget.actor.isSuperAdmin);
    return AlertDialog(
      title: Text(context.l10n.text('Edit user account')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (!canManageTarget) ...[
              const Icon(Icons.lock_outline, size: 42),
              const SizedBox(height: 12),
              Text(
                context.l10n.text(
                  'You do not have permission to manage this account role.',
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _name,
              enabled: canManageTarget,
              decoration: InputDecoration(
                labelText: context.l10n.text('Display name'),
              ),
            ),
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                context.l10n.text('Assigned roles'),
                style: Theme.of(context).textTheme.labelLarge,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final role in widget.roles)
                  ChoiceChip(
                    label: Text(context.l10n.roleName(_int(role['id']))),
                    selected: _roleIds.contains(_int(role['id'])),
                    onSelected: _saving || !canManageTarget
                        ? null
                        : (selected) {
                            final roleId = _int(role['id']);
                            if (selected &&
                                !actorPermissions.canAssignRole(roleId)) {
                              return;
                            }
                            setState(() {
                              if (selected) {
                                _roleIds.add(roleId);
                              } else if (_roleIds.length > 1) {
                                _roleIds.remove(roleId);
                              }
                              _roleId = '${_roleIds.first}';
                            });
                          },
                  ),
              ],
            ),
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              title: Text(context.l10n.text('Active account')),
              value: _status == 1,
              onChanged: _saving || !canManageTarget
                  ? null
                  : (value) => setState(() => _status = value ? 1 : 0),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _saving || !canManageTarget ? null : _save,
          child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
        ),
      ],
    );
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await widget.repository.adminUpdateUser(
        '${widget.user['id']}',
        name: _name.text.trim(),
        roleId: _roleId,
        roleIds: _roleIds,
        status: _status,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _CreateUserDialog extends StatefulWidget {
  const _CreateUserDialog({required this.repository, required this.roles});

  final TradingRepository repository;
  final List<Map<String, dynamic>> roles;

  @override
  State<_CreateUserDialog> createState() => _CreateUserDialogState();
}

class _CreateUserDialogState extends State<_CreateUserDialog> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _username = TextEditingController();
  final _password = TextEditingController();
  late String _roleId;
  var _status = 1;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    _roleId = '${widget.roles.first['id']}';
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Create user')),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _name,
                enabled: !_saving,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Display name'),
                ),
                validator: _required,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _email,
                enabled: !_saving,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Email'),
                ),
                onChanged: _syncUsername,
                validator: (value) {
                  final text = (value ?? '').trim();
                  return text.contains('@')
                      ? null
                      : context.l10n.text('Enter a valid email');
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _username,
                enabled: !_saving,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Username'),
                ),
                validator: (value) {
                  final text = (value ?? '').trim();
                  return RegExp(r'^[a-z0-9_]+$').hasMatch(text)
                      ? null
                      : context.l10n.text(
                          'Use lowercase letters, numbers, or underscore',
                        );
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _password,
                enabled: !_saving,
                obscureText: true,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Temporary password'),
                ),
                validator: (value) => (value ?? '').length >= 8
                    ? null
                    : context.l10n.text('Use 8+ characters'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _roleId,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Role'),
                ),
                items: [
                  for (final role in widget.roles)
                    DropdownMenuItem(
                      value: '${role['id']}',
                      child: Text(context.l10n.roleName(_int(role['id']))),
                    ),
                ],
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _roleId = value ?? _roleId),
              ),
              const SizedBox(height: 12),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Active account')),
                value: _status == 1,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _status = value ? 1 : 0),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context, false),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(context.l10n.text(_saving ? 'Saving...' : 'Create')),
        ),
      ],
    );
  }

  void _syncUsername(String value) {
    if (_username.text.trim().isNotEmpty) return;
    final local = value.split('@').first.toLowerCase();
    final sanitized = local.replaceAll(RegExp(r'[^a-z0-9_]'), '_');
    _username.text = sanitized.replaceAll(RegExp(r'_+'), '_');
  }

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.repository.adminCreateUser(
        name: _name.text,
        email: _email.text,
        username: _username.text,
        password: _password.text,
        roleId: int.parse(_roleId),
        status: _status,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _DeletedUsersTab extends StatelessWidget {
  const _DeletedUsersTab({required this.repository});

  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<Map<String, dynamic>>>(
      stream: repository.deletedUsersStream(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            !snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) return _ErrorState(error: snapshot.error);
        final users = snapshot.data ?? const <Map<String, dynamic>>[];
        if (users.isEmpty) {
          return const _EmptyState(
            icon: Icons.person_remove_outlined,
            title: 'No deleted users',
            subtitle: 'Self-deleted account audit records will appear here.',
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
          itemCount: users.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final user = users[index];
            final status =
                '${user['deletion_status'] ?? user['deletionStatus'] ?? 'completed'}';
            return Card(
              color: Theme.of(
                context,
              ).colorScheme.errorContainer.withValues(alpha: .35),
              child: ListTile(
                leading: const CircleAvatar(
                  child: Icon(Icons.person_remove_outlined),
                ),
                title: Text(
                  '${user['name'] ?? user['username'] ?? user['email'] ?? user['id']}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                subtitle: Text(
                  [
                    if ('${user['email'] ?? ''}'.trim().isNotEmpty)
                      '${user['email']}',
                    context.l10n.text('Deleted {date}', {
                      'date': _shortDate(user['deleted_at']),
                    }),
                    if ('${user['role_name'] ?? ''}'.trim().isNotEmpty)
                      '${user['role_name']}',
                    if ('${user['previous_access_status'] ?? ''}'
                        .trim()
                        .isNotEmpty)
                      '${context.l10n.text('Previous access status')}: ${context.l10n.text('${user['previous_access_status']}')}',
                    if ('${user['deletion_source'] ?? ''}'.trim().isNotEmpty)
                      '${context.l10n.text('Deletion source')}: ${context.l10n.text('${user['deletion_source']}')}',
                  ].join('\n'),
                ),
                isThreeLine: true,
                trailing: Chip(label: Text(context.l10n.text(status))),
              ),
            );
          },
        );
      },
    );
  }
}

class _OnboardingTab extends StatefulWidget {
  const _OnboardingTab({required this.repository});

  final TradingRepository repository;

  @override
  State<_OnboardingTab> createState() => _OnboardingTabState();
}

class _OnboardingTabState extends State<_OnboardingTab> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .adminOnboarding();

  void _reload() {
    final nextFuture = widget.repository.adminOnboarding();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error, onRetry: _reload);
        }
        final pending = _maps(snapshot.data!['pending']);
        final recent = _maps(snapshot.data!['recent']);
        final reasons = _map(snapshot.data!['rejection_reasons']) ?? const {};
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            children: [
              _SectionHeading(
                title: 'Pending review',
                subtitle:
                    '${pending.length} application${pending.length == 1 ? '' : 's'} awaiting a decision',
              ),
              const SizedBox(height: 8),
              if (pending.isEmpty)
                const _InlineEmpty(
                  message: 'No onboarding applications are waiting.',
                )
              else
                for (final application in pending)
                  _OnboardingCard(
                    application: application,
                    onReview: () => _review(application, reasons),
                  ),
              const SizedBox(height: 20),
              const _SectionHeading(
                title: 'Recent decisions',
                subtitle: 'Latest completed onboarding reviews',
              ),
              const SizedBox(height: 8),
              for (final application in recent.take(15))
                _OnboardingCard(application: application),
            ],
          ),
        );
      },
    );
  }

  Future<void> _review(
    Map<String, dynamic> application,
    Map<String, dynamic> reasons,
  ) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (context) => _OnboardingReviewDialog(
        repository: widget.repository,
        application: application,
        reasons: reasons,
      ),
    );
    if (changed == true) _reload();
  }
}

class _OnboardingCard extends StatelessWidget {
  const _OnboardingCard({required this.application, this.onReview});

  final Map<String, dynamic> application;
  final VoidCallback? onReview;

  @override
  Widget build(BuildContext context) {
    final trader = _map(application['trader']) ?? const {};
    final status = '${application['status'] ?? 'pending'}';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    _userName(trader),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusChip(
                  label: _pretty(status),
                  positive: status == 'approved',
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text('${trader['email'] ?? ''}'),
            Text(
              'Broker UID: ${application['broker_uid'] ?? '—'} · Deposit: ${application['deposit_amount'] ?? '—'}',
            ),
            if ('${application['discord_username'] ?? ''}'.isNotEmpty)
              Text('Discord: ${application['discord_username']}'),
            if (onReview != null) ...[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerRight,
                child: FilledButton.tonalIcon(
                  onPressed: onReview,
                  icon: const Icon(Icons.fact_check_outlined),
                  label: const Text('Review'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _OnboardingReviewDialog extends StatefulWidget {
  const _OnboardingReviewDialog({
    required this.repository,
    required this.application,
    required this.reasons,
  });

  final TradingRepository repository;
  final Map<String, dynamic> application;
  final Map<String, dynamic> reasons;

  @override
  State<_OnboardingReviewDialog> createState() =>
      _OnboardingReviewDialogState();
}

class _OnboardingReviewDialogState extends State<_OnboardingReviewDialog> {
  String _decision = 'approve';
  String? _reason;
  final _note = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final rejecting = _decision != 'approve';
    return AlertDialog(
      title: const Text('Review onboarding'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            OutlinedButton.icon(
              onPressed: _saving ? null : _previewDocument,
              icon: const Icon(Icons.description_outlined),
              label: const Text('Preview verification document'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _decision,
              decoration: const InputDecoration(labelText: 'Decision'),
              items: const [
                DropdownMenuItem(
                  value: 'approve',
                  child: Text('Approve application'),
                ),
                DropdownMenuItem(
                  value: 'resubmit_documents',
                  child: Text('Request document resubmission'),
                ),
                DropdownMenuItem(
                  value: 'close_new_application',
                  child: Text('Close; allow new application'),
                ),
                DropdownMenuItem(
                  value: 'close_final',
                  child: Text('Close permanently'),
                ),
              ],
              onChanged: (value) => setState(() {
                _decision = value!;
                _reason = null;
              }),
            ),
            if (rejecting) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _reason,
                decoration: const InputDecoration(labelText: 'Reason'),
                items: [
                  for (final reason in widget.reasons.entries)
                    DropdownMenuItem(
                      value: reason.key,
                      child: Text(
                        '${reason.value}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (value) => setState(() => _reason = value),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _note,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Review note (optional)',
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving || (rejecting && _reason == null) ? null : _save,
          child: Text(_saving ? 'Saving…' : 'Confirm'),
        ),
      ],
    );
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.repository.adminReviewOnboarding(
        '${widget.application['id']}',
        decision: _decision,
        rejectionReason: _reason,
        note: _note.text,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }

  Future<void> _previewDocument() async {
    try {
      final document = await widget.repository.adminOnboardingDocument(
        '${widget.application['id']}',
      );
      if (!mounted) return;
      if (document == null || document['bytes'] is! Uint8List) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No verification document is available.'),
          ),
        );
        return;
      }
      final bytes = document['bytes'] as Uint8List;
      final mime = '${document['content_type'] ?? ''}'.toLowerCase();
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => Dialog.fullscreen(
          child: Scaffold(
            appBar: AppBar(
              title: Text('${document['name'] ?? 'Verification document'}'),
              leading: IconButton(
                onPressed: () => Navigator.pop(dialogContext),
                icon: const Icon(Icons.close),
              ),
            ),
            body: mime.startsWith('image/')
                ? InteractiveViewer(
                    minScale: .5,
                    maxScale: 5,
                    child: Center(child: Image.memory(bytes)),
                  )
                : mime == 'application/pdf'
                ? PdfPreview(
                    build: (_) async => bytes,
                    allowPrinting: false,
                    allowSharing: false,
                    canChangeOrientation: false,
                    canChangePageFormat: false,
                  )
                : const Center(
                    child: Text('This document format cannot be previewed.'),
                  ),
          ),
        ),
      );
    } catch (error) {
      if (mounted) _showError(context, error);
    }
  }
}

class _AccessRequestsTab extends StatefulWidget {
  const _AccessRequestsTab({required this.repository});

  final TradingRepository repository;

  @override
  State<_AccessRequestsTab> createState() => _AccessRequestsTabState();
}

class _AccessRequestsTabState extends State<_AccessRequestsTab> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .adminAccessRequests();
  final _reviewing = <String>{};

  void _reload() {
    final nextFuture = widget.repository.adminAccessRequests();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error, onRetry: _reload);
        }
        final pending = _maps(snapshot.data!['pending']);
        final recent = _maps(snapshot.data!['recent']);
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            children: [
              _SectionHeading(
                title: context.l10n.text('Pending client access'),
                subtitle: context.l10n.text(
                  '{count} client access request(s) awaiting a decision',
                  {'count': pending.length},
                ),
              ),
              const SizedBox(height: 8),
              if (pending.isEmpty)
                const _InlineEmpty(
                  message: 'No client access requests are waiting.',
                )
              else
                for (final request in pending)
                  _AccessRequestCard(
                    request: request,
                    busy: _reviewing.contains('${request['id']}'),
                    onReview: (decision) => _review(request, decision),
                  ),
              const SizedBox(height: 20),
              _SectionHeading(
                title: context.l10n.text('Recent access decisions'),
                subtitle: context.l10n.text(
                  'Latest completed client access reviews',
                ),
              ),
              const SizedBox(height: 8),
              if (recent.isEmpty)
                const _InlineEmpty(message: 'No completed access reviews yet.')
              else
                for (final request in recent.take(20))
                  _AccessRequestCard(request: request),
            ],
          ),
        );
      },
    );
  }

  Future<void> _review(Map<String, dynamic> request, String decision) async {
    final note = TextEditingController();
    final approved = decision == 'approve';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          context.l10n.text(
            approved ? 'Approve client access?' : 'Reject client access?',
          ),
        ),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: context.l10n.text(
              approved
                  ? 'Admin note (optional)'
                  : 'Reason or admin note (optional)',
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Confirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      note.dispose();
      return;
    }
    final requestId = '${request['id']}';
    if (_reviewing.contains(requestId)) {
      note.dispose();
      return;
    }
    setState(() => _reviewing.add(requestId));
    try {
      await widget.repository.adminReviewAccessRequest(
        requestId,
        decision: decision,
        note: note.text,
      );
      if (mounted) {
        AppFeedback.success(context, 'Client access review saved.');
        _reload();
      }
    } catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      note.dispose();
      if (mounted) setState(() => _reviewing.remove(requestId));
    }
  }
}

class _AccessRequestCard extends StatelessWidget {
  const _AccessRequestCard({
    required this.request,
    this.onReview,
    this.busy = false,
  });

  final Map<String, dynamic> request;
  final ValueChanged<String>? onReview;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final client = _map(request['client']) ?? const <String, dynamic>{};
    final status =
        '${request['status'] ?? AccessStatus.notSubmitted.storageValue}';
    final email = '${request['email'] ?? client['email'] ?? ''}';
    final phone =
        '${request['phone_number'] ?? request['phoneNumber'] ?? client['phone_number'] ?? client['phoneNumber'] ?? ''}';
    final note = '${request['admin_note'] ?? request['adminNote'] ?? ''}'
        .trim();
    final pending = status == AccessStatus.pendingReview.storageValue;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    _userName(client.isEmpty ? request : client),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusChip(
                  label: _pretty(status),
                  positive: status == AccessStatus.approved.storageValue,
                ),
              ],
            ),
            if (email.isNotEmpty) Text(email),
            if (phone.isNotEmpty) Text('${context.l10n.text('Phone')}: $phone'),
            Text(
              '${context.l10n.text('Submitted')}: '
              '${_shortDate(request['created_at'] ?? request['createdAt'])}',
            ),
            if (note.isNotEmpty)
              Text('${context.l10n.text('Admin note')}: $note'),
            if (pending && onReview != null) ...[
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  OutlinedButton(
                    onPressed: busy ? null : () => onReview!('reject'),
                    child: Text(context.l10n.text('Reject')),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: busy ? null : () => onReview!('approve'),
                    child: busy
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(context.l10n.text('Approve')),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PositionsTab extends StatefulWidget {
  const _PositionsTab({required this.repository});

  final TradingRepository repository;

  @override
  State<_PositionsTab> createState() => _PositionsTabState();
}

class _PositionsTabState extends State<_PositionsTab> {
  late Future<List<Map<String, dynamic>>> _future = widget.repository
      .adminPositions();

  void _reload() {
    final nextFuture = widget.repository.adminPositions();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error, onRetry: _reload);
        }
        final applications = snapshot.data!;
        if (applications.isEmpty) {
          return const _EmptyState(
            icon: Icons.badge_outlined,
            title: 'No position applications',
            subtitle: 'Applications will appear here when traders submit them.',
          );
        }
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            itemCount: applications.length,
            itemBuilder: (context, index) {
              final application = applications[index];
              final applicant = _map(application['applicant']) ?? const {};
              final pending = '${application['status']}' == 'pending';
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              _userName(applicant),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ),
                          _StatusChip(
                            label: _pretty('${application['status']}'),
                            positive: '${application['status']}' == 'approved',
                          ),
                        ],
                      ),
                      Text('${applicant['email'] ?? ''}'),
                      const SizedBox(height: 8),
                      Text(
                        'Requested position: ${_pretty('${application['requested_position']}')}',
                      ),
                      Text(
                        'Trade history: ${application['trade_count_snapshot'] ?? 0} trades',
                      ),
                      if ('${application['strategy_summary'] ?? ''}'.isNotEmpty)
                        Text(
                          '${application['strategy_summary']}',
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                      if (pending) ...[
                        const SizedBox(height: 10),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            OutlinedButton(
                              onPressed: () => _review(application, 'reject'),
                              child: const Text('Reject'),
                            ),
                            const SizedBox(width: 8),
                            FilledButton(
                              onPressed: () => _review(application, 'approve'),
                              child: const Text('Approve'),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _review(
    Map<String, dynamic> application,
    String decision,
  ) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          '${decision == 'approve' ? 'Approve' : 'Reject'} position application?',
        ),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Review note (optional)',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      note.dispose();
      return;
    }
    try {
      await widget.repository.adminReviewPosition(
        '${application['id']}',
        decision: decision,
        note: note.text,
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Position review saved.')));
        _reload();
      }
    } catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      note.dispose();
    }
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(title),
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
      ),
      Text(
        context.l10n.text(subtitle),
        style: Theme.of(context).textTheme.bodySmall,
      ),
    ],
  );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.positive});

  final String label;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    final color = positive
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.secondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        context.l10n.text(label),
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _InlineEmpty extends StatelessWidget {
  const _InlineEmpty({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Center(child: Text(context.l10n.text(message))),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 54, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(
            context.l10n.text(title),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(context.l10n.text(subtitle), textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error, this.onRetry});

  final Object? error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 48,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 12),
          Text(context.l10n.text('$error'), textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: onRetry,
              child: Text(context.l10n.text('Try again')),
            ),
          ],
        ],
      ),
    ),
  );
}

List<Map<String, dynamic>> _maps(Object? value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((item) => Map<String, dynamic>.from(item))
    .toList();

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _userName(Map<String, dynamic> user) =>
    '${user['name'] ?? user['username'] ?? 'User'}';

String _shortDate(Object? value) {
  DateTime? parsed;
  if (value is DateTime) {
    parsed = value;
  } else {
    try {
      final dynamic maybeTimestamp = value;
      final converted = maybeTimestamp?.toDate();
      if (converted is DateTime) parsed = converted;
    } catch (_) {
      parsed = DateTime.tryParse('$value');
    }
  }
  if (parsed == null) return '-';
  return '${parsed.year.toString().padLeft(4, '0')}-'
      '${parsed.month.toString().padLeft(2, '0')}-'
      '${parsed.day.toString().padLeft(2, '0')}';
}

String _initials(String value) {
  final words = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((item) => item.isNotEmpty)
      .take(2);
  return words.map((word) => word[0].toUpperCase()).join();
}

String _pretty(String value) => value
    .replaceAllMapped(
      RegExp(r'([a-z])([A-Z])'),
      (match) => '${match[1]} ${match[2]}',
    )
    .replaceAll('_', ' ')
    .split(' ')
    .where((word) => word.isNotEmpty)
    .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
    .join(' ');

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(context.l10n.text('$error')),
      backgroundColor: Theme.of(context).colorScheme.error,
    ),
  );
}
