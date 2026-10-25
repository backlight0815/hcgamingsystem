import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/standalone_models.dart';
import '../../models/trading_strategy_recommendation.dart';
import '../../services/trading_strategy_recommendation_service.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';

class TradingStrategyRecommendationScreen extends StatefulWidget {
  const TradingStrategyRecommendationScreen({
    super.key,
    required this.userId,
    required this.storage,
    required this.onSave,
  });

  final String userId;
  final LocalAppStorage storage;
  final Future<void> Function(TradingStrategyRecord record) onSave;

  @override
  State<TradingStrategyRecommendationScreen> createState() =>
      _TradingStrategyRecommendationScreenState();
}

class _TradingStrategyRecommendationScreenState
    extends State<TradingStrategyRecommendationScreen> {
  static const _draftKey = 'trading_strategy_recommendation_draft';
  static const _sections = TradingStrategyRecommendationService.sections;

  final _service = const TradingStrategyRecommendationService();
  var _step = 0;
  var _response = const TradingStrategyQuestionnaireResponse();
  TradingStrategyRecommendationResult? _result;

  int get _reviewIndex => _sections.length;
  int get _previewIndex => _sections.length + 1;
  bool get _isReview => _step == _reviewIndex;
  bool get _isPreview => _step == _previewIndex;

  @override
  void initState() {
    super.initState();
    _restoreDraft();
  }

  void _restoreDraft() {
    final draft = widget.storage.readMap(widget.userId, _draftKey);
    if (draft == null) return;
    final step = draft['step'];
    final response = draft['response'];
    _step = (step is num ? step.toInt() : 0).clamp(0, _previewIndex).toInt();
    _response = TradingStrategyQuestionnaireResponse.fromJson(
      response is Map ? Map<String, dynamic>.from(response) : null,
    );
    if (_isPreview && _response.isComplete(_sections)) {
      _result = _service.recommend(_response);
    } else if (_isPreview) {
      _step = 0;
    }
  }

  Future<void> _saveDraft() {
    return widget.storage.writeMap(widget.userId, _draftKey, {
      'step': _step,
      'response': _response.toJson(),
      'updated_at': DateTime.now().toIso8601String(),
    });
  }

  void _selectAnswer(TradingStrategyQuestion question, String answerKey) {
    setState(() {
      _response = question.allowsMultiple
          ? _response.toggleAnswer(
              questionId: question.id,
              answerKey: answerKey,
            )
          : _response.putAnswer(questionId: question.id, answerKey: answerKey);
    });
    unawaited(_saveDraft());
  }

  bool _validateCurrentSection() {
    if (_isReview || _isPreview) return true;
    final section = _sections[_step];
    final missing = [
      for (final question in section.questions)
        if (question.required &&
            (question.allowsMultiple
                ? question.optionsFor(_response.answerFor(question.id)).isEmpty
                : question.optionFor(_response.answerFor(question.id)) == null))
          question,
    ];
    if (missing.isEmpty) return true;
    AppFeedback.error(
      context,
      'Answer all required questions before continuing.',
    );
    return false;
  }

  void _next() {
    if (_isPreview) return;
    if (!_validateCurrentSection()) return;
    if (_isReview) {
      final missing = _response.missingQuestions(_sections);
      if (missing.isNotEmpty) {
        AppFeedback.error(
          context,
          'Answer all required questions before generating the strategy.',
        );
        setState(() => _step = _sectionIndexForQuestion(missing.first.id));
        unawaited(_saveDraft());
        return;
      }
      setState(() {
        _result = _service.recommend(_response);
        _step = _previewIndex;
      });
      unawaited(_saveDraft());
      return;
    }
    setState(() => _step += 1);
    unawaited(_saveDraft());
  }

  void _back() {
    if (_step == 0) {
      Navigator.pop(context);
      return;
    }
    setState(() => _step -= 1);
    unawaited(_saveDraft());
  }

  int _sectionIndexForQuestion(String questionId) {
    for (var index = 0; index < _sections.length; index++) {
      if (_sections[index].questions.any(
        (question) => question.id == questionId,
      )) {
        return index;
      }
    }
    return 0;
  }

  void _editSection(int index) {
    setState(() => _step = index);
    unawaited(_saveDraft());
  }

  Future<void> _startOver() async {
    await widget.storage.remove(widget.userId, _draftKey);
    if (!mounted) return;
    setState(() {
      _step = 0;
      _response = const TradingStrategyQuestionnaireResponse();
      _result = null;
    });
  }

  Future<void> _saveGeneratedStrategy(TradingStrategy strategy) async {
    final now = DateTime.now();
    final record = TradingStrategyRecord(
      id: 'strategy-${now.microsecondsSinceEpoch}',
      userId: widget.userId,
      strategy: strategy,
      createdAt: now,
      updatedAt: now,
    );
    await widget.onSave(record);
    await widget.storage.remove(widget.userId, _draftKey);
    if (!mounted) return;
    Navigator.pop(context, record);
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Trading Strategy Questionnaire')),
      ),
      body: Column(
        children: [
          _StrategyQuestionnaireProgress(
            step: _step,
            total: _sections.length + 2,
            label: _stepLabel(context),
          ),
          Expanded(
            child: _isPreview
                ? _StrategyRecommendationPreviewForm(
                    result: result ?? _service.recommend(_response),
                    response: _response,
                    onSave: _saveGeneratedStrategy,
                    onStartOver: _startOver,
                  )
                : _isReview
                ? _StrategyReviewAnswersView(
                    sections: _sections,
                    response: _response,
                    onEditSection: _editSection,
                  )
                : _StrategyQuestionSectionView(
                    section: _sections[_step],
                    response: _response,
                    onSelected: _selectAnswer,
                  ),
          ),
        ],
      ),
      bottomNavigationBar: _isPreview
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _back,
                        icon: const Icon(Icons.arrow_back),
                        label: Text(context.l10n.text('Back')),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _next,
                        icon: Icon(
                          _isReview
                              ? Icons.auto_awesome_outlined
                              : Icons.arrow_forward,
                        ),
                        label: Text(
                          context.l10n.text(
                            _isReview ? 'Generate Strategy' : 'Next',
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  String _stepLabel(BuildContext context) {
    if (_isReview) return context.l10n.text('Review Answers');
    if (_isPreview) {
      return context.l10n.text('Recommended Trading Strategy Preview');
    }
    return context.l10n.text(_sections[_step].label);
  }
}

class _StrategyQuestionnaireProgress extends StatelessWidget {
  const _StrategyQuestionnaireProgress({
    required this.step,
    required this.total,
    required this.label,
  });

  final int step;
  final int total;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                context.l10n.text('Step {current} of {total}', {
                  'current': step + 1,
                  'total': total,
                }),
              ),
            ],
          ),
          const SizedBox(height: 8),
          LinearProgressIndicator(value: (step + 1) / total),
        ],
      ),
    );
  }
}

class _StrategyQuestionSectionView extends StatelessWidget {
  const _StrategyQuestionSectionView({
    required this.section,
    required this.response,
    required this.onSelected,
  });

  final TradingStrategyQuestionSection section;
  final TradingStrategyQuestionnaireResponse response;
  final void Function(TradingStrategyQuestion question, String answerKey)
  onSelected;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      children: [
        for (final question in section.questions) ...[
          Card(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 14, 12, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Text(
                      context.l10n.text(question.label),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final option in question.options)
                        ChoiceChip(
                          label: Text(context.l10n.text(option.label)),
                          selected: question.allowsMultiple
                              ? response
                                    .answersFor(question.id)
                                    .contains(option.key)
                              : response.answerFor(question.id) == option.key,
                          onSelected: (_) => onSelected(question, option.key),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _StrategyReviewAnswersView extends StatelessWidget {
  const _StrategyReviewAnswersView({
    required this.sections,
    required this.response,
    required this.onEditSection,
  });

  final List<TradingStrategyQuestionSection> sections;
  final TradingStrategyQuestionnaireResponse response;
  final ValueChanged<int> onEditSection;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      children: [
        Text(
          context.l10n.text(
            'Review your answers before generating the recommended strategy.',
          ),
        ),
        const SizedBox(height: 12),
        for (var index = 0; index < sections.length; index++) ...[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          context.l10n.text(sections[index].label),
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () => onEditSection(index),
                        icon: const Icon(Icons.edit_outlined),
                        label: Text(context.l10n.text('Edit')),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  for (final question in sections[index].questions)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _StrategyAnswerSummaryRow(
                        label: context.l10n.text(question.label),
                        value: _answerLabel(context, question),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  String _answerLabel(BuildContext context, TradingStrategyQuestion question) {
    if (question.allowsMultiple) {
      final options = question.optionsFor(response.answerFor(question.id));
      if (options.isEmpty) return context.l10n.text('Not answered');
      return options
          .map((option) => context.l10n.text(option.label))
          .join(', ');
    }
    return context.l10n.text(
      question.optionFor(response.answerFor(question.id))?.label ??
          'Not answered',
    );
  }
}

class _StrategyAnswerSummaryRow extends StatelessWidget {
  const _StrategyAnswerSummaryRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(value)),
      ],
    );
  }
}

class _StrategyRecommendationPreviewForm extends StatefulWidget {
  const _StrategyRecommendationPreviewForm({
    required this.result,
    required this.response,
    required this.onSave,
    required this.onStartOver,
  });

  final TradingStrategyRecommendationResult result;
  final TradingStrategyQuestionnaireResponse response;
  final Future<void> Function(TradingStrategy strategy) onSave;
  final Future<void> Function() onStartOver;

  @override
  State<_StrategyRecommendationPreviewForm> createState() =>
      _StrategyRecommendationPreviewFormState();
}

class _StrategyRecommendationPreviewFormState
    extends State<_StrategyRecommendationPreviewForm> {
  final _formKey = GlobalKey<FormState>();
  var _editing = false;
  var _saving = false;
  var _initialized = false;

  late final TextEditingController _strategyName;
  late final TextEditingController _strategyCategory;
  late final TextEditingController _technicalConcept;
  late final TextEditingController _marketCondition;
  late final TextEditingController _style;
  late final TextEditingController _directionTimeframe;
  late final TextEditingController _entryTimeframe;
  late final TextEditingController _multiTimeframeRule;
  late final TextEditingController _entryModel;
  late final TextEditingController _confirmationRules;
  late final TextEditingController _invalidSetup;
  late final TextEditingController _stopLoss;
  late final TextEditingController _takeProfit;
  late final TextEditingController _management;
  late final TextEditingController _checklist;
  late final TextEditingController _suitableTraderType;
  late final TextEditingController _warning;
  late final TextEditingController _practiceNotes;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    final result = widget.result;
    _strategyName = TextEditingController(text: _t(result.strategyName));
    _strategyCategory = TextEditingController(
      text: _t(result.strategyCategory),
    );
    _technicalConcept = TextEditingController(
      text: _t(result.technicalConcept),
    );
    _marketCondition = TextEditingController(
      text: _t(result.suitableMarketCondition),
    );
    _style = TextEditingController(text: _t(result.recommendedTradingStyle));
    _directionTimeframe = TextEditingController(
      text: result.recommendedDirectionTimeframe,
    );
    _entryTimeframe = TextEditingController(
      text: result.recommendedEntryTimeframe,
    );
    _multiTimeframeRule = TextEditingController(
      text: _block(result.multiTimeframeConfirmationRule),
    );
    _entryModel = TextEditingController(text: _t(result.entryModel));
    _confirmationRules = TextEditingController(
      text: _block(result.entryConfirmationRules),
    );
    _invalidSetup = TextEditingController(
      text: _block(result.invalidSetupConditions),
    );
    _stopLoss = TextEditingController(
      text: _block(result.stopLossPlacementRule),
    );
    _takeProfit = TextEditingController(text: _block(result.takeProfitRule));
    _management = TextEditingController(
      text: _block(result.tradeManagementRule),
    );
    _checklist = TextEditingController(
      text: _block(result.exampleSetupChecklist),
    );
    _suitableTraderType = TextEditingController(
      text: _block(result.suitableTraderType),
    );
    _warning = TextEditingController(text: _block(result.strategyWarning));
    _practiceNotes = TextEditingController(
      text: _block(result.recommendedPracticeNotes),
    );
    _initialized = true;
  }

  @override
  void dispose() {
    if (_initialized) {
      for (final controller in [
        _strategyName,
        _strategyCategory,
        _technicalConcept,
        _marketCondition,
        _style,
        _directionTimeframe,
        _entryTimeframe,
        _multiTimeframeRule,
        _entryModel,
        _confirmationRules,
        _invalidSetup,
        _stopLoss,
        _takeProfit,
        _management,
        _checklist,
        _suitableTraderType,
        _warning,
        _practiceNotes,
      ]) {
        controller.dispose();
      }
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_initialized) return const SizedBox.shrink();
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Recommended Trading Strategy'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    context.l10n.text(
                      'Review and edit the recommendation before saving it as your own strategy.',
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _field(_strategyName, 'Strategy Name', required: true, maxLines: 1),
          _field(_strategyCategory, 'Strategy Category', maxLines: 1),
          _field(_technicalConcept, 'Technical Concept'),
          _field(_marketCondition, 'Suitable Market Condition'),
          Row(
            children: [
              Expanded(child: _field(_style, 'Recommended Trading Style')),
              const SizedBox(width: 10),
              Expanded(
                child: _field(
                  _directionTimeframe,
                  'Recommended Direction Timeframe',
                ),
              ),
            ],
          ),
          _field(_entryTimeframe, 'Recommended Entry Timeframe', maxLines: 1),
          _field(_multiTimeframeRule, 'Multi-Timeframe Confirmation Rule'),
          _field(_entryModel, 'Entry Model', required: true),
          _field(
            _confirmationRules,
            'Entry Confirmation Rules',
            required: true,
          ),
          _field(_invalidSetup, 'Invalid Setup Conditions'),
          _field(_stopLoss, 'Stop Loss Placement Rule', required: true),
          _field(_takeProfit, 'Take Profit Rule', required: true),
          _field(_management, 'Trade Management Rule'),
          _field(_checklist, 'Example Setup Checklist'),
          _field(_suitableTraderType, 'Suitable Trader Type'),
          _field(_warning, 'Strategy Weakness / Warning'),
          _field(_practiceNotes, 'Recommended Practice Notes'),
          const SizedBox(height: 6),
          OutlinedButton.icon(
            onPressed: _saving
                ? null
                : () => setState(() => _editing = !_editing),
            icon: Icon(
              _editing ? Icons.visibility_outlined : Icons.edit_outlined,
            ),
            label: Text(
              context.l10n.text(
                _editing ? 'Finish Editing' : 'Edit Before Save',
              ),
            ),
          ),
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(context.l10n.text('Save Recommended Strategy')),
          ),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: _saving ? null : widget.onStartOver,
            icon: const Icon(Icons.restart_alt),
            label: Text(context.l10n.text('Start Over')),
          ),
        ],
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    bool required = false,
    bool numeric = false,
    bool integer = false,
    int maxLines = 5,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        readOnly: !_editing,
        minLines: maxLines == 1 ? 1 : 2,
        maxLines: maxLines,
        keyboardType: numeric || integer
            ? const TextInputType.numberWithOptions(decimal: true)
            : TextInputType.multiline,
        decoration: InputDecoration(
          labelText: context.l10n.text(label),
          alignLabelWithHint: maxLines != 1,
        ),
        validator: (value) {
          final text = (value ?? '').trim();
          if (required && text.isEmpty) return context.l10n.text('Required');
          if (numeric && (double.tryParse(text) ?? 0) <= 0) {
            return context.l10n.text('Enter a valid number.');
          }
          if (integer && (int.tryParse(text) ?? 0) <= 0) {
            return context.l10n.text('Enter a valid number.');
          }
          return null;
        },
      ),
    );
  }

  Future<void> _save() async {
    if (_saving) return;
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    final result = widget.result;
    final answerLabels = widget.response
        .answerLabelMap(TradingStrategyRecommendationService.sections)
        .map((key, value) => MapEntry(key, value));
    final strategy = TradingStrategy(
      name: _generatedText(_strategyName, result.strategyName),
      description: _generatedText(
        _practiceNotes,
        result.recommendedPracticeNotes,
        fallback:
            'Practice this strategy on at least 20 screenshots before changing rules.',
      ),
      source: 'strategy_questionnaire',
      strategyType: _generatedText(_strategyCategory, result.strategyCategory),
      questionnaireVersion: result.questionnaireVersion,
      questionnaireAnswers: answerLabels,
      suitableTraderPersonality: _generatedText(
        _suitableTraderType,
        result.suitableTraderType,
        fallback:
            'Suitable for traders who need a simple repeatable entry model.',
      ),
      recommendedMarket: _generatedText(
        _marketCondition,
        result.suitableMarketCondition,
      ),
      technicalConcept: _generatedText(
        _technicalConcept,
        result.technicalConcept,
      ),
      riskManagementRules: result.riskReducedStrategyRules.trim(),
      psychologyRules: _generatedText(
        _suitableTraderType,
        result.suitableTraderType,
      ),
      noTradeConditions: _generatedText(
        _invalidSetup,
        result.invalidSetupConditions,
      ),
      preTradeChecklist: _generatedText(
        _checklist,
        result.exampleSetupChecklist,
      ),
      postTradeReviewRules: _generatedText(
        _practiceNotes,
        result.recommendedPracticeNotes,
      ),
      tradingStyle: _generatedText(_style, result.recommendedTradingStyle),
      marketCondition: _generatedText(
        _marketCondition,
        result.suitableMarketCondition,
      ),
      analysisMethods: result.analysisMethods,
      selectedStrategyTypes: result.selectedStrategyTypes,
      selectedMarketConditions: result.selectedMarketConditions,
      majorTimeframe: _directionTimeframe.text.trim().toUpperCase(),
      internalTimeframe: _generatedText(
        _multiTimeframeRule,
        result.multiTimeframeConfirmationRule,
      ),
      entryTimeframe: _entryTimeframe.text.trim(),
      entryModelRules: _lines([
        result.recommendedStrategySet,
        result.marketConditionRules,
        result.riskReducedStrategyRules,
        _generatedText(_entryModel, result.entryModel),
        _generatedText(
          _confirmationRules,
          result.entryConfirmationRules,
          fallback: 'Use one clear confirmation that matches the strategy.',
        ),
        _generatedText(
          _invalidSetup,
          result.invalidSetupConditions,
          fallback:
              'Invalid when direction, setup location, and confirmation do not align.',
        ),
      ]),
      entryConfirmationChecklist: _generatedLineList(
        _checklist,
        result.exampleSetupChecklist,
      ),
      stopLossRule: _generatedText(
        _stopLoss,
        result.stopLossPlacementRule,
        fallback: 'Place SL beyond technical invalidation.',
      ),
      takeProfitRule: _generatedText(
        _takeProfit,
        result.takeProfitRule,
        fallback: 'Take profit at planned technical target.',
      ),
      invalidationRule: _generatedText(
        _invalidSetup,
        result.invalidSetupConditions,
      ),
      tradeManagementRule: _generatedText(
        _management,
        result.tradeManagementRule,
      ),
      exampleSetupNotes: _generatedText(
        _practiceNotes,
        result.recommendedPracticeNotes,
        fallback:
            'Practice this strategy on at least 20 screenshots before changing rules.',
      ),
      personalityWarnings: _generatedText(
        _warning,
        result.strategyWarning,
        fallback: 'Do not force entries when technical conditions are weak.',
      ),
      improvementNotes: _generatedText(
        _practiceNotes,
        result.recommendedPracticeNotes,
        fallback:
            'Practice this strategy on at least 20 screenshots before changing rules.',
      ),
      isActive: true,
    );
    try {
      await widget.onSave(strategy);
      if (mounted) {
        AppFeedback.success(context, 'Recommended trading strategy saved.');
      }
    } catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.error(
          context,
          error,
          operation: 'save recommended strategy',
        );
      }
    }
  }

  String _t(String value) => context.l10n.text(value);

  String _block(String value) {
    return value
        .split('\n')
        .map((line) => context.l10n.text(line))
        .join('\n')
        .trim();
  }

  String _generatedText(
    TextEditingController controller,
    String generated, {
    String? fallback,
  }) {
    final text = controller.text.trim();
    final fallbackText = (fallback ?? generated).trim();
    if (text.isEmpty) return fallbackText;
    final generatedText = generated.trim();
    if (generatedText.isNotEmpty && _matchesGenerated(text, generatedText)) {
      return generatedText;
    }
    if (fallbackText.isNotEmpty && _matchesGenerated(text, fallbackText)) {
      return fallbackText;
    }
    return text;
  }

  List<String> _generatedLineList(
    TextEditingController controller,
    String generated,
  ) {
    final text = controller.text.trim();
    if (text.isEmpty) return const [];
    final generatedText = generated.trim();
    if (generatedText.isNotEmpty && _matchesGenerated(text, generatedText)) {
      return _lineList(generatedText);
    }
    return _lineList(text);
  }

  bool _matchesGenerated(String text, String generated) {
    return text == generated ||
        text == _t(generated) ||
        text == _block(generated);
  }

  List<String> _lineList(String value) => value
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .toList();

  String _lines(Iterable<String> lines) {
    return lines.where((line) => line.trim().isNotEmpty).join('\n');
  }
}
