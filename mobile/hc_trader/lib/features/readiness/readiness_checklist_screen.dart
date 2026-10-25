import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/localized_value.dart';
import '../../models/app_user.dart';
import '../../repositories/trading_repository.dart';

class ReadinessChecklistScreen extends StatefulWidget {
  const ReadinessChecklistScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<ReadinessChecklistScreen> createState() =>
      _ReadinessChecklistScreenState();
}

class _ReadinessChecklistScreenState extends State<ReadinessChecklistScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load() =>
      widget.repository.readinessChecklist(widget.user.uid);

  void _reload() {
    final nextFuture = _load();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text('Trader readiness')),
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) {
              if (value == 'reset') _reset();
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'reset',
                child: Text(context.l10n.text('Reset checklist')),
              ),
            ],
          ),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(error: snapshot.error, onRetry: _reload);
          }
          final items = snapshot.data!;
          final completed = items.where(_isComplete).length;
          final percent = items.isEmpty
              ? 0
              : (completed / items.length * 100).round();
          final categories = <String, List<Map<String, dynamic>>>{};
          for (final item in items) {
            categories.putIfAbsent('${item['category']}', () => []).add(item);
          }
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _ProgressCard(
                  completed: completed,
                  total: items.length,
                  percent: percent,
                ),
                const SizedBox(height: 18),
                for (final category in categories.entries) ...[
                  _CategoryHeading(
                    title: _itemText(context, category.value.first, 'category'),
                    completed: category.value.where(_isComplete).length,
                    total: category.value.length,
                  ),
                  const SizedBox(height: 8),
                  for (final item in category.value)
                    _ChecklistCard(
                      item: item,
                      onComplete: () => _complete(item),
                      onReopen: () => _reopen(item),
                    ),
                  const SizedBox(height: 14),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _complete(Map<String, dynamic> item) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _CompletionSheet(
        title: _itemText(context, item, 'title'),
        onSave: ({required rating, required demoPracticed, required note}) =>
            widget.repository.saveReadinessProgress(
              uid: widget.user.uid,
              itemId: '${item['id']}',
              completed: true,
              selfRating: rating,
              demoPracticed: demoPracticed,
              reflectionNote: note,
            ),
      ),
    );
    if (saved == true) _reload();
  }

  Future<void> _reopen(Map<String, dynamic> item) async {
    await widget.repository.saveReadinessProgress(
      uid: widget.user.uid,
      itemId: '${item['id']}',
      completed: false,
    );
    _reload();
  }

  Future<void> _reset() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Reset checklist?')),
        content: Text(
          context.l10n.text(
            'All ratings, practice confirmations, and reflection notes will be cleared.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Keep progress')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Reset')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.resetReadinessChecklist(widget.user.uid);
      _reload();
    } catch (error) {
      if (mounted) _showError(context, error);
    }
  }
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({
    required this.completed,
    required this.total,
    required this.percent,
  });

  final int completed;
  final int total;
  final int percent;

  @override
  Widget build(BuildContext context) {
    final stage = switch (percent) {
      >= 100 => 'Checklist complete',
      >= 80 => 'Final review',
      >= 50 => 'Practice phase',
      _ => 'Foundation stage',
    };
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Theme.of(context).colorScheme.primaryContainer,
            Theme.of(context).colorScheme.tertiaryContainer,
          ],
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.fact_check_outlined),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  context.l10n.text(stage),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
              Text(
                '$percent%',
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ],
          ),
          const SizedBox(height: 12),
          LinearProgressIndicator(value: total == 0 ? 0 : completed / total),
          const SizedBox(height: 8),
          Text(
            context.l10n.text(
              '{completed} of {total} readiness items completed',
              {'completed': completed, 'total': total},
            ),
          ),
          const SizedBox(height: 4),
          Text(
            context.l10n.text(
              'Complete these honestly. The goal is safer execution-not a perfect score.',
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryHeading extends StatelessWidget {
  const _CategoryHeading({
    required this.title,
    required this.completed,
    required this.total,
  });

  final String title;
  final int completed;
  final int total;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      Text('$completed/$total'),
    ],
  );
}

class _ChecklistCard extends StatelessWidget {
  const _ChecklistCard({
    required this.item,
    required this.onComplete,
    required this.onReopen,
  });

  final Map<String, dynamic> item;
  final VoidCallback onComplete;
  final VoidCallback onReopen;

  @override
  Widget build(BuildContext context) {
    final complete = _isComplete(item);
    return Card(
      child: ExpansionTile(
        leading: Icon(
          complete ? Icons.check_circle : Icons.radio_button_unchecked,
          color: complete ? const Color(0xFF17855B) : null,
        ),
        title: Text(
          _itemText(context, item, 'title'),
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        subtitle: Text(
          context.l10n.text(complete ? 'Completed' : 'Not completed'),
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: Text(_itemText(context, item, 'description')),
          ),
          const SizedBox(height: 10),
          _Detail(
            label: 'Why it matters',
            value: _itemText(context, item, 'why_it_matters'),
          ),
          const SizedBox(height: 8),
          _Detail(
            label: 'Suggested action',
            value: _itemText(context, item, 'suggested_action'),
          ),
          if (complete && '${item['reflection_note'] ?? ''}'.isNotEmpty) ...[
            const SizedBox(height: 8),
            _Detail(
              label: 'Your reflection',
              value: '${item['reflection_note']}',
            ),
          ],
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: complete
                ? OutlinedButton.icon(
                    onPressed: onReopen,
                    icon: const Icon(Icons.undo),
                    label: Text(context.l10n.text('Reopen item')),
                  )
                : FilledButton.icon(
                    onPressed: onComplete,
                    icon: const Icon(Icons.check),
                    label: Text(context.l10n.text('Complete thoughtfully')),
                  ),
          ),
        ],
      ),
    );
  }
}

class _CompletionSheet extends StatefulWidget {
  const _CompletionSheet({required this.title, required this.onSave});

  final String title;
  final Future<void> Function({
    required int rating,
    required bool demoPracticed,
    required String note,
  })
  onSave;

  @override
  State<_CompletionSheet> createState() => _CompletionSheetState();
}

class _CompletionSheetState extends State<_CompletionSheet> {
  final _note = TextEditingController();
  int _rating = 3;
  bool _demoPractised = false;
  bool _saving = false;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        MediaQuery.viewInsetsOf(context).bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.title,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 16),
            Text(
              context.l10n.text('Confidence: {rating} / 5', {
                'rating': _rating,
              }),
            ),
            Slider(
              value: _rating.toDouble(),
              min: 1,
              max: 5,
              divisions: 4,
              label: '$_rating',
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _rating = value.round()),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _demoPractised,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _demoPractised = value ?? false),
              title: Text(context.l10n.text('I practised this safely on demo')),
            ),
            TextField(
              controller: _note,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: context.l10n.text('Reflection note (optional)'),
                hintText: context.l10n.text(
                  'What did you learn or demonstrate?',
                ),
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _saving ? null : _save,
                child: Text(
                  context.l10n.text(_saving ? 'Saving...' : 'Mark complete'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.onSave(
        rating: _rating,
        demoPracticed: _demoPractised,
        note: _note.text,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      Text(value),
    ],
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

bool _isComplete(Map<String, dynamic> item) => item['completed'] == true;

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(context.l10n.text('$error'))));
}

String _itemText(
  BuildContext context,
  Map<String, dynamic> item,
  String field,
) {
  final languageCode = context.l10n.locale.languageCode;
  final value = localizedValue(
    item['${field}_i18n'] ??
        item['${field}_localized'] ??
        item['${field}_translations'],
    languageCode,
    fallback: item[field],
  );
  final text = value.trim().isEmpty ? '${item[field] ?? ''}' : value;
  return context.l10n.text(text);
}
