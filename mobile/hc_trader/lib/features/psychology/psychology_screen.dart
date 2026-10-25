import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/standalone_models.dart';
import '../../repositories/trading_repository.dart';
import '../../services/psychology_score_service.dart';
import '../../shared/app_back_button.dart';
import '../../shared/formatters.dart';

class PsychologyScreen extends StatefulWidget {
  const PsychologyScreen({
    super.key,
    required this.user,
    required this.storage,
    required this.repository,
  });

  final AppUser user;
  final LocalAppStorage storage;
  final TradingRepository repository;

  @override
  State<PsychologyScreen> createState() => _PsychologyScreenState();
}

class _PsychologyScreenState extends State<PsychologyScreen> {
  final _service = const PsychologyScoreService();
  late List<PsychologyCheckIn> _history;

  @override
  void initState() {
    super.initState();
    _history =
        widget.storage
            .readList(widget.user.uid, 'psychology_checkins')
            .map(PsychologyCheckIn.fromJson)
            .toList()
          ..sort((left, right) => right.createdAt.compareTo(left.createdAt));
    _refreshHistory();
  }

  Future<void> _refreshHistory() async {
    try {
      final history = await widget.repository.loadPsychologyCheckIns(
        widget.user.uid,
      );
      if (mounted) setState(() => _history = history);
    } catch (_) {
      // The local check-in history remains available while offline.
    }
  }

  @override
  Widget build(BuildContext context) {
    final latest = _history.isEmpty ? null : _history.first;
    final score = latest == null ? null : _service.evaluate(latest);
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Psychology')),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addCheckIn,
        icon: const Icon(Icons.add),
        label: Text(context.l10n.text('Check in')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 90),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: latest == null
                  ? Text(
                      context.l10n.text(
                        'Complete a check-in before trading to measure emotional readiness.',
                      ),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                context.l10n.text('Trading Readiness'),
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w900),
                              ),
                            ),
                            Chip(label: Text(context.l10n.text(score!.status))),
                          ],
                        ),
                        Text(
                          '${score.score.toStringAsFixed(0)}/100',
                          style: Theme.of(context).textTheme.displaySmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 8),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(999),
                          child: LinearProgressIndicator(
                            value: score.score / 100,
                            minHeight: 10,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(context.l10n.text(score.explanation)),
                        const SizedBox(height: 6),
                        Text(
                          '${context.l10n.text(latest.emotion)} | ${formatDate(latest.createdAt)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        for (final warning in score.warnings)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(
                                  Icons.warning_amber,
                                  size: 18,
                                  color: Color(0xFFD99A17),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(context.l10n.text(warning)),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            context.l10n.text('History'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          if (_history.isEmpty)
            Text(context.l10n.text('No check-ins yet.'))
          else
            for (final item in _history)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Text(
                        _service.evaluate(item).score.toStringAsFixed(0),
                      ),
                    ),
                    title: Text(context.l10n.text(item.emotion)),
                    subtitle: Text(
                      '${formatDate(item.createdAt)}${item.notes.isEmpty ? '' : ' | ${item.notes}'}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: Text(
                      context.l10n.text(_service.evaluate(item).status),
                    ),
                  ),
                ),
              ),
        ],
      ),
    );
  }

  Future<void> _addCheckIn() async {
    final checkIn = await showDialog<PsychologyCheckIn>(
      context: context,
      builder: (_) => const _CheckInDialog(),
    );
    if (checkIn == null) return;
    final normalized = PsychologyCheckIn(
      id: checkIn.id,
      userId: widget.user.uid,
      createdAt: checkIn.createdAt,
      emotion: checkIn.emotion,
      confidence: checkIn.confidence,
      discipline: checkIn.discipline,
      fomo: checkIn.fomo,
      revengeUrge: checkIn.revengeUrge,
      overconfidence: checkIn.overconfidence,
      notes: checkIn.notes,
    );
    setState(() => _history.insert(0, normalized));
    await widget.repository.upsertPsychologyCheckIn(
      uid: widget.user.uid,
      checkIn: normalized,
    );
  }
}

class _CheckInDialog extends StatefulWidget {
  const _CheckInDialog();

  @override
  State<_CheckInDialog> createState() => _CheckInDialogState();
}

class _CheckInDialogState extends State<_CheckInDialog> {
  final _notes = TextEditingController();
  String _emotion = 'Calm';
  double _confidence = 5;
  double _discipline = 5;
  bool _fomo = false;
  bool _revenge = false;
  bool _overconfidence = false;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Psychology check-in')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _emotion,
              decoration: InputDecoration(
                labelText: context.l10n.text('Current emotion'),
              ),
              items:
                  const [
                        'Great',
                        'Calm',
                        'Neutral',
                        'Anxious',
                        'Frustrated',
                        'Angry',
                        'Fearful',
                        'Revenge trading mood',
                      ]
                      .map(
                        (item) => DropdownMenuItem(
                          value: item,
                          child: Text(context.l10n.text(item)),
                        ),
                      )
                      .toList(),
              onChanged: (value) => setState(() => _emotion = value ?? 'Calm'),
            ),
            const SizedBox(height: 10),
            Text(
              context.l10n.text('Confidence {value}/10', {
                'value': _confidence.round(),
              }),
            ),
            Slider(
              value: _confidence,
              min: 1,
              max: 10,
              divisions: 9,
              onChanged: (value) => setState(() => _confidence = value),
            ),
            Text(
              context.l10n.text('Discipline {value}/10', {
                'value': _discipline.round(),
              }),
            ),
            Slider(
              value: _discipline,
              min: 1,
              max: 10,
              divisions: 9,
              onChanged: (value) => setState(() => _discipline = value),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _fomo,
              onChanged: (value) => setState(() => _fomo = value ?? false),
              title: Text(context.l10n.text('Feeling FOMO')),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _revenge,
              onChanged: (value) => setState(() => _revenge = value ?? false),
              title: Text(context.l10n.text('Urge to recover a loss')),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _overconfidence,
              onChanged: (value) =>
                  setState(() => _overconfidence = value ?? false),
              title: Text(context.l10n.text('Feeling overconfident')),
            ),
            TextField(
              controller: _notes,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: context.l10n.text('Reflection notes'),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(
            context,
            PsychologyCheckIn(
              id: DateTime.now().microsecondsSinceEpoch.toString(),
              createdAt: DateTime.now(),
              emotion: _emotion,
              confidence: _confidence.round(),
              discipline: _discipline.round(),
              fomo: _fomo,
              revengeUrge: _revenge,
              overconfidence: _overconfidence,
              notes: _notes.text.trim(),
            ),
          ),
          child: Text(context.l10n.text('Save')),
        ),
      ],
    );
  }
}
