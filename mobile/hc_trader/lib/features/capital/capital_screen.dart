import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trading_data_source.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_analytics.dart';
import '../../services/trading_data_source_service.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';
import '../../shared/trading_data_builder.dart';

class CapitalScreen extends StatelessWidget {
  const CapitalScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return TradingDataBuilder(
      user: user,
      repository: repository,
      builder: (context, journals, capitals, pairs, mode) {
        final summary = TradingAnalytics().summarize(
          journals: journals,
          capitals: capitals,
        );

        if (mode == TradingDataDisplayMode.mt5Only) {
          return StreamBuilder<Mt5Account?>(
            stream: repository.mainMt5AccountFor(user.uid),
            builder: (context, snapshot) {
              if (!snapshot.hasData &&
                  snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              final account = snapshot.data;
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 96),
                children: [
                  _CapitalHeader(
                    balance: mt5AccountCapitalBalance(account),
                    deposits: 0,
                    withdrawals: 0,
                    currency: account?.currency ?? '',
                  ),
                  const SizedBox(height: 14),
                  const SizedBox(
                    height: 360,
                    child: EmptyState(
                      icon: Icons.account_balance_wallet_outlined,
                      title: 'MT5 capital is provided by synchronization.',
                    ),
                  ),
                ],
              );
            },
          );
        }

        return Stack(
          children: [
            ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 96),
              children: [
                _CapitalHeader(
                  balance: summary.currentBalance,
                  deposits: summary.totalDeposits,
                  withdrawals: summary.totalWithdrawals,
                  currency: '',
                ),
                const SizedBox(height: 14),
                if (capitals.isEmpty)
                  SizedBox(
                    height: 360,
                    child: EmptyState(
                      icon: Icons.account_balance_wallet_outlined,
                      title: 'No capital records',
                      action: FilledButton.icon(
                        onPressed: () => _openCapitalDialog(context),
                        icon: const Icon(Icons.add),
                        label: const Text('Add Capital'),
                      ),
                    ),
                  )
                else
                  ...capitals.map(
                    (capital) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _CapitalCard(capital: capital),
                    ),
                  ),
              ],
            ),
            Positioned(
              right: 16,
              bottom: 16,
              child: FloatingActionButton.extended(
                onPressed: () => _openCapitalDialog(context),
                icon: const Icon(Icons.add),
                label: const Text('Capital'),
              ),
            ),
          ],
        );
      },
    );
  }

  Future<void> _openCapitalDialog(BuildContext context) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _CapitalDialog(user: user, repository: repository),
    );
    if (saved == true && context.mounted) {
      AppFeedback.success(context, 'Capital transaction saved.');
    }
  }
}

class _CapitalHeader extends StatelessWidget {
  const _CapitalHeader({
    required this.balance,
    required this.deposits,
    required this.withdrawals,
    required this.currency,
  });

  final double balance;
  final double deposits;
  final double withdrawals;
  final String currency;

  @override
  Widget build(BuildContext context) {
    String money(double value) => formatCurrencyMoney(value, currency);

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Account Capital',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              money(balance),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _CapitalFigure(
                    label: 'Deposits',
                    value: money(deposits),
                    color: const Color(0xFF17855B),
                  ),
                ),
                Expanded(
                  child: _CapitalFigure(
                    label: 'Withdrawals',
                    value: money(withdrawals),
                    color: const Color(0xFFC23B36),
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

class _CapitalCard extends StatelessWidget {
  const _CapitalCard({required this.capital});

  final CapitalTransaction capital;

  @override
  Widget build(BuildContext context) {
    final color = capital.isDeposit
        ? const Color(0xFF17855B)
        : const Color(0xFFC23B36);

    return Card(
      elevation: 0,
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: .12),
          child: Icon(
            capital.isDeposit ? Icons.south_west : Icons.north_east,
            color: color,
          ),
        ),
        title: Text(capital.typeLabel),
        subtitle: Text(
          [
            formatDate(capital.depositDate),
            if (capital.notes.trim().isNotEmpty) capital.notes.trim(),
          ].join('  |  '),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: Text(
          formatMoney(capital.amount),
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: color,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _CapitalFigure extends StatelessWidget {
  const _CapitalFigure({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: color,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _CapitalDialog extends StatefulWidget {
  const _CapitalDialog({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_CapitalDialog> createState() => _CapitalDialogState();
}

class _CapitalDialogState extends State<_CapitalDialog> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _notesController = TextEditingController();
  var _type = 1;
  var _date = DateTime.now();
  var _saving = false;

  @override
  void dispose() {
    _amountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Capital'),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SegmentedButton<int>(
                segments: const [
                  ButtonSegment(
                    value: 1,
                    icon: Icon(Icons.south_west),
                    label: Text('Deposit'),
                  ),
                  ButtonSegment(
                    value: 2,
                    icon: Icon(Icons.north_east),
                    label: Text('Withdraw'),
                  ),
                ],
                selected: {_type},
                onSelectionChanged: (selection) {
                  setState(() => _type = selection.first);
                },
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Amount',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
                validator: (value) {
                  final amount = double.tryParse(value ?? '');
                  return amount != null && amount > 0 ? null : 'Enter amount';
                },
              ),
              const SizedBox(height: 14),
              InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: _pickDate,
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Date',
                    prefixIcon: Icon(Icons.event_outlined),
                  ),
                  child: Text(formatDate(_date)),
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _notesController,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Notes',
                  alignLabelWithHint: true,
                  prefixIcon: Icon(Icons.notes_outlined),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.save_outlined),
          label: const Text('Save'),
        ),
      ],
    );
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );
    if (selected != null) {
      setState(() => _date = selected);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _saving = true);
    try {
      final amount = double.parse(_amountController.text).abs();
      await widget.repository.addCapitalTransaction(
        CapitalTransaction(
          id: '',
          userId: widget.user.uid,
          depositDate: _date,
          amount: _type == 2 ? -amount : amount,
          type: _type,
          notes: _notesController.text.trim(),
        ),
      );

      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }
}
