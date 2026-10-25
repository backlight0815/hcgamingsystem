import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/personality_assessment.dart';
import '../../models/personality_question_configuration.dart';
import '../../repositories/personality_question_admin_repository.dart';
import '../../shared/app_feedback.dart';

class AdminPersonalityQuestionSetsScreen extends StatefulWidget {
  const AdminPersonalityQuestionSetsScreen({
    super.key,
    required this.user,
    this.repository,
  });

  final AppUser user;
  final PersonalityQuestionAdminRepository? repository;

  @override
  State<AdminPersonalityQuestionSetsScreen> createState() =>
      _AdminPersonalityQuestionSetsScreenState();
}

class _AdminPersonalityQuestionSetsScreenState
    extends State<AdminPersonalityQuestionSetsScreen> {
  late final PersonalityQuestionAdminRepository _repository =
      widget.repository ?? PersonalityQuestionAdminRepository();
  bool _saving = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Personality question sets'))),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _saving ? null : _createVersion,
      icon: const Icon(Icons.add),
      label: Text(context.l10n.text('New version')),
    ),
    body: StreamBuilder<List<PersonalityQuestionSetConfiguration>>(
      stream: _repository.watchSets(),
      builder: (context, snapshot) {
        if (!snapshot.hasData && !snapshot.hasError) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _QuestionAdminError(
            error: snapshot.error!,
            onRetry: () => setState(() {}),
          );
        }
        final sets = snapshot.data!;
        if (sets.isEmpty) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.psychology_alt_outlined, size: 54),
                  const SizedBox(height: 12),
                  Text(
                    context.l10n.text('No personality question sets yet.'),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    context.l10n.text(
                      'Create a draft seeded from the current validated 32-question bank.',
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 92),
          itemCount: sets.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final set = sets[index];
            return Card(
              child: ListTile(
                leading: CircleAvatar(
                  child: Icon(
                    set.isPublished
                        ? Icons.verified_outlined
                        : Icons.edit_note_outlined,
                  ),
                ),
                title: Text(
                  set.title.isEmpty ? set.version : set.title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  '${set.version} · ${set.questionCount} ${context.l10n.text('questions')} · ${context.l10n.text(set.isPublished ? 'Published' : 'Draft')}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => AdminPersonalityQuestionsScreen(
                      user: widget.user,
                      questionSet: set,
                      repository: _repository,
                    ),
                  ),
                ),
              ),
            );
          },
        );
      },
    ),
  );

  Future<void> _createVersion() async {
    final version = TextEditingController(text: 'personality-16-v2');
    final title = TextEditingController(text: 'Self-Personality Test v2');
    var seed = true;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(context.l10n.text('Create question-set version')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: version,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Test version'),
                    helperText: context.l10n.text(
                      'Version IDs cannot be changed after creation.',
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: title,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Title'),
                  ),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: seed,
                  onChanged: (value) =>
                      setDialogState(() => seed = value ?? true),
                  title: Text(
                    context.l10n.text('Seed the validated 32-question bank'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(context.l10n.text('Cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(context.l10n.text('Create')),
            ),
          ],
        ),
      ),
    );
    if (accepted != true || !mounted) return;
    setState(() => _saving = true);
    try {
      const chinese = AppLocalizations(Locale('zh'));
      await _repository.createVersion(
        version: version.text,
        title: title.text,
        actorUid: widget.user.uid,
        seedBuiltInQuestions: seed,
        chineseTranslations: {
          for (final question in PersonalityQuestionBank.questions)
            question.textKey: chinese.text(question.textKey),
        },
      );
      if (mounted) AppFeedback.success(context, 'Question-set draft created.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class AdminPersonalityQuestionsScreen extends StatefulWidget {
  const AdminPersonalityQuestionsScreen({
    super.key,
    required this.user,
    required this.questionSet,
    required this.repository,
  });

  final AppUser user;
  final PersonalityQuestionSetConfiguration questionSet;
  final PersonalityQuestionAdminRepository repository;

  @override
  State<AdminPersonalityQuestionsScreen> createState() =>
      _AdminPersonalityQuestionsScreenState();
}

class _AdminPersonalityQuestionsScreenState
    extends State<AdminPersonalityQuestionsScreen> {
  bool _working = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.questionSet.version),
      actions: [
        IconButton(
          tooltip: context.l10n.text('Preview assessment'),
          onPressed: _working ? null : _preview,
          icon: const Icon(Icons.preview_outlined),
        ),
        if (!widget.questionSet.isPublished)
          IconButton(
            tooltip: context.l10n.text('Publish version'),
            onPressed: _working ? null : _publish,
            icon: const Icon(Icons.publish_outlined),
          ),
      ],
    ),
    floatingActionButton: widget.questionSet.isPublished
        ? null
        : FloatingActionButton.extended(
            onPressed: _working ? null : () => _editQuestion(),
            icon: const Icon(Icons.add),
            label: Text(context.l10n.text('Add question')),
          ),
    body: StreamBuilder<List<PersonalityQuestionConfiguration>>(
      stream: widget.repository.watchQuestions(widget.questionSet.version),
      builder: (context, snapshot) {
        if (!snapshot.hasData && !snapshot.hasError) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _QuestionAdminError(
            error: snapshot.error!,
            onRetry: () => setState(() {}),
          );
        }
        final questions = snapshot.data!;
        return Column(
          children: [
            Material(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              child: ListTile(
                leading: Icon(
                  widget.questionSet.isPublished
                      ? Icons.lock_outline
                      : Icons.edit_outlined,
                ),
                title: Text(
                  context.l10n.text(
                    widget.questionSet.isPublished
                        ? 'Published versions are immutable.'
                        : 'Draft questions can be edited and deactivated.',
                  ),
                ),
                subtitle: Text(
                  context.l10n.text(
                    widget.questionSet.isPublished
                        ? 'Create a new version to make changes.'
                        : 'Publishing validates translations, balance, scoring, and order.',
                  ),
                ),
              ),
            ),
            Expanded(
              child: questions.isEmpty
                  ? Center(
                      child: Text(
                        context.l10n.text('No questions in this draft.'),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 92),
                      itemCount: questions.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 6),
                      itemBuilder: (context, index) {
                        final question = questions[index];
                        return Card(
                          child: ListTile(
                            enabled: question.active,
                            leading: CircleAvatar(
                              child: Text('${question.displayOrder}'),
                            ),
                            title: Text(question.textEn),
                            subtitle: Text(
                              '${question.textZh}\n${question.dimension.key} · ${context.l10n.text('Direction')} ${question.direction} · ${context.l10n.text(question.reverseScored ? 'Reverse scored' : 'Normal scored')} · ${context.l10n.text('Weight')} ${question.weight.toStringAsFixed(2)}${question.active ? '' : ' · ${context.l10n.text('Inactive')}'}',
                            ),
                            isThreeLine: true,
                            trailing: widget.questionSet.isPublished
                                ? const Icon(Icons.lock_outline)
                                : const Icon(Icons.edit_outlined),
                            onTap: widget.questionSet.isPublished
                                ? null
                                : () => _editQuestion(question),
                          ),
                        );
                      },
                    ),
            ),
          ],
        );
      },
    ),
  );

  Future<List<PersonalityQuestionConfiguration>> _currentQuestions() async {
    return widget.repository.watchQuestions(widget.questionSet.version).first;
  }

  Future<void> _preview() async {
    try {
      final questions = await _currentQuestions();
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => _PersonalityQuestionPreview(
            version: widget.questionSet.version,
            questions: questions.where((question) => question.active).toList(),
          ),
        ),
      );
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  Future<void> _publish() async {
    try {
      final questions = await _currentQuestions();
      final errors = PersonalityQuestionSetValidator.validate(
        widget.questionSet.version,
        questions,
      );
      if (errors.isNotEmpty) {
        if (mounted) AppFeedback.error(context, errors.first);
        return;
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
      return;
    }
    if (!mounted) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Publish this version?')),
        content: Text(
          context.l10n.text(
            'Publishing is permanent. Historical scoring requires this question set to remain unchanged.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Publish version')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _working = true);
    try {
      await widget.repository.publishVersion(
        version: widget.questionSet.version,
        actorUid: widget.user.uid,
      );
      if (!mounted) return;
      AppFeedback.success(context, 'Question-set version published.');
      Navigator.pop(context);
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }

  Future<void> _editQuestion([
    PersonalityQuestionConfiguration? existing,
  ]) async {
    final questions = await _currentQuestions();
    if (!mounted) return;
    final edited = await showDialog<PersonalityQuestionConfiguration>(
      context: context,
      builder: (_) => _QuestionEditorDialog(
        version: widget.questionSet.version,
        existing: existing,
        suggestedOrder: questions.isEmpty
            ? 1
            : questions
                      .map((question) => question.displayOrder)
                      .reduce((left, right) => left > right ? left : right) +
                  1,
      ),
    );
    if (edited == null || !mounted) return;
    setState(() => _working = true);
    try {
      await widget.repository.saveQuestion(
        version: widget.questionSet.version,
        question: edited,
        actorUid: widget.user.uid,
        isNew: existing == null,
      );
      if (mounted) AppFeedback.success(context, 'Question saved.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }
}

class _QuestionEditorDialog extends StatefulWidget {
  const _QuestionEditorDialog({
    required this.version,
    required this.existing,
    required this.suggestedOrder,
  });

  final String version;
  final PersonalityQuestionConfiguration? existing;
  final int suggestedOrder;

  @override
  State<_QuestionEditorDialog> createState() => _QuestionEditorDialogState();
}

class _QuestionEditorDialogState extends State<_QuestionEditorDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _id;
  late final TextEditingController _english;
  late final TextEditingController _chinese;
  late final TextEditingController _weight;
  late final TextEditingController _order;
  late final TextEditingController _group;
  late PersonalityDimension _dimension;
  late String _direction;
  late bool _reverse;
  late bool _active;
  late bool _tieBreaker;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _dimension = existing?.dimension ?? PersonalityDimension.energy;
    _direction = existing?.direction ?? _dimension.firstLetter;
    _reverse = existing?.reverseScored ?? false;
    _active = existing?.active ?? true;
    _tieBreaker = existing?.tieBreaker ?? false;
    _id = TextEditingController(
      text: existing?.id ?? 'question_${widget.suggestedOrder}',
    );
    _english = TextEditingController(text: existing?.textEn ?? '');
    _chinese = TextEditingController(text: existing?.textZh ?? '');
    _weight = TextEditingController(text: '${existing?.weight ?? 1}');
    _order = TextEditingController(
      text: '${existing?.displayOrder ?? widget.suggestedOrder}',
    );
    _group = TextEditingController(
      text: existing?.randomizationGroup ?? _dimension.key,
    );
  }

  @override
  void dispose() {
    _id.dispose();
    _english.dispose();
    _chinese.dispose();
    _weight.dispose();
    _order.dispose();
    _group.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text(
        widget.existing == null ? 'Add question' : 'Edit question',
      ),
    ),
    content: SizedBox(
      width: 520,
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _field(_id, 'Question ID', enabled: widget.existing == null),
              _field(_english, 'English question', maxLines: 3),
              _field(_chinese, 'Chinese question', maxLines: 3),
              DropdownButtonFormField<PersonalityDimension>(
                initialValue: _dimension,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Dimension'),
                ),
                items: [
                  for (final dimension in PersonalityDimension.values)
                    DropdownMenuItem(
                      value: dimension,
                      child: Text(
                        '${dimension.key} — ${context.l10n.text(dimension.titleKey)}',
                      ),
                    ),
                ],
                onChanged: (value) {
                  if (value == null) return;
                  setState(() {
                    _dimension = value;
                    _direction = value.firstLetter;
                    _group.text = value.key;
                  });
                },
              ),
              DropdownButtonFormField<String>(
                initialValue: _direction,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Scoring direction'),
                ),
                items: [
                  for (final value in [
                    _dimension.firstLetter,
                    _dimension.secondLetter,
                  ])
                    DropdownMenuItem(value: value, child: Text(value)),
                ],
                onChanged: (value) => setState(
                  () => _direction = value ?? _dimension.firstLetter,
                ),
              ),
              Row(
                children: [
                  Expanded(child: _field(_weight, 'Weight', number: true)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _field(_order, 'Display order', number: true),
                  ),
                ],
              ),
              _field(_group, 'Randomization group'),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _reverse,
                onChanged: (value) => setState(() => _reverse = value),
                title: Text(context.l10n.text('Reverse scored')),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _tieBreaker,
                onChanged: (value) => setState(() => _tieBreaker = value),
                title: Text(context.l10n.text('Tie-breaker question')),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _active,
                onChanged: (value) => setState(() => _active = value),
                title: Text(context.l10n.text('Active')),
              ),
            ],
          ),
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(onPressed: _submit, child: Text(context.l10n.text('Save'))),
    ],
  );

  Widget _field(
    TextEditingController controller,
    String label, {
    int maxLines = 1,
    bool enabled = true,
    bool number = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextFormField(
      controller: controller,
      enabled: enabled,
      maxLines: maxLines,
      keyboardType: number
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      decoration: InputDecoration(labelText: context.l10n.text(label)),
      validator: (value) =>
          value?.trim().isEmpty == true ? context.l10n.text('Required') : null,
    ),
  );

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    final weight = double.tryParse(_weight.text);
    final order = int.tryParse(_order.text);
    if (weight == null || weight <= 0 || order == null || order <= 0) {
      AppFeedback.error(context, 'Weight and display order must be positive.');
      return;
    }
    final id = _id.text.trim();
    if (!RegExp(r'^[A-Za-z0-9_-]{2,64}$').hasMatch(id)) {
      AppFeedback.error(
        context,
        'Question IDs may contain only letters, numbers, hyphens, and underscores.',
      );
      return;
    }
    Navigator.pop(
      context,
      PersonalityQuestionConfiguration(
        id: id,
        textEn: _english.text.trim(),
        textZh: _chinese.text.trim(),
        dimension: _dimension,
        direction: _direction,
        reverseScored: _reverse,
        weight: weight,
        active: _active,
        testVersion: widget.version,
        displayOrder: order,
        randomizationGroup: _group.text.trim(),
        tieBreaker: _tieBreaker,
      ),
    );
  }
}

class _PersonalityQuestionPreview extends StatelessWidget {
  const _PersonalityQuestionPreview({
    required this.version,
    required this.questions,
  });

  final String version;
  final List<PersonalityQuestionConfiguration> questions;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Assessment preview'))),
    body: ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: questions.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Card(
            child: ListTile(
              leading: const Icon(Icons.visibility_outlined),
              title: Text('$version · ${questions.length} questions'),
              subtitle: Text(
                context.l10n.text(
                  'Preview only. Answers are not saved or scored here.',
                ),
              ),
            ),
          );
        }
        final question = questions[index - 1];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '$index. ${question.textEn}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Text(question.textZh),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 6,
                  children: [
                    for (var value = 1; value <= 7; value++)
                      Chip(label: Text('$value')),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    ),
  );
}

class _QuestionAdminError extends StatelessWidget {
  const _QuestionAdminError({required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 12),
          Text(AppFeedback.messageFor(error), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Retry')),
          ),
        ],
      ),
    ),
  );
}
