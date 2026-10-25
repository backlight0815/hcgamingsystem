import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/permission_service.dart';
import '../../models/admin_user_management.dart';
import '../../models/app_user.dart';
import '../../models/firestore_helpers.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../appointments/trading_appointments_screen.dart';
import '../support/support_tickets_screen.dart';

class AdminUserManagementScreen extends StatefulWidget {
  const AdminUserManagementScreen({
    super.key,
    required this.actor,
    required this.repository,
  });

  final AppUser actor;
  final TradingRepository repository;

  @override
  State<AdminUserManagementScreen> createState() =>
      _AdminUserManagementScreenState();
}

class _AdminUserManagementScreenState extends State<AdminUserManagementScreen> {
  final _search = TextEditingController();
  Timer? _searchDebounce;
  AdminUserFilters _filters = const AdminUserFilters();
  AdminUserSort _sort = AdminUserSort.newest;
  int _page = 1;
  late Future<AdminUserPage> _future;
  AdminUserRecord? _selected;
  Future<AdminUserDetail>? _detailFuture;
  bool _actionBusy = false;
  bool _detailCollapsed = false;
  List<Map<String, dynamic>> _roles = const [];

  @override
  void initState() {
    super.initState();
    _future = _requestPage();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<AdminUserPage> _requestPage() => widget.repository.adminUserPage(
    filters: _filters,
    sort: _sort,
    page: _page,
  );

  void _reload({bool firstPage = false, bool clearSelection = false}) {
    if (firstPage) _page = 1;
    if (clearSelection) {
      _selected = null;
      _detailFuture = null;
    }
    setState(() {
      _future = _requestPage();
    });
  }

  void _onSearch(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 450), () {
      if (!mounted) return;
      final normalized = value.trim();
      if (normalized.length == 1) return;
      _filters = _filters.copyWith(search: normalized);
      _reload(firstPage: true, clearSelection: true);
    });
  }

  void _applyFilters(AdminUserFilters filters) {
    _search.text = filters.search;
    _filters = filters;
    if ((filters.registeredAfter != null || filters.registeredBefore != null) &&
        _sort != AdminUserSort.newest &&
        _sort != AdminUserSort.oldest) {
      _sort = AdminUserSort.newest;
    }
    _reload(firstPage: true, clearSelection: true);
  }

  void _select(AdminUserRecord user) {
    if (_selected?.id == user.id) return;
    setState(() {
      _selected = user;
      _detailFuture = widget.repository.adminUserDetail(user.id);
    });
  }

  void _refreshDetail() {
    final selected = _selected;
    if (selected == null) return;
    setState(() {
      _detailFuture = widget.repository.adminUserDetail(selected.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<AdminUserPage>(
      future: _future,
      builder: (context, snapshot) {
        final data = snapshot.data;
        if (data != null) _roles = data.roles;
        if (_selected == null && data?.users.isNotEmpty == true) {
          _selected = data!.users.first;
          _detailFuture = widget.repository.adminUserDetail(_selected!.id);
        }
        return LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 840;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                UserManagementHeader(
                  metrics: data?.metrics ?? const AdminUserMetrics(),
                  loading: snapshot.connectionState != ConnectionState.done,
                ),
                _UserToolbar(
                  search: _search,
                  filters: _filters,
                  sort: _sort,
                  onSearch: _onSearch,
                  onFilter: () => _showFilters(data?.roles ?? const []),
                  onSort: (value) {
                    if (value == null || value == _sort) return;
                    _sort = value;
                    _reload(firstPage: true, clearSelection: true);
                  },
                  onRefresh: () => _reload(clearSelection: false),
                ),
                if (!_filters.isEmpty)
                  _ActiveUserFilterChips(
                    filters: _filters,
                    onChanged: _applyFilters,
                  ),
                Expanded(
                  child: switch (snapshot.connectionState) {
                    ConnectionState.waiting when data == null =>
                      const _UserListSkeleton(),
                    _ when snapshot.hasError => _UserManagementError(
                      error: snapshot.error,
                      onRetry: () => _reload(firstPage: true),
                    ),
                    _ when data == null || data.users.isEmpty =>
                      _UserManagementEmpty(hasFilters: !_filters.isEmpty),
                    _ =>
                      wide
                          ? Row(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Expanded(
                                  flex: 7,
                                  child: _UserDirectory(
                                    page: data,
                                    selectedId: _selected?.id,
                                    onRefresh: () async => _reload(
                                      firstPage: true,
                                      clearSelection: false,
                                    ),
                                    onSelect: _select,
                                    onOpen: _openMobileDetail,
                                    onPrevious: _page > 1
                                        ? () {
                                            _page--;
                                            _reload(clearSelection: true);
                                          }
                                        : null,
                                    onNext: data.hasNext
                                        ? () {
                                            _page++;
                                            _reload(clearSelection: true);
                                          }
                                        : null,
                                  ),
                                ),
                                const VerticalDivider(width: 1),
                                if (_detailCollapsed)
                                  SizedBox(
                                    width: 52,
                                    child: Align(
                                      alignment: Alignment.topCenter,
                                      child: IconButton(
                                        tooltip: context.l10n.text(
                                          'Show details',
                                        ),
                                        onPressed: () => setState(
                                          () => _detailCollapsed = false,
                                        ),
                                        icon: const Icon(Icons.chevron_left),
                                      ),
                                    ),
                                  )
                                else
                                  SizedBox(
                                    width: constraints.maxWidth >= 1250
                                        ? 500
                                        : 410,
                                    child: _buildDetail(
                                      data.roles,
                                      onCollapse: () => setState(
                                        () => _detailCollapsed = true,
                                      ),
                                    ),
                                  ),
                              ],
                            )
                          : _UserDirectory(
                              page: data,
                              selectedId: null,
                              mobile: true,
                              onRefresh: () async => _reload(
                                firstPage: true,
                                clearSelection: false,
                              ),
                              onSelect: _openMobileDetail,
                              onOpen: _openMobileDetail,
                              onPrevious: _page > 1
                                  ? () {
                                      _page--;
                                      _reload(clearSelection: true);
                                    }
                                  : null,
                              onNext: data.hasNext
                                  ? () {
                                      _page++;
                                      _reload(clearSelection: true);
                                    }
                                  : null,
                            ),
                  },
                ),
              ],
            );
          },
        );
      },
    );
  }

  Widget _buildDetail(
    List<Map<String, dynamic>> roles, {
    VoidCallback? onCollapse,
  }) {
    final selected = _selected;
    final detail = _detailFuture;
    if (selected == null || detail == null) {
      return const _SelectUserPrompt();
    }
    return UserDetailPanel(
      actor: widget.actor,
      repository: widget.repository,
      selected: selected,
      detail: detail,
      roles: roles,
      actionBusy: _actionBusy,
      onCollapse: onCollapse,
      onRefresh: _refreshDetail,
      onAction: (action) => _performAction(action, roles),
    );
  }

  Future<void> _openMobileDetail(AdminUserRecord user) async {
    _select(user);
    final detail = _detailFuture!;
    final page = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (context) => Scaffold(
          appBar: AppBar(title: Text(user.label)),
          body: UserDetailPanel(
            actor: widget.actor,
            repository: widget.repository,
            selected: user,
            detail: detail,
            roles: _roles,
            actionBusy: _actionBusy,
            onRefresh: _refreshDetail,
            onAction: (action) => _performAction(action, _roles),
          ),
        ),
      ),
    );
    if (page == true && mounted) _reload(clearSelection: true);
  }

  Future<void> _showFilters(List<Map<String, dynamic>> roles) async {
    final result = await showModalBottomSheet<AdminUserFilters>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => UserFilterPanel(initial: _filters, roles: roles),
    );
    if (result == null || !mounted) return;
    _applyFilters(result.copyWith(search: _search.text.trim()));
  }

  Future<void> _performAction(
    AdminUserAction action,
    List<Map<String, dynamic>> roles,
  ) async {
    final user = _selected;
    if (user == null || _actionBusy) return;
    switch (action) {
      case AdminUserAction.changeRole:
        await _changeRole(user, roles);
      case AdminUserAction.suspend:
        await _changeAccountStatus(user, suspended: true);
      case AdminUserAction.restore:
        await _changeAccountStatus(user, suspended: false);
      case AdminUserAction.addNote:
        await _addNote(user);
      case AdminUserAction.approveAccess:
        await _reviewAccess(user, 'approve');
      case AdminUserAction.rejectAccess:
        await _reviewAccess(user, 'reject');
      case AdminUserAction.requestMoreInformation:
        await _reviewAccess(user, 'request_info');
      case AdminUserAction.revokeAccess:
        await _reviewAccess(user, 'revoke');
      case AdminUserAction.restoreAccess:
        await _reviewAccess(user, 'restore');
    }
  }

  Future<void> _changeRole(
    AdminUserRecord user,
    List<Map<String, dynamic>> roles,
  ) async {
    final permission = PermissionService(widget.actor);
    final assignable = roles
        .where((role) => permission.canAssignRole(firestoreInt(role['id'])))
        .toList(growable: false);
    if (assignable.isEmpty) {
      AppFeedback.error(
        context,
        context.l10n.text(
          'You do not have permission to assign any available role.',
        ),
      );
      return;
    }
    var selectedRole = user.roleId;
    if (!assignable.any((item) => firestoreInt(item['id']) == selectedRole)) {
      selectedRole = firestoreInt(assignable.first['id']);
    }
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(context.l10n.text('Change user role')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _DialogImpactRow(
                label: 'Current role',
                value: context.l10n.roleName(user.roleId),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<int>(
                initialValue: selectedRole,
                decoration: InputDecoration(
                  labelText: context.l10n.text('New role'),
                ),
                items: [
                  for (final role in assignable)
                    DropdownMenuItem(
                      value: firestoreInt(role['id']),
                      child: Text(
                        context.l10n.roleName(firestoreInt(role['id'])),
                      ),
                    ),
                ],
                onChanged: (value) =>
                    setDialogState(() => selectedRole = value ?? selectedRole),
              ),
              const SizedBox(height: 10),
              Text(
                context.l10n.text(
                  'The new role changes this user\'s administration and publishing permissions.',
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: reason,
                maxLength: 300,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Audit reason'),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(context.l10n.text('Cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(context.l10n.text('Confirm role change')),
            ),
          ],
        ),
      ),
    );
    final auditReason = reason.text.trim();
    reason.dispose();
    if (confirmed != true || auditReason.isEmpty || !mounted) return;
    await _runAction(() async {
      await widget.repository.adminUpdateUser(
        user.id,
        name: user.name,
        roleId: '$selectedRole',
        roleIds: {selectedRole},
        status: user.isSuspended ? 0 : 1,
        permissionOverrides: _permissionOverrides(user.raw),
        reason: auditReason,
      );
    }, 'User role updated.');
  }

  Future<void> _changeAccountStatus(
    AdminUserRecord user, {
    required bool suspended,
  }) async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          context.l10n.text(
            suspended ? 'Suspend this account?' : 'Restore this account?',
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(user.label, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                suspended
                    ? 'The user will lose application and staff access until restored.'
                    : 'The user will regain access allowed by their role and verification status.',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: reason,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: context.l10n.text('Audit reason'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              context.l10n.text(suspended ? 'Suspend account' : 'Restore'),
            ),
          ),
        ],
      ),
    );
    final auditReason = reason.text.trim();
    reason.dispose();
    if (confirmed != true || auditReason.isEmpty || !mounted) return;
    final roles = user.roleIds.isEmpty ? {user.roleId} : user.roleIds;
    await _runAction(() async {
      await widget.repository.adminUpdateUser(
        user.id,
        name: user.name,
        roleId: '${user.roleId}',
        roleIds: roles,
        status: suspended ? 0 : 1,
        permissionOverrides: _permissionOverrides(user.raw),
        reason: auditReason,
      );
    }, suspended ? 'Account suspended.' : 'Account restored.');
  }

  Future<void> _addNote(AdminUserRecord user) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Add internal note')),
        content: TextField(
          controller: note,
          maxLines: 5,
          maxLength: 2000,
          decoration: InputDecoration(
            hintText: context.l10n.text(
              'Visible only to authorized administrators',
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
            child: Text(context.l10n.text('Save note')),
          ),
        ],
      ),
    );
    final value = note.text.trim();
    note.dispose();
    if (confirmed != true || value.isEmpty || !mounted) return;
    await _runAction(
      () => widget.repository.adminAddUserNote(userId: user.id, note: value),
      'Internal note saved.',
    );
  }

  Future<void> _reviewAccess(AdminUserRecord user, String decision) async {
    final detail = await widget.repository.adminUserDetail(user.id);
    final expectedStatus = switch (decision) {
      'revoke' => 'approved',
      'restore' => 'revoked',
      _ => 'pendingreview',
    };
    final application = detail.accessApplications.where((application) {
      final value = '${application['status'] ?? application['access_status']}'
          .toLowerCase();
      final normalized = value.replaceAll(RegExp(r'[-_\s]'), '');
      if (expectedStatus == 'pendingreview') {
        return const {
          'pending',
          'pendingreview',
          'submitted',
        }.contains(normalized);
      }
      return normalized == expectedStatus;
    }).firstOrNull;
    if (!mounted) return;
    if (application == null) {
      AppFeedback.error(
        context,
        context.l10n.text('No matching access application was found.'),
      );
      return;
    }
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          context.l10n.text(switch (decision) {
            'approve' => 'Approve client access?',
            'reject' => 'Reject client access?',
            'request_info' => 'Request more information?',
            'revoke' => 'Revoke client access?',
            _ => 'Restore client access?',
          }),
        ),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: context.l10n.text('Review reason or note'),
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
    final reviewNote = note.text.trim();
    note.dispose();
    if (confirmed != true || !mounted) return;
    if (decision != 'approve' && reviewNote.isEmpty) {
      AppFeedback.error(
        context,
        context.l10n.text('An audit reason is required.'),
      );
      return;
    }
    await _runAction(
      () => widget.repository.adminReviewAccessRequest(
        '${application['id']}',
        decision: decision,
        note: reviewNote,
      ),
      switch (decision) {
        'approve' => 'Access approved.',
        'reject' => 'Access rejected.',
        'request_info' => 'Access information requested.',
        'revoke' => 'Access revoked.',
        _ => 'Access restored.',
      },
    );
  }

  Future<void> _runAction(
    Future<void> Function() action,
    String success,
  ) async {
    setState(() {
      _actionBusy = true;
    });
    try {
      await action();
      if (!mounted) return;
      AppFeedback.success(context, context.l10n.text(success));
      _reload(clearSelection: false);
      _refreshDetail();
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) {
        setState(() {
          _actionBusy = false;
        });
      }
    }
  }
}

class UserManagementHeader extends StatelessWidget {
  const UserManagementHeader({
    super.key,
    required this.metrics,
    required this.loading,
  });

  final AdminUserMetrics metrics;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final items = <(IconData, String, int)>[
      (Icons.groups_outlined, 'Total users', metrics.total),
      (Icons.check_circle_outline, 'Active users', metrics.active),
      (Icons.hourglass_top, 'Pending access', metrics.pendingAccess),
      (
        Icons.verified_user_outlined,
        'Pending verification',
        metrics.pendingVerification,
      ),
      (Icons.block_outlined, 'Suspended', metrics.suspended),
      (
        Icons.person_add_alt,
        'Recent registrations',
        metrics.recentlyRegistered,
      ),
    ];
    return Container(
      color: scheme.surfaceContainerLow,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: scheme.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(Icons.manage_accounts, color: scheme.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.text('User Management'),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      context.l10n.text(
                        'Accounts, access, verification and operational activity',
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              if (loading)
                const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
            ],
          ),
          const SizedBox(height: 14),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (var index = 0; index < items.length; index++) ...[
                  if (index > 0)
                    SizedBox(
                      height: 34,
                      child: VerticalDivider(color: scheme.outlineVariant),
                    ),
                  _HeaderMetric(
                    icon: items[index].$1,
                    label: items[index].$2,
                    value: items[index].$3,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderMetric extends StatelessWidget {
  const _HeaderMetric({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 10),
    child: Row(
      children: [
        Icon(icon, size: 18),
        const SizedBox(width: 7),
        Text(
          NumberFormat.compact().format(value),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(width: 5),
        Text(context.l10n.text(label)),
      ],
    ),
  );
}

class _ActiveUserFilterChips extends StatelessWidget {
  const _ActiveUserFilterChips({
    required this.filters,
    required this.onChanged,
  });

  final AdminUserFilters filters;
  final ValueChanged<AdminUserFilters> onChanged;

  @override
  Widget build(BuildContext context) {
    final chips = <({String label, VoidCallback clear})>[
      if (filters.normalizedSearch.isNotEmpty)
        (
          label: '${context.l10n.text('Search')}: ${filters.search}',
          clear: () => onChanged(filters.copyWith(search: '')),
        ),
      if (filters.roleId != null)
        (
          label:
              '${context.l10n.text('Role')}: ${context.l10n.roleName(filters.roleId!)}',
          clear: () => onChanged(filters.copyWith(clearRole: true)),
        ),
      if (filters.accountStatus != null)
        (
          label:
              '${context.l10n.text('Account status')}: ${context.l10n.text(_pretty(filters.accountStatus!))}',
          clear: () => onChanged(filters.copyWith(clearAccountStatus: true)),
        ),
      if (filters.accessStatus != null)
        (
          label:
              '${context.l10n.text('Access status')}: ${context.l10n.text(_pretty(filters.accessStatus!))}',
          clear: () => onChanged(filters.copyWith(clearAccessStatus: true)),
        ),
      if (filters.verificationStatus != null)
        (
          label:
              '${context.l10n.text('Verification status')}: ${context.l10n.text(_pretty(filters.verificationStatus!))}',
          clear: () =>
              onChanged(filters.copyWith(clearVerificationStatus: true)),
        ),
      if (filters.mt5Status != null)
        (
          label:
              '${context.l10n.text('MT5 status')}: ${context.l10n.text(_pretty(filters.mt5Status!))}',
          clear: () => onChanged(filters.copyWith(clearMt5Status: true)),
        ),
      if (filters.registeredAfter != null)
        (
          label:
              '${context.l10n.text('Registered after')}: ${_dateOnly(filters.registeredAfter!)}',
          clear: () => onChanged(filters.copyWith(clearRegisteredAfter: true)),
        ),
      if (filters.registeredBefore != null)
        (
          label:
              '${context.l10n.text('Registered before')}: ${_dateOnly(filters.registeredBefore!)}',
          clear: () => onChanged(filters.copyWith(clearRegisteredBefore: true)),
        ),
      if (filters.hasPendingApplication != null)
        (
          label: context.l10n.text('Has pending application'),
          clear: () =>
              onChanged(filters.copyWith(clearPendingApplication: true)),
        ),
      if (filters.hasUnresolvedTicket != null)
        (
          label: context.l10n.text('Has unresolved support ticket'),
          clear: () => onChanged(filters.copyWith(clearUnresolvedTicket: true)),
        ),
      if (filters.assignedProviderId != null)
        (
          label:
              '${context.l10n.text('Assigned provider')}: ${filters.assignedProviderId}',
          clear: () => onChanged(filters.copyWith(clearAssignedProvider: true)),
        ),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Row(
        children: [
          for (final chip in chips) ...[
            InputChip(
              label: Text(chip.label),
              onDeleted: chip.clear,
              deleteButtonTooltipMessage: context.l10n.text('Clear filter'),
            ),
            const SizedBox(width: 8),
          ],
          TextButton.icon(
            onPressed: () => onChanged(const AdminUserFilters()),
            icon: const Icon(Icons.filter_alt_off_outlined, size: 18),
            label: Text(context.l10n.text('Clear all')),
          ),
        ],
      ),
    );
  }
}

class _UserToolbar extends StatelessWidget {
  const _UserToolbar({
    required this.search,
    required this.filters,
    required this.sort,
    required this.onSearch,
    required this.onFilter,
    required this.onSort,
    required this.onRefresh,
  });

  final TextEditingController search;
  final AdminUserFilters filters;
  final AdminUserSort sort;
  final ValueChanged<String> onSearch;
  final VoidCallback onFilter;
  final ValueChanged<AdminUserSort?> onSort;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 660;
        final searchField = SearchBar(
          controller: search,
          hintText: context.l10n.text(
            'Search name, email, ID, referral or MT5 login',
          ),
          leading: const Icon(Icons.search),
          trailing: [
            if (search.text.isNotEmpty)
              IconButton(
                tooltip: context.l10n.text('Clear search'),
                onPressed: () {
                  search.clear();
                  onSearch('');
                },
                icon: const Icon(Icons.close),
              ),
          ],
          onChanged: onSearch,
          onSubmitted: onSearch,
        );
        final actions = Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Badge(
              isLabelVisible: filters.activeCount > 0,
              label: Text('${filters.activeCount}'),
              child: OutlinedButton.icon(
                onPressed: onFilter,
                icon: const Icon(Icons.filter_alt_outlined),
                label: Text(context.l10n.text('Filters')),
              ),
            ),
            const SizedBox(width: 8),
            DropdownButton<AdminUserSort>(
              value: sort,
              underline: const SizedBox.shrink(),
              onChanged: onSort,
              items: [
                for (final item in AdminUserSort.values)
                  DropdownMenuItem(
                    value: item,
                    child: Row(
                      children: [
                        const Icon(Icons.sort, size: 18),
                        const SizedBox(width: 6),
                        Text(context.l10n.text(item.label)),
                      ],
                    ),
                  ),
              ],
            ),
            IconButton(
              tooltip: context.l10n.text('Refresh'),
              onPressed: onRefresh,
              icon: const Icon(Icons.refresh),
            ),
          ],
        );
        if (compact) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              searchField,
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                reverse: true,
                child: actions,
              ),
            ],
          );
        }
        return Row(
          children: [
            Expanded(child: searchField),
            const SizedBox(width: 12),
            actions,
          ],
        );
      },
    ),
  );
}

class _UserDirectory extends StatelessWidget {
  const _UserDirectory({
    required this.page,
    required this.selectedId,
    required this.onRefresh,
    required this.onSelect,
    required this.onOpen,
    required this.onPrevious,
    required this.onNext,
    this.mobile = false,
  });

  final AdminUserPage page;
  final String? selectedId;
  final Future<void> Function() onRefresh;
  final ValueChanged<AdminUserRecord> onSelect;
  final ValueChanged<AdminUserRecord> onOpen;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final bool mobile;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final compactTable = constraints.maxWidth < 650;
      return Column(
        children: [
          if (!mobile) _UserTableHeader(compact: compactTable),
          Expanded(
            child: RefreshIndicator(
              onRefresh: onRefresh,
              child: ListView.separated(
                padding: EdgeInsets.fromLTRB(12, mobile ? 2 : 0, 12, 10),
                itemCount: page.users.length,
                separatorBuilder: (_, _) => const SizedBox(height: 4),
                itemBuilder: (context, index) {
                  final user = page.users[index];
                  return mobile
                      ? _MobileUserRow(user: user, onTap: () => onOpen(user))
                      : _DesktopUserRow(
                          user: user,
                          selected: user.id == selectedId,
                          compact: compactTable,
                          onTap: () => onSelect(user),
                          onOpen: () => onOpen(user),
                        );
                },
              ),
            ),
          ),
          _PaginationBar(
            page: page.page,
            pageSize: page.pageSize,
            total: page.total,
            onPrevious: onPrevious,
            onNext: onNext,
          ),
        ],
      );
    },
  );
}

class _UserTableHeader extends StatelessWidget {
  const _UserTableHeader({required this.compact});

  final bool compact;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 12),
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    color: Theme.of(context).colorScheme.surfaceContainerLow,
    child: Row(
      children: [
        Expanded(flex: 4, child: Text(context.l10n.text('User'))),
        Expanded(flex: 2, child: Text(context.l10n.text('Role'))),
        Expanded(flex: 2, child: Text(context.l10n.text('Access'))),
        if (!compact) ...[
          Expanded(flex: 2, child: Text(context.l10n.text('Verification'))),
          Expanded(flex: 2, child: Text(context.l10n.text('Last active'))),
        ],
        const SizedBox(width: 36),
      ],
    ),
  );
}

class _DesktopUserRow extends StatelessWidget {
  const _DesktopUserRow({
    required this.user,
    required this.selected,
    required this.compact,
    required this.onTap,
    required this.onOpen,
  });

  final AdminUserRecord user;
  final bool selected;
  final bool compact;
  final VoidCallback onTap;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: selected ? scheme.primaryContainer.withValues(alpha: .5) : null,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: [
              Expanded(flex: 4, child: UserSummaryRow(user: user)),
              Expanded(flex: 2, child: UserRoleBadge(roleId: user.roleId)),
              Expanded(
                flex: 2,
                child: UserStatusBadge(status: user.accessStatus),
              ),
              if (!compact) ...[
                Expanded(
                  flex: 2,
                  child: VerificationStatusBadge(
                    status: user.verificationStatus,
                  ),
                ),
                Expanded(
                  flex: 2,
                  child: Text(
                    _relativeDate(user.lastActiveAt, context),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
              IconButton(
                tooltip: context.l10n.text('Open full profile'),
                onPressed: onOpen,
                icon: const Icon(Icons.open_in_new, size: 18),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MobileUserRow extends StatelessWidget {
  const _MobileUserRow({required this.user, required this.onTap});

  final AdminUserRecord user;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: UserSummaryRow(user: user)),
                const Icon(Icons.chevron_right),
              ],
            ),
            const SizedBox(height: 9),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                UserRoleBadge(roleId: user.roleId),
                UserStatusBadge(status: user.accountStatus),
                VerificationStatusBadge(status: user.verificationStatus),
                Mt5ConnectionBadge(status: user.mt5Status),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class UserSummaryRow extends StatelessWidget {
  const UserSummaryRow({super.key, required this.user});

  final AdminUserRecord user;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      CircleAvatar(
        foregroundImage: user.avatarUrl.isEmpty
            ? null
            : NetworkImage(user.avatarUrl),
        child: Text(_initials(user.label)),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              user.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            Text(
              user.email,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    ],
  );
}

class UserStatusBadge extends StatelessWidget {
  const UserStatusBadge({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.replaceAll(RegExp(r'[-_\s]'), '').toLowerCase();
    final (icon, color) = switch (normalized) {
      'active' || 'approved' => (Icons.check_circle_outline, Colors.teal),
      'pending' ||
      'pendingreview' ||
      'submitted' => (Icons.schedule, Colors.orange),
      'suspended' ||
      'disabled' ||
      'inactive' ||
      'revoked' => (Icons.block, Theme.of(context).colorScheme.error),
      'rejected' => (Icons.cancel_outlined, Colors.red),
      'expired' => (Icons.timer_off_outlined, Colors.deepOrange),
      _ => (Icons.help_outline, Colors.blueGrey),
    };
    return _SemanticBadge(
      icon: icon,
      color: color,
      label: context.l10n.text(_pretty(status)),
    );
  }
}

class UserRoleBadge extends StatelessWidget {
  const UserRoleBadge({super.key, required this.roleId});

  final int roleId;

  @override
  Widget build(BuildContext context) => _SemanticBadge(
    icon: roleId <= 3 ? Icons.admin_panel_settings : Icons.badge_outlined,
    color: roleId <= 3 ? Colors.indigo : Colors.blueGrey,
    label: context.l10n.roleName(roleId),
  );
}

class VerificationStatusBadge extends StatelessWidget {
  const VerificationStatusBadge({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.replaceAll(RegExp(r'[-_\s]'), '').toLowerCase();
    return _SemanticBadge(
      icon: normalized == 'approved' || normalized == 'verified'
          ? Icons.verified_outlined
          : normalized.contains('pending') || normalized == 'submitted'
          ? Icons.hourglass_top
          : Icons.gpp_maybe_outlined,
      color: normalized == 'approved' || normalized == 'verified'
          ? Colors.teal
          : normalized.contains('pending') || normalized == 'submitted'
          ? Colors.orange
          : Colors.blueGrey,
      label: context.l10n.text(_pretty(status)),
    );
  }
}

class Mt5ConnectionBadge extends StatelessWidget {
  const Mt5ConnectionBadge({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final connected = status.toLowerCase() == 'connected';
    return _SemanticBadge(
      icon: connected ? Icons.link : Icons.link_off,
      color: connected ? Colors.teal : Colors.blueGrey,
      label: context.l10n.text(
        connected
            ? 'MT5 Connected'
            : status.toLowerCase() == 'none'
            ? 'No MT5 account'
            : 'MT5 Disconnected',
      ),
    );
  }
}

class _SemanticBadge extends StatelessWidget {
  const _SemanticBadge({
    required this.icon,
    required this.color,
    required this.label,
  });

  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(maxWidth: 170),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: color.withValues(alpha: .25)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

enum AdminUserAction {
  approveAccess,
  rejectAccess,
  requestMoreInformation,
  revokeAccess,
  restoreAccess,
  changeRole,
  suspend,
  restore,
  addNote,
}

class AdminActionMenu extends StatelessWidget {
  const AdminActionMenu({
    super.key,
    required this.actor,
    required this.user,
    required this.busy,
    required this.onSelected,
  });

  final AppUser actor;
  final AdminUserRecord user;
  final bool busy;
  final ValueChanged<AdminUserAction> onSelected;

  @override
  Widget build(BuildContext context) {
    final permission = PermissionService(actor);
    final actions = <AdminUserAction>[
      if (user.hasPendingApplication &&
          permission.can(AppPermission.accessApprove))
        AdminUserAction.approveAccess,
      if (user.hasPendingApplication &&
          permission.can(AppPermission.accessReject))
        AdminUserAction.rejectAccess,
      if (user.hasPendingApplication &&
          permission.can(AppPermission.accessReject))
        AdminUserAction.requestMoreInformation,
      if (user.accessStatus.toLowerCase() == 'approved' &&
          permission.can(AppPermission.accessApprove))
        AdminUserAction.revokeAccess,
      if (user.accessStatus.toLowerCase() == 'revoked' &&
          permission.can(AppPermission.accessApprove))
        AdminUserAction.restoreAccess,
      if (permission.can(AppPermission.usersAssignNonPrivilegedRole) ||
          permission.can(AppPermission.usersAssignPrivilegedRole))
        AdminUserAction.changeRole,
      if (permission.can(AppPermission.usersSuspend))
        user.isSuspended ? AdminUserAction.restore : AdminUserAction.suspend,
      if (permission.can(AppPermission.usersUpdateBasic))
        AdminUserAction.addNote,
    ];
    return PopupMenuButton<AdminUserAction>(
      enabled: !busy && actions.isNotEmpty,
      onSelected: onSelected,
      itemBuilder: (context) => [
        for (final action in actions)
          PopupMenuItem(
            value: action,
            child: Row(
              children: [
                Icon(_actionIcon(action), size: 18),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    context.l10n.text(_actionLabel(action)),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
      ],
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.secondaryContainer,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (busy)
              const SizedBox.square(
                dimension: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              const Icon(Icons.bolt, size: 18),
            const SizedBox(width: 8),
            Text(context.l10n.text('Actions')),
          ],
        ),
      ),
    );
  }
}

class UserDetailPanel extends StatelessWidget {
  const UserDetailPanel({
    super.key,
    required this.actor,
    required this.repository,
    required this.selected,
    required this.detail,
    required this.roles,
    required this.actionBusy,
    required this.onRefresh,
    required this.onAction,
    this.onCollapse,
  });

  final AppUser actor;
  final TradingRepository repository;
  final AdminUserRecord selected;
  final Future<AdminUserDetail> detail;
  final List<Map<String, dynamic>> roles;
  final bool actionBusy;
  final VoidCallback onRefresh;
  final ValueChanged<AdminUserAction> onAction;
  final VoidCallback? onCollapse;

  @override
  Widget build(BuildContext context) => FutureBuilder<AdminUserDetail>(
    future: detail,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Center(child: CircularProgressIndicator());
      }
      if (snapshot.hasError || snapshot.data == null) {
        return _UserManagementError(error: snapshot.error, onRetry: onRefresh);
      }
      final data = snapshot.data!;
      return DefaultTabController(
        length: 8,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 10, 10),
              child: Row(
                children: [
                  Expanded(child: UserSummaryRow(user: data.user)),
                  if (onCollapse != null)
                    IconButton(
                      tooltip: context.l10n.text('Collapse details'),
                      onPressed: onCollapse,
                      icon: const Icon(Icons.chevron_right),
                    ),
                  IconButton(
                    tooltip: context.l10n.text('Refresh'),
                    onPressed: onRefresh,
                    icon: const Icon(Icons.refresh),
                  ),
                  AdminActionMenu(
                    actor: actor,
                    user: data.user,
                    busy: actionBusy,
                    onSelected: onAction,
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  UserRoleBadge(roleId: data.user.roleId),
                  UserStatusBadge(status: data.user.accountStatus),
                  UserStatusBadge(status: data.user.accessStatus),
                  Mt5ConnectionBadge(status: data.user.mt5Status),
                ],
              ),
            ),
            const SizedBox(height: 8),
            TabBar(
              isScrollable: true,
              tabs: [
                for (final label in const [
                  'Overview',
                  'Access',
                  'Roles',
                  'Trading & MT5',
                  'Referral',
                  'Activity',
                  'Support',
                  'Audit log',
                ])
                  Tab(text: context.l10n.text(label)),
              ],
            ),
            Expanded(
              child: TabBarView(
                children: [
                  _OverviewTab(detail: data),
                  _AccessTab(detail: data),
                  _RolesTab(detail: data),
                  _TradingTab(detail: data),
                  _ReferralTab(detail: data),
                  _RecordListTab(
                    records: data.activities,
                    emptyLabel: 'No recent activity',
                    icon: Icons.timeline,
                  ),
                  _SupportTab(
                    detail: data,
                    actor: actor,
                    repository: repository,
                  ),
                  _AuditTab(records: data.auditLogs),
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.detail});

  final AdminUserDetail detail;

  @override
  Widget build(BuildContext context) {
    final user = detail.user;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        UserDetailSection(
          title: 'Profile overview',
          icon: Icons.person_outline,
          children: [
            _DetailField(label: 'User ID', value: user.id),
            _DetailField(label: 'Email', value: user.email),
            _DetailField(label: 'Phone', value: user.phone),
            _DetailField(label: 'Registered', value: _fullDate(user.createdAt)),
            _DetailField(
              label: 'Last active',
              value: _fullDate(user.lastActiveAt),
            ),
            _DetailField(
              label: 'Preferred language',
              value: user.preferredLanguage,
            ),
            _DetailField(
              label: 'Email verification',
              value: user.emailVerified ? 'Verified' : 'Unverified',
            ),
          ],
        ),
        const SizedBox(height: 12),
        UserDetailSection(
          title: 'Administrative notes',
          icon: Icons.sticky_note_2_outlined,
          children: detail.notes.isEmpty
              ? [Text(context.l10n.text('No administrative notes'))]
              : [
                  for (final note in detail.notes.take(5))
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.notes, size: 19),
                      title: Text('${note['note'] ?? ''}'),
                      subtitle: Text(
                        _fullDate(firestoreDate(note['created_at'])),
                      ),
                    ),
                ],
        ),
      ],
    );
  }
}

class _AccessTab extends StatelessWidget {
  const _AccessTab({required this.detail});

  final AdminUserDetail detail;

  @override
  Widget build(BuildContext context) {
    if (detail.accessApplications.isEmpty) {
      return Center(child: Text(context.l10n.text('No access applications')));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: detail.accessApplications.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final application = detail.accessApplications[index];
        final attachment =
            '${application['document_url'] ?? application['attachment_url'] ?? ''}'
                .trim();
        return UserDetailSection(
          title: 'Access and verification',
          icon: Icons.verified_user_outlined,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                UserStatusBadge(
                  status:
                      '${application['status'] ?? application['access_status'] ?? 'pending'}',
                ),
                VerificationStatusBadge(
                  status:
                      '${application['broker_verification_status'] ?? application['verification_status'] ?? 'not_submitted'}',
                ),
              ],
            ),
            const SizedBox(height: 8),
            _DetailField(
              label: 'Application type',
              value: '${application['application_type'] ?? '-'}',
            ),
            _DetailField(
              label: 'Submitted',
              value: _fullDate(
                firestoreDate(
                  application['submitted_at'] ?? application['created_at'],
                ),
              ),
            ),
            _DetailField(
              label: 'Broker verification',
              value:
                  '${application['broker_verification_status'] ?? application['verification_status'] ?? '-'}',
            ),
            _DetailField(
              label: 'Position verification',
              value: '${application['position_verification_status'] ?? '-'}',
            ),
            _DetailField(
              label: 'Reviewed by',
              value:
                  '${application['reviewed_by_name'] ?? application['reviewed_by'] ?? '-'}',
            ),
            _DetailField(
              label: 'Review time',
              value: _fullDate(firestoreDate(application['reviewed_at'])),
            ),
            if ('${application['rejection_reason'] ?? application['rejection_note'] ?? ''}'
                .trim()
                .isNotEmpty)
              _DetailField(
                label: 'Rejection reason',
                value:
                    '${application['rejection_reason'] ?? application['rejection_note']}',
              ),
            if (application['expires_at'] != null)
              _DetailField(
                label: 'Expiry date',
                value: _fullDate(firestoreDate(application['expires_at'])),
              ),
            if (attachment.isNotEmpty)
              Align(
                alignment: Alignment.centerLeft,
                child: OutlinedButton.icon(
                  onPressed: () => _openProtectedDocument(context, attachment),
                  icon: const Icon(Icons.open_in_new, size: 18),
                  label: Text(context.l10n.text('Open verification file')),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _RolesTab extends StatelessWidget {
  const _RolesTab({required this.detail});

  final AdminUserDetail detail;

  @override
  Widget build(BuildContext context) {
    final roles = detail.user.roleIds.isEmpty
        ? {detail.user.roleId}
        : detail.user.roleIds;
    final overrides = _permissionOverrides(detail.user.raw);
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        UserDetailSection(
          title: 'Roles and permissions',
          icon: Icons.policy_outlined,
          children: [
            for (final role in roles)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: UserRoleBadge(roleId: role),
              ),
            const Divider(),
            if (overrides.isEmpty)
              Text(context.l10n.text('No explicit permission overrides'))
            else
              for (final entry in overrides.entries)
                _DetailField(
                  label: entry.key,
                  value: entry.value ? 'Allowed' : 'Denied',
                ),
            if (detail.auditLogs.any(
              (record) => '${record['action']}'.toLowerCase().contains('role'),
            )) ...[
              const Divider(),
              Text(
                context.l10n.text('Recent role assignment history'),
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              for (final record
                  in detail.auditLogs
                      .where(
                        (item) =>
                            '${item['action']}'.toLowerCase().contains('role'),
                      )
                      .take(5))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.history, size: 19),
                  title: Text(
                    context.l10n.text(_pretty('${record['action']}')),
                  ),
                  subtitle: Text(
                    _fullDate(
                      firestoreDate(
                        record['created_at'] ?? record['createdAt'],
                      ),
                    ),
                  ),
                ),
            ],
          ],
        ),
      ],
    );
  }
}

class _TradingTab extends StatelessWidget {
  const _TradingTab({required this.detail});

  final AdminUserDetail detail;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      UserDetailSection(
        title: 'Trading statistics summary',
        icon: Icons.query_stats_outlined,
        children: [
          _DetailField(
            label: 'Total trades',
            value:
                '${detail.user.raw['total_trades'] ?? detail.user.raw['trading_activity_count'] ?? '-'}',
          ),
          _DetailField(
            label: 'Win rate',
            value: '${detail.user.raw['win_rate'] ?? '-'}',
          ),
          _DetailField(
            label: 'Journal data source',
            value: '${detail.user.raw['journal_data_source'] ?? '-'}',
          ),
          _DetailField(
            label: 'Prop Firm status',
            value: '${detail.user.raw['prop_firm_status'] ?? '-'}',
          ),
        ],
      ),
      const SizedBox(height: 12),
      UserDetailSection(
        title: 'MT5 connection health',
        icon: Icons.monitor_heart_outlined,
        children: detail.mt5Accounts.isEmpty
            ? [Text(context.l10n.text('No MT5 accounts'))]
            : [
                for (final account in detail.mt5Accounts)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      firestoreBool(account['is_active'])
                          ? Icons.link
                          : Icons.link_off,
                    ),
                    title: Text(
                      '${account['broker_name'] ?? account['server'] ?? 'MT5'}',
                    ),
                    subtitle: Text(
                      '${context.l10n.text('Login')}: ${account['login'] ?? account['mt5_login'] ?? '-'}\n'
                      '${context.l10n.text('Account type')}: ${account['account_type'] ?? account['type'] ?? '-'}\n'
                      '${context.l10n.text('Connection status')}: ${account['connection_status'] ?? account['status'] ?? '-'}\n'
                      '${context.l10n.text('Last synchronized')}: ${_fullDate(firestoreDate(account['last_synced_at'] ?? account['updated_at']))}'
                      '${('${account['last_sync_error'] ?? ''}').trim().isEmpty ? '' : '\n${context.l10n.text('Recent sync error')}: ${account['last_sync_error']}'}',
                    ),
                    trailing: firestoreBool(account['is_main'])
                        ? _SemanticBadge(
                            icon: Icons.star,
                            color: Colors.amber.shade800,
                            label: context.l10n.text('Main'),
                          )
                        : null,
                  ),
              ],
      ),
    ],
  );
}

class _ReferralTab extends StatelessWidget {
  const _ReferralTab({required this.detail});

  final AdminUserDetail detail;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      UserDetailSection(
        title: 'Referral network',
        icon: Icons.account_tree_outlined,
        children: [
          _DetailField(
            label: 'Relationship date',
            value: _fullDate(
              firestoreDate(
                detail.user.raw['referral_registered_at'] ??
                    detail.user.raw['created_at'],
              ),
            ),
          ),
          _DetailField(label: 'Referral code', value: detail.user.referralCode),
          _DetailField(label: 'Referred by', value: detail.user.uplineName),
          _DetailField(
            label: 'Assigned signal provider',
            value: detail.user.assignedProviderName.isNotEmpty
                ? detail.user.assignedProviderName
                : detail.user.assignedProviderId,
          ),
          const Divider(),
          Text(
            context.l10n.text('Direct referrals'),
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          if (detail.referrals.isEmpty)
            Text(context.l10n.text('No direct referrals'))
          else
            for (final referral in detail.referrals.take(20))
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.person_add_alt_1, size: 19),
                title: Text(
                  '${referral['referred_user_name'] ?? referral['user_name'] ?? referral['user_id'] ?? referral['id']}',
                ),
                subtitle: Text(
                  _fullDate(
                    firestoreDate(
                      referral['created_at'] ?? referral['registered_at'],
                    ),
                  ),
                ),
              ),
          const SizedBox(height: 8),
          Text(
            context.l10n.text(
              'Referral corrections require elevated permission, confirmation and an audit reason.',
            ),
          ),
        ],
      ),
    ],
  );
}

class _SupportTab extends StatelessWidget {
  const _SupportTab({
    required this.detail,
    required this.actor,
    required this.repository,
  });

  final AdminUserDetail detail;
  final AppUser actor;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      UserDetailSection(
        title: 'Support tickets',
        icon: Icons.support_agent_outlined,
        children: [
          if (detail.supportTickets.isEmpty)
            Text(context.l10n.text('No support tickets'))
          else
            for (final ticket in detail.supportTickets)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${ticket['subject'] ?? ticket['title'] ?? '-'}'),
                subtitle: Text('${ticket['status'] ?? ''}'),
              ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      SupportTicketsScreen(user: actor, repository: repository),
                ),
              ),
              icon: const Icon(Icons.open_in_new, size: 18),
              label: Text(context.l10n.text('Open Support Tickets')),
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      UserDetailSection(
        title: 'Appointments',
        icon: Icons.calendar_month_outlined,
        children: [
          if (detail.appointments.isEmpty)
            Text(context.l10n.text('No appointments'))
          else
            for (final appointment in detail.appointments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${appointment['subject'] ?? 'Appointment'}'),
                subtitle: Text(
                  '${appointment['status'] ?? ''} · ${_fullDate(firestoreDate(appointment['scheduled_start_at']))}',
                ),
              ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => TradingAppointmentsScreen(
                    user: actor,
                    repository: repository,
                  ),
                ),
              ),
              icon: const Icon(Icons.open_in_new, size: 18),
              label: Text(context.l10n.text('Open Appointments')),
            ),
          ),
        ],
      ),
    ],
  );
}

class _AuditTab extends StatelessWidget {
  const _AuditTab({required this.records});

  final List<Map<String, dynamic>> records;

  @override
  Widget build(BuildContext context) {
    if (records.isEmpty) {
      return Center(child: Text(context.l10n.text('No audit records')));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: records.length,
      itemBuilder: (context, index) {
        final record = records[index];
        return AuditTimelineEntry(
          action: '${record['action'] ?? 'Administrative update'}',
          actor: '${record['actorName'] ?? record['actorUserId'] ?? ''}',
          actorRole:
              '${record['actorRole'] ?? record['actor_role'] ?? record['actorRoleIds'] ?? record['actor_role_ids'] ?? ''}',
          reason: '${record['reason'] ?? ''}',
          before: record['before'] ?? record['old_values'],
          after: record['after'] ?? record['new_values'],
          source: '${record['source'] ?? ''}',
          relatedReference:
              '${record['relatedDocumentReference'] ?? record['related_document_reference'] ?? record['resourceId'] ?? ''}',
          createdAt: firestoreDate(record['created_at'] ?? record['createdAt']),
          last: index == records.length - 1,
        );
      },
    );
  }
}

class _RecordListTab extends StatelessWidget {
  const _RecordListTab({
    required this.records,
    required this.emptyLabel,
    required this.icon,
  });

  final List<Map<String, dynamic>> records;
  final String emptyLabel;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    if (records.isEmpty) {
      return Center(child: Text(context.l10n.text(emptyLabel)));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: records.length,
      itemBuilder: (context, index) {
        final item = records[index];
        final title =
            '${item['title'] ?? item['action'] ?? item['application_type'] ?? item['status'] ?? 'Activity'}';
        final date = firestoreDate(
          item['created_at'] ?? item['updated_at'] ?? item['submitted_at'],
        );
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Icon(icon),
          title: Text(context.l10n.text(_pretty(title))),
          subtitle: Text(_fullDate(date)),
          trailing: item['status'] == null
              ? null
              : UserStatusBadge(status: '${item['status']}'),
        );
      },
    );
  }
}

class UserDetailSection extends StatelessWidget {
  const UserDetailSection({
    super.key,
    required this.title,
    required this.icon,
    required this.children,
  });

  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
    ),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  context.l10n.text(title),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const Divider(height: 22),
          ...children,
        ],
      ),
    ),
  );
}

class AuditTimelineEntry extends StatelessWidget {
  const AuditTimelineEntry({
    super.key,
    required this.action,
    required this.actor,
    this.actorRole = '',
    required this.reason,
    this.before,
    this.after,
    this.source = '',
    this.relatedReference = '',
    required this.createdAt,
    required this.last,
  });

  final String action;
  final String actor;
  final String actorRole;
  final String reason;
  final Object? before;
  final Object? after;
  final String source;
  final String relatedReference;
  final DateTime? createdAt;
  final bool last;

  @override
  Widget build(BuildContext context) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            CircleAvatar(radius: 12, child: Icon(_auditIcon(action), size: 14)),
            if (!last) Expanded(child: VerticalDivider(width: 1)),
          ],
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text(_pretty(action)),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                if (actor.isNotEmpty)
                  Text(actorRole.isEmpty ? actor : '$actor · $actorRole'),
                if (reason.isNotEmpty) Text(reason),
                if (_safeAuditValue(before).isNotEmpty)
                  Text(
                    '${context.l10n.text('Previous value')}: ${_safeAuditValue(before)}',
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                if (_safeAuditValue(after).isNotEmpty)
                  Text(
                    '${context.l10n.text('New value')}: ${_safeAuditValue(after)}',
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                if (source.isNotEmpty || relatedReference.isNotEmpty)
                  Text(
                    [
                      if (source.isNotEmpty)
                        '${context.l10n.text('Source')}: $source',
                      if (relatedReference.isNotEmpty)
                        '${context.l10n.text('Reference')}: $relatedReference',
                    ].join(' · '),
                    style: Theme.of(context).textTheme.bodySmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                Text(
                  _fullDate(createdAt),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class UserFilterPanel extends StatefulWidget {
  const UserFilterPanel({
    super.key,
    required this.initial,
    required this.roles,
  });

  final AdminUserFilters initial;
  final List<Map<String, dynamic>> roles;

  @override
  State<UserFilterPanel> createState() => _UserFilterPanelState();
}

class _UserFilterPanelState extends State<UserFilterPanel> {
  late int? _role = widget.initial.roleId;
  late String? _account = widget.initial.accountStatus;
  late String? _access = widget.initial.accessStatus;
  late String? _verification = widget.initial.verificationStatus;
  late String? _mt5 = widget.initial.mt5Status;
  late bool? _pending = widget.initial.hasPendingApplication;
  late bool? _ticket = widget.initial.hasUnresolvedTicket;
  late DateTime? _registeredAfter = widget.initial.registeredAfter;
  late DateTime? _registeredBefore = widget.initial.registeredBefore;
  late final TextEditingController _assignedProvider = TextEditingController(
    text: widget.initial.assignedProviderId,
  );

  @override
  void dispose() {
    _assignedProvider.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        20 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                context.l10n.text('Filter users'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _filterField(
                    DropdownButtonFormField<int?>(
                      initialValue: _role,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Role'),
                      ),
                      items: [
                        DropdownMenuItem(
                          value: null,
                          child: Text(context.l10n.text('All roles')),
                        ),
                        for (final role in widget.roles)
                          DropdownMenuItem(
                            value: firestoreInt(role['id']),
                            child: Text(
                              context.l10n.roleName(firestoreInt(role['id'])),
                            ),
                          ),
                      ],
                      onChanged: (value) => setState(() => _role = value),
                    ),
                  ),
                  _filterField(
                    _stringFilter(
                      label: 'Account status',
                      value: _account,
                      values: const ['active', 'suspended', 'disabled'],
                      onChanged: (value) => setState(() => _account = value),
                    ),
                  ),
                  _filterField(
                    _stringFilter(
                      label: 'Access status',
                      value: _access,
                      values: const [
                        'notSubmitted',
                        'pendingReview',
                        'approved',
                        'rejected',
                        'revoked',
                      ],
                      onChanged: (value) => setState(() => _access = value),
                    ),
                  ),
                  _filterField(
                    _stringFilter(
                      label: 'Verification status',
                      value: _verification,
                      values: const [
                        'not_submitted',
                        'pending',
                        'verified',
                        'rejected',
                      ],
                      onChanged: (value) =>
                          setState(() => _verification = value),
                    ),
                  ),
                  _filterField(
                    _stringFilter(
                      label: 'MT5 status',
                      value: _mt5,
                      values: const ['connected', 'disconnected', 'none'],
                      onChanged: (value) => setState(() => _mt5 = value),
                    ),
                  ),
                  _filterField(
                    _dateFilter(
                      label: 'Registered after',
                      value: _registeredAfter,
                      onChanged: (value) =>
                          setState(() => _registeredAfter = value),
                    ),
                  ),
                  _filterField(
                    _dateFilter(
                      label: 'Registered before',
                      value: _registeredBefore,
                      onChanged: (value) =>
                          setState(() => _registeredBefore = value),
                    ),
                  ),
                  _filterField(
                    TextField(
                      controller: _assignedProvider,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Assigned provider ID'),
                      ),
                    ),
                  ),
                ],
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                tristate: true,
                value: _pending,
                onChanged: (value) => setState(() => _pending = value),
                title: Text(context.l10n.text('Has pending application')),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                tristate: true,
                value: _ticket,
                onChanged: (value) => setState(() => _ticket = value),
                title: Text(context.l10n.text('Has unresolved support ticket')),
              ),
              const SizedBox(height: 12),
              Wrap(
                alignment: WrapAlignment.end,
                spacing: 8,
                runSpacing: 8,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(
                      context,
                      AdminUserFilters(search: widget.initial.search),
                    ),
                    child: Text(context.l10n.text('Clear all')),
                  ),
                  OutlinedButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text(context.l10n.text('Cancel')),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.pop(
                      context,
                      AdminUserFilters(
                        search: widget.initial.search,
                        roleId: _role,
                        accountStatus: _account,
                        accessStatus: _access,
                        verificationStatus: _verification,
                        mt5Status: _mt5,
                        registeredAfter: _registeredAfter,
                        registeredBefore: _registeredBefore,
                        hasPendingApplication: _pending,
                        hasUnresolvedTicket: _ticket,
                        assignedProviderId:
                            _assignedProvider.text.trim().isEmpty
                            ? null
                            : _assignedProvider.text.trim(),
                      ),
                    ),
                    child: Text(context.l10n.text('Apply filters')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    ),
  );

  Widget _filterField(Widget child) => SizedBox(width: 220, child: child);

  Widget _dateFilter({
    required String label,
    required DateTime? value,
    required ValueChanged<DateTime?> onChanged,
  }) => InkWell(
    borderRadius: BorderRadius.circular(12),
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        initialDate: value ?? DateTime.now(),
        firstDate: DateTime(2000),
        lastDate: DateTime.now(),
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        suffixIcon: value == null
            ? const Icon(Icons.calendar_month_outlined)
            : IconButton(
                tooltip: context.l10n.text('Clear filter'),
                onPressed: () => onChanged(null),
                icon: const Icon(Icons.close),
              ),
      ),
      child: Text(value == null ? context.l10n.text('Any') : _dateOnly(value)),
    ),
  );

  Widget _stringFilter({
    required String label,
    required String? value,
    required List<String> values,
    required ValueChanged<String?> onChanged,
  }) => DropdownButtonFormField<String?>(
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(labelText: context.l10n.text(label)),
    items: [
      DropdownMenuItem(value: null, child: Text(context.l10n.text('Any'))),
      for (final item in values)
        DropdownMenuItem(
          value: item,
          child: Text(context.l10n.text(_pretty(item))),
        ),
    ],
    onChanged: onChanged,
  );
}

class _PaginationBar extends StatelessWidget {
  const _PaginationBar({
    required this.page,
    required this.pageSize,
    required this.total,
    required this.onPrevious,
    required this.onNext,
  });

  final int page;
  final int pageSize;
  final int total;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final first = total == 0 ? 0 : (page - 1) * pageSize + 1;
    final last = (page * pageSize).clamp(0, total);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              context.l10n.text('{first}–{last} of {total}', {
                'first': first,
                'last': last,
                'total': total,
              }),
            ),
          ),
          IconButton(
            tooltip: context.l10n.text('Previous page'),
            onPressed: onPrevious,
            icon: const Icon(Icons.chevron_left),
          ),
          Text('${context.l10n.text('Page')} $page'),
          IconButton(
            tooltip: context.l10n.text('Next page'),
            onPressed: onNext,
            icon: const Icon(Icons.chevron_right),
          ),
        ],
      ),
    );
  }
}

class _DetailField extends StatelessWidget {
  const _DetailField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 128,
          child: Text(
            context.l10n.text(label),
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ),
        Expanded(
          child: SelectableText(
            value.trim().isEmpty ? '—' : context.l10n.text(value),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _DialogImpactRow extends StatelessWidget {
  const _DialogImpactRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(child: Text(context.l10n.text(label))),
      Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
    ],
  );
}

class _SelectUserPrompt extends StatelessWidget {
  const _SelectUserPrompt();

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.person_search_outlined, size: 44),
          const SizedBox(height: 10),
          Text(context.l10n.text('Select a user to inspect their account')),
        ],
      ),
    ),
  );
}

class _UserListSkeleton extends StatelessWidget {
  const _UserListSkeleton();

  @override
  Widget build(BuildContext context) => ListView.builder(
    padding: const EdgeInsets.all(16),
    itemCount: 8,
    itemBuilder: (context, index) => Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            const CircleAvatar(),
            const SizedBox(width: 12),
            Expanded(
              child: LinearProgressIndicator(
                minHeight: 10,
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _UserManagementEmpty extends StatelessWidget {
  const _UserManagementEmpty({required this.hasFilters});

  final bool hasFilters;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.people_outline, size: 48),
          const SizedBox(height: 10),
          Text(
            context.l10n.text(
              hasFilters ? 'No users match these filters' : 'No users found',
            ),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 5),
          Text(
            context.l10n.text(
              hasFilters
                  ? 'Clear one or more filters and try again.'
                  : 'Registered accounts will appear here.',
            ),
          ),
        ],
      ),
    ),
  );
}

class _UserManagementError extends StatelessWidget {
  const _UserManagementError({required Object? error, required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 46,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 10),
          Text(
            context.l10n.text('Unable to load user management data.'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

Future<void> _openProtectedDocument(BuildContext context, String value) async {
  final uri = Uri.tryParse(value.trim());
  if (uri == null || !const {'https', 'http'}.contains(uri.scheme)) {
    AppFeedback.error(
      context,
      context.l10n.text('The verification file link is unavailable.'),
    );
    return;
  }
  try {
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && context.mounted) {
      AppFeedback.error(
        context,
        context.l10n.text('Unable to open the verification file.'),
      );
    }
  } catch (_) {
    if (context.mounted) {
      AppFeedback.error(
        context,
        context.l10n.text('Unable to open the verification file.'),
      );
    }
  }
}

String _safeAuditValue(Object? value) {
  if (value == null) return '';
  if (value is Map) {
    final parts = <String>[];
    for (final entry in value.entries) {
      final key = '${entry.key}'.toLowerCase();
      if (const {
        'password',
        'token',
        'secret',
        'credential',
        'private_key',
      }.any(key.contains)) {
        continue;
      }
      parts.add('${_pretty('${entry.key}')}: ${entry.value}');
    }
    final result = parts.join(', ');
    return result.length <= 320 ? result : '${result.substring(0, 317)}…';
  }
  final result = '$value'.trim();
  return result.length <= 320 ? result : '${result.substring(0, 317)}…';
}

Map<String, bool> _permissionOverrides(Map<String, dynamic> data) {
  final raw = data['permission_overrides'] ?? data['permissionOverrides'];
  if (raw is! Map) return const {};
  return raw.map(
    (key, value) =>
        MapEntry('$key', value == true || '$value'.toLowerCase() == 'allow'),
  );
}

String _actionLabel(AdminUserAction action) => switch (action) {
  AdminUserAction.approveAccess => 'Approve access',
  AdminUserAction.rejectAccess => 'Reject access',
  AdminUserAction.requestMoreInformation => 'Request more information',
  AdminUserAction.revokeAccess => 'Revoke access',
  AdminUserAction.restoreAccess => 'Restore access',
  AdminUserAction.changeRole => 'Change role',
  AdminUserAction.suspend => 'Suspend account',
  AdminUserAction.restore => 'Restore account',
  AdminUserAction.addNote => 'Add internal note',
};

IconData _actionIcon(AdminUserAction action) => switch (action) {
  AdminUserAction.approveAccess => Icons.verified_user_outlined,
  AdminUserAction.rejectAccess => Icons.gpp_bad_outlined,
  AdminUserAction.requestMoreInformation => Icons.contact_support_outlined,
  AdminUserAction.revokeAccess => Icons.lock_person_outlined,
  AdminUserAction.restoreAccess => Icons.lock_open_outlined,
  AdminUserAction.changeRole => Icons.manage_accounts_outlined,
  AdminUserAction.suspend => Icons.block,
  AdminUserAction.restore => Icons.restore,
  AdminUserAction.addNote => Icons.note_add_outlined,
};

IconData _auditIcon(String action) {
  final normalized = action.toLowerCase();
  if (normalized.contains('role')) return Icons.badge_outlined;
  if (normalized.contains('suspend')) return Icons.block;
  if (normalized.contains('restore')) return Icons.restore;
  if (normalized.contains('access')) return Icons.verified_user_outlined;
  return Icons.history;
}

String _pretty(String value) => value
    .replaceAllMapped(
      RegExp(r'([a-z])([A-Z])'),
      (match) => '${match[1]} ${match[2]}',
    )
    .replaceAll(RegExp(r'[-_]'), ' ')
    .split(' ')
    .where((word) => word.isNotEmpty)
    .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
    .join(' ');

String _initials(String value) {
  final words = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .take(2);
  final result = words.map((word) => word[0].toUpperCase()).join();
  return result.isEmpty ? 'HC' : result;
}

String _fullDate(DateTime? value) =>
    value == null ? '—' : DateFormat.yMMMd().add_jm().format(value.toLocal());

String _dateOnly(DateTime value) => DateFormat.yMMMd().format(value.toLocal());

String _relativeDate(DateTime? value, BuildContext context) {
  if (value == null) return context.l10n.text('Never');
  final difference = DateTime.now().difference(value);
  if (difference.inMinutes < 2) return context.l10n.text('Just now');
  if (difference.inHours < 1) {
    return context.l10n.text('{count} min ago', {
      'count': difference.inMinutes,
    });
  }
  if (difference.inDays < 1) {
    return context.l10n.text('{count} hr ago', {'count': difference.inHours});
  }
  if (difference.inDays < 14) {
    return context.l10n.text('{count} d ago', {'count': difference.inDays});
  }
  return DateFormat.yMMMd().format(value);
}
