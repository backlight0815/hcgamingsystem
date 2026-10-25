import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/localization/app_localizations.dart';
import '../domain/prop_firm_calculation_service.dart';
import '../domain/prop_firm_models.dart';

Future<PropFirmAccount?> showPropFirmAccountEditor(
  BuildContext context,
  PropFirmAccount account,
) {
  return Navigator.of(context).push<PropFirmAccount>(
    MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => _AccountEditor(account: account),
    ),
  );
}

class _AccountEditor extends StatefulWidget {
  const _AccountEditor({required this.account});

  final PropFirmAccount account;

  @override
  State<_AccountEditor> createState() => _AccountEditorState();
}

class _AccountEditorState extends State<_AccountEditor> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late DrawdownType _drawdownType;
  late AccountStatus _status;
  late AccountPriority _priority;
  late bool _dailyTradeLimitEnabled;
  DateTime? _nextPayoutDate;

  @override
  void initState() {
    super.initState();
    final account = widget.account;
    _fields = {
      'name': TextEditingController(text: account.name),
      'firm': TextEditingController(text: account.propFirmName),
      'identifier': TextEditingController(text: account.identifier),
      'currency': TextEditingController(text: account.currency),
      'accountSize': _numberController(account.accountSize),
      'startingBalance': _numberController(account.startingBalance),
      'currentBalance': _numberController(account.currentBalance),
      'equity': TextEditingController(
        text: account.manuallyEnteredEquity == null
            ? ''
            : _number(account.manuallyEnteredEquity!),
      ),
      'fundedStatus': TextEditingController(text: account.fundedStatus),
      'phase': TextEditingController(text: account.phase),
      'dailyLoss': _numberController(account.dailyLossLimit),
      'maximumLoss': _numberController(account.maximumLossLimit),
      'profitTarget': _numberController(account.profitTarget),
      'minimumPayout': _numberController(account.minimumPayout),
      'desiredPayout': _numberController(account.desiredPayout),
      'profitSplit': _numberController(account.profitSplit * 100),
      'minimumDays': _numberController(account.minimumTradingDays.toDouble()),
      'completedDays': _numberController(
        account.completedTradingDays.toDouble(),
      ),
      'payoutFrequency': TextEditingController(text: account.payoutFrequency),
      'consistency': _numberController(account.consistencyRuleLimit),
      'maxRisk': _numberController(account.maxRiskPerTrade),
      'maxTrades': _numberController(account.maxTradesPerDay.toDouble()),
      'allocation': _numberController(account.virtualRiskAllocation),
      'notes': TextEditingController(text: account.notes),
    };
    _drawdownType = account.drawdownType;
    _status = account.status;
    _priority = account.priority;
    _dailyTradeLimitEnabled = account.dailyTradeLimitEnabled;
    _nextPayoutDate = account.nextPayoutDate;
  }

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
    return Scaffold(
      appBar: AppBar(
        title: Text(
          l10n.text(
            widget.account.name.isEmpty ? 'Add funded account' : 'Edit account',
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: FilledButton(
              onPressed: _save,
              child: Text(l10n.text('Save account')),
            ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
          children: [
            _notice(
              context,
              Icons.lock_outline,
              l10n.text(
                'Planner only. Never enter an account password or broker credential.',
              ),
            ),
            const SizedBox(height: 18),
            _section(context, 'Identity'),
            _responsiveFields([
              _text('name', 'Account name', required: true),
              _text('firm', 'Prop-firm name', required: true),
              _text('identifier', 'Account nickname or identifier'),
              _text('currency', 'Currency', required: true),
              _text('fundedStatus', 'Challenge or funded status'),
              _text('phase', 'Phase'),
            ]),
            const SizedBox(height: 20),
            _section(context, 'Balances and drawdown'),
            _responsiveFields([
              _numeric('accountSize', 'Account size', positive: true),
              _numeric('startingBalance', 'Starting balance', positive: true),
              _numeric('currentBalance', 'Current balance'),
              _numeric('equity', 'Current equity (manual)', optional: true),
              _numeric('dailyLoss', 'Daily loss limit', positive: true),
              _numeric('maximumLoss', 'Maximum loss limit', positive: true),
            ]),
            const SizedBox(height: 12),
            DropdownButtonFormField<DrawdownType>(
              initialValue: _drawdownType,
              decoration: InputDecoration(
                labelText: l10n.text('Drawdown model'),
                helperText: l10n.text(
                  'Choose the model configured by your firm. Verify its exact formula.',
                ),
              ),
              items: [
                for (final value in DrawdownType.values)
                  DropdownMenuItem(
                    value: value,
                    child: Text(l10n.text(_drawdownLabel(value))),
                  ),
              ],
              onChanged: (value) => _drawdownType = value ?? _drawdownType,
            ),
            const SizedBox(height: 20),
            _section(context, 'Targets and payout rules'),
            _responsiveFields([
              _numeric('profitTarget', 'Profit target', optional: true),
              _numeric('minimumPayout', 'Minimum payout', optional: true),
              _numeric('desiredPayout', 'Desired gross payout', optional: true),
              _numeric('profitSplit', 'Profit split (%)', positive: true),
              _numeric('minimumDays', 'Minimum trading days', optional: true),
              _numeric(
                'completedDays',
                'Completed trading days',
                optional: true,
              ),
              _text('payoutFrequency', 'Payout frequency'),
              _numeric(
                'consistency',
                'Consistency-rule limit (%)',
                optional: true,
              ),
            ]),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _pickDate,
              icon: const Icon(Icons.event_outlined),
              label: Text(
                _nextPayoutDate == null
                    ? l10n.text('Set next payout eligibility date')
                    : l10n.text('Payout eligible: {date}', {
                        'date': MaterialLocalizations.of(
                          context,
                        ).formatMediumDate(_nextPayoutDate!),
                      }),
              ),
            ),
            const SizedBox(height: 20),
            _section(context, 'Daily Trading Controls'),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _dailyTradeLimitEnabled,
              onChanged: (value) =>
                  setState(() => _dailyTradeLimitEnabled = value),
              title: Text(l10n.text('Enable daily trade limit')),
              subtitle: Text(
                l10n.text(
                  'This limit applies only to this account. Other eligible accounts can continue trading.',
                ),
              ),
            ),
            _responsiveFields([
              _wholeNumber('maxTrades', 'Maximum trades per day'),
            ]),
            const SizedBox(height: 20),
            _section(context, 'Risk and planner state'),
            _responsiveFields([
              _numeric('maxRisk', 'Maximum risk per trade', positive: true),
              _numeric('allocation', 'Virtual risk allocation', optional: true),
            ]),
            const SizedBox(height: 12),
            _responsiveFields([
              DropdownButtonFormField<AccountStatus>(
                initialValue: _status,
                decoration: InputDecoration(
                  labelText: l10n.text('Account status'),
                ),
                items: [
                  for (final value in AccountStatus.values)
                    DropdownMenuItem(
                      value: value,
                      child: Text(l10n.text(accountStatusLabel(value))),
                    ),
                ],
                onChanged: (value) => _status = value ?? _status,
              ),
              DropdownButtonFormField<AccountPriority>(
                initialValue: _priority,
                decoration: InputDecoration(
                  labelText: l10n.text('Account priority'),
                ),
                items: [
                  for (final value in AccountPriority.values)
                    DropdownMenuItem(
                      value: value,
                      child: Text(l10n.text(_priorityLabel(value))),
                    ),
                ],
                onChanged: (value) => _priority = value ?? _priority,
              ),
            ]),
            const SizedBox(height: 14),
            TextFormField(
              controller: _fields['notes'],
              maxLines: 4,
              decoration: InputDecoration(labelText: l10n.text('Notes')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _section(BuildContext context, String key) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Text(
      context.l10n.text(key),
      style: Theme.of(
        context,
      ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
    ),
  );

  Widget _text(String key, String label, {bool required = false}) => Builder(
    builder: (context) => TextFormField(
      controller: _fields[key],
      decoration: InputDecoration(labelText: context.l10n.text(label)),
      validator: required
          ? (value) => (value ?? '').trim().isEmpty
                ? context.l10n.text('This field is required')
                : null
          : null,
    ),
  );

  Widget _numeric(
    String key,
    String label, {
    bool positive = false,
    bool optional = false,
  }) => Builder(
    builder: (context) => TextFormField(
      controller: _fields[key],
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: context.l10n.text(label)),
      validator: (value) {
        final text = (value ?? '').trim();
        if (optional && text.isEmpty) return null;
        final number = double.tryParse(text);
        if (number == null || !number.isFinite || (positive && number <= 0)) {
          return context.l10n.text('Enter a valid number');
        }
        return null;
      },
    ),
  );

  Widget _responsiveFields(List<Widget> children) => LayoutBuilder(
    builder: (context, constraints) {
      if (constraints.maxWidth < 720) {
        return Column(
          children: [
            for (final child in children) ...[
              child,
              const SizedBox(height: 12),
            ],
          ],
        );
      }
      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          for (final child in children)
            SizedBox(width: (constraints.maxWidth - 12) / 2, child: child),
        ],
      );
    },
  );

  Widget _wholeNumber(String key, String label) => Builder(
    builder: (context) => TextFormField(
      controller: _fields[key],
      enabled: _dailyTradeLimitEnabled,
      keyboardType: TextInputType.number,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      decoration: InputDecoration(
        labelText: context.l10n.text(label),
        helperText: context.l10n.text('Enter a whole number from 1 to 100.'),
      ),
      validator: (value) {
        if (!_dailyTradeLimitEnabled) return null;
        final number = int.tryParse((value ?? '').trim());
        return number == null || number < 1 || number > 100
            ? context.l10n.text('Enter a whole number from 1 to 100.')
            : null;
      },
    ),
  );

  Future<void> _pickDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _nextPayoutDate ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (value != null) setState(() => _nextPayoutDate = value);
  }

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    final source = widget.account;
    final maximumLoss = _value('maximumLoss');
    final dailyLoss = _value('dailyLoss');
    if (maximumLoss < dailyLoss) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.l10n.text(
              'Maximum loss limit cannot be smaller than the daily loss limit.',
            ),
          ),
        ),
      );
      return;
    }
    final split = _value('profitSplit') / 100;
    if (split > 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Profit split cannot exceed 100%.')),
        ),
      );
      return;
    }
    Navigator.pop(
      context,
      source.copyWith(
        name: _fields['name']!.text.trim(),
        propFirmName: _fields['firm']!.text.trim(),
        identifier: _fields['identifier']!.text.trim(),
        currency: _fields['currency']!.text.trim().toUpperCase(),
        accountSize: _value('accountSize'),
        startingBalance: _value('startingBalance'),
        currentBalance: _value('currentBalance'),
        manuallyEnteredEquity: _nullableValue('equity'),
        clearEquity: _fields['equity']!.text.trim().isEmpty,
        fundedStatus: _fields['fundedStatus']!.text.trim(),
        phase: _fields['phase']!.text.trim(),
        dailyLossLimit: dailyLoss,
        maximumLossLimit: maximumLoss,
        drawdownType: _drawdownType,
        profitTarget: _value('profitTarget'),
        minimumPayout: _value('minimumPayout'),
        desiredPayout: _value('desiredPayout'),
        profitSplit: split,
        minimumTradingDays: _value('minimumDays').round(),
        completedTradingDays: _value('completedDays').round(),
        payoutFrequency: _fields['payoutFrequency']!.text.trim(),
        nextPayoutDate: _nextPayoutDate,
        clearNextPayoutDate: _nextPayoutDate == null,
        consistencyRuleLimit: _value('consistency'),
        maxRiskPerTrade: _value('maxRisk'),
        dailyTradeLimitEnabled: _dailyTradeLimitEnabled,
        maxTradesPerDay: _value('maxTrades').round().clamp(1, 100),
        virtualRiskAllocation: _value('allocation'),
        notes: _fields['notes']!.text.trim(),
        status: _status,
        priority: _priority,
      ),
    );
  }

  double _value(String key) => double.tryParse(_fields[key]!.text.trim()) ?? 0;
  double? _nullableValue(String key) =>
      double.tryParse(_fields[key]!.text.trim());
}

Future<PortfolioRiskPolicy?> showRiskPolicyEditor(
  BuildContext context,
  PortfolioRiskPolicy policy,
) => showDialog<PortfolioRiskPolicy>(
  context: context,
  builder: (_) => _RiskPolicyDialog(policy: policy),
);

class _RiskPolicyDialog extends StatefulWidget {
  const _RiskPolicyDialog({required this.policy});

  final PortfolioRiskPolicy policy;

  @override
  State<_RiskPolicyDialog> createState() => _RiskPolicyDialogState();
}

class _RiskPolicyDialogState extends State<_RiskPolicyDialog> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late bool _simultaneous;
  late bool _emotionalStop;
  late bool _oneWinStop;
  late bool _oneLossStop;
  late bool _consecutiveLossStop;
  late bool _dailyTargetStop;

  @override
  void initState() {
    super.initState();
    final value = widget.policy;
    _fields = {
      'daily': _numberController(value.maximumDailyLoss),
      'risk': _numberController(value.maximumRiskPerTrade),
      'trades': _numberController(value.maximumTradesPerDay.toDouble()),
      'losses': _numberController(value.maximumConsecutiveLosses.toDouble()),
      'accounts': _numberController(
        value.maximumAccountsTradedPerDay.toDouble(),
      ),
      'positions': _numberController(value.maximumOpenPositions.toDouble()),
      'winCooldown': _numberController(
        value.cooldownAfterWinMinutes.toDouble(),
      ),
      'lossCooldown': _numberController(
        value.cooldownAfterLossMinutes.toDouble(),
      ),
      'resetHour': _numberController(value.resetHourUtc.toDouble()),
      'utcOffset': _numberController(value.resetUtcOffsetMinutes.toDouble()),
      'maximumSessionLossR': _numberController(value.maximumSessionLossR),
      'profitTarget': _numberController(value.combinedSessionProfitTarget),
      'sameInstrument': _numberController(
        value.maximumAccountsSameInstrument.toDouble(),
      ),
      'combinedRisk': _numberController(value.maximumCombinedRisk),
    };
    _simultaneous = value.simultaneousTradingAllowed;
    _emotionalStop = value.stopAfterEmotionalEvent;
    _oneWinStop = value.stopAfterOneWinningTrade;
    _oneLossStop = value.stopAfterOneLosingTrade;
    _consecutiveLossStop = value.stopAfterConsecutiveLosses;
    _dailyTargetStop = value.stopAfterDailyTarget;
  }

  @override
  void dispose() {
    for (final value in _fields.values) {
      value.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.text('Portfolio safeguards')),
      content: SizedBox(
        width: 620,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _field('daily', 'Maximum total daily loss'),
                _field('risk', 'Maximum portfolio risk per trade'),
                _field(
                  'trades',
                  'Legacy default daily trades for accounts without a limit',
                ),
                _field('losses', 'Maximum consecutive losses'),
                _field('accounts', 'Maximum accounts traded per day'),
                _field('positions', 'Maximum open or planned positions'),
                _field('winCooldown', 'Cooldown after a win (minutes)'),
                _field('lossCooldown', 'Cooldown after a loss (minutes)'),
                _field('resetHour', 'Firm reset hour (0-23)'),
                _field('utcOffset', 'Firm UTC offset (minutes)'),
                _field(
                  'maximumSessionLossR',
                  'Maximum session loss (R, 0 disables)',
                ),
                _field(
                  'profitTarget',
                  'Combined session profit target (0 disables)',
                ),
                _field(
                  'sameInstrument',
                  'Maximum accounts on the same instrument',
                ),
                _field(
                  'combinedRisk',
                  'Maximum combined open risk (0 uses daily limit)',
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _simultaneous,
                  onChanged: (value) => setState(() => _simultaneous = value),
                  title: Text(l10n.text('Allow simultaneous account trading')),
                  subtitle: Text(
                    l10n.text('Combined planned risk warnings still apply.'),
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _emotionalStop,
                  onChanged: (value) => setState(() => _emotionalStop = value),
                  title: Text(
                    l10n.text('Stop the session after an emotional-risk event'),
                  ),
                ),
                _stopSwitch(
                  value: _oneWinStop,
                  onChanged: (value) => setState(() => _oneWinStop = value),
                  title: 'Stop after one winning trade',
                ),
                _stopSwitch(
                  value: _oneLossStop,
                  onChanged: (value) => setState(() => _oneLossStop = value),
                  title: 'Stop after one losing trade',
                ),
                _stopSwitch(
                  value: _consecutiveLossStop,
                  onChanged: (value) =>
                      setState(() => _consecutiveLossStop = value),
                  title: 'Stop after the consecutive-loss limit',
                ),
                _stopSwitch(
                  value: _dailyTargetStop,
                  onChanged: (value) =>
                      setState(() => _dailyTargetStop = value),
                  title: 'Stop after the daily profit target',
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _save,
          child: Text(l10n.text('Save safeguards')),
        ),
      ],
    );
  }

  Widget _field(String key, String label) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextFormField(
      controller: _fields[key],
      keyboardType: const TextInputType.numberWithOptions(
        decimal: true,
        signed: true,
      ),
      decoration: InputDecoration(labelText: context.l10n.text(label)),
      validator: (value) => double.tryParse((value ?? '').trim()) == null
          ? context.l10n.text('Enter a valid number')
          : null,
    ),
  );

  Widget _stopSwitch({
    required bool value,
    required ValueChanged<bool> onChanged,
    required String title,
  }) => SwitchListTile(
    contentPadding: EdgeInsets.zero,
    value: value,
    onChanged: onChanged,
    title: Text(context.l10n.text(title)),
  );

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    double value(String key) => double.parse(_fields[key]!.text.trim());
    final updated = widget.policy.copyWith(
      maximumDailyLoss: value('daily'),
      maximumRiskPerTrade: value('risk'),
      maximumTradesPerDay: value('trades').round(),
      maximumConsecutiveLosses: value('losses').round(),
      maximumAccountsTradedPerDay: value('accounts').round(),
      maximumOpenPositions: value('positions').round(),
      cooldownAfterWinMinutes: value('winCooldown').round(),
      cooldownAfterLossMinutes: value('lossCooldown').round(),
      simultaneousTradingAllowed: _simultaneous,
      stopAfterEmotionalEvent: _emotionalStop,
      stopAfterOneWinningTrade: _oneWinStop,
      stopAfterOneLosingTrade: _oneLossStop,
      stopAfterConsecutiveLosses: _consecutiveLossStop,
      stopAfterMaximumTrades: widget.policy.stopAfterMaximumTrades,
      stopAfterDailyTarget: _dailyTargetStop,
      maximumSessionLossR: value('maximumSessionLossR'),
      combinedSessionProfitTarget: value('profitTarget'),
      maximumAccountsSameInstrument: value('sameInstrument').round(),
      maximumCombinedRisk: value('combinedRisk'),
      resetHourUtc: value('resetHour').round(),
      resetUtcOffsetMinutes: value('utcOffset').round(),
    );
    Navigator.pop(context, updated);
  }
}

Future<PlannedTrade?> showPreTradeAssistant(
  BuildContext context, {
  required List<PropFirmAccount> accounts,
  required String? recommendedAccountId,
  DateTime Function()? now,
}) => Navigator.of(context).push<PlannedTrade>(
  MaterialPageRoute(
    fullscreenDialog: true,
    builder: (_) => _PreTradeAssistant(
      accounts: accounts,
      recommendedAccountId: recommendedAccountId,
      now: now ?? DateTime.now,
    ),
  ),
);

class _PreTradeAssistant extends StatefulWidget {
  const _PreTradeAssistant({
    required this.accounts,
    required this.recommendedAccountId,
    required this.now,
  });

  final List<PropFirmAccount> accounts;
  final String? recommendedAccountId;
  final DateTime Function() now;

  @override
  State<_PreTradeAssistant> createState() => _PreTradeAssistantState();
}

class _PreTradeAssistantState extends State<_PreTradeAssistant> {
  final _formKey = GlobalKey<FormState>();
  final _calculations = const PropFirmCalculationService();
  late String _accountId;
  var _direction = TradeDirection.buy;
  final _instrument = TextEditingController();
  final _entry = TextEditingController();
  final _stop = TextEditingController();
  final _target = TextEditingController();
  final _risk = TextEditingController();
  final _riskPercent = TextEditingController(text: '1');
  final _strategy = TextEditingController();
  final _quality = TextEditingController(text: 'A');
  final _reason = TextEditingController();
  final _invalidation = TextEditingController();
  final _session = TextEditingController(text: 'London');
  final _screenshot = TextEditingController();
  final _override = TextEditingController();
  var _calm = true;
  var _recovering = false;
  var _frustrated = false;
  var _fomo = false;
  var _duplicate = false;
  var _followsPlan = true;
  var _acceptsLoss = true;
  var _stopReached = false;

  bool get _highRisk =>
      !_calm ||
      _recovering ||
      _frustrated ||
      _fomo ||
      !_acceptsLoss ||
      _stopReached;

  @override
  void initState() {
    super.initState();
    _accountId = widget.recommendedAccountId ?? widget.accounts.first.id;
    _risk.text = _number(
      widget.accounts
          .firstWhere((account) => account.id == _accountId)
          .maxRiskPerTrade,
    );
  }

  @override
  void dispose() {
    for (final controller in [
      _instrument,
      _entry,
      _stop,
      _target,
      _risk,
      _riskPercent,
      _strategy,
      _quality,
      _reason,
      _invalidation,
      _session,
      _screenshot,
      _override,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.text('Pre-trade decision assistant'))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
          children: [
            _notice(
              context,
              Icons.rule_outlined,
              l10n.text(
                'This checklist provides guidance according to your rules. It does not approve or execute a trade.',
              ),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _accountId,
              decoration: InputDecoration(
                labelText: l10n.text('Selected account'),
              ),
              items: [
                for (final account in widget.accounts)
                  DropdownMenuItem(
                    value: account.id,
                    child: Text(account.name),
                  ),
              ],
              onChanged: (value) => setState(() => _accountId = value!),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _instrument,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(labelText: l10n.text('Instrument')),
              validator: _required,
            ),
            const SizedBox(height: 12),
            SegmentedButton<TradeDirection>(
              segments: [
                ButtonSegment(
                  value: TradeDirection.buy,
                  label: Text(l10n.text('Buy')),
                  icon: const Icon(Icons.north_east),
                ),
                ButtonSegment(
                  value: TradeDirection.sell,
                  label: Text(l10n.text('Sell')),
                  icon: const Icon(Icons.south_east),
                ),
              ],
              selected: {_direction},
              onSelectionChanged: (value) =>
                  setState(() => _direction = value.first),
            ),
            const SizedBox(height: 12),
            _twoFields(
              _numberField(_entry, 'Planned entry'),
              _numberField(_stop, 'Stop-loss price'),
            ),
            const SizedBox(height: 12),
            _twoFields(
              _numberField(_target, 'Take-profit price'),
              _numberField(_risk, 'Planned risk amount'),
            ),
            const SizedBox(height: 12),
            _twoFields(
              _numberField(_riskPercent, 'Planned risk percentage'),
              TextFormField(
                controller: _quality,
                decoration: InputDecoration(
                  labelText: l10n.text('Setup quality'),
                ),
                validator: _required,
              ),
            ),
            const SizedBox(height: 12),
            _twoFields(
              TextFormField(
                controller: _strategy,
                decoration: InputDecoration(
                  labelText: l10n.text('Strategy used'),
                ),
                validator: _required,
              ),
              TextFormField(
                controller: _session,
                decoration: InputDecoration(labelText: l10n.text('Session')),
                validator: _required,
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reason,
              maxLines: 2,
              decoration: InputDecoration(labelText: l10n.text('Trade reason')),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _invalidation,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: l10n.text('Invalidation reason'),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _screenshot,
              decoration: InputDecoration(
                labelText: l10n.text('Screenshot reference (optional)'),
              ),
            ),
            const SizedBox(height: 22),
            Text(
              l10n.text('Psychology checklist'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            _check('I am calm.', _calm, (value) => _calm = value),
            _check(
              'I am trying to recover a recent loss.',
              _recovering,
              (value) => _recovering = value,
            ),
            _check(
              'I am increasing risk because of frustration.',
              _frustrated,
              (value) => _frustrated = value,
            ),
            _check(
              'I am entering because of FOMO.',
              _fomo,
              (value) => _fomo = value,
            ),
            _check(
              'This idea is already planned on another account.',
              _duplicate,
              (value) => _duplicate = value,
            ),
            _check(
              'This trade follows my plan.',
              _followsPlan,
              (value) => _followsPlan = value,
            ),
            _check(
              'I can accept the planned loss.',
              _acceptsLoss,
              (value) => _acceptsLoss = value,
            ),
            _check(
              'I have reached today\'s stop condition.',
              _stopReached,
              (value) => _stopReached = value,
            ),
            if (_highRisk) ...[
              const SizedBox(height: 12),
              _notice(
                context,
                Icons.pan_tool_alt_outlined,
                l10n.text(
                  'High emotional risk detected. Consider pausing all accounts. Continuing requires a recorded override reason.',
                ),
                warning: true,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _override,
                maxLines: 3,
                decoration: InputDecoration(
                  labelText: l10n.text('Override reason'),
                ),
                validator: (value) =>
                    _highRisk && (value ?? '').trim().length < 5
                    ? l10n.text('Provide a clear override reason.')
                    : null,
              ),
            ],
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _save,
              icon: const Icon(Icons.fact_check_outlined),
              label: Text(l10n.text('Record planned trade')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _check(String label, bool value, ValueChanged<bool> setter) =>
      CheckboxListTile(
        contentPadding: EdgeInsets.zero,
        value: value,
        onChanged: (next) => setState(() => setter(next ?? false)),
        title: Text(context.l10n.text(label)),
        controlAffinity: ListTileControlAffinity.leading,
      );

  Widget _numberField(TextEditingController controller, String label) =>
      TextFormField(
        controller: controller,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(labelText: context.l10n.text(label)),
        validator: (value) {
          final number = double.tryParse((value ?? '').trim());
          return number == null || !number.isFinite || number <= 0
              ? context.l10n.text('Enter a valid number')
              : null;
        },
      );

  Widget _twoFields(Widget first, Widget second) => LayoutBuilder(
    builder: (context, constraints) => constraints.maxWidth < 620
        ? Column(children: [first, const SizedBox(height: 12), second])
        : Row(
            children: [
              Expanded(child: first),
              const SizedBox(width: 12),
              Expanded(child: second),
            ],
          ),
  );

  String? _required(String? value) => (value ?? '').trim().isEmpty
      ? context.l10n.text('This field is required')
      : null;

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    try {
      final entry = double.parse(_entry.text.trim());
      final stop = double.parse(_stop.text.trim());
      final target = double.parse(_target.text.trim());
      final ratio = _calculations.rewardRiskRatio(
        direction: _direction,
        entryPrice: entry,
        stopLossPrice: stop,
        takeProfitPrice: target,
      );
      final risk = double.parse(_risk.text.trim());
      Navigator.pop(
        context,
        PlannedTrade(
          id: 'plan-${widget.now().microsecondsSinceEpoch}',
          accountId: _accountId,
          instrument: _instrument.text.trim().toUpperCase(),
          direction: _direction,
          entryPrice: entry,
          stopLossPrice: stop,
          takeProfitPrice: target,
          riskAmount: risk,
          riskPercentage: double.parse(_riskPercent.text.trim()),
          expectedReward: risk * ratio,
          rewardRiskRatio: ratio,
          strategy: _strategy.text.trim(),
          setupQuality: _quality.text.trim(),
          tradeReason: _reason.text.trim(),
          invalidationReason: _invalidation.text.trim(),
          session: _session.text.trim(),
          screenshotPath: _screenshot.text.trim(),
          overrideReason: _override.text.trim(),
          psychology: PsychologyCheck(
            isCalm: _calm,
            recoveringRecentLoss: _recovering,
            increasingRiskFromFrustration: _frustrated,
            fomo: _fomo,
            duplicateIdea: _duplicate,
            followsPlan: _followsPlan,
            acceptsLoss: _acceptsLoss,
            stopConditionReached: _stopReached,
          ),
          createdAt: widget.now(),
        ),
      );
    } on FormatException catch (error) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.text(error.message))));
    }
  }
}

Future<RecordedTradeResult?> showResultRecorder(
  BuildContext context, {
  required List<PropFirmAccount> accounts,
  required List<PlannedTrade> openPlans,
  required String? defaultAccountId,
  DateTime Function()? now,
}) => showDialog<RecordedTradeResult>(
  context: context,
  builder: (_) => _ResultRecorder(
    accounts: accounts,
    openPlans: openPlans,
    defaultAccountId: defaultAccountId,
    now: now ?? DateTime.now,
  ),
);

class _ResultRecorder extends StatefulWidget {
  const _ResultRecorder({
    required this.accounts,
    required this.openPlans,
    required this.defaultAccountId,
    required this.now,
  });

  final List<PropFirmAccount> accounts;
  final List<PlannedTrade> openPlans;
  final String? defaultAccountId;
  final DateTime Function() now;

  @override
  State<_ResultRecorder> createState() => _ResultRecorderState();
}

class _ResultRecorderState extends State<_ResultRecorder> {
  final _formKey = GlobalKey<FormState>();
  late String _accountId;
  var _type = TradeResultType.fullTp;
  final _amount = TextEditingController(text: '200');
  final _r = TextEditingController(text: '2');
  final _mistake = TextEditingController();
  final _emotion = TextEditingController(text: 'Calm');
  final _notes = TextEditingController();
  final _screenshot = TextEditingController();
  var _followedPlan = true;
  var _followedRotation = true;

  @override
  void initState() {
    super.initState();
    _accountId = widget.defaultAccountId ?? widget.accounts.first.id;
  }

  @override
  void dispose() {
    for (final value in [
      _amount,
      _r,
      _mistake,
      _emotion,
      _notes,
      _screenshot,
    ]) {
      value.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.text('Record result')),
      content: SizedBox(
        width: 560,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (widget.openPlans.isEmpty)
                  _notice(
                    context,
                    Icons.info_outline,
                    l10n.text(
                      'No open planned trade was found. This manual result will still be recorded.',
                    ),
                  ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: _accountId,
                  decoration: InputDecoration(labelText: l10n.text('Account')),
                  items: [
                    for (final account in widget.accounts)
                      DropdownMenuItem(
                        value: account.id,
                        child: Text(account.name),
                      ),
                  ],
                  onChanged: (value) => setState(() => _accountId = value!),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<TradeResultType>(
                  initialValue: _type,
                  decoration: InputDecoration(
                    labelText: l10n.text('Result type'),
                  ),
                  items: [
                    for (final value in TradeResultType.values)
                      DropdownMenuItem(
                        value: value,
                        child: Text(l10n.text(resultTypeLabel(value))),
                      ),
                  ],
                  onChanged: (value) => setState(() {
                    _type = value ?? _type;
                    if (_type == TradeResultType.fullSl ||
                        _type == TradeResultType.partialSl) {
                      _amount.text = '-${_amount.text.replaceAll('-', '')}';
                      _r.text = '-${_r.text.replaceAll('-', '')}';
                    } else if (_type == TradeResultType.breakeven ||
                        _type == TradeResultType.cancelled ||
                        _type == TradeResultType.notTriggered ||
                        _type == TradeResultType.missedTrade) {
                      _amount.text = '0';
                      _r.text = '0';
                    }
                  }),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _amount,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: InputDecoration(
                    labelText: l10n.text('Profit or loss amount'),
                  ),
                  validator: _numberValidator,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _r,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: InputDecoration(
                    labelText: l10n.text('R-multiple'),
                  ),
                  validator: _numberValidator,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _mistake,
                  decoration: InputDecoration(
                    labelText: l10n.text('Mistake category'),
                  ),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _emotion,
                  decoration: InputDecoration(
                    labelText: l10n.text('Emotional state'),
                  ),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _followedPlan,
                  onChanged: (value) =>
                      setState(() => _followedPlan = value ?? false),
                  title: Text(l10n.text('Trading plan was followed')),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _followedRotation,
                  onChanged: (value) =>
                      setState(() => _followedRotation = value ?? false),
                  title: Text(l10n.text('Account rotation was followed')),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                TextFormField(
                  controller: _notes,
                  maxLines: 3,
                  decoration: InputDecoration(labelText: l10n.text('Notes')),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _screenshot,
                  decoration: InputDecoration(
                    labelText: l10n.text('Screenshot reference (optional)'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(l10n.text('Cancel')),
        ),
        FilledButton(onPressed: _save, child: Text(l10n.text('Save result'))),
      ],
    );
  }

  String? _numberValidator(String? value) =>
      double.tryParse((value ?? '').trim()) == null
      ? context.l10n.text('Enter a valid number')
      : null;

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    final openPlan = widget.openPlans
        .where((plan) => plan.accountId == _accountId)
        .firstOrNull;
    Navigator.pop(
      context,
      RecordedTradeResult(
        id: 'result-${widget.now().microsecondsSinceEpoch}',
        accountId: _accountId,
        plannedTradeId: openPlan?.id,
        type: _type,
        amount: double.parse(_amount.text.trim()),
        rMultiple: double.parse(_r.text.trim()),
        resultTime: widget.now(),
        mistakeCategory: _mistake.text.trim(),
        emotionalState: _emotion.text.trim(),
        followedPlan: _followedPlan,
        followedRotation: _followedRotation,
        notes: _notes.text.trim(),
        screenshotPath: _screenshot.text.trim(),
      ),
    );
  }
}

String accountStatusLabel(AccountStatus value) => switch (value) {
  AccountStatus.available => 'Healthy',
  AccountStatus.recommended => 'Recommended',
  AccountStatus.active => 'Active',
  AccountStatus.coolingAfterWin => 'Cooling after win',
  AccountStatus.coolingAfterLoss => 'Cooling after loss',
  AccountStatus.dailyLocked => 'Daily locked',
  AccountStatus.portfolioLocked => 'Portfolio stopped',
  AccountStatus.recovery => 'Recovery',
  AccountStatus.payoutProtection => 'Protected',
  AccountStatus.payoutReady => 'Payout ready',
  AccountStatus.payoutRequested => 'Payout requested',
  AccountStatus.paused => 'Paused',
  AccountStatus.breached => 'Breached',
  AccountStatus.archived => 'Archived',
};

String resultTypeLabel(TradeResultType value) => switch (value) {
  TradeResultType.fullTp => 'Full TP',
  TradeResultType.fullSl => 'Full SL',
  TradeResultType.breakeven => 'Breakeven',
  TradeResultType.partialTp => 'Partial TP',
  TradeResultType.partialSl => 'Partial SL',
  TradeResultType.closedManually => 'Closed manually',
  TradeResultType.cancelled => 'Cancelled',
  TradeResultType.notTriggered => 'Not triggered',
  TradeResultType.missedTrade => 'Missed trade',
  TradeResultType.manualAdjustment => 'Manual adjustment',
};

String _drawdownLabel(DrawdownType value) => switch (value) {
  DrawdownType.static => 'Static drawdown',
  DrawdownType.balanceBased => 'Balance-based drawdown',
  DrawdownType.equityBased => 'Equity-based drawdown',
  DrawdownType.trailing => 'Trailing drawdown',
  DrawdownType.endOfDayTrailing => 'End-of-day trailing drawdown',
  DrawdownType.custom => 'Custom drawdown',
};

String _priorityLabel(AccountPriority value) => switch (value) {
  AccountPriority.low => 'Low priority',
  AccountPriority.standard => 'Standard priority',
  AccountPriority.high => 'High priority',
};

TextEditingController _numberController(double value) =>
    TextEditingController(text: _number(value));

String _number(double value) => value == value.roundToDouble()
    ? value.toStringAsFixed(0)
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

Widget _notice(
  BuildContext context,
  IconData icon,
  String text, {
  bool warning = false,
}) {
  final scheme = Theme.of(context).colorScheme;
  final color = warning ? scheme.error : scheme.primary;
  return DecoratedBox(
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      border: Border.all(color: color.withValues(alpha: .35)),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ],
      ),
    ),
  );
}
