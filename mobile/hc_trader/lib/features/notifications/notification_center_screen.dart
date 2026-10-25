import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_notification.dart';
import '../../models/app_user.dart';
import '../../repositories/notification_repository.dart';
import '../../services/push_notification_service.dart';

class NotificationCenterScreen extends StatefulWidget {
  const NotificationCenterScreen({
    super.key,
    required this.user,
    required this.repository,
    this.onOpen,
  });

  final AppUser user;
  final NotificationRepository repository;
  final ValueChanged<AppNotificationRecord>? onOpen;

  @override
  State<NotificationCenterScreen> createState() =>
      _NotificationCenterScreenState();
}

class _NotificationCenterScreenState extends State<NotificationCenterScreen> {
  AppNotificationCategory? _category;
  bool _unreadOnly = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(context.l10n.text('Notification Centre')),
      actions: [
        IconButton(
          tooltip: context.l10n.text('Notification preferences'),
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => NotificationPreferencesScreen(user: widget.user),
            ),
          ),
          icon: const Icon(Icons.tune),
        ),
        PopupMenuButton<String>(
          onSelected: (value) {
            if (value == 'read_all') {
              _markAllRead();
            } else {
              setState(() => _unreadOnly = !_unreadOnly);
            }
          },
          itemBuilder: (_) => [
            PopupMenuItem(
              value: 'read_all',
              child: Text(context.l10n.text('Mark all as read')),
            ),
            PopupMenuItem(
              value: 'unread',
              child: Text(
                context.l10n.text(
                  _unreadOnly ? 'Show all notifications' : 'Show unread only',
                ),
              ),
            ),
          ],
        ),
      ],
    ),
    body: Column(
      children: [
        SizedBox(
          height: 58,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            children: [
              _CategoryChip(
                label: context.l10n.text('All'),
                selected: _category == null,
                onSelected: () => setState(() => _category = null),
              ),
              _CategoryChip(
                label: context.l10n.text('Trading Signals'),
                selected: _category == AppNotificationCategory.tradingSignals,
                onSelected: () => setState(
                  () => _category = AppNotificationCategory.tradingSignals,
                ),
              ),
              _CategoryChip(
                label: context.l10n.text('Announcements'),
                selected: _category == AppNotificationCategory.announcements,
                onSelected: () => setState(
                  () => _category = AppNotificationCategory.announcements,
                ),
              ),
              _CategoryChip(
                label: context.l10n.text('Stock Reports'),
                selected: _category == AppNotificationCategory.stockReports,
                onSelected: () => setState(
                  () => _category = AppNotificationCategory.stockReports,
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: StreamBuilder<List<AppNotificationRecord>>(
            stream: widget.repository.notifications(
              userId: widget.user.uid,
              category: _category,
              unreadOnly: _unreadOnly,
            ),
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return _NotificationState(
                  icon: Icons.cloud_off_outlined,
                  title: context.l10n.text('Notifications are unavailable'),
                  message: context.l10n.text(
                    'Check your connection and try again.',
                  ),
                );
              }
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              final notifications = snapshot.data!;
              if (notifications.isEmpty) {
                return _NotificationState(
                  icon: Icons.notifications_none,
                  title: context.l10n.text('You are all caught up'),
                  message: context.l10n.text(
                    'New signal, announcement, and stock-report updates will appear here.',
                  ),
                );
              }
              return ListView.builder(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 28),
                itemCount: notifications.length,
                itemBuilder: (context, index) {
                  final current = notifications[index];
                  final showHeader =
                      index == 0 ||
                      !_sameDay(
                        notifications[index - 1].createdAt,
                        current.createdAt,
                      );
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (showHeader)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(4, 14, 4, 8),
                          child: Text(
                            _dateGroup(context, current.createdAt),
                            style: Theme.of(context).textTheme.labelLarge,
                          ),
                        ),
                      Dismissible(
                        key: ValueKey(current.id),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          decoration: BoxDecoration(
                            color: Theme.of(context).colorScheme.errorContainer,
                            borderRadius: BorderRadius.circular(16),
                          ),
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 20),
                          child: const Icon(Icons.archive_outlined),
                        ),
                        onDismissed: (_) => widget.repository.archive(
                          userId: widget.user.uid,
                          notificationId: current.id,
                        ),
                        child: _NotificationTile(
                          notification: current,
                          onTap: () => _open(current),
                        ),
                      ),
                    ],
                  );
                },
              );
            },
          ),
        ),
      ],
    ),
  );

  Future<void> _open(AppNotificationRecord notification) async {
    if (!notification.isRead) {
      await widget.repository.markRead(
        userId: widget.user.uid,
        notificationId: notification.id,
      );
    }
    if (mounted) widget.onOpen?.call(notification);
  }

  Future<void> _markAllRead() async {
    try {
      await widget.repository.markAllRead(widget.user.uid);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.text('All notifications read.'))),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Unable to update notifications.')),
        ),
      );
    }
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(right: 8),
    child: FilterChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onSelected(),
    ),
  );
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, required this.onTap});

  final AppNotificationRecord notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    final color = _categoryColor(context, notification.category);
    return Card(
      color: notification.isRead
          ? null
          : Color.alphaBlend(
              color.withValues(alpha: .07),
              Theme.of(context).cardColor,
            ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: color.withValues(alpha: .14),
                foregroundColor: color,
                child: Icon(_categoryIcon(notification.category)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            notification.localizedTitle(language),
                            style: TextStyle(
                              fontWeight: notification.isRead
                                  ? FontWeight.w700
                                  : FontWeight.w900,
                            ),
                          ),
                        ),
                        if (!notification.isRead)
                          Semantics(
                            label: context.l10n.text('Unread'),
                            child: Container(
                              width: 9,
                              height: 9,
                              decoration: BoxDecoration(
                                color: color,
                                shape: BoxShape.circle,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      notification.localizedMessage(language),
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 7),
                    Text(
                      DateFormat.jm().format(notification.createdAt.toLocal()),
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                  ],
                ),
              ),
              if (notification.deepLink.isNotEmpty ||
                  notification.entityId.isNotEmpty)
                const Padding(
                  padding: EdgeInsets.only(left: 4, top: 12),
                  child: Icon(Icons.chevron_right),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class NotificationPreferencesScreen extends StatefulWidget {
  const NotificationPreferencesScreen({super.key, required this.user});

  final AppUser user;

  @override
  State<NotificationPreferencesScreen> createState() =>
      _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState
    extends State<NotificationPreferencesScreen> {
  late Future<NotificationPreferences> _future;
  NotificationPreferences? _value;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _future = PushNotificationService.instance.loadPreferences(widget.user);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Notification preferences'))),
    body: FutureBuilder<NotificationPreferences>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        _value ??= snapshot.data!;
        final value = _value!;
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
          children: [
            _PreferenceHeading(
              title: context.l10n.text('Trading signals'),
              subtitle: context.l10n.text(
                'Choose which authorized signal lifecycle events reach this device.',
              ),
            ),
            SwitchListTile.adaptive(
              title: Text(context.l10n.text('Trading signal notifications')),
              subtitle: Text(
                context.l10n.text('Master control for signal notifications'),
              ),
              value: value.signals,
              onChanged: (enabled) => _set(value.copyWith(signals: enabled)),
            ),
            ...[
              _toggle(
                'New signals',
                value.newSignals,
                (enabled) => value.copyWith(newSignals: enabled),
              ),
              _toggle(
                'Entry activation',
                value.entryActivation,
                (enabled) => value.copyWith(entryActivation: enabled),
              ),
              _toggle(
                'Stop-loss updates',
                value.stopLossUpdates,
                (enabled) => value.copyWith(stopLossUpdates: enabled),
              ),
              _toggle(
                'Breakeven updates',
                value.breakevenUpdates,
                (enabled) => value.copyWith(breakevenUpdates: enabled),
              ),
              _toggle(
                'Take-profit events',
                value.takeProfitEvents,
                (enabled) => value.copyWith(takeProfitEvents: enabled),
              ),
              _toggle(
                'Signal cancellation',
                value.signalCancellation,
                (enabled) => value.copyWith(signalCancellation: enabled),
              ),
              _toggle(
                'Signal closure',
                value.signalClosure,
                (enabled) => value.copyWith(signalClosure: enabled),
              ),
              _toggle(
                'Important analyst notes',
                value.importantSignalNotes,
                (enabled) => value.copyWith(importantSignalNotes: enabled),
              ),
            ].map(
              (tile) => IgnorePointer(
                ignoring: !value.signals,
                child: Opacity(opacity: value.signals ? 1 : .45, child: tile),
              ),
            ),
            const Divider(height: 28),
            _PreferenceHeading(
              title: context.l10n.text('Communication and reports'),
              subtitle: context.l10n.text(
                'Control announcements, financial reports, and watchlist updates.',
              ),
            ),
            _toggle(
              'Announcements',
              value.announcements,
              (enabled) => value.copyWith(announcements: enabled),
            ),
            _toggle(
              'Financial daily reports',
              value.financialDailyReports,
              (enabled) => value.copyWith(financialDailyReports: enabled),
            ),
            _toggle(
              'Stock watchlist updates',
              value.stockWatchlistUpdates,
              (enabled) => value.copyWith(stockWatchlistUpdates: enabled),
            ),
            const Divider(height: 28),
            _PreferenceHeading(
              title: context.l10n.text('Delivery'),
              subtitle: context.l10n.text(
                'Operating-system notification permission still applies.',
              ),
            ),
            _toggle(
              'Sound',
              value.sound,
              (enabled) => value.copyWith(sound: enabled),
            ),
            _toggle(
              'Vibration',
              value.vibration,
              (enabled) => value.copyWith(vibration: enabled),
            ),
            SwitchListTile.adaptive(
              title: Text(context.l10n.text('Quiet hours')),
              subtitle: Text(
                context.l10n.text(
                  'Non-critical notifications wait until quiet hours end.',
                ),
              ),
              value: value.quietHoursEnabled,
              onChanged: (enabled) =>
                  _set(value.copyWith(quietHoursEnabled: enabled)),
            ),
            if (value.quietHoursEnabled)
              Row(
                children: [
                  Expanded(
                    child: ListTile(
                      title: Text(context.l10n.text('Starts')),
                      subtitle: Text(_time(value.quietHoursStartMinutes)),
                      onTap: () => _pickQuietTime(start: true),
                    ),
                  ),
                  Expanded(
                    child: ListTile(
                      title: Text(context.l10n.text('Ends')),
                      subtitle: Text(_time(value.quietHoursEndMinutes)),
                      onTap: () => _pickQuietTime(start: false),
                    ),
                  ),
                ],
              ),
          ],
        );
      },
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _saving || _value == null ? null : _save,
      icon: const Icon(Icons.save_outlined),
      label: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
    ),
  );

  Widget _toggle(
    String label,
    bool value,
    NotificationPreferences Function(bool) update,
  ) => SwitchListTile.adaptive(
    title: Text(context.l10n.text(label)),
    value: value,
    onChanged: (enabled) => _set(update(enabled)),
  );

  void _set(NotificationPreferences value) => setState(() => _value = value);

  Future<void> _pickQuietTime({required bool start}) async {
    final value = _value!;
    final initialMinutes = start
        ? value.quietHoursStartMinutes
        : value.quietHoursEndMinutes;
    final selected = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: initialMinutes ~/ 60,
        minute: initialMinutes % 60,
      ),
    );
    if (selected == null) return;
    final minutes = selected.hour * 60 + selected.minute;
    _set(
      start
          ? value.copyWith(quietHoursStartMinutes: minutes)
          : value.copyWith(quietHoursEndMinutes: minutes),
    );
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await PushNotificationService.instance.updatePreferences(
        widget.user,
        _value!,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Notification preferences saved.')),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.l10n.text('Unable to save notification preferences.'),
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String _time(int minutes) =>
      TimeOfDay(hour: minutes ~/ 60, minute: minutes % 60).format(context);
}

class _PreferenceHeading extends StatelessWidget {
  const _PreferenceHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(8, 8, 8, 6),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 3),
        Text(subtitle),
      ],
    ),
  );
}

class _NotificationState extends StatelessWidget {
  const _NotificationState({
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

IconData _categoryIcon(AppNotificationCategory category) => switch (category) {
  AppNotificationCategory.tradingSignals => Icons.candlestick_chart,
  AppNotificationCategory.announcements => Icons.campaign_outlined,
  AppNotificationCategory.stockReports => Icons.article_outlined,
  AppNotificationCategory.stockWatchlist => Icons.monitor_heart_outlined,
  AppNotificationCategory.appointments => Icons.calendar_month_outlined,
  AppNotificationCategory.education => Icons.school_outlined,
  AppNotificationCategory.system => Icons.notifications_outlined,
};

Color _categoryColor(BuildContext context, AppNotificationCategory category) =>
    switch (category) {
      AppNotificationCategory.tradingSignals => Colors.teal.shade700,
      AppNotificationCategory.announcements => Colors.orange.shade800,
      AppNotificationCategory.stockReports => Colors.blue.shade700,
      _ => Theme.of(context).colorScheme.primary,
    };

bool _sameDay(DateTime left, DateTime right) =>
    left.year == right.year &&
    left.month == right.month &&
    left.day == right.day;

String _dateGroup(BuildContext context, DateTime date) {
  final local = date.toLocal();
  final now = DateTime.now();
  if (_sameDay(local, now)) return context.l10n.text('Today');
  if (_sameDay(local, now.subtract(const Duration(days: 1)))) {
    return context.l10n.text('Yesterday');
  }
  return DateFormat.yMMMMd().format(local);
}
