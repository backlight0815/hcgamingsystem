import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/announcement.dart';
import '../../models/app_user.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';

class AnnouncementListScreen extends StatelessWidget {
  const AnnouncementListScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Announcements')),
      ),
      body: StreamBuilder<List<Announcement>>(
        stream: repository.publishedAnnouncementsStream(user: user),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting &&
              !snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(error: snapshot.error);
          }
          final announcements = snapshot.data ?? const <Announcement>[];
          if (announcements.isEmpty) {
            return EmptyState(
              icon: Icons.campaign_outlined,
              title: context.l10n.text('No announcements yet'),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            itemCount: announcements.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) => _AnnouncementCard(
              announcement: announcements[index],
              onTap: () {
                unawaited(
                  repository.markAnnouncementRead(
                    announcementId: announcements[index].id,
                    uid: user.uid,
                  ),
                );
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => AnnouncementDetailScreen(
                      announcement: announcements[index],
                      user: user,
                      repository: repository,
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class AnnouncementDetailScreen extends StatelessWidget {
  const AnnouncementDetailScreen({
    super.key,
    required this.announcement,
    required this.user,
    required this.repository,
  });

  final Announcement announcement;
  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Announcement')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          _AnnouncementHero(announcement: announcement),
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Text(announcement.localizedMessage(language)),
            ),
          ),
          if (announcement.requireAcknowledgement &&
              !announcement.isAcknowledged) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () async {
                await repository.acknowledgeAnnouncement(
                  announcementId: announcement.id,
                  uid: user.uid,
                );
                if (context.mounted) Navigator.pop(context);
              },
              icon: const Icon(Icons.verified_outlined),
              label: Text(context.l10n.text('Acknowledge announcement')),
            ),
          ],
          if (announcement.allowDismissal) ...[
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: () async {
                await repository.dismissAnnouncement(
                  announcementId: announcement.id,
                  uid: user.uid,
                );
                if (context.mounted) Navigator.pop(context);
              },
              icon: const Icon(Icons.close),
              label: Text(context.l10n.text('Dismiss')),
            ),
          ],
        ],
      ),
    );
  }
}

class _AnnouncementHero extends StatelessWidget {
  const _AnnouncementHero({required this.announcement});

  final Announcement announcement;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final language = Localizations.localeOf(context).languageCode;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: colors.primaryContainer.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _NoticeChip(
                icon: Icons.campaign_outlined,
                label: context.l10n.text(announcement.category),
              ),
              _NoticeChip(
                icon: Icons.priority_high,
                label: context.l10n.text(announcement.priority),
              ),
              _NoticeChip(
                icon: Icons.schedule_outlined,
                label: context.l10n.text('Published {date}', {
                  'date': formatDate(
                    announcement.publishedAt ?? announcement.updatedAt,
                  ),
                }),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            announcement.localizedTitle(language),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          if (announcement
              .localizedShortMessage(language)
              .trim()
              .isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(announcement.localizedShortMessage(language)),
          ],
          if (announcement.authorName.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('${context.l10n.text('Author')}: ${announcement.authorName}'),
          ],
          if (announcement.attachmentUrls.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              context.l10n.text('{count} attachment(s)', {
                'count': announcement.attachmentUrls.length,
              }),
            ),
          ],
        ],
      ),
    );
  }
}

class AdminAnnouncementsPanel extends StatelessWidget {
  const AdminAnnouncementsPanel({super.key, required this.repository});

  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<Announcement>>(
      stream: repository.adminAnnouncementsStream(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            !snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error);
        }
        final announcements = snapshot.data ?? const <Announcement>[];
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Announcements'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                FilledButton.icon(
                  onPressed: () => _edit(context),
                  icon: const Icon(Icons.add),
                  label: Text(context.l10n.text('New announcement')),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _AnnouncementMetric(
                  label: 'Active',
                  value: announcements.where((item) => item.isActive).length,
                  icon: Icons.campaign_outlined,
                ),
                _AnnouncementMetric(
                  label: 'Scheduled',
                  value: announcements
                      .where((item) => item.status == 'scheduled')
                      .length,
                  icon: Icons.schedule_outlined,
                ),
                _AnnouncementMetric(
                  label: 'Expired',
                  value: announcements
                      .where(
                        (item) =>
                            item.status == 'expired' ||
                            item.expiresAt?.isBefore(DateTime.now()) == true,
                      )
                      .length,
                  icon: Icons.event_busy_outlined,
                ),
                _AnnouncementMetric(
                  label: 'Draft',
                  value: announcements
                      .where((item) => item.status == 'draft')
                      .length,
                  icon: Icons.edit_note_outlined,
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (announcements.isEmpty)
              EmptyState(
                icon: Icons.campaign_outlined,
                title: context.l10n.text('No announcements yet'),
              )
            else
              for (final announcement in announcements) ...[
                _AdminAnnouncementCard(
                  announcement: announcement,
                  onEdit: () => _edit(context, announcement),
                  onArchive: announcement.status == 'archived'
                      ? null
                      : () => _archive(context, announcement),
                ),
                const SizedBox(height: 10),
              ],
          ],
        );
      },
    );
  }

  Future<void> _edit(BuildContext context, [Announcement? announcement]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _AnnouncementEditorDialog(
        repository: repository,
        announcement: announcement,
      ),
    );
    if (saved == true && context.mounted) {
      AppFeedback.success(context, 'Announcement saved.');
    }
  }

  Future<void> _archive(BuildContext context, Announcement announcement) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Archive announcement?')),
        content: Text(context.l10n.text('Users will no longer see it.')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Archive')),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    try {
      await repository.adminArchiveAnnouncement(announcement.id);
      if (context.mounted) {
        AppFeedback.success(context, 'Announcement archived.');
      }
    } catch (error) {
      if (context.mounted) AppFeedback.error(context, error);
    }
  }
}

class _AnnouncementMetric extends StatelessWidget {
  const _AnnouncementMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final int value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 155,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(icon),
            const SizedBox(width: 9),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$value', style: Theme.of(context).textTheme.titleLarge),
                Text(context.l10n.text(label)),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _AnnouncementEditorDialog extends StatefulWidget {
  const _AnnouncementEditorDialog({
    required this.repository,
    this.announcement,
  });

  final TradingRepository repository;
  final Announcement? announcement;

  @override
  State<_AnnouncementEditorDialog> createState() =>
      _AnnouncementEditorDialogState();
}

class _AnnouncementEditorDialogState extends State<_AnnouncementEditorDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _titleZh;
  late final TextEditingController _shortMessage;
  late final TextEditingController _shortMessageZh;
  late final TextEditingController _message;
  late final TextEditingController _messageZh;
  late final TextEditingController _targetRoles;
  late final TextEditingController _targetUsers;
  late final TextEditingController _targetGroups;
  late final TextEditingController _targetAccessLevels;
  late final TextEditingController _imageUrl;
  late final TextEditingController _actionLabel;
  late final TextEditingController _actionLabelZh;
  late final TextEditingController _deepLink;
  var _status = 'published';
  var _audience = 'all';
  var _category = 'general';
  var _priority = 'normal';
  var _pinned = false;
  var _push = false;
  var _requiresAcknowledgement = false;
  var _allowDismissal = true;
  var _displayLocations = <String>{'inbox'};
  DateTime? _startAt;
  DateTime? _expiresAt;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final announcement = widget.announcement;
    _title = TextEditingController(
      text: announcement?.titleI18n['en'] ?? announcement?.title ?? '',
    );
    _titleZh = TextEditingController(text: announcement?.titleI18n['zh'] ?? '');
    _shortMessage = TextEditingController(
      text:
          announcement?.shortMessageI18n['en'] ??
          announcement?.shortMessage ??
          '',
    );
    _shortMessageZh = TextEditingController(
      text: announcement?.shortMessageI18n['zh'] ?? '',
    );
    _message = TextEditingController(
      text: announcement?.messageI18n['en'] ?? announcement?.message ?? '',
    );
    _messageZh = TextEditingController(
      text: announcement?.messageI18n['zh'] ?? '',
    );
    _targetRoles = TextEditingController(
      text: announcement?.targetRoleIds.join(', ') ?? '',
    );
    _targetUsers = TextEditingController(
      text: announcement?.targetUserIds.join(', ') ?? '',
    );
    _targetGroups = TextEditingController(
      text: announcement?.targetPropFirmGroupIds.join(', ') ?? '',
    );
    _targetAccessLevels = TextEditingController(
      text: announcement?.targetAccessLevels.join(', ') ?? '',
    );
    _imageUrl = TextEditingController(text: announcement?.imageUrl ?? '');
    _actionLabel = TextEditingController(
      text: announcement?.actionLabelI18n['en'] ?? '',
    );
    _actionLabelZh = TextEditingController(
      text: announcement?.actionLabelI18n['zh'] ?? '',
    );
    _deepLink = TextEditingController(text: announcement?.deepLink ?? '');
    _status = announcement?.status ?? 'published';
    _audience = announcement?.audience ?? 'all';
    _category = announcement?.category ?? 'general';
    _priority = announcement?.priority ?? 'normal';
    _pinned = announcement?.isPinned ?? false;
    _push = announcement?.pushNotification ?? false;
    _requiresAcknowledgement = announcement?.requireAcknowledgement ?? false;
    _allowDismissal = announcement?.allowDismissal ?? true;
    _displayLocations = announcement == null
        ? {'inbox'}
        : announcement.displayLocations.toSet();
    _startAt = announcement?.startAt;
    _expiresAt = announcement?.expiresAt;
  }

  @override
  void dispose() {
    _title.dispose();
    _titleZh.dispose();
    _shortMessage.dispose();
    _shortMessageZh.dispose();
    _message.dispose();
    _messageZh.dispose();
    _targetRoles.dispose();
    _targetUsers.dispose();
    _targetGroups.dispose();
    _targetAccessLevels.dispose();
    _imageUrl.dispose();
    _actionLabel.dispose();
    _actionLabelZh.dispose();
    _deepLink.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        context.l10n.text(
          widget.announcement == null
              ? 'New announcement'
              : 'Edit announcement',
        ),
      ),
      content: SizedBox(
        width: 760,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _AnnouncementLanguageFields(
                  language: context.l10n.text('English'),
                  title: _title,
                  shortMessage: _shortMessage,
                  message: _message,
                ),
                const SizedBox(height: 12),
                _AnnouncementLanguageFields(
                  language: context.l10n.text('Simplified Chinese'),
                  title: _titleZh,
                  shortMessage: _shortMessageZh,
                  message: _messageZh,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Status'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'draft',
                      child: Text(context.l10n.text('Draft')),
                    ),
                    DropdownMenuItem(
                      value: 'published',
                      child: Text(context.l10n.text('Published')),
                    ),
                    DropdownMenuItem(
                      value: 'scheduled',
                      child: Text(context.l10n.text('Scheduled')),
                    ),
                  ],
                  onChanged: (value) => setState(() {
                    _status = value ?? 'published';
                  }),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _audience,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Target audience'),
                  ),
                  items:
                      const [
                            'all',
                            'public',
                            'approved_clients',
                            'pending_users',
                            'roles',
                            'prop_firm_groups',
                            'subscription_levels',
                            'users',
                          ]
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(value),
                            ),
                          )
                          .toList(),
                  onChanged: (value) =>
                      setState(() => _audience = value ?? 'all'),
                ),
                if (_audience == 'roles') ...[
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _targetRoles,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Target role IDs'),
                      helperText: context.l10n.text('Comma separated'),
                    ),
                  ),
                ],
                if (_audience == 'users') ...[
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _targetUsers,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Selected user IDs'),
                      helperText: context.l10n.text('Comma separated'),
                    ),
                  ),
                ],
                if (_audience == 'prop_firm_groups') ...[
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _targetGroups,
                    decoration: InputDecoration(
                      labelText: context.l10n.text(
                        'Target prop firm group IDs',
                      ),
                      helperText: context.l10n.text('Comma separated'),
                    ),
                  ),
                ],
                if (_audience == 'subscription_levels') ...[
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _targetAccessLevels,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Target access levels'),
                      helperText: context.l10n.text('Comma separated'),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _category,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Category'),
                  ),
                  items:
                      const [
                            'general',
                            'important',
                            'maintenance',
                            'system_update',
                            'trading_event',
                            'new_feature',
                            'education',
                            'promotion',
                            'emergency',
                          ]
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(context.l10n.text(value)),
                            ),
                          )
                          .toList(),
                  onChanged: (value) =>
                      setState(() => _category = value ?? 'general'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _priority,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Priority'),
                  ),
                  items: const ['low', 'normal', 'high', 'critical']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(context.l10n.text(value)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setState(() => _priority = value ?? 'normal'),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(context.l10n.text('Start date and time')),
                        subtitle: Text(
                          _startAt == null
                              ? context.l10n.text(
                                  'Immediately after publishing',
                                )
                              : formatDate(_startAt!),
                        ),
                        trailing: const Icon(Icons.event_available_outlined),
                        onTap: () => _pickDateTime(start: true),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(context.l10n.text('Expiry date and time')),
                        subtitle: Text(
                          _expiresAt == null
                              ? context.l10n.text('No expiry')
                              : formatDate(_expiresAt!),
                        ),
                        trailing: const Icon(Icons.event_busy_outlined),
                        onTap: () => _pickDateTime(start: false),
                      ),
                    ),
                  ],
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(context.l10n.text('Display locations')),
                ),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final location in const ['home', 'inbox', 'dashboard'])
                      FilterChip(
                        label: Text(context.l10n.text(location)),
                        selected: _displayLocations.contains(location),
                        onSelected: (selected) => setState(() {
                          if (selected) {
                            _displayLocations.add(location);
                          } else {
                            _displayLocations.remove(location);
                          }
                        }),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _imageUrl,
                  keyboardType: TextInputType.url,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Image or attachment URL'),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _actionLabel,
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            'Action label (English)',
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        controller: _actionLabelZh,
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            'Action label (Chinese)',
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _deepLink,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Deep-link destination'),
                  ),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Pinned')),
                  value: _pinned,
                  onChanged: (value) => setState(() => _pinned = value),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Send push notification')),
                  value: _push,
                  onChanged: (value) => setState(() => _push = value),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Require acknowledgement')),
                  value: _requiresAcknowledgement,
                  onChanged: (value) =>
                      setState(() => _requiresAcknowledgement = value),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Allow dismissal')),
                  value: _allowDismissal,
                  onChanged: (value) => setState(() => _allowDismissal = value),
                ),
              ],
            ),
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
          child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
        ),
      ],
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_title.text.trim().isEmpty && _titleZh.text.trim().isEmpty) {
      AppFeedback.error(
        context,
        context.l10n.text('Add a title in English or Chinese.'),
      );
      return;
    }
    if (_message.text.trim().isEmpty && _messageZh.text.trim().isEmpty) {
      AppFeedback.error(
        context,
        context.l10n.text('Add a message in English or Chinese.'),
      );
      return;
    }
    if (_expiresAt != null &&
        _startAt != null &&
        !_expiresAt!.isAfter(_startAt!)) {
      AppFeedback.error(
        context,
        context.l10n.text('Expiry must be after the start time.'),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.repository.adminSaveAnnouncement(
        id: widget.announcement?.id,
        title: _title.text,
        titleZh: _titleZh.text,
        shortMessage: _shortMessage.text,
        shortMessageZh: _shortMessageZh.text,
        message: _message.text,
        messageZh: _messageZh.text,
        status: _status,
        audience: _audience,
        category: _category,
        priority: _priority,
        isPinned: _pinned,
        targetRoleIds: _targetRoles.text
            .split(',')
            .map((value) => int.tryParse(value.trim()))
            .whereType<int>()
            .toList(),
        targetUserIds: _targetUsers.text
            .split(',')
            .map((value) => value.trim())
            .where((value) => value.isNotEmpty)
            .toList(),
        targetPropFirmGroupIds: _targetGroups.text
            .split(',')
            .map((value) => value.trim())
            .where((value) => value.isNotEmpty)
            .toList(),
        targetAccessLevels: _targetAccessLevels.text
            .split(',')
            .map((value) => value.trim())
            .where((value) => value.isNotEmpty)
            .toList(),
        startAt: _startAt,
        expiresAt: _expiresAt,
        displayLocations: _displayLocations.toList(),
        imageUrl: _imageUrl.text,
        actionLabel: _actionLabel.text,
        actionLabelZh: _actionLabelZh.text,
        deepLink: _deepLink.text,
        pushNotification: _push,
        requireAcknowledgement: _requiresAcknowledgement,
        allowDismissal: _allowDismissal,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.error(context, error);
      }
    }
  }

  Future<void> _pickDateTime({required bool start}) async {
    final current = start ? _startAt : _expiresAt;
    final date = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(current ?? DateTime.now()),
    );
    if (time == null) return;
    final value = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    setState(() {
      if (start) {
        _startAt = value;
      } else {
        _expiresAt = value;
      }
    });
  }
}

class _AnnouncementLanguageFields extends StatelessWidget {
  const _AnnouncementLanguageFields({
    required this.language,
    required this.title,
    required this.shortMessage,
    required this.message,
  });

  final String language;
  final TextEditingController title;
  final TextEditingController shortMessage;
  final TextEditingController message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(language, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          TextFormField(
            controller: title,
            decoration: InputDecoration(labelText: context.l10n.text('Title')),
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: shortMessage,
            maxLength: 140,
            decoration: InputDecoration(
              labelText: context.l10n.text('Short message'),
            ),
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: message,
            minLines: 4,
            maxLines: 8,
            decoration: InputDecoration(
              labelText: context.l10n.text('Message'),
              alignLabelWithHint: true,
            ),
          ),
        ],
      ),
    ),
  );
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement, required this.onTap});

  final Announcement announcement;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    final localizedShort = announcement.localizedShortMessage(language);
    final preview = localizedShort.trim().isEmpty
        ? announcement.localizedMessage(language)
        : localizedShort;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                child: const Icon(Icons.campaign_outlined),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (!announcement.isRead)
                          _NoticeChip(
                            icon: Icons.fiber_new,
                            label: context.l10n.text('Unread'),
                          ),
                        if (announcement.isPinned)
                          _NoticeChip(
                            icon: Icons.push_pin_outlined,
                            label: context.l10n.text('Pinned'),
                          ),
                        _NoticeChip(
                          icon: Icons.schedule_outlined,
                          label: context.l10n.text('Published {date}', {
                            'date': formatDate(
                              announcement.publishedAt ??
                                  announcement.updatedAt,
                            ),
                          }),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      announcement.localizedTitle(language),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(preview, maxLines: 3, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _NoticeChip extends StatelessWidget {
  const _NoticeChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: colors.surface.withValues(alpha: .72),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: colors.primary),
          const SizedBox(width: 5),
          Text(label, style: Theme.of(context).textTheme.labelSmall),
        ],
      ),
    );
  }
}

class _AdminAnnouncementCard extends StatelessWidget {
  const _AdminAnnouncementCard({
    required this.announcement,
    required this.onEdit,
    required this.onArchive,
  });

  final Announcement announcement;
  final VoidCallback onEdit;
  final VoidCallback? onArchive;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
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
                    announcement.localizedTitle(language),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                Chip(label: Text(context.l10n.text(announcement.status))),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              announcement.localizedShortMessage(language).trim().isEmpty
                  ? announcement.localizedMessage(language)
                  : announcement.localizedShortMessage(language),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text('Updated {date}', {
                'date': formatDate(announcement.updatedAt),
              }),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                _Tag(
                  icon: Icons.send_outlined,
                  label: context.l10n.text('{count} delivered', {
                    'count': announcement.deliverySuccessCount,
                  }),
                ),
                _Tag(
                  icon: Icons.mark_email_read_outlined,
                  label: context.l10n.text('{count} read', {
                    'count': announcement.readCount,
                  }),
                ),
                if (announcement.requireAcknowledgement)
                  _Tag(
                    icon: Icons.verified_outlined,
                    label: context.l10n.text('{count} acknowledged', {
                      'count': announcement.acknowledgementCount,
                    }),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(context.l10n.text('Edit')),
                ),
                if (onArchive != null)
                  OutlinedButton.icon(
                    onPressed: onArchive,
                    icon: const Icon(Icons.archive_outlined),
                    label: Text(context.l10n.text('Archive')),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15),
        const SizedBox(width: 5),
        Text(label, style: Theme.of(context).textTheme.labelMedium),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error});

  final Object? error;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          context.l10n.text('Unable to load announcements: {error}', {
            'error': error,
          }),
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}
