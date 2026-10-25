import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/personality_assessment.dart';
import '../../models/personality_type_profile.dart';
import '../../repositories/personality_assessment_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../services/personality_assessment_guidance_service.dart';
import '../../services/personality_scoring_engine.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';
import '../../shared/formatters.dart';

class PersonalityGuidanceScreen extends StatefulWidget {
  const PersonalityGuidanceScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
    this.assessmentRepository,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;
  final PersonalityAssessmentRepository? assessmentRepository;

  @override
  State<PersonalityGuidanceScreen> createState() =>
      _PersonalityGuidanceScreenState();
}

enum _AssessmentView {
  loading,
  introduction,
  questions,
  submitting,
  result,
  error,
}

class _PersonalityGuidanceScreenState extends State<PersonalityGuidanceScreen> {
  static const _questions = PersonalityQuestionBank.questions;
  final _scoring = const PersonalityScoringEngine();
  final _guidanceService = const PersonalityAssessmentGuidanceService();
  late PersonalityAssessmentRepository _assessmentRepository;
  _AssessmentView _view = _AssessmentView.loading;
  List<PersonalityAssessmentAttempt> _history = const [];
  PersonalityAssessmentAttempt? _attempt;
  PersonalityAssessmentResult? _result;
  PersonalityAssessmentGuidance? _guidance;
  Object? _error;
  int _questionIndex = 0;
  final Set<String> _selectedGuidance = {};
  bool _syncWarningShown = false;

  @override
  void initState() {
    super.initState();
    _assessmentRepository =
        widget.assessmentRepository ??
        PersonalityAssessmentRepository(
          firebaseEnabled: widget.repository.firebaseEnabled,
          storage: widget.storage,
        );
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _view = _AssessmentView.loading;
      _error = null;
    });
    try {
      final history = await _assessmentRepository.loadAttempts(widget.user.uid);
      final incomplete = history
          .where(
            (attempt) =>
                !attempt.isCompleted &&
                attempt.testVersion == PersonalityQuestionBank.testVersion,
          )
          .firstOrNull;
      final completed = history
          .where(
            (attempt) =>
                attempt.isCompleted &&
                attempt.testVersion == PersonalityQuestionBank.testVersion &&
                attempt.result?.testVersion ==
                    PersonalityQuestionBank.testVersion,
          )
          .firstOrNull;
      if (!mounted) return;
      setState(() {
        _history = history;
        _attempt = incomplete;
        if (incomplete == null && completed?.result != null) {
          _showResult(completed!.result!);
        } else {
          _view = _AssessmentView.introduction;
        }
      });
      _showCloudSyncWarningIfNeeded();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _view = _AssessmentView.error;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _view != _AssessmentView.questions,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && _view == _AssessmentView.questions) _confirmExit();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: _view == _AssessmentView.questions
              ? IconButton(
                  tooltip: context.l10n.text('Exit assessment'),
                  onPressed: _confirmExit,
                  icon: const Icon(Icons.close),
                )
              : const AppBackButton(),
          title: Text(context.l10n.text('Self-Personality Test')),
          actions: [
            if (_view == _AssessmentView.result)
              IconButton(
                tooltip: context.l10n.text('Retake test'),
                onPressed: _startRetake,
                icon: const Icon(Icons.replay),
              ),
          ],
        ),
        body: switch (_view) {
          _AssessmentView.loading => const Center(
            child: CircularProgressIndicator(),
          ),
          _AssessmentView.introduction => _introduction(),
          _AssessmentView.questions => _questionView(),
          _AssessmentView.submitting => _processingView(),
          _AssessmentView.result => _resultView(),
          _AssessmentView.error => _errorView(),
        },
      ),
    );
  }

  Widget _introduction() {
    final l10n = context.l10n;
    final answered = _attempt?.answers.length ?? 0;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.psychology_alt_outlined,
                  size: 48,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 14),
                Text(
                  l10n.text(
                    'Understand how you process information and make trading decisions',
                  ),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  l10n.text(
                    'This original four-dimension assessment explores energy, information, decisions, and planning preferences. It produces one of 16 educational personality-style results.',
                  ),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    Chip(
                      avatar: const Icon(Icons.quiz_outlined, size: 18),
                      label: Text(
                        l10n.text('{count} questions', {
                          'count': _questions.length,
                        }),
                      ),
                    ),
                    Chip(
                      avatar: const Icon(Icons.timer_outlined, size: 18),
                      label: Text(l10n.text('About 8 to 12 minutes')),
                    ),
                    Chip(
                      avatar: const Icon(Icons.save_outlined, size: 18),
                      label: Text(l10n.text('Progress is saved')),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        _DisclaimerCard(),
        if (_attempt != null) ...[
          const SizedBox(height: 14),
          Card(
            child: ListTile(
              leading: const Icon(Icons.restore),
              title: Text(l10n.text('Incomplete assessment')),
              subtitle: Text(
                l10n.text('{answered} of {total} answered', {
                  'answered': answered,
                  'total': _questions.length,
                }),
              ),
              trailing: CircularProgressIndicator(
                value: answered / _questions.length,
                strokeWidth: 5,
              ),
            ),
          ),
        ],
        const SizedBox(height: 18),
        FilledButton.icon(
          key: const ValueKey('personality-start-resume'),
          onPressed: _startOrResume,
          icon: Icon(_attempt == null ? Icons.play_arrow : Icons.restore),
          label: Text(
            l10n.text(
              _attempt == null ? 'Start assessment' : 'Resume assessment',
            ),
          ),
        ),
        if (_history.any(
          (attempt) =>
              attempt.isCompleted &&
              attempt.testVersion == PersonalityQuestionBank.testVersion &&
              attempt.result?.testVersion ==
                  PersonalityQuestionBank.testVersion,
        )) ...[
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: () {
              final latest = _history.firstWhere(
                (attempt) =>
                    attempt.isCompleted &&
                    attempt.testVersion ==
                        PersonalityQuestionBank.testVersion &&
                    attempt.result?.testVersion ==
                        PersonalityQuestionBank.testVersion,
              );
              _showResult(latest.result!);
            },
            icon: const Icon(Icons.history),
            label: Text(l10n.text('View latest result')),
          ),
        ],
      ],
    );
  }

  Widget _questionView() {
    final attempt = _attempt!;
    final question = _questions[_questionIndex];
    final selected = attempt.answers[question.id];
    final l10n = context.l10n;
    return ListView(
      key: ValueKey('personality-question-${question.id}'),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
      children: [
        Semantics(
          label: l10n.text('Question {current} of {total}', {
            'current': _questionIndex + 1,
            'total': _questions.length,
          }),
          child: LinearProgressIndicator(
            value: (_questionIndex + 1) / _questions.length,
            minHeight: 8,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          l10n.text('Question {current} of {total}', {
            'current': _questionIndex + 1,
            'total': _questions.length,
          }),
          textAlign: TextAlign.end,
        ),
        const SizedBox(height: 18),
        Card(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 22, 18, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  l10n.text(question.textKey),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 18),
                RadioGroup<int>(
                  groupValue: selected,
                  onChanged: (answer) {
                    if (answer != null) _selectAnswer(question, answer);
                  },
                  child: Column(
                    children: [
                      for (var value = 1; value <= 7; value++)
                        RadioListTile<int>(
                          key: ValueKey('personality-answer-$value'),
                          value: value,
                          title: Text(l10n.text(_answerLabel(value))),
                          contentPadding: EdgeInsets.zero,
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                key: const ValueKey('personality-previous'),
                onPressed: _questionIndex == 0 ? null : _previousQuestion,
                icon: const Icon(Icons.arrow_back),
                label: Text(l10n.text('Previous')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton.icon(
                key: const ValueKey('personality-next'),
                onPressed: _nextQuestion,
                icon: Icon(
                  _questionIndex == _questions.length - 1
                      ? Icons.check
                      : Icons.arrow_forward,
                ),
                label: Text(
                  l10n.text(
                    _questionIndex == _questions.length - 1
                        ? 'Review and submit'
                        : 'Next',
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _processingView() => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 18),
          Text(
            context.l10n.text('Calculating your preference profile...'),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );

  Widget _resultView() {
    final result = _result!;
    final profile = PersonalityTypeCatalog.forResult(result);
    final guidance = _guidance!;
    final l10n = context.l10n;
    return ListView(
      key: const ValueKey('personality-result'),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
      children: [
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                Theme.of(context).colorScheme.primaryContainer,
                Theme.of(context).colorScheme.tertiaryContainer,
              ],
            ),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Column(
            children: [
              Text(
                result.type,
                style: Theme.of(context).textTheme.displayMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                l10n.text(profile.titleKey),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              Text(l10n.text(profile.summaryKey), textAlign: TextAlign.center),
              const SizedBox(height: 10),
              Text(
                '${formatDate(result.submittedAt)} · ${result.testVersion}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                for (final score in result.dimensionScores) ...[
                  _DimensionIndicator(score: score),
                  if (score != result.dimensionScores.last)
                    const Divider(height: 24),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        _TwoColumnInsights(profile: profile),
        const SizedBox(height: 14),
        _ProfileDetails(profile: profile),
        const SizedBox(height: 14),
        Text(
          l10n.text('Trading guidance'),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                Chip(
                  label: Text(
                    l10n.text('Suggested max risk: {risk}%', {
                      'risk': guidance.maximumRiskPercent,
                    }),
                  ),
                ),
                Chip(
                  label: Text(
                    l10n.text('Suggested daily loss limit: {loss}%', {
                      'loss': guidance.dailyLossLimitPercent,
                    }),
                  ),
                ),
                Chip(
                  label: Text(
                    l10n.text('Suggested maximum trades: {count}', {
                      'count': guidance.maximumTradesPerDay,
                    }),
                  ),
                ),
                for (final style in profile.suitableStyleKeys)
                  Chip(label: Text(l10n.text(style))),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        for (final suggestion in guidance.suggestions)
          Card(
            child: CheckboxListTile(
              value: _selectedGuidance.contains(suggestion.id),
              onChanged: (selected) => setState(() {
                selected == true
                    ? _selectedGuidance.add(suggestion.id)
                    : _selectedGuidance.remove(suggestion.id);
              }),
              title: Text(l10n.text(suggestion.titleKey)),
              subtitle: Text(
                '${l10n.text(suggestion.actionKey, suggestion.actionValues)}\n${l10n.text(suggestion.cautionKey)}',
              ),
              isThreeLine: true,
            ),
          ),
        const SizedBox(height: 14),
        _DisclaimerCard(),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            FilledButton.icon(
              key: const ValueKey('personality-apply-guidance'),
              onPressed: _selectedGuidance.isEmpty ? null : _applyGuidanceDraft,
              icon: const Icon(Icons.playlist_add_check),
              label: Text(l10n.text('Add selected guidance to draft')),
            ),
            OutlinedButton.icon(
              onPressed: _showAnswers,
              icon: const Icon(Icons.fact_check_outlined),
              label: Text(l10n.text('View answer summary')),
            ),
            OutlinedButton.icon(
              key: const ValueKey('personality-retake'),
              onPressed: _startRetake,
              icon: const Icon(Icons.replay),
              label: Text(l10n.text('Retake test')),
            ),
          ],
        ),
      ],
    );
  }

  Widget _errorView() => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 12),
          Text(
            context.l10n.text(
              'The personality assessment could not be loaded.',
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 6),
            Text(
              context.l10n.text('Try again when your connection is available.'),
            ),
          ],
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _load,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );

  Future<void> _startOrResume() async {
    var attempt = _attempt;
    if (attempt == null) {
      attempt = _assessmentRepository.createAttempt(userId: widget.user.uid);
      try {
        await _assessmentRepository.saveProgress(attempt);
      } catch (error) {
        if (mounted) AppFeedback.error(context, error);
        return;
      }
    }
    _showCloudSyncWarningIfNeeded();
    final firstMissing = _questions.indexWhere(
      (question) => !attempt!.answers.containsKey(question.id),
    );
    setState(() {
      _attempt = attempt;
      _questionIndex = firstMissing < 0 ? _questions.length - 1 : firstMissing;
      _view = _AssessmentView.questions;
    });
  }

  Future<void> _startRetake() async {
    final attempt = _assessmentRepository.createAttempt(
      userId: widget.user.uid,
      retakeOf: _result?.attemptId,
    );
    try {
      await _assessmentRepository.saveProgress(attempt);
      if (!mounted) return;
      _showCloudSyncWarningIfNeeded();
      setState(() {
        _attempt = attempt;
        _questionIndex = 0;
        _view = _AssessmentView.questions;
      });
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  Future<void> _selectAnswer(PersonalityQuestion question, int answer) async {
    final attempt = _attempt!;
    final updated = attempt.copyWith(
      answers: {...attempt.answers, question.id: answer},
      updatedAt: DateTime.now(),
    );
    setState(() => _attempt = updated);
    try {
      await _assessmentRepository.saveProgress(updated);
      _showCloudSyncWarningIfNeeded();
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  void _previousQuestion() => setState(() => _questionIndex--);

  Future<void> _nextQuestion() async {
    final question = _questions[_questionIndex];
    if (!_attempt!.answers.containsKey(question.id)) {
      AppFeedback.warning(context, 'Select an answer before continuing.');
      return;
    }
    if (_questionIndex < _questions.length - 1) {
      setState(() => _questionIndex++);
      return;
    }
    if (_attempt!.answers.length != _questions.length) {
      final missing = _questions.indexWhere(
        (item) => !_attempt!.answers.containsKey(item.id),
      );
      setState(() => _questionIndex = missing);
      AppFeedback.warning(
        context,
        'Answer every required question before submitting.',
      );
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(dialogContext.l10n.text('Submit assessment?')),
        content: Text(
          dialogContext.l10n.text(
            'Your answers will be scored and saved as a new result. Previous completed results will remain unchanged.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(dialogContext.l10n.text('Review answers')),
          ),
          FilledButton(
            key: const ValueKey('personality-submit'),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(dialogContext.l10n.text('Submit test')),
          ),
        ],
      ),
    );
    if (confirmed == true) await _submit();
  }

  Future<void> _submit() async {
    setState(() => _view = _AssessmentView.submitting);
    try {
      final previous = _history
          .where((attempt) => attempt.isCompleted && attempt.result != null)
          .firstOrNull
          ?.result
          ?.type;
      final result = _scoring.score(
        attemptId: _attempt!.id,
        questionVersion: _attempt!.testVersion,
        questions: _questions,
        answers: _questions.map(
          (question) => PersonalityAnswer(
            questionId: question.id,
            value: _attempt!.answers[question.id]!,
          ),
        ),
        previousStableType: previous,
      );
      final completed = await _assessmentRepository.complete(_attempt!, result);
      if (!mounted) return;
      _showCloudSyncWarningIfNeeded();
      setState(() {
        _attempt = null;
        _history = [
          completed,
          ..._history.where((value) => value.id != completed.id),
        ];
        _showResult(result);
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _view = _AssessmentView.error;
      });
    }
  }

  void _showResult(PersonalityAssessmentResult result) {
    _result = result;
    _guidance = _guidanceService.generate(result);
    _selectedGuidance
      ..clear()
      ..addAll(_guidance!.suggestions.map((suggestion) => suggestion.id));
    _view = _AssessmentView.result;
  }

  void _showCloudSyncWarningIfNeeded() {
    final syncError = _assessmentRepository.takeLastSyncError();
    if (syncError == null || _syncWarningShown || !mounted) return;
    _syncWarningShown = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      AppFeedback.warning(
        context,
        'Cloud sync is temporarily unavailable. You can continue; progress is saved on this device and will retry automatically.',
      );
    });
  }

  Future<void> _confirmExit() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(dialogContext.l10n.text('Exit assessment?')),
        content: Text(
          dialogContext.l10n.text(
            'Your answered questions are saved. You can resume this version later.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(dialogContext.l10n.text('Continue assessment')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(dialogContext.l10n.text('Save and exit')),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      setState(() => _view = _AssessmentView.introduction);
    }
  }

  Future<void> _applyGuidanceDraft() async {
    final plan = widget.storage
        .readList(widget.user.uid, 'trading_plans')
        .firstOrNull;
    final currentRisk = plan?['plan'] is Map
        ? '${(plan!['plan'] as Map)['risk_percent'] ?? context.l10n.text('Not set')}'
        : context.l10n.text('Not set');
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          dialogContext.l10n.text('Create personality guidance draft?'),
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                dialogContext.l10n.text('Existing Trading Plan risk: {value}', {
                  'value': currentRisk,
                }),
              ),
              const SizedBox(height: 6),
              Text(
                dialogContext.l10n.text('Proposed guidance risk: {value}%', {
                  'value': _guidance!.maximumRiskPercent,
                }),
              ),
              const SizedBox(height: 10),
              Text(
                dialogContext.l10n.text(
                  '{count} selected suggestions will be saved to a versioned draft. Your Trading Plan and Trading Strategy will not be changed.',
                  {'count': _selectedGuidance.length},
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(dialogContext.l10n.text('Cancel')),
          ),
          FilledButton(
            key: const ValueKey('personality-confirm-draft'),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(dialogContext.l10n.text('Create draft')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    final draft = _guidance!.draftFor(_selectedGuidance, _result!.attemptId);
    final history = widget.storage.readList(
      widget.user.uid,
      'personality_guidance_drafts',
    );
    await widget.storage.writeList(
      widget.user.uid,
      'personality_guidance_drafts',
      [Map<String, Object?>.from(draft), ...history],
    );
    await widget.storage.writeMap(
      widget.user.uid,
      'personality_guidance_draft',
      draft,
    );
    if (mounted) {
      AppFeedback.success(
        context,
        'Guidance draft saved. Existing plans and strategies were not changed.',
      );
    }
  }

  Future<void> _showAnswers() => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheetContext) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: .75,
      maxChildSize: .95,
      builder: (_, controller) => ListView.builder(
        controller: controller,
        padding: const EdgeInsets.all(16),
        itemCount: _questions.length + 1,
        itemBuilder: (_, index) {
          if (index == 0) {
            return ListTile(
              title: Text(
                sheetContext.l10n.text('Answer summary'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            );
          }
          final question = _questions[index - 1];
          final value =
              _history
                  .firstWhere((attempt) => attempt.id == _result!.attemptId)
                  .answers[question.id] ??
              0;
          return ListTile(
            leading: CircleAvatar(child: Text('$index')),
            title: Text(sheetContext.l10n.text(question.textKey)),
            subtitle: Text(sheetContext.l10n.text(_answerLabel(value))),
          );
        },
      ),
    ),
  );

  String _answerLabel(int value) => switch (value) {
    1 => 'Strongly disagree',
    2 => 'Disagree',
    3 => 'Slightly disagree',
    4 => 'Neutral',
    5 => 'Slightly agree',
    6 => 'Agree',
    7 => 'Strongly agree',
    _ => 'Not answered',
  };
}

class _DimensionIndicator extends StatelessWidget {
  const _DimensionIndicator({required this.score});
  final PersonalityDimensionScore score;

  @override
  Widget build(BuildContext context) {
    final first = score.firstPercent.round();
    final second = 100 - first;
    return Semantics(
      label:
          '${score.dimension.firstLetter} $first%, ${score.dimension.secondLetter} $second%',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Text(
                '${score.dimension.firstLetter} $first%',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const Spacer(),
              Text(
                '${score.dimension.secondLetter} $second%',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: score.firstPercent / 100,
              minHeight: 12,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '${context.l10n.text(score.dimension.titleKey)} · ${context.l10n.text(score.strength.name)}${score.balanced ? ' · ${context.l10n.text('Balanced preference')}' : ''}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _TwoColumnInsights extends StatelessWidget {
  const _TwoColumnInsights({required this.profile});
  final PersonalityTypeProfile profile;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final strengths = _InsightCard(
        titleKey: 'Key strengths',
        icon: Icons.auto_awesome_outlined,
        values: profile.strengthKeys,
      );
      final challenges = _InsightCard(
        titleKey: 'Potential challenges',
        icon: Icons.warning_amber_outlined,
        values: profile.challengeKeys,
      );
      if (constraints.maxWidth < 700) {
        return Column(
          children: [strengths, const SizedBox(height: 12), challenges],
        );
      }
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: strengths),
          const SizedBox(width: 12),
          Expanded(child: challenges),
        ],
      );
    },
  );
}

class _InsightCard extends StatelessWidget {
  const _InsightCard({
    required this.titleKey,
    required this.icon,
    required this.values,
  });
  final String titleKey;
  final IconData icon;
  final List<String> values;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  context.l10n.text(titleKey),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final value in values)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('• '),
                  Expanded(child: Text(context.l10n.text(value))),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}

class _ProfileDetails extends StatelessWidget {
  const _ProfileDetails({required this.profile});
  final PersonalityTypeProfile profile;

  @override
  Widget build(BuildContext context) {
    final sections = <(String, String)>[
      ('Decision-making style', profile.decisionKey),
      ('Planning style', profile.planningKey),
      ('Stress response', profile.stressKey),
      ('Learning style', profile.learningKey),
      ('Communication tendency', profile.communicationKey),
      ('Risk-management guidance', profile.riskGuidanceKey),
      ('Discipline guidance', profile.disciplineGuidanceKey),
      ('Trading Plan suggestion', profile.planSuggestionKey),
      ('Trading Strategy suggestion', profile.strategySuggestionKey),
    ];
    return Card(
      child: Column(
        children: [
          for (final section in sections)
            ExpansionTile(
              title: Text(context.l10n.text(section.$1)),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              expandedCrossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: double.infinity,
                  child: Text(context.l10n.text(section.$2)),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _DisclaimerCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.secondaryContainer,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              context.l10n.text(
                'This assessment is for education and self-reflection, not a medical or psychological diagnosis. Results may change over time. Trading guidance does not guarantee profit; you remain responsible for every trading and risk decision.',
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
