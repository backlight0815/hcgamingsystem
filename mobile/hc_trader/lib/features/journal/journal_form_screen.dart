import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/standalone_models.dart';
import '../../models/trading_account.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/trading_pair.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_calculator.dart';
import '../../services/trading_time_service.dart';
import '../../shared/formatters.dart';

class JournalFormScreen extends StatefulWidget {
  const JournalFormScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
    required this.pairs,
    required this.availableBalance,
    this.journal,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;
  final List<TradingPair> pairs;
  final double availableBalance;
  final TradingJournalEntry? journal;

  @override
  State<JournalFormScreen> createState() => _JournalFormScreenState();
}

class _JournalFormScreenState extends State<JournalFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _timeService = TradingTimeService();
  final _calculator = TradingCalculator();
  late final TextEditingController _entryController;
  late final TextEditingController _exitController;
  late final TextEditingController _lotController;
  late final TextEditingController _stopLossController;
  late final TextEditingController _takeProfitController;
  late final TextEditingController _commissionController;
  late final TextEditingController _swapController;
  late final TextEditingController _feesController;
  late final TextEditingController _riskAmountController;
  late final TextEditingController _riskPercentController;
  late final TextEditingController _setupController;
  late final TextEditingController _sessionController;
  late final TextEditingController _notesController;
  late final TextEditingController _emotionBeforeController;
  late final TextEditingController _emotionDuringController;
  late final TextEditingController _emotionAfterController;
  late final TextEditingController _mistakeTagsController;
  late final TextEditingController _psychologyNotesController;
  late final TextEditingController _duplicateController;
  late DateTime _openDate;
  late DateTime _closeDate;
  late String _timeMode;
  late int _mt5Offset;
  late int _direction;
  late int _result;
  late String _status;
  String? _tradingAccountId;
  late double _confidence;
  late double _discipline;
  var _tradingPlans = <_JournalTradingPlanOption>[];
  var _tradingStrategies = <TradingStrategyRecord>[];
  var _followedTradingPlan = false;
  var _followedRiskRules = false;
  var _avoidedFomo = false;
  var _followedSessionRules = false;
  var _avoidedRestrictedNews = false;
  var _stoppedAfterMaxLoss = false;
  var _followedStrategyRules = false;
  var _followedSlRule = false;
  var _followedTpRule = false;
  var _followedInvalidationRule = false;
  String? _selectedTradingPlanId;
  String? _selectedStrategyId;
  TradingPair? _selectedPair;
  var _saving = false;

  bool get _isEdit => widget.journal != null;

  @override
  void initState() {
    super.initState();
    final journal = widget.journal;
    _timeMode = _timeService.normalizeMode(journal?.timeInputTimezone);
    _mt5Offset = _timeService.normalizeOffset(
      journal?.timeInputOffsetMinutes,
      _timeMode,
    );
    _direction = journal?.direction ?? 1;
    _result = journal?.result ?? 1;
    _status = journal?.status ?? 'closed';
    _tradingAccountId = journal?.tradingAccountId;
    _confidence = (journal?.confidenceLevel ?? 5).toDouble();
    _discipline = (journal?.disciplineRating ?? 5).toDouble();
    _tradingPlans = _loadTradingPlans();
    _tradingStrategies = _loadCachedTradingStrategies();
    _followedTradingPlan = journal?.followedTradingPlan ?? false;
    _followedRiskRules = journal?.followedRiskRules ?? false;
    _avoidedFomo = journal?.avoidedFomo ?? false;
    _followedSessionRules = journal?.followedSessionRules ?? false;
    _avoidedRestrictedNews = journal?.avoidedRestrictedNews ?? false;
    _stoppedAfterMaxLoss = journal?.stoppedAfterMaxLoss ?? false;
    _followedStrategyRules = journal?.followedStrategyRules ?? false;
    _followedSlRule = journal?.followedSlRule ?? false;
    _followedTpRule = journal?.followedTpRule ?? false;
    _followedInvalidationRule = journal?.followedInvalidationRule ?? false;
    _selectedTradingPlanId = journal?.tradingPlanId;
    _selectedStrategyId = journal?.strategyId;
    _ensurePlanStrategySelection();
    unawaited(_loadRepositoryPlanStrategyOptions());
    _selectedPair = _initialPair();

    if (journal == null) {
      final now = DateTime.now();
      _openDate = DateTime(now.year, now.month, now.day, now.hour, now.minute);
      _closeDate = _openDate.add(const Duration(hours: 1));
    } else {
      _openDate = _timeService.fromMalaysia(
        value: journal.openDate,
        targetMode: _timeMode,
        targetOffsetMinutes: _mt5Offset,
      );
      _closeDate = _timeService.fromMalaysia(
        value: journal.closeDate,
        targetMode: _timeMode,
        targetOffsetMinutes: _mt5Offset,
      );
    }

    _entryController = TextEditingController(
      text: journal == null ? '' : _formatNumber(journal.entryPrice),
    );
    _exitController = TextEditingController(
      text: journal == null ? '' : _formatNumber(journal.exitPrice),
    );
    _lotController = TextEditingController(
      text: journal == null ? '0.10' : _formatNumber(journal.lotSize),
    );
    _stopLossController = TextEditingController(
      text: journal?.stopLoss == null ? '' : _formatNumber(journal!.stopLoss!),
    );
    _takeProfitController = TextEditingController(
      text: journal?.takeProfit == null
          ? ''
          : _formatNumber(journal!.takeProfit!),
    );
    _commissionController = TextEditingController(
      text: journal == null ? '0' : _formatNumber(journal.commission),
    );
    _swapController = TextEditingController(
      text: journal == null ? '0' : _formatNumber(journal.swap),
    );
    _feesController = TextEditingController(
      text: journal == null ? '0' : _formatNumber(journal.fees),
    );
    _riskAmountController = TextEditingController(
      text: journal?.initialRiskAmount == null
          ? ''
          : _formatNumber(journal!.initialRiskAmount!),
    );
    _riskPercentController = TextEditingController(
      text: journal?.initialRiskPercent == null
          ? ''
          : _formatNumber(journal!.initialRiskPercent!),
    );
    _setupController = TextEditingController(text: journal?.setupName ?? '');
    _sessionController = TextEditingController(text: journal?.session ?? '');
    _notesController = TextEditingController(text: journal?.notes ?? '');
    _emotionBeforeController = TextEditingController(
      text: journal?.emotionBeforeEntry ?? '',
    );
    _emotionDuringController = TextEditingController(
      text: journal?.emotionDuringTrade ?? '',
    );
    _emotionAfterController = TextEditingController(
      text: journal?.emotionAfterTrade ?? '',
    );
    _mistakeTagsController = TextEditingController(
      text: journal?.mistakeTags.join(', ') ?? '',
    );
    _psychologyNotesController = TextEditingController(
      text: journal?.psychologyNotes ?? '',
    );
    _duplicateController = TextEditingController(text: '1');
  }

  @override
  void dispose() {
    _entryController.dispose();
    _exitController.dispose();
    _lotController.dispose();
    _stopLossController.dispose();
    _takeProfitController.dispose();
    _commissionController.dispose();
    _swapController.dispose();
    _feesController.dispose();
    _riskAmountController.dispose();
    _riskPercentController.dispose();
    _setupController.dispose();
    _sessionController.dispose();
    _notesController.dispose();
    _emotionBeforeController.dispose();
    _emotionDuringController.dispose();
    _emotionAfterController.dispose();
    _mistakeTagsController.dispose();
    _psychologyNotesController.dispose();
    _duplicateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final calculated = _calculate();
    final netCalculated = calculated == null
        ? null
        : _netProfitLoss(calculated);
    final timeline = _timeService.toMalaysiaTimeline(
      openValue: _openDate,
      closeValue: _closeDate,
      sourceMode: _timeMode,
      sourceOffsetMinutes: _mt5Offset,
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text(_isEdit ? 'Edit Trade' : 'Add Trade')),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 110),
          children: [
            _Section(
              title: context.l10n.text('Timeline'),
              children: [
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'draft',
                      label: Text(context.l10n.text('Draft')),
                    ),
                    ButtonSegment(
                      value: 'open',
                      label: Text(context.l10n.text('Open')),
                    ),
                    ButtonSegment(
                      value: 'closed',
                      label: Text(context.l10n.text('Closed')),
                    ),
                  ],
                  selected: {_status},
                  onSelectionChanged: (selection) {
                    setState(() => _status = selection.first);
                  },
                ),
                const SizedBox(height: 14),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: TradingTimeService.malaysia,
                      icon: Icon(Icons.schedule),
                      label: Text('MYT'),
                    ),
                    ButtonSegment(
                      value: TradingTimeService.mt5,
                      icon: Icon(Icons.desktop_windows_outlined),
                      label: Text('MT5'),
                    ),
                  ],
                  selected: {_timeMode},
                  onSelectionChanged: (selection) =>
                      _changeTimeMode(selection.first),
                ),
                if (_timeMode == TradingTimeService.mt5) ...[
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int>(
                    initialValue: _mt5Offset,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('MT5 Server Offset'),
                      prefixIcon: const Icon(Icons.public),
                    ),
                    items: [
                      for (final option
                          in TradingTimeService.mt5Offsets.entries)
                        DropdownMenuItem(
                          value: option.key,
                          child: Text(option.value),
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        _changeMt5Offset(value);
                      }
                    },
                  ),
                ],
                const SizedBox(height: 14),
                _DateTimeField(
                  label: context.l10n.text('Open Time'),
                  value: _openDate,
                  onTap: () => _pickDateTime(open: true),
                ),
                if (_status == 'closed') ...[
                  const SizedBox(height: 14),
                  _DateTimeField(
                    label: context.l10n.text('Close Time'),
                    value: _closeDate,
                    onTap: () => _pickDateTime(open: false),
                  ),
                ],
                if (_status == 'closed' && timeline.closeRolledForward) ...[
                  const SizedBox(height: 10),
                  _InlineNotice(
                    icon: Icons.nightlight_round,
                    text: context.l10n.text('Close time saved as next day'),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 14),
            _Section(
              title: context.l10n.text('Setup'),
              children: [
                StreamBuilder<List<TradingAccount>>(
                  stream: widget.repository.tradingAccountsFor(widget.user.uid),
                  builder: (context, snapshot) {
                    final accounts = snapshot.data ?? const <TradingAccount>[];
                    if (accounts.isEmpty) return const SizedBox.shrink();
                    if (_tradingAccountId == null) {
                      for (final account in accounts) {
                        if (account.isPrimary) {
                          _tradingAccountId = account.id;
                          break;
                        }
                      }
                    }
                    _tradingAccountId ??= accounts.first.id;
                    if (!accounts.any(
                      (account) => account.id == _tradingAccountId,
                    )) {
                      _tradingAccountId = accounts.first.id;
                    }
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: DropdownButtonFormField<String>(
                        initialValue: _tradingAccountId,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Trading account'),
                          prefixIcon: const Icon(
                            Icons.account_balance_outlined,
                          ),
                        ),
                        items: [
                          for (final account in accounts)
                            DropdownMenuItem(
                              value: account.id,
                              child: Text(
                                '${account.displayName} · ${account.broker}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: (value) => _tradingAccountId = value,
                      ),
                    );
                  },
                ),
                DropdownButtonFormField<TradingPair>(
                  initialValue: _selectedPair,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Pair'),
                    prefixIcon: const Icon(Icons.currency_exchange),
                  ),
                  items: [
                    for (final pair in widget.pairs)
                      DropdownMenuItem(
                        value: pair,
                        child: Text(pair.displayName),
                      ),
                  ],
                  validator: (value) => value == null
                      ? context.l10n.text('Pair is required')
                      : null,
                  onChanged: (value) {
                    setState(() => _selectedPair = value);
                  },
                ),
                const SizedBox(height: 14),
                SegmentedButton<int>(
                  segments: [
                    ButtonSegment(
                      value: 1,
                      icon: const Icon(Icons.arrow_upward),
                      label: Text(context.l10n.text('Buy')),
                    ),
                    ButtonSegment(
                      value: 2,
                      icon: const Icon(Icons.arrow_downward),
                      label: Text(context.l10n.text('Sell')),
                    ),
                  ],
                  selected: {_direction},
                  onSelectionChanged: (selection) {
                    setState(() => _direction = selection.first);
                  },
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _NumberField(
                        controller: _entryController,
                        label: context.l10n.text('Entry Price'),
                        onChanged: () => setState(() {}),
                      ),
                    ),
                    if (_status == 'closed') ...[
                      const SizedBox(width: 12),
                      Expanded(
                        child: _NumberField(
                          controller: _exitController,
                          label: context.l10n.text('Exit Price'),
                          onChanged: () => setState(() {}),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 14),
                _NumberField(
                  controller: _lotController,
                  label: context.l10n.text('Lot Size'),
                  min: 0,
                  onChanged: () => setState(() {}),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _Section(
              title: context.l10n.text('Optional analytics details'),
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _NumberField(
                        controller: _stopLossController,
                        label: context.l10n.text('Stop loss'),
                        optional: true,
                        onChanged: () => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _NumberField(
                        controller: _takeProfitController,
                        label: context.l10n.text('Take profit'),
                        optional: true,
                        onChanged: () => setState(() {}),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _NumberField(
                        controller: _commissionController,
                        label: context.l10n.text('Commission'),
                        onChanged: () => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _NumberField(
                        controller: _swapController,
                        label: context.l10n.text('Swap'),
                        onChanged: () => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _NumberField(
                        controller: _feesController,
                        label: context.l10n.text('Fees'),
                        onChanged: () => setState(() {}),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                _InlineNotice(
                  icon: Icons.info_outline,
                  text: context.l10n.text(
                    'Enter costs as negative values. They are included in net trading P/L.',
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _NumberField(
                        controller: _riskAmountController,
                        label: context.l10n.text('Initial risk amount'),
                        min: 0,
                        optional: true,
                        onChanged: () => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _NumberField(
                        controller: _riskPercentController,
                        label: context.l10n.text('Initial risk %'),
                        min: 0,
                        optional: true,
                        onChanged: () => setState(() {}),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _setupController,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Setup name'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _sessionController,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Trading session'),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 14),
            _Section(
              title: context.l10n.text('Trading Plan'),
              children: [
                if (_tradingPlans.isEmpty)
                  _InlineNotice(
                    icon: Icons.info_outline,
                    text: context.l10n.text(
                      'No trading plan found. You can still save this journal.',
                    ),
                  )
                else ...[
                  if (_tradingPlans.length > 1) ...[
                    DropdownButtonFormField<String>(
                      initialValue: _selectedTradingPlanId,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Select Trading Plan'),
                        prefixIcon: const Icon(Icons.rule_folder_outlined),
                      ),
                      items: [
                        for (final plan in _tradingPlans)
                          DropdownMenuItem(
                            value: plan.id,
                            child: Text(
                              plan.displayName(context),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: (value) => setState(() {
                        _selectedTradingPlanId = value;
                        if (!_availableStrategiesForPlan().any(
                          (strategy) => strategy.id == _selectedStrategyId,
                        )) {
                          _selectedStrategyId = null;
                        }
                      }),
                    ),
                    const SizedBox(height: 8),
                  ] else
                    _InlineNotice(
                      icon: Icons.rule_folder_outlined,
                      text: _tradingPlans.first.displayName(context),
                    ),
                  const SizedBox(height: 8),
                  _ComplianceTile(
                    value: _followedTradingPlan,
                    label: context.l10n.text('Did I follow my trading plan?'),
                    onChanged: (value) =>
                        setState(() => _followedTradingPlan = value),
                  ),
                  _ComplianceTile(
                    value: _followedRiskRules,
                    label: context.l10n.text('Did I risk properly?'),
                    onChanged: (value) =>
                        setState(() => _followedRiskRules = value),
                  ),
                  _ComplianceTile(
                    value: _avoidedFomo,
                    label: context.l10n.text('Did I avoid FOMO?'),
                    onChanged: (value) => setState(() => _avoidedFomo = value),
                  ),
                  _ComplianceTile(
                    value: _followedSessionRules,
                    label: context.l10n.text(
                      'Did I follow session/time rules?',
                    ),
                    onChanged: (value) =>
                        setState(() => _followedSessionRules = value),
                  ),
                  _ComplianceTile(
                    value: _avoidedRestrictedNews,
                    label: context.l10n.text(
                      'Did I avoid trading during restricted news?',
                    ),
                    onChanged: (value) =>
                        setState(() => _avoidedRestrictedNews = value),
                  ),
                  _ComplianceTile(
                    value: _stoppedAfterMaxLoss,
                    label: context.l10n.text('Did I stop after max loss?'),
                    onChanged: (value) =>
                        setState(() => _stoppedAfterMaxLoss = value),
                  ),
                  const SizedBox(height: 12),
                  _SelectedPlanPreview(plan: _selectedTradingPlan()),
                ],
                const SizedBox(height: 14),
                if (_availableStrategiesForPlan().isEmpty)
                  _InlineNotice(
                    icon: Icons.auto_graph_outlined,
                    text: context.l10n.text(
                      'No strategy selected. You can add strategies from the toolkit.',
                    ),
                  )
                else ...[
                  DropdownButtonFormField<String>(
                    initialValue: _selectedStrategyId,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Select Trading Strategy'),
                      prefixIcon: const Icon(Icons.auto_graph_outlined),
                    ),
                    items: [
                      for (final strategy in _availableStrategiesForPlan())
                        DropdownMenuItem(
                          value: strategy.id,
                          child: Text(
                            strategy.strategy.name,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: (value) =>
                        setState(() => _selectedStrategyId = value),
                  ),
                  const SizedBox(height: 10),
                  _SelectedStrategyPreview(strategy: _selectedStrategy()),
                  CheckboxListTile(
                    value: _followedStrategyRules,
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    onChanged: (value) =>
                        setState(() => _followedStrategyRules = value ?? false),
                    title: Text(
                      context.l10n.text(
                        'Did I follow my selected strategy rules?',
                      ),
                    ),
                  ),
                  _ComplianceTile(
                    value: _followedSlRule,
                    label: context.l10n.text('Did I follow SL rule?'),
                    onChanged: (value) =>
                        setState(() => _followedSlRule = value),
                  ),
                  _ComplianceTile(
                    value: _followedTpRule,
                    label: context.l10n.text('Did I follow TP rule?'),
                    onChanged: (value) =>
                        setState(() => _followedTpRule = value),
                  ),
                  _ComplianceTile(
                    value: _followedInvalidationRule,
                    label: context.l10n.text('Did I follow invalidation rule?'),
                    onChanged: (value) =>
                        setState(() => _followedInvalidationRule = value),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 14),
            _Section(
              title: context.l10n.text('Outcome'),
              children: [
                if (_status == 'closed') ...[
                  SegmentedButton<int>(
                    segments: [
                      ButtonSegment(
                        value: 1,
                        label: Text(context.l10n.text('Win')),
                      ),
                      ButtonSegment(
                        value: 2,
                        label: Text(context.l10n.text('Loss')),
                      ),
                      ButtonSegment(
                        value: 3,
                        label: Text(context.l10n.text('BE')),
                      ),
                    ],
                    selected: {_result},
                    onSelectionChanged: (selection) {
                      _selectResult(selection.first);
                    },
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: _ReadOnlyFigure(
                          label: context.l10n.text('Pips'),
                          value: calculated == null
                              ? '-'
                              : calculated.pips.toStringAsFixed(
                                  _selectedPair?.pipDecimal ?? 1,
                                ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _ReadOnlyFigure(
                          label: context.l10n.text('Profit / Loss'),
                          value: netCalculated == null
                              ? '-'
                              : formatMoney(netCalculated),
                          color: netCalculated == null
                              ? null
                              : netCalculated >= 0
                              ? const Color(0xFF17855B)
                              : const Color(0xFFC23B36),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 14),
                TextFormField(
                  controller: _notesController,
                  minLines: 3,
                  maxLines: 5,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Notes'),
                    alignLabelWithHint: true,
                    prefixIcon: const Icon(Icons.notes_outlined),
                  ),
                ),
                if (!_isEdit && _status == 'closed') ...[
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _duplicateController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Number of Trades'),
                      prefixIcon: const Icon(Icons.copy_all_outlined),
                    ),
                    validator: (value) {
                      final count = int.tryParse(value ?? '');
                      if (count == null || count < 1 || count > 500) {
                        return context.l10n.text('Enter 1 to 500');
                      }

                      return null;
                    },
                  ),
                ],
              ],
            ),
            const SizedBox(height: 14),
            _Section(
              title: context.l10n.text('Psychology & Review'),
              children: [
                TextFormField(
                  controller: _emotionBeforeController,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Emotion before entry'),
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _emotionDuringController,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Emotion during trade'),
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _emotionAfterController,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Emotion after trade'),
                  ),
                ),
                const SizedBox(height: 12),
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
                TextFormField(
                  controller: _mistakeTagsController,
                  decoration: InputDecoration(
                    labelText: context.l10n.text(
                      'Mistake tags (comma separated)',
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _psychologyNotesController,
                  minLines: 2,
                  maxLines: 5,
                  decoration: InputDecoration(
                    labelText: context.l10n.text(
                      'Lesson learned / psychology notes',
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      bottomSheet: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            border: Border(
              top: BorderSide(
                color: Theme.of(context).colorScheme.outlineVariant,
              ),
            ),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  '${context.l10n.text('Balance')} ${formatMoney(widget.availableBalance)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge,
                ),
              ),
              const SizedBox(width: 12),
              FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(context.l10n.text(_isEdit ? 'Update' : 'Save')),
              ),
            ],
          ),
        ),
      ),
    );
  }

  TradingPair? _initialPair() {
    final journal = widget.journal;
    if (widget.pairs.isEmpty) {
      return null;
    }

    if (journal == null) {
      return widget.pairs.first;
    }

    for (final pair in widget.pairs) {
      if (pair.symbol.toUpperCase() == journal.pair.toUpperCase()) {
        return pair;
      }
    }

    return widget.pairs.first;
  }

  List<_JournalTradingPlanOption> _loadTradingPlans() {
    final rows = widget.storage
        .readList(widget.user.uid, 'trading_plans')
        .map(_JournalTradingPlanOption.fromJson)
        .where((plan) => plan.hasContent)
        .toList();
    if (rows.isNotEmpty) return rows;

    final legacy = widget.storage.readMap(widget.user.uid, 'trading_plan');
    if (legacy == null) return const [];
    final plan = TradingPlan.fromJson(legacy);
    final option = _JournalTradingPlanOption(
      id: 'legacy-trading-plan',
      plan: plan,
    );
    return option.hasContent ? [option] : const [];
  }

  List<TradingStrategyRecord> _loadCachedTradingStrategies() {
    final rows = widget.storage
        .readList(widget.user.uid, 'trading_strategies')
        .map(TradingStrategyRecord.fromJson)
        .where((record) => record.strategy.name.trim().isNotEmpty)
        .toList();
    rows.sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
    return rows;
  }

  Future<void> _loadRepositoryPlanStrategyOptions() async {
    late final List<TradingPlanRecord> plans;
    late final List<TradingStrategyRecord> strategies;
    try {
      plans = await widget.repository.loadTradingPlanRecords(widget.user.uid);
      strategies = await widget.repository.loadTradingStrategyRecords(
        widget.user.uid,
      );
    } catch (_) {
      return;
    }
    if (!mounted) return;
    setState(() {
      _tradingPlans = plans
          .map(_JournalTradingPlanOption.fromRecord)
          .where((plan) => plan.hasContent)
          .toList();
      _tradingStrategies = strategies
          .where((record) => record.strategy.name.trim().isNotEmpty)
          .toList();
      _ensurePlanStrategySelection();
    });
  }

  void _ensurePlanStrategySelection() {
    if (_tradingPlans.isNotEmpty &&
        !_tradingPlans.any((plan) => plan.id == _selectedTradingPlanId)) {
      _selectedTradingPlanId =
          _defaultTradingPlan()?.id ?? _tradingPlans.first.id;
    }
    if (_selectedStrategyId != null &&
        !_tradingStrategies.any(
          (strategy) => strategy.id == _selectedStrategyId,
        )) {
      _selectedStrategyId = null;
    }
  }

  _JournalTradingPlanOption? _selectedTradingPlan() {
    if (_tradingPlans.isEmpty) return null;
    for (final plan in _tradingPlans) {
      if (plan.id == _selectedTradingPlanId) return plan;
    }
    return _tradingPlans.first;
  }

  _JournalTradingPlanOption? _defaultTradingPlan() {
    for (final plan in _tradingPlans) {
      if (plan.plan.isDefault) return plan;
    }
    return null;
  }

  List<TradingStrategyRecord> _availableStrategiesForPlan() {
    final selectedPlan = _selectedTradingPlan();
    final activeStrategies = _tradingStrategies
        .where((strategy) => strategy.strategy.isActive)
        .toList();
    if (selectedPlan == null || selectedPlan.plan.allowedStrategyIds.isEmpty) {
      return activeStrategies;
    }
    return activeStrategies
        .where(
          (strategy) =>
              selectedPlan.plan.allowedStrategyIds.contains(strategy.id),
        )
        .toList();
  }

  TradingStrategyRecord? _selectedStrategy() {
    for (final strategy in _availableStrategiesForPlan()) {
      if (strategy.id == _selectedStrategyId) return strategy;
    }
    return null;
  }

  Map<String, bool> _planComplianceChecklist() => {
    'followed_trading_plan': _followedTradingPlan,
    'followed_risk_rules': _followedRiskRules,
    'avoided_fomo': _avoidedFomo,
    'followed_session_rules': _followedSessionRules,
    'avoided_restricted_news': _avoidedRestrictedNews,
    'stopped_after_max_loss': _stoppedAfterMaxLoss,
  };

  Map<String, bool> _strategyComplianceChecklist() => {
    'followed_strategy_rules': _followedStrategyRules,
    'followed_sl_rule': _followedSlRule,
    'followed_tp_rule': _followedTpRule,
    'followed_invalidation_rule': _followedInvalidationRule,
  };

  CalculatedTrade? _calculate() {
    final pair = _selectedPair;
    final entry = double.tryParse(_entryController.text);
    final exit = double.tryParse(_exitController.text);
    final lot = double.tryParse(_lotController.text);
    if (pair == null || entry == null || exit == null || lot == null) {
      return null;
    }

    return _calculator.calculate(
      pair: pair,
      entryPrice: entry,
      exitPrice: exit,
      lotSize: lot,
      result: _result,
    );
  }

  void _selectResult(int result) {
    final suggestedExit = _calculator.suggestedExitPrice(
      result: result,
      entryPrice: double.tryParse(_entryController.text),
      currentExitPrice: double.tryParse(_exitController.text),
      stopLoss: double.tryParse(_stopLossController.text),
      takeProfit: double.tryParse(_takeProfitController.text),
    );

    setState(() {
      _result = result;
      if (suggestedExit != null) {
        _exitController.text = _formatNumber(suggestedExit);
      }
    });
  }

  double _netProfitLoss(CalculatedTrade calculated) {
    return calculated.profitLoss +
        _numberOrZero(_commissionController) +
        _numberOrZero(_swapController) +
        _numberOrZero(_feesController);
  }

  double _numberOrZero(TextEditingController controller) =>
      double.tryParse(controller.text.trim()) ?? 0;

  double? _nullableNumber(TextEditingController controller) {
    final text = controller.text.trim();
    return text.isEmpty ? null : double.tryParse(text);
  }

  String? _nullableText(TextEditingController controller) {
    final text = controller.text.trim();
    return text.isEmpty ? null : text;
  }

  void _changeTimeMode(String mode) {
    final newMode = _timeService.normalizeMode(mode);
    final newOffset = _timeService.normalizeOffset(_mt5Offset, newMode);

    setState(() {
      _openDate = _timeService.convertInputBetween(
        value: _openDate,
        sourceMode: _timeMode,
        sourceOffsetMinutes: _mt5Offset,
        targetMode: newMode,
        targetOffsetMinutes: newOffset,
      );
      _closeDate = _timeService.convertInputBetween(
        value: _closeDate,
        sourceMode: _timeMode,
        sourceOffsetMinutes: _mt5Offset,
        targetMode: newMode,
        targetOffsetMinutes: newOffset,
      );
      _timeMode = newMode;
      _mt5Offset = newOffset;
    });
  }

  void _changeMt5Offset(int offset) {
    final newOffset = _timeService.normalizeOffset(offset, _timeMode);

    setState(() {
      _openDate = _timeService.convertInputBetween(
        value: _openDate,
        sourceMode: _timeMode,
        sourceOffsetMinutes: _mt5Offset,
        targetMode: _timeMode,
        targetOffsetMinutes: newOffset,
      );
      _closeDate = _timeService.convertInputBetween(
        value: _closeDate,
        sourceMode: _timeMode,
        sourceOffsetMinutes: _mt5Offset,
        targetMode: _timeMode,
        targetOffsetMinutes: newOffset,
      );
      _mt5Offset = newOffset;
    });
  }

  Future<void> _pickDateTime({required bool open}) async {
    final initial = open ? _openDate : _closeDate;
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );
    if (date == null || !mounted) {
      return;
    }

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) {
      return;
    }

    setState(() {
      final selected = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
      if (open) {
        _openDate = selected;
      } else {
        _closeDate = selected;
      }
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final pair = _selectedPair;
    final calculated = _status == 'closed'
        ? _calculate()
        : const CalculatedTrade(pips: 0, profitLoss: 0);
    final entry = double.tryParse(_entryController.text);
    final exit = double.tryParse(_exitController.text);
    final lot = double.tryParse(_lotController.text);
    if (pair == null ||
        calculated == null ||
        entry == null ||
        (_status == 'closed' && exit == null) ||
        lot == null) {
      _showMessage('Complete the trade values.');
      return;
    }
    final netProfitLoss = _netProfitLoss(calculated);

    final timeline = _timeService.toMalaysiaTimeline(
      openValue: _openDate,
      closeValue: _closeDate,
      sourceMode: _timeMode,
      sourceOffsetMinutes: _mt5Offset,
    );
    if (_status == 'closed' && !timeline.isValid) {
      _showMessage('Close time must be after open time.');
      return;
    }

    if (!_isEdit && widget.availableBalance <= 0) {
      _showMessage('Add capital before recording trades.');
      return;
    }

    final balanceAfter = widget.availableBalance + netProfitLoss;
    if (_status == 'closed' && balanceAfter < 0) {
      _showMessage('This trade would result in a negative balance.');
      return;
    }

    setState(() => _saving = true);
    try {
      final count = _isEdit || _status != 'closed'
          ? 1
          : int.parse(_duplicateController.text);
      var runningBalance = widget.availableBalance;
      var created = 0;
      final selectedPlan = _selectedTradingPlan();
      final selectedStrategy = _selectedStrategy();
      final planCheckedAt = selectedPlan == null ? null : DateTime.now();

      for (var index = 0; index < count; index++) {
        if (runningBalance + netProfitLoss < 0) {
          break;
        }

        await widget.repository.upsertJournal(
          TradingJournalEntry(
            id: _isEdit ? widget.journal!.id : '',
            userId: widget.user.uid,
            tradingAccountId: _tradingAccountId,
            type: 'trade',
            status: _status,
            timeInputTimezone: _timeMode,
            timeInputOffsetMinutes: _timeService.normalizeOffset(
              _mt5Offset,
              _timeMode,
            ),
            openDate: timeline.openDate!,
            closeDate: timeline.closeDate!,
            pair: pair.symbol,
            direction: _direction,
            entryPrice: entry,
            exitPrice: exit ?? 0,
            lotSize: lot,
            pips: calculated.pips,
            profitLoss: netProfitLoss,
            grossProfit: calculated.profitLoss,
            commission: _numberOrZero(_commissionController),
            swap: _numberOrZero(_swapController),
            fees: _numberOrZero(_feesController),
            stopLoss: _nullableNumber(_stopLossController),
            takeProfit: _nullableNumber(_takeProfitController),
            initialRiskAmount: _nullableNumber(_riskAmountController),
            initialRiskPercent: _nullableNumber(_riskPercentController),
            setupName: _nullableText(_setupController),
            session: _nullableText(_sessionController),
            result: _result,
            notes: _notesController.text.trim(),
            emotionBeforeEntry: _emotionBeforeController.text.trim(),
            emotionDuringTrade: _emotionDuringController.text.trim(),
            emotionAfterTrade: _emotionAfterController.text.trim(),
            confidenceLevel: _confidence.round(),
            disciplineRating: _discipline.round(),
            mistakeTags: _mistakeTagsController.text
                .split(',')
                .map((item) => item.trim())
                .where((item) => item.isNotEmpty)
                .toSet()
                .toList(),
            psychologyNotes: _psychologyNotesController.text.trim(),
            followedTradingPlan: selectedPlan != null && _followedTradingPlan,
            tradingPlanId: selectedPlan?.id,
            tradingPlanName: selectedPlan?.name,
            tradingPlanCheckedAt: planCheckedAt,
            strategyId: selectedStrategy?.id,
            strategyName: selectedStrategy?.strategy.name,
            planComplianceChecklist: selectedPlan == null
                ? const {}
                : _planComplianceChecklist(),
            strategyComplianceChecklist: selectedStrategy == null
                ? const {}
                : _strategyComplianceChecklist(),
            followedRiskRules: selectedPlan != null && _followedRiskRules,
            avoidedFomo: selectedPlan != null && _avoidedFomo,
            followedSessionRules: selectedPlan != null && _followedSessionRules,
            avoidedRestrictedNews:
                selectedPlan != null && _avoidedRestrictedNews,
            stoppedAfterMaxLoss: selectedPlan != null && _stoppedAfterMaxLoss,
            followedStrategyRules:
                selectedStrategy != null && _followedStrategyRules,
            followedSlRule: selectedStrategy != null && _followedSlRule,
            followedTpRule: selectedStrategy != null && _followedTpRule,
            followedInvalidationRule:
                selectedStrategy != null && _followedInvalidationRule,
          ),
        );
        runningBalance += netProfitLoss;
        created++;
      }

      if (!mounted) {
        return;
      }

      if (created == 0) {
        _showMessage('No trades were saved.');
        return;
      }

      Navigator.of(context).pop();
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.text(message))));
  }

  String _formatNumber(double value) {
    return value.toStringAsFixed(5).replaceFirst(RegExp(r'\.?0+$'), '');
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _ComplianceTile extends StatelessWidget {
  const _ComplianceTile({
    required this.value,
    required this.label,
    required this.onChanged,
  });

  final bool value;
  final String label;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => CheckboxListTile(
    value: value,
    dense: true,
    contentPadding: EdgeInsets.zero,
    controlAffinity: ListTileControlAffinity.leading,
    title: Text(label),
    onChanged: (value) => onChanged(value ?? false),
  );
}

class _SelectedPlanPreview extends StatelessWidget {
  const _SelectedPlanPreview({required this.plan});

  final _JournalTradingPlanOption? plan;

  @override
  Widget build(BuildContext context) {
    final selected = plan;
    if (selected == null) return const SizedBox.shrink();
    final rules = [
      if (selected.plan.newsFilterRule.trim().isNotEmpty)
        '${context.l10n.text('News filter rule')}: ${selected.plan.newsFilterRule}',
      if (selected.plan.psychologyRules.trim().isNotEmpty)
        '${context.l10n.text('Psychology rules')}: ${selected.plan.psychologyRules}',
      if (selected.plan.fomoPreventionRules.trim().isNotEmpty)
        '${context.l10n.text('FOMO prevention rules')}: ${selected.plan.fomoPreventionRules}',
      if (selected.plan.dailyRoutine.trim().isNotEmpty)
        '${context.l10n.text('Daily routine before trading')}: ${selected.plan.dailyRoutine}',
    ];
    if (rules.isEmpty) return const SizedBox.shrink();
    return _RulePreview(
      title: context.l10n.text('Plan rules to review'),
      rules: rules,
    );
  }
}

class _SelectedStrategyPreview extends StatelessWidget {
  const _SelectedStrategyPreview({required this.strategy});

  final TradingStrategyRecord? strategy;

  @override
  Widget build(BuildContext context) {
    final selected = strategy?.strategy;
    if (selected == null) return const SizedBox.shrink();
    final rules = [
      if (selected.entryModelRules.trim().isNotEmpty)
        '${context.l10n.text('Entry model / rules')}: ${selected.entryModelRules}',
      if (selected.entryConfirmationChecklist.isNotEmpty)
        '${context.l10n.text('Entry confirmation checklist')}: ${selected.entryConfirmationChecklist.join(', ')}',
      if (selected.stopLossRule.trim().isNotEmpty)
        '${context.l10n.text('Stop loss rule')}: ${selected.stopLossRule}',
      if (selected.takeProfitRule.trim().isNotEmpty)
        '${context.l10n.text('Take profit rule')}: ${selected.takeProfitRule}',
      if (selected.invalidationRule.trim().isNotEmpty)
        '${context.l10n.text('Invalidation rule')}: ${selected.invalidationRule}',
    ];
    if (rules.isEmpty) return const SizedBox.shrink();
    return _RulePreview(
      title: context.l10n.text('Strategy rules to review'),
      rules: rules,
    );
  }
}

class _RulePreview extends StatelessWidget {
  const _RulePreview({required this.title, required this.rules});

  final String title;
  final List<String> rules;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 6),
        for (final rule in rules)
          Padding(padding: const EdgeInsets.only(bottom: 4), child: Text(rule)),
      ],
    ),
  );
}

class _JournalTradingPlanOption {
  const _JournalTradingPlanOption({required this.id, required this.plan});

  final String id;
  final TradingPlan plan;

  factory _JournalTradingPlanOption.fromJson(Map<String, dynamic> json) {
    return _JournalTradingPlanOption(
      id: (json['id'] ?? '').toString(),
      plan: TradingPlan.fromJson(
        Map<String, dynamic>.from(json['plan'] as Map? ?? json),
      ),
    );
  }

  factory _JournalTradingPlanOption.fromRecord(TradingPlanRecord record) {
    return _JournalTradingPlanOption(id: record.id, plan: record.plan);
  }

  String get name {
    final name = plan.planName.trim();
    return name.isEmpty ? 'Untitled plan' : name;
  }

  bool get hasContent =>
      plan.planName.trim().isNotEmpty ||
      plan.description.trim().isNotEmpty ||
      plan.market.trim().isNotEmpty ||
      plan.entryCriteria.trim().isNotEmpty ||
      plan.exitRules.trim().isNotEmpty ||
      plan.allowedPairs.isNotEmpty ||
      plan.allowedStrategyIds.isNotEmpty;

  String displayName(BuildContext context) => context.l10n.text(name);
}

class _DateTimeField extends StatelessWidget {
  const _DateTimeField({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: onTap,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: const Icon(Icons.event_outlined),
        ),
        child: Row(
          children: [
            Expanded(child: Text(formatDateTime(value))),
            Text(
              formatTime(value),
              style: Theme.of(context).textTheme.labelLarge,
            ),
          ],
        ),
      ),
    );
  }
}

class _NumberField extends StatelessWidget {
  const _NumberField({
    required this.controller,
    required this.label,
    required this.onChanged,
    this.min,
    this.optional = false,
  });

  final TextEditingController controller;
  final String label;
  final VoidCallback onChanged;
  final double? min;
  final bool optional;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(
        decimal: true,
        signed: true,
      ),
      decoration: InputDecoration(labelText: label),
      onChanged: (_) => onChanged(),
      validator: (value) {
        final text = value?.trim() ?? '';
        if (optional && text.isEmpty) return null;
        final number = double.tryParse(text);
        if (number == null) {
          return context.l10n.text('Required');
        }

        if (min != null && number < min!) {
          return context.l10n.text('Minimum {value}', {
            'value': min!.toStringAsFixed(0),
          });
        }

        return null;
      },
    );
  }
}

class _ReadOnlyFigure extends StatelessWidget {
  const _ReadOnlyFigure({required this.label, required this.value, this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return InputDecorator(
      decoration: InputDecoration(labelText: label),
      child: Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
          color: color,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.tertiary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    );
  }
}
