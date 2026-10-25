import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trading_pair.dart';
import '../../repositories/trading_repository.dart';
import '../../services/risk_calculator_service.dart';
import '../../services/trading_data_source_service.dart';
import '../../shared/app_feedback.dart';
import '../../shared/app_back_button.dart';
import '../../shared/formatters.dart';

class RiskCalculatorScreen extends StatefulWidget {
  const RiskCalculatorScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<RiskCalculatorScreen> createState() => _RiskCalculatorScreenState();
}

class _RiskCalculatorScreenState extends State<RiskCalculatorScreen> {
  final _formKey = GlobalKey<FormState>();
  final _service = const RiskCalculatorService();
  final _balance = TextEditingController(text: '10000');
  final _risk = TextEditingController(text: '0.5');
  final _riskAmount = TextEditingController();
  final _entry = TextEditingController();
  final _stop = TextEditingController();
  final _take = TextEditingController();
  final _pipValue = TextEditingController(text: '10');
  final _contractSize = TextEditingController(text: '100000');
  final _minLot = TextEditingController(text: '0.01');
  final _maxLot = TextEditingController(text: '100');
  final _currency = TextEditingController(text: 'USD');
  StreamSubscription<Mt5Account?>? _mainAccountSubscription;
  TradingPair? _pair;
  String? _savedSymbol;
  var _direction = 'buy';
  RiskCalculation? _result;
  var _applyingAccountDefault = false;
  var _userEditedBalance = false;

  @override
  void initState() {
    super.initState();
    final saved = widget.storage.readMap(
      widget.user.uid,
      'last_risk_calculation',
    );
    if (saved == null) return;
    _savedSymbol = saved['symbol']?.toString();
    _balance.text = (saved['balance'] ?? 10000).toString();
    _risk.text = (saved['risk_percent'] ?? .5).toString();
    _riskAmount.text = (saved['risk_amount_input'] ?? '').toString();
    _entry.text = (saved['entry'] ?? '').toString();
    _stop.text = (saved['stop_loss'] ?? '').toString();
    _take.text = (saved['take_profit'] ?? '').toString();
    _pipValue.text = (saved['pip_value'] ?? 10).toString();
    _contractSize.text = (saved['contract_size'] ?? 100000).toString();
    _minLot.text = (saved['min_lot'] ?? .01).toString();
    _maxLot.text = (saved['max_lot'] ?? 100).toString();
    _currency.text = (saved['currency'] ?? 'USD').toString();
    _direction = (saved['direction'] ?? 'buy').toString().toLowerCase();
    if (_direction != 'buy' && _direction != 'sell') {
      _direction = 'buy';
    }
    if (saved['risk_amount'] is num) {
      _result = RiskCalculation(
        riskAmount: (saved['risk_amount'] as num).toDouble(),
        stopDistance: (saved['stop_distance'] as num?)?.toDouble() ?? 0,
        pipDistance: (saved['pip_distance'] as num?)?.toDouble() ?? 0,
        takeProfitDistance:
            (saved['take_profit_distance'] as num?)?.toDouble() ?? 0,
        lotSize: (saved['lot_size'] as num?)?.toDouble() ?? 0,
        positionSize: (saved['position_size'] as num?)?.toDouble() ?? 0,
        riskReward: (saved['risk_reward'] as num?)?.toDouble() ?? 0,
        projectedProfit: (saved['projected_profit'] as num?)?.toDouble() ?? 0,
        projectedLoss: (saved['projected_loss'] as num?)?.toDouble() ?? 0,
        direction: _direction,
        symbol: _savedSymbol ?? '',
        accountCurrency: _currency.text,
      );
    }
    _balance.addListener(() {
      if (!_applyingAccountDefault) _userEditedBalance = true;
    });
    _mainAccountSubscription = widget.repository
        .mainMt5AccountFor(widget.user.uid)
        .listen(_applyMainAccountDefault, onError: (_) {});
  }

  @override
  void dispose() {
    _mainAccountSubscription?.cancel();
    for (final controller in [
      _balance,
      _risk,
      _riskAmount,
      _entry,
      _stop,
      _take,
      _pipValue,
      _contractSize,
      _minLot,
      _maxLot,
      _currency,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _applyMainAccountDefault(Mt5Account? account) {
    if (!mounted || account == null || _userEditedBalance) return;
    final balance = mt5AccountCapitalBalance(account);
    if (balance <= 0) return;
    _applyingAccountDefault = true;
    _balance.text = balance.toStringAsFixed(2);
    if (account.currency.trim().isNotEmpty) {
      _currency.text = account.currency.trim().toUpperCase();
    }
    _applyingAccountDefault = false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Risk Calculator')),
      ),
      body: StreamBuilder<List<TradingPair>>(
        stream: widget.repository.tradingPairsStream(),
        builder: (context, snapshot) {
          final pairs = snapshot.data?.isNotEmpty == true
              ? snapshot.data!
              : TradingPair.defaults;
          if (_pair == null) {
            _pair = pairs.first;
            for (final pair in pairs) {
              if (pair.symbol.toUpperCase() == _savedSymbol?.toUpperCase()) {
                _pair = pair;
                break;
              }
            }
          }
          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                DropdownButtonFormField<TradingPair>(
                  initialValue: pairs.any((item) => item.id == _pair?.id)
                      ? _pair
                      : pairs.first,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Symbol'),
                    prefixIcon: const Icon(Icons.candlestick_chart_outlined),
                  ),
                  items: [
                    for (final pair in pairs)
                      DropdownMenuItem(
                        value: pair,
                        child: Text(pair.displayName),
                      ),
                  ],
                  onChanged: (value) => setState(() => _pair = value),
                ),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'buy',
                      icon: const Icon(Icons.trending_up),
                      label: Text(context.l10n.text('Buy')),
                    ),
                    ButtonSegment(
                      value: 'sell',
                      icon: const Icon(Icons.trending_down),
                      label: Text(context.l10n.text('Sell')),
                    ),
                  ],
                  selected: {_direction},
                  onSelectionChanged: (selection) =>
                      setState(() => _direction = selection.first),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _number(
                        _balance,
                        context.l10n.text('Account balance'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _number(_risk, context.l10n.text('Risk %')),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _number(
                  _riskAmount,
                  context.l10n.text('Risk amount (optional)'),
                  optional: true,
                  helperText: context.l10n.text(
                    'Leave empty to calculate from balance and risk percent.',
                  ),
                ),
                const SizedBox(height: 12),
                _number(_entry, context.l10n.text('Entry price')),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _number(_stop, context.l10n.text('Stop loss')),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _number(_take, context.l10n.text('Take profit')),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _number(
                        _pipValue,
                        context.l10n.text('Pip value per standard lot'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _number(
                        _contractSize,
                        context.l10n.text('Contract size'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _number(_minLot, context.l10n.text('Minimum lot')),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _number(_maxLot, context.l10n.text('Maximum lot')),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _currency,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Account currency'),
                  ),
                  validator: (value) => (value ?? '').trim().isEmpty
                      ? context.l10n.text('Required')
                      : null,
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _calculate,
                  icon: const Icon(Icons.calculate),
                  label: Text(context.l10n.text('Calculate position')),
                ),
                if (_result != null) ...[
                  const SizedBox(height: 18),
                  _ResultCard(result: _result!),
                ],
                const SizedBox(height: 14),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text(
                      context.l10n.text(
                        'Confirm contract size, pip value, and lot limits with your broker before placing an order.',
                      ),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  TextFormField _number(
    TextEditingController controller,
    String label, {
    bool optional = false,
    String? helperText,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: label, helperText: helperText),
      validator: (value) {
        if (optional && (value ?? '').trim().isEmpty) return null;
        final number = double.tryParse((value ?? '').trim());
        return number != null && number > 0
            ? null
            : context.l10n.text('Required');
      },
    );
  }

  Future<void> _calculate() async {
    if (!_formKey.currentState!.validate() || _pair == null) return;
    try {
      final result = _service.calculate(
        accountBalance: double.parse(_balance.text.trim()),
        riskPercent: double.parse(_risk.text.trim()),
        riskAmount: _optionalDouble(_riskAmount.text),
        entryPrice: double.parse(_entry.text.trim()),
        stopLoss: double.parse(_stop.text.trim()),
        takeProfit: double.parse(_take.text.trim()),
        pipSize: _pair!.pipFactor,
        pipValuePerStandardLot: double.parse(_pipValue.text.trim()),
        contractSize: double.parse(_contractSize.text.trim()),
        minLotSize: double.parse(_minLot.text.trim()),
        maxLotSize: double.parse(_maxLot.text.trim()),
        direction: _direction,
        symbol: _pair!.symbol,
        accountCurrency: _currency.text.trim().toUpperCase(),
      );
      setState(() => _result = result);
      await widget.storage.writeMap(widget.user.uid, 'last_risk_calculation', {
        'symbol': _pair!.symbol,
        'balance': double.parse(_balance.text.trim()),
        'risk_percent': double.parse(_risk.text.trim()),
        'risk_amount_input': _riskAmount.text.trim(),
        'entry': double.parse(_entry.text.trim()),
        'stop_loss': double.parse(_stop.text.trim()),
        'take_profit': double.parse(_take.text.trim()),
        'pip_value': double.parse(_pipValue.text.trim()),
        'contract_size': double.parse(_contractSize.text.trim()),
        'min_lot': double.parse(_minLot.text.trim()),
        'max_lot': double.parse(_maxLot.text.trim()),
        'currency': _currency.text.trim().toUpperCase(),
        'direction': _direction,
        'risk_amount': result.riskAmount,
        'stop_distance': result.stopDistance,
        'pip_distance': result.pipDistance,
        'take_profit_distance': result.takeProfitDistance,
        'lot_size': result.lotSize,
        'position_size': result.positionSize,
        'risk_reward': result.riskReward,
        'projected_profit': result.projectedProfit,
        'projected_loss': result.projectedLoss,
      });
    } on FormatException catch (error) {
      if (mounted) {
        AppFeedback.error(context, error.message);
      }
    }
  }
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.result});

  final RiskCalculation result;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Position Preview'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            _row(
              context,
              'Direction',
              context.l10n.text(result.direction == 'sell' ? 'Sell' : 'Buy'),
            ),
            if (result.symbol.isNotEmpty)
              _row(context, 'Symbol', result.symbol),
            _row(context, 'Risk amount', formatMoney(result.riskAmount)),
            _row(
              context,
              'Pip distance',
              result.pipDistance.toStringAsFixed(2),
            ),
            _row(
              context,
              'Take profit distance',
              result.takeProfitDistance.toStringAsFixed(2),
            ),
            _row(context, 'Lot size', result.lotSize.toStringAsFixed(4)),
            _row(
              context,
              'Position size',
              result.positionSize.toStringAsFixed(2),
            ),
            _row(
              context,
              'Risk / reward',
              '1 : ${result.riskReward.toStringAsFixed(2)}',
            ),
            _row(context, 'Projected loss', formatMoney(result.projectedLoss)),
            _row(
              context,
              'Projected profit',
              formatMoney(result.projectedProfit),
            ),
            if (result.warnings.isNotEmpty) ...[
              const SizedBox(height: 12),
              for (final warning in result.warnings)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.warning_amber_outlined,
                        size: 18,
                        color: Theme.of(context).colorScheme.error,
                      ),
                      const SizedBox(width: 8),
                      Expanded(child: Text(context.l10n.text(warning))),
                    ],
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _row(BuildContext context, String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(child: Text(context.l10n.text(label))),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
      ],
    ),
  );
}

double? _optionalDouble(String value) {
  final text = value.trim();
  if (text.isEmpty) return null;
  return double.tryParse(text);
}
