import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/trading_exam_models.dart';

class DailyExamResultScreen extends StatelessWidget {
  const DailyExamResultScreen({
    super.key,
    required this.attempt,
    this.currentQuestions = const [],
  });

  final TradingExamAttemptMobile attempt;
  final List<TradingExamQuestionMobile> currentQuestions;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final results = attempt.questionResults.isNotEmpty
        ? attempt.questionResults
        : _legacyResults();
    final passed = attempt.passed;
    final pendingReview = attempt.status == 'pending_review';
    final resultColor = passed == null
        ? Theme.of(context).colorScheme.primary
        : passed
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.error;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.text('Examination Result'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: resultColor.withValues(alpha: .12),
                    child: Icon(
                      pendingReview
                          ? Icons.hourglass_top_rounded
                          : passed == false
                          ? Icons.close_rounded
                          : Icons.check_rounded,
                      color: resultColor,
                      size: 34,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    l10n.text(
                      pendingReview
                          ? 'Pending review'
                          : passed == null
                          ? 'Completed'
                          : passed
                          ? 'Passed'
                          : 'Not passed',
                    ),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: resultColor,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (pendingReview) ...[
                    Text(
                      l10n.text(
                        'Your chart analysis was submitted securely. An administrator will review it and notify you.',
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 14),
                  ],
                  Row(
                    children: [
                      _Metric(
                        label: l10n.text('Total questions'),
                        value: '${attempt.totalQuestions}',
                      ),
                      _Metric(
                        label: l10n.text('Correct answers'),
                        value: '${attempt.score}',
                      ),
                      _Metric(
                        label: l10n.text('Wrong answers'),
                        value: '${attempt.wrongAnswers}',
                      ),
                      _Metric(
                        label: l10n.text('Score'),
                        value: '${attempt.percentage}%',
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          for (final entry in results.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ResultCard(number: entry.$1 + 1, result: entry.$2),
            ),
        ],
      ),
    );
  }

  List<TradingExamQuestionResultMobile> _legacyResults() {
    return currentQuestions
        .where((question) => attempt.answers.containsKey(question.id))
        .map(
          (question) => TradingExamQuestionResultMobile.fromQuestion(
            question,
            attempt.answers[question.id]!,
          ),
        )
        .toList(growable: false);
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.labelSmall,
        ),
      ],
    ),
  );
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.number, required this.result});

  final int number;
  final TradingExamQuestionResultMobile result;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final pendingReview = result.reviewStatus == 'pending_review';
    final color = result.isCorrect
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.error;
    final languageCode = l10n.locale.languageCode;
    final options = result.localizedOptions(languageCode);
    String? answerAt(int index) =>
        index >= 0 && index < options.length ? options[index] : null;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  pendingReview
                      ? Icons.hourglass_top_rounded
                      : result.isCorrect
                      ? Icons.check_circle
                      : Icons.cancel_rounded,
                  color: color,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    '$number. ${result.localizedQuestionText(languageCode)}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (result.writtenExplanation.trim().isNotEmpty) ...[
              Text(
                l10n.text('Your written analysis'),
                style: Theme.of(
                  context,
                ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              Text(result.writtenExplanation),
              if (result.screenshotUrl.trim().isNotEmpty) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.network(
                    result.screenshotUrl,
                    height: 180,
                    width: double.infinity,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const SizedBox.shrink(),
                  ),
                ),
              ],
              const SizedBox(height: 8),
              _AnswerLine(
                label: l10n.text('Review status'),
                value: l10n.text(pendingReview ? 'Pending review' : 'Reviewed'),
                color: pendingReview
                    ? Theme.of(context).colorScheme.primary
                    : const Color(0xFF17855B),
              ),
              if (result.reviewFeedback.trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(result.reviewFeedback),
              ],
            ] else ...[
              _AnswerLine(
                label: l10n.text('Your answer'),
                value:
                    answerAt(result.selectedIndex) ?? l10n.text('Not answered'),
                color: color,
              ),
            ],
            if (!pendingReview &&
                result.writtenExplanation.isEmpty &&
                !result.isCorrect) ...[
              const SizedBox(height: 8),
              _AnswerLine(
                label: l10n.text('Correct answer'),
                value: answerAt(result.correctIndex) ?? '—',
                color: const Color(0xFF17855B),
              ),
            ],
            if (result
                .localizedExplanation(languageCode)
                .trim()
                .isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                l10n.text('Explanation'),
                style: Theme.of(
                  context,
                ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 3),
              Text(result.localizedExplanation(languageCode)),
            ],
          ],
        ),
      ),
    );
  }
}

class _AnswerLine extends StatelessWidget {
  const _AnswerLine({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: color.withValues(alpha: .35)),
    ),
    child: Text('$label: $value'),
  );
}
