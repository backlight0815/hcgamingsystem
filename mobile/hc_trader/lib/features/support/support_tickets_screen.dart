import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/network/api_client.dart';
import '../../models/app_user.dart';
import '../../models/firestore_helpers.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/formatters.dart';

class SupportTicketsScreen extends StatefulWidget {
  const SupportTicketsScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<SupportTicketsScreen> createState() => _SupportTicketsScreenState();
}

class _SupportTicketsScreenState extends State<SupportTicketsScreen> {
  String _filter = 'all';

  Future<List<Map<String, dynamic>>> _load() => widget.repository
      .supportTickets(uid: widget.user.uid, isAdmin: widget.user.isAdmin);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          context.l10n.text(
            widget.user.isAdmin ? 'Support desk' : 'Support tickets',
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add_comment_outlined),
        label: Text(context.l10n.text('New ticket')),
      ),
      body: StreamBuilder<List<Map<String, dynamic>>>(
        stream: widget.repository.supportTicketsStream(
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
          final all = snapshot.data!;
          final tickets = _filter == 'all'
              ? all
              : all.where((ticket) => ticket['status'] == _filter).toList();
          return RefreshIndicator(
            onRefresh: () async => setState(() {}),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 92),
              children: [
                _Summary(tickets: all),
                const SizedBox(height: 14),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'all',
                      label: Text(context.l10n.text('All')),
                    ),
                    ButtonSegment(
                      value: 'open',
                      label: Text(context.l10n.text('Open')),
                    ),
                    ButtonSegment(
                      value: 'answered',
                      label: Text(context.l10n.text('Answered')),
                    ),
                    ButtonSegment(
                      value: 'closed',
                      label: Text(context.l10n.text('Closed')),
                    ),
                  ],
                  selected: {_filter},
                  onSelectionChanged: (value) =>
                      setState(() => _filter = value.first),
                  showSelectedIcon: false,
                ),
                const SizedBox(height: 14),
                if (tickets.isEmpty)
                  const _InlineEmpty(
                    icon: Icons.support_agent_outlined,
                    message: 'No support tickets match this filter.',
                  ),
                for (final ticket in tickets)
                  _TicketCard(ticket: ticket, onTap: () => _openTicket(ticket)),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _create() async {
    final id = await showDialog<String>(
      context: context,
      builder: (_) =>
          _NewTicketDialog(repository: widget.repository, user: widget.user),
    );
    if (id == null) return;
    if (mounted) AppFeedback.success(context, 'Support ticket opened.');
    final tickets = await _load();
    if (!mounted) return;
    final ticket = tickets.where((item) => '${item['id']}' == id).firstOrNull;
    if (ticket != null) await _openTicket(ticket);
  }

  Future<void> _openTicket(Map<String, dynamic> ticket) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _TicketDetailScreen(
          user: widget.user,
          repository: widget.repository,
          ticket: ticket,
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.tickets});

  final List<Map<String, dynamic>> tickets;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      _Count(
        label: 'Open',
        value: tickets.where((item) => item['status'] == 'open').length,
      ),
      const SizedBox(width: 8),
      _Count(
        label: 'Answered',
        value: tickets.where((item) => item['status'] == 'answered').length,
      ),
      const SizedBox(width: 8),
      _Count(
        label: 'Closed',
        value: tickets.where((item) => item['status'] == 'closed').length,
      ),
    ],
  );
}

class _Count extends StatelessWidget {
  const _Count({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Text('$value', style: Theme.of(context).textTheme.titleLarge),
            Text(
              context.l10n.text(label),
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ),
      ),
    ),
  );
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket, required this.onTap});

  final Map<String, dynamic> ticket;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final status = '${ticket['status'] ?? 'open'}';
    return Card(
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(
          child: Icon(
            status == 'answered' ? Icons.mark_chat_read : Icons.support_agent,
          ),
        ),
        title: Text(
          ticket['subject'] == null
              ? context.l10n.text('Support request')
              : '${ticket['subject']}',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Text(
          '${ticket['ticket_number'] ?? ''} · ${ticket['requester_name'] ?? ''}\n${ticket['last_message'] ?? ''}',
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        isThreeLine: true,
        trailing: _StatusBadge(status: status),
      ),
    );
  }
}

class _NewTicketDialog extends StatefulWidget {
  const _NewTicketDialog({required this.repository, required this.user});

  final TradingRepository repository;
  final AppUser user;

  @override
  State<_NewTicketDialog> createState() => _NewTicketDialogState();
}

class _NewTicketDialogState extends State<_NewTicketDialog> {
  final _formKey = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  String _category = 'trading';
  String _priority = 'medium';
  final _attachments = <PlatformFile>[];
  bool _saving = false;

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.l10n.text('Open support ticket')),
    content: Form(
      key: _formKey,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _subject,
              decoration: InputDecoration(
                labelText: context.l10n.text('Subject'),
              ),
              validator: (value) => _required(context, value),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _saving ? null : _addAttachments,
              icon: const Icon(Icons.attach_file),
              label: Text(
                _attachments.isEmpty
                    ? context.l10n.text('Attach screenshot or PDF')
                    : context.l10n.text('{count} attachment(s)', {
                        'count': _attachments.length,
                      }),
              ),
            ),
            if (_attachments.isNotEmpty)
              Wrap(
                spacing: 6,
                children: [
                  for (final file in _attachments)
                    InputChip(
                      label: Text(file.name),
                      onDeleted: _saving
                          ? null
                          : () => setState(() => _attachments.remove(file)),
                    ),
                ],
              ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: InputDecoration(
                labelText: context.l10n.text('Category'),
              ),
              items: [
                DropdownMenuItem(
                  value: 'trading',
                  child: Text(context.l10n.text('Trading')),
                ),
                DropdownMenuItem(
                  value: 'verification',
                  child: Text(context.l10n.text('Verification')),
                ),
                DropdownMenuItem(
                  value: 'account',
                  child: Text(context.l10n.text('Account')),
                ),
                DropdownMenuItem(
                  value: 'technical',
                  child: Text(context.l10n.text('Technical')),
                ),
                DropdownMenuItem(
                  value: 'other',
                  child: Text(context.l10n.text('Other')),
                ),
              ],
              onChanged: (value) => setState(() => _category = value!),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _priority,
              decoration: InputDecoration(
                labelText: context.l10n.text('Priority'),
              ),
              items: [
                DropdownMenuItem(
                  value: 'low',
                  child: Text(context.l10n.text('Low')),
                ),
                DropdownMenuItem(
                  value: 'medium',
                  child: Text(context.l10n.text('Medium')),
                ),
                DropdownMenuItem(
                  value: 'high',
                  child: Text(context.l10n.text('High')),
                ),
                DropdownMenuItem(
                  value: 'urgent',
                  child: Text(context.l10n.text('Urgent')),
                ),
              ],
              onChanged: (value) => setState(() => _priority = value!),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _message,
              minLines: 4,
              maxLines: 7,
              decoration: InputDecoration(
                labelText: context.l10n.text('Describe the issue'),
                alignLabelWithHint: true,
              ),
              validator: (value) => _required(context, value),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: _saving ? null : () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: _saving ? null : _save,
        child: Text(context.l10n.text(_saving ? 'Opening...' : 'Open ticket')),
      ),
    ],
  );

  String? _required(BuildContext context, String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final id = await widget.repository.createSupportTicket(
        uid: widget.user.uid,
        requesterName: widget.user.label,
        subject: _subject.text,
        category: _category,
        priority: _priority,
        message: _message.text,
        isAdmin: widget.user.isAdmin,
        attachments: _attachments.map(_apiFile).toList(),
      );
      if (mounted) Navigator.pop(context, id);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }

  Future<void> _addAttachments() async {
    final selected = await _pickSupportFiles();
    if (!mounted || selected.isEmpty) return;
    setState(() {
      for (final file in selected) {
        if (_attachments.length >= 3) break;
        _attachments.add(file);
      }
    });
  }
}

class _TicketDetailScreen extends StatefulWidget {
  const _TicketDetailScreen({
    required this.user,
    required this.repository,
    required this.ticket,
  });

  final AppUser user;
  final TradingRepository repository;
  final Map<String, dynamic> ticket;

  @override
  State<_TicketDetailScreen> createState() => _TicketDetailScreenState();
}

class _TicketDetailScreenState extends State<_TicketDetailScreen> {
  final _reply = TextEditingController();
  final _attachments = <PlatformFile>[];
  bool _sending = false;
  late String _status = '${widget.ticket['status'] ?? 'open'}';
  StreamSubscription<Map<String, dynamic>?>? _ticketSubscription;

  @override
  void initState() {
    super.initState();
    _ticketSubscription = widget.repository
        .supportTicketStream('${widget.ticket['id']}')
        .listen((ticket) {
          if (!mounted || ticket == null) return;
          final status = '${ticket['status'] ?? 'open'}';
          if (status != _status) setState(() => _status = status);
        });
  }

  @override
  void dispose() {
    _ticketSubscription?.cancel();
    _reply.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.ticket['ticket_number'] ?? 'Ticket'}'),
        actions: [
          if (_status != 'closed')
            IconButton(
              tooltip: context.l10n.text('Close ticket'),
              onPressed: _close,
              icon: const Icon(Icons.check_circle_outline),
            ),
          if (_status == 'closed' && widget.user.isAdmin)
            IconButton(
              tooltip: context.l10n.text('Reopen ticket'),
              onPressed: _reopen,
              icon: const Icon(Icons.lock_open_outlined),
            ),
        ],
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
            color: Theme.of(context).colorScheme.surfaceContainerLow,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${widget.ticket['subject']}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                    _StatusBadge(status: _status),
                  ],
                ),
                Text(
                  context.l10n.text('{category} - {priority} priority', {
                    'category': context.l10n.text(
                      _pretty('${widget.ticket['category']}'),
                    ),
                    'priority': context.l10n.text(
                      _pretty('${widget.ticket['priority']}'),
                    ),
                  }),
                ),
              ],
            ),
          ),
          Expanded(
            child: StreamBuilder<List<Map<String, dynamic>>>(
              stream: widget.repository.supportTicketMessages(
                '${widget.ticket['id']}',
              ),
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _ErrorState(
                    error: snapshot.error,
                    onRetry: () => setState(() {}),
                  );
                }
                if (!snapshot.hasData) {
                  return const Center(child: CircularProgressIndicator());
                }
                final messages = snapshot.data!;
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: messages.length,
                  itemBuilder: (_, index) {
                    final message = messages[index];
                    final mine = '${message['user_id']}' == widget.user.uid;
                    return Align(
                      alignment: mine
                          ? Alignment.centerRight
                          : Alignment.centerLeft,
                      child: Container(
                        constraints: const BoxConstraints(maxWidth: 320),
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: mine
                              ? Theme.of(context).colorScheme.primaryContainer
                              : Theme.of(
                                  context,
                                ).colorScheme.surfaceContainerHigh,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              message['sender_name'] == null
                                  ? context.l10n.text(
                                      message['is_admin_reply'] == true
                                          ? 'Support'
                                          : 'Trader',
                                    )
                                  : '${message['sender_name']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text('${message['message']}'),
                            _MessageAttachments(message: message),
                            if (firestoreDate(message['created_at'])
                                case final date?)
                              Text(
                                formatDateTime(date),
                                style: Theme.of(context).textTheme.labelSmall,
                              ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
          if (_status != 'closed')
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
                child: Column(
                  children: [
                    if (_attachments.isNotEmpty)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Wrap(
                          spacing: 6,
                          children: [
                            for (final file in _attachments)
                              InputChip(
                                label: Text(file.name),
                                onDeleted: _sending
                                    ? null
                                    : () => setState(
                                        () => _attachments.remove(file),
                                      ),
                              ),
                          ],
                        ),
                      ),
                    Row(
                      children: [
                        IconButton(
                          tooltip: context.l10n.text(
                            'Attach screenshot or PDF',
                          ),
                          onPressed: _sending ? null : _addAttachments,
                          icon: const Icon(Icons.attach_file),
                        ),
                        Expanded(
                          child: TextField(
                            controller: _reply,
                            minLines: 1,
                            maxLines: 4,
                            decoration: InputDecoration(
                              hintText: context.l10n.text('Write a reply...'),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton.filled(
                          onPressed: _sending ? null : _send,
                          icon: _sending
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.send),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _send() async {
    if (_reply.text.trim().isEmpty && _attachments.isEmpty) return;
    setState(() => _sending = true);
    try {
      await widget.repository.replySupportTicket(
        ticketId: '${widget.ticket['id']}',
        uid: widget.user.uid,
        senderName: widget.user.label,
        message: _reply.text,
        isAdmin: widget.user.isAdmin,
        attachments: _attachments.map(_apiFile).toList(),
      );
      if (!mounted) return;
      _reply.clear();
      _attachments.clear();
      setState(() {
        _status = widget.user.isAdmin ? 'answered' : 'open';
        _sending = false;
      });
      AppFeedback.success(context, 'Reply sent.');
    } catch (error) {
      if (!mounted) return;
      setState(() => _sending = false);
      AppFeedback.error(context, error, operation: 'send reply');
    }
  }

  Future<void> _addAttachments() async {
    final selected = await _pickSupportFiles();
    if (!mounted || selected.isEmpty) return;
    setState(() {
      for (final file in selected) {
        if (_attachments.length >= 3) break;
        _attachments.add(file);
      }
    });
  }

  Future<void> _close() async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Close this ticket?')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              context.l10n.text(
                'Closed tickets cannot receive additional replies.',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: note,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: context.l10n.text('Closing note (optional)'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Keep open')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Close ticket')),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      note.dispose();
      return;
    }
    try {
      await widget.repository.closeSupportTicket(
        '${widget.ticket['id']}',
        note: note.text,
      );
      if (mounted) {
        setState(() => _status = 'closed');
        AppFeedback.success(context, 'Ticket closed.');
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'close ticket');
      }
    } finally {
      note.dispose();
    }
  }

  Future<void> _reopen() async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Reopen this ticket?')),
        content: TextField(
          controller: reason,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: context.l10n.text('Reason (optional)'),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton.tonal(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Reopen')),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      reason.dispose();
      return;
    }
    try {
      await widget.repository.reopenSupportTicket(
        '${widget.ticket['id']}',
        reason: reason.text,
      );
      if (mounted) {
        setState(() => _status = 'reopened');
        AppFeedback.success(context, 'Ticket reopened.');
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'reopen ticket');
      }
    } finally {
      reason.dispose();
    }
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'answered' => const Color(0xFF17855B),
      'closed' => Theme.of(context).colorScheme.outline,
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

class _MessageAttachments extends StatelessWidget {
  const _MessageAttachments({required this.message});

  final Map<String, dynamic> message;

  @override
  Widget build(BuildContext context) {
    final attachments = (message['attachments'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    if (attachments.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final attachment in attachments)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: _AttachmentTile(attachment: attachment),
            ),
        ],
      ),
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  const _AttachmentTile({required this.attachment});

  final Map<String, dynamic> attachment;

  @override
  Widget build(BuildContext context) {
    final url = '${attachment['url'] ?? ''}';
    final mime = '${attachment['mime_type'] ?? ''}';
    final name = '${attachment['name'] ?? context.l10n.text('Attachment')}';
    if (mime.startsWith('image/')) {
      return InkWell(
        onTap: () => _openAttachment(url),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Image.network(
            url,
            height: 140,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const SizedBox(
              height: 72,
              child: Center(child: Icon(Icons.broken_image_outlined)),
            ),
          ),
        ),
      );
    }
    return OutlinedButton.icon(
      onPressed: () => _openAttachment(url),
      icon: const Icon(Icons.picture_as_pdf_outlined),
      label: Text(name, overflow: TextOverflow.ellipsis),
    );
  }
}

class _InlineEmpty extends StatelessWidget {
  const _InlineEmpty({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(32),
    child: Column(
      children: [
        Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
        const SizedBox(height: 10),
        Text(context.l10n.text(message), textAlign: TextAlign.center),
      ],
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
        Text(context.l10n.text('$error'), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        FilledButton.tonal(
          onPressed: onRetry,
          child: Text(context.l10n.text('Try again')),
        ),
      ],
    ),
  );
}

String _pretty(String value) => value.isEmpty
    ? value
    : '${value[0].toUpperCase()}${value.substring(1).replaceAll('_', ' ')}';

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(context.l10n.text('$error'))));
}

Future<List<PlatformFile>> _pickSupportFiles() async {
  final result = await FilePicker.platform.pickFiles(
    type: FileType.custom,
    allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
    allowMultiple: true,
    withData: true,
  );
  return result?.files.take(3).toList() ?? const [];
}

ApiUploadFile _apiFile(PlatformFile file) => ApiUploadFile(
  field: 'attachment',
  filename: file.name,
  path: file.path,
  bytes: file.bytes,
);

Future<void> _openAttachment(String value) async {
  final uri = Uri.tryParse(value);
  if (uri != null && uri.hasScheme) {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
