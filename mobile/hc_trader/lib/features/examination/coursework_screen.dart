import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/coursework_models.dart';
import '../../repositories/coursework_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import 'coursework_chart.dart';
import 'final_exam_screen.dart';

class CourseworkScreen extends StatefulWidget {
  const CourseworkScreen({
    super.key,
    required this.user,
    required this.tradingRepository,
  });

  final AppUser user;
  final TradingRepository tradingRepository;

  @override
  State<CourseworkScreen> createState() => _CourseworkScreenState();
}

class _CourseworkScreenState extends State<CourseworkScreen> {
  late final CourseworkRepository _repository = CourseworkRepository(
    firebaseEnabled: widget.tradingRepository.firebaseEnabled,
    localStorage: widget.tradingRepository.storage,
  );

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<CourseworkDashboardData>(
      stream: _repository.dashboardFor(widget.user.uid),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _CourseworkError(error: snapshot.error);
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final data = snapshot.data!;
        final assignment = data.currentAssignment;
        final attempt = assignment == null
            ? null
            : data.attemptFor(assignment.id);
        return StreamBuilder<AssessmentSettings>(
          stream: _repository.assessmentSettings(),
          builder: (context, settingsSnapshot) {
            final settings =
                settingsSnapshot.data ?? const AssessmentSettings();
            return FutureBuilder<double?>(
              future: _repository.latestFinalExamScore(widget.user.uid),
              builder: (context, finalSnapshot) => _CourseworkOverview(
                assignment: assignment,
                attempt: attempt,
                legacyAttemptCount: data.legacyAttemptCount,
                finalExamScore: finalSnapshot.data,
                settings: settings,
                onOpenCoursework: assignment == null
                    ? null
                    : () => _openCoursework(assignment, attempt),
                onOpenFinalExam: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) =>
                        FinalExamScreen(repository: widget.tradingRepository),
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _openCoursework(
    CourseworkAssignment assignment,
    CourseworkAttempt? attempt,
  ) async {
    var attemptId = attempt?.id;
    if (attemptId == null) {
      try {
        attemptId = await _repository.startAttempt(
          userId: widget.user.uid,
          assignmentId: assignment.id,
        );
      } catch (error) {
        if (mounted) AppFeedback.error(context, error);
        return;
      }
    }
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CourseworkAttemptScreen(
          user: widget.user,
          repository: _repository,
          attemptId: attemptId!,
        ),
      ),
    );
  }
}

class _CourseworkOverview extends StatelessWidget {
  const _CourseworkOverview({
    required this.assignment,
    required this.attempt,
    required this.legacyAttemptCount,
    required this.finalExamScore,
    required this.settings,
    required this.onOpenCoursework,
    required this.onOpenFinalExam,
  });

  final CourseworkAssignment? assignment;
  final CourseworkAttempt? attempt;
  final int legacyAttemptCount;
  final double? finalExamScore;
  final AssessmentSettings settings;
  final VoidCallback? onOpenCoursework;
  final VoidCallback onOpenFinalExam;

  @override
  Widget build(BuildContext context) {
    final language = context.l10n.locale.languageCode;
    final theme = assignment?.themeSnapshot;
    final result = AssessmentCalculator.calculate(
      courseworkRawScore: attempt?.rawScore,
      courseworkFinalised: attempt?.isFinalised == true,
      finalExamRawScore: finalExamScore,
      settings: settings,
    );
    return CustomScrollView(
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
          sliver: SliverList.list(
            children: [
              _JourneyHeader(
                courseworkStatus: attempt?.status ?? assignment?.status,
                hasFinalExam: finalExamScore != null,
                hasOverall: result != null,
              ),
              const SizedBox(height: 20),
              Text(
                context.l10n.text('Coursework'),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.text(
                  'One theme · five questions · 100 marks · 70% contribution',
                ),
              ),
              const SizedBox(height: 12),
              if (assignment == null)
                _NoAssignment(legacyAttemptCount: legacyAttemptCount)
              else
                _AssignmentPanel(
                  assignment: assignment!,
                  attempt: attempt,
                  title: theme!.localizedTitle(language),
                  onOpen: onOpenCoursework!,
                ),
              const SizedBox(height: 24),
              Text(
                context.l10n.text('Final Examination'),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.text(
                  'Randomised quiz assessment · 100 marks · 30% contribution',
                ),
              ),
              const SizedBox(height: 12),
              _FinalExamPanel(
                rawScore: finalExamScore,
                settings: settings,
                onOpen: onOpenFinalExam,
              ),
              const SizedBox(height: 24),
              _OverallPanel(
                result: result,
                courseworkReady: attempt?.isFinalised == true,
                finalExamReady: finalExamScore != null,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _JourneyHeader extends StatelessWidget {
  const _JourneyHeader({
    required this.courseworkStatus,
    required this.hasFinalExam,
    required this.hasOverall,
  });

  final CourseworkStatus? courseworkStatus;
  final bool hasFinalExam;
  final bool hasOverall;

  @override
  Widget build(BuildContext context) {
    final courseworkDone = courseworkStatus == CourseworkStatus.completed;
    final reviewDone = courseworkDone;
    final steps = [
      ('Coursework', courseworkStatus != null),
      ('Review', reviewDone),
      ('Final Examination', hasFinalExam),
      ('Overall Result', hasOverall),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 560;
        return Semantics(
          label: 'Assessment journey',
          child: compact
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final entry in steps.indexed) ...[
                      if (entry.$1 > 0)
                        Center(
                          child: Container(
                            width: 2,
                            height: 10,
                            color: Theme.of(context).colorScheme.outlineVariant,
                          ),
                        ),
                      _JourneyStep(
                        number: entry.$1 + 1,
                        label: entry.$2.$1,
                        complete: entry.$2.$2,
                      ),
                    ],
                  ],
                )
              : Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    for (final entry in steps.indexed) ...[
                      if (entry.$1 > 0)
                        SizedBox(
                          width: 20,
                          child: Container(
                            height: 2,
                            color: Theme.of(context).colorScheme.outlineVariant,
                          ),
                        ),
                      Expanded(
                        child: _JourneyStep(
                          number: entry.$1 + 1,
                          label: entry.$2.$1,
                          complete: entry.$2.$2,
                        ),
                      ),
                    ],
                  ],
                ),
        );
      },
    );
  }
}

class _JourneyStep extends StatelessWidget {
  const _JourneyStep({
    required this.number,
    required this.label,
    required this.complete,
  });
  final int number;
  final String label;
  final bool complete;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        CircleAvatar(
          radius: 15,
          backgroundColor: complete
              ? scheme.primary
              : scheme.surfaceContainerHigh,
          foregroundColor: complete
              ? scheme.onPrimary
              : scheme.onSurfaceVariant,
          child: complete
              ? const Icon(Icons.check, size: 17)
              : Text(
                  '$number',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            context.l10n.text(label),
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}

class _AssignmentPanel extends StatelessWidget {
  const _AssignmentPanel({
    required this.assignment,
    required this.attempt,
    required this.title,
    required this.onOpen,
  });
  final CourseworkAssignment assignment;
  final CourseworkAttempt? attempt;
  final String title;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final progress = attempt?.completion ?? 0;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: scheme.primaryContainer.withValues(alpha: .55),
            padding: const EdgeInsets.all(18),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  backgroundColor: scheme.primary,
                  foregroundColor: scheme.onPrimary,
                  child: const Icon(Icons.assignment_outlined),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${assignment.themeSnapshot.category} · '
                        '${context.l10n.text(assignment.themeSnapshot.difficulty)}',
                      ),
                    ],
                  ),
                ),
                _StatusPill(status: attempt?.status ?? assignment.status),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _SmallMetric(
                        label: 'Progress',
                        value: '${attempt?.completedQuestionCount ?? 0}/5',
                      ),
                    ),
                    Expanded(
                      child: _SmallMetric(
                        label: 'Due date',
                        value: assignment.dueAt == null
                            ? '—'
                            : DateFormat.MMMd().format(
                                assignment.dueAt!.toLocal(),
                              ),
                      ),
                    ),
                    Expanded(
                      child: _SmallMetric(
                        label: 'Estimated time',
                        value:
                            '${assignment.themeSnapshot.estimatedCompletionMinutes} min',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                LinearProgressIndicator(value: progress),
                if (attempt?.rawScore != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    context.l10n.text('Coursework Raw Score: {score}/100', {
                      'score': attempt!.rawScore!.toStringAsFixed(1),
                    }),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ],
                const SizedBox(height: 16),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.icon(
                    onPressed: onOpen,
                    icon: Icon(
                      attempt == null ? Icons.play_arrow : Icons.arrow_forward,
                    ),
                    label: Text(
                      context.l10n.text(
                        attempt == null
                            ? 'Start Coursework'
                            : attempt!.status.canEdit
                            ? 'Continue Coursework'
                            : 'View Coursework',
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NoAssignment extends StatelessWidget {
  const _NoAssignment({required this.legacyAttemptCount});
  final int legacyAttemptCount;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      children: [
        const Icon(Icons.assignment_late_outlined, size: 36),
        const SizedBox(height: 8),
        Text(
          context.l10n.text('No Coursework assigned'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text(
          context.l10n.text(
            'Your next theme will appear here when an administrator assigns it.',
          ),
          textAlign: TextAlign.center,
        ),
        if (legacyAttemptCount > 0) ...[
          const Divider(height: 28),
          Text(
            context.l10n.text('{count} legacy Daily Exam record(s) preserved', {
              'count': legacyAttemptCount,
            }),
          ),
        ],
      ],
    ),
  );
}

class _FinalExamPanel extends StatelessWidget {
  const _FinalExamPanel({
    required this.rawScore,
    required this.settings,
    required this.onOpen,
  });
  final double? rawScore;
  final AssessmentSettings settings;
  final VoidCallback onOpen;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      children: [
        const Icon(Icons.school_outlined, size: 34),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                rawScore == null
                    ? context.l10n.text('Not completed')
                    : context.l10n.text('Raw Score: {score}/100', {
                        'score': rawScore!.toStringAsFixed(1),
                      }),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              if (rawScore != null)
                Text(
                  context.l10n.text('Weighted Contribution: {score}/30', {
                    'score': (rawScore! * settings.finalExamWeight)
                        .toStringAsFixed(1),
                  }),
                ),
            ],
          ),
        ),
        TextButton(onPressed: onOpen, child: Text(context.l10n.text('Open'))),
      ],
    ),
  );
}

class _OverallPanel extends StatelessWidget {
  const _OverallPanel({
    required this.result,
    required this.courseworkReady,
    required this.finalExamReady,
  });
  final OverallAcademicResult? result;
  final bool courseworkReady;
  final bool finalExamReady;
  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: scheme.secondaryContainer.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(18),
      ),
      child: result == null
          ? Row(
              children: [
                const Icon(Icons.hourglass_top_rounded),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    context.l10n.text(
                      !courseworkReady
                          ? 'Overall result is pending Coursework review.'
                          : !finalExamReady
                          ? 'Overall result is pending the Final Examination.'
                          : 'Overall result is pending.',
                    ),
                  ),
                ),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  context.l10n.text('Overall Academic Result'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 24,
                  runSpacing: 12,
                  children: [
                    _ResultMetric(
                      'Coursework',
                      '${result!.courseworkWeightedScore}/70',
                    ),
                    _ResultMetric(
                      'Final Examination',
                      '${result!.finalExamWeightedScore}/30',
                    ),
                    _ResultMetric(
                      'Overall Score',
                      '${result!.overallScore}/100',
                    ),
                    _ResultMetric('Grade', result!.grade),
                    _ResultMetric(
                      'Result',
                      result!.passed ? 'Passed' : 'Not passed',
                    ),
                  ],
                ),
              ],
            ),
    );
  }
}

class _ResultMetric extends StatelessWidget {
  const _ResultMetric(this.label, this.value);
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelMedium,
      ),
      Text(
        value,
        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
      ),
    ],
  );
}

class CourseworkAttemptScreen extends StatefulWidget {
  const CourseworkAttemptScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.attemptId,
  });
  final AppUser user;
  final CourseworkRepository repository;
  final String attemptId;

  @override
  State<CourseworkAttemptScreen> createState() =>
      _CourseworkAttemptScreenState();
}

class _CourseworkAttemptScreenState extends State<CourseworkAttemptScreen> {
  final _answers = <String, CourseworkAnswer>{};
  final _controllers = <String, TextEditingController>{};
  final _saveTimers = <String, Timer>{};
  final _saveStates = <String, String>{};
  final _uploads = <String, CourseworkUploadController>{};
  final _uploadProgress = <String, double>{};
  var _current = 0;
  var _submitting = false;

  @override
  void dispose() {
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    for (final upload in _uploads.values) {
      upload.cancel();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<CourseworkAttempt>(
      stream: widget.repository.watchAttempt(widget.attemptId),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Scaffold(body: _CourseworkError(error: snapshot.error));
        }
        if (!snapshot.hasData) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        final attempt = snapshot.data!;
        _mergeAnswers(attempt);
        if (!attempt.status.canEdit) {
          return Scaffold(
            appBar: AppBar(title: Text(context.l10n.text('Coursework Result'))),
            body: CourseworkResultView(attempt: attempt),
          );
        }
        final questions = [...attempt.themeSnapshot.questions]
          ..sort((left, right) => left.number.compareTo(right.number));
        if (questions.length != 5) {
          return Scaffold(
            appBar: AppBar(title: Text(context.l10n.text('Coursework'))),
            body: const _CourseworkError(
              error: 'The immutable question snapshot is incomplete.',
            ),
          );
        }
        _current = _current.clamp(0, 4);
        final question = questions[_current];
        return Scaffold(
          appBar: AppBar(
            title: Text(
              attempt.themeSnapshot.localizedTitle(
                context.l10n.locale.languageCode,
              ),
            ),
            actions: [
              Padding(
                padding: const EdgeInsets.only(right: 14),
                child: Center(
                  child: _SaveState(value: _saveStates[question.id] ?? 'Saved'),
                ),
              ),
            ],
          ),
          body: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 850;
                final navigator = _QuestionNavigator(
                  questions: questions,
                  answers: _answers,
                  current: _current,
                  onSelected: (value) => setState(() => _current = value),
                );
                final workspace = _QuestionWorkspace(
                  question: question,
                  answer: _answer(question),
                  controller: (field, initial) =>
                      _controller(question.id, field, initial),
                  onChanged: (answer) => _changed(question, answer),
                  onPickEvidence: () => _pickEvidence(question),
                  uploadProgress: _uploadProgress[question.id],
                );
                if (wide) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SizedBox(width: 270, child: navigator),
                      const VerticalDivider(width: 1),
                      Expanded(child: workspace),
                    ],
                  );
                }
                return Column(
                  children: [
                    SizedBox(height: 76, child: navigator),
                    const Divider(height: 1),
                    Expanded(child: workspace),
                  ],
                );
              },
            ),
          ),
          bottomNavigationBar: SafeArea(
            top: false,
            child: Material(
              elevation: 8,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                child: Row(
                  children: [
                    OutlinedButton.icon(
                      onPressed: _current == 0
                          ? null
                          : () => setState(() => _current--),
                      icon: const Icon(Icons.arrow_back),
                      label: Text(context.l10n.text('Previous')),
                    ),
                    const Spacer(),
                    if (_current < 4)
                      FilledButton.icon(
                        onPressed: () => setState(() => _current++),
                        iconAlignment: IconAlignment.end,
                        icon: const Icon(Icons.arrow_forward),
                        label: Text(context.l10n.text('Next')),
                      )
                    else
                      FilledButton.icon(
                        onPressed: _submitting || _uploads.isNotEmpty
                            ? null
                            : () => _review(attempt, questions),
                        icon: const Icon(Icons.fact_check_outlined),
                        label: Text(context.l10n.text('Review submission')),
                      ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  void _mergeAnswers(CourseworkAttempt attempt) {
    for (final question in attempt.themeSnapshot.questions) {
      if (_answers.containsKey(question.id)) continue;
      final server = attempt.answers[question.id];
      final local = widget.repository.restoreLocalDraft(
        userId: widget.user.uid,
        attemptId: widget.attemptId,
        questionId: question.id,
      );
      final selected =
          local != null && local.clientVersion > (server?.clientVersion ?? -1)
          ? local
          : server;
      if (selected != null) _answers[question.id] = selected;
    }
  }

  CourseworkAnswer _answer(CourseworkQuestion question) =>
      _answers[question.id] ??
      CourseworkAnswer(
        questionId: question.id,
        questionNumber: question.number,
        questionType: question.type,
        maximumMark: question.marks,
      );

  TextEditingController _controller(
    String questionId,
    String field,
    String initial,
  ) {
    return _controllers.putIfAbsent(
      '$questionId::$field',
      () => TextEditingController(text: initial),
    );
  }

  void _changed(CourseworkQuestion question, CourseworkAnswer answer) {
    final completed = _isComplete(question, answer);
    final next = answer.copyWith(isCompleted: completed);
    setState(() {
      _answers[question.id] = next;
      _saveStates[question.id] = 'Saving';
    });
    _saveTimers[question.id]?.cancel();
    _saveTimers[question.id] = Timer(
      const Duration(milliseconds: 750),
      () async {
        try {
          await widget.repository.saveDraft(
            userId: widget.user.uid,
            attemptId: widget.attemptId,
            answer: next,
          );
          if (mounted) setState(() => _saveStates[question.id] = 'Saved');
        } catch (_) {
          if (mounted) setState(() => _saveStates[question.id] = 'Save failed');
        }
      },
    );
  }

  bool _isComplete(CourseworkQuestion question, CourseworkAnswer answer) {
    return switch (question.type) {
      CourseworkQuestionType.singleChoice ||
      CourseworkQuestionType.multipleChoice ||
      CourseworkQuestionType.trueFalse => answer.selectedOptionIds.isNotEmpty,
      CourseworkQuestionType.shortAnswer =>
        answer.textAnswer.trim().length >=
            (question.minimumCharacters > 0 ? question.minimumCharacters : 10),
      CourseworkQuestionType.chartZoneSelection =>
        answer.selectedChartZone.isNotEmpty ||
            (answer.selectedMinimumPrice != null &&
                answer.selectedMaximumPrice != null),
      CourseworkQuestionType.marketAnalysis =>
        answer.textAnswer.trim().length >=
                (question.minimumCharacters > 0
                    ? question.minimumCharacters
                    : 40) &&
            (!question.requiresImage ||
                answer.attachments.length >= question.minimumImages),
      CourseworkQuestionType.practicalDemonstration =>
        answer.textAnswer.trim().length >=
                (question.minimumCharacters > 0
                    ? question.minimumCharacters
                    : 80) &&
            (!question.requiresImage ||
                answer.attachments.length >= question.minimumImages),
      CourseworkQuestionType.orderManagementDemonstration =>
        _orderCalculation(answer).valid &&
            answer.textAnswer.trim().length >= 20 &&
            answer.attachments.isNotEmpty,
    };
  }

  OrderManagementCalculation _orderCalculation(CourseworkAnswer answer) =>
      OrderManagementValidator.calculate(
        direction: '${answer.orderManagementData['direction'] ?? ''}',
        entry:
            double.tryParse(
              '${answer.orderManagementData['entry_price'] ?? ''}',
            ) ??
            double.nan,
        stopLoss:
            double.tryParse(
              '${answer.orderManagementData['stop_loss_price'] ?? ''}',
            ) ??
            double.nan,
        takeProfit:
            double.tryParse(
              '${answer.orderManagementData['take_profit_price'] ?? ''}',
            ) ??
            double.nan,
      );

  Future<void> _pickEvidence(CourseworkQuestion question) async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
      withData: true,
    );
    if (selected == null || selected.files.isEmpty || !mounted) return;
    final file = selected.files.single;
    if (file.bytes == null || file.size > 8 * 1024 * 1024) {
      AppFeedback.error(context, 'Choose a readable image smaller than 8 MB.');
      return;
    }
    final extension = (file.extension ?? '').toLowerCase();
    final mime = extension == 'png'
        ? 'image/png'
        : extension == 'webp'
        ? 'image/webp'
        : 'image/jpeg';
    try {
      final upload = widget.repository.uploadEvidence(
        userId: widget.user.uid,
        attemptId: widget.attemptId,
        questionId: question.id,
        fileName: file.name,
        mimeType: mime,
        bytes: file.bytes!,
      );
      setState(() {
        _uploads[question.id] = upload;
        _uploadProgress[question.id] = 0;
      });
      final subscription = upload.progress.listen((value) {
        if (mounted) setState(() => _uploadProgress[question.id] = value);
      });
      final attachment = await upload.result;
      await subscription.cancel();
      final answer = _answer(question);
      _changed(
        question,
        answer.copyWith(attachments: [...answer.attachments, attachment]),
      );
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) {
        setState(() {
          _uploads.remove(question.id);
          _uploadProgress.remove(question.id);
        });
      }
    }
  }

  Future<void> _review(
    CourseworkAttempt attempt,
    List<CourseworkQuestion> questions,
  ) async {
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    for (final question in questions) {
      try {
        await widget.repository.saveDraft(
          userId: widget.user.uid,
          attemptId: widget.attemptId,
          answer: _answer(question),
        );
      } catch (error) {
        if (mounted) AppFeedback.error(context, error);
        return;
      }
    }
    if (!mounted) return;
    final submit = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => CourseworkSubmissionReviewScreen(
          theme: attempt.themeSnapshot,
          answers: Map.unmodifiable(_answers),
        ),
      ),
    );
    if (submit != true || !mounted) return;
    setState(() => _submitting = true);
    try {
      await widget.repository.submitAttempt(
        userId: widget.user.uid,
        attemptId: widget.attemptId,
        declarationAccepted: true,
      );
      await widget.repository.clearLocalDrafts(
        userId: widget.user.uid,
        attemptId: widget.attemptId,
        questionIds: questions.map((item) => item.id),
      );
      if (mounted) {
        AppFeedback.success(context, 'Coursework submitted for review.');
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _QuestionNavigator extends StatelessWidget {
  const _QuestionNavigator({
    required this.questions,
    required this.answers,
    required this.current,
    required this.onSelected,
  });
  final List<CourseworkQuestion> questions;
  final Map<String, CourseworkAnswer> answers;
  final int current;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final vertical = constraints.maxHeight > 150;
      final list = ListView.separated(
        padding: const EdgeInsets.all(12),
        scrollDirection: vertical ? Axis.vertical : Axis.horizontal,
        itemCount: questions.length,
        separatorBuilder: (_, _) =>
            SizedBox(width: vertical ? 0 : 8, height: vertical ? 8 : 0),
        itemBuilder: (context, index) {
          final question = questions[index];
          final answer = answers[question.id];
          return SizedBox(
            width: vertical ? null : 128,
            child: ListTile(
              dense: true,
              selected: current == index,
              selectedTileColor: Theme.of(
                context,
              ).colorScheme.primaryContainer.withValues(alpha: .45),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              leading: CircleAvatar(
                radius: 15,
                child: answer?.isCompleted == true
                    ? const Icon(Icons.check, size: 16)
                    : Text('${index + 1}'),
              ),
              title: Text(
                context.l10n.text(_questionTypeLabel(question.type)),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: vertical
                  ? Text('${question.marks.toStringAsFixed(0)} marks')
                  : null,
              onTap: () => onSelected(index),
            ),
          );
        },
      );
      return list;
    },
  );
}

class _QuestionWorkspace extends StatelessWidget {
  const _QuestionWorkspace({
    required this.question,
    required this.answer,
    required this.controller,
    required this.onChanged,
    required this.onPickEvidence,
    required this.uploadProgress,
  });
  final CourseworkQuestion question;
  final CourseworkAnswer answer;
  final TextEditingController Function(String field, String initial) controller;
  final ValueChanged<CourseworkAnswer> onChanged;
  final VoidCallback onPickEvidence;
  final double? uploadProgress;

  @override
  Widget build(BuildContext context) {
    final language = context.l10n.locale.languageCode;
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 36),
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            Chip(
              label: Text(
                '${context.l10n.text('Question')} ${question.number}',
              ),
            ),
            Chip(
              label: Text(context.l10n.text(_questionTypeLabel(question.type))),
            ),
            Chip(
              label: Text(
                '${question.marks.toStringAsFixed(0)} ${context.l10n.text('marks')}',
              ),
            ),
            if (answer.isFlagged)
              Chip(
                avatar: const Icon(Icons.flag, size: 16),
                label: Text(context.l10n.text('Flagged for review')),
              ),
          ],
        ),
        const SizedBox(height: 16),
        Text(
          question.localizedQuestion(language),
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
        if (question.localizedInstructions(language).isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(
            question.localizedInstructions(language),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ],
        const SizedBox(height: 20),
        _answerArea(context),
        const SizedBox(height: 18),
        Row(
          children: [
            Checkbox(
              value: answer.isFlagged,
              onChanged: (value) =>
                  onChanged(answer.copyWith(isFlagged: value ?? false)),
            ),
            Expanded(
              child: Text(context.l10n.text('Flag this question for review')),
            ),
          ],
        ),
      ],
    );
  }

  Widget _answerArea(BuildContext context) {
    return switch (question.type) {
      CourseworkQuestionType.singleChoice ||
      CourseworkQuestionType.trueFalse => _ChoiceAnswer(
        question: question,
        selected: answer.selectedOptionIds,
        multiple: false,
        onChanged: (values) =>
            onChanged(answer.copyWith(selectedOptionIds: values)),
      ),
      CourseworkQuestionType.multipleChoice => _ChoiceAnswer(
        question: question,
        selected: answer.selectedOptionIds,
        multiple: true,
        onChanged: (values) =>
            onChanged(answer.copyWith(selectedOptionIds: values)),
      ),
      CourseworkQuestionType.shortAnswer => TextField(
        controller: controller('answer', answer.textAnswer),
        minLines: 5,
        maxLines: 12,
        maxLength: 3000,
        decoration: InputDecoration(
          labelText: context.l10n.text('Structured answer'),
          alignLabelWithHint: true,
        ),
        onChanged: (value) => onChanged(answer.copyWith(textAnswer: value)),
      ),
      CourseworkQuestionType.chartZoneSelection => _ChartAnswer(
        question: question,
        answer: answer,
        controller: controller,
        onChanged: onChanged,
      ),
      CourseworkQuestionType.marketAnalysis => _MarketAnalysisAnswer(
        question: question,
        answer: answer,
        controller: controller,
        onChanged: onChanged,
        onPickEvidence: onPickEvidence,
        uploadProgress: uploadProgress,
      ),
      CourseworkQuestionType.practicalDemonstration => _PracticalAnswer(
        question: question,
        answer: answer,
        controller: controller,
        onChanged: onChanged,
        onPickEvidence: onPickEvidence,
        uploadProgress: uploadProgress,
      ),
      CourseworkQuestionType.orderManagementDemonstration => _OrderAnswer(
        answer: answer,
        controller: controller,
        onChanged: onChanged,
        onPickEvidence: onPickEvidence,
        uploadProgress: uploadProgress,
      ),
    };
  }
}

class _ChoiceAnswer extends StatelessWidget {
  const _ChoiceAnswer({
    required this.question,
    required this.selected,
    required this.multiple,
    required this.onChanged,
  });
  final CourseworkQuestion question;
  final List<String> selected;
  final bool multiple;
  final ValueChanged<List<String>> onChanged;
  @override
  Widget build(BuildContext context) {
    final language = context.l10n.locale.languageCode;
    return Column(
      children: [
        for (final option in question.answerOptions)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () {
                if (!multiple) return onChanged([option.id]);
                final next = [...selected];
                next.contains(option.id)
                    ? next.remove(option.id)
                    : next.add(option.id);
                onChanged(next);
              },
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  border: Border.all(
                    color: selected.contains(option.id)
                        ? Theme.of(context).colorScheme.primary
                        : Theme.of(context).colorScheme.outlineVariant,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Icon(
                      selected.contains(option.id)
                          ? multiple
                                ? Icons.check_box
                                : Icons.radio_button_checked
                          : multiple
                          ? Icons.check_box_outline_blank
                          : Icons.radio_button_unchecked,
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Text(option.localized(language))),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _ChartAnswer extends StatelessWidget {
  const _ChartAnswer({
    required this.question,
    required this.answer,
    required this.controller,
    required this.onChanged,
  });
  final CourseworkQuestion question;
  final CourseworkAnswer answer;
  final TextEditingController Function(String, String) controller;
  final ValueChanged<CourseworkAnswer> onChanged;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      CourseworkChart(
        candles: question.chartCandles,
        symbol: question.symbol,
        timeframe: question.timeframe,
        marketDate: question.marketDate,
        zones: question.answerZones,
        selectedZoneId: answer.selectedChartZone,
        onZoneSelected: (id) =>
            onChanged(answer.copyWith(selectedChartZone: id)),
      ),
      const SizedBox(height: 12),
      Wrap(
        spacing: 8,
        children: [
          for (final zone in question.answerZones)
            ChoiceChip(
              label: Text(
                '${zone.label} · ${zone.minimumPrice}–${zone.maximumPrice}',
              ),
              selected: answer.selectedChartZone == zone.id,
              onSelected: (_) =>
                  onChanged(answer.copyWith(selectedChartZone: zone.id)),
            ),
        ],
      ),
      if (question.requiresWrittenExplanation) ...[
        const SizedBox(height: 12),
        TextField(
          controller: controller('answer', answer.textAnswer),
          minLines: 3,
          maxLines: 7,
          decoration: InputDecoration(
            labelText: context.l10n.text('Explain your chart selection'),
            alignLabelWithHint: true,
          ),
          onChanged: (value) => onChanged(answer.copyWith(textAnswer: value)),
        ),
      ],
    ],
  );
}

class _MarketAnalysisAnswer extends StatelessWidget {
  const _MarketAnalysisAnswer({
    required this.question,
    required this.answer,
    required this.controller,
    required this.onChanged,
    required this.onPickEvidence,
    required this.uploadProgress,
  });
  final CourseworkQuestion question;
  final CourseworkAnswer answer;
  final TextEditingController Function(String, String) controller;
  final ValueChanged<CourseworkAnswer> onChanged;
  final VoidCallback onPickEvidence;
  final double? uploadProgress;

  @override
  Widget build(BuildContext context) {
    final fields = <String, String>{
      'bias': 'Market direction or bias',
      'structure': 'Current market structure',
      'support_zones': 'Important support zones',
      'resistance_zones': 'Important resistance zones',
      'entry_scenario': 'Entry scenario',
      'invalidation_scenario': 'Invalidation scenario',
      'risk_considerations': 'Risk considerations',
      'alternative_scenario': 'Alternative market scenario',
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _MarketSnapshot(question: question),
        const SizedBox(height: 14),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            SizedBox(
              width: 240,
              child: TextFormField(
                controller: controller(
                  'symbol',
                  '${answer.marketAnalysisData['symbol'] ?? question.symbol}',
                ),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Symbol'),
                ),
                onChanged: (value) => _dataChanged('symbol', value),
              ),
            ),
            SizedBox(
              width: 240,
              child: TextFormField(
                controller: controller(
                  'timeframe',
                  '${answer.marketAnalysisData['timeframe'] ?? question.timeframe}',
                ),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Timeframe'),
                ),
                onChanged: (value) => _dataChanged('timeframe', value),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        for (final field in fields.entries) ...[
          TextField(
            controller: controller(
              field.key,
              '${answer.marketAnalysisData[field.key] ?? ''}',
            ),
            minLines: 2,
            maxLines: 5,
            decoration: InputDecoration(
              labelText: context.l10n.text(field.value),
              alignLabelWithHint: true,
            ),
            onChanged: (value) => _dataChanged(field.key, value),
          ),
          const SizedBox(height: 12),
        ],
        TextField(
          controller: controller('answer', answer.textAnswer),
          minLines: 5,
          maxLines: 12,
          decoration: InputDecoration(
            labelText: context.l10n.text('Complete written market analysis'),
            alignLabelWithHint: true,
          ),
          onChanged: (value) => onChanged(answer.copyWith(textAnswer: value)),
        ),
        const SizedBox(height: 14),
        _EvidenceArea(
          attachments: answer.attachments,
          progress: uploadProgress,
          onPick: onPickEvidence,
        ),
      ],
    );
  }

  void _dataChanged(String key, String value) {
    onChanged(
      answer.copyWith(
        marketAnalysisData: {...answer.marketAnalysisData, key: value},
      ),
    );
  }
}

class _PracticalAnswer extends StatelessWidget {
  const _PracticalAnswer({
    required this.question,
    required this.answer,
    required this.controller,
    required this.onChanged,
    required this.onPickEvidence,
    required this.uploadProgress,
  });

  final CourseworkQuestion question;
  final CourseworkAnswer answer;
  final TextEditingController Function(String, String) controller;
  final ValueChanged<CourseworkAnswer> onChanged;
  final VoidCallback onPickEvidence;
  final double? uploadProgress;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (question.chartCandles.isNotEmpty) ...[
        _MarketSnapshot(question: question),
        const SizedBox(height: 14),
      ],
      TextField(
        controller: controller('answer', answer.textAnswer),
        minLines: 5,
        maxLines: 12,
        maxLength: question.maximumCharacters > 0
            ? question.maximumCharacters
            : 1500,
        decoration: InputDecoration(
          labelText: context.l10n.text('Practical demonstration explanation'),
          helperText: context.l10n.text(
            'Explain the annotations, invalidation, target, and risk controls shown in your evidence.',
          ),
          alignLabelWithHint: true,
        ),
        onChanged: (value) => onChanged(answer.copyWith(textAnswer: value)),
      ),
      const SizedBox(height: 14),
      _EvidenceArea(
        attachments: answer.attachments,
        progress: uploadProgress,
        onPick: onPickEvidence,
      ),
    ],
  );
}

class _OrderAnswer extends StatelessWidget {
  const _OrderAnswer({
    required this.answer,
    required this.controller,
    required this.onChanged,
    required this.onPickEvidence,
    required this.uploadProgress,
  });
  final CourseworkAnswer answer;
  final TextEditingController Function(String, String) controller;
  final ValueChanged<CourseworkAnswer> onChanged;
  final VoidCallback onPickEvidence;
  final double? uploadProgress;

  @override
  Widget build(BuildContext context) {
    final data = answer.orderManagementData;
    final calculation = OrderManagementValidator.calculate(
      direction: '${data['direction'] ?? ''}',
      entry: double.tryParse('${data['entry_price'] ?? ''}') ?? double.nan,
      stopLoss:
          double.tryParse('${data['stop_loss_price'] ?? ''}') ?? double.nan,
      takeProfit:
          double.tryParse('${data['take_profit_price'] ?? ''}') ?? double.nan,
    );
    const numeric = {
      'entry_price': 'Entry price',
      'stop_loss_price': 'Stop-loss price',
      'take_profit_price': 'Take-profit price',
      'position_size': 'Position size',
      'risk_percentage': 'Risk percentage',
      'risk_amount': 'Risk amount',
      'reward_amount': 'Reward amount',
    };
    const explanation = {
      'entry_justification': 'Entry justification',
      'stop_loss_justification': 'Stop-loss justification',
      'take_profit_justification': 'Take-profit justification',
      'invalidation_condition': 'Invalidation condition',
      'simulation_source': 'Demo or simulation source',
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Theme.of(
              context,
            ).colorScheme.tertiaryContainer.withValues(alpha: .45),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            context.l10n.text(
              'Use a demo account, simulation, replay environment, or historical example. Do not place a real-money trade for this assignment.',
            ),
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            SizedBox(
              width: 220,
              child: TextField(
                controller: controller('symbol', '${data['symbol'] ?? ''}'),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Symbol'),
                ),
                onChanged: (value) => _change('symbol', value),
              ),
            ),
            SizedBox(
              width: 220,
              child: DropdownButtonFormField<String>(
                initialValue: const {'buy', 'sell'}.contains(data['direction'])
                    ? '${data['direction']}'
                    : null,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Direction'),
                ),
                items: const [
                  DropdownMenuItem(value: 'buy', child: Text('Buy')),
                  DropdownMenuItem(value: 'sell', child: Text('Sell')),
                ],
                onChanged: (value) => _change('direction', value ?? ''),
              ),
            ),
            for (final field in numeric.entries)
              SizedBox(
                width: 220,
                child: TextField(
                  controller: controller(field.key, '${data[field.key] ?? ''}'),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: context.l10n.text(field.value),
                  ),
                  onChanged: (value) => _change(field.key, value),
                ),
              ),
          ],
        ),
        const SizedBox(height: 14),
        _OrderMetrics(calculation: calculation),
        const SizedBox(height: 14),
        for (final field in explanation.entries) ...[
          TextField(
            controller: controller(field.key, '${data[field.key] ?? ''}'),
            minLines: 2,
            maxLines: 5,
            decoration: InputDecoration(
              labelText: context.l10n.text(field.value),
            ),
            onChanged: (value) => _change(field.key, value),
          ),
          const SizedBox(height: 12),
        ],
        TextField(
          controller: controller('answer', answer.textAnswer),
          minLines: 4,
          maxLines: 10,
          decoration: InputDecoration(
            labelText: context.l10n.text('Additional explanation'),
            alignLabelWithHint: true,
          ),
          onChanged: (value) => onChanged(answer.copyWith(textAnswer: value)),
        ),
        const SizedBox(height: 14),
        _EvidenceArea(
          attachments: answer.attachments,
          progress: uploadProgress,
          onPick: onPickEvidence,
        ),
      ],
    );
  }

  void _change(String key, String value) => onChanged(
    answer.copyWith(
      orderManagementData: {...answer.orderManagementData, key: value},
    ),
  );
}

class _OrderMetrics extends StatelessWidget {
  const _OrderMetrics({required this.calculation});
  final OrderManagementCalculation calculation;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(12),
      border: Border.all(
        color: calculation.valid
            ? const Color(0xFF16866D)
            : Theme.of(context).colorScheme.outlineVariant,
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 24,
          runSpacing: 10,
          children: [
            _ResultMetric(
              'Stop-loss distance',
              calculation.stopLossDistance.toStringAsFixed(5),
            ),
            _ResultMetric(
              'Take-profit distance',
              calculation.takeProfitDistance.toStringAsFixed(5),
            ),
            _ResultMetric(
              'Risk-to-reward ratio',
              '1:${calculation.riskRewardRatio.toStringAsFixed(2)}',
            ),
          ],
        ),
        if (calculation.error != null) ...[
          const SizedBox(height: 8),
          Text(
            context.l10n.text(calculation.error!),
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ],
      ],
    ),
  );
}

class _MarketSnapshot extends StatelessWidget {
  const _MarketSnapshot({required this.question});
  final CourseworkQuestion question;
  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 18,
    runSpacing: 8,
    children: [
      _ResultMetric('Assigned market', question.symbol),
      _ResultMetric('Timeframe', question.timeframe),
      _ResultMetric(
        'Market date',
        question.marketDate == null
            ? '—'
            : DateFormat.yMMMd().add_jm().format(
                question.marketDate!.toLocal(),
              ),
      ),
    ],
  );
}

class _EvidenceArea extends StatelessWidget {
  const _EvidenceArea({
    required this.attachments,
    required this.progress,
    required this.onPick,
  });
  final List<CourseworkAttachment> attachments;
  final double? progress;
  final VoidCallback onPick;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Icon(Icons.attach_file),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                context.l10n.text('Screenshot evidence'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            OutlinedButton.icon(
              onPressed: progress == null ? onPick : null,
              icon: const Icon(Icons.upload_file),
              label: Text(context.l10n.text('Add image')),
            ),
          ],
        ),
        if (progress != null) ...[
          const SizedBox(height: 10),
          LinearProgressIndicator(value: progress),
          const SizedBox(height: 4),
          Text(
            context.l10n.text('Uploading {percent}%', {
              'percent': (progress! * 100).round(),
            }),
          ),
        ],
        for (final attachment in attachments)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.image_outlined),
            title: Text(attachment.fileName),
            subtitle: Text(
              '${(attachment.sizeBytes / 1024).toStringAsFixed(0)} KB · protected',
            ),
            trailing: const Icon(Icons.check_circle, color: Color(0xFF16866D)),
          ),
      ],
    ),
  );
}

class CourseworkSubmissionReviewScreen extends StatefulWidget {
  const CourseworkSubmissionReviewScreen({
    super.key,
    required this.theme,
    required this.answers,
  });
  final CourseworkTheme theme;
  final Map<String, CourseworkAnswer> answers;
  @override
  State<CourseworkSubmissionReviewScreen> createState() =>
      _CourseworkSubmissionReviewScreenState();
}

class _CourseworkSubmissionReviewScreenState
    extends State<CourseworkSubmissionReviewScreen> {
  var _declared = false;
  @override
  Widget build(BuildContext context) {
    final questions = [...widget.theme.questions]
      ..sort((a, b) => a.number.compareTo(b.number));
    final allComplete = questions.every(
      (question) => widget.answers[question.id]?.isCompleted == true,
    );
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Review submission'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 32),
        children: [
          Text(
            context.l10n.text('Check every answer before final submission.'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          for (final question in questions)
            ListTile(
              leading: CircleAvatar(child: Text('${question.number}')),
              title: Text(
                question.localizedTitle(context.l10n.locale.languageCode),
              ),
              subtitle: Text(
                '${question.marks.toStringAsFixed(0)} ${context.l10n.text('marks')} · '
                '${widget.answers[question.id]?.attachments.length ?? 0} ${context.l10n.text('attachment(s)')}',
              ),
              trailing: Icon(
                widget.answers[question.id]?.isCompleted == true
                    ? Icons.check_circle
                    : Icons.error_outline,
                color: widget.answers[question.id]?.isCompleted == true
                    ? const Color(0xFF16866D)
                    : Theme.of(context).colorScheme.error,
              ),
            ),
          const Divider(height: 28),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _declared,
            onChanged: (value) => setState(() => _declared = value ?? false),
            title: Text(
              context.l10n.text(
                'I confirm that this submission represents my own analysis and work. Any charts or screenshots submitted are used for educational assessment purposes.',
              ),
            ),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: allComplete && _declared
                ? () => Navigator.of(context).pop(true)
                : null,
            icon: const Icon(Icons.send_outlined),
            label: Text(context.l10n.text('Submit Coursework')),
          ),
          if (!allComplete)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                context.l10n.text(
                  'Complete every required answer and upload before submitting.',
                ),
                textAlign: TextAlign.center,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
        ],
      ),
    );
  }
}

class CourseworkResultView extends StatelessWidget {
  const CourseworkResultView({super.key, required this.attempt});
  final CourseworkAttempt attempt;
  @override
  Widget build(BuildContext context) {
    final questions = [...attempt.themeSnapshot.questions]
      ..sort((a, b) => a.number.compareTo(b.number));
    final pending = !attempt.isFinalised;
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 32),
      children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Theme.of(
              context,
            ).colorScheme.primaryContainer.withValues(alpha: .5),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            children: [
              Icon(
                pending
                    ? Icons.hourglass_top
                    : Icons.workspace_premium_outlined,
                size: 42,
              ),
              const SizedBox(height: 8),
              Text(
                context.l10n.text(
                  pending ? 'Under Review' : 'Coursework Graded',
                ),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 26,
                runSpacing: 12,
                alignment: WrapAlignment.center,
                children: [
                  _ResultMetric(
                    'Raw Score',
                    attempt.rawScore == null
                        ? 'Pending'
                        : '${attempt.rawScore}/100',
                  ),
                  _ResultMetric(
                    'Weighted Score',
                    attempt.weightedScore == null
                        ? 'Pending'
                        : '${attempt.weightedScore}/70',
                  ),
                  _ResultMetric('Revision', '${attempt.revisionNumber}'),
                ],
              ),
            ],
          ),
        ),
        if (attempt.reviewerFeedback.isNotEmpty) ...[
          const SizedBox(height: 18),
          Text(
            context.l10n.text('Reviewer feedback'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(attempt.reviewerFeedback),
        ],
        const SizedBox(height: 18),
        Text(
          context.l10n.text('Marks by question'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        for (final question in questions)
          ListTile(
            leading: CircleAvatar(child: Text('${question.number}')),
            title: Text(
              question.localizedTitle(context.l10n.locale.languageCode),
            ),
            subtitle: Text(
              attempt.answers[question.id]?.reviewerFeedback ?? '',
            ),
            trailing: Text(
              '${attempt.answers[question.id]?.automaticMark ?? attempt.answers[question.id]?.manualMark ?? '—'} / ${question.marks.toStringAsFixed(0)}',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
      ],
    );
  }
}

class _SmallMetric extends StatelessWidget {
  const _SmallMetric({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelSmall,
      ),
      const SizedBox(height: 2),
      Text(value, style: const TextStyle(fontWeight: FontWeight.w900)),
    ],
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});
  final CourseworkStatus status;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surface.withValues(alpha: .75),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      context.l10n.text(_statusLabel(status)),
      style: Theme.of(
        context,
      ).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w900),
    ),
  );
}

class _SaveState extends StatelessWidget {
  const _SaveState({required this.value});
  final String value;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(
        value == 'Saving'
            ? Icons.sync
            : value == 'Save failed'
            ? Icons.sync_problem
            : Icons.cloud_done_outlined,
        size: 17,
        color: value == 'Save failed'
            ? Theme.of(context).colorScheme.error
            : null,
      ),
      const SizedBox(width: 5),
      Text(context.l10n.text(value)),
    ],
  );
}

class _CourseworkError extends StatelessWidget {
  const _CourseworkError({required this.error});
  final Object? error;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 42,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 10),
          Text(
            context.l10n.text('Coursework could not be loaded.'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 5),
          Text('$error', textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

String _questionTypeLabel(CourseworkQuestionType type) => switch (type) {
  CourseworkQuestionType.singleChoice ||
  CourseworkQuestionType.multipleChoice ||
  CourseworkQuestionType.trueFalse ||
  CourseworkQuestionType.shortAnswer => 'Theory Question',
  CourseworkQuestionType.chartZoneSelection => 'Chart Question',
  CourseworkQuestionType.marketAnalysis => 'Self-Analysis',
  CourseworkQuestionType.practicalDemonstration => 'Practical Demonstration',
  CourseworkQuestionType.orderManagementDemonstration => 'Order Management',
};

String _statusLabel(CourseworkStatus status) => switch (status) {
  CourseworkStatus.assigned || CourseworkStatus.notStarted => 'Assigned',
  CourseworkStatus.inProgress || CourseworkStatus.draft => 'In Progress',
  CourseworkStatus.submitted || CourseworkStatus.underReview => 'Under Review',
  CourseworkStatus.revisionRequested => 'Revision Required',
  CourseworkStatus.resubmitted => 'Resubmitted',
  CourseworkStatus.graded || CourseworkStatus.completed => 'Completed',
  CourseworkStatus.late => 'Late',
  CourseworkStatus.expired => 'Expired',
  CourseworkStatus.cancelled => 'Cancelled',
};
