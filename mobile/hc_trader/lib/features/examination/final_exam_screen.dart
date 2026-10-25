import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/final_exam_mobile.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';

class FinalExamScreen extends StatefulWidget {
  const FinalExamScreen({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<FinalExamScreen> createState() => _FinalExamScreenState();
}

class _FinalExamScreenState extends State<FinalExamScreen> {
  late Future<FinalExamStatusMobile> _status;
  FinalExamAttemptMobile? _attempt;
  final _answers = <String, String>{};
  var _questionIndex = 0;
  var _busy = false;
  Map<String, dynamic>? _result;

  @override
  void initState() {
    super.initState();
    _status = widget.repository.finalExamStatus();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Final Examination'))),
      body: FutureBuilder<FinalExamStatusMobile>(
        future: _status,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(
              message: snapshot.error.toString(),
              onRetry: _reload,
            );
          }
          final status = snapshot.data!;
          _attempt ??= status.currentAttempt;
          if (_result != null) {
            return _ResultView(data: _result!, onReload: _reload);
          }
          if (_attempt != null && _attempt!.questions.isNotEmpty) {
            return _exam(_attempt!);
          }
          return _intro(status);
        },
      ),
    );
  }

  Widget _intro(FinalExamStatusMobile status) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  status.passed ? Icons.verified : Icons.school_outlined,
                  size: 42,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 12),
                Text(
                  status.passed
                      ? context.l10n.text('Examination passed')
                      : context.l10n.text('Trading Final Examination'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  context.l10n.text(
                    '50 questions: Foundation, Technique and Analysis, and Psychology. Passing score: {percent}%.',
                    {'percent': status.passingPercentage},
                  ),
                ),
                if (status.message.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Text(status.message),
                  ),
                const SizedBox(height: 16),
                if (!status.passed)
                  FilledButton.icon(
                    onPressed: status.allowed && !_busy ? _start : null,
                    icon: _busy
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.play_arrow),
                    label: Text(
                      status.currentAttempt == null
                          ? context.l10n.text('Start examination')
                          : context.l10n.text('Resume examination'),
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (status.passed && status.propFirmModels.isNotEmpty) ...[
          const SizedBox(height: 14),
          _PropFirmSelection(
            models: status.propFirmModels,
            onSelected: _selectPropFirm,
          ),
        ],
        if (status.recentAttempts.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(
            context.l10n.text('Attempt history'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          for (final attempt in status.recentAttempts)
            Card(
              child: ListTile(
                leading: Icon(
                  attempt.status == 'passed'
                      ? Icons.check_circle
                      : Icons.cancel_outlined,
                  color: attempt.status == 'passed'
                      ? const Color(0xFF17855B)
                      : const Color(0xFFC23B36),
                ),
                title: Text('${attempt.scorePercentage.toStringAsFixed(1)}%'),
                subtitle: Text(attempt.status.toUpperCase()),
                trailing: Text(
                  '${attempt.correctAnswers}/${attempt.totalQuestions}',
                ),
              ),
            ),
        ],
      ],
    );
  }

  Widget _exam(FinalExamAttemptMobile attempt) {
    final question = attempt.questions[_questionIndex];
    final selected = _answers[question.attemptQuestionId];
    final l10n = context.l10n;
    final languageCode = l10n.locale.languageCode;
    return Column(
      children: [
        LinearProgressIndicator(
          value: (_questionIndex + 1) / attempt.questions.length,
          minHeight: 6,
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                l10n.text('Question {current} of {total}', {
                  'current': _questionIndex + 1,
                  'total': attempt.questions.length,
                }),
                style: Theme.of(context).textTheme.labelLarge,
              ),
              const SizedBox(height: 4),
              Text(
                question.localizedCategory(languageCode),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (question.localizedScenarioText(languageCode).isNotEmpty) ...[
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text(question.localizedScenarioText(languageCode)),
                  ),
                ),
              ],
              if (question.chartData != null) ...[
                const SizedBox(height: 12),
                _ExamChartCard(
                  data: question.chartData!,
                  timeframe: question.chartTimeframe,
                ),
              ],
              if (question.chartImageUrl?.isNotEmpty == true) ...[
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    question.chartImageUrl!,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const SizedBox(
                      height: 160,
                      child: Center(child: Icon(Icons.broken_image_outlined)),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              Text(
                question.localizedQuestion(languageCode),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              for (final option in question.options.entries)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Card(
                    child: InkWell(
                      borderRadius: BorderRadius.circular(8),
                      onTap: () => setState(
                        () => _answers[question.attemptQuestionId] = option.key,
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            Icon(
                              selected == option.key
                                  ? Icons.radio_button_checked
                                  : Icons.radio_button_off,
                              color: selected == option.key
                                  ? Theme.of(context).colorScheme.primary
                                  : null,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                '${option.key}. ${question.localizedOption(option.key, languageCode)}',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Row(
              children: [
                OutlinedButton(
                  onPressed: _questionIndex == 0
                      ? null
                      : () => setState(() => _questionIndex--),
                  child: Text(l10n.text('Previous')),
                ),
                const Spacer(),
                Text(
                  l10n.text('{answered}/{total} answered', {
                    'answered': _answers.length,
                    'total': attempt.questions.length,
                  }),
                ),
                const Spacer(),
                if (_questionIndex < attempt.questions.length - 1)
                  FilledButton(
                    onPressed: selected == null
                        ? null
                        : () => setState(() => _questionIndex++),
                    child: Text(l10n.text('Next')),
                  )
                else
                  FilledButton(
                    onPressed:
                        _busy || _answers.length != attempt.questions.length
                        ? null
                        : _submit,
                    child: Text(l10n.text(_busy ? 'Submitting…' : 'Submit')),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _start() async {
    setState(() => _busy = true);
    try {
      final attempt = await widget.repository.startFinalExam();
      setState(() {
        _attempt = attempt;
        _questionIndex = 0;
        _answers.clear();
      });
    } catch (error) {
      _error(error);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    try {
      final result = await widget.repository.submitFinalExam(
        attemptId: _attempt!.id,
        answers: _answers,
      );
      setState(() => _result = result);
    } catch (error) {
      _error(error);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _selectPropFirm(String id) async {
    final selectedMessage = context.l10n.text('Prop firm model selected.');
    try {
      await widget.repository.selectPropFirmModel(id);
      _message(selectedMessage);
      _reload();
    } catch (error) {
      _error(error);
    }
  }

  void _reload() => setState(() {
    _attempt = null;
    _result = null;
    _status = widget.repository.finalExamStatus();
  });

  void _message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  void _error(Object error) {
    if (mounted) AppFeedback.error(context, error);
  }
}

class _ExamChartCard extends StatelessWidget {
  const _ExamChartCard({required this.data, required this.timeframe});

  final Map<String, dynamic> data;
  final String timeframe;

  @override
  Widget build(BuildContext context) {
    final symbol = (data['symbol'] ?? 'Market chart').toString();
    final rawCandles = data['candles'];
    final candles = rawCandles is List
        ? rawCandles
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
        : const <Map<String, dynamic>>[];
    if (candles.isEmpty) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLowest,
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.candlestick_chart_outlined, size: 19),
              const SizedBox(width: 8),
              Text(
                '$symbol ${timeframe.isEmpty ? '' : '· $timeframe'}'.trim(),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const Spacer(),
              Text(
                context.l10n.text('Read the chart'),
                style: const TextStyle(fontSize: 12),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 210,
            width: double.infinity,
            child: CustomPaint(
              painter: _CandlestickPainter(
                candles: candles,
                gridColor: Theme.of(context).colorScheme.outlineVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CandlestickPainter extends CustomPainter {
  const _CandlestickPainter({required this.candles, required this.gridColor});

  final List<Map<String, dynamic>> candles;
  final Color gridColor;

  double _number(Object? value) => value is num
      ? value.toDouble()
      : double.tryParse(value?.toString() ?? '') ?? 0;

  @override
  void paint(Canvas canvas, Size size) {
    final highs = candles.map((candle) => _number(candle['high'])).toList();
    final lows = candles.map((candle) => _number(candle['low'])).toList();
    final high = highs.reduce((a, b) => a > b ? a : b);
    final low = lows.reduce((a, b) => a < b ? a : b);
    final range = high == low ? 1.0 : high - low;
    final grid = Paint()
      ..color = gridColor.withValues(alpha: .55)
      ..strokeWidth = 1;
    for (var index = 0; index <= 4; index++) {
      final y = size.height * index / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }

    final slot = size.width / candles.length;
    final bodyWidth = (slot * .56).clamp(3.0, 11.0);
    double y(double price) => 5 + ((high - price) / range) * (size.height - 10);

    for (var index = 0; index < candles.length; index++) {
      final candle = candles[index];
      final open = _number(candle['open']);
      final close = _number(candle['close']);
      final x = slot * index + slot / 2;
      final color = close >= open
          ? const Color(0xFF1AA37A)
          : const Color(0xFFE05252);
      final paint = Paint()
        ..color = color
        ..strokeWidth = 1.4;
      canvas.drawLine(
        Offset(x, y(_number(candle['high']))),
        Offset(x, y(_number(candle['low']))),
        paint,
      );
      final top = y(open > close ? open : close);
      final bottom = y(open < close ? open : close);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTRB(
            x - bodyWidth / 2,
            top,
            x + bodyWidth / 2,
            bottom <= top + 2 ? top + 2 : bottom,
          ),
          const Radius.circular(1.5),
        ),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _CandlestickPainter oldDelegate) =>
      oldDelegate.candles != candles || oldDelegate.gridColor != gridColor;
}

class _PropFirmSelection extends StatefulWidget {
  const _PropFirmSelection({required this.models, required this.onSelected});
  final List<Map<String, dynamic>> models;
  final ValueChanged<String> onSelected;
  @override
  State<_PropFirmSelection> createState() => _PropFirmSelectionState();
}

class _PropFirmSelectionState extends State<_PropFirmSelection> {
  String? _selected;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text('Select prop firm model'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _selected,
            items: widget.models
                .map(
                  (model) => DropdownMenuItem(
                    value: model['id'].toString(),
                    child: Text((model['name'] ?? '').toString()),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _selected = value),
            decoration: InputDecoration(labelText: context.l10n.text('Model')),
          ),
          const SizedBox(height: 10),
          FilledButton(
            onPressed: _selected == null
                ? null
                : () => widget.onSelected(_selected!),
            child: Text(context.l10n.text('Confirm selection')),
          ),
        ],
      ),
    ),
  );
}

class _ResultView extends StatelessWidget {
  const _ResultView({required this.data, required this.onReload});
  final Map<String, dynamic> data;
  final VoidCallback onReload;
  @override
  Widget build(BuildContext context) {
    final rawAttempt = Map<String, dynamic>.from(data['attempt'] as Map);
    final attempt = FinalExamAttemptMobile.fromJson(rawAttempt);
    final rawAnswers = rawAttempt['answers'];
    final answers = rawAnswers is Map
        ? rawAnswers.map(
            (key, value) => MapEntry('$key', '$value'.trim().toUpperCase()),
          )
        : const <String, String>{};
    final passed = attempt.status == 'passed';
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Column(
              children: [
                Icon(
                  passed ? Icons.verified : Icons.refresh,
                  size: 56,
                  color: passed
                      ? const Color(0xFF17855B)
                      : const Color(0xFFD99A17),
                ),
                const SizedBox(height: 12),
                Text(
                  context.l10n.text(passed ? 'Passed' : 'Retake required'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  context.l10n.text('{score}% · {correct}/{total} correct', {
                    'score': attempt.scorePercentage.toStringAsFixed(1),
                    'correct': attempt.correctAnswers,
                    'total': attempt.totalQuestions,
                  }),
                ),
                const SizedBox(height: 6),
                Text(
                  context.l10n.text(
                    'Final Examination Raw Score: {score}/100',
                    {'score': attempt.scorePercentage.toStringAsFixed(1)},
                  ),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                Text(
                  context.l10n.text('Weighted Contribution: {score}/30', {
                    'score': (attempt.scorePercentage * .30).toStringAsFixed(1),
                  }),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: onReload,
                  child: Text(
                    context.l10n.text(
                      passed ? 'Continue' : 'View retake availability',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (attempt.questions.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(
            context.l10n.text('Review'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          for (final entry in attempt.questions.indexed)
            _FinalResultQuestionCard(
              number: entry.$1 + 1,
              question: entry.$2,
              selectedKey: answers[entry.$2.attemptQuestionId],
            ),
        ],
      ],
    );
  }
}

class _FinalResultQuestionCard extends StatelessWidget {
  const _FinalResultQuestionCard({
    required this.number,
    required this.question,
    required this.selectedKey,
  });

  final int number;
  final FinalExamQuestionMobile question;
  final String? selectedKey;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final languageCode = l10n.locale.languageCode;
    final correct = question.answerKey;
    final isCorrect =
        selectedKey != null && selectedKey!.toUpperCase() == correct;
    final color = isCorrect
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.error;

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
                  isCorrect ? Icons.check_circle : Icons.cancel_rounded,
                  color: color,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    '$number. ${question.localizedQuestion(languageCode)}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _FinalAnswerLine(
              label: l10n.text('Your answer'),
              value: _optionText(context, selectedKey),
              color: color,
            ),
            if (correct.isNotEmpty && !isCorrect) ...[
              const SizedBox(height: 8),
              _FinalAnswerLine(
                label: l10n.text('Correct answer'),
                value: _optionText(context, correct),
                color: const Color(0xFF17855B),
              ),
            ],
            if (question
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
              Text(question.localizedExplanation(languageCode)),
            ],
          ],
        ),
      ),
    );
  }

  String _optionText(BuildContext context, String? key) {
    if (key == null || key.isEmpty || !question.options.containsKey(key)) {
      return context.l10n.text('Not answered');
    }
    final languageCode = context.l10n.locale.languageCode;
    return '$key. ${question.localizedOption(key, languageCode)}';
  }
}

class _FinalAnswerLine extends StatelessWidget {
  const _FinalAnswerLine({
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
    ),
    child: RichText(
      text: TextSpan(
        style: DefaultTextStyle.of(context).style,
        children: [
          TextSpan(
            text: '$label: ',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          TextSpan(text: value),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: onRetry,
            child: Text(context.l10n.text('Retry')),
          ),
        ],
      ),
    ),
  );
}
