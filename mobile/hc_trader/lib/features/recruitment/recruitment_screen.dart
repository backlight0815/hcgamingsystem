import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';

class RecruitmentScreen extends StatefulWidget {
  const RecruitmentScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<RecruitmentScreen> createState() => _RecruitmentScreenState();
}

class _RecruitmentScreenState extends State<RecruitmentScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() =>
      widget.repository.recruitmentWorkspace(
        uid: widget.user.uid,
        roleId: widget.user.roleId,
        roleIds: widget.user.effectiveRoleIds,
      );

  void _reload() {
    final nextFuture = _load();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.text('Recruitment'))),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _RecruitmentError(error: snapshot.error, onRetry: _reload);
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          final code = '${data['referral_code'] ?? ''}'.trim();
          final weeklyCode = '${data['weekly_referral_code'] ?? ''}'.trim();
          final weeklyExpires = '${data['weekly_referral_expires_at'] ?? ''}'
              .trim();
          final weeklyCodes =
              (data['weekly_referral_codes'] as Map? ?? const {}).map(
                (key, value) => MapEntry(
                  '$key',
                  value is Map
                      ? Map<String, dynamic>.from(value)
                      : const <String, dynamic>{},
                ),
              );
          final roles = (data['allowed_roles'] as List? ?? const [])
              .map((role) => role is int ? role : int.tryParse('$role') ?? 0)
              .where((role) => role > 0)
              .toList(growable: false);
          final downlines = (data['downlines'] as List? ?? const [])
              .whereType<Map>()
              .map(Map<String, dynamic>.from)
              .toList(growable: false);
          final downlineLoadLimited = data['downline_load_limited'] == true;
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _ReferralCodeCard(
                  code: code,
                  weeklyCode: weeklyCode,
                  weeklyExpires: weeklyExpires,
                  weeklyCodes: weeklyCodes,
                  allowedRoles: roles,
                ),
                const SizedBox(height: 14),
                _RecruitmentNotice(
                  message: l10n.text(
                    'Referral codes are entered only by invited users while creating a new account. Share a code above; existing accounts cannot be reassigned.',
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  l10n.text('Your downline'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                if (downlineLoadLimited) ...[
                  _RecruitmentNotice(
                    message: l10n.text(
                      'Downline list is temporarily unavailable. Invited users can still register with your shared code.',
                    ),
                  ),
                  const SizedBox(height: 8),
                ],
                if (downlines.isEmpty)
                  EmptyState(
                    icon: Icons.group_add_outlined,
                    title: 'No downline users yet.',
                    action: Text(
                      l10n.text(
                        'Invited users will appear here after registration.',
                      ),
                      textAlign: TextAlign.center,
                    ),
                  )
                else
                  for (final downline in downlines)
                    _DownlineTile(downline: downline),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ReferralCodeCard extends StatelessWidget {
  const _ReferralCodeCard({
    required this.code,
    required this.weeklyCode,
    required this.weeklyExpires,
    required this.weeklyCodes,
    required this.allowedRoles,
  });

  final String code;
  final String weeklyCode;
  final String weeklyExpires;
  final Map<String, Map<String, dynamic>> weeklyCodes;
  final List<int> allowedRoles;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.text('Invite traders and lead clients'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(l10n.text('Share your referral code with invited users.')),
            const SizedBox(height: 16),
            if (weeklyCodes.isEmpty)
              _ReferralCodeBox(
                label: l10n.text('Weekly referral code'),
                code: weeklyCode,
                detail: weeklyExpires.isEmpty
                    ? ''
                    : l10n.text('Resets after {date}', {
                        'date': _compactDate(weeklyExpires),
                      }),
              )
            else
              for (final role in allowedRoles) ...[
                _ReferralCodeBox(
                  label:
                      '${l10n.roleName(role)} - ${l10n.text('Weekly referral code')}',
                  code: '${weeklyCodes['$role']?['code'] ?? ''}',
                  detail: l10n.text('Resets after {date}', {
                    'date': _compactDate(
                      '${weeklyCodes['$role']?['expiresAt'] ?? ''}',
                    ),
                  }),
                ),
                const SizedBox(height: 8),
              ],
            const SizedBox(height: 10),
            _ReferralCodeBox(
              label: l10n.text('Permanent referral code'),
              code: code,
            ),
            if (allowedRoles.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                l10n.text('Allowed invite roles'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final role in allowedRoles)
                    Chip(label: Text(l10n.roleName(role))),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ReferralCodeBox extends StatelessWidget {
  const _ReferralCodeBox({
    required this.label,
    required this.code,
    this.detail = '',
  });

  final String label;
  final String code;
  final String detail;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  code.isEmpty ? l10n.text('Unavailable') : code,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (detail.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(detail, style: Theme.of(context).textTheme.bodySmall),
                ],
              ],
            ),
          ),
          IconButton.filledTonal(
            tooltip: l10n.text('Copy code'),
            onPressed: code.isEmpty
                ? null
                : () async {
                    await Clipboard.setData(ClipboardData(text: code));
                    if (context.mounted) {
                      AppFeedback.success(context, 'Referral code copied.');
                    }
                  },
            icon: const Icon(Icons.copy_outlined),
          ),
        ],
      ),
    );
  }
}

class _RecruitmentNotice extends StatelessWidget {
  const _RecruitmentNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.info_outline, size: 18),
            const SizedBox(width: 8),
            Expanded(child: Text(message)),
          ],
        ),
      ),
    );
  }
}

class _DownlineTile extends StatelessWidget {
  const _DownlineTile({required this.downline});

  final Map<String, dynamic> downline;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final name =
        '${downline['name'] ?? downline['username'] ?? downline['email'] ?? downline['user_id'] ?? downline['id'] ?? ''}'
            .trim();
    final roleId = downline['role_id'] is int
        ? downline['role_id'] as int
        : int.tryParse('${downline['role_id']}') ?? 0;
    final createdAt = _dateLabel(
      downline['created_at'] ?? downline['joined_at'],
    );
    final referralCode =
        '${downline['referral_code_used'] ?? downline['referral_code'] ?? ''}'
            .trim();
    return Card(
      child: ListTile(
        leading: const CircleAvatar(child: Icon(Icons.person_outline)),
        title: Text(
          name.isEmpty ? l10n.text('Downline') : name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (roleId > 0) Text(l10n.roleName(roleId)),
            if (createdAt.isNotEmpty)
              Text(l10n.text('Joined {date}', {'date': createdAt})),
            if (referralCode.isNotEmpty)
              Text(l10n.text('Referral used: {code}', {'code': referralCode})),
          ],
        ),
      ),
    );
  }

  String _dateLabel(Object? value) {
    DateTime? date;
    if (value is Timestamp) date = value.toDate();
    if (value is DateTime) date = value;
    date ??= DateTime.tryParse('$value');
    if (date == null || date.year < 2000) return '';
    return DateFormat.yMMMd().format(date);
  }
}

class _RecruitmentError extends StatelessWidget {
  const _RecruitmentError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(context.l10n.text('$error'), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

String _compactDate(String value) {
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  return DateFormat.yMMMd().format(parsed.toLocal());
}
