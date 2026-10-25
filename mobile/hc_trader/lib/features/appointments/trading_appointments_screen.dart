import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/firestore_helpers.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/formatters.dart';

const _clientCancellableAppointmentStatuses = {
  'pending',
  'requested',
  'awaiting_confirmation',
  'awaiting confirmation',
  'awaiting-confirmation',
  'approved',
  'confirmed',
};

class TradingAppointmentsScreen extends StatefulWidget {
  const TradingAppointmentsScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<TradingAppointmentsScreen> createState() =>
      _TradingAppointmentsScreenState();
}

class _TradingAppointmentsScreenState extends State<TradingAppointmentsScreen> {
  final _bookingSlotIds = <String>{};

  @override
  Widget build(BuildContext context) {
    final tabCount = widget.user.isAdmin ? 3 : 2;
    return DefaultTabController(
      key: ValueKey('${widget.user.uid}-$tabCount'),
      length: tabCount,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.l10n.text('Trading appointments')),
          bottom: TabBar(
            tabs: [
              Tab(
                icon: const Icon(Icons.event_available_outlined),
                text: context.l10n.text('Available'),
              ),
              Tab(
                icon: const Icon(Icons.calendar_month_outlined),
                text: context.l10n.text('My appointments'),
              ),
              if (widget.user.isAdmin)
                Tab(
                  icon: const Icon(Icons.admin_panel_settings_outlined),
                  text: context.l10n.text('Manage'),
                ),
            ],
          ),
        ),
        body: StreamBuilder<Map<String, dynamic>>(
          stream: widget.repository.appointmentWorkspaceStream(
            uid: widget.user.uid,
            isAdmin: widget.user.isAdmin,
          ),
          builder: (context, snapshot) {
            if (!snapshot.hasData && !snapshot.hasError) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _ErrorState(
                error: snapshot.error,
                onRetry: () => setState(() {}),
              );
            }
            final slots = _maps(snapshot.data!['slots']);
            final appointments = _maps(snapshot.data!['appointments']);
            final hosts = _maps(snapshot.data!['hosts']);
            final mine = appointments
                .where(
                  (item) =>
                      '${item['requester_id']}' == widget.user.uid ||
                      '${item['host_user_id']}' == widget.user.uid,
                )
                .toList();
            return TabBarView(
              children: [
                _AvailableTab(
                  slots: slots
                      .where(
                        (slot) =>
                            slot['status'] == 'active' &&
                            '${slot['host_user_id']}' != widget.user.uid &&
                            (firestoreDate(
                                  slot['start_at'],
                                )?.isAfter(DateTime.now()) ??
                                false),
                      )
                      .toList(),
                  onBook: _book,
                  onPreferred: () => _preferred(hosts),
                  bookingSlotIds: _bookingSlotIds,
                ),
                _AppointmentsList(appointments: mine, onCancel: _cancel),
                if (widget.user.isAdmin)
                  _ManageTab(
                    slots: slots,
                    appointments: appointments,
                    onAddSlot: () => _editSlot(hosts),
                    onEditSlot: (slot) => _editSlot(hosts, slot),
                    onDeleteSlot: _deleteSlot,
                    onReview: _review,
                  ),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _book(Map<String, dynamic> slot) async {
    final slotId = '${slot['id']}';
    if (_bookingSlotIds.contains(slotId)) return;
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _BookingDialog(),
    );
    if (result == null) return;
    setState(() => _bookingSlotIds.add(slotId));
    try {
      await widget.repository.bookAppointmentSlot(
        slotId: slotId,
        uid: widget.user.uid,
        requesterName: widget.user.label,
        subject: result['subject']!,
        note: result['note']!,
      );
      if (mounted) {
        AppFeedback.success(
          context,
          context.l10n.text('Appointment booked successfully.'),
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'book appointment');
      }
    } finally {
      if (mounted) setState(() => _bookingSlotIds.remove(slotId));
    }
  }

  Future<void> _preferred(List<Map<String, dynamic>> hosts) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _PreferredDialog(
        repository: widget.repository,
        user: widget.user,
        hosts: hosts
            .where((host) => '${host['id']}' != widget.user.uid)
            .toList(),
      ),
    );
    if (saved == true && mounted) {
      AppFeedback.success(
        context,
        context.l10n.text('Preferred appointment submitted for review.'),
      );
    }
  }

  Future<void> _editSlot(
    List<Map<String, dynamic>> hosts, [
    Map<String, dynamic>? slot,
  ]) async {
    final l10n = context.l10n;
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) =>
          _SlotDialog(repository: widget.repository, hosts: hosts, slot: slot),
    );
    if (saved == true && mounted) {
      AppFeedback.success(
        context,
        l10n.text(
          slot == null
              ? 'Appointment slot created.'
              : 'Appointment slot updated.',
        ),
      );
    }
  }

  Future<void> _review(Map<String, dynamic> appointment, bool approve) async {
    final l10n = context.l10n;
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          l10n.text(approve ? 'Approve appointment?' : 'Reject appointment?'),
        ),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: l10n.text(approve ? 'Review note (optional)' : 'Reason'),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(l10n.text('Confirm')),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      note.dispose();
      return;
    }
    if (!mounted) {
      note.dispose();
      return;
    }
    if (!approve && note.text.trim().isEmpty) {
      AppFeedback.error(
        context,
        const ApiException('Please enter a rejection reason.'),
        operation: 'reject appointment',
      );
      note.dispose();
      return;
    }
    try {
      await widget.repository.reviewAppointment(
        appointmentId: '${appointment['id']}',
        approve: approve,
        note: note.text,
      );
      if (mounted) {
        AppFeedback.success(
          context,
          l10n.text(
            approve ? 'Appointment approved.' : 'Appointment rejected.',
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'review appointment');
      }
    } finally {
      note.dispose();
    }
  }

  Future<void> _deleteSlot(Map<String, dynamic> slot) async {
    final l10n = context.l10n;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.text('Delete appointment slot?')),
        content: Text(l10n.text('This cannot be undone.')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(l10n.text('Delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.deleteAppointmentSlot('${slot['id']}');
      if (mounted) {
        AppFeedback.success(context, l10n.text('Appointment slot deleted.'));
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'delete slot');
      }
    }
  }

  Future<void> _cancel(Map<String, dynamic> appointment) async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Cancel appointment?')),
        content: TextField(
          controller: reason,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: context.l10n.text('Cancellation reason'),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Keep appointment')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Cancel appointment')),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      reason.dispose();
      return;
    }
    if (!mounted) {
      reason.dispose();
      return;
    }
    if (reason.text.trim().length < 5) {
      AppFeedback.error(
        context,
        const ApiException('Please enter a cancellation reason.'),
        operation: 'cancel appointment',
      );
      reason.dispose();
      return;
    }
    try {
      await widget.repository.cancelAppointment(
        appointmentId: '${appointment['id']}',
        reason: reason.text,
      );
      if (mounted) {
        AppFeedback.success(
          context,
          context.l10n.text('Appointment cancelled.'),
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'cancel appointment');
      }
    } finally {
      reason.dispose();
    }
  }
}

class _AvailableTab extends StatelessWidget {
  const _AvailableTab({
    required this.slots,
    required this.onBook,
    required this.onPreferred,
    required this.bookingSlotIds,
  });

  final List<Map<String, dynamic>> slots;
  final ValueChanged<Map<String, dynamic>> onBook;
  final VoidCallback onPreferred;
  final Set<String> bookingSlotIds;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
    children: [
      Card(
        child: ListTile(
          leading: const CircleAvatar(
            child: Icon(Icons.schedule_send_outlined),
          ),
          title: Text(context.l10n.text('Need a different time?')),
          subtitle: Text(
            context.l10n.text(
              'Send a preferred-time request for admin approval.',
            ),
          ),
          trailing: FilledButton.tonal(
            onPressed: onPreferred,
            child: Text(context.l10n.text('Request')),
          ),
        ),
      ),
      const SizedBox(height: 16),
      Text(
        context.l10n.text('Available slots'),
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 8),
      if (slots.isEmpty)
        _InlineEmpty(
          icon: Icons.event_busy_outlined,
          message: context.l10n.text(
            'No available appointment slots right now.',
          ),
        ),
      for (final slot in slots)
        Card(
          child: ListTile(
            leading: const CircleAvatar(child: Icon(Icons.event_available)),
            title: Text(_dateTime(context, slot['start_at'])),
            subtitle: Text(
              '${slot['host_name'] ?? context.l10n.text('Trading mentor')} · '
              '${slot['duration_minutes'] ?? 30} '
              '${context.l10n.text('min')}\n${slot['location'] ?? ''}',
            ),
            isThreeLine: true,
            trailing: FilledButton(
              onPressed: bookingSlotIds.contains('${slot['id']}')
                  ? null
                  : () => onBook(slot),
              child: bookingSlotIds.contains('${slot['id']}')
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(context.l10n.text('Book')),
            ),
          ),
        ),
    ],
  );
}

class _AppointmentsList extends StatelessWidget {
  const _AppointmentsList({required this.appointments, required this.onCancel});

  final List<Map<String, dynamic>> appointments;
  final ValueChanged<Map<String, dynamic>> onCancel;

  @override
  Widget build(BuildContext context) {
    if (appointments.isEmpty) {
      return _InlineEmpty(
        icon: Icons.calendar_month_outlined,
        message: context.l10n.text('You do not have any appointments yet.'),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      itemCount: appointments.length,
      itemBuilder: (_, index) {
        final item = appointments[index];
        final status = '${item['status'] ?? 'pending'}';
        final start = firestoreDate(item['scheduled_start_at']);
        final canCancel =
            _clientCancellableAppointmentStatuses.contains(
              status.trim().toLowerCase(),
            ) &&
            start != null &&
            start.isAfter(DateTime.now());
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
                        item['subject'] == null
                            ? context.l10n.text('Trading appointment')
                            : '${item['subject']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    _StatusBadge(status: status),
                  ],
                ),
                const SizedBox(height: 6),
                Text(_dateTime(context, item['scheduled_start_at'])),
                Text(
                  context.l10n.text('Host: {name}', {
                    'name':
                        item['host_name'] ??
                        context.l10n.text('To be confirmed'),
                  }),
                ),
                if ('${item['location'] ?? ''}'.isNotEmpty)
                  Text(
                    context.l10n.text('Location: {location}', {
                      'location': item['location'],
                    }),
                  ),
                if ('${item['review_note'] ?? ''}'.isNotEmpty)
                  Text(
                    context.l10n.text('Review: {review}', {
                      'review': item['review_note'],
                    }),
                  ),
                if (canCancel) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: OutlinedButton(
                      onPressed: () => onCancel(item),
                      child: Text(context.l10n.text('Cancel')),
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ManageTab extends StatelessWidget {
  const _ManageTab({
    required this.slots,
    required this.appointments,
    required this.onAddSlot,
    required this.onEditSlot,
    required this.onDeleteSlot,
    required this.onReview,
  });

  final List<Map<String, dynamic>> slots;
  final List<Map<String, dynamic>> appointments;
  final VoidCallback onAddSlot;
  final ValueChanged<Map<String, dynamic>> onEditSlot;
  final ValueChanged<Map<String, dynamic>> onDeleteSlot;
  final void Function(Map<String, dynamic>, bool) onReview;

  @override
  Widget build(BuildContext context) {
    final pending = appointments.where((item) => item['status'] == 'pending');
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                context.l10n.text('Pending requests'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            FilledButton.tonalIcon(
              onPressed: onAddSlot,
              icon: const Icon(Icons.add),
              label: Text(context.l10n.text('New slot')),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (pending.isEmpty)
          _InlineEmpty(
            icon: Icons.task_alt,
            message: context.l10n.text('No appointment requests are waiting.'),
          ),
        for (final item in pending)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${item['subject']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(
                    item['requester_name'] == null
                        ? context.l10n.text('Trader')
                        : '${item['requester_name']}',
                  ),
                  Text(_dateTime(context, item['scheduled_start_at'])),
                  if ('${item['request_note'] ?? ''}'.isNotEmpty)
                    Text('${item['request_note']}'),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      OutlinedButton(
                        onPressed: () => onReview(item, false),
                        child: Text(context.l10n.text('Reject')),
                      ),
                      const SizedBox(width: 8),
                      FilledButton(
                        onPressed: () => onReview(item, true),
                        child: Text(context.l10n.text('Approve')),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 18),
        Text(
          context.l10n.text('Upcoming slots'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        for (final slot in slots)
          Card(
            child: ListTile(
              title: Text(_dateTime(context, slot['start_at'])),
              subtitle: Text(
                '${slot['host_name'] ?? context.l10n.text('Host')} · '
                '${context.l10n.text(_pretty('${slot['status'] ?? 'active'}'))}',
              ),
              trailing: slot['status'] == 'booked'
                  ? const Icon(Icons.lock_clock_outlined)
                  : PopupMenuButton<String>(
                      onSelected: (value) => value == 'edit'
                          ? onEditSlot(slot)
                          : onDeleteSlot(slot),
                      itemBuilder: (_) => [
                        PopupMenuItem(
                          value: 'edit',
                          child: Text(context.l10n.text('Edit')),
                        ),
                        PopupMenuItem(
                          value: 'delete',
                          child: Text(context.l10n.text('Delete')),
                        ),
                      ],
                    ),
            ),
          ),
      ],
    );
  }
}

class _BookingDialog extends StatefulWidget {
  const _BookingDialog();

  @override
  State<_BookingDialog> createState() => _BookingDialogState();
}

class _BookingDialogState extends State<_BookingDialog> {
  final _subject = TextEditingController();
  final _note = TextEditingController();

  @override
  void dispose() {
    _subject.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.l10n.text('Book appointment')),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TextField(
          controller: _subject,
          decoration: InputDecoration(labelText: context.l10n.text('Subject')),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _note,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: context.l10n.text('What would you like to discuss?'),
          ),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: () {
          if (_subject.text.trim().isEmpty) return;
          Navigator.pop(context, {
            'subject': _subject.text.trim(),
            'note': _note.text.trim(),
          });
        },
        child: Text(context.l10n.text('Book')),
      ),
    ],
  );
}

class _PreferredDialog extends StatefulWidget {
  const _PreferredDialog({
    required this.repository,
    required this.user,
    required this.hosts,
  });

  final TradingRepository repository;
  final AppUser user;
  final List<Map<String, dynamic>> hosts;

  @override
  State<_PreferredDialog> createState() => _PreferredDialogState();
}

class _PreferredDialogState extends State<_PreferredDialog> {
  final _subject = TextEditingController();
  final _note = TextEditingController();
  String? _hostId;
  DateTime _start = DateTime.now().add(const Duration(days: 1));
  int _duration = 30;
  bool _saving = false;

  @override
  void dispose() {
    _subject.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.l10n.text('Preferred appointment')),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _hostId,
            decoration: InputDecoration(labelText: context.l10n.text('Host')),
            items: [
              for (final host in widget.hosts)
                DropdownMenuItem(
                  value: '${host['id']}',
                  child: Text('${host['name'] ?? host['email'] ?? 'Host'}'),
                ),
            ],
            onChanged: (value) => setState(() => _hostId = value),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _subject,
            decoration: InputDecoration(
              labelText: context.l10n.text('Subject'),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _note,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: context.l10n.text('Request details'),
            ),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.text('Preferred time')),
            subtitle: Text(formatDateTime(_start)),
            trailing: const Icon(Icons.edit_calendar_outlined),
            onTap: _pickDateTime,
          ),
          DropdownButtonFormField<int>(
            initialValue: _duration,
            decoration: InputDecoration(
              labelText: context.l10n.text('Duration'),
            ),
            items: [
              DropdownMenuItem(
                value: 30,
                child: Text(context.l10n.text('30 minutes')),
              ),
              DropdownMenuItem(
                value: 45,
                child: Text(context.l10n.text('45 minutes')),
              ),
              DropdownMenuItem(
                value: 60,
                child: Text(context.l10n.text('60 minutes')),
              ),
            ],
            onChanged: (value) => setState(() => _duration = value!),
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
        onPressed: _saving ? null : _save,
        child: Text(context.l10n.text(_saving ? 'Submitting…' : 'Submit')),
      ),
    ],
  );

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _start,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_start),
    );
    if (time == null) return;
    setState(
      () => _start = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<void> _save() async {
    if (_hostId == null ||
        _subject.text.trim().isEmpty ||
        _note.text.trim().isEmpty) {
      return;
    }
    setState(() => _saving = true);
    try {
      final host = widget.hosts.firstWhere(
        (item) => '${item['id']}' == _hostId,
      );
      await widget.repository.requestPreferredAppointment(
        uid: widget.user.uid,
        requesterName: widget.user.label,
        hostId: _hostId!,
        hostName: '${host['name'] ?? host['email'] ?? 'Host'}',
        subject: _subject.text,
        note: _note.text,
        startAt: _start,
        durationMinutes: _duration,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _SlotDialog extends StatefulWidget {
  const _SlotDialog({required this.repository, required this.hosts, this.slot});

  final TradingRepository repository;
  final List<Map<String, dynamic>> hosts;
  final Map<String, dynamic>? slot;

  @override
  State<_SlotDialog> createState() => _SlotDialogState();
}

class _SlotDialogState extends State<_SlotDialog> {
  late String? _hostId;
  late DateTime _start;
  late int _duration;
  late bool _active;
  late final TextEditingController _location;
  late final TextEditingController _notes;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final slot = widget.slot ?? const <String, dynamic>{};
    _hostId = slot['host_user_id']?.toString();
    _start =
        firestoreDate(slot['start_at']) ??
        DateTime.now().add(const Duration(days: 1));
    _duration = _int(slot['duration_minutes'], fallback: 30);
    _active = slot['status'] != 'inactive';
    _location = TextEditingController(text: '${slot['location'] ?? ''}');
    _notes = TextEditingController(text: '${slot['notes'] ?? ''}');
  }

  @override
  void dispose() {
    _location.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text(
        widget.slot == null ? 'Create appointment slot' : 'Edit slot',
      ),
    ),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _hostId,
            decoration: InputDecoration(labelText: context.l10n.text('Host')),
            items: [
              for (final host in widget.hosts)
                DropdownMenuItem(
                  value: '${host['id']}',
                  child: Text('${host['name'] ?? host['email'] ?? 'Host'}'),
                ),
            ],
            onChanged: (value) => setState(() => _hostId = value),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.text('Date and time')),
            subtitle: Text(formatDateTime(_start)),
            trailing: const Icon(Icons.edit_calendar_outlined),
            onTap: _pickDateTime,
          ),
          DropdownButtonFormField<int>(
            initialValue: _duration,
            decoration: InputDecoration(
              labelText: context.l10n.text('Duration'),
            ),
            items: [
              DropdownMenuItem(
                value: 30,
                child: Text(context.l10n.text('30 minutes')),
              ),
              DropdownMenuItem(
                value: 45,
                child: Text(context.l10n.text('45 minutes')),
              ),
              DropdownMenuItem(
                value: 60,
                child: Text(context.l10n.text('60 minutes')),
              ),
            ],
            onChanged: (value) => setState(() => _duration = value!),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _location,
            decoration: InputDecoration(
              labelText: context.l10n.text('Location or meeting URL'),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notes,
            maxLines: 2,
            decoration: InputDecoration(labelText: context.l10n.text('Notes')),
          ),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            value: _active,
            onChanged: (value) => setState(() => _active = value),
            title: Text(context.l10n.text('Slot available')),
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
        onPressed: _saving ? null : _save,
        child: Text(context.l10n.text(_saving ? 'Saving…' : 'Save')),
      ),
    ],
  );

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _start,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_start),
    );
    if (time == null) return;
    setState(
      () => _start = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<void> _save() async {
    if (_hostId == null) return;
    setState(() => _saving = true);
    try {
      final host = widget.hosts.firstWhere(
        (item) => '${item['id']}' == _hostId,
      );
      await widget.repository.saveAppointmentSlot(
        id: widget.slot?['id']?.toString(),
        hostId: _hostId!,
        hostName: '${host['name'] ?? host['email'] ?? 'Host'}',
        startAt: _start,
        durationMinutes: _duration,
        location: _location.text,
        notes: _notes.text,
        active: _active,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'approved' => const Color(0xFF17855B),
      'rejected' => Theme.of(context).colorScheme.error,
      'cancelled' => Theme.of(context).colorScheme.outline,
      _ => Theme.of(context).colorScheme.secondary,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        context.l10n.text(_pretty(status)),
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
  const _InlineEmpty({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          context.l10n.text(AppFeedback.messageFor(error ?? Exception())),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        FilledButton.tonal(
          onPressed: onRetry,
          child: Text(context.l10n.text('Try again')),
        ),
      ],
    ),
  );
}

List<Map<String, dynamic>> _maps(Object? value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((item) => Map<String, dynamic>.from(item))
    .toList();

String _dateTime(BuildContext context, Object? value) {
  final date = firestoreDate(value);
  if (date == null) return context.l10n.text('Date to be confirmed');
  if (context.l10n.isChinese) {
    String two(int value) => value.toString().padLeft(2, '0');
    return '${date.year}-${two(date.month)}-${two(date.day)} '
        '${two(date.hour)}:${two(date.minute)}';
  }
  return formatDateTime(date);
}

int _int(Object? value, {required int fallback}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

String _pretty(String value) => value.isEmpty
    ? value
    : '${value[0].toUpperCase()}${value.substring(1).replaceAll('_', ' ')}';

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(context.l10n.text(AppFeedback.messageFor(error)))),
  );
}
