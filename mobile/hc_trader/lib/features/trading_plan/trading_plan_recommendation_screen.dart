import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/standalone_models.dart';
import '../../models/trading_plan_recommendation.dart';
import '../../services/trading_plan_recommendation_service.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';

class TradingPlanRecommendationScreen extends StatefulWidget {
  const TradingPlanRecommendationScreen({
    super.key,
    required this.userId,
    required this.storage,
    required this.onSave,
  });

  final String userId;
  final LocalAppStorage storage;
  final Future<void> Function(TradingPlanRecord record) onSave;

  @override
  State<TradingPlanRecommendationScreen> createState() =>
      _TradingPlanRecommendationScreenState();
}

class _TradingPlanRecommendationScreenState
    extends State<TradingPlanRecommendationScreen> {
  static const _draftKey = 'trading_plan_recommendation_draft';
  static const _sections = TradingPlanRecommendationService.sections;

  final _service = const TradingPlanRecommendationService();
  var _step = 0;
  var _response = const TradingPlanQuestionnaireResponse();
  TradingPlanRecommendationResult? _result;

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
    _response = TradingPlanQuestionnaireResponse.fromJson(
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

  void _selectAnswer(TradingPlanQuestion question, String answerKey) {
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
          'Answer all required questions before generating the plan.',
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
      _response = const TradingPlanQuestionnaireResponse();
      _result = null;
    });
  }

  Future<void> _saveGeneratedPlan(TradingPlan plan) async {
    final now = DateTime.now();
    final record = TradingPlanRecord(
      id: 'plan-${now.microsecondsSinceEpoch}',
      userId: widget.userId,
      plan: plan,
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
        title: Text(context.l10n.text('Trading Plan Questionnaire')),
      ),
      body: Column(
        children: [
          _QuestionnaireProgress(
            step: _step,
            total: _sections.length + 2,
            label: _stepLabel(context),
          ),
          Expanded(
            child: _isPreview
                ? _RecommendationPreviewForm(
                    result: result ?? _service.recommend(_response),
                    response: _response,
                    onSave: _saveGeneratedPlan,
                    onStartOver: _startOver,
                  )
                : _isReview
                ? _ReviewAnswersView(
                    sections: _sections,
                    response: _response,
                    onEditSection: _editSection,
                  )
                : _QuestionSectionView(
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
                            _isReview ? 'Generate Plan' : 'Next',
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
      return context.l10n.text('Recommended Trading Plan Preview');
    }
    return context.l10n.text(_sections[_step].label);
  }
}

class _QuestionnaireProgress extends StatelessWidget {
  const _QuestionnaireProgress({
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

class _QuestionSectionView extends StatelessWidget {
  const _QuestionSectionView({
    required this.section,
    required this.response,
    required this.onSelected,
  });

  final TradingPlanQuestionSection section;
  final TradingPlanQuestionnaireResponse response;
  final void Function(TradingPlanQuestion question, String answerKey)
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

class _ReviewAnswersView extends StatelessWidget {
  const _ReviewAnswersView({
    required this.sections,
    required this.response,
    required this.onEditSection,
  });

  final List<TradingPlanQuestionSection> sections;
  final TradingPlanQuestionnaireResponse response;
  final ValueChanged<int> onEditSection;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      children: [
        Text(
          context.l10n.text(
            'Review your answers before generating the recommended plan.',
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
                      child: _AnswerSummaryRow(
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

  String _answerLabel(BuildContext context, TradingPlanQuestion question) {
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

class _AnswerSummaryRow extends StatelessWidget {
  const _AnswerSummaryRow({required this.label, required this.value});

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

class _RecommendationPreviewForm extends StatefulWidget {
  const _RecommendationPreviewForm({
    required this.result,
    required this.response,
    required this.onSave,
    required this.onStartOver,
  });

  final TradingPlanRecommendationResult result;
  final TradingPlanQuestionnaireResponse response;
  final Future<void> Function(TradingPlan plan) onSave;
  final Future<void> Function() onStartOver;

  @override
  State<_RecommendationPreviewForm> createState() =>
      _RecommendationPreviewFormState();
}

class _RecommendationPreviewFormState
    extends State<_RecommendationPreviewForm> {
  final _formKey = GlobalKey<FormState>();
  var _editing = false;
  var _saving = false;
  var _initialized = false;

  late final TextEditingController _planName;
  late final TextEditingController _profile;
  late final TextEditingController _style;
  late final TextEditingController _market;
  late final TextEditingController _timeframes;
  late final TextEditingController _technical;
  late final TextEditingController _entry;
  late final TextEditingController _exit;
  late final TextEditingController _stopLoss;
  late final TextEditingController _takeProfit;
  late final TextEditingController _riskRules;
  late final TextEditingController _riskPercent;
  late final TextEditingController _dailyLoss;
  late final TextEditingController _weeklyLoss;
  late final TextEditingController _maxTrades;
  late final TextEditingController _minimumRr;
  late final TextEditingController _psychology;
  late final TextEditingController _warnings;
  late final TextEditingController _noTrade;
  late final TextEditingController _preTrade;
  late final TextEditingController _postTrade;
  late final TextEditingController _discipline;
  late final TextEditingController _improvement;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    final result = widget.result;
    _planName = TextEditingController(text: _t(result.planName));
    _profile = TextEditingController(text: _block(result.traderProfileSummary));
    _style = TextEditingController(text: _t(result.selectedTradingStrategy));
    _market = TextEditingController(text: _t(result.accountType));
    _timeframes = TextEditingController(text: _block(result.tradingObjective));
    _technical = TextEditingController(text: _block(result.tradingSessionRule));
    _entry = TextEditingController(text: _block(result.tradingRoutine));
    _exit = TextEditingController(text: _block(result.entryPermissionRules));
    _stopLoss = TextEditingController(text: _block(result.lossRecoveryRules));
    _takeProfit = TextEditingController(text: _block(result.weeklyReviewRules));
    _riskRules = TextEditingController(
      text: _block(result.riskManagementRules),
    );
    _riskPercent = TextEditingController(text: _numberText(result.riskPercent));
    _dailyLoss = TextEditingController(
      text: _numberText(result.maxDailyLossPercent),
    );
    _weeklyLoss = TextEditingController(
      text: _numberText(result.maxWeeklyLossPercent),
    );
    _maxTrades = TextEditingController(text: '${result.maxTradesPerDay}');
    _minimumRr = TextEditingController(
      text: _numberText(result.minimumRiskRewardRatio),
    );
    _psychology = TextEditingController(text: _block(result.psychologyRules));
    _warnings = TextEditingController(text: _block(result.personalityWarnings));
    _noTrade = TextEditingController(text: _block(result.noTradeConditions));
    _preTrade = TextEditingController(text: _block(result.preTradeChecklist));
    _postTrade = TextEditingController(
      text: _block(result.postTradeReviewRules),
    );
    _discipline = TextEditingController(text: _block(result.disciplineRules));
    _improvement = TextEditingController(text: _block(result.improvementNotes));
    _initialized = true;
  }

  @override
  void dispose() {
    if (_initialized) {
      for (final controller in [
        _planName,
        _profile,
        _style,
        _market,
        _timeframes,
        _technical,
        _entry,
        _exit,
        _stopLoss,
        _takeProfit,
        _riskRules,
        _riskPercent,
        _dailyLoss,
        _weeklyLoss,
        _maxTrades,
        _minimumRr,
        _psychology,
        _warnings,
        _noTrade,
        _preTrade,
        _postTrade,
        _discipline,
        _improvement,
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
                    context.l10n.text('Recommended Trading Plan'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    context.l10n.text(
                      'Review and edit the recommendation before saving it as your own plan.',
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _field(_planName, 'Plan Name', required: true, maxLines: 1),
          _field(_profile, 'Trader Profile Summary'),
          _field(
            _style,
            'Selected / Recommended Trading Strategy',
            maxLines: 1,
          ),
          _field(_market, 'Account Type', maxLines: 1),
          _field(_timeframes, 'Trading Objective'),
          _field(_riskRules, 'Risk Management Rules', required: true),
          Row(
            children: [
              Expanded(
                child: _field(
                  _riskPercent,
                  'Risk per trade %',
                  maxLines: 1,
                  numeric: true,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _field(
                  _minimumRr,
                  'Minimum Risk Reward Ratio',
                  maxLines: 1,
                  numeric: true,
                ),
              ),
            ],
          ),
          Row(
            children: [
              Expanded(
                child: _field(
                  _dailyLoss,
                  'Max Daily Loss',
                  maxLines: 1,
                  numeric: true,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _field(
                  _weeklyLoss,
                  'Max Weekly Loss',
                  maxLines: 1,
                  numeric: true,
                ),
              ),
            ],
          ),
          _field(_maxTrades, 'Max Trades Per Day', maxLines: 1, integer: true),
          _field(_technical, 'Trading Session Rule'),
          _field(_entry, 'Trading Routine'),
          _field(_preTrade, 'Pre-Trade Checklist'),
          _field(_exit, 'Entry Permission Rules', required: true),
          _field(_noTrade, 'No-Trade Conditions'),
          _field(_psychology, 'Psychology Rules', required: true),
          _field(_warnings, 'Personality-Based Warnings'),
          _field(_stopLoss, 'Loss Recovery Rules'),
          _field(_takeProfit, 'Weekly Review Rules'),
          _field(_postTrade, 'Post-Trade Review Rules'),
          _field(_discipline, 'Discipline Rules'),
          _field(_improvement, 'Recommended Improvement Notes'),
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
            label: Text(context.l10n.text('Save Recommended Plan')),
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
        .answerLabelMap(TradingPlanRecommendationService.sections)
        .map((key, value) => MapEntry(key, value));
    final plan = TradingPlan(
      strategy: _generatedText(_planName, result.planName),
      description: _generatedText(
        _profile,
        result.traderProfileSummary,
        fallback: 'No profile summary provided.',
      ),
      isActive: true,
      planType: 'recommended',
      source: 'plan_questionnaire',
      questionnaireVersion: result.questionnaireVersion,
      questionnaireAnswers: answerLabels,
      generatedProfileSummary: _generatedText(
        _profile,
        result.traderProfileSummary,
        fallback: 'No profile summary provided.',
      ),
      templateKey: result.templateKey,
      capitalBasis: result.propFirmRules.trim().isEmpty
          ? 'custom_capital'
          : 'prop_firm_max_loss',
      tradingStyle: 'Personal trading rulebook',
      strategyType: _generatedText(_style, result.selectedTradingStrategy),
      allowedStrategyNames: result.allowedStrategyNames,
      technicalRules: _generatedText(_exit, result.entryPermissionRules),
      market: '',
      timeframe: _generatedText(_technical, result.tradingSessionRule),
      entryCriteria: _generatedText(_exit, result.entryPermissionRules),
      exitRules: _generatedText(_stopLoss, result.lossRecoveryRules),
      stopLossRules: '',
      takeProfitRules: '',
      checklistBeforeEntry: _generatedText(
        _preTrade,
        result.preTradeChecklist,
        fallback: 'Risk checked and setup matches the plan.',
      ),
      preTradeChecklist: _generatedText(
        _preTrade,
        result.preTradeChecklist,
        fallback: 'Risk checked and setup matches the plan.',
      ),
      checklistAfterTrade: _generatedText(
        _postTrade,
        result.postTradeReviewRules,
        fallback: 'Record result, emotion, and rule compliance.',
      ),
      postTradeReviewRoutine: _generatedText(
        _postTrade,
        result.postTradeReviewRules,
        fallback: 'Record result, emotion, and rule compliance.',
      ),
      postTradeReviewRules: _generatedText(
        _postTrade,
        result.postTradeReviewRules,
        fallback: 'Record result, emotion, and rule compliance.',
      ),
      riskPercent: double.parse(_riskPercent.text.trim()),
      dailyMaxLossPercent: double.parse(_dailyLoss.text.trim()),
      weeklyMaxLossPercent: double.parse(_weeklyLoss.text.trim()),
      maxTradesPerDay: int.parse(_maxTrades.text.trim()),
      maxLossesPerDay: result.maxLossesPerDay,
      maxConsecutiveLosses: result.maxConsecutiveLosses,
      maxOpenTrades: result.maxOpenTrades,
      minimumRiskReward: double.parse(_minimumRr.text.trim()),
      cooldownAfterLossMinutes: result.cooldownAfterLossMinutes,
      allowedSessions: result.allowedSessions,
      allowedPairs: result.allowedPairs,
      newsFilterRule: result.newsFilterRule.trim(),
      accountRules: _lines([
        'Account Type: ${_generatedText(_market, result.accountType)}',
        result.accountRules,
      ]),
      propFirmRules: result.propFirmRules.trim(),
      psychologyRules: _generatedText(_psychology, result.psychologyRules),
      personalityWarnings: _generatedText(
        _warnings,
        result.personalityWarnings,
        fallback: 'Review your personality risk before placing the trade.',
      ),
      noTradeConditions: _generatedText(_noTrade, result.noTradeConditions),
      riskManagementRules: _generatedText(
        _riskRules,
        result.riskManagementRules,
      ),
      disciplineRules: _generatedText(_discipline, result.disciplineRules),
      improvementNotes: _generatedText(_improvement, result.improvementNotes),
      fomoPreventionRules: result.fomoPreventionRules.trim(),
      revengeTradingPreventionRules: result.revengeTradingPreventionRules
          .trim(),
      afterWinRules: result.afterWinRules.trim(),
      afterLossRules: result.afterLossRules.trim(),
      afterBreakingRules: result.afterBreakingRules.trim(),
      dailyRoutine: _generatedText(_entry, result.tradingRoutine),
      weeklyReviewRoutine: _generatedText(
        _takeProfit,
        result.weeklyReviewRules,
      ),
      reviewNotes: _generatedText(_improvement, result.improvementNotes),
    );
    try {
      await widget.onSave(plan);
      if (mounted) {
        AppFeedback.success(context, 'Recommended trading plan saved.');
      }
    } catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.error(context, error, operation: 'save recommended plan');
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

  String _numberText(double value) {
    if (value == value.roundToDouble()) return value.toStringAsFixed(0);
    return value.toStringAsFixed(2);
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

  bool _matchesGenerated(String text, String generated) {
    return text == generated ||
        text == context.l10n.text(generated) ||
        text == _block(generated);
  }

  String _lines(Iterable<String> lines) {
    return lines
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .join('\n');
  }
}
