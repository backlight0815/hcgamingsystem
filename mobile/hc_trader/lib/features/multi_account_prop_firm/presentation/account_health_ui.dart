import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/localization/app_localizations.dart';
import '../domain/prop_firm_models.dart';
import 'prop_firm_planner_controller.dart';

class CurrentSituationAssessmentPage extends StatefulWidget {
  const CurrentSituationAssessmentPage({
    super.key,
    required this.controller,
    required this.portfolio,
    this.onCompleted,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final VoidCallback? onCompleted;

  @override
  State<CurrentSituationAssessmentPage> createState() =>
      _CurrentSituationAssessmentPageState();
}

class _CurrentSituationAssessmentPageState
    extends State<CurrentSituationAssessmentPage> {
  late final Map<String, PropFirmAccount> _drafts = {
    for (final account in widget.portfolio.activeAccounts) account.id: account,
  };
  final _completed = <String>{};
  var _saving = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.text('Current Account Situation Assessment')),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Card(
              color: Theme.of(context).colorScheme.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.text('Start from where every account is today'),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      l10n.text(
                        'Enter existing profit, loss, drawdown, trading-day, reset, and payout progress before using a recommendation. No broker connection is used.',
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            _RuleDisclaimer(),
            const SizedBox(height: 16),
            for (final account in widget.portfolio.activeAccounts) ...[
              _AssessmentAccountCard(
                account: _drafts[account.id]!,
                completed: _completed.contains(account.id),
                onQuick: () => _edit(account.id, detailed: false),
                onDetailed: () => _edit(account.id, detailed: true),
              ),
              const SizedBox(height: 10),
            ],
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _saving ? null : _complete,
              icon: _saving
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.analytics_outlined),
              label: Text(l10n.text('Save assessment and analyse portfolio')),
            ),
            const SizedBox(height: 10),
            Text(
              l10n.text(
                '{complete} of {total} accounts updated; unreviewed accounts keep their existing planner values',
                {
                  'complete': _completed.length,
                  'total': widget.portfolio.activeAccounts.length,
                },
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _edit(String accountId, {required bool detailed}) async {
    final updated = await showAccountSituationEditor(
      context,
      _drafts[accountId]!,
      detailed: detailed,
    );
    if (updated == null || !mounted) return;
    setState(() {
      _drafts[accountId] = updated;
      _completed.add(accountId);
    });
  }

  Future<void> _complete() async {
    setState(() => _saving = true);
    try {
      await widget.controller.completeCurrentSituationAssessment(
        _drafts,
        reviewedAccountIds: _completed,
      );
      widget.onCompleted?.call();
    } on Object catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _AssessmentAccountCard extends StatelessWidget {
  const _AssessmentAccountCard({
    required this.account,
    required this.completed,
    required this.onQuick,
    required this.onDetailed,
  });

  final PropFirmAccount account;
  final bool completed;
  final VoidCallback onQuick;
  final VoidCallback onDetailed;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Wrap(
        runSpacing: 12,
        spacing: 12,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          CircleAvatar(
            backgroundColor: Color(account.colorValue).withValues(alpha: .12),
            foregroundColor: Color(account.colorValue),
            child: Icon(completed ? Icons.check : Icons.account_balance),
          ),
          SizedBox(
            width: 220,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  account.name,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(account.propFirmName),
                Text(
                  completed
                      ? context.l10n.text('Reviewed')
                      : context.l10n.text('Assessment required'),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
            ),
          ),
          OutlinedButton.icon(
            onPressed: onQuick,
            icon: const Icon(Icons.bolt_outlined),
            label: Text(context.l10n.text('Quick setup')),
          ),
          FilledButton.tonalIcon(
            onPressed: onDetailed,
            icon: const Icon(Icons.tune_outlined),
            label: Text(context.l10n.text('Detailed setup')),
          ),
        ],
      ),
    ),
  );
}

Future<PropFirmAccount?> showAccountSituationEditor(
  BuildContext context,
  PropFirmAccount account, {
  bool detailed = true,
}) {
  final editor = _AccountSituationEditorDialog(
    account: account,
    initiallyDetailed: detailed,
  );
  if (MediaQuery.sizeOf(context).width < 600) {
    return showModalBottomSheet<PropFirmAccount>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => editor,
    );
  }
  return showDialog<PropFirmAccount>(
    context: context,
    barrierDismissible: false,
    builder: (_) => editor,
  );
}

class _AccountSituationEditorDialog extends StatefulWidget {
  const _AccountSituationEditorDialog({
    required this.account,
    required this.initiallyDetailed,
  });

  final PropFirmAccount account;
  final bool initiallyDetailed;

  @override
  State<_AccountSituationEditorDialog> createState() =>
      _AccountSituationEditorDialogState();
}

class _AccountSituationEditorDialogState
    extends State<_AccountSituationEditorDialog> {
  late var _detailed = widget.initiallyDetailed;
  late DrawdownType _drawdown = widget.account.drawdownType;
  late DailyLossCalculationMethod _dailyMethod =
      widget.account.situation.dailyLossCalculationMethod;
  late DailyResetMethod _resetMethod =
      widget.account.situation.dailyResetMethod;
  late ManualAccountHealthStatus _manualStatus =
      widget.account.situation.manualStatus;
  late bool _emotional = widget.account.situation.emotionallyAffected;
  late bool _automaticEligibility =
      widget.account.situation.eligibilityAutomaticallyCalculated;
  late bool _requestConfirmed =
      widget.account.situation.payoutCanCurrentlyBeRequested ?? false;
  late bool _consistencySatisfied =
      widget.account.situation.consistencyRuleSatisfied ?? false;
  late bool _protectPayout = widget.account.situation.protectUntilPayout;
  late bool _lastTradeFollowedPlan =
      widget.account.situation.lastTradeFollowedPlan ?? true;
  late PayoutRequestStatus _payoutStatus =
      widget.account.situation.payoutRequestStatus;

  late final Map<String, TextEditingController> _fields = {
    'program': TextEditingController(
      text: widget.account.situation.programType,
    ),
    'creationDate': _dateController(
      widget.account.situation.accountCreationDate,
    ),
    'activationDate': _dateController(widget.account.situation.activationDate),
    'accountSize': _controller(widget.account.accountSize),
    'startingBalance': _controller(widget.account.startingBalance),
    'balance': _controller(widget.account.currentBalance),
    'equity': _controller(widget.account.manuallyEnteredEquity),
    'today': _controller(widget.account.situation.todayRealisedProfitLoss),
    'unrealised': _controller(
      widget.account.situation.todayUnrealisedProfitLoss,
    ),
    'recordedLoss': _controller(
      widget.account.situation.lossAlreadyRecordedToday,
    ),
    'openRisk': _controller(widget.account.situation.manuallyEnteredOpenRisk),
    'maxPercent': _controller(widget.account.situation.maximumLossPercentage),
    'maxAmount': _controller(widget.account.situation.maximumLossAmount),
    'dailyPercent': _controller(widget.account.situation.dailyLossPercentage),
    'dailyAmount': _controller(widget.account.situation.dailyLossAmount),
    'dailyBalance': _controller(widget.account.situation.dailyStartingBalance),
    'dailyEquity': _controller(widget.account.situation.dailyStartingEquity),
    'trailing': _controller(widget.account.situation.trailingReferenceBalance),
    'lowestToday': _controller(
      widget.account.situation.lowestBalanceOrEquityToday,
    ),
    'latestTradingDay': _dateController(
      widget.account.situation.latestTradingDayCompleted,
    ),
    'resetHour': _controller(widget.account.situation.resetHour),
    'resetMinute': _controller(widget.account.situation.resetMinute),
    'resetZone': TextEditingController(
      text: widget.account.situation.resetTimeZone,
    ),
    'resetOffset': _controller(widget.account.situation.resetUtcOffsetMinutes),
    'tradingDays': _controller(widget.account.completedTradingDays),
    'tradesToday': _controller(widget.account.tradesToday),
    'eligibleProfit': _controller(
      widget.account.situation.currentPayoutEligibleProfit,
    ),
    'minimumPayout': _controller(widget.account.minimumPayout),
    'desiredPayout': _controller(widget.account.desiredPayout),
    'payoutFrequency': TextEditingController(
      text: widget.account.payoutFrequency,
    ),
    'earliestDate': _dateController(
      widget.account.situation.earliestPayoutDate,
    ),
    'requestDate': _dateController(
      widget.account.situation.nextPayoutRequestDate ??
          widget.account.nextPayoutDate,
    ),
    'processingDate': _dateController(
      widget.account.situation.expectedPayoutProcessingDate,
    ),
    'plannedDate': _dateController(widget.account.situation.plannedPayoutDate),
    'payoutZone': TextEditingController(
      text: widget.account.situation.payoutTimeZone,
    ),
    'safetyBuffer': _controller(widget.account.situation.payoutSafetyBuffer),
    'notes': TextEditingController(text: widget.account.situation.payoutNotes),
    'healthy': _controller(widget.account.situation.healthyUpperPercent),
    'caution': _controller(widget.account.situation.cautionUpperPercent),
    'highRisk': _controller(widget.account.situation.highRiskUpperPercent),
    'lock': _controller(widget.account.situation.dailySafetyThresholdPercent),
  };

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 900, maxHeight: 820),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 12, 10),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l10n.text('Current situation: {account}', {
                            'account': widget.account.name,
                          }),
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          l10n.text(
                            'Manual snapshot only. Verify all firm rules independently.',
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            ),
            SegmentedButton<bool>(
              segments: [
                ButtonSegment(value: false, label: Text(l10n.text('Quick'))),
                ButtonSegment(value: true, label: Text(l10n.text('Detailed'))),
              ],
              selected: {_detailed},
              onSelectionChanged: (value) =>
                  setState(() => _detailed = value.first),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                children: [
                  _formSection('Current balance and today', [
                    _number('Current balance', 'balance'),
                    _number('Current equity (optional)', 'equity'),
                    _number('Realised P/L today', 'today'),
                    _number('Unrealised P/L today', 'unrealised'),
                    _number('Loss already recorded today', 'recordedLoss'),
                    _number('Planned open risk', 'openRisk'),
                    _number('Trades completed today', 'tradesToday'),
                  ]),
                  _formSection('Manual condition', [
                    DropdownButtonFormField<ManualAccountHealthStatus>(
                      isExpanded: true,
                      initialValue: _manualStatus,
                      decoration: InputDecoration(
                        labelText: l10n.text('Your account status'),
                      ),
                      items: [
                        for (final status in ManualAccountHealthStatus.values)
                          DropdownMenuItem(
                            value: status,
                            child: Text(
                              l10n.text(manualHealthStatusLabel(status)),
                            ),
                          ),
                      ],
                      onChanged: (value) =>
                          setState(() => _manualStatus = value!),
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _emotional,
                      title: Text(l10n.text('Emotionally affected today')),
                      subtitle: Text(
                        l10n.text(
                          'This can stop the full portfolio; switching accounts does not reset emotional risk.',
                        ),
                      ),
                      onChanged: (value) => setState(() => _emotional = value),
                    ),
                  ]),
                  _formSection('Payout progress', [
                    _number('Completed trading days', 'tradingDays'),
                    _number('Current payout-eligible profit', 'eligibleProfit'),
                    _text(
                      'Next payout request date (YYYY-MM-DD)',
                      'requestDate',
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _protectPayout,
                      title: Text(l10n.text('Protect until payout')),
                      onChanged: (value) =>
                          setState(() => _protectPayout = value),
                    ),
                  ]),
                  if (_detailed) ...[
                    _formSection('Account details', [
                      _text('Program type', 'program'),
                      _text(
                        'Account creation date (YYYY-MM-DD)',
                        'creationDate',
                      ),
                      _text('Activation date (YYYY-MM-DD)', 'activationDate'),
                      _number('Account size', 'accountSize'),
                      _number('Starting balance', 'startingBalance'),
                    ]),
                    _formSection('Loss-limit rules', [
                      DropdownButtonFormField<DrawdownType>(
                        isExpanded: true,
                        initialValue: _drawdown,
                        decoration: InputDecoration(
                          labelText: l10n.text('Maximum drawdown method'),
                        ),
                        items: [
                          for (final type in DrawdownType.values)
                            DropdownMenuItem(
                              value: type,
                              child: Text(l10n.text(drawdownTypeLabel(type))),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _drawdown = value!),
                      ),
                      DropdownButtonFormField<DailyLossCalculationMethod>(
                        isExpanded: true,
                        initialValue: _dailyMethod,
                        decoration: InputDecoration(
                          labelText: l10n.text('Daily loss method'),
                        ),
                        items: [
                          for (final method
                              in DailyLossCalculationMethod.values)
                            DropdownMenuItem(
                              value: method,
                              child: Text(
                                l10n.text(dailyLossMethodLabel(method)),
                              ),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _dailyMethod = value!),
                      ),
                      _number('Maximum loss percentage', 'maxPercent'),
                      _number('Maximum loss amount', 'maxAmount'),
                      _number('Daily loss percentage', 'dailyPercent'),
                      _number('Daily loss amount', 'dailyAmount'),
                      _number('Daily starting balance', 'dailyBalance'),
                      _number('Daily starting equity', 'dailyEquity'),
                      _number('Trailing reference balance', 'trailing'),
                      _number('Lowest balance or equity today', 'lowestToday'),
                      _text(
                        'Latest trading day completed (YYYY-MM-DD)',
                        'latestTradingDay',
                      ),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _lastTradeFollowedPlan,
                        title: Text(l10n.text('Last trade followed the plan')),
                        onChanged: (value) => setState(
                          () => _lastTradeFollowedPlan = value ?? true,
                        ),
                      ),
                    ]),
                    _formSection('Daily reset', [
                      DropdownButtonFormField<DailyResetMethod>(
                        isExpanded: true,
                        initialValue: _resetMethod,
                        decoration: InputDecoration(
                          labelText: l10n.text('Reset method'),
                        ),
                        items: [
                          for (final method in DailyResetMethod.values)
                            DropdownMenuItem(
                              value: method,
                              child: Text(
                                l10n.text(dailyResetMethodLabel(method)),
                              ),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _resetMethod = value!),
                      ),
                      _number('Reset hour (0-23)', 'resetHour'),
                      _number('Reset minute', 'resetMinute'),
                      _text('Reset time zone', 'resetZone'),
                      _number('UTC offset in minutes', 'resetOffset'),
                    ]),
                    _formSection('Payout dates and protection', [
                      _number('Minimum payout amount', 'minimumPayout'),
                      _number('Desired payout amount', 'desiredPayout'),
                      _text('Payout frequency', 'payoutFrequency'),
                      _text(
                        'Earliest payout date (YYYY-MM-DD)',
                        'earliestDate',
                      ),
                      _text(
                        'Expected processing date (YYYY-MM-DD)',
                        'processingDate',
                      ),
                      _text('Planned payout date (YYYY-MM-DD)', 'plannedDate'),
                      _text('Payout time zone', 'payoutZone'),
                      _number('Payout safety buffer', 'safetyBuffer'),
                      _text('Payout notes', 'notes', lines: 2),
                      DropdownButtonFormField<PayoutRequestStatus>(
                        isExpanded: true,
                        initialValue: _payoutStatus,
                        decoration: InputDecoration(
                          labelText: l10n.text('Payout request status'),
                        ),
                        items: [
                          for (final status in PayoutRequestStatus.values)
                            DropdownMenuItem(
                              value: status,
                              child: Text(
                                l10n.text(payoutRequestStatusLabel(status)),
                              ),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _payoutStatus = value!),
                      ),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _automaticEligibility,
                        title: Text(
                          l10n.text('Calculate eligibility automatically'),
                        ),
                        onChanged: (value) =>
                            setState(() => _automaticEligibility = value),
                      ),
                      if (!_automaticEligibility)
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _requestConfirmed,
                          title: Text(
                            l10n.text('Payout can currently be requested'),
                          ),
                          onChanged: (value) => setState(
                            () => _requestConfirmed = value ?? false,
                          ),
                        ),
                      if (widget.account.consistencyRuleLimit > 0)
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _consistencySatisfied,
                          title: Text(
                            l10n.text('Consistency rule is satisfied'),
                          ),
                          onChanged: (value) => setState(
                            () => _consistencySatisfied = value ?? false,
                          ),
                        ),
                    ]),
                    _formSection('Health thresholds', [
                      _number('Healthy below percent', 'healthy'),
                      _number('Caution below percent', 'caution'),
                      _number('High risk below percent', 'highRisk'),
                      _number('Daily lock threshold percent', 'lock'),
                    ]),
                  ],
                  const _RuleDisclaimer(),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Wrap(
                alignment: WrapAlignment.end,
                spacing: 8,
                runSpacing: 8,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text(l10n.text('Cancel')),
                  ),
                  FilledButton.icon(
                    onPressed: _save,
                    icon: const Icon(Icons.save_outlined),
                    label: Text(l10n.text('Save current situation')),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _formSection(String title, List<Widget> children) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          context.l10n.text(title),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) => Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              for (final child in children)
                SizedBox(
                  width: constraints.maxWidth >= 620
                      ? (constraints.maxWidth - 12) / 2
                      : constraints.maxWidth,
                  child: child,
                ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _number(String label, String key) => TextField(
    controller: _fields[key],
    keyboardType: const TextInputType.numberWithOptions(
      decimal: true,
      signed: true,
    ),
    decoration: InputDecoration(labelText: context.l10n.text(label)),
  );

  Widget _text(String label, String key, {int lines = 1}) => TextField(
    controller: _fields[key],
    minLines: lines,
    maxLines: lines,
    decoration: InputDecoration(labelText: context.l10n.text(label)),
  );

  void _save() {
    try {
      final situation = widget.account.situation.copyWith(
        programType: _fields['program']!.text.trim(),
        accountCreationDate: _optionalDate('creationDate'),
        clearAccountCreationDate: _fields['creationDate']!.text.trim().isEmpty,
        activationDate: _optionalDate('activationDate'),
        clearActivationDate: _fields['activationDate']!.text.trim().isEmpty,
        maximumLossPercentage: _requiredDouble('maxPercent'),
        maximumLossAmount: _optionalDouble('maxAmount') ?? 0,
        dailyLossPercentage: _requiredDouble('dailyPercent'),
        dailyLossAmount: _optionalDouble('dailyAmount') ?? 0,
        dailyLossCalculationMethod: _dailyMethod,
        dailyStartingBalance: _optionalDouble('dailyBalance'),
        clearDailyStartingBalance: _fields['dailyBalance']!.text.trim().isEmpty,
        dailyStartingEquity: _optionalDouble('dailyEquity'),
        clearDailyStartingEquity: _fields['dailyEquity']!.text.trim().isEmpty,
        trailingReferenceBalance: _optionalDouble('trailing'),
        clearTrailingReferenceBalance: _fields['trailing']!.text.trim().isEmpty,
        lowestBalanceOrEquityToday: _optionalDouble('lowestToday'),
        clearLowestToday: _fields['lowestToday']!.text.trim().isEmpty,
        resetHour: _optionalInt('resetHour'),
        clearResetHour: _fields['resetHour']!.text.trim().isEmpty,
        resetMinute: _optionalInt('resetMinute') ?? 0,
        resetTimeZone: _fields['resetZone']!.text.trim(),
        resetUtcOffsetMinutes: _optionalInt('resetOffset'),
        clearResetUtcOffset: _fields['resetOffset']!.text.trim().isEmpty,
        dailyResetMethod: _resetMethod,
        todayRealisedProfitLoss: _requiredDouble('today'),
        todayUnrealisedProfitLoss: _optionalDouble('unrealised'),
        clearUnrealisedProfitLoss: _fields['unrealised']!.text.trim().isEmpty,
        lossAlreadyRecordedToday: _requiredDouble('recordedLoss'),
        manuallyEnteredOpenRisk: _requiredDouble('openRisk'),
        latestTradingDayCompleted: _optionalDate('latestTradingDay'),
        clearLatestTradingDay: _fields['latestTradingDay']!.text.trim().isEmpty,
        lastTradeFollowedPlan: _lastTradeFollowedPlan,
        emotionallyAffected: _emotional,
        manualStatus: _manualStatus,
        currentPayoutEligibleProfit: _optionalDouble('eligibleProfit'),
        clearPayoutEligibleProfit: _fields['eligibleProfit']!.text
            .trim()
            .isEmpty,
        earliestPayoutDate: _optionalDate('earliestDate'),
        clearEarliestPayoutDate: _fields['earliestDate']!.text.trim().isEmpty,
        nextPayoutRequestDate: _optionalDate('requestDate'),
        clearNextPayoutRequestDate: _fields['requestDate']!.text.trim().isEmpty,
        expectedPayoutProcessingDate: _optionalDate('processingDate'),
        clearExpectedProcessingDate: _fields['processingDate']!.text
            .trim()
            .isEmpty,
        plannedPayoutDate: _optionalDate('plannedDate'),
        clearPlannedPayoutDate: _fields['plannedDate']!.text.trim().isEmpty,
        payoutTimeZone: _fields['payoutZone']!.text.trim(),
        eligibilityAutomaticallyCalculated: _automaticEligibility,
        payoutCanCurrentlyBeRequested: _requestConfirmed,
        consistencyRuleSatisfied: _consistencySatisfied,
        protectUntilPayout: _protectPayout,
        payoutSafetyBuffer: _requiredDouble('safetyBuffer'),
        payoutNotes: _fields['notes']!.text.trim(),
        payoutRequestStatus: _payoutStatus,
        payoutHistory:
            _payoutStatus != widget.account.situation.payoutRequestStatus
            ? [
                ...widget.account.situation.payoutHistory,
                PayoutHistoryEntry(
                  id: 'payout-${DateTime.now().microsecondsSinceEpoch}',
                  status: _payoutStatus,
                  createdAt: DateTime.now(),
                  amount: _optionalDouble('eligibleProfit') ?? 0,
                  notes: _fields['notes']!.text.trim(),
                ),
              ]
            : widget.account.situation.payoutHistory,
        healthyUpperPercent: _requiredDouble('healthy'),
        cautionUpperPercent: _requiredDouble('caution'),
        highRiskUpperPercent: _requiredDouble('highRisk'),
        dailySafetyThresholdPercent: _requiredDouble('lock'),
        quickSetup: !_detailed,
        updatedAt: DateTime.now(),
      );
      final balance = _requiredDouble('balance');
      if (balance <= 0) {
        throw const FormatException('Balance must be positive.');
      }
      Navigator.pop(
        context,
        widget.account.copyWith(
          accountSize: _requiredDouble('accountSize'),
          startingBalance: _requiredDouble('startingBalance'),
          currentBalance: balance,
          manuallyEnteredEquity: _optionalDouble('equity'),
          clearEquity: _fields['equity']!.text.trim().isEmpty,
          completedTradingDays: _optionalInt('tradingDays') ?? 0,
          tradesToday: _optionalInt('tradesToday') ?? 0,
          minimumPayout: _requiredDouble('minimumPayout'),
          desiredPayout: _requiredDouble('desiredPayout'),
          payoutFrequency: _fields['payoutFrequency']!.text.trim(),
          dailyLossLimit: (_optionalDouble('dailyAmount') ?? 0) > 0
              ? _requiredDouble('dailyAmount')
              : _requiredDouble('accountSize') *
                    _requiredDouble('dailyPercent') /
                    100,
          maximumLossLimit: (_optionalDouble('maxAmount') ?? 0) > 0
              ? _requiredDouble('maxAmount')
              : _requiredDouble('startingBalance') *
                    _requiredDouble('maxPercent') /
                    100,
          nextPayoutDate: _optionalDate('requestDate'),
          clearNextPayoutDate: _fields['requestDate']!.text.trim().isEmpty,
          drawdownType: _drawdown,
          situation: situation,
        ),
      );
    } on Object catch (error) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  double _requiredDouble(String key) {
    final value = double.tryParse(_fields[key]!.text.trim());
    if (value == null || !value.isFinite) {
      throw FormatException('Enter a valid value for $key.');
    }
    return value;
  }

  double? _optionalDouble(String key) {
    final raw = _fields[key]!.text.trim();
    if (raw.isEmpty) return null;
    final value = double.tryParse(raw);
    if (value == null || !value.isFinite) {
      throw FormatException('Enter a valid value for $key.');
    }
    return value;
  }

  int? _optionalInt(String key) {
    final raw = _fields[key]!.text.trim();
    if (raw.isEmpty) return null;
    final value = int.tryParse(raw);
    if (value == null) throw FormatException('Enter a valid value for $key.');
    return value;
  }

  DateTime? _optionalDate(String key) {
    final raw = _fields[key]!.text.trim();
    if (raw.isEmpty) return null;
    final value = DateTime.tryParse(raw);
    if (value == null) throw FormatException('Use YYYY-MM-DD for $key.');
    return value;
  }
}

class AccountHealthPage extends StatefulWidget {
  const AccountHealthPage({
    super.key,
    required this.controller,
    required this.portfolio,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  State<AccountHealthPage> createState() => _AccountHealthPageState();
}

class _AccountHealthPageState extends State<AccountHealthPage> {
  var _filter = 'all';
  var _sort = 'healthiest';

  @override
  Widget build(BuildContext context) {
    final recommendation = widget.controller.healthRecommendation!;
    final assessments = recommendation.accountAssessments;
    final mobile = MediaQuery.sizeOf(context).width < 600;
    final accounts = widget.portfolio.activeAccounts.where((account) {
      final status = assessments[account.id]!.status;
      return _filter == 'all' ||
          _filter == 'tradeable' && !assessments[account.id]!.isRestricted ||
          _filter == 'restricted' && assessments[account.id]!.isRestricted ||
          _filter == 'payout' &&
              {
                AccountHealthStatus.payoutProgress,
                AccountHealthStatus.payoutEligible,
                AccountHealthStatus.waitingForPayoutDate,
                AccountHealthStatus.payoutProtection,
              }.contains(status);
    }).toList();
    accounts.sort((left, right) {
      final a = assessments[left.id]!;
      final b = assessments[right.id]!;
      return switch (_sort) {
        'healthiest' => _healthSortScore(
          b.status,
        ).compareTo(_healthSortScore(a.status)),
        'daily' => a.lossLimits.dailyUsagePercent.compareTo(
          b.lossLimits.dailyUsagePercent,
        ),
        'buffer' => b.lossLimits.maximumLossRemaining.compareTo(
          a.lossLimits.maximumLossRemaining,
        ),
        'payout' => _nextPayoutSortDate(
          left,
        ).compareTo(_nextPayoutSortDate(right)),
        'recent' => (right.situation.updatedAt ?? DateTime(1900)).compareTo(
          left.situation.updatedAt ?? DateTime(1900),
        ),
        _ =>
          math
              .max(
                b.lossLimits.dailyUsagePercent,
                b.lossLimits.maximumUsagePercent,
              )
              .compareTo(
                math.max(
                  a.lossLimits.dailyUsagePercent,
                  a.lossLimits.maximumUsagePercent,
                ),
              ),
      };
    });
    return Stack(
      children: [
        ListView(
          padding: EdgeInsets.fromLTRB(20, 20, 20, mobile ? 110 : 20),
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Account Health Overview'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        context.l10n.text(
                          'Loss limits include manual open risk for internal planning. They are not broker-synchronised values.',
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton.filledTonal(
                  tooltip: context.l10n.text('Refresh assessment'),
                  onPressed: widget.controller.refreshHealthAssessment,
                  icon: const Icon(Icons.refresh),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (!widget.portfolio.currentSituationAssessed) ...[
              Card(
                color: Theme.of(context).colorScheme.secondaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      const Icon(Icons.fact_check_outlined),
                      SizedBox(
                        width: mobile ? 260 : 560,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.l10n.text(
                                'Review current account situations',
                              ),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            Text(
                              context.l10n.text(
                                'The original planner remains available. Add current account details when you want health and payout recommendations to use them.',
                              ),
                            ),
                          ],
                        ),
                      ),
                      FilledButton.tonal(
                        onPressed: _openAssessment,
                        child: Text(context.l10n.text('Review situations')),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
            ],
            _PortfolioHealthSummary(
              portfolio: widget.portfolio,
              recommendation: recommendation,
              controller: widget.controller,
            ),
            const SizedBox(height: 14),
            _RecommendationPanel(
              recommendation: recommendation,
              portfolio: widget.portfolio,
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                SizedBox(
                  width: 210,
                  child: DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: _filter,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Filter accounts'),
                    ),
                    items: [
                      for (final value in const [
                        ('all', 'All accounts'),
                        ('tradeable', 'Available to trade'),
                        ('restricted', 'Restricted accounts'),
                        ('payout', 'Payout accounts'),
                      ])
                        DropdownMenuItem(
                          value: value.$1,
                          child: Text(context.l10n.text(value.$2)),
                        ),
                    ],
                    onChanged: (value) => setState(() => _filter = value!),
                  ),
                ),
                SizedBox(
                  width: 210,
                  child: DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: _sort,
                    decoration: InputDecoration(
                      labelText: context.l10n.text('Sort accounts'),
                    ),
                    items: [
                      for (final value in const [
                        ('healthiest', 'Healthiest account'),
                        ('daily', 'Lowest daily-loss usage'),
                        ('buffer', 'Highest overall buffer'),
                        ('payout', 'Closest payout'),
                        ('risk', 'Highest risk first'),
                        ('recent', 'Most recently updated'),
                      ])
                        DropdownMenuItem(
                          value: value.$1,
                          child: Text(context.l10n.text(value.$2)),
                        ),
                    ],
                    onChanged: (value) => setState(() => _sort = value!),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (mobile && accounts.isNotEmpty) ...[
              Text(
                context.l10n.text('Swipe horizontally to compare accounts'),
                style: Theme.of(context).textTheme.labelMedium,
              ),
              const SizedBox(height: 8),
              SizedBox(
                height: 720,
                child: PageView.builder(
                  itemCount: accounts.length,
                  controller: PageController(viewportFraction: .94),
                  itemBuilder: (context, index) => Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: SingleChildScrollView(
                      child: _healthCard(accounts[index], assessments),
                    ),
                  ),
                ),
              ),
            ] else
              for (final account in accounts) ...[
                _healthCard(account, assessments),
                const SizedBox(height: 10),
              ],
            const SizedBox(height: 10),
            _ComparisonTable(accounts: accounts, assessments: assessments),
            const SizedBox(height: 18),
            _PayoutCalendar(
              accounts: widget.portfolio.activeAccounts,
              assessments: assessments,
            ),
            const SizedBox(height: 14),
            const _HealthScenarios(),
            const SizedBox(height: 14),
            const _RuleDisclaimer(),
          ],
        ),
        if (mobile)
          Positioned(
            left: 12,
            right: 12,
            bottom: 8,
            child: SafeArea(
              top: false,
              child: Card(
                elevation: 8,
                child: ListTile(
                  leading: Icon(
                    recommendation.shouldStop
                        ? Icons.stop_circle_outlined
                        : Icons.route_outlined,
                  ),
                  title: Text(
                    context.l10n.text(
                      portfolioRecommendationLabel(recommendation.type),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(
                    context.l10n.text('Sticky portfolio recommendation'),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  Future<void> _openAssessment() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (assessmentContext) => CurrentSituationAssessmentPage(
          controller: widget.controller,
          portfolio: widget.portfolio,
          onCompleted: () => Navigator.of(assessmentContext).pop(),
        ),
      ),
    );
  }

  Future<void> _edit(PropFirmAccount account) async {
    var draft = await showAccountSituationEditor(context, account);
    while (draft != null && mounted) {
      try {
        await widget.controller.saveAccount(draft);
        return;
      } on Object catch (error) {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
        draft = await showAccountSituationEditor(context, draft);
      }
    }
  }

  Widget _healthCard(
    PropFirmAccount account,
    Map<String, AccountHealthAssessment> assessments,
  ) => _HealthAccountCard(
    account: account,
    assessment: assessments[account.id]!,
    onEdit: () => _edit(account),
    onReset: () => _reset(account),
    onOverride: () => _override(account),
  );

  Future<void> _reset(PropFirmAccount account) async {
    await widget.controller.resetAccountToday(account.id);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Account daily values were reset.')),
        ),
      );
    }
  }

  Future<void> _override(PropFirmAccount account) async {
    var status = account.situation.manualStatus;
    final reason = TextEditingController();
    final result = await showDialog<(ManualAccountHealthStatus, String)>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(context.l10n.text('Override calculated health status')),
          content: SizedBox(
            width: 480,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<ManualAccountHealthStatus>(
                  isExpanded: true,
                  initialValue: status,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Manual status'),
                  ),
                  items: [
                    for (final item in ManualAccountHealthStatus.values)
                      DropdownMenuItem(
                        value: item,
                        child: Text(
                          context.l10n.text(manualHealthStatusLabel(item)),
                        ),
                      ),
                  ],
                  onChanged: (value) => setDialogState(() => status = value!),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: reason,
                  minLines: 2,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Override reason (required)'),
                    helperText: context.l10n.text(
                      'The reason is retained in the Firestore audit trail.',
                    ),
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
              onPressed: () => Navigator.pop(context, (status, reason.text)),
              child: Text(context.l10n.text('Record override')),
            ),
          ],
        ),
      ),
    );
    reason.dispose();
    if (result == null) return;
    try {
      await widget.controller.overrideAccountHealthStatus(
        account.id,
        result.$1,
        result.$2,
      );
    } on Object catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    }
  }
}

class _PortfolioHealthSummary extends StatelessWidget {
  const _PortfolioHealthSummary({
    required this.portfolio,
    required this.recommendation,
    required this.controller,
  });

  final PropFirmPortfolio portfolio;
  final PortfolioRecommendation recommendation;
  final PropFirmPlannerController controller;

  @override
  Widget build(BuildContext context) {
    final statuses = recommendation.accountAssessments.values
        .map((item) => item.status)
        .toList();
    final calculations = controller.calculations;
    final now = recommendation.generatedAt;
    final realised = calculations.realisedResultToday(portfolio, now);
    final planned = calculations.activePlannedRisk(portfolio);
    final remaining = calculations.portfolioRiskRemaining(
      portfolioDailyLossLimit: portfolio.riskPolicy.maximumDailyLoss,
      totalRealisedLossToday: calculations.realisedLossToday(portfolio, now),
      totalActivePlannedRisk: planned,
    );
    final metrics = <(String, String, IconData)>[
      (
        'Total accounts',
        '${statuses.length}',
        Icons.account_balance_wallet_outlined,
      ),
      (
        'Healthy',
        '${statuses.where((item) => item == AccountHealthStatus.healthy).length}',
        Icons.health_and_safety_outlined,
      ),
      (
        'Caution',
        '${statuses.where((item) => item == AccountHealthStatus.caution).length}',
        Icons.info_outline,
      ),
      (
        'High risk',
        '${statuses.where((item) => {AccountHealthStatus.highRisk, AccountHealthStatus.critical, AccountHealthStatus.nearDailyLossLimit, AccountHealthStatus.nearMaximumLossLimit}.contains(item)).length}',
        Icons.warning_amber_outlined,
      ),
      (
        'Daily locked',
        '${statuses.where((item) => item == AccountHealthStatus.dailyLocked).length}',
        Icons.lock_clock_outlined,
      ),
      (
        'Payout eligible',
        '${statuses.where((item) => item == AccountHealthStatus.payoutEligible).length}',
        Icons.payments_outlined,
      ),
      (
        'Waiting for payout',
        '${statuses.where((item) => item == AccountHealthStatus.waitingForPayoutDate).length}',
        Icons.event_outlined,
      ),
      (
        'Payout protected',
        '${statuses.where((item) => item == AccountHealthStatus.payoutProtection).length}',
        Icons.shield_outlined,
      ),
      (
        'Breached',
        '${statuses.where((item) => item == AccountHealthStatus.breached).length}',
        Icons.dangerous_outlined,
      ),
      (
        'Planned risk today',
        _money(planned, portfolio.currency),
        Icons.pending_actions_outlined,
      ),
      (
        'Realised today',
        _money(realised, portfolio.currency),
        Icons.payments_outlined,
      ),
      (
        'Portfolio risk remaining',
        _money(remaining, portfolio.currency),
        Icons.shield_outlined,
      ),
      (
        'Portfolio can continue',
        context.l10n.text(recommendation.shouldStop ? 'No' : 'Yes'),
        recommendation.shouldStop
            ? Icons.stop_circle_outlined
            : Icons.play_circle_outline,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth >= 960
            ? (constraints.maxWidth - 24) / 3
            : constraints.maxWidth >= 560
            ? (constraints.maxWidth - 12) / 2
            : constraints.maxWidth;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (final metric in metrics)
              SizedBox(
                width: width,
                child: Card(
                  child: ListTile(
                    leading: Icon(metric.$3),
                    title: Text(context.l10n.text(metric.$1)),
                    trailing: Text(
                      metric.$2,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _RecommendationPanel extends StatelessWidget {
  const _RecommendationPanel({
    required this.recommendation,
    required this.portfolio,
  });

  final PortfolioRecommendation recommendation;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final selected = portfolio.accounts
        .where((account) => account.id == recommendation.recommendedAccountId)
        .firstOrNull;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              spacing: 10,
              runSpacing: 8,
              alignment: WrapAlignment.spaceBetween,
              children: [
                Text(
                  context.l10n.text('Portfolio recommendation'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
                _TextStatusChip(
                  label: context.l10n.text(
                    portfolioRecommendationLabel(recommendation.type),
                  ),
                  warning: recommendation.shouldStop,
                ),
              ],
            ),
            if (selected != null) ...[
              const SizedBox(height: 8),
              Text(
                context.l10n.text('Recommended account: {account}', {
                  'account': selected.name,
                }),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
            if (recommendation.maximumSuggestedRisk case final risk?)
              Text(
                context.l10n.text('Maximum suggested planned risk: {amount}', {
                  'amount': _money(risk, portfolio.currency),
                }),
              ),
            const SizedBox(height: 10),
            for (final reason in recommendation.reasons)
              _ReasonLine(reason: reason, currency: portfolio.currency),
            if (recommendation.confidence !=
                RecommendationConfidence.completeData) ...[
              const Divider(height: 22),
              Text(
                context.l10n.text(
                  'Recommendation based on incomplete information. Review each missing-data warning before trading.',
                ),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const Divider(height: 22),
            Text(
              context.l10n.text('Stop conditions'),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            for (final reason in recommendation.stopConditions)
              _ReasonLine(reason: reason, currency: portfolio.currency),
          ],
        ),
      ),
    );
  }
}

class _HealthAccountCard extends StatelessWidget {
  const _HealthAccountCard({
    required this.account,
    required this.assessment,
    required this.onEdit,
    required this.onReset,
    required this.onOverride,
  });

  final PropFirmAccount account;
  final AccountHealthAssessment assessment;
  final VoidCallback onEdit;
  final VoidCallback onReset;
  final VoidCallback onOverride;

  @override
  Widget build(BuildContext context) {
    final loss = assessment.lossLimits;
    final situation = account.situation;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              spacing: 12,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                CircleAvatar(
                  backgroundColor: Color(
                    account.colorValue,
                  ).withValues(alpha: .12),
                  foregroundColor: Color(account.colorValue),
                  child: const Icon(Icons.account_balance_outlined),
                ),
                SizedBox(
                  width: 210,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account.name,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(account.propFirmName),
                    ],
                  ),
                ),
                _TextStatusChip(
                  label: context.l10n.text(
                    accountHealthStatusLabel(assessment.status),
                  ),
                  warning:
                      assessment.isRestricted ||
                      {
                        AccountHealthStatus.highRisk,
                        AccountHealthStatus.critical,
                        AccountHealthStatus.nearDailyLossLimit,
                        AccountHealthStatus.nearMaximumLossLimit,
                      }.contains(assessment.status),
                ),
                if (assessment.manualStatusDiffers)
                  Chip(
                    avatar: const Icon(Icons.person_outline, size: 18),
                    label: Text(
                      context.l10n.text('Manual: {status}', {
                        'status': context.l10n.text(
                          manualHealthStatusLabel(assessment.manualStatus),
                        ),
                      }),
                    ),
                  ),
                const SizedBox(width: 8),
                TextButton.icon(
                  onPressed: onOverride,
                  icon: const Icon(Icons.rule_folder_outlined),
                  label: Text(context.l10n.text('Override')),
                ),
                TextButton.icon(
                  onPressed: onReset,
                  icon: const Icon(Icons.restart_alt),
                  label: Text(context.l10n.text('Reset day')),
                ),
                FilledButton.tonalIcon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(context.l10n.text('Update snapshot')),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 20,
              runSpacing: 8,
              children: [
                _ValueText(
                  'Current balance',
                  _money(account.currentBalance, account.currency),
                ),
                _ValueText(
                  "Today's result",
                  _money(situation.todayRealisedProfitLoss, account.currency),
                ),
                _ValueText(
                  'Payout progress',
                  '${_money(assessment.payout.eligibleProfit, account.currency)} / ${_money(assessment.payout.requiredProfit, account.currency)}',
                ),
                _ValueText(
                  'Next payout date',
                  _date(
                    situation.nextPayoutRequestDate ?? account.nextPayoutDate,
                  ),
                ),
                _ValueText(
                  'Payout request status',
                  context.l10n.text(
                    payoutRequestStatusLabel(situation.payoutRequestStatus),
                  ),
                ),
                _ValueText(
                  'Payout history events',
                  '${situation.payoutHistory.length}',
                ),
                _ValueText(
                  'Daily reset time',
                  context.l10n.text(_resetLabel(situation)),
                ),
                _ValueText('Last updated', _dateTime(situation.updatedAt)),
              ],
            ),
            if (situation.resetUtcOffsetMinutes case final offset?
                when offset != DateTime.now().timeZoneOffset.inMinutes) ...[
              const SizedBox(height: 8),
              Text(
                context.l10n.text(
                  'The configured firm UTC offset differs from this device. Confirm the reset time before relying on daily values.',
                ),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const SizedBox(height: 16),
            _RiskBar(
              title: 'Daily loss',
              used: loss.dailyLossUsed,
              limit: loss.dailyLossAmount,
              remaining: loss.dailyLossRemaining,
              percent: loss.dailyUsagePercent,
              currency: account.currency,
              estimated: loss.isEstimated,
            ),
            const SizedBox(height: 12),
            _RiskBar(
              title: 'Overall loss',
              used: loss.maximumLossUsed,
              limit: loss.maximumLossAmount,
              remaining: loss.maximumLossRemaining,
              percent: loss.maximumUsagePercent,
              currency: account.currency,
              estimated: loss.isEstimated,
            ),
            const SizedBox(height: 14),
            Text(
              context.l10n.text(
                assessment.recommendedAction.messageKey,
                _displayValues(
                  assessment.recommendedAction.values,
                  account.currency,
                ),
              ),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (assessment.missingData.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final item in assessment.missingData)
                    Chip(
                      avatar: const Icon(Icons.help_outline, size: 17),
                      label: Text(context.l10n.text(item.messageKey)),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RiskBar extends StatelessWidget {
  const _RiskBar({
    required this.title,
    required this.used,
    required this.limit,
    required this.remaining,
    required this.percent,
    required this.currency,
    required this.estimated,
  });

  final String title;
  final double used;
  final double limit;
  final double remaining;
  final double percent;
  final String currency;
  final bool estimated;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final warning = percent >= 60;
    return Semantics(
      label:
          '${context.l10n.text(title)}. ${percent.toStringAsFixed(0)} percent used. ${_money(remaining, currency)} remaining.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            children: [
              Text(
                context.l10n.text('{title} used: {used} / {limit}', {
                  'title': context.l10n.text(title),
                  'used': _money(used, currency),
                  'limit': _money(limit, currency),
                }),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              Text(
                context.l10n.text('{percent}% used · {remaining} remaining', {
                  'percent': percent.toStringAsFixed(0),
                  'remaining': _money(remaining, currency),
                }),
              ),
            ],
          ),
          const SizedBox(height: 6),
          LinearProgressIndicator(
            value: (percent / 100).clamp(0, 1),
            minHeight: 9,
            borderRadius: BorderRadius.circular(9),
            color: warning ? scheme.error : scheme.primary,
          ),
          if (estimated)
            Text(
              context.l10n.text('Estimated from incomplete data'),
              style: Theme.of(context).textTheme.labelSmall,
            ),
        ],
      ),
    );
  }
}

class _ComparisonTable extends StatelessWidget {
  const _ComparisonTable({required this.accounts, required this.assessments});

  final List<PropFirmAccount> accounts;
  final Map<String, AccountHealthAssessment> assessments;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text('Account comparison table'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                for (final label in const [
                  'Account',
                  'Firm',
                  'Balance',
                  'Daily used',
                  'Daily remaining',
                  'Overall used',
                  'Overall remaining',
                  'Profit',
                  'Payout progress',
                  'Payout date',
                  'Trading days remaining',
                  'Status',
                  'Recommended action',
                  'Suggested risk',
                ])
                  DataColumn(label: Text(context.l10n.text(label))),
              ],
              rows: [
                for (final account in accounts)
                  DataRow(
                    cells: [
                      DataCell(Text(account.name)),
                      DataCell(Text(account.propFirmName)),
                      DataCell(
                        Text(_money(account.currentBalance, account.currency)),
                      ),
                      DataCell(
                        Text(
                          '${_money(assessments[account.id]!.lossLimits.dailyLossUsed, account.currency)} '
                          '(${assessments[account.id]!.lossLimits.dailyUsagePercent.toStringAsFixed(0)}%)',
                        ),
                      ),
                      DataCell(
                        Text(
                          _money(
                            assessments[account.id]!
                                .lossLimits
                                .dailyLossRemaining,
                            account.currency,
                          ),
                        ),
                      ),
                      DataCell(
                        Text(
                          '${_money(assessments[account.id]!.lossLimits.maximumLossUsed, account.currency)} '
                          '(${assessments[account.id]!.lossLimits.maximumUsagePercent.toStringAsFixed(0)}%)',
                        ),
                      ),
                      DataCell(
                        Text(
                          _money(
                            assessments[account.id]!
                                .lossLimits
                                .maximumLossRemaining,
                            account.currency,
                          ),
                        ),
                      ),
                      DataCell(
                        Text(_money(account.currentProfit, account.currency)),
                      ),
                      DataCell(
                        Text(
                          '${_money(assessments[account.id]!.payout.eligibleProfit, account.currency)} / '
                          '${_money(assessments[account.id]!.payout.requiredProfit, account.currency)}',
                        ),
                      ),
                      DataCell(
                        Text(
                          '${assessments[account.id]!.payout.tradingDaysRemaining}',
                        ),
                      ),
                      DataCell(
                        Text(
                          context.l10n.text(
                            accountHealthStatusLabel(
                              assessments[account.id]!.status,
                            ),
                          ),
                        ),
                      ),
                      DataCell(
                        Text(
                          context.l10n.text(
                            assessments[account.id]!
                                .recommendedAction
                                .messageKey,
                            _displayValues(
                              assessments[account.id]!.recommendedAction.values,
                              account.currency,
                            ),
                          ),
                        ),
                      ),
                      DataCell(
                        Text(
                          _date(
                            account.situation.nextPayoutRequestDate ??
                                account.nextPayoutDate,
                          ),
                        ),
                      ),
                      DataCell(
                        Text(
                          _money(
                            assessments[account.id]!.maximumSuggestedRisk,
                            account.currency,
                          ),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _PayoutCalendar extends StatelessWidget {
  const _PayoutCalendar({required this.accounts, required this.assessments});

  final List<PropFirmAccount> accounts;
  final Map<String, AccountHealthAssessment> assessments;

  @override
  Widget build(BuildContext context) {
    final dated =
        accounts
            .where(
              (account) =>
                  account.situation.nextPayoutRequestDate != null ||
                  account.nextPayoutDate != null ||
                  account.situation.plannedPayoutDate != null ||
                  account.situation.expectedPayoutProcessingDate != null,
            )
            .toList()
          ..sort((a, b) => _nextDate(a).compareTo(_nextDate(b)));
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text('Payout calendar'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            Text(
              context.l10n.text(
                'A payout date alone does not establish eligibility; profit, trading-day, consistency, and confirmation rules are also checked.',
              ),
            ),
            const SizedBox(height: 10),
            if (dated.isEmpty)
              Text(context.l10n.text('No payout dates have been entered.'))
            else
              for (final account in dated)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.event_available_outlined),
                  title: Text(account.name),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _payoutCountdown(
                          context,
                          assessments[account.id]!,
                          _nextDate(account),
                        ),
                      ),
                      Text(
                        context.l10n.text(
                          'Eligible profit {profit} · safety buffer {buffer} · above threshold {above}',
                          {
                            'profit': _money(
                              assessments[account.id]!.payout.eligibleProfit,
                              account.currency,
                            ),
                            'buffer': _money(
                              account.situation.payoutSafetyBuffer,
                              account.currency,
                            ),
                            'above': _money(
                              assessments[account.id]!
                                  .payout
                                  .profitAboveThreshold,
                              account.currency,
                            ),
                          },
                        ),
                      ),
                      Text(
                        context.l10n.text(
                          'Planned {planned} · expected processing {processing} · history {count}',
                          {
                            'planned': _date(
                              account.situation.plannedPayoutDate,
                            ),
                            'processing': _date(
                              account.situation.expectedPayoutProcessingDate,
                            ),
                            'count': account.situation.payoutHistory.length,
                          },
                        ),
                      ),
                    ],
                  ),
                  trailing: _TextStatusChip(
                    label: context.l10n.text(
                      accountHealthStatusLabel(assessments[account.id]!.status),
                    ),
                    warning: assessments[account.id]!.isRestricted,
                  ),
                ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'The safety buffer is a personal planning setting, not an official prop-firm requirement. Expected processing dates are not payout guarantees.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }

  DateTime _nextDate(PropFirmAccount account) =>
      account.situation.nextPayoutRequestDate ??
      account.nextPayoutDate ??
      account.situation.plannedPayoutDate ??
      account.situation.expectedPayoutProcessingDate ??
      DateTime(9999);
}

class _RuleDisclaimer extends StatelessWidget {
  const _RuleDisclaimer();

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: ListTile(
      leading: const Icon(Icons.rule_outlined),
      title: Text(
        context.l10n.text(
          'Planning defaults: 10% maximum loss and 5% daily loss',
        ),
      ),
      subtitle: Text(
        context.l10n.text(
          'Verify the exact calculation method, reset time, balance or equity basis, trailing rule, and payout terms with your prop firm. Defaults are editable.',
        ),
      ),
    ),
  );
}

class _HealthScenarios extends StatelessWidget {
  const _HealthScenarios();

  @override
  Widget build(BuildContext context) {
    const scenarios = <(String, String, String)>[
      (
        'Scenario A · Near daily loss',
        'Daily limit USD 5,000 · used USD 4,400 · remaining USD 600 · normal risk USD 1,000',
        'Stop this account today because its remaining daily room is below normal risk.',
      ),
      (
        'Scenario B · Healthy account',
        'Daily loss used USD 300 of USD 5,000 · overall loss used USD 1,000 of USD 10,000',
        'The account may be considered for the next valid planned trade, subject to portfolio limits.',
      ),
      (
        'Scenario C · Waiting for payout',
        'Minimum payout USD 150 · eligible profit USD 450 · requirements completed · future payout date',
        'Protect the account until its payout date; additional trading may reduce eligibility.',
      ),
      (
        'Scenario D · Two accounts lose',
        'Account A SL · Account B SL · portfolio maximum losses per day 2',
        'Stop the portfolio. Do not move to another account after the portfolio limit is reached.',
      ),
      (
        'Scenario E · Trading days remain',
        'Profit complete · 5 trading days required · 3 completed',
        'Use only genuine planned low-risk setups. Do not trade solely to create artificial trading days.',
      ),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          children: [
            ListTile(
              leading: const Icon(Icons.school_outlined),
              title: Text(
                context.l10n.text('Account health scenarios'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                context.l10n.text(
                  'Educational examples only; your configured rules control live recommendations.',
                ),
              ),
            ),
            for (final scenario in scenarios)
              ExpansionTile(
                title: Text(context.l10n.text(scenario.$1)),
                subtitle: Text(context.l10n.text(scenario.$2)),
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        context.l10n.text(scenario.$3),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _ReasonLine extends StatelessWidget {
  const _ReasonLine({required this.reason, required this.currency});

  final RecommendationReason reason;
  final String currency;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 7),
          child: Icon(Icons.circle, size: 6),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            context.l10n.text(
              reason.messageKey,
              _displayValues(reason.values, currency),
            ),
          ),
        ),
      ],
    ),
  );
}

class _TextStatusChip extends StatelessWidget {
  const _TextStatusChip({required this.label, this.warning = false});

  final String label;
  final bool warning;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(
      warning ? Icons.warning_amber_outlined : Icons.check_circle_outline,
      size: 18,
    ),
    label: Text(label),
    backgroundColor: warning
        ? Theme.of(context).colorScheme.errorContainer
        : Theme.of(context).colorScheme.secondaryContainer,
  );
}

class _ValueText extends StatelessWidget {
  const _ValueText(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 190,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(label),
          style: Theme.of(context).textTheme.labelSmall,
        ),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
}

String accountHealthStatusLabel(AccountHealthStatus status) => switch (status) {
  AccountHealthStatus.healthy => 'Healthy',
  AccountHealthStatus.caution => 'Caution',
  AccountHealthStatus.highRisk => 'High risk',
  AccountHealthStatus.critical => 'Critical',
  AccountHealthStatus.nearDailyLossLimit => 'Near daily loss limit',
  AccountHealthStatus.nearMaximumLossLimit => 'Near maximum loss limit',
  AccountHealthStatus.dailyLocked => 'Daily locked',
  AccountHealthStatus.recovery => 'Recovery',
  AccountHealthStatus.payoutProgress => 'Payout progress',
  AccountHealthStatus.payoutEligible => 'Payout eligible',
  AccountHealthStatus.waitingForPayoutDate => 'Waiting for payout date',
  AccountHealthStatus.payoutProtection => 'Payout protection',
  AccountHealthStatus.paused => 'Paused',
  AccountHealthStatus.breached => 'Breached',
  AccountHealthStatus.archived => 'Archived',
};

int _healthSortScore(AccountHealthStatus status) => switch (status) {
  AccountHealthStatus.healthy => 100,
  AccountHealthStatus.caution => 85,
  AccountHealthStatus.payoutProgress => 80,
  AccountHealthStatus.highRisk => 65,
  AccountHealthStatus.critical => 20,
  AccountHealthStatus.recovery => 55,
  AccountHealthStatus.payoutEligible => 50,
  AccountHealthStatus.waitingForPayoutDate => 45,
  AccountHealthStatus.nearDailyLossLimit ||
  AccountHealthStatus.nearMaximumLossLimit => 25,
  AccountHealthStatus.dailyLocked ||
  AccountHealthStatus.payoutProtection ||
  AccountHealthStatus.paused => 15,
  AccountHealthStatus.breached || AccountHealthStatus.archived => 0,
};

DateTime _nextPayoutSortDate(PropFirmAccount account) =>
    account.situation.nextPayoutRequestDate ??
    account.nextPayoutDate ??
    account.situation.plannedPayoutDate ??
    DateTime(9999);

String manualHealthStatusLabel(
  ManualAccountHealthStatus status,
) => switch (status) {
  ManualAccountHealthStatus.healthy => 'Healthy',
  ManualAccountHealthStatus.moderateRisk => 'Moderate risk',
  ManualAccountHealthStatus.nearDailyLossLimit => 'Near daily loss limit',
  ManualAccountHealthStatus.nearMaximumLossLimit => 'Near maximum loss limit',
  ManualAccountHealthStatus.dailyLocked => 'Daily locked',
  ManualAccountHealthStatus.recovery => 'Recovery',
  ManualAccountHealthStatus.payoutProgress => 'Payout progress',
  ManualAccountHealthStatus.payoutEligible => 'Payout eligible',
  ManualAccountHealthStatus.waitingForPayoutDate => 'Waiting for payout date',
  ManualAccountHealthStatus.payoutProtection => 'Payout protection',
  ManualAccountHealthStatus.paused => 'Paused',
  ManualAccountHealthStatus.breached => 'Breached',
  ManualAccountHealthStatus.archived => 'Archived',
};

String portfolioRecommendationLabel(PortfolioRecommendationType type) =>
    switch (type) {
      PortfolioRecommendationType.continueNormally =>
        'Continue within configured limits',
      PortfolioRecommendationType.continueWithReducedRisk =>
        'Continue with reduced risk',
      PortfolioRecommendationType.switchAccount => 'Switch account',
      PortfolioRecommendationType.protectForPayout => 'Protect for payout',
      PortfolioRecommendationType.stopSpecificAccount =>
        'Stop a specific account',
      PortfolioRecommendationType.stopPortfolio => 'Stop portfolio',
      PortfolioRecommendationType.pauseAndReview => 'Pause and review',
      PortfolioRecommendationType.completeRemainingRequirementsCarefully =>
        'Complete remaining requirements carefully',
      PortfolioRecommendationType.noEligibleAccount => 'No eligible account',
    };

String drawdownTypeLabel(DrawdownType type) => switch (type) {
  DrawdownType.static => 'Static',
  DrawdownType.balanceBased => 'Balance based',
  DrawdownType.equityBased => 'Equity based',
  DrawdownType.trailing => 'Trailing',
  DrawdownType.endOfDayTrailing => 'End-of-day trailing',
  DrawdownType.custom => 'Custom',
};

String dailyLossMethodLabel(DailyLossCalculationMethod method) =>
    switch (method) {
      DailyLossCalculationMethod.realisedOnly => 'Realised only',
      DailyLossCalculationMethod.realisedAndUnrealised =>
        'Realised and unrealised',
      DailyLossCalculationMethod.balanceBased => 'Balance based',
      DailyLossCalculationMethod.equityBased => 'Equity based',
      DailyLossCalculationMethod.custom => 'Custom',
    };

String payoutRequestStatusLabel(PayoutRequestStatus status) => switch (status) {
  PayoutRequestStatus.profitIncomplete => 'Profit incomplete',
  PayoutRequestStatus.tradingDaysIncomplete => 'Trading days incomplete',
  PayoutRequestStatus.waitingForDate => 'Waiting for date',
  PayoutRequestStatus.eligibleToday => 'Eligible today',
  PayoutRequestStatus.protected => 'Protected',
  PayoutRequestStatus.requestSubmitted => 'Request submitted',
  PayoutRequestStatus.paid => 'Paid',
  PayoutRequestStatus.rejectedOrDelayed => 'Rejected or delayed',
  PayoutRequestStatus.userCancelled => 'User cancelled',
};

String dailyResetMethodLabel(DailyResetMethod method) => switch (method) {
  DailyResetMethod.automaticAtFirmTime => 'Automatic at firm time',
  DailyResetMethod.manualOnly => 'Manual only',
};

String _payoutCountdown(
  BuildContext context,
  AccountHealthAssessment assessment,
  DateTime date,
) {
  if (!assessment.payout.profitReached) {
    return context.l10n.text('Payout profit requirement is incomplete');
  }
  if (!assessment.payout.tradingDaysComplete) {
    return context.l10n.text('{count} trading days remain', {
      'count': assessment.payout.tradingDaysRemaining,
    });
  }
  if (!assessment.payout.consistencySatisfied) {
    return context.l10n.text('Consistency requirement is incomplete');
  }
  if (assessment.payout.eligible) {
    return context.l10n.text('Payout request is available today');
  }
  if (assessment.payout.waitingForDate) {
    return context.l10n.text('Payout request available in {count} days', {
      'count': assessment.payout.daysUntilRequest,
    });
  }
  return _date(date);
}

Map<String, Object?> _displayValues(
  Map<String, Object?> values,
  String currency,
) => {
  for (final entry in values.entries)
    entry.key:
        entry.value is double && {'amount', 'remaining'}.contains(entry.key)
        ? _money(entry.value! as double, currency)
        : entry.value is double
        ? (entry.value! as double).toStringAsFixed(0)
        : entry.value,
};

TextEditingController _controller(Object? value) => TextEditingController(
  text: value == null
      ? ''
      : value is double && value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : '$value',
);

TextEditingController _dateController(DateTime? value) =>
    TextEditingController(text: _date(value, empty: ''));

String _money(double value, String currency) =>
    '$currency ${value.toStringAsFixed(2)}';

String _date(DateTime? value, {String empty = '—'}) => value == null
    ? empty
    : '${value.year.toString().padLeft(4, '0')}-'
          '${value.month.toString().padLeft(2, '0')}-'
          '${value.day.toString().padLeft(2, '0')}';

String _dateTime(DateTime? value) => value == null
    ? '—'
    : '${_date(value)} ${value.hour.toString().padLeft(2, '0')}:'
          '${value.minute.toString().padLeft(2, '0')}';

String _resetLabel(AccountCurrentSituation situation) {
  if (situation.dailyResetMethod == DailyResetMethod.manualOnly) {
    return 'Manual only';
  }
  if (situation.resetHour == null) return '—';
  final time =
      '${situation.resetHour!.toString().padLeft(2, '0')}:'
      '${situation.resetMinute.toString().padLeft(2, '0')}';
  final zone = situation.resetTimeZone.trim();
  return zone.isEmpty ? time : '$time $zone';
}
